<?php
/**
 * 編輯客戶頁面
 * Edit Customer Page
 *
 * @version v2.0.0
 * @description 編輯客戶資訊表單頁面
 * @遵循憲法原則I: 安全優先開發 - XSS防護、CSRF驗證、PDO預處理
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
$success = '';
$customer = null;

// 獲取客戶資訊
try {
    $customer = get_customer($customer_id);

    if (!$customer) {
        header('Location: /customers/?error=' . urlencode('客戶不存在'));
        exit;
    }
} catch (Exception $e) {
    error_log("Get customer error: " . $e->getMessage());
    $error = '載入客戶資訊失敗';
}

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

        // 更新客戶
        $result = update_customer($customer_id, $data);

        if ($result['success']) {
            // 成功，重定向到列表頁
            header('Location: /customers/?success=' . urlencode('客戶資訊更新成功'));
            exit;
        } else {
            $error = $result['error'];
            $customer = array_merge($customer, $data);
        }
    }
} else {
    // GET請求，使用資料庫中的客戶資訊
    // $customer 已經在上面獲取了
}

// 頁面開始
html_start('編輯客戶');

// 輸出頭部
page_header('編輯客戶', [
    ['label' => '首頁', 'url' => '/'],
    ['label' => '客戶管理', 'url' => '/customers/'],
    ['label' => '編輯客戶', 'url' => '/customers/edit.php?id=' . $customer_id]
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

    <?php if ($customer): ?>
        <?php card_start('編輯客戶資訊'); ?>

        <form method="POST" action="/customers/edit.php?id=<?php echo $customer_id; ?>">
            <?php echo csrf_input(); ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                <!-- 基本資訊 -->
                <div>
                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">基本資訊</h3>

                    <?php
                    form_field('name', '客戶名稱', 'text', [], [
                        'required' => true,
                        'placeholder' => '請輸入客戶名稱',
                        'value' => $customer['name'] ?? ''
                    ]);
                    ?>

                    <?php
                    form_field('tax_id', '稅務登記號', 'text', [], [
                        'placeholder' => '請輸入8位稅務登記號',
                        'value' => $customer['tax_id'] ?? ''
                    ]);
                    ?>

                    <?php
                    form_field('email', '郵箱', 'email', [], [
                        'placeholder' => '請輸入郵箱地址',
                        'value' => $customer['email'] ?? ''
                    ]);
                    ?>

                    <?php
                    form_field('phone', '電話', 'text', [], [
                        'placeholder' => '請輸入聯絡電話',
                        'value' => $customer['phone'] ?? ''
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
                        'value' => $customer['billing_address'] ?? ''
                    ]);
                    ?>

                    <?php
                    form_field('shipping_address', '收貨地址', 'textarea', [], [
                        'placeholder' => '請輸入收貨地址',
                        'rows' => 3,
                        'value' => $customer['shipping_address'] ?? ''
                    ]);
                    ?>

                    <?php
                    form_field('note', '備註', 'textarea', [], [
                        'placeholder' => '請輸入備註資訊（可選）',
                        'rows' => 3,
                        'value' => $customer['note'] ?? ''
                    ]);
                    ?>
                </div>
            </div>

            <div style="margin-top: 32px; display: flex; gap: 12px; justify-content: space-between;">
                <div style="display: flex; gap: 12px;">
                    <a href="/customers/view.php?id=<?php echo $customer_id; ?>" class="btn btn-outline">檢視詳情</a>
                </div>
                <div style="display: flex; gap: 12px;">
                    <a href="/customers/" class="btn btn-secondary">取消</a>
                    <button type="submit" class="btn btn-primary">儲存更改</button>
                </div>
            </div>
        </form>

        <?php card_end(); ?>
    <?php endif; ?>
</div>

<?php
// 輸出底部導航
bottom_tab_navigation();

// 頁面結束
html_end();
?>
