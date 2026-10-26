<?php
/**
 * Teklif formu uç noktası (spec 4.5).
 *  - Yalnızca POST (diğerleri 405).
 *  - Accept: application/json ise JSON döner: {"ok":true,"talep":"T-20261024-07","tarih":"24.10.2026"}
 *    ya da {"ok":false,"hata":"...","alanlar":{...}}.
 *  - JS yoksa: hata durumunda iletisim.php alan hataları ve gönderilen değerlerle yeniden çizilir (yönlendirme yok,
 *    adreste kişisel veri yok); başarıda 303 ile iletisim.php?talep=T-...#kase adresine gider.
 *  - Koruma: HMAC zaman anahtarı (en az 3 sn, en fazla 2 saat), Origin/Referer bu site olmalı, bal tuzağı "web",
 *    IP başına 10 dakikada 5 talep (storage/hiz), gövde sınırı.
 *  - Her talep storage/talepler/YYYY-MM.jsonl dosyasına numarasıyla yazılır (e-posta gitmese bile).
 *  - ok:true yalnızca e-posta gittiyse ya da (e-posta gitmedi + kayıt yazıldı + kabul_kayitla açık) ise.
 */
require __DIR__ . '/app/bootstrap.php';
require APP . '/lib/eposta.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$jsonIstek = stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
$telefon = (string) config('phone.label');
$eposta = (string) config('email');

/** JSON ya da (JS yok) sayfa yanıtı */
function yanit(array $veri, int $kod = 200): void
{
    global $jsonIstek;
    http_response_code($kod);
    if ($jsonIstek) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($veri, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!empty($veri['ok'])) {
        $hedef = url('iletisim.php') . (!empty($veri['talep']) ? '?talep=' . rawurlencode($veri['talep']) . '#kase' : '#talep');
        header('Location: ' . $hedef, true, 303);
        exit;
    }
    sayfa_yeniden_ciz($veri, $kod);
}

/** JS kapalıyken iletisim.php'yi hatalar ve gönderilen değerlerle çizer */
function sayfa_yeniden_ciz(array $veri, int $kod): void
{
    $degerler = [];
    foreach (array_keys(form_alanlari()) as $ad) {
        if ($ad === 'kvkk') {
            $degerler['kvkk'] = isset($_POST['kvkk']) ? '1' : '';
            continue;
        }
        $degerler[$ad] = form_al($_POST, $ad, (int) (form_alanlari()[$ad]['sinir'] ?? 200));
    }
    $degerler['kaynak'] = form_al($_POST, 'kaynak', 120);
    $degerler['hesap'] = form_al($_POST, 'hesap', 600);
    $GLOBALS['form_durum'] = [
        'degerler' => $degerler,
        'hatalar'  => $veri['alanlar'] ?? [],
        'genel'    => $veri['hata'] ?? null,
        'talep'    => null,
        'kod'      => $kod,
    ];
    http_response_code($kod);
    header('Content-Type: text/html; charset=utf-8');
    if (is_file(ROOT . '/iletisim.php')) {
        require ROOT . '/iletisim.php';
        exit;
    }
    // iletisim.php henüz yoksa (geliştirme) en sade form sayfası
    $foy = foy('iletisim');
    partial('head', ['foy' => $foy, 'css' => ['form'], 'noindex' => true]);
    partial('ust', ['foy' => $foy]);
    echo '<main id="icerik" class="foy" data-foy="8"><section id="talep" data-satir="B" class="bant"><div class="izgara"><div>';
    echo '<h1>Teklif al</h1>';
    if (!empty($GLOBALS['form_durum']['genel'])) {
        echo '<p class="form-uyari" role="alert">' . e($GLOBALS['form_durum']['genel']) . '</p>';
    }
    echo '<form method="post" action="' . e(url('teklif-gonder.php')) . '" novalidate><div class="form-hucreler">';
    foreach (array_keys(form_alanlari()) as $ad) {
        echo form_hucre($ad);
    }
    echo '</div>' . form_gizli_alanlar($degerler['kaynak'], $degerler['hesap']) . dugme('Talebi gönder', null, ['button' => true, 'tip' => 'submit']) . '</form>';
    echo '</div></div></section></main>';
    partial('antet', ['foy' => $foy]);
    partial('son', ['foy' => $foy]);
    exit;
}

/** İstek bu siteden mi (Origin, yoksa Referer). İkisi de yoksa anahtar denetimi yeterli sayılır. */
function kaynak_gecerli(): bool
{
    $kaynak = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($kaynak === '' || $kaynak === 'null') {
        $kaynak = $_SERVER['HTTP_REFERER'] ?? '';
    }
    if ($kaynak === '') {
        return true;
    }
    $p = parse_url($kaynak);
    if (!$p || empty($p['host'])) {
        return false;
    }
    $gelen = strtolower($p['host'] . (isset($p['port']) ? ':' . $p['port'] : ''));
    $izinli = [strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''))];
    $siteHost = strtolower((string) parse_url((string) config('url'), PHP_URL_HOST));
    if ($siteHost !== '') {
        $izinli[] = $siteHost;
        $izinli[] = strpos($siteHost, 'www.') === 0 ? substr($siteHost, 4) : 'www.' . $siteHost;
    }
    return in_array($gelen, array_filter($izinli), true);
}

/* ---------------------------------------------------------------- denetimler */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    if ($jsonIstek) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'hata' => 'Bu adres yalnızca form gönderimi kabul eder.'], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Bu adres yalnızca form gönderimi kabul eder. Teklif formu: " . tam_url('iletisim.php#talep') . "\n";
    }
    exit;
}

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536) {
    yanit(['ok' => false, 'hata' => 'Gönderilen metin çok uzun. Talebinizi kısaltın ya da ' . $eposta . ' adresine yazın.'], 413);
}

if (!kaynak_gecerli()) {
    yanit(['ok' => false, 'hata' => 'Form bu siteden gönderilmedi. Sayfayı yenileyip tekrar deneyin.'], 403);
}

/* bal tuzağı dolu: sessizce kabul et, hiçbir şey gönderme ve yazma */
if (form_al($_POST, 'web', 200) !== '') {
    yanit(['ok' => true, 'talep' => null]);
}

if (!form_token_valid(form_al($_POST, 'anahtar', 200))) {
    yanit(['ok' => false, 'hata' => 'Formun süresi doldu ya da çok hızlı gönderildi. Sayfayı yenileyip tekrar gönderin.'], 400);
}

[$veri, $hatalar] = form_dogrula($_POST);
if ($hatalar) {
    yanit(['ok' => false, 'hata' => 'Eksik ya da hatalı alanları düzeltin.', 'alanlar' => $hatalar], 422);
}

if (hiz_siniri_asildi(true)) {
    yanit(['ok' => false, 'hata' => 'Kısa sürede çok fazla talep gönderildi. Birkaç dakika sonra tekrar deneyin ya da ' . $telefon . ' numarasını arayın.'], 429);
}

/* ---------------------------------------------------------------- kayıt ve e-posta */

$ipIz = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . gizli_anahtar()), 0, 16);
$kayit = [
    'adsoyad' => $veri['adsoyad'], 'firma' => $veri['firma'], 'eposta' => $veri['eposta'], 'telefon' => $veri['telefon'],
    'konu' => $veri['konu'], 'mesaj' => $veri['mesaj'], 'kaynak' => $veri['kaynak'], 'hesap' => $veri['hesap'],
    'kvkk' => true, 'ip_iz' => $ipIz,
];
$k = talep_kaydet($kayit);
$no = $k['no'];

$satirlar = [
    'Web sitesinden yeni bir teklif talebi geldi.',
    '',
    'Talep no : ' . $no,
    'Ad soyad : ' . $veri['adsoyad'],
    'Firma    : ' . ($veri['firma'] !== '' ? $veri['firma'] : '-'),
    'E-posta  : ' . $veri['eposta'],
    'Telefon  : ' . $veri['telefon'],
    'Konu     : ' . $veri['konu'],
    '',
    'Talep:',
    $veri['mesaj'] !== '' ? $veri['mesaj'] : '-',
];
if ($veri['hesap'] !== '') {
    $satirlar[] = '';
    $satirlar[] = 'Pano soğutma hesabı: ' . $veri['hesap'];
}
$satirlar = array_merge($satirlar, [
    '',
    '---',
    'Gönderildiği sayfa: ' . ($veri['kaynak'] !== '' ? $veri['kaynak'] : '-'),
    'Zaman: ' . date('d.m.Y H:i'),
    'KVKK aydınlatma metni onaylandı.',
]);

$sonuc = eposta_gonder(
    (string) config('mail.to'),
    'Teklif talebi ' . $no . ': ' . $veri['konu'],
    implode("\n", $satirlar),
    ['reply_to' => $veri['eposta']]
);
talep_durum_yaz($no, $sonuc['ok'] ? ('gonderildi' . (isset($sonuc['not']) ? ' (' . $sonuc['not'] . ')' : '')) : ('hata: ' . ($sonuc['hata'] ?? '')));

$basarili = $sonuc['ok'] || ($k['yazildi'] && config('kabul_kayitla') === true);
if (!$basarili) {
    error_log('Tekayper form e-postası gönderilemedi (' . $no . '): ' . ($sonuc['hata'] ?? ''));
    yanit(['ok' => false, 'hata' => 'Talebiniz şu an iletilemedi. ' . $telefon . ' numarasını arayın ya da ' . $eposta . ' adresine yazın.'], 502);
}

yanit(['ok' => true, 'talep' => $no, 'tarih' => date('d.m.Y')]);
