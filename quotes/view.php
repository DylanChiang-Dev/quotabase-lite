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
require_once __DIR__ . '/../helpers/quote_consent.php';
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
$consents = [];
$latest_consent = null;
$receipt_record = null;
$latest_consent_token = null;
$active_consent_token = null;
$consent_public_url = '';
$consent_input_id = null;
$has_copy_button = false;

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

if ($quote) {
    $consents = get_receipt_consents($quote_id);
    $latest_consent = $consents[0] ?? null;
    $receipt_record = get_receipt_by_quote($quote_id);
    $latest_consent_token = get_latest_quote_consent_token($quote_id, (int)$quote['org_id']);
    $active_consent_token = get_active_quote_consent_token($quote_id, (int)$quote['org_id']);
    $consent_public_url = '';
    if ($active_consent_token) {
        $consent_public_url = quote_consent_token_url($active_consent_token);
    } elseif ($latest_consent_token) {
        $consent_public_url = quote_consent_token_url($latest_consent_token);
    }
}

// 處理狀態更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '無效的請求，請重新提交。';
    } else {
        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'update_status':
                $new_status = $_POST['status'] ?? '';
                $result = update_quote_status($quote_id, $new_status);

                if ($result['success']) {
                    header('Location: /quotes/view.php?id=' . $quote_id . '&success=' . urlencode('狀態更新成功'));
                    exit;
                } else {
                    $error = $result['error'];
                }
                break;

            case 'save_consent':
                $consent_payload = [
                    'method' => $_POST['consent_method'] ?? 'checkbox',
                    'consented_at' => $_POST['consented_at'] ?? '',
                    'counterparty_ip' => $_POST['counterparty_ip'] ?? '',
                    'evidence_ref' => $_POST['evidence_ref'] ?? '',
                    'notes' => $_POST['consent_notes'] ?? '',
                ];
                $result = create_receipt_consent($quote_id, $consent_payload);
                if ($result['success']) {
                    header('Location: /quotes/view.php?id=' . $quote_id . '&success=' . urlencode('電子同意紀錄已新增'));
                    exit;
                } else {
                    $error = $result['error'] ?? '新增電子同意紀錄失敗';
                }
                break;

            case 'generate_receipt':
                $selectedConsent = trim($_POST['consent_id'] ?? '');
                $consentId = $selectedConsent !== '' ? intval($selectedConsent) : null;
                $result = generate_personal_receipt($quote_id, $consentId);
                if ($result['success']) {
                    header('Location: /quotes/view.php?id=' . $quote_id . '&success=' . urlencode('個人收據已產出，可於下方開啟列印頁。'));
                    exit;
                } else {
                    $error = $result['error'] ?? '產出個人收據失敗';
                }
                break;

            default:
                $error = '未知的操作。';
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
            <div style="margin-bottom: 32px; padding: 20px; background: var(--bg-secondary); border-radius: var(--border-radius-md); display: flex; flex-direction: column; gap: 16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div>
                        <h4 style="font-size: 16px; font-weight: 600; color: var(--text-primary); margin:0;">狀態管理</h4>
                        <p style="font-size:13px; color:var(--text-tertiary); margin:4px 0 0;">於此更新報價單狀態</p>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-end;">
                        <span style="font-size:12px; color:var(--text-tertiary);">當前狀態</span>
                        <span><?php echo get_status_badge($quote['status']); ?></span>
                    </div>
                </div>
                <form method="POST" action="/quotes/view.php?id=<?php echo $quote_id; ?>" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="update_status">
                    <span style="font-size:13px; color:var(--text-secondary); min-width:60px;">更新為：</span>
                    <select name="status" style="padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); background: var(--bg-primary); color: var(--text-primary); min-width: 220px;">
                        <option value="">選擇新狀態</option>
                        <option value="sent">標記為已傳送</option>
                        <option value="accepted">標記為已接受</option>
                        <option value="rejected">標記為已拒絕</option>
                        <option value="expired">標記為已過期</option>
                    </select>
                    <button type="submit" class="btn btn-primary" style="height:42px;">更新狀態</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- 電子簽署連結 -->
        <div style="margin-bottom: 32px; padding: 20px; background: var(--bg-secondary); border-radius: var(--border-radius-md);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px;">
                <div>
                    <h4 style="font-size: 16px; font-weight: 600; margin: 0; color: var(--text-primary);">電子簽署連結</h4>
                    <p style="font-size: 13px; color: var(--text-tertiary); margin: 4px 0 0;">提供客戶線上確認或複製連結使用</p>
                </div>
                <?php if ($latest_consent_token): ?>
                    <div style="text-align: right; font-size: 13px; color: var(--text-secondary);">
                        <div>狀態：<?php echo h(quote_consent_status_label($latest_consent_token['status'])); ?></div>
                        <div>建立：<?php echo h(format_datetime($latest_consent_token['created_at'])); ?></div>
                        <?php if (!empty($latest_consent_token['expires_at'])): ?>
                            <div>到期：<?php echo h(format_datetime($latest_consent_token['expires_at'])); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($consent_public_url && $active_consent_token): ?>
                <?php
                    $consent_input_id = 'consent-link-' . $quote_id;
                    $has_copy_button = true;
                ?>
                <div style="margin-top: 16px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                    <input
                        type="text"
                        id="<?php echo h($consent_input_id); ?>"
                        value="<?php echo h($consent_public_url); ?>"
                        readonly
                        style="flex:1; min-width: 260px; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); background: var(--bg-primary); color: var(--text-primary); font-size: 14px;"
                    >
                    <button type="button" class="btn btn-secondary" data-copy-target="<?php echo h($consent_input_id); ?>">複製連結</button>
                    <a href="<?php echo h($consent_public_url); ?>" target="_blank" class="btn btn-outline">預覽簽署頁</a>
                </div>
                <p style="font-size: 12px; color: var(--text-tertiary); margin-top: 8px;">連結有效至 <?php echo h(format_datetime($active_consent_token['expires_at'] ?? '')); ?>，並僅能由客戶操作一次。</p>
            <?php elseif ($latest_consent_token): ?>
                <p style="font-size: 13px; color: var(--text-tertiary); margin-top: 16px;">
                    目前 token 狀態為「<?php echo h(quote_consent_status_label($latest_consent_token['status'])); ?>」。如需新的簽署連結，請重新輸出報價單或重新整理列印頁以生成新 QR Code。
                </p>
            <?php else: ?>
                <p style="font-size: 13px; color: var(--text-tertiary); margin-top: 16px;">
                    尚未產生電子簽署 QR。請先開啟列印頁 / 匯出 PDF 以建立首次 token。
                </p>
            <?php endif; ?>
        </div>

        <!-- 電子同意紀錄 -->
        <div style="margin-bottom: 32px; padding: 24px; background: var(--bg-secondary); border-radius: var(--border-radius-md);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 20px;">
                <div>
                    <h4 style="font-size: 16px; font-weight: 600; color: var(--text-primary); margin: 0;">電子同意紀錄</h4>
                    <p style="font-size: 13px; color: var(--text-tertiary); margin: 4px 0 0;">追蹤同意紀錄並保留稽核證據</p>
                </div>
                <?php if ($latest_consent): ?>
                    <div style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); padding: 10px 14px; font-size: 13px; color: var(--text-secondary); line-height: 1.5; min-width: 220px;">
                        <div style="font-size: 12px; color: var(--text-tertiary); letter-spacing: 0.4px;">最新紀錄</div>
                        <div style="font-weight: 600; color: var(--text-primary); margin: 2px 0;">
                            <?php echo h(format_datetime($latest_consent['consented_at'])); ?>
                        </div>
                        <div><?php echo h(receipt_method_label($latest_consent['method'])); ?></div>
                        <div style="font-size: 12px;">
                            IP：<?php echo h($latest_consent['counterparty_ip'] ?: '—'); ?> / <?php echo h($latest_consent['recorded_ip'] ?: '—'); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($consents)): ?>
                <div style="padding: 16px; background: var(--bg-primary); border: 1px dashed var(--border-color); border-radius: var(--border-radius-sm); color: var(--text-secondary); font-size: 14px; margin-bottom: 20px;">
                    尚未記錄任何電子同意。完成同意紀錄後才能產出個人收據。
                </div>
            <?php else: ?>
                <div class="table-responsive" style="margin-bottom: 20px;">
                    <table class="table" style="min-width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 150px;">時間</th>
                                <th style="width: 120px;">方式</th>
                                <th>備註 / 證據</th>
                                <th style="width: 200px;">IP / 記錄者</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consents as $consent): ?>
                                <tr>
                                    <td><?php echo h(format_datetime($consent['consented_at'])); ?></td>
                                    <td><?php echo h(receipt_method_label($consent['method'])); ?></td>
                                    <td style="font-size: 13px; color: var(--text-secondary);">
                                        <?php if (!empty($consent['evidence_ref'])): ?>
                                            <div>證據：<?php echo h($consent['evidence_ref']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($consent['notes'])): ?>
                                            <div>備註：<?php echo nl2br(h($consent['notes'])); ?></div>
                                        <?php endif; ?>
                                        <?php if (empty($consent['evidence_ref']) && empty($consent['notes'])): ?>
                                            <span>—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 13px; color: var(--text-secondary);">
                                        <div>相對人IP：<?php echo h($consent['counterparty_ip'] ?: '—'); ?></div>
                                        <div>記錄IP：<?php echo h($consent['recorded_ip'] ?: '—'); ?></div>
                                        <div>記錄者：<?php echo h($consent['recorded_by_username'] ?? '系統'); ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div style="border-top: 1px solid var(--border-color); padding-top: 20px; margin-top: 4px;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:16px;">
                    <div>
                        <strong style="display:block; font-size:14px; color:var(--text-primary);">新增電子同意</strong>
                        <span style="font-size:12px; color:var(--text-tertiary);">紀錄來源與時間以備審計追蹤</span>
                    </div>
                </div>
                <form method="POST" action="/quotes/view.php?id=<?php echo $quote_id; ?>" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px;">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="save_consent">
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label for="consent_method" style="font-weight:600;">同意方式</label>
                        <select
                            id="consent_method"
                            name="consent_method"
                            required
                            style="height:42px; padding:10px 12px; border:1px solid var(--border-color); border-radius:var(--border-radius-sm); background:var(--bg-primary); color:var(--text-primary);">
                            <option value="checkbox">頁面勾選</option>
                            <option value="email">Email 同意</option>
                            <option value="other">其他</option>
                        </select>
                        <span style="font-size:12px; color: var(--text-tertiary); display:block;">請依實際取得同意的管道選擇</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label for="consented_at" style="font-weight:600;">同意時間</label>
                        <input
                            type="datetime-local"
                            id="consented_at"
                            name="consented_at"
                            value="<?php echo h(date('Y-m-d\TH:i')); ?>"
                            required
                            style="height:42px; padding:10px 12px; border:1px solid var(--border-color); border-radius:var(--border-radius-sm); background:var(--bg-primary); color:var(--text-primary);">
                        <span style="font-size:12px; color: var(--text-tertiary); display:block;">預設為現在時間，可依實際情況調整</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label for="counterparty_ip" style="font-weight:600;">相對人IP（可選）</label>
                        <input
                            type="text"
                            id="counterparty_ip"
                            name="counterparty_ip"
                            value=""
                            style="height:42px; padding:10px 12px; border:1px solid var(--border-color); border-radius:var(--border-radius-sm); background:var(--bg-primary); color:var(--text-primary);">
                        <span style="font-size:12px; color: var(--text-tertiary); display:block;">若同意來自線上操作，建議填寫</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label for="evidence_ref" style="font-weight:600;">佐證資訊（例如 Email ID）</label>
                        <input
                            type="text"
                            id="evidence_ref"
                            name="evidence_ref"
                            value=""
                            style="height:42px; padding:10px 12px; border:1px solid var(--border-color); border-radius:var(--border-radius-sm); background:var(--bg-primary); color:var(--text-primary);">
                        <span style="font-size:12px; color: var(--text-tertiary); display:block;">可填寫郵件編號、檔案連結等</span>
                    </div>
                    <div style="grid-column: 1 / -1; display:flex; flex-direction:column; gap:6px;">
                        <label for="consent_notes" style="font-weight:600;">備註</label>
                        <textarea
                            id="consent_notes"
                            name="consent_notes"
                            rows="3"
                            style="padding:10px 12px; border:1px solid var(--border-color); border-radius:var(--border-radius-sm); background:var(--bg-primary); color:var(--text-primary); resize: vertical;"></textarea>
                        <span style="font-size:12px; color: var(--text-tertiary); display:block;">例如聯繫經過、附件位置等補充資訊</span>
                    </div>
                    <div style="grid-column: 1 / -1; display:flex; justify-content:flex-end;">
                        <button type="submit" class="btn btn-secondary">新增電子同意紀錄</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 個人收據 -->
        <div style="margin-bottom: 8px; padding: 24px; background: var(--bg-secondary); border-radius: var(--border-radius-md);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:20px;">
                <div>
                    <h4 style="font-size: 16px; font-weight: 600; color: var(--text-primary); margin:0;">個人收據</h4>
                    <p style="font-size: 13px; color: var(--text-tertiary); margin:4px 0 0;">列印頁會儲存於 storage/receipts/，並自動嵌入 QR / hash_short</p>
                </div>
                <?php if ($receipt_record): ?>
                    <span style="padding:4px 10px; border-radius:999px; font-size:12px; border:1px solid var(--border-color); color:<?php echo $receipt_record['status'] === 'issued' ? 'var(--success-color)' : 'var(--danger-color)'; ?>;">
                        <?php echo $receipt_record['status'] === 'issued' ? '已發行' : '已撤銷'; ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($receipt_record): ?>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:16px; margin-bottom: 18px;">
                    <div style="padding:12px; background: var(--bg-primary); border:1px solid var(--border-color); border-radius: var(--border-radius-sm);">
                        <div style="font-size:12px; color:var(--text-tertiary);">序號</div>
                        <div style="font-size:16px; font-weight:600; font-family:monospace; color:var(--text-primary);"><?php echo h($receipt_record['serial']); ?></div>
                    </div>
                    <div style="padding:12px; background: var(--bg-primary); border:1px solid var(--border-color); border-radius: var(--border-radius-sm);">
                        <div style="font-size:12px; color:var(--text-tertiary);">開立日期</div>
                        <div style="font-size:15px;"><?php echo h(format_date($receipt_record['issued_on'])); ?></div>
                    </div>
                    <div style="padding:12px; background: var(--bg-primary); border:1px solid var(--border-color); border-radius: var(--border-radius-sm);">
                        <div style="font-size:12px; color:var(--text-tertiary);">hash_short</div>
                        <div style="font-size:15px; font-weight:600; font-family:monospace;"><?php echo h($receipt_record['hash_short']); ?></div>
                    </div>
                    <div style="padding:12px; background: var(--bg-primary); border:1px solid var(--border-color); border-radius: var(--border-radius-sm);">
                        <div style="font-size:12px; color:var(--text-tertiary);">保存期限</div>
                        <div style="font-size:15px;"><?php echo h(format_date($receipt_record['expires_at'])); ?></div>
                    </div>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom: 20px;">
                    <a class="btn btn-secondary" href="/receipts/download.php?quote_id=<?php echo $quote_id; ?>" target="_blank" rel="noopener">開啟列印頁</a>
                    <?php if (!empty($receipt_record['qr_token'])): ?>
                        <a class="btn" href="<?php echo h(build_verify_url($receipt_record['serial'], $receipt_record['qr_token'])); ?>" target="_blank">開啟查驗頁</a>
                    <?php endif; ?>
                    <span style="align-self:center; font-size:13px; color:var(--text-tertiary);">token：<?php echo h(substr($receipt_record['qr_token'], 0, 12)); ?>...</span>
                </div>
            <?php else: ?>
                <div style="padding: 16px; background: var(--bg-primary); border: 1px dashed var(--border-color); border-radius: var(--border-radius-sm); color: var(--text-secondary); font-size: 14px; margin-bottom: 20px;">
                    尚未產出個人收據。請確認已有電子同意紀錄後再執行。
                </div>
            <?php endif; ?>

            <form method="POST" action="/quotes/view.php?id=<?php echo $quote_id; ?>" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px; align-items:end;">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="generate_receipt">
                <div>
                    <label for="consent_id">選擇電子同意</label>
                    <select id="consent_id" name="consent_id" <?php echo empty($consents) ? 'disabled' : ''; ?>>
                        <option value="">最新紀錄（<?php echo $latest_consent ? h(format_datetime($latest_consent['consented_at'])) : '無'; ?>）</option>
                        <?php foreach ($consents as $consent): ?>
                            <option value="<?php echo $consent['id']; ?>">
                                #<?php echo $consent['id']; ?> · <?php echo h(receipt_method_label($consent['method'])); ?> · <?php echo h(format_datetime($consent['consented_at'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary" <?php echo empty($consents) ? 'disabled' : ''; ?>>產出個人收據 (列印頁)</button>
                    <?php if ($receipt_record): ?>
                        <a class="btn btn-secondary" href="/verify?serial=<?php echo urlencode($receipt_record['serial']); ?>&token=<?php echo urlencode($receipt_record['qr_token']); ?>" target="_blank">查驗頁示範</a>
                    <?php endif; ?>
                </div>
            </form>
            <?php if (empty($consents)): ?>
                <p style="font-size: 13px; color: var(--danger-color); margin-top: 8px;">請先新增電子同意紀錄。</p>
            <?php endif; ?>
        </div>

        <?php if ($has_copy_button && $consent_input_id): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const copyBtn = document.querySelector('[data-copy-target="<?php echo h($consent_input_id); ?>"]');
                    if (!copyBtn) {
                        return;
                    }
                    copyBtn.addEventListener('click', function () {
                        const input = document.getElementById('<?php echo h($consent_input_id); ?>');
                        if (!input) {
                            return;
                        }
                        const original = copyBtn.textContent;
                        const selectAndCopy = function () {
                            input.select();
                            document.execCommand('copy');
                            copyBtn.textContent = '已複製';
                            setTimeout(function () {
                                copyBtn.textContent = original;
                            }, 2000);
                        };

                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(input.value).then(function () {
                                copyBtn.textContent = '已複製';
                                setTimeout(function () {
                                    copyBtn.textContent = original;
                                }, 2000);
                            }).catch(selectAndCopy);
                        } else {
                            selectAndCopy();
                        }
                    });
                });
            </script>
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
