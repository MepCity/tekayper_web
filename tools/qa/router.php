<?php
/**
 * php -S yönlendiricisi (spec 6.1): .htaccess'teki ErrorDocument 404 ve erişim engellerini taklit eder.
 *
 *   cd site && TEKAYPER_MAIL=log php -S 127.0.0.1:8096 -t . ../tools/qa/router.php
 *
 * QA_YAYIN=1 ile _dev/ de engellenir (yayın paketinin davranışı).
 */
$uri = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$kok = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/');

$engelli = '#^/(app|storage|tools)(/|$)#';
if (preg_match($engelli, $uri) || preg_match('#/\.(?!well-known)#', $uri)
    || (getenv('QA_YAYIN') && preg_match('#^/_dev(/|$)#', $uri))) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403\n";
    return true;
}

$yol = $kok . $uri;
if ($uri === '/' || is_file($yol) || (is_dir($yol) && is_file(rtrim($yol, '/') . '/index.php'))) {
    return false; // php -S dosyayı kendisi sunar (PHP ise çalıştırır)
}

http_response_code(404);
if (is_file($kok . '/404.php')) {
    chdir($kok);
    require $kok . '/404.php';
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo "404\n";
}
return true;
