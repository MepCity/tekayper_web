<?php
/**
 * Föy 0, Föy yok (spec 3.10, paket P9). HTTP 404.
 * Sizi buraya getiren çapraz referans projede olmayan bir föyü gösteriyor: sol üstten çizilen hat
 * kopuk bir kesme noktasında "/?" ile biter. Föy listesinden bir föy seçince (üzerine gelme ya da odak)
 * kopukluk kapanır, hat enerjilenir ve kesme noktası o föyün numarasını alır.
 * Apache: .htaccess ErrorDocument 404 /404.php; nginx: error_page 404 /404.php (tools/nginx-ornek.conf);
 * php -S: tools/qa/router.php. Bütün adresler kök göreli (url/asset), derin yollarda da çalışır.
 */
require_once __DIR__ . '/app/bootstrap.php';
http_response_code(404);
header('Cache-Control: no-store');

$foy = foy('yok');

// İstenen adres (yalnızca gösterim; kaçışlı, kısaltılmış). Doğrudan /404.php açıldıysa gösterilmez.
$istenen = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$istenen = rawurldecode($istenen);
if (!mb_check_encoding($istenen, 'UTF-8')) {
    $istenen = '';
}
if ($istenen !== '' && preg_match('~/404\.php$~', $istenen)) {
    $istenen = '';
}
if (mb_strlen($istenen) > 64) {
    $istenen = mb_substr($istenen, 0, 63) . '…';
}

$foyler = array_values(array_filter(foy_listesi(), fn ($f) => $f['no'] >= 1 && $f['no'] <= 8));

partial('head', ['foy' => $foy, 'css' => ['sayfa-yok'], 'js' => ['sayfa-yok']]);
partial('ust', ['foy' => $foy]);
?>
<main id="icerik" class="foy foy--yok" data-foy="0">
  <section id="yok" class="yok" data-satir="A" data-sc-act="flow" aria-labelledby="yok-baslik">
    <div class="izgara yok__izgara">

      <div class="yok__ust" data-yok-sahne data-sc-verify-state="hat:kopuk ref:/?">
        <div class="yok__cizim" aria-hidden="true">
          <span class="yok__baslangic"></span>
          <span class="yok__tel yok__tel--yatay"><span class="yok__enerji"></span></span>
          <span class="yok__tel yok__tel--dusey"><span class="yok__enerji"></span></span>
          <span class="yok__kopuk"><span class="yok__kopuk-cizgi"></span></span>
          <span class="yok__uc">
            <svg class="yok__ok" width="24" height="24" viewBox="0 0 24 24" focusable="false"><path d="M4 2.75h16L12 21.25Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="miter"/></svg>
            <span class="yok__ref" data-yok-ref>/?</span>
            <span class="yok__ref-ad" data-yok-ad></span>
          </span>
        </div>

<?php if ($istenen !== ''): ?>
        <p class="yok__adres"><span class="yok__adres-etiket">İstenen adres</span> <span class="yok__adres-yol"><?= e($istenen) ?></span></p>
<?php endif; ?>
        <h1 class="yok__baslik t-display" id="yok-baslik" data-capraz-baslik>Bu föy projede yok</h1>
        <p class="yok__metin">Aradığınız sayfa taşınmış ya da kaldırılmış olabilir. Föy listesinden devam edin.</p>
      </div>

      <nav class="yok__liste" aria-labelledby="yok-liste-baslik">
        <h2 class="yok__liste-baslik" id="yok-liste-baslik">Föy listesi</h2>
        <div class="yok__tablo-bas" aria-hidden="true">
          <span>Föy</span><span>Sayfa</span><span>Föy adı</span><span>Tanım</span>
        </div>
        <ol class="yok__foyler">
<?php foreach ($foyler as $f): ?>
          <li>
            <a class="yok__foy" href="<?= e($f['href']) ?>" data-capraz="/<?= (int) $f['no'] ?>" data-yok-foy="/<?= (int) $f['no'] ?>" data-yok-foy-ad="<?= e($f['ad']) ?>">
              <span class="yok__foy-no" aria-hidden="true">/<?= (int) $f['no'] ?></span>
              <span class="yok__foy-menu"><?= e($f['menu']) ?></span>
              <span class="yok__foy-ad"><span class="gizli">, föy <?= (int) $f['no'] ?>: </span><?= e($f['ad']) ?></span>
              <span class="yok__foy-tanim" aria-hidden="true"><?= e($f['tanim']) ?></span>
            </a>
          </li>
<?php endforeach; ?>
        </ol>
      </nav>

    </div>
  </section>
</main>
<?php partial('antet', ['foy' => $foy]); partial('son', ['foy' => $foy]); ?>
