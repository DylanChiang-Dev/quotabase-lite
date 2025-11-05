<?php
/**
 * 报价单数据导出
 * Export Quotes
 *
 * @version v2.0.0
 * @description 导出报价单数据为CSV或JSON格式，支持日期范围筛选
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

// 获取日期范围筛选
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status = $_GET['status'] ?? '';

try {
    // 构建查询条件
    $org_id = get_current_org_id();
    $where_conditions = ['q.org_id = ?'];
    $params = [$org_id];

    if (!empty($date_from)) {
        $where_conditions[] = 'q.issue_date >= ?';
        $params[] = $date_from;
    }

    if (!empty($date_to)) {
        $where_conditions[] = 'q.issue_date <= ?';
        $params[] = $date_to;
    }

    if (!empty($status)) {
        $where_conditions[] = 'q.status = ?';
        $params[] = $status;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // 获取报价单数据
    $sql = "
        SELECT
            q.id, q.quote_number, q.status, q.issue_date, q.valid_until,
            q.subtotal_cents, q.tax_cents, q.total_cents,
            q.currency,
            customer.name as customer_name, customer.tax_id as customer_tax_id,
            q.created_at, q.updated_at
        FROM quotes q
        LEFT JOIN customers customer ON q.customer_id = customer.id
        WHERE {$where_clause}
        ORDER BY q.issue_date DESC, q.id DESC
    ";
    $quotes = dbQuery($sql, $params);
    $exportedAt = gmdate('c');

    // 设置响应头
    if ($format === 'csv') {
        // CSV导出
        $filename = 'quotes_' . date('Y-m-d');
        if (!empty($date_from) || !empty($date_to)) {
            $filename .= '_' . ($date_from ?: 'all') . '_to_' . ($date_to ?: 'all');
        }
        $filename .= '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // 输出BOM头，确保Excel正确识别UTF-8
        echo "\xEF\xBB\xBF";

        // 打开输出流
        $output = fopen('php://output', 'w');

        // 写入表头
        fputcsv($output, [
            '编号',
            '报价单号',
            '状态',
            '客户名称',
            '客户税号',
            '开票日期',
            '有效期至',
            '小计(分)',
            '税额(分)',
            '总计(分)',
            '币种',
            '创建时间',
            '更新时间'
        ]);

        // 写入数据
        foreach ($quotes as $quote) {
            fputcsv($output, [
                $quote['id'],
                $quote['quote_number'],
                get_status_label($quote['status']),
                $quote['customer_name'],
                $quote['customer_tax_id'],
                format_date($quote['issue_date']),
                !empty($quote['valid_until']) ? format_date($quote['valid_until']) : '',
                $quote['subtotal_cents'],
                $quote['tax_cents'],
                $quote['total_cents'],
                $quote['currency'],
                format_datetime($quote['created_at'], 'Y-m-d H:i:s'),
                !empty($quote['updated_at']) ? format_datetime($quote['updated_at'], 'Y-m-d H:i:s') : ''
            ]);
        }

        fclose($output);

    } else {
        // JSON导出
        $filename = 'quotes_' . date('Y-m-d');
        if (!empty($date_from) || !empty($date_to)) {
            $filename .= '_' . ($date_from ?: 'all') . '_to_' . ($date_to ?: 'all');
        }
        $filename .= '.json';

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = [
            'success' => true,
            'exported_at' => $exportedAt,
            'total_records' => count($quotes),
            'filters' => [
                'date_from' => $date_from ?: null,
                'date_to' => $date_to ?: null,
                'status' => $status ?: null
            ],
            'data' => array_map(function ($quote) {
                return [
                    'id' => (int)$quote['id'],
                    'quote_number' => $quote['quote_number'],
                    'status' => $quote['status'],
                    'status_label' => get_status_label($quote['status']),
                    'customer_name' => $quote['customer_name'],
                    'customer_tax_id' => $quote['customer_tax_id'],
                    'issue_date' => $quote['issue_date'],
                    'valid_until' => $quote['valid_until'],
                    'subtotal_cents' => (int)$quote['subtotal_cents'],
                    'tax_amount_cents' => (int)$quote['tax_cents'],
                    'total_cents' => (int)$quote['total_cents'],
                    'currency' => $quote['currency'],
                    'created_at' => format_iso8601_utc($quote['created_at']),
                    'updated_at' => format_iso8601_utc($quote['updated_at'])
                ];
            }, $quotes)
        ];

        echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    error_log("Export quotes error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'EXPORT_FAILED',
        'message' => '导出失败: ' . $e->getMessage()
    ]);
}
?>
