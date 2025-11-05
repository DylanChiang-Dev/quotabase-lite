<?php
/**
 * 服务数据导出
 * Export Services
 *
 * @version v2.0.0
 * @description 导出服务数据为CSV或JSON格式
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
    // 获取所有服务数据
    $org_id = get_current_org_id();
    $sql = "
        SELECT id, type, sku, name, unit, currency, unit_price_cents, tax_rate, active, created_at, updated_at
        FROM catalog_items
        WHERE org_id = ? AND type = 'service'
        ORDER BY name ASC
    ";
    $services = dbQuery($sql, [$org_id]);
    $exportedAt = gmdate('c');

    // 设置响应头
    if ($format === 'csv') {
        // CSV导出
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="services_' . date('Y-m-d') . '.csv"');

        // 输出BOM头，确保Excel正确识别UTF-8
        echo "\xEF\xBB\xBF";

        // 打开输出流
        $output = fopen('php://output', 'w');

        // 写入表头
        fputcsv($output, [
            'ID',
            '类型',
            'SKU',
            '名称',
            '单位',
            '币种',
            '单价(分)',
            '税率(%)',
            '状态',
            '创建时间',
            '更新时间'
        ]);

        // 写入数据
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
                $service['active'] ? '启用' : '禁用',
                format_datetime($service['created_at'], 'Y-m-d H:i:s'),
                !empty($service['updated_at']) ? format_datetime($service['updated_at'], 'Y-m-d H:i:s') : ''
            ]);
        }

        fclose($output);

    } else {
        // JSON导出
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="services_' . date('Y-m-d') . '.json"');

        // 格式化输出
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
        'message' => '导出失败: ' . $e->getMessage()
    ]);
}
?>
