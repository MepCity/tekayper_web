<?php
/**
 * Genel site ayarları (depoya girer, gizli bilgi içermez).
 * Gizli değerler (secret, SMTP) app/config.local.php dosyasındadır; şablon: config.local.ornek.php.
 */
return [
    'name'  => 'Tekayper',
    'legal' => 'Tekayper Elektromarket San. Tic. A.Ş.',

    // Kanonik adres. PDF'lerde www yazdığı için www varsayıldı (açık soru: Search Console ile teyit).
    'url'   => 'https://www.tekayper.com',

    'phone' => ['label' => '0224 441 70 88', 'tel' => '+902244417088', 'schema' => '+90 224 441 70 88'],
    'gsm'   => ['label' => '0549 604 64 48', 'tel' => '+905496046448'],
    'email' => 'satis@tekayper.com',
    'address' => [
        'line1'    => 'Alaaddinbey Mah. 628. Sk.',
        'line2'    => 'Elektromekanik İş Merkezi No:3/A',
        'district' => 'Nilüfer',
        'city'     => 'Bursa',
        'country'  => 'TR',
    ],
    'geo'  => ['lat' => 40.208353, 'lng' => 28.9299453],
    // Koordinatın çizim katmanında yazılışı (Osifont), iki hassasiyet.
    'geo_yazi' => ['kisa' => '40,2084 K 28,9299 D', 'uzun' => '40,208353 K 28,929945 D'],
    'maps' => 'https://www.google.com/maps/place/Tekayper+Elektromarket+A.%C5%9E./@40.2083085,28.9276138,17z/data=!3m1!4b1!4m5!3m4!1s0x14ca1143ca29032b:0x8d2488a83432a1b5!8m2!3d40.208353!4d28.9299453',

    'armendus_url' => 'https://www.armendus.com/',

    // Bayilik bilgisi Tekayper yazılı teyit verene kadar hiçbir yerde gösterilmez.
    'show_dealer_info' => false,

    // "Teknokol ürünleri artık Armendus adıyla sunuluyor" cümlesi Tekayper teyit edene kadar gösterilmez.
    // false iken armendus_cumlesi() kısa cümleyi döndürür; urunler.php bu fonksiyonu kullanmalı.
    'show_armendus_note' => false,

    // Form: e-posta gönderilemese bile talep kayda geçtiyse ziyaretçiye başarı göster.
    // false (varsayılan): yalnızca e-posta gerçekten gittiyse "ok".
    'kabul_kayitla' => false,

    'form' => [
        'min_sure'   => 3,      // saniye: bundan hızlı gönderim bot sayılır
        'max_sure'   => 7200,   // saniye: anahtar 2 saat geçerli
        'hiz_adet'   => 5,      // pencere başına en fazla talep
        'hiz_pencere'=> 600,    // saniye
    ],

    'mail' => [
        'to'        => 'satis@tekayper.com',
        'from'      => 'web@tekayper.com',
        'from_name' => 'Tekayper web sitesi',
        'smtp' => ['host' => '', 'port' => 587, 'secure' => 'tls', 'user' => '', 'pass' => ''],
    ],

    'secret' => '',
];
