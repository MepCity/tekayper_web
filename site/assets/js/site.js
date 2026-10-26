/* site.js: her föyde yüklenen ortak modül (paket F1).
 *
 * Kurar: GSAP eklenti kaydı, Lenis (yalnızca data-lenis olan sayfalarda), sol cetvelde etkin satır,
 * Ürünler alt menüsü, telefon föy listesi (dialog), ses düğmeleri (F2 ses.js ile), çapraz referanslar, föy geçişi.
 *
 * Sayfa modülleri için ortak nesne: window.tky
 *   tky.hareketAz()            azaltılmış hareket mi (data-hareket="az")
 *   tky.lenis                  Lenis örneği ya da null
 *   tky.kaydir(hedef, {ofset}) bölüme kaydırır (Lenis varsa onunla), Promise
 *   tky.sesAcikMi()            ses tercihi açık mı
 *   tky.sesCal(ad, secenek)    ses açıksa F2 ses.js ile çalar; kapalıysa hiçbir dosya istenmez
 *   tky.ses()                  Promise<ses nesnesi|null> (F2 ses.js; yüklenemezse null)
 * Olaylar: document 'ses:degisti' (F2), window 'tky:liste' (liste.js), window 'tky:satir' (etkin satır harfi).
 * Hazır kancası: sayfa yüklenip yazı tipleri çözülünce <html> 'sc-ready' sınıfını alır (scroll-craft shoot.mjs bekler).
 */
import { caprazKur, hedefVurgula } from './capraz.js';
import { gecisKur } from './gecis.js';

const kok = document.documentElement;
const SES_ANAHTAR = 'tky.ses';

const hareketAz = () => kok.dataset.hareket === 'az';

/* ---------------------------------------------------------------- ses */
let sesModul = null;
function sesYukle() {
  if (!sesModul) {
    sesModul = import('./ses.js')
      .then((m) => m.ses || m.default || null)
      .catch(() => null);
  }
  return sesModul;
}
function sesAcikMi() {
  try { return localStorage.getItem(SES_ANAHTAR) === '1'; } catch { return false; }
}
function sesArayuz(acik) {
  if (acik) kok.dataset.ses = 'acik'; else delete kok.dataset.ses;
  document.querySelectorAll('[data-ses-dugme]').forEach((d) => {
    // görünür yazısı olan düğmeler (antet, föy listesi) adını yazıdan alır, durumu aria-pressed taşır;
    // yalnızca simgeli başlık düğmesi aria-label ile eylemi söyler (axe label-content-name-mismatch)
    if (d.querySelector('[data-ses-metin]')) d.setAttribute('aria-pressed', acik ? 'true' : 'false');
    else d.setAttribute('aria-label', acik ? 'Sesi kapat' : 'Sesi aç');
  });
  document.querySelectorAll('[data-ses-metin]').forEach((m) => {
    const dizin = m.closest('.foy-dizini__ses');
    m.textContent = dizin ? (acik ? 'Ses açık' : 'Ses kapalı') : (acik ? 'Açık' : 'Kapalı');
  });
}
async function sesDegistir() {
  const yeni = !sesAcikMi();
  sesArayuz(yeni);
  const s = await sesYukle();
  if (s) {
    if (yeni) await s.ac?.(); else s.kapat?.();
  } else {
    try { localStorage.setItem(SES_ANAHTAR, yeni ? '1' : '0'); } catch { /* özel pencere */ }
  }
}
function sesKur() {
  sesArayuz(sesAcikMi());
  document.addEventListener('click', (e) => {
    const d = e.target.closest('[data-ses-dugme]');
    if (d) { e.preventDefault(); sesDegistir(); }
  });
  document.addEventListener('ses:degisti', (e) => sesArayuz(!!e.detail?.acik));
  if (sesAcikMi()) sesYukle();
}

/* ---------------------------------------------------------------- GSAP ve Lenis */
let lenis = null;
function hareketKur() {
  const g = window.gsap;
  if (g) {
    const eklentiler = ['ScrollTrigger', 'DrawSVGPlugin', 'Flip'].map((n) => window[n]).filter(Boolean);
    if (eklentiler.length) g.registerPlugin(...eklentiler);
  }
  if (!kok.hasAttribute('data-lenis') || !window.Lenis || hareketAz()) return;
  lenis = new window.Lenis({ autoRaf: !g, lerp: 0.12, wheelMultiplier: 1 });
  if (g && window.ScrollTrigger) {
    lenis.on('scroll', window.ScrollTrigger.update);
    g.ticker.add((t) => lenis.raf(t * 1000));
    g.ticker.lagSmoothing(0);
  }
  window.tky.lenis = lenis;
}

function ustBosluk() {
  const v = getComputedStyle(kok).getPropertyValue('scroll-padding-top');
  return parseFloat(v) || 100;
}
function kaydir(hedef, { ofset } = {}) {
  const el = typeof hedef === 'string' ? document.querySelector(hedef) : hedef;
  if (!el) return Promise.resolve();
  const off = ofset ?? ustBosluk();
  return new Promise((bitti) => {
    const y = Math.max(0, el.getBoundingClientRect().top + window.scrollY - off);
    if (lenis) {
      // sayısal konum: Lenis öğeye verilirse öğenin scroll-margin-top ve html scroll-padding-top değerlerini de ekler (çift ofset)
      lenis.scrollTo(y, { duration: hareketAz() ? 0 : 0.9, onComplete: bitti });
      return;
    }
    const once = Math.abs(window.scrollY - y);
    if (once < 2) { bitti(); return; }
    let bitmis = false;
    const son = () => { if (!bitmis) { bitmis = true; window.removeEventListener('scrollend', son); bitti(); } };
    window.addEventListener('scrollend', son, { once: true });
    setTimeout(son, hareketAz() ? 50 : 900);
    window.scrollTo({ top: y, behavior: hareketAz() ? 'auto' : 'smooth' });
  });
}

/* ---------------------------------------------------------------- sol cetvel: etkin satır */
function cetvelKur() {
  const harfler = new Map();
  document.querySelectorAll('[data-satir-baglanti]').forEach((a) => harfler.set(a.dataset.satirBaglanti, a));
  const bolumler = [...document.querySelectorAll('main [data-satir]')];
  if (!bolumler.length || !harfler.size) return;
  const gorunen = new Set();
  let etkin = null;
  const sec = () => {
    // ekranın %40 çizgisini kesen bölümlerden belge sırasında ilki
    const aday = bolumler.find((b) => gorunen.has(b)) || null;
    const harf = aday?.dataset.satir || null;
    if (harf === etkin) return;
    etkin = harf;
    harfler.forEach((a, h) => {
      const on = h === harf;
      a.classList.toggle('is-aktif', on);
      if (on) a.setAttribute('aria-current', 'location'); else a.removeAttribute('aria-current');
    });
    window.dispatchEvent(new CustomEvent('tky:satir', { detail: { harf } }));
  };
  const io = new IntersectionObserver((kayitlar) => {
    kayitlar.forEach((k) => (k.isIntersecting ? gorunen.add(k.target) : gorunen.delete(k.target)));
    sec();
  }, { rootMargin: '-40% 0px -59% 0px' });
  bolumler.forEach((b) => io.observe(b));
}

/* ---------------------------------------------------------------- alt menü */
function altMenuKur() {
  document.querySelectorAll('[data-altmenu]').forEach((li) => {
    const btn = li.querySelector('.menu__ac');
    if (!btn) return;
    const ac = (on) => { li.classList.toggle('is-acik', on); btn.setAttribute('aria-expanded', on ? 'true' : 'false'); };
    btn.addEventListener('click', () => ac(!li.classList.contains('is-acik')));
    li.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && li.classList.contains('is-acik')) { ac(false); btn.focus(); }
    });
    li.addEventListener('focusout', (e) => { if (!li.contains(e.relatedTarget)) ac(false); });
    document.addEventListener('pointerdown', (e) => { if (!li.contains(e.target)) ac(false); });
  });
}

/* ---------------------------------------------------------------- föy listesi (telefon ve dar ekran) */
function dizinKur() {
  const dlg = document.getElementById('foy-listesi');
  const ac = document.querySelector('.ust__liste');
  if (!dlg || !ac || typeof dlg.showModal !== 'function') return;
  ac.setAttribute('aria-expanded', 'false');
  ac.addEventListener('click', () => {
    dlg.showModal();
    ac.setAttribute('aria-expanded', 'true');
    document.documentElement.style.overflow = 'hidden';
    lenis?.stop();
  });
  dlg.addEventListener('close', () => {
    ac.setAttribute('aria-expanded', 'false');
    document.documentElement.style.overflow = '';
    lenis?.start();
  });
  dlg.querySelector('[data-kapat]')?.addEventListener('click', () => dlg.close());
  dlg.addEventListener('click', (e) => {
    if (e.target.closest('a[href]')) dlg.close();
  });
}

/* ---------------------------------------------------------------- başlat */
window.tky = {
  hareketAz,
  lenis: null,
  kaydir,
  hedefVurgula,
  sesAcikMi,
  ses: sesYukle,
  sesCal(ad, secenek) {
    if (!sesAcikMi()) return;
    sesYukle().then((s) => s?.cal?.(ad, secenek));
  },
};

hareketKur();
sesKur();
cetvelKur();
altMenuKur();
dizinKur();
caprazKur();
gecisKur();

/* scroll-craft hazır kancası (spec 6.2): load + document.fonts.ready */
function hazirKur() {
  const yaziTipi = () => (document.fonts?.ready ?? Promise.resolve()).catch(() => {});
  const isaretle = () => yaziTipi().then(() => kok.classList.add('sc-ready'));
  if (document.readyState === 'complete') isaretle();
  else window.addEventListener('load', isaretle, { once: true });
}
hazirKur();
