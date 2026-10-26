<?php
/**
 * Pano soğutma hesabı (paket P5, spec 3.5). Sayfa: pano-sogutma-sistemleri.php. JS ikizi: assets/js/sayfa-pano.js
 * (window.tkySogutma.hesapla). İkisi aynı formülü ve aynı yuvarlamayı kullanır; QA: tools/qa/sogutma-testi.php
 * ve tools/qa/sogutma.mjs 5 test vektörünü karşılaştırır.
 *
 * Yöntem: IEC/TR 60890 (DIN VDE 0660-507) etkin yüzey hesabı.
 *   A  = kurulum şekline göre etkin soğutma yüzeyi (m²), H W D metre.
 *        Yüzey katsayıları: açık yan yüzey 0,9, açık tavan 1,4, kapalı (duvara/komşuya dayalı) yan yüzey 0,5,
 *        kapalı tavan 0,7. Aşağıdaki 7 formül bu katsayıların toplanmış hâlidir (ön+arka+yanlar+tavan; taban sayılmaz).
 *   Qs = k * A * (Tu - Ti)    W. Pozitif: ortam iç sıcaklıktan sıcak, ısı duvarlardan içeri girer.
 *   Qk = Qv + Qs              W. Klimanın çekmesi gereken ısı.
 *   Qr = 1,1 * Qk             W. %10 pay.
 *   Model: kapasitesi Qr'den büyük ya da eşit en küçük TPK. Qk <= 0 ise klima gerekmeyebilir. Hiçbiri yetmezse 'buyuk'.
 *
 * Sığma kontrolü (mühendis kontrolü): TPK dikili tip, yan kapağa monte edilir. Ünite yüksekliği <= pano yüksekliği
 * ve ünite genişliği <= pano derinliği ise yan yüzeye sığar. Montaj boşlukları, kapak menteşesi ve kesim ölçüleri
 * hesaba katılmaz; kesin karar için üretici montaj çizimi ile kontrol edilmelidir.
 * Ek kural (mühendis kontrolü): sıranın ortasındaki panoda (kurulum 5, 6, 7) iki yan yüzey de komşu panoya dayalıdır,
 * yan yüzey montajı yapılamaz; bu durumda 'yan_bos' = false olur ve sayfa bunu yazar.
 */
declare(strict_types=1);

/** Girdi alanları: [etiket, birim, varsayılan, en az, en çok, adım] (spec 3.5 tablosu) */
function sogutma_alanlar(): array
{
    return [
        'h'  => ['etiket' => 'Yükseklik', 'birim' => 'mm', 'varsayilan' => 2000, 'min' => 300, 'max' => 2500, 'adim' => 10],
        'w'  => ['etiket' => 'Genişlik', 'birim' => 'mm', 'varsayilan' => 800, 'min' => 200, 'max' => 2400, 'adim' => 10],
        'd'  => ['etiket' => 'Derinlik', 'birim' => 'mm', 'varsayilan' => 600, 'min' => 150, 'max' => 1000, 'adim' => 10],
        'qv' => ['etiket' => 'Panodaki ısı kaybı', 'birim' => 'W', 'varsayilan' => 800, 'min' => 0, 'max' => 20000, 'adim' => 10],
        'tu' => ['etiket' => 'En yüksek ortam sıcaklığı', 'birim' => '°C', 'varsayilan' => 40, 'min' => 0, 'max' => 60, 'adim' => 1],
        'ti' => ['etiket' => 'İstenen iç sıcaklık', 'birim' => '°C', 'varsayilan' => 35, 'min' => 20, 'max' => 50, 'adim' => 1],
    ];
}

/** Pano gövdesi ısı geçirgenlik katsayıları k (W/m²K) */
function sogutma_govdeler(): array
{
    return [
        '5.5' => 'Boyalı sac',
        '3.7' => 'Paslanmaz çelik',
        '12'  => 'Alüminyum',
        '4.5' => 'Çift cidarlı alüminyum',
        '3.5' => 'Polyester',
    ];
}

/** Kurulum şekilleri (IEC/TR 60890). kisa: piktogram altı, ad: tam ad, formul: ekranda gösterilen formül */
function sogutma_kurulumlar(): array
{
    return [
        1 => ['ad' => 'Tek başına, her yanı açık', 'kisa' => 'Tek, açık', 'formul' => 'A = 1,8·H·(W+D) + 1,4·W·D', 'duvar' => false, 'komsu' => 0, 'tavan' => true],
        2 => ['ad' => 'Tek başına, duvara dayalı', 'kisa' => 'Tek, duvarda', 'formul' => 'A = 1,4·W·(H+D) + 1,8·D·H', 'duvar' => true, 'komsu' => 0, 'tavan' => true],
        3 => ['ad' => 'Sıranın başında ya da sonunda, her yanı açık', 'kisa' => 'Sıra ucu, açık', 'formul' => 'A = 1,4·D·(H+W) + 1,8·W·H', 'duvar' => false, 'komsu' => 1, 'tavan' => true],
        4 => ['ad' => 'Sıranın başında ya da sonunda, duvara dayalı', 'kisa' => 'Sıra ucu, duvarda', 'formul' => 'A = 1,4·H·(W+D) + 1,4·W·D', 'duvar' => true, 'komsu' => 1, 'tavan' => true],
        5 => ['ad' => 'Sıranın ortasında, her yanı açık', 'kisa' => 'Sıra ortası, açık', 'formul' => 'A = 1,8·W·H + 1,4·W·D + D·H', 'duvar' => false, 'komsu' => 2, 'tavan' => true],
        6 => ['ad' => 'Sıranın ortasında, duvara dayalı', 'kisa' => 'Sıra ortası, duvarda', 'formul' => 'A = 1,4·W·(H+D) + D·H', 'duvar' => true, 'komsu' => 2, 'tavan' => true],
        7 => ['ad' => 'Sıranın ortasında, duvara dayalı, üstü kapalı', 'kisa' => 'Sıra ortası, üstü kapalı', 'formul' => 'A = 1,4·W·H + 0,7·W·D + D·H', 'duvar' => true, 'komsu' => 2, 'tavan' => false],
    ];
}

/** Etkin yüzey A (m²). H W D metre. */
function sogutma_alan(int $kurulum, float $H, float $W, float $D): float
{
    switch ($kurulum) {
        case 1: return 1.8 * $H * ($W + $D) + 1.4 * $W * $D;
        case 2: return 1.4 * $W * ($H + $D) + 1.8 * $D * $H;
        case 3: return 1.4 * $D * ($H + $W) + 1.8 * $W * $H;
        case 4: return 1.4 * $H * ($W + $D) + 1.4 * $W * $D;
        case 5: return 1.8 * $W * $H + 1.4 * $W * $D + $D * $H;
        case 6: return 1.4 * $W * ($H + $D) + $D * $H;
        case 7: return 1.4 * $W * $H + 0.7 * $W * $D + $D * $H;
    }
    throw new InvalidArgumentException('Kurulum 1-7 olmalı: ' . $kurulum);
}

/**
 * TPK kapasite basamakları, tpk.php'den: [kapasite => ['ad' => 'TPK 1000', 'p' => 'TPK 1000P', 'yukseklik', 'genislik']]
 * Yükseklik ve genişlik iki seride aynıdır (derinlik farklı, sığma kontrolünde kullanılmaz).
 */
function sogutma_basamaklar(): array
{
    static $b = null;
    if ($b !== null) {
        return $b;
    }
    $b = [];
    // QA testi bu dosyayı bootstrap olmadan yükler: data() yoksa veri dosyası doğrudan okunur
    $tpk = function_exists('data') ? data('tpk') : require dirname(__DIR__) . '/data/tpk.php';
    foreach ($tpk['modeller'] as $m) {
        $c = (int) $m['w'];
        $b[$c] = $b[$c] ?? ['kapasite' => $c, 'ad' => null, 'p' => null, 'yukseklik' => $m['ebat'][0], 'genislik' => $m['ebat'][1]];
        if ($m['seri'] === 'p') {
            $b[$c]['p'] = $m['kod'];
        } else {
            $b[$c]['ad'] = $m['kod'];
        }
    }
    ksort($b);
    return $b;
}

/**
 * Hesap. Girdi: ['h'=>mm, 'w'=>mm, 'd'=>mm, 'kurulum'=>1..7, 'k'=>W/m²K, 'qv'=>W, 'tu'=>°C, 'ti'=>°C]
 * Çıktı (QA sözleşmesi): A, Qs, Qk, Qr (null), kapasite (null), durum 'model'|'yok'|'buyuk', sigar (bool|null)
 * Ek alanlar: model (basamak dizisi|null), kucuk (bir alt basamak|null), yan_bos (bool), aralik_disi (bool)
 */
function sogutma_hesapla(array $g): array
{
    $H = (float) $g['h'] / 1000;
    $W = (float) $g['w'] / 1000;
    $D = (float) $g['d'] / 1000;
    $kurulum = (int) $g['kurulum'];
    $A = sogutma_alan($kurulum, $H, $W, $D);
    $Qs = (float) $g['k'] * $A * ((float) $g['tu'] - (float) $g['ti']);
    $Qk = (float) $g['qv'] + $Qs;
    $sonuc = [
        'A' => $A, 'Qs' => $Qs, 'Qk' => $Qk, 'Qr' => null, 'kapasite' => null, 'durum' => 'yok', 'sigar' => null,
        'model' => null, 'kucuk' => null,
        'yan_bos' => $kurulum <= 4,
        'aralik_disi' => (float) $g['tu'] < 20 || (float) $g['tu'] > 55,
    ];
    if ($Qk <= 0) {
        return $sonuc;
    }
    $Qr = 1.1 * $Qk;
    $sonuc['Qr'] = $Qr;
    $sonuc['durum'] = 'buyuk';
    $onceki = null;
    foreach (sogutma_basamaklar() as $c => $b) {
        if ($c >= $Qr) {
            $sonuc['kapasite'] = $c;
            $sonuc['durum'] = 'model';
            $sonuc['model'] = $b;
            $sonuc['kucuk'] = $onceki;
            // mühendis kontrolü: yan yüzey ölçüsüne göre sığma
            $sonuc['sigar'] = $b['yukseklik'] <= (float) $g['h'] && $b['genislik'] <= (float) $g['d'];
            return $sonuc;
        }
        $onceki = $b;
    }
    $sonuc['kucuk'] = $onceki; // en büyük ünite (3340 W) metinde anılır
    return $sonuc;
}

/** Varsayılan girdi */
function sogutma_varsayilan(): array
{
    $g = ['kurulum' => 2, 'k' => 5.5];
    foreach (sogutma_alanlar() as $ad => $a) {
        $g[$ad] = $a['varsayilan'];
    }
    return $g;
}

/** "1.200,5" / "1200.5" / "1200,5" metnini sayıya çevirir; geçersizse null */
function sogutma_sayi(string $s): ?float
{
    $s = trim(str_replace(["\u{00A0}", ' '], '', $s));
    if ($s === '') {
        return null;
    }
    if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $s)) {      // 1.200 ya da 1.200,5
        $s = str_replace('.', '', $s);
    }
    $s = str_replace(',', '.', $s);
    if (!preg_match('/^-?\d+(\.\d+)?$/', $s)) {
        return null;
    }
    return (float) $s;
}

/**
 * GET girdisini okur ve denetler. Dönüş: ['g' => geçerli girdi (hatalı alan varsayılana döner), 'ham' => yazılan metin,
 * 'hatalar' => [alan => cümle], 'gonderildi' => bool]
 */
function sogutma_girdi(array $kaynak): array
{
    $g = sogutma_varsayilan();
    $ham = [];
    $hatalar = [];
    $gonderildi = false;
    foreach (sogutma_alanlar() as $ad => $a) {
        $ham[$ad] = (string) $g[$ad];
        if (!isset($kaynak[$ad]) || is_array($kaynak[$ad])) {
            continue;
        }
        $gonderildi = true;
        $ham[$ad] = mb_substr(trim((string) $kaynak[$ad]), 0, 12);
        $v = sogutma_sayi($ham[$ad]);
        if ($v === null || $v < $a['min'] || $v > $a['max']) {
            $hatalar[$ad] = sogutma_hata_metni($ad);
            continue;
        }
        $g[$ad] = $v;
    }
    if (isset($kaynak['kurulum']) && !is_array($kaynak['kurulum'])) {
        $gonderildi = true;
        $k = (int) $kaynak['kurulum'];
        if ($k >= 1 && $k <= 7) {
            $g['kurulum'] = $k;
        }
    }
    if (isset($kaynak['k']) && !is_array($kaynak['k'])) {
        $gonderildi = true;
        $k = str_replace(',', '.', (string) $kaynak['k']);
        if (isset(sogutma_govdeler()[$k])) {
            $g['k'] = (float) $k;
        }
    }
    return ['g' => $g, 'ham' => $ham, 'hatalar' => $hatalar, 'gonderildi' => $gonderildi];
}

/** Aralık dışı ya da sayı olmayan değer için cümle (JS aynı metni üretir) */
function sogutma_hata_metni(string $ad): string
{
    $a = sogutma_alanlar()[$ad];
    return $a['etiket'] . ' için ' . sogutma_tr($a['min']) . ' ile ' . sogutma_tr($a['max']) . ' ' . $a['birim'] . ' arası bir sayı yazın.';
}

/** Türkçe sayı: binlik nokta, ondalık virgül. Yuvarlama sıfırdan uzağa (JS'deki yuvarla() ile aynı). */
function sogutma_tr(float $v, int $basamak = 0): string
{
    $c = 10 ** $basamak;
    $r = ($v < 0 ? -1 : 1) * floor(abs($v) * $c + 0.5) / $c;
    if ($r == 0) {
        $r = 0.0;
    }
    return number_format($r, $basamak, ',', '.');
}

/** İşaretli W: "+139 W" ya da "-418 W" (kısa çizgi; ChillDIN'de U+2212 yok) */
function sogutma_isaretli(float $v): string
{
    $s = sogutma_tr($v);
    return ($s !== '0' && $v > 0 ? '+' : '') . $s . ' W';
}

/** Kapasiteden model adı: 1080 => 'TPK 1000' */
function sogutma_model_adi(int $kapasite): string
{
    return sogutma_basamaklar()[$kapasite]['ad'] ?? ('TPK ' . $kapasite);
}

/**
 * Sonuç metinleri (spec 3.5 şablonları). JS aynı fonksiyonu taşır (sayfa-pano.js metinler()).
 * Dönüş: ['satirlar' => [[kod, ad, değer]], 'oneri' => ?string, 'kucuk' => ?string, 'montaj' => ?string,
 *         'montaj_uyari' => bool, 'durum_metni' => ?string, 'aralik' => ?string, 'konu' => string, 'hesap' => string,
 *         'ozet' => string]
 */
function sogutma_metinler(array $g, array $r): array
{
    $qs = $r['Qs'];
    $qsYon = sogutma_tr($qs) === '0' ? '' : ($qs > 0 ? ' (içeri)' : ' (dışarı)');
    $m = [
        'satirlar' => [
            ['A', 'Etkin yüzey', sogutma_tr($r['A'], 2) . ' m²'],
            ['Qv', 'Panodaki ısı kaybı', sogutma_tr((float) $g['qv']) . ' W'],
            ['Qs', 'Yüzeyden geçen ısı', sogutma_isaretli($qs) . $qsYon],
            ['Qk', 'Gereken soğutma', sogutma_tr($r['Qk']) . ' W'],
            ['Qr', '%10 payla', $r['Qr'] === null ? '-' : sogutma_tr($r['Qr']) . ' W'],
        ],
        'oneri' => null, 'kucuk' => null, 'montaj' => null, 'montaj_uyari' => false, 'durum_metni' => null, 'aralik' => null,
    ];
    $kurulum = sogutma_kurulumlar()[(int) $g['kurulum']];
    if ($r['durum'] === 'model') {
        $b = $r['model'];
        $m['oneri'] = 'Önerilen model: ' . $b['p'] . ' (iç ortam) ya da ' . $b['ad'] . ' (dış ortam), ' . sogutma_tr((float) $b['kapasite']) . ' W.';
        if ($r['kucuk']) {
            $m['kucuk'] = $r['kucuk']['ad'] . ' yetmez: ' . sogutma_tr((float) $r['kucuk']['kapasite']) . ' W veriyor, gereken ' . sogutma_tr($r['Qr']) . ' W.';
        }
        $yanYuzey = sogutma_tr((float) $g['h']) . ' x ' . sogutma_tr((float) $g['d']) . ' mm';
        if (!$r['yan_bos']) {
            $m['montaj'] = 'Montaj: ' . $b['ad'] . ' yan yüzeye monte edilir. Panonuz sıranın ortasında olduğu için iki yan yüzeyi de komşu panoya dayalı; montaj yeri için bize danışın.';
            $m['montaj_uyari'] = true;
        } elseif ($r['sigar']) {
            $m['montaj'] = 'Montaj: ' . $b['ad'] . ' yüksekliği ' . sogutma_tr((float) $b['yukseklik']) . ' mm, genişliği ' . sogutma_tr((float) $b['genislik'])
                . ' mm. Panonuzun yan yüzeyi ' . $yanYuzey . ', sığar.';
        } else {
            $parca = [];
            if ($b['yukseklik'] > $g['h']) {
                $parca[] = 'yüksekliği ' . sogutma_tr((float) $b['yukseklik']) . ' mm, panonuzun yüksekliği ' . sogutma_tr((float) $g['h']) . ' mm';
            }
            if ($b['genislik'] > $g['d']) {
                $parca[] = 'genişliği ' . sogutma_tr((float) $b['genislik']) . ' mm, panonuzun derinliği ' . sogutma_tr((float) $g['d']) . ' mm';
            }
            $m['montaj'] = 'Montaj: ' . $b['ad'] . ' ' . implode('; ', $parca) . '. Yan yüzeye sığmaz; farklı montaj için bize danışın.';
            $m['montaj_uyari'] = true;
        }
    } elseif ($r['durum'] === 'yok') {
        $m['durum_metni'] = 'Klima gerekmeyebilir: pano yüzeyi bu ısıyı dışarı atabiliyor. Toz ve nem için filtreli fan yeterli olabilir, bize danışın.';
    } else {
        $m['durum_metni'] = 'Gereken soğutma tek ünitenin kapasitesini aşıyor (3.340 W). İki ya da daha fazla ünite veya farklı bir çözüm için projeyi birlikte değerlendirelim.';
    }
    if ($r['aralik_disi']) {
        $m['aralik'] = 'Dikkat: TPK serisi +20 °C ile +55 °C ortam sıcaklığında çalışır.';
    }

    // teklif bağlantısı: yalnızca teknik değerler (kişisel veri yok)
    $gerek = $r['Qr'] === null ? null : sogutma_tr($r['Qr']) . ' W';
    if ($r['durum'] === 'model') {
        $m['konu'] = 'Pano soğutma: ' . $r['model']['ad'] . ', gereken ' . $gerek;
    } elseif ($r['durum'] === 'buyuk') {
        $m['konu'] = 'Pano soğutma: gereken ' . $gerek;
    } else {
        $m['konu'] = 'Pano soğutma: filtreli fan ya da klima';
    }
    // P8 sözleşmesi: anahtar=değer, ';' ile, yalnızca teknik değerler (iletisim.php Türkçe etiketlerle açar)
    $duz = fn (float $v): string => (string) (int) (($v < 0 ? -1 : 1) * floor(abs($v) + 0.5));
    $m['hesap'] = 'h=' . $duz((float) $g['h']) . ';w=' . $duz((float) $g['w']) . ';d=' . $duz((float) $g['d'])
        . ';kurulum=' . (int) $g['kurulum'] . ';k=' . str_replace('.', ',', sogutma_k_anahtar((float) $g['k']))
        . ';qv=' . $duz((float) $g['qv']) . ';tu=' . $duz((float) $g['tu']) . ';ti=' . $duz((float) $g['ti'])
        . ';qk=' . $duz($r['Qr'] ?? $r['Qk'])
        . ($r['durum'] === 'model' ? ';model=' . $r['model']['ad'] : '');

    // kapanış özeti (tek cümle)
    $pano = sogutma_tr((float) $g['h']) . ' x ' . sogutma_tr((float) $g['w']) . ' x ' . sogutma_tr((float) $g['d']) . ' mm';
    if ($r['durum'] === 'model') {
        $m['ozet'] = $pano . ' pano, ' . sogutma_tr((float) $g['qv']) . ' W ısı kaybı: %10 payla ' . $gerek . ' soğutma, ' . $r['model']['p'] . ' ya da ' . $r['model']['ad'] . '.';
    } elseif ($r['durum'] === 'buyuk') {
        $m['ozet'] = $pano . ' pano, ' . sogutma_tr((float) $g['qv']) . ' W ısı kaybı: %10 payla ' . $gerek . ' soğutma, tek ünitenin üstünde.';
    } else {
        $m['ozet'] = $pano . ' pano, ' . sogutma_tr((float) $g['qv']) . ' W ısı kaybı: yüzey ısıyı dışarı atabiliyor, klima gerekmeyebilir.';
    }
    return $m;
}

/** k değerinin govdeler() anahtarı: 5.5 => '5.5', 12.0 => '12' */
function sogutma_k_anahtar(float $k): string
{
    return rtrim(rtrim(number_format($k, 1, '.', ''), '0'), '.');
}

/** Teklif bağlantısı (iletisim.php?konu=...&hesap=...#talep) */
function sogutma_teklif_url(array $metin): string
{
    return url('iletisim.php') . '?' . http_build_query(['konu' => $metin['konu'], 'hesap' => $metin['hesap']], '', '&', PHP_QUERY_RFC3986) . '#talep';
}

/* ------------------------------------------------------------------ çizim (sunucu tarafı, JS aynı yerleşimi kurar) */

/** Ölçek paydaları: çizim gerçek ölçekle (CSS mm) çizilir, sığan en büyük ölçek seçilir. */
function sogutma_olcekler(): array
{
    return [5, 6, 8, 10, 12, 15, 20, 25, 30, 40, 50, 60, 80, 100];
}

/**
 * Çizim yerleşimi. Kutu: SVG'nin piksel boyutu (1 birim = 1 CSS px). Dönüş: ölçek paydası ve px cinsinden dikdörtgenler.
 *   on: ön görünüş, yan: sağdan görünüş, tpk: yan yüzeydeki ünite (yoksa null)
 */
function sogutma_yerlesim(array $g, ?array $r, float $kutuW, float $kutuH): array
{
    $pxMm = 96 / 25.4;
    $mL = 116; $mR = 44; $mT = 40; $mB = 84; $ara = 64;
    $uH = 0; $uW = 0;
    if ($r && $r['durum'] === 'model') {
        $uH = (float) $r['model']['yukseklik'];
        $uW = (float) $r['model']['genislik'];
    }
    $H = (float) $g['h']; $W = (float) $g['w']; $D = (float) $g['d'];
    $komsu = sogutma_kurulumlar()[(int) $g['kurulum']]['komsu'];
    $komsuPay = $komsu ? 0.18 * $W : 0; // komşu panonun görünen parçası (kırık çizgiyle kesilir)
    $sigW = $kutuW - $mL - $mR - $ara;
    $sigH = $kutuH - $mT - $mB;
    $n = 100;
    foreach (sogutma_olcekler() as $aday) {
        $k = $pxMm / $aday;
        if (($W + $komsuPay * ($komsu === 2 ? 2 : 1) + max($D, $uW)) * $k <= $sigW && max($H, $uH) * $k <= $sigH) {
            $n = $aday;
            break;
        }
    }
    $k = $pxMm / $n;
    $taban = $mT + $sigH;
    // kullanılmayan genişliğin yarısı kadar sağa: çizim kutuda ortalanır
    $kullanilan = ($W + $komsuPay * ($komsu === 2 ? 2 : 1) + max($D, $uW)) * $k;
    $x0 = $mL + max(0, ($sigW - $kullanilan) / 2) + ($komsu ? $komsuPay * $k : 0);
    $on = ['x' => $x0, 'y' => $taban - $H * $k, 'w' => $W * $k, 'h' => $H * $k];
    $yx = $x0 + $W * $k + ($komsu === 2 ? $komsuPay * $k : 0) + $ara + max(0, ($uW - $D) / 2) * $k;
    $yan = ['x' => $yx, 'y' => $taban - $H * $k, 'w' => $D * $k, 'h' => $H * $k];
    $tpk = null;
    if ($uH) {
        $ty = $uH <= $H ? $yan['y'] + min(150.0, ($H - $uH) / 2) * $k : $taban - $uH * $k;
        $tpk = ['x' => $yan['x'] + ($D - $uW) / 2 * $k, 'y' => $ty, 'w' => $uW * $k, 'h' => $uH * $k];
    }
    return ['n' => $n, 'k' => $k, 'taban' => $taban, 'on' => $on, 'yan' => $yan, 'tpk' => $tpk, 'komsu' => $komsu, 'komsuPay' => $komsuPay * $k];
}

/** SVG sayı biçimi */
function sogutma_s(float $v): string
{
    return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') ?: '0';
}

/**
 * Hesap çiziminin sunucu çıktısı (JS kapalıyken görünen hâl; JS açılınca aynı yerleşimi kendisi kurar).
 * Ölçü çizgileri ISO 129 kapalı oklarla, değerler Osifont.
 */
function sogutma_cizim_svg(array $g, array $r, array $y, float $kutuW, float $kutuH): string
{
    $s = 'sogutma_s';
    $on = $y['on']; $yan = $y['yan']; $t = $y['tpk']; $taban = $y['taban'];
    $kur = sogutma_kurulumlar()[(int) $g['kurulum']];
    $o = [];
    $ok = function (float $x, float $yy, float $dx, float $dy) use ($s): string {
        // ok ucu (x,yy), yön (dx,dy): 9 px uzun, 3 px geniş
        $bx = $x - $dx * 9; $by = $yy - $dy * 9;
        return '<path class="c-ok" d="M' . $s($x) . ' ' . $s($yy) . 'L' . $s($bx - $dy * 1.5) . ' ' . $s($by + $dx * 1.5) . 'L' . $s($bx + $dy * 1.5) . ' ' . $s($by - $dx * 1.5) . 'Z"/>';
    };
    $yatayKota = function (float $x1, float $x2, float $yy, float $c, string $deger) use ($s, $ok): string {
        return '<g class="c-kota-s"><path class="cizgi--ince" d="M' . $s($x1) . ' ' . $s($yy + 2) . 'V' . $s($c + 4) . 'M' . $s($x2) . ' ' . $s($yy + 2) . 'V' . $s($c + 4)
            . 'M' . $s($x1) . ' ' . $s($c) . 'H' . $s($x2) . '"/>' . $ok($x1, $c, -1, 0) . $ok($x2, $c, 1, 0)
            . '<text class="c-yazi" x="' . $s(($x1 + $x2) / 2) . '" y="' . $s($c + 18) . '" text-anchor="middle">' . e($deger) . '</text></g>';
    };
    // yapı ızgarası (5 mm)
    $o[] = '<rect class="c-izgara" x="0" y="0" width="' . $s($kutuW) . '" height="' . $s($kutuH) . '" fill="url(#c-izgara-desen)"/>';
    // zemin
    $sag = max($yan['x'] + $yan['w'], $t ? $t['x'] + $t['w'] : 0);
    $o[] = '<path class="cizgi--ince c-zemin" d="M' . $s($on['x'] - $y['komsuPay'] - 12) . ' ' . $s($taban) . 'H' . $s($sag + 16) . '"/>';
    // komşu panolar (kesik çizgi)
    if ($y['komsu']) {
        $p = $y['komsuPay'];
        $o[] = '<path class="cizgi--kesik c-komsu" d="M' . $s($on['x'] - $p) . ' ' . $s($on['y']) . 'H' . $s($on['x']) . 'M' . $s($on['x'] - $p) . ' ' . $s($taban) . 'H' . $s($on['x']) . '"/>';
        if ($y['komsu'] === 2) {
            $x = $on['x'] + $on['w'];
            $o[] = '<path class="cizgi--kesik c-komsu" d="M' . $s($x) . ' ' . $s($on['y']) . 'H' . $s($x + $p) . 'M' . $s($x) . ' ' . $s($taban) . 'H' . $s($x + $p) . '"/>';
        }
    }
    // ön görünüş
    $o[] = '<rect class="cizgi c-on" x="' . $s($on['x']) . '" y="' . $s($on['y']) . '" width="' . $s($on['w']) . '" height="' . $s($on['h']) . '"/>';
    $i = min(8.0, $on['w'] * 0.03);
    $o[] = '<rect class="cizgi--ince" x="' . $s($on['x'] + $i) . '" y="' . $s($on['y'] + $i) . '" width="' . $s($on['w'] - 2 * $i) . '" height="' . $s($on['h'] - 2 * $i) . '"/>';
    // yan görünüş
    $o[] = '<rect class="cizgi c-yan" x="' . $s($yan['x']) . '" y="' . $s($yan['y']) . '" width="' . $s($yan['w']) . '" height="' . $s($yan['h']) . '"/>';
    if ($kur['duvar']) {
        $dx = $yan['x'] + $yan['w'] + 4;
        $o[] = '<path class="cizgi c-duvar" d="M' . $s($dx) . ' ' . $s($yan['y'] - 12) . 'V' . $s($taban) . '"/>';
    }
    if (!$kur['tavan']) {
        $o[] = '<path class="cizgi c-tavan" d="M' . $s($on['x'] - 6) . ' ' . $s($on['y'] - 4) . 'H' . $s($on['x'] + $on['w'] + 6) . 'M' . $s($yan['x'] - 6) . ' ' . $s($yan['y'] - 4) . 'H' . $s($yan['x'] + $yan['w'] + 6) . '"/>';
    }
    // ünite
    if ($t) {
        $sinif = $r['sigar'] && $r['yan_bos'] ? 'cizgi' : 'cizgi--kesik';
        $o[] = '<rect class="' . $sinif . ' c-tpk" x="' . $s($t['x']) . '" y="' . $s($t['y']) . '" width="' . $s($t['w']) . '" height="' . $s($t['h']) . '"/>';
        $o[] = '<text class="c-yazi c-etiket" x="' . $s($t['x']) . '" y="' . $s(min($t['y'], $yan['y']) - 10) . '">-E1 ' . e($r['model']['ad']) . '</text>';
    }
    // ölçüler
    $o[] = $yatayKota($on['x'], $on['x'] + $on['w'], $taban, $taban + 30, sogutma_tr((float) $g['w']));
    $o[] = $yatayKota($yan['x'], $yan['x'] + $yan['w'], $taban, $taban + 30, sogutma_tr((float) $g['d']));
    $hx = $on['x'] - $y['komsuPay'] - 30;
    $o[] = '<g class="c-kota-s"><path class="cizgi--ince" d="M' . $s($on['x'] - 2) . ' ' . $s($on['y']) . 'H' . $s($hx - 4) . 'M' . $s($on['x'] - 2) . ' ' . $s($taban) . 'H' . $s($hx - 4)
        . 'M' . $s($hx) . ' ' . $s($on['y']) . 'V' . $s($taban) . '"/>' . $ok($hx, $on['y'], 0, -1) . $ok($hx, $taban, 0, 1)
        . '<text class="c-yazi" x="' . $s($hx - 8) . '" y="' . $s(($on['y'] + $taban) / 2) . '" text-anchor="end">' . e(sogutma_tr((float) $g['h'])) . '</text></g>';
    $o[] = '<text class="c-yazi c-olcek" x="12" y="' . $s($kutuH - 12) . '">Ölçek 1:' . (int) $y['n'] . '</text>';
    return '<svg class="cizim__svg" viewBox="0 0 ' . $s($kutuW) . ' ' . $s($kutuH) . '" width="' . $s($kutuW) . '" height="' . $s($kutuH) . '" role="img" aria-labelledby="cizim-baslik" data-n="' . (int) $y['n'] . '">'
        . '<title id="cizim-baslik">Pano ön ve yan görünüşü, ölçek 1:' . (int) $y['n'] . ': yükseklik ' . e(sogutma_tr((float) $g['h'])) . ' mm, genişlik '
        . e(sogutma_tr((float) $g['w'])) . ' mm, derinlik ' . e(sogutma_tr((float) $g['d'])) . ' mm' . ($t ? ', yan yüzeyde ' . e($r['model']['ad']) : '') . '</title>'
        . '<defs><pattern id="c-izgara-desen" width="18.898" height="18.898" patternUnits="userSpaceOnUse"><path d="M18.898 0H0V18.898" fill="none" stroke="#3EB8EB" stroke-width="1" opacity="0.18"/></pattern></defs>'
        . '<g class="c-cizim">' . implode('', $o) . '</g></svg>';
}
