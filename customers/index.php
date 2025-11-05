<?php
/**
 * 客户列表页面
 * Customer List Page
 *
 * @version v2.0.0
 * @description 客户管理列表页面，支持分页和搜索
 * @遵循宪法原则V: iOS风格用户体验
 * @遵循宪法原则I: XSS防护
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

// 获取请求参数
$page = max(1, intval($_GET['page'] ?? 1));
$limit = DEFAULT_PAGE_SIZE;
$search = trim($_GET['search'] ?? '');

try {
    // 获取客户列表
    $result = get_customers($page, $limit, $search);
    $customers = $result['data'];
    $total = $result['total'];
    $total_pages = $result['pages'];

} catch (Exception $e) {
    error_log("Customer list error: " . $e->getMessage());
    $customers = [];
    $total = 0;
    $total_pages = 0;
    $error = '加载客户列表失败';
}

// 页面开始
html_start('客户列表');

// 输出头部
page_header('客户管理', [
    ['label' => '首页', 'url' => '/'],
    ['label' => '客户管理', 'url' => '/customers/']
]);

?>

<div class="main-content">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <span class="alert-message"><?php echo h($_GET['success']); ?></span>
        </div>
    <?php endif; ?>

    <?php
    // 卡片容器
    card_start('客户列表', [
        ['label' => '新建客户', 'url' => '/customers/new.php', 'class' => 'btn-primary']
    ]);
    ?>

    <!-- 搜索表单 -->
    <div class="list-toolbar">
        <form method="GET" class="list-search">
            <input
                type="text"
                name="search"
                placeholder="客户名称、税务登记号或邮箱"
                value="<?php echo h($search); ?>"
            >
            <button type="submit" class="btn btn-secondary btn-compact">搜索</button>
            <?php if (!empty($search)): ?>
                <a href="/customers/" class="btn btn-outline btn-compact">清除</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($customers)): ?>
        <?php empty_state('暂无客户数据', '新建客户', '/customers/new.php'); ?>
    <?php else: ?>
        <!-- 客户列表 -->
        <div class="customer-list" style="display: grid; gap: 12px;">
            <?php foreach ($customers as $customer): ?>
                <div class="customer-card" style="border: 1px solid var(--border-light); border-radius: var(--border-radius-md); padding: 16px; background: var(--card-bg); transition: var(--transition);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <div style="flex: 1;">
                            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 8px; color: var(--text-primary);">
                                <a href="/customers/view.php?id=<?php echo $customer['id']; ?>" style="color: var(--text-primary); text-decoration: none;">
                                    <?php echo h($customer['name']); ?>
                                </a>
                            </h3>

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; font-size: 14px; color: var(--text-secondary);">
                                <?php if (!empty($customer['tax_id'])): ?>
                                    <div>
                                        <strong>税号:</strong> <?php echo h($customer['tax_id']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($customer['email'])): ?>
                                    <div>
                                        <strong>邮箱:</strong>
                                        <a href="mailto:<?php echo h($customer['email']); ?>" style="color: var(--primary-color);">
                                            <?php echo h($customer['email']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($customer['phone'])): ?>
                                    <div>
                                        <strong>电话:</strong>
                                        <a href="tel:<?php echo h($customer['phone']); ?>" style="color: var(--primary-color);">
                                            <?php echo h($customer['phone']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <div>
                                    <strong>创建日期:</strong> <?php echo format_date($customer['created_at']); ?>
                                </div>
                            </div>

                            <?php if (!empty($customer['note'])): ?>
                                <p style="margin-top: 8px; padding: 8px; background: var(--bg-secondary); border-radius: var(--border-radius-sm); font-size: 14px; color: var(--text-secondary);">
                                    <?php echo h(truncate_string($customer['note'], 100)); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; gap: 8px; margin-left: 16px;">
                            <a href="/customers/view.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline" title="查看详情">
                                查看
                            </a>
                            <a href="/customers/edit.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-primary" title="编辑客户">
                                编辑
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- 分页 -->
        <?php if ($total_pages > 1): ?>
            <?php echo generate_pagination($page, $total_pages, '/customers/'); ?>
        <?php endif; ?>

        <!-- 统计信息 -->
        <div style="margin-top: 16px; padding: 12px; background: var(--bg-secondary); border-radius: var(--border-radius-md); font-size: 14px; color: var(--text-secondary); text-align: center;">
            共 <?php echo number_format($total); ?> 位客户，第 <?php echo $page; ?>/<?php echo $total_pages; ?> 页
        </div>
    <?php endif; ?>

    <?php card_end(); ?>
</div>

<?php
// 输出底部导航
bottom_tab_navigation();

// 页面结束
html_end();
?>
