<?php
/**
 * Pano soğutma hesabı birim testi (spec 3.5), PHP tarafı.
 *   php tools/qa/sogutma-testi.php [--json]
 *
 * Kaynak: site/app/lib/sogutma.php (P5). Sözleşme: sogutma_hesapla(array $g): array
 *   girdi  ['h'=>mm, 'w'=>mm, 'd'=>mm, 'kurulum'=>1..7, 'k'=>float, 'qv'=>W, 'tu'=>°C, 'ti'=>°C]
 *   çıktı  ['A'=>m², 'Qs'=>W, 'Qk'=>W, 'Qr'=>W|null, 'kapasite'=>W|null, 'durum'=>'model'|'yok'|'buyuk', 'sigar'=>bool|null]
 * Test, aynı formülle yazılmış bağımsız bir başvuru hesabını ve spec tablosunu birlikte denetler.
 * Spec ile başvuru hesabı ayrışırsa bunu RAPORLAR; formül tabloya uydurulmaz.
 */
declare(strict_types=1);

const KAPASITE = [450, 1080, 1930, 2960, 3340];
const BOYUT = [450 => [550, 300], 1080 => [895, 405], 1930 => [1000, 405], 2960 => [1000, 405], 3340 => [1050, 485]]; // [yükseklik, genişlik] mm

function basvuru(array $g): array
{
    [$H, $W, $D] = [$g['h'] / 1000, $g['w'] / 1000, $g['d'] / 1000];
    $A = match ((int) $g['kurulum']) {
        1 => 1.8 * $H * ($W + $D) + 1.4 * $W * $D,
        2 => 1.4 * $W * ($H + $D) + 1.8 * $D * $H,
        3 => 1.4 * $D * ($H + $W) + 1.8 * $W * $H,
        4 => 1.4 * $H * ($W + $D) + 1.4 * $W * $D,
        5 => 1.8 * $W * $H + 1.4 * $W * $D + $D * $H,
        6 => 1.4 * $W * ($H + $D) + $D * $H,
        7 => 1.4 * $W * $H + 0.7 * $W * $D + $D * $H,
    };
    $Qs = $g['k'] * $A * ($g['tu'] - $g['ti']);
    $Qk = $g['qv'] + $Qs;
    if ($Qk <= 0) {
        return ['A' => $A, 'Qs' => $Qs, 'Qk' => $Qk, 'Qr' => null, 'kapasite' => null, 'durum' => 'yok', 'sigar' => null];
    }
    $Qr = 1.1 * $Qk;
    foreach (KAPASITE as $c) {
        if ($c >= $Qr) {
            [$uh, $uw] = BOYUT[$c];
            return ['A' => $A, 'Qs' => $Qs, 'Qk' => $Qk, 'Qr' => $Qr, 'kapasite' => $c, 'durum' => 'model', 'sigar' => $uh <= $g['h'] && $uw <= $g['d']];
        }
    }
    return ['A' => $A, 'Qs' => $Qs, 'Qk' => $Qk, 'Qr' => $Qr, 'kapasite' => null, 'durum' => 'buyuk', 'sigar' => null];
}

// spec 3.5 test vektörleri: girdi + tablo değerleri
$VEKTOR = [
    [['h' => 2000, 'w' => 800, 'd' => 600, 'kurulum' => 2, 'k' => 5.5, 'qv' => 800, 'tu' => 40, 'ti' => 35], ['A' => 5.072, 'Qs' => 139.5, 'Qk' => 939.5, 'Qr' => 1033.4, 'kapasite' => 1080, 'durum' => 'model', 'sigar' => true]],
    [['h' => 2000, 'w' => 800, 'd' => 600, 'kurulum' => 2, 'k' => 5.5, 'qv' => 300, 'tu' => 30, 'ti' => 35], ['A' => 5.072, 'Qs' => -139.5, 'Qk' => 160.5, 'Qr' => 176.6, 'kapasite' => 450, 'durum' => 'model', 'sigar' => true]],
    [['h' => 2000, 'w' => 800, 'd' => 600, 'kurulum' => 2, 'k' => 5.5, 'qv' => 3500, 'tu' => 45, 'ti' => 35], ['A' => 5.072, 'Qs' => 279.0, 'Qk' => 3779.0, 'Qr' => 4156.9, 'kapasite' => null, 'durum' => 'buyuk', 'sigar' => null]],
    [['h' => 2000, 'w' => 800, 'd' => 600, 'kurulum' => 2, 'k' => 5.5, 'qv' => 100, 'tu' => 20, 'ti' => 35], ['A' => 5.072, 'Qs' => -418.4, 'Qk' => -318.4, 'Qr' => null, 'kapasite' => null, 'durum' => 'yok', 'sigar' => null]],
    [['h' => 600, 'w' => 600, 'd' => 300, 'kurulum' => 1, 'k' => 5.5, 'qv' => 1200, 'tu' => 35, 'ti' => 35], ['A' => 1.224, 'Qs' => 0.0, 'Qk' => 1200.0, 'Qr' => 1320.0, 'kapasite' => 1930, 'durum' => 'model', 'sigar' => false]],
];

function ayni($a, $b, float $tol): bool
{
    if ($a === null || $b === null || is_bool($a) || is_bool($b) || is_string($a) || is_string($b)) {
        return $a === $b;
    }
    return abs((float) $a - (float) $b) <= $tol;
}
$TOL = ['A' => 0.0015, 'Qs' => 0.1, 'Qk' => 0.1, 'Qr' => 0.1, 'kapasite' => 0, 'durum' => 0, 'sigar' => 0];

$lib = dirname(__DIR__, 2) . '/site/app/lib/sogutma.php';
$libVar = is_file($lib);
if ($libVar) {
    require_once $lib;
}
$hatalar = [];
$satirlar = [];
foreach ($VEKTOR as $i => [$g, $tablo]) {
    $ref = basvuru($g);
    foreach ($TOL as $alan => $t) {
        if (!ayni($ref[$alan], $tablo[$alan], $t)) {
            $hatalar[] = sprintf('satır %d %s: spec tablosu %s, formül %s (spec tablosu formülle uyuşmuyor, rapor edin)', $i + 1, $alan, var_export($tablo[$alan], true), var_export($ref[$alan], true));
        }
    }
    $uyg = null;
    if ($libVar && function_exists('sogutma_hesapla')) {
        $uyg = sogutma_hesapla($g);
        foreach ($TOL as $alan => $t) {
            if (!array_key_exists($alan, $uyg) || !ayni($uyg[$alan], $ref[$alan], max($t, 0.05))) {
                $hatalar[] = sprintf('satır %d %s: sogutma.php %s, beklenen %s', $i + 1, $alan, var_export($uyg[$alan] ?? '(yok)', true), var_export($ref[$alan], true));
            }
        }
    }
    $satirlar[] = ['girdi' => $g, 'basvuru' => $ref, 'uygulama' => $uyg];
}

if (in_array('--json', $argv, true)) {
    echo json_encode(['lib' => $libVar, 'satirlar' => $satirlar, 'hatalar' => $hatalar], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    foreach ($satirlar as $i => $s) {
        $r = $s['basvuru'];
        printf("%d  A %.3f  Qs %+.1f  Qk %.1f  Qr %s  %s%s\n", $i + 1, $r['A'], $r['Qs'], $r['Qk'], $r['Qr'] === null ? '-' : sprintf('%.1f', $r['Qr']),
            $r['durum'] === 'model' ? 'kapasite ' . $r['kapasite'] . ' W' : $r['durum'], $r['sigar'] === null ? '' : ($r['sigar'] ? ', sığar' : ', sığmaz'));
    }
    if (!$libVar) {
        echo "not: site/app/lib/sogutma.php henüz yok; yalnızca spec tablosu başvuru hesabıyla denetlendi.\n";
    } elseif (!function_exists('sogutma_hesapla')) {
        $hatalar[] = 'sogutma.php içinde sogutma_hesapla() yok';
    }
    foreach ($hatalar as $h) {
        echo "HATA $h\n";
    }
    echo $hatalar ? count($hatalar) . " hata\n" : "tamam\n";
}
exit($hatalar ? 1 : 0);
