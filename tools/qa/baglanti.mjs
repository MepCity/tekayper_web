/* Bağlantı taraması (spec 6.3): iç bağlantılar, yönlendirme kökleri, PDF #page, tel/mailto, konsol.
 *   node baglanti.mjs [sayfa ...]
 * Denetler: iç bağlantılar 200 (yönlendirmeler izlenir), '#' ya da boş href yok, 6 eski adres 301 ve doğru Location,
 * tel: +90 ile 12 hane, mailto: geçerli adres, PDF bağlantıları var ve #page=N biçiminde,
 * her sayfada konsol hatası ve başarısız istek yok (eksik üretilmiş görseller 404 isteği yaratmamalı).
 */
import path from 'node:path';
import { tarayici, baglam, izle, url, TABAN, klasor, yazJson, sunucuKontrol, SAYFALAR, konumsal, bekle } from './ortak.mjs';

await sunucuKontrol();
const baslangic = konumsal().length ? konumsal() : SAYFALAR.filter((s) => s !== '404.php');
const YONLENDIRME = {
  'teknokol.php': '/urunler.php#armendus',
  'kol-sistemleri.php': '/urunler.php#armendus',
  'pano-sistemleri.php': '/urunler.php#armendus',
  'kiosk-sistemleri.php': '/urunler.php#armendus',
  'baglanti-kuleleri.php': '/urunler.php#armendus',
  'iletisimSmtp.php': '/iletisim.php',
};
const sorunlar = [];
const kontrolEdilen = new Map();

async function durum(u) {
  if (kontrolEdilen.has(u)) return kontrolEdilen.get(u);
  const r = await fetch(u, { redirect: 'follow' }).then((x) => x.status).catch((e) => String(e));
  kontrolEdilen.set(u, r);
  return r;
}

// 1. Yönlendirme kökleri
for (const [eski, hedef] of Object.entries(YONLENDIRME)) {
  const r = await fetch(url(eski), { redirect: 'manual' });
  const loc = r.headers.get('location') || '';
  const ok = r.status === 301 && loc.endsWith(hedef);
  if (!ok) sorunlar.push(`yönlendirme ${eski}: ${r.status} ${loc} (beklenen 301 ${hedef})`);
  console.log(`${ok ? 'tamam' : 'SORUN'}  ${eski} -> ${r.status} ${loc}`);
}
{
  const r = await fetch(url('olmayan-sayfa-qa.php'), { redirect: 'manual' });
  if (r.status !== 404) sorunlar.push(`olmayan sayfa ${r.status} (beklenen 404; router.php ile mi çalışıyor?)`);
}

// 2. Sayfa sayfa bağlantılar
const b = await tarayici();
const ctx = await baglam(b, 'masaustu');
const sayfaRapor = {};
for (const s of baslangic) {
  const p = await ctx.newPage();
  const k = izle(p);
  await p.goto(url(s), { waitUntil: 'load' });
  await bekle(800);
  const linkler = await p.$$eval('a', (as) => as.map((a) => ({ href: a.getAttribute('href'), tam: a.href, metin: (a.innerText || a.getAttribute('aria-label') || '').trim().slice(0, 50), hedef: a.target })));
  const yerel = [];
  for (const l of linkler) {
    const h = (l.href || '').trim();
    if (!h || h === '#') { sorunlar.push(`${s}: boş ya da # href ("${l.metin}")`); continue; }
    if (h.startsWith('tel:')) { if (!/^tel:\+90\d{10}$/.test(h)) sorunlar.push(`${s}: tel biçimi ${h}`); continue; }
    if (h.startsWith('mailto:')) { if (!/^mailto:[^@\s?]+@[^@\s?]+\.[a-z]{2,}(\?.*)?$/i.test(h)) sorunlar.push(`${s}: mailto biçimi ${h}`); continue; }
    if (/^https?:/.test(h) && !l.tam.startsWith(TABAN)) continue; // dış bağlantı
    if (/\.pdf(#|$)/i.test(h) && /#/.test(h) && !/#page=\d+$/.test(h)) sorunlar.push(`${s}: PDF çapası #page=N değil: ${h}`);
    yerel.push(l.tam.split('#')[0]);
  }
  for (const u of [...new Set(yerel)]) {
    const st = await durum(u);
    if (st !== 200) sorunlar.push(`${s}: ${u} -> ${st}`);
  }
  if (k.konsol.length) sorunlar.push(`${s}: konsol ${k.konsol.join(' | ')}`);
  if (k.istek.length) sorunlar.push(`${s}: başarısız istek ${k.istek.join(' | ')}`);
  if (k.sayfaHata.length) sorunlar.push(`${s}: sayfa hatası ${k.sayfaHata.join(' | ')}`);
  sayfaRapor[s] = { link: linkler.length, yerel: [...new Set(yerel)].length, ...k };
  console.log(`${s}: ${linkler.length} bağlantı, ${[...new Set(yerel)].length} iç adres denetlendi, konsol ${k.konsol.length}, istek ${k.istek.length}`);
  await p.close();
}
await b.close();
yazJson(path.join(klasor('baglanti'), 'rapor.json'), { sorunlar, sayfaRapor });
sorunlar.forEach((x) => console.log('SORUN', x));
console.log(`${sorunlar.length} sorun.`);
process.exit(sorunlar.length ? 1 : 0);
