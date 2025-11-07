<?php
/**
 * 客戶列表頁面
 * Customer List Page
 *
 * @version v2.0.0
 * @description 客戶管理列表頁面，支援分頁和搜尋
 * @遵循憲法原則V: iOS風格使用者體驗
 * @遵循憲法原則I: XSS防護
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

// 獲取請求引數
$page = max(1, intval($_GET['page'] ?? 1));
$limit = DEFAULT_PAGE_SIZE;
$search = trim($_GET['search'] ?? '');

try {
    // 獲取客戶列表
    $result = get_customers($page, $limit, $search);
    $customers = $result['data'];
    $total = $result['total'];
    $total_pages = $result['pages'];

} catch (Exception $e) {
    error_log("Customer list error: " . $e->getMessage());
    $customers = [];
    $total = 0;
    $total_pages = 0;
    $error = '載入客戶列表失敗';
}

// 頁面開始
html_start('客戶列表');

// 輸出頭部
page_header('客戶管理', [
    ['label' => '首頁', 'url' => '/'],
    ['label' => '客戶管理', 'url' => '/customers/']
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
    card_start('客戶列表', [
        ['label' => '新建客戶', 'url' => '/customers/new.php', 'class' => 'btn-primary']
    ]);
    ?>

    <!-- 搜尋表單 -->
    <div class="list-toolbar">
        <form method="GET" class="list-search">
            <input
                type="text"
                name="search"
                placeholder="客戶名稱、稅務登記號或郵箱"
                value="<?php echo h($search); ?>"
            >
            <button type="submit" class="btn btn-secondary btn-compact">搜尋</button>
            <?php if (!empty($search)): ?>
                <a href="/customers/" class="btn btn-outline btn-compact">清除</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($customers)): ?>
        <?php empty_state('暫無客戶資料', '新建客戶', '/customers/new.php'); ?>
    <?php else: ?>
        <!-- 客戶列表 -->
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
                                        <strong>稅號:</strong> <?php echo h($customer['tax_id']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($customer['email'])): ?>
                                    <div>
                                        <strong>郵箱:</strong>
                                        <a href="mailto:<?php echo h($customer['email']); ?>" style="color: var(--primary-color);">
                                            <?php echo h($customer['email']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($customer['phone'])): ?>
                                    <div>
                                        <strong>電話:</strong>
                                        <a href="tel:<?php echo h($customer['phone']); ?>" style="color: var(--primary-color);">
                                            <?php echo h($customer['phone']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <div>
                                    <strong>建立日期:</strong> <?php echo format_date($customer['created_at']); ?>
                                </div>
                            </div>

                            <?php if (!empty($customer['note'])): ?>
                                <p style="margin-top: 8px; padding: 8px; background: var(--bg-secondary); border-radius: var(--border-radius-sm); font-size: 14px; color: var(--text-secondary);">
                                    <?php echo h(truncate_string($customer['note'], 100)); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; gap: 8px; margin-left: 16px;">
                            <a href="/customers/view.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline" title="檢視詳情">
                                檢視
                            </a>
                            <a href="/customers/edit.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-primary" title="編輯客戶">
                                編輯
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- 分頁 -->
        <?php if ($total_pages > 1): ?>
            <?php echo generate_pagination($page, $total_pages, '/customers/'); ?>
        <?php endif; ?>

        <!-- 統計資訊 -->
        <div style="margin-top: 16px; padding: 12px; background: var(--bg-secondary); border-radius: var(--border-radius-md); font-size: 14px; color: var(--text-secondary); text-align: center;">
            共 <?php echo number_format($total); ?> 位客戶，第 <?php echo $page; ?>/<?php echo $total_pages; ?> 頁
        </div>
    <?php endif; ?>

    <?php card_end(); ?>
</div>

<?php
// 輸出底部導航
bottom_tab_navigation();

// 頁面結束
html_end();
?>
