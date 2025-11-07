<?php
/**
 * 編輯產品頁面
 * Edit Product Page
 *
 * @version v2.0.0
 * @description 編輯產品資訊表單頁面
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

// 獲取產品ID
$product_id = intval($_GET['id'] ?? 0);

if ($product_id <= 0) {
    header('Location: /products/?error=' . urlencode('無效的產品ID'));
    exit;
}

$error = '';
$success = '';
$product = null;
$category_tree = get_catalog_categories_tree('product');
$category_map = get_catalog_category_map('product');
$selected_category_id = 0;
// 獲取產品資訊
try {
    $product = get_catalog_item($product_id);

    if (!$product) {
        header('Location: /products/?error=' . urlencode('產品不存在'));
        exit;
    }

    // 確保是產品型別
    if ($product['type'] !== 'product') {
        header('Location: /products/?error=' . urlencode('無效的產品型別'));
        exit;
    }

    $selected_category_id = intval($product['category_id'] ?? 0);
} catch (Exception $e) {
    error_log("Get product error: " . $e->getMessage());
    $error = '載入產品資訊失敗';
}

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 驗證CSRF令牌
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '無效的請求，請重新提交。';
    } else {
        // 準備資料
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

        // 更新產品
        $result = update_catalog_item($product_id, $data);

        if ($result['success']) {
            // 成功，重定向到列表頁
            header('Location: /products/?success=' . urlencode('產品資訊更新成功'));
            exit;
        } else {
            $error = $result['error'];
            // 合併資料用於表單顯示
            $product = array_merge($product, [
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
    // GET請求，將價格從分轉換為元顯示
    $product['unit_price'] = ($product['unit_price_cents'] ?? 0) / 100;
}

// 頁面開始
html_start('編輯產品');

// 輸出頭部
page_header('編輯產品', [
    ['label' => '首頁', 'url' => '/'],
    ['label' => '產品管理', 'url' => '/products/'],
    ['label' => '編輯產品', 'url' => '/products/edit.php?id=' . $product_id]
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

    <?php if ($product): ?>
        <?php card_start('編輯產品資訊'); ?>

        <form method="POST" action="/products/edit.php?id=<?php echo $product_id; ?>">
            <?php echo csrf_input(); ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                <!-- 基本資訊 -->
                <div>
                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">基本資訊</h3>

                    <?php
                    form_field('sku', 'SKU編碼', 'text', [], [
                        'required' => true,
                        'placeholder' => '請輸入唯一SKU編碼',
                        'value' => $product['sku'] ?? '',
                        'help' => 'SKU用於唯一標識產品，僅支援字母、數字、-和_'
                    ]);
                    ?>

                    <?php
                    form_field('name', '產品名稱', 'text', [], [
                        'required' => true,
                        'placeholder' => '請輸入產品名稱',
                        'value' => $product['name'] ?? ''
                    ]);
                    ?>

                    <?php
                    // 設定產品單位
                    $selected_unit = $product['unit'] ?? 'pcs';
                    form_field('unit', '計量單位', 'select', PRODUCT_UNITS, [
                        'required' => true,
                        'selected' => $selected_unit,
                        'help' => '產品常用單位：件、組、盒、包、臺等'
                    ]);
                    ?>

                    <?php
                // 貨幣選擇
                $selected_currency = $product['currency'] ?? 'TWD';
                form_field('currency', '貨幣', 'select', CURRENCIES, [
                    'required' => true,
                    'selected' => $selected_currency
                ]);
                ?>
                <label class="form-label" style="margin-top: 24px;">產品分類</label>
                <?php
                render_category_selector('product', $category_tree, $category_map, $selected_category_id, [
                    'id_prefix' => 'product_edit_category_' . $product_id,
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
                        'value' => $product['unit_price'] ?? '0.00',
                        'help' => '請輸入產品價格，系統將以分為單位精確儲存'
                    ]);
                    ?>

                    <?php
                    // 稅率選擇
                    $selected_tax = $product['tax_rate'] ?? 0;
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

            <div style="margin-top: 32px; display: flex; gap: 12px; justify-content: space-between;">
                <div style="display: flex; gap: 12px;">
                    <a href="/products/" class="btn btn-outline">返回列表</a>
                </div>
                <div style="display: flex; gap: 12px;">
                    <a href="/products/new.php" class="btn btn-secondary">新建產品</a>
                    <button type="submit" class="btn btn-primary">儲存更改</button>
                </div>
            </div>
        </form>

        <?php card_end(); ?>
    <?php endif; ?>
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
        updatePricePreview();
    }
});
</script>
