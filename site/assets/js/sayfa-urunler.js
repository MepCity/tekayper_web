/* sayfa-urunler.js: Föy 3, Ürün dağıtımı (paket P3).
 *
 * İmza hareketi: seçilen hat bir sonraki föyde devam eder.
 *  - Üzerine gelme ya da odak: hattın enerji bandı baradan föy kenarına koşar, kesme noktası tam tanımını
 *    açar (=TKY+MLZ/4.A Malzeme listesi), hedef föyün 40 px'lik kenarı sağdan kayar (data-etkin).
 *  - Tıklama: föy çevirme (gecis.css) başlar. Kenar şeridi ve kesme noktası ayrı görünüm geçişi adları alır
 *    (foy-dilim, hat-devam), yeni föy kenarın altından gelir. sessionStorage 'tky.hat' hedef föye hattın
 *    nereden geldiğini bildirir (gelen kesme noktasını önce çizmek için).
 *  - Dokunma: ilk dokunuş durumu 0,6 s gösterir, sonra gider (çift dokunma gerekmez).
 *  - Armendus: föy çevrilmez, yeni sekme; tel çerçeveden çıkar.
 * Yüklenince bara ve çıkışlar bir kez çizilir (CSS, 900 ms), föy çevirme bittikten sonra.
 * Kaydırma dinleyicisi yok; durum yalnızca işaretçi, odak ve dokunma olaylarıyla değişir.
 */
import { gecisBitti } from './gecis.js';

const kok = document.documentElement;
const dagitim = document.querySelector('[data-dagitim]');

if (dagitim) {
  const az = () => kok.dataset.hareket === 'az';
  const linkler = [...dagitim.querySelectorAll('.cikis__a')];
  const hatOf = (a) => a.closest('[data-cikis]')?.dataset.cikis || null;
  let etkin = null;
  let birakZaman = 0;
  let isaretci = 'mouse';
  let gidiyor = false;

  const durumYaz = () => {
    const dilim = etkin && document.querySelector(`[data-dilim="${etkin}"]`) ? etkin : 'yok';
    dagitim.dataset.scVerifyState = `cizim:${dagitim.classList.contains('is-ciz') || az() ? 'tamam' : 'bekliyor'} hat:${etkin || 'yok'} dilim:${dilim}${gidiyor ? ' gidis:' + etkin : ''}`;
  };

  const etkinlestir = (k) => {
    clearTimeout(birakZaman);
    if (gidiyor && k !== etkin) return;
    if (etkin === k) return;
    etkin = k;
    if (k) dagitim.dataset.etkin = k; else delete dagitim.dataset.etkin;
    durumYaz();
  };
  const birak = (gecikme = 90) => {
    if (gidiyor) return;
    clearTimeout(birakZaman);
    if (gecikme <= 0) { etkinlestir(null); return; }
    birakZaman = setTimeout(() => etkinlestir(null), gecikme);
  };

  /* föy çevirmeye hazırlık: adlar ve hedef föy için iz */
  const gidisHazirla = (a) => {
    const k = hatOf(a);
    etkinlestir(k);
    gidiyor = true;
    kok.dataset.gidis = k;
    try {
      const tel = a.closest('.cikis')?.querySelector('.cikis__tel');
      const y = tel ? tel.getBoundingClientRect().top / window.innerHeight : null;
      sessionStorage.setItem('tky.hat', JSON.stringify({ kaynak: '/3', hedef: a.dataset.ref || '', y, t: Date.now() }));
    } catch { /* özel pencere */ }
    durumYaz();
  };

  const sifirla = () => {
    gidiyor = false;
    delete kok.dataset.gidis;
    linkler.forEach((a) => delete a.dataset.hazir);
    etkinlestir(null);
  };

  document.addEventListener('pointerdown', (e) => { isaretci = e.pointerType || 'mouse'; }, { capture: true, passive: true });

  linkler.forEach((a) => {
    const k = hatOf(a);
    const dis = a.target === '_blank';

    a.addEventListener('pointerenter', (e) => { if (e.pointerType !== 'touch') etkinlestir(k); });
    a.addEventListener('pointerleave', (e) => { if (e.pointerType !== 'touch') birak(); });
    a.addEventListener('focus', () => etkinlestir(k));
    a.addEventListener('blur', () => birak(0));
    a.addEventListener('pointerdown', (e) => { if (e.pointerType === 'touch') etkinlestir(k); });

    a.addEventListener('click', (e) => {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      if (dis) { etkinlestir(k); return; }               // yeni sekme, föy çevrilmez
      if (isaretci === 'touch' && !a.dataset.hazir) {
        // dokunma: durumu 0,6 s göster, sonra git
        e.preventDefault();
        a.dataset.hazir = '1';
        etkinlestir(k);
        setTimeout(() => { gidisHazirla(a); location.assign(a.href); }, az() ? 250 : 600);
        return;
      }
      gidisHazirla(a);
    });
  });

  // başka bir yere dokununca durumu bırak
  document.addEventListener('pointerdown', (e) => {
    if (e.pointerType === 'touch' && !e.target.closest('.cikis__a')) birak(0);
  }, { passive: true });

  // geri dönüşte (bfcache) temiz başla
  window.addEventListener('pageshow', (e) => { if (e.persisted) sifirla(); });
  // föy çevrilirken adlar yalnızca bu geçiş için; geçiş yoksa (Firefox) zarar vermez
  window.addEventListener('pageswap', (e) => {
    if (!kok.dataset.gidis || !e.viewTransition) return;
    const hedef = e.activation?.entry?.url || '';
    const a = linkler.find((l) => l.href === hedef || hedef.startsWith(l.href.split('#')[0]));
    if (!a) delete kok.dataset.gidis;
  });

  // ilk çizim: föy çevirme bittikten sonra bir kez
  gecisBitti().then(() => {
    requestAnimationFrame(() => {
      dagitim.classList.add('is-ciz');
      durumYaz();
      // eski Teknokol adreslerinden (#armendus) ya da çapraz referansla gelindiyse o hat bir süre enerjili kalır
      let id = location.hash.slice(1);
      try { id = decodeURIComponent(id); } catch { /* bozuk kodlama */ }
      const hedef = id && dagitim.querySelector(`.cikis#${CSS.escape(id)}`);
      if (hedef) {
        const k = hedef.dataset.cikis;
        setTimeout(() => { if (!etkin) { etkinlestir(k); birak(2200); } }, az() ? 0 : 700);
      }
    });
  });
  durumYaz();
}
