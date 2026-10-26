/* sayfa-kurumsal.js: Föy 2, Firma bilgisi (paket P2).
 *
 * İmza: antet açılır. Kapaktaki gerçek boy antetin altı hücresi, sahne yapışkanken (1,2 ekran boyu kaydırma)
 * tam genişlikte altı hücreli içindekiler tablosuna açılır: önce yatay kat, sonra dikey kat; hücre çizgileri
 * yalnızca transform ile uzar (kalınlık sabit), alan etiketleri köşeleriyle birlikte taşınır, değerler sonunda
 * çizici yazısı gibi soldan sağa yazılır, camgöbeği yapı çizgileri mürekkep geldikçe söner. A09 sesi bir kez.
 * Kolofonda (F) aynı motor tersine çalışır: büyük hücreler önce dikey, sonra yatay katlanıp dolu gerçek boy
 * antete döner ve dolu değerler yazılır. İki çizelge de kaydırmaya bağlıdır, iki yönde de çalışır.
 * Diğer bölümler: B bir kez giriş + liste çizgileri, C soldan sağa silme, D durağan, E fotoğraf 6 px iner ve oturur.
 * Azaltılmış hareket / JS yok: hiçbiri çalışmaz, sayfa durağan düzende kalır (CSS).
 */
import { gecisBitti } from './gecis.js';
import { ciz, gizle } from './cizim.js';

const kok = document.documentElement;
const az = () => kok.dataset.hareket === 'az';

const sinir = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
const dilim = (p, a, b) => sinir((p - a) / (b - a));
const kolay = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
const ara = (a, b, t) => a + (b - a) * t;

/* ------------------------------------------------------------------ açılım motoru */
class Acilim {
  /**
   * kap     konum kökü (çizim katmanı buraya eklenir)
   * kucuk   gerçek boy antet (.krm-antet--kucuk)
   * buyuk   büyük hücreler (.krm-antet--buyuk)
   * kilavuz camgöbeği yapı çizgileri (yalnızca kapakta)
   * kucukYazi 'sol' (açılırken söner) | 'harf' (katlanırken yazılır)
   */
  constructor({ kap, kucuk, buyuk, kilavuz = false, kucukYazi = 'sol', bas = 0 }) {
    Object.assign(this, { kap, kucuk, buyuk, kilavuz, kucukYazi, bas });
    this.harfler = [...buyuk.querySelectorAll('.krm-hucre')].map((h) => h.dataset.harf);
    this.katman = document.createElement('div');
    this.katman.className = 'krm-cizim';
    this.katman.setAttribute('aria-hidden', 'true');
    this.yaprak = this.ekle('krm-cizim__yaprak');
    this.cizgiler = [];
    this.harfler.forEach(() => {
      this.cizgiler.push({
        u: this.ekle('krm-cizim__y'), a: this.ekle('krm-cizim__y'),
        s: this.ekle('krm-cizim__d'), r: this.ekle('krm-cizim__d'),
      });
    });
    this.kilavuzlar = [];
    kap.prepend(this.katman);
    this.p = -1;
  }

  ekle(sinif) {
    const d = document.createElement('div');
    d.className = sinif;
    this.katman.appendChild(d);
    return d;
  }

  hucre(kapsayici, harf) { return kapsayici.querySelector(`.krm-hucre[data-harf="${harf}"]`); }

  /* Ölç: iki durumdaki hücre, etiket ve değer kutuları (kap'a göre) */
  olc() {
    const k = this.katman.getBoundingClientRect();
    const rel = (el) => {
      const r = el.getBoundingClientRect();
      return { x: r.left - k.left, y: r.top - k.top, w: r.width, h: r.height };
    };
    this.g = parseFloat(getComputedStyle(this.kucuk).borderTopWidth) || 1.5;
    this.dpr = window.devicePixelRatio || 1;
    this.veri = this.harfler.map((harf) => {
      const hk = this.hucre(this.kucuk, harf);
      const hb = this.hucre(this.buyuk, harf);
      const ek = hk.querySelector('.krm-hucre__etiket');
      const eb = hb.querySelector('.krm-hucre__etiket');
      eb.style.transform = '';
      const fk = parseFloat(getComputedStyle(ek).fontSize) || 13;
      const fb = parseFloat(getComputedStyle(eb).fontSize) || 14;
      return {
        k: rel(hk), b: rel(hb), ek: rel(ek), eb: rel(eb), olcek: fk / fb, et: eb,
        degerK: hk.querySelector('.krm-hucre__deger'),
        degerB: hb.querySelector('.krm-hucre__deger'),
        etK: ek,
      };
    });
    // sütun sırası (büyük durumda): değerler soldan sağa sırayla yazılır
    const xs = [...new Set(this.veri.map((v) => Math.round(v.b.x)))].sort((a, b) => a - b);
    this.veri.forEach((v) => { v.sutun = xs.indexOf(Math.round(v.b.x)); });
    this.sutunSay = xs.length;
    const kutu = (anahtar) => {
      const x = Math.min(...this.veri.map((v) => v[anahtar].x)) - this.g;
      const y = Math.min(...this.veri.map((v) => v[anahtar].y)) - this.g;
      const r = Math.max(...this.veri.map((v) => v[anahtar].x + v[anahtar].w)) + this.g;
      const a = Math.max(...this.veri.map((v) => v[anahtar].y + v[anahtar].h)) + this.g;
      return { x, y, w: r - x, h: a - y };
    };
    this.kutuK = kutu('k');
    this.kutuB = kutu('b');
    this.yaprak.style.width = `${this.kutuB.w}px`;
    this.yaprak.style.height = `${this.kutuB.h}px`;
    if (this.kilavuz) this.kilavuzKur();
    const p = this.p;
    this.p = -1;
    this.ciz(p < 0 ? this.bas : p);
  }

  /* Camgöbeği yapı çizgileri: büyük tablonun çizgi eksenlerinde, köşelerden 14 px taşar */
  kilavuzKur() {
    this.kilavuzlar.forEach((el) => el.remove());
    this.kilavuzlar = [];
    const g = this.g;
    const B = this.kutuB;
    const tas = 14;
    const yatay = new Set();
    const dikey = new Set();
    this.veri.forEach(({ b }) => {
      yatay.add(Math.round((b.y - g / 2) * 2) / 2);
      yatay.add(Math.round((b.y + b.h + g / 2) * 2) / 2);
      dikey.add(Math.round((b.x - g / 2) * 2) / 2);
      dikey.add(Math.round((b.x + b.w + g / 2) * 2) / 2);
    });
    yatay.forEach((y) => {
      const el = document.createElement('div');
      el.className = 'krm-cizim__ky';
      el.style.transform = `translate(${B.x - tas}px, ${y - 0.5}px) scaleX(${(B.w + 2 * tas) / 100})`;
      this.katman.insertBefore(el, this.yaprak);
      this.kilavuzlar.push(el);
    });
    dikey.forEach((x) => {
      const el = document.createElement('div');
      el.className = 'krm-cizim__kd';
      el.style.transform = `translate(${x - 0.5}px, ${B.y - tas}px) scaleY(${(B.h + 2 * tas) / 100})`;
      this.katman.insertBefore(el, this.yaprak);
      this.kilavuzlar.push(el);
    });
  }

  piksel(v) { return Math.round(v * this.dpr) / this.dpr; }

  /* p: 0 = gerçek boy antet, 1 = büyük hücreler */
  ciz(p) {
    p = sinir(p);
    if (Math.abs(p - this.p) < 0.0005) return;
    this.p = p;
    const e1 = kolay(dilim(p, 0.02, 0.42));  // yatay kat
    const e2 = kolay(dilim(p, 0.36, 0.72));  // dikey kat
    const g = this.g;
    const px = (v) => this.piksel(v);
    const durgun = p === 0 || p === 1;

    // kağıt
    const K = this.kutuK;
    const B = this.kutuB;
    const yx = ara(K.x, B.x, e1);
    const yw = ara(K.w, B.w, e1);
    const yy = ara(K.y, B.y, e2);
    const yh = ara(K.h, B.h, e2);
    this.yaprak.style.transform = `translate(${px(yx)}px, ${px(yy)}px) scale(${yw / B.w}, ${yh / B.h})`;

    this.veri.forEach((v, i) => {
      const x = ara(v.k.x, v.b.x, e1);
      const w = ara(v.k.w, v.b.w, e1);
      const y = ara(v.k.y, v.b.y, e2);
      const h = ara(v.k.h, v.b.h, e2);
      const c = this.cizgiler[i];
      const yat = (el, ex, ey, uz) => { el.style.transform = `translate(${px(ex)}px, ${px(ey)}px) scaleX(${uz / 100})`; };
      const dik = (el, ex, ey, uz) => { el.style.transform = `translate(${px(ex)}px, ${px(ey)}px) scaleY(${uz / 100})`; };
      yat(c.u, x - g, y - g, w + 2 * g);
      yat(c.a, x - g, y + h, w + 2 * g);
      dik(c.s, x - g, y - g, h + 2 * g);
      dik(c.r, x + w, y - g, h + 2 * g);

      // etiket: köşesiyle taşınır
      const ex = ara(v.ek.x, v.eb.x, e1) - v.eb.x;
      const ey = ara(v.ek.y, v.eb.y, e2) - v.eb.y;
      const s = ara(v.olcek, 1, e2);
      v.et.style.transform = durgun && p === 1 ? '' : `translate(${px(ex)}px, ${px(ey)}px) scale(${s})`;

      // gerçek boy değerler
      if (this.kucukYazi === 'sol') {
        v.degerK.style.opacity = String(1 - dilim(p, 0, 0.1));
      } else {
        const g2 = 1 - dilim(p, 0, 0.16);
        v.degerK.style.clipPath = g2 >= 1 ? '' : `inset(-0.3em ${((1 - g2) * 100).toFixed(2)}% -0.3em -0.3em)`;
        v.degerK.style.visibility = g2 <= 0 ? 'hidden' : '';
      }
      // büyük değerler: sütun sırasıyla soldan sağa yazılır
      const bas = 0.72 + (v.sutun / Math.max(1, this.sutunSay)) * 0.12;
      const gb = dilim(p, bas, bas + 0.16);
      v.degerB.style.clipPath = gb >= 1 ? '' : `inset(-0.3em ${((1 - gb) * 100).toFixed(2)}% -0.3em -0.3em)`;
      v.degerB.style.visibility = gb <= 0 ? 'hidden' : '';
    });

    if (this.kilavuz) {
      const o = 0.45 * (1 - dilim(p, 0.6, 0.86));
      this.kilavuzlar.forEach((el) => { el.style.opacity = o.toFixed(3); });
    }
  }

  sok() {
    this.katman.remove();
    this.veri?.forEach((v) => {
      v.et.style.transform = '';
      v.degerK.style.opacity = '';
      v.degerK.style.clipPath = '';
      v.degerK.style.visibility = '';
      v.degerB.style.clipPath = '';
      v.degerB.style.visibility = '';
    });
  }
}

/* ------------------------------------------------------------------ yardımcılar */
function birKez(el, esik, fn) {
  const io = new IntersectionObserver((kayit) => {
    kayit.forEach((k) => {
      if (k.isIntersecting) { io.disconnect(); fn(); }
    });
  }, { rootMargin: `0px 0px -${Math.round(100 - esik * 100)}% 0px` });
  io.observe(el);
}

/* Hücre içeriği hücreye sığıyor mu (offset ölçüleri transform'dan etkilenmez) */
function tasmaVar(kapsayici) {
  return [...kapsayici.querySelectorAll('.krm-hucre')].some((h) => {
    const pb = parseFloat(getComputedStyle(h).paddingBottom) || 0;
    const alt = [...h.querySelectorAll('.krm-hucre__baslik, .krm-hucre__ozet')]
      .filter((el) => el.offsetParent !== null)
      .reduce((m, el) => Math.max(m, el.offsetTop + el.offsetHeight), 0);
    return alt + pb > h.clientHeight + 2;
  });
}

/* ------------------------------------------------------------------ bölümler */
function bKur() {
  const ogeler = [...document.querySelectorAll('#faaliyet .krm-giris')];
  ogeler.forEach((el, i) => {
    el.style.setProperty('--krm-gecikme', `${el.matches('li') ? (i - 1) * 80 : 0}ms`);
    birKez(el, 0.85, () => el.classList.add('is-gorunur'));
  });
}

function cKur() {
  const sutun = document.querySelector('#calisma .krm-c__sutun');
  if (!sutun) return;
  const kenar = document.createElement('span');
  kenar.className = 'krm-c__kenar';
  kenar.setAttribute('aria-hidden', 'true');
  sutun.appendChild(kenar);
  const ac = () => sutun.classList.add('is-acik');
  birKez(sutun, 0.7, ac);
  sutun.addEventListener('focusin', ac);
}

function eKur() {
  const foto = document.querySelector('#konum .krm-foto');
  if (!foto) return;
  const atac = foto.querySelector('.krm-atac');
  if (atac) gizle(atac);
  birKez(foto, 0.72, () => {
    foto.classList.add('is-yerinde');
    if (atac) setTimeout(() => ciz(atac, { sure: 520 }), 420);
  });
}

/* ------------------------------------------------------------------ imza: aç ve katla */
function imzaKur() {
  const ST = window.ScrollTrigger;
  const bolum = document.getElementById('unvan');
  const sahne = bolum?.querySelector('.krm-sahne');
  const kucuk = sahne?.querySelector('.krm-antet--kucuk');
  const dizin = sahne?.querySelector('.krm-dizin');
  const alan = document.querySelector('.krm-kolofon__alan');
  const katlanan = alan?.querySelector('.krm-antet--katlanan');
  const dolu = alan?.querySelector('.krm-antet--dolu');
  if (!ST || !sahne || !kucuk || !dizin) return false;

  if (tasmaVar(dizin)) return false; // ekran çok kısa: durağan düzen

  kok.classList.add('krm-hazir');
  const ac = new Acilim({ kap: sahne, kucuk, buyuk: dizin, kilavuz: true, kucukYazi: 'sol' });
  const katla = alan && katlanan && dolu ? new Acilim({ kap: alan, kucuk: dolu, buyuk: katlanan, kucukYazi: 'harf', bas: 1 }) : null;
  ac.olc();

  const ustBosluk = () => parseFloat(getComputedStyle(document.body).paddingTop) || 87;
  let calindi = false;
  let sonP = 0;
  const acTetik = ST.create({
    trigger: bolum,
    start: () => `top ${ustBosluk()}px`,
    end: () => `bottom ${ustBosluk() + sahne.offsetHeight}px`,
    invalidateOnRefresh: true,
    onUpdate(self) {
      const p = sinir(self.progress / 0.86);
      ac.ciz(p);
      bolum.dataset.scVerifyState = `acilim:${p.toFixed(2)}`;
      if (p > 0.03 && sonP <= 0.03 && !calindi && self.direction > 0) { window.tky?.sesCal?.('antet'); calindi = true; }
      if (p === 0) calindi = false;
      sonP = p;
    },
  });
  bolum.dataset.scVerifyState = 'acilim:0.00';

  // içindekilerdeki bir bağlantıya klavyeyle gelinirse tablo tam açık konuma park edilir
  dizin.addEventListener('focusin', () => {
    if (ac.p >= 1) return;
    const hedef = acTetik.start + (acTetik.end - acTetik.start) * 0.9;
    const lenis = window.tky?.lenis;
    if (lenis) lenis.scrollTo(hedef, { immediate: true, force: true });
    else window.scrollTo(0, hedef);
    ST.update();
  });

  /* Kolofon: sığıyorsa bölüm 0,4 ekran boyu yapışkan tutulur ve katlama metin görünürken olur (tutma);
     sığmıyorsa (telefon) katlama bölüm kayarken olur. */
  const fBolum = document.getElementById('antet');
  const fIc = fBolum?.querySelector('.krm-bolum--f');
  const altBosluk = () => parseFloat(getComputedStyle(document.body).paddingBottom) || 14;
  const tutmaKur = () => {
    if (!katla || !fIc) return;
    const gorunur = window.innerHeight - ustBosluk() - altBosluk();
    const ust = alan.getBoundingClientRect().top - fIc.getBoundingClientRect().top;
    const altPay = parseFloat(getComputedStyle(fIc.querySelector('.krm-icerik')).paddingBottom) || 0;
    const h = Math.floor(Math.min(432, gorunur - ust - altPay - 8));
    const tut = h >= 240;
    kok.classList.toggle('krm-tutma', tut);
    if (tut) alan.style.setProperty('--krm-f-alan', `${h}px`);
    else alan.style.removeProperty('--krm-f-alan');
  };
  const tutuyor = () => kok.classList.contains('krm-tutma');

  let katlaTetik = null;
  if (katla) {
    tutmaKur();
    katla.olc();
    const basla = () => {
      if (tutuyor()) return fBolum.getBoundingClientRect().top + window.scrollY - ustBosluk();
      const r = alan.getBoundingClientRect();
      return r.bottom + window.scrollY - window.innerHeight * 0.96;
    };
    const bitir = () => {
      const enCok = document.documentElement.scrollHeight - window.innerHeight - 2;
      if (tutuyor()) return Math.min(basla() + fBolum.offsetHeight - fIc.offsetHeight, enCok);
      return Math.max(basla() + 80, Math.min(basla() + window.innerHeight * 0.4, enCok));
    };
    const ilerleme = (self) => sinir(self.progress / (tutuyor() ? 0.86 : 1));
    katlaTetik = ST.create({
      trigger: fBolum,
      start: basla,
      end: bitir,
      invalidateOnRefresh: true,
      onUpdate(self) {
        const q = ilerleme(self);
        katla.ciz(1 - q);
        fBolum.dataset.scVerifyState = `katlama:${q.toFixed(2)}`;
      },
    });
    katlaTetik.krmIlerleme = () => ilerleme(katlaTetik);
    fBolum.dataset.scVerifyState = 'katlama:0.00';
  }

  // ölçüleri yenile: yazı tipleri, yeniden boyutlandırma
  let bekleyen = 0;
  const yenile = () => {
    cancelAnimationFrame(bekleyen);
    bekleyen = requestAnimationFrame(() => {
      if (tasmaVar(dizin)) { duraganaGec(); return; }
      tutmaKur();
      ac.olc();
      katla?.olc();
      ST.refresh();
    });
  };
  const ro = new ResizeObserver(yenile);
  ro.observe(sahne);
  if (alan) ro.observe(alan);
  ST.addEventListener('refreshInit', () => { ac.p = -1; if (katla) katla.p = -1; });
  ST.addEventListener('refresh', () => { ac.ciz(sinir(acTetik.progress / 0.86)); if (katla && katlaTetik) katla.ciz(1 - katlaTetik.krmIlerleme()); });

  function duraganaGec() {
    ro.disconnect();
    acTetik.kill();
    katlaTetik?.kill();
    ac.sok();
    katla?.sok();
    kok.classList.remove('krm-tam', 'krm-hazir', 'krm-tutma');
    alan?.style.removeProperty('--krm-f-alan');
    delete bolum.dataset.scVerifyState;
    if (fBolum) delete fBolum.dataset.scVerifyState;
    ST.refresh();
  }
  return true;
}

/* ------------------------------------------------------------------ başlat */
async function baslat() {
  try { await document.fonts.ready; } catch { /* yazı tipi olayı yok */ }
  if (az() || !kok.classList.contains('krm-tam')) {
    kok.classList.remove('krm-tam');
    kok.classList.add('sc-ready');
    return;
  }
  const tamam = imzaKur();
  if (!tamam) kok.classList.remove('krm-tam', 'krm-hazir');
  if (kok.classList.contains('krm-hazir')) {
    bKur();
    cKur();
    eKur();
  }
  window.ScrollTrigger?.refresh();
  kok.classList.add('sc-ready');
  // geçişten sonra konumlar kesinleşir (görünüm geçişi sırasında ölçüler kayabilir)
  gecisBitti().then(() => window.ScrollTrigger?.refresh());
}

baslat();
