/* Tam sayfa ekran görüntüleri (spec 6.2): her sayfa 4 görünümde + azaltılmış hareket (1440 ve 390).
 *   node ekran.mjs [sayfa.php ...] [--cikti klasör]
 * Varsayılan: 10 sayfa. Çıktı: _qa/ekran/<sayfa>/<görünüm>.png ve rapor.json
 * Raporlar: konsol hataları, başarısız istekler, yatay taşma (scrollWidth > innerWidth), başlık ve H1 sayısı.
 */
import path from 'node:path';
import { tarayici, baglam, izle, url, klasor, yazJson, sunucuKontrol, SAYFALAR, arg, konumsal, bekle } from './ortak.mjs';

await sunucuKontrol();
const sayfalar = konumsal().length ? konumsal() : SAYFALAR;
const OUT = arg('--cikti') ? path.resolve(arg('--cikti')) : klasor('ekran');
const turlar = [
  ['masaustu', {}], ['dizustu', {}], ['telefon', {}], ['kucuk', {}],
  ['masaustu', { azHareket: true }, 'masaustu-az'], ['telefon', { azHareket: true }, 'telefon-az'],
];
const b = await tarayici();
const rapor = {};
let sorun = 0;
for (const s of sayfalar) {
  rapor[s] = {};
  for (const [g, sec, adi] of turlar) {
    const ad = adi || g;
    const ctx = await baglam(b, g, sec);
    const p = await ctx.newPage();
    const k = izle(p);
    const yanit = await p.goto(url(s), { waitUntil: 'load' }).catch((e) => ({ status: () => String(e) }));
    await p.evaluate(() => document.fonts && document.fonts.ready);
    await bekle(800);
    const bilgi = await p.evaluate(() => ({
      baslik: document.title,
      h1: document.querySelectorAll('h1').length,
      tasma: document.documentElement.scrollWidth - window.innerWidth,
      yukseklik: document.documentElement.scrollHeight,
    }));
    const dosya = path.join(OUT, s.replace(/\.php$/, ''), `${ad}.png`);
    await (await import('node:fs')).promises.mkdir(path.dirname(dosya), { recursive: true });
    await p.screenshot({ path: dosya, fullPage: true, timeout: 60000 }).catch((e) => { k.sayfaHata.push('ekran: ' + e.message); });
    const durum = yanit.status();
    rapor[s][ad] = { durum, ...bilgi, ...k };
    const kotu = (s !== '404.php' && durum !== 200) || bilgi.tasma > 0 || bilgi.h1 !== 1 || k.konsol.length || k.istek.length || k.sayfaHata.length;
    if (kotu) sorun++;
    console.log(`${kotu ? 'SORUN' : 'tamam'}  ${s} ${ad}  durum ${durum}  taşma ${bilgi.tasma}px  h1 ${bilgi.h1}  konsol ${k.konsol.length}  istek ${k.istek.length}`);
    await ctx.close();
  }
}
await b.close();
yazJson(path.join(OUT, 'rapor.json'), rapor);
console.log(`${sorun} sorunlu görüntü. Rapor: ${path.join(OUT, 'rapor.json')}`);
process.exit(sorun ? 1 : 0);
