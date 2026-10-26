<?php
/**
 * Atlama bağlantısı, başlık (çizim çerçevesinin üst kenarı), çerçeve ve cetveller, telefon föy listesi.
 *   partial('ust', ['foy' => $foy]);
 */
$foy = $foy ?? $GLOBALS['sayfa']['foy'];
$aktif = $foy['id'];
$aktifUst = $foy['ust'] ?? null;

$menu = [
    ['id' => 'ana'], ['id' => 'kurumsal'],
    ['id' => 'urunler', 'alt' => [['id' => 'malzeme'], ['dis' => true], ['id' => 'sogutma']]],
    ['id' => 'referans'], ['id' => 'ekler'], ['id' => 'iletisim'],
];

$menuA = function (array $f, string $sinif) use ($aktif, $aktifUst): string {
    $cur = $f['id'] === $aktif ? ' aria-current="page"' : '';
    $ust = $f['id'] === $aktifUst ? ' is-ust-aktif' : '';
    return '<a class="' . $sinif . $ust . '" href="' . e($f['href']) . '"' . $cur . '><span class="menu__etiket">' . e($f['menu'])
         . '</span><span class="menu__no" aria-hidden="true">/' . (int) $f['no'] . '</span></a>';
};
$armendus = '<a class="menu__a menu__a--dis" href="' . e(config('armendus_url')) . '" target="_blank" rel="noopener">'
          . '<span class="menu__etiket">Armendus Ürünler</span><span class="menu__no" aria-hidden="true">=ARM</span>'
          . glif('dis-proje', 16, 'menu__glif') . '<span class="gizli"> (yeni sekmede açılır)</span></a>';
$sesDugme = function (string $sinif): string {
    return '<button class="' . $sinif . '" type="button" data-ses-dugme aria-label="Sesi aç">' . glif('hoparlor', 24) . '</button>';
};
?>
<a class="atla" href="#icerik">İçeriğe geç</a>
<header class="ust">
  <a class="ust__logo" href="<?= e(url('')) ?>" aria-label="Tekayper, anasayfa"<?= $aktif === 'ana' ? ' aria-current="page"' : '' ?>><?php partial('logo', ['varyant' => $foy['zemin'] === 'ozalit' ? 'beyaz' : 'renkli']); ?></a>
  <nav class="ust__menu" aria-label="Ana menü">
    <ul class="menu">
<?php foreach ($menu as $m): $f = foy($m['id']); ?>
<?php if (empty($m['alt'])): ?>
      <li class="menu__oge"><?= $menuA($f, 'menu__a') ?></li>
<?php else: ?>
      <li class="menu__oge menu__oge--alt" data-altmenu>
        <?= $menuA($f, 'menu__a') ?><button class="menu__ac" type="button" aria-expanded="false" aria-controls="altmenu-<?= e($m['id']) ?>"><span class="gizli">Ürünler alt menüsü</span><svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="M2.25 4.5 6 8.25 9.75 4.5" fill="none" stroke="currentColor" stroke-width="1.5"/></svg></button>
        <ul class="menu__alt" id="altmenu-<?= e($m['id']) ?>">
<?php foreach ($m['alt'] as $a): ?>
          <li><?= !empty($a['dis']) ? $armendus : $menuA(foy($a['id']), 'menu__a') ?></li>
<?php endforeach; ?>
        </ul>
      </li>
<?php endif; ?>
<?php endforeach; ?>
    </ul>
  </nav>
  <p class="ust__foy"><span class="ust__foy-no"><?= e($foy['no'] > 0 ? $foy['etiket'] : 'Föy ?/' . $foy['toplam']) ?></span> <span class="ust__foy-ad"><?= e($foy['ad']) ?></span></p>
  <a class="ust__teklif dugme dugme--birincil dugme--kucuk" href="<?= e(url('iletisim.php#talep')) ?>"<?= $aktif === 'iletisim' ? ' data-ayni-sayfa' : '' ?>><span class="dugme__etiket" data-etiket="Teklif Al">Teklif Al</span></a>
  <?= $sesDugme('ust__ses') ?>
  <button class="ust__liste" type="button" aria-haspopup="dialog" aria-controls="foy-listesi" aria-label="Föy listesi"><?= glif('menu', 22) ?><span class="ust__liste-metin">Föy listesi</span></button>
</header>
<?php partial('cerceve', ['foy' => $foy]); ?>
<dialog class="foy-dizini" id="foy-listesi" aria-labelledby="foy-dizini-baslik">
  <div class="foy-dizini__ic">
    <div class="foy-dizini__ust">
      <h2 class="foy-dizini__baslik" id="foy-dizini-baslik">Föy listesi</h2>
      <button class="foy-dizini__kapat" type="button" data-kapat aria-label="Föy listesini kapat"><?= glif('kapat', 24) ?></button>
    </div>
    <ol class="foy-dizini__liste">
<?php foreach (foy_listesi() as $f): if ($f['menu'] === null) continue; ?>
      <li class="foy-dizini__oge<?= in_array($f['id'], ['malzeme', 'sogutma'], true) ? ' foy-dizini__oge--alt' : '' ?>">
        <a href="<?= e($f['href']) ?>"<?= $f['id'] === $aktif ? ' aria-current="page"' : '' ?>><span class="foy-dizini__no" aria-hidden="true">/<?= (int) $f['no'] ?></span><span class="foy-dizini__menu"><?= e($f['menu']) ?></span><span class="foy-dizini__ad"><?= e($f['ad']) ?></span></a>
      </li>
<?php if ($f['id'] === 'malzeme'): ?>
      <li class="foy-dizini__oge foy-dizini__oge--alt"><a href="<?= e(config('armendus_url')) ?>" target="_blank" rel="noopener"><span class="foy-dizini__no" aria-hidden="true">=ARM</span><span class="foy-dizini__menu">Armendus Ürünler</span><span class="foy-dizini__ad">armendus.com<span class="gizli"> (yeni sekmede açılır)</span></span></a></li>
<?php endif; ?>
<?php endforeach; ?>
    </ol>
    <div class="foy-dizini__alt">
      <?= dugme('Teklif Al', url('iletisim.php#talep')) ?>
      <button class="foy-dizini__ses" type="button" data-ses-dugme aria-pressed="false"><?= glif('hoparlor', 24) ?><span data-ses-metin>Ses kapalı</span></button>
    </div>
  </div>
</dialog>
