<?php
/**
 * 系统设置页面
 * Settings Page
 *
 * @version v2.0.0
 * @description 系统设置管理页面
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

$error = '';
$success = '';
$settings = null;

// 获取当前设置
try {
    $settings = get_settings();
} catch (Exception $e) {
    error_log("Get settings error: " . $e->getMessage());
    $error = '加载设置失败';
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '无效的请求，请重新提交。';
    } else {
        // 准备数据
        $data = [
            'company_name' => trim($_POST['company_name'] ?? ''),
            'company_address' => trim($_POST['company_address'] ?? ''),
            'quote_prefix' => strtoupper(trim($_POST['quote_prefix'] ?? 'Q')),
            'default_tax_rate' => floatval($_POST['default_tax_rate'] ?? 0),
            'print_terms' => trim($_POST['print_terms'] ?? ''),
            'company_contact' => trim($_POST['company_contact'] ?? ''),
            'timezone' => trim($_POST['timezone'] ?? '')
        ];

        if ($data['quote_prefix'] === '') {
            $data['quote_prefix'] = 'Q';
        }

        if ($data['timezone'] === '') {
            $data['timezone'] = defined('DISPLAY_TIMEZONE') ? DISPLAY_TIMEZONE : 'Asia/Taipei';
        }

        // 更新设置
        $result = update_settings($data);

        if ($result['success']) {
            $success = $result['message'];
            // 重新加载设置
            $settings = get_settings();
        } else {
            $error = $result['error'];
        }
    }
}

// 页面开始
html_start('系统设置');

// 输出头部
page_header('系统设置', [
    ['label' => '首页', 'url' => '/'],
    ['label' => '系统设置', 'url' => '/settings/']
]);

?>

<div class="main-content">
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <span class="alert-message"><?php echo h($success); ?></span>
        </div>
    <?php endif; ?>

    <?php card_start('系统设置'); ?>

    <form method="POST" action="/settings/index.php">
        <?php echo csrf_input(); ?>

        <!-- 公司信息 -->
        <div style="margin-bottom: 32px;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color: var(--primary-color);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                公司信息
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                <?php
                form_field('company_name', '公司名称', 'text', [], [
                    'required' => true,
                    'placeholder' => '请输入公司名称',
                    'value' => $settings['company_name'] ?? ''
                ]);
                ?>

                <?php
                form_field('company_address', '公司地址', 'textarea', [], [
                    'placeholder' => '请输入公司地址',
                    'rows' => 3,
                    'value' => $settings['company_address'] ?? ''
                ]);
                ?>

                <?php
                form_field('company_contact', '联系方式 / 统一编号', 'text', [], [
                    'placeholder' => '例：电话：(02)1234-5678；Email：info@example.com',
                    'value' => $settings['company_contact'] ?? '',
                    'help' => '可填写电话、邮箱或统一编号等资讯（255字内）'
                ]);
                ?>
            </div>
        </div>

        <!-- 报价设置 -->
        <div style="margin-bottom: 32px;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color: var(--primary-color);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                报价设置
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                <?php
                form_field('quote_prefix', '报价单编号前缀', 'text', [], [
                    'required' => true,
                    'placeholder' => 'Q',
                    'value' => $settings['quote_prefix'] ?? 'Q',
                    'help' => '用于生成报价单编号的前缀，如 Q-2025001'
                ]);
                ?>

                <?php
                // 默认税率选择
                $selected_tax = $settings['default_tax_rate'] ?? 0;
                form_field('default_tax_rate', '默认税率', 'select', TAX_RATES, [
                    'required' => true,
                    'selected' => (string)$selected_tax,
                    'help' => '新创建的产品/服务将使用此默认税率'
                ]);
                ?>

                <?php
                form_field('timezone', '显示时区', 'text', [], [
                    'placeholder' => '例如：Asia/Taipei',
                    'value' => $settings['timezone'] ?? (defined('DISPLAY_TIMEZONE') ? DISPLAY_TIMEZONE : 'Asia/Taipei'),
                    'help' => '输入有效的 PHP 时区标识，例如 Asia/Taipei'
                ]);
                ?>
            </div>
        </div>

        <!-- 打印设置 -->
        <div style="margin-bottom: 32px;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color: var(--primary-color);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                打印设置
            </h3>

            <?php
            form_field('print_terms', '打印条款', 'textarea', [], [
                'placeholder' => '请输入打印条款，如付款方式、交付时间等',
                'rows' => 5,
                'value' => $settings['print_terms'] ?? '',
                'help' => '条款将显示在报价单打印版本的底部'
            ]);
            ?>
        </div>

        <!-- 预览区域 -->
            <div style="margin-bottom: 32px; padding: 20px; background: var(--bg-secondary); border-radius: var(--border-radius-md);">
                <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">设置预览</h4>

                <div style="display: grid; gap: 12px; font-size: 14px;">
                    <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">公司名称：</span>
                    <span style="font-weight: 600; color: var(--text-primary);"><?php echo h($settings['company_name'] ?? '未设置'); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">编号前缀：</span>
                    <span style="font-weight: 600; color: var(--text-primary);">
                        <?php echo h($settings['quote_prefix'] ?? 'Q'); ?>-<span style="color: var(--text-tertiary);">[自动编号]</span>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">默认税率：</span>
                    <span style="font-weight: 600; color: var(--text-primary);">
                        <?php echo number_format($settings['default_tax_rate'] ?? 0, 2); ?>%
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">打印条款：</span>
                    <span style="font-weight: 600; color: var(--text-primary);">
                        <?php echo !empty($settings['print_terms']) ? '已设置' : '未设置'; ?>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">联系方式：</span>
                    <span style="font-weight: 600; color: var(--text-primary);">
                        <?php echo !empty($settings['company_contact']) ? h($settings['company_contact']) : '未设置'; ?>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">显示时区：</span>
                    <span style="font-weight: 600; color: var(--text-primary);">
                        <?php echo h($settings['timezone'] ?? (defined('DISPLAY_TIMEZONE') ? DISPLAY_TIMEZONE : 'Asia/Taipei')); ?>
                    </span>
                </div>
            </div>

            <div style="margin-top: 16px; padding: 12px; background: var(--bg-primary); border-radius: var(--border-radius-sm); border-left: 4px solid var(--primary-color);">
                <div style="font-size: 13px; color: var(--text-secondary); line-height: 1.6;">
                    <strong>预览示例：</strong><br>
                    报价单号：<?php echo h($settings['quote_prefix'] ?? 'Q'); ?>-2025001<br>
                    客户信息将在此处显示<br>
                    报价项目明细...<br>
                    <?php if (!empty($settings['print_terms'])): ?>
                        <br><strong>条款：</strong><br>
                        <?php echo nl2br(h($settings['print_terms'])); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="margin-top: 32px; display: flex; gap: 12px; justify-content: flex-end;">
            <a href="/" class="btn btn-secondary">返回首页</a>
            <button type="submit" class="btn btn-primary">保存设置</button>
        </div>
    </form>

    <?php card_end(); ?>
</div>

<?php
// 输出底部导航
bottom_tab_navigation();

// 页面结束
html_end();
?>
