<?php
/**
 * 报价单打印页面
 * Quote Print Page
 *
 * @version v2.0.0
 * @description A4格式打印输出页面
 * @遵循宪法原则I: 安全优先开发 - XSS防护
 * @遵循宪法原则VI: 专业打印输出 - A4格式，表头固定
 */

// 防止直接访问
define('QUOTABASE_SYSTEM', true);

// 加载配置和依赖
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../db.php';

// 获取报价单ID
$quote_id = intval($_GET['id'] ?? 0);

if ($quote_id <= 0) {
    http_response_code(404);
    echo '无效的报价单ID';
    exit;
}

$error = '';
$quote = null;

// 获取报价单信息
try {
    $quote = get_quote($quote_id);

    if (!$quote) {
        http_response_code(404);
        echo '报价单不存在';
        exit;
    }

    // 获取公司信息
    $company_info = get_company_info();
    $print_terms = get_print_terms();

} catch (Exception $e) {
    error_log("Get quote error: " . $e->getMessage());
    $error = '加载报价单信息失败';
}

// 如果有错误，显示错误页面
if ($error) {
    http_response_code(500);
    echo $error;
    exit;
}

// 打印页面不需要常规的HTML头，直接输出打印友好的HTML
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>报价单 - <?php echo h($quote['quote_number']); ?></title>
    <style>
        /* 全局样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans TC", "Noto Sans CJK SC", "Source Han Sans SC", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            background: #fff;
        }

        /* 打印专用样式 */
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .no-print {
                display: none !important;
            }

            @page {
                size: A4;
                margin: 15mm;
            }

            /* 表头固定 */
            thead {
                display: table-header-group;
            }

            /* 避免在元素内分页 */
            .avoid-break {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }

        /* A4容器 */
        .a4-container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 20mm;
            background: #fff;
        }

        /* 公司头部 */
        .company-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }

        .company-name {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #000;
        }

        .company-details {
            font-size: 14px;
            color: #666;
            line-height: 1.8;
        }

        /* 报价单标题 */
        .quote-title {
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 30px;
            color: #000;
        }

        /* 报价单信息 */
        .quote-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 40px;
        }

        .info-section {
            flex: 1;
        }

        .info-section h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #000;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .info-row {
            display: flex;
            margin-bottom: 8px;
            line-height: 1.8;
        }

        .info-label {
            font-weight: 600;
            margin-right: 10px;
            color: #555;
            min-width: 80px;
        }

        .info-value {
            color: #000;
        }

        /* 报价单明细表格 */
        .quote-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .quote-items-table th {
            background: #f5f5f5;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #ddd;
            font-size: 13px;
        }

        .quote-items-table td {
            padding: 10px 12px;
            border: 1px solid #ddd;
            font-size: 13px;
        }

        .quote-items-table .number {
            text-align: right;
        }

        .quote-items-table .sku {
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }

        /* 金额汇总 */
        .amount-summary {
            width: 400px;
            margin-left: auto;
            margin-bottom: 30px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }

        .summary-row.total {
            font-size: 18px;
            font-weight: 700;
            border-bottom: 2px solid #000;
            border-top: 2px solid #000;
            padding-top: 15px;
            padding-bottom: 15px;
        }

        .summary-label {
            font-weight: 600;
        }

        .summary-value {
            font-weight: 600;
        }

        /* 备注 */
        .quote-notes {
            margin-bottom: 30px;
        }

        .quote-notes h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #000;
        }

        .quote-notes-content {
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #333;
            line-height: 1.8;
        }

        /* 打印条款 */
        .print-terms {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
        }

        .print-terms h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #000;
        }

        .print-terms-content {
            font-size: 13px;
            line-height: 1.8;
            color: #666;
            white-space: pre-line;
        }

        /* 页脚 */
        .page-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 12px;
            color: #999;
            padding: 10px;
        }

        /* 控制按钮 */
        .print-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .print-controls button {
            padding: 12px 24px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .print-controls button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <!-- 打印控制按钮 -->
    <div class="print-controls no-print">
        <button onclick="window.print()">打印报价单</button>
        <button onclick="window.close()" style="margin-left: 10px; background: #6c757d;">关闭</button>
    </div>

    <div class="a4-container">
        <!-- 公司头部 -->
        <div class="company-header">
            <?php if (!empty($company_info['name'])): ?>
                <div class="company-name"><?php echo h($company_info['name']); ?></div>
            <?php endif; ?>
            <div class="company-details">
                <?php if (!empty($company_info['address'])): ?>
                    <div><?php echo h($company_info['address']); ?></div>
                <?php endif; ?>
                <?php if (!empty($company_info['contact'])): ?>
                    <div><?php echo nl2br(h($company_info['contact'])); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 报价单标题 -->
        <div class="quote-title">报价单 QUOTATION</div>

        <!-- 报价单信息 -->
        <div class="quote-info">
            <div class="info-section">
                <h3>报价单信息</h3>
                <div class="info-row">
                    <span class="info-label">编号:</span>
                    <span class="info-value"><?php echo h($quote['quote_number']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">日期:</span>
                    <span class="info-value"><?php echo format_date($quote['issue_date']); ?></span>
                </div>
                <?php if (!empty($quote['valid_until'])): ?>
                    <div class="info-row">
                        <span class="info-label">有效期:</span>
                        <span class="info-value"><?php echo format_date($quote['valid_until']); ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">状态:</span>
                    <span class="info-value"><?php echo get_status_label($quote['status']); ?></span>
                </div>
            </div>

            <div class="info-section">
                <h3>客户信息</h3>
                <div class="info-row">
                    <span class="info-label">名称:</span>
                    <span class="info-value"><?php echo h($quote['customer_name']); ?></span>
                </div>
                <?php if (!empty($quote['tax_id'])): ?>
                    <div class="info-row">
                        <span class="info-label">税号:</span>
                        <span class="info-value"><?php echo h($quote['tax_id']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($quote['email'])): ?>
                    <div class="info-row">
                        <span class="info-label">邮箱:</span>
                        <span class="info-value"><?php echo h($quote['email']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($quote['phone'])): ?>
                    <div class="info-row">
                        <span class="info-label">电话:</span>
                        <span class="info-value"><?php echo h($quote['phone']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 报价单明细 -->
        <table class="quote-items-table avoid-break">
            <thead>
                <tr>
                    <th style="width: 15%;">SKU</th>
                    <th style="width: 35%;">产品/服务名称</th>
                    <th style="width: 10%; text-align: right;">数量</th>
                    <th style="width: 15%; text-align: right;">单价</th>
                    <th style="width: 10%; text-align: right;">税率</th>
                    <th style="width: 15%; text-align: right;">小计</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quote['items'] as $item): ?>
                    <tr>
                        <td class="sku"><?php echo h($item['sku']); ?></td>
                        <td><?php echo h($item['item_name']); ?></td>
                        <td class="number">
                            <?php echo number_format($item['quantity'], 4); ?>
                            <?php echo h(UNITS[$item['unit']] ?? $item['unit']); ?>
                        </td>
                        <td class="number"><?php echo format_currency_cents($item['unit_price_cents']); ?></td>
                        <td class="number"><?php echo number_format($item['tax_rate'], 2); ?>%</td>
                        <td class="number"><?php echo format_currency_cents($item['line_total_cents']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- 金额汇总 -->
        <div class="amount-summary">
            <div class="summary-row">
                <span class="summary-label">小计:</span>
                <span class="summary-value"><?php echo format_currency_cents($quote['subtotal_cents']); ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">税额:</span>
                <span class="summary-value"><?php echo format_currency_cents($quote['tax_cents']); ?></span>
            </div>
            <div class="summary-row total">
                <span class="summary-label">总计:</span>
                <span class="summary-value"><?php echo format_currency_cents($quote['total_cents']); ?></span>
            </div>
        </div>

        <div style="clear: both;"></div>

        <!-- 备注 -->
        <?php if (!empty($quote['note'])): ?>
            <div class="quote-notes avoid-break">
                <h3>备注</h3>
                <div class="quote-notes-content">
                    <?php echo nl2br(h($quote['note'])); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 打印条款 -->
        <?php if (!empty($print_terms)): ?>
            <div class="print-terms avoid-break">
                <h3>条款与条件</h3>
                <div class="print-terms-content">
                    <?php echo nl2br(h($print_terms)); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- 页脚 -->
    <div class="page-footer">
        打印时间: <?php echo date('Y-m-d H:i:s'); ?> | 报价单号: <?php echo h($quote['quote_number']); ?>
    </div>

    <script>
        // 页面加载完成后自动打印
        window.addEventListener('load', function() {
            // 延迟500ms再打印，确保页面渲染完成
            setTimeout(function() {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
