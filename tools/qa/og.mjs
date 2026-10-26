/* OG görselleri (spec 4.6): site/_dev/og.php?foy=<id> sayfasını 1200x630 olarak fotoğraflar.
 *   node og.mjs [id ...] [--cikti klasör]
 * Varsayılan çıktı: site/assets/img/og/<id>.png (F1'in klasörü; bu betik yalnızca üretir).
 */
import fs from 'node:fs';
import path from 'node:path';
import { tarayici, url, sunucuKontrol, SITE, arg, konumsal, bekle } from './ortak.mjs';

await sunucuKontrol();
const IDLER = konumsal().length ? konumsal() : ['ana', 'kurumsal', 'urunler', 'malzeme', 'sogutma', 'referans', 'ekler', 'iletisim', 'kvkk', 'yok'];
const OUT = arg('--cikti') ? path.resolve(arg('--cikti')) : path.join(SITE, 'assets', 'img', 'og');
fs.mkdirSync(OUT, { recursive: true });
const b = await tarayici();
const ctx = await b.newContext({ viewport: { width: 1200, height: 630 }, deviceScaleFactor: 1 });
const p = await ctx.newPage();
let hata = 0;
for (const id of IDLER) {
  const r = await p.goto(url(`_dev/og.php?foy=${encodeURIComponent(id)}`), { waitUntil: 'load' });
  if (!r || r.status() !== 200) { console.log(`SORUN ${id}: ${r && r.status()}`); hata++; continue; }
  await p.evaluate(() => document.fonts.ready);
  await bekle(300);
  const dosya = path.join(OUT, `${id}.png`);
  await p.screenshot({ path: dosya, clip: { x: 0, y: 0, width: 1200, height: 630 } });
  console.log(`${id} -> ${dosya} (${Math.round(fs.statSync(dosya).size / 1024)} KB)`);
}
await b.close();
process.exit(hata ? 1 : 0);
