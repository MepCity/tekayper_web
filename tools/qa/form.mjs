/* Teklif formu testleri (spec 3.8, 4.5, 6.3). Sunucu TEKAYPER_MAIL=log ile çalışmalı.
 *   node form.mjs [--hiz]
 * Denetler: geçerli gönderim (JSON ok, talep no T-YYYYMMDD-NN, jsonl satırı, e-posta günlüğü), her zorunlu alan
 * eksikken hata, bal tuzağı (sessiz ok, e-posta yok), çok hızlı anahtar (< 3 s) reddi, JS kapalıyken başarı (303,
 * damga sunucuda) ve hata yolu (adreste kişisel veri yok), ?konu ve ?liste=1 ön doldurma.
 * --hiz: 10 dakikada 5 sınırını dener (6. istek 429). storage/hiz sayacını tüketir; en sona çalıştırın.
 * Alan adları spec 3.8: adsoyad firma telefon eposta konu mesaj kvkk web anahtar kaynak.
 */
import fs from 'node:fs';
import path from 'node:path';
import { tarayici, baglam, izle, url, TABAN, klasor, yazJson, sunucuKontrol, bayrak, bekle, SITE } from './ortak.mjs';

await sunucuKontrol();
const hatalar = [];
const not = (ok, mesaj) => { console.log(`${ok ? 'tamam' : 'SORUN'}  ${mesaj}`); if (!ok) hatalar.push(mesaj); };
const GECERLI = { adsoyad: 'QA Deneme', firma: 'QA', telefon: '0224 000 00 00', eposta: 'qa@example.com', konu: 'QA otomatik test', mesaj: 'QA: bu bir test talebidir.', kvkk: '1', web: '' };
const ay = new Date().toISOString().slice(0, 7);
const jsonl = path.join(SITE, 'storage', 'talepler', `${ay}.jsonl`);
const postaLog = path.join(SITE, 'storage', 'eposta-log.txt');
const satirSay = (f) => (fs.existsSync(f) ? fs.readFileSync(f, 'utf8').split('\n').filter(Boolean).length : 0);
const boyut = (f) => (fs.existsSync(f) ? fs.statSync(f).size : 0);

async function anahtarAl(p) {
  await p.goto(url('iletisim.php'), { waitUntil: 'load' });
  return p.$eval('input[name="anahtar"]', (e) => e.value).catch(() => '');
}
async function gonder(req, alanlar) {
  const r = await req.post(url('teklif-gonder.php'), {
    form: alanlar, headers: { Accept: 'application/json', Origin: TABAN, Referer: url('iletisim.php') }, maxRedirects: 0,
  });
  let govde = null;
  try { govde = await r.json(); } catch { govde = null; }
  return { durum: r.status(), govde, konum: r.headers().location || '' };
}

const b = await tarayici();
const ctx = await baglam(b, 'masaustu');
const p = await ctx.newPage();
const k = izle(p);
const anahtar = await anahtarAl(p);
not(!!anahtar, 'formda anahtar alanı var');
const adlar = await p.$$eval('form [name]', (els) => els.map((e) => e.name));
['adsoyad', 'firma', 'telefon', 'eposta', 'konu', 'mesaj', 'kvkk', 'web', 'anahtar'].forEach((a) => not(adlar.includes(a), `alan "${a}" var`));
const yontem = await p.$eval('form[action*="teklif-gonder"]', (f) => f.method).catch(() => '');
not(yontem === 'post', `form method=post (${yontem})`);

// çok hızlı anahtar
{
  const r = await gonder(ctx.request, { ...GECERLI, anahtar });
  not(r.govde && r.govde.ok === false, `3 saniyeden önce gönderim reddedildi (${r.durum})`);
}
await bekle(3300);

// geçerli
{
  const once = satirSay(jsonl), onceLog = boyut(postaLog);
  const taze = await anahtarAl(p);
  await bekle(3300); // anahtar 3 sn dolmadan gönderim 400 döner
  const r = await gonder(ctx.request, { ...GECERLI, anahtar: taze });
  const no = r.govde && (r.govde.talep || r.govde.no || r.govde.talep_no);
  not(r.govde && r.govde.ok === true, `geçerli gönderim ok:true (${r.durum})`);
  not(/^T-\d{8}-\d{2,}$/.test(String(no)), `talep numarası biçimi (${no})`);
  not(satirSay(jsonl) === once + 1, 'jsonl kaydına bir satır eklendi');
  not(boyut(postaLog) > onceLog && fs.readFileSync(postaLog, 'utf8').includes(String(no)), 'e-posta günlüğünde talep numarası var (TEKAYPER_MAIL=log)');
}

// zorunlu alanlar tek tek
await bekle(3300);
for (const alan of ['adsoyad', 'telefon', 'eposta', 'konu', 'kvkk']) {
  const veri = { ...GECERLI, anahtar: await anahtarAl(p) };
  await bekle(3300);
  delete veri[alan];
  const r = await gonder(ctx.request, veri);
  const metin = JSON.stringify(r.govde || {});
  not(r.govde && r.govde.ok === false && metin.includes(alan), `eksik "${alan}" hatası`);
}
// geçersiz e-posta ve kısa telefon
{
  const veri = { ...GECERLI, eposta: 'gecersiz', telefon: '123', anahtar: await anahtarAl(p) };
  await bekle(3300);
  const r = await gonder(ctx.request, veri);
  not(r.govde && r.govde.ok === false, 'geçersiz e-posta ve telefon reddedildi');
}
// bal tuzağı
{
  const onceLog = boyut(postaLog);
  const veri = { ...GECERLI, web: 'http://spam.example', anahtar: await anahtarAl(p) };
  await bekle(3300);
  const r = await gonder(ctx.request, veri);
  not(r.govde && r.govde.ok === true && boyut(postaLog) === onceLog, 'bal tuzağı: sessiz ok, e-posta yok');
}

// ön doldurma
{
  await p.goto(url('iletisim.php?konu=' + encodeURIComponent('Pano soğutma: TPK 1000') + '#talep'));
  const konu = await p.$eval('[name="konu"]', (e) => e.value).catch(() => '');
  not(konu.includes('TPK 1000'), `?konu ön doldurma (${konu})`);
  await p.evaluate(() => localStorage.setItem('tky.talepListesi', JSON.stringify([{ marka: 'Finder', grup: 'Güvenlik röleleri' }])));
  await p.goto(url('iletisim.php?liste=1#talep'));
  await bekle(400);
  const mesaj = await p.$eval('[name="mesaj"]', (e) => e.value).catch(() => '');
  not(mesaj.includes('Finder: Güvenlik röleleri'), '?liste=1 talep listesini doldurdu');
}

// JS kapalı talep listesi: ?grup[]= Talebiniz alanına satır satır yazılır
{
  const c3 = await baglam(b, 'masaustu', { js: false });
  const p3 = await c3.newPage();
  await p3.goto(url('iletisim.php?' + ['Finder: Güvenlik röleleri', 'Omron: Röleler'].map((g) => 'grup[]=' + encodeURIComponent(g)).join('&') + '&konu=Talep+listesi#talep'));
  const mesaj = await p3.$eval('[name="mesaj"]', (e) => e.value).catch(() => '');
  not(mesaj.includes('Finder: Güvenlik röleleri') && mesaj.includes('Omron: Röleler'), `JS kapalı ?grup[]= talep listesini yazdı (${JSON.stringify(mesaj)})`);
  await c3.close();
}

// JS kapalı
{
  const c2 = await baglam(b, 'masaustu', { js: false });
  const p2 = await c2.newPage();
  await p2.goto(url('iletisim.php'));
  await bekle(3300);
  for (const [ad, v] of Object.entries(GECERLI)) {
    if (ad === 'web') continue;
    if (ad === 'kvkk') await p2.check('[name="kvkk"]'); else await p2.fill(`[name="${ad}"]`, v);
  }
  await Promise.all([p2.waitForNavigation(), p2.click('form[action*="teklif-gonder"] [type="submit"]')]);
  const u = p2.url();
  not(/talep=T-\d{8}-\d{2,}/.test(u), `JS kapalı başarı: 303 sonrası adres ${u}`);
  not(!/qa@example|QA%20Deneme|QA\+Deneme|0224/.test(u), 'JS kapalı başarı: adreste kişisel veri yok');
  // hata yolu
  await p2.goto(url('iletisim.php'));
  await bekle(3300);
  await p2.fill('[name="adsoyad"]', 'QA Deneme');
  await p2.fill('[name="eposta"]', 'qa@example.com');
  await Promise.all([p2.waitForNavigation(), p2.click('form[action*="teklif-gonder"] [type="submit"]')]);
  const u2 = p2.url();
  const deger = await p2.$eval('[name="adsoyad"]', (e) => e.value).catch(() => '');
  not(!/qa@example|QA%20Deneme|QA\+Deneme/.test(u2), `JS kapalı hata: adreste kişisel veri yok (${u2})`);
  not(deger === 'QA Deneme', 'JS kapalı hata: girilen değerler korunuyor');
  await c2.close();
}

// hız sınırı
if (bayrak('--hiz')) {
  let son = null;
  for (let i = 0; i < 6; i++) {
    const veri = { ...GECERLI, anahtar: await anahtarAl(p) };
    await bekle(3300);
    son = await gonder(ctx.request, veri);
  }
  not(son.durum === 429, `6. istek 429 (${son.durum})`);
}
not(!k.sayfaHata.length, 'sayfa hatası yok');
await b.close();
yazJson(path.join(klasor('form'), 'rapor.json'), { hatalar, konsol: k });
console.log(hatalar.length ? `${hatalar.length} sorun` : 'tamam');
process.exit(hatalar.length ? 1 : 0);
