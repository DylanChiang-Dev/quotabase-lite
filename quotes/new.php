<?php
/**
 * 新建報價單頁面
 * Create New Quote Page
 *
 * @version v2.0.0
 * @description 報價單建立頁面，支援客戶選擇、動態新增專案
 * @遵循憲法原則I: 安全優先開發 - XSS防護、CSRF驗證
 * @遵循憲法原則II: 精確財務資料處理 - 金額以分儲存
 * @遵循憲法原則III: 事務原子性 - 確保資料一致性
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
$customers = [];
$catalog_items = [];

// 獲取客戶列表和目錄項列表
try {
    $customers = get_customer_list();
    $catalog_items = get_catalog_item_list();
} catch (Exception $e) {
    error_log("Get lists error: " . $e->getMessage());
    $error = '載入資料失敗';
}

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 驗證CSRF令牌
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '無效的請求，請重新提交。';
    } else {
        // 準備資料
        $data = [
            'customer_id' => intval($_POST['customer_id'] ?? 0),
            'status' => $_POST['status'] ?? 'draft',
            'issue_date' => $_POST['issue_date'] ?? get_current_date_utc(),
            'valid_until' => !empty($_POST['valid_until']) ? $_POST['valid_until'] : null,
            'note' => trim($_POST['note'] ?? '')
        ];

        // 處理報價專案
        $items = [];
        $item_count = intval($_POST['item_count'] ?? 0);

        for ($i = 0; $i < $item_count; $i++) {
            $catalog_item_id = intval($_POST['items'][$i]['catalog_item_id'] ?? 0);
            $quantity = floatval($_POST['items'][$i]['qty'] ?? ($_POST['items'][$i]['quantity'] ?? 0));

            if ($catalog_item_id > 0 && $quantity > 0) {
                $items[] = [
                    'catalog_item_id' => $catalog_item_id,
                    'qty' => $quantity
                ];
            }
        }

        // 建立報價單
        $result = create_quote($data, $items);

        if ($result['success']) {
            // 成功，重定向到詳情頁
            header('Location: /quotes/view.php?id=' . $result['id'] . '&success=' . urlencode('報價單建立成功'));
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

// 頁面開始
html_start('新建報價單');

// 輸出頭部
page_header('新建報價單', [
    ['label' => '首頁', 'url' => '/'],
    ['label' => '報價管理', 'url' => '/quotes/'],
    ['label' => '新建報價單', 'url' => '/quotes/new.php']
]);

?>

<div class="main-content">
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($error); ?></span>
        </div>
    <?php endif; ?>

    <?php card_start('新建報價單'); ?>

    <form method="POST" action="/quotes/new.php" id="quote-form">
        <?php echo csrf_input(); ?>

        <!-- 基本資訊 -->
        <div style="margin-bottom: 32px;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">基本資訊</h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                <?php
                // 客戶選擇
                $selected_customer = $_POST['customer_id'] ?? '';
                form_field('customer_id', '客戶', 'select', array_column($customers, 'name', 'id'), [
                    'required' => true,
                    'selected' => $selected_customer,
                    'placeholder' => '請選擇客戶'
                ]);
                ?>

                <?php
                // 狀態選擇
                $selected_status = $_POST['status'] ?? 'draft';
                $status_options = [
                    'draft' => '草稿',
                    'sent' => '已傳送',
                    'accepted' => '已接受',
                    'rejected' => '已拒絕',
                    'expired' => '已過期'
                ];
                form_field('status', '狀態', 'select', $status_options, [
                    'required' => true,
                    'selected' => $selected_status
                ]);
                ?>

                <?php
                // 開票日期
                form_field('issue_date', '開票日期', 'date', [], [
                    'required' => true,
                    'value' => $_POST['issue_date'] ?? date('Y-m-d')
                ]);
                ?>

                <?php
                // 有效期至
                form_field('valid_until', '有效期至', 'date', [], [
                    'value' => $_POST['valid_until'] ?? ''
                ]);
                ?>

                <?php
                // 備註
                form_field('note', '備註', 'textarea', [], [
                    'placeholder' => '請輸入備註資訊（可選）',
                    'rows' => 3,
                    'value' => $_POST['note'] ?? ''
                ]);
                ?>
            </div>
        </div>

        <!-- 報價專案 -->
        <div style="margin-bottom: 32px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="font-size: 18px; font-weight: 600; color: var(--text-primary);">報價專案</h3>
                <button type="button" onclick="addItem()" class="btn btn-secondary" style="display: flex; align-items: center; gap: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    新增專案
                </button>
            </div>

            <div id="items-container">
                <!-- 專案將動態新增到這裡 -->
            </div>

            <input type="hidden" name="item_count" id="item_count" value="0">
        </div>

        <!-- 金額彙總 -->
        <div style="margin-bottom: 32px; padding: 20px; background: var(--bg-secondary); border-radius: var(--border-radius-md);">
            <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">金額彙總</h4>
            <div style="display: grid; gap: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 15px;">
                    <span style="color: var(--text-secondary);">小計：</span>
                    <span id="subtotal-display" style="font-weight: 600; color: var(--text-primary);">NT$ 0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 15px;">
                    <span style="color: var(--text-secondary);">稅額：</span>
                    <span id="tax-display" style="font-weight: 600; color: var(--text-primary);">NT$ 0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 2px solid var(--border-color); font-size: 18px; font-weight: 700;">
                    <span style="color: var(--text-primary);">總計：</span>
                    <span id="total-display" style="color: var(--primary-color);">NT$ 0.00</span>
                </div>
            </div>
        </div>

        <div style="margin-top: 32px; display: flex; gap: 12px; justify-content: flex-end;">
            <a href="/quotes/" class="btn btn-secondary">取消</a>
            <button type="submit" class="btn btn-primary">建立報價單</button>
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
// 報價單專案管理
let itemIndex = 0;
const catalogItems = <?php echo json_encode($catalog_items); ?>;

function addItem() {
    const container = document.getElementById('items-container');
    const itemCount = parseInt(document.getElementById('item_count').value);

    const itemDiv = document.createElement('div');
    itemDiv.className = 'quote-item';
    itemDiv.style.cssText = 'padding: 20px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--border-radius-md); margin-bottom: 16px; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 12px; align-items: end;';
    itemDiv.innerHTML = `
        <div>
            <label style="display: block; font-size: 13px; color: var(--text-tertiary); margin-bottom: 6px;">產品/服務 *</label>
            <select name="items[${itemCount}][catalog_item_id]" onchange="updateItemPrice(${itemCount})" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); background: var(--bg-primary); color: var(--text-primary);">
                <option value="">請選擇</option>
                ${catalogItems.map(item => `
                    <option value="${item.id}" data-price="${item.unit_price_cents}" data-tax="${item.tax_rate}" data-unit="${item.unit}">
                        ${item.sku} - ${item.name} (NT$ ${(item.unit_price_cents / 100).toFixed(2)})
                    </option>
                `).join('')}
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; color: var(--text-tertiary); margin-bottom: 6px;">數量 *</label>
            <input type="number" name="items[${itemCount}][qty]" value="1" min="0.0001" step="0.0001" onchange="updateItemPrice(${itemCount})" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); background: var(--bg-primary); color: var(--text-primary);">
        </div>
        <div>
            <label style="display: block; font-size: 13px; color: var(--text-tertiary); margin-bottom: 6px;">單價</label>
            <div id="price-${itemCount}" style="padding: 10px 12px; background: var(--bg-secondary); border-radius: var(--border-radius-sm); font-size: 14px; font-weight: 600; color: var(--text-primary);">NT$ 0.00</div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; color: var(--text-tertiary); margin-bottom: 6px;">小計</label>
            <div id="subtotal-${itemCount}" style="padding: 10px 12px; background: var(--bg-secondary); border-radius: var(--border-radius-sm); font-size: 14px; font-weight: 600; color: var(--text-primary);">NT$ 0.00</div>
        </div>
        <div>
            <button type="button" onclick="removeItem(this)" style="padding: 10px; background: none; border: none; color: var(--text-tertiary); cursor: pointer; border-radius: var(--border-radius-sm);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    `;

    container.appendChild(itemDiv);
    document.getElementById('item_count').value = itemCount + 1;
    itemIndex++;
}

function removeItem(button) {
    const itemDiv = button.closest('.quote-item');
    itemDiv.parentNode.removeChild(itemDiv);
    recalculateTotal();
}

function updateItemPrice(index) {
    const select = document.querySelector(`select[name="items[${index}][catalog_item_id]"]`);
    const quantityInput = document.querySelector(`input[name="items[${index}][qty]"]`);
    const priceDiv = document.getElementById(`price-${index}`);
    const subtotalDiv = document.getElementById(`subtotal-${index}`);

    if (select && quantityInput && priceDiv && subtotalDiv) {
        const selectedOption = select.options[select.selectedIndex];
        const priceCents = parseInt(selectedOption.dataset.price) || 0;
        const taxRate = parseFloat(selectedOption.dataset.tax) || 0;
        const quantity = parseFloat(quantityInput.value) || 0;

        const price = priceCents / 100;
        const subtotal = price * quantity;

        priceDiv.textContent = `NT$ ${price.toFixed(2)}`;
        subtotalDiv.textContent = `NT$ ${subtotal.toFixed(2)}`;

        recalculateTotal();
    }
}

function recalculateTotal() {
    let subtotalSum = 0;
    let totalTax = 0;

    document.querySelectorAll('.quote-item').forEach((itemDiv) => {
        const select = itemDiv.querySelector('select');
        const quantityInput = itemDiv.querySelector('input[name^="items"][name$="[qty]"]');

        if (select && quantityInput) {
            const selectedOption = select.options[select.selectedIndex];
            const priceCents = parseInt(selectedOption.dataset.price) || 0;
            const taxRate = parseFloat(selectedOption.dataset.tax) || 0;
            const quantity = parseFloat(quantityInput.value) || 0;

            const lineSubtotal = (priceCents / 100) * quantity;
            const tax = lineSubtotal * (taxRate / 100);

            subtotalSum += lineSubtotal;
            totalTax += tax;
        }
    });

    const total = subtotalSum + totalTax;

    document.getElementById('subtotal-display').textContent = `NT$ ${subtotalSum.toFixed(2)}`;
    document.getElementById('tax-display').textContent = `NT$ ${totalTax.toFixed(2)}`;
    document.getElementById('total-display').textContent = `NT$ ${total.toFixed(2)}`;
}

// 頁面載入時新增一個初始專案
document.addEventListener('DOMContentLoaded', function() {
    addItem();
});
</script>
