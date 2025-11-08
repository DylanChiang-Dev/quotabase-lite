<?php
/**
 * 個人收據下載
 */

define('QUOTABASE_SYSTEM', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../db.php';

$quoteId = intval($_GET['quote_id'] ?? 0);
$serial = trim($_GET['serial'] ?? '');
$token = trim($_GET['token'] ?? '');

$receipt = null;

if ($quoteId > 0) {
    $receipt = get_receipt_by_quote($quoteId);
} elseif ($serial !== '') {
    $receipt = dbQueryOne("SELECT * FROM receipts WHERE serial = ?", [$serial]);
}

if (!$receipt) {
    http_response_code(404);
    echo '收據不存在';
    exit;
}

$allowed = false;

if (is_logged_in()) {
    $allowed = true;
} elseif ($token !== '') {
    $verification = verify_receipt_token($receipt['serial'], $token);
    $allowed = $verification['success'];
}

if (!$allowed) {
    http_response_code(403);
    echo '未授權下載';
    exit;
}

$basePath = realpath(__DIR__ . '/..');
$relativePath = '/' . ltrim($receipt['pdf_path'], '/');
$absolutePath = realpath($basePath . $relativePath);

if ($absolutePath === false || strpos($absolutePath, $basePath) !== 0 || !is_file($absolutePath)) {
    http_response_code(404);
    echo '檔案不存在或已移除';
    exit;
}

$mime = mime_content_type($absolutePath) ?: 'application/octet-stream';
$filename = basename($absolutePath);
$isHtml = stripos($mime, 'text/html') === 0 || preg_match('/\\.html?$/i', $filename);

if ($isHtml) {
    $mime = 'text/html; charset=UTF-8';
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($absolutePath));
header('X-Content-Type-Options: nosniff');
readfile($absolutePath);
exit;
