<?php
/**
 * Görsel çıktısı. tools/gorsel.sh şu dosyaları üretir:
 *   assets/img/{klasor}/{ad}-{genislik}.avif|webp|jpg   (genişlikler 480, 960, 1440, 1920)
 * resim('urun/u01', 'Kompakt şalter, temsilî görsel') hangi genişlikler varsa onlarla <picture> yazar.
 * Hiç dosya yoksa boş metin döner (istasyon yalnızca sembolle görünür, 404 isteği oluşmaz).
 */

/**
 * Varlık planı kimliğini dosya adına çözer: 'urun/u01' -> 'urun/u01-kompakt-salter' (u01-kompakt-salter-480.jpg vb. varsa).
 * Yalnızca kimlik biçimindeki adlara (u01, k12) uygulanır; tam ad zaten dosyaysa olduğu gibi döner, dosya yoksa ad değişmez.
 */
function resim_adi(string $ad): string
{
    static $cache = [];
    if (isset($cache[$ad])) {
        return $cache[$ad];
    }
    $sonuc = $ad;
    if (resim_dosyalari($ad) === [] && preg_match('~^(.*/)?[a-z]{1,3}\d{2,3}$~', $ad)) {
        foreach (glob(ROOT . '/assets/img/' . ltrim($ad, '/') . '-*.{avif,webp,jpg,png}', GLOB_BRACE) ?: [] as $yol) {
            $tam = substr($yol, strlen(ROOT . '/assets/img/'));
            if (preg_match('~^(.*)-\d+\.(avif|webp|jpg|png)$~', $tam, $m) && resim_dosyalari($m[1]) !== []) {
                $sonuc = $m[1];
                break;
            }
        }
    }
    return $cache[$ad] = $sonuc;
}

/** Var olan genişlikler ve biçimler: ['480' => ['avif' => bool, 'webp' => bool, 'jpg' => bool], ...] */
function resim_dosyalari(string $ad): array
{
    static $cache = [];
    if (isset($cache[$ad])) {
        return $cache[$ad];
    }
    $taban = ROOT . '/assets/img/' . ltrim($ad, '/');
    $sonuc = [];
    foreach (glob($taban . '-*.{avif,webp,jpg,png}', GLOB_BRACE) ?: [] as $yol) {
        if (preg_match('~-(\d+)\.(avif|webp|jpg|png)$~', $yol, $m) && strpos(substr($yol, 0, -strlen($m[0])), $taban) === 0
            && substr($yol, 0, -strlen($m[0])) === $taban) {
            $sonuc[(int) $m[1]][$m[2]] = $yol;
        }
    }
    ksort($sonuc);
    return $cache[$ad] = $sonuc;
}

/** Görsel var mı */
function resim_var(string $ad): bool
{
    return resim_dosyalari(resim_adi($ad)) !== [];
}

/**
 * <picture> çıktısı.
 * $opts:
 *   sizes     ör. '(min-width: 1024px) 560px, 100vw' (varsayılan 100vw)
 *   onyukle   true: loading eager + fetchpriority high (LCP görseli); head.php'ye de 'onyukle' verin
 *   sinif     <img> sınıfı;  cerceve_sinif: <picture> sınıfı
 *   genislik  en büyük kullanılacak genişlik sınırı
 */
function resim(string $ad, string $alt, array $opts = []): string
{
    $ad = resim_adi($ad);
    $dosyalar = resim_dosyalari($ad);
    if (!$dosyalar) {
        return '';
    }
    if (!empty($opts['genislik'])) {
        $dosyalar = array_filter($dosyalar, fn ($w) => $w <= (int) $opts['genislik'], ARRAY_FILTER_USE_KEY) ?: array_slice($dosyalar, 0, 1, true);
    }
    $kok = base_path() . '/assets/img/' . ltrim($ad, '/');
    $srcset = function (string $bicim) use ($dosyalar, $kok): string {
        $p = [];
        foreach ($dosyalar as $w => $b) {
            if (isset($b[$bicim])) {
                $p[] = $kok . '-' . $w . '.' . $bicim . ' ' . $w . 'w';
            }
        }
        return implode(', ', $p);
    };
    $sizes = $opts['sizes'] ?? '100vw';
    $html = '<picture' . (isset($opts['cerceve_sinif']) ? ' class="' . e($opts['cerceve_sinif']) . '"' : '') . '>';
    foreach (['avif' => 'image/avif', 'webp' => 'image/webp'] as $bicim => $mime) {
        $s = $srcset($bicim);
        if ($s !== '') {
            $html .= '<source type="' . $mime . '" srcset="' . e($s) . '" sizes="' . e($sizes) . '">';
        }
    }
    // yedek: en küçük jpg/png, boyutları gerçek dosyadan
    $yedekW = null;
    $yedekYol = null;
    foreach ($dosyalar as $w => $b) {
        $yedekYol = $b['jpg'] ?? $b['png'] ?? null;
        if ($yedekYol) {
            $yedekW = $w;
            break;
        }
    }
    if ($yedekYol === null) {
        $ilk = reset($dosyalar);
        $yedekYol = reset($ilk);
        $yedekW = array_key_first($dosyalar);
    }
    $boyut = @getimagesize($yedekYol) ?: [0, 0];
    if (!$boyut[0]) {
        // AVIF için getimagesize desteklenmeyebilir; oranı en az bir jpg/webp'den al
        foreach ($dosyalar as $b) {
            foreach (['jpg', 'png', 'webp'] as $bc) {
                if (isset($b[$bc]) && ($bz = @getimagesize($b[$bc]))) {
                    $boyut = $bz;
                    break 2;
                }
            }
        }
    }
    $en = (int) ($boyut[0] ?: $yedekW);
    $boy = (int) ($boyut[1] ?: 0);
    $jpgSrcset = $srcset('jpg') ?: $srcset('png');
    $src = $kok . '-' . $yedekW . '.' . pathinfo($yedekYol, PATHINFO_EXTENSION);
    $html .= '<img src="' . e($src) . '"' . ($jpgSrcset !== '' ? ' srcset="' . e($jpgSrcset) . '" sizes="' . e($sizes) . '"' : '')
           . ' alt="' . e($alt) . '" width="' . $en . '"' . ($boy ? ' height="' . $boy . '"' : '')
           . (isset($opts['sinif']) ? ' class="' . e($opts['sinif']) . '"' : '')
           . (!empty($opts['onyukle']) ? ' loading="eager" fetchpriority="high"' : ' loading="lazy"')
           . ' decoding="async">';
    return $html . '</picture>';
}

/** head.php için LCP ön yükleme satırı (yalnızca dosya varsa) */
function resim_onyukle(string $ad, string $sizes = '100vw'): string
{
    $ad = resim_adi($ad);
    $dosyalar = resim_dosyalari($ad);
    if (!$dosyalar) {
        return '';
    }
    $kok = base_path() . '/assets/img/' . ltrim($ad, '/');
    foreach (['avif' => 'image/avif', 'webp' => 'image/webp', 'jpg' => 'image/jpeg'] as $bicim => $mime) {
        $p = [];
        foreach ($dosyalar as $w => $b) {
            if (isset($b[$bicim])) {
                $p[] = $kok . '-' . $w . '.' . $bicim . ' ' . $w . 'w';
            }
        }
        if ($p) {
            return '<link rel="preload" as="image" type="' . $mime . '" imagesrcset="' . e(implode(', ', $p)) . '" imagesizes="' . e($sizes) . '" fetchpriority="high">';
        }
    }
    return '';
}
