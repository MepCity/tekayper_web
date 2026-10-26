<?php
/**
 * Ortak yardımcılar. Her sayfanın ilk satırı: require __DIR__ . '/app/bootstrap.php';
 * Birden çok kez yüklenmesi güvenlidir (teklif-gonder.php JS kapalıyken iletisim.php'yi içeriden çizer).
 * Fonksiyonlar app/lib/yardimci.php içindedir; adlar spec 4.3 ile aynıdır.
 */
declare(strict_types=1);

// Önizleme kipi (yalnızca TEKAYPER_ONIZLEME=1): Basic Auth, noindex, e-posta günlüğü. Canlıda hiçbir şey yapmaz.
require_once __DIR__ . '/lib/onizleme.php';
onizleme_basla();

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
    define('APP', __DIR__);

    mb_internal_encoding('UTF-8');
    date_default_timezone_set('Europe/Istanbul');

    $GLOBALS['config'] = require APP . '/config.php';
    if (is_file(APP . '/config.local.php')) {
        $GLOBALS['config'] = array_replace_recursive($GLOBALS['config'], (array) require APP . '/config.local.php');
    }
}

require_once APP . '/lib/yardimci.php';
require_once APP . '/lib/form.php';
require_once APP . '/lib/gorsel.php';
require_once APP . '/lib/seo.php';
