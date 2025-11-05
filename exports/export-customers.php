<?php
/**
 * 客户数据导出
 * Export Customers
 *
 * @version v2.0.0
 * @description 导出客户数据为CSV或JSON格式
 * @遵循宪法原则I: 安全优先开发 - 权限检查
 */

// 防止直接访问
define('QUOTABASE_SYSTEM', true);

// 加载配置和依赖
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../db.php';

// 检查登录
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => '未授权访问']);
    exit;
}

// 获取导出格式
$format = $_GET['format'] ?? 'csv';
$format = strtolower($format);

if (!in_array($format, ['csv', 'json'])) {
    http_response_code(400);
    echo json_encode(['error' => '不支持的导出格式']);
    exit;
}

try {
    // 获取所有客户数据
    $org_id = get_current_org_id();
    $sql = "
        SELECT id, name, tax_id, email, phone, billing_address, shipping_address, note, active, created_at, updated_at
        FROM customers
        WHERE org_id = ?
        ORDER BY name ASC
    ";
    $customers = dbQuery($sql, [$org_id]);
    $exportedAt = gmdate('c');

    // 设置响应头
    if ($format === 'csv') {
        // CSV导出
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="customers_' . date('Y-m-d') . '.csv"');

        // 输出BOM头，确保Excel正确识别UTF-8
        echo "\xEF\xBB\xBF";

        // 打开输出流
        $output = fopen('php://output', 'w');

        // 写入表头
        fputcsv($output, [
            'ID',
            '客户名称',
            '税务登记号',
            '邮箱',
            '电话',
            '账单地址',
            '收货地址',
            '备注',
            '状态',
            '创建时间',
            '更新时间'
        ]);

        // 写入数据
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
                $customer['active'] ? '活跃' : '禁用',
                format_datetime($customer['created_at'], 'Y-m-d H:i:s'),
                !empty($customer['updated_at']) ? format_datetime($customer['updated_at'], 'Y-m-d H:i:s') : ''
            ]);
        }

        fclose($output);

    } else {
        // JSON导出
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
        'message' => '导出失败: ' . $e->getMessage()
    ]);
}
?>
