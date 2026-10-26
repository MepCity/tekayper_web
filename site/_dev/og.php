<?php
/**
 * Paylaşım görseli (1200x630): bir föyün çizim sayfası olarak küçük hâli. Geliştirme aracı, yayına girmez.
 *   _dev/og.php?foy=sogutma  ->  ekran görüntüsü assets/img/og/sogutma.png olarak kaydedilir.
 *   _dev/og.php?foy=genel    ->  genel görsel (föy adı yerine firma adı).
 */
require dirname(__DIR__) . '/app/bootstrap.php';
$id = isset($_GET['foy']) && is_string($_GET['foy']) ? $_GET['foy'] : 'genel';
$genel = !isset(data('foyler')[$id]);
$foy = foy($genel ? 'ana' : $id);
$baslik = $genel ? 'Endüstriyel otomasyon malzemesi ve teknik destek' : ($foy['id'] === 'ana' ? 'Endüstriyel otomasyon malzemesi ve teknik destek' : ($foy['menu'] ?? $foy['ad']));
$alt = $genel ? 'Bursa Nilüfer' : $foy['etiket'] . '  ' . $foy['ad'];
$ozalit = $foy['zemin'] === 'ozalit' && !$genel;
?><!doctype html>
<html lang="tr" data-zemin="<?= $ozalit ? 'ozalit' : 'kagit' ?>">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex">
<title>OG <?= e($id) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
<style>
  html, body { margin: 0; width: 1200px; height: 630px; overflow: hidden; background: var(--zemin); color: var(--metin); font-family: var(--f-metin); }
  .sayfa { position: absolute; inset: 20px; border: 3px solid var(--cizgi-renk); }
  .cetvel { position: absolute; left: 28px; right: 0; top: 0; height: 28px; display: grid; grid-template-columns: repeat(8, 1fr); border-bottom: 1.5px solid var(--cizgi-renk); font-family: var(--f-cizim); font-size: 16px; }
  .cetvel span { display: grid; place-items: center; border-left: 1px solid var(--kil); }
  .cetvel span:first-child { border-left: 0; }
  .sol { position: absolute; left: 0; top: 28px; bottom: 0; width: 28px; border-right: 1.5px solid var(--cizgi-renk); display: grid; grid-template-rows: repeat(4, 1fr); font-family: var(--f-cizim); font-size: 16px; }
  .sol span { display: grid; place-items: center; border-top: 1px solid var(--kil); }
  .sol span:first-child { border-top: 0; }
  .icerik { position: absolute; left: 76px; top: 84px; right: 60px; }
  h1 { margin: 0; font-size: 84px; font-weight: 340; line-height: 0.98; letter-spacing: -0.025em; max-width: 900px; text-wrap: balance; }
  .alt { margin-top: 26px; font-family: var(--f-cizim); font-size: 26px; color: var(--metin-2); }
  .antet { position: absolute; right: 0; bottom: 0; display: grid; grid-template-columns: 230px 250px 170px; gap: 1.5px; background: var(--cizgi-renk); border-top: 1.5px solid var(--cizgi-renk); border-left: 1.5px solid var(--cizgi-renk); }
  .antet > div { background: var(--zemin); padding: 10px 14px 12px; display: grid; gap: 4px; align-content: start; }
  .antet .logo-h { background: var(--murekkep); display: grid; place-items: center; grid-row: span 2; }
  [data-zemin="ozalit"] .antet .logo-h { background: var(--zemin); }
  .logo { width: 180px; height: auto; }
  .logo__harf { fill: var(--logo-harf); } .logo__simsek { fill: var(--logo-simsek); }
  .e { font-family: var(--f-cizim); font-size: 14px; text-transform: uppercase; }
  .d { font-size: 20px; }
  .yapi { position: absolute; left: 28px; top: 28px; right: 0; bottom: 0; background-image: linear-gradient(var(--yapi) 1px, transparent 1px), linear-gradient(90deg, var(--yapi) 1px, transparent 1px); background-size: 20px 20px; opacity: 0.12; }
</style>
</head>
<body>
  <div class="sayfa">
    <div class="yapi"></div>
    <div class="cetvel"><?php for ($i = 1; $i <= 8; $i++) echo '<span>' . $i . '</span>'; ?></div>
    <div class="sol"><span>A</span><span>B</span><span>C</span><span>D</span></div>
    <div class="icerik">
      <h1><?= e($baslik) ?></h1>
      <p class="alt"><?= e($alt) ?></p>
    </div>
    <div class="antet">
      <div class="logo-h"><?php partial('logo', ['varyant' => $ozalit ? 'beyaz' : 'renkli']); ?></div>
      <div><span class="e">Firma</span><span class="d">Tekayper Elektromarket</span></div>
      <div><span class="e">Föy</span><span class="d"><?= e($genel ? 'tekayper.com' : $foy['etiket']) ?></span></div>
      <div><span class="e">Telefon</span><span class="d"><?= e(config('phone.label')) ?></span></div>
      <div><span class="e">Tanım</span><span class="d" style="font-family: var(--f-cizim)"><?= e($genel ? '=TKY' : $foy['tanim']) ?></span></div>
    </div>
  </div>
</body>
</html>
