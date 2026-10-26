/* gecis.js: föy çevirme (spec 2.4, paket F1).
 *
 * Belgeler arası görünüm geçişi CSS ile açılır (gecis.css: @view-transition { navigation: auto }).
 * Bu modül:
 *  - pageswap: geçiş varsa ve ses açıksa A01 (sayfa) sesini çalar. İndirme, dış bağlantı, tel: ve mailto:
 *    için geçiş zaten oluşmaz (aynı köken + iki belgenin de katılması gerekir); PDF hedefinde atlanır.
 *  - pagereveal: gelen adres kayıtlı bir bölüm içeriyorsa (#modeller) geçiş bittikten sonra çapraz vurguyu çalıştırır.
 *  - Hat devamı: urunler.php'den gelindiyse head.php html[data-hat-devam] koyar; gelen /3 kesme noktası "hat-devam"
 *    adıyla giden telin yerine uçar (gecis.css), geçiş bitince ad ve öznitelik kaldırılır, sayfa kesme noktasını önce çizer.
 *  - Görünüm geçişi desteklemeyen tarayıcıda main 320 ms kısa giriş yapar (.gecis-yedek).
 *   gecisBitti()   Promise: açılış geçişi bitince çözülür (sayfa modülleri ilk çizimlerini buna bağlayabilir).
 */
import { hedefVurgula } from './capraz.js';

const kok = document.documentElement;
const destek = 'onpagereveal' in window && typeof CSS !== 'undefined' && CSS.supports && CSS.supports('selector(::view-transition)');

let bittiCoz;
const bitti = new Promise((c) => { bittiCoz = c; });
export function gecisBitti() { return bitti; }

function acilis() {
  const vt = window.__tkyGecis;
  const sonra = () => {
    kok.removeAttribute('data-hat-devam');
    bittiCoz();
    if (location.hash) hedefVurgula(location.hash);
  };
  if (vt && vt.finished) {
    vt.finished.then(sonra, sonra);
  } else if (!destek) {
    kok.classList.add('gecis-yedek');
    setTimeout(() => { kok.classList.remove('gecis-yedek'); sonra(); }, kok.dataset.hareket === 'az' ? 130 : 340);
  } else {
    sonra();
  }
}

export function gecisKur() {
  window.addEventListener('pageswap', (e) => {
    if (!e.viewTransition) return;
    const hedef = e.activation?.entry?.url || '';
    if (/\.pdf(#|\?|$)/i.test(hedef)) { e.viewTransition.skipTransition(); return; }
    window.tky?.sesCal?.('sayfa');
  });
  // geri/ileri önbellekten dönüşte vurguyu tekrar çalıştırma
  window.addEventListener('pageshow', (e) => { if (e.persisted) kok.classList.remove('gecis-yedek'); });
  acilis();
}
