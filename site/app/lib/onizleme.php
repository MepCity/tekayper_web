<?php
/**
 * Önizleme (staging) kipi. YALNIZCA TEKAYPER_ONIZLEME=1 ortam değişkeni varken çalışır; canlı sunucuda bu
 * değişken tanımlı olmadığından bu dosya hiçbir şey yapmaz (kod canlıya hazır kalır).
 * Müşterilere gösterim için geçici barındırma (bkz. ../ONIZLEME-RENDER.md). Kip açıkken:
 *   - isteğe bağlı HTTP Basic Auth: yalnızca ONIZLEME_SIFRE tanımlı ve boş değilse etkin (kullanıcı adı
 *     ONIZLEME_KULLANICI, boşsa "onizleme"). Şifre tanımlı değilse site herkese açıktır (yine noindex).
 *   - her yanıtta X-Robots-Tag: noindex, nofollow
 *   - e-posta gönderilmez (TEKAYPER_MAIL=log, kayıt storage/eposta-log.txt)
 *   - _dev/ engellenir (tools/qa/router.php içindeki QA_YAYIN davranışı)
 *   - sayfa altına "Önizleme sürümü" bandı basılır (partials/onizleme.php, son.php çağırır)
 * Statik dosyalar (css, js, yazı tipi, görsel, PDF) PHP'ye uğramaz; şifre tanımlıysa onlar için aynı bilgilerle
 * Apache tarafında Basic Auth docker/giris.sh + docker/onizleme-sifre.conf ile açılır.
 */

function onizleme(): bool
{
    return getenv('TEKAYPER_ONIZLEME') === '1';
}

/** bootstrap.php'nin en başında çağrılır; kip kapalıysa hemen döner. */
function onizleme_basla(): void
{
    if (!onizleme()) {
        return;
    }
    header('X-Robots-Tag: noindex, nofollow');
    putenv('TEKAYPER_MAIL=log');

    $yol = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
    if (preg_match('#^/_dev(/|$)#', $yol)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit("403\n");
    }

    $sifre = (string) getenv('ONIZLEME_SIFRE');
    if ($sifre === '') {
        return; // şifre yok: herkese açık önizleme
    }
    $kullanici = (string) getenv('ONIZLEME_KULLANICI');
    if ($kullanici === '') {
        $kullanici = 'onizleme';
    }
    $gelenK = (string) ($_SERVER['PHP_AUTH_USER'] ?? '');
    $gelenS = (string) ($_SERVER['PHP_AUTH_PW'] ?? '');
    if (!hash_equals($kullanici, $gelenK) || !hash_equals($sifre, $gelenS)) {
        http_response_code(401);
        header('WWW-Authenticate: Basic realm="Tekayper onizleme", charset="UTF-8"');
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        exit("401 Giris gerekli\n");
    }
    header('Cache-Control: private, no-store');
}
