<?php
/**
 * 客戶資料匯出
 * Export Customers
 *
 * @version v2.0.0
 * @description 匯出客戶資料為CSV或JSON格式
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
    // 獲取所有客戶資料
    $org_id = get_current_org_id();
    $sql = "
        SELECT id, name, tax_id, email, phone, billing_address, shipping_address, note, active, created_at, updated_at
        FROM customers
        WHERE org_id = ?
        ORDER BY name ASC
    ";
    $customers = dbQuery($sql, [$org_id]);
    $exportedAt = gmdate('c');

    // 設定響應頭
    if ($format === 'csv') {
        // CSV匯出
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="customers_' . date('Y-m-d') . '.csv"');

        // 輸出BOM頭，確保Excel正確識別UTF-8
        echo "\xEF\xBB\xBF";

        // 開啟輸出流
        $output = fopen('php://output', 'w');

        // 寫入表頭
        fputcsv($output, [
            'ID',
            '客戶名稱',
            '稅務登記號',
            '郵箱',
            '電話',
            '賬單地址',
            '收貨地址',
            '備註',
            '狀態',
            '建立時間',
            '更新時間'
        ]);

        // 寫入資料
        foreach ($customers as $customer) {
            fputcsv($output, [
                $customer['id'],
                $customer['name'],
                $customer['tax_id'],
                $customer['email'],
                $customer['phone'],
                $customer['billing_address'],
                $customer['shipping_address'],
                $customer['note'],
                $customer['active'] ? '活躍' : '停用',
                format_datetime($customer['created_at'], 'Y-m-d H:i:s'),
                !empty($customer['updated_at']) ? format_datetime($customer['updated_at'], 'Y-m-d H:i:s') : ''
            ]);
        }

        fclose($output);

    } else {
        // JSON匯出
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="customers_' . date('Y-m-d') . '.json"');

        $output = [
            'success' => true,
            'exported_at' => $exportedAt,
            'total_records' => count($customers),
            'data' => array_map(function ($customer) {
                return [
                    'id' => (int)$customer['id'],
                    'name' => $customer['name'],
                    'tax_id' => $customer['tax_id'],
                    'email' => $customer['email'],
                    'phone' => $customer['phone'],
                    'billing_address' => $customer['billing_address'],
                    'shipping_address' => $customer['shipping_address'],
                    'note' => $customer['note'],
                    'active' => (bool)$customer['active'],
                    'created_at' => format_iso8601_utc($customer['created_at']),
                    'updated_at' => format_iso8601_utc($customer['updated_at'])
                ];
            }, $customers)
        ];

        echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    error_log("Export customers error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'EXPORT_FAILED',
        'message' => '匯出失敗: ' . $e->getMessage()
    ]);
}
?>
