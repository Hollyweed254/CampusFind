<?php
require_once __DIR__ . '/../includes/mpesa.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'POST required']);
    exit;
}
$raw = file_get_contents('php://input');
$payload = json_decode((string)$raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid JSON']);
    exit;
}
$token = (string)($_GET['token'] ?? '');
$r = mpesa_handle_callback($payload, $token);
if (!$r['ok']) {
    http_response_code($r['status'] === 'rejected' ? 403 : 400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Rejected']);
    exit;
}
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
