/* sayfa-iletisim.js: Föy 8, Talep ve iletişim (paket P8). ES module.
 *
 *  - Vaziyet planı (OSM verisi) A bölümü görününce kalem sırasıyla çizilir.
 *  - Teklif formu bir antettir: B bölümü görününce hücreler okuma sırasıyla soldan sağa çizilir.
 *  - ?liste=1: talep listesi (liste.js, localStorage) Talebiniz alanına satır satır aktarılır.
 *  - Gönderim fetch ile (Accept: application/json). Hata: hücre altında cümle, köşede numaralı revizyon üçgeni,
 *    antetin üstünde revizyon listesi, odak ilk hatalı hücreye. Sunucu hatası: formun üstünde uyarı.
 *    Başarı yalnızca ok:true ise: hücreler kilitlenir, ONAY hücresine "TALEP ALINDI" kaşesi basılır (sunucunun
 *    talep numarası ve tarihi), TALEP NO hücresi harflenir, C bandı 160 ms'de tam genişliğe açılır, talep listesi silinir.
 *  - Azaltılmış harekette çizim ve kesmeler son hâlinde gelir, kaşe sarsıntısız ve sessizdir (cizim.js).
 * Durum imzası: main[data-sc-verify-state] = "form:... kase:... plan:... antet:..."
 */
import { kase, ciz, gizle, harfle } from './cizim.js';
import { liste } from './liste.js';

window.__iltHazir = true;

const kok = document.documentElement;
const az = () => kok.dataset.hareket === 'az';
const hareketli = kok.classList.contains('ilt-hazir') && !az();
const main = document.getElementById('icerik');
const veri = (() => {
  try { return JSON.parse(document.getElementById('ilt-veri')?.textContent || '{}'); } catch { return {}; }
})();

const durum = { form: 'bos', kase: 'yok', plan: 'bekliyor', antet: 'bekliyor' };
const ilk = main?.dataset.scVerifyState || '';
durum.form = ilk.match(/form:(\S+)/)?.[1] || durum.form;
durum.kase = ilk.match(/kase:(\S+)/)?.[1] || durum.kase;
function imza() {
  if (main) main.dataset.scVerifyState = Object.entries(durum).map(([k, v]) => `${k}:${v}`).join(' ');
}

const bugun = () => new Date().toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' });
const bekle = (ms) => new Promise((r) => setTimeout(r, ms));

/* ---------------------------------------------------------------- vaziyet planı */
function planKur() {
  const svg = document.querySelector('.plan');
  if (!svg) return;
  const bitir = () => { svg.classList.add('is-hazir', 'is-cizildi'); durum.plan = 'cizildi'; imza(); };
  if (!hareketli) { bitir(); return; }
  const yollar = svg.querySelector('.plan__yollar');
  const binalar = svg.querySelector('.plan__binalar');
  gizle([yollar, binalar]);
  svg.classList.add('is-hazir');
  const io = new IntersectionObserver(async (kayit) => {
    if (!kayit.some((k) => k.isIntersecting)) return;
    io.disconnect();
    durum.plan = 'ciziliyor'; imza();
    await ciz(yollar, { sure: 1000 });
    svg.classList.add('is-cizildi');
    await ciz(binalar, { sure: 320, sira: false });
    bitir();
  }, { threshold: 0.3 });
  io.observe(svg);
}

/* ---------------------------------------------------------------- antet girişi */
function antetKur() {
  const antet = document.querySelector('.ilt-antet');
  if (!antet) { durum.antet = 'yok'; return; }
  const bitir = () => { antet.classList.add('is-cizildi'); durum.antet = 'cizildi'; imza(); };
  if (!hareketli) { bitir(); return; }
  let basladi = false;
  const baslat = () => {
    if (basladi) return;
    basladi = true;
    const hucreler = [...antet.children].filter((el) => el.matches('.hucre, .ilt-revizyon') && !el.hidden);
    const konum = new Map(hucreler.map((h) => [h, h.getBoundingClientRect()]));
    hucreler.sort((a, b) => (Math.round(konum.get(a).top / 6) - Math.round(konum.get(b).top / 6)) || (konum.get(a).left - konum.get(b).left));
    hucreler.forEach((h, i) => setTimeout(() => h.classList.add('is-acik'), i * 45));
    setTimeout(bitir, hucreler.length * 45 + 260);
  };
  const io = new IntersectionObserver((kayit) => {
    if (kayit.some((k) => k.isIntersecting)) { io.disconnect(); baslat(); }
  }, { threshold: 0.08 });
  io.observe(antet);
  // klavyeyle hücreye gelinirse çizimi bekletme
  antet.addEventListener('focusin', () => { if (!basladi) { basladi = true; io.disconnect(); bitir(); } });
}

/* ---------------------------------------------------------------- form */
const form = document.querySelector('[data-ilt-form]');
const antet = form?.querySelector('.ilt-antet');
const uyariEl = document.getElementById('form-uyari');
const revEl = form?.querySelector('[data-ilt-revizyon]');
const sonuc = document.querySelector('[data-ilt-sonuc]');
const gonderBtn = form?.querySelector('.ilt-gonder');
const SIRA = ['adsoyad', 'firma', 'telefon', 'eposta', 'konu', 'mesaj', 'kvkk'];

const kurallar = {
  adsoyad: (el) => el.value.trim().length >= 3,
  telefon: (el) => el.value.replace(/\D/g, '').length >= 10,
  eposta: (el) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(el.value.trim()),
  konu: (el) => el.value.trim().length >= 3,
  kvkk: (el) => el.checked,
};

function dogrula(adlar = Object.keys(kurallar)) {
  const hatalar = {};
  adlar.forEach((ad) => {
    const el = form.elements[ad];
    if (el && kurallar[ad] && !kurallar[ad](el)) hatalar[ad] = veri.hatalar?.[ad] || 'Bu alanı düzeltin.';
  });
  return hatalar;
}

function alanTemizle(ad) {
  const h = form.querySelector(`.hucre[data-alan="${ad}"]`);
  const el = form.elements[ad];
  const p = document.getElementById(`alan-${ad}-hata`);
  h?.classList.remove('hucre--hata');
  h?.querySelector('.hucre__revizyon')?.removeAttribute('data-rev');
  el?.removeAttribute('aria-invalid');
  if (p) { p.textContent = ''; p.hidden = true; }
}

function revizyonYaz(hatalar) {
  if (!revEl) return;
  const adlar = SIRA.filter((a) => hatalar[a]);
  const ol = revEl.querySelector('[data-ilt-rev-liste]');
  const ozet = revEl.querySelector('[data-ilt-rev-ozet]');
  ol.replaceChildren(...adlar.map((ad, i) => {
    const li = document.createElement('li');
    const a = document.createElement('a');
    a.href = `#alan-${ad}`;
    const ucgen = document.createElement('span');
    ucgen.className = 'ilt-rev';
    ucgen.setAttribute('aria-hidden', 'true');
    ucgen.textContent = String(i + 1);
    const alan = document.createElement('span');
    alan.className = 'ilt-revizyon__alan';
    alan.textContent = veri.etiketler?.[ad] || ad;
    a.append(ucgen, alan);
    li.append(a, ' ', hatalar[ad]);
    return li;
  }));
  ozet.textContent = adlar.length ? `${adlar.length} alan düzeltilmeli.` : '';
  revEl.hidden = !adlar.length;
}

let gorunenHatalar = {};
function hataGoster(hatalar, { odakla = true } = {}) {
  SIRA.forEach(alanTemizle);
  const adlar = SIRA.filter((a) => hatalar[a]);
  adlar.forEach((ad, i) => {
    const h = form.querySelector(`.hucre[data-alan="${ad}"]`);
    const el = form.elements[ad];
    const p = document.getElementById(`alan-${ad}-hata`);
    h?.classList.add('hucre--hata');
    h?.querySelector('.hucre__revizyon')?.setAttribute('data-rev', String(i + 1));
    el?.setAttribute('aria-invalid', 'true');
    if (p) { p.textContent = hatalar[ad]; p.hidden = false; }
  });
  gorunenHatalar = Object.fromEntries(adlar.map((a) => [a, hatalar[a]]));
  revizyonYaz(gorunenHatalar);
  if (adlar.length) {
    antet?.classList.add('is-cizildi');
    if (odakla) form.elements[adlar[0]]?.focus();
  }
}

/* düzeltildikçe hata kalkar (yalnızca görünen hatalar için; yazarken yeni hata çıkmaz) */
function canliDuzelt(e) {
  const ad = e.target?.name;
  if (!ad || !gorunenHatalar[ad]) return;
  if (Object.keys(dogrula([ad])).length) return;
  delete gorunenHatalar[ad];
  alanTemizle(ad);
  // numaraları yeniden ver
  SIRA.filter((a) => gorunenHatalar[a]).forEach((a, i) => {
    form.querySelector(`.hucre[data-alan="${a}"] .hucre__revizyon`)?.setAttribute('data-rev', String(i + 1));
  });
  revizyonYaz(gorunenHatalar);
}

function uyari(metin) {
  if (!uyariEl) return;
  uyariEl.textContent = metin || '';
  uyariEl.hidden = !metin;
  if (metin) uyariEl.scrollIntoView({ block: 'nearest', behavior: az() ? 'auto' : 'smooth' });
}

function bekliyor(on) {
  if (!gonderBtn) return;
  gonderBtn.classList.toggle('is-bekliyor', on);
  if (on) gonderBtn.setAttribute('aria-busy', 'true'); else gonderBtn.removeAttribute('aria-busy');
}

async function yeniAnahtar() {
  try {
    const r = await fetch(veri.iletisim || 'iletisim.php', { headers: { Accept: 'text/html' }, credentials: 'same-origin', cache: 'no-store' });
    const html = await r.text();
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const v = doc.querySelector('input[name="anahtar"]')?.value;
    if (v && form.elements.anahtar) { form.elements.anahtar.value = v; return true; }
  } catch { /* ağ yok */ }
  return false;
}

async function gonder(tekrar = false) {
  let yanit = null;
  let govde = null;
  try {
    yanit = await fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    govde = await yanit.json().catch(() => null);
  } catch { govde = null; }
  // anahtarın süresi dolduysa (2 saat) bir kez yenileyip yeniden gönder
  if (!tekrar && yanit && yanit.status === 400 && govde && govde.ok === false && !govde.alanlar) {
    if (await yeniAnahtar()) { await bekle(3300); return gonder(true); }
  }
  return { yanit, govde };
}

let gonderiliyor = false;
async function gonderimKur() {
  if (!form) return;
  form.addEventListener('input', canliDuzelt);
  form.addEventListener('change', canliDuzelt);
  revEl?.addEventListener('click', (e) => {
    const a = e.target.closest('a[href^="#alan-"]');
    if (!a) return;
    e.preventDefault();
    document.getElementById(a.getAttribute('href').slice(1))?.focus();
  });
  // sunucunun çizdiği hatalar (JS kapalı yoldan dönüldüyse) canlı düzeltmeye katılsın
  SIRA.forEach((ad) => {
    const p = document.getElementById(`alan-${ad}-hata`);
    if (p && !p.hidden && p.textContent) gorunenHatalar[ad] = p.textContent;
  });

  if (form.elements.kaynak && !form.elements.kaynak.value && document.referrer) {
    try {
      const r = new URL(document.referrer);
      if (r.origin === location.origin && !r.pathname.endsWith('/iletisim.php')) form.elements.kaynak.value = r.pathname.slice(0, 120);
    } catch { /* geçersiz */ }
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (gonderiliyor) return;
    uyari(null);
    const hatalar = dogrula();
    if (Object.keys(hatalar).length) {
      hataGoster(hatalar);
      durum.form = 'hata'; imza();
      return;
    }
    hataGoster({}, { odakla: false });
    gonderiliyor = true;
    bekliyor(true);
    durum.form = 'gonderiliyor'; imza();
    const { govde } = await gonder();
    gonderiliyor = false;
    bekliyor(false);
    if (govde && govde.ok === true) {
      await basari(govde);
      return;
    }
    if (govde && govde.alanlar && Object.keys(govde.alanlar).length) {
      hataGoster(govde.alanlar);
      durum.form = 'hata'; imza();
      return;
    }
    uyari((govde && govde.hata) || veri.sunucu);
    durum.form = 'sunucu-hatasi'; imza();
  });

  sonuc?.querySelector('[data-ilt-yeni]')?.addEventListener('click', yeniTalep);
}

/* ---------------------------------------------------------------- başarı: kilit, kaşe, kesme */
function kilitle() {
  antet.classList.add('is-kilitli', 'is-cizildi');
  form.querySelectorAll('.hucre[data-alan]').forEach((h) => {
    const el = form.elements[h.dataset.alan];
    if (!el) return;
    h.classList.add('is-kilitli');
    h.querySelector('.hucre__yardim')?.setAttribute('hidden', '');
    if (el.type === 'checkbox') { el.disabled = true; return; }
    const p = document.createElement('p');
    p.className = 'hucre__kilit';
    p.dataset.iltKilit = '';
    p.textContent = el.value.trim() || '-';
    el.hidden = true;
    el.after(p);
  });
  gonderBtn?.setAttribute('aria-disabled', 'true');
  gonderBtn?.setAttribute('tabindex', '-1');
}

function kilitAc() {
  antet.classList.remove('is-kilitli');
  form.querySelectorAll('[data-ilt-kilit]').forEach((p) => p.remove());
  form.querySelectorAll('.hucre[data-alan]').forEach((h) => {
    const el = form.elements[h.dataset.alan];
    h.classList.remove('is-kilitli');
    h.querySelector('.hucre__yardim')?.removeAttribute('hidden');
    if (el) { el.hidden = false; el.disabled = false; }
  });
  gonderBtn?.removeAttribute('aria-disabled');
  gonderBtn?.removeAttribute('tabindex');
}

function bantAc(no, tarih, kaynakHucre) {
  const bant = document.querySelector('[data-ilt-bant]');
  if (!bant) return;
  bant.querySelector('[data-ilt-bant-no]').textContent = no;
  bant.querySelector('[data-ilt-bant-tarih]').textContent = tarih;
  const bolum = bant.closest('.ilt-c');
  bant.hidden = false;
  bolum?.classList.add('is-acik');
  if (az()) { bant.scrollIntoView({ block: 'nearest' }); return; }
  // bant ONAY hücresinin genişliğinden tam föy genişliğine tek vuruşta açılır (160 ms, doğrusal)
  const b = bant.getBoundingClientRect();
  const o = kaynakHucre.getBoundingClientRect();
  const sol = Math.max(0, Math.round(o.left - b.left));
  const sag = Math.max(0, Math.round(b.right - o.right));
  bant.style.setProperty('--ilt-kesme-bas', `inset(0 ${sag}px 0 ${sol}px)`);
  bant.classList.add('is-kesme');
  void bant.offsetWidth;
  bant.classList.add('is-acik');
  setTimeout(() => { bant.classList.remove('is-kesme', 'is-acik'); bant.style.removeProperty('--ilt-kesme-bas'); }, 240);
  bant.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

async function basari(govde) {
  const no = typeof govde.talep === 'string' ? govde.talep : '';
  const tarih = typeof govde.tarih === 'string' && govde.tarih ? govde.tarih : bugun();
  durum.form = 'gonderildi'; imza();
  try { liste.temizle(); } catch { /* yok */ }
  kilitle();
  uyari(null);
  const onay = form.querySelector('[data-ilt-onay]');
  onay.classList.add('is-kaseli');
  const tarihEl = form.querySelector('[data-ilt-tarih]');
  if (tarihEl) tarihEl.textContent = tarih;
  const kaseEl = await kase(onay, { metin: 'TALEP ALINDI', no, tarih });
  kaseEl?.classList?.add('ilt-kase');
  durum.kase = no ? 'basildi' : 'numarasiz'; imza();
  if (no) {
    const noEl = form.querySelector('[data-ilt-no]');
    noEl.textContent = no;
    noEl.closest('.hucre')?.classList.add('is-dolu');
    harfle(noEl);
  }
  const sonucNo = sonuc?.querySelector('[data-ilt-sonuc-no]');
  if (sonucNo) sonucNo.textContent = no ? `Talep numaranız ${no}. ` : '';
  if (sonuc) sonuc.hidden = false;
  if (!az()) await bekle(220);
  if (no) {
    bantAc(no, tarih, onay);
    try { history.replaceState(null, '', `${location.pathname}?talep=${encodeURIComponent(no)}#kase`); } catch { /* yok */ }
  }
  sonuc?.focus({ preventScroll: true });
}

function yeniTalep() {
  if (!form) return;
  kilitAc();
  form.reset();
  hataGoster({}, { odakla: false });
  const onay = form.querySelector('[data-ilt-onay]');
  onay.classList.remove('is-kaseli');
  onay.querySelectorAll('.kase').forEach((k) => k.remove());
  const noEl = form.querySelector('[data-ilt-no]');
  noEl.textContent = 'Gönderince verilir';
  noEl.closest('.hucre')?.classList.remove('is-dolu');
  if (sonuc) sonuc.hidden = true;
  const bant = document.querySelector('[data-ilt-bant]');
  if (bant) { bant.hidden = true; bant.closest('.ilt-c')?.classList.remove('is-acik'); }
  try { history.replaceState(null, '', `${location.pathname}#talep`); } catch { /* yok */ }
  durum.form = 'bos'; durum.kase = 'yok'; imza();
  form.elements.adsoyad?.focus();
}

/* ---------------------------------------------------------------- talep listesi */
function listeKur() {
  const mesaj = form?.elements.mesaj;
  if (!mesaj) return;
  const yardim = document.getElementById('alan-mesaj-yardim');
  const satirlar = liste.satirlar();
  const aktar = () => {
    const mevcut = mesaj.value.replace(/\s+$/, '');
    const yeni = liste.satirlar().filter((s) => !mevcut.includes(s));
    mesaj.value = [mevcut, ...yeni].filter(Boolean).join('\n');
    return yeni.length;
  };
  if (veri.liste) {
    if (satirlar.length) {
      aktar();
      if (yardim) yardim.textContent = `Talep listenizden ${satirlar.length} kalem aktarıldı. Adetleri satırların sonuna yazabilirsiniz.`;
    } else if (yardim) {
      yardim.textContent = 'Talep listeniz boş. Ürünleri bu alana yazabilirsiniz.';
    }
    return;
  }
  if (!satirlar.length || !yardim) return;
  // liste dolu ama ?liste=1 ile gelinmedi: aktarmayı öner
  const b = document.createElement('button');
  b.type = 'button';
  b.className = 'ilt-liste-ekle';
  b.textContent = `Talep listenizdeki ${satirlar.length} kalemi ekleyin`;
  b.addEventListener('click', () => {
    aktar();
    b.remove();
    yardim.textContent = `Talep listenizden ${satirlar.length} kalem aktarıldı.`;
    mesaj.focus();
  });
  yardim.after(b);
}

/* ---------------------------------------------------------------- başlat */
planKur();
antetKur();
listeKur();
gonderimKur();
imza();
