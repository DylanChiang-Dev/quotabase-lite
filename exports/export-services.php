<?php
/**
 * 服務資料匯出
 * Export Services
 *
 * @version v2.0.0
 * @description 匯出服務資料為CSV或JSON格式
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
    // 獲取所有服務資料
    $org_id = get_current_org_id();
    $sql = "
        SELECT id, type, sku, name, unit, currency, unit_price_cents, tax_rate, active, created_at, updated_at
        FROM catalog_items
        WHERE org_id = ? AND type = 'service'
        ORDER BY name ASC
    ";
    $services = dbQuery($sql, [$org_id]);
    $exportedAt = gmdate('c');

    // 設定響應頭
    if ($format === 'csv') {
        // CSV匯出
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="services_' . date('Y-m-d') . '.csv"');

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
        foreach ($services as $service) {
            fputcsv($output, [
                $service['id'],
                $service['type'],
                $service['sku'],
                $service['name'],
                UNITS[$service['unit']] ?? $service['unit'],
                $service['currency'],
                $service['unit_price_cents'],
                number_format($service['tax_rate'], 2),
                $service['active'] ? '啟用' : '停用',
                format_datetime($service['created_at'], 'Y-m-d H:i:s'),
                !empty($service['updated_at']) ? format_datetime($service['updated_at'], 'Y-m-d H:i:s') : ''
            ]);
        }

        fclose($output);

    } else {
        // JSON匯出
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="services_' . date('Y-m-d') . '.json"');

        // 格式化輸出
        $output = [
            'success' => true,
            'exported_at' => $exportedAt,
            'total_records' => count($services),
            'data' => array_map(function ($service) {
                return [
                    'id' => (int)$service['id'],
                    'type' => $service['type'],
                    'sku' => $service['sku'],
                    'name' => $service['name'],
                    'unit' => $service['unit'],
                    'currency' => $service['currency'],
                    'unit_price_cents' => (int)$service['unit_price_cents'],
                    'tax_rate' => (float)$service['tax_rate'],
                    'active' => (bool)$service['active'],
                    'created_at' => format_iso8601_utc($service['created_at']),
                    'updated_at' => format_iso8601_utc($service['updated_at'])
                ];
            }, $services)
        ];

        echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    error_log("Export services error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'EXPORT_FAILED',
        'message' => '匯出失敗: ' . $e->getMessage()
    ]);
}
?>
