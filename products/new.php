<?php
/**
 * 新建產品頁面
 * Create New Product Page
 *
 * @version v2.0.0
 * @description 新建產品表單頁面
 * @遵循憲法原則I: 安全優先開發 - XSS防護、CSRF驗證
 * @遵循憲法原則I: PDO預處理防止SQL注入
 * @遵循憲法原則II: 精確財務資料處理 - 金額以分儲存
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

$error = '';
$success = '';

$category_tree = get_catalog_categories_tree('product');
$category_map = get_catalog_category_map('product');
$selected_category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
$generated_sku = generate_catalog_item_sku('product');
$sku_value = $_POST['sku'] ?? $generated_sku;

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 驗證CSRF令牌
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '無效的請求，請重新提交。';
    } else {
        // 準備資料
        $data = [
            'type' => 'product', // 預設型別為產品
            'sku' => trim($_POST['sku'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'unit' => trim($_POST['unit'] ?? 'pcs'),
            'currency' => trim($_POST['currency'] ?? 'TWD'),
            'unit_price_cents' => amount_to_cents($_POST['unit_price'] ?? '0'),
            'tax_rate' => floatval($_POST['tax_rate'] ?? 0),
            'category_id' => $selected_category_id
        ];

        // 建立產品
        $result = create_catalog_item($data);

        if ($result['success']) {
            // 成功，重定向到列表頁
            header('Location: /products/?success=' . urlencode('產品建立成功'));
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

// 頁面開始
html_start('新建產品');

// 輸出頭部
page_header('新建產品', [
    ['label' => '首頁', 'url' => '/'],
    ['label' => '產品管理', 'url' => '/products/'],
    ['label' => '新建產品', 'url' => '/products/new.php']
]);

?>

<div class="main-content">
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($error); ?></span>
        </div>
    <?php endif; ?>

    <?php card_start('新建產品資訊'); ?>

    <form method="POST" action="/products/new.php">
        <?php echo csrf_input(); ?>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
            <!-- 基本資訊 -->
            <div>
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">基本資訊</h3>

                <?php
                form_field('sku', 'SKU編碼', 'text', [], [
                    'required' => true,
                    'placeholder' => '系統已自動生成，可自行修改',
                    'value' => $sku_value,
                    'help' => 'SKU用於唯一標識產品，系統會自動生成（可手動調整，允許字母、數字、- 和 _）'
                ]);
                ?>

                <?php
                form_field('name', '產品名稱', 'text', [], [
                    'required' => true,
                    'placeholder' => '請輸入產品名稱',
                    'value' => $_POST['name'] ?? ''
                ]);
                ?>

                <?php
                // 設定產品單位
                $selected_unit = $_POST['unit'] ?? 'pcs';
                form_field('unit', '計量單位', 'select', PRODUCT_UNITS, [
                    'required' => true,
                    'selected' => $selected_unit,
                    'help' => '產品常用單位：件、組、盒、包、臺等'
                ]);
                ?>

                <?php
                // 貨幣選擇
                $selected_currency = $_POST['currency'] ?? 'TWD';
                form_field('currency', '貨幣', 'select', CURRENCIES, [
                    'required' => true,
                    'selected' => $selected_currency
                ]);
                ?>

                <label class="form-label" style="margin-top: 24px;">產品分類</label>
                <?php
                render_category_selector('product', $category_tree, $category_map, $selected_category_id, [
                    'id_prefix' => 'product_new_category',
                    'manage_url' => '/categories/index.php?type=product',
                    'manage_label' => '分類管理',
                    'help_text' => '分類最多三級，可在分類管理中維護。',
                    'empty_text' => '未選擇分類'
                ]);
                ?>
            </div>

            <!-- 價格資訊 -->
            <div>
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">價格資訊</h3>

                <?php
                form_field('unit_price', '單價', 'text', [], [
                    'required' => true,
                    'placeholder' => '0.00',
                    'value' => $_POST['unit_price'] ?? '',
                    'help' => '請輸入產品價格，系統將以分為單位精確儲存'
                ]);
                ?>

                <?php
                // 稅率選擇
                $selected_tax = $_POST['tax_rate'] ?? 0;
                form_field('tax_rate', '稅率', 'select', TAX_RATES, [
                    'required' => true,
                    'selected' => (string)$selected_tax
                ]);
                ?>

                <!-- 價格預覽 -->
                <div id="price-preview" style="margin-top: 16px; padding: 16px; background: var(--bg-secondary); border-radius: var(--border-radius-md); display: none;">
                    <div style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 12px;">價格預覽</div>
                    <div style="display: grid; gap: 8px; font-size: 14px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-secondary);">單價:</span>
                            <span id="preview-unit-price" style="font-weight: 600; color: var(--text-primary);">-</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-secondary);">稅率:</span>
                            <span id="preview-tax-rate" style="font-weight: 600; color: var(--text-primary);">-</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-top: 8px; border-top: 1px solid var(--border-color);">
                            <span style="color: var(--text-secondary);">含稅價格:</span>
                            <span id="preview-total-price" style="font-weight: 700; color: var(--primary-color);">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 32px; display: flex; gap: 12px; justify-content: flex-end;">
            <a href="/products/" class="btn btn-secondary">取消</a>
            <button type="submit" class="btn btn-primary">建立產品</button>
        </div>
    </form>

    <?php card_end(); ?>
</div>

<?php
// 輸出底部導航
bottom_tab_navigation();

// 頁面結束
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
