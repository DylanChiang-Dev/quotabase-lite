<?php
define('QUOTABASE_SYSTEM', true);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers/import/catalog_import.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'METHOD_NOT_ALLOWED'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED'], JSON_UNESCAPED_UNICODE);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'INVALID_CSRF_TOKEN'], JSON_UNESCAPED_UNICODE);
    exit;
}

$type = strtolower($_POST['type'] ?? 'product');
if (!in_array($type, ['product', 'service'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'INVALID_TYPE'], JSON_UNESCAPED_UNICODE);
    exit;
}

$strategy = strtolower($_POST['strategy'] ?? 'skip');
if (!in_array($strategy, ['skip', 'overwrite'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'INVALID_STRATEGY'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'UPLOAD_FAILED'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['file'];
if ($file['size'] > 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'FILE_TOO_LARGE'], JSON_UNESCAPED_UNICODE);
    exit;
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($extension !== 'txt') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'INVALID_FILE_TYPE'], JSON_UNESCAPED_UNICODE);
    exit;
}

$handle = fopen($file['tmp_name'], 'r');
if (!$handle) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'FILE_OPEN_FAILED'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getDB()->getConnection();
    $service = new CatalogImportService($pdo, $type, $strategy);
    $result = $service->importFromStream($handle, $type);

    fclose($handle);

    error_log(sprintf(
        '[CatalogImport] user=%d type=%s strategy=%s total=%d created=%d updated=%d skipped=%d errors=%d',
        get_current_user_id() ?? 0,
        $type,
        $strategy,
        $result->total,
        $result->created,
        $result->updated,
        $result->skipped,
        count($result->errors)
    ));

    echo json_encode([
        'success' => true,
        'data' => array_merge([
            'type' => $type,
            'strategy' => $strategy
        ], $result->toArray())
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    if (is_resource($handle)) {
        fclose($handle);
    }
    error_log('Catalog import error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'INTERNAL_ERROR'], JSON_UNESCAPED_UNICODE);
}
