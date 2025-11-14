<?php
define('QUOTABASE_SYSTEM', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../partials/ui.php';

$serial = trim($_GET['serial'] ?? '');
$token = trim($_GET['token'] ?? '');
$verification = null;
$receipt = null;
$quote = null;
$consent = null;
$errorCode = null;
$consent_storage_timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'Asia/Taipei';
$extraHead = ['<style>
    .info-panel {
        background: var(--bg-secondary);
        border-radius: var(--border-radius-md);
        padding: 16px;
    }
    .info-panel h3 {
        margin-top: 0;
        font-size: 15px;
        margin-bottom: 8px;
        color: var(--text-primary);
    }
</style>'];

if ($serial !== '' && $token !== '') {
    $verification = verify_receipt_token($serial, $token);
    if (!empty($verification['receipt'])) {
        $receipt = $verification['receipt'];
        $quote = get_quote((int)$receipt['quote_id']);
        if (!empty($receipt['consent_id'])) {
            $consent = dbQueryOne("SELECT * FROM receipt_consents WHERE id = ?", [$receipt['consent_id']]);
        }
    }
    if (!$verification['success']) {
        $errorCode = $verification['code'] ?? 'TOKEN_INVALID';
    }
}

html_start('個人收據查驗', $extraHead);

page_header('個人收據查驗', [
    ['label' => '首頁', 'url' => '/'],
    ['label' => '收據查驗', 'url' => '/verify/']
]);
?>

<div class="main-content">
    <div class="card">
        <h2 class="card-title">輸入收據序號與驗證碼</h2>
        <form method="GET" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
            <div>
                <label for="serial">收據序號</label>
                <input type="text" id="serial" name="serial" value="<?php echo h($serial); ?>" required placeholder="例如：Q-2025-000123">
            </div>
            <div>
                <label for="token">驗證碼 (HMAC)</label>
                <input type="text" id="token" name="token" value="<?php echo h($token); ?>" required placeholder="掃描QR取得">
            </div>
            <div style="align-self: flex-end;">
                <button type="submit" class="btn btn-primary" style="width: 100%;">開始查驗</button>
            </div>
        </form>
        <p class="form-help-text" style="margin-top: 8px;">請掃描收據 QR 或向開立單位取得驗證碼，以核對金額與 hash_short。</p>
    </div>

    <?php if ($serial && $token): ?>
        <div class="card" style="margin-top: 24px;">
            <h2 class="card-title">查驗結果</h2>
            <?php if ($verification && $verification['success'] && $receipt): ?>
                <div class="alert alert-success">
                    <span class="alert-message">驗證成功，收據序號 <?php echo h($receipt['serial']); ?> 仍在有效期內。</span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">
                    <div class="info-panel">
                        <h3>金額與日期</h3>
                        <p>金額：<?php echo format_currency_cents($receipt['amount_cents']); ?></p>
                        <p>開立日期：<?php echo format_date($receipt['issued_on']); ?></p>
                        <p>保存期限：<?php echo format_date($receipt['expires_at']); ?></p>
                    </div>
                    <div class="info-panel">
                        <h3>hash_short</h3>
                        <p style="font-family: monospace; font-size: 18px;"><?php echo h($receipt['hash_short']); ?></p>
                        <p class="muted">如紙本列印 hash_short 不符，請聯繫開立單位。</p>
                    </div>
                    <?php if ($quote): ?>
                        <div class="info-panel">
                            <h3>收據資訊</h3>
                            <p>客戶：<?php echo h($quote['customer_name'] ?? ''); ?></p>
                            <p>報價單號：<?php echo h($quote['quote_number']); ?></p>
                            <p>貨幣：<?php echo h($receipt['currency']); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($consent): ?>
                        <div class="info-panel">
                            <h3>電子同意紀錄</h3>
                            <p>方式：<?php echo h(receipt_method_label($consent['method'] ?? '')); ?></p>
                            <p>時間：<?php echo h(format_datetime($consent['consented_at'], 'Y-m-d H:i', $consent_storage_timezone)); ?></p>
                            <?php if (!empty($consent['evidence_ref'])): ?>
                                <p>證據：<?php echo h($consent['evidence_ref']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 16px; display:flex; gap:12px; flex-wrap: wrap;">
                    <a class="btn btn-secondary" href="/receipts/download.php?serial=<?php echo urlencode($receipt['serial']); ?>&token=<?php echo urlencode($token); ?>" target="_blank">
                        下載 PDF
                    </a>
                    <a class="btn" href="/verify/?serial=<?php echo urlencode($receipt['serial']); ?>&token=<?php echo urlencode($token); ?>">
                        重新整理
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <span class="alert-message">
                        查驗失敗：<?php echo h(match ($errorCode) {
                            'NOT_FOUND' => '找不到收據序號，請確認序號是否正確。',
                            'RECORD_EXPIRED' => '此收據已超過保存期限，預設視為無效。',
                            'SECRET_VERSION_MISSING' => '驗證秘鑰版本不存在，請聯繫開立單位。',
                            'SECRET_MISSING' => '伺服器尚未設定驗證秘鑰。',
                            default => '驗證碼不正確或已失效。'
                        }); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
page_footer();
html_end();
