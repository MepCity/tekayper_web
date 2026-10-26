<?php
/**
 * Kategori testi (spec 4.4). Çalıştırma: php tools/qa/kategori-testi.php
 *  1. 37 marka, 178 grup, 15 kategori.
 *  2. Her grubun markalar.php'deki (elle kontrol edilmiş) kategorisi geçerli bir kimlik.
 *  3. Kategori kuralları (kategoriler.php) her grup için aynı kategoriyi buluyor; farklı olanlar ve
 *     hiçbir kurala uymayanlar yazdırılır.
 * Çıkış kodu: 0 = geçti, 1 = hata.
 */
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2) . '/site';
require dirname(__DIR__, 2) . '/site/app/bootstrap.php';

$hata = 0;
$markalar = data('markalar');
$katlar = data('kategoriler')['liste'];

$m = count($markalar);
$g = grup_sayisi();
$k = count($katlar);
printf("Marka: %d (beklenen 37)  Grup: %d (beklenen 178)  Kategori: %d (beklenen 15)\n", $m, $g, $k);
if ($m !== 37 || $g !== 178 || $k !== 15) {
    $hata++;
}

$farkli = [];
$eslesmeyen = [];
$sayac = array_fill_keys(array_keys($katlar), 0);
foreach ($markalar as $mk) {
    foreach ($mk['gruplar'] as $gr) {
        if (!isset($katlar[$gr['kategori']])) {
            echo "GEÇERSİZ KATEGORİ: {$mk['ad']}: {$gr['ad']} -> {$gr['kategori']}\n";
            $hata++;
            continue;
        }
        $sayac[$gr['kategori']]++;
        $kural = kategori_bul($gr['ad']);
        if ($kural === null) {
            $eslesmeyen[] = "{$mk['ad']}: {$gr['ad']} (elle: {$gr['kategori']})";
        } elseif ($kural !== $gr['kategori']) {
            $farkli[] = "{$mk['ad']}: {$gr['ad']}  kural: $kural  elle: {$gr['kategori']}";
        }
    }
}

echo "\nKategori başına grup:\n";
foreach ($sayac as $id => $n) {
    printf("  %-8s %3d  %s\n", $id, $n, $katlar[$id]['ad']);
    if ($n === 0) {
        echo "    UYARI: boş kategori\n";
        $hata++;
    }
}

echo "\nKurala uymayan grup: " . count($eslesmeyen) . "\n";
foreach ($eslesmeyen as $s) {
    echo "  $s\n";
}
echo "Kural ile elle kontrol farklı: " . count($farkli) . "\n";
foreach ($farkli as $s) {
    echo "  $s\n";
}
$hata += count($eslesmeyen) + count($farkli);

echo $hata === 0 ? "\nSONUÇ: GEÇTİ\n" : "\nSONUÇ: $hata HATA\n";
exit($hata === 0 ? 0 : 1);
