/* sayfa-pano.js: Föy 5, Isı hesabı (paket P5, spec 3.5). ES module.
 *
 * İmza: çizim formdur. Pano ön ve yan görünüşü gerçek ölçekle (CSS mm) çizilir; H, W, D değerleri ölçü
 * çizgilerinin üzerindeki <input> öğeleridir. Değer değişince ölçek yeniden seçilir, çizim 280 ms'de yeni boya geçer,
 * ölçü çizgileri kota().guncelle ile yeniden çizilir, denge sütunu (duvar) hesabı gösterir.
 *
 * Hesap app/lib/sogutma.php ile aynıdır (IEC/TR 60890). QA sözleşmesi:
 *   window.tkySogutma.hesapla(g) -> {A, Qs, Qk, Qr, kapasite, durum, sigar, ...}
 *   form alanı id'leri: h w d kurulum k qv tu ti
 * Tek rAF sahibi: ScrollTrigger (kahraman paralaksı ve kapanış), çizim tween'leri kendi kısa rAF'ları.
 */
import { kota, ciz, gizle, harfle, hareketAz } from './cizim.js';

const kok = document.documentElement;
const veri = (() => {
  try { return JSON.parse(document.getElementById('pano-veri')?.textContent || '{}'); } catch { return {}; }
})();
const ALAN = veri.alanlar || {};
const KUR = veri.kurulumlar || {};
const BASAMAK = (veri.basamaklar || []).slice().sort((a, b) => a.kapasite - b.kapasite);
const OLCEK = veri.olcekler || [5, 6, 8, 10, 12, 15, 20, 25, 30, 40, 50, 60, 80, 100];
const SAYILAR = ['h', 'w', 'd', 'qv', 'tu', 'ti'];
const NS = 'http://www.w3.org/2000/svg';
const PX_MM = 96 / 25.4;

/* ================================================================ hesap (sogutma.php ile birebir) */

export function alan(kurulum, H, W, D) {
  switch (kurulum) {
    case 1: return 1.8 * H * (W + D) + 1.4 * W * D;
    case 2: return 1.4 * W * (H + D) + 1.8 * D * H;
    case 3: return 1.4 * D * (H + W) + 1.8 * W * H;
    case 4: return 1.4 * H * (W + D) + 1.4 * W * D;
    case 5: return 1.8 * W * H + 1.4 * W * D + D * H;
    case 6: return 1.4 * W * (H + D) + D * H;
    case 7: return 1.4 * W * H + 0.7 * W * D + D * H;
    default: throw new RangeError('Kurulum 1-7 olmalı');
  }
}

export function hesapla(g) {
  const H = +g.h / 1000, W = +g.w / 1000, D = +g.d / 1000;
  const kurulum = parseInt(g.kurulum, 10);
  const A = alan(kurulum, H, W, D);
  const Qs = +g.k * A * (+g.tu - +g.ti);
  const Qk = +g.qv + Qs;
  const r = {
    A, Qs, Qk, Qr: null, kapasite: null, durum: 'yok', sigar: null,
    model: null, kucuk: null, yan_bos: kurulum <= 4, aralik_disi: +g.tu < 20 || +g.tu > 55,
  };
  if (Qk <= 0) return r;
  const Qr = 1.1 * Qk;
  r.Qr = Qr;
  r.durum = 'buyuk';
  let onceki = null;
  for (const b of BASAMAK) {
    if (b.kapasite >= Qr) {
      r.kapasite = b.kapasite;
      r.durum = 'model';
      r.model = b;
      r.kucuk = onceki;
      // mühendis kontrolü: yan yüzeye sığma (ünite yüksekliği <= H, ünite genişliği <= D)
      r.sigar = b.yukseklik <= +g.h && b.genislik <= +g.d;
      return r;
    }
    onceki = b;
  }
  r.kucuk = onceki;
  return r;
}

/* Türkçe sayı: sıfırdan uzağa yuvarlama, binlik nokta, ondalık virgül (sogutma_tr ile aynı) */
export function tr(v, basamak = 0) {
  const c = 10 ** basamak;
  let r = (v < 0 ? -1 : 1) * Math.floor(Math.abs(v) * c + 0.5) / c;
  if (r === 0) r = 0;
  const neg = r < 0;
  const [i, f] = Math.abs(r).toFixed(basamak).split('.');
  return (neg ? '-' : '') + i.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (basamak ? ',' + f : '');
}
const isaretli = (v) => { const s = tr(v); return (s !== '0' && v > 0 ? '+' : '') + s + ' W'; };
const kAnahtar = (k) => String(Math.round(k * 10) / 10);

export function sayi(s) {
  s = String(s ?? '').replace(/[ \s]/g, '');
  if (s === '') return null;
  if (/^-?\d{1,3}(\.\d{3})+(,\d+)?$/.test(s)) s = s.replace(/\./g, '');
  s = s.replace(',', '.');
  if (!/^-?\d+(\.\d+)?$/.test(s)) return null;
  return parseFloat(s);
}

export function hataMetni(ad) {
  const a = ALAN[ad];
  return `${a.etiket} için ${tr(a.min)} ile ${tr(a.max)} ${a.birim} arası bir sayı yazın.`;
}

export function metinler(g, r) {
  const qs = r.Qs;
  const qsYon = tr(qs) === '0' ? '' : (qs > 0 ? ' (içeri)' : ' (dışarı)');
  const m = {
    satirlar: [
      ['A', 'Etkin yüzey', tr(r.A, 2) + ' m²'],
      ['Qv', 'Panodaki ısı kaybı', tr(+g.qv) + ' W'],
      ['Qs', 'Yüzeyden geçen ısı', isaretli(qs) + qsYon],
      ['Qk', 'Gereken soğutma', tr(r.Qk) + ' W'],
      ['Qr', '%10 payla', r.Qr === null ? '-' : tr(r.Qr) + ' W'],
    ],
    oneri: null, kucuk: null, montaj: null, montaj_uyari: false, durum_metni: null, aralik: null,
  };
  if (r.durum === 'model') {
    const b = r.model;
    m.oneri = `Önerilen model: ${b.p} (iç ortam) ya da ${b.ad} (dış ortam), ${tr(b.kapasite)} W.`;
    if (r.kucuk) m.kucuk = `${r.kucuk.ad} yetmez: ${tr(r.kucuk.kapasite)} W veriyor, gereken ${tr(r.Qr)} W.`;
    const yanYuzey = `${tr(+g.h)} x ${tr(+g.d)} mm`;
    if (!r.yan_bos) {
      m.montaj = `Montaj: ${b.ad} yan yüzeye monte edilir. Panonuz sıranın ortasında olduğu için iki yan yüzeyi de komşu panoya dayalı; montaj yeri için bize danışın.`;
      m.montaj_uyari = true;
    } else if (r.sigar) {
      m.montaj = `Montaj: ${b.ad} yüksekliği ${tr(b.yukseklik)} mm, genişliği ${tr(b.genislik)} mm. Panonuzun yan yüzeyi ${yanYuzey}, sığar.`;
    } else {
      const p = [];
      if (b.yukseklik > +g.h) p.push(`yüksekliği ${tr(b.yukseklik)} mm, panonuzun yüksekliği ${tr(+g.h)} mm`);
      if (b.genislik > +g.d) p.push(`genişliği ${tr(b.genislik)} mm, panonuzun derinliği ${tr(+g.d)} mm`);
      m.montaj = `Montaj: ${b.ad} ${p.join('; ')}. Yan yüzeye sığmaz; farklı montaj için bize danışın.`;
      m.montaj_uyari = true;
    }
  } else if (r.durum === 'yok') {
    m.durum_metni = 'Klima gerekmeyebilir: pano yüzeyi bu ısıyı dışarı atabiliyor. Toz ve nem için filtreli fan yeterli olabilir, bize danışın.';
  } else {
    m.durum_metni = 'Gereken soğutma tek ünitenin kapasitesini aşıyor (3.340 W). İki ya da daha fazla ünite veya farklı bir çözüm için projeyi birlikte değerlendirelim.';
  }
  if (r.aralik_disi) m.aralik = 'Dikkat: TPK serisi +20 °C ile +55 °C ortam sıcaklığında çalışır.';

  const gerek = r.Qr === null ? null : tr(r.Qr) + ' W';
  if (r.durum === 'model') m.konu = `Pano soğutma: ${r.model.ad}, gereken ${gerek}`;
  else if (r.durum === 'buyuk') m.konu = `Pano soğutma: gereken ${gerek}`;
  else m.konu = 'Pano soğutma: filtreli fan ya da klima';
  // P8 sözleşmesi: anahtar=değer, ';' ile, yalnızca teknik değerler
  const duz = (v) => String((v < 0 ? -1 : 1) * Math.floor(Math.abs(v) + 0.5));
  m.hesap = `h=${duz(+g.h)};w=${duz(+g.w)};d=${duz(+g.d)};kurulum=${parseInt(g.kurulum, 10)};k=${kAnahtar(+g.k).replace('.', ',')}`
    + `;qv=${duz(+g.qv)};tu=${duz(+g.tu)};ti=${duz(+g.ti)};qk=${duz(r.Qr ?? r.Qk)}`
    + (r.durum === 'model' ? `;model=${r.model.ad}` : '');
  const pano = `${tr(+g.h)} x ${tr(+g.w)} x ${tr(+g.d)} mm`;
  if (r.durum === 'model') m.ozet = `${pano} pano, ${tr(+g.qv)} W ısı kaybı: %10 payla ${gerek} soğutma, ${r.model.p} ya da ${r.model.ad}.`;
  else if (r.durum === 'buyuk') m.ozet = `${pano} pano, ${tr(+g.qv)} W ısı kaybı: %10 payla ${gerek} soğutma, tek ünitenin üstünde.`;
  else m.ozet = `${pano} pano, ${tr(+g.qv)} W ısı kaybı: yüzey ısıyı dışarı atabiliyor, klima gerekmeyebilir.`;
  return m;
}

export function teklifUrl(m) {
  return `${veri.iletisim || 'iletisim.php'}?konu=${encodeURIComponent(m.konu)}&hesap=${encodeURIComponent(m.hesap)}#talep`;
}

/* Çizim yerleşimi (sogutma_yerlesim ile aynı). Kutu px; dönüş px. */
export function yerlesim(g, r, kutuW, kutuH, kenar = { mL: 116, mR: 44, mT: 40, mB: 84, ara: 64 }) {
  const { mL, mR, mT, mB, ara } = kenar;
  let uH = 0, uW = 0;
  if (r && r.durum === 'model') { uH = r.model.yukseklik; uW = r.model.genislik; }
  const H = +g.h, W = +g.w, D = +g.d;
  const komsu = (KUR[g.kurulum] || {}).komsu || 0;
  const komsuPay = komsu ? 0.18 * W : 0;
  const sigW = kutuW - mL - mR - ara;
  const sigH = kutuH - mT - mB;
  let n = 100;
  for (const aday of OLCEK) {
    const k = PX_MM / aday;
    if ((W + komsuPay * (komsu === 2 ? 2 : 1) + Math.max(D, uW)) * k <= sigW && Math.max(H, uH) * k <= sigH) { n = aday; break; }
  }
  const k = PX_MM / n;
  const taban = mT + sigH;
  const kullanilan = (W + komsuPay * (komsu === 2 ? 2 : 1) + Math.max(D, uW)) * k;
  const x0 = mL + Math.max(0, (sigW - kullanilan) / 2) + (komsu ? komsuPay * k : 0);
  const on = { x: x0, y: taban - H * k, w: W * k, h: H * k };
  const yx = x0 + W * k + (komsu === 2 ? komsuPay * k : 0) + ara + Math.max(0, (uW - D) / 2) * k;
  const yan = { x: yx, y: taban - H * k, w: D * k, h: H * k };
  let tpk = null;
  if (uH) {
    const ty = uH <= H ? yan.y + Math.min(150, (H - uH) / 2) * k : taban - uH * k;
    tpk = { x: yan.x + ((D - uW) / 2) * k, y: ty, w: uW * k, h: uH * k };
  }
  return { n, k, taban, on, yan, tpk, komsu, komsuPay: komsuPay * k };
}

window.tkySogutma = { hesapla, metinler, yerlesim, tr };

/* ================================================================ yardımcılar */

const el = (ad, oz = {}, ust = null) => {
  const e = document.createElementNS(NS, ad);
  for (const [k, v] of Object.entries(oz)) e.setAttribute(k, v);
  if (ust) ust.appendChild(e);
  return e;
};
const f = (v) => (Math.round(v * 100) / 100).toString();
const kolay = (() => { // cubic-bezier(0.23, 1, 0.32, 1)
  const [x1, y1, x2, y2] = [0.23, 1, 0.32, 1];
  const cx = 3 * x1, bx = 3 * (x2 - x1) - cx, ax = 1 - cx - bx;
  const cy = 3 * y1, by = 3 * (y2 - y1) - cy, ay = 1 - cy - by;
  return (p) => {
    let t = p;
    for (let i = 0; i < 6; i++) {
      const d = (3 * ax * t + 2 * bx) * t + cx;
      if (Math.abs(d) < 1e-6) break;
      t -= (((ax * t + bx) * t + cx) * t - p) / d;
    }
    t = Math.min(1, Math.max(0, t));
    return ((ay * t + by) * t + cy) * t;
  };
})();
const ara = (a, b, t) => a + (b - a) * t;
const araKutu = (a, b, t) => (a && b ? { x: ara(a.x, b.x, t), y: ara(a.y, b.y, t), w: ara(a.w, b.w, t), h: ara(a.h, b.h, t) } : b);
const ust = () => parseFloat(getComputedStyle(kok).getPropertyValue('--c-ust')) || 87;

/* ================================================================ hesap formu ve canlı çizim */

const form = document.querySelector('[data-hesap]');
const kutu = document.querySelector('[data-kutu]');
const cizimKok = document.querySelector('[data-cizim]');
let svg = kutu?.querySelector('svg');
const olcekAntet = document.querySelector('#antet-bant [data-olcek]');

let sonGecerli = null;   // son geçerli girdi
let sonuc = null;
let L = null;            // ekrandaki yerleşim
let kotalar = null;      // {w, d, h}
let parca = null;        // svg öğeleri
let mod = null;          // 'cizimde' | 'akis'
let cizildi = false;     // ilk çizim yapıldı mı
let tweenNo = 0;

function girdiOku() {
  const g = { ...(sonGecerli || {}) };
  const hatalar = {};
  for (const ad of SAYILAR) {
    const v = sayi(form.elements[ad].value);
    const a = ALAN[ad];
    if (v === null || v < a.min || v > a.max) hatalar[ad] = hataMetni(ad);
    else g[ad] = v;
  }
  const sec = form.querySelector('input[name="kurulum"]:checked');
  g.kurulum = sec ? parseInt(sec.value, 10) : 2;
  g.k = parseFloat(form.elements.k.value);
  return { g, hatalar };
}

function hataGoster(ad, metin) {
  const inp = form.elements[ad];
  const hucre = inp.closest('.hucre');
  const p = document.getElementById(`alan-${ad}-hata`);
  if (metin) {
    hucre.classList.add('hucre--hata');
    inp.setAttribute('aria-invalid', 'true');
    p.textContent = metin;
    p.hidden = false;
  } else {
    hucre.classList.remove('hucre--hata');
    inp.removeAttribute('aria-invalid');
    p.textContent = '';
    p.hidden = true;
  }
}

function sonucYaz(g, r, m) {
  const yer = form.querySelector('[data-sonuc]');
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  let o = '<dl class="denge__liste">';
  for (const [kod, ad, deger] of m.satirlar) {
    o += `<div class="denge__satir denge__satir--${kod.toLowerCase()}"><dt><span class="denge__kod" aria-hidden="true">${kod}</span> ${esc(ad)}</dt><dd class="sayi" data-deger="${kod}">${esc(deger)}</dd></div>`;
  }
  o += `</dl><div class="denge__sonuc" data-durum="${r.durum}">`;
  if (m.oneri) o += `<p class="denge__oneri" data-metin="oneri">${esc(m.oneri)}</p>`;
  if (m.durum_metni) o += `<p class="denge__oneri" data-metin="durum">${esc(m.durum_metni)}</p>`;
  if (m.kucuk) o += `<p class="denge__not" data-metin="kucuk">${esc(m.kucuk)}</p>`;
  const uc = '<span class="revizyon" aria-hidden="true"></span> ';
  if (m.montaj) o += `<p class="denge__not${m.montaj_uyari ? ' denge__not--uyari' : ''}" data-metin="montaj">${m.montaj_uyari ? uc : ''}${esc(m.montaj)}</p>`;
  if (m.aralik) o += `<p class="denge__not denge__not--uyari" data-metin="aralik">${uc}${esc(m.aralik)}</p>`;
  o += '</div>';
  yer.innerHTML = o;

  // duvardaki ısı akışı
  const akis = document.querySelector('[data-akis]');
  if (akis) {
    akis.dataset.yon = tr(r.Qs) === '0' ? 'yok' : (r.Qs > 0 ? 'iceri' : 'disari');
    akis.querySelector('[data-akis-deger]').textContent = `Qs ${isaretli(r.Qs)}${akis.dataset.yon === 'iceri' ? ' içeri' : akis.dataset.yon === 'disari' ? ' dışarı' : ''}`;
  }
  // kurulum formülü
  const formul = form.querySelector('[data-formul]');
  if (formul) formul.textContent = (KUR[g.kurulum] || {}).formul || '';
  // filtreli fan durumu
  const fan = document.querySelector('[data-fan]');
  if (fan) fan.hidden = r.durum !== 'yok';
  // teklif bağlantıları ve kapanış özeti
  const url = teklifUrl(m);
  document.querySelectorAll('[data-teklif]').forEach((a) => a.setAttribute('href', url));
  const ozet = document.querySelector('[data-ozet]');
  if (ozet) ozet.textContent = m.ozet;
  // model tablosunda önerilen satırlar
  document.querySelectorAll('.modeller__satir').forEach((tr_) => {
    const on = r.durum === 'model' && +tr_.dataset.kapasite === r.kapasite;
    tr_.classList.toggle('is-onerilen', on);
    const isaret = tr_.querySelector('[data-onerilen-metin]');
    if (isaret) isaret.textContent = on ? 'Hesaba göre önerilen' : '';
  });
  const bolum = document.getElementById('hesap');
  if (bolum) bolum.dataset.scVerifyState = `Qr:${r.Qr === null ? '-' : Math.round(r.Qr)} durum:${r.durum} olcek:1:${L ? L.n : '-'}`;
}

let duyuruZaman = 0;
function duyur(m) {
  clearTimeout(duyuruZaman);
  duyuruZaman = setTimeout(() => {
    const p = document.querySelector('[data-duyuru]');
    if (!p) return;
    const qr = m.satirlar.find((s) => s[0] === 'Qr')[2];
    p.textContent = [qr !== '-' ? `Gereken soğutma %10 payla ${qr}.` : '', m.oneri || m.durum_metni || '', m.montaj || ''].filter(Boolean).join(' ');
  }, 700);
}

let adresZaman = 0;
function adresYaz(g) {
  clearTimeout(adresZaman);
  adresZaman = setTimeout(() => {
    const q = new URLSearchParams();
    ['h', 'w', 'd', 'kurulum', 'k', 'qv', 'tu', 'ti'].forEach((a) => q.set(a, String(g[a]).replace('.', a === 'k' ? '.' : ',')));
    try { history.replaceState(history.state, '', `${location.pathname}?${q}${location.hash}`); } catch { /* dosya kipi */ }
  }, 500);
}

/* ---------------------------------------------------------------- svg kurulumu */

function svgKur() {
  const w = Math.max(280, Math.round(kutu.clientWidth));
  const h = Math.max(260, Math.round(kutu.clientHeight));
  const baslik = svg.querySelector('title');
  while (svg.firstChild) svg.removeChild(svg.firstChild);
  if (baslik) svg.appendChild(baslik);
  svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
  svg.setAttribute('width', w);
  svg.setAttribute('height', h);
  // yapı ızgarası çizim SVG'inde değil, föyün ortak zemininde (temel.css); burada yerel desen yok
  const g = el('g', { class: 'c-cizim' }, svg);
  const p = {
    w, h,
    zemin: el('path', { class: 'cizgi--ince c-zemin', 'data-sira': 1 }, g),
    zeminTara: el('path', { class: 'cizgi--ince c-tara', 'data-sira': 1 }, g),
    komsu: el('path', { class: 'cizgi--kesik c-komsu', 'data-sira': 2 }, g),
    on: el('rect', { class: 'cizgi c-on', 'data-sira': 2 }, g),
    kapak: el('rect', { class: 'cizgi--ince', 'data-sira': 3 }, g),
    kol: el('rect', { class: 'cizgi--ince', 'data-sira': 3 }, g),
    u1: el('rect', { class: 'cizgi c-kutu', 'data-sira': 4 }, g),
    g1: el('rect', { class: 'cizgi c-kutu', 'data-sira': 4 }, g),
    kutuCiz: el('path', { class: 'cizgi--ince', 'data-sira': 5 }, g),
    u1Yazi: el('text', { class: 'c-yazi', 'data-sira': 6 }, g),
    g1Yazi: el('text', { class: 'c-yazi', 'data-sira': 6 }, g),
    qvYazi: el('text', { class: 'c-yazi c-qv', 'data-sira': 6 }, g),
    yan: el('rect', { class: 'cizgi c-yan', 'data-sira': 2 }, g),
    duvar: el('path', { class: 'cizgi c-duvar', 'data-sira': 3 }, g),
    duvarTara: el('path', { class: 'cizgi--ince c-tara', 'data-sira': 3 }, g),
    tavan: el('path', { class: 'cizgi c-tavan', 'data-sira': 3 }, g),
    tavanTara: el('path', { class: 'cizgi--ince c-tara', 'data-sira': 3 }, g),
    tpk: el('g', { class: 'c-tpk' }, g),
    olcek: el('text', { class: 'c-yazi c-olcek', x: 12, y: h - 12 }, svg),
    arti: el('g', { class: 'c-arti', 'aria-hidden': 'true' }, svg),
  };
  p.tpkCerceve = el('rect', { class: 'cizgi c-tpk-cerceve', 'data-sira': 7 }, p.tpk);
  p.tpkFan = el('path', { class: 'cizgi--ince', 'data-sira': 8 }, p.tpk);
  p.tpkYazi = el('text', { class: 'c-yazi c-etiket', 'data-sira': 9 }, p.tpk);
  p.tpkUyari = el('text', { class: 'c-yazi c-uyari', 'data-sira': 9 }, p.tpk);
  p.artiY = el('path', { class: 'cizgi--yapi' }, p.arti);
  p.artiX = el('path', { class: 'cizgi--yapi' }, p.arti);
  p.artiYazi = el('text', { class: 'c-yazi c-arti-yazi' }, p.arti);
  parca = p;
}

function tara(x1, x2, y, yon = 1, aralik = 10) {
  // yatay çizginin altında (yon 1) ya da üstünde (yon -1) 45° tarama
  let d = '';
  for (let x = x1 + 4; x < x2; x += aralik) d += `M${f(x)} ${f(y + yon * 7)}l7 ${f(-yon * 7)}`;
  return d;
}
function taraDikey(x, y1, y2, aralik = 10) {
  let d = '';
  for (let y = y1 + 4; y < y2; y += aralik) d += `M${f(x)} ${f(y + 7)}l7 -7`;
  return d;
}

/* Yerleşimi svg öğelerine uygular (tween karesinde de çağrılır) */
function uygula(Y, g, r) {
  const p = parca;
  const { on, yan, taban } = Y;
  const tp = Y.tpk;
  const sag = Math.max(yan.x + yan.w, tp ? tp.x + tp.w : 0);
  const sol = on.x - Y.komsuPay - 12;
  p.zemin.setAttribute('d', `M${f(sol)} ${f(taban)}H${f(sag + 16)}`);
  p.zeminTara.setAttribute('d', tara(sol, sag + 16, taban));
  let kd = '';
  if (Y.komsu) {
    const pay = Y.komsuPay;
    kd += `M${f(on.x - pay)} ${f(on.y)}H${f(on.x)}M${f(on.x - pay)} ${f(taban)}H${f(on.x)}`;
    kd += `M${f(on.x - pay + 4)} ${f(on.y - 6)}l-8 ${f((taban - on.y) / 3 + 6)}l8 ${f((taban - on.y) / 3)}l-8 ${f((taban - on.y) / 3)}`;
    if (Y.komsu === 2) {
      const x = on.x + on.w;
      kd += `M${f(x)} ${f(on.y)}H${f(x + pay)}M${f(x)} ${f(taban)}H${f(x + pay)}`;
      kd += `M${f(x + pay - 4)} ${f(on.y - 6)}l8 ${f((taban - on.y) / 3 + 6)}l-8 ${f((taban - on.y) / 3)}l8 ${f((taban - on.y) / 3)}`;
    }
  }
  p.komsu.setAttribute('d', kd || 'M0 0');
  p.komsu.style.display = kd ? '' : 'none';
  const set = (e, b) => { e.setAttribute('x', f(b.x)); e.setAttribute('y', f(b.y)); e.setAttribute('width', f(Math.max(0, b.w))); e.setAttribute('height', f(Math.max(0, b.h))); };
  set(p.on, on);
  const i = Math.min(8, on.w * 0.03);
  set(p.kapak, { x: on.x + i, y: on.y + i, w: on.w - 2 * i, h: on.h - 2 * i });
  const kolW = Math.max(3, on.w * 0.025), kolH = Math.max(12, on.h * 0.08);
  set(p.kol, { x: on.x + on.w - i - kolW - 4, y: on.y + on.h * 0.45, w: kolW, h: kolH });
  const u1 = { x: on.x + on.w * 0.1, y: on.y + on.h * 0.09, w: on.w * 0.34, h: on.h * 0.17 };
  const g1 = { x: on.x + on.w * 0.1, y: on.y + on.h * 0.33, w: on.w * 0.24, h: on.h * 0.1 };
  set(p.u1, u1); set(p.g1, g1);
  p.kutuCiz.setAttribute('d', `M${f(u1.x)} ${f(u1.y + u1.h)}L${f(u1.x + u1.w)} ${f(u1.y)}M${f(g1.x)} ${f(g1.y + g1.h)}L${f(g1.x + g1.w)} ${f(g1.y)}`);
  const yaziVar = u1.w >= 34 && u1.h >= 22;
  p.u1Yazi.setAttribute('x', f(u1.x)); p.u1Yazi.setAttribute('y', f(u1.y - 5)); p.u1Yazi.textContent = yaziVar ? '-U1' : '';
  p.g1Yazi.setAttribute('x', f(g1.x)); p.g1Yazi.setAttribute('y', f(g1.y - 5)); p.g1Yazi.textContent = yaziVar ? '-G1' : '';
  p.qvYazi.setAttribute('x', f(on.x + on.w * 0.1)); p.qvYazi.setAttribute('y', f(on.y + on.h * 0.56));
  p.qvYazi.textContent = on.w >= 70 && on.h >= 120 ? `Qv ${tr(+g.qv)} W` : '';
  set(p.yan, yan);
  const kur = KUR[g.kurulum] || {};
  if (kur.duvar) {
    const dx = sag + 4;
    p.duvar.setAttribute('d', `M${f(dx)} ${f(Math.min(yan.y, tp ? tp.y : yan.y) - 12)}V${f(taban)}`);
    p.duvarTara.setAttribute('d', taraDikey(dx, Math.min(yan.y, tp ? tp.y : yan.y) - 12, taban));
  }
  p.duvar.style.display = kur.duvar ? '' : 'none';
  p.duvarTara.style.display = kur.duvar ? '' : 'none';
  if (!kur.tavan) {
    p.tavan.setAttribute('d', `M${f(on.x - 6)} ${f(on.y - 4)}H${f(on.x + on.w + 6)}M${f(yan.x - 6)} ${f(yan.y - 4)}H${f(yan.x + yan.w + 6)}`);
    p.tavanTara.setAttribute('d', tara(on.x - 6, on.x + on.w + 6, on.y - 4, -1) + tara(yan.x - 6, yan.x + yan.w + 6, yan.y - 4, -1));
  }
  p.tavan.style.display = kur.tavan === false ? '' : 'none';
  p.tavanTara.style.display = kur.tavan === false ? '' : 'none';
  // ünite
  if (tp && r.model) {
    p.tpk.style.display = '';
    set(p.tpkCerceve, tp);
    const uyum = r.sigar && r.yan_bos;
    p.tpkCerceve.setAttribute('class', `${uyum ? 'cizgi' : 'cizgi--kesik'} c-tpk-cerceve`);
    p.tpk.classList.toggle('is-sigmaz', !uyum);
    const rr = Math.min(tp.w, tp.h) * 0.22;
    const cx = tp.x + tp.w / 2, cy = tp.y + tp.h * 0.42;
    p.tpkFan.setAttribute('d', `M${f(cx - rr)} ${f(cy)}a${f(rr)} ${f(rr)} 0 1 0 ${f(2 * rr)} 0a${f(rr)} ${f(rr)} 0 1 0 ${f(-2 * rr)} 0M${f(cx - rr)} ${f(cy)}H${f(cx + rr)}M${f(cx)} ${f(cy - rr)}V${f(cy + rr)}`);
    const ty = Math.min(tp.y, yan.y) - 10;
    p.tpkYazi.setAttribute('x', f(Math.min(tp.x, yan.x))); p.tpkYazi.setAttribute('y', f(ty));
    p.tpkYazi.textContent = `-E1 ${r.model.ad}`;
    p.tpkUyari.setAttribute('x', f(Math.min(tp.x, yan.x))); p.tpkUyari.setAttribute('y', f(ty - 17));
    p.tpkUyari.textContent = uyum ? '' : (r.yan_bos ? 'sığmaz' : 'yan yüzey dolu');
  } else {
    p.tpk.style.display = 'none';
  }
  p.olcek.textContent = `Ölçek 1:${Y.n}`;
}

/* Ölçü çizgisi geometrileri */
function kotaGeo(Y) {
  const { on, yan, taban } = Y;
  return {
    w: { x1: on.x, y1: taban, x2: on.x + on.w, y2: taban, cizgi: taban + 30, yon: 'yatay' },
    d: { x1: yan.x, y1: taban, x2: yan.x + yan.w, y2: taban, cizgi: taban + 30, yon: 'yatay' },
    h: { x1: on.x, y1: on.y, x2: on.x, y2: taban, cizgi: on.x - Y.komsuPay - 30, yon: 'dikey' },
  };
}

/* H W D girişlerini ölçü çizgilerinin üzerine konumlar */
function girisKonumla(Y) {
  if (mod !== 'cizimde') return;
  const G = kotaGeo(Y);
  const kutuR = kutu.getBoundingClientRect();
  const kokR = cizimKok.getBoundingClientRect();
  const ox = kutuR.left - kokR.left, oy = kutuR.top - kokR.top;
  const yer = {
    w: [ox + (G.w.x1 + G.w.x2) / 2, oy + G.w.cizgi + 6, 'orta'],
    d: [ox + (G.d.x1 + G.d.x2) / 2, oy + G.d.cizgi + 6, 'orta'],
    h: [ox + G.h.cizgi - 8, oy + (G.h.y1 + G.h.y2) / 2, 'sag'],
  };
  for (const [ad, [x, y, hiza]] of Object.entries(yer)) {
    const hucre = form.elements[ad].closest('.hucre');
    hucre.style.setProperty('--x', `${f(x)}px`);
    hucre.style.setProperty('--y', `${f(y)}px`);
    hucre.dataset.hiza = hiza;
  }
}

function kotaKur(Y, g, ciz_) {
  kotalar && Object.values(kotalar).forEach((k) => k.sil());
  const G = kotaGeo(Y);
  const yazi = mod !== 'cizimde';
  const deger = { w: +g.w, d: +g.d, h: +g.h };
  kotalar = {};
  let gecikme = 0;
  for (const ad of ['w', 'd', 'h']) {
    kotalar[ad] = kota(svg, { ...G[ad], deger: deger[ad], yazi, ciz: ciz_, gecikme, sinif: `c-kota c-kota--${ad}` });
    gecikme += 120;
  }
  return Promise.all(Object.values(kotalar).map((k) => k.hazir));
}

/* Yeni girdiyle çizimi günceller (280 ms tween, azaltılmış harekette anında) */
function cizimGuncelle(g, r, { aninda = false } = {}) {
  if (!parca) return;
  const Y1 = yerlesim(g, r, parca.w, parca.h);
  const Y0 = L;
  L = Y1;
  if (olcekAntet) olcekAntet.textContent = `1:${Y1.n}`;
  const baslik = svg.querySelector('title');
  if (baslik) baslik.textContent = `Pano ön ve yan görünüşü, ölçek 1:${Y1.n}: yükseklik ${tr(+g.h)} mm, genişlik ${tr(+g.w)} mm, derinlik ${tr(+g.d)} mm${r.model ? `, yan yüzeyde ${r.model.ad}` : ''}`;
  if (kotalar) {
    const G = kotaGeo(Y1);
    const deger = { w: +g.w, d: +g.d, h: +g.h };
    for (const ad of ['w', 'd', 'h']) kotalar[ad].guncelle(deger[ad], G[ad]);
  }
  if (!Y0 || aninda || hareketAz()) {
    uygula(Y1, g, r);
    girisKonumla(Y1);
    return;
  }
  const no = ++tweenNo;
  const t0 = performance.now();
  const kare = (s) => {
    if (no !== tweenNo) return;
    const p = Math.min(1, (s - t0) / 280);
    const e = kolay(p);
    const Yt = {
      ...Y1,
      taban: ara(Y0.taban, Y1.taban, e),
      komsuPay: ara(Y0.komsuPay, Y1.komsuPay, e),
      on: araKutu(Y0.on, Y1.on, e),
      yan: araKutu(Y0.yan, Y1.yan, e),
      tpk: Y1.tpk ? araKutu(Y0.tpk || Y1.tpk, Y1.tpk, e) : null,
    };
    uygula(Yt, g, r);
    girisKonumla(Yt);
    if (p < 1) requestAnimationFrame(kare);
  };
  requestAnimationFrame(kare);
}

function hesapla_(olay = null) {
  const { g, hatalar } = girdiOku();
  for (const ad of SAYILAR) {
    // yazarken hata gösterme; alan terk edilince (change) ya da hata zaten görünüyorsa güncelle
    const gorunur = !document.getElementById(`alan-${ad}-hata`).hidden;
    if (!hatalar[ad]) hataGoster(ad, null);
    else if (olay === 'change' || olay === 'submit' || gorunur) hataGoster(ad, hatalar[ad]);
  }
  sonGecerli = g;
  sonuc = hesapla(g);
  const m = metinler(g, sonuc);
  cizimGuncelle(g, sonuc);
  sonucYaz(g, sonuc, m);
  if (olay) { duyur(m); adresYaz(g); }
  return Object.keys(hatalar);
}

function modSec() {
  return kutu.clientWidth >= 560 ? 'cizimde' : 'akis';
}

function cizimBaslat() {
  if (!form || !kutu || !svg) return;
  // ilk değerler: sunucunun yazdığı (geçerli) değerler
  sonGecerli = {};
  for (const ad of SAYILAR) {
    const v = sayi(form.elements[ad].value);
    const a = ALAN[ad];
    sonGecerli[ad] = v === null || v < a.min || v > a.max ? a.varsayilan : v;
  }
  mod = modSec();
  cizimKok.classList.add('is-canli');
  cizimKok.dataset.mod = mod;
  svgKur();
  hesapla_();
  const az = hareketAz();
  if (!az) gizle(svg.querySelector('.c-cizim'));
  cizimKok.classList.add('is-hazir');

  const ilkCizim = () => {
    if (cizildi) return;
    cizildi = true;
    const bitti = Promise.all([
      az ? Promise.resolve() : ciz(svg.querySelector('.c-cizim'), { sure: 900 }),
      new Promise((c) => setTimeout(c, az ? 0 : 520)).then(() => kotaKur(L, sonGecerli, !az)),
    ]);
    bitti.then(() => cizimKok.classList.add('is-olculu'));
  };
  cizimKok.addEventListener('focusin', ilkCizim);
  if (az || !('IntersectionObserver' in window)) ilkCizim();
  else {
    const io = new IntersectionObserver((k) => {
      if (k.some((x) => x.isIntersecting)) { io.disconnect(); ilkCizim(); }
    }, { rootMargin: '0px 0px -25% 0px' });
    io.observe(kutu);
  }

  // olaylar
  form.addEventListener('input', (e) => { if (e.target.matches('input, select')) hesapla_('input'); });
  form.addEventListener('change', () => hesapla_('change'));
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const hatali = hesapla_('submit');
    if (hatali.length) form.elements[hatali[0]].focus();
  });
  form.addEventListener('keydown', (e) => {
    const inp = e.target;
    if (!(inp instanceof HTMLInputElement) || !SAYILAR.includes(inp.name)) return;
    if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
    e.preventDefault();
    const a = ALAN[inp.name];
    const v = sayi(inp.value) ?? a.varsayilan;
    const adim = a.adim * (e.shiftKey ? 10 : 1) * (e.key === 'ArrowUp' ? 1 : -1);
    const yeni = Math.min(a.max, Math.max(a.min, Math.round((v + adim) / a.adim) * a.adim));
    inp.value = String(yeni);
    hesapla_('input');
  });
  // ölçü girişlerinde yazı genişliği değere uysun
  SAYILAR.slice(0, 3).forEach((ad) => {
    const inp = form.elements[ad];
    const boy = () => inp.style.setProperty('--hane', Math.max(3, inp.value.length));
    boy();
    inp.addEventListener('input', boy);
  });

  // kutu boyutu ve mod değişimi
  let rz = 0;
  const ro = new ResizeObserver(() => {
    clearTimeout(rz);
    rz = setTimeout(() => {
      const yeniMod = modSec();
      const w = Math.round(kutu.clientWidth), h = Math.round(kutu.clientHeight);
      if (parca && w === parca.w && h === parca.h && yeniMod === mod) return;
      mod = yeniMod;
      cizimKok.dataset.mod = mod;
      svgKur();
      L = null;
      const g = sonGecerli;
      cizimGuncelle(g, sonuc, { aninda: true });
      if (cizildi) kotaKur(L, g, false);
    }, 120);
  });
  ro.observe(kutu);

  artiKur();
}

/* İnce işaretçide yapı çizgisi artı imleci: işaretçinin pano köşesine uzaklığı (mm) */
function artiKur() {
  if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
  svg.addEventListener('pointermove', (e) => {
    if (!L || !parca) return;
    const m = svg.getScreenCTM();
    if (!m) return;
    const x = (e.clientX - m.e) / m.a, y = (e.clientY - m.f) / m.d;
    const { on, taban, k } = L;
    const icinde = x >= on.x && x <= on.x + on.w && y >= on.y && y <= taban;
    parca.arti.classList.toggle('is-acik', icinde);
    if (!icinde) return;
    parca.artiY.setAttribute('d', `M${f(x)} 0V${parca.h}`);
    parca.artiX.setAttribute('d', `M0 ${f(y)}H${parca.w}`);
    parca.artiYazi.setAttribute('x', f(x + 10));
    parca.artiYazi.setAttribute('y', f(y - 10));
    parca.artiYazi.textContent = `x ${tr((x - on.x) / k)}  y ${tr((taban - y) / k)}`;
  });
  svg.addEventListener('pointerleave', () => parca?.arti.classList.remove('is-acik'));
}

/* ================================================================ kahraman: çizim, ünite, paralaks */

function kahramanKur() {
  const sahne = document.querySelector('[data-sahne]');
  if (!sahne) return;
  const az = hareketAz();
  const cizimler = [...sahne.querySelectorAll('.sahne__cizim, .sahne__cikis, .sahne__ortam-kota')];
  // ürün dağıtımından (/3) gelen hat: önce gelen kesme noktası çizilir, sonra pano
  const kesme = document.querySelector('.ic-dis__kesme');
  const kesmeSvg = kesme?.querySelector('svg');
  const kesmeRef = kesme?.querySelector('.kesme-noktasi__ref');
  if (!az) { cizimler.forEach((c) => gizle(c)); if (kesmeSvg) gizle(kesmeSvg); if (kesmeRef) { kesmeRef.dataset.cizimKirp = ''; kesmeRef.style.clipPath = 'inset(-0.3em 100% -0.3em -0.3em)'; } }
  sahne.classList.add('is-hazir');
  document.querySelector('.ic-dis')?.classList.add('is-hazir');
  if (!az) {
    (kesmeSvg ? ciz(kesmeSvg, { sure: 300 }).then(() => harfle(kesmeRef)) : Promise.resolve())
      .then(() => {
        setTimeout(() => sahne.classList.add('is-tpk'), 700);
        return Promise.all([
          ciz(sahne.querySelector('.sahne__cizim'), { sure: 1100 }),
          ciz(sahne.querySelector('.sahne__ortam-kota'), { sure: 520, gecikme: 600 }),
          ciz(sahne.querySelector('.sahne__cikis'), { sure: 520, gecikme: 1100 }),
        ]);
      });
  } else {
    sahne.classList.add('is-tpk');
  }

  const g = window.gsap, ST = window.ScrollTrigger;
  const tpk = sahne.querySelector('[data-tpk]');
  if (!g || !ST || az || !tpk) { sahne.dataset.scVerifyState = 'tpk:0'; return; }
  const mm = g.matchMedia();
  mm.add('(min-width: 768px)', () => {
    g.to(tpk, {
      y: () => sahne.offsetHeight * 0.1,
      ease: 'none',
      scrollTrigger: {
        trigger: '#ic-dis',
        start: () => `top ${ust()}px`,
        end: 'bottom top',
        scrub: 0.6,
        invalidateOnRefresh: true,
        onUpdate: (s) => { sahne.dataset.scVerifyState = `tpk:${s.progress.toFixed(2)}`; },
      },
    });
  });
}

/* ================================================================ kapanış: duvar sağ kenara kayar */

function kapanisKur() {
  const sahne = document.querySelector('[data-kapanis]');
  if (!sahne) return;
  const duvar = sahne.querySelector('[data-duvar]');
  const dis = sahne.querySelector('[data-dis]');
  const izgara = sahne.querySelector('.teklif__izgara');
  const bolum = document.getElementById('teklif');
  const g = window.gsap, ST = window.ScrollTrigger;
  if (!g || !ST || hareketAz()) { if (bolum) bolum.dataset.scVerifyState = 'duvar:0'; return; }
  const dugme = sahne.querySelector('.teklif__eylem .dugme');
  const mm = g.matchMedia();
  mm.add('(min-width: 768px)', () => {
    sahne.classList.add('is-kapanis');
    const mesafe = () => sahne.clientWidth / 2 - 2;
    const tl = g.timeline({
      scrollTrigger: {
        trigger: sahne,
        start: () => `top ${ust() + 24}px`,
        end: () => `+=${Math.round(window.innerHeight * 0.4)}`,
        pin: true,
        scrub: 0.6,
        invalidateOnRefresh: true,
        onUpdate: (s) => {
          const p = s.progress;
          dis.style.visibility = p > 0.97 ? 'hidden' : '';
          if (bolum) bolum.dataset.scVerifyState = `duvar:${p.toFixed(2)}`;
          if (p > 0.96) dugme?.classList.add('is-supur');
          else if (p < 0.5) dugme?.classList.remove('is-supur');
        },
      },
    });
    tl.to(duvar, { x: mesafe, ease: 'none', duration: 1 }, 0)
      .to(dis, { x: mesafe, ease: 'none', duration: 1 }, 0)
      .to(dis, { opacity: 0, ease: 'none', duration: 0.3 }, 0.62)
      .fromTo(izgara, { clipPath: 'inset(0% 50% 0% 0%)' }, { clipPath: 'inset(0% 0% 0% 0%)', ease: 'none', duration: 1 }, 0);
    return () => { sahne.classList.remove('is-kapanis'); dis.style.visibility = ''; };
  });
}

/* ================================================================ başlat */

cizimBaslat();
kahramanKur();
kapanisKur();
