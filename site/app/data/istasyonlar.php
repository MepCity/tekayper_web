<?php
/**
 * Ana sayfa tek hat şeması istasyonları (spec 3.1), besleme -> motor sırasıyla.
 *  satir     föy satırı (ana sayfa bölüm harfi)
 *  etiket    cihaz etiketi(leri), IEC 81346 ürün öneki
 *  sembol    IEC 60617 sembol kimliği(leri) (assets/sembol/iec-{sembol}.svg)
 *  baslik    istasyon başlığı
 *  cumle     tek cümle açıklama
 *  markalar  marka satırları. Her satır markalar.php'den kategoriye göre üretilir:
 *              kategori  kategori kimliği ya da listesi
 *              filtre    grup adında kelime başından aranan katlanmış ifadeler (boşsa kategorideki tüm gruplar)
 *              slug      açık marka listesi (veriyle ayırt edilemeyen durumlarda; kategori yine kontrol edilir)
 *              etiket    satır öneki (ör. "enkoder"), yoksa null
 *            Çözümleme: istasyon_markalari($istasyon). Beklenen çıktı spec 3.1 tablosudur.
 *  metin     marka yerine gösterilecek metin (ör. TPK serisi)
 *  capraz    çapraz referans ve bağlantı metni; liste_kategori malzeme listesinde ön seçili kategori
 *  foto      resim() adı (yoksa istasyon yalnızca sembolle görünür). 'urun/u01' gibi kimlikler resim() içinde
 *            'urun/u01-kompakt-salter' dosya adına çözülür (gorsel.php resim_adi); mevcut görsel için tam ad yazıldı.
 */
return [
    ['satir' => 'B', 'etiket' => [], 'sembol' => ['besleme'], 'baslik' => 'Şebeke 3/N/PE 400/230 V 50 Hz',
     'cumle' => null, 'markalar' => [], 'capraz' => null, 'foto' => null, 'yalniz_etiket' => true],

    ['satir' => 'B', 'etiket' => ['-P1'], 'sembol' => ['analizor'], 'baslik' => 'Enerji analizörü',
     'cumle' => 'Hattın akımını, gerilimini ve tüketimini ölçer.',
     'markalar' => [['kategori' => 'olcum', 'filtre' => ['analizor']]],
     'capraz' => ['/4.B', 'ölçüm'], 'liste_kategori' => 'olcum', 'foto' => 'urun/u13', 'alt' => 'Enerji analizörü, temsilî görsel'],

    ['satir' => 'B', 'etiket' => ['-Q1'], 'sembol' => ['devre-kesici'], 'baslik' => 'Kompakt şalter',
     'cumle' => 'Hattı aşırı akıma ve kısa devreye karşı korur.',
     'markalar' => [['kategori' => 'koruma', 'filtre' => ['devre kesici']]],
     'capraz' => ['/4.B', 'koruma'], 'liste_kategori' => 'koruma', 'foto' => 'urun/u01-kompakt-salter', 'alt' => 'Kompakt şalter, temsilî görsel'],

    ['satir' => 'C', 'etiket' => ['-G1'], 'sembol' => ['guc-kaynagi'], 'baslik' => 'Güç kaynağı 24 V DC',
     'cumle' => 'Kumanda devresini besler.',
     'markalar' => [['kategori' => 'guc', 'filtre' => ['guc kaynak']]],
     'capraz' => ['/4.B', 'güç'], 'liste_kategori' => 'guc', 'foto' => 'urun/u08', 'alt' => 'Güç kaynağı, temsilî görsel'],

    ['satir' => 'C', 'etiket' => ['-A1'], 'sembol' => ['plc'], 'baslik' => 'PLC',
     'cumle' => 'Sensörden ve butondan gelen sinyalle çıkışları yönetir.',
     'markalar' => [['kategori' => 'plc', 'filtre' => ['plc']]],
     'capraz' => ['/4.B', 'PLC'], 'liste_kategori' => 'plc', 'foto' => 'urun/u07', 'alt' => 'PLC, temsilî görsel'],

    ['satir' => 'C', 'etiket' => ['-B1'], 'sembol' => ['sensor-enduktif'], 'baslik' => 'Endüktif sensör',
     'cumle' => 'Metal parçayı temas etmeden algılar.',
     'markalar' => [['kategori' => 'sensor', 'filtre' => ['enduktif', 'sensorler']]],
     'capraz' => ['/4.B', 'sensör'], 'liste_kategori' => 'sensor', 'foto' => 'urun/u09', 'alt' => 'Endüktif sensör, temsilî görsel'],

    ['satir' => 'C', 'etiket' => ['-S1', '-H1'], 'sembol' => ['buton-no', 'lamba'], 'baslik' => 'Buton ve sinyal lambası',
     'cumle' => 'Operatör komutu ve durum bilgisi.',
     'markalar' => [['kategori' => 'kumanda', 'filtre' => ['kumanda buton', 'isikli kolon']]],
     'capraz' => ['/4.B', 'kumanda'], 'liste_kategori' => 'kumanda', 'foto' => 'urun/u10', 'alt' => 'Buton ve sinyal lambası, temsilî görsel'],

    ['satir' => 'C', 'etiket' => ['-K2'], 'sembol' => ['bobin', 'kontak-no'], 'baslik' => 'Ara röle',
     'cumle' => 'PLC çıkışını kontaktör bobinine aktarır.',
     'markalar' => [['kategori' => 'role', 'filtre' => ['endustriyel ve pcb', 'elektromekanik', 'zaman ve anahtarlama']]],
     'capraz' => ['/4.B', 'röle'], 'liste_kategori' => 'role', 'foto' => 'urun/u04', 'alt' => 'Ara röle, temsilî görsel'],

    ['satir' => 'C', 'etiket' => ['-K1'], 'sembol' => ['kontaktor', 'bobin'], 'baslik' => 'Kontaktör',
     'cumle' => 'Motoru devreye alır ve devreden çıkarır.',
     'markalar' => [['kategori' => ['koruma', 'role'], 'filtre' => ['koruma ve kontrol', 'solid state kontaktor']]],
     'capraz' => ['/4.B', 'kontaktör'], 'liste_kategori' => 'role', 'foto' => 'urun/u03', 'alt' => 'Kontaktör, temsilî görsel'],

    ['satir' => 'C', 'etiket' => ['-E1'], 'sembol' => ['klima'], 'baslik' => 'Pano kliması',
     'cumle' => 'Pano içindeki ısıyı dışarı atar.',
     'markalar' => [], 'metin' => 'TPK serisi',
     'capraz' => ['/5', 'pano soğutma'], 'foto' => 'tpk/pano-sogutma-grup', 'alt' => 'TPK pano kliması'],

    ['satir' => 'D', 'etiket' => ['-U1'], 'sembol' => ['surucu'], 'baslik' => 'AC sürücü',
     'cumle' => 'Motorun hızını ve kalkışını ayarlar.',
     'markalar' => [['kategori' => 'surucu', 'filtre' => ['hiz kontrol', 'ac surucu']]],
     'capraz' => ['/4.B', 'sürücü'], 'liste_kategori' => 'surucu', 'foto' => 'urun/u05', 'alt' => 'AC sürücü, temsilî görsel'],

    ['satir' => 'D', 'etiket' => ['-X1'], 'sembol' => ['klemens'], 'baslik' => 'Klemens',
     'cumle' => 'Pano içi ve saha bağlantılarını toplar.',
     'markalar' => [['kategori' => 'klemens', 'filtre' => ['klemens']]],
     'capraz' => ['/4.B', 'klemens'], 'liste_kategori' => 'klemens', 'foto' => 'urun/u11', 'alt' => 'Klemens, temsilî görsel'],

    ['satir' => 'D', 'etiket' => ['-W1'], 'sembol' => ['kablo'], 'baslik' => 'Motor kablosu',
     'cumle' => 'Sürücüden motora giden bağlantı.',
     'markalar' => [
         ['kategori' => 'kablo', 'filtre' => ['nya', 'ttr', 'nym', 'nyy', 'kumanda kablo']],
         ['kategori' => 'kablo', 'filtre' => ['rakor', 'kablo kanallari', 'hareketli'], 'etiket' => 'rakor ve kanal'],
     ],
     'capraz' => ['/4.B', 'kablo'], 'liste_kategori' => 'kablo', 'foto' => 'urun/u12', 'alt' => 'Kumanda kablosu, temsilî görsel'],

    ['satir' => 'E', 'etiket' => ['-M1', '-B2'], 'sembol' => ['motor', 'enkoder'], 'baslik' => 'Servo motor ve enkoder',
     'cumle' => 'Sürücüyle birlikte seçilen motor, redüktör ve konum geri bildirimi.',
     'markalar' => [
         ['kategori' => 'surucu', 'filtre' => ['servo surucu ve motor', 'reduktor'], 'not' => 'servo motor, redüktör'],
         // Bedok ve Alfa Sensör de enkoder listeler; spec 3.1 bu istasyonda yalnızca döner enkoder markalarını gösterir.
         ['kategori' => 'sensor', 'slug' => ['nemicon', 'hanyoung', 'autonics'], 'etiket' => 'enkoder'],
     ],
     'capraz' => ['/4.B', 'motor'], 'liste_kategori' => 'surucu', 'foto' => 'urun/u06', 'alt' => 'Servo motor, temsilî görsel'],
];
