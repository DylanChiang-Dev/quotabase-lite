<?php
/**
 * 產品資料匯出
 * Export Products
 *
 * @version v2.0.0
 * @description 匯出產品資料為CSV或JSON格式
 * @遵循憲法原則I: 安全優先開發 - 許可權檢查
 */

// 防止直接訪問
define('QUOTABASE_SYSTEM', true);

// 載入配置和依賴
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../db.php';

// 檢查登入
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => '未授權訪問']);
    exit;
}

// 獲取匯出格式
$format = $_GET['format'] ?? 'csv';
$format = strtolower($format);

if (!in_array($format, ['csv', 'json'])) {
    http_response_code(400);
    echo json_encode(['error' => '不支援的匯出格式']);
    exit;
}

try {
    // 獲取所有產品資料
    $org_id = get_current_org_id();
    $sql = "
        SELECT id, type, sku, name, unit, currency, unit_price_cents, tax_rate, active, created_at, updated_at
        FROM catalog_items
        WHERE org_id = ? AND type = 'product'
        ORDER BY name ASC
    ";
    $products = dbQuery($sql, [$org_id]);
    $exportedAt = gmdate('c');

    // 設定響應頭
    if ($format === 'csv') {
        // CSV匯出
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products_' . date('Y-m-d') . '.csv"');

        // 輸出BOM頭，確保Excel正確識別UTF-8
        echo "\xEF\xBB\xBF";

        // 開啟輸出流
        $output = fopen('php://output', 'w');

        // 寫入表頭
        fputcsv($output, [
            'ID',
            '型別',
            'SKU',
            '名稱',
            '單位',
            '幣種',
            '單價(分)',
            '稅率(%)',
            '狀態',
            '建立時間',
            '更新時間'
        ]);

        // 寫入資料
        foreach ($products as $product) {
            fputcsv($output, [
                $product['id'],
                $product['type'],
                $product['sku'],
                $product['name'],
                UNITS[$product['unit']] ?? $product['unit'],
                $product['currency'],
                $product['unit_price_cents'],
                number_format($product['tax_rate'], 2),
                $product['active'] ? '啟用' : '停用',
                format_datetime($product['created_at'], 'Y-m-d H:i:s'),
                !empty($product['updated_at']) ? format_datetime($product['updated_at'], 'Y-m-d H:i:s') : ''
            ]);
        }

        fclose($output);

    } else {
        // JSON匯出
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="products_' . date('Y-m-d') . '.json"');

        // 格式化輸出
        $output = [
            'success' => true,
            'exported_at' => $exportedAt,
            'total_records' => count($products),
            'data' => array_map(function ($product) {
                return [
                    'id' => (int)$product['id'],
                    'type' => $product['type'],
                    'sku' => $product['sku'],
                    'name' => $product['name'],
                    'unit' => $product['unit'],
                    'currency' => $product['currency'],
                    'unit_price_cents' => (int)$product['unit_price_cents'],
                    'tax_rate' => (float)$product['tax_rate'],
                    'active' => (bool)$product['active'],
                    'created_at' => format_iso8601_utc($product['created_at']),
                    'updated_at' => format_iso8601_utc($product['updated_at'])
                ];
            }, $products)
        ];

        echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    error_log("Export products error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'EXPORT_FAILED',
        'message' => '匯出失敗: ' . $e->getMessage()
    ]);
}
?>
