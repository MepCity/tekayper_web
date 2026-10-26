<?php
/**
 * <!doctype> ... <body> açılışı.
 *   partial('head', [
 *     'foy'     => foy('sogutma'),            zorunlu
 *     'css'     => ['sayfa-pano'],            assets/css/{ad}.css (ortak dosyalardan sonra); 'form' de verilebilir
 *     'js'      => ['sayfa-pano'],            assets/js/{ad}.js, type="module" (son.php yazar)
 *     'gsap'    => ['ScrollTrigger', 'DrawSVGPlugin', 'Flip'] | true (hepsi) | false (varsayılan)
 *     'lenis'   => false,                     yumuşak kaydırma (index, kurumsal, referanslar, kataloglar)
 *     'onyukle' => [['urun/u01', '(min-width: 1024px) 560px, 100vw']],  LCP görseli (resim() adı, sizes)
 *     'schema'  => [schema_sayfa('AboutPage', $foy)],  sayfaya özel JSON-LD (firma ve breadcrumb otomatik)
 *     'baslik'  => null, 'aciklama' => null,  föy kaydındaki değerlerin yerine
 *     'noindex' => null,                      föy kaydındakinin yerine
 *   ]);
 */
$foy = $foy ?? foy('ana');
$gsapTum = ['ScrollTrigger', 'DrawSVGPlugin', 'Flip'];
$gsap = $gsap ?? false;
$gsap = $gsap === true ? $gsapTum : (is_array($gsap) ? array_values(array_intersect($gsapTum, $gsap)) : false);

$GLOBALS['sayfa'] = [
    'foy'   => $foy,
    'js'    => $js ?? [],
    'gsap'  => $gsap,
    'lenis' => (bool) ($lenis ?? false),
];

$baslik = $baslik ?? $foy['baslik'];
$aciklama = $aciklama ?? $foy['aciklama'];
$noindex = $noindex ?? $foy['noindex'];
$kanonik = kanonik($foy);
$zemin = $foy['zemin'];
$tema = $zemin === 'ozalit' ? '#16324A' : '#F1F2EE';

$cssOrtak = ['tokens', 'temel', 'cerceve', 'antet', 'bilesen', 'cizim', 'gecis'];
$cssListe = array_unique(array_merge($cssOrtak, $css ?? []));
$font = base_path() . '/assets/fonts/';
?><!doctype html>
<html lang="tr" class="no-js" data-hareket="az" data-foy="<?= (int) $foy['no'] ?>" data-foy-id="<?= e($foy['id']) ?>" data-zemin="<?= e($zemin) ?>"<?= $GLOBALS['sayfa']['lenis'] ? ' data-lenis' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($baslik) ?></title>
<meta name="description" content="<?= e($aciklama) ?>">
<?php if ($noindex): ?>
<meta name="robots" content="noindex, follow">
<?php endif; ?>
<link rel="canonical" href="<?= e($kanonik) ?>">
<meta name="theme-color" content="<?= $tema ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="tr_TR">
<meta property="og:site_name" content="Tekayper">
<meta property="og:title" content="<?= e($baslik) ?>">
<meta property="og:description" content="<?= e($aciklama) ?>">
<meta property="og:url" content="<?= e($kanonik) ?>">
<meta property="og:image" content="<?= e(og_gorsel($foy)) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="<?= e(url('favicon.ico')) ?>" sizes="48x48">
<link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(url('apple-touch-icon.png')) ?>">
<link rel="preload" href="<?= e($font) ?>chilldin-vf.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e($font) ?>osifont.woff2" as="font" type="font/woff2" crossorigin>
<?php foreach ($onyukle ?? [] as $o): ?>
<?= resim_onyukle(is_array($o) ? $o[0] : $o, is_array($o) ? ($o[1] ?? '100vw') : '100vw') ?>

<?php endforeach; ?>
<?php foreach ($cssListe as $c): if (is_file(ROOT . '/assets/css/' . $c . '.css')): ?>
<link rel="stylesheet" href="<?= e(asset('css/' . $c . '.css')) ?>">
<?php endif; endforeach; ?>
<script>
(function (h) {
  h.className = h.className.replace('no-js', 'js');
  var az = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  h.setAttribute('data-hareket', az ? 'az' : 'tam');
  try { if (!az && !sessionStorage.getItem('tky.cerceve')) { h.classList.add('cerceve-ciz'); sessionStorage.setItem('tky.cerceve', '1'); } } catch (e) {}
  try { if (localStorage.getItem('tky.ses') === '1') h.setAttribute('data-ses', 'acik'); } catch (e) {}
  // pagereveal ilk çizimden önce gelir; modüller o an yüklenmemiş olabilir, geçişi burada yakala (gecis.js okur)
  window.addEventListener('pagereveal', function (e) {
    window.__tkyGecis = e.viewTransition || null; window.__tkyAcilis = true;
    // urunler.php'den gelen hat: gelen /3 kesme noktası "hat-devam" adını alır (gecis.css), gecis.js geçiş bitince kaldırır
    try {
      if (!e.viewTransition) return;
      var k = window.navigation && navigation.activation && navigation.activation.from && navigation.activation.from.url;
      var t = JSON.parse(sessionStorage.getItem('tky.hat') || 'null');
      if ((k && /\/urunler\.php(?:[?#]|$)/.test(k)) || (t && t.kaynak === '/3' && Date.now() - (t.t || 0) < 5000)) h.setAttribute('data-hat-devam', '');
    } catch (x) {}
  }, { once: true });
})(document.documentElement);
</script>
<?php partial('schema', ['foy' => $foy, 'ek' => $schema ?? []]); ?>
</head>
<body class="foy-govde foy-govde--<?= e($foy['id']) ?>">
