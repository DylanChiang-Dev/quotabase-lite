<?php
/**
 * 报价单详情页面
 * Quote View Page
 *
 * @version v2.0.0
 * @description 报价单详情查看页面
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

// 获取报价单ID
$quote_id = intval($_GET['id'] ?? 0);

if ($quote_id <= 0) {
    header('Location: /quotes/?error=' . urlencode('无效的报价单ID'));
    exit;
}

$error = '';
$quote = null;

// 获取报价单信息
try {
    $quote = get_quote($quote_id);

    if (!$quote) {
        header('Location: /quotes/?error=' . urlencode('报价单不存在'));
        exit;
    }

} catch (Exception $e) {
    error_log("Get quote error: " . $e->getMessage());
    $error = '加载报价单信息失败';
}

// 处理状态更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '无效的请求，请重新提交。';
    } else {
        $new_status = $_POST['status'] ?? '';
        $result = update_quote_status($quote_id, $new_status);

        if ($result['success']) {
            header('Location: /quotes/view.php?id=' . $quote_id . '&success=' . urlencode('状态更新成功'));
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

// 页面开始
html_start('报价单详情');

// 输出头部
page_header('报价单详情', [
    ['label' => '首页', 'url' => '/'],
    ['label' => '报价管理', 'url' => '/quotes/'],
    ['label' => '报价单详情', 'url' => '/quotes/view.php?id=' . $quote_id]
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

    <?php if ($quote): ?>
        <?php card_start('报价单信息', [
            ['label' => '编辑', 'url' => '/quotes/edit.php?id=' . $quote_id, 'class' => 'btn-primary'],
            ['label' => '打印', 'url' => '/quotes/print.php?id=' . $quote_id, 'class' => 'btn-secondary', 'target' => '_blank']
        ]); ?>

        <!-- 基本信息 -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 32px; margin-bottom: 32px;">
            <div>
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color: var(--primary-color);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    报价单信息
                </h3>

                <div style="display: grid; gap: 16px;">
                    <div class="info-item">
                        <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">报价单号</label>
                        <div style="font-size: 16px; font-weight: 600; color: var(--text-primary); padding: 8px 0; font-family: monospace;">
                            <?php echo h($quote['quote_number']); ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">状态</label>
                        <div style="padding: 8px 0;">
                            <?php echo get_status_badge($quote['status']); ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">开票日期</label>
                        <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                            <?php echo format_date($quote['issue_date']); ?>
                        </div>
                    </div>

                    <?php if (!empty($quote['valid_until'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">有效期至</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <?php echo format_date($quote['valid_until']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="info-item">
                        <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">创建日期</label>
                        <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                            <?php echo format_datetime($quote['created_at']); ?>
                        </div>
                    </div>

                    <?php if (!empty($quote['updated_at'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">最后更新</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <?php echo format_datetime($quote['updated_at']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color: var(--primary-color);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    客户信息
                </h3>

                <div style="display: grid; gap: 16px;">
                    <div class="info-item">
                        <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">客户名称</label>
                        <div style="font-size: 16px; font-weight: 600; color: var(--text-primary); padding: 8px 0;">
                            <?php echo h($quote['customer_name']); ?>
                        </div>
                    </div>

                    <?php if (!empty($quote['tax_id'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">税务登记号</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <?php echo h($quote['tax_id']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($quote['email'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">邮箱</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <a href="mailto:<?php echo h($quote['email']); ?>" style="color: var(--primary-color); text-decoration: none;">
                                    <?php echo h($quote['email']); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($quote['phone'])): ?>
                        <div class="info-item">
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">电话</label>
                            <div style="font-size: 16px; font-weight: 500; color: var(--text-primary); padding: 8px 0;">
                                <a href="tel:<?php echo h($quote['phone']); ?>" style="color: var(--primary-color); text-decoration: none;">
                                    <?php echo h($quote['phone']); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 16px;">
                        <a href="/customers/view.php?id=<?php echo $quote['customer_id']; ?>" class="btn btn-outline" style="width: 100%; justify-content: center;">
                            查看客户详情
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 报价项目 -->
        <div style="margin-bottom: 32px;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">报价项目</h3>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: var(--bg-secondary); border-bottom: 2px solid var(--border-color);">
                        <tr>
                            <th style="padding: 12px; text-align: left; font-size: 14px; font-weight: 600; color: var(--text-secondary);">SKU</th>
                            <th style="padding: 12px; text-align: left; font-size: 14px; font-weight: 600; color: var(--text-secondary);">产品/服务</th>
                            <th style="padding: 12px; text-align: right; font-size: 14px; font-weight: 600; color: var(--text-secondary);">数量</th>
                            <th style="padding: 12px; text-align: right; font-size: 14px; font-weight: 600; color: var(--text-secondary);">单价</th>
                            <th style="padding: 12px; text-align: right; font-size: 14px; font-weight: 600; color: var(--text-secondary);">税率</th>
                            <th style="padding: 12px; text-align: right; font-size: 14px; font-weight: 600; color: var(--text-secondary);">小计</th>
                            <th style="padding: 12px; text-align: right; font-size: 14px; font-weight: 600; color: var(--text-secondary);">税额</th>
                            <th style="padding: 12px; text-align: right; font-size: 14px; font-weight: 600; color: var(--text-secondary);">总计</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quote['items'] as $item): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 16px 12px;">
                                    <div style="font-size: 14px; font-weight: 600; color: var(--text-primary); font-family: monospace;">
                                        <?php echo h($item['sku']); ?>
                                    </div>
                                </td>
                                <td style="padding: 16px 12px;">
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);">
                                        <?php echo h($item['item_name']); ?>
                                    </div>
                                </td>
                                <td style="padding: 16px 12px; text-align: right;">
                                    <div style="font-size: 14px; color: var(--text-secondary);">
                                        <?php echo number_format($item['quantity'], 4); ?>
                                        <?php echo h(UNITS[$item['unit']] ?? $item['unit']); ?>
                                    </div>
                                </td>
                                <td style="padding: 16px 12px; text-align: right;">
                                    <div style="font-size: 14px; font-weight: 600; color: var(--text-primary);">
                                        <?php echo format_currency_cents($item['unit_price_cents']); ?>
                                    </div>
                                </td>
                                <td style="padding: 16px 12px; text-align: right;">
                                    <div style="font-size: 14px; color: var(--text-secondary);">
                                        <?php echo number_format($item['tax_rate'], 2); ?>%
                                    </div>
                                </td>
                                <td style="padding: 16px 12px; text-align: right;">
                                    <div style="font-size: 14px; font-weight: 600; color: var(--text-primary);">
                                        <?php echo format_currency_cents($item['line_subtotal_cents']); ?>
                                    </div>
                                </td>
                                <td style="padding: 16px 12px; text-align: right;">
                                    <div style="font-size: 14px; color: var(--text-secondary);">
                                        <?php echo format_currency_cents($item['line_tax_cents']); ?>
                                    </div>
                                </td>
                                <td style="padding: 16px 12px; text-align: right;">
                                    <div style="font-size: 15px; font-weight: 700; color: var(--text-primary);">
                                        <?php echo format_currency_cents($item['line_total_cents']); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 金额汇总 -->
        <div style="margin-bottom: 32px; display: flex; justify-content: flex-end;">
            <div style="width: 400px; padding: 20px; background: var(--bg-secondary); border-radius: var(--border-radius-md);">
                <div style="display: grid; gap: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 15px;">
                        <span style="color: var(--text-secondary);">小计：</span>
                        <span style="font-weight: 600; color: var(--text-primary);">
                            <?php echo format_currency_cents($quote['subtotal_cents']); ?>
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 15px;">
                        <span style="color: var(--text-secondary);">税额：</span>
                        <span style="font-weight: 600; color: var(--text-primary);">
                            <?php echo format_currency_cents($quote['tax_cents']); ?>
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 2px solid var(--border-color); font-size: 18px; font-weight: 700;">
                        <span style="color: var(--text-primary);">总计：</span>
                        <span style="color: var(--primary-color);">
                            <?php echo format_currency_cents($quote['total_cents']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 备注 -->
        <?php if (!empty($quote['note'])): ?>
            <div style="margin-bottom: 32px;">
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">备注</h3>
                <div style="font-size: 16px; color: var(--text-secondary); padding: 16px; background: var(--bg-secondary); border-radius: var(--border-radius-md); line-height: 1.6;">
                    <?php echo nl2br(h($quote['note'])); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 状态操作 -->
        <?php if ($quote['status'] === 'draft'): ?>
            <div style="margin-bottom: 32px; padding: 20px; background: var(--bg-secondary); border-radius: var(--border-radius-md);">
                <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">状态操作</h4>
                <form method="POST" action="/quotes/view.php?id=<?php echo $quote_id; ?>" style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="update_status">
                    <select name="status" required style="padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); background: var(--bg-primary); color: var(--text-primary);">
                        <option value="">选择新状态</option>
                        <option value="sent">标记为已发送</option>
                        <option value="accepted">标记为已接受</option>
                        <option value="rejected">标记为已拒绝</option>
                        <option value="expired">标记为已过期</option>
                    </select>
                    <button type="submit" class="btn btn-primary">更新状态</button>
                </form>
            </div>
        <?php endif; ?>

        <?php card_end(); ?>
    <?php endif; ?>
</div>

<?php
// 输出底部导航
bottom_tab_navigation();

// 页面结束
html_end();
?>
