/* sayfa-referans.js: Föy 6, kontak aynası (paket P6).
 *
 * Açılış: bobin -K0'ın hattı enerjilenir (A1 hattı, bobin, A2 hattı), sonra ekranın %70 çizgisini geçmiş
 * kontaklar sırayla kapanır. Kaydırdıkça her satırın kontağı %70 çizgisine gelince BİR KEZ kapanır
 * (cizim.js kontak(), 180 ms, A07) ve uç numaraları ile kontak hattı fosforlu bantla işaretlenir.
 * Hızlı atlamada (A-Z dizini, adres çubuğundaki #harf) ekranın üstünde kalan kontaklar animasyonsuz kapanır.
 * Bir grubun bütün kontakları kapanınca dizindeki harf enerjilenir; ekranda görünen grupların harfi koyulaşır.
 * Azaltılmış hareket: bütün kontaklar baştan kapalı (CSS son durumu), ses yok.
 * Kaydırma saati: ScrollTrigger (Lenis onu besler); yoksa IntersectionObserver. Pencere scroll dinleyicisi yok.
 */
import { kontak, enerji, hareketAz, vurgula } from './cizim.js';
import { gecisBitti } from './gecis.js';

const kok = document.documentElement;
const az = hareketAz();
const bolum = document.getElementById('kontaklar');
const dizin = document.querySelector('.dizin');
const satirlar = [...document.querySelectorAll('.firma[data-kontak]')];
const toplam = satirlar.length;
const ESIK = 0.7;
const bekle = (ms) => new Promise((ok) => setTimeout(ok, ms));

const gruplar = [...document.querySelectorAll('.grup')].map((el) => ({
  el,
  harf: el.dataset.harf,
  satirlar: [...el.querySelectorAll('.firma')],
  link: dizin?.querySelector(`.dizin__harf[data-harf="${el.dataset.harf}"]`) || null,
}));
const grupOf = new Map();
gruplar.forEach((g) => g.satirlar.forEach((s) => grupOf.set(s, g)));

let kapaliSayi = 0;
const gorunen = new Set();
const planli = new Set();
let hazir = false;

function durumYaz() {
  if (!bolum) return;
  const harfler = gruplar.filter((g) => gorunen.has(g.el)).map((g) => g.harf).join(',');
  bolum.setAttribute('data-sc-verify-state', `kapali:${kapaliSayi}/${toplam} gorunur:${harfler || '-'}`);
}

function grupDenetle(li) {
  const g = grupOf.get(li);
  if (g && g.link && g.satirlar.every((s) => s.classList.contains('is-kapali'))) g.link.classList.add('is-enerjili');
}

function bicakKapat(svg) {
  svg.querySelectorAll('.kontak__bicak').forEach((b) => {
    const [mx, my] = (b.getAttribute('data-mafsal') || '0 0').split(/\s+/);
    b.setAttribute('transform', `rotate(0 ${mx} ${my})`);
    b.setAttribute('data-aci', '0');
  });
}

/* Bir kontağı kapatır. anlik: ekran dışında, animasyon ve ses yok. */
function kapat(li, anlik, isaretle = true) {
  if (li.classList.contains('is-kapali')) return;
  const svg = li.querySelector('.kontak-cizim');
  kapaliSayi++;
  if (anlik || az) {
    li.classList.add('is-anlik', 'is-kapali');
    if (svg) bicakKapat(svg);
  } else {
    li.classList.add('is-kapali');
    if (svg) kontak(svg, true);
    // ad bir kez işaret kalemiyle geçilir, sonra söner (bant kontak hattında kalır)
    const ad = li.querySelector('.firma__ad');
    if (ad && isaretle) setTimeout(() => vurgula(ad, { kalici: false, bekle: 700, cik: 600 }), 200);
  }
  grupDenetle(li);
  durumYaz();
}

/* %70 çizgisini geçmiş, henüz kapanmamış satırları bulur. Yalnızca okur; yazma işleri sonraya kalır. */
function denetle() {
  if (!hazir) return;
  const vh = window.innerHeight;
  const cizgi = vh * ESIK;
  const yeni = [];
  for (const li of satirlar) {
    if (planli.has(li)) continue;
    const svg = li.querySelector('.kontak-cizim');
    const r = (svg || li).getBoundingClientRect();
    const y = r.top + r.height * 0.8; // kontak hattının yüksekliği
    if (y <= cizgi) yeni.push({ li, y, x: r.left, gorunur: r.bottom > 0 });
  }
  if (!yeni.length) return;
  yeni.sort((a, b) => a.y - b.y || a.x - b.x);
  const gorunurSayi = yeni.filter((s) => s.gorunur).length;
  let n = 0;
  for (const s of yeni) {
    planli.add(s.li);
    if (!s.gorunur) { kapat(s.li, true); continue; }
    const gecikme = n++ * 90;
    const tek = gorunurSayi <= 2; // hızlı atlamada adlar işaretlenmez, yalnızca kontaklar kapanır
    if (gecikme === 0) kapat(s.li, false, tek); else setTimeout(() => kapat(s.li, false, tek), gecikme);
  }
}

/* ---------------------------------------------------------------- görünen gruplar (dizin) */
function dizinKur() {
  if (!dizin) return;
  const serit = dizin.querySelector('.dizin__liste');
  let sonIlk = null;
  const io = new IntersectionObserver((kayitlar) => {
    kayitlar.forEach((k) => (k.isIntersecting ? gorunen.add(k.target) : gorunen.delete(k.target)));
    gruplar.forEach((g) => g.link?.classList.toggle('is-gorunur', gorunen.has(g.el)));
    durumYaz();
    // telefonda yatay şerit: ilk görünen harf şeritte görünür kalsın (yalnızca şerit kayar, sayfa değil)
    const ilk = gruplar.find((g) => gorunen.has(g.el));
    if (ilk && ilk !== sonIlk && serit && serit.scrollWidth > serit.clientWidth + 1) {
      sonIlk = ilk;
      const a = ilk.link?.parentElement;
      if (a) serit.scrollTo({ left: Math.max(0, a.offsetLeft - 44), behavior: az ? 'auto' : 'smooth' });
    }
  }, { rootMargin: `-${Math.round(parseFloat(getComputedStyle(kok).scrollPaddingTop) || 100)}px 0px 0px 0px` });
  gruplar.forEach((g) => io.observe(g.el));

  dizin.addEventListener('click', (e) => {
    const a = e.target.closest('a.dizin__harf');
    if (!a) return;
    const hedef = document.getElementById(decodeURIComponent(a.hash.slice(1)));
    if (!hedef) return;
    e.preventDefault();
    const sabitSerit = getComputedStyle(dizin).position === 'sticky';
    const ofset = (parseFloat(getComputedStyle(kok).scrollPaddingTop) || 100) + (sabitSerit ? dizin.offsetHeight : 0) - 8;
    history.replaceState(null, '', a.hash);
    git(hedef, ofset).then(() => {
      const baslik = hedef.querySelector('.grup__harf');
      if (baslik) {
        baslik.focus({ preventScroll: true });
        vurgula(baslik.querySelector('.grup__harf-metin') || baslik, { kalici: false });
      }
      denetle();
    });
  });
}

/* Sayısal hedefe kaydırır. tky.kaydir() Lenis 1.3'te öğenin scroll-margin'ini ve sayfanın scroll-padding'ini
   kendisi de eklediği için hedef çift ofsetle duruyordu; burada konum elle hesaplanır. */
function git(hedef, ofset) {
  const y = Math.max(0, hedef.getBoundingClientRect().top + window.scrollY - ofset);
  const L = window.tky?.lenis;
  return new Promise((ok) => {
    if (L) { L.scrollTo(y, { duration: az ? 0 : 0.9, force: true, onComplete: ok }); return; }
    window.scrollTo({ top: y, behavior: az ? 'auto' : 'smooth' });
    setTimeout(ok, az ? 60 : 900);
  });
}

/* ---------------------------------------------------------------- kaydırma saati */
function saatKur() {
  const ST = window.ScrollTrigger;
  if (ST && bolum) {
    ST.create({
      trigger: bolum,
      start: 'top bottom',
      end: 'bottom top',
      onUpdate: denetle,
      onToggle: denetle,
      onRefresh: denetle,
    });
    return;
  }
  // yedek: her satır %70 çizgisini geçince denetle (atlamalarda da çalışır: denetle konuma bakar)
  const io = new IntersectionObserver(() => denetle(), { rootMargin: `0px 0px -${Math.round((1 - ESIK) * 100)}% 0px` });
  satirlar.forEach((s) => io.observe(s));
  window.addEventListener('resize', denetle, { passive: true });
}

/* ---------------------------------------------------------------- NOT plakası: boştaki kontak 423-424 */
function plakaKur() {
  const plaka = document.querySelector('.plaka');
  const svg = plaka?.querySelector('.kontak-cizim');
  const dugme = plaka?.querySelector('.dugme');
  if (!svg || !dugme) return;
  const ac = () => { plaka.classList.remove('is-kapali'); return kontak(svg, false, { ses: null }); };
  const kapa = () => { plaka.classList.add('is-kapali'); return kontak(svg, true); };
  dugme.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') kapa(); });
  dugme.addEventListener('pointerleave', (e) => { if (e.pointerType === 'mouse') ac(); });
  dugme.addEventListener('focus', kapa);
  dugme.addEventListener('blur', ac);
}

/* ---------------------------------------------------------------- açılış: hat enerjilenir, ilk kontak kapanır */
async function acilis() {
  const a1 = document.querySelector('.bobin__hat--a1');
  const a2 = document.querySelector('.bobin__hat--a2');
  const yollar = [...document.querySelectorAll('.bobin__iec path[data-sira]')]
    .sort((x, y) => (+x.dataset.sira) - (+y.dataset.sira));

  await Promise.race([gecisBitti(), bekle(1200)]);
  try { await document.fonts.ready; } catch { /* yok say */ }

  if (az) {
    a1?.classList.add('is-enerjili');
    a2?.classList.add('is-enerjili');
    yollar.forEach((p) => enerji(p));
    satirlar.forEach((s) => { planli.add(s); kapat(s, true); });
    hazir = true;
    return;
  }
  if (a1) {
    const boy = a1.getBoundingClientRect().height;
    a1.style.setProperty('--hat-sure', `${Math.round(Math.min(520, Math.max(160, boy * 2.2)))}ms`);
    a1.classList.add('is-enerjili');
    await bekle(Math.min(520, Math.max(160, boy * 2.2)));
  }
  for (const p of yollar) {
    const dikdortgen = /Z\s*$/i.test(p.getAttribute('d') || '');
    await enerji(p, { gecikme: 0, sure: dikdortgen ? 300 : 120 });
  }
  if (a2) {
    a2.style.setProperty('--hat-sure', '140ms');
    a2.classList.add('is-enerjili');
    await bekle(160);
  }
  hazir = true;
  // açılış nesnesi: ilk kontak (13-14) ilk ekrandaysa, %70 çizgisinin altında kalsa da bobinle birlikte kapanır
  const ilk = satirlar[0];
  if (ilk && !planli.has(ilk)) {
    const r = ilk.getBoundingClientRect();
    if (r.top < window.innerHeight - 40 && r.bottom > 0) { planli.add(ilk); kapat(ilk, false); await bekle(90); }
  }
  denetle();
}

durumYaz();
dizinKur();
saatKur();
plakaKur();
acilis().finally(() => {
  kok.classList.add('sc-ready');
});
