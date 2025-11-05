<?php
/**
 * 报价列表页面
 * Quote List Page
 *
 * @version v2.0.0
 * @description 报价单列表页面，支持分页和状态筛选
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
$status = trim($_GET['status'] ?? '');
$status_filters = [
    '' => '所有状态',
    'draft' => '草稿',
    'sent' => '已发送',
    'accepted' => '已接受',
    'rejected' => '已拒绝',
    'expired' => '已过期'
];

// 获取报价单列表
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
    $error = '加载报价单列表失败';
    $error_debug = [
        'code' => $error_code,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ];
}

// 页面开始
html_start('报价管理');

// 输出头部
page_header('报价管理', [
    ['label' => '首页', 'url' => '/'],
    ['label' => '报价管理', 'url' => '/quotes/']
]);

?>

<div class="main-content">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($error); ?></span>
        </div>
        <script>
            console.group('[QB] 报价单列表加载失败');
            console.error('提示：', <?php echo json_encode($error, JSON_UNESCAPED_UNICODE); ?>);
            <?php if (isset($error_debug)): ?>
            console.error('详细：', <?php echo json_encode($error_debug, JSON_UNESCAPED_UNICODE); ?>);
            console.error('如需反馈给 Codex，请复制以上 code 与 message。');
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

    <?php card_start('报价单列表'); ?>

    <!-- 工具栏 -->
    <div class="list-toolbar">
        <form method="GET" action="/quotes/index.php" class="list-search">
            <input
                type="text"
                name="search"
                placeholder="搜索报价单号或客户名称..."
                value="<?php echo h($search); ?>"
            >
            <button type="submit" class="btn btn-secondary btn-compact">搜索</button>
        </form>

        <div class="list-filters" role="toolbar" aria-label="报价状态筛选">
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
            <a href="/quotes/new.php" class="btn btn-primary btn-compact list-primary-action">新建报价单</a>
        </div>
    </div>

    <!-- 统计信息 -->
    <div style="margin-bottom: 24px; padding: 16px; background: var(--bg-secondary); border-radius: var(--border-radius-md); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; gap: 24px; align-items: center;">
            <div>
                <div style="font-size: 24px; font-weight: 600; color: var(--primary-color);">
                    <?php echo number_format($total); ?>
                </div>
                <div style="font-size: 14px; color: var(--text-tertiary);">报价单总数</div>
            </div>
            <?php if (!empty($status)): ?>
                <div style="font-size: 14px; color: var(--text-secondary);">
                    状态: <?php echo get_status_label($status); ?>
                </div>
            <?php endif; ?>
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

    <!-- 报价单列表 -->
    <?php if (empty($quotes)): ?>
        <div style="text-align: center; padding: 48px 24px; color: var(--text-tertiary);">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin: 0 auto 16px; color: var(--text-tertiary); opacity: 0.5;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <?php if (!empty($search) || !empty($status)): ?>
                <div style="font-size: 16px;">未找到匹配的报价单</div>
                <div style="font-size: 14px; margin-top: 4px;">请尝试调整筛选条件</div>
            <?php else: ?>
                <div style="font-size: 16px;">暂无报价单</div>
                <div style="font-size: 14px; margin-top: 4px;">点击上方按钮创建第一个报价单</div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="quotes-table">
                <thead>
                    <tr>
                        <th class="col-number">报价单号</th>
                        <th class="col-customer">客户</th>
                        <th class="col-status">状态</th>
                        <th class="col-date">开票日期</th>
                        <th class="col-total">总金额</th>
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
                                        title="查看详情"
                                    >
                                        查看
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
// 输出底部导航
bottom_tab_navigation();

// 页面结束
html_end();
?>
