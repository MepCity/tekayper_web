/* sayfa-ekler.js: Föy 7, Ekler (kataloglar.php). Paket P7.
 *
 * İmza (spec 3.7): markadan sayfaya. Masaüstünde tam harekette:
 *   A (kapak)  kapak büyük ve sahnenin ortasında, 3 derece dönük; sayfaların kenarları arkasında kalınlık olarak görünür.
 *              Kaydırdıkça kapak yerine oturur, EK-1 etiketi (yakın düzlem) biraz daha hızlı kayar.
 *   B (dizin)  dizin sabit kalırken (CSS sticky) sayfalar kaydırmayla sırayla kapağın arkasından çıkar ve sağda
 *              gevşek bir yığın olur (her sayfa -2..4 derece, kendi kayması). Son çıkan sayfanın dizin satırı işaretlenir.
 *              Dizinde bir markaya gelince (fare ya da odak) sayfası 280 ms'de yığının önüne kayar, A10 çalar.
 *   D (indir)  kısa sabitleme: kapağın arkasındaki yelpaze kapanır, indirme düğmesi kalır.
 * Telefon ve tablet (< 1024 px): sabitleme yok; dizin satırına dokununca sayfa görseli satırın içinde açılır.
 * Azaltılmış hareket ve JS yok: yığın dağıtılmış durur (CSS varsayılanı), dizin bağlantıları PDF'i açar.
 * Kaydırma ilerlemesi GSAP ScrollTrigger'dan okunur (Lenis ile site.js bağlar); ham scroll dinleyicisi yok.
 */

const kok = document.documentElement;
const ana = document.querySelector('.foy--ekler');
const tky = () => window.tky || {};
const hareketAz = () => kok.dataset.hareket === 'az';

if (ana) kur();

function kur() {
  const gorselKok = ana.dataset.gorselKok || '/assets/img/';
  const yigin = ana.querySelector('[data-yigin]');
  const kapak = ana.querySelector('[data-kapak]');
  const etiket = ana.querySelector('[data-ek-etiketi]');
  const yapraklar = [...ana.querySelectorAll('[data-yaprak]')];
  const satirlar = [...ana.querySelectorAll('.dizin__satir')];
  const dizinBolum = ana.querySelector('#sayfa-dizini');
  const kapakBolum = ana.querySelector('#kapak');
  const kapanis = ana.querySelector('#indir');
  const kapanisYapraklar = [...ana.querySelectorAll('.kapanis__yaprak')];
  const altyaziEl = ana.querySelector('[data-altyazi]');
  const altyazi = {
    sayfa: ana.querySelector('[data-altyazi-sayfa]'),
    ad: ana.querySelector('[data-altyazi-ad]'),
    konu: ana.querySelector('[data-altyazi-konu]'),
  };
  // dizin verisi PHP'den (katalog-sayfalari.php): [{ad, sayfa, konu}]
  let veri = [];
  try { veri = JSON.parse(document.getElementById('ekler-veri')?.textContent || '[]'); } catch { veri = []; }

  /* ------------------------------------------------------------ görseller (ilk boyamadan sonra) */
  const resimHtml = (ad, sizes) => {
    const g = [480, 835];
    const set = (b) => g.map((w) => `${gorselKok}${ad}-${w}.${b} ${w}w`).join(', ');
    return `<picture><source type="image/avif" srcset="${set('avif')}" sizes="${sizes}">`
      + `<source type="image/webp" srcset="${set('webp')}" sizes="${sizes}">`
      + `<img src="${gorselKok}${ad}-480.jpg" srcset="${set('jpg')}" sizes="${sizes}" alt="" width="835" height="1181" decoding="async"></picture>`;
  };
  const yukle = (el, sizes) => {
    if (!el || el.dataset.yuklendi) return;
    el.dataset.yuklendi = '1';
    el.insertAdjacentHTML('afterbegin', resimHtml(el.dataset.ad, sizes));
  };
  const masaustuMq = matchMedia('(min-width: 1024px)');
  function sahneGorselleri() {
    if (!masaustuMq.matches) return;
    const sizes = '(min-width: 1024px) 360px, 90vw';
    yapraklar.forEach((y) => yukle(y, sizes));
    kapanisYapraklar.forEach((y) => yukle(y, '300px'));
  }
  const bosta = (fn) => (window.requestIdleCallback ? requestIdleCallback(fn, { timeout: 1500 }) : setTimeout(fn, 300));
  if (document.readyState === 'complete') bosta(sahneGorselleri);
  else window.addEventListener('load', () => bosta(sahneGorselleri), { once: true });
  masaustuMq.addEventListener?.('change', () => { sahneGorselleri(); modKur(); });

  /* ------------------------------------------------------------ telefon: satırın içinde önizleme */
  ana.querySelectorAll('[data-dizin-ac]').forEach((btn) => {
    const panel = document.getElementById(btn.getAttribute('aria-controls'));
    btn.addEventListener('click', () => {
      const acik = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', acik ? 'false' : 'true');
      if (acik) { panel.hidden = true; return; }
      yukle(panel.querySelector('.dizin__resim'), '90vw');
      panel.hidden = false;
      if (!hareketAz()) {
        panel.classList.remove('is-aciliyor');
        void panel.offsetWidth;
        panel.classList.add('is-aciliyor');
      }
      tky().sesCal?.('katalog', { aralik: 150 });
    });
  });

  /* ------------------------------------------------------------ yığın: duruşlar */
  const N = yapraklar.length;
  const durus = yapraklar.map((y) => ({
    px: parseFloat(y.style.getPropertyValue('--px')) || 98,
    py: parseFloat(y.style.getPropertyValue('--py')) || 0,
    pr: parseFloat(y.style.getPropertyValue('--pr')) || 0,
  }));
  const KAPAK_R = -1.5;
  const istif = (i) => ({ x: Math.min(i, 14) * 0.32, y: Math.min(i, 14) * 0.32, r: KAPAK_R });
  const kolay = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
  const sinirla = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
  const DAGIT_BAS = 0.04;
  const DAGIT_SON = 0.8;
  const PARCA = 0.16;
  const ADIM = N > 1 ? (DAGIT_SON - DAGIT_BAS - PARCA) / (N - 1) : 0;

  let canli = false;
  let bIlerleme = 1;
  let onde = -1;          // fareyle/odakla öne alınan sayfa
  let sonDagitilan = N - 1;
  let ustSira = 0;
  const ekZ = new Map();  // öne alınıp bırakılan sayfalar yığının üstünde kalır

  const poz = (i) => {
    if (i === onde) {
      const d = durus[i];
      return `translate(${(d.px + 2).toFixed(2)}%, -3%) rotate(0deg) scale(1.03)`;
    }
    if (!canli) return '';
    const t = kolay(sinirla((bIlerleme - DAGIT_BAS - i * ADIM) / PARCA));
    const a = istif(i);
    const d = durus[i];
    const x = a.x + (d.px - a.x) * t;
    const y = a.y + (d.py - a.y) * t - Math.sin(Math.PI * t) * 7;
    const r = a.r + (d.pr - a.r) * t;
    return `translate(${x.toFixed(2)}%, ${y.toFixed(2)}%) rotate(${r.toFixed(2)}deg)`;
  };
  const dagildiMi = (i) => !canli || (bIlerleme - DAGIT_BAS - i * ADIM) / PARCA >= 0.92;

  function yiginCiz() {
    yapraklar.forEach((y, i) => {
      y.style.transform = poz(i);
      y.style.zIndex = i === onde ? '' : (ekZ.has(i) && dagildiMi(i) ? String(100 + ekZ.get(i)) : '');
    });
  }

  function altyaziYaz(i) {
    if (i < 0 || !veri[i]) return;
    if (altyazi.sayfa) altyazi.sayfa.textContent = `s. ${veri[i].sayfa}`;
    if (altyazi.ad) altyazi.ad.textContent = veri[i].ad;
    if (altyazi.konu) altyazi.konu.textContent = veri[i].konu;
  }
  function isaretle() {
    const i = onde >= 0 ? onde : sonDagitilan;
    satirlar.forEach((li, k) => li.classList.toggle('is-on', k === i && (onde >= 0 || canli)));
    altyaziYaz(i);
    altyaziEl?.classList.toggle('is-gizli', canli && onde < 0 && sonDagitilan < 0);
    if (dizinBolum) {
      dizinBolum.setAttribute('data-sc-verify-state',
        `dagitilan:${sonDagitilan + 1}/${N} on:${i >= 0 ? veri[i].ad : '-'} ilerleme:${bIlerleme.toFixed(2)}`);
    }
  }

  /* öne al / bırak (masaüstü, ince işaretçi ya da klavye odağı) */
  let gecisZaman = 0;
  const gecisli = (y) => {
    y.classList.add('gecisli');
    clearTimeout(y._gz);
    y._gz = setTimeout(() => y.classList.remove('gecisli'), 320);
  };
  function oneAl(i) {
    if (!masaustuMq.matches || i === onde) return;
    const once = onde;
    onde = i;
    if (once >= 0) { ekZ.set(once, ++ustSira); yapraklar[once].classList.remove('is-on'); gecisli(yapraklar[once]); }
    const y = yapraklar[i];
    if (!y) return;
    if (!hareketAz()) gecisli(y);
    y.classList.add('is-on');
    yiginCiz();
    isaretle();
    const simdi = performance.now();
    if (simdi - gecisZaman > 140) tky().sesCal?.('katalog', { aralik: 150 });
    gecisZaman = simdi;
  }
  function birak(i) {
    if (onde !== i) return;
    onde = -1;
    const y = yapraklar[i];
    ekZ.set(i, ++ustSira);
    y.classList.remove('is-on');
    if (!hareketAz()) gecisli(y);
    yiginCiz();
    isaretle();
  }
  ana.querySelectorAll('[data-dizin-a]').forEach((a) => {
    const i = Number(a.dataset.dizinA);
    a.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse' || e.pointerType === 'pen') oneAl(i); });
    a.addEventListener('pointerleave', (e) => { if (e.pointerType === 'mouse' || e.pointerType === 'pen') birak(i); });
    a.addEventListener('focus', () => oneAl(i));
    a.addEventListener('blur', () => birak(i));
  });
  yapraklar.forEach((y, i) => {
    y.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse' && dagildiMi(i)) oneAl(i); });
    y.addEventListener('pointerleave', (e) => { if (e.pointerType === 'mouse') birak(i); });
  });

  /* ------------------------------------------------------------ kaydırma bağları (yalnızca tam hareket + masaüstü) */
  let tetikler = [];
  function cUst() {
    return parseFloat(getComputedStyle(kok).getPropertyValue('scroll-padding-top')) - 16 || 90;
  }
  function kapakCiz(a) {
    // a: 0 = açılış (kapak büyük, ortada), 1 = yerinde (B duruşu)
    if (!canli) { yigin.style.transform = ''; kapak.style.transform = ''; etiket.style.transform = ''; return; }
    const pw = yigin.offsetWidth;
    const k = kolay(sinirla(a));
    const kaydir = pw * 0.48 * (1 - k);
    const olcek = 1 + 0.14 * (1 - k);
    yigin.style.transform = `translate3d(${kaydir.toFixed(1)}px, ${(18 * (1 - k)).toFixed(1)}px, 0) scale(${olcek.toFixed(4)})`;
    kapak.style.transform = `rotate(${(3 + (KAPAK_R - 3) * k).toFixed(2)}deg)`;
    etiket.style.transform = `translateY(${(-26 * a).toFixed(1)}px) rotate(${(-9 + 5 * a).toFixed(2)}deg)`;
    kapakBolum?.setAttribute('data-sc-verify-state', `kapak:${a.toFixed(2)}`);
  }
  function kapanisCiz(p) {
    const n = kapanisYapraklar.length;
    const t = canli ? kolay(sinirla(p / 0.85)) : 1;
    kapanisYapraklar.forEach((y, k) => {
      if (!canli) { y.style.transform = ''; return; }
      // yelpaze kapağın arkasından sola açılır (B'deki yığın sağdaydı); kapanınca sayfalar kapağın altına girer
      const m = k - (n - 1);
      const ac = 1 - t;
      y.style.transform = `translate(${(m * 7 * ac + k * 0.6 * t).toFixed(2)}%, ${(Math.abs(m) * 1.2 * ac + k * 0.6 * t).toFixed(2)}%) rotate(${(m * 4.5 * ac).toFixed(2)}deg)`;
    });
    kapanis?.setAttribute('data-sc-verify-state', `kapanis:${t.toFixed(2)}`);
  }

  function modKur() {
    tetikler.forEach((t) => t.kill());
    tetikler = [];
    const ST = window.ScrollTrigger;
    const olmali = masaustuMq.matches && !hareketAz() && !!(window.gsap && ST);
    canli = olmali;
    ana.classList.toggle('is-canli', canli);
    onde = -1;
    satirlar.forEach((li) => li.classList.remove('is-on'));
    yapraklar.forEach((y) => y.classList.remove('is-on'));
    if (!canli) {
      bIlerleme = 1; sonDagitilan = N - 1;
      kapakCiz(1); yiginCiz(); kapanisCiz(1); isaretle();
      return;
    }
    const ust = () => cUst();
    const alt = () => parseFloat(getComputedStyle(ana).getPropertyValue('--c-alt')) || 14;
    tetikler.push(ST.create({
      trigger: kapakBolum,
      start: () => `top top+=${ust()}`,
      end: () => `bottom top+=${ust()}`,
      onUpdate: (s) => kapakCiz(s.progress),
      onRefresh: (s) => kapakCiz(s.progress),
    }));
    tetikler.push(ST.create({
      trigger: dizinBolum,
      start: () => `top top+=${ust()}`,
      end: () => `bottom bottom-=${alt()}`,
      onUpdate: (s) => bGuncelle(s.progress),
      onRefresh: (s) => bGuncelle(s.progress),
    }));
    if (kapanis) {
      tetikler.push(ST.create({
        trigger: kapanis,
        start: () => `top top+=${ust()}`,
        end: () => `bottom bottom-=${alt()}`,
        onUpdate: (s) => kapanisCiz(s.progress),
        onRefresh: (s) => kapanisCiz(s.progress),
      }));
    }
    ST.refresh();
  }

  function bGuncelle(p) {
    bIlerleme = p;
    let son = -1;
    for (let i = 0; i < N; i++) if (dagildiMi(i)) son = i;
    sonDagitilan = son;
    yiginCiz();
    isaretle();
  }

  const basla = () => modKur();
  if (window.ScrollTrigger || document.readyState === 'complete') basla();
  else window.addEventListener('load', basla, { once: true });
}
