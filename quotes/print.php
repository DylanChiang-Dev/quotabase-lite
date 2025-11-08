<?php
/**
 * 報價單列印頁面
 * Quote Print Page
 *
 * @version v2.0.0
 * @description A4 格式列印輸出頁面
 * @遵循憲法原則I: 安全優先開發 - XSS防護
 * @遵循憲法原則VI: 專業列印輸出 - A4格式，表頭固定
 */

// 防止直接存取
define('QUOTABASE_SYSTEM', true);

// 載入配置和依賴
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../db.php';

// 取得報價單 ID
$quote_id = intval($_GET['id'] ?? 0);

if ($quote_id <= 0) {
    http_response_code(404);
    echo '無效的報價單 ID';
    exit;
}

$error = '';
$quote = null;

// 取得報價單資訊
try {
    $quote = get_quote($quote_id);

    if (!$quote) {
        http_response_code(404);
        echo '報價單不存在';
        exit;
    }

    // 取得公司資訊
    $company_info = get_company_info();
    $print_terms = get_print_terms();

} catch (Exception $e) {
    error_log("取得報價單錯誤: " . $e->getMessage());
    $error = '載入報價單資訊失敗';
}

// 如果有錯誤，顯示錯誤頁面
if ($error) {
    http_response_code(500);
    echo $error;
    exit;
}

// 列印頁面不需要常規的 HTML 頁首，直接輸出列印友好的 HTML
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>報價單 - <?php echo h($quote['quote_number']); ?></title>
    <style>
        /* 全域樣式 */
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

        /* 列印專用樣式 */
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

            /* 表頭固定 */
            thead {
                display: table-header-group;
            }

            /* 避免在元素內分頁 */
            .avoid-break {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }

        /* A4 容器 */
        .a4-container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 20mm;
            background: #fff;
        }

        /* 公司抬頭 */
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

        /* 報價單標題 */
        .quote-title {
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 30px;
            color: #000;
        }

        /* 報價單資訊 */
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

        /* 報價單明細表格 */
        .quote-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .quote-items-table th {
            background: #f5f5f5;
            padding: 12px;
            font-weight: 600;
            border: 1px solid #ddd;
            font-size: 13px;
            text-align: center;
        }

        .quote-items-table td {
            padding: 10px 12px;
            border: 1px solid #ddd;
            font-size: 13px;
        }

        .quote-items-table td:nth-child(1) {
            text-align: left;
        }

        .quote-items-table td:nth-child(2),
        .quote-items-table td:nth-child(3),
        .quote-items-table td:nth-child(4),
        .quote-items-table td:nth-child(5),
        .quote-items-table td:nth-child(6) {
            text-align: right;
        }

        /* 金額匯總 */
        .amount-summary {
            width: 320px;
            margin-left: auto;
            margin-bottom: 18px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 12px;
            background: #fafafa;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dashed #ddd;
        }

        .summary-row.no-border {
            border-bottom: none;
        }

        .summary-row.total {
            font-size: 16px;
            font-weight: 700;
            border-bottom: none;
            padding-top: 10px;
            margin-top: 6px;
            border-top: 1px solid #bbb;
        }

        .summary-label {
            font-weight: 600;
            color: #555;
        }

        .summary-value {
            font-weight: 600;
            color: #111;
        }

        .summary-value.negative {
            color: #FF3B30;
        }

        /* 備註 */
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

        /* 列印條款 */
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

        /* 頁尾 */
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

        /* 控制按鈕 */
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
    <!-- 列印控制按鈕 -->
    <div class="print-controls no-print">
        <button type="button" onclick="window.print()">列印報價單</button>
        <button type="button" onclick="closePrintWindow()" style="margin-left: 10px; background: #6c757d;">關閉</button>
    </div>

    <div class="a4-container">
        <!-- 公司抬頭（需求：隱藏公司資訊，因此不輸出） -->

        <!-- 報價單標題 -->
        <div class="quote-title">報價單 QUOTATION</div>

        <!-- 報價單資訊 -->
        <div class="quote-info">
            <div class="info-section">
                <h3>報價單資訊</h3>
                <div class="info-row">
                    <span class="info-label">編號:</span>
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
                    <span class="info-label">狀態:</span>
                    <span class="info-value"><?php echo get_status_label($quote['status']); ?></span>
                </div>
            </div>

            <div class="info-section">
                <h3>客戶資訊</h3>
                <div class="info-row">
                    <span class="info-label">名稱:</span>
                    <span class="info-value"><?php echo h($quote['customer_name']); ?></span>
                </div>
                <?php if (!empty($quote['tax_id'])): ?>
                    <div class="info-row">
                        <span class="info-label">統編:</span>
                        <span class="info-value"><?php echo h($quote['tax_id']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($quote['email'])): ?>
                    <div class="info-row">
                        <span class="info-label">信箱:</span>
                        <span class="info-value"><?php echo h($quote['email']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($quote['phone'])): ?>
                    <div class="info-row">
                        <span class="info-label">電話:</span>
                        <span class="info-value"><?php echo h($quote['phone']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 報價單明細 -->
        <table class="quote-items-table avoid-break" style="table-layout: auto;">
            <thead>
                <tr>
                    <th style="width: 40%;">產品／服務</th>
                    <th style="width: 10%;">數量</th>
                    <th style="width: 15%;">單價</th>
                    <th style="width: 15%;">折扣</th>
                    <th style="width: 10%;">稅率</th>
                    <th style="width: 10%;">含稅總計</th>
                </tr>
            </thead>
            <tbody>
                <?php $total_discount_cents = 0; ?>
                <?php foreach ($quote['items'] as $item): ?>
                    <?php
                        $quantity_value = $item['qty'] ?? ($item['quantity'] ?? 0);
                        $unit_label = $item['unit'] ?? '';
                        $discount_cents = (int)($item['discount_cents'] ?? 0);
                        $gross_cents = calculate_line_gross($quantity_value, $item['unit_price_cents']);
                        $discount_percent = $item['discount_percent'] ?? calculate_discount_percent($discount_cents, $gross_cents);
                        $total_discount_cents += $discount_cents;
                    ?>
                    <tr>
                        <td>
                            <?php
                                $category_path = $item['category_path'] ?? '';
                                $display_name = $category_path ? ($category_path . ' · ' . $item['item_name']) : $item['item_name'];
                            ?>
                            <div class="table-text-strong" style="display: inline-block;"><?php echo h($display_name); ?></div>
                        </td>
                        <td class="number">
                            <span><?php echo h(format_quantity($quantity_value)); ?></span>
                            <span><?php echo h(UNITS[$unit_label] ?? $unit_label); ?></span>
                        </td>
                        <td class="number"><?php echo format_currency_cents_compact($item['unit_price_cents']); ?></td>
                        <td class="number">
                            <?php echo $discount_cents > 0 ? format_currency_cents_compact($discount_cents) : '—'; ?>
                        </td>
                        <td class="number"><?php echo number_format($item['tax_rate'], 2); ?>%</td>
                        <td class="number"><?php echo format_currency_cents_compact($item['line_total_cents']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- 金額匯總 -->
        <div class="amount-summary">
            <?php if ($total_discount_cents > 0): ?>
                <div class="summary-row">
                    <span class="summary-label">折扣:</span>
                    <span class="summary-value negative">-<?php echo format_currency_cents_compact($total_discount_cents); ?></span>
                </div>
            <?php endif; ?>
            <div class="summary-row">
                <span class="summary-label">小計:</span>
                <span class="summary-value"><?php echo format_currency_cents_compact($quote['subtotal_cents']); ?></span>
            </div>
            <div class="summary-row no-border">
                <span class="summary-label">稅額:</span>
                <span class="summary-value"><?php echo format_currency_cents_compact($quote['tax_cents']); ?></span>
            </div>
            <div class="summary-row total">
                <span class="summary-label">總計:</span>
                <span class="summary-value"><?php echo format_currency_cents_compact($quote['total_cents']); ?></span>
            </div>
        </div>

        <div style="clear: both;"></div>

        <!-- 備註 -->
        <?php if (!empty($quote['note'])): ?>
            <div class="quote-notes avoid-break">
                <h3>備註</h3>
                <div class="quote-notes-content">
                    <?php echo nl2br(h($quote['note'])); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 列印條款 -->
        <?php if (!empty($print_terms)): ?>
            <div class="print-terms avoid-break">
                <h3>條款與條件</h3>
                <div class="print-terms-content">
                    <?php echo nl2br(h($print_terms)); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- 頁尾 -->
    <div class="page-footer">
        列印時間: <?php echo date('Y-m-d H:i:s'); ?> | 報價單號: <?php echo h($quote['quote_number']); ?>
    </div>

    <script>
        function closePrintWindow() {
            if (window.opener && !window.opener.closed) {
                window.close();
            } else {
                history.back();
            }
        }
    </script>
</body>
</html>
