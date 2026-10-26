<?php
/**
 * TPK pano klimaları (tedarik ettiğimiz ürünler; üretici Teknokol). Değerler üretici kataloğundaki
 * teknik tablolardan alınmıştır: tekayper.com/2.pdf, s. 7-10 (Teknokol, 2021).
 *  w        soğutma kapasitesi (W)
 *  ebat     yükseklik x genişlik x derinlik (mm)
 *  tuketim  güç tüketimi; null = teyit ediliyor (bkz. not)
 *  ortam    'ic' (P serisi, iç ortam) ya da 'dis' (boyalı kasa, dış ortam)
 */
$ortak = [
    'gaz'      => 'R134a',
    'aralik'   => '+20 °C / +55 °C',
    'montaj'   => 'Dik duvar (yan kapak)',
    'termostat'=> '30 °C',
];

$seriler = [
    'p' => [
        'ad'       => 'P serisi',
        'ortam'    => 'ic',
        'kasa'     => 'Galvaniz-inox kasa',
        'ozellik'  => ['Galvaniz-inox kasa', 'Dijital termostat', 'Açılabilir kapak, kolay bakım', 'A sınıfı ekipman'],
    ],
    'd' => [
        'ad'       => 'Dış ortam serisi',
        'ortam'    => 'dis',
        'kasa'     => 'Boyalı kasa',
        'ozellik'  => ['Dış ortama uygun', 'Boyalı kasa', 'A sınıfı ekipman'],
    ],
];

$modeller = [
    // P serisi
    ['kod' => 'TPK 500P',  'seri' => 'p', 'foto' => 'tpk-500p',  'w' => 450,  'ebat' => [550, 300, 215],  'paket' => [600, 350, 265],  'net' => '23',   'brut' => '25',   'gerilim' => '220-230 V / 50-60 Hz', 'kalkis' => '4,2 A',  'calisma' => '2,2 A', 'sigorta' => '6 A',  'basinc' => '26 bar', 'evap' => '325 m³/h',  'kond' => '465 m³/h',  'tuketim' => '260 W',  'ip' => 'IP54', 'cop' => '1,73'],
    ['kod' => 'TPK 1000P', 'seri' => 'p', 'foto' => 'tpk-1000p', 'w' => 1080, 'ebat' => [895, 405, 215],  'paket' => [945, 455, 265],  'net' => '35,4', 'brut' => '37',   'gerilim' => '198-252 V / 50-60 Hz', 'kalkis' => '12,2 A', 'calisma' => '4,1 A', 'sigorta' => '16 A', 'basinc' => '26 bar', 'evap' => '540 m³/h',  'kond' => '945 m³/h',  'tuketim' => '690 W',  'ip' => 'IP54', 'cop' => '1,73'],
    ['kod' => 'TPK 2000P', 'seri' => 'p', 'foto' => 'tpk-2000p', 'w' => 1930, 'ebat' => [1000, 405, 230], 'paket' => [1050, 455, 280], 'net' => '46',   'brut' => '48',   'gerilim' => '187-254 V / 50-60 Hz', 'kalkis' => '12,8 A', 'calisma' => '4,6 A', 'sigorta' => '16 A', 'basinc' => '26 bar', 'evap' => '945 m³/h',  'kond' => '945 m³/h',  'tuketim' => '980 W',  'ip' => 'IP54', 'cop' => '1,97'],
    ['kod' => 'TPK 3000P', 'seri' => 'p', 'foto' => 'tpk-3000p', 'w' => 2960, 'ebat' => [1000, 405, 230], 'paket' => [1050, 455, 280], 'net' => '52',   'brut' => '54,5', 'gerilim' => '187-254 V / 50-60 Hz', 'kalkis' => '19,8 A', 'calisma' => '7,2 A', 'sigorta' => '25 A', 'basinc' => '26 bar', 'evap' => '945 m³/h',  'kond' => '1220 m³/h', 'tuketim' => null, 'not' => 'Katalogda 690 W yazıyor; üreticiden teyit ediliyor.',  'ip' => 'IP54', 'cop' => '2,24'],
    ['kod' => 'TPK 4000P', 'seri' => 'p', 'foto' => 'tpk-4000p', 'w' => 3340, 'ebat' => [1050, 485, 255], 'paket' => [1100, 510, 305], 'net' => '62',   'brut' => '65',   'gerilim' => '220-230 V / 50-60 Hz', 'kalkis' => '22 A',   'calisma' => '8,6 A', 'sigorta' => '25 A', 'basinc' => '29 bar', 'evap' => '1175 m³/h', 'kond' => '1470 m³/h', 'tuketim' => '1530 W', 'ip' => 'IP54', 'cop' => '2,25'],
    // Dış ortam serisi
    ['kod' => 'TPK 500',   'seri' => 'd', 'foto' => 'tpk-500',   'w' => 450,  'ebat' => [550, 300, 230],  'paket' => [600, 350, 265],  'net' => '23',   'brut' => '25',   'gerilim' => '220-230 V / 50-60 Hz', 'kalkis' => '4,2 A',  'calisma' => '2,2 A', 'sigorta' => '6 A',  'basinc' => '26 bar', 'evap' => '325 m³/h',  'kond' => '465 m³/h',  'tuketim' => '260 W',  'ip' => 'IP65', 'cop' => '1,73'],
    ['kod' => 'TPK 1000',  'seri' => 'd', 'foto' => 'tpk-1000',  'w' => 1080, 'ebat' => [895, 405, 220],  'paket' => [945, 455, 270],  'net' => '37,2', 'brut' => '38,8', 'gerilim' => '198-252 V / 50-60 Hz', 'kalkis' => '12,2 A', 'calisma' => '4,1 A', 'sigorta' => '16 A', 'basinc' => '26 bar', 'evap' => '540 m³/h',  'kond' => '945 m³/h',  'tuketim' => '690 W',  'ip' => 'IP65', 'cop' => '1,57'],
    ['kod' => 'TPK 2000',  'seri' => 'd', 'foto' => 'tpk-2000',  'w' => 1930, 'ebat' => [1000, 405, 230], 'paket' => [1050, 455, 280], 'net' => '47',   'brut' => '51',   'gerilim' => '187-254 V / 50-60 Hz', 'kalkis' => '12,8 A', 'calisma' => '4,6 A', 'sigorta' => '16 A', 'basinc' => '26 bar', 'evap' => '945 m³/h',  'kond' => '945 m³/h',  'tuketim' => '980 W',  'ip' => 'IP54', 'cop' => '1,97'],
    ['kod' => 'TPK 3000',  'seri' => 'd', 'foto' => 'tpk-3000',  'w' => 2960, 'ebat' => [1000, 405, 230], 'paket' => [1050, 455, 280], 'net' => '52',   'brut' => '54,5', 'gerilim' => '187-254 V / 50-60 Hz', 'kalkis' => '19,8 A', 'calisma' => '7,2 A', 'sigorta' => '25 A', 'basinc' => '26 bar', 'evap' => '945 m³/h',  'kond' => '1220 m³/h', 'tuketim' => null, 'not' => 'Katalogda 690 W yazıyor; üreticiden teyit ediliyor.',  'ip' => 'IP54', 'cop' => '2,24'],
    ['kod' => 'TPK 4000',  'seri' => 'd', 'foto' => 'tpk-4000',  'w' => 3340, 'ebat' => [1050, 485, 255], 'paket' => [1100, 510, 305], 'net' => '62',   'brut' => '65',   'gerilim' => '220-230 V / 50-60 Hz', 'kalkis' => '22 A',   'calisma' => '8,6 A', 'sigorta' => '25 A', 'basinc' => '29 bar', 'evap' => '1175 m³/h', 'kond' => '1470 m³/h', 'tuketim' => '1530 W', 'ip' => 'IP54', 'cop' => '2,25'],
];

foreach ($modeller as &$m) {
    $m['slug'] = strtolower(str_replace(' ', '-', $m['kod']));
    $m['ortam'] = $seriler[$m['seri']]['ortam'];
    $m['not'] = $m['not'] ?? null;
}
unset($m);

return ['ortak' => $ortak, 'seriler' => $seriler, 'modeller' => $modeller];
