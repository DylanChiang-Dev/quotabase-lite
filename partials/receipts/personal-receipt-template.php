<?php
$company = $data['company'] ?? [];
$quote = $data['quote'] ?? [];
$items = $data['items'] ?? [];
$subtotalCents = $data['subtotal_cents'] ?? 0;
$taxCents = $data['tax_cents'] ?? 0;
$totalCents = $data['total_cents'] ?? 0;
$issuedOnDisplay = $data['issued_on_display'] ?? '';
$customerTaxId = $data['customer_tax_id'] ?? '';
$qrImage = $data['qr_image'] ?? '';
$hashShort = $data['hash_short'] ?? '';
$stampImage = $data['stamp_image'] ?? null;
$printTerms = $data['print_terms'] ?? '';
$printTermsHtml = $data['print_terms_html'] ?? '';
$consent = $data['consent'] ?? null;
$amountUppercase = $data['amount_uppercase'] ?? '';
$verifyUrl = $data['verify_url'] ?? '';
?>
<style>
    body {
        font-family: "Noto Sans TC", "PingFang TC", "Heiti TC", "Microsoft JhengHei", sans-serif;
        font-size: 10pt;
        color: #111;
        line-height: 1.35;
        margin: 0;
    }
    .receipt-container {
        width: 100%;
        max-width: 190mm;
        margin: 0 auto;
        padding: 0 4mm;
        box-sizing: border-box;
    }
    .receipt-header {
        text-align: center;
        margin-bottom: 6mm;
    }
    .receipt-header h1 {
        font-size: 18pt;
        margin-bottom: 4pt;
        letter-spacing: 0.5pt;
    }
    .info-grid {
        display: table;
        width: 100%;
        margin-bottom: 3mm;
    }
    .info-cell {
        display: table-cell;
        width: 50%;
        vertical-align: top;
        padding-right: 3mm;
    }
    .info-block h3 {
        font-size: 10pt;
        margin-bottom: 2pt;
        color: #555;
    }
    .info-block p {
        margin: 0;
        font-size: 9.5pt;
    }
    table.items {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 4mm;
        font-size: 9pt;
        table-layout: fixed;
    }
    table.items th,
    table.items td {
        border: 1px solid #ccc;
        padding: 4pt 3pt;
        word-break: keep-all;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    table.items th {
        background: #f5f5f5;
        font-size: 9.5pt;
    }
    .totals {
        width: 60%;
        margin-left: auto;
        border-collapse: collapse;
        font-size: 9.5pt;
    }
    .totals td {
        padding: 3pt 5pt;
    }
    .totals tr:last-child td {
        font-weight: bold;
        border-top: 1px solid #444;
        font-size: 10.5pt;
    }
    .stamp-area {
        margin-top: 6mm;
        text-align: right;
    }
    .stamp-area img {
        width: 15mm;
        height: auto;
    }
    .stamp-placeholder {
        font-size: 10pt;
        color: #b71c1c;
    }
    .qr-block {
        display: flex;
        justify-content: flex-end;
        gap: 4mm;
        align-items: center;
        margin-top: 4mm;
    }
    .qr-block img {
        width: 25mm;
        height: 25mm;
    }
    .qr-text {
        font-size: 9pt;
    }
    .hash-label {
        font-size: 9pt;
        color: #444;
        text-align: right;
        margin-top: 1mm;
    }
</style>

<div class="receipt-container">
    <div class="receipt-header">
        <h1>個人收據</h1>
    </div>

    <div class="info-grid">
        <div class="info-cell">
            <div class="info-block">
                <h3>開立人</h3>
                <p><?php echo h($company['name'] ?? ''); ?></p>
                <?php if (!empty($company['address'])): ?>
                    <p><?php echo h($company['address']); ?></p>
                <?php endif; ?>
                <?php if (!empty($company['contact'])): ?>
                    <p><?php echo h($company['contact']); ?></p>
                <?php endif; ?>
                <?php if (!empty($company['tax_id'])): ?>
                    <p>證號：<?php echo h($company['tax_id']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="info-cell">
            <div class="info-block">
                <h3>受款人資訊</h3>
                <p>客戶：<?php echo h($quote['customer_name'] ?? ''); ?></p>
                <?php if (!empty($quote['email'])): ?>
                    <p>Email：<?php echo h($quote['email']); ?></p>
                <?php endif; ?>
                <?php if (!empty($quote['phone'])): ?>
                    <p>電話：<?php echo h($quote['phone']); ?></p>
                <?php endif; ?>
                <?php if (!empty($customerTaxId)): ?>
                    <p>統編：<?php echo h($customerTaxId); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="info-grid" style="margin-top: -6mm;">
        <div class="info-cell">
            <div class="info-block">
                <h3>收據資訊</h3>
                <p>序號：<?php echo h($quote['quote_number'] ?? ''); ?></p>
                <p>開立日期：<?php echo h($issuedOnDisplay); ?></p>
                <p>金額（大寫）：<?php echo h($amountUppercase); ?></p>
            </div>
        </div>
        <div class="info-cell">
            <div class="info-block">
                <h3>付款摘要</h3>
                <?php if ($consent): ?>
                    <p>電子同意：<?php echo h(receipt_method_label($consent['method'] ?? '')); ?> / <?php echo h(format_datetime($consent['consented_at'] ?? '')); ?></p>
                <?php endif; ?>
                <p>付款條件：<?php echo !empty($quote['payment_terms'] ?? '') ? h($quote['payment_terms']) : '未指定'; ?></p>
            </div>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 8%;">項次</th>
                <th style="width: 44%;">品項 / 描述</th>
                <th style="width: 12%;">數量</th>
                <th style="width: 18%;">單價 (NT$)</th>
                <th style="width: 18%;">金額 (NT$)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td style="text-align: center;"><?php echo $index + 1; ?></td>
                    <td><?php echo h($item['description'] ?? ''); ?></td>
                    <td style="text-align: center;">
                        <?php
                            $qtyValue = number_format((float)($item['qty'] ?? $item['quantity'] ?? 0), 2);
                            $unitLabel = receipt_unit_label($item['unit'] ?? '');
                            echo $qtyValue;
                            if ($unitLabel !== '') {
                                echo ' ' . h($unitLabel);
                            }
                        ?>
                    </td>
                    <td style="text-align: right;"><?php echo number_format(((int)($item['unit_price_cents'] ?? 0)) / 100, 2); ?></td>
                    <td style="text-align: right;"><?php echo number_format(((int)($item['line_total_cents'] ?? 0)) / 100, 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td style="text-align:right;">小計：</td>
            <td style="text-align:right;"><?php echo number_format($subtotalCents / 100, 2); ?></td>
        </tr>
        <tr>
            <td style="text-align:right;">稅額：</td>
            <td style="text-align:right;"><?php echo number_format($taxCents / 100, 2); ?></td>
        </tr>
        <tr>
            <td style="text-align:right;">總計（NT$）：</td>
            <td style="text-align:right;"><?php echo number_format($totalCents / 100, 2); ?></td>
        </tr>
    </table>

    <div class="stamp-area">
        <?php if ($stampImage): ?>
            <img src="<?php echo $stampImage; ?>" alt="公司圖章">
        <?php else: ?>
            <div class="stamp-placeholder">公司印章缺漏</div>
        <?php endif; ?>
    </div>

    <div class="qr-block">
        <div>
            <img src="<?php echo $qrImage; ?>" alt="QR 驗證">
        </div>
        <div class="qr-text">
            <strong>QR 驗證</strong><br>
            掃描 QR 或前往：<br>
            <?php echo h($verifyUrl); ?><br>
            以查驗序號、金額與狀態
        </div>
    </div>

    <div class="hash-label">
        hash_short：<?php echo h($hashShort); ?>
    </div>

</div>
