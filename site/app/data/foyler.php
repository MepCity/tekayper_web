<?php
/**
 * Föy (çizim sayfası) kaydı. Her sayfa projenin bir föyüdür.
 *  no       föy numarası (antet, başlık etiketi, çapraz referans /5.C)
 *  dosya    sayfa dosyası (kök dizine göre)
 *  tanim    IEC 81346 tarzı tanım (=TKY+SGT)
 *  ad       föy adı (antet FÖY ADI, başlık etiketi)
 *  menu     menü etiketi (canlı sitedeki etiketler aynen); null = menüde yok
 *  baslik   HTML title
 *  aciklama meta description
 *  satirlar satır harfi => [bölüm id, bölüm adı]
 *  noindex  arama motorlarına kapalı
 */
return [
    'ana' => [
        'no' => 1, 'dosya' => 'index.php', 'tanim' => '=TKY+ANA', 'ad' => 'Tek hat şeması', 'menu' => 'Anasayfa',
        'baslik' => 'Tekayper | Endüstriyel otomasyon ürünleri ve pano soğutma, Bursa',
        'aciklama' => "Tekayper Elektromarket, Bursa Nilüfer'de 37 markanın endüstriyel otomasyon ve elektrik ürünlerini tedarik eder. Proje, montaj ve servis hizmeti verir.",
        'satirlar' => [
            'A' => ['giris', 'Giriş'], 'B' => ['besleme', 'Besleme'], 'C' => ['kumanda', 'Kumanda'],
            'D' => ['surucu', 'Sürücü'], 'E' => ['motor', 'Motor'], 'F' => ['notlar', 'Notlar'],
        ],
    ],
    'kurumsal' => [
        'no' => 2, 'dosya' => 'kurumsal.php', 'tanim' => '=TKY+KRM', 'ad' => 'Firma bilgisi', 'menu' => 'Kurumsal',
        'baslik' => 'Kurumsal | Tekayper Elektromarket, Bursa',
        'aciklama' => 'Tekayper Elektromarket San. Tic. A.Ş.: endüstriyel otomasyon ürünleri tedariki, mühendislik, proje, montaj ve servis. Alaaddinbey Mah., Nilüfer / Bursa.',
        'satirlar' => [
            'A' => ['unvan', 'Unvan'], 'B' => ['faaliyet', 'Faaliyet'], 'C' => ['calisma', 'Çalışma'],
            'D' => ['kalite', 'Kalite'], 'E' => ['konum', 'Konum'], 'F' => ['antet', 'Antet'],
        ],
    ],
    'urunler' => [
        'no' => 3, 'dosya' => 'urunler.php', 'tanim' => '=TKY+URN', 'ad' => 'Ürün dağıtımı', 'menu' => 'Ürünler',
        'baslik' => 'Ürünler | Tekayper',
        'aciklama' => 'Tekayper ürünleri (37 marka, 178 ürün grubu), TPK pano soğutma sistemleri ve Armendus taşıyıcı kol, pano ve kiosk sistemleri.',
        'satirlar' => [
            'A' => ['tekayper', 'Tekayper ürünleri'], 'B' => ['sogutma', 'Pano soğutma'],
            'C' => ['armendus', 'Armendus'], 'D' => ['not', 'Not'],
        ],
    ],
    'malzeme' => [
        'no' => 4, 'dosya' => 'tekayper-urunler.php', 'tanim' => '=TKY+MLZ', 'ad' => 'Malzeme listesi', 'menu' => 'Tekayper Ürünler',
        'ust' => 'urunler',
        'baslik' => 'Tekayper ürünleri | 37 marka, 178 ürün grubu',
        'aciklama' => 'Wieland, LS Electric, Datalogic, Finder, Omron, Schneider Electric, Siemens, Mean Well ve 29 marka daha: tedarik ettiğimiz ürün grupları ve hızlı arama.',
        'satirlar' => [
            'A' => ['arama', 'Arama'], 'B' => ['liste', 'Liste'], 'C' => ['talep-listesi', 'Talep listesi'],
        ],
    ],
    'sogutma' => [
        'no' => 5, 'dosya' => 'pano-sogutma-sistemleri.php', 'tanim' => '=TKY+SGT', 'ad' => 'Isı hesabı', 'menu' => 'Pano Soğutma Sistemleri',
        'ust' => 'urunler',
        'baslik' => 'Pano soğutma sistemleri | TPK pano klimaları ve ısı hesabı',
        'aciklama' => 'TPK pano klimaları, 450 W ile 3340 W arası. IEC/TR 60890 yöntemiyle pano soğutma ihtiyacını hesaplayın ve uygun modeli seçin.',
        'satirlar' => [
            'A' => ['ic-dis', 'Pano içi ve dışı'], 'B' => ['hesap', 'Hesap'],
            'C' => ['modeller', 'Modeller'], 'D' => ['teklif', 'Teklif'],
        ],
    ],
    'referans' => [
        'no' => 6, 'dosya' => 'referanslar.php', 'tanim' => '=TKY+REF', 'ad' => 'Referans listesi', 'menu' => 'Referanslar',
        'baslik' => 'Referanslar | Tekayper',
        'aciklama' => "Arçelik, Tofaş, Bosch, Aselsan, Renault, Şişecam ve 35 firma daha: Tekayper'in referans listesi.",
        'satirlar' => [
            'A' => ['bobin', 'Bobin'], 'B' => ['kontaklar', 'Kontaklar'], 'C' => ['son', 'Son'],
        ],
    ],
    'ekler' => [
        'no' => 7, 'dosya' => 'kataloglar.php', 'tanim' => '=TKY+EK', 'ad' => 'Ekler', 'menu' => 'Kataloglar',
        'zemin' => 'ozalit',
        'baslik' => 'Kataloglar | Tekayper ürün kataloğu (PDF)',
        'aciklama' => 'Tekayper ürün kataloğu (17 sayfa) ve Armendus kataloğu PDF olarak. Marka sayfalarına doğrudan gidin.',
        'satirlar' => [
            'A' => ['kapak', 'Kapak'], 'B' => ['sayfa-dizini', 'Sayfa dizini'],
            'C' => ['diger', 'Diğer kataloglar'], 'D' => ['indir', 'İndir'],
        ],
    ],
    'iletisim' => [
        'no' => 8, 'dosya' => 'iletisim.php', 'tanim' => '=TKY+ILT', 'ad' => 'Talep ve iletişim', 'menu' => 'İletişim',
        'baslik' => 'İletişim | Tekayper, Nilüfer / Bursa',
        'aciklama' => 'Tekayper Elektromarket iletişim: 0224 441 70 88, satis@tekayper.com, Alaaddinbey Mah. 628. Sk. Elektromekanik İş Merkezi No:3/A Nilüfer / Bursa. Teklif formu.',
        'satirlar' => [
            'A' => ['adres', 'Adres'], 'B' => ['talep', 'Talep'], 'C' => ['kase', 'Kaşe'],
        ],
    ],
    'kvkk' => [
        'no' => 9, 'dosya' => 'kvkk.php', 'tanim' => '=TKY+NOT', 'ad' => 'Notlar', 'menu' => null,
        'baslik' => 'KVKK aydınlatma metni | Tekayper',
        'aciklama' => 'Tekayper Elektromarket San. Tic. A.Ş. kişisel verilerin korunması aydınlatma metni.',
        'noindex' => true,
        'satirlar' => ['A' => ['metin', 'Metin']],
    ],
    'yok' => [
        'no' => 0, 'dosya' => '404.php', 'tanim' => '=TKY+YOK', 'ad' => 'Föy yok', 'menu' => null,
        'baslik' => 'Sayfa bulunamadı | Tekayper',
        'aciklama' => 'Aradığınız sayfa taşınmış ya da kaldırılmış olabilir.',
        'noindex' => true,
        'satirlar' => ['A' => ['yok', 'Föy yok']],
    ],
];
