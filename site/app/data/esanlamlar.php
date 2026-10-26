<?php
/**
 * Arama eş anlamlıları (spec 3.4). Her satır birbirinin yerine geçen terimlerdir, katlanmış (tr_fold) yazılır.
 * Çok kelimeli terimler grup adında kelime öbeği olarak aranır. app/lib/arama.php ve assets/js/arama.js aynı listeyi kullanır.
 *
 * "kontaktor" satırındaki "koruma ve kontrol": Schneider ve Siemens'in "Elektrik koruma ve kontrol" grupları
 * kontaktörleri kapsar (spec 3.4 test listesi: "kontaktor" koruma markalarını da bulmalı).
 */
return [
    ['enkoder', 'encoder'],
    ['fotosel', 'photocell', 'fotoelektrik'],
    ['surucu', 'hiz kontrol', 'inverter', 'invertor', 'frekans konvertoru', 'vfd'],
    ['plc', 'programlanabilir'],
    ['hmi', 'operator paneli', 'ekran'],
    ['kontaktor', 'contactor', 'koruma ve kontrol'],
    ['role', 'relay'],
    ['klemens', 'terminal', 'klemensler'],
    ['kablo', 'cable'],
    ['sigorta', 'fuse'],
    ['salter', 'sivic', 'kesici'],
    ['guc kaynagi', 'smps', 'adaptor'],
    ['sensor', 'algilayici'],
    ['rakor', 'gland'],
    ['pano', 'kabin', 'kutu'],
    ['buton', 'dugme'],
    ['lamba', 'isik'],
    ['ssr', 'solid state'],
    ['termokupl', 'thermocouple'],
    ['load cell', 'yuk hucresi'],
];
