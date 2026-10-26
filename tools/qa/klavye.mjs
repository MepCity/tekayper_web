/* Klavye geçişi (spec 6.3): Tab ile bütün odak duraklarını gezer, her durağı fotoğraflar.
 *   node klavye.mjs [sayfa ...] [--telefon] [--en-fazla 120] [--cikti klasör]
 * Denetler: odak görünür (outline ya da box-shadow), opaklığı 0 olan odaklanabilir yok, odaklanan öğe ekranda ve
 * üstü örtülmemiş, görsel sıra (yukarıdan aşağı) ile DOM sırası arasındaki ters dönüşler, Esc ile diyalog kapanması.
 * Çıktı: _qa/klavye/<sayfa>/NN.png, sheet.png, rapor.json. Çıkış kodu 1 = sorun var.
 */
import path from 'node:path';
import fs from 'node:fs';
import { tarayici, baglam, url, klasor, yazJson, sunucuKontrol, SAYFALAR, arg, bayrak, konumsal, bekle, temasSayfasi } from './ortak.mjs';

await sunucuKontrol();
const sayfalar = konumsal().length ? konumsal() : SAYFALAR;
const ENFAZLA = parseInt(arg('--en-fazla', '120'), 10);
const g = bayrak('--telefon') ? 'telefon' : 'masaustu';
const b = await tarayici();
const rapor = {};
let sorun = 0;

for (const s of sayfalar) {
  const ad = s.replace(/\.php.*$/, '');
  const OUT = arg('--cikti') ? path.resolve(arg('--cikti'), ad) : klasor('klavye', `${ad}-${g}`);
  fs.mkdirSync(OUT, { recursive: true });
  const ctx = await baglam(b, g);
  const p = await ctx.newPage();
  await p.goto(url(s), { waitUntil: 'load' });
  await p.evaluate(() => document.fonts.ready);
  await bekle(600);
  const duraklar = [];
  const kareler = [];
  let ilk = null;
  for (let i = 0; i < ENFAZLA; i++) {
    await p.keyboard.press('Tab');
    await bekle(120);
    // tarayıcının odakla yaptığı kaydırmayı korur; yumuşak kaydırma (CSS/Lenis) bitsin diye bekler
    await p.evaluate(() => new Promise((ok) => { let y = -1, n = 0; const t = () => { if (scrollY === y && ++n > 3) ok(); else { n = scrollY === y ? n : 0; y = scrollY; requestAnimationFrame(t); } }; t(); }));
    const d = await p.evaluate(() => {
      const el = document.activeElement;
      if (!el || el === document.body) return null;
      const r = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      let opak = 1;
      for (let a = el; a && a.nodeType === 1; a = a.parentElement) opak *= parseFloat(getComputedStyle(a).opacity);
      const cx = Math.min(innerWidth - 1, Math.max(0, r.left + r.width / 2));
      const cy = Math.min(innerHeight - 1, Math.max(0, r.top + r.height / 2));
      const ust = document.elementFromPoint(cx, cy);
      const sec = el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '');
      return {
        sec,
        metin: (el.getAttribute('aria-label') || el.innerText || el.value || '').trim().slice(0, 60),
        x: Math.round(r.left), y: Math.round(r.top + scrollY), w: Math.round(r.width), h: Math.round(r.height),
        rx: r.left, ry: r.top,
        // odak göstergesi öğenin kendisinde ya da :focus-within ile kapsayan hücrede (antet hücresi gibi) olabilir
        cerceve: (cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0) || (cs.boxShadow && cs.boxShadow !== 'none') ||
          [el.parentElement, el.parentElement && el.parentElement.parentElement].some((a) => {
            if (!a) return false;
            const c = getComputedStyle(a);
            return (c.outlineStyle !== 'none' && parseFloat(c.outlineWidth) > 0) || (c.boxShadow && c.boxShadow !== 'none') || parseFloat(c.borderTopWidth) >= 2;
          }),
        opak: Math.round(opak * 100) / 100,
        ekranda: r.bottom > 0 && r.top < innerHeight && r.right > 0 && r.left < innerWidth && r.width > 0,
        ortulu: !!ust && ust !== el && !el.contains(ust) && !ust.contains(el),
        kimlik: sec + '|' + Math.round(r.left) + '|' + Math.round(r.top + scrollY),
      };
    });
    if (!d) continue;
    if (ilk && d.kimlik === ilk) break;
    if (!ilk) ilk = d.kimlik;
    const sorunlar = [];
    if (!d.cerceve) sorunlar.push('odak görünmüyor');
    if (d.opak < 0.5) sorunlar.push(`opaklık ${d.opak}`);
    if (!d.ekranda) sorunlar.push('ekran dışında');
    if (d.ortulu) sorunlar.push('üstü örtülü');
    d.sorunlar = sorunlar;
    duraklar.push(d);
    const no = String(duraklar.length).padStart(2, '0');
    const dosya = path.join(OUT, `${no}.png`);
    const pad = 24;
    const vp = p.viewportSize();
    const clip = { x: Math.max(0, d.rx - pad), y: Math.max(0, d.ry - pad), width: Math.min(vp.width, d.w + pad * 2), height: Math.min(vp.height, d.h + pad * 2) };
    if (clip.width > 2 && clip.height > 2 && d.ekranda) {
      await p.screenshot({ path: dosya, clip }).catch(() => {});
      if (fs.existsSync(dosya)) kareler.push({ dosya, etiket: `${no} ${d.metin || d.sec}${sorunlar.length ? ' · ' + sorunlar.join(', ') : ''}` });
    }
  }
  // görsel sıra: bir durak öncekinden belirgin biçimde yukarıdaysa (aynı sütun bandında) ters dönüş say
  let ters = 0;
  for (let i = 1; i < duraklar.length; i++) if (duraklar[i].y < duraklar[i - 1].y - 120) ters++;
  // Esc ile diyalog kapanıyor mu
  let esc = 'diyalog yok';
  const dugmeler = await p.$$('button[aria-haspopup="dialog"]');
  let dugme = null;
  for (const d of dugmeler) if (await d.isVisible()) { dugme = d; break; }
  if (dugme) {
    await p.evaluate(() => window.scrollTo(0, 0));
    await bekle(300);
    const tik = await dugme.click({ trial: true, timeout: 2000 }).then(() => '', (e) => (/intercepts pointer events/.test(e.message) ? 'düğmenin üstünü başka öğe örtüyor (tıklanamıyor)' : ''));
    if (tik) console.log(`UYARI ${s}: ${tik}`);
    await dugme.focus();
    await p.keyboard.press('Enter');
    await bekle(400);
    const acik = await p.evaluate(() => !!document.querySelector('dialog[open], [role="dialog"]:not([hidden])'));
    await p.keyboard.press('Escape');
    await bekle(400);
    const kapali = await p.evaluate(() => !document.querySelector('dialog[open], [role="dialog"]:not([hidden])'));
    esc = acik ? (kapali ? 'tamam' : 'Esc ile kapanmadı') : 'diyalog açılmadı';
  }
  const kotu = duraklar.filter((d) => d.sorunlar.length).length;
  rapor[s] = { gorunum: g, durak: duraklar.length, sorunlu: kotu, tersSira: ters, esc, duraklar };
  if (kotu || esc.startsWith('Esc') || esc === 'diyalog açılmadı') sorun++;
  if (kareler.length) await temasSayfasi(b, kareler, path.join(OUT, 'sheet.png'), { sutun: 6, baslik: `${s} klavye (${g})` });
  console.log(`${s}: ${duraklar.length} durak, ${kotu} sorunlu, ${ters} ters sıra, Esc: ${esc}`);
  await ctx.close();
}
await b.close();
yazJson(path.join(klasor('klavye'), `rapor-${g}.json`), rapor);
process.exit(sorun ? 1 : 0);
