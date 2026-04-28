<?php
header('Content-Type: application/json');
require_once __DIR__ . '/click.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

$account_id = intval($_POST['account_id'] ?? 0);
if ($account_id <= 0 || !accountExists($conn, $account_id)) {
    echo json_encode(['success' => false]);
    exit;
}

$ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
$ok = recordAccountClick($conn, $account_id, $ip_hash);

echo json_encode(['success' => $ok]);
