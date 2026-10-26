<?php
/**
 * Malzeme listesi araması, sunucu tarafı (spec 3.4). JS olmadan ?ara=&kategori=&marka= ile süzer.
 * assets/js/arama.js (P4) aynı kuralları uygular; kurallar arama_veri() ile sayfaya gömülebilir.
 *
 * Kurallar:
 *  - Katlama: I -> ı, İ -> i, Türkçe küçük harf, ç ğ ı ö ş ü â î û -> c g i o s u a i u; / . - dışındaki noktalama boşluk.
 *  - Kelimelere bölme (boşluk, / . - ayraç), durak kelimeleri (ve ile icin veya) atılır.
 *  - Kök: 5+ harfli kelimeden ek atılır (leri lari nin nun ler lar si su in un i u), kelime 5 harften kısalana kadar tekrar.
 *  - Puan (her sorgu birimi için en iyisi): grup kelimesi aynı 3, marka adı 3, grup kelimesi sorguyla başlıyor 2,
 *    kategori adı 2, eş anlamlı 2, yazım hatası (Damerau-Levenshtein 1, 5+ harf) 1.
 *  - Marka adı: eski sitenin yazılışı (site_ad: MEANWELL, HANGYOUNG) ve bitişik yazım ("meanwell") marka sayılır (3);
 *    marka adındaki yazım hatası (hangyoun, schneder) grup adındakiyle aynı kuralla 1 puan alır.
 *  - Her sorgu birimi eşleşmeli (VE). Marka adı sorgusu markanın bütün gruplarını getirir.
 */

const ARAMA_DURAK = ['ve', 'ile', 'icin', 'veya'];
const ARAMA_EKLER = ['leri', 'lari', 'nin', 'nun', 'ler', 'lar', 'si', 'su', 'in', 'un', 'i', 'u'];

/** Arama katlaması: harf harf (uzunluk korunur, vurgulama için) */
function arama_katla_harfler(string $s): array
{
    $out = [];
    foreach (mb_str_split($s) as $ch) {
        $f = tr_fold($ch);
        if (mb_strlen($f) !== 1) {
            $f = mb_substr($f . ' ', 0, 1);
        }
        if (!preg_match('~[a-z0-9]~', $f)) {
            $f = in_array($f, ['/', '.', '-'], true) ? $f : ' ';
        }
        $out[] = $f;
    }
    return $out;
}

function arama_katla(string $s): string
{
    return implode('', arama_katla_harfler($s));
}

/** Katlanmış metni kelimelere böler (konumlarıyla): [[kelime, başlangıç], ...] */
function arama_kelimeler_konum(string $katli): array
{
    preg_match_all('~[a-z0-9]+~', $katli, $m, PREG_OFFSET_CAPTURE);
    $out = [];
    foreach ($m[0] as [$k, $o]) {
        if (!in_array($k, ARAMA_DURAK, true)) {
            $out[] = [$k, $o];
        }
    }
    return $out;
}

/** Kök bulma */
function arama_kok(string $k): string
{
    while (strlen($k) >= 5) {
        $kesildi = false;
        foreach (ARAMA_EKLER as $ek) {
            $l = strlen($ek);
            if (strlen($k) - $l >= 3 && substr($k, -$l) === $ek) {
                $k = substr($k, 0, -$l);
                $kesildi = true;
                break;
            }
        }
        if (!$kesildi) {
            break;
        }
    }
    return $k;
}

/** Metnin kök dizisi */
function arama_kokler(string $metin): array
{
    return array_map(fn ($x) => arama_kok($x[0]), arama_kelimeler_konum(arama_katla($metin)));
}

/** Damerau-Levenshtein (optimal hizalama) mesafesi en fazla $sinir mi */
function arama_yakin(string $a, string $b, int $sinir = 1): bool
{
    $la = strlen($a);
    $lb = strlen($b);
    if (abs($la - $lb) > $sinir) {
        return false;
    }
    $d = [];
    for ($i = 0; $i <= $la; $i++) {
        $d[$i][0] = $i;
    }
    for ($j = 0; $j <= $lb; $j++) {
        $d[0][$j] = $j;
    }
    for ($i = 1; $i <= $la; $i++) {
        $enAz = PHP_INT_MAX;
        for ($j = 1; $j <= $lb; $j++) {
            $c = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
            $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $c);
            if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
            }
            $enAz = min($enAz, $d[$i][$j]);
        }
        if ($enAz > $sinir) {
            return false;
        }
    }
    return $d[$la][$lb] <= $sinir;
}

/** Eş anlamlı satırları kök dizileri olarak: [[ ['kok',...], ['kok','kok'] ], ...] */
function arama_esanlam_kokleri(): array
{
    static $c = null;
    if ($c === null) {
        $c = [];
        foreach (data('esanlamlar') as $satir) {
            $c[] = array_map('arama_kokler', $satir);
        }
    }
    return $c;
}

/** Bir kök dizisi diğerinin içinde ardışık geçiyor mu */
function arama_dizi_icerir(array $saman, array $igne): bool
{
    $n = count($igne);
    if ($n === 0) {
        return false;
    }
    for ($i = 0; $i + $n <= count($saman); $i++) {
        if (array_slice($saman, $i, $n) === $igne) {
            return true;
        }
    }
    return false;
}

/**
 * Sorguyu birimlere ayırır. Çok kelimeli eş anlamlı terimler (ör. "hiz kontrol", "load cell") tek birim olur.
 * Birim: ['kokler' => [...], 'alternatif' => [[kökler], ...]]
 */
function arama_birimler(string $sorgu): array
{
    $kokler = arama_kokler($sorgu);
    $esan = arama_esanlam_kokleri();
    $birimler = [];
    $i = 0;
    while ($i < count($kokler)) {
        $bulundu = null;
        foreach ($esan as $satir) {
            foreach ($satir as $terim) {
                $n = count($terim);
                if ($n > 1 && array_slice($kokler, $i, $n) === $terim && ($bulundu === null || $n > count($bulundu[0]))) {
                    $bulundu = [$terim, $satir];
                }
            }
        }
        if ($bulundu) {
            $birimler[] = ['kokler' => $bulundu[0], 'alternatif' => $bulundu[1]];
            $i += count($bulundu[0]);
            continue;
        }
        $k = $kokler[$i];
        $alt = [];
        foreach ($esan as $satir) {
            foreach ($satir as $terim) {
                if (count($terim) === 1 && ($terim[0] === $k || (strlen($terim[0]) >= 4 && strpos($k, $terim[0]) === 0))) {
                    $alt = array_merge($alt, $satir);
                    break;
                }
            }
        }
        $birimler[] = ['kokler' => [$k], 'alternatif' => $alt];
        $i++;
    }
    return $birimler;
}

/** Arama dizini: her grup için kökler, marka kökleri, kategori kökleri */
function arama_dizin(): array
{
    static $d = null;
    if ($d !== null) {
        return $d;
    }
    $katlar = data('kategoriler')['liste'];
    $katKok = [];
    foreach ($katlar as $id => $k) {
        $katKok[$id] = arama_kokler($k['ad']);
    }
    $d = [];
    foreach (data('markalar') as $mi => $m) {
        $markaKok = arama_kokler($m['ad']);
        // eski sitenin yazılışı (site_ad: MEANWELL, HANGYOUNG) ve bitişik yazım ("meanwell") aynı markadır
        $markaAlt = [];
        $bitisik = [implode('', $markaKok)];
        if (!empty($m['site_ad'])) {
            $siteKok = arama_kokler($m['site_ad']);
            if ($siteKok !== $markaKok) {
                $markaAlt[] = $siteKok;
            }
            $bitisik[] = implode('', $siteKok);
        }
        $bitisik = array_values(array_unique($bitisik));
        foreach ($m['gruplar'] as $gi => $g) {
            $d[] = [
                'marka' => $m['slug'], 'marka_ad' => $m['ad'], 'grup' => $g['ad'], 'kategori' => $g['kategori'],
                'kategori_ad' => $katlar[$g['kategori']]['ad'],
                'kokler' => arama_kokler($g['ad']), 'marka_kokler' => $markaKok, 'marka_alt' => $markaAlt, 'marka_bitisik' => $bitisik, 'kategori_kokler' => $katKok[$g['kategori']],
                'sira' => $mi * 100 + $gi,
            ];
        }
    }
    return $d;
}

/** Bir birimin bir gruptaki puanı ve türü */
function arama_birim_puani(array $birim, array $g): array
{
    $k = $birim['kokler'];
    $tek = count($k) === 1 ? $k[0] : null;
    // marka adı
    if (arama_dizi_icerir($g['marka_kokler'], $k)) {
        return [3, 'marka'];
    }
    foreach ($g['marka_alt'] as $alt) {
        if (arama_dizi_icerir($alt, $k)) {
            return [3, 'marka'];
        }
    }
    if ($tek !== null && strlen($tek) >= 4 && in_array($tek, $g['marka_bitisik'], true)) {
        return [3, 'marka'];
    }
    // grup: tam kelime / kelime öbeği
    if (arama_dizi_icerir($g['kokler'], $k)) {
        return [3, 'tam'];
    }
    $en = [0, null];
    if ($tek !== null && strlen($tek) >= 3) {
        foreach ($g['kokler'] as $gk) {
            if (strpos($gk, $tek) === 0) {
                $en = [2, 'onek'];
                break;
            }
        }
    }
    if ($en[0] < 2) {
        foreach ($birim['alternatif'] as $alt) {
            if (arama_dizi_icerir($g['kokler'], $alt) || arama_dizi_icerir($g['marka_kokler'], $alt)) {
                $en = [2, 'esanlam'];
                break;
            }
        }
    }
    if ($en[0] < 2 && (arama_dizi_icerir($g['kategori_kokler'], $k) || ($tek !== null && $tek === $g['kategori']))) {
        $en = [2, 'kategori'];
    }
    if ($en[0] < 1 && $tek !== null && strlen($tek) >= 5) {
        // marka adındaki yazım hatası (hangyoun, schneder): marka kelimeleri, eski yazılış ve bitişik yazım
        $markaAdaylari = $g['marka_kokler'];
        foreach ($g['marka_alt'] as $alt) {
            $markaAdaylari = array_merge($markaAdaylari, $alt);
        }
        $markaAdaylari = array_merge($markaAdaylari, $g['marka_bitisik']);
        foreach (array_merge($g['kokler'], $g['kategori_kokler'], $markaAdaylari) as $gk) {
            if (strlen($gk) >= 4 && arama_yakin($tek, $gk)) {
                $en = [1, 'yakin'];
                break;
            }
        }
    }
    return $en;
}

/**
 * Arama. $kategori, $marka: isteğe bağlı süzgeçler (kimlik / slug).
 * Dönüş: ['sorgu','kategori','marka','toplam','sonuclar' => [[marka, marka_ad, grup, kategori, kategori_ad, puan], ...]]
 * Sorgu boşsa (yalnız süzgeç) markalar.php sırasıyla gelir.
 */
function arama_yap(string $sorgu = '', ?string $kategori = null, ?string $marka = null): array
{
    $sorgu = mb_substr(trim($sorgu), 0, 80);
    $birimler = $sorgu === '' ? [] : arama_birimler($sorgu);
    $sonuclar = [];
    foreach (arama_dizin() as $g) {
        if ($kategori && $g['kategori'] !== $kategori) {
            continue;
        }
        if ($marka && $g['marka'] !== $marka) {
            continue;
        }
        $puan = 0;
        $tamam = true;
        foreach ($birimler as $b) {
            [$p] = arama_birim_puani($b, $g);
            if ($p === 0) {
                $tamam = false;
                break;
            }
            $puan += $p;
        }
        if (!$tamam) {
            continue;
        }
        $sonuclar[] = ['marka' => $g['marka'], 'marka_ad' => $g['marka_ad'], 'grup' => $g['grup'], 'kategori' => $g['kategori'],
                       'kategori_ad' => $g['kategori_ad'], 'puan' => $puan, 'sira' => $g['sira']];
    }
    if ($birimler) {
        usort($sonuclar, fn ($a, $b) => [$b['puan'], $a['sira']] <=> [$a['puan'], $b['sira']]);
    }
    return ['sorgu' => $sorgu, 'kategori' => $kategori, 'marka' => $marka, 'toplam' => count(arama_dizin()), 'sonuclar' => $sonuclar];
}

/** İstekten (?ara=&kategori=&marka=) arama */
function arama_istek(): array
{
    $al = fn ($k) => isset($_GET[$k]) && is_string($_GET[$k]) ? trim($_GET[$k]) : '';
    $kat = $al('kategori');
    $kat = isset(data('kategoriler')['liste'][$kat]) ? $kat : null;
    $mar = $al('marka');
    $mar = marka($mar) ? $mar : null;
    return arama_yap($al('ara'), $kat, $mar);
}

/**
 * Metindeki eşleşen kısımları <mark> ile sarar (HTML kaçışlı döner).
 *   arama_vurgula('Işıklı kolonlar', 'isik') -> '<mark>Işık</mark>lı kolonlar'
 */
function arama_vurgula(string $metin, string $sorgu): string
{
    $sorgu = trim($sorgu);
    $harfler = mb_str_split($metin);
    if ($sorgu === '' || !$harfler) {
        return e($metin);
    }
    $katli = implode('', arama_katla_harfler($metin));
    $birimler = arama_birimler($sorgu);
    $isaret = array_fill(0, count($harfler), false);
    foreach (arama_kelimeler_konum($katli) as [$kelime, $bas]) {
        $kok = arama_kok($kelime);
        $uzun = 0;
        foreach ($birimler as $b) {
            foreach ($b['kokler'] as $bk) {
                if ($kok === $bk || (strlen($bk) >= 3 && strpos($kelime, $bk) === 0)) {
                    $uzun = max($uzun, min(strlen($kelime), $kok === $bk ? strlen($kok) : strlen($bk)));
                } elseif (strlen($bk) >= 5 && arama_yakin($bk, $kok)) {
                    $uzun = max($uzun, strlen($kok));
                }
            }
            foreach ($b['alternatif'] as $alt) {
                if (in_array($kok, $alt, true)) {
                    $uzun = max($uzun, strlen($kok));
                }
            }
        }
        for ($i = 0; $i < $uzun; $i++) {
            $isaret[$bas + $i] = true;
        }
    }
    $out = '';
    $acik = false;
    foreach ($harfler as $i => $ch) {
        if ($isaret[$i] && !$acik) {
            $out .= '<mark>';
            $acik = true;
        } elseif (!$isaret[$i] && $acik) {
            $out .= '</mark>';
            $acik = false;
        }
        $out .= e($ch);
    }
    return $out . ($acik ? '</mark>' : '');
}

/** JS'in aynı kuralları kullanması için gömülecek veri: json_gom('malzeme-veri', arama_veri()) */
function arama_veri(): array
{
    $markalar = [];
    foreach (data('markalar') as $m) {
        $markalar[] = ['slug' => $m['slug'], 'ad' => $m['ad'], 'site_ad' => $m['site_ad'] ?? $m['ad'], 'gruplar' => $m['gruplar']];
    }
    $katlar = [];
    foreach (data('kategoriler')['liste'] as $id => $k) {
        $katlar[] = ['id' => $id, 'ad' => $k['ad'], 'sembol' => $k['sembol']];
    }
    return [
        'markalar' => $markalar,
        'kategoriler' => $katlar,
        'esanlamlar' => data('esanlamlar'),
        'durak' => ARAMA_DURAK,
        'ekler' => ARAMA_EKLER,
    ];
}
