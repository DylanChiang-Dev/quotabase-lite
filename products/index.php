<?php
/**
 * 產品列表頁面
 * Products List Page
 *
 * @version v2.0.0
 * @description 產品目錄列表頁面，支援分頁和搜尋
 * @遵循憲法原則I: 安全優先開發 - XSS防護、CSRF驗證、PDO預處理
 */

// 防止直接訪問
define('QUOTABASE_SYSTEM', true);

// 載入配置和依賴
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../partials/ui.php';
require_once __DIR__ . '/../partials/catalog-import-ui.php';

// 檢查登入
if (!is_logged_in()) {
    header('Location: /login.php');
    exit;
}

$bulk_csrf_token = generate_csrf_token();

// 獲取查詢引數
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 100;
$search = trim($_GET['search'] ?? '');
$offset = ($page - 1) * $limit;

// 獲取產品列表
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
    $error = '載入產品列表失敗';
    $category_paths = [];
}

// 頁面開始
html_start('產品管理');

// 輸出頭部
page_header('產品管理', [
    ['label' => '首頁', 'url' => '/'],
    ['label' => '產品管理', 'url' => '/products/']
]);

?>

<div class="main-content" data-catalog-bulk-root data-bulk-type="product" data-bulk-endpoint="/api/catalog/bulk-delete.php">
    <input type="hidden" data-catalog-bulk-token value="<?php echo h($bulk_csrf_token); ?>">
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

    <?php card_start('產品目錄'); ?>

    <!-- 工具欄 -->
    <div class="list-toolbar">
        <form method="GET" action="/products/index.php" class="list-search">
            <input
                type="text"
                name="search"
                placeholder="搜尋 SKU 或產品名稱..."
                value="<?php echo h($search); ?>"
            >
            <button type="submit" class="btn btn-secondary btn-compact">搜尋</button>
            <?php if (!empty($search)): ?>
                <a href="/products/" class="btn btn-outline btn-compact">清除</a>
            <?php endif; ?>
        </form>

        <div class="list-actions">
            <button type="button" class="btn btn-danger btn-compact" data-bulk-delete-btn disabled>批量刪除</button>
            <span class="bulk-selection-indicator" data-selected-count hidden style="font-size: 13px; color: var(--text-secondary); margin-left: 8px;">已選擇 0 項</span>
            <?php render_catalog_import_ui('product'); ?>
            <a href="/products/new.php" class="btn btn-primary btn-compact list-primary-action">新建產品</a>
        </div>
    </div>

    <!-- 統計資訊 -->
    <div style="margin-bottom: 24px; padding: 16px; background: var(--bg-secondary); border-radius: var(--border-radius-md); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; gap: 24px; align-items: center;">
            <div>
                <div style="font-size: 24px; font-weight: 600; color: var(--primary-color);">
                    <?php echo number_format($total); ?>
                </div>
                <div style="font-size: 14px; color: var(--text-tertiary);">產品總數</div>
            </div>
            <?php if (!empty($search)): ?>
                <div style="font-size: 14px; color: var(--text-secondary);">
                    搜尋: "<?php echo h($search); ?>"
                </div>
            <?php endif; ?>
        </div>
        <div style="font-size: 14px; color: var(--text-tertiary);">
            第 <?php echo $current_page; ?> 頁，共 <?php echo $total_pages; ?> 頁
        </div>
    </div>

    <!-- 產品列表 -->
    <?php if (empty($products)): ?>
        <div style="text-align: center; padding: 48px 24px; color: var(--text-tertiary);">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin: 0 auto 16px; color: var(--text-tertiary); opacity: 0.5;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <?php if (!empty($search)): ?>
                <div style="font-size: 16px;">未找到匹配的產品</div>
                <div style="font-size: 14px; margin-top: 4px;">請嘗試其他搜尋關鍵詞</div>
            <?php else: ?>
                <div style="font-size: 16px;">暫無產品</div>
                <div style="font-size: 14px; margin-top: 4px;">點選上方按鈕建立第一個產品</div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: var(--bg-secondary); border-bottom: 2px solid var(--border-color);">
                    <tr>
                        <th style="padding: 12px; text-align: left; width: 48px;">
                            <input type="checkbox" data-catalog-select-all aria-label="全選產品">
                        </th>
                        <th style="padding: 12px; text-align: left; font-size: 14px; font-weight: 600; color: var(--text-secondary); width: 26%;">分類</th>
                        <th style="padding: 12px; text-align: left; font-size: 14px; font-weight: 600; color: var(--text-secondary);">產品名稱</th>
                        <th style="padding: 12px; text-align: left; font-size: 14px; font-weight: 600; color: var(--text-secondary);">單位</th>
                        <th style="padding: 12px; text-align: right; font-size: 14px; font-weight: 600; color: var(--text-secondary);">單價</th>
                        <th style="padding: 12px; text-align: right; font-size: 14px; font-weight: 600; color: var(--text-secondary);">稅率</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr style="border-bottom: 1px solid var(--border-color); hover: var(--bg-secondary);">
                            <td style="padding: 16px 12px;">
                                <input type="checkbox" value="<?php echo h($product['id']); ?>" data-catalog-select aria-label="選擇產品 #<?php echo h($product['sku']); ?>">
                            </td>
                            <td style="padding: 16px 12px;">
                                <?php
                                $path = '';
                                if (!empty($product['category_id'])) {
                                    $path = $category_paths[$product['category_id']] ?? get_catalog_category_path($product['category_id']);
                                }
                                ?>
                                <div style="font-size: 13px; color: var(--text-tertiary);" title="<?php echo h($path ?: '未分類'); ?>">
                                    <?php echo $path ? h($path) : '<span style="color: var(--text-secondary);">未分類</span>'; ?>
                                </div>
                            </td>
                            <td style="padding: 16px 12px;">
                                <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);">
                                    <a href="/products/edit.php?id=<?php echo h($product['id']); ?>" style="color: inherit; text-decoration: none;">
                                        <?php echo h($product['name']); ?>
                                    </a>
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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- 分頁 -->
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
echo '<script src="/assets/js/catalog-bulk-actions.js"></script>';
// 輸出底部導航
bottom_tab_navigation();

// 頁面結束
html_end();
?>
