<?php
/**
 * 編輯報價單頁面
 * Edit Quote Page with line discount handling
 *
 * @version v2.1.0
 * @description 草稿報價單明細可編修，並新增折扣金額計算
 * @遵循憲法原則I: 安全優先開發
 */

define('QUOTABASE_SYSTEM', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../partials/ui.php';

if (!is_logged_in()) {
    header('Location: /login.php');
    exit;
}

$quote_id = intval($_GET['id'] ?? 0);
if ($quote_id <= 0) {
    header('Location: /quotes/?error=' . urlencode('無效的報價單ID'));
    exit;
}

$error = '';
$success = isset($_GET['success']) ? trim($_GET['success']) : '';
$quote = null;
$form_items_override = null;

try {
    $quote = get_quote($quote_id);
    if (!$quote) {
        header('Location: /quotes/?error=' . urlencode('報價單不存在'));
        exit;
    }
} catch (Exception $e) {
    error_log("Get quote error: " . $e->getMessage());
    $error = '載入報價單資訊失敗';
}

if (!$error && isset($_GET['error'])) {
    $error = trim($_GET['error']);
}

$default_tax_rate = get_default_tax_rate();

$catalog_groups = [
    'product' => ['label' => '產品', 'items' => []],
    'service' => ['label' => '服務', 'items' => []],
];
$catalog_map = [];

try {
    $catalog_items = get_catalog_item_list();
    foreach ($catalog_items as $item) {
        $type = $item['type'] === 'service' ? 'service' : 'product';
        $catalog_groups[$type]['items'][] = $item;
        $catalog_map[(int)$item['id']] = [
            'id' => (int)$item['id'],
            'type' => $type,
            'sku' => $item['sku'],
            'name' => $item['name'],
            'unit' => $item['unit'],
            'unit_price_cents' => (int)$item['unit_price_cents'],
            'tax_rate' => $item['tax_rate'] !== null ? (float)$item['tax_rate'] : null,
        ];
    }
} catch (Exception $e) {
    error_log("Catalog list error: " . $e->getMessage());
    if (!$error) {
        $error = '載入產品/服務列表失敗';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '無效的請求，請重新提交。';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'update_status') {
            $new_status = $_POST['status'] ?? '';
            $result = update_quote_status($quote_id, $new_status);
            if ($result['success']) {
                header('Location: /quotes/view.php?id=' . $quote_id . '&success=' . urlencode('狀態更新成功'));
                exit;
            }
            $error = $result['error'] ?? '狀態更新失敗';
            $quote['status'] = $new_status;
        } elseif ($action === 'update_items') {
            if ($quote['status'] !== 'draft') {
                $error = '僅草稿狀態可編輯明細';
            } else {
                $result = process_quote_edit($quote, $_POST);
                if ($result['success']) {
                    header('Location: /quotes/edit.php?id=' . $quote_id . '&success=' . urlencode('明細已更新'));
                    exit;
                }
                $error = $result['error'] ?? '更新明細失敗';
                $form_items_override = $_POST['items'] ?? [];
            }
        } else {
            $error = '未知操作請求';
        }
    }
}

$initial_items = [];
if ($form_items_override !== null) {
    foreach ($form_items_override as $item) {
        $catalog_item_id = intval($item['catalog_item_id'] ?? 0);
        $catalog_item = $catalog_map[$catalog_item_id] ?? null;

        $qty_raw = trim((string)($item['qty'] ?? $item['quantity'] ?? ''));
        $unit_price_cents = null;
        if (isset($item['unit_price_cents']) && $item['unit_price_cents'] !== '') {
            $unit_price_cents = intval($item['unit_price_cents']);
        } elseif (isset($item['unit_price']) && $item['unit_price'] !== '') {
            $unit_price_cents = amount_to_cents($item['unit_price']);
        } elseif ($catalog_item) {
            $unit_price_cents = $catalog_item['unit_price_cents'];
        }

        $discount_cents = null;
        if (isset($item['discount_cents']) && $item['discount_cents'] !== '') {
            $discount_cents = intval($item['discount_cents']);
        } elseif (isset($item['discount']) && $item['discount'] !== '') {
            $discount_cents = amount_to_cents($item['discount']);
        }

        $tax_rate_value = '';
        if (isset($item['tax_rate']) && $item['tax_rate'] !== '') {
            $tax_rate_value = (string)$item['tax_rate'];
        } elseif ($catalog_item && $catalog_item['tax_rate'] !== null) {
            $tax_rate_value = (string)$catalog_item['tax_rate'];
        }

        $description = trim($item['description'] ?? '');
        if ($description === '' && $catalog_item) {
            $description = $catalog_item['name'];
        }

        $unit = trim($item['unit'] ?? '');
        if ($catalog_item) {
            $unit = $catalog_item['unit'] ?? $unit;
            if (empty($unit)) {
                $unit = $catalog_item['type'] === 'service' ? 'time' : 'pcs';
            }
        }
        if ($unit === '') {
            $unit = 'pcs';
        }

        $initial_items[] = [
            'catalog_item_id' => $catalog_item_id,
            'description' => $description,
            'qty' => $qty_raw,
            'unit' => $unit,
            'unit_price_cents' => $unit_price_cents,
            'tax_rate' => $tax_rate_value,
            'discount_cents' => $discount_cents,
        ];
    }
} else {
    foreach ($quote['items'] as $item) {
        $qty_str = number_format((float)$item['qty'], 4, '.', '');
        $qty_str = rtrim(rtrim($qty_str, '0'), '.');
        if ($qty_str === '') {
            $qty_str = '0';
        }

        $initial_items[] = [
            'catalog_item_id' => (int)$item['catalog_item_id'],
            'description' => $item['description'],
            'qty' => $qty_str,
            'unit' => $item['unit'],
            'unit_price_cents' => (int)$item['unit_price_cents'],
            'tax_rate' => (string)$item['tax_rate'],
            'discount_cents' => (int)($item['discount_cents'] ?? 0),
        ];
    }
}

if (empty($initial_items)) {
    $initial_items[] = [
        'catalog_item_id' => 0,
        'description' => '',
        'qty' => '',
        'unit' => '',
        'unit_price_cents' => null,
        'tax_rate' => '',
        'discount_cents' => null,
    ];
}

$initial_totals = [
    'subtotal' => 0,
    'tax' => 0,
    'total' => 0,
];
foreach ($initial_items as $item) {
    $qty = is_numeric($item['qty']) ? (float)$item['qty'] : 0;
    $unit_price_cents = $item['unit_price_cents'] ?? 0;
    $discount_cents = $item['discount_cents'] ?? 0;
    if ($unit_price_cents === null) {
        $unit_price_cents = 0;
    }
    if ($discount_cents === null) {
        $discount_cents = 0;
    }
    $tax_rate = is_numeric($item['tax_rate']) ? (float)$item['tax_rate'] : (float)$default_tax_rate;
    $line_subtotal = calculate_line_subtotal($qty, $unit_price_cents, $discount_cents);
    $line_tax = calculate_line_tax($line_subtotal, $tax_rate);
    $initial_totals['subtotal'] += $line_subtotal;
    $initial_totals['tax'] += $line_tax;
    $initial_totals['total'] += $line_subtotal + $line_tax;
}

ob_start();
?>
<option value="">請選擇產品/服務</option>
<?php foreach ($catalog_groups as $group): ?>
    <?php if (!empty($group['items'])): ?>
        <optgroup label="<?php echo h($group['label']); ?>">
            <?php foreach ($group['items'] as $item): ?>
                <option value="<?php echo (int)$item['id']; ?>">
                    <?php echo h($item['sku'] . ' · ' . $item['name']); ?>
                </option>
            <?php endforeach; ?>
        </optgroup>
    <?php endif; ?>
<?php endforeach; ?>
<?php
$catalog_options_html = ob_get_clean();

$catalog_map_json = json_encode($catalog_map, JSON_UNESCAPED_UNICODE);
$initial_items_json = json_encode($initial_items, JSON_UNESCAPED_UNICODE);
$default_tax_rate_json = json_encode($default_tax_rate);
$unit_labels_json = json_encode(UNITS, JSON_UNESCAPED_UNICODE);

html_start('編輯報價單');

page_header('編輯報價單', [
    ['label' => '首頁', 'url' => '/'],
    ['label' => '報價管理', 'url' => '/quotes/'],
    ['label' => '編輯報價單', 'url' => '/quotes/edit.php?id=' . $quote_id]
]);
?>
<div class="main-content">
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <span class="alert-message"><?php echo h($success); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($quote): ?>
        <?php card_start('報價單基本資訊'); ?>
            <div class="info-grid">
                <div class="info-item">
                    <label>報價單號</label>
                    <div class="info-value monospace"><?php echo h($quote['quote_number']); ?></div>
                </div>
                <div class="info-item">
                    <label>客戶</label>
                    <div class="info-value"><?php echo h($quote['customer_name']); ?></div>
                </div>
                <div class="info-item">
                    <label>開票日期</label>
                    <div class="info-value"><?php echo format_date($quote['issue_date']); ?></div>
                </div>
                <div class="info-item">
                    <label>有效期至</label>
                    <div class="info-value"><?php echo $quote['valid_until'] ? format_date($quote['valid_until']) : '—'; ?></div>
                </div>
                <div class="info-item">
                    <label>狀態</label>
                    <div class="info-value"><?php echo get_status_badge($quote['status']); ?></div>
                </div>
                <div class="info-item">
                    <label>備註</label>
                    <div class="info-value multiline"><?php echo nl2br(h($quote['note'] ?? '')); ?></div>
                </div>
            </div>
        <?php card_end(); ?>

        <?php if ($quote['status'] === 'draft'): ?>
            <?php card_start('編輯報價明細'); ?>
                <p class="section-hint">草稿狀態可自由增刪調整明細，折扣金額會自動換算百分比與合計。</p>
                <div id="quote-items-error" class="form-message hidden"></div>

                <form method="POST" action="/quotes/edit.php?id=<?php echo $quote_id; ?>" id="quote-items-form">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="update_items">

                    <div id="quote-items-container" class="quote-items-container"></div>

                    <button type="button" class="btn btn-secondary add-item-btn" id="add-quote-item-btn">
                        <span class="btn-icon-circle">＋</span>
                        新增專案
                    </button>

                    <div class="quote-totals" id="quote-totals">
                        <div class="quote-total-card">
                            <span>稅前小計</span>
                            <strong id="quote-total-subtotal"><?php echo format_currency_cents($initial_totals['subtotal']); ?></strong>
                        </div>
                        <div class="quote-total-card">
                            <span>稅額</span>
                            <strong id="quote-total-tax"><?php echo format_currency_cents($initial_totals['tax']); ?></strong>
                        </div>
                        <div class="quote-total-card highlight">
                            <span>含稅總計</span>
                            <strong id="quote-total-total"><?php echo format_currency_cents($initial_totals['total']); ?></strong>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="/quotes/view.php?id=<?php echo $quote_id; ?>" class="btn btn-secondary">取消</a>
                        <button type="submit" class="btn btn-primary">儲存明細</button>
                    </div>
                </form>
            <?php card_end(); ?>
        <?php else: ?>
            <?php card_start('報價單明細'); ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>專案</th>
                                <th class="text-right">數量</th>
                                <th class="text-right">單價</th>
                                <th class="text-right">折扣</th>
                                <th class="text-right">折扣%</th>
                                <th class="text-right">稅率</th>
                                <th class="text-right">稅額</th>
                                <th class="text-right">含稅總計</th>
                            </tr>
                        </thead>
                        <tbody>
                    <?php foreach ($quote['items'] as $item): ?>
                        <?php
                        $discount_cents = (int)($item['discount_cents'] ?? 0);
                        $discount_percent = $item['discount_percent'] ?? calculate_discount_percent($discount_cents, calculate_line_gross($item['qty'], $item['unit_price_cents']));
                        $unit_code = $item['unit'] ?? '';
                        $unit_label = UNITS[$unit_code] ?? $unit_code;
                        ?>
                                <tr>
                                    <td class="monospace"><?php echo h($item['sku'] ?? ''); ?></td>
                                    <td>
                                        <div class="table-text-strong"><?php echo h($item['description']); ?></div>
                                        <small class="text-muted"><?php echo h($item['catalog_name'] ?? ''); ?></small>
                                    </td>
                                    <td class="text-right">
                                        <?php echo h(number_format((float)$item['qty'], 4, '.', '')); ?>
                                        <?php if ($unit_label): ?>
                                            <span class="text-muted"><?php echo h($unit_label); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right"><?php echo format_currency_cents($item['unit_price_cents']); ?></td>
                                    <td class="text-right"><?php echo $discount_cents > 0 ? format_currency_cents($discount_cents) : '—'; ?></td>
                                    <td class="text-right"><?php echo $discount_cents > 0 ? h(number_format($discount_percent, 2)) . '%' : '—'; ?></td>
                                    <td class="text-right"><?php echo h(number_format((float)$item['tax_rate'], 2)) . '%'; ?></td>
                                    <td class="text-right"><?php echo format_currency_cents($item['line_tax_cents']); ?></td>
                                    <td class="text-right"><?php echo format_currency_cents($item['line_total_cents']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php card_end(); ?>
        <?php endif; ?>

        <?php card_start('狀態管理'); ?>
            <form method="POST" action="/quotes/edit.php?id=<?php echo $quote_id; ?>" class="status-form">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="update_status">

                <div class="status-grid">
                    <div class="status-field">
                        <label>當前狀態</label>
                        <div class="info-value"><?php echo get_status_badge($quote['status']); ?></div>
                    </div>
                    <div class="status-field">
                        <label>更新為</label>
                        <?php
                        $status_options = [
                            'draft' => '草稿',
                            'sent' => '已傳送',
                            'accepted' => '已接受',
                            'rejected' => '已拒絕',
                            'expired' => '已過期'
                        ];
                        form_field('status', '', 'select', $status_options, [
                            'required' => true,
                            'selected' => $quote['status'],
                            'placeholder' => '選擇新狀態'
                        ]);
                        ?>
                    </div>
                </div>

                <div class="status-legend">
                    <strong>狀態說明</strong>
                    <ul>
                        <li><span>草稿</span>：可編輯明細</li>
                        <li><span>已傳送</span>：已交付客戶，可繼續變更狀態</li>
                        <li><span>已接受 / 已拒絕 / 已過期</span>：明細鎖定，僅供檢視</li>
                    </ul>
                </div>

                <div class="form-actions">
                    <a href="/quotes/view.php?id=<?php echo $quote_id; ?>" class="btn btn-secondary">返回詳情</a>
                    <button type="submit" class="btn btn-primary">更新狀態</button>
                </div>
            </form>
        <?php card_end(); ?>
    <?php endif; ?>
</div>

<template id="quote-item-template">
    <div class="quote-item-row" data-index="__INDEX__">
        <div class="quote-item-field quote-item-field--wide">
            <label>產品/服務 *</label>
            <select name="items[__INDEX__][catalog_item_id]" class="catalog-select" required>
                <?php echo $catalog_options_html; ?>
            </select>
        </div>
        <div class="quote-item-field quote-item-field--wide">
            <label>描述 *</label>
            <input type="text" name="items[__INDEX__][description]" class="description-input" maxlength="500" placeholder="請輸入描述">
        </div>
        <div class="quote-item-field quote-item-field--sm">
            <label>數量 *</label>
            <input type="number" name="items[__INDEX__][qty]" class="qty-input" min="0.0001" step="0.0001" required>
        </div>
        <div class="quote-item-field quote-item-field--sm">
            <label>單位</label>
            <input type="hidden" name="items[__INDEX__][unit]" class="unit-input" value="pcs">
            <div class="unit-display" data-role="unit-label">—</div>
        </div>
        <div class="quote-item-field quote-item-field--sm">
            <label>單價 (NT$)</label>
            <input type="number" name="items[__INDEX__][unit_price]" class="unit-price-input" min="0" step="0.01" placeholder="0.00">
            <input type="hidden" name="items[__INDEX__][unit_price_cents]" class="unit-price-cents-input">
        </div>
        <div class="quote-item-field quote-item-field--sm">
            <label>稅率 (%)</label>
            <input type="number" name="items[__INDEX__][tax_rate]" class="tax-rate-input" min="0" max="100" step="0.01" placeholder="0">
        </div>
        <div class="quote-item-field quote-item-field--sm">
            <label>折扣金額 (NT$)</label>
            <input type="number" name="items[__INDEX__][discount]" class="discount-input" min="0" step="0.01" placeholder="0.00">
            <input type="hidden" name="items[__INDEX__][discount_cents]" class="discount-cents-input">
            <div class="field-error" data-role="discount-error"></div>
        </div>
        <div class="quote-item-field quote-item-field--summary">
            <label>折扣 / 金額摘要</label>
            <div class="quote-item-summary">
                <div class="summary-line">
                    <span>折扣%</span>
                    <strong data-role="discount-percent">0%</strong>
                </div>
                <div class="summary-line">
                    <span>稅額</span>
                    <strong data-role="line-tax">NT$ 0.00</strong>
                </div>
                <div class="summary-line">
                    <span>稅前小計</span>
                    <strong data-role="line-subtotal">NT$ 0.00</strong>
                </div>
                <div class="summary-line">
                    <span>含稅總計</span>
                    <strong data-role="line-total">NT$ 0.00</strong>
                </div>
            </div>
        </div>
        <div class="quote-item-field quote-item-field--actions">
            <button type="button" class="btn-icon remove-item-btn" aria-label="刪除明細">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
    </div>
</template>

<?php bottom_tab_navigation(); ?>

<?php if ($quote && $quote['status'] === 'draft'): ?>
<script>
(function() {
    const catalogItems = <?php echo $catalog_map_json; ?>;
    const initialItems = <?php echo $initial_items_json; ?>;
    const defaultTaxRate = <?php echo $default_tax_rate_json; ?>;
    const unitLabels = <?php echo $unit_labels_json; ?>;
    const defaultUnit = "pcs";
    const container = document.getElementById('quote-items-container');
    const addButton = document.getElementById('add-quote-item-btn');
    const form = document.getElementById('quote-items-form');
    const totals = {
        subtotal: document.getElementById('quote-total-subtotal'),
        tax: document.getElementById('quote-total-tax'),
        total: document.getElementById('quote-total-total'),
    };
    const errorBox = document.getElementById('quote-items-error');
    const template = document.getElementById('quote-item-template');
    let itemIndex = 0;

    function formatCurrency(cents) {
        const value = (cents || 0) / 100;
        return 'NT$ ' + value.toFixed(2);
    }

    function clamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }

    function createRowElement(index) {
        const html = template.innerHTML.replace(/__INDEX__/g, index);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        return wrapper.firstElementChild;
    }

    function applyUnit(row, unitCode) {
        const hidden = row.querySelector('.unit-input');
        const labelEl = row.querySelector('[data-role="unit-label"]');
        const normalized = unitLabels[unitCode] ? unitCode : defaultUnit;
        if (hidden) {
            hidden.value = normalized;
        }
        if (labelEl) {
            labelEl.textContent = unitLabels[normalized] || normalized;
        }
    }

    function handleCatalogChange(row) {
        const select = row.querySelector('.catalog-select');
        const catalogId = select.value;
        const data = catalogItems[catalogId];
        if (!data) {
            calculateRow(row);
            updateTotals();
            return;
        }

        const descriptionInput = row.querySelector('.description-input');
        if (descriptionInput.dataset.userEdited !== 'true') {
            descriptionInput.value = data.name || '';
        }

        applyUnit(row, data.unit || defaultUnit);

        const unitPriceInput = row.querySelector('.unit-price-input');
        const unitPriceCentsInput = row.querySelector('.unit-price-cents-input');
        if (unitPriceInput.dataset.userEdited !== 'true') {
            if (data.unit_price_cents) {
                unitPriceInput.value = (data.unit_price_cents / 100).toFixed(2);
                unitPriceCentsInput.value = data.unit_price_cents;
            } else {
                unitPriceInput.value = '';
                unitPriceCentsInput.value = '';
            }
        }

        const taxRateInput = row.querySelector('.tax-rate-input');
        if (taxRateInput.dataset.userEdited !== 'true') {
            if (data.tax_rate !== null && data.tax_rate !== undefined) {
                taxRateInput.value = data.tax_rate;
            } else {
                taxRateInput.value = defaultTaxRate;
            }
        }

        calculateRow(row);
        updateTotals();
    }

    function applyDataToRow(row, data) {
        const catalogSelect = row.querySelector('.catalog-select');
        if (data.catalog_item_id) {
            catalogSelect.value = String(data.catalog_item_id);
        }

        const descriptionInput = row.querySelector('.description-input');
        descriptionInput.value = data.description || '';
        descriptionInput.dataset.userEdited = 'false';

        const qtyInput = row.querySelector('.qty-input');
        qtyInput.value = data.qty !== undefined && data.qty !== null ? data.qty : '';

        applyUnit(row, data.unit || defaultUnit);

        const unitPriceInput = row.querySelector('.unit-price-input');
        const unitPriceCentsInput = row.querySelector('.unit-price-cents-input');
        if (data.unit_price_cents !== undefined && data.unit_price_cents !== null) {
            unitPriceInput.value = (data.unit_price_cents / 100).toFixed(2);
            unitPriceCentsInput.value = data.unit_price_cents;
            unitPriceInput.dataset.userEdited = 'false';
        }

        const taxRateInput = row.querySelector('.tax-rate-input');
        if (data.tax_rate !== undefined && data.tax_rate !== null && data.tax_rate !== '') {
            taxRateInput.value = data.tax_rate;
            taxRateInput.dataset.userEdited = 'false';
        }

        const discountInput = row.querySelector('.discount-input');
        const discountCentsInput = row.querySelector('.discount-cents-input');
        if (data.discount_cents !== undefined && data.discount_cents !== null) {
            discountInput.value = (data.discount_cents / 100).toFixed(2);
            discountCentsInput.value = data.discount_cents;
            discountInput.dataset.userEdited = 'false';
        }
    }

    function calculateRow(row) {
        const qtyInput = row.querySelector('.qty-input');
        const unitPriceInput = row.querySelector('.unit-price-input');
        const unitPriceCentsInput = row.querySelector('.unit-price-cents-input');
        const taxRateInput = row.querySelector('.tax-rate-input');
        const discountInput = row.querySelector('.discount-input');
        const discountCentsInput = row.querySelector('.discount-cents-input');

        const discountError = row.querySelector('[data-role="discount-error"]');
        const lineSubtotalEl = row.querySelector('[data-role="line-subtotal"]');
        const lineTaxEl = row.querySelector('[data-role="line-tax"]');
        const lineTotalEl = row.querySelector('[data-role="line-total"]');
        const discountPercentEl = row.querySelector('[data-role="discount-percent"]');

        const qty = parseFloat(qtyInput.value);
        const qtyValue = isFinite(qty) ? Math.max(qty, 0) : 0;

        const unitPriceValue = parseFloat(unitPriceInput.value);
        let unitPriceCents = isFinite(unitPriceValue) ? Math.round(Math.max(unitPriceValue, 0) * 100) : 0;
        if (unitPriceInput.value.trim() === '') {
            unitPriceCents = 0;
            unitPriceCentsInput.value = '';
        } else {
            unitPriceCentsInput.value = unitPriceCents;
        }

        const discountValue = parseFloat(discountInput.value);
        let discountCents = isFinite(discountValue) ? Math.round(Math.max(discountValue, 0) * 100) : 0;
        if (discountInput.value.trim() === '') {
            discountCentsInput.value = '';
        } else {
            discountCentsInput.value = discountCents;
        }

        const taxRateValue = parseFloat(taxRateInput.value);
        const appliedTaxRate = isFinite(taxRateValue) ? clamp(taxRateValue, 0, 100) : defaultTaxRate;

        const grossCents = Math.round(qtyValue * unitPriceCents);
        const effectiveDiscount = Math.min(discountCents, grossCents);

        if (discountCents > grossCents) {
            discountError.textContent = '折扣金額超過行金額';
            row.dataset.hasError = 'true';
        } else {
            discountError.textContent = '';
            row.dataset.hasError = '';
        }

        const lineSubtotal = grossCents - effectiveDiscount;
        const lineTax = Math.round(lineSubtotal * (appliedTaxRate / 100));
        const lineTotal = lineSubtotal + lineTax;
        const discountPercent = grossCents > 0 ? (effectiveDiscount / grossCents) * 100 : 0;

        lineSubtotalEl.textContent = formatCurrency(lineSubtotal);
        lineTaxEl.textContent = formatCurrency(lineTax);
        lineTotalEl.textContent = formatCurrency(lineTotal);
        discountPercentEl.textContent = discountPercent.toFixed(2) + '%';

        row.dataset.lineSubtotal = lineSubtotal;
        row.dataset.lineTax = lineTax;
        row.dataset.lineTotal = lineTotal;
    }

    function updateTotals() {
        let subtotal = 0;
        let tax = 0;
        let total = 0;

        container.querySelectorAll('.quote-item-row').forEach((row) => {
            subtotal += parseInt(row.dataset.lineSubtotal || '0', 10);
            tax += parseInt(row.dataset.lineTax || '0', 10);
            total += parseInt(row.dataset.lineTotal || '0', 10);
        });

        totals.subtotal.textContent = formatCurrency(subtotal);
        totals.tax.textContent = formatCurrency(tax);
        totals.total.textContent = formatCurrency(total);

        if (errorBox) {
            errorBox.classList.add('hidden');
            errorBox.textContent = '';
        }
    }

    function attachRowEvents(row) {
        const descriptionInput = row.querySelector('.description-input');
        const unitPriceInput = row.querySelector('.unit-price-input');
        const taxRateInput = row.querySelector('.tax-rate-input');
        const discountInput = row.querySelector('.discount-input');
        const qtyInput = row.querySelector('.qty-input');

        descriptionInput.addEventListener('input', () => { descriptionInput.dataset.userEdited = 'true'; });

        unitPriceInput.addEventListener('input', () => {
            unitPriceInput.dataset.userEdited = 'true';
            calculateRow(row);
            updateTotals();
        });
        unitPriceInput.addEventListener('blur', () => {
            if (unitPriceInput.value !== '') {
                const value = parseFloat(unitPriceInput.value);
                if (isFinite(value)) {
                    unitPriceInput.value = value.toFixed(2);
                }
            }
        });

        taxRateInput.addEventListener('input', () => {
            taxRateInput.dataset.userEdited = 'true';
            calculateRow(row);
            updateTotals();
        });

        discountInput.addEventListener('input', () => {
            discountInput.dataset.userEdited = 'true';
            calculateRow(row);
            updateTotals();
        });
        discountInput.addEventListener('blur', () => {
            if (discountInput.value !== '') {
                const value = parseFloat(discountInput.value);
                if (isFinite(value)) {
                    discountInput.value = value.toFixed(2);
                }
            }
        });

        qtyInput.addEventListener('input', () => {
            calculateRow(row);
            updateTotals();
        });
        qtyInput.addEventListener('blur', () => {
            if (qtyInput.value !== '') {
                const value = parseFloat(qtyInput.value);
                if (isFinite(value)) {
                    qtyInput.value = value.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
                }
            }
        });

        row.querySelector('.catalog-select').addEventListener('change', () => handleCatalogChange(row));
        row.querySelector('.remove-item-btn').addEventListener('click', () => {
            row.remove();
            if (!container.querySelector('.quote-item-row')) {
                addItemRow({});
            }
            updateTotals();
        });
    }

    function addItemRow(data) {
        const index = itemIndex++;
        const row = createRowElement(index);
        container.appendChild(row);
        attachRowEvents(row);
        const rowData = Object.assign({
            catalog_item_id: '',
            description: '',
            qty: '',
            unit: defaultUnit,
            unit_price_cents: null,
            tax_rate: defaultTaxRate,
            discount_cents: null
        }, data || {});
        applyDataToRow(row, rowData);
        calculateRow(row);
        updateTotals();
    }

    function validateForm() {
        let valid = true;
        const rows = container.querySelectorAll('.quote-item-row');
        if (!rows.length) {
            valid = false;
        }

        rows.forEach((row) => {
            const catalogSelect = row.querySelector('.catalog-select');
            const qtyInput = row.querySelector('.qty-input');
            const discountError = row.querySelector('[data-role="discount-error"]');

            catalogSelect.classList.remove('input-error');
            qtyInput.classList.remove('input-error');

            if (!catalogSelect.value) {
                catalogSelect.classList.add('input-error');
                valid = false;
            }

            const qty = parseFloat(qtyInput.value);
            if (!isFinite(qty) || qty <= 0) {
                qtyInput.classList.add('input-error');
                valid = false;
            }

            if (discountError && discountError.textContent) {
                valid = false;
            }
        });

        if (!valid && errorBox) {
            errorBox.classList.remove('hidden');
            errorBox.textContent = '請修正標記專案後再儲存。';
            window.scrollTo({ top: errorBox.offsetTop - 120, behavior: 'smooth' });
        }

        return valid;
    }

    addButton.addEventListener('click', () => addItemRow({}));

    form.addEventListener('submit', (event) => {
        if (!validateForm()) {
            event.preventDefault();
        }
    });

    if (initialItems && initialItems.length) {
        initialItems.forEach((item) => addItemRow(item));
    } else {
        addItemRow({});
    }
})();
</script>
<?php endif; ?>

<?php html_end(); ?>
