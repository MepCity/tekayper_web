/* Yapı ızgarası denetimi: seçili sayfa ve kaydırma konumlarında görünüm (viewport) ekran görüntüleri.
 *   QA_URL=http://127.0.0.1:8132 node izgara.mjs [--cikti klasör] [--az]
 * Çıktı: _qa/izgara/<ad>-<genislik>.png ve konsol/taşma raporu.
 */
import path from 'node:path';
import { tarayici, baglam, izle, url, klasor, sunucuKontrol, arg, bayrak, bekle, yazJson } from './ortak.mjs';

await sunucuKontrol();
const OUT = arg('--cikti') ? path.resolve(arg('--cikti')) : klasor('izgara');
const az = process.argv.includes('--az');
/* [ad, sayfa, konum]: konum sayı (0-1, kaydırılabilir aralığın oranı) ya da '#id' (bölüm üstü) */
const CEKIMLER = [
  ['ana-ust', 'index.php', 0],
  ['ana-orta', 'index.php', 0.28],
  ['ana-orta2', 'index.php', 0.55],
  ['ana-alt', 'index.php', 1],
  ['ana-gecis', 'index.php', '#notlar'],
  ['pano-gecis', 'pano-sogutma-sistemleri.php', '#modeller'],
  ['kataloglar-gecis', 'kataloglar.php', '#sayfa-dizini'],
  ['urunler-gecis', 'urunler.php', '#not'],
  ['pano-cizim', 'pano-sogutma-sistemleri.php', '#ic-dis'],
  ['pano-hesap', 'pano-sogutma-sistemleri.php', '#hesap'],
  ['pano-teklif', 'pano-sogutma-sistemleri.php', '#teklif'],
  ['kurumsal-orta', 'kurumsal.php', 0.5],
  ['malzeme-tablo', 'tekayper-urunler.php', '#liste'],
  ['kataloglar-ust', 'kataloglar.php', 0],
  ['kataloglar-orta', 'kataloglar.php', 0.5],
  ['kataloglar-alt', 'kataloglar.php', 1],
  ['iletisim-alt', 'iletisim.php', 1],
  ['urunler-orta', 'urunler.php', 0.5],
  ['referans-orta', 'referanslar.php', 0.5],
  ['kvkk-orta', 'kvkk.php', 0.5],
  ['404', '404.php', 0],
];
const secili = process.argv.slice(2).filter((a) => CEKIMLER.some((c) => c[0] === a));
const b = await tarayici();
const rapor = {};
for (const g of ['masaustu', 'telefon']) {
  const ctx = await baglam(b, g, { azHareket: az });
  let sayfaNo = '';
  let p, k;
  for (const [ad, sayfa, konum] of CEKIMLER) {
    if (secili.length && !secili.includes(ad)) continue;
    p = await ctx.newPage();
    k = izle(p);
    await p.goto(url(sayfa), { waitUntil: 'load' });
    await p.evaluate(() => document.fonts && document.fonts.ready);
    await bekle(900);
    const tasma = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    const yukseklik = await p.evaluate(() => document.documentElement.scrollHeight);
    // yavaş kaydırarak ilerle (tembel yükleme ve ScrollTrigger tetiklensin)
    const hedefY = await p.evaluate((konum) => {
      const max = document.documentElement.scrollHeight - innerHeight;
      if (typeof konum === 'number') return Math.round(max * konum);
      const e = document.querySelector(konum);
      return e ? Math.round(e.getBoundingClientRect().top + scrollY - 140) : 0;
    }, konum);
    let y = 0;
    while (y < hedefY) {
      y = Math.min(hedefY, y + 600);
      await p.evaluate((y) => window.scrollTo(0, y), y);
      await bekle(60);
    }
    await p.evaluate((y) => window.scrollTo(0, y), hedefY);
    await bekle(1100);
    const dosya = path.join(OUT, `${ad}-${g === 'masaustu' ? 1440 : 390}${az ? '-az' : ''}.png`);
    await p.screenshot({ path: dosya });
    rapor[`${ad}-${g}`] = { tasma, yukseklik, konsol: k.konsol, istek: k.istek, hata: k.sayfaHata };
    await p.close();
  }
  await ctx.close();
}
await b.close();
yazJson(path.join(OUT, `rapor${az ? '-az' : ''}.json`), rapor);
for (const [a, r] of Object.entries(rapor)) console.log(a, 'tasma', r.tasma, 'konsol', r.konsol.length, r.istek.length, r.hata.length);
