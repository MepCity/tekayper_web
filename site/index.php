<?php
/**
 * Föy 1, Tek hat şeması (paket P1). Sayfa, beslemeden motora inen tek hat şemasıdır.
 * Çizim katmanı (hat parçaları, semboller) sunucuda son durumuyla basılır; sayfa-ana.js kaydırmayla çizer.
 * Hat parçaları CSS ile konumlanır: masaüstünde çizim sütunu (--s sütun, --f sütun içi oran, --o px),
 * telefonda --xt (px). Dikeyde --y1/--y2 (telefonda --yt1/--yt2 isteğe bağlı).
 */
require __DIR__ . '/app/bootstrap.php';
sembol_hazir();

$foy = foy('ana');
$ist = [];
foreach (data('istasyonlar') as $i) {
    $ist[$i['etiket'] ? implode(' ', $i['etiket']) : 'sebeke'] = $i;
}

/** Görsel adını çözer: 'urun/u01' ya da 'urun/u01-kompakt-salter' (varlık planı kimliğiyle başlayan dosya). */
function ana_foto(?string $ad): ?string
{
    if ($ad === null || $ad === '') {
        return null;
    }
    if (resim_var($ad)) {
        return $ad;
    }
    foreach (glob(ROOT . '/assets/img/' . $ad . '-*.{avif,webp,jpg}', GLOB_BRACE) ?: [] as $yol) {
        if (preg_match('~^(.*)-\d+\.(avif|webp|jpg)$~', substr($yol, strlen(ROOT . '/assets/img/')), $m) && resim_var($m[1])) {
            return $m[1];
        }
    }
    return null;
}

/* ---------------------------------------------------------------- çizim parçaları */

/** konum: [sütun, oran, px] masaüstü; $xt telefon px. null = o görünümde yok. */
function ana_konum(?array $x, $xt, string $on = ''): string
{
    $s = '';
    if ($x !== null) {
        [$a, $b, $c] = $x + [1, 0, 0];
        $s .= "--{$on}s:{$a};--{$on}f:{$b};--{$on}o:{$c}px;";
    }
    if ($xt !== null && $on === '') {
        $s .= "--xt:{$xt}px;";
    }
    return $s;
}

function ana_gorunum(?array $x, $xt): string
{
    return $x === null ? ' h--t' : ($xt === null ? ' h--m' : '');
}

/** Dikey hat parçası. $tur: g (güç 2px), k (kumanda 1,5px), s (pano sınırı), e (enerji bandı), kk (kesik) */
function hd(string $tur, ?array $x, $xt, string $y1, string $y2, array $o = []): string
{
    $st = ana_konum($x, $xt) . "--y1:{$y1};--y2:{$y2};";
    foreach (['yt1', 'yt2'] as $k) {
        if (isset($o[$k])) {
            $st .= "--{$k}:{$o[$k]};";
        }
    }
    $sinif = 'h h--d h--' . $tur . ana_gorunum($x, $xt) . (!empty($o['sinif']) ? ' ' . $o['sinif'] : '');
    $ek = isset($o['ana']) ? ' data-ana' : '';
    return '<i class="' . $sinif . '" style="' . $st . '"' . $ek . '></i>';
}

/** Yatay parça, soldan sağa ($x1 < $x2). $sol: çizim sağdan sola. */
function hy(string $tur, ?array $x1, ?array $x2, $xt1, $xt2, string $y, array $o = []): string
{
    $st = ana_konum($x1, $xt1) . ($x2 !== null ? ana_konum($x2, null, '2') : '') . "--y:{$y};";
    if ($xt1 !== null && $xt2 !== null) {
        $st .= '--wt:' . ($xt2 - $xt1) . 'px;';
    }
    if (isset($o['yt'])) {
        $st .= "--yt:{$o['yt']};";
    }
    $sinif = 'h h--y h--' . $tur . ana_gorunum($x1, $xt1) . (!empty($o['sol']) ? ' h--sol' : '');
    return '<i class="' . $sinif . '" style="' . $st . '"></i>';
}

/** Nokta (bağlantı noktası), ok ucu, yazı */
function hn(?array $x, $xt, string $y, array $o = []): string
{
    $st = ana_konum($x, $xt) . "--y:{$y};" . (isset($o['yt']) ? "--yt:{$o['yt']};" : '');
    return '<i class="h h--n' . ana_gorunum($x, $xt) . '" style="' . $st . '"' . (isset($o['ana']) ? ' data-ana' : '') . '></i>';
}
function hok(?array $x, $xt, string $y, string $yon = 'alt', array $o = []): string
{
    $st = ana_konum($x, $xt) . "--y:{$y};" . (isset($o['yt']) ? "--yt:{$o['yt']};" : '');
    return '<i class="h h--ok h--ok-' . $yon . ana_gorunum($x, $xt) . '" style="' . $st . '"></i>';
}
function hyaz(string $metin, ?array $x, $xt, string $y, string $hiza = 'sag', array $o = []): string
{
    $st = ana_konum($x, $xt) . "--y:{$y};" . (isset($o['yt']) ? "--yt:{$o['yt']};" : '');
    return '<span class="h h--yazi h--yazi-' . $hiza . ana_gorunum($x, $xt) . '" style="' . $st . '">' . e($metin) . '</span>';
}

/**
 * Hattaki sembol. $boy: CSS boyu ('var(--b)' ya da 'var(--bq)'), $uc: üst bağlantı ucu (birim).
 * $o: etiket, uclar, durum, ana (ana hat üzerinde), dur (enerji bandı burada bekler), sinif, yt (telefon üst)
 */
function hs(string $ad, ?array $x, $xt, string $y, array $o = []): string
{
    static $liste = null; $liste ??= sembol_listesi();
    $kaynak = $liste[$ad] ?? ['genislik' => 48, 'yukseklik' => 48, 'baglantilar' => []];
    $uc = $o['uc'] ?? null;
    if ($uc === null) {
        $uc = $kaynak['genislik'] / 2;
        foreach ($kaynak['baglantilar'] as $b) {
            if ($b['yon'] === 'ust') {
                $uc = $b['x'];
                break;
            }
        }
    }
    $boy = $o['boy'] ?? 'var(--b)';
    $st = ana_konum($x, $xt) . "--y1:{$y};--bb:{$boy};--uk:{$uc};--wu:{$kaynak['genislik']};--hu:{$kaynak['yukseklik']};"
        . (isset($o['yt']) ? "--yt1:{$o['yt']};" : '');
    $sec = ['buyukluk' => 96, 'etiket' => $o['etiket'] ?? null];
    foreach (['uclar', 'durum', 'yazi', 'iletken'] as $k) {
        if (isset($o[$k])) {
            $sec[$k] = $o[$k];
        }
    }
    $sinif = 'hs hs--' . $ad . ana_gorunum($x, $xt) . (!empty($o['sinif']) ? ' ' . $o['sinif'] : '');
    $ek = (!empty($o['ana']) ? ' data-ana' : '') . (!empty($o['dur']) ? ' data-dur="' . e($o['dur']) . '"' : '');
    return '<span class="' . $sinif . '" style="' . $st . '"' . $ek . '>' . sembol($ad, $sec) . '</span>';
}

/** -K1 ana kontakları, tek hat: kontaktör işlevli tek kutuplu kapanan kontak (kontaktor sembolünün orta kutbu). */
function ana_k1_kontak(string $durum): string
{
    $aci = $durum === 'kapali' ? 0 : -30;
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="96" height="96" class="sembol__cizim iec iec-kontaktor" data-sembol="kontaktor" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">'
        . '<path d="M24 0V16" data-sira="1" vector-effect="non-scaling-stroke"/>'
        . '<path d="M21 19A3 3 0 0 1 27 19" data-sira="2" vector-effect="non-scaling-stroke"/>'
        . '<path d="M24 48V32" data-sira="3" vector-effect="non-scaling-stroke"/>'
        . '<path class="kontak__bicak" d="M24 32V11" transform="rotate(' . $aci . ' 24 32)" data-mafsal="24 32" data-acik="-30" data-kapali="0" data-aci="' . $aci . '" data-sira="4" vector-effect="non-scaling-stroke"/>'
        . '<path d="M9 22.5L13 18.5M10.5 25.5L14.5 21.5M12 28.5L16 24.5" data-sira="5" vector-effect="non-scaling-stroke"/>'
        . '</svg>';
    return '<span class="sembol sembol--kontaktor" data-sembol="kontaktor" style="--sembol-olcek:2" aria-hidden="true">' . $svg
        . '<span class="sembol__etiket sembol__etiket--sol">-K1</span></span>';
}

/* ---------------------------------------------------------------- istasyon metni */

function ana_markalar(array $i): string
{
    if (!empty($i['metin'])) {
        return '<p class="ist__markalar"><span class="ist__alan">Ürün</span> ' . e($i['metin']) . '</p>';
    }
    $parca = [];
    foreach (istasyon_markalari($i) as $s) {
        if (!$s['markalar']) {
            continue;
        }
        $p = ($s['etiket'] ? '<span class="ist__alt">' . e($s['etiket']) . ':</span> ' : '')
           . '<span class="ist__marka">' . implode('</span>, <span class="ist__marka">', array_map('e', $s['markalar'])) . '</span>'
           . ($s['not'] ? ' <span class="ist__alt">(' . e($s['not']) . ')</span>' : '');
        $parca[] = $p;
    }
    return $parca ? '<p class="ist__markalar"><span class="ist__alan">Markalar</span> ' . implode('<span class="ist__ayrac" aria-hidden="true"> / </span><span class="gizli">; </span>', $parca) . '</p>' : '';
}

function ana_baglanti(array $i): string
{
    if (empty($i['capraz'])) {
        return '';
    }
    [$ref, $metin] = $i['capraz'];
    $opts = [];
    if (!empty($i['liste_kategori'])) {
        $opts['sorgu'] = ['kategori' => $i['liste_kategori']];
        $metin = data('kategoriler')['liste'][$i['liste_kategori']]['ad'] ?? $metin;
    } else {
        $metin = 'Pano soğutma sistemleri';
    }
    return '<p class="ist__baglanti">' . capraz_a($ref, $metin, $opts) . '</p>';
}

function ana_metin(array $i, string $ek = ''): string
{
    $etiket = implode(' ', $i['etiket']);
    return '<div class="ist__metin">'
        . '<h3 class="ist__baslik"><span class="ist__etiket">' . e($etiket) . '</span> ' . e($i['baslik']) . '</h3>'
        . '<p class="ist__cumle">' . e($i['cumle']) . '</p>'
        . ana_markalar($i) . ana_baglanti($i) . $ek . '</div>';
}

/** Görsel sahnesi: görsel varsa aydınger altında görsel (düğme), yoksa yalnızca sembolün çizildiği aydınger. */
function ana_sahne(array $i, string $sembolAd, array $o = []): string
{
    $foto = ana_foto($i['foto'] ?? null);
    $etiket = implode(' ', $i['etiket']);
    $iz = '<span class="aydinger__iz" aria-hidden="true">' . sembol($sembolAd, ['buyukluk' => $o['iz'] ?? 144, 'yazilar' => false]) . '</span>'
        . '<span class="aydinger__etiket" aria-hidden="true">' . e($etiket) . '</span>'
        . '<span class="aydinger__eksen aydinger__eksen--y" aria-hidden="true"></span><span class="aydinger__eksen aydinger__eksen--x" aria-hidden="true"></span>';
    if ($foto === null) {
        return '<div class="ist__sahne"><div class="sahne sahne--bos" aria-hidden="true"><span class="aydinger aydinger--bos">' . $iz . '</span></div></div>';
    }
    $img = resim($foto, $i['alt'] ?? $i['baslik'] . ', temsilî görsel', [
        'sizes' => $o['sizes'] ?? '(min-width: 1024px) 600px, 100vw', 'onyukle' => !empty($o['onyukle']), 'sinif' => 'sahne__img',
    ]);
    return '<div class="ist__sahne' . (!empty($o['sinif']) ? ' ' . $o['sinif'] : '') . '">'
        . '<button class="sahne sahne--foto" type="button" aria-pressed="true">'
        . '<span class="sahne__foto">' . $img . '</span><span class="gizli"> (aydınger kağıdını kaldır)</span>'
        . '<span class="aydinger">' . $iz . '</span>'
        . '</button></div>';
}

/** Kontak aynası (bobin altı), sayfa içi çapraz referanslar istasyona gider. */
function ana_ayna(array $satirlar, string $hedef, string $tam): string
{
    $h = '<span class="kontak-aynasi ana-ayna" role="list">';
    foreach ($satirlar as [$u, $a, $ref]) {
        $h .= '<span class="kontak-aynasi__kol" role="listitem"><span class="ka__uc" aria-hidden="true">' . e($u) . '</span>'
            . sembol('kontak-no', ['buyukluk' => 24, 'kap' => 'svg', 'yazilar' => false, 'durum' => 'kapali', 'sinif' => 'ka__sembol'])
            . '<span class="ka__uc" aria-hidden="true">' . e($a) . '</span>'
            . '<a class="capraz ka__ref" href="#' . e($hedef) . '" data-ist-ref data-capraz-tam="=TKY+ANA' . e($ref) . ' ' . e($tam) . '" aria-label="Kontak ' . e($u . '-' . $a) . ', ' . e($tam) . ' ' . e($ref) . '">' . e($ref) . '</a></span>';
    }
    return $h . '</span>';
}

/* ---------------------------------------------------------------- sabit konumlar */
$XP = [2, 0.5, 0];   $tP = 40;     // ana güç hattı
$XC = [3, 0.5, 0];                 // L+ rayı (masaüstü)
$XD = [4, 0.5, 0];                 // kumanda cihazları
$XH = [5, 0.38, 0];                // ikinci kumanda cihazı (-H1)
$XE = [5, 1, 12];                  // pano sınırı sağ kenarı, -E1
$XB = [1, 0.32, 0];                // pano sınırı sol kenarı
$T = '22px';                       // kumanda dalının ayrıldığı yükseklik

$anaHat = fn () => hd('g', $XP, $tP, '0px', '100%', ['ana' => true]);
$anaHatM = fn () => hd('g', $XP, null, '0px', '100%', ['ana' => true]);
$bant = fn (string $y1 = '0px', string $y2 = '100%', $xt = 40) => hd('e', $XP, $xt, $y1, $y2);
$sinir = fn (string $y1 = '0px', string $y2 = '100%') => hd('s', $XB, null, $y1, $y2) . hd('s', $XE, null, $y1, $y2);
$PB = 'calc(var(--P) + var(--b))';  // sembol altı

$q1 = $ist['-Q1'];
$q1Foto = ana_foto($q1['foto']);

partial('head', [
    'foy' => $foy, 'css' => ['sayfa-ana'], 'js' => ['sayfa-ana'], 'gsap' => ['ScrollTrigger'], 'lenis' => true,
    'onyukle' => $q1Foto ? [[$q1Foto, '(min-width: 1024px) 600px, 100vw']] : [],
]);
partial('ust', ['foy' => $foy]);
// satır içi SVG'lerde etiketler arası boşlukları at (çizim katmanı bütçesi)
ob_start(fn ($h) => preg_replace_callback('~<svg\b.*?</svg>~s', fn ($m) => preg_replace('~>\s+<~', '><', $m[0]), $h));
?>
<main id="icerik" class="foy foy--ana" data-foy="1">
<div class="sema" id="sema" data-sc-verify-state="">

  <section id="giris" class="bolum bolum--giris izgara" data-izgara="cizim" data-satir="A" data-sc-act="parallax" aria-labelledby="giris-baslik">
    <div class="giris">
      <h1 class="giris__baslik" id="giris-baslik">Endüstriyel otomasyon malzemesi ve teknik destek, Bursa Nilüfer</h1>
      <p class="giris__alt"><?= marka_sayisi() ?> markanın <?= grup_sayisi() ?> ürün grubunu tedarik ediyoruz. Mühendislik, montaj ve servis hizmeti veriyoruz.</p>
      <p class="giris__dugmeler"><?= dugme('Teklif al', url('iletisim.php#talep')) ?><?= dugme('Ürünleri incele', url('tekayper-urunler.php'), ['tur' => 'ikincil', 'ref' => '/4']) ?></p>
    </div>
  </section>

  <section id="besleme" class="bolum bolum--besleme" data-izgara="cizim" data-satir="B" data-sc-act="reveal" aria-labelledby="besleme-baslik">
    <h2 class="gizli" id="besleme-baslik">Besleme</h2>
    <ol class="istasyonlar izgara">
      <li class="ist ist--sebeke" id="ist-sebeke">
        <div class="ist__cizim" aria-hidden="true">
          <?= hy('g', [1, 0.12, 0], [3, 0.2, 0], 12, 120, '40px') ?>
          <?= hn($XP, $tP, '40px', ['ana' => true]) ?>
          <?= $bant('40px') ?>
          <?= hd('g', $XP, $tP, '40px', '100%', ['ana' => true]) ?>
          <svg class="h h--isaret" style="<?= ana_konum($XP, $tP) ?>--y:56px" width="18" height="22" viewBox="0 0 18 22"><path d="M3 5l12-3M3 9l12-3M3 13l12-3M3 17l12-3M3 21l12-3" stroke="currentColor" stroke-width="1"/></svg>
        </div>
        <p class="ist__besleme"><span class="ist__besleme-ad">Şebeke</span> <span class="osi">3/N/PE 400/230 V 50 Hz</span></p>
      </li>

      <li class="ist ist--guc ist--q1" id="ist-q1" style="--P: var(--Pq)" data-elle>
        <div class="ist__cizim" aria-hidden="true">
          <?= $bant() ?>
          <?= hd('g', $XP, $tP, '0px', 'var(--P)', ['ana' => true]) ?>
          <?= hs('devre-kesici', $XP, $tP, 'var(--P)', ['boy' => 'var(--bq)', 'etiket' => '-Q1', 'ana' => true, 'dur' => 'q1', 'sinif' => 'hs--q1', 'durum' => 'kapali']) ?>
          <?= hd('g', $XP, $tP, 'calc(var(--P) + var(--bq))', '100%', ['ana' => true]) ?>
          <?= hy('s', $XB, $XE, null, null, 'calc(var(--P) / 2)') ?>
          <?= $sinir('calc(var(--P) / 2)') ?>
        </div>
        <?= ana_metin($q1) ?>
        <?= ana_sahne($q1, 'devre-kesici', ['iz' => 240, 'onyukle' => true, 'sinif' => 'ist__sahne--q1']) ?>
      </li>

      <?php $p1 = $ist['-P1']; ?>
      <li class="ist ist--guc" id="ist-p1">
        <div class="ist__cizim" aria-hidden="true">
          <?= $bant() ?>
          <?= hd('g', $XP, $tP, '0px', 'var(--P)', ['ana' => true]) ?>
          <?= hs('analizor', $XP, $tP, 'var(--P)', ['etiket' => '-P1', 'ana' => true]) ?>
          <?= hd('g', $XP, $tP, $PB, '100%', ['ana' => true]) ?>
          <?= $sinir() ?>
        </div>
        <?= ana_metin($p1) ?>
        <?= ana_sahne($p1, 'analizor') ?>
      </li>
    </ol>
  </section>

  <section id="kumanda" class="bolum bolum--kumanda" data-izgara="cizim" data-satir="C" data-sc-act="flow" aria-labelledby="kumanda-baslik">
    <div class="akis akis--baslik izgara">
      <div class="ist__cizim" aria-hidden="true">
        <?= $bant() ?>
        <?= hd('g', $XP, $tP, '0px', '100%', ['ana' => true, 'yt2' => '60%']) ?>
        <?= hn(null, $tP, '0px', ['yt' => '60%']) ?>
        <?= hy('k', null, null, $tP, $tP + 30, '0px', ['yt' => '60%']) ?>
        <?= hok(null, $tP + 34, '0px', 'sag', ['yt' => '60%']) ?>
        <?= hyaz('/1.D', null, $tP + 42, '0px', 'sag', ['yt' => '60%']) ?>
        <?= hd('k', null, $tP, '60%', '100%') ?>
        <?= $sinir() ?>
      </div>
      <h2 class="akis__baslik akis__baslik--kumanda" id="kumanda-baslik">Kumanda</h2>
    </div>
    <ol class="istasyonlar izgara" start="4">
<?php
$g1 = $ist['-G1'];
$a1 = $ist['-A1'];
$b1 = $ist['-B1'];
$s1 = $ist['-S1 -H1'];
$k2 = $ist['-K2'];
$k1 = $ist['-K1'];
$e1 = $ist['-E1'];
$cikis = 'calc(var(--P) + var(--b) + 22px)';
?>
      <li class="ist ist--kumanda" id="ist-g1" style="--ch: calc(var(--P) + var(--b) + 64px)">
        <div class="ist__cizim" aria-hidden="true">
          <?= $bant() ?>
          <?= $anaHatM() ?>
          <?= hn($XP, null, $T, ['ana' => true]) ?>
          <?= hy('k', $XP, $XD, null, null, $T) ?>
          <?= hd('k', $XD, null, $T, 'var(--P)', ['sinif' => 'h--sonra']) ?>
          <?= hd('k', null, $tP, '0px', 'var(--P)') ?>
          <?= hs('guc-kaynagi', $XD, $tP, 'var(--P)', ['etiket' => '-G1']) ?>
          <?= hd('k', $XD, $tP, $PB, 'calc(var(--P) + var(--b) + 18px)') ?>
          <?= hy('k', $XC, $XD, null, null, 'calc(var(--P) + var(--b) + 18px)', ['sol' => true]) ?>
          <?= hd('k', $XC, null, 'calc(var(--P) + var(--b) + 18px)', '100%', ['sinif' => 'h--sonra']) ?>
          <?= hyaz('L+ 24 V DC', $XC, null, 'calc(var(--P) + var(--b) + 28px)', 'sol') ?>
          <?= hok(null, $tP, 'calc(var(--P) + var(--b) + 18px)') ?>
          <?= hyaz('L+ 24 V DC', null, $tP + 12, 'calc(var(--P) + var(--b) + 16px)') ?>
          <?= $sinir() ?>
        </div>
        <?= ana_metin($g1) ?>
      </li>

      <li class="ist ist--kumanda" id="ist-a1" style="--ch: calc(var(--P) + var(--b) + 24px)">
        <div class="ist__cizim" aria-hidden="true">
          <?= $bant() ?>
          <?= $anaHatM() ?>
          <?= hd('k', $XC, null, '0px', '100%') ?>
          <?= hn($XC, null, $T) ?>
          <?= hy('k', $XC, $XD, null, null, $T) ?>
          <?= hd('k', $XD, null, $T, 'var(--P)', ['sinif' => 'h--sonra']) ?>
          <?= hyaz('L+', null, $tP + 8, '2px') ?>
          <?= hd('k', null, $tP, '18px', 'var(--P)') ?>
          <?= hs('plc', $XD, $tP, 'var(--P)', ['etiket' => '-A1', 'uc' => 16]) ?>
          <?= $sinir() ?>
        </div>
        <?= ana_metin($a1) ?>
      </li>

      <li class="ist ist--kumanda" id="ist-b1" style="--ch: calc(var(--P) + var(--b) + 48px)">
        <div class="ist__cizim" aria-hidden="true">
          <?= $bant() ?>
          <?= $anaHatM() ?>
          <?= hd('k', $XC, null, '0px', '100%') ?>
          <?= hn($XC, null, $T) ?>
          <?= hy('k', $XC, $XD, null, null, $T) ?>
          <?= hd('k', $XD, null, $T, 'var(--P)', ['sinif' => 'h--sonra']) ?>
          <?= hyaz('L+', null, $tP + 8, '2px') ?>
          <?= hd('k', null, $tP, '18px', 'var(--P)') ?>
          <?= hs('sensor-enduktif', $XD, $tP, 'var(--P)', ['etiket' => '-B1', 'durum' => 'kapali']) ?>
          <?= hd('k', $XD, $tP, $PB, $cikis) ?>
          <?= hok($XD, $tP, $cikis) ?>
          <?= hyaz('-A1:I0.0', $XD, $tP, $cikis) ?>
          <?= $sinir() ?>
        </div>
        <?= ana_metin($b1) ?>
      </li>

      <li class="ist ist--kumanda" id="ist-s1" style="--ch: calc(var(--P) + var(--b) + 48px); --cht: calc(var(--P) * 2 + var(--b) * 2 + 96px)">
        <div class="ist__cizim" aria-hidden="true">
          <?= $bant() ?>
          <?= $anaHatM() ?>
          <?= hd('k', $XC, null, '0px', '100%') ?>
          <?= hn($XC, null, $T) ?>
          <?= hy('k', $XC, $XD, null, null, $T) ?>
          <?= hd('k', $XD, null, $T, 'var(--P)', ['sinif' => 'h--sonra']) ?>
          <?= hyaz('L+', null, $tP + 8, '2px') ?>
          <?= hd('k', null, $tP, '18px', 'var(--P)') ?>
          <?= hs('buton-no', $XD, $tP, 'var(--P)', ['etiket' => '-S1']) ?>
          <?= hd('k', $XD, $tP, $PB, $cikis) ?>
          <?= hok($XD, $tP, $cikis) ?>
          <?= hyaz('-A1:I0.1', $XD, $tP, $cikis) ?>
          <?php $h1y = 'calc(var(--P) * 2 + var(--b) + 48px)'; ?>
          <?= hyaz('-A1:Q0.1', $XH, $tP + 8, '2px', 'sag', ['yt' => 'calc(var(--P) + var(--b) + 48px)']) ?>
          <?= hd('k', $XH, $tP, '18px', 'var(--P)', ['yt1' => 'calc(var(--P) + var(--b) + 64px)', 'yt2' => $h1y]) ?>
          <?= hs('lamba', $XH, $tP, 'var(--P)', ['etiket' => '-H1', 'yt' => $h1y]) ?>
          <?= hd('k', $XH, $tP, $PB, $cikis, ['yt1' => "calc({$h1y} + var(--b))", 'yt2' => "calc({$h1y} + var(--b) + 22px)"]) ?>
          <?= hok($XH, $tP, $cikis, 'alt', ['yt' => "calc({$h1y} + var(--b) + 22px)"]) ?>
          <?= hyaz('M', $XH, $tP, $cikis, 'sag', ['yt' => "calc({$h1y} + var(--b) + 22px)"]) ?>
          <?= $sinir() ?>
        </div>
        <?= ana_metin($s1) ?>
      </li>

      <li class="ist ist--kumanda" id="ist-k2" style="--ch: calc(var(--P) + var(--b) + 96px)">
        <div class="ist__cizim" aria-hidden="true">
          <?= $bant() ?>
          <?= $anaHatM() ?>
          <?= hd('k', $XC, null, '0px', '100%') ?>
          <?= hyaz('-A1:Q0.0', $XD, $tP + 8, '2px') ?>
          <?= hd('k', $XD, $tP, '18px', 'var(--P)') ?>
          <?= hs('bobin', $XD, $tP, 'var(--P)', ['etiket' => '-K2']) ?>
          <?= hd('k', $XD, $tP, $PB, $cikis) ?>
          <?= hok($XD, $tP, $cikis) ?>
          <?= hyaz('M', $XD, $tP, $cikis) ?>
          <?= $sinir() ?>
        </div>
        <?= ana_metin($k2, '<div class="ist__ayna">' . ana_ayna([['11', '14', '/1.C']], 'ist-k1', '-K2 kontağı, -K1 bobin hattında') . '</div>') ?>
      </li>

      <li class="ist ist--kumanda" id="ist-k1" style="--ch: calc(var(--P) + var(--b) * 2 + 64px)">
        <div class="ist__cizim" aria-hidden="true">
          <?= $bant() ?>
          <?= $anaHatM() ?>
          <?= hd('k', $XC, null, '0px', $T) ?>
          <?= hy('k', $XC, $XD, null, null, $T) ?>
          <?= hd('k', $XD, null, $T, 'var(--P)', ['sinif' => 'h--sonra']) ?>
          <?= hyaz('L+', null, $tP + 8, '2px') ?>
          <?= hd('k', null, $tP, '18px', 'var(--P)') ?>
          <?= hs('kontak-no', $XD, $tP, 'var(--P)', ['etiket' => '-K2', 'uclar' => ['ust' => '11', 'alt' => '14'], 'sinif' => 'hs--k2kontak', 'durum' => 'kapali']) ?>
          <?= hd('k', $XD, $tP, $PB, 'calc(var(--P) + var(--b) + 16px)') ?>
          <?= hs('bobin', $XD, $tP, 'calc(var(--P) + var(--b) + 16px)', ['etiket' => '-K1']) ?>
          <?= hd('k', $XD, $tP, 'calc(var(--P) + var(--b) * 2 + 16px)', 'calc(var(--P) + var(--b) * 2 + 38px)') ?>
          <?= hok($XD, $tP, 'calc(var(--P) + var(--b) * 2 + 38px)') ?>
          <?= hyaz('M', $XD, $tP, 'calc(var(--P) + var(--b) * 2 + 38px)') ?>
          <?= $sinir() ?>
        </div>
        <?= ana_metin($k1, '<div class="ist__ayna">' . ana_ayna([['1', '2', '/1.D'], ['3', '4', '/1.D'], ['5', '6', '/1.D']], 'k1-ana', '-K1 ana kontakları') . '</div>') ?>
      </li>

      <li class="ist ist--kumanda ist--e1" id="ist-e1" style="--ch: calc(var(--P) + var(--b) + 24px)">
        <div class="ist__cizim" aria-hidden="true">
          <?= $bant() ?>
          <?= $anaHatM() ?>
          <?= hn($XP, null, $T, ['ana' => true]) ?>
          <?= hy('k', $XP, $XE, null, null, $T) ?>
          <?= hd('k', $XE, null, $T, 'var(--P)', ['sinif' => 'h--sonra']) ?>
          <?= hyaz('230 V AC', null, $tP + 8, '2px') ?>
          <?= hd('k', null, $tP, '18px', 'var(--P)') ?>
          <?= hs('klima', $XE, $tP, 'var(--P)', ['etiket' => '-E1']) ?>
          <?= hd('s', $XB, null, '0px', '100%') ?>
          <?= hd('s', $XE, null, '0px', 'calc(var(--P) + var(--b) / 6)') ?>
          <?= hd('s', $XE, null, 'calc(var(--P) + var(--b) * 5 / 6)', '100%') ?>
        </div>
        <?= ana_metin($e1) ?>
      </li>
    </ol>
  </section>

  <section id="surucu" class="bolum bolum--surucu" data-izgara="cizim" data-satir="D" data-sc-act="scrub" aria-labelledby="surucu-baslik">
    <div class="akis akis--baslik izgara">
      <div class="ist__cizim" aria-hidden="true">
        <?= $bant() ?>
        <?= hd('g', $XP, null, '0px', '100%', ['ana' => true]) ?>
        <?= hyaz('/1.C', null, $tP + 10, '30%') ?>
        <?= hok(null, $tP, 'calc(30% + 4px)') ?>
        <?= hd('g', null, $tP, 'calc(30% + 12px)', '100%', ['ana' => true]) ?>
        <?= $sinir() ?>
      </div>
      <h2 class="akis__baslik" id="surucu-baslik">Sürücü</h2>
    </div>
    <div class="akis akis--k1 izgara" id="k1-ana">
      <div class="ist__cizim" aria-hidden="true">
        <?= $bant() ?>
        <?= hd('g', $XP, $tP, '0px', 'var(--P)', ['ana' => true]) ?>
        <span class="hs hs--k1ana" style="<?= ana_konum($XP, $tP) ?>--y1:var(--P);--bb:var(--b);--uk:24;--wu:48;--hu:48" data-ana data-dur="k1"><?= ana_k1_kontak('kapali') ?></span>
        <?= hd('g', $XP, $tP, $PB, '100%', ['ana' => true]) ?>
        <?= $sinir() ?>
      </div>
      <p class="akis__not"><span class="ist__etiket">-K1</span> Kontaktör ana kontakları 1-2, 3-4, 5-6. Bobin <a class="capraz" href="#ist-k1" data-ist-ref data-capraz-tam="=TKY+ANA/1.C -K1 bobini"><span class="capraz__metin">-K1</span><span class="capraz__ref" aria-hidden="true">/1.C</span></a></p>
    </div>
    <ol class="istasyonlar izgara" start="11">
<?php foreach (['-U1' => 'surucu', '-X1' => 'klemens', '-W1' => 'kablo'] as $tag => $sem): $i = $ist[$tag]; $id = 'ist-' . strtolower(ltrim($tag, '-')); ?>
      <li class="ist ist--guc" id="<?= $id ?>">
        <div class="ist__cizim" aria-hidden="true">
          <?= $bant() ?>
          <?= hd('g', $XP, $tP, '0px', 'var(--P)', ['ana' => true]) ?>
          <?= hs($sem, $XP, $tP, 'var(--P)', ['etiket' => $tag, 'ana' => true] + ($sem === 'klemens' ? ['uclar' => ['no' => '1']] : []) + ($sem === 'kablo' ? ['iletken' => 4] : [])) ?>
          <?= hd('g', $XP, $tP, $PB, '100%', ['ana' => true]) ?>
<?php if ($tag === '-X1'): ?>
          <?= $sinir('0px', 'calc(100% - 20px)') ?>
          <?= hy('s', $XB, $XE, null, null, 'calc(100% - 20px)') ?>
<?php elseif ($tag === '-U1'): ?>
          <?= $sinir() ?>
<?php endif; ?>
        </div>
        <?= ana_metin($i) ?>
        <?= ana_sahne($i, $sem) ?>
      </li>
<?php endforeach; ?>
    </ol>
  </section>

  <div class="akis akis--sessizlik izgara" aria-hidden="true">
    <div class="ist__cizim">
      <?= $bant() ?>
      <?= hd('g', $XP, $tP, '0px', '100%', ['ana' => true]) ?>
    </div>
  </div>

<?php
/* ---------------------------------------------------------------- tepe noktası: bütün föy (genel görünüş) */
$m1 = $ist['-M1 -B2'];
$o = fn (string $ad, float $x, float $y, array $s = []) => sembol($ad, ['kap' => 'g', 'x' => $x, 'y' => $y, 'olcek' => 1, 'sinif' => $s['sinif'] ?? ''] + $s);
$yol = fn (string $d, string $sinif = 'k') => '<path class="gy gy--' . $sinif . '" d="' . $d . '" vector-effect="non-scaling-stroke"/>';
$bnt = fn (string $d, float $a, float $b) => '<path class="ob" d="' . $d . '" data-q="' . $a . ' ' . $b . '" vector-effect="non-scaling-stroke"/>';
$yaz = fn (string $t, float $x, float $y, string $a = 'end') => '<text x="' . $x . '" y="' . $y . '" text-anchor="' . $a . '" dominant-baseline="middle">' . e($t) . '</text>';
$nok = fn (float $x, float $y) => '<circle class="gn" cx="' . $x . '" cy="' . $y . '" r="2.6"/>';
$k1tek = '<g class="sembol-g iec-kontaktor gk1" transform="translate(96 540)">'
    . '<path d="M24 0V16" vector-effect="non-scaling-stroke"/><path d="M21 19A3 3 0 0 1 27 19" vector-effect="non-scaling-stroke"/>'
    . '<path d="M24 48V32" vector-effect="non-scaling-stroke"/>'
    . '<path class="kontak__bicak" d="M24 32V11" transform="rotate(0 24 32)" data-mafsal="24 32" data-acik="-30" data-kapali="0" data-aci="0" vector-effect="non-scaling-stroke"/>'
    . '<path d="M9 22.5L13 18.5M10.5 25.5L14.5 21.5M12 28.5L16 24.5" vector-effect="non-scaling-stroke"/></g>';
?>
  <section id="motor" class="bolum bolum--motor" data-izgara="cizim alt" data-satir="E" data-sc-act="pin" aria-labelledby="motor-baslik">
    <h2 class="gizli" id="motor-baslik">Motor</h2>
    <div class="zirve" data-sc-verify-state="">
      <svg class="genel" viewBox="0 0 640 1050" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.5" data-m1="120 974" data-kutu="20 8 600 1040">
        <g class="genel__kamera">
          <g class="genel__bant">
            <?= $bnt('M120 40V210', 0, 0.12) ?>
            <?= $bnt('M120 210H560', 0.12, 0.2) ?>
            <?= $bnt('M120 210V540', 0.12, 0.2) ?>
            <?= $bnt('M300 210V296', 0.18, 0.24) ?>
            <?= $bnt('M560 210V240', 0.2, 0.24) ?>
            <?= $bnt('M300 296H510', 0.24, 0.3) ?>
            <?= $bnt('M340 296V386', 0.3, 0.38) ?>
            <?= $bnt('M420 296V386', 0.32, 0.4) ?>
            <?= $bnt('M340 426V512', 0.42, 0.5) ?>
            <?= $bnt('M420 426V512', 0.44, 0.52) ?>
            <?= $bnt('M340 512H510', 0.5, 0.56) ?>
            <?= $bnt('M510 296V512', 0.52, 0.64) ?>
            <?= $bnt('M120 540V1036', 0.66, 1) ?>
          </g>
          <g class="genel__murekkep">
            <?= $yaz('Şebeke 3/N/PE 400/230 V 50 Hz', 40, 22, 'start') ?>
            <?= $yol('M40 40H230', 'g') ?><?= $nok(120, 40) ?>
            <?= $yol('M120 40V76', 'g') ?>
            <?= $o('devre-kesici', 96, 76, ['durum' => 'kapali']) ?><?= $yaz('-Q1', 98, 100) ?>
            <?= $yol('M120 124V140', 'g') ?>
            <?= $o('analizor', 108, 140) ?><?= $yaz('-P1', 102, 164) ?>
            <?= $yol('M120 188V540', 'g') ?><?= $nok(120, 210) ?>
            <?= $yol('M120 210H560') ?><?= $nok(300, 210) ?>
            <?= $yol('M300 210V228') ?><?= $o('guc-kaynagi', 276, 228) ?><?= $yaz('-G1', 282, 252) ?>
            <?= $yol('M300 276V296H510') ?><?= $yaz('L+', 292, 300) ?>
            <?= $nok(340, 296) ?><?= $nok(420, 296) ?>
            <?= $yol('M340 296V316') ?><?= $o('sensor-enduktif', 308, 316, ['durum' => 'kapali', 'sinif' => 'gb1']) ?><?= $yaz('-B1', 306, 352) ?>
            <?= $yol('M420 296V316') ?><?= $o('buton-no', 396, 316, ['durum' => 'kapali', 'sinif' => 'gs1']) ?><?= $yaz('-S1', 398, 352) ?>
            <?= $yol('M340 364V386M420 364V386') ?>
            <?= $yol('M320 386H440V426H320Z') ?><?= $yaz('PLC', 380, 407, 'middle') ?><?= $yaz('-A1', 314, 407) ?>
            <?= $yol('M340 426V444M420 426V444') ?>
            <?= $o('bobin', 316, 444) ?><?= $yaz('-K2', 326, 470) ?>
            <?= $o('lamba', 396, 444) ?><?= $yaz('-H1', 398, 470) ?>
            <?= $yol('M340 492V512H510M420 492V512') ?><?= $nok(420, 512) ?><?= $yaz('M', 332, 516) ?>
            <?= $yol('M510 296V316') ?><?= $o('kontak-no', 486, 316, ['durum' => 'kapali', 'sinif' => 'gk2']) ?><?= $yaz('-K2', 504, 340) ?>
            <?= $yol('M510 364V444') ?><?= $o('bobin', 486, 444) ?><?= $yaz('-K1', 496, 470) ?>
            <?= $yol('M510 492V512') ?>
            <?= $yol('M560 210V228') ?><rect class="gmaske" x="536" y="236" width="48" height="32"/><?= $o('klima', 528, 228) ?><?= $yaz('-E1', 596, 252, 'start') ?>
            <path class="gy gy--s gw1" d="M60 58H560V724H60Z" vector-effect="non-scaling-stroke"/>
            <?= $k1tek ?><?= $yaz('-K1', 98, 564) ?>
            <?= $yol('M120 588V600', 'g') ?><?= $o('surucu', 96, 600) ?><?= $yaz('-U1', 104, 624) ?>
            <?= $yol('M120 648V708', 'g') ?><g class="gw1"><circle class="gmaske" cx="120" cy="684" r="2.3"/><?= $o('klemens', 96, 660) ?><?= $yaz('-X1', 110, 684) ?></g>
            <?= $yol('M120 708V792', 'g') ?><g class="gw1"><rect class="gmaske" x="113" y="760" width="14" height="16"/><?= $o('kablo', 96, 744, ['iletken' => 4]) ?><?= $yaz('-W1', 110, 768) ?></g>
            <?= $yol('M120 792V948', 'g') ?>
            <?= $o('motor', 96, 948, ['sinif' => 'gm1']) ?><?= $yaz('-M1', 102, 974) ?>
            <path class="gy gy--kk" d="M120 988V1000" vector-effect="non-scaling-stroke"/>
            <?= $o('enkoder', 96, 988) ?><?= $yaz('-B2', 106, 1012) ?>
            <?= $yol('M120 1036V1600') ?>
          </g>
        </g>
      </svg>
      <ol class="istasyonlar izgara zirve__liste" start="14">
        <li class="ist ist--guc ist--m1" id="ist-m1" style="--ch: calc(var(--P) + var(--b) * 2 + 40px)">
          <div class="ist__cizim" aria-hidden="true">
            <?= $bant('0px', 'calc(var(--P) + var(--b) * 0.84)') ?>
            <?= hd('g', $XP, $tP, '0px', 'var(--P)', ['ana' => true]) ?>
            <?= hs('motor', $XP, $tP, 'var(--P)', ['etiket' => '-M1', 'sinif' => 'hs--m1']) ?>
            <?= hd('kk', $XP, $tP, 'calc(var(--P) + var(--b) * 0.84)', 'calc(var(--P) + var(--b) * 0.84 + 10px)') ?>
            <?= hs('enkoder', $XP, $tP, 'calc(var(--P) + var(--b) * 0.84 + 10px - var(--b) * 0.25)', ['etiket' => '-B2', 'uc' => 24]) ?>
            <?= hd('k', $XP, $tP, 'calc(var(--P) + var(--b) * 1.84 + 10px - var(--b) * 0.25)', '100%') ?>
          </div>
          <?= ana_metin($m1) ?>
          <?= ana_sahne($m1, 'motor') ?>
        </li>
      </ol>
    </div>
  </section>
</div>

  <section id="notlar" class="bolum bolum--notlar izgara" data-satir="F" data-sc-act="flow" aria-labelledby="notlar-baslik">
    <div class="ist__cizim notlar__cizim" aria-hidden="true">
      <?= hd('k', $XP, $tP, '0px', '100%') ?>
      <i class="h h--n h--son" style="<?= ana_konum($XP, $tP) ?>--y:100%"></i>
    </div>
    <div class="notlar">
      <h2 class="notlar__baslik" id="notlar-baslik">Notlar</h2>
      <ol class="notlar__liste">
        <li><span><strong>Ürün tedariki:</strong> Tanınmış üreticilerin otomasyon ve elektrik ürünlerini ihtiyacınıza göre temin ediyoruz.</span></li>
        <li><span><strong>Mühendislik:</strong> Projenize uygun ürün seçimi ve mühendislik desteği veriyoruz.</span></li>
        <li><span><strong>Proje, uygulama, montaj ve servis:</strong> Pano ve otomasyon işlerinde projeden satış sonrası servise kadar çalışıyoruz.</span></li>
      </ol>
      <p class="notlar__baglantilar"><?= capraz_a('/2', 'Firma bilgisi') ?><?= capraz_a('/4', 'Malzeme listesi') ?></p>
    </div>
  </section>
</main>
<?php ob_end_flush(); partial('antet', ['foy' => $foy, 'onay' => true]); partial('son', ['foy' => $foy]); ?>
