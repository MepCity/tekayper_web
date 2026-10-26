<?php
/** Eski Teknokol sayfası: ürünler artık Armendus'ta. Kalıcı yönlendirme (spec 1.2). */
require __DIR__ . '/app/bootstrap.php';
header('Location: ' . url('urunler.php#armendus'), true, 301);
exit;
