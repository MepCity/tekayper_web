<?php
/**
 * Kesme noktası (interruption point): bir hattın başka föyde devam ettiğini gösteren çapraz referans işareti.
 *   partial('kesme-noktasi', ['ref' => '/3']);                         gelen hat: "bu hat Föy 3'ten geliyor"
 *   partial('kesme-noktasi', ['ref' => '/4.A', 'yon' => 'giden']);    giden hat
 *   'metin' => 'Ürün dağıtımı'  (yoksa föy/bölüm adı), 'sinif' => ek sınıf
 * Sembol F2'nin iec-kesme-noktasi.svg dosyasından gelir; yoksa aynı geometride yedek çizilir.
 * Elemanın kendisi bağlantıdır (hedef föye döner). data-kesme="gelen|giden" sayfa betiğine çizim sırasında kanca verir.
 */
$ref = $ref ?? '/1';
$yon = $yon ?? 'gelen';
$c = capraz($ref);
if ($c !== null):
    $metin = $metin ?? $c['ad'];
    $etiket = $yon === 'gelen' ? 'Hat ' . $c['kisa'] . ' föyünden geliyor: ' . $metin : 'Hat ' . $c['kisa'] . ' föyünde devam ediyor: ' . $metin;
    if (is_file(ROOT . '/assets/sembol/iec-kesme-noktasi.svg') && sembol_hazir()) {
        $isaret = sembol('kesme-noktasi', ['buyukluk' => 48, 'sinif' => 'kesme-noktasi__sembol']);
    } else {
        // yedek: hat ucu + ok bayrağı (16 px modül, 1,5 px çizgi)
        $isaret = '<svg class="kesme-noktasi__sembol" width="48" height="24" viewBox="0 0 48 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">'
                . ($yon === 'gelen'
                    ? '<path d="M4 6v12l8-6z" fill="currentColor"/><path d="M12 12h36" stroke-width="2"/>'
                    : '<path d="M0 12h36" stroke-width="2"/><path d="M36 6v12l8-6z" fill="currentColor"/>')
                . '</svg>';
    }
?>
<a class="kesme-noktasi kesme-noktasi--<?= e($yon) ?><?= isset($sinif) ? ' ' . e($sinif) : '' ?>" href="<?= e($c['href']) ?>" data-kesme="<?= e($yon) ?>" data-capraz="<?= e($c['kisa']) ?>" data-capraz-tam="<?= e($c['tam'] . ' ' . $c['ad']) ?>" aria-label="<?= e($etiket) ?>">
  <?= $isaret ?>
  <span class="kesme-noktasi__ref" aria-hidden="true"><?= e($c['kisa']) ?></span>
</a>
<?php endif; ?>
