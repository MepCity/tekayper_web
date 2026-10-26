/* capraz.js: çapraz referanslar (spec 2.3, paket F1).
 *
 *   hedefVurgula(hash)  Bölüm başlığının altından işaret kalemi geçer (280 ms), 1,6 s kalır, 400 ms'de söner;
 *                       sol cetveldeki harf yanıp söner. Azaltılmış harekette bant taramasız belirip söner.
 *                       hash: '#modeller' ya da bölüm öğesi. Kayıtlı bir bölüm değilse hiçbir şey yapmaz.
 *   caprazKur()         Aynı föy içindeki çapraz bağlantılarda yumuşak kaydırma + vurgu, .capraz üzerinde
 *                       400 ms sonra tam tanım ipucu (=TKY+SGT/5.C Modeller), yalnızca ince işaretçide.
 * Bölüm = main içinde id ve data-satir taşıyan öğe. Başlık = bölümün ilk h1/h2/h3'ü
 * (ya da data-capraz-baslik işaretli öğe).
 */

const kok = document.documentElement;
const az = () => kok.dataset.hareket === 'az';
const sure = { tarama: 280, kalma: 1600, sonme: 400 };

function bolumBul(hedef) {
  if (!hedef) return null;
  if (hedef instanceof Element) return hedef.closest('[data-satir]');
  let id = String(hedef).replace(/^#/, '');
  try { id = decodeURIComponent(id); } catch { /* bozuk kodlama */ }
  if (!id) return null;
  const el = document.getElementById(id);
  return el && el.matches('[data-satir]') ? el : null;
}

function bantHazirla(baslik) {
  let bant = baslik.querySelector(':scope > .vurgu-bant');
  if (!bant) {
    bant = document.createElement('span');
    bant.className = 'vurgu-bant';
    while (baslik.firstChild) bant.appendChild(baslik.firstChild);
    baslik.appendChild(bant);
  }
  return bant;
}

const zamanlar = new WeakMap();

export function hedefVurgula(hedef = location.hash) {
  const bolum = bolumBul(hedef);
  if (!bolum) return false;
  const baslik = bolum.querySelector('[data-capraz-baslik]') || bolum.querySelector('h1, h2, h3');
  if (baslik) {
    const bant = bantHazirla(baslik);
    (zamanlar.get(bant) || []).forEach(clearTimeout);
    bant.classList.remove('is-tarama', 'is-sonme');
    void bant.offsetWidth; // yeniden başlat
    bant.classList.add('is-tarama');
    const t1 = setTimeout(() => { bant.classList.add('is-sonme'); }, (az() ? 0 : sure.tarama) + sure.kalma);
    const t2 = setTimeout(() => { bant.classList.remove('is-tarama', 'is-sonme'); }, (az() ? 0 : sure.tarama) + sure.kalma + sure.sonme + 40);
    zamanlar.set(bant, [t1, t2]);
  }
  const harf = bolum.dataset.satir;
  const cetvel = harf && document.querySelector(`[data-satir-baglanti="${harf}"]`);
  if (cetvel && !az()) {
    cetvel.classList.remove('is-flas');
    void cetvel.offsetWidth;
    cetvel.classList.add('is-flas');
    setTimeout(() => cetvel.classList.remove('is-flas'), 1700);
  }
  return true;
}

/* aynı belgeye işaret eden ve kayıtlı bir bölüme giden bağlantı mı */
function ayniFoyBolumu(a) {
  if (!a || a.target === '_blank' || a.hasAttribute('download')) return null;
  const href = a.getAttribute('href');
  if (!href || !href.includes('#')) return null;
  let u;
  try { u = new URL(href, location.href); } catch { return null; }
  if (u.origin !== location.origin || u.pathname !== location.pathname || u.search !== location.search) return null;
  return bolumBul(u.hash);
}

function ipucuKur() {
  if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
  let kutu = null;
  let zaman = null;
  let aktif = null;
  const gizle = () => {
    clearTimeout(zaman);
    aktif = null;
    kutu?.classList.remove('is-acik');
  };
  const goster = (a) => {
    if (!kutu) {
      kutu = document.createElement('div');
      kutu.className = 'capraz-ipucu';
      kutu.setAttribute('aria-hidden', 'true');
      document.body.appendChild(kutu);
    }
    kutu.textContent = a.dataset.caprazTam;
    const r = (a.querySelector('.capraz__ref, .kesme-noktasi__ref') || a).getBoundingClientRect();
    const k = kutu.getBoundingClientRect();
    const x = Math.min(Math.max(8, r.left), window.innerWidth - k.width - 8);
    const yUst = r.top - k.height - 6;
    const y = yUst > 90 ? yUst : r.bottom + 6;
    kutu.style.transform = `translate(${Math.round(x)}px, ${Math.round(y)}px)`;
    kutu.classList.add('is-acik');
  };
  document.addEventListener('pointerover', (e) => {
    if (e.pointerType && e.pointerType !== 'mouse' && e.pointerType !== 'pen') return;
    const a = e.target.closest('[data-capraz-tam]');
    if (a === aktif) return;
    gizle();
    if (!a) return;
    aktif = a;
    zaman = setTimeout(() => { if (aktif === a) goster(a); }, 400);
  });
  document.addEventListener('pointerout', (e) => {
    const a = e.target.closest('[data-capraz-tam]');
    if (a && !a.contains(e.relatedTarget)) gizle();
  });
  // kaydırma dinleyicisi yerine: tekerlek ya da tuş ipucunu kapatır
  window.addEventListener('wheel', gizle, { passive: true });
  window.addEventListener('keydown', gizle);
}

export function caprazKur() {
  document.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const a = e.target.closest('a[href]');
    const bolum = ayniFoyBolumu(a);
    if (!bolum) return;
    e.preventDefault();
    const hash = '#' + bolum.id;
    if (location.hash !== hash) history.pushState(null, '', hash);
    const git = window.tky?.kaydir ? window.tky.kaydir(bolum) : Promise.resolve(bolum.scrollIntoView());
    git.then(() => {
      hedefVurgula(bolum);
      const odak = bolum.querySelector('h1, h2, h3') || bolum;
      if (!odak.hasAttribute('tabindex')) odak.setAttribute('tabindex', '-1');
      odak.focus({ preventScroll: true });
    });
  });
  window.addEventListener('popstate', () => { if (bolumBul(location.hash)) hedefVurgula(); });
  ipucuKur();
}
