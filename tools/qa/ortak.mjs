/* QA ortak yardımcıları (spec bölüm 6). Geliştirme aracı, yayına çıkmaz.
 * Kurulum bir kez: cd tools/qa && npm i   (playwright-core, axe-core; yüklü Chrome kullanılır)
 * Sunucu: cd site && TEKAYPER_MAIL=log php -S 127.0.0.1:8096 -t . ../tools/qa/router.php
 * Ortam: QA_URL (varsayılan http://127.0.0.1:8096), QA_CIKTI (varsayılan <proje>/_qa), SCROLLCRAFT_CHROME (Chrome yolu)
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

export const QA_DIR = path.dirname(fileURLToPath(import.meta.url));
export const PROJE = path.resolve(QA_DIR, '..', '..');
export const SITE = path.join(PROJE, 'site');
export const TABAN = (process.env.QA_URL || 'http://127.0.0.1:8096').replace(/\/$/, '');
export const CIKTI = process.env.QA_CIKTI || path.join(PROJE, '_qa');

export const SAYFALAR = [
  'index.php', 'kurumsal.php', 'urunler.php', 'tekayper-urunler.php', 'pano-sogutma-sistemleri.php',
  'referanslar.php', 'kataloglar.php', 'iletisim.php', 'kvkk.php', '404.php',
];

export const GORUNUMLER = {
  masaustu: { width: 1440, height: 900 },
  dizustu: { width: 1280, height: 800 },
  telefon: { width: 390, height: 844, isMobile: true, hasTouch: true, deviceScaleFactor: 2 },
  kucuk: { width: 360, height: 640, isMobile: true, hasTouch: true, deviceScaleFactor: 2 },
};

const CHROME_ADAYLARI = [
  process.env.SCROLLCRAFT_CHROME,
  '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  '/Applications/Chromium.app/Contents/MacOS/Chromium',
  '/usr/bin/google-chrome',
  '/usr/bin/chromium',
].filter(Boolean);

export function arg(ad, varsayilan) {
  const a = process.argv.slice(2);
  const i = a.indexOf(ad);
  return i > -1 && a[i + 1] && !a[i + 1].startsWith('--') ? a[i + 1] : varsayilan;
}
export const bayrak = (ad) => process.argv.includes(ad);
const BAYRAKLAR = new Set(['--azhareket', '--telefon', '--hiz', '--json', '--yaz']);
/* Bayrak ve değerli seçenekler dışındaki argümanlar (sayfa adları) */
export function konumsal() {
  const a = process.argv.slice(2);
  const out = [];
  for (let i = 0; i < a.length; i++) {
    if (a[i].startsWith('--')) { if (!BAYRAKLAR.has(a[i])) i++; continue; }
    out.push(a[i]);
  }
  return out;
}

export async function tarayici() {
  const exe = CHROME_ADAYLARI.find((p) => fs.existsSync(p));
  if (!exe) throw new Error('Chrome bulunamadı. SCROLLCRAFT_CHROME ile yolunu verin.');
  return chromium.launch({ executablePath: exe, headless: true, args: ['--autoplay-policy=no-user-gesture-required'] });
}

export async function baglam(b, gorunum = 'masaustu', { azHareket = false, js = true } = {}) {
  const g = typeof gorunum === 'string' ? GORUNUMLER[gorunum] : gorunum;
  const { width, height, ...geri } = g;
  return b.newContext({ viewport: { width, height }, ...geri, reducedMotion: azHareket ? 'reduce' : 'no-preference', javaScriptEnabled: js, locale: 'tr-TR' });
}

/* Konsol hatalarını ve başarısız istekleri toplar */
export function izle(sayfa) {
  const kayit = { konsol: [], istek: [], sayfaHata: [] };
  sayfa.on('console', (m) => { if (m.type() === 'error' || m.type() === 'warning') kayit.konsol.push(`${m.type()}: ${m.text()}`); });
  sayfa.on('pageerror', (e) => kayit.sayfaHata.push(String(e)));
  sayfa.on('requestfailed', (r) => kayit.istek.push(`${r.failure()?.errorText} ${r.url()}`));
  sayfa.on('response', (r) => { if (r.status() >= 400) kayit.istek.push(`${r.status()} ${r.url()}`); });
  return kayit;
}

export function klasor(...p) {
  const d = path.join(CIKTI, ...p);
  fs.mkdirSync(d, { recursive: true });
  return d;
}

export function yazJson(dosya, veri) {
  fs.mkdirSync(path.dirname(dosya), { recursive: true });
  fs.writeFileSync(dosya, JSON.stringify(veri, null, 2) + '\n');
}

export function url(yol) {
  return /^https?:/.test(yol) ? yol : `${TABAN}/${String(yol).replace(/^\//, '')}`;
}

/* Sunucunun BU yapıyı sunduğunu doğrular (spec 6.1) */
export async function sunucuKontrol() {
  try {
    const r = await fetch(url('assets/fonts/chilldin-vf.woff2'));
    if (!r.ok) throw new Error(String(r.status));
  } catch (e) {
    console.error(`Sunucu yanıt vermiyor ya da başka bir yapı sunuyor: ${TABAN} (${e.message}).`);
    console.error('Başlatın: cd site && TEKAYPER_MAIL=log php -S 127.0.0.1:8096 -t . ../tools/qa/router.php');
    process.exit(2);
  }
}

/* Görüntüleri tek bir temas sayfasında birleştirir (sharp gerekmez: Chrome ile çizilir) */
export async function temasSayfasi(b, gorseller, cikti, { sutun = 4, genislik = 1600, baslik = '' } = {}) {
  const ctx = await b.newContext({ viewport: { width: genislik, height: 800 } });
  const p = await ctx.newPage();
  const hucre = gorseller.map((g) => {
    const veri = fs.readFileSync(g.dosya).toString('base64');
    return `<figure><img src="data:image/png;base64,${veri}"><figcaption>${g.etiket || path.basename(g.dosya)}</figcaption></figure>`;
  }).join('');
  await p.setContent(`<!doctype html><meta charset="utf-8"><style>
    body{margin:0;padding:16px;background:#fff;font:13px/1.3 Arial,sans-serif;color:#1B1D1F}
    h1{font-size:16px;margin:0 0 12px}
    .g{display:grid;grid-template-columns:repeat(${sutun},1fr);gap:12px}
    figure{margin:0;border:1px solid #8E959C}img{display:block;width:100%;height:auto}
    figcaption{padding:4px 6px;border-top:1px solid #8E959C}</style>
    <h1>${baslik}</h1><div class="g">${hucre}</div>`);
  await p.waitForLoadState('load');
  await p.screenshot({ path: cikti, fullPage: true });
  await ctx.close();
  return cikti;
}

export const bekle = (ms) => new Promise((r) => setTimeout(r, ms));
