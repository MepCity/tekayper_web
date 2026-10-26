<?php
/**
 * Çizim çerçevesi: sabit dış çizgi, kenar maskeleri, üst sütun cetveli 1-8 ve sol satır cetveli (bu föyün bölüm harfleri).
 * Sol cetvel harfleri bölümlere bağlantıdır; site.js görünür bölümün harfini koyulaştırır.
 */
$foy = $foy ?? $GLOBALS['sayfa']['foy'];
?>
<div class="cerceve" aria-hidden="true">
  <div class="cerceve__maske cerceve__maske--ust"></div>
  <div class="cerceve__maske cerceve__maske--sol"></div>
  <div class="cerceve__maske cerceve__maske--sag"></div>
  <div class="cerceve__maske cerceve__maske--alt"></div>
  <div class="cerceve__dis"></div>
  <div class="cerceve__ic"></div>
  <div class="cetvel cetvel--ust">
    <div class="cetvel__izgara">
<?php for ($i = 1; $i <= 8; $i++): ?>
      <span class="cetvel__sutun" data-sutun="<?= $i ?>"><?= $i ?></span>
<?php endfor; ?>
    </div>
  </div>
</div>
<nav class="cetvel cetvel--sol" aria-label="Föy bölümleri">
  <ol class="cetvel__satirlar">
<?php foreach ($foy['satirlar'] as $harf => [$id, $ad]): ?>
    <li><a class="cetvel__satir" href="#<?= e($id) ?>" data-satir-baglanti="<?= e($harf) ?>" aria-label="Bölüm <?= e($harf) ?>: <?= e($ad) ?>"><?= e($harf) ?></a></li>
<?php endforeach; ?>
  </ol>
</nav>
