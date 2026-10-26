<?php
/**
 * Sitedeki PDF kataloglar (kök dizinde, adresleri değişmez).
 * Sayfa sayıları ve tarihler PDF'lerin kendisinden okundu (24.10.2026). Boyut her istekte dosyadan hesaplanır:
 * pdf_boyut('1.pdf') -> "4,4 MB" (ondalık MB, Finder ile aynı).
 *  ust: kataloglar.php'de ana ek olarak gösterilen (EK-1)
 */
return [
    'tekayper' => [
        'dosya' => '1.pdf', 'baslik' => 'Tekayper ürün kataloğu', 'dil' => 'Türkçe', 'sayfa' => 17,
        'tarih' => '15.06.2021', 'ay' => 'Haziran 2021', 'kapak' => 'katalog/kapak-tekayper', 'ek' => 'EK-1',
    ],
    'armendus' => [
        'dosya' => 'katalog1.pdf', 'baslik' => 'Armendus kataloğu', 'dil' => 'İngilizce', 'sayfa' => 13,
        'tarih' => '10.03.2023', 'ay' => 'Mart 2023', 'kapak' => 'katalog/kapak-armendus', 'ek' => 'EK-2',
    ],
    'teknokol' => [
        'dosya' => '2.pdf', 'baslik' => 'Teknokol kataloğu (2021)', 'dil' => 'Türkçe', 'sayfa' => 17,
        'tarih' => '15.06.2021', 'ay' => 'Haziran 2021', 'kapak' => 'katalog/kapak-teknokol', 'ek' => 'EK-3',
        'not' => 'TPK pano klimalarının teknik tablolarını içerir.', 'arsiv' => true,
    ],
];
