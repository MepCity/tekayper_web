/* cizim.js: ŞEMA çizim ilkelleri (paket F2). ES module.
 *
 * Tek seferlik çizimler kendi küçük kalem motoruyla (requestAnimationFrame + stroke-dasharray) çalışır;
 * GSAP gerekmez, bu yüzden GSAP yüklemeyen sayfalarda da (malzeme listesi gibi) semboller çizilir.
 * Kaydırmaya bağlı çizimde (scrub) GSAP varsa duraklatılmış bir gsap.timeline döner (ScrollTrigger'a
 * animation olarak verilir); GSAP yoksa aynı arayüzde progress(p) taşıyan bir nesne döner.
 * Motor non-scaling-stroke çizgilerin uzunluğunu ekran pikseliyle ölçer (DrawSVGPlugin ile aynı yöntem),
 * kesik çizgilerin desenini çizim sırasında korur, dolu öğeleri (ok, nokta) sırası gelince basar.
 *
 * Hareket: document.documentElement.dataset.hareket 'az' ise (ya da prefers-reduced-motion) her ilkel
 * son durumu hemen gösterir, Promise hemen çözülür ve ses çalınmaz.
 *
 * Dışa açılanlar:
 *   ciz(hedef, {sure, gecikme, sira, scrub})      çizgileri 0'dan %100'e çizer (data-sira sırasıyla)
 *   enerji(yol, {gecikme, sure, scrub})            yolun altına sarı enerji bandı çizer, sonunda .is-enerjili
 *   sembolCiz(sembol, {sure, gecikme, etiket})     satır içi IEC sembolünü kalem sırasıyla çizer, sonra etiketi harfler
 *   harfle(el, {sure, gecikme})                    çizici yazısı: soldan sağa clip-path silmesi (240 ms)
 *   kota(svg, {x1,y1,x2,y2, deger, birim, yon})    ölçü çizgisi; {g, guncelle(deger, geo), hazir, yaziKutusu(), sil()}
 *   vurgula(el, {kalici, bekle, cik, sure})        metnin arkasında fosforlu kalem süpürmesi
 *   kase(el, {metin, no, tarih})                   onay kaşesi (A08)
 *   kontak(sembol, kapali, {sure, ses})            kontak bıçağını mafsalı etrafında döndürür (A07)
 *   gizle(hedef)                                   çizilecek öğeleri çizilmemiş duruma alır (ilk boyamada parlamayı önler)
 *   baglantilar(sembol)                            bağlantı noktaları: [{yon, uc, x, y (ekran), ux, uy (birim)}]
 *   hareketAz()                                    azaltılmış hareket modu açık mı
 */
import { ses } from './ses.js';

const BOS = 100000;
const UI_KOLAY = [0.23, 1, 0.32, 1];
const VURGU_KOLAY = 'cubic-bezier(0.65, 0, 0.35, 1)';
const CIZILIR = 'path,line,polyline,polygon,circle,ellipse,rect';
const SVGNS = 'http://www.w3.org/2000/svg';

export function hareketAz() {
  const h = document.documentElement.dataset.hareket;
  if (h === 'az') return true;
  if (h === 'tam') return false;
  return !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);
}

/* ------------------------------------------------------------------ yardımcılar */

function dizi(hedef) {
  if (!hedef) return [];
  if (typeof hedef === 'string') return [...document.querySelectorAll(hedef)];
  if (hedef instanceof Element) return [hedef];
  return [...hedef];
}

function cizimOgeleri(hedef) {
  const out = [];
  for (const el of dizi(hedef)) {
    if (el.matches && el.matches(CIZILIR + ',text') && !el.closest('.baglantilar')) { out.push(el); continue; }
    const sirali = el.querySelectorAll('[data-sira]');
    const liste = sirali.length ? sirali : el.querySelectorAll(CIZILIR);
    liste.forEach((c) => { if (!c.closest('.baglantilar') && !c.classList.contains('baglanti')) out.push(c); });
  }
  return out;
}

function tur(el) {
  if (!(el instanceof SVGElement) || el.tagName.toLowerCase() === 'text') return 'yazi';
  if (el.classList.contains('dolgu') || el.classList.contains('kota__ok') || el.getAttribute('stroke') === 'none') return 'dolgu';
  return 'cizgi';
}

function olcek(el) {
  const m = el.getScreenCTM && el.getScreenCTM();
  return m ? Math.hypot(m.a, m.b) || 1 : 1;
}

function ekranUzunlugu(el) {
  let L = 0;
  try { L = el.getTotalLength(); } catch { L = 0; }
  if (!L) {
    try { const b = el.getBBox(); L = 2 * (b.width + b.height); } catch { L = 0; }
  }
  const ns = el.getAttribute('vector-effect') === 'non-scaling-stroke' ||
    getComputedStyle(el).vectorEffect === 'non-scaling-stroke';
  return ns ? L * olcek(el) : L;
}

function desenOku(el) {
  const d = el.dataset.cizimDesen;
  if (!d || d === 'none') return null;
  const n = d.split(/[\s,]+/).map(parseFloat).filter((v) => v >= 0);
  if (!n.length || n.every((v) => v === 0)) return null;
  return n.length % 2 ? n.concat(n) : n;
}

function desenliDash(desen, uzunluk) {
  const out = [];
  let t = 0;
  let i = 0;
  while (t < uzunluk && out.length < 4000) {
    const v = desen[i % desen.length];
    if (i % 2 === 0) {
      const p = Math.min(v, uzunluk - t);
      out.push(p);
      t += v;
      if (p < v) break;
    } else {
      out.push(v);
      t += v;
    }
    i++;
  }
  if (out.length % 2 === 0) out.push(0);
  out.push(BOS);
  return out.join(' ');
}

/* Bir öğenin çizim ilerlemesini uygular (p: 0..1) */
function uygula(o, p) {
  const el = o.el;
  if (o.tur === 'cizgi') {
    if (p >= 1) {
      el.style.strokeDasharray = o.ilkDash;
    } else {
      const L = o.L * p;
      el.style.strokeDasharray = o.desen ? desenliDash(o.desen, L) : `${L} ${BOS}`;
    }
  } else if (o.tur === 'dolgu') {
    el.style.opacity = p > 0 ? o.ilkOpak : '0';
  } else {
    el.style.clipPath = p >= 1 ? o.ilkKirp : `inset(-0.3em ${((1 - p) * 100).toFixed(2)}% -0.3em -0.3em)`;
  }
}

function hazirlaOge(el) {
  const t = tur(el);
  const o = { el, tur: t };
  if (t === 'cizgi') {
    if (el.dataset.cizimDesen === undefined) {
      const d = getComputedStyle(el).strokeDasharray;
      el.dataset.cizimDesen = d && d !== 'none' ? d : '';
    }
    o.ilkDash = el.dataset.cizimIlk ?? el.style.strokeDasharray;
    el.dataset.cizimIlk = o.ilkDash;
    o.desen = desenOku(el);
    o.L = ekranUzunlugu(el);
  } else if (t === 'dolgu') {
    o.ilkOpak = el.dataset.cizimOpak ?? el.style.opacity;
    el.dataset.cizimOpak = o.ilkOpak;
  } else {
    o.ilkKirp = el.dataset.cizimKirp ?? el.style.clipPath;
    el.dataset.cizimKirp = o.ilkKirp;
  }
  return o;
}

/* Kalem planı: data-sira gruplarına süre dağıtır. Kalem sabit hızla çizer: grup süresi en uzun çizgisiyle orantılı. */
function plan(ogeler, { sure = 600, sira = true, harfSure = 240 } = {}) {
  const gruplar = new Map();
  ogeler.forEach((el, i) => {
    const k = sira ? (parseFloat(el.getAttribute('data-sira')) || 0) : 0;
    if (!gruplar.has(k)) gruplar.set(k, []);
    gruplar.get(k).push(hazirlaOge(el));
  });
  const anahtarlar = [...gruplar.keys()].sort((a, b) => a - b);
  const kalem = anahtarlar.map((k) => {
    const g = gruplar.get(k);
    const cizgiler = g.filter((o) => o.tur === 'cizgi');
    const dolgular = g.filter((o) => o.tur === 'dolgu');
    let agirlik = cizgiler.reduce((m, o) => Math.max(m, o.L), 0);
    if (!agirlik && dolgular.length) agirlik = 8;
    return { g, agirlik };
  });
  const toplam = kalem.reduce((s, k) => s + k.agirlik, 0) || 1;
  const enAz = Math.min(40, sure / Math.max(1, kalem.length));
  let ham = kalem.map((k) => (k.agirlik ? Math.max(enAz, (sure * k.agirlik) / toplam) : 0));
  const hamToplam = ham.reduce((s, v) => s + v, 0) || 1;
  ham = ham.map((v) => (v * sure) / hamToplam);
  const parcalar = [];
  let t = 0;
  let son = 0;
  kalem.forEach((k, i) => {
    const d = ham[i];
    k.g.forEach((o) => {
      if (o.tur === 'cizgi') parcalar.push({ ...o, bas: t, sure: d });
      else if (o.tur === 'dolgu') parcalar.push({ ...o, bas: t + d * 0.6, sure: 1 });
      else parcalar.push({ ...o, bas: t, sure: harfSure });
    });
    t += d;
  });
  parcalar.forEach((p) => { son = Math.max(son, p.bas + p.sure); });
  return { parcalar, sure: son };
}

function oynat(pl, gecikme = 0) {
  pl.parcalar.forEach((p) => uygula(p, 0));
  return new Promise((coz) => {
    if (document.hidden) { pl.parcalar.forEach((p) => uygula(p, 1)); coz(); return; }
    const t0 = performance.now() + gecikme;
    const kare = (simdi) => {
      const t = simdi - t0;
      let bitti = true;
      for (const p of pl.parcalar) {
        const v = p.sure <= 1 ? (t >= p.bas ? 1 : 0) : Math.min(1, Math.max(0, (t - p.bas) / p.sure));
        if (v < 1) bitti = false;
        if (v !== p.son) { uygula(p, v); p.son = v; }
      }
      if (bitti) coz(); else requestAnimationFrame(kare);
    };
    requestAnimationFrame(kare);
  });
}

function bitir(pl) {
  pl.parcalar.forEach((p) => uygula(p, 1));
}

function ilerlet(pl, t) {
  for (const p of pl.parcalar) {
    const v = p.sure <= 1 ? (t >= p.bas ? 1 : 0) : Math.min(1, Math.max(0, (t - p.bas) / p.sure));
    if (v !== p.son) { uygula(p, v); p.son = v; }
  }
}

/* Kaydırma çizelgeleri: pencere boyutu değişince çizgi uzunluklarını yeniden ölç */
const canliPlanlar = new Set();
let boyutZamani = 0;
window.addEventListener('resize', () => {
  clearTimeout(boyutZamani);
  boyutZamani = setTimeout(() => {
    canliPlanlar.forEach((pl) => {
      pl.parcalar.forEach((p) => { if (p.tur === 'cizgi') { p.L = ekranUzunlugu(p.el); p.son = undefined; } });
      if (pl.yenile) pl.yenile();
    });
  }, 150);
}, { passive: true });

function zamanCizelgesi(pl) {
  const toplam = pl.sure || 1;
  canliPlanlar.add(pl);
  const gsap = window.gsap;
  if (gsap && typeof gsap.timeline === 'function') {
    const vekil = { p: 0 };
    const tl = gsap.timeline({ paused: true });
    tl.to(vekil, { p: 1, duration: 1, ease: 'none', onUpdate: () => ilerlet(pl, vekil.p * toplam) });
    pl.yenile = () => ilerlet(pl, vekil.p * toplam);
    const oldur = tl.kill.bind(tl);
    tl.kill = (...a) => { canliPlanlar.delete(pl); return oldur(...a); };
    ilerlet(pl, 0);
    return tl;
  }
  let ilerleme = 0;
  pl.yenile = () => ilerlet(pl, ilerleme * toplam);
  ilerlet(pl, 0);
  return {
    progress(p) {
      if (p === undefined) return ilerleme;
      ilerleme = Math.min(1, Math.max(0, p));
      ilerlet(pl, ilerleme * toplam);
      return this;
    },
    duration() { return 1; },
    kill() { canliPlanlar.delete(pl); },
  };
}

function sabitCizelge() {
  return { progress(p) { return p === undefined ? 1 : this; }, duration() { return 1; }, kill() {} };
}

/* cubic-bezier yardımcısı (UI kolaylaştırma) */
function bezier([x1, y1, x2, y2]) {
  const cx = 3 * x1, bx = 3 * (x2 - x1) - cx, ax = 1 - cx - bx;
  const cy = 3 * y1, by = 3 * (y2 - y1) - cy, ay = 1 - cy - by;
  const x = (t) => ((ax * t + bx) * t + cx) * t;
  const y = (t) => ((ay * t + by) * t + cy) * t;
  return (p) => {
    let t = p;
    for (let i = 0; i < 6; i++) {
      const d = (3 * ax * t + 2 * bx) * t + cx;
      if (Math.abs(d) < 1e-6) break;
      t -= (x(t) - p) / d;
    }
    return y(Math.min(1, Math.max(0, t)));
  };
}
const uiKolay = bezier(UI_KOLAY);

function tween(sure, adim) {
  return new Promise((coz) => {
    if (sure <= 0 || document.hidden) { adim(1); coz(); return; }
    const t0 = performance.now();
    const kare = (s) => {
      const p = Math.min(1, (s - t0) / sure);
      adim(p);
      if (p < 1) requestAnimationFrame(kare); else coz();
    };
    requestAnimationFrame(kare);
  });
}

/* ------------------------------------------------------------------ ciz */

export function ciz(hedef, { sure = 600, gecikme = 0, sira = true, scrub = false } = {}) {
  const ogeler = cizimOgeleri(hedef);
  const pl = plan(ogeler, { sure, sira });
  if (hareketAz()) {
    bitir(pl);
    return scrub ? sabitCizelge() : Promise.resolve();
  }
  if (scrub) return zamanCizelgesi(pl);
  return oynat(pl, gecikme);
}

/* ------------------------------------------------------------------ gizle */

export function gizle(hedef) {
  if (hareketAz()) return;
  for (const el of dizi(hedef)) {
    const kok = el;
    plan(cizimOgeleri(kok)).parcalar.forEach((p) => uygula(p, 0));
    const etiket = sembolEtiketi(kok);
    if (etiket) {
      if (etiket.dataset.cizimKirp === undefined) etiket.dataset.cizimKirp = etiket.style.clipPath;
      etiket.style.clipPath = 'inset(-0.3em 100% -0.3em -0.3em)';
    }
  }
}

/* ------------------------------------------------------------------ harfle */

export function harfle(el, { sure = 240, gecikme = 0 } = {}) {
  if (!el) return Promise.resolve();
  const ilk = el.dataset.cizimKirp ?? el.style.clipPath;
  el.dataset.cizimKirp = ilk;
  if (hareketAz()) { el.style.clipPath = ilk; return Promise.resolve(); }
  el.style.clipPath = 'inset(-0.3em 100% -0.3em -0.3em)';
  return new Promise((coz) => setTimeout(coz, gecikme)).then(() => tween(sure, (p) => {
    el.style.clipPath = p >= 1 ? ilk : `inset(-0.3em ${((1 - p) * 100).toFixed(2)}% -0.3em -0.3em)`;
  }));
}

/* ------------------------------------------------------------------ sembolCiz */

function sembolKok(hedef) {
  const el = typeof hedef === 'string' ? document.querySelector(hedef) : hedef;
  if (!el) return null;
  if (el.classList.contains('sembol')) return { kok: el, cizim: el.querySelector('svg') };
  return { kok: el.closest('.sembol') || el, cizim: el };
}

function sembolEtiketi(el) {
  if (!el) return null;
  const k = el.classList && el.classList.contains('sembol') ? el : el.closest && el.closest('.sembol');
  return k ? k.querySelector(':scope > .sembol__etiket') : null;
}

export function sembolCiz(hedef, { sure = 500, gecikme = 0, etiket = true, harfSure = 240 } = {}) {
  const s = sembolKok(hedef);
  if (!s || !s.cizim) return Promise.resolve();
  const pl = plan(cizimOgeleri(s.cizim), { sure, sira: true, harfSure });
  const et = etiket ? (etiket instanceof Element ? etiket : sembolEtiketi(s.kok)) : null;
  if (hareketAz()) {
    bitir(pl);
    if (et) { et.style.clipPath = et.dataset.cizimKirp ?? ''; }
    return Promise.resolve();
  }
  if (et) {
    pl.parcalar.push({ el: et, tur: 'yazi', ilkKirp: et.dataset.cizimKirp ?? et.style.clipPath, bas: sure - 20, sure: harfSure });
    et.dataset.cizimKirp = et.dataset.cizimKirp ?? et.style.clipPath;
    pl.sure = Math.max(pl.sure, sure - 20 + harfSure);
  }
  s.kok.dataset.ciziliyor = 'evet';
  return oynat(pl, gecikme).then(() => { delete s.kok.dataset.ciziliyor; s.kok.dataset.cizildi = 'evet'; });
}

/* ------------------------------------------------------------------ enerji */

export function enerji(yol, { gecikme = 120, sure = 600, scrub = false } = {}) {
  const el = typeof yol === 'string' ? document.querySelector(yol) : yol;
  if (!el) return scrub ? sabitCizelge() : Promise.resolve();
  let bant = el.previousElementSibling;
  if (!bant || !bant.classList.contains('hat-enerji') || bant.dataset.enerjiIcin !== (el.id || 'x')) {
    bant = el.cloneNode(false);
    bant.removeAttribute('id');
    bant.removeAttribute('data-sira');
    bant.removeAttribute('style');
    bant.removeAttribute('stroke-width');
    bant.removeAttribute('stroke-dasharray');
    bant.setAttribute('class', 'hat-enerji');
    bant.setAttribute('aria-hidden', 'true');
    bant.setAttribute('vector-effect', 'non-scaling-stroke');
    bant.dataset.enerjiIcin = el.id || 'x';
    el.parentNode.insertBefore(bant, el);
  }
  const son = () => { el.classList.add('is-enerjili'); bant.classList.add('is-enerjili'); };
  const pl = plan([bant], { sure, sira: false });
  if (hareketAz()) {
    bitir(pl); son();
    return scrub ? sabitCizelge() : Promise.resolve();
  }
  if (scrub) return zamanCizelgesi(pl);
  return oynat(pl, gecikme).then(son);
}

/* ------------------------------------------------------------------ kontak */

export function kontak(hedef, kapali = true, { sure = 180, ses: sesAdi = 'kontak' } = {}) {
  const kok = typeof hedef === 'string' ? document.querySelector(hedef) : hedef;
  if (!kok) return Promise.resolve();
  const bicaklar = [...kok.querySelectorAll('.kontak__bicak')];
  const baglar = [...kok.querySelectorAll('.kontak__bag[data-kayma]')];
  if (!bicaklar.length) return Promise.resolve();
  const hedefler = bicaklar.map((b) => {
    const [mx, my] = (b.getAttribute('data-mafsal') || '0 0').split(/\s+/).map(parseFloat);
    const a0 = parseFloat(b.getAttribute('data-aci') ?? b.getAttribute('data-acik')) || 0;
    const a1 = parseFloat(b.getAttribute(kapali ? 'data-kapali' : 'data-acik')) || 0;
    return { b, mx, my, a0, a1 };
  });
  const kaymalar = baglar.map((g) => {
    const k = parseFloat(g.getAttribute('data-kayma')) || 0;
    const m = /translate\(\s*([-\d.]+)/.exec(g.getAttribute('transform') || '');
    return { g, x0: m ? parseFloat(m[1]) : 0, x1: kapali ? k : 0 };
  });
  const yaz = (p) => {
    hedefler.forEach((h) => {
      const a = h.a0 + (h.a1 - h.a0) * p;
      h.b.setAttribute('transform', `rotate(${+a.toFixed(3)} ${h.mx} ${h.my})`);
      if (p >= 1) h.b.setAttribute('data-aci', String(h.a1));
    });
    kaymalar.forEach((k) => {
      const x = k.x0 + (k.x1 - k.x0) * p;
      if (Math.abs(x) < 1e-3) k.g.removeAttribute('transform'); else k.g.setAttribute('transform', `translate(${+x.toFixed(3)} 0)`);
    });
  };
  const degisim = hedefler.some((h) => h.a0 !== h.a1);
  kok.classList.toggle('is-kapali', !!kapali);
  if (hareketAz() || !degisim) { yaz(1); return Promise.resolve(); }
  if (sesAdi) ses.cal(sesAdi, { aralik: 120 });
  return tween(sure, (p) => yaz(uiKolay(p)));
}

/* ------------------------------------------------------------------ baglantilar */

export function baglantilar(hedef) {
  const s = sembolKok(hedef);
  if (!s || !s.cizim) return [];
  return [...s.cizim.querySelectorAll('.baglanti')].map((c) => {
    const ux = parseFloat(c.getAttribute('cx'));
    const uy = parseFloat(c.getAttribute('cy'));
    const m = c.getScreenCTM();
    let x = ux, y = uy;
    if (m) { x = m.a * ux + m.c * uy + m.e; y = m.b * ux + m.d * uy + m.f; }
    return { yon: c.dataset.baglanti, uc: c.dataset.uc, x, y, ux, uy };
  });
}

/* ------------------------------------------------------------------ vurgula */

export function vurgula(el, { kalici = true, sure = 280, bekle = 1600, cik = 400 } = {}) {
  if (!el) return Promise.resolve();
  el.classList.add('vurgu');
  el.style.removeProperty('--vurgu-o');
  const yukseklik = getComputedStyle(el).getPropertyValue('--vurgu-boy').trim() || '0.42em';
  const kapanis = () => {
    if (kalici) return Promise.resolve();
    return new Promise((coz) => setTimeout(coz, bekle)).then(() => {
      if (!el.animate) { el.classList.remove('vurgu'); return; }
      const a = el.animate([{ '--vurgu-o': 1 }, { '--vurgu-o': 0 }], { duration: cik, easing: 'linear', fill: 'forwards' });
      return a.finished.catch(() => {}).then(() => { a.cancel(); el.classList.remove('vurgu'); });
    });
  };
  if (hareketAz() || !el.animate) return kapanis();
  const a = el.animate(
    [{ backgroundSize: `0% ${yukseklik}` }, { backgroundSize: `100% ${yukseklik}` }],
    { duration: sure, easing: VURGU_KOLAY },
  );
  return a.finished.catch(() => {}).then(kapanis);
}

/* ------------------------------------------------------------------ kota */

function trSayi(v) {
  if (typeof v !== 'number') return String(v ?? '');
  return v.toLocaleString('tr-TR', { maximumFractionDigits: 2 });
}

export function kota(svg, secenek = {}) {
  const sv = typeof svg === 'string' ? document.querySelector(svg) : svg;
  const kok = sv.ownerSVGElement || sv;
  let geo = { ...secenek };
  let deger = secenek.deger;
  const birim = secenek.birim ?? '';
  const yaziVar = secenek.yazi !== false;

  const g = document.createElementNS(SVGNS, 'g');
  g.setAttribute('class', ['kota', secenek.sinif].filter(Boolean).join(' '));
  g.setAttribute('aria-hidden', 'true');
  const yeni = (ad, sinif) => { const e = document.createElementNS(SVGNS, ad); e.setAttribute('class', sinif); g.appendChild(e); return e; };
  const u1 = yeni('path', 'kota__uzatma');
  const u2 = yeni('path', 'kota__uzatma');
  const cz = yeni('path', 'kota__cizgi');
  const o1 = yeni('path', 'kota__ok');
  const o2 = yeni('path', 'kota__ok');
  [u1, u2, cz].forEach((p) => p.setAttribute('vector-effect', 'non-scaling-stroke'));
  const yazi = yaziVar ? yeni('text', 'kota__deger') : null;
  if (yazi) yazi.setAttribute('text-anchor', 'middle');
  sv.appendChild(g);

  const px = () => 1 / olcek(kok);

  function hesapla(G) {
    const s = px();
    const yon = G.yon || (Math.abs(G.y2 - G.y1) > Math.abs(G.x2 - G.x1) ? 'dikey' : 'yatay');
    const bosluk = 2 * s, tasma = 4 * s, okL = 9 * s, okW = 3 * s, yaziArasi = 4 * s;
    let a, b, n, e1, e2;
    if (yon === 'yatay') {
      const c = G.cizgi ?? (Math.min(G.y1, G.y2) - 24 * s);
      const yukari = c < Math.min(G.y1, G.y2) ? -1 : 1;
      a = [G.x1, c]; b = [G.x2, c]; n = [0, yukari];
      e1 = `M${G.x1} ${G.y1 + yukari * bosluk}V${c + yukari * tasma}`;
      e2 = `M${G.x2} ${G.y2 + yukari * bosluk}V${c + yukari * tasma}`;
    } else if (yon === 'dikey') {
      const c = G.cizgi ?? (Math.min(G.x1, G.x2) - 24 * s);
      const sola = c < Math.min(G.x1, G.x2) ? -1 : 1;
      a = [c, G.y1]; b = [c, G.y2]; n = [sola, 0];
      e1 = `M${G.x1 + sola * bosluk} ${G.y1}H${c + sola * tasma}`;
      e2 = `M${G.x2 + sola * bosluk} ${G.y2}H${c + sola * tasma}`;
    } else {
      const dx = G.x2 - G.x1, dy = G.y2 - G.y1, L = Math.hypot(dx, dy) || 1;
      const nx = -dy / L, ny = dx / L, d = G.cizgi ?? 24 * s;
      a = [G.x1 + nx * d, G.y1 + ny * d]; b = [G.x2 + nx * d, G.y2 + ny * d]; n = [nx, ny];
      e1 = `M${G.x1 + nx * bosluk} ${G.y1 + ny * bosluk}L${a[0] + nx * tasma} ${a[1] + ny * tasma}`;
      e2 = `M${G.x2 + nx * bosluk} ${G.y2 + ny * bosluk}L${b[0] + nx * tasma} ${b[1] + ny * tasma}`;
    }
    const dx = b[0] - a[0], dy = b[1] - a[1], L = Math.hypot(dx, dy) || 1;
    const ux = dx / L, uy = dy / L;
    const ok = (p, yonx, yony) => {
      const bx = p[0] - yonx * okL, by = p[1] - yony * okL;
      return `M${p[0]} ${p[1]}L${bx - yony * okW / 2} ${by + yonx * okW / 2}L${bx + yony * okW / 2} ${by - yonx * okW / 2}Z`;
    };
    const orta = [(a[0] + b[0]) / 2 + n[0] * yaziArasi, (a[1] + b[1]) / 2 + n[1] * yaziArasi];
    let aci = Math.atan2(uy, ux) * 180 / Math.PI;
    if (aci >= 90 || aci < -90) aci += 180; // ISO 129: yazı alttan ya da sağdan okunur
    return {
      e1, e2,
      cz: `M${a[0]} ${a[1]}L${b[0]} ${b[1]}`,
      o1: ok(a, -ux, -uy), o2: ok(b, ux, uy),
      orta, aci, boy: 14 * s, yon, n,
    };
  }

  function boya(G) {
    const h = hesapla(G);
    u1.setAttribute('d', h.e1); u2.setAttribute('d', h.e2); cz.setAttribute('d', h.cz);
    o1.setAttribute('d', h.o1); o2.setAttribute('d', h.o2);
    if (yazi) {
      yazi.setAttribute('font-size', String(h.boy));
      const yukari = h.n[0] + h.n[1] < 0;
      yazi.setAttribute('dominant-baseline', yukari ? 'auto' : 'hanging');
      yazi.setAttribute('x', String(h.orta[0])); yazi.setAttribute('y', String(h.orta[1]));
      yazi.setAttribute('transform', h.aci ? `rotate(${h.aci} ${h.orta[0]} ${h.orta[1]})` : '');
      yazi.textContent = `${trSayi(deger)}${birim ? ' ' + birim : ''}`;
    }
  }

  boya(geo);

  let hazir;
  if (hareketAz() || secenek.ciz === false) {
    hazir = Promise.resolve();
  } else {
    g.classList.add('is-ciziliyor');
    const parca = (el, bas, sure) => ({ ...hazirlaOge(el), bas, sure });
    const pl = {
      parcalar: [parca(u1, 0, 160), parca(u2, 0, 160), parca(cz, 160, 220), parca(o1, 380, 1), parca(o2, 380, 1)]
        .concat(yazi ? [parca(yazi, 400, 240)] : []),
    };
    hazir = oynat(pl, secenek.gecikme || 0).then(() => g.classList.remove('is-ciziliyor'));
  }

  return {
    g,
    hazir,
    guncelle(yeniDeger, yeniGeo) {
      if (yeniDeger !== undefined) deger = yeniDeger;
      if (!yeniGeo) { boya(geo); return Promise.resolve(); }
      const eski = { ...geo };
      const hedefGeo = { ...geo, ...yeniGeo };
      if (hareketAz()) { geo = hedefGeo; boya(geo); return Promise.resolve(); }
      const anahtar = ['x1', 'y1', 'x2', 'y2', 'cizgi'].filter((k) => typeof hedefGeo[k] === 'number' && typeof eski[k] === 'number');
      return tween(280, (p) => {
        const e = uiKolay(p);
        const ara = { ...hedefGeo };
        anahtar.forEach((k) => { ara[k] = eski[k] + (hedefGeo[k] - eski[k]) * e; });
        boya(ara);
      }).then(() => { geo = hedefGeo; boya(geo); });
    },
    yaziKutusu() {
      const h = hesapla(geo);
      const m = kok.getScreenCTM();
      if (!m) return null;
      return { x: m.a * h.orta[0] + m.c * h.orta[1] + m.e, y: m.b * h.orta[0] + m.d * h.orta[1] + m.f, aci: h.aci };
    },
    sil() { g.remove(); },
  };
}

/* ------------------------------------------------------------------ kase */

let kaseSayaci = 0;

export function kase(el, { metin = 'TALEP ALINDI', no = '', tarih = '', sure = 420 } = {}) {
  if (!el) return Promise.resolve();
  const id = `kase-${++kaseSayaci}`;
  const kap = document.createElement('div');
  kap.className = 'kase';
  kap.setAttribute('role', 'img');
  kap.setAttribute('aria-label', [metin, no, tarih].filter(Boolean).join(', '));
  const buyuk = (s) => String(s).toLocaleUpperCase('tr-TR');
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  kap.innerHTML = `
<svg viewBox="0 0 200 200" aria-hidden="true" focusable="false">
  <defs>
    <filter id="${id}-f" x="0" y="0" width="100%" height="100%">
      <feTurbulence type="fractalNoise" baseFrequency="0.85" numOctaves="2" seed="${kaseSayaci * 7}" result="n"/>
      <feColorMatrix in="n" type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 -2.6 2.05"/>
    </filter>
    <mask id="${id}-d"><rect width="200" height="200" fill="#fff" filter="url(#${id}-f)"/></mask>
    <mask id="${id}-b"><rect width="200" height="200" fill="#fff"/><rect x="0" y="72" width="200" height="56" fill="#000"/></mask>
  </defs>
  <g class="kase__murekkep" fill="none" stroke="currentColor" mask="url(#${id}-d)">
    <g mask="url(#${id}-b)">
      <circle cx="100" cy="100" r="93" stroke-width="3.2"/>
      <circle cx="100" cy="100" r="86" stroke-width="1.4"/>
    </g>
    <rect x="2" y="74" width="196" height="52" stroke-width="3"/>
    <rect x="8" y="80" width="184" height="40" stroke-width="1.2"/>
    <text class="kase__metin" x="100" y="100" text-anchor="middle" dominant-baseline="central" font-size="25" fill="currentColor" stroke="none">${esc(buyuk(metin))}</text>
    ${no ? `<text class="kase__kucuk" x="100" y="52" text-anchor="middle" dominant-baseline="central" font-size="17" fill="currentColor" stroke="none">${esc(no)}</text>` : ''}
    ${tarih ? `<text class="kase__kucuk" x="100" y="150" text-anchor="middle" dominant-baseline="central" font-size="17" fill="currentColor" stroke="none">${esc(tarih)}</text>` : ''}
  </g>
</svg>`;
  el.appendChild(kap);
  if (hareketAz() || !kap.animate) return Promise.resolve(kap);
  setTimeout(() => ses.cal('kase'), Math.round(sure * 0.55));
  const a = kap.animate([
    { transform: 'rotate(-6deg) scale(1.08)', opacity: 0, offset: 0 },
    { transform: 'rotate(-6deg) scale(1.08)', opacity: 1, offset: 0.25 },
    { transform: 'rotate(-6deg) scale(0.995)', opacity: 1, offset: 0.55 },
    { transform: 'rotate(-6deg) scale(1) translate(1px, 1px)', opacity: 1, offset: 0.7 },
    { transform: 'rotate(-6deg) scale(1)', opacity: 1, offset: 1 },
  ], { duration: sure, easing: 'cubic-bezier(0.23, 1, 0.32, 1)' });
  return a.finished.catch(() => {}).then(() => kap);
}

export default { ciz, enerji, sembolCiz, harfle, kota, vurgula, kase, kontak, gizle, baglantilar, hareketAz };
