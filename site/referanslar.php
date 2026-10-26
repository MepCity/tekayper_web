<?php
/**
 * Föy 6, Referans listesi (paket P6).
 * Bobin -K0 TEKAYPER ve altındaki kontak aynası: 41 firma, Türkçe alfabe sırası, her firma bir kapanan kontak
 * (13-14, 23-24 ... 413-414). Satır ekranın %70 çizgisine gelince kontağı bir kez kapanır (sayfa-referans.js).
 * Masaüstünde ayna üç sütundur (çizim bölgeleri 2-3, 4-5, 6-7), sütunlar harf gruplarının sınırında bölünür.
 */
require __DIR__ . '/app/bootstrap.php';
sembol_hazir();
$foy = foy('referans');

/* ---------------------------------------------------------------- veri */
$firmalar = array_values(tr_sirala(data('referanslar'), 'ad'));
$harfId = static function (string $harf): string {
    return 'harf-' . strtr(tr_kucuk($harf), ['ç' => 'c2', 'ğ' => 'g2', 'ı' => 'i1', 'ö' => 'o2', 'ş' => 's2', 'ü' => 'u2']);
};
$gruplar = [];
foreach ($firmalar as $i => $f) {
    $f['sira'] = $i + 1;
    $f['ust'] = (string) (($i + 1) * 10 + 3);
    $f['alt'] = (string) (($i + 1) * 10 + 4);
    $h = $f['harf'];
    if (!isset($gruplar[$h])) {
        $gruplar[$h] = ['harf' => $h, 'id' => $harfId($h), 'firmalar' => []];
    }
    $gruplar[$h]['firmalar'][] = $f;
}
$gruplar = array_values($gruplar);
$toplam = count($firmalar);

/* Üç sütun: grup sınırlarında, en uzun sütunu en kısa tutan bölme (son sütunda NOT plakası da var). */
$boy = static fn (array $g): int => 72 + 156 * count($g['firmalar']);
$G = count($gruplar);
$enIyi = [1, 2];
$enIyiBoy = PHP_INT_MAX;
for ($a = 1; $a < $G - 1; $a++) {
    for ($b = $a + 1; $b < $G; $b++) {
        $s = [0, 0, 320];
        foreach ($gruplar as $k => $g) {
            $s[$k < $a ? 0 : ($k < $b ? 1 : 2)] += $boy($g);
        }
        if (max($s) < $enIyiBoy) {
            $enIyiBoy = max($s);
            $enIyi = [$a, $b];
        }
    }
}
$sutunlar = [
    array_slice($gruplar, 0, $enIyi[0]),
    array_slice($gruplar, $enIyi[0], $enIyi[1] - $enIyi[0]),
    array_slice($gruplar, $enIyi[1]),
];

/* Yatay kapanan kontak: F2'nin kontak-no sembolü 90 derece döndürülür (mafsal solda, bıçak sağa ve yukarı açık). */
$kontakG = sembol('kontak-no', ['kap' => 'g', 'olcek' => 2, 'durum' => 'acik', 'yazilar' => false]);
$kontakSvg = static function (string $sinif = '') use ($kontakG): string {
    return '<svg class="kontak-cizim' . ($sinif !== '' ? ' ' . e($sinif) : '') . '" viewBox="0 22 96 32" width="96" height="32" aria-hidden="true" focusable="false">'
         . '<line class="kontak-cizim__bant" x1="0" y1="48" x2="96" y2="48"/>'
         . '<g transform="rotate(90 48 48)">' . $kontakG . '</g></svg>';
};

/* Şema: CollectionPage + firma listesi (BreadcrumbList head tarafından eklenir) */
$liste = [];
foreach ($firmalar as $i => $f) {
    $liste[] = ['@type' => 'ListItem', 'position' => $i + 1, 'item' => ['@type' => 'Organization', 'name' => $f['ad']]];
}

partial('head', [
    'foy' => $foy,
    'css' => ['sayfa-referans'],
    'js' => ['sayfa-referans'],
    'gsap' => ['ScrollTrigger'],
    'lenis' => true,
    'schema' => [schema_sayfa('CollectionPage', $foy, [
        'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => $toplam, 'itemListOrder' => 'https://schema.org/ItemListOrderAscending', 'itemListElement' => $liste],
    ])],
]);
partial('ust', ['foy' => $foy]);
?>
<main id="icerik" class="foy foy--referans" data-foy="6">
  <nav class="dizin" aria-label="Firma dizini, A'dan Z'ye">
    <ol class="dizin__liste">
<?php foreach ($gruplar as $g): ?>
      <li><a class="dizin__harf" href="#<?= e($g['id']) ?>" data-harf="<?= e($g['harf']) ?>" aria-label="<?= e($g['harf']) ?>: <?= count($g['firmalar']) ?> firma"><?= e($g['harf']) ?></a></li>
<?php endforeach; ?>
    </ol>
  </nav>

  <section id="bobin" class="bobin izgara" data-satir="A" data-sc-act="flow" aria-labelledby="ref-baslik">
    <div class="bobin__cizim" aria-hidden="true">
      <span class="bobin__hat bobin__hat--a1"></span>
      <div class="bobin__sembol">
        <?= sembol('bobin', ['buyukluk' => 192, 'uclar' => ['ust' => 'A1', 'alt' => 'A2'], 'sinif' => 'bobin__iec']) ?>
        <span class="bobin__etiket"><span class="bobin__tag">-K0</span><span class="bobin__ad">TEKAYPER</span></span>
      </div>
      <span class="bobin__hat bobin__hat--a2"></span>
    </div>
    <div class="bobin__metin">
      <h1 id="ref-baslik" class="bobin__baslik">Referanslar</h1>
      <p class="bobin__alt">Tekayper'den ürün ve hizmet alan firmalardan bazıları.</p>
    </div>
  </section>

  <section id="kontaklar" class="kontaklar izgara" data-satir="B" data-sc-act="reveal" aria-labelledby="kontaklar-baslik" data-sc-verify-state="kapali:0/<?= $toplam ?>">
    <h2 id="kontaklar-baslik" class="gizli">Referans firmalar, alfabe sırasıyla</h2>
    <div class="ayna">
<?php foreach ($sutunlar as $si => $sutun): ?>
      <div class="ayna__kol">
<?php foreach ($sutun as $g): ?>
        <div class="grup" id="<?= e($g['id']) ?>" data-harf="<?= e($g['harf']) ?>">
          <h2 class="grup__harf" tabindex="-1"><span class="grup__harf-metin"><?= e($g['harf']) ?></span></h2>
          <ul class="grup__liste" role="list">
<?php foreach ($g['firmalar'] as $f): ?>
            <li class="firma" tabindex="0" data-kontak="<?= e($f['ust']) ?>">
              <span class="firma__kontak" aria-hidden="true"><span class="firma__uc"><?= e($f['ust']) ?></span><?= $kontakSvg() ?><span class="firma__uc"><?= e($f['alt']) ?></span></span>
              <span class="firma__ad"><?= e($f['ad']) ?></span>
              <span class="firma__logo"><?= resim('referans/' . $f['dosya'], '', ['sinif' => 'firma__logo-img', 'sizes' => '220px']) ?></span>
            </li>
<?php endforeach; ?>
          </ul>
        </div>
<?php endforeach; ?>
<?php if ($si === 2): ?>
        <section id="son" class="plaka" data-satir="C" data-sc-act="flow" aria-labelledby="son-baslik">
          <p class="plaka__etiket" id="son-baslik">Not</p>
          <span class="plaka__kontak" aria-hidden="true"><span class="firma__uc"><?= ($toplam + 1) * 10 + 3 ?></span><?= $kontakSvg('plaka__cizim') ?><span class="firma__uc"><?= ($toplam + 1) * 10 + 4 ?></span></span>
          <p class="plaka__metin">Logolar ilgili firmalara aittir ve yalnızca referans amacıyla gösterilir.</p>
          <?= dugme('Teklif al', url('iletisim.php#talep'), ['sinif' => 'plaka__dugme']) ?>
        </section>
<?php endif; ?>
      </div>
<?php endforeach; ?>
    </div>
  </section>
</main>
<?php partial('antet', ['foy' => $foy]); partial('son', ['foy' => $foy]); ?>
