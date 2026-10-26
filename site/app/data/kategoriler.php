<?php
/**
 * 15 ürün kategorisi (filtre rayı sırası) ve kategori bulma kuralları.
 *
 *  liste    görüntüleme sırası: id => [ad, sembol]
 *  kurallar değerlendirme sırası: [kategori, [anahtar kelimeler]]. İlk eşleşen kazanır.
 *           Anahtar kelimeler katlanmış (tr_fold) yazılır ve grup adında bir kelime başından itibaren aranır
 *           ("rakor" -> "rakorlari" eşleşir, "nem" -> "menholler" eşleşmez). Tire ve noktalama boşluk sayılır.
 *           Özel kelimeler genel olanlardan önce gelir (spec 4.4: "Kablo kanalı" önce, "kablo" sonra).
 *
 * markalar.php'deki her grubun kategorisi elle kontrol edilmiş olarak orada yazılıdır.
 * tools/qa/kategori-testi.php kuralların çıktısını o listeyle karşılaştırır.
 */
return [
    'liste' => [
        'koruma'  => ['ad' => 'Koruma ve anahtarlama',          'sembol' => 'devre-kesici'],
        'role'    => ['ad' => 'Kontaktör ve röle',              'sembol' => 'kontaktor'],
        'surucu'  => ['ad' => 'Sürücü, motor ve redüktör',      'sembol' => 'surucu'],
        'plc'     => ['ad' => 'PLC, HMI ve kontrol',            'sembol' => 'plc'],
        'sensor'  => ['ad' => 'Sensör, enkoder ve cetvel',      'sembol' => 'sensor-enduktif'],
        'olcum'   => ['ad' => 'Ölçüm ve proses kontrol',        'sembol' => 'sicaklik-kontrol'],
        'tartim'  => ['ad' => 'Tartım ve kalite kontrol',       'sembol' => 'yuk-hucresi'],
        'kumanda' => ['ad' => 'Kumanda ve sinyal',              'sembol' => 'buton-no'],
        'guc'     => ['ad' => 'Güç kaynağı',                    'sembol' => 'guc-kaynagi'],
        'klemens' => ['ad' => 'Klemens ve bağlantı',            'sembol' => 'klemens'],
        'kablo'   => ['ad' => 'Kablo ve kablo yönetimi',        'sembol' => 'kablo'],
        'pano'    => ['ad' => 'Pano, muhafaza ve havalandırma', 'sembol' => 'fan'],
        'priz'    => ['ad' => 'Priz ve aydınlatma',             'sembol' => 'priz'],
        'plazma'  => ['ad' => 'Plazma ve kesim',                'sembol' => 'plazma'],
        'mekanik' => ['ad' => 'Mekanik ve yedek parça',         'sembol' => 'mekanik'],
    ],

    'kurallar' => [
        // IHT'nin APC ürünleri kesim torcu yükseklik kontrolüdür, bu yüzden plazma (spec'te plc altındaydı).
        ['plazma',  ['plazma', 'oksi', 'torc', 'focus', 'cutfire', 'apc']],
        ['mekanik', ['kampana', 'cetop', 'yedek parca', 'ayak pedal']],
        ['tartim',  ['tartim', 'load cell', 'yuk hucresi', 'checkweigher', 'metal dedektor', 'dozaj']],
        ['kablo',   ['kablo kanal', 'rakor', 'spiral boru']],
        ['klemens', ['pabuc', 'yuksuk', 'kablo bag', 'makaron', 'markalama', 'klemens', 'konnektor', 'baglanti eleman', 'bant']],
        ['olcum',   ['termokupl']],
        ['pano',    ['salter kutu', 'pano kutu', 'buat']],
        ['koruma',  ['pako', 'otomat ve', 'kacak akim', 'devre kesici', 'yuk ayirici', 'koruma ve kontrol', 'anahtarlama cihaz',
                     'kutup degistirici', 'enversor', 'kademeli', 'acma kapama', 'termik']],
        ['guc',     ['led surucu', 'guc kaynak', 'guc kaynag', 'konvertor', 'adaptor', 'guc modul']],
        ['kumanda', ['buton', 'sinyal lamba', 'kumanda kutu', 'pedal', 'sinir salter', 'isikli kolon', 'tepe lamba', 'korna', 'siren']],
        ['role',    ['role', 'kontaktor', 'emr', 'ssr', 'solid state']],
        ['surucu',  ['hiz kontrol', 'surucu', 'servo', 'reduktor', 'ac drive', 'ac surucu', 'solar pompa']],
        ['plc',     ['plc', 'hmi', 'operator panel', 'otomasyon urun', 'hareket kontrol']],
        ['olcum',   ['sicaklik', 'proses', 'sayici', 'takometre', 'analizor', 'multimetre', 'ampermetre', 'enerji ol',
                     'olcu', 'olcum', 'nem', 'defrost', 'kumes', 'mutfak', 'kontrol cihaz', 'izleme']],
        ['sensor',  ['sensor', 'fotosel', 'guvenlik bariyer', 'enkoder', 'encoder', 'lineer', 'cetvel', 'potansiyometre', 'emniyet']],
        ['priz',    ['priz', 'fis', 'aydinlatma', 'armatur', 'lamba ve tup', 'led lamba', 'led elektron', 'lamba']],
        // "Elektrik ve elektronik" (Loobar) hiçbir genel kurala uymaz; Loobar pano malzemesi markası olduğu için pano.
        ['pano',    ['pano', 'kutu', 'menhol', 'tepe fan', 'havalandirma', 'dagitici', 'elektrik ve elektronik']],
        ['kablo',   ['kablo', 'nya', 'nyaf', 'ttr', 'nym', 'nyy']],
    ],
];
