/* sayfa-malzeme.js: Föy 4, Malzeme listesi (spec 3.4, paket P4). ES module.
 *
 * Sunucunun çizdiği listeyi (37 marka, 178 grup) aynı arama kurallarıyla (arama.js) anında süzer:
 *  - ?ara=&kategori=&marka= adres durumu (replaceState, 150 ms gecikmeli), sunucu da aynılarını okur
 *  - sembol rayı: aria-pressed düğmeler; seçilen sembol raydan başlığa bir hatla bağlanıp büyük çizilir (imza)
 *  - talep listesi: liste.js (localStorage tky.talepListesi), iletisim.php?liste=1#talep ile forma aktarılır
 *  - satıra gelince (üzerine ya da odakla) satırın sembolü bir kez çizilir
 * QA kancası: main[data-sc-verify-state] = "ara:… kategori:… marka:… eslesme:N talep:N mod:grup|duz"
 */
import { aramaKur } from './arama.js';
import { liste } from './liste.js';
import { sembolCiz, ciz, enerji, kontak, gizle, vurgula, harfle, hareketAz } from './cizim.js';

const kok = document.documentElement;
const main = document.querySelector('.foy--malzeme');
const veriEl = document.getElementById('malzeme-veri');

if (main && veriEl) kur(JSON.parse(veriEl.textContent));

function kur(veri) {
  const arama = aramaKur(veri);
  const katAd = new Map(veri.kategoriler.map((k) => [k.id, k.ad]));
  const markaAd = new Map(veri.markalar.map((m) => [m.slug, m.ad]));
  const TOPLAM = arama.toplam;
  const MARKA_TOPLAM = veri.markalar.length;

  const $ = (s, el = main) => el.querySelector(s);
  const $$ = (s, el = main) => [...el.querySelectorAll(s)];

  const girdi = $('input[name="ara"]');
  const aramaForm = $('.arama');
  const temizleEski = $('[data-temizle]');
  const temizle = document.createElement('button');
  temizle.type = 'button';
  temizle.className = temizleEski.className;
  temizle.dataset.temizle = '';
  temizle.textContent = 'Temizle';
  temizleEski.replaceWith(temizle);
  const sayac = $('[data-sayac]');
  const ray = $('.ray');
  const rayDugmeler = new Map($$('.ray__dugme').map((d) => [d.dataset.kategori, d]));
  const baslik = $('.sonuc-baslik');
  const baslikAd = $('[data-baslik-ad]');
  const baslikAlt = $('[data-baslik-alt]');
  const yuva = $('[data-yuva]');
  const suzgecler = $('[data-suzgecler]');
  const bom = $('#bom');
  const duzGovde = $('.bom__duz');
  const bloklar = new Map($$('.bom__blok').map((b) => [b.dataset.blok, b]));
  const bos = $('[data-bos]');
  const bosMetin = $('[data-bos-metin]');
  const bosSor = $('[data-bos-sor]');
  const durumEslesme = $('[data-durum-eslesme]');
  const durumTalep = $('[data-durum-talep]');
  const talepGovde = $('[data-talep-govde]');
  const talepAciklama = $('[data-talep-aciklama]');
  const canli = $('[data-canli]');
  const tel = document.querySelector('[data-tel]');
  const yuvaSablon = document.getElementById('yuva-semboller');

  let sonEklenen = '';

  /* ------------------------------------------------------------ satırlar */
  const satirlar = new Map();
  $$('.bom__satir').forEach((tr) => {
    const sira = +tr.dataset.sira;
    satirlar.set(sira, {
      tr, sira,
      marka: tr.dataset.marka, slug: tr.dataset.slug, grup: tr.dataset.grup, kategori: tr.dataset.kategori,
      grupEl: tr.querySelector('[data-vurgu="grup"]'), markaEl: tr.querySelector('[data-vurgu="marka"]'),
      vurguAnahtar: '',
    });
  });
  const siraliSatirlar = [...satirlar.values()].sort((a, b) => a.sira - b.sira);

  /* işaret kutularını aria-pressed düğmelere çevir (JS kapalıyken kutular iletisim.php'ye GET ile gider) */
  siraliSatirlar.forEach((s) => {
    const lbl = s.tr.querySelector('label.ekle');
    if (!lbl) return;
    const d = document.createElement('button');
    d.type = 'button';
    d.className = 'ekle';
    d.setAttribute('aria-pressed', 'false');
    d.innerHTML = '<span class="ekle__kutu" aria-hidden="true"></span><span class="ekle__etiket">Listeye ekle</span>';
    const g = document.createElement('span');
    g.className = 'gizli';
    g.textContent = `: ${s.marka}, ${s.grup}`;
    d.appendChild(g);
    lbl.replaceWith(d);
    s.dugme = d;
  });

  /* aktar düğmesi: bağlantıya çevrilir */
  const aktarEski = $('[data-aktar]');
  const aktar = document.createElement('a');
  aktar.className = aktarEski.className;
  aktar.href = 'iletisim.php?liste=1#talep';
  aktar.innerHTML = aktarEski.innerHTML;
  aktar.dataset.aktar = '';
  aktarEski.replaceWith(aktar);
  const ipucu = document.createElement('p');
  ipucu.className = 'durum__ipucu';
  ipucu.id = 'aktar-ipucu';
  ipucu.hidden = true;
  ipucu.textContent = 'Önce listeye grup ekleyin.';
  aktar.closest('.durum__eylem').appendChild(ipucu);
  talepAciklama.textContent = 'Listeye eklediğiniz gruplar bu tarayıcıda saklanır. Teklif formu her grubu Talebiniz alanına bir satır olarak yazar.';

  /* ------------------------------------------------------------ durum */
  const ilkAdres = new URLSearchParams(location.search);
  const durum = {
    ara: (ilkAdres.get('ara') || '').slice(0, 80),
    kategori: katAd.has(ilkAdres.get('kategori')) ? ilkAdres.get('kategori') : null,
    marka: markaAd.has(ilkAdres.get('marka')) ? ilkAdres.get('marka') : null,
  };
  if (girdi.value !== durum.ara) girdi.value = durum.ara;
  let mod = main.dataset.mod;
  let sonSonuc = null;
  let oncekiGorunur = new Set(siraliSatirlar.filter((s) => !s.tr.hidden).map((s) => s.sira));

  function adresYaz() {
    const p = new URLSearchParams();
    if (durum.ara.trim()) p.set('ara', durum.ara.trim());
    if (durum.kategori) p.set('kategori', durum.kategori);
    if (durum.marka) p.set('marka', durum.marka);
    const q = p.toString();
    const yeni = location.pathname + (q ? '?' + q : '') + location.hash;
    if (yeni !== location.pathname + location.search + location.hash) history.replaceState(history.state, '', yeni);
  }
  let adresZaman = 0;
  const adresGecikmeli = () => { clearTimeout(adresZaman); adresZaman = setTimeout(adresYaz, 150); };

  let canliZaman = 0;
  function canliYaz(metin) {
    clearTimeout(canliZaman);
    canliZaman = setTimeout(() => { canli.textContent = metin; }, 650);
  }

  /* ------------------------------------------------------------ uygula: süz, sırala, vurgula */
  function uygula({ ilk = false, solma = true } = {}) {
    const sorgu = durum.ara.trim();
    const r = arama.yap(sorgu, durum.kategori, durum.marka);
    sonSonuc = r;
    const eslesen = new Map(r.sonuclar.map((s) => [s.sira, s]));
    const birimVar = sorgu !== '' && arama.birimler(sorgu).length > 0;
    const yalnizMarka = r.sonuclar.length > 0 && r.sonuclar.every((s) => s.yalnizMarka);
    const yeniMod = birimVar && !yalnizMarka ? 'duz' : 'grup';

    /* yerleşim */
    if (yeniMod === 'duz') {
      const frag = document.createDocumentFragment();
      r.sonuclar.forEach((s) => frag.appendChild(satirlar.get(s.sira).tr));
      duzGovde.appendChild(frag);
      duzGovde.hidden = false;
      bloklar.forEach((b) => { b.hidden = true; });
    } else if (mod === 'duz' || ilk) {
      // satırları marka bloklarına geri koy (özgün sırayla, marka başlık satırının altına)
      const yerinde = siraliSatirlar.every((s) => s.tr.parentNode === bloklar.get(s.slug));
      if (!yerinde || !ilk) siraliSatirlar.forEach((s) => bloklar.get(s.slug).appendChild(s.tr));
      duzGovde.hidden = true;
    }
    mod = yeniMod;
    main.dataset.mod = mod;

    /* görünürlük ve vurgu */
    const blokSay = new Map();
    siraliSatirlar.forEach((s) => {
      const gor = eslesen.has(s.sira);
      if (s.tr.hidden === gor) s.tr.hidden = !gor;
      if (gor) {
        blokSay.set(s.slug, (blokSay.get(s.slug) || 0) + 1);
        const anahtar = sorgu;
        if (s.vurguAnahtar !== anahtar) {
          if (sorgu) {
            s.grupEl.innerHTML = arama.vurgula(s.grup, sorgu);
            s.markaEl.innerHTML = arama.vurgula(s.marka, sorgu);
          } else {
            s.grupEl.textContent = s.grup;
            s.markaEl.textContent = s.marka;
          }
          s.vurguAnahtar = anahtar;
        }
      }
    });
    if (mod === 'grup') {
      bloklar.forEach((b, slug) => {
        const n = blokSay.get(slug) || 0;
        b.hidden = n === 0;
        const say = b.querySelector('[data-blok-sayi]');
        if (n && say) say.textContent = `${n} grup`;
      });
    }

    /* ray sayıları: kategori dışındaki süzgeçlerle */
    const rs = new Map();
    arama.yap(sorgu, null, durum.marka).sonuclar.forEach((s) => rs.set(s.kategori, (rs.get(s.kategori) || 0) + 1));
    rayDugmeler.forEach((d, id) => {
      const n = rs.get(id) || 0;
      const say = d.querySelector('[data-sayi]');
      if (say.textContent !== String(n)) say.textContent = String(n);
      const sm = d.querySelector('[data-sayi-metin]');
      if (sm) sm.textContent = `, ${n} grup`;
      d.toggleAttribute('data-bos', n === 0);
      const basili = durum.kategori === id;
      d.setAttribute('aria-pressed', basili ? 'true' : 'false');
      d.value = basili ? '' : id;
    });

    /* başlık */
    const n = r.sonuclar.length;
    const markaSay = new Set(r.sonuclar.map((s) => s.marka)).size;
    let ad;
    let alt;
    if (durum.kategori) { ad = katAd.get(durum.kategori); alt = `${n} grup, ${markaSay} marka`; }
    else if (sorgu) { ad = `“${sorgu}”`; alt = `${n} grup, ${markaSay} marka`; }
    else if (durum.marka) { ad = markaAd.get(durum.marka); alt = `${n} grup`; }
    else { ad = 'Bütün ürün grupları'; alt = `${TOPLAM} grup, ${MARKA_TOPLAM} marka`; }
    if (baslikAd.textContent !== ad) baslikAd.textContent = ad;
    baslikAlt.textContent = alt;
    baslik.dataset.durum = durum.kategori ? 'kategori' : (sorgu ? 'sorgu' : 'bos');
    suzgecCiz();
    $$('[data-foto]').forEach((f) => { f.hidden = f.dataset.foto !== durum.kategori; });

    /* sayaç, durum çubuğu, boş durum */
    sayac.textContent = String(n);
    durumEslesme.textContent = `${TOPLAM} gruptan ${n} eşleşme`;
    temizle.toggleAttribute('data-bos', durum.ara === '');
    temizle.setAttribute('aria-disabled', durum.ara === '' ? 'true' : 'false');
    bos.hidden = n !== 0;
    if (n === 0) {
      const ne = sorgu || ad;
      bosMetin.textContent = `“${ne}” için listede eşleşme yok. Liste tedarik edebildiğimiz her ürünü kapsamaz; ürünü bize sorun.`;
      bosSor.href = 'iletisim.php?' + new URLSearchParams({ konu: ne }).toString() + '#talep';
    }
    bom.toggleAttribute('data-bos', n === 0);

    if (!ilk) canliYaz(n === 0 ? 'Eşleşme yok.' : `${TOPLAM} gruptan ${n} eşleşme.`);
    dogrulamaYaz();

    /* satır solması: yeni görünen satırlar 180 ms'de belirir (ekrandaki ilk satırlar) */
    if (!ilk && solma && !hareketAz()) {
      const yeni = [];
      for (const s of r.sonuclar) {
        if (!oncekiGorunur.has(s.sira) || mod === 'duz') yeni.push(satirlar.get(s.sira).tr);
        if (yeni.length >= 24) break;
      }
      requestAnimationFrame(() => {
        yeni.forEach((tr) => tr.animate?.([{ opacity: 0 }, { opacity: 1 }], { duration: 180, easing: 'cubic-bezier(0.23, 1, 0.32, 1)' }));
      });
    }
    oncekiGorunur = new Set(eslesen.keys());
  }

  function suzgecCiz() {
    const parca = [];
    const link = (tur, etiket, deger) => {
      const p = new URLSearchParams(location.search);
      p.delete(tur);
      const a = document.createElement('a');
      a.className = 'suzgec';
      a.href = location.pathname + (p.toString() ? '?' + p : '') + '#liste';
      a.dataset.suzgecKaldir = tur;
      a.innerHTML = '<span class="suzgec__etiket"></span> <span class="suzgec__deger"></span><span class="suzgec__x" aria-hidden="true"></span><span class="gizli"> (süzgeci kaldır)</span>';
      a.querySelector('.suzgec__etiket').textContent = etiket;
      a.querySelector('.suzgec__deger').textContent = deger;
      parca.push(a);
    };
    if (durum.marka) link('marka', 'Marka:', markaAd.get(durum.marka));
    if (durum.kategori) link('kategori', 'Kategori:', katAd.get(durum.kategori));
    const imza = parca.map((a) => a.textContent).join('|');
    if (suzgecler.dataset.imza === imza) return;
    suzgecler.dataset.imza = imza;
    suzgecler.replaceChildren(...parca);
  }

  /* ------------------------------------------------------------ arama girdisi */
  let kare = 0;
  girdi.addEventListener('input', () => {
    durum.ara = girdi.value.slice(0, 80);
    // süzme, girdinin kendi boyamasından sonra ayrı bir görevde çalışır (INP); hızlı yazışlar birleşir
    clearTimeout(kare);
    kare = setTimeout(() => uygula(), 70);
    adresGecikmeli();
  });
  aramaForm.addEventListener('submit', (e) => {
    e.preventDefault();
    durum.ara = girdi.value.slice(0, 80);
    clearTimeout(kare);
    uygula();
    adresYaz();
    girdi.blur();
    if (window.matchMedia('(max-width: 1023.98px)').matches) window.tky?.kaydir?.('#liste');
  });
  girdi.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && girdi.value) { e.preventDefault(); aramaTemizle(); }
  });
  function aramaTemizle() {
    girdi.value = '';
    durum.ara = '';
    uygula();
    adresYaz();
    girdi.focus();
  }
  temizle.addEventListener('click', () => { if (durum.ara) aramaTemizle(); });
  document.addEventListener('keydown', (e) => {
    if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
    const t = e.target;
    if (t instanceof HTMLElement && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
    e.preventDefault();
    girdi.focus({ preventScroll: true });
    girdi.scrollIntoView({ block: 'center', behavior: hareketAz() ? 'auto' : 'smooth' });
  });

  /* süzgeç kaldır */
  suzgecler.addEventListener('click', (e) => {
    const a = e.target.closest('[data-suzgec-kaldir]');
    if (!a) return;
    e.preventDefault();
    const tur = a.dataset.suzgecKaldir;
    if (tur === 'kategori') kategoriSec(null);
    else { durum.marka = null; uygula(); adresYaz(); }
    girdi.focus({ preventScroll: true });
  });

  /* ------------------------------------------------------------ sembol rayı: imza */
  ray.addEventListener('submit', (e) => e.preventDefault());
  ray.addEventListener('click', (e) => {
    const d = e.target.closest('.ray__dugme');
    if (!d) return;
    e.preventDefault();
    const id = d.dataset.kategori;
    kategoriSec(durum.kategori === id ? null : id, d);
  });
  ray.addEventListener('keydown', (e) => {
    // ok tuşlarıyla raydaki düğmeler arasında gezinme (dikey rayda yukarı/aşağı, yatayda sol/sağ)
    const d = e.target.closest('.ray__dugme');
    if (!d) return;
    const dizi = [...rayDugmeler.values()];
    const i = dizi.indexOf(d);
    const ileri = ['ArrowDown', 'ArrowRight'].includes(e.key);
    const geri = ['ArrowUp', 'ArrowLeft'].includes(e.key);
    if (!ileri && !geri && e.key !== 'Home' && e.key !== 'End') return;
    e.preventDefault();
    const j = e.key === 'Home' ? 0 : e.key === 'End' ? dizi.length - 1 : (i + (ileri ? 1 : -1) + dizi.length) % dizi.length;
    dizi[j].focus();
  });

  let imzaNo = 0;
  function kategoriSec(id, dugme = null) {
    durum.kategori = id;
    uygula();
    adresYaz();
    // tablo kaydırılmış durumdaysa listenin başına dön (rayla başlık yapışkan, yerinde kalır)
    const liste0 = $('#liste');
    const ust = liste0.getBoundingClientRect().top;
    const cUst = (parseFloat(getComputedStyle(kok).getPropertyValue('scroll-padding-top')) || 100) - 16;
    if (ust < cUst - 1) window.scrollTo({ top: window.scrollY + ust - cUst, behavior: 'auto' });
    imza(id, dugme || rayDugmeler.get(id));
  }

  function yuvaSembolu(id) {
    const ad = veri.kategoriler.find((k) => k.id === id)?.sembol;
    const s = yuvaSablon?.content.querySelector(`[data-yuva-icin="${id}"]`) || (ad && yuvaSablon?.content.querySelector(`.sembol--${ad}`));
    return s ? s.cloneNode(true) : null;
  }

  function imza(id, dugme) {
    const no = ++imzaNo;
    tel.replaceChildren();
    tel.classList.remove('is-sonuyor');
    const eski = yuva.firstElementChild;
    if (!id) {
      if (eski) {
        if (hareketAz()) eski.remove();
        else eski.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 140, easing: 'linear' }).finished.then(() => { if (no === imzaNo) eski.remove(); }, () => {});
      }
      return;
    }
    const s = yuvaSembolu(id);
    if (!s) return;
    if (eski) eski.remove();
    s.removeAttribute('data-yuva-icin');
    yuva.appendChild(s);
    if (hareketAz()) { kontak(s, true); return; }
    gizle(s);

    const yapi = yapiCizgisi();
    // hat: raydaki düğmenin bağlantı noktasından başlıktaki yuvaya (dikey rayda Y-D-Y, yatay rayda D-Y-D)
    const hat = hatCiz(dugme, s);
    const cizim = () => {
      if (no !== imzaNo) return Promise.resolve();
      yapi.classList.add('is-acik');
      return sembolCiz(s, { sure: 620 }).then(() => {
        if (no !== imzaNo) return;
        yapi.classList.remove('is-acik');
        kontak(s, true, { sure: 180 });
        vurgula(baslikAdBant(), { kalici: false, bekle: 900, cik: 400 });
      });
    };
    (hat ? hat.then(cizim) : cizim()).then(() => {
      if (no !== imzaNo || !tel.firstChild) return;
      setTimeout(() => {
        if (no !== imzaNo) return;
        tel.classList.add('is-sonuyor');
        setTimeout(() => { if (no === imzaNo) { tel.replaceChildren(); tel.classList.remove('is-sonuyor'); } }, 460);
      }, 700);
    });
  }

  function baslikAdBant() {
    let b = baslikAd.querySelector(':scope > .baslik-bant');
    if (!b || b.textContent !== baslikAd.textContent) {
      b = document.createElement('span');
      b.className = 'baslik-bant';
      b.textContent = baslikAd.textContent;
      baslikAd.replaceChildren(b);
    }
    return b;
  }

  function yapiCizgisi() {
    let y = baslik.querySelector('.yuva__yapi');
    if (!y) {
      y = document.createElement('span');
      y.className = 'yuva__yapi';
      y.setAttribute('aria-hidden', 'true');
      baslik.querySelector('.sonuc-baslik__yuva').appendChild(y);
    }
    return y;
  }

  function hatCiz(dugme, sembol) {
    if (!dugme || !tel) return null;
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const a = (dugme.querySelector('.ray__sembol') || dugme).getBoundingClientRect();
    const d = dugme.getBoundingClientRect();
    const hedef = (sembol.querySelector('svg') || sembol).getBoundingClientRect();
    const yv = baslik.querySelector('.sonuc-baslik__yuva').getBoundingClientRect();
    const gorunur = (r) => r.bottom > 0 && r.top < vh && r.right > 0 && r.left < vw && r.width > 0;
    if (!gorunur(d) || !gorunur(yv)) return null;
    tel.setAttribute('viewBox', `0 0 ${vw} ${vh}`);
    tel.setAttribute('width', vw);
    tel.setAttribute('height', vh);
    let yol;
    let bas;
    let son;
    const r1 = (v) => Math.round(v) + 0.5;
    if (yv.left > d.right + 8) {
      // dikey ray solda: düğmenin sağ kenarı -> oluk ortası -> yuvanın sol kenarı
      const x1 = r1(d.right + 5);
      const y1 = r1(a.top + a.height / 2);
      const x2 = r1(yv.left);
      const y2 = r1(hedef.top + hedef.height / 2);
      const xm = r1(d.right + (yv.left - d.right) / 2);
      yol = `M${x1} ${y1}H${xm}V${y2}H${x2}`;
      bas = [x1, y1];
      son = [x2, y2];
    } else {
      // yatay ray üstte: düğmenin alt kenarı -> ara düzey -> yuvanın üst kenarı
      const x1 = r1(d.left + d.width / 2);
      const y1 = r1(d.bottom - 6.5);
      const x2 = r1(yv.left + yv.width / 2);
      const y2 = r1(yv.top);
      if (y2 <= y1 + 4) return null;
      const ym = r1(y1 + (y2 - y1) / 2);
      yol = `M${x1} ${y1}V${ym}H${x2}V${y2}`;
      bas = [x1, y1];
      son = [x2, y2];
    }
    const ns = 'http://www.w3.org/2000/svg';
    const p = document.createElementNS(ns, 'path');
    p.setAttribute('d', yol);
    p.setAttribute('vector-effect', 'non-scaling-stroke');
    p.id = `tel-${imzaNo}`;
    const n1 = document.createElementNS(ns, 'circle');
    n1.setAttribute('class', 'tel__nokta');
    n1.setAttribute('cx', bas[0]); n1.setAttribute('cy', bas[1]); n1.setAttribute('r', '3.5');
    const n2 = document.createElementNS(ns, 'circle');
    n2.setAttribute('class', 'tel__nokta');
    n2.setAttribute('cx', son[0]); n2.setAttribute('cy', son[1]); n2.setAttribute('r', '0');
    tel.append(p, n1, n2);
    const uzunluk = Math.abs(son[0] - bas[0]) + Math.abs(son[1] - bas[1]);
    const sure = Math.max(240, Math.min(520, uzunluk * 0.7));
    enerji(p, { gecikme: 120, sure });
    return ciz(p, { sure }).then(() => { n2.setAttribute('r', '3.5'); });
  }

  /* ------------------------------------------------------------ satır sembolü: üzerine gelince bir kez çiz */
  const rayKaynak = new Map();
  rayDugmeler.forEach((d, id) => rayKaynak.set(id, d.querySelector('.sembol')));
  function satirSembolCiz(tr) {
    if (!tr || tr.dataset.cizildi || hareketAz()) return;
    tr.dataset.cizildi = '1';
    const hucre = tr.querySelector('.bom__sembol');
    const kaynak = rayKaynak.get(tr.dataset.kategori);
    if (!hucre || !kaynak) return;
    const s = kaynak.cloneNode(true);
    const svg = s.querySelector('svg');
    const vb = svg.viewBox.baseVal;
    const k = 32 / Math.max(vb.width, vb.height);
    svg.setAttribute('width', String(+(vb.width * k).toFixed(2)));
    svg.setAttribute('height', String(+(vb.height * k).toFixed(2)));
    hucre.replaceChildren(s);
    gizle(s);
    sembolCiz(s, { sure: 480, etiket: false });
  }
  bom.addEventListener('pointerover', (e) => {
    if (e.pointerType && e.pointerType !== 'mouse' && e.pointerType !== 'pen') return;
    const tr = e.target.closest('.bom__satir');
    if (tr) satirSembolCiz(tr);
  });
  bom.addEventListener('focusin', (e) => satirSembolCiz(e.target.closest('.bom__satir')));
  // raydaki sembol de üzerine gelince bir kez yeniden çizilir
  ray.addEventListener('pointerover', (e) => {
    if (e.pointerType !== 'mouse' || hareketAz()) return;
    const d = e.target.closest('.ray__dugme');
    if (!d || d.dataset.cizildi) return;
    d.dataset.cizildi = '1';
    const s = d.querySelector('.sembol');
    gizle(s);
    sembolCiz(s, { sure: 420, etiket: false });
  });

  /* ------------------------------------------------------------ talep listesi */
  bom.addEventListener('click', (e) => {
    const d = e.target.closest('button.ekle');
    if (!d) return;
    const s = satirlar.get(+d.closest('.bom__satir').dataset.sira);
    sonEklenen = liste.varMi(s.marka, s.grup) ? '' : `${s.marka}\u0000${s.grup}`;
    const simdi = liste.degistir(s.marka, s.grup);
    d.setAttribute('aria-pressed', simdi ? 'true' : 'false');
    s.tr.classList.toggle('is-listede', simdi);
    if (simdi) window.tky?.sesCal?.('kontak');
  });

  function talepCiz(l) {
    const anahtarlar = new Set(l.map((o) => `${o.marka}\u0000${o.grup}`));
    siraliSatirlar.forEach((s) => {
      const var_ = anahtarlar.has(`${s.marka}\u0000${s.grup}`);
      if (s.dugme && s.dugme.getAttribute('aria-pressed') !== String(var_)) s.dugme.setAttribute('aria-pressed', String(var_));
      s.tr.classList.toggle('is-listede', var_);
    });
    const n = l.length;
    const eskiMetin = durumTalep.textContent;
    durumTalep.innerHTML = '<span class="durum__on">Talep listesi: </span>' + (n ? `${n} grup` : 'boş');
    if (n && eskiMetin !== durumTalep.textContent && !hareketAz() && sonEklenen) {
      vurgula(durumTalep, { kalici: false, bekle: 700, cik: 400 });
    }
    aktar.setAttribute('aria-disabled', n ? 'false' : 'true');
    if (n) { aktar.removeAttribute('aria-describedby'); ipucu.hidden = true; } else aktar.setAttribute('aria-describedby', 'aktar-ipucu');

    const form = talepGovde.querySelector('#talep-formu');
    if (!n) {
      const p = document.createElement('p');
      p.className = 'talep__bos';
      p.textContent = 'Listeniz boş. Satırlardaki “Listeye ekle” düğmesiyle grup ekleyin.';
      talepGovde.replaceChildren(p, form);
    } else {
      const ol = document.createElement('ol');
      ol.className = 'talep__satirlar';
      l.forEach((o, i) => {
        const li = document.createElement('li');
        li.className = 'talep__satir';
        if (`${o.marka}\u0000${o.grup}` === sonEklenen) li.classList.add('is-yeni');
        li.innerHTML = '<span class="talep__poz"></span><span class="talep__marka"></span><span class="talep__grup"></span><button class="talep__cikar" type="button"></button>';
        li.children[0].textContent = String(i + 1);
        li.children[1].textContent = o.marka;
        li.children[2].textContent = o.grup;
        const b = li.children[3];
        b.textContent = 'Çıkar';
        b.dataset.marka = o.marka;
        b.dataset.grup = o.grup;
        const g = document.createElement('span');
        g.className = 'gizli';
        g.textContent = `: ${o.marka}, ${o.grup}`;
        b.appendChild(g);
        ol.appendChild(li);
      });
      const alt = document.createElement('div');
      alt.className = 'talep__alt';
      alt.innerHTML = '<button class="talep__cikar talep__temizle" type="button" data-talep-temizle>Listeyi temizle</button>';
      talepGovde.replaceChildren(ol, alt, form);
    }
    sonEklenen = '';
    dogrulamaYaz();
  }
  talepGovde.addEventListener('click', (e) => {
    const c = e.target.closest('.talep__cikar');
    if (!c) return;
    if (c.hasAttribute('data-talep-temizle')) { liste.temizle(); return; }
    const sonraki = c.closest('.talep__satir')?.nextElementSibling?.querySelector('.talep__cikar')
      || c.closest('.talep__satir')?.previousElementSibling?.querySelector('.talep__cikar');
    liste.cikar(c.dataset.marka, c.dataset.grup);
    // odak kaybolmasın: bir sonraki satırın düğmesine, yoksa başlığa
    const hedef = sonraki && talepGovde.querySelector(`.talep__cikar[data-marka="${CSS.escape(sonraki.dataset.marka)}"][data-grup="${CSS.escape(sonraki.dataset.grup)}"]`);
    (hedef || document.getElementById('talep-b'))?.focus?.();
  });
  document.getElementById('talep-b')?.setAttribute('tabindex', '-1');
  aktar.addEventListener('click', (e) => {
    if (liste.sayi()) return;
    e.preventDefault();
    ipucu.hidden = false;
    setTimeout(() => { ipucu.hidden = true; }, 2600);
  });
  liste.dinle(talepCiz);

  /* ------------------------------------------------------------ QA ve scroll-craft doğrulama durumu */
  function dogrulamaYaz() {
    const n = sonSonuc ? sonSonuc.sonuclar.length : 0;
    main.dataset.scVerifyState = `ara:${durum.ara.trim() || '-'} kategori:${durum.kategori || '-'} marka:${durum.marka || '-'} eslesme:${n} talep:${liste.sayi()} mod:${mod}`;
  }

  /* ------------------------------------------------------------ gelen hat (/3): urunler.php'den gelindiyse ilk o çizilir */
  const kesme = $('.malz-a__kesme');
  if (kesme && !hareketAz()) {
    let hat = null;
    try { hat = JSON.parse(sessionStorage.getItem('tky.hat') || 'null'); } catch { /* özel pencere */ }
    const taze = hat && hat.kaynak === '/3' && (!hat.t || Date.now() - hat.t < 5000);
    if (taze || /\/urunler\.php(?:[?#]|$)/.test(document.referrer)) {
      const s = kesme.querySelector('.sembol') || kesme.querySelector('svg');
      const ref = kesme.querySelector('.kesme-noktasi__ref');
      gizle(s);
      if (ref) ref.style.clipPath = 'inset(-0.3em 100% -0.3em -0.3em)';
      const bas = () => sembolCiz(s, { sure: 420, etiket: false }).then(() => ref && harfle(ref));
      (window.__tkyGecis?.finished || Promise.resolve()).then(bas, bas);
    }
  }

  /* ------------------------------------------------------------ başlangıç */
  uygula({ ilk: true });
  talepCiz(liste.oku());
  // yüzey hazır durumda açılır: arama kutusu odakta (yalnızca ince işaretçide ve adres bir bölüme gitmiyorsa)
  if (!location.hash && window.matchMedia('(pointer: fine)').matches) {
    girdi.focus({ preventScroll: true });
    const v = girdi.value;
    if (v) girdi.setSelectionRange(v.length, v.length);
  }
  // geri/ileri önbellekten dönüş: başka sekmede değişen listeyi oku
  window.addEventListener('pageshow', (e) => { if (e.persisted) talepCiz(liste.oku()); });
}
