<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/db.php';

function ensureClickTable($conn)
{
    $conn->query("CREATE TABLE IF NOT EXISTS account_clicks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ip_hash VARCHAR(64),
        INDEX idx_account (account_id),
        INDEX idx_clicked_at (clicked_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function accountExists($conn, $accountId)
{
    $stmt = $conn->prepare("SELECT id FROM accounts WHERE id = ? AND sold = 0 LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $accountId);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }

    $stmt->bind_result($foundId);
    $exists = $stmt->fetch();
    $stmt->close();

    return $exists ? true : false;
}

function recordAccountClick($conn, $accountId, $ipHash)
{
    ensureClickTable($conn);

    $check = $conn->prepare("SELECT id FROM account_clicks WHERE account_id = ? AND ip_hash = ? AND clicked_at >= CURDATE() LIMIT 1");
    if (!$check) {
        return false;
    }

    $check->bind_param('is', $accountId, $ipHash);
    if (!$check->execute()) {
        $check->close();
        return false;
    }

    $check->bind_result($existingId);
    $exists = $check->fetch();
    $check->close();

    if ($exists) {
        return true;
    }

    $insert = $conn->prepare("INSERT INTO account_clicks (account_id, ip_hash) VALUES (?, ?)");
    if (!$insert) {
        return false;
    }

    $insert->bind_param('is', $accountId, $ipHash);
    $ok = $insert->execute();
    $insert->close();

    return $ok ? true : false;
}

function resolveClickRedirect($accountId, $redirect)
{
    $target = 'index.php#' . intval($accountId);
    if ($redirect === '') {
        return $target;
    }

    $parts = parse_url($redirect);
    $host = isset($parts['host']) ? strtolower($parts['host']) : '';
    $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';

    if ($scheme === 'https' && in_array($host, array('wa.me', 'api.whatsapp.com'))) {
        return $redirect;
    }

    return $target;
}

$account_id = isset($_REQUEST['account_id']) ? intval($_REQUEST['account_id']) : 0;
$redirect = isset($_REQUEST['redirect']) ? trim($_REQUEST['redirect']) : '';
$isPost = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';

if ($account_id <= 0 || !accountExists($conn, $account_id)) {
    if ($isPost) {
        header('Content-Type: application/json');
        echo json_encode(array('success' => false));
        exit;
    }

    header('Location: index.php');
    exit;
}

$ip_hash = hash('sha256', isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
$ok = recordAccountClick($conn, $account_id, $ip_hash);

if ($isPost) {
    header('Content-Type: application/json');
    echo json_encode(array('success' => $ok ? true : false));
    exit;
}

header('Location: ' . resolveClickRedirect($account_id, $redirect));
exit;