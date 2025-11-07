<?php
/**
 * 系統設定頁面
 * Settings Page
 *
 * @version v2.0.0
 * @description 系統設定管理頁面
 * @遵循憲法原則I: 安全優先開發 - XSS防護、CSRF驗證
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

$error = '';
$success = '';
$settings = null;

// 獲取當前設定
try {
    $settings = get_settings();
} catch (Exception $e) {
    error_log("Get settings error: " . $e->getMessage());
    $error = '載入設定失敗';
}

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 驗證CSRF令牌
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '無效的請求，請重新提交。';
    } else {
        // 準備資料
        $data = [
            'company_name' => trim($_POST['company_name'] ?? ''),
            'company_address' => trim($_POST['company_address'] ?? ''),
            'print_terms' => trim($_POST['print_terms'] ?? ''),
            'company_contact' => trim($_POST['company_contact'] ?? ''),
            'company_tax_id' => trim($_POST['company_tax_id'] ?? '')
        ];

        // 更新設定
        $result = update_settings($data);

        if ($result['success']) {
            $success = $result['message'];
            // 重新載入設定
            $settings = get_settings();
        } else {
            $error = $result['error'];
        }
    }
}

// 頁面開始
html_start('系統設定');

// 輸出頭部
page_header('系統設定', [
    ['label' => '首頁', 'url' => '/'],
    ['label' => '系統設定', 'url' => '/settings/']
]);

?>

<div class="main-content">
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <span class="alert-message"><?php echo h($success); ?></span>
        </div>
    <?php endif; ?>

    <?php card_start('賬號與安全'); ?>
        <p style="margin-bottom: 16px; color: var(--text-secondary);">
            建議定期更新管理員密碼，併為團隊成員建立獨立賬號以便追蹤操作記錄。
        </p>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <a href="/settings/account.php" class="btn btn-primary">修改密碼 / 帳號資訊</a>
            <?php if (($_SESSION['user_role'] ?? 'staff') === 'admin'): ?>
                <a href="/settings/users.php" class="btn btn-secondary">使用者管理</a>
            <?php endif; ?>
        </div>
    <?php card_end(); ?>

    <?php card_start('系統設定'); ?>

    <form method="POST" action="/settings/index.php">
        <?php echo csrf_input(); ?>

        <!-- 公司資訊 -->
        <div style="margin-bottom: 32px;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color: var(--primary-color);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                公司資訊
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;">
                <?php
                form_field('company_name', '公司名稱', 'text', [], [
                    'required' => true,
                    'placeholder' => '請輸入公司名稱',
                    'value' => $settings['company_name'] ?? ''
                ]);

                form_field('company_contact', '聯絡電話', 'text', [], [
                    'placeholder' => '例：(02)1234-5678',
                    'value' => $settings['company_contact'] ?? '',
                    'help' => '建議填寫主要聯絡電話（50字內）'
                ]);

                form_field('company_address', '公司地址', 'text', [], [
                    'placeholder' => '請輸入公司地址',
                    'value' => $settings['company_address'] ?? ''
                ]);

                form_field('company_tax_id', '統一編號', 'text', [], [
                    'placeholder' => '例：12345678',
                    'value' => $settings['company_tax_id'] ?? '',
                    'help' => '最多50字，若無可留空'
                ]);
                ?>
            </div>
        </div>

        <!-- 列印設定 -->
        <div style="margin-bottom: 32px;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color: var(--primary-color);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                列印設定
            </h3>

            <?php
            form_field('print_terms', '列印條款', 'textarea', [], [
                'placeholder' => '請輸入列印條款，如付款方式、交付時間等',
                'rows' => 5,
                'value' => $settings['print_terms'] ?? '',
                'help' => '條款將顯示在報價單列印版本的底部'
            ]);
            ?>
        </div>

        <!-- 預覽區域 -->
            <div style="margin-bottom: 32px; padding: 20px; background: var(--bg-secondary); border-radius: var(--border-radius-md);">
                <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">設定預覽</h4>

                <div style="display: grid; gap: 12px; font-size: 14px;">
                    <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">公司名稱：</span>
                    <span style="font-weight: 600; color: var(--text-primary);"><?php echo h($settings['company_name'] ?? '未設定'); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">編號字首：</span>
                    <span style="font-weight: 600; color: var(--text-primary);">
                        Q-<span style="color: var(--text-tertiary);">[自動編號]</span>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">列印條款：</span>
                    <span style="font-weight: 600; color: var(--text-primary);">
                        <?php echo !empty($settings['print_terms']) ? '已設定' : '未設定'; ?>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">聯絡方式：</span>
                    <span style="font-weight: 600; color: var(--text-primary);">
                        <?php echo !empty($settings['company_contact']) ? h($settings['company_contact']) : '未設定'; ?>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">統一編號：</span>
                    <span style="font-weight: 600; color: var(--text-primary);">
                        <?php echo !empty($settings['company_tax_id']) ? h($settings['company_tax_id']) : '未設定'; ?>
                    </span>
                </div>
            </div>

            <div style="margin-top: 16px; padding: 12px; background: var(--bg-primary); border-radius: var(--border-radius-sm); border-left: 4px solid var(--primary-color);">
                <div style="font-size: 13px; color: var(--text-secondary); line-height: 1.6;">
                    <strong>預覽示例：</strong><br>
                    報價單號：Q-2025001<br>
                    客戶資訊將在此處顯示<br>
                    報價專案明細...<br>
                    <?php if (!empty($settings['print_terms'])): ?>
                        <br><strong>條款：</strong><br>
                        <?php echo nl2br(h($settings['print_terms'])); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="margin-top: 32px; display: flex; gap: 12px; justify-content: flex-end;">
            <a href="/" class="btn btn-secondary">返回首頁</a>
            <button type="submit" class="btn btn-primary">儲存設定</button>
        </div>
    </form>

    <?php card_end(); ?>
</div>

<?php
// 輸出底部導航
bottom_tab_navigation();

// 頁面結束
html_end();
?>
