<?php
/**
 * Eski form arka ucu (canlı sitede jQuery $.post ile kullanılıyordu).
 * GET: iletişim sayfasına kalıcı yönlendirme. POST: 410, eski betikler için JSON.
 */
require __DIR__ . '/app/bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    http_response_code(410);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => false, 'hata' => 'Bu form adresi kaldırıldı. Teklif formu: ' . tam_url('iletisim.php#talep')], JSON_UNESCAPED_UNICODE);
    exit;
}
header('Location: ' . url('iletisim.php'), true, 301);
exit;
