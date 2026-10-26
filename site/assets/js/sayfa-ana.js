/* sayfa-ana.js: Föy 1, Tek hat şeması (paket P1). ES module.
 *
 * Sunucu son durumu basar. Tam harekette bu modül:
 *  - hattı kaydırmayla çizer: kalem ekranın %62 çizgisindedir; dikey parçalar o çizgiye kadar uzar,
 *    yatay parçalar kalem geçince kendi uzunlukları boyunca çizilir (kaydırma ile geri sarılır);
 *  - sembolleri kalem sırasıyla bir kez çizer, istasyon metnini açar;
 *  - sarı enerji bandını son geçilen istasyona kadar akıtır (açık -Q1 ve açık -K1 ana kontağında bekler);
 *  - açılışta dört düzlemi ayrı hızlarda kaydırır ve -Q1'in aydınger kağıdını kaldırır;
 *  - E satırında föyü sabitler: kamera bütün föyü gösterir, devre sarıya boyanır, -K1 çeker, motor döner.
 * Azaltılmış harekette ve JS yokken sayfa son durumunda kalır (sabitleme yok, motor dönmez).
 */
import { sembolCiz, kontak, gizle, hareketAz, vurgula } from './cizim.js';

const kok = document.documentElement;
const sema = document.getElementById('sema');
const gsap = window.gsap;
const ST = window.ScrollTrigger;
const az = hareketAz() || !gsap || !ST || !sema;
const telefonMu = () => matchMedia('(max-width: 1023.98px)').matches;
const incePointer = matchMedia('(hover: hover) and (pointer: fine)').matches;
const sinirla = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
const kolay = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
const sesCal = (ad) => window.tky?.sesCal?.(ad);

/* ---------------------------------------------------------------- ortak: aydınger düğmeleri, çapraz referans */
function aydingerDugmeleri(elle) {
  document.querySelectorAll('.sahne--foto').forEach((d) => {
    d.addEventListener('click', () => {
      const kalkik = d.getAttribute('aria-pressed') !== 'true';
      d.setAttribute('aria-pressed', String(kalkik));
      elle?.(d, kalkik ? 1 : 0, false);
    });
    if (incePointer && elle) {
      d.addEventListener('pointerenter', () => elle(d, 0, true));
      d.addEventListener('pointerleave', () => elle(d, d.getAttribute('aria-pressed') === 'true' ? 1 : 0, true));
    }
  });
}

function istasyonFlas(hedef) {
  const baslik = hedef.querySelector('.ist__baslik, .akis__not');
  if (baslik) vurgula(baslik, { kalici: false });
  hedef.querySelectorAll('.hs .sembol').forEach((s, i) => sembolCiz(s, { sure: 600, gecikme: i * 120 }));
  hedef.classList.add('is-gorunur');
}

function caprazKur() {
  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[data-ist-ref]');
    if (!a) return;
    const hedef = document.querySelector(a.getAttribute('href'));
    if (!hedef) return;
    e.preventDefault();
    const git = window.tky?.kaydir ? window.tky.kaydir(hedef, { ofset: Math.round(innerHeight * 0.3) }) : Promise.resolve(hedef.scrollIntoView());
    git.then(() => {
      istasyonFlas(hedef);
      const odak = hedef.querySelector('a, button') || hedef;
      odak.focus({ preventScroll: true });
    });
  });
}

function hazir() {
  (document.fonts?.ready || Promise.resolve()).then(() => requestAnimationFrame(() => kok.classList.add('sc-ready')));
}

/* ---------------------------------------------------------------- azaltılmış hareket / yedek */
if (az) {
  aydingerDugmeleri(null);
  caprazKur();
  hazir();
} else {
  canli();
}

function canli() {
  kok.classList.add('ana-canli');
  const zirve = document.querySelector('.zirve');
  zirve.classList.add('zirve--kamera');
  if (window.lenis === undefined && window.tky?.lenis) window.lenis = window.tky.lenis; // ornekle.mjs okur
  ST.config({ ignoreMobileResize: true });

  /* ---------------- ölçüm */
  let parcalar = [];   // {el, tur, bas, uz, son}
  let semboller = [];  // {el, kok, bas, cizildi}
  let istasyonlar = []; // {el, bas, acik}
  let bantlar = [];    // {el, ust, boy}
  let duraklar = [];   // ana hat: {y, dur}
  let q1Kapali = false;

  const gorunur = (el) => el.getClientRects().length > 0 && getComputedStyle(el).display !== 'none';
  const docUst = (el) => {
    // dönüşümleri yok sayan konum: kabın kutusu + offsetTop
    const kap = el.offsetParent || el.parentElement;
    return kap.getBoundingClientRect().top + scrollY + el.offsetTop;
  };

  function olc() {
    parcalar = [];
    sema.querySelectorAll('.h').forEach((el) => {
      if (el.closest('.zirve') || !gorunur(el)) return;
      const c = el.classList;
      const ust = docUst(el);
      let tur = 'nokta', uz = 1;
      if (c.contains('h--d') && !c.contains('h--e')) { tur = c.contains('h--s') || c.contains('h--kk') ? 'kirp' : 'dikey'; uz = el.offsetHeight; }
      else if (c.contains('h--y')) { tur = c.contains('h--s') ? 'kirpY' : 'yatay'; uz = el.offsetWidth; }
      else if (c.contains('h--e')) return;
      parcalar.push({ el, tur, bas: ust, uz: Math.max(1, uz), son: -1 });
    });
    // yatay parçanın ardından gelen düşüş, yatay bitince başlar
    parcalar.forEach((p, i) => {
      if (p.el.classList.contains('h--sonra')) {
        const onceki = parcalar.slice(0, i).reverse().find((q) => q.tur === 'yatay');
        if (onceki) p.bas += onceki.uz;
      }
    });
    document.querySelectorAll('#notlar .h').forEach((el) => {
      if (!gorunur(el)) return;
      parcalar.push({ el, tur: el.classList.contains('h--d') ? 'dikey' : 'nokta', bas: docUst(el), uz: Math.max(1, el.offsetHeight), son: -1 });
    });

    semboller = [];
    sema.querySelectorAll('.hs').forEach((h) => {
      if (h.closest('.zirve') || !gorunur(h)) return;
      const s = h.querySelector('.sembol');
      semboller.push({ el: s, h, bas: docUst(h) + 4, cizildi: !!s.dataset.ana_cizildi });
    });

    istasyonlar = [...sema.querySelectorAll('.ist, .akis--k1')].filter((li) => !li.closest('.zirve') && !li.hasAttribute('data-elle')).map((li) => {
      const P = parseFloat(getComputedStyle(li).getPropertyValue('--P')) || 40;
      return { el: li, bas: li.getBoundingClientRect().top + scrollY + P - 8 };
    });

    bantlar = [...sema.querySelectorAll('.h--e')].filter((el) => !el.closest('.zirve') && gorunur(el)).map((el) => ({ el, ust: docUst(el), boy: el.offsetHeight }));

    duraklar = [];
    sema.querySelectorAll('[data-ana]').forEach((el) => {
      if (el.closest('.zirve') || !gorunur(el)) return;
      const ust = docUst(el);
      if (el.classList.contains('hs')) {
        const boy = el.querySelector('svg').getBoundingClientRect().height;
        const dur = el.dataset.dur;
        duraklar.push({ y: ust + boy, ust, dur, durY: ust + boy * 0.36 });
      } else if (el.classList.contains('h--n')) {
        duraklar.push({ y: ust });
      }
    });
    duraklar.sort((a, b) => a.y - b.y);
    const q1h = sema.querySelector('.hs--q1');
    kalemTaban = q1h && gorunur(q1h) ? docUst(q1h) + q1h.offsetHeight + 24 : 0;
    guncelle(true);
  }

  /* ---------------- kalem */
  let kalemGiris = -Infinity; // açılışta kalem yukarıdan aşağı iner
  let kalemTaban = 0; // açılışta -Q1 her durumda çizilir
  const kalemY = () => Math.min(Math.max(scrollY + innerHeight * 0.74, kalemTaban), kalemGiris);

  function parcaCiz(p, k) {
    let v;
    if (p.tur === 'nokta') v = k >= p.bas ? 1 : 0;
    else v = sinirla((k - p.bas) / p.uz);
    v = Math.round(v * 1000) / 1000;
    if (v === p.son) return;
    p.son = v;
    const s = p.el.style;
    if (p.tur === 'dikey') s.transform = `scaleY(${v})`;
    else if (p.tur === 'yatay') s.transform = `scaleX(${v})`;
    else if (p.tur === 'kirp') s.clipPath = v >= 1 ? '' : `inset(0 0 ${((1 - v) * 100).toFixed(2)}% 0)`;
    else if (p.tur === 'kirpY') s.clipPath = v >= 1 ? '' : `inset(0 ${((1 - v) * 100).toFixed(2)}% 0 0)`;
    else s.opacity = String(v);
  }

  /* ---------------- enerji bandı */
  const bant = { y: -Infinity, hedef: -Infinity };
  let bantTween = null;
  function bantHedef(k) {
    let h = duraklar.length ? duraklar[0].y : 0;
    for (const d of duraklar) {
      const durVar = (d.dur === 'q1' && !q1Kapali) || d.dur === 'k1';
      if (durVar && k >= d.ust) { h = Math.max(h, d.durY); break; }
      if (durVar) break;
      if (d.y <= k) h = d.y; else break;
    }
    return Math.min(h, k);
  }
  function bantCiz() {
    for (const b of bantlar) {
      const v = Math.round(sinirla((bant.y - b.ust) / Math.max(1, b.boy)) * 1000) / 1000;
      if (v !== b.son) { b.son = v; b.el.style.transform = `scaleY(${v})`; }
    }
  }
  function bantGuncelle(k, ani) {
    const h = bantHedef(k);
    if (h === bant.hedef && !ani) return;
    bant.hedef = h;
    bantTween?.kill();
    if (ani || h < bant.y || !isFinite(bant.y)) { bant.y = h; bantCiz(); return; }
    bantTween = gsap.to(bant, { y: h, duration: Math.min(1.1, Math.max(0.25, (h - bant.y) / 1500)), ease: 'none', onUpdate: bantCiz });
  }

  /* ---------------- ana güncelleme */
  let durumYaz = 0;
  function guncelle(ani = false) {
    const k = kalemY();
    for (const p of parcalar) parcaCiz(p, k);
    for (const s of semboller) {
      if (!s.cizildi && k >= s.bas) {
        s.cizildi = true;
        s.el.dataset.ana_cizildi = '1';
        sembolCiz(s.el, { sure: 650 });
      }
    }
    for (const i of istasyonlar) {
      if (!i.acik && k >= i.bas) { i.acik = true; i.el.classList.add('is-gorunur'); }
    }
    bantGuncelle(k, ani);
    clearTimeout(durumYaz);
    durumYaz = setTimeout(() => {
      const son = semboller.filter((s) => s.cizildi).pop();
      sema.setAttribute('data-sc-verify-state', `kalem:${Math.round(k)} sembol:${son ? (son.h.querySelector('.sembol__etiket')?.textContent || '') : '-'} enerji:${Math.round(bant.y)} q1:${q1Kapali ? 'kapali' : 'acik'}`);
    }, 120);
  }

  /* ---------------- başlangıç durumu: henüz çizilmemiş her şey gizli */
  sema.querySelectorAll('.hs .sembol').forEach((s) => {
    if (s.closest('.zirve') || s.closest('.hs--q1')) return;
    gizle(s);
  });
  const q1Sembol = sema.querySelector('.hs--q1 .sembol');
  if (q1Sembol) { gizle(q1Sembol); kontak(q1Sembol, false, { sure: 0, ses: null }); }
  const k1Ana = sema.querySelector('.hs--k1ana .sembol');
  if (k1Ana) kontak(k1Ana, false, { sure: 0, ses: null });
  sema.querySelectorAll('.hs--sensor-enduktif .sembol, .hs--k2kontak .sembol').forEach((s) => kontak(s, false, { sure: 0, ses: null }));

  /* ---------------- açılış: düzlemler ve aydınger */
  const q1Li = document.getElementById('ist-q1');
  const q1Dugme = q1Li?.querySelector('.sahne--foto');
  const foto = q1Li?.querySelector('.sahne__foto');
  const kagit = q1Li?.querySelector('.aydinger');
  const izgara = sema.querySelector('.sema__izgara');
  const acilis = { p: 0, kalk: 0, ek: null };

  function q1Ac() {
    if (q1Kapali) return;
    q1Kapali = true;
    q1Li.classList.add('is-gorunur');
    if (q1Sembol) kontak(q1Sembol, true, { ses: null });
    bantGuncelle(kalemY(), false);
  }

  function kagitCiz() {
    if (!kagit) return;
    const l = acilis.ek ?? acilis.kalk;
    const tel = telefonMu();
    const s = acilis.p;
    const S = acilis.S || 400;
    // düzlem hızları (masaüstü): ızgara 0,35x, görsel 0,5x, hat ve yazı 1x, aydınger 1,15x
    const fotoY = tel ? 0 : -(S * 0.5) * (1 - s);
    const kagitY = tel ? 0 : fotoY - 0.15 * s * S;
    if (foto) foto.style.transform = `translate3d(0, ${fotoY.toFixed(1)}px, 0)`;
    kagit.style.transform = `translate3d(0, ${(kagitY - 28 * l).toFixed(1)}px, 0) rotate(${(-1.5 * l).toFixed(3)}deg)`;
    kagit.style.opacity = (0.78 - 0.66 * l).toFixed(3);
    if (izgara) izgara.style.transform = tel ? '' : `translate3d(0, ${(0.65 * s * S).toFixed(1)}px, 0)`;
  }

  // elle: aydınger düğmesi ya da üzerine gelme. 1 = kaydırmanın değerine dön, 0 = indir.
  aydingerDugmeleri((d, hedef, gecici) => {
    if (d !== q1Dugme) return;
    if (gecici && (acilis.kalk < 0.9 || d.getAttribute('aria-pressed') !== 'true')) return;
    if (acilis.ek === null) acilis.ek = acilis.kalk;
    gsap.to(acilis, {
      ek: hedef === 1 ? acilis.kalk : 0, duration: 0.28, ease: 'power2.out', overwrite: true, onUpdate: kagitCiz,
      onComplete: () => { if (hedef === 1) { acilis.ek = null; kagitCiz(); } },
    });
  });

  function acilisKur() {
    if (!q1Li) return;
    const mm = gsap.matchMedia();
    mm.add('(min-width: 1024px)', () => {
      const st = ST.create({
        trigger: sema,
        start: 'top top',
        end: () => '+=' + Math.round(innerHeight * 0.45),
        onRefresh: (self) => { acilis.S = self.end - self.start; },
        onUpdate: (self) => {
          acilis.p = self.progress;
          acilis.kalk = sinirla((self.progress - 0.12) / 0.43);
          if (acilis.kalk >= 0.55) q1Li.classList.add('is-gorunur');
          if (acilis.kalk >= 0.8) q1Ac();
          kagitCiz();
        },
      });
      acilis.S = st.end - st.start;
      kagitCiz();
      return () => { [foto, kagit, izgara].forEach((el) => el && (el.style.transform = '')); };
    });
    mm.add('(max-width: 1023.98px)', () => {
      const sahne = q1Li.querySelector('.ist__sahne--q1');
      ST.create({
        trigger: sahne,
        start: 'top 70%',
        end: 'center 38%',
        onUpdate: (self) => {
          acilis.kalk = self.progress;
          if (self.progress >= 0.85) q1Ac();
          kagitCiz();
        },
      });
      acilis.p = 0;
      kagitCiz();
    });
  }

  /* ---------------- tepe noktası */
  const svg = zirve.querySelector('.genel');
  const kamera = svg.querySelector('.genel__kamera');
  const m1Li = document.getElementById('ist-m1');
  const m1Html = m1Li.querySelector('.hs--m1 svg');
  const [mX, mY] = svg.dataset.m1.split(' ').map(Number);
  const [kx, ky, kw, kh] = svg.dataset.kutu.split(' ').map(Number);
  const obler = [...svg.querySelectorAll('.ob')].map((el) => {
    const [a, b] = el.dataset.q.split(' ').map(Number);
    return { el, a, b, L: el.getTotalLength(), son: -1 };
  });
  const kontaklar = [
    { el: svg.querySelector('.gb1'), q: 0.33 },
    { el: svg.querySelector('.gs1'), q: 0.35 },
    { el: svg.querySelector('.gk2'), q: 0.5 },
    { el: svg.querySelector('.gk1'), q: 0.66, ses: 'kontaktor' },
  ].filter((k) => k.el);
  kontaklar.forEach((k) => { kontak(k.el, false, { sure: 0, ses: null }); k.kapali = false; });
  const motorM = svg.querySelector('.gm1 .motor__m');
  const z = { W: 0, H: 0, s0: 2, kf: 0.7, c0: [0, 0], cS: [0, 0], ust: 87, alt: 14, p: -1 };
  let motorAnim = null;
  let zirveGorunur = false;

  function zirveOlc() {
    const r = zirve.getBoundingClientRect();
    z.W = r.width; z.H = r.height;
    const cs = getComputedStyle(document.body);
    z.ust = parseFloat(cs.paddingTop) || 87;
    z.alt = parseFloat(cs.paddingBottom) || 14;
    const visH = innerHeight - z.ust - z.alt;
    const b = parseFloat(getComputedStyle(m1Li).getPropertyValue('--b')) || 96;
    const P = parseFloat(getComputedStyle(m1Li).getPropertyValue('--P')) || 56;
    // motor merkezi görünür alanın %46'sına
    const ustPx = z.ust + visH * 0.46 - P - b * (26 / 48);
    zirve.style.setProperty('--m1-ust', `${Math.round(ustPx)}px`);
    svg.setAttribute('viewBox', `0 0 ${Math.round(z.W)} ${Math.round(z.H)}`);
    const mr = m1Html.getBoundingClientRect();
    const zr = zirve.getBoundingClientRect();
    z.s0 = mr.width / 48;
    z.c0 = [mr.left - zr.left + mr.width / 2, mr.top - zr.top + mr.height * (26 / 48)];
    z.kf = Math.min((z.W * 0.92) / kw, (visH * 0.95) / kh);
    z.cS = [z.W / 2, z.ust + visH / 2];
    z.p = -1;
    zirveCiz(z.son ?? 0, true);
  }

  function kameraAyarla(t) {
    const e = kolay(sinirla(t));
    const k = Math.exp(Math.log(z.s0) + (Math.log(z.kf) - Math.log(z.s0)) * e);
    const fx = mX + (kx + kw / 2 - mX) * e;
    const fy = mY + (ky + kh / 2 - mY) * e;
    const cx = z.c0[0] + (z.cS[0] - z.c0[0]) * e;
    const cy = z.c0[1] + (z.cS[1] - z.c0[1]) * e;
    kamera.setAttribute('transform', `translate(${(cx - k * fx).toFixed(2)} ${(cy - k * fy).toFixed(2)}) scale(${k.toFixed(4)})`);
    svg.style.setProperty('--k', k.toFixed(4));
    svg.style.setProperty('--w1', sinirla(e * 3).toFixed(3));
    z.k = k;
  }

  function supur(q) {
    for (const o of obler) {
      const v = Math.round(sinirla((q - o.a) / (o.b - o.a)) * 1000) / 1000;
      if (v === o.son) continue;
      o.son = v;
      if (v >= 1) o.el.style.strokeDasharray = '';
      else {
        const Ls = o.L * (z.k || 1);
        o.el.style.strokeDasharray = `${(v * Ls).toFixed(1)} 100000`;
      }
    }
    for (const k of kontaklar) {
      const kapali = q >= k.q;
      if (kapali !== k.kapali) {
        k.kapali = kapali;
        kontak(k.el, kapali, { ses: null, sure: 160 });
        if (kapali && k.ses) sesCal(k.ses);
      }
    }
  }

  function motor(calis) {
    if (!motorM) return;
    if (calis && !motorAnim) {
      sesCal('motor');
      const ilk = motorM.animate([{ transform: 'rotate(0deg)' }, { transform: 'rotate(360deg)' }], { duration: 1200, easing: 'cubic-bezier(0.45, 0, 0.25, 1)' });
      motorAnim = ilk;
      ilk.onfinish = () => {
        if (motorAnim !== ilk) return;
        motorAnim = motorM.animate([{ transform: 'rotate(0deg)' }, { transform: 'rotate(360deg)' }], { duration: 6000, iterations: Infinity });
        if (!zirveGorunur) motorAnim.pause();
      };
    } else if (!calis && motorAnim) {
      motorAnim.cancel();
      motorAnim = null;
    }
  }

  function zirveCiz(p, zorla = false) {
    z.son = p;
    if (!zorla && Math.abs(p - z.p) < 0.0005) return;
    z.p = p;
    let t;
    if (p < 0.35) t = p / 0.35;
    else if (p < 0.8) t = 1;
    else t = 1 - (p - 0.8) / 0.2;
    kameraAyarla(t);
    supur(sinirla((p - 0.6) / 0.2));
    motor(p >= 0.8);
    m1Li.classList.toggle('is-gorunur', p >= 0.97);
    zirve.setAttribute('data-sc-verify-state', `zirve:${p.toFixed(2)} olcek:${(z.k || 0).toFixed(2)} enerji:${sinirla((p - 0.6) / 0.2).toFixed(2)} motor:${p >= 0.8 ? 'doner' : 'durur'}`);
  }

  function zirveKur() {
    ST.create({
      trigger: zirve,
      start: 'top top',
      end: () => '+=' + Math.round(innerHeight * 2.2),
      pin: true,
      anticipatePin: 1,
      invalidateOnRefresh: true,
      onUpdate: (self) => zirveCiz(self.progress),
    });
    new IntersectionObserver(([g]) => {
      zirveGorunur = g.isIntersecting;
      if (motorAnim && motorAnim.playState !== 'finished') { if (zirveGorunur) motorAnim.play(); else motorAnim.pause(); }
    }).observe(zirve);
  }

  /* ---------------- sembol üzerine gelince yeniden çiz (ince işaretçi) */
  if (incePointer) {
    sema.addEventListener('pointerover', (e) => {
      const h = e.target.closest('.hs');
      if (!h || h.closest('.zirve')) return;
      const s = h.querySelector('.sembol');
      if (!s.dataset.ana_cizildi || s.dataset.ciziliyor) return;
      if (h._son && performance.now() - h._son < 1500) return;
      h._son = performance.now();
      sembolCiz(s, { sure: 600, etiket: false });
    });
  }

  /* ---------------- başlat */
  olc();
  caprazKur();
  acilisKur();
  zirveKur();
  ST.addEventListener('refresh', () => { zirveOlc(); olc(); });
  ST.create({ trigger: sema, start: 'top bottom', end: 'bottom top', onUpdate: () => guncelle() });
  document.getElementById('notlar') && ST.create({ trigger: '#notlar', start: 'top bottom', end: 'bottom top', onUpdate: () => guncelle() });

  // çerçeve çizilirken (yaklaşık 700 ms) bekle, sonra kalem besleme hattından aşağı iner
  const giris = { y: 0 };
  const basla = () => {
    ST.refresh();
    const hedef = Math.max(scrollY + innerHeight * 0.74, kalemTaban);
    const ilk = sema.querySelector('#ist-sebeke')?.getBoundingClientRect().top + scrollY || 0;
    giris.y = ilk;
    gsap.to(giris, {
      y: hedef + 4, duration: 1.1, ease: 'power1.inOut',
      onUpdate: () => { kalemGiris = giris.y; guncelle(); },
      onComplete: () => { kalemGiris = Infinity; guncelle(); hazir(); },
    });
  };
  kalemGiris = -Infinity;
  const bekle = kok.classList.contains('cerceve-ciz') ? 650 : 150;
  (document.fonts?.ready || Promise.resolve()).then(() => setTimeout(basla, bekle));
}
