<?php
/**
 * Föy 3, Ürün dağıtımı (paket P3). Dil bilgisi: tipografik afiş.
 * Üstte bara, bara üzerinden inen üç çıkış hattı, her hatta asılı dev bir satır. Hat seçilince enerjilenir,
 * föy kenarındaki kesme noktası tam tanımını açar ve hedef föyün kenarı sağdan görünür; tıklayınca föy döner
 * ve hat bir sonraki föyde devam eder. Armendus hattı çerçeveden çıkıp dış proje bayrağına gider.
 */
require __DIR__ . '/app/bootstrap.php';
$foy = foy('urunler');

$tpk = array_column(data('tpk')['modeller'], 'w');
$wAlt = (string) min($tpk);
$wUst = (string) max($tpk);

// Çıkış hatları. 'k' = bara üzerindeki çıkış sütunu (masaüstünde 0'dan sayılır, CSS ayarlar).
$cikislar = [
    'a' => [
        'id' => 'tekayper', 'harf' => 'A', 'baslik' => 'Tekayper ürünleri', 'ref' => '/4.A',
        'not' => marka_sayisi() . ' marka, ' . grup_sayisi() . ' ürün grubu. Koruma, kumanda, sürücü, sensör, ölçüm, kablo ve pano malzemeleri.',
    ],
    'b' => [
        'id' => 'sogutma', 'harf' => 'B', 'baslik' => 'Pano soğutma', 'ref' => '/5.A',
        'not' => 'TPK serisi pano klimaları, ' . $wAlt . "\u{00A0}W ile " . $wUst . "\u{00A0}W arası. Soğutma ihtiyacı hesabı.",
    ],
    'c' => [
        'id' => 'armendus', 'harf' => 'C', 'baslik' => 'Armendus', 'dis' => config('armendus_url'),
        'not' => armendus_cumlesi(),
    ],
];
foreach ($cikislar as $k => &$c) {
    if (isset($c['ref'])) {
        $x = capraz($c['ref']);
        $hf = foy($x['foy']);
        $c['href'] = $hf['href'];
        $c['kisa'] = '/' . $hf['no'];
        $c['tam'] = $x['tam'] . ' ' . $hf['ad'];
        $c['hedef'] = $hf;
    }
}
unset($c);
$giris = capraz('/1');

partial('head', [
    'foy' => $foy,
    'css' => ['sayfa-urunler'],
    'js' => ['sayfa-urunler'],
    'schema' => [schema_sayfa('CollectionPage', $foy, [
        'hasPart' => [
            ['@type' => 'WebPage', 'name' => 'Tekayper ürünleri', 'url' => kanonik(foy('malzeme'))],
            ['@type' => 'WebPage', 'name' => 'Pano soğutma sistemleri', 'url' => kanonik(foy('sogutma'))],
        ],
        'relatedLink' => config('armendus_url'),
    ])],
]);
partial('ust', ['foy' => $foy]);

/** Bir bölümün düşey hat parçaları: o bölümden geçen ya da o bölümde biten çıkışlar. */
$duseyler = function (string $bu) use ($cikislar): string {
    $sira = array_keys($cikislar);
    $html = '';
    foreach (array_slice($sira, array_search($bu, $sira, true)) as $k) {
        $tur = $k === $bu ? 'biter' : 'gecer';
        $html .= '<span class="dusey dusey--' . $k . ' dusey--' . $tur . '" data-hat="' . $k . '" aria-hidden="true"><i class="dusey__enerji"></i></span>';
    }
    return $html;
};
?>
<main id="icerik" class="foy foy--urunler" data-foy="3">
  <div class="dagitim" data-dagitim data-sc-verify-state="hat:yok">
    <div class="dagitim__bas">
      <h1 class="dagitim__h1">Ürünler</h1>
      <div class="bara">
        <a class="bara__giris" href="<?= e($giris['href']) ?>" data-capraz="/1" data-capraz-tam="<?= e($giris['tam'] . ' ' . $giris['ad']) ?>" aria-label="Bara, Föy 1 Tek hat şemasından besleniyor">
          <span class="bara__ref" aria-hidden="true">/1</span><i class="bara__ok" aria-hidden="true"></i>
        </a>
        <span class="bara__cizgi" aria-hidden="true"></span>
<?php foreach (array_keys($cikislar) as $k): ?>
        <i class="bara__nokta bara__nokta--<?= $k ?>" aria-hidden="true"></i>
<?php endforeach; ?>
      </div>
    </div>

<?php foreach ($cikislar as $k => $c): $dis = isset($c['dis']); ?>
    <section id="<?= e($c['id']) ?>" class="cikis cikis--<?= $k ?><?= $dis ? ' cikis--dis' : '' ?>" data-satir="<?= e($c['harf']) ?>" data-cikis="<?= $k ?>" data-sc-act="flow" aria-labelledby="cikis-<?= $k ?>">
      <?= $duseyler($k) ?>
      <span class="cikis__tel" aria-hidden="true"><i class="cikis__enerji"></i></span>
<?php if ($k !== array_key_last($cikislar)): ?>
      <i class="cikis__nokta" aria-hidden="true"></i>
<?php endif; ?>
      <div class="cikis__govde">
        <h2 class="cikis__baslik" id="cikis-<?= $k ?>" data-capraz-baslik>
<?php if ($dis): ?>
          <a class="cikis__a" href="<?= e($c['dis']) ?>" target="_blank" rel="noopener" aria-describedby="cikis-<?= $k ?>-not"><?= e($c['baslik']) ?><span class="gizli">, armendus.com, yeni sekmede açılır</span></a>
<?php else: ?>
          <a class="cikis__a" href="<?= e($c['href']) ?>" data-hedef="<?= e($c['hedef']['id']) ?>" data-ref="<?= e($c['ref']) ?>" aria-describedby="cikis-<?= $k ?>-not"><?= e($c['baslik']) ?><span class="gizli">, Föy <?= (int) $c['hedef']['no'] ?>, <?= e($c['hedef']['ad']) ?></span></a>
<?php endif; ?>
        </h2>
        <p class="cikis__not" id="cikis-<?= $k ?>-not"><?= e($c['not']) ?></p>
      </div>
<?php if ($dis): ?>
      <span class="kesme kesme--dis" aria-hidden="true">
        <span class="kesme__yazi"><span class="kesme__kisa">=ARM</span><span class="kesme__tam">=ARM armendus.com, yeni sekmede açılır</span></span>
        <span class="kesme__bayrak"><?= glif('dis-proje', 32, 'kesme__glif') ?></span>
      </span>
      <span class="cikis__cikan" aria-hidden="true"><i class="cikis__enerji"></i></span>
<?php else: ?>
      <span class="kesme" aria-hidden="true">
        <span class="kesme__yazi"><span class="kesme__kisa"><?= e($c['kisa']) ?></span><span class="kesme__tam"><?= e($c['tam']) ?></span></span>
        <i class="kesme__ok"></i>
      </span>
      <span class="cikis__devam" aria-hidden="true"><i class="cikis__enerji"></i></span>
<?php endif; ?>
    </section>
<?php endforeach; ?>
  </div>

  <section id="not" class="kapanis" data-satir="D" data-sc-act="flow" aria-labelledby="kapanis-b">
    <h2 class="gizli" id="kapanis-b">Not</h2>
    <p class="kapanis__satir">Aradığınız ürün listede yoksa bize sorun: <a class="alti-cizili" href="mailto:<?= e(config('email')) ?>"><?= e(config('email')) ?></a></p>
    <p class="kapanis__satir"><a class="alti-cizili" href="<?= e(url('iletisim.php#talep')) ?>">Teklif al</a></p>
  </section>

<?php foreach ($cikislar as $k => $c): if (empty($c['hedef'])) continue; $h = $c['hedef']; ?>
  <div class="dilim dilim--<?= $k ?>" data-dilim="<?= $k ?>" aria-hidden="true">
    <ol class="dilim__cetvel">
<?php foreach ($h['satirlar'] as $harf => [$sid, $sad]): ?>
      <li class="dilim__satir<?= $harf === 'A' ? ' is-hedef' : '' ?>"><?= e($harf) ?></li>
<?php endforeach; ?>
    </ol>
    <span class="dilim__antet"><span class="dilim__no"><?= (int) $h['no'] ?></span></span>
  </div>
<?php endforeach; ?>
</main>
<?php partial('antet', ['foy' => $foy]); partial('son', ['foy' => $foy]); ?>
