/* Pano soğutma hesabı, JS tarafı ve PHP ile karşılaştırma (spec 3.5, 6.3).
 *   node sogutma.mjs [--sayfa pano-sogutma-sistemleri.php]
 * Sayfa sözleşmesi (P5): window.tkySogutma.hesapla(girdi) PHP'deki sogutma_hesapla() ile aynı alanları döndürür
 *   (A, Qs, Qk, Qr, kapasite, durum, sigar). Beklenen değerler: php sogutma-testi.php --json (başvuru hesabı).
 * Ayrıca biçim: forma girilen vektör 1 için sonuç alanında "1.033 W" ve "TPK 1000" metni görünmeli.
 */
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { tarayici, baglam, izle, url, klasor, yazJson, sunucuKontrol, arg, bekle, QA_DIR } from './ortak.mjs';

await sunucuKontrol();
const SAYFA = arg('--sayfa', 'pano-sogutma-sistemleri.php');
let php;
try {
  php = JSON.parse(execFileSync('php', [path.join(QA_DIR, 'sogutma-testi.php'), '--json'], { encoding: 'utf8' }));
} catch (e) {
  php = JSON.parse(e.stdout || '{}');
}
const b = await tarayici();
const ctx = await baglam(b, 'masaustu');
const p = await ctx.newPage();
const k = izle(p);
await p.goto(url(SAYFA), { waitUntil: 'load' });
await bekle(500);
const varMi = await p.evaluate(() => !!(window.tkySogutma && window.tkySogutma.hesapla));
if (!varMi) { console.error('window.tkySogutma.hesapla yok (P5 sözleşmesi).'); await b.close(); process.exit(2); }
const TOL = { A: 0.0015, Qs: 0.1, Qk: 0.1, Qr: 0.1 };
const hatalar = [];
for (const [i, s] of php.satirlar.entries()) {
  const js = await p.evaluate((g) => window.tkySogutma.hesapla(g), s.girdi);
  for (const alan of ['A', 'Qs', 'Qk', 'Qr', 'kapasite', 'durum', 'sigar']) {
    const a = js[alan], e = s.basvuru[alan];
    const ok = (typeof e === 'number' && typeof a === 'number') ? Math.abs(a - e) <= (TOL[alan] ?? 0) : a === e;
    if (!ok) hatalar.push(`vektör ${i + 1} ${alan}: JS ${JSON.stringify(a)}, beklenen ${JSON.stringify(e)}`);
    if (s.uygulama && alan in s.uygulama) {
      const u = s.uygulama[alan];
      const ok2 = (typeof u === 'number' && typeof a === 'number') ? Math.abs(a - u) <= (TOL[alan] ?? 0) : a === u;
      if (!ok2) hatalar.push(`vektör ${i + 1} ${alan}: JS ${JSON.stringify(a)} ile PHP ${JSON.stringify(u)} farklı`);
    }
  }
  console.log(`vektör ${i + 1}: ${JSON.stringify(js)}`);
}
// form üzerinden metin
const sec = (id) => `#${id}`;
const v1 = php.satirlar[0].girdi;
for (const [id, val] of Object.entries(v1)) {
  const el = await p.$(sec(id));
  if (!el) { hatalar.push(`form alanı yok: #${id}`); continue; }
  const tag = await el.evaluate((e) => e.tagName.toLowerCase() + ':' + (e.type || ''));
  if (tag.startsWith('select')) await el.selectOption(String(val)).catch(() => el.selectOption(String(val).replace('.', ',')));
  else if (tag.endsWith('radio')) await p.check(`input[name="${id}"][value="${val}"]`).catch(() => {});
  else await el.fill(String(val).replace('.', ','));
}
await bekle(500);
const metin = await p.evaluate(() => (document.querySelector('[aria-live]')?.closest('section') || document.body).innerText);
if (!/1\.033 W/.test(metin)) hatalar.push('sonuç metninde "1.033 W" yok');
if (!/TPK 1000/.test(metin)) hatalar.push('sonuç metninde "TPK 1000" yok');
if (k.konsol.length || k.sayfaHata.length) hatalar.push(`konsol: ${JSON.stringify(k)}`);
await b.close();
yazJson(path.join(klasor('sogutma'), 'rapor.json'), { hatalar, php });
hatalar.forEach((h) => console.log('HATA', h));
console.log(hatalar.length ? `${hatalar.length} hata` : 'tamam: JS, PHP ve başvuru hesabı aynı');
process.exit(hatalar.length ? 1 : 0);
