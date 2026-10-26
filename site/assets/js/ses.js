/* ses.js: ses yöneticisi (paket F2). ES module, bağımlılık yok.
 *
 * Kurallar (spec 2.7): varsayılan kapalı; tercih localStorage 'tky.ses' ('1' açık, '0' kapalı).
 * Ses açılana kadar hiçbir dosya istenmez. Açıkken bile AudioContext ilk kullanıcı etkileşiminde kurulur
 * (tarayıcı otomatik oynatma kuralı). Dosya listesi assets/ses/liste.json içindedir (tools/ses.sh yazar);
 * listede olmayan bir ses için cal() sessizce hiçbir şey yapmaz, ağ isteği de atmaz.
 *
 * API:
 *   ses.acikMi()                   kullanıcı tercihi açık mı
 *   ses.ac()                       aç (bir tıklama/tuş olayının içinde çağırın), Promise
 *   ses.kapat()                    kapat, çalan döngüleri durdur
 *   ses.degistir()                 aç/kapat, yeni durumu döndürür (Promise<boolean>)
 *   ses.cal(ad, {aralik, duzey})   kısa ses; aralik ms: aynı ses en fazla bu sıklıkla (kontak için 120)
 *   ses.dongu(ad, el, {esik})      el en az %25 görünürken döngü çalar; {durdur()} döndürür
 *   ses.surdur(ad, {bekleme})      her çağrıda döngüyü canlı tutar, bekleme ms sessizlikte susar (çizim kalemi)
 *   ses.durum()                    hata ayıklama/QA için anlık durum
 * Olay: document üzerinde 'ses:degisti' (detail: {acik}).
 *
 * Ses adları: sayfa (A01), kalem (A02), kontaktor (A03), motor (A04), ugultu (A05), fan (A06),
 * kontak (A07), kase (A08), antet (A09), katalog (A10).
 */

const ANAHTAR = 'tky.ses';
const KOK = new URL('../ses/', import.meta.url);
const KISA = new Set(['sayfa', 'kontaktor', 'motor', 'kontak', 'kase', 'antet', 'katalog']);

let acik = okuTercih();
let ctx = null;
let ana = null;
let liste = null;          // Promise<object>
let listeVeri = null;      // çözülmüş liste
const tamponlar = new Map(); // ad -> Promise<AudioBuffer|null>
const sonCalma = new Map();
const donguler = new Set();  // {ad, el, gorunur, kaynak, kazanc}
const surenler = new Map();  // ad -> {kaynak, kazanc, zaman}
let kilitBekliyor = false;

function okuTercih() {
  try { return localStorage.getItem(ANAHTAR) === '1'; } catch { return false; }
}
function yazTercih(v) {
  try { localStorage.setItem(ANAHTAR, v ? '1' : '0'); } catch { /* özel pencere */ }
}
function duyur() {
  try { document.dispatchEvent(new CustomEvent('ses:degisti', { detail: { acik } })); } catch { /* yok */ }
}

function baglamKur() {
  if (ctx) return ctx;
  const AC = window.AudioContext || window.webkitAudioContext;
  if (!AC) return null;
  try {
    ctx = new AC();
    ana = ctx.createGain();
    ana.gain.value = 0.9;
    ana.connect(ctx.destination);
  } catch { ctx = null; }
  return ctx;
}

function listeYukle() {
  if (!liste) {
    liste = fetch(new URL('liste.json', KOK), { cache: 'no-cache' })
      .then((r) => (r.ok ? r.json() : {}))
      .catch(() => ({}))
      .then((v) => {
        listeVeri = v && typeof v === 'object' ? (v.sesler || v) : {};
        return listeVeri;
      });
  }
  return liste;
}

function tampon(ad) {
  if (tamponlar.has(ad)) return tamponlar.get(ad);
  const p = listeYukle().then((l) => {
    const dosya = l[ad];
    if (!dosya || !ctx) return null;
    return fetch(new URL(dosya, KOK))
      .then((r) => (r.ok ? r.arrayBuffer() : null))
      .then((b) => (b ? new Promise((ok) => ctx.decodeAudioData(b, ok, () => ok(null))) : null))
      .catch(() => null);
  });
  tamponlar.set(ad, p);
  return p;
}

function kisalariOnYukle() {
  listeYukle().then((l) => { Object.keys(l).forEach((ad) => { if (KISA.has(ad)) tampon(ad); }); });
}

/* Tercih açıkken sayfa yüklendiyse: ilk etkileşimde bağlamı kur. */
function kilitAcmayiBekle() {
  if (kilitBekliyor || ctx) return;
  kilitBekliyor = true;
  const ac = () => {
    window.removeEventListener('pointerdown', ac, true);
    window.removeEventListener('keydown', ac, true);
    kilitBekliyor = false;
    if (!acik) return;
    if (baglamKur()) {
      ctx.resume().catch(() => {});
      kisalariOnYukle();
      donguleriDegerlendir();
    }
  };
  window.addEventListener('pointerdown', ac, true);
  window.addEventListener('keydown', ac, true);
}

function kaynakBaslat(buf, { dongu = false, duzey = 1, sonum = 0 } = {}) {
  const k = ctx.createBufferSource();
  k.buffer = buf;
  k.loop = dongu;
  const g = ctx.createGain();
  const t = ctx.currentTime;
  if (sonum > 0) {
    g.gain.setValueAtTime(0, t);
    g.gain.linearRampToValueAtTime(duzey, t + sonum);
  } else {
    g.gain.value = duzey;
  }
  k.connect(g).connect(ana);
  k.start();
  return { kaynak: k, kazanc: g };
}

function kaynakDurdur(o, sure = 0.2) {
  if (!o || !o.kaynak || !ctx) return;
  const t = ctx.currentTime;
  try {
    o.kazanc.gain.cancelScheduledValues(t);
    o.kazanc.gain.setValueAtTime(o.kazanc.gain.value, t);
    o.kazanc.gain.linearRampToValueAtTime(0, t + sure);
    o.kaynak.stop(t + sure + 0.02);
  } catch { /* zaten durdu */ }
  o.kaynak = null;
}

function donguBaslat(d) {
  if (d.kaynak || !acik || !ctx || ctx.state !== 'running') return;
  d.kaynak = 'bekliyor';
  tampon(d.ad).then((buf) => {
    if (d.kaynak !== 'bekliyor') return;
    if (!buf || !acik || !d.gorunur) { d.kaynak = null; return; }
    Object.assign(d, kaynakBaslat(buf, { dongu: true, duzey: d.duzey, sonum: 0.3 }));
  });
}

function donguleriDegerlendir() {
  donguler.forEach((d) => {
    if (acik && d.gorunur) donguBaslat(d);
    else if (d.kaynak && d.kaynak !== 'bekliyor') kaynakDurdur(d);
    else d.kaynak = null;
  });
}

export const ses = {
  acikMi() { return acik; },

  async ac() {
    acik = true;
    yazTercih(true);
    if (baglamKur()) {
      try { await ctx.resume(); } catch { /* izin yok */ }
      kisalariOnYukle();
    }
    donguleriDegerlendir();
    duyur();
    return true;
  },

  kapat() {
    acik = false;
    yazTercih(false);
    donguler.forEach((d) => { if (d.kaynak && d.kaynak !== 'bekliyor') kaynakDurdur(d); d.kaynak = null; });
    surenler.forEach((s) => kaynakDurdur(s));
    surenler.clear();
    if (ctx && ctx.state === 'running') ctx.suspend().catch(() => {});
    duyur();
    return false;
  },

  async degistir() {
    return acik ? ses.kapat() : ses.ac();
  },

  cal(ad, { aralik = 0, duzey = 1 } = {}) {
    if (!acik) return;
    if (!ctx) { kilitAcmayiBekle(); return; }
    if (ctx.state !== 'running') return;
    if (listeVeri && !listeVeri[ad]) return;
    const simdi = performance.now();
    if (aralik > 0 && simdi - (sonCalma.get(ad) || -1e9) < aralik) return;
    sonCalma.set(ad, simdi);
    tampon(ad).then((buf) => {
      if (!buf || !acik || performance.now() - simdi > 250) return; // geç gelen sesi çalma
      try { kaynakBaslat(buf, { duzey }); } catch { /* yok say */ }
    });
  },

  dongu(ad, el, { esik = 0.25, duzey = 1 } = {}) {
    const d = { ad, el, gorunur: false, kaynak: null, kazanc: null, duzey };
    donguler.add(d);
    let io = null;
    if (el && 'IntersectionObserver' in window) {
      io = new IntersectionObserver((girdiler) => {
        girdiler.forEach((g) => { d.gorunur = g.isIntersecting && g.intersectionRatio >= esik; });
        if (acik && !ctx) kilitAcmayiBekle();
        donguleriDegerlendir();
      }, { threshold: [0, esik] });
      io.observe(el);
    }
    return {
      durdur() {
        if (io) io.disconnect();
        if (d.kaynak && d.kaynak !== 'bekliyor') kaynakDurdur(d);
        d.kaynak = null;
        donguler.delete(d);
      },
    };
  },

  surdur(ad, { bekleme = 160, duzey = 1 } = {}) {
    if (!acik || !ctx || ctx.state !== 'running') { if (acik && !ctx) kilitAcmayiBekle(); return; }
    if (listeVeri && !listeVeri[ad]) return;
    let s = surenler.get(ad);
    if (!s) {
      s = { kaynak: null, kazanc: null, zaman: 0, bekliyor: true };
      surenler.set(ad, s);
      tampon(ad).then((buf) => {
        if (!buf || surenler.get(ad) !== s) { surenler.delete(ad); return; }
        Object.assign(s, kaynakBaslat(buf, { dongu: true, duzey, sonum: 0.08 }), { bekliyor: false });
      });
    }
    clearTimeout(s.zaman);
    s.zaman = setTimeout(() => {
      kaynakDurdur(s, 0.12);
      surenler.delete(ad);
    }, bekleme);
  },

  durum() {
    return {
      acik,
      baglam: ctx ? ctx.state : 'yok',
      liste: listeVeri ? Object.keys(listeVeri) : null,
      donguler: donguler.size,
    };
  },
};

if (acik) kilitAcmayiBekle();

export default ses;
