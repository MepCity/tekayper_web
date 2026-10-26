<?php
/**
 * Küçük e-posta gönderici: SMTP (STARTTLS / SSL, AUTH LOGIN) ya da PHP mail().
 * Harici kütüphane gerektirmez. Önceki sürümden aynen alındı; tek ek: log kipi.
 * Kip: TEKAYPER_MAIL ortam değişkeni (smtp | mail | log), yoksa SMTP host doluysa smtp, değilse mail().
 * log kipi (yerel geliştirme): e-posta gönderilmez, storage/eposta-log.txt dosyasına yazılır.
 */

function eposta_baslik(string $s): string
{
    return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

function eposta_temiz(string $s): string
{
    return trim(str_replace(["\r", "\n", "\0"], ' ', $s));
}

/**
 * @return array{ok: bool, hata?: string}
 */
function eposta_gonder(string $kime, string $konu, string $govde, array $secenek = []): array
{
    $cfg = config('mail');
    $kimden = eposta_temiz((string) ($cfg['from'] ?? ''));
    $kimdenAd = eposta_temiz((string) ($cfg['from_name'] ?? 'Web sitesi'));
    $yanit = isset($secenek['reply_to']) ? eposta_temiz((string) $secenek['reply_to']) : '';
    if ($yanit !== '' && !filter_var($yanit, FILTER_VALIDATE_EMAIL)) {
        $yanit = '';
    }

    $basliklar = [
        'Date: ' . date('r'),
        'From: ' . eposta_baslik($kimdenAd) . ' <' . $kimden . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'X-Mailer: Tekayper',
    ];
    if ($yanit !== '') {
        $basliklar[] = 'Reply-To: ' . $yanit;
    }
    $kodluGovde = rtrim(chunk_split(base64_encode($govde), 76, "\r\n"));
    $kodluKonu = eposta_baslik(eposta_temiz($konu));

    $mod = getenv('TEKAYPER_MAIL') ?: (($cfg['smtp']['host'] ?? '') !== '' ? 'smtp' : 'mail');
    if ($mod === 'log') {
        $kayit = "==== " . date('c') . "\nTo: " . $kime . "\n" . implode("\n", $basliklar) . "\nSubject: " . eposta_temiz($konu)
               . "\n\n" . $govde . "\n\n";
        $ok = @file_put_contents(ROOT . '/storage/eposta-log.txt', $kayit, FILE_APPEND | LOCK_EX) !== false;
        return $ok ? ['ok' => true, 'not' => 'log'] : ['ok' => false, 'hata' => 'log dosyası yazılamadı'];
    }
    if ($mod === 'smtp') {
        return smtp_gonder($cfg['smtp'], $kimden, $kime, $kodluKonu, $basliklar, $kodluGovde);
    }

    $ek = $kimden !== '' ? '-f' . $kimden : '';
    $ok = @mail($kime, $kodluKonu, $kodluGovde, implode("\r\n", $basliklar), $ek);
    return $ok ? ['ok' => true] : ['ok' => false, 'hata' => 'mail() başarısız'];
}

function smtp_gonder(array $s, string $kimden, string $kime, string $konu, array $basliklar, string $govde): array
{
    $host = (string) $s['host'];
    $port = (int) ($s['port'] ?? 587);
    $guvenlik = (string) ($s['secure'] ?? 'tls');
    $hedef = ($guvenlik === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $fp = @stream_socket_client($hedef, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return ['ok' => false, 'hata' => "Bağlantı kurulamadı: $errstr ($errno)"];
    }
    stream_set_timeout($fp, 15);

    $oku = function () use ($fp) {
        $yanit = '';
        while (($satir = fgets($fp, 515)) !== false) {
            $yanit .= $satir;
            if (strlen($satir) < 4 || $satir[3] === ' ') {
                break;
            }
        }
        return $yanit;
    };
    $komut = function (string $c, array $beklenen) use ($fp, $oku) {
        if ($c !== '') {
            fwrite($fp, $c . "\r\n");
        }
        $y = $oku();
        $kod = (int) substr($y, 0, 3);
        if (!in_array($kod, $beklenen, true)) {
            throw new RuntimeException(trim($y) ?: 'yanıt yok');
        }
        return $y;
    };

    try {
        $komut('', [220]);
        $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
        $komut($ehlo, [250]);
        if ($guvenlik === 'tls') {
            $komut('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('TLS başlatılamadı');
            }
            $komut($ehlo, [250]);
        }
        if (($s['user'] ?? '') !== '') {
            $komut('AUTH LOGIN', [334]);
            $komut(base64_encode((string) $s['user']), [334]);
            $komut(base64_encode((string) $s['pass']), [235]);
        }
        $komut('MAIL FROM:<' . $kimden . '>', [250]);
        $komut('RCPT TO:<' . $kime . '>', [250, 251]);
        $komut('DATA', [354]);
        $mesaj = implode("\r\n", array_merge($basliklar, ['To: <' . $kime . '>', 'Subject: ' . $konu])) . "\r\n\r\n" . $govde;
        $mesaj = preg_replace('/^\./m', '..', $mesaj);
        $komut($mesaj . "\r\n.", [250]);
        $komut('QUIT', [221]);
        fclose($fp);
        return ['ok' => true];
    } catch (Throwable $e) {
        @fwrite($fp, "QUIT\r\n");
        @fclose($fp);
        return ['ok' => false, 'hata' => 'SMTP: ' . $e->getMessage()];
    }
}
