/* sayfa-yok.js: Föy 0, Föy yok (404.php, paket P9).
 *
 * Açılış: hat sol üstten çizilir (sabit kalem hızı), 7. sütunda aşağı döner, kopukluk çentikleri ve içi boş ok
 * belirir, "/?" çizici yazısıyla harflenir. Azaltılmış harekette son durum hemen görünür.
 * Etkileşim: föy listesinde bir satırın üzerine gelince ya da odaklanınca kopukluk kapanır, ok dolar,
 * hat işaret kalemiyle enerjilenir ve kesme noktası o föyün numarasını (/3) ve adını alır. Ayrılınca geri döner.
 * Kaydırma dinleyicisi yok; yalnızca pointer/focus olayları.
 */
import { harfle, hareketAz } from './cizim.js';
import { gecisBitti } from './gecis.js';

const sahne = document.querySelector('[data-yok-sahne]');
const KALEM = 1.7; // px / ms, çizici hızı

function cizimAc() {
  if (!sahne) return Promise.resolve();
  const bitir = () => sahne.classList.add('is-cizildi');
  if (hareketAz() || typeof sahne.animate !== 'function') { bitir(); return Promise.resolve(); }

  const yatay = sahne.querySelector('.yok__tel--yatay');
  const dusey = sahne.querySelector('.yok__tel--dusey');
  const bas = sahne.querySelector('.yok__baslangic');
  const kopuk = sahne.querySelector('.yok__kopuk');
  const ok = sahne.querySelector('.yok__ok');
  const ref = sahne.querySelector('[data-yok-ref]');

  const sYatay = Math.min(760, Math.max(380, yatay.offsetWidth / KALEM));
  const sDusey = Math.min(420, Math.max(180, dusey.offsetHeight / KALEM));
  const ac = { fill: 'both', easing: 'linear' };
  const t0 = 60;

  const anim = [
    bas.animate([{ opacity: 0 }, { opacity: 1 }], { ...ac, duration: 60 }),
    yatay.animate([{ transform: 'scaleX(0)' }, { transform: 'scaleX(1)' }], { ...ac, duration: sYatay, delay: t0 }),
    dusey.animate([{ transform: 'scaleY(0)' }, { transform: 'scaleY(1)' }], { ...ac, duration: sDusey, delay: t0 + sYatay }),
    kopuk.animate([{ opacity: 0 }, { opacity: 1 }], { ...ac, duration: 90, delay: t0 + sYatay + sDusey + 60 }),
    ok.animate([{ opacity: 0, transform: 'translateY(-6px)' }, { opacity: 1, transform: 'none' }],
      { fill: 'both', easing: 'cubic-bezier(0.23, 1, 0.32, 1)', duration: 200, delay: t0 + sYatay + sDusey + 140 }),
  ];
  const refBas = t0 + sYatay + sDusey + 300;
  // harfle satır içi clip-path ile başlar; CSS bekleme kırpmasını sınıf düşünce o devralır
  const yazi = new Promise((c) => setTimeout(c, refBas)).then(() => harfle(ref, { sure: 260 }));
  // bekleme durumunu hemen kaldır: başlangıç kareleri (fill: both) görünümü tutar
  ref.style.clipPath = 'inset(-0.3em 100% -0.3em -0.3em)';
  ref.dataset.cizimKirp = '';
  bitir();
  return Promise.all([...anim.map((a) => a.finished), yazi])
    .catch(() => {})
    .then(() => anim.forEach((a) => a.cancel()));
}

function etkilesimKur() {
  if (!sahne) return;
  const ref = sahne.querySelector('[data-yok-ref]');
  const ad = sahne.querySelector('[data-yok-ad]');
  const satirlar = [...document.querySelectorAll('[data-yok-foy]')];
  let etkin = null;
  let hazir = false;

  const yaz = (metin, adMetin) => {
    if (ref.textContent === metin) return;
    ref.textContent = metin;
    ad.textContent = adMetin;
    sahne.dataset.scVerifyState = metin === '/?' ? 'hat:kopuk ref:/?' : `hat:bagli ref:${metin}`;
    if (hazir && !hareketAz()) harfle(ref, { sure: 200 });
  };
  const bagla = (a) => {
    etkin = a;
    sahne.classList.add('is-bagli');
    yaz(a.dataset.yokFoy, a.dataset.yokFoyAd || '');
  };
  const coz = (a) => {
    if (etkin !== a) return;
    // odak başka bir satırdaysa ona bağlı kal
    const odak = document.activeElement?.closest?.('[data-yok-foy]');
    if (odak && odak !== a) { bagla(odak); return; }
    etkin = null;
    sahne.classList.remove('is-bagli');
    yaz('/?', '');
  };

  satirlar.forEach((a) => {
    a.addEventListener('pointerenter', (e) => { if (e.pointerType !== 'touch') bagla(a); });
    a.addEventListener('pointerleave', () => { if (document.activeElement !== a) coz(a); });
    a.addEventListener('focus', () => bagla(a));
    a.addEventListener('blur', () => coz(a));
  });
  return () => { hazir = true; };
}

const hazirla = etkilesimKur();
Promise.race([gecisBitti(), new Promise((c) => setTimeout(c, 1500))])
  .then(cizimAc)
  .catch(() => sahne?.classList.add('is-cizildi'))
  .finally(() => hazirla?.());
