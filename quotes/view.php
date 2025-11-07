<?php
/**
 * 報價單詳情頁面
 * Quote View Page
 *
 * @version v2.0.0
 * @description 報價單詳情檢視頁面
 * @遵循憲法原則I: 安全優先開發 - XSS防護
 */

// 防止直接訪問
define('QUOTABASE_SYSTEM', true);

// 載入配置和依賴
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../partials/ui.php';

// 檢查登入
if (!is_logged_in()) {
    header('Location: /login.php');
    exit;
}

// 獲取報價單ID
$quote_id = intval($_GET['id'] ?? 0);

if ($quote_id <= 0) {
    header('Location: /quotes/?error=' . urlencode('無效的報價單ID'));
    exit;
}

$error = '';
$quote = null;

// 獲取報價單資訊
try {
    $quote = get_quote($quote_id);

    if (!$quote) {
        header('Location: /quotes/?error=' . urlencode('報價單不存在'));
        exit;
    }

} catch (Exception $e) {
    error_log("Get quote error: " . $e->getMessage());
    $error = '載入報價單資訊失敗';
}

// 處理狀態更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '無效的請求，請重新提交。';
    } else {
        $new_status = $_POST['status'] ?? '';
        $result = update_quote_status($quote_id, $new_status);

        if ($result['success']) {
            header('Location: /quotes/view.php?id=' . $quote_id . '&success=' . urlencode('狀態更新成功'));
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

// 頁面開始
html_start('報價單詳情');

// 輸出頭部
page_header('報價單詳情', [
    ['label' => '首頁', 'url' => '/'],
    ['label' => '報價管理', 'url' => '/quotes/'],
    ['label' => '報價單詳情', 'url' => '/quotes/view.php?id=' . $quote_id]
]);

?>

<div class="main-content">
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <span class="alert-message"><?php echo h($_GET['success']); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($quote): ?>
        <?php card_start('報價單資訊', [
            ['label' => '編輯', 'url' => '/quotes/edit.php?id=' . $quote_id, 'class' => 'btn-primary'],
            ['label' => '列印', 'url' => '/quotes/print.php?id=' . $quote_id, 'class' => 'btn-secondary', 'target' => '_blank']
        ]); ?>

        <!-- 基本資訊 -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 32px; margin-bottom: 32px;">
            <div>
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color: var(--primary-color);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    報價單資訊
                </h3>

                <div style="display: grid; gap: 16px;">
                    <div class="info-item">
                        <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">報價單號</label>
                        <div style="font-size: 16px; font-weight: 600; color: var(--text-primary); padding: 8px 0; font-family: monospace;">
                            <?php echo h($quote['quote_number']); ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">狀態</label>
                        <div style="padding: 8px 0;">
                            <?php echo get_status_badge($quote['status']); ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">開票日期</label>
                        <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                            <?php echo format_date($quote['issue_date']); ?>
                        </div>
                    </div>

                    <?php if (!empty($quote['valid_until'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">有效期至</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <?php echo format_date($quote['valid_until']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="info-item">
                        <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">建立日期</label>
                        <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                            <?php echo format_datetime($quote['created_at']); ?>
                        </div>
                    </div>

                    <?php if (!empty($quote['updated_at'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">最後更新</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <?php echo format_datetime($quote['updated_at']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color: var(--primary-color);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    客戶資訊
                </h3>

                <div style="display: grid; gap: 16px;">
                    <div class="info-item">
                        <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">客戶名稱</label>
                        <div style="font-size: 16px; font-weight: 600; color: var(--text-primary); padding: 8px 0;">
                            <?php echo h($quote['customer_name']); ?>
                        </div>
                    </div>

                    <?php if (!empty($quote['tax_id'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">稅務登記號</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <?php echo h($quote['tax_id']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($quote['email'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">郵箱</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <a href="mailto:<?php echo h($quote['email']); ?>" style="color: var(--primary-color); text-decoration: none;">
                                    <?php echo h($quote['email']); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($quote['phone'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">電話</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <a href="tel:<?php echo h($quote['phone']); ?>" style="color: var(--primary-color); text-decoration: none;">
                                    <?php echo h($quote['phone']); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 16px;">
                        <a href="/customers/view.php?id=<?php echo $quote['customer_id']; ?>" class="btn btn-outline" style="width: 100%; justify-content: center;">
                            檢視客戶詳情
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 報價專案 -->
        <div style="margin-bottom: 32px;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">報價專案</h3>

            <div style="overflow-x: auto;">
        <table class="quote-items-table" style="width: 100%; border-collapse: collapse; table-layout: auto;">
            <thead style="background: var(--bg-secondary); border-bottom: 2px solid var(--border-color);">
                <tr>
                    <th style="padding: 12px; text-align: center; font-size: 14px; font-weight: 600; color: var(--text-secondary); width: 40%;">產品/服務</th>
                    <th style="padding: 12px; text-align: center; font-size: 14px; font-weight: 600; color: var(--text-secondary);">數量</th>
                    <th style="padding: 12px; text-align: center; font-size: 14px; font-weight: 600; color: var(--text-secondary);">單價</th>
                    <th style="padding: 12px; text-align: center; font-size: 14px; font-weight: 600; color: var(--text-secondary);">折扣</th>
                    <th style="padding: 12px; text-align: center; font-size: 14px; font-weight: 600; color: var(--text-secondary);">稅率</th>
                    <th style="padding: 12px; text-align: center; font-size: 14px; font-weight: 600; color: var(--text-secondary);">含稅總計</th>
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
                                $category_label = $item['category_path'] ?? '';
                                $display_name = $category_label ? ($category_label . ' · ' . $item['item_name']) : $item['item_name'];
                                $total_discount_cents += $discount_cents;
                                ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 16px 12px; text-align: center;">
                                        <div style="font-size: 15px; font-weight: 500; color: var(--text-primary); display: inline-block;"><?php echo h($display_name); ?></div>
                                    </td>
                                    <td style="padding: 16px 12px; text-align: center;">
                                        <div style="font-size: 14px; color: var(--text-secondary); display: inline-block;">
                                            <?php echo h(format_quantity($quantity_value)); ?>
                                            <?php echo h(UNITS[$unit_label] ?? $unit_label); ?>
                                        </div>
                                    </td>
                                    <td style="padding: 16px 12px; text-align: center;">
                                        <div style="font-size: 14px; font-weight: 600; color: var(--text-primary);">
                                            <?php echo format_currency_cents_compact($item['unit_price_cents']); ?>
                                        </div>
                                    </td>
                                    <td style="padding: 16px 12px; text-align: center;">
                                        <div style="font-size: 14px; color: var(--text-secondary);">
                                            <?php echo $discount_cents > 0 ? format_currency_cents_compact($discount_cents) : '—'; ?>
                                        </div>
                                    </td>
                                    <td style="padding: 16px 12px; text-align: center;">
                                        <div style="font-size: 14px; color: var(--text-secondary);">
                                            <?php echo number_format($item['tax_rate'], 2); ?>%
                                        </div>
                                    </td>
                                    <td style="padding: 16px 12px; text-align: center;">
                                        <div style="font-size: 15px; font-weight: 700; color: var(--text-primary);">
                                            <?php echo format_currency_cents_compact($item['line_total_cents']); ?>
                                        </div>
                                    </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 金額彙總 -->
        <div style="margin-bottom: 32px; display: flex; justify-content: flex-end;">
            <div style="width: 400px; padding: 20px; background: var(--bg-secondary); border-radius: var(--border-radius-md);">
                <div style="display: grid; gap: 12px;">
                    <?php if ($total_discount_cents > 0): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 15px;">
                            <span style="color: var(--text-secondary);">折扣：</span>
                            <span style="font-weight: 600; color: var(--danger-color);">
                                -<?php echo format_currency_cents_compact($total_discount_cents); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 15px;">
                        <span style="color: var(--text-secondary);">小計：</span>
                        <span style="font-weight: 600; color: var(--text-primary);">
                            <?php echo format_currency_cents_compact($quote['subtotal_cents']); ?>
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 15px;">
                        <span style="color: var(--text-secondary);">稅額：</span>
                        <span style="font-weight: 600; color: var(--text-primary);">
                            <?php echo format_currency_cents_compact($quote['tax_cents']); ?>
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 2px solid var(--border-color); font-size: 18px; font-weight: 700;">
                        <span style="color: var(--text-primary);">總計：</span>
                        <span style="color: var(--primary-color);">
                            <?php echo format_currency_cents_compact($quote['total_cents']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 備註 -->
        <?php if (!empty($quote['note'])): ?>
            <div style="margin-bottom: 32px;">
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">備註</h3>
                <div style="font-size: 16px; color: var(--text-secondary); padding: 16px; background: var(--bg-secondary); border-radius: var(--border-radius-md); line-height: 1.6;">
                    <?php echo nl2br(h($quote['note'])); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 狀態操作 -->
        <?php if ($quote['status'] === 'draft'): ?>
            <div style="margin-bottom: 32px; padding: 20px; background: var(--bg-secondary); border-radius: var(--border-radius-md);">
                <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">狀態操作</h4>
                <form method="POST" action="/quotes/view.php?id=<?php echo $quote_id; ?>" style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="update_status">
                    <select name="status" required style="padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); background: var(--bg-primary); color: var(--text-primary);">
                        <option value="">選擇新狀態</option>
                        <option value="sent">標記為已傳送</option>
                        <option value="accepted">標記為已接受</option>
                        <option value="rejected">標記為已拒絕</option>
                        <option value="expired">標記為已過期</option>
                    </select>
                    <button type="submit" class="btn btn-primary">更新狀態</button>
                </form>
            </div>
        <?php endif; ?>

        <?php card_end(); ?>
    <?php endif; ?>
</div>

<?php
// 輸出底部導航
bottom_tab_navigation();

// 頁面結束
html_end();
?>
