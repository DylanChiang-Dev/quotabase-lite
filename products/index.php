<?php
/**
 * 产品列表页面
 * Products List Page
 *
 * @version v2.0.0
 * @description 产品目录列表页面，支持分页和搜索
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

// 获取查询参数
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$search = trim($_GET['search'] ?? '');
$offset = ($page - 1) * $limit;

// 获取产品列表
try {
    $result = get_catalog_items('product', $page, $limit, $search);
    $products = $result['data'];
    $total = $result['total'];
    $total_pages = $result['pages'];
    $current_page = $result['current_page'];
    $category_paths = get_catalog_category_paths(array_column($products, 'category_id'));
} catch (Exception $e) {
    error_log("Get products error: " . $e->getMessage());
    $products = [];
    $total = 0;
    $total_pages = 0;
    $current_page = 1;
    $error = '加载产品列表失败';
    $category_paths = [];
}

// 页面开始
html_start('产品管理');

// 输出头部
page_header('产品管理', [
    ['label' => '首页', 'url' => '/'],
    ['label' => '产品管理', 'url' => '/products/']
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

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($_GET['error']); ?></span>
        </div>
    <?php endif; ?>

    <?php card_start('产品目录'); ?>

    <!-- 工具栏 -->
    <div class="list-toolbar">
        <form method="GET" action="/products/index.php" class="list-search">
            <input
                type="text"
                name="search"
                placeholder="搜索 SKU 或产品名称..."
                value="<?php echo h($search); ?>"
            >
            <button type="submit" class="btn btn-secondary btn-compact">搜索</button>
            <?php if (!empty($search)): ?>
                <a href="/products/" class="btn btn-outline btn-compact">清除</a>
            <?php endif; ?>
        </form>

        <div class="list-actions">
            <a href="/products/new.php" class="btn btn-primary btn-compact list-primary-action">新建产品</a>
        </div>
    </div>

    <!-- 统计信息 -->
    <div style="margin-bottom: 24px; padding: 16px; background: var(--bg-secondary); border-radius: var(--border-radius-md); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; gap: 24px; align-items: center;">
            <div>
                <div style="font-size: 24px; font-weight: 600; color: var(--primary-color);">
                    <?php echo number_format($total); ?>
                </div>
                <div style="font-size: 14px; color: var(--text-tertiary);">产品总数</div>
            </div>
            <?php if (!empty($search)): ?>
                <div style="font-size: 14px; color: var(--text-secondary);">
                    搜索: "<?php echo h($search); ?>"
                </div>
            <?php endif; ?>
        </div>
        <div style="font-size: 14px; color: var(--text-tertiary);">
            第 <?php echo $current_page; ?> 页，共 <?php echo $total_pages; ?> 页
        </div>
    </div>

    <!-- 产品列表 -->
    <?php if (empty($products)): ?>
        <div style="text-align: center; padding: 48px 24px; color: var(--text-tertiary);">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin: 0 auto 16px; color: var(--text-tertiary); opacity: 0.5;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <?php if (!empty($search)): ?>
                <div style="font-size: 16px;">未找到匹配的产品</div>
                <div style="font-size: 14px; margin-top: 4px;">请尝试其他搜索关键词</div>
            <?php else: ?>
                <div style="font-size: 16px;">暂无产品</div>
                <div style="font-size: 14px; margin-top: 4px;">点击上方按钮创建第一个产品</div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: var(--bg-secondary); border-bottom: 2px solid var(--border-color);">
                    <tr>
                        <th style="padding: 12px; text-align: left; font-size: 14px; font-weight: 600; color: var(--text-secondary); width: 26%;">分类</th>
                        <th style="padding: 12px; text-align: left; font-size: 14px; font-weight: 600; color: var(--text-secondary);">产品名称</th>
                        <th style="padding: 12px; text-align: left; font-size: 14px; font-weight: 600; color: var(--text-secondary);">单位</th>
                        <th style="padding: 12px; text-align: right; font-size: 14px; font-weight: 600; color: var(--text-secondary);">单价</th>
                        <th style="padding: 12px; text-align: right; font-size: 14px; font-weight: 600; color: var(--text-secondary);">税率</th>
                        <th style="padding: 12px; text-align: center; font-size: 14px; font-weight: 600; color: var(--text-secondary);">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr style="border-bottom: 1px solid var(--border-color); hover: var(--bg-secondary);">
                            <td style="padding: 16px 12px;">
                                <?php
                                $path = '';
                                if (!empty($product['category_id'])) {
                                    $path = $category_paths[$product['category_id']] ?? get_catalog_category_path($product['category_id']);
                                }
                                ?>
                                <div style="font-size: 13px; color: var(--text-tertiary);" title="<?php echo h($path ?: '未分类'); ?>">
                                    <?php echo $path ? h($path) : '<span style="color: var(--text-secondary);">未分类</span>'; ?>
                                </div>
                            </td>
                            <td style="padding: 16px 12px;">
                                <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);">
                                    <a href="/products/edit.php?id=<?php echo h($product['id']); ?>" style="color: inherit; text-decoration: none;">
                                        <?php echo h($product['name']); ?>
                                    </a>
                                </div>
                                <div style="margin-top: 6px; font-size: 12px; color: var(--text-tertiary); display: flex; gap: 12px; flex-wrap: wrap;">
                                    <span>SKU：<?php echo h($product['sku']); ?></span>
                                    <span>创建于：<?php echo format_date($product['created_at']); ?></span>
                                </div>
                            </td>
                            <td style="padding: 16px 12px;">
                                <div style="font-size: 14px; color: var(--text-secondary);">
                                    <?php echo h(UNITS[$product['unit']] ?? $product['unit']); ?>
                                </div>
                            </td>
                            <td style="padding: 16px 12px; text-align: right;">
                                <div style="font-size: 15px; font-weight: 600; color: var(--text-primary);">
                                    <?php echo format_currency_cents($product['unit_price_cents']); ?>
                                </div>
                            </td>
                            <td style="padding: 16px 12px; text-align: right;">
                                <div style="font-size: 14px; color: var(--text-secondary);">
                                    <?php echo number_format($product['tax_rate'], 2); ?>%
                                </div>
                            </td>
                            <td style="padding: 16px 12px; text-align: center;">
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <a
                                        href="/products/edit.php?id=<?php echo $product['id']; ?>"
                                        class="btn btn-sm btn-outline"
                                        title="编辑"
                                    >
                                        编辑
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- 分页 -->
    <?php if ($total_pages > 1): ?>
        <div style="margin-top: 24px; display: flex; justify-content: center;">
            <?php
            $base_url = '/products/index.php';
            if (!empty($search)) {
                $base_url .= '?search=' . urlencode($search);
            }
            echo generate_pagination($current_page, $total_pages, $base_url);
            ?>
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
