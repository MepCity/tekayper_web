<?php
/**
 * Çapraz referans bağlantısı, partial biçimi (capraz_a() ile aynı çıktı).
 *   partial('capraz', ['ref' => '/5.C', 'metin' => 'TPK modelleri']);
 *   partial('capraz', ['ref' => '/4.B', 'metin' => 'Kontaktörler', 'opts' => ['sorgu' => ['kategori' => 'role']]]);
 */
echo capraz_a($ref ?? '/1', $metin ?? '', $opts ?? []);
