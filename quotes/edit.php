<?php
/**
 * 编辑报价单页面
 * Edit Quote Page
 *
 * @version v2.0.0
 * @description 报价单编辑页面，支持状态更新
 * @遵循宪法原则I: 安全优先开发 - XSS防护、CSRF验证
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
$success = '';
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

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '无效的请求，请重新提交。';
    } else {
        // 更新状态
        $new_status = $_POST['status'] ?? '';
        $result = update_quote_status($quote_id, $new_status);

        if ($result['success']) {
            header('Location: /quotes/view.php?id=' . $quote_id . '&success=' . urlencode('状态更新成功'));
            exit;
        } else {
            $error = $result['error'];
            // 更新状态用于表单显示
            if ($quote) {
                $quote['status'] = $new_status;
            }
        }
    }
}

// 页面开始
html_start('编辑报价单');

// 输出头部
page_header('编辑报价单', [
    ['label' => '首页', 'url' => '/'],
    ['label' => '报价管理', 'url' => '/quotes/'],
    ['label' => '编辑报价单', 'url' => '/quotes/edit.php?id=' . $quote_id]
]);

?>

<div class="main-content">
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($quote): ?>
        <?php card_start('编辑报价单'); ?>

        <!-- 基本信息（只读） -->
        <div style="margin-bottom: 32px;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">基本信息</h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                <div class="info-item">
                    <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">报价单号</label>
                    <div style="font-size: 16px; font-weight: 600; color: var(--text-primary); padding: 8px 0; font-family: monospace; background: var(--bg-secondary); border-radius: var(--border-radius-sm);">
                        <?php echo h($quote['quote_number']); ?>
                    </div>
                </div>

                <div class="info-item">
                    <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">客户</label>
                    <div style="font-size: 16px; font-weight: 600; color: var(--text-primary); padding: 8px 0; background: var(--bg-secondary); border-radius: var(--border-radius-sm);">
                        <?php echo h($quote['customer_name']); ?>
                    </div>
                </div>

                <div class="info-item">
                    <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">开票日期</label>
                    <div style="font-size: 16px; font-weight: 500; color: var(--text-secondary); padding: 8px 0; background: var(--bg-secondary); border-radius: var(--border-radius-sm);">
                        <?php echo format_date($quote['issue_date']); ?>
                    </div>
                </div>

                <?php if (!empty($quote['valid_until'])): ?>
                    <div class="info-item">
                        <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 4px;">有效期至</label>
                        <div style="font-size: 16px; font-weight: 500; color: var(--text-secondary); padding: 8px 0; background: var(--bg-secondary); border-radius: var(--border-radius-sm);">
                            <?php echo format_date($quote['valid_until']); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 状态管理 -->
        <div style="margin-bottom: 32px;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">状态管理</h3>

            <div style="padding: 24px; background: var(--bg-secondary); border-radius: var(--border-radius-md);">
                <form method="POST" action="/quotes/edit.php?id=<?php echo $quote_id; ?>">
                    <?php echo csrf_input(); ?>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; align-items: end;">
                        <div>
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 8px;">当前状态</label>
                            <div style="padding: 8px 0;">
                                <?php echo get_status_badge($quote['status']); ?>
                            </div>
                        </div>

                        <div>
                            <label style="display: block; font-size: 14px; color: var(--text-tertiary); margin-bottom: 8px;">更新为</label>
                            <?php
                            $status_options = [
                                'draft' => '草稿',
                                'sent' => '已发送',
                                'accepted' => '已接受',
                                'rejected' => '已拒绝',
                                'expired' => '已过期'
                            ];
                            form_field('status', '', 'select', $status_options, [
                                'required' => true,
                                'selected' => $quote['status'],
                                'placeholder' => '选择新状态'
                            ]);
                            ?>
                        </div>
                    </div>

                    <div style="margin-top: 24px; padding: 16px; background: var(--bg-primary); border-radius: var(--border-radius-sm); border-left: 4px solid var(--primary-color);">
                        <div style="font-size: 14px; color: var(--text-secondary); line-height: 1.6;">
                            <strong>状态说明：</strong><br>
                            • 草稿：报价单创建后的初始状态，可以编辑<br>
                            • 已发送：已发送给客户，可以更新为已接受/已拒绝/已过期<br>
                            • 已接受：客户已确认，无法再修改<br>
                            • 已拒绝：客户已拒绝，无法再修改<br>
                            • 已过期：超过有效期，无法再修改
                        </div>
                    </div>

                    <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                        <a href="/quotes/view.php?id=<?php echo $quote_id; ?>" class="btn btn-secondary">取消</a>
                        <button type="submit" class="btn btn-primary">更新状态</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 报价单明细（只读） -->
        <div style="margin-bottom: 32px;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">报价明细</h3>

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

        <!-- 操作按钮 -->
        <div style="margin-top: 32px; display: flex; gap: 12px; justify-content: space-between;">
            <div style="display: flex; gap: 12px;">
                <a href="/quotes/view.php?id=<?php echo $quote_id; ?>" class="btn btn-outline">返回详情</a>
                <a href="/quotes/" class="btn btn-secondary">返回列表</a>
            </div>
            <div style="display: flex; gap: 12px;">
                <a href="/quotes/print.php?id=<?php echo $quote_id; ?>" class="btn btn-secondary" target="_blank">打印报价单</a>
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
