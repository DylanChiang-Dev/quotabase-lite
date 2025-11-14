<?php
/**
 * 報價列表頁面
 * Quote List Page
 *
 * @version v2.0.0
 * @description 報價單列表頁面，支援分頁和狀態篩選
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

// 獲取查詢引數
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$status_filters = [
    '' => '所有狀態',
    'draft' => '草稿',
    'sent' => '已傳送',
    'accepted' => '已接受',
    'rejected' => '已拒絕',
    'expired' => '已過期'
];
$consent_storage_timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'Asia/Taipei';

// 獲取報價單列表
try {
    $result = get_quotes($page, $limit, $search, $status);
    $quotes = $result['data'];
    $total = $result['total'];
    $total_pages = $result['pages'];
    $current_page = $result['current_page'];
} catch (Exception $e) {
    $error_code = uniqid('quotes_', true);
    error_log(sprintf(
        '[%s] Get quotes error: %s | params=%s | trace=%s',
        $error_code,
        $e->getMessage(),
        json_encode(['page' => $page, 'search' => $search, 'status' => $status], JSON_UNESCAPED_UNICODE),
        $e->getTraceAsString()
    ));
    $quotes = [];
    $total = 0;
    $total_pages = 0;
    $current_page = 1;
    $error = '載入報價單列表失敗';
    $error_debug = [
        'code' => $error_code,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ];
}

// 頁面開始
html_start('報價管理');

// 輸出頭部
page_header('報價管理', [
    ['label' => '首頁', 'url' => '/'],
    ['label' => '報價管理', 'url' => '/quotes/']
]);

?>

<div class="main-content">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($error); ?></span>
        </div>
        <script>
            console.group('[QB] 報價單列表載入失敗');
            console.error('提示：', <?php echo json_encode($error, JSON_UNESCAPED_UNICODE); ?>);
            <?php if (isset($error_debug)): ?>
            console.error('詳細：', <?php echo json_encode($error_debug, JSON_UNESCAPED_UNICODE); ?>);
            console.error('如需反饋給 Codex，請複製以上 code 與 message。');
            debugger;
            <?php endif; ?>
            console.groupEnd();
        </script>
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

    <?php card_start('報價單列表'); ?>

    <!-- 工具欄 -->
    <div class="list-toolbar">
        <form method="GET" action="/quotes/index.php" class="list-search">
            <input
                type="text"
                name="search"
                placeholder="搜尋報價單號或客戶名稱..."
                value="<?php echo h($search); ?>"
            >
            <button type="submit" class="btn btn-secondary btn-compact">搜尋</button>
        </form>

        <div class="list-filters" role="toolbar" aria-label="報價狀態篩選">
            <?php foreach ($status_filters as $value => $label): ?>
                <?php
                    $query = [];
                    if ($search !== '') {
                        $query['search'] = $search;
                    }
                    if ($value !== '') {
                        $query['status'] = $value;
                    }
                    $query_string = http_build_query($query);
                    $url = '/quotes/index.php' . ($query_string ? '?' . $query_string : '');
                    $is_active = $status === $value || ($value === '' && $status === '');
                ?>
                <a
                    href="<?php echo h($url); ?>"
                    class="filter-pill <?php echo $is_active ? 'active' : ''; ?>"
                >
                    <?php echo h($label); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="list-actions">
            <a href="/quotes/new.php" class="btn btn-primary btn-compact list-primary-action">新建報價單</a>
        </div>
    </div>

    <!-- 統計資訊 -->
    <div style="margin-bottom: 24px; padding: 16px; background: var(--bg-secondary); border-radius: var(--border-radius-md); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; gap: 24px; align-items: center;">
            <div>
                <div style="font-size: 24px; font-weight: 600; color: var(--primary-color);">
                    <?php echo number_format($total); ?>
                </div>
                <div style="font-size: 14px; color: var(--text-tertiary);">報價單總數</div>
            </div>
            <?php if (!empty($status)): ?>
                <div style="font-size: 14px; color: var(--text-secondary);">
                    狀態: <?php echo get_status_label($status); ?>
                </div>
            <?php endif; ?>
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

    <!-- 報價單列表 -->
    <?php if (empty($quotes)): ?>
        <div style="text-align: center; padding: 48px 24px; color: var(--text-tertiary);">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin: 0 auto 16px; color: var(--text-tertiary); opacity: 0.5;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <?php if (!empty($search) || !empty($status)): ?>
                <div style="font-size: 16px;">未找到匹配的報價單</div>
                <div style="font-size: 14px; margin-top: 4px;">請嘗試調整篩選條件</div>
            <?php else: ?>
                <div style="font-size: 16px;">暫無報價單</div>
                <div style="font-size: 14px; margin-top: 4px;">點選上方按鈕建立第一個報價單</div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="quotes-table">
                <thead>
                    <tr>
                        <th class="col-number">報價單號</th>
                        <th class="col-customer">客戶</th>
                        <th class="col-status">狀態</th>
                        <th class="col-consent">電子簽署</th>
                        <th class="col-date">開票日期</th>
                        <th class="col-total">總金額</th>
                        <th class="col-actions">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotes as $quote): ?>
                        <tr>
                            <td class="col-number">
                                <span class="quote-number"><?php echo h($quote['quote_number']); ?></span>
                            </td>
                            <td class="col-customer">
                                <div class="quote-customer"><?php echo h($quote['customer_name']); ?></div>
                            </td>
                            <td class="col-status">
                                <?php echo get_status_badge($quote['status']); ?>
                            </td>
                            <td class="col-consent">
                                <?php if (!empty($quote['latest_consent_at'])): ?>
                                    <div style="font-size: 13px; color: var(--text-secondary);">
                                        <strong style="display:block; color: var(--text-primary);">已簽署</strong>
                                        <?php echo h(format_datetime($quote['latest_consent_at'], 'Y-m-d H:i', $consent_storage_timezone)); ?>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size: 13px; color: var(--text-tertiary);">尚未簽署</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-date">
                                <div class="quote-date"><?php echo format_date($quote['issue_date']); ?></div>
                                <?php if (!empty($quote['valid_until'])): ?>
                                    <div class="quote-valid-until">
                                        有效期至: <?php echo format_date($quote['valid_until']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="col-total">
                                <div class="quote-amount">
                                    <?php echo format_currency_cents($quote['total_cents']); ?>
                                </div>
                            </td>
                            <td class="col-actions">
                                <div class="quote-actions">
                                    <a
                                        href="/quotes/view.php?id=<?php echo $quote['id']; ?>"
                                        class="btn btn-sm btn-outline"
                                        title="檢視詳情"
                                    >
                                        檢視
                                    </a>
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
            $base_url = '/quotes/index.php';
            $params = [];
            if (!empty($search)) $params['search'] = $search;
            if (!empty($status)) $params['status'] = $status;

            if (!empty($params)) {
                $base_url .= '?' . http_build_query($params);
            }

            echo generate_pagination($current_page, $total_pages, $base_url);
            ?>
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
