<?php
/**
 * Bu dosyayı config.local.php adıyla kopyalayıp doldurun. config.local.php depoya ve yükleme paketine girmez.
 * Burada verilen anahtarlar config.php değerlerinin üzerine yazılır.
 */
return [
    // Uzun ve rastgele bir metin (en az 32 karakter). Boş kalırsa storage/anahtar.txt otomatik üretilir.
    'secret' => '',

    'mail' => [
        'to'   => 'satis@tekayper.com',
        'from' => 'web@tekayper.com',
        // SMTP: host boşsa PHP mail() kullanılır.
        'smtp' => [
            'host'   => '',
            'port'   => 587,
            'secure' => 'tls',   // 'tls' (587), 'ssl' (465) ya da ''
            'user'   => '',
            'pass'   => '',
        ],
    ],

    // Kanonik adres farklıysa (www olmadan):
    // 'url' => 'https://tekayper.com',
];
