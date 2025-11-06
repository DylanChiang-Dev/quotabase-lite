<?php
/**
 * 编辑服务页面
 * Edit Service Page
 *
 * @version v2.0.0
 * @description 编辑服务信息表单页面
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

// 获取服务ID
$service_id = intval($_GET['id'] ?? 0);

if ($service_id <= 0) {
    header('Location: /services/?error=' . urlencode('无效的服务ID'));
    exit;
}

$error = '';
$success = '';
$service = null;
$category_tree = get_catalog_categories_tree('service');
$category_map = get_catalog_category_map('service');
$selected_category_id = 0;
// 获取服务信息
try {
    $service = get_catalog_item($service_id);

    if (!$service) {
        header('Location: /services/?error=' . urlencode('服务不存在'));
        exit;
    }

    // 确保是服务类型
    if ($service['type'] !== 'service') {
        header('Location: /services/?error=' . urlencode('无效的服务类型'));
        exit;
    }

    $selected_category_id = intval($service['category_id'] ?? 0);
} catch (Exception $e) {
    error_log("Get service error: " . $e->getMessage());
    $error = '加载服务信息失败';
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '无效的请求，请重新提交。';
    } else {
        // 准备数据
        $selected_category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $data = [
            'sku' => trim($_POST['sku'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'unit' => trim($_POST['unit'] ?? 'pcs'),
            'currency' => trim($_POST['currency'] ?? 'TWD'),
            'unit_price_cents' => amount_to_cents($_POST['unit_price'] ?? '0'),
            'tax_rate' => floatval($_POST['tax_rate'] ?? 0),
            'category_id' => $selected_category_id
        ];

        // 更新服务
        $result = update_catalog_item($service_id, $data);

        if ($result['success']) {
            // 成功，重定向到列表页
            header('Location: /services/?success=' . urlencode('服务信息更新成功'));
            exit;
        } else {
            $error = $result['error'];
            // 合并数据用于表单显示
            $service = array_merge($service, [
                'sku' => $data['sku'],
                'name' => $data['name'],
                'unit' => $data['unit'],
                'currency' => $data['currency'],
                'unit_price_cents' => $data['unit_price_cents'],
                'unit_price' => $_POST['unit_price'] ?? '',
                'tax_rate' => $data['tax_rate'],
                'category_id' => $selected_category_id
            ]);
        }
    }
} else {
    // GET请求，将价格从分转换为元显示
    $service['unit_price'] = ($service['unit_price_cents'] ?? 0) / 100;
}

// 页面开始
html_start('编辑服务');

// 输出头部
page_header('编辑服务', [
    ['label' => '首页', 'url' => '/'],
    ['label' => '服务管理', 'url' => '/services/'],
    ['label' => '编辑服务', 'url' => '/services/edit.php?id=' . $service_id]
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

    <?php if ($service): ?>
        <?php card_start('编辑服务信息'); ?>

        <form method="POST" action="/services/edit.php?id=<?php echo $service_id; ?>">
            <?php echo csrf_input(); ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                <!-- 基本信息 -->
                <div>
                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">基本信息</h3>

                    <?php
                    form_field('sku', 'SKU编码', 'text', [], [
                        'required' => true,
                        'placeholder' => '请输入唯一SKU编码',
                        'value' => $service['sku'] ?? '',
                        'help' => 'SKU用于唯一标识服务，仅支持字母、数字、-和_'
                    ]);
                    ?>

                    <?php
                    form_field('name', '服务名称', 'text', [], [
                        'required' => true,
                        'placeholder' => '请输入服务名称',
                        'value' => $service['name'] ?? ''
                    ]);
                    ?>

                    <?php
                    // 服務單位
                    $selected_unit = $service['unit'] ?? 'time';
                    form_field('unit', '計量單位', 'select', SERVICE_UNITS, [
                        'required' => true,
                        'selected' => $selected_unit,
                        'help' => '服務支援次、時、日、週、月、年'
                    ]);
                    ?>

                    <?php
                // 货币选择
                $selected_currency = $service['currency'] ?? 'TWD';
                form_field('currency', '货币', 'select', CURRENCIES, [
                    'required' => true,
                    'selected' => $selected_currency
                ]);
                ?>

                <label class="form-label" style="margin-top: 24px;">服务分类</label>
                <?php
                render_category_selector('service', $category_tree, $category_map, $selected_category_id, [
                    'id_prefix' => 'service_edit_category_' . $service_id,
                    'manage_url' => '/categories/index.php?type=service',
                    'manage_label' => '分类管理',
                    'help_text' => '分类最多三级，可在分类管理中维护。',
                    'empty_text' => '未选择分类'
                ]);
                ?>
            </div>

                <!-- 价格信息 -->
                <div>
                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">价格信息</h3>

                    <?php
                    form_field('unit_price', '单价', 'text', [], [
                        'required' => true,
                        'placeholder' => '0.00',
                        'value' => $service['unit_price'] ?? '0.00',
                        'help' => '请输入服务价格，系统将以分为单位精确存储'
                    ]);
                    ?>

                    <?php
                    // 税率选择
                    $selected_tax = $service['tax_rate'] ?? 0;
                    form_field('tax_rate', '税率', 'select', TAX_RATES, [
                        'required' => true,
                        'selected' => (string)$selected_tax
                    ]);
                    ?>

                    <!-- 价格预览 -->
                    <div id="price-preview" style="margin-top: 16px; padding: 16px; background: var(--bg-secondary); border-radius: var(--border-radius-md); display: none;">
                        <div style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 12px;">价格预览</div>
                        <div style="display: grid; gap: 8px; font-size: 14px;">
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary);">单价:</span>
                                <span id="preview-unit-price" style="font-weight: 600; color: var(--text-primary);">-</span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary);">税率:</span>
                                <span id="preview-tax-rate" style="font-weight: 600; color: var(--text-primary);">-</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding-top: 8px; border-top: 1px solid var(--border-color);">
                                <span style="color: var(--text-secondary);">含税价格:</span>
                                <span id="preview-total-price" style="font-weight: 700; color: var(--primary-color);">-</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top: 32px; display: flex; gap: 12px; justify-content: space-between;">
                <div style="display: flex; gap: 12px;">
                    <a href="/services/" class="btn btn-outline">返回列表</a>
                </div>
                <div style="display: flex; gap: 12px;">
                    <a href="/services/new.php" class="btn btn-secondary">新建服务</a>
                    <button type="submit" class="btn btn-primary">保存更改</button>
                </div>
            </div>
        </form>

        <?php card_end(); ?>
    <?php endif; ?>
</div>

<?php
// 输出底部导航
bottom_tab_navigation();

// 页面结束
html_end();
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const unitPriceInput = document.querySelector('input[name="unit_price"]');
    const taxRateSelect = document.querySelector('select[name="tax_rate"]');
    const pricePreview = document.getElementById('price-preview');
    const previewUnitPrice = document.getElementById('preview-unit-price');
    const previewTaxRate = document.getElementById('preview-tax-rate');
    const previewTotalPrice = document.getElementById('preview-total-price');

    function updatePricePreview() {
        const price = unitPriceInput.value;
        const taxRate = taxRateSelect.value;

        if (price && !isNaN(price) && price > 0) {
            const priceNum = parseFloat(price);
            const taxNum = parseFloat(taxRate);
            const total = priceNum + (priceNum * taxNum / 100);

            previewUnitPrice.textContent = 'NT$ ' + priceNum.toFixed(2);
            previewTaxRate.textContent = taxNum.toFixed(2) + '%';
            previewTotalPrice.textContent = 'NT$ ' + total.toFixed(2);

            pricePreview.style.display = 'block';
        } else {
            pricePreview.style.display = 'none';
        }
    }

    if (unitPriceInput && taxRateSelect) {
        unitPriceInput.addEventListener('input', updatePricePreview);
        taxRateSelect.addEventListener('change', updatePricePreview);
        updatePricePreview();
    }
});
</script>
