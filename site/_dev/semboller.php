<?php
/**
 * Geliştirme sayfası (paket F2): bütün IEC sembolleri 32/48/96/192 px, çizim animasyonu ve süre ölçümü,
 * çizim ilkelleri gösterimi (ciz, enerji, kota, vurgula, harfle, kase, kontak) ve ses yöneticisi denemesi.
 * Yayına çıkmaz (_dev/.htaccess engeller, paket zip'inden çıkarılır).
 *
 * Parametreler: ?hareket=az (azaltılmış hareketi zorla), ?gsap=0 (GSAP yükleme), ?ad=kontaktor (tek sembol).
 * QA kancası: window.__f2 (olc, olcHepsi, sonDurum, ilkeller) ve body[data-hazir="evet"].
 */
declare(strict_types=1);

$kok = dirname(__DIR__);
if (is_file($kok . '/app/bootstrap.php')) {
    require $kok . '/app/bootstrap.php';
}
require_once $kok . '/app/partials/sembol.php';

$v = static function (string $yol) use ($kok): string {
    if (function_exists('asset')) {
        return asset($yol);
    }
    $f = $kok . '/assets/' . $yol;
    return '/assets/' . $yol . '?v=' . (is_file($f) ? substr((string) filemtime($f), -6) : '0');
};
$h = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$liste = sembol_listesi();
$tek = isset($_GET['ad']) ? preg_replace('/[^a-z0-9-]/', '', (string) $_GET['ad']) : '';
if ($tek !== '' && isset($liste[$tek])) {
    $liste = [$tek => $liste[$tek]];
}
$boylar = [32, 48, 96, 192];
$etiketler = [
    'besleme' => '', 'analizor' => '-P1', 'devre-kesici' => '-Q1', 'motor-koruma' => '-Q2', 'sigorta' => '-F1',
    'yuk-ayirici' => '-Q0', 'kontaktor' => '-K1', 'bobin' => '-K1', 'kontak-no' => '-K2', 'kontak-nc' => '-K2',
    'zaman-rolesi' => '-K3', 'ssr' => '-K4', 'surucu' => '-U1', 'motor' => '-M1', 'enkoder' => '-B2', 'plc' => '-A1',
    'guc-kaynagi' => '-G1', 'sensor-enduktif' => '-B1', 'fotosel' => '-B3', 'buton-no' => '-S1', 'acil-stop' => '-S0',
    'lamba' => '-H1', 'korna' => '-H2', 'isikli-kolon' => '-P2', 'klemens' => '-X1', 'klemens-sirasi' => '-X1',
    'konnektor' => '-X2', 'kablo' => '-W1', 'toprak' => '', 'trafo' => '-T1', 'sicaklik-kontrol' => '-N1',
    'termokupl' => '-B4', 'yuk-hucresi' => '-B5', 'fan' => '-E2', 'klima' => '-E1', 'priz' => '-X3',
    'aydinlatma' => '-E3', 'plazma' => '-U2', 'mekanik' => '-S2',
];
$yazilar = ['kesme-noktasi' => '/4.A', 'kablo' => '4G2,5'];
$css = array_filter(['css/tokens.css', 'css/temel.css', 'css/cizim.css'], static fn ($c) => is_file($kok . '/assets/' . $c));
$gsapVar = is_file($kok . '/assets/js/vendor/gsap/gsap.min.js') && ($_GET['gsap'] ?? '1') !== '0';
$hareket = ($_GET['hareket'] ?? '') === 'az' ? 'az' : '';
$kontrolSayisi = count(array_filter($liste, static fn ($s) => $s['kontrol']));
?><!doctype html>
<html lang="tr" data-hareket="<?= $hareket !== '' ? 'az' : 'tam' ?>"<?= $hareket !== '' ? ' data-hareket-zorla="az"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Semboller (geliştirme)</title>
<link rel="preload" href="<?= $h($v('fonts/osifont.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= $h($v('fonts/chilldin-vf.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<script>
  (function () {
    var h = document.documentElement;
    if (h.dataset.hareketZorla === 'az') return;
    var az = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    h.setAttribute('data-hareket', az ? 'az' : 'tam');
  })();
</script>
<?php foreach ($css as $c): ?>
<link rel="stylesheet" href="<?= $h($v($c)) ?>">
<?php endforeach; ?>
<style>
  @font-face { font-family: "Osifont"; src: url("<?= $h($v('fonts/osifont.woff2')) ?>") format("woff2"); font-display: swap; }
  @font-face { font-family: "ChillDIN"; src: url("<?= $h($v('fonts/chilldin-vf.woff2')) ?>") format("woff2"); font-weight: 325 700; font-stretch: 70% 100%; font-display: swap; }
  :root { --k: #F1F2EE; --m: #1B1D1F; --kil: #8E959C; }
  body { margin: 0; background: var(--kagit, var(--k)); color: var(--murekkep, var(--m)); font: 400 15px/1.5 "ChillDIN", Arial, sans-serif; }
  .dev { padding: 24px 32px 96px; max-width: 1600px; }
  .dev h1 { font-size: 28px; font-weight: 380; margin: 0 0 4px; letter-spacing: -0.01em; }
  .dev h2 { font-size: 20px; font-weight: 420; margin: 48px 0 12px; }
  .dev p { margin: 0 0 8px; max-width: 80ch; color: var(--n-600, #4B5157); }
  .arac { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 16px 0 8px; padding: 8px 0; border-bottom: 1px solid var(--kil); }
  .arac button, .ilkel button { font: 500 14px/1 "ChillDIN", Arial, sans-serif; background: var(--m); color: var(--k); border: 0; padding: 10px 14px; cursor: pointer; }
  .arac button[aria-pressed="true"] { background: #4B5157; }
  .arac output { font-family: "Osifont", Arial, sans-serif; font-size: 13px; }
  table.semboller { border-collapse: collapse; width: 100%; }
  table.semboller th, table.semboller td { border: 1px solid var(--kil); padding: 12px; vertical-align: middle; text-align: left; }
  table.semboller th { font-weight: 500; font-size: 13px; }
  td.ad { width: 220px; }
  td.ad b { display: block; font-family: "Osifont", Arial, sans-serif; font-weight: 400; font-size: 14px; }
  td.ad small { display: block; color: #4B5157; font-size: 13px; line-height: 1.35; }
  .kontrol-isaret { display: inline-block; margin-top: 4px; font-family: "Osifont", Arial, sans-serif; font-size: 13px; background: linear-gradient(#F9B233, #F9B233) 0 85% / 100% 0.42em no-repeat; }
  td.boy { text-align: center; }
  td.boy .sembol { margin: 0 auto; }
  td.boy[data-px="96"], td.boy[data-px="192"] { padding-left: 56px; }
  td.sure { font-family: "Osifont", Arial, sans-serif; font-size: 13px; width: 90px; }
  .ilkeller { display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 24px; }
  .ilkel { border: 1.5px solid var(--m); padding: 16px; position: relative; min-height: 220px; }
  .ilkel h3 { margin: 0 0 8px; font-weight: 500; font-size: 16px; }
  .ilkel .dugmeler { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
  .ilkel svg.alan { display: block; width: 100%; height: auto; color: var(--m); }
  .vurgu-ornek { font-size: 40px; font-weight: 360; letter-spacing: -0.02em; line-height: 1.1; margin: 16px 0; }
  .onay-hucre { position: relative; border: 1.5px solid var(--m); height: 200px; background: #E6E8E3; }
  .onay-hucre span { position: absolute; left: 8px; top: 6px; font-family: "Osifont", Arial, sans-serif; font-size: 13px; text-transform: uppercase; }
  .onay-hucre .kase { left: calc(50% - 5.5rem); top: 10px; }
  .yazi-ornek { font-family: "Osifont", Arial, sans-serif; font-size: 22px; }
  .sprite { display: flex; flex-wrap: wrap; gap: 16px; align-items: center; }
  .sprite figure { margin: 0; display: grid; justify-items: center; gap: 4px; font-family: "Osifont", Arial, sans-serif; font-size: 13px; }
  .ses-durum { font-family: "Osifont", Arial, sans-serif; font-size: 13px; white-space: pre-wrap; }
  label.kota-giris { display: inline-flex; gap: 8px; align-items: center; font-size: 14px; }
  label.kota-giris input { width: 7ch; font: inherit; padding: 6px; border: 1.5px solid var(--m); background: #E6E8E3; }
  input[type=range] { width: 100%; }
</style>
<?php if ($gsapVar): ?>
<script defer src="<?= $h($v('js/vendor/gsap/gsap.min.js')) ?>"></script>
<?php endif; ?>
</head>
<body>
<main class="dev">
  <h1>IEC 60617 sembol kütüphanesi</h1>
  <p>Geliştirme sayfası. <?= count($liste) ?> sembol, her biri 32, 48, 96 ve 192 piksel. Çizgi kalınlığı her boyda 1,5 px (non-scaling-stroke). Uç numaraları 84 px ve üstünde görünür. Sarı işaretli <?= $kontrolSayisi ?> sembol mühendis kontrolü bekliyor (data-kontrol).</p>
  <p>Hareket modu: <b data-hareket-goster></b>. GSAP: <b><?= $gsapVar ? 'yüklü (kaydırma çizelgesi gsap.timeline)' : 'yok (yerleşik çizelge)' ?></b>.</p>

  <div class="arac" role="toolbar" aria-label="Sembol araçları">
    <button type="button" id="hepsini-ciz">Hepsini çiz</button>
    <button type="button" id="baglanti-goster" aria-pressed="false">Bağlantı noktaları</button>
    <button type="button" id="ses-ac" aria-pressed="false">Sesi aç</button>
    <output id="ozet" aria-live="polite"></output>
  </div>

  <div style="overflow-x:auto">
  <table class="semboller">
    <thead><tr><th>Sembol</th><?php foreach ($boylar as $b): ?><th><?= $b ?> px</th><?php endforeach; ?><th>Çizim</th></tr></thead>
    <tbody>
    <?php foreach ($liste as $ad => $s): ?>
      <tr data-ad="<?= $h($ad) ?>"<?= $s['kontrol'] ? ' data-kontrol="evet"' : '' ?>>
        <td class="ad">
          <b><?= $h($ad) ?></b>
          <small><?= $h($s['baslik']) ?></small>
          <?php if ($s['kontrol']): ?><span class="kontrol-isaret">kontrol</span><?php endif; ?>
        </td>
        <?php foreach ($boylar as $b): ?>
          <td class="boy" data-px="<?= $b ?>">
            <?= sembol($ad, array_filter([
                'buyukluk' => $b,
                'etiket' => $b >= 96 ? ($etiketler[$ad] ?? '') : '',
                'yazi' => $yazilar[$ad] ?? null,
                'sinif' => 'dev-sembol',
            ], static fn ($x) => $x !== null && $x !== '')) ?>
          </td>
        <?php endforeach; ?>
        <td class="sure"><button type="button" class="ciz-tek" data-ad="<?= $h($ad) ?>" aria-label="<?= $h($ad) ?> çiz">Çiz</button> <output data-sure></output></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <h2>Sprite (iec.svg, &lt;use&gt;), 32 px</h2>
  <div class="sprite">
    <?php foreach ($liste as $ad => $s): ?>
      <figure><?= sembol_ikon($ad, 32) ?><figcaption><?= $h($ad) ?></figcaption></figure>
    <?php endforeach; ?>
  </div>

  <h2>Çizim ilkelleri</h2>
  <div class="ilkeller">
    <section class="ilkel" id="ilkel-ciz">
      <h3>ciz() ve enerji(): güç hattı, kesik-noktalı pano sınırı</h3>
      <svg class="alan" viewBox="0 0 480 200" aria-hidden="true">
        <rect class="cizgi--sinir" x="200" y="20" width="250" height="150" data-sira="2"/>
        <path id="ornek-hat" class="cizgi--kalin" d="M20 20V100H320V180" data-sira="1"/>
      </svg>
      <div class="dugmeler">
        <button type="button" data-islem="ciz">Çiz</button>
        <button type="button" data-islem="enerji">Enerji ver</button>
      </div>
      <p>Kaydırma çizelgesi (scrub):</p>
      <input type="range" min="0" max="1000" value="0" id="kaydir" aria-label="Kaydırma ilerlemesi">
      <svg class="alan" viewBox="0 0 480 60" aria-hidden="true">
        <path id="kaydir-hat" class="cizgi--kalin" d="M20 30H460"/>
      </svg>
    </section>

    <section class="ilkel" id="ilkel-kota">
      <h3>kota(): ölçü çizgisi ve guncelle()</h3>
      <svg class="alan" id="kota-alan" viewBox="0 0 480 300" aria-hidden="true">
        <rect id="kota-pano" class="cizgi" x="190" y="60" width="100" height="220"/>
      </svg>
      <div class="dugmeler">
        <label class="kota-giris">Genişlik (mm) <input type="number" id="kota-w" value="800" min="200" max="2400" step="100"></label>
        <button type="button" data-islem="kota">Yeniden çiz</button>
      </div>
    </section>

    <section class="ilkel" id="ilkel-vurgu">
      <h3>vurgula() ve harfle()</h3>
      <p class="vurgu-ornek"><span id="vurgu-hedef">Kontaktör ve röle</span></p>
      <p class="yazi-ornek"><span id="harf-hedef">=TKY+SGT/5.C</span></p>
      <div class="dugmeler">
        <button type="button" data-islem="vurgu-kalici">Kalıcı vurgu</button>
        <button type="button" data-islem="vurgu-gecici">Çapraz referans vurgusu</button>
        <button type="button" data-islem="harfle">Harfle</button>
      </div>
    </section>

    <section class="ilkel" id="ilkel-kase">
      <h3>kase(): onay kaşesi</h3>
      <div class="onay-hucre" id="onay"><span>Onay</span></div>
      <div class="dugmeler"><button type="button" data-islem="kase">Kaşe bas</button></div>
    </section>

    <section class="ilkel" id="ilkel-kontak">
      <h3>kontak(): bıçak mafsal etrafında döner (180 ms)</h3>
      <div style="display:flex;flex-wrap:wrap;gap:40px 64px;align-items:flex-start;padding:8px 0 0 40px">
        <?= sembol('kontak-no', ['buyukluk' => 96, 'etiket' => '-K2', 'uclar' => ['ust' => '13', 'alt' => '14'], 'sinif' => 'ornek-kontak']) ?>
        <?= sembol('kontaktor', ['buyukluk' => 96, 'etiket' => '-K1', 'sinif' => 'ornek-kontaktor']) ?>
        <?= sembol('bobin', ['buyukluk' => 96, 'etiket' => '-K1', 'ayna' => [['1', '2', '/1.D'], ['3', '4', '/1.D'], ['5', '6', '/1.D'], ['13', '14', '/1.C'], ['21', '22', '', 'nc']]]) ?>
      </div>
      <div class="dugmeler">
        <button type="button" data-islem="kontak">Kontakları kapat / aç</button>
      </div>
    </section>

    <section class="ilkel" id="ilkel-ses">
      <h3>ses: liste boşken cal() sessizdir</h3>
      <div class="dugmeler">
        <button type="button" data-islem="ses-cal">Bütün sesleri çal</button>
        <button type="button" data-islem="ses-durum">Durum</button>
      </div>
      <p class="ses-durum" id="ses-durum" aria-live="polite"></p>
    </section>
  </div>
</main>

<script type="module">
  import { ciz, enerji, sembolCiz, harfle, kota, vurgula, kase, kontak, gizle, baglantilar, hareketAz } from '<?= $h($v('js/cizim.js')) ?>';
  import { ses } from '<?= $h($v('js/ses.js')) ?>';

  const $ = (s, k = document) => k.querySelector(s);
  const $$ = (s, k = document) => [...k.querySelectorAll(s)];
  $('[data-hareket-goster]').textContent = hareketAz() ? 'az (son durum hemen)' : 'tam';

  const sonuc = {};
  async function olc(ad) {
    const tr = $(`tr[data-ad="${ad}"]`);
    if (!tr) return null;
    const hepsi = $$('.sembol', tr);
    hepsi.forEach((s) => gizle(s));
    const t0 = performance.now();
    await Promise.all(hepsi.map((s) => sembolCiz(s)));
    const ms = Math.round(performance.now() - t0);
    sonuc[ad] = ms;
    $('[data-sure]', tr).textContent = `${ms} ms`;
    return ms;
  }
  async function olcHepsi() {
    for (const tr of $$('tr[data-ad]')) await olc(tr.dataset.ad);
    const degerler = Object.values(sonuc);
    $('#ozet').textContent = `en uzun ${Math.max(...degerler)} ms, ortalama ${Math.round(degerler.reduce((a, b) => a + b, 0) / degerler.length)} ms`;
    return { ...sonuc };
  }
  /* Son durumda olmayan öğeler (çizilmemiş, görünmez ya da kırpılmış) */
  function sonDurum(kok = document) {
    const sorunlu = [];
    $$('[data-sira], .sembol__etiket, .hat-enerji', kok).forEach((el) => {
      const s = el.style;
      if ((s.strokeDasharray && s.strokeDasharray !== '' ) || s.opacity === '0' || (s.clipPath && s.clipPath !== '')) {
        sorunlu.push((el.closest('[data-sembol]')?.dataset.sembol || el.id || el.className.baseVal || el.className) + ':' + (s.strokeDasharray || s.opacity || s.clipPath));
      }
    });
    return sorunlu;
  }

  $('#hepsini-ciz').addEventListener('click', olcHepsi);
  $$('.ciz-tek').forEach((b) => b.addEventListener('click', () => olc(b.dataset.ad)));
  $('#baglanti-goster').addEventListener('click', (e) => {
    const on = document.body.classList.toggle('baglanti-goster');
    e.currentTarget.setAttribute('aria-pressed', String(on));
  });
  const sesDugme = $('#ses-ac');
  const sesYaz = () => { sesDugme.setAttribute('aria-pressed', String(ses.acikMi())); sesDugme.textContent = ses.acikMi() ? 'Sesi kapat' : 'Sesi aç'; };
  sesDugme.addEventListener('click', () => ses.degistir().then(sesYaz));
  document.addEventListener('ses:degisti', sesYaz);
  sesYaz();

  /* İlkeller */
  const hat = $('#ornek-hat');
  const kaydirCizelge = ciz('#kaydir-hat', { scrub: true });
  $('#kaydir').addEventListener('input', (e) => kaydirCizelge.progress(e.target.value / 1000));

  const kotaRect = $('#kota-pano');
  let kotalar = [];
  function kotaKur() {
    kotalar.forEach((k) => k.sil());
    const r = { x: +kotaRect.getAttribute('x'), y: +kotaRect.getAttribute('y'), w: +kotaRect.getAttribute('width'), h: +kotaRect.getAttribute('height') };
    kotalar = [
      kota('#kota-alan', { x1: r.x, y1: r.y, x2: r.x + r.w, y2: r.y, deger: +$('#kota-w').value, birim: '', yon: 'yatay', cizgi: r.y - 24 }),
      kota('#kota-alan', { x1: r.x, y1: r.y, x2: r.x, y2: r.y + r.h, deger: 2000, yon: 'dikey', cizgi: r.x - 28 }),
    ];
    return Promise.all(kotalar.map((k) => k.hazir));
  }
  kotaKur();
  $('#kota-w').addEventListener('input', (e) => {
    const mm = Math.min(2400, Math.max(200, +e.target.value || 800));
    const w = mm / 8;
    const x = 240 - w / 2;
    kotaRect.setAttribute('x', x); kotaRect.setAttribute('width', w);
    kotalar[0].guncelle(mm, { x1: x, x2: x + w });
    kotalar[1].guncelle(2000, { x1: x, x2: x, cizgi: x - 28 });
  });

  let kontakKapali = false;
  const islemler = {
    ciz: () => ciz('#ilkel-ciz svg.alan', { sure: 900 }),
    enerji: () => enerji(hat),
    kota: kotaKur,
    'vurgu-kalici': () => vurgula($('#vurgu-hedef')),
    'vurgu-gecici': () => vurgula($('#vurgu-hedef'), { kalici: false }),
    harfle: () => harfle($('#harf-hedef')),
    kase: () => { $$('#onay .kase').forEach((k) => k.remove()); return kase($('#onay'), { metin: 'Talep alındı', no: 'T-20261024-07', tarih: '24.10.2026' }); },
    kontak: () => { kontakKapali = !kontakKapali; return Promise.all([kontak($('.ornek-kontak'), kontakKapali), kontak($('.ornek-kontaktor'), kontakKapali, { ses: 'kontaktor' })]); },
    'ses-cal': () => { ['sayfa', 'kalem', 'kontaktor', 'motor', 'ugultu', 'fan', 'kontak', 'kase', 'antet', 'katalog'].forEach((a) => ses.cal(a)); ses.surdur('kalem'); return islemler['ses-durum'](); },
    'ses-durum': () => new Promise((ok) => setTimeout(ok, 300)).then(() => { $('#ses-durum').textContent = JSON.stringify(ses.durum()); return ses.durum(); }),
  };
  $$('[data-islem]').forEach((b) => b.addEventListener('click', () => islemler[b.dataset.islem]()));

  window.__f2 = { olc, olcHepsi, sonDurum, islemler, baglantilar, ses, ilkeller: { ciz, enerji, sembolCiz, harfle, kota, vurgula, kase, kontak, gizle }, kaydirCizelge };
  document.fonts.ready.then(() => { document.body.dataset.hazir = 'evet'; });
</script>
</body>
</html>
