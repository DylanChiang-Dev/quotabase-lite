<?php
$company = $data['company'] ?? [];
$quote = $data['quote'] ?? [];
$items = $data['items'] ?? [];
$subtotalCents = $data['subtotal_cents'] ?? 0;
$taxCents = $data['tax_cents'] ?? 0;
$totalCents = $data['total_cents'] ?? 0;
$issuedOnDisplay = $data['issued_on_display'] ?? '';
$customerTaxMasked = $data['customer_tax_masked'] ?? '';
$qrImage = $data['qr_image'] ?? '';
$hashShort = $data['hash_short'] ?? '';
$stampImage = $data['stamp_image'] ?? null;
$printTerms = $data['print_terms'] ?? '';
$consent = $data['consent'] ?? null;
$amountUppercase = $data['amount_uppercase'] ?? '';
$verifyUrl = $data['verify_url'] ?? '';
?>
<style>
    body {
        font-family: "Noto Sans TC", "PingFang TC", "Heiti TC", "Microsoft JhengHei", sans-serif;
        font-size: 12pt;
        color: #111;
        line-height: 1.6;
        margin: 0;
    }
    .receipt-container {
        width: 100%;
        max-width: 190mm;
        margin: 0 auto;
        padding: 0 5mm;
        box-sizing: border-box;
    }
    .receipt-header {
        text-align: center;
        margin-bottom: 12mm;
    }
    .receipt-header h1 {
        font-size: 22pt;
        margin-bottom: 6pt;
        letter-spacing: 1pt;
    }
    .info-grid {
        display: table;
        width: 100%;
        margin-bottom: 10mm;
    }
    .info-cell {
        display: table-cell;
        width: 50%;
        vertical-align: top;
        padding-right: 5mm;
    }
    .info-block h3 {
        font-size: 11pt;
        margin-bottom: 4pt;
        color: #555;
    }
    .info-block p {
        margin: 0;
        font-size: 11pt;
    }
    table.items {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8mm;
        font-size: 10.5pt;
        table-layout: fixed;
    }
    table.items th,
    table.items td {
        border: 1px solid #ccc;
        padding: 6pt 4pt;
        word-break: break-word;
        overflow-wrap: anywhere;
    }
    table.items th {
        background: #f5f5f5;
    }
    .totals {
        width: 60%;
        margin-left: auto;
        border-collapse: collapse;
        font-size: 11pt;
    }
    .totals td {
        padding: 4pt 6pt;
    }
    .totals tr:last-child td {
        font-weight: bold;
        border-top: 2px solid #444;
        font-size: 12pt;
    }
    .signature-block {
        width: 100%;
        margin-top: 12mm;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
    }
    .signature-area {
        width: 55%;
        border-top: 1px solid #444;
        padding-top: 6mm;
        font-size: 10pt;
    }
    .stamp-area {
        width: 40%;
        text-align: right;
    }
    .stamp-area img {
        width: 45mm;
        height: auto;
    }
    .qr-block {
        display: flex;
        justify-content: flex-end;
        gap: 8mm;
        align-items: center;
        margin-top: 8mm;
    }
    .qr-block img {
        width: 32mm;
        height: 32mm;
    }
    .qr-text {
        font-size: 9pt;
    }
    .hash-label {
        font-size: 9pt;
        color: #444;
        text-align: right;
        margin-top: 2mm;
    }
    .terms {
        margin-top: 8mm;
        font-size: 9.5pt;
        color: #666;
        line-height: 1.4;
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
                    <p>統編：<?php echo h($company['tax_id']); ?></p>
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
                <?php if (!empty($customerTaxMasked)): ?>
                    <p>證號：<?php echo h($customerTaxMasked); ?></p>
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

    <div class="signature-block">
        <div class="signature-area">
            <div>簽名：</div>
            <div style="margin-top: 8mm; font-size: 10pt; color: #666;">相對人簽名 / 日期</div>
        </div>
        <div class="stamp-area">
            <?php if ($stampImage): ?>
                <img src="<?php echo $stampImage; ?>" alt="公司圖章">
            <?php else: ?>
                <div style="font-size: 10pt; color: #b71c1c;">公司印章缺漏</div>
            <?php endif; ?>
        </div>
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

    <?php if (!empty($printTerms)): ?>
        <div class="terms">
            <strong>備註／條款</strong><br>
            <?php echo nl2br(h($printTerms)); ?>
        </div>
    <?php endif; ?>
</div>
