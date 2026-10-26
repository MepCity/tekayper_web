/* liste.js: talep listesi (spec 3.4, paket F1). Malzeme listesinde toplanan gruplar, iletişim formuna aktarılır.
 *
 * Kayıt: localStorage 'tky.talepListesi' = [{marka, grup}]. Kişisel veri içermez, sunucuya gönderilmez
 * (form gönderilene kadar). localStorage yoksa (özel pencere) bellekte tutulur.
 *
 *   import { liste } from './liste.js';
 *   liste.oku()                  [{marka, grup}, ...]
 *   liste.varMi(marka, grup)     bool
 *   liste.ekle(marka, grup)      ekler (aynısı varsa eklemez), yeni listeyi döndürür
 *   liste.cikar(marka, grup)
 *   liste.degistir(marka, grup)  ekle/çıkar, yeni durumu (bool, listede mi) döndürür
 *   liste.temizle()
 *   liste.sayi()
 *   liste.satirlar()             ['Finder: Güvenlik röleleri', ...]  (iletişim Talep alanı, satır başına bir öğe)
 *   liste.metin()                satirlar().join('\n')
 *   liste.dinle(fn)              değişince fn(liste) çağrılır (bu sekme ve diğer sekmeler); kaldırıcı döndürür
 * Olay: window 'tky:liste' (detail: {liste}).
 */

const ANAHTAR = 'tky.talepListesi';
let bellek = [];

function temiz(v) {
  if (!Array.isArray(v)) return [];
  return v
    .filter((o) => o && typeof o.marka === 'string' && typeof o.grup === 'string')
    .map((o) => ({ marka: o.marka.slice(0, 80), grup: o.grup.slice(0, 160) }))
    .slice(0, 200);
}
function oku() {
  try {
    const ham = localStorage.getItem(ANAHTAR);
    return ham ? temiz(JSON.parse(ham)) : [];
  } catch {
    return bellek.slice();
  }
}
function yaz(l) {
  const t = temiz(l);
  bellek = t;
  try {
    if (t.length) localStorage.setItem(ANAHTAR, JSON.stringify(t));
    else localStorage.removeItem(ANAHTAR);
  } catch { /* bellekte kalır */ }
  window.dispatchEvent(new CustomEvent('tky:liste', { detail: { liste: t.slice() } }));
  return t;
}
const ayni = (o, marka, grup) => o.marka === marka && o.grup === grup;

export const liste = {
  oku,
  varMi(marka, grup) { return oku().some((o) => ayni(o, marka, grup)); },
  ekle(marka, grup) {
    const l = oku();
    if (!l.some((o) => ayni(o, marka, grup))) l.push({ marka, grup });
    return yaz(l);
  },
  cikar(marka, grup) { return yaz(oku().filter((o) => !ayni(o, marka, grup))); },
  degistir(marka, grup) {
    if (this.varMi(marka, grup)) { this.cikar(marka, grup); return false; }
    this.ekle(marka, grup);
    return true;
  },
  temizle() { return yaz([]); },
  sayi() { return oku().length; },
  satirlar() { return oku().map((o) => `${o.marka}: ${o.grup}`); },
  metin() { return this.satirlar().join('\n'); },
  dinle(fn) {
    const yerel = (e) => fn(e.detail.liste);
    const diger = (e) => { if (e.key === ANAHTAR) fn(oku()); };
    window.addEventListener('tky:liste', yerel);
    window.addEventListener('storage', diger);
    return () => { window.removeEventListener('tky:liste', yerel); window.removeEventListener('storage', diger); };
  },
};

export default liste;
