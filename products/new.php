<?php
/**
 * 新建产品页面
 * Create New Product Page
 *
 * @version v2.0.0
 * @description 新建产品表单页面
 * @遵循宪法原则I: 安全优先开发 - XSS防护、CSRF验证
 * @遵循宪法原则I: PDO预处理防止SQL注入
 * @遵循宪法原则II: 精确财务数据处理 - 金额以分存储
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

$category_tree = get_catalog_categories_tree('product');
$category_map = get_catalog_category_map('product');
$selected_category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
$generated_sku = generate_catalog_item_sku('product');
$sku_value = $_POST['sku'] ?? $generated_sku;

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '无效的请求，请重新提交。';
    } else {
        // 准备数据
        $data = [
            'type' => 'product', // 默认类型为产品
            'sku' => trim($_POST['sku'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'unit' => trim($_POST['unit'] ?? 'pcs'),
            'currency' => trim($_POST['currency'] ?? 'TWD'),
            'unit_price_cents' => amount_to_cents($_POST['unit_price'] ?? '0'),
            'tax_rate' => floatval($_POST['tax_rate'] ?? 0),
            'category_id' => $selected_category_id
        ];

        // 创建产品
        $result = create_catalog_item($data);

        if ($result['success']) {
            // 成功，重定向到列表页
            header('Location: /products/?success=' . urlencode('产品创建成功'));
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

// 页面开始
html_start('新建产品');

// 输出头部
page_header('新建产品', [
    ['label' => '首页', 'url' => '/'],
    ['label' => '产品管理', 'url' => '/products/'],
    ['label' => '新建产品', 'url' => '/products/new.php']
]);

?>

<div class="main-content">
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($error); ?></span>
        </div>
    <?php endif; ?>

    <?php card_start('新建产品信息'); ?>

    <form method="POST" action="/products/new.php">
        <?php echo csrf_input(); ?>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
            <!-- 基本信息 -->
            <div>
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">基本信息</h3>

                <?php
                form_field('sku', 'SKU编码', 'text', [], [
                    'required' => true,
                    'placeholder' => '系统已自动生成，可自行修改',
                    'value' => $sku_value,
                    'help' => 'SKU用于唯一标识产品，系统会自动生成（可手动调整，允许字母、数字、- 和 _）'
                ]);
                ?>

                <?php
                form_field('name', '产品名称', 'text', [], [
                    'required' => true,
                    'placeholder' => '请输入产品名称',
                    'value' => $_POST['name'] ?? ''
                ]);
                ?>

                <?php
                // 单位选择
                $selected_unit = $_POST['unit'] ?? 'pcs';
                form_field('unit', '计量单位', 'select', UNITS, [
                    'required' => true,
                    'selected' => $selected_unit
                ]);
                ?>

                <?php
                // 货币选择
                $selected_currency = $_POST['currency'] ?? 'TWD';
                form_field('currency', '货币', 'select', CURRENCIES, [
                    'required' => true,
                    'selected' => $selected_currency
                ]);
                ?>

                <label class="form-label" style="margin-top: 24px;">产品分类</label>
                <?php
                render_category_selector('product', $category_tree, $category_map, $selected_category_id, [
                    'id_prefix' => 'product_new_category',
                    'manage_url' => '/categories/index.php?type=product',
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
                    'value' => $_POST['unit_price'] ?? '',
                    'help' => '请输入产品价格，系统将以分为单位精确存储'
                ]);
                ?>

                <?php
                // 税率选择
                $selected_tax = $_POST['tax_rate'] ?? 0;
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

        <div style="margin-top: 32px; display: flex; gap: 12px; justify-content: flex-end;">
            <a href="/products/" class="btn btn-secondary">取消</a>
            <button type="submit" class="btn btn-primary">创建产品</button>
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
    }
});
</script>
