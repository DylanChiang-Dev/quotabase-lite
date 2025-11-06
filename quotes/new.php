<?php
/**
 * 新建报价单页面
 * Create New Quote Page
 *
 * @version v2.0.0
 * @description 报价单创建页面，支持客户选择、动态添加项目
 * @遵循宪法原则I: 安全优先开发 - XSS防护、CSRF验证
 * @遵循宪法原则II: 精确财务数据处理 - 金额以分存储
 * @遵循宪法原则III: 事务原子性 - 确保数据一致性
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
$customers = [];
$catalog_items = [];

// 获取客户列表和目录项列表
try {
    $customers = get_customer_list();
    $catalog_items = get_catalog_item_list();
} catch (Exception $e) {
    error_log("Get lists error: " . $e->getMessage());
    $error = '加载数据失败';
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '无效的请求，请重新提交。';
    } else {
        // 准备数据
        $data = [
            'customer_id' => intval($_POST['customer_id'] ?? 0),
            'status' => $_POST['status'] ?? 'draft',
            'issue_date' => $_POST['issue_date'] ?? get_current_date_utc(),
            'valid_until' => !empty($_POST['valid_until']) ? $_POST['valid_until'] : null,
            'note' => trim($_POST['note'] ?? '')
        ];

        // 处理报价项目
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

        // 创建报价单
        $result = create_quote($data, $items);

        if ($result['success']) {
            // 成功，重定向到详情页
            header('Location: /quotes/view.php?id=' . $result['id'] . '&success=' . urlencode('报价单创建成功'));
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

// 页面开始
html_start('新建报价单');

// 输出头部
page_header('新建报价单', [
    ['label' => '首页', 'url' => '/'],
    ['label' => '报价管理', 'url' => '/quotes/'],
    ['label' => '新建报价单', 'url' => '/quotes/new.php']
]);

?>

<div class="main-content">
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($error); ?></span>
        </div>
    <?php endif; ?>

    <?php card_start('新建报价单'); ?>

    <form method="POST" action="/quotes/new.php" id="quote-form">
        <?php echo csrf_input(); ?>

        <!-- 基本信息 -->
        <div style="margin-bottom: 32px;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">基本信息</h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                <?php
                // 客户选择
                $selected_customer = $_POST['customer_id'] ?? '';
                form_field('customer_id', '客户', 'select', array_column($customers, 'name', 'id'), [
                    'required' => true,
                    'selected' => $selected_customer,
                    'placeholder' => '请选择客户'
                ]);
                ?>

                <?php
                // 状态选择
                $selected_status = $_POST['status'] ?? 'draft';
                $status_options = [
                    'draft' => '草稿',
                    'sent' => '已发送',
                    'accepted' => '已接受',
                    'rejected' => '已拒绝',
                    'expired' => '已过期'
                ];
                form_field('status', '状态', 'select', $status_options, [
                    'required' => true,
                    'selected' => $selected_status
                ]);
                ?>

                <?php
                // 开票日期
                form_field('issue_date', '开票日期', 'date', [], [
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
                // 备注
                form_field('note', '备注', 'textarea', [], [
                    'placeholder' => '请输入备注信息（可选）',
                    'rows' => 3,
                    'value' => $_POST['note'] ?? ''
                ]);
                ?>
            </div>
        </div>

        <!-- 报价项目 -->
        <div style="margin-bottom: 32px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="font-size: 18px; font-weight: 600; color: var(--text-primary);">报价项目</h3>
                <button type="button" onclick="addItem()" class="btn btn-secondary" style="display: flex; align-items: center; gap: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    添加项目
                </button>
            </div>

            <div id="items-container">
                <!-- 项目将动态添加到这里 -->
            </div>

            <input type="hidden" name="item_count" id="item_count" value="0">
        </div>

        <!-- 金额汇总 -->
        <div style="margin-bottom: 32px; padding: 20px; background: var(--bg-secondary); border-radius: var(--border-radius-md);">
            <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">金额汇总</h4>
            <div style="display: grid; gap: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 15px;">
                    <span style="color: var(--text-secondary);">小计：</span>
                    <span id="subtotal-display" style="font-weight: 600; color: var(--text-primary);">NT$ 0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 15px;">
                    <span style="color: var(--text-secondary);">税额：</span>
                    <span id="tax-display" style="font-weight: 600; color: var(--text-primary);">NT$ 0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 2px solid var(--border-color); font-size: 18px; font-weight: 700;">
                    <span style="color: var(--text-primary);">总计：</span>
                    <span id="total-display" style="color: var(--primary-color);">NT$ 0.00</span>
                </div>
            </div>
        </div>

        <div style="margin-top: 32px; display: flex; gap: 12px; justify-content: flex-end;">
            <a href="/quotes/" class="btn btn-secondary">取消</a>
            <button type="submit" class="btn btn-primary">创建报价单</button>
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
// 报价单项目管理
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
            <label style="display: block; font-size: 13px; color: var(--text-tertiary); margin-bottom: 6px;">产品/服务 *</label>
            <select name="items[${itemCount}][catalog_item_id]" onchange="updateItemPrice(${itemCount})" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); background: var(--bg-primary); color: var(--text-primary);">
                <option value="">请选择</option>
                ${catalogItems.map(item => `
                    <option value="${item.id}" data-price="${item.unit_price_cents}" data-tax="${item.tax_rate}" data-unit="${item.unit}">
                        ${item.sku} - ${item.name} (NT$ ${(item.unit_price_cents / 100).toFixed(2)})
                    </option>
                `).join('')}
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; color: var(--text-tertiary); margin-bottom: 6px;">数量 *</label>
            <input type="number" name="items[${itemCount}][qty]" value="1" min="0.0001" step="0.0001" onchange="updateItemPrice(${itemCount})" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); background: var(--bg-primary); color: var(--text-primary);">
        </div>
        <div>
            <label style="display: block; font-size: 13px; color: var(--text-tertiary); margin-bottom: 6px;">单价</label>
            <div id="price-${itemCount}" style="padding: 10px 12px; background: var(--bg-secondary); border-radius: var(--border-radius-sm); font-size: 14px; font-weight: 600; color: var(--text-primary);">NT$ 0.00</div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; color: var(--text-tertiary); margin-bottom: 6px;">小计</label>
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

// 页面加载时添加一个初始项目
document.addEventListener('DOMContentLoaded', function() {
    addItem();
});
</script>
