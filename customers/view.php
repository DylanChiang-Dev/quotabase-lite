<?php
/**
 * 檢視客戶頁面
 * View Customer Page
 *
 * @version v2.0.0
 * @description 客戶詳情檢視頁面
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

// 獲取客戶ID
$customer_id = intval($_GET['id'] ?? 0);

if ($customer_id <= 0) {
    header('Location: /customers/?error=' . urlencode('無效的客戶ID'));
    exit;
}

$error = '';
$customer = null;

// 獲取客戶資訊
try {
    $customer = get_customer($customer_id);

    if (!$customer) {
        header('Location: /customers/?error=' . urlencode('客戶不存在'));
        exit;
    }

    // 獲取客戶的報價單數量
    $org_id = get_current_org_id();
    $quote_count = dbQueryOne(
        "SELECT COUNT(*) as count FROM quotes WHERE customer_id = ? AND org_id = ?",
        [$customer_id, $org_id]
    );
    $customer['quote_count'] = $quote_count['count'];

} catch (Exception $e) {
    error_log("Get customer error: " . $e->getMessage());
    $error = '載入客戶資訊失敗';
}

// 頁面開始
html_start('檢視客戶');

// 輸出頭部
page_header('客戶詳情', [
    ['label' => '首頁', 'url' => '/'],
    ['label' => '客戶管理', 'url' => '/customers/'],
    ['label' => '客戶詳情', 'url' => '/customers/view.php?id=' . $customer_id]
]);

?>

<div class="main-content">
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($customer): ?>
        <?php card_start('客戶資訊', [
            ['label' => '編輯客戶', 'url' => '/customers/edit.php?id=' . $customer_id, 'class' => 'btn-primary']
        ]); ?>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 32px;">
            <!-- 基本資訊 -->
            <div>
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color: var(--primary-color);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    基本資訊
                </h3>

                <div style="display: grid; gap: 16px;">
                    <div class="info-item">
                        <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">客戶名稱</label>
                        <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                            <?php echo h($customer['name']); ?>
                        </div>
                    </div>

                    <?php if (!empty($customer['tax_id'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">稅務登記號</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <?php echo h($customer['tax_id']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($customer['email'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">郵箱地址</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <a href="mailto:<?php echo h($customer['email']); ?>" style="color: var(--primary-color); text-decoration: none;">
                                    <?php echo h($customer['email']); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($customer['phone'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">聯絡電話</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <a href="tel:<?php echo h($customer['phone']); ?>" style="color: var(--primary-color); text-decoration: none;">
                                    <?php echo h($customer['phone']); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="info-item">
                        <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">建立日期</label>
                        <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                            <?php echo format_datetime($customer['created_at']); ?>
                        </div>
                    </div>

                    <?php if (!empty($customer['updated_at'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">最後更新</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <?php echo format_datetime($customer['updated_at']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 地址資訊 -->
            <div>
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color: var(--primary-color);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    地址資訊
                </h3>

                <div style="display: grid; gap: 16px;">
                    <?php if (!empty($customer['billing_address'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">賬單地址</label>
                            <div style="font-size: 16px; color: var(--text-secondary); padding: 8px 12px; background: var(--bg-secondary); border-radius: var(--border-radius-md); line-height: 1.5;">
                                <?php echo h($customer['billing_address']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($customer['shipping_address'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">收貨地址</label>
                            <div style="font-size: 16px; color: var(--text-secondary); padding: 8px 12px; background: var(--bg-secondary); border-radius: var(--border-radius-md); line-height: 1.5;">
                                <?php echo h($customer['shipping_address']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($customer['billing_address']) && empty($customer['shipping_address'])): ?>
                        <div style="font-size: 14px; color: var(--text-tertiary); padding: 16px; background: var(--bg-secondary); border-radius: var(--border-radius-md); text-align: center;">
                            暫無地址資訊
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 備註資訊 -->
        <?php if (!empty($customer['note'])): ?>
            <div style="margin-top: 32px;">
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color: var(--primary-color);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    備註
                </h3>
                <div style="font-size: 16px; color: var(--text-secondary); padding: 12px; background: var(--bg-secondary); border-radius: var(--border-radius-md); line-height: 1.6;">
                    <?php echo nl2br(h($customer['note'])); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 統計資訊 -->
        <div style="margin-top: 32px; padding: 16px; background: var(--bg-secondary); border-radius: var(--border-radius-md); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; gap: 24px; align-items: center;">
                <div>
                    <div style="font-size: 24px; font-weight: 600; color: var(--primary-color);">
                        <?php echo number_format($customer['quote_count']); ?>
                    </div>
                    <div style="font-size: 14px; color: var(--text-tertiary);">關聯報價單</div>
                </div>
            </div>
            <div style="font-size: 14px; color: var(--text-tertiary);">
                客戶ID: <?php echo $customer_id; ?>
            </div>
        </div>

        <?php card_end(); ?>
    <?php endif; ?>
</div>

<?php
// 輸出底部導航
bottom_tab_navigation();

// 頁面結束
html_end();
?>
