<?php
/**
 * Tekayper'in tedarik ettiği 37 marka ve 178 ürün grubu (canlı tekayper-urunler.php, 23.10.2026).
 *  slug     adres ve veri anahtarı
 *  ad       düzeltilmiş marka adı (Hanyoung, LS Electric, Kjellberg, Mean Well, Schneider Electric, Alfa Sensör)
 *  site_ad  canlı sitedeki yazılış (yalnızca kayıt için, gösterilmez)
 *  kart     canlı sitedeki marka kartı görselinin adı (_kaynak/assets/05-marka-logo-kart/)
 *  bayi     bayilik notu. Tekayper yazılı teyit verene kadar null; config show_dealer_info = false.
 *  gruplar  [ad, kategori] çiftleri. Ad cümle düzeninde, kategori kategoriler.php kimliği (elle kontrol edildi).
 *
 * Dosyanın sonunda gruplar ['ad' => ..., 'kategori' => ...] biçimine çevrilir.
 */
$markalar = [
    ['slug' => 'wieland', 'ad' => 'Wieland', 'site_ad' => 'WIELAND', 'kart' => 'wieland', 'gruplar' => [
        ['Klemens ve aksesuarlar', 'klemens'], ['Endüstriyel konnektörler', 'klemens'], ['Ölçüm ve izleme röleleri', 'role'],
        ['Zaman ve anahtarlama röleleri', 'role'], ['Güç kaynakları', 'guc']]],
    ['slug' => 'ls-electric', 'ad' => 'LS Electric', 'site_ad' => 'LS', 'kart' => 'ls', 'gruplar' => [
        ['AC hız kontrol cihazları', 'surucu'], ['PLC ve hareket kontrolü', 'plc'], ['Operatör panelleri (HMI)', 'plc'],
        ['Servo sürücü ve motorlar', 'surucu'], ['Redüktörler', 'surucu']]],
    ['slug' => 'datalogic', 'ad' => 'Datalogic', 'site_ad' => 'DATALOGIC', 'kart' => 'datalogic', 'gruplar' => [
        ['Fotosel ve güvenlik bariyerleri', 'sensor'], ['Silindirik endüktif sensörler', 'sensor'], ['Kübik endüktif sensörler', 'sensor'],
        ['Silindirik kapasitif sensörler', 'sensor'], ['Sensör aksesuarları', 'sensor']]],
    ['slug' => 'hanyoung', 'ad' => 'Hanyoung', 'site_ad' => 'HANGYOUNG', 'kart' => 'hangyoung', 'gruplar' => [
        ['Proses ve sıcaklık kontrol cihazları', 'olcum'], ['Oransal ve on/off sıcaklık kontrol', 'olcum'], ['Solid state röleler', 'role'],
        ['Sayıcı ve zaman röleleri', 'role'], ['Enkoderler', 'sensor']]],
    ['slug' => 'nemicon', 'ad' => 'Nemicon', 'site_ad' => 'NEMICON', 'kart' => 'nemicon', 'gruplar' => [
        ['Enkoderler (NOC3 ve 38S serisi)', 'sensor']]],
    ['slug' => 'iht', 'ad' => 'IHT', 'site_ad' => 'IHT', 'kart' => 'iht', 'gruplar' => [
        ['APC otomatik işlem kontrolü', 'plazma'], ['Robot için APC uygulamaları', 'plazma'], ['Oksi-yakıt sistemleri', 'plazma'],
        ['Oksi-yakıt torçları', 'plazma'], ['Plazma sistemleri', 'plazma']]],
    ['slug' => 'kjellberg', 'ad' => 'Kjellberg', 'site_ad' => 'KJELLBERG', 'kart' => 'kiellberg', 'gruplar' => [
        ['Plazma kesim üniteleri', 'plazma'], ['Hi-Focus', 'plazma'], ['Smart-Focus', 'plazma'], ['X-Focus', 'plazma'], ['CutFire', 'plazma']]],
    ['slug' => 'finder', 'ad' => 'Finder', 'site_ad' => 'FINDER', 'kart' => 'finder', 'gruplar' => [
        ['Endüstriyel ve PCB röleler (EMR/SSR)', 'role'], ['Röle arayüz modülleri (EMR/SSR)', 'role'], ['Güvenlik röleleri', 'role'],
        ['Solid state güç röleleri', 'role'], ['İzleme röleleri ve enerji sayaçları', 'role']]],
    ['slug' => 'emas', 'ad' => 'Emas', 'site_ad' => 'EMAS', 'kart' => 'emas', 'gruplar' => [
        ['Kumanda butonları', 'kumanda'], ['Sinyal lambaları', 'kumanda'], ['Kumanda kutuları', 'kumanda'],
        ['Pedallar', 'kumanda'], ['Sınır şalterleri', 'kumanda']]],
    ['slug' => 'schneider', 'ad' => 'Schneider Electric', 'site_ad' => 'SCHNEIDER', 'kart' => 'schneider', 'gruplar' => [
        ['Elektrik koruma ve kontrol', 'koruma'], ['Termik manyetik şalter ve devre kesiciler', 'koruma'], ['Yük ayırıcılar', 'koruma'],
        ['Kumanda butonları', 'kumanda'], ['Pako şalterler', 'koruma']]],
    ['slug' => 'turkuaz', 'ad' => 'Turkuaz', 'site_ad' => 'TURKUAZ', 'kart' => 'turkuaz', 'gruplar' => [
        ['Polyamid ve PP spiral borular', 'kablo'], ['PVC spiral boru rakorları', 'kablo'], ['Polyamid ve pirinç kablo rakorları', 'kablo'],
        ['Paslanmaz EMC ve kablo rakorları', 'kablo'], ['Havalandırmalı pirinç rakorlar', 'kablo']]],
    ['slug' => 'pulse', 'ad' => 'Pulse', 'site_ad' => 'PULSE', 'kart' => 'pulse', 'gruplar' => [
        ['Checkweigher otomatik tartım bantları', 'tartim'], ['Endüstriyel metal dedektörleri', 'tartim'], ['Dozajlama sistemleri', 'tartim'],
        ['Load cell (yük hücresi)', 'tartim'], ['Tartım indikatörü ve load cell transmitter', 'tartim']]],
    ['slug' => 'loobar', 'ad' => 'Loobar', 'site_ad' => 'LOOBAR', 'kart' => 'loobar', 'gruplar' => [
        ['Loobar dağıtıcılar', 'pano'], ['Havalandırma, ısıtma ve soğutma ürünleri', 'pano'], ['Pano montaj malzemeleri', 'pano'],
        ['Elektrik ve elektronik', 'pano'], ['Kablolama malzemeleri', 'kablo']]],
    ['slug' => 'gmt', 'ad' => 'GMT', 'site_ad' => 'GMT', 'kart' => 'gmt', 'gruplar' => [
        ['PLC', 'plc'], ['Ekranlı mini PLC', 'plc'], ['HMI ürünleri', 'plc'], ['AC sürücüler', 'surucu'], ['Solar pompa sürücüleri', 'surucu']]],
    ['slug' => 'onka', 'ad' => 'Onka', 'site_ad' => 'ONKA', 'kart' => 'onka', 'gruplar' => [
        ['Vida bağlantılı ray klemensleri', 'klemens'], ['Yay bağlantılı ray klemensleri', 'klemens'], ['Sıra klemensler', 'klemens'],
        ['Sıkmalı kablo pabuçları', 'klemens'], ['Kablo bağları ve makaronlar', 'klemens']]],
    ['slug' => 'klemsan', 'ad' => 'Klemsan', 'site_ad' => 'KLEMSAN', 'kart' => 'klemsan', 'gruplar' => [
        ['Klemens ve aksesuarlar', 'klemens'], ['Otomasyon ürünleri', 'plc'], ['Markalama sistemleri', 'klemens'],
        ['Kablo kanalı ve aksesuarları', 'kablo'], ['Güç kaynakları', 'guc']]],
    ['slug' => 'isiso', 'ad' => 'ISISO', 'site_ad' => 'ISISO', 'kart' => 'isiso', 'gruplar' => [
        ['Güç kaynakları', 'guc'], ['Hız kontrol ve servo motor sürücüleri', 'surucu'], ['Termokupl ve ölçü aletleri', 'olcum'],
        ['Termokupl fiş-prizleri ve kafa içi çeviriciler', 'olcum'], ['LED aydınlatma', 'priz']]],
    ['slug' => 'tem', 'ad' => 'TEM', 'site_ad' => 'TEM', 'kart' => 'tem', 'gruplar' => [
        ['Plastik grup prizler', 'priz'], ['Alüminyum grup prizler', 'priz'], ['Aydınlatma grubu', 'priz'],
        ['Masa grubu prizleri', 'priz'], ['Priz kutuları', 'priz']]],
    ['slug' => 'anipa', 'ad' => 'Anipa', 'site_ad' => 'ANİPA', 'kart' => 'anipa', 'gruplar' => [
        ['Kablo kanalları', 'kablo'], ['ABS panolar', 'pano'], ['Otomat kutuları', 'pano'], ['Menholler', 'pano'], ['Tepe fanları', 'pano']]],
    ['slug' => 'omron', 'ad' => 'Omron', 'site_ad' => 'OMRON', 'kart' => 'omron', 'gruplar' => [
        ['Elektromekanik röleler', 'role'], ['Solid state röleler', 'role'], ['Düşük gerilim anahtarlama cihazları', 'koruma'],
        ['İzleme ürünleri', 'olcum'], ['Kumanda butonları', 'kumanda']]],
    ['slug' => 'entes', 'ad' => 'Entes', 'site_ad' => 'ENTES', 'kart' => 'entes', 'gruplar' => [
        ['Şebeke analizörleri', 'olcum'], ['EMK serisi Class A kalite analizörü', 'olcum'], ['Güç ve enerji ölçerler', 'olcum'],
        ['Multimetreler', 'olcum'], ['Ampermetreler', 'olcum']]],
    ['slug' => 'opas', 'ad' => 'Opaş', 'site_ad' => 'OPAŞ', 'kart' => 'opas', 'gruplar' => [
        ['Açma-kapama şalterleri', 'koruma'], ['Kutup değiştirici şalterler', 'koruma'], ['Enversör şalterler', 'koruma'], ['Kademeli şalterler', 'koruma']]],
    ['slug' => 'bemis', 'ad' => 'Bemis', 'site_ad' => 'BEMİS', 'kart' => 'bemis', 'gruplar' => [
        ['Endüstriyel kauçuk fiş ve prizler', 'priz'], ['Aydınlatma', 'priz'], ['Kombinasyon ve şantiye panoları', 'pano'],
        ['Buat ve pano kutuları', 'pano'], ['Buton ve şalter kutuları', 'pano']]],
    ['slug' => 'usmetal', 'ad' => 'Usmetal', 'site_ad' => 'USMETAL', 'kart' => 'usmetal', 'gruplar' => [
        ['Kampana (kaplin muhafaza kapağı)', 'mekanik'], ['Alüminyum ayak pedalı', 'mekanik'], ['CETOP piston parçaları', 'mekanik'],
        ['Makine yedek parçaları', 'mekanik']]],
    ['slug' => 'autonics', 'ad' => 'Autonics', 'site_ad' => 'AUTONICS', 'kart' => 'autonics', 'gruplar' => [
        ['Sensörler', 'sensor'], ['Emniyet ürünleri', 'sensor'], ['Kontrol cihazları', 'olcum'], ['Enkoderler', 'sensor'],
        ['Hareket kontrol cihazları', 'plc']]],
    ['slug' => 'enda', 'ad' => 'Enda', 'site_ad' => 'ENDA', 'kart' => 'enda', 'gruplar' => [
        ['Sıcaklık kontrol cihazları', 'olcum'], ['Proses kontrol cihazları', 'olcum'], ['Nem ve sıcaklık ölçerler', 'olcum'],
        ['Defrost kontrol cihazları', 'olcum'], ['Sayıcılar ve takometreler', 'olcum']]],
    ['slug' => 'emko', 'ad' => 'Emko', 'site_ad' => 'EMKO', 'kart' => 'emko', 'gruplar' => [
        ['Ölçüm ve kontrol cihazları', 'olcum'], ['Profesyonel operatör panelleri', 'plc'], ['Sıcaklık ve nem sensörleri', 'olcum'],
        ['Endüstriyel mutfak kontrol cihazları', 'olcum'], ['Kümes otomasyonu', 'olcum']]],
    ['slug' => 'bedok', 'ad' => 'Bedok', 'site_ad' => 'BEDOK', 'kart' => 'bedok', 'gruplar' => [
        ['Güç kaynakları', 'guc'], ['Solid state kontaktörler', 'role'], ['Röleler', 'role'], ['Enkoderler', 'sensor'], ['Sensörler', 'sensor']]],
    ['slug' => 'tmx', 'ad' => 'TMX', 'site_ad' => 'TMX', 'kart' => 'tmx', 'gruplar' => [
        ['Işıklı kolonlar', 'kumanda'], ['Tepe lambaları', 'kumanda'], ['Borulu kornalar', 'kumanda'], ['Motorlu sirenler', 'kumanda'],
        ['Endüstriyel ışıklı sirenler', 'kumanda']]],
    ['slug' => 'alfa-sensor', 'ad' => 'Alfa Sensör', 'site_ad' => 'ALFA SENSOR', 'kart' => 'alfa', 'gruplar' => [
        ['Temassız lineer cetveller', 'sensor'], ['Potansiyometrik lineer cetveller', 'sensor'], ['Manyetik lineer enkoderler', 'sensor'],
        ['Optik lineer enkoderler', 'sensor'], ['İpli enkoder ve potansiyometreler', 'sensor']]],
    ['slug' => 'cks', 'ad' => 'CKS', 'site_ad' => 'CKS', 'kart' => 'cks', 'gruplar' => [
        ['Plastik hafif seri hareketli kablo kanalı', 'kablo'], ['Plastik orta seri hareketli kablo kanalı', 'kablo'],
        ['Plastik ağır seri hareketli kablo kanalı', 'kablo'], ['Çelik seri hareketli kablo kanalı', 'kablo']]],
    ['slug' => 'siemens', 'ad' => 'Siemens', 'site_ad' => 'SIEMENS', 'kart' => 'siemens', 'gruplar' => [
        ['Otomat ve kaçak akım röleleri', 'koruma'], ['Elektrik koruma ve kontrol cihazları', 'koruma'], ['Termik manyetik şalterler', 'koruma'],
        ['Devre kesici ve yük ayırıcılar', 'koruma'], ['Pako şalter ve sinyal lambaları', 'koruma']]],
    ['slug' => 'philips', 'ad' => 'Philips', 'site_ad' => 'PHILIPS', 'kart' => 'philips', 'gruplar' => [
        ['İç mekân aydınlatma armatürleri', 'priz'], ['Dış mekân aydınlatma armatürleri', 'priz'], ['LED lamba ve tüpler', 'priz'],
        ['Geleneksel lamba ve tüpler', 'priz'], ['LED elektroniği', 'priz']]],
    ['slug' => 'prysmian', 'ad' => 'Prysmian', 'site_ad' => 'PRYSMIAN', 'kart' => 'prysmian', 'gruplar' => [
        ['NYA kablo', 'kablo'], ['NYAF kablo', 'kablo'], ['TTR kablo', 'kablo'], ['NYM kablo', 'kablo'], ['NYY kablo', 'kablo']]],
    ['slug' => 'birtas', 'ad' => 'Birtaş', 'site_ad' => 'BİRTAŞ', 'kart' => 'birtas', 'gruplar' => [
        ['Kumanda kabloları', 'kablo'], ['TPU (PUR) kablolar', 'kablo'], ['Data ve bilgisayar kabloları', 'kablo'],
        ['Güvenlik kamerası ve ses kabloları', 'kablo'], ['Koaksiyel kablolar', 'kablo']]],
    ['slug' => 'mean-well', 'ad' => 'Mean Well', 'site_ad' => 'MEANWELL', 'kart' => 'meanwell', 'gruplar' => [
        ['Güç kaynakları', 'guc'], ['LED sürücüler', 'guc'], ['Konvertörler', 'guc'], ['Adaptörler', 'guc'], ['Kart tipi güç modülleri', 'guc']]],
    ['slug' => 'gwest', 'ad' => 'Gwest', 'site_ad' => 'GWEST', 'kart' => 'gwest', 'gruplar' => [
        ['Kablo bağları ve aksesuarları', 'klemens'], ['Kablo yüksükleri ve pabuçları', 'klemens'], ['Bağlantı elemanları', 'klemens'],
        ['Makaron ve bant çeşitleri', 'klemens'], ['Kompakt çok amaçlı klemensler', 'klemens']]],
];

foreach ($markalar as &$m) {
    $m['bayi'] = null;
    $m['gruplar'] = array_map(fn ($g) => ['ad' => $g[0], 'kategori' => $g[1]], $m['gruplar']);
}
unset($m);

return $markalar;
