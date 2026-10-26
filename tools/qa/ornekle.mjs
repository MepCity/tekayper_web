/* Bölüm örnekleyici (spec 6.2): GSAP pinleri harness'in göremediği durumlar için.
 *   node ornekle.mjs <sayfa-ya-da-url> [--adim 6] [--cikti klasör] [--azhareket]
 * Her bölümü (main içindeki [data-satir], yoksa section, yoksa [data-sc-act]) giriş öncesinden çıkışına
 * kadar N konumda örnekler, masaüstü (1440) ve telefon (390). Varsa data-sc-verify-state okunur.
 * Çıktı: _qa/ornek/<sayfa>/<görünüm>/NN.png, temas sayfası sheet-<görünüm>.png ve durum.json.
 * Ölü kaydırma uyarısı: art arda iki karede ekran görüntüsü bayt bayt aynıysa ve bölüm pinliyse yazılır.
 */
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { tarayici, baglam, url, klasor, yazJson, sunucuKontrol, arg, bayrak, konumsal, bekle, temasSayfasi } from './ortak.mjs';

await sunucuKontrol();
const hedef = konumsal()[0] || 'index.php';
const N = parseInt(arg('--adim', '6'), 10);
const ad = hedef.replace(/^https?:\/\/[^/]+\//, '').replace(/[?#].*$/, '').replace(/\.php$/, '') || 'index';
const OUT = arg('--cikti') ? path.resolve(arg('--cikti')) : klasor('ornek', ad);
const b = await tarayici();
const ozet = {};

for (const g of ['masaustu', 'telefon']) {
  const ctx = await baglam(b, g, { azHareket: bayrak('--azhareket') });
  const p = await ctx.newPage();
  await p.goto(url(hedef), { waitUntil: 'load' });
  await p.evaluate(() => document.fonts.ready);
  await bekle(900);
  const bolumler = await p.evaluate(() => {
    const kok = document.querySelector('main') || document.body;
    let els = [...kok.querySelectorAll('[data-satir]')];
    if (!els.length) els = [...kok.querySelectorAll(':scope > section, section')];
    if (!els.length) els = [...document.querySelectorAll('[data-sc-act]')];
    return els.map((el, i) => {
      const r = el.getBoundingClientRect();
      return { i, id: el.id || `bolum-${i}`, satir: el.dataset.satir || '', ust: r.top + scrollY, boy: r.height };
    });
  });
  const dizin = path.join(OUT, g);
  fs.mkdirSync(dizin, { recursive: true });
  const kareler = [];
  const durumlar = [];
  const vh = g === 'telefon' ? 844 : 900;
  for (const bl of bolumler) {
    let onceki = null;
    for (let k = 0; k < N; k++) {
      const y = Math.max(0, bl.ust - vh * 0.6 + (k / (N - 1)) * (bl.boy + vh * 0.2));
      await p.evaluate((yy) => {
        if (window.lenis && typeof window.lenis.scrollTo === 'function') window.lenis.scrollTo(yy, { immediate: true, force: true });
        else window.scrollTo(0, yy);
      }, y);
      await bekle(650);
      const durum = await p.evaluate((id) => {
        const el = document.getElementById(id);
        return el ? (el.getAttribute('data-sc-verify-state') || el.querySelector('[data-sc-verify-state]')?.getAttribute('data-sc-verify-state') || '') : '';
      }, bl.id);
      const dosya = path.join(dizin, `${String(bl.i).padStart(2, '0')}-${bl.id}-${k}.png`);
      const tampon = await p.screenshot({ path: dosya });
      const ozetHash = crypto.createHash('md5').update(tampon).digest('hex');
      const olu = onceki && onceki === ozetHash && k > 0 && k < N - 1;
      onceki = ozetHash;
      kareler.push({ dosya, etiket: `${bl.satir || bl.i} ${bl.id} ${k + 1}/${N}${durum ? ' · ' + durum : ''}${olu ? ' · AYNI KARE' : ''}` });
      durumlar.push({ bolum: bl.id, k, y: Math.round(y), durum, ayniKare: !!olu });
    }
  }
  await temasSayfasi(b, kareler, path.join(OUT, `sheet-${g}.png`), { sutun: N, baslik: `${hedef} ${g}` });
  ozet[g] = durumlar;
  console.log(`${g}: ${bolumler.length} bölüm, ${kareler.length} kare, ${durumlar.filter((d) => d.ayniKare).length} aynı kare`);
  await ctx.close();
}
await b.close();
yazJson(path.join(OUT, 'durum.json'), ozet);
console.log(`Temas sayfaları: ${OUT}/sheet-masaustu.png, sheet-telefon.png`);
