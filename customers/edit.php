<?php
/**
 * 编辑客户页面
 * Edit Customer Page
 *
 * @version v2.0.0
 * @description 编辑客户信息表单页面
 * @遵循宪法原则I: 安全优先开发 - XSS防护、CSRF验证、PDO预处理
 */

// 防止直接访问
define('QUOTABASE_SYSTEM', true);

// 加载配置和依赖
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../partials/ui.php';

// 检查登录
if (!is_logged_in()) {
    header('Location: /login.php');
    exit;
}

// 获取客户ID
$customer_id = intval($_GET['id'] ?? 0);

if ($customer_id <= 0) {
    header('Location: /customers/?error=' . urlencode('无效的客户ID'));
    exit;
}

$error = '';
$success = '';
$customer = null;

// 获取客户信息
try {
    $customer = get_customer($customer_id);

    if (!$customer) {
        header('Location: /customers/?error=' . urlencode('客户不存在'));
        exit;
    }
} catch (Exception $e) {
    error_log("Get customer error: " . $e->getMessage());
    $error = '加载客户信息失败';
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '无效的请求，请重新提交。';
    } else {
        // 准备数据
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'tax_id' => trim($_POST['tax_id'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'billing_address' => trim($_POST['billing_address'] ?? ''),
            'shipping_address' => trim($_POST['shipping_address'] ?? ''),
            'note' => trim($_POST['note'] ?? '')
        ];

        // 更新客户
        $result = update_customer($customer_id, $data);

        if ($result['success']) {
            // 成功，重定向到列表页
            header('Location: /customers/?success=' . urlencode('客户信息更新成功'));
            exit;
        } else {
            $error = $result['error'];
            $customer = array_merge($customer, $data);
        }
    }
} else {
    // GET请求，使用数据库中的客户信息
    // $customer 已经在上面获取了
}

// 页面开始
html_start('编辑客户');

// 输出头部
page_header('编辑客户', [
    ['label' => '首页', 'url' => '/'],
    ['label' => '客户管理', 'url' => '/customers/'],
    ['label' => '编辑客户', 'url' => '/customers/edit.php?id=' . $customer_id]
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
        <?php card_start('编辑客户信息'); ?>

        <form method="POST" action="/customers/edit.php?id=<?php echo $customer_id; ?>">
            <?php echo csrf_input(); ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                <!-- 基本信息 -->
                <div>
                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">基本信息</h3>

                    <?php
                    form_field('name', '客户名称', 'text', [], [
                        'required' => true,
                        'placeholder' => '请输入客户名称',
                        'value' => $customer['name'] ?? ''
                    ]);
                    ?>

                    <?php
                    form_field('tax_id', '税务登记号', 'text', [], [
                        'placeholder' => '请输入8位税务登记号',
                        'value' => $customer['tax_id'] ?? ''
                    ]);
                    ?>

                    <?php
                    form_field('email', '邮箱', 'email', [], [
                        'placeholder' => '请输入邮箱地址',
                        'value' => $customer['email'] ?? ''
                    ]);
                    ?>

                    <?php
                    form_field('phone', '电话', 'text', [], [
                        'placeholder' => '请输入联系电话',
                        'value' => $customer['phone'] ?? ''
                    ]);
                    ?>
                </div>

                <!-- 地址信息 -->
                <div>
                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">地址信息</h3>

                    <?php
                    form_field('billing_address', '账单地址', 'textarea', [], [
                        'placeholder' => '请输入账单地址',
                        'rows' => 3,
                        'value' => $customer['billing_address'] ?? ''
                    ]);
                    ?>

                    <?php
                    form_field('shipping_address', '收货地址', 'textarea', [], [
                        'placeholder' => '请输入收货地址',
                        'rows' => 3,
                        'value' => $customer['shipping_address'] ?? ''
                    ]);
                    ?>

                    <?php
                    form_field('note', '备注', 'textarea', [], [
                        'placeholder' => '请输入备注信息（可选）',
                        'rows' => 3,
                        'value' => $customer['note'] ?? ''
                    ]);
                    ?>
                </div>
            </div>

            <div style="margin-top: 32px; display: flex; gap: 12px; justify-content: space-between;">
                <div style="display: flex; gap: 12px;">
                    <a href="/customers/view.php?id=<?php echo $customer_id; ?>" class="btn btn-outline">查看详情</a>
                </div>
                <div style="display: flex; gap: 12px;">
                    <a href="/customers/" class="btn btn-secondary">取消</a>
                    <button type="submit" class="btn btn-primary">保存更改</button>
                </div>
            </div>
        </form>

        <?php card_end(); ?>
    <?php endif; ?>
</div>

<?php
// 输出底部导航
bottom_tab_navigation();

// 页面结束
html_end();
?>
