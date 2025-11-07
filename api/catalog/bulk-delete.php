<?php
define('QUOTABASE_SYSTEM', true);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'METHOD_NOT_ALLOWED'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'UNAUTHORIZED'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'INVALID_CSRF_TOKEN'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$type = strtolower($_POST['type'] ?? 'product');
if (!in_array($type, ['product', 'service'], true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'INVALID_TYPE'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) {
    $ids = [$ids];
}

$result = bulk_delete_catalog_items($ids, $type);

http_response_code($result['success'] ? 200 : 422);

echo json_encode([
    'success' => $result['success'],
    'data' => $result
], JSON_UNESCAPED_UNICODE);
