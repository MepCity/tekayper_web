<?php
/**
 * Teklif formu: alan tanımları, doğrulama, durumsuz anahtar, talep numarası ve hücre (antet hücresi) çıktısı.
 * teklif-gonder.php ve iletisim.php aynı tanımları kullanır.
 */

/** Durumsuz form anahtarı: zaman damgası + HMAC. Oturum ve çerez gerektirmez. */
function form_token(): string
{
    $t = (string) time();
    return $t . '.' . hash_hmac('sha256', 'teklif|' . $t, gizli_anahtar());
}

/** Anahtar geçerli mi: imza doğru, en az min_sure saniye, en fazla max_sure saniye önce üretilmiş. */
function form_token_valid(string $token, ?int $minAge = null, ?int $maxAge = null): bool
{
    $minAge = $minAge ?? (int) config('form.min_sure', 3);
    $maxAge = $maxAge ?? (int) config('form.max_sure', 7200);
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2 || !ctype_digit($parts[0])) {
        return false;
    }
    $expected = hash_hmac('sha256', 'teklif|' . $parts[0], gizli_anahtar());
    if (!hash_equals($expected, $parts[1])) {
        return false;
    }
    $age = time() - (int) $parts[0];
    return $age >= $minAge && $age <= $maxAge;
}

/**
 * Form alanları (adlar önceki sürümle aynı). sira = ekrandaki sıra.
 *  etiket   hücre etiketi (Osifont, antet hücresi gibi büyük harfle gösterilir)
 *  ad_uzun  erişilebilir ad ve hata cümlesindeki ad
 */
function form_alanlari(): array
{
    return [
        'adsoyad' => ['etiket' => 'Ad soyad', 'zorunlu' => true, 'tip' => 'text', 'sinir' => 120, 'autocomplete' => 'name'],
        'firma'   => ['etiket' => 'Firma', 'zorunlu' => false, 'tip' => 'text', 'sinir' => 160, 'autocomplete' => 'organization'],
        'telefon' => ['etiket' => 'Telefon', 'zorunlu' => true, 'tip' => 'tel', 'sinir' => 40, 'autocomplete' => 'tel', 'inputmode' => 'tel'],
        'eposta'  => ['etiket' => 'E-posta', 'zorunlu' => true, 'tip' => 'email', 'sinir' => 160, 'autocomplete' => 'email'],
        'konu'    => ['etiket' => 'Konu', 'zorunlu' => true, 'tip' => 'text', 'sinir' => 200],
        'mesaj'   => ['etiket' => 'Talebiniz (ürün, marka, adet)', 'zorunlu' => false, 'tip' => 'textarea', 'sinir' => 4000],
        'kvkk'    => ['etiket' => 'Kişisel verilerimin talebimin yanıtlanması amacıyla işlenmesine ilişkin aydınlatma metnini okudum.',
                      'zorunlu' => true, 'tip' => 'checkbox'],
    ];
}

/** Hata cümleleri (önceki sürümle aynı, KVKK ve telefon eklendi) */
function form_hata_metinleri(): array
{
    return [
        'adsoyad' => 'Adınızı ve soyadınızı yazın.',
        'eposta'  => 'Geçerli bir e-posta adresi yazın.',
        'telefon' => 'Size ulaşabileceğimiz bir telefon numarası yazın (en az 10 rakam).',
        'konu'    => 'Talebinizin konusunu yazın.',
        'kvkk'    => 'Devam etmek için aydınlatma metnini onaylayın.',
    ];
}

/** Gönderilen değeri temizler ve kısaltır */
function form_al(array $kaynak, string $ad, int $sinir): string
{
    $v = isset($kaynak[$ad]) && is_string($kaynak[$ad]) ? trim($kaynak[$ad]) : '';
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr($v, 0, $sinir);
}

/**
 * Doğrulama. Dönüş: [veri, hatalar]. hatalar boşsa form geçerli.
 * veri: adsoyad, firma, telefon, eposta, konu, mesaj, kaynak, hesap, kvkk (bool)
 */
function form_dogrula(array $girdi): array
{
    $veri = [];
    foreach (form_alanlari() as $ad => $t) {
        if ($t['tip'] === 'checkbox') {
            continue;
        }
        $veri[$ad] = form_al($girdi, $ad, $t['sinir']);
    }
    $veri['kaynak'] = form_al($girdi, 'kaynak', 120);
    $veri['hesap'] = form_al($girdi, 'hesap', 600);
    $veri['kvkk'] = isset($girdi['kvkk']) && in_array($girdi['kvkk'], ['1', 'on', 'evet'], true);

    $m = form_hata_metinleri();
    $hatalar = [];
    if (mb_strlen($veri['adsoyad']) < 3) $hatalar['adsoyad'] = $m['adsoyad'];
    if (strlen((string) preg_replace('/\D/', '', $veri['telefon'])) < 10) $hatalar['telefon'] = $m['telefon'];
    if (!filter_var($veri['eposta'], FILTER_VALIDATE_EMAIL)) $hatalar['eposta'] = $m['eposta'];
    if (mb_strlen($veri['konu']) < 3) $hatalar['konu'] = $m['konu'];
    if (!$veri['kvkk']) $hatalar['kvkk'] = $m['kvkk'];
    return [$veri, $hatalar];
}

/**
 * JS kapalıyken teklif-gonder.php hata durumunda iletisim.php'yi bu durumla yeniden çizer.
 * Dönüş: ['degerler' => [...], 'hatalar' => [...], 'genel' => ?string, 'talep' => ?string] ya da boş durum.
 */
function form_durum(): array
{
    return $GLOBALS['form_durum'] ?? ['degerler' => [], 'hatalar' => [], 'genel' => null, 'talep' => null];
}

/**
 * Antet hücresi biçiminde form alanı. iletisim.php formu bu fonksiyonla kurar.
 *   form_hucre('adsoyad')                             durumdan değer ve hata okur
 *   form_hucre('konu', ['deger' => 'Pano soğutma'])   ön doldurma
 *   form_hucre('mesaj', ['satir' => 5, 'sinif' => 'hucre--genis'])
 * $opts: deger, hata, sinif, satir (textarea), yardim (alan altı açıklama), kilitli (salt okunur metin)
 */
function form_hucre(string $ad, array $opts = []): string
{
    $tanim = form_alanlari()[$ad] ?? null;
    if ($tanim === null) {
        return '';
    }
    $durum = form_durum();
    $deger = (string) ($opts['deger'] ?? $durum['degerler'][$ad] ?? '');
    $hata = $opts['hata'] ?? $durum['hatalar'][$ad] ?? null;
    $id = 'alan-' . $ad;
    $desc = [];
    $sinif = 'hucre hucre--' . $tanim['tip'] . ($hata ? ' hucre--hata' : '') . (isset($opts['sinif']) ? ' ' . $opts['sinif'] : '');

    $yardimHtml = '';
    if (!empty($opts['yardim'])) {
        $desc[] = $id . '-yardim';
        $yardimHtml = '<p class="hucre__yardim" id="' . e($id) . '-yardim">' . e($opts['yardim']) . '</p>';
    }
    $hataHtml = '<p class="hucre__hata" id="' . e($id) . '-hata"' . ($hata ? '' : ' hidden') . '>' . e((string) $hata) . '</p>';
    $desc[] = $id . '-hata';
    $ortak = ' id="' . e($id) . '" name="' . e($ad) . '"'
           . ($tanim['zorunlu'] ? ' required aria-required="true"' : '')
           . ($hata ? ' aria-invalid="true"' : '')
           . ' aria-describedby="' . e(implode(' ', $desc)) . '"'
           . (!empty($opts['kilitli']) ? ' readonly' : '');

    if ($tanim['tip'] === 'checkbox') {
        $kvkkLink = '<a href="' . e(url('kvkk.php')) . '">aydınlatma metnini</a>';
        $etiket = str_replace('aydınlatma metnini', $kvkkLink, e($tanim['etiket']));
        $isaretli = ($deger === '1' || !empty($opts['isaretli'])) ? ' checked' : '';
        return '<div class="' . e($sinif) . '" data-alan="' . e($ad) . '">'
             . '<input type="checkbox" value="1"' . $ortak . $isaretli . '>'
             . '<label class="hucre__onay" for="' . e($id) . '">' . $etiket . '</label>'
             . '<span class="hucre__revizyon" aria-hidden="true"></span>'
             . $hataHtml . '</div>';
    }

    $etiketHtml = '<label class="hucre__etiket" for="' . e($id) . '">' . e($tanim['etiket'])
                . ($tanim['zorunlu'] ? '<span class="hucre__zorunlu" aria-hidden="true"> *</span>' : '') . '</label>';
    if ($tanim['tip'] === 'textarea') {
        $alan = '<textarea class="hucre__deger"' . $ortak . ' rows="' . (int) ($opts['satir'] ?? 4) . '" maxlength="' . (int) $tanim['sinir'] . '">'
              . e($deger) . '</textarea>';
    } else {
        $alan = '<input class="hucre__deger" type="' . e($tanim['tip']) . '"' . $ortak . ' value="' . e($deger) . '"'
              . ' maxlength="' . (int) $tanim['sinir'] . '"'
              . (isset($tanim['autocomplete']) ? ' autocomplete="' . e($tanim['autocomplete']) . '"' : '')
              . (isset($tanim['inputmode']) ? ' inputmode="' . e($tanim['inputmode']) . '"' : '')
              . ($ad === 'telefon' ? ' minlength="10"' : '')
              . ($ad === 'adsoyad' || $ad === 'konu' ? ' minlength="3"' : '')
              . '>';
    }
    return '<div class="' . e($sinif) . '" data-alan="' . e($ad) . '">' . $etiketHtml . $alan
         . '<span class="hucre__revizyon" aria-hidden="true"></span>' . $yardimHtml . $hataHtml . '</div>';
}

/** Formun gizli alanları: anahtar, bal tuzağı (web), kaynak sayfa, hesap */
function form_gizli_alanlar(string $kaynak = '', string $hesap = ''): string
{
    $h = '<input type="hidden" name="anahtar" value="' . e(form_token()) . '">'
       . '<input type="hidden" name="kaynak" value="' . e($kaynak) . '">';
    if ($hesap !== '') {
        $h .= '<input type="hidden" name="hesap" value="' . e($hesap) . '">';
    }
    $h .= '<div class="bal" aria-hidden="true"><label for="alan-web">Web sitesi</label>'
        . '<input type="text" id="alan-web" name="web" tabindex="-1" autocomplete="off" value=""></div>';
    return $h;
}

/* ---------------------------------------------------------------- talep kaydı */

function talep_dizin(): string
{
    $d = ROOT . '/storage/talepler';
    if (!is_dir($d)) {
        @mkdir($d, 0755, true);
    }
    return $d;
}

/**
 * Sıradaki talep numarası (yalnızca bakmak için; kilitsiz). Kayıt için talep_kaydet() kullanın.
 * Biçim: T-YYYYMMDD-NN, NN = o günün kayıt sayısı + 1.
 */
function talep_no(?int $zaman = null): string
{
    $zaman = $zaman ?? time();
    $dosya = talep_dizin() . '/' . date('Y-m', $zaman) . '.jsonl';
    $n = is_file($dosya) ? talep_gun_sayisi((string) file_get_contents($dosya), $zaman) : 0;
    return sprintf('T-%s-%02d', date('Ymd', $zaman), $n + 1);
}

function talep_gun_sayisi(string $icerik, int $zaman): int
{
    return substr_count($icerik, '"talep":"T-' . date('Ymd', $zaman) . '-');
}

/**
 * Talebi kilit altında numaralandırıp aylık jsonl dosyasına yazar.
 * Dönüş: ['no' => 'T-20261024-07', 'yazildi' => bool]
 */
function talep_kaydet(array $kayit): array
{
    $zaman = time();
    $dosya = talep_dizin() . '/' . date('Y-m', $zaman) . '.jsonl';
    $fp = @fopen($dosya, 'c+');
    if (!$fp) {
        return ['no' => sprintf('T-%s-%s', date('Ymd', $zaman), strtoupper(bin2hex(random_bytes(2)))), 'yazildi' => false];
    }
    flock($fp, LOCK_EX);
    $icerik = stream_get_contents($fp);
    $no = sprintf('T-%s-%02d', date('Ymd', $zaman), talep_gun_sayisi((string) $icerik, $zaman) + 1);
    $satir = json_encode(['talep' => $no] + $kayit + ['zaman' => date('c', $zaman)], JSON_UNESCAPED_UNICODE) . "\n";
    fseek($fp, 0, SEEK_END);
    $ok = fwrite($fp, $satir) === strlen($satir);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return ['no' => $no, 'yazildi' => $ok];
}

/** E-posta sonucunu ayrı durum dosyasına yazar (numaralandırma dosyasını bozmamak için) */
function talep_durum_yaz(string $no, string $durum): void
{
    $satir = json_encode(['talep' => $no, 'eposta' => $durum, 'zaman' => date('c')], JSON_UNESCAPED_UNICODE) . "\n";
    @file_put_contents(talep_dizin() . '/' . date('Y-m') . '-durum.jsonl', $satir, FILE_APPEND | LOCK_EX);
}

/**
 * URL'deki talep numarası gerçekten kayıtlı mı (kaşe yalnızca o zaman basılır).
 * Dönüş: ['no' => ..., 'tarih' => 'gg.aa.yyyy'] ya da null. Kişisel veri döndürmez.
 */
function talep_dogrula(string $no): ?array
{
    if (!preg_match('/^T-(\d{4})(\d{2})(\d{2})-[0-9A-F]{2,4}$/', $no, $m)) {
        return null;
    }
    $dosya = talep_dizin() . '/' . $m[1] . '-' . $m[2] . '.jsonl';
    if (!is_file($dosya) || strpos((string) file_get_contents($dosya), '"talep":"' . $no . '"') === false) {
        return null;
    }
    return ['no' => $no, 'tarih' => $m[3] . '.' . $m[2] . '.' . $m[1]];
}

/* ---------------------------------------------------------------- hız sınırı */

/** Bu IP için pencere dolduysa true. $kaydet true ise bu isteği sayar. */
function hiz_siniri_asildi(bool $kaydet = true): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $dizin = ROOT . '/storage/hiz';
    if (!is_dir($dizin)) {
        @mkdir($dizin, 0755, true);
    }
    $dosya = $dizin . '/' . hash('sha256', $ip . '|' . gizli_anahtar()) . '.json';
    $pencere = (int) config('form.hiz_pencere', 600);
    $adet = (int) config('form.hiz_adet', 5);
    $simdi = time();
    $fp = @fopen($dosya, 'c+');
    if (!$fp) {
        return false;
    }
    flock($fp, LOCK_EX);
    $kayitlar = json_decode((string) stream_get_contents($fp), true) ?: [];
    $kayitlar = array_values(array_filter($kayitlar, fn ($t) => is_int($t) && $t > $simdi - $pencere));
    $asildi = count($kayitlar) >= $adet;
    if (!$asildi && $kaydet) {
        $kayitlar[] = $simdi;
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($kayitlar));
    flock($fp, LOCK_UN);
    fclose($fp);
    return $asildi;
}
