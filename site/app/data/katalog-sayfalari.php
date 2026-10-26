<?php
/**
 * 1.pdf (Tekayper ürün kataloğu, Haziran 2021) marka sayfa dizini. Paket P7.
 *
 * Doğrulama (24.10.2026): PDF'in 17 sayfasının her biri poppler (pdftoppm) ile çizdirildi ve tek tek bakıldı.
 * 1.pdf yapısı: s. 1 kapak (A4), s. 2-16 iki yüzlü yayılım (A3 yatay = iki A4 yan yana), s. 17 arka kapak (A4).
 * Bu yüzden bir PDF sayfasında iki marka olabilir: 'yan' = sol / sag / tam (yayılımın tamamı).
 * Bağlantılar 1.pdf#page=N biçimindedir; N her zaman PDF sayfa sırasıdır (yayılım tek sayfa sayılır).
 *
 * Alanlar:
 *   ad        katalogda göründüğü yazımla marka (ChillDIN ile metin olarak basılır, logo kullanılmaz)
 *   slug      markalar.php'deki karşılığı; null = ürün listesinde yok (bağlantı verilmez)
 *   sayfa     PDF sayfa numarası (#page=N)
 *   yan       sol | sag | tam
 *   gorsel    assets/img/katalog altındaki görsel adı (yarım sayfa, gerçek PDF'ten çizdirildi, 835 px)
 *   kesin     false = sayfada marka logosu yok ya da kimlik teyit istiyor (aşağıdaki 'not')
 *   not       doğrulama notu (sayfada gösterilmez)
 *   bag       false = ürün listesine (/4) çapraz referans verilmez
 *   ek_dizin  aynı marka iki kez geçtiğinde dizinde adın yanına yazılan kısa ayırt edici
 *   capraz    ilgili föy (/5 gibi)
 *
 * Sayfada marka sayfası olmayanlar: s. 1 kapak, s. 2 "Güçlü Çözüm Ortağınız" metni + referans logoları ızgarası,
 * s. 16 boş not sayfası, s. 17 arka kapak (adres ve telefon).
 */
return [
    'dosya' => '1.pdf',
    'sayfalar' => [
        ['ad' => 'Teknokol', 'slug' => null, 'sayfa' => 3, 'yan' => 'sol', 'gorsel' => 'katalog/tekayper-s03-sol', 'kesin' => true, 'bag' => false,
         'konu' => 'Taşıyıcı kol ve kontrol paneli sistemleri',
         'not' => 'Logo "TEKNOKOL Support Arm and Control Panel Systems". Ürün listesinde yok; ürünler artık armendus.com\'da.'],
        ['ad' => 'Pano klima', 'slug' => null, 'sayfa' => 3, 'yan' => 'sag', 'gorsel' => 'katalog/tekayper-s03-sag', 'kesin' => true, 'bag' => false,
         'konu' => 'TPK pano klimaları', 'capraz' => '/5',
         'not' => '"Pano Klima ile İşiniz Güvence Altında", Tekayper logolu. TPK sayfasıdır; /5 föyüne bağlanır.'],
        ['ad' => 'LS IS', 'slug' => 'ls-electric', 'sayfa' => 4, 'yan' => 'sol', 'gorsel' => 'katalog/tekayper-s04-sol', 'kesin' => true,
         'konu' => 'Sürücü, PLC, HMI, servo', 'ek_dizin' => 'sürücü, PLC', 'not' => 'LS IS logosu; sürücü, HMI, servo. Bugünkü adı LS Electric.'],
        ['ad' => 'Datalogic', 'slug' => 'datalogic', 'sayfa' => 4, 'yan' => 'sag', 'gorsel' => 'katalog/tekayper-s04-sag', 'kesin' => true,
         'konu' => 'Sensör, barkod okuyucu, güvenlik', 'not' => 'Datalogic logosu ve metni.'],
        ['ad' => 'Wieland', 'slug' => 'wieland', 'sayfa' => 5, 'yan' => 'tam', 'gorsel' => 'katalog/tekayper-s05-sol', 'kesin' => true,
         'konu' => 'Klemens, konnektör, güvenlik rölesi', 'not' => 'Yayılımın tamamı Wieland (tek görsel); görsel sol yarıdır, logo oradadır.'],
        ['ad' => 'Schneider Electric', 'slug' => 'schneider', 'sayfa' => 6, 'yan' => 'sol', 'gorsel' => 'katalog/tekayper-s06-sol', 'kesin' => true,
         'konu' => 'Şalter, kaçak akım, buton', 'not' => '"Life Is On Schneider Electric" logosu.'],
        ['ad' => 'Finder', 'slug' => 'finder', 'sayfa' => 6, 'yan' => 'sag', 'gorsel' => 'katalog/tekayper-s06-sag', 'kesin' => true,
         'konu' => 'Röle, güç kaynağı, zaman rölesi', 'not' => 'Finder logosu.'],
        ['ad' => 'Birtaş', 'slug' => 'birtas', 'sayfa' => 7, 'yan' => 'sol', 'gorsel' => 'katalog/tekayper-s07-sol', 'kesin' => true,
         'konu' => 'Kablo', 'not' => '"Birtaş Kablo" logosu, kablo türleri tablosu.'],
        ['ad' => 'Emas', 'slug' => 'emas', 'sayfa' => 7, 'yan' => 'sag', 'gorsel' => 'katalog/tekayper-s07-sag', 'kesin' => true,
         'konu' => 'Buton, sinyal lambası, sınır şalteri', 'not' => 'Emas logosu ve metni.'],
        ['ad' => 'Pulse', 'slug' => 'pulse', 'sayfa' => 8, 'yan' => 'sol', 'gorsel' => 'katalog/tekayper-s08-sol', 'kesin' => true,
         'konu' => 'Tartım, statik çubuk, termokupl', 'not' => '"Pulse Electronic" logosu ve metni.'],
        ['ad' => 'Siemens', 'slug' => 'siemens', 'sayfa' => 8, 'yan' => 'sag', 'gorsel' => 'katalog/tekayper-s08-sag', 'kesin' => true,
         'konu' => 'Otomasyon ürünleri', 'not' => '"Siemens Otomasyon Ürünleri".'],
        ['ad' => 'Klemsan', 'slug' => 'klemsan', 'sayfa' => 9, 'yan' => 'sol', 'gorsel' => 'katalog/tekayper-s09-sol', 'kesin' => true,
         'konu' => 'Klemens, kablo yönetimi, markalama', 'not' => 'Klemsan logosu ve metni.'],
        ['ad' => 'Anipa', 'slug' => 'anipa', 'sayfa' => 9, 'yan' => 'sag', 'gorsel' => 'katalog/tekayper-s09-sag', 'kesin' => true,
         'konu' => 'Kablo kanalı, pano, bara', 'not' => 'Anipa logosu ve ürün listesi metni.'],
        ['ad' => 'ISISO', 'slug' => 'isiso', 'sayfa' => 10, 'yan' => 'sol', 'gorsel' => 'katalog/tekayper-s10-sol', 'kesin' => true,
         'konu' => 'Hız kontrol, güç kaynağı, SSR', 'not' => 'ISISO logosu ve metni.'],
        ['ad' => 'Tempa Pano', 'slug' => null, 'sayfa' => 10, 'yan' => 'sag', 'gorsel' => 'katalog/tekayper-s10-sag', 'kesin' => true, 'bag' => false,
         'konu' => 'Dikili tip, ex-proof, paslanmaz panolar',
         'not' => 'Tempa Pano logosu. Ürün listesinden çıkarılmış (GMT geldi); hâlâ satılıyor mu açık soru. Ürün listesine bağlanmaz.'],
        ['ad' => 'Loobar', 'slug' => 'loobar', 'sayfa' => 11, 'yan' => 'sol', 'gorsel' => 'katalog/tekayper-s11-sol', 'kesin' => true,
         'konu' => 'Elektrik dağıtım ve ek çözümleri', 'not' => '"Loobar Elektrik Dağıtım ve Ek Çözümleri" logosu.'],
        ['ad' => 'Turkuaz', 'slug' => 'turkuaz', 'sayfa' => 11, 'yan' => 'sag', 'gorsel' => 'katalog/tekayper-s11-sag', 'kesin' => true,
         'konu' => 'Spiral boru, rakor, kablo kanalı', 'not' => '"Turkuaz Elektroteknik" logosu.'],
        ['ad' => 'Alfa Elektronik Sensör', 'slug' => 'alfa-sensor', 'sayfa' => 12, 'yan' => 'sol', 'gorsel' => 'katalog/tekayper-s12-sol', 'kesin' => false,
         'konu' => 'Cetvel, basınç sensörü, enkoder',
         'not' => 'Sayfada "ALFA ELEKTRONİK SENSÖR A.Ş." yazıyor; ürün listesindeki "Alfa Sensör" ile aynı firma olduğu teyit edilmeli (açık soru).'],
        ['ad' => 'Entes', 'slug' => 'entes', 'sayfa' => 12, 'yan' => 'sag', 'gorsel' => 'katalog/tekayper-s12-sag', 'kesin' => true,
         'konu' => 'Reaktif güç rölesi, faz koruma', 'not' => 'Entes logosu.'],
        ['ad' => 'Usmetal', 'slug' => 'usmetal', 'sayfa' => 13, 'yan' => 'sol', 'gorsel' => 'katalog/tekayper-s13-sol', 'kesin' => false,
         'konu' => 'Alüminyum döküm pedal, kampana',
         'not' => 'Sayfada marka logosu yok: "Alüminyum Döküm PEDAL", US.A-1P01 ... kodları, kampana ve CETOP parçaları. Ürünler Usmetal grupları ile birebir aynı ve envanter bu sayfayı Usmetal sayıyor; çıkarımdır.'],
        ['ad' => 'Onka', 'slug' => 'onka', 'sayfa' => 13, 'yan' => 'sag', 'gorsel' => 'katalog/tekayper-s13-sag', 'kesin' => true,
         'konu' => 'Klemens', 'not' => 'Onka logosu.'],
        ['ad' => 'LS IS', 'slug' => 'ls-electric', 'sayfa' => 14, 'yan' => 'sol', 'gorsel' => 'katalog/tekayper-s14-sol', 'kesin' => true,
         'konu' => 'Şalt ürünleri: kontaktör, şalter', 'ek_dizin' => 'şalt', 'not' => 'İkinci LS IS sayfası, "Şalt grubu ürün çeşitliliği".'],
        ['ad' => 'Emko', 'slug' => 'emko', 'sayfa' => 14, 'yan' => 'sag', 'gorsel' => 'katalog/tekayper-s14-sag', 'kesin' => true,
         'konu' => 'Sıcaklık ve proses kontrol, jeneratör kontrol', 'not' => 'Emko logosu.'],
        ['ad' => 'Kjellberg', 'slug' => 'kjellberg', 'sayfa' => 15, 'yan' => 'sol', 'gorsel' => 'katalog/tekayper-s15-sol', 'kesin' => true,
         'konu' => 'Plazma kesim', 'not' => '"Kjellberg Finsterwalde" logosu.'],
        ['ad' => 'IHT', 'slug' => 'iht', 'sayfa' => 15, 'yan' => 'sag', 'gorsel' => 'katalog/tekayper-s15-sag', 'kesin' => true,
         'konu' => 'Kesim torcu ve yükseklik kontrolü', 'not' => '"iht automation" logosu.'],
    ],
];
