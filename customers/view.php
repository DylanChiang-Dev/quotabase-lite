<?php
/**
 * 查看客户页面
 * View Customer Page
 *
 * @version v2.0.0
 * @description 客户详情查看页面
 * @遵循宪法原则I: 安全优先开发 - XSS防护
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
$customer = null;

// 获取客户信息
try {
    $customer = get_customer($customer_id);

    if (!$customer) {
        header('Location: /customers/?error=' . urlencode('客户不存在'));
        exit;
    }

    // 获取客户的报价单数量
    $org_id = get_current_org_id();
    $quote_count = dbQueryOne(
        "SELECT COUNT(*) as count FROM quotes WHERE customer_id = ? AND org_id = ?",
        [$customer_id, $org_id]
    );
    $customer['quote_count'] = $quote_count['count'];

} catch (Exception $e) {
    error_log("Get customer error: " . $e->getMessage());
    $error = '加载客户信息失败';
}

// 页面开始
html_start('查看客户');

// 输出头部
page_header('客户详情', [
    ['label' => '首页', 'url' => '/'],
    ['label' => '客户管理', 'url' => '/customers/'],
    ['label' => '客户详情', 'url' => '/customers/view.php?id=' . $customer_id]
]);

?>

<div class="main-content">
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($customer): ?>
        <?php card_start('客户信息', [
            ['label' => '编辑客户', 'url' => '/customers/edit.php?id=' . $customer_id, 'class' => 'btn-primary']
        ]); ?>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 32px;">
            <!-- 基本信息 -->
            <div>
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color: var(--primary-color);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    基本信息
                </h3>

                <div style="display: grid; gap: 16px;">
                    <div class="info-item">
                        <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">客户名称</label>
                        <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                            <?php echo h($customer['name']); ?>
                        </div>
                    </div>

                    <?php if (!empty($customer['tax_id'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">税务登记号</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <?php echo h($customer['tax_id']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($customer['email'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">邮箱地址</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <a href="mailto:<?php echo h($customer['email']); ?>" style="color: var(--primary-color); text-decoration: none;">
                                    <?php echo h($customer['email']); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($customer['phone'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">联系电话</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <a href="tel:<?php echo h($customer['phone']); ?>" style="color: var(--primary-color); text-decoration: none;">
                                    <?php echo h($customer['phone']); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="info-item">
                        <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">创建日期</label>
                        <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                            <?php echo format_datetime($customer['created_at']); ?>
                        </div>
                    </div>

                    <?php if (!empty($customer['updated_at'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">最后更新</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <?php echo format_datetime($customer['updated_at']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 地址信息 -->
            <div>
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color: var(--primary-color);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    地址信息
                </h3>

                <div style="display: grid; gap: 16px;">
                    <?php if (!empty($customer['billing_address'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">账单地址</label>
                            <div style="font-size: 16px; color: var(--text-secondary); padding: 8px 12px; background: var(--bg-secondary); border-radius: var(--border-radius-md); line-height: 1.5;">
                                <?php echo h($customer['billing_address']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($customer['shipping_address'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">收货地址</label>
                            <div style="font-size: 16px; color: var(--text-secondary); padding: 8px 12px; background: var(--bg-secondary); border-radius: var(--border-radius-md); line-height: 1.5;">
                                <?php echo h($customer['shipping_address']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($customer['billing_address']) && empty($customer['shipping_address'])): ?>
                        <div style="font-size: 14px; color: var(--text-tertiary); padding: 16px; background: var(--bg-secondary); border-radius: var(--border-radius-md); text-align: center;">
                            暂无地址信息
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 备注信息 -->
        <?php if (!empty($customer['note'])): ?>
            <div style="margin-top: 32px;">
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color: var(--primary-color);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    备注
                </h3>
                <div style="font-size: 16px; color: var(--text-secondary); padding: 12px; background: var(--bg-secondary); border-radius: var(--border-radius-md); line-height: 1.6;">
                    <?php echo nl2br(h($customer['note'])); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 统计信息 -->
        <div style="margin-top: 32px; padding: 16px; background: var(--bg-secondary); border-radius: var(--border-radius-md); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; gap: 24px; align-items: center;">
                <div>
                    <div style="font-size: 24px; font-weight: 600; color: var(--primary-color);">
                        <?php echo number_format($customer['quote_count']); ?>
                    </div>
                    <div style="font-size: 14px; color: var(--text-tertiary);">关联报价单</div>
                </div>
            </div>
            <div style="font-size: 14px; color: var(--text-tertiary);">
                客户ID: <?php echo $customer_id; ?>
            </div>
        </div>

        <?php card_end(); ?>
    <?php endif; ?>
</div>

<?php
// 输出底部导航
bottom_tab_navigation();

// 页面结束
html_end();
?>
