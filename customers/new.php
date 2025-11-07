<?php
/**
 * 新建客戶頁面
 * Create New Customer Page
 *
 * @version v2.0.0
 * @description 新建客戶表單頁面
 * @遵循憲法原則I: 安全優先開發 - XSS防護、CSRF驗證
 * @遵循憲法原則I: PDO預處理防止SQL注入
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

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 驗證CSRF令牌
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '無效的請求，請重新提交。';
    } else {
        // 準備資料
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'tax_id' => trim($_POST['tax_id'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'billing_address' => trim($_POST['billing_address'] ?? ''),
            'shipping_address' => trim($_POST['shipping_address'] ?? ''),
            'note' => trim($_POST['note'] ?? '')
        ];

        // 建立客戶
        $result = create_customer($data);

        if ($result['success']) {
            // 成功，重定向到列表頁
            header('Location: /customers/?success=' . urlencode('客戶建立成功'));
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

// 頁面開始
html_start('新建客戶');

// 輸出頭部
page_header('新建客戶', [
    ['label' => '首頁', 'url' => '/'],
    ['label' => '客戶管理', 'url' => '/customers/'],
    ['label' => '新建客戶', 'url' => '/customers/new.php']
]);

?>

<div class="main-content">
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($error); ?></span>
        </div>
    <?php endif; ?>

    <?php card_start('新建客戶資訊'); ?>

    <form method="POST" action="/customers/new.php">
        <?php echo csrf_input(); ?>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
            <!-- 基本資訊 -->
            <div>
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">基本資訊</h3>

                <?php
                form_field('name', '客戶名稱', 'text', [], [
                    'required' => true,
                    'placeholder' => '請輸入客戶名稱',
                    'value' => $_POST['name'] ?? ''
                ]);
                ?>

                <?php
                form_field('tax_id', '稅務登記號', 'text', [], [
                    'placeholder' => '請輸入8位稅務登記號',
                    'value' => $_POST['tax_id'] ?? ''
                ]);
                ?>

                <?php
                form_field('email', '郵箱', 'email', [], [
                    'placeholder' => '請輸入郵箱地址',
                    'value' => $_POST['email'] ?? ''
                ]);
                ?>

                <?php
                form_field('phone', '電話', 'text', [], [
                    'placeholder' => '請輸入聯絡電話',
                    'value' => $_POST['phone'] ?? ''
                ]);
                ?>
            </div>

            <!-- 地址資訊 -->
            <div>
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">地址資訊</h3>

                <?php
                form_field('billing_address', '賬單地址', 'textarea', [], [
                    'placeholder' => '請輸入賬單地址',
                    'rows' => 3,
                    'value' => $_POST['billing_address'] ?? ''
                ]);
                ?>

                <?php
                form_field('shipping_address', '收貨地址', 'textarea', [], [
                    'placeholder' => '請輸入收貨地址',
                    'rows' => 3,
                    'value' => $_POST['shipping_address'] ?? ''
                ]);
                ?>

                <?php
                form_field('note', '備註', 'textarea', [], [
                    'placeholder' => '請輸入備註資訊（可選）',
                    'rows' => 3,
                    'value' => $_POST['note'] ?? ''
                ]);
                ?>
            </div>
        </div>

        <div style="margin-top: 32px; display: flex; gap: 12px; justify-content: flex-end;">
            <a href="/customers/" class="btn btn-secondary">取消</a>
            <button type="submit" class="btn btn-primary">建立客戶</button>
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
