<?php
/**
 * 報價單電子簽署頁
 * Public Quote Consent Page
 */

define('QUOTABASE_SYSTEM', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/quote_consent.php';
require_once __DIR__ . '/../helpers/receipts.php';
require_once __DIR__ . '/../helpers/markdown.php';
require_once __DIR__ . '/../db.php';

ensure_receipt_tables_exist();

$tokenValue = trim($_REQUEST['token'] ?? '');
$tokenRow = null;
$quote = null;
$quoteItems = [];
$orgSettings = [];
$printTerms = '';
$printTermsHtml = '';
$error = '';
$success = '';
$tokenExpired = false;
$canSign = false;

if ($tokenValue === '') {
    $error = '電子簽署連結無效，請掃描最新的 QR Code。';
} else {
    $tokenRow = find_quote_consent_token_by_value($tokenValue);
    if (!$tokenRow) {
        $error = '此電子簽署連結不存在或已被撤銷。';
    } else {
        $quote = fetch_quote_for_consent((int)$tokenRow['quote_id'], (int)$tokenRow['org_id']);
        if (!$quote) {
            $error = '找不到對應的報價單，請聯絡業務窗口。';
        } else {
            $quoteItems = get_quote_items((int)$quote['id']);
            $orgSettings = fetch_org_settings((int)$quote['org_id']);
            $printTerms = $orgSettings['print_terms'] ?? '';
            $printTermsHtml = $printTerms !== '' ? render_markdown_to_html($printTerms) : '';
        }
    }
}

if (!$error && $tokenRow) {
    $expiresAt = null;
    if (!empty($tokenRow['expires_at'])) {
        try {
            $expiresAt = new DateTimeImmutable($tokenRow['expires_at'], new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            $expiresAt = null;
        }
    }

    if ($tokenRow['status'] === 'active' && $expiresAt !== null && $expiresAt < quote_consent_now()) {
        expire_quote_consent_token((int)$tokenRow['id']);
        $tokenRow['status'] = 'expired';
        $tokenExpired = true;
    } else {
        $tokenExpired = $tokenRow['status'] === 'expired';
    }

    $canSign = !$tokenExpired && $tokenRow['status'] === 'active' && $quote && !in_array($quote['status'], ['accepted', 'rejected'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && $tokenRow && $quote) {
    $action = $_POST['action'] ?? '';
    if ($action === 'accept') {
        if ($canSign) {
            [$success, $error] = handle_qr_accept($quote, $tokenRow);
            $canSign = false;
        } else {
            $error = '此連結目前不可簽署，請確認狀態後再試。';
        }
    } elseif ($action === 'reject') {
        if ($tokenRow['status'] === 'active') {
            [$success, $error] = handle_qr_reject($quote, $tokenRow);
            $canSign = false;
        } else {
            $error = '此連結已無法拒絕。';
        }
    } else {
        $error = '未知的操作，請重新整理後再試。';
    }
}

$companyName = $orgSettings['company_name'] ?? 'Quotabase-Lite';
$companyContact = $orgSettings['company_contact'] ?? '';
$companyAddress = $orgSettings['company_address'] ?? '';
$quoteStatusLabel = $quote ? get_status_label($quote['status']) : '';
$tokenStatusLabel = translate_token_status($tokenRow['status'] ?? '');

?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>電子簽署 - <?php echo h($quote['quote_number'] ?? '報價單'); ?></title>
    <style>
        :root {
            color-scheme: light dark;
        }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans TC", "Microsoft JhengHei", sans-serif;
            background: #f5f7fb;
            color: #1f2430;
        }
        .page {
            max-width: 760px;
            margin: 0 auto;
            padding: 32px 16px 48px;
        }
        header {
            text-align: center;
            margin-bottom: 24px;
        }
        header h1 {
            font-size: 24px;
            margin: 0;
            color: #111;
        }
        header p {
            margin: 6px 0 0;
            color: #555;
            font-size: 14px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 10px 40px rgba(15, 23, 42, 0.08);
            margin-bottom: 20px;
        }
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }
        .alert-success {
            background: #ecfdf3;
            color: #065f46;
            border: 1px solid #bbf7d0;
        }
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
        }
        .summary-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f2f8;
        }
        .summary-label {
            display: block;
            font-size: 13px;
            color: #6b7280;
            letter-spacing: 0.4px;
        }
        .summary-value {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin-top: 4px;
        }
        .status-badges {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .badge {
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .badge-quote {
            background: #e0f2fe;
            color: #0369a1;
        }
        .badge-token {
            background: #ede9fe;
            color: #5b21b6;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 12px;
        }
        .actions button {
            flex: 1 1 200px;
            padding: 14px 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s ease;
        }
        .actions button:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }
        .btn-primary {
            background: #2563eb;
            color: #fff;
        }
        .btn-danger {
            background: #f43f5e;
            color: #fff;
        }
        .actions button:not(:disabled):hover {
            transform: translateY(-1px);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #f0f2f8;
            font-size: 14px;
            text-align: left;
        }
        th {
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            color: #6b7280;
            background: #f9fafb;
        }
        td.number {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .terms {
            font-size: 13px;
            color: #4b5563;
            white-space: normal;
            line-height: 1.7;
        }
        footer {
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
            margin-top: 32px;
        }
    </style>
</head>
<body>
    <div class="page">
        <header>
            <h1><?php echo h($companyName); ?></h1>
            <?php if ($companyContact): ?>
                <p><?php echo h($companyContact); ?></p>
            <?php endif; ?>
            <?php if ($companyAddress): ?>
                <p><?php echo h($companyAddress); ?></p>
            <?php endif; ?>
        </header>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>

        <?php if ($quote): ?>
            <div class="card">
                <div class="status-badges">
                    <span class="badge badge-quote">報價狀態：<?php echo h($quoteStatusLabel); ?></span>
                    <span class="badge badge-token">連結狀態：<?php echo h($tokenStatusLabel); ?></span>
                </div>

                <div class="summary-grid">
                    <div class="summary-item">
                        <span class="summary-label">報價單號</span>
                        <span class="summary-value"><?php echo h($quote['quote_number']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">開立日期</span>
                        <span class="summary-value"><?php echo h(format_date($quote['issue_date'])); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">客戶名稱</span>
                        <span class="summary-value"><?php echo h($quote['customer_name']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">總金額</span>
                        <span class="summary-value"><?php echo format_currency_cents_compact($quote['total_cents']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">連結有效至</span>
                        <span class="summary-value">
                            <?php echo h($tokenRow['expires_at'] ? format_datetime($tokenRow['expires_at']) : '—'); ?>
                        </span>
                    </div>
                </div>

                <?php if ($canSign): ?>
                    <form method="POST" class="actions">
                        <input type="hidden" name="token" value="<?php echo h($tokenValue); ?>">
                        <button type="submit" name="action" value="accept" class="btn-primary">同意並完成簽署</button>
                        <button type="submit" name="action" value="reject" class="btn-danger">拒絕此報價</button>
                    </form>
                <?php else: ?>
                    <p style="margin-top:16px; font-size:13px; color:#6b7280;">
                        若需重新簽署或調整內容，請聯絡您的業務窗口。
                    </p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2 style="margin:0 0 12px; font-size:18px; color:#111;">報價內容</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 45%;">項目</th>
                                <th style="width: 15%;">數量</th>
                                <th style="width: 20%;">單價</th>
                                <th style="width: 20%;">含稅金額</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quoteItems as $item): ?>
                                <tr>
                                    <td><?php echo h($item['description'] ?? $item['item_name']); ?></td>
                                    <td class="number">
                                        <?php echo h(format_quantity($item['qty'] ?? $item['quantity'] ?? 0)); ?>
                                        <?php echo h($item['unit'] ?? ''); ?>
                                    </td>
                                    <td class="number"><?php echo format_currency_cents_compact($item['unit_price_cents']); ?></td>
                                    <td class="number"><?php echo format_currency_cents_compact($item['line_total_cents']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

<?php if ($printTermsHtml): ?>
    <div class="card">
        <h3 style="margin:0 0 12px; font-size:16px; color:#111;">條款與條件</h3>
        <div class="terms"><?php echo $printTermsHtml; ?></div>
    </div>
<?php endif; ?>
        <?php endif; ?>

        <footer>
            &copy; <?php echo date('Y'); ?> <?php echo h($companyName); ?> · 電子簽署服務
        </footer>
    </div>
</body>
</html>
<?php

function fetch_quote_for_consent(int $quoteId, int $orgId): ?array {
    $sql = "
        SELECT q.*, c.name AS customer_name, c.email AS customer_email,
               c.phone AS customer_phone, c.tax_id AS customer_tax_id
        FROM quotes q
        LEFT JOIN customers c ON q.customer_id = c.id
        WHERE q.id = ? AND q.org_id = ?
        LIMIT 1
    ";
    $quote = dbQueryOne($sql, [$quoteId, $orgId]);
    return $quote ?: null;
}

function fetch_org_settings(int $orgId): array {
    $row = dbQueryOne("
        SELECT company_name, company_address, company_contact, print_terms
        FROM settings
        WHERE org_id = ?
        LIMIT 1
    ", [$orgId]);
    return $row ?: [];
}

function handle_qr_accept(array &$quote, array &$tokenRow): array {
    $pdo = getDB()->getConnection();
    $error = '';
    $success = '';

    try {
        $pdo->beginTransaction();

        $counterpartyIp = $_SERVER['REMOTE_ADDR'] ?? null;
        $serverIp = $_SERVER['SERVER_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $consentTime = (new DateTimeImmutable('now', receipt_timezone()))->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            INSERT INTO receipt_consents (
                org_id, quote_id, customer_id, method,
                counterparty_ip, recorded_ip, user_agent,
                evidence_ref, notes, consented_at, recorded_by,
                created_at, updated_at
            ) VALUES (?, ?, ?, 'qr', ?, ?, ?, ?, ?, ?, NULL, NOW(), NOW())
        ");
        $stmt->execute([
            $quote['org_id'],
            $quote['id'],
            $quote['customer_id'],
            $counterpartyIp,
            $serverIp,
            $userAgent,
            'token#' . $tokenRow['id'],
            'QR 自助簽署',
            $consentTime,
        ]);

        $consentId = (int)$pdo->lastInsertId();

        if ($quote['status'] !== 'accepted') {
            $update = $pdo->prepare("UPDATE quotes SET status = 'accepted', updated_at = NOW() WHERE id = ? AND org_id = ?");
            $update->execute([$quote['id'], $quote['org_id']]);
            $quote['status'] = 'accepted';
        }

        consume_quote_consent_token((int)$tokenRow['id'], $consentId);

        $pdo->commit();

        $tokenRow['status'] = 'consumed';
        $tokenRow['consent_id'] = $consentId;
        $tokenRow['consumed_at'] = quote_consent_now()->format('Y-m-d H:i:s');
        $success = '感謝，已完成電子簽署並回報給業務人員。';
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[quote-consent] accept failed: ' . $e->getMessage());
        $error = '提交失敗，請稍後再試或聯絡業務窗口。';
    }

    return [$success, $error];
}

function handle_qr_reject(array &$quote, array &$tokenRow): array {
    $pdo = getDB()->getConnection();
    $error = '';
    $success = '';

    try {
        $pdo->beginTransaction();

        if ($quote['status'] !== 'rejected') {
            $update = $pdo->prepare("UPDATE quotes SET status = 'rejected', updated_at = NOW() WHERE id = ? AND org_id = ?");
            $update->execute([$quote['id'], $quote['org_id']]);
            $quote['status'] = 'rejected';
        }

        revoke_quote_consent_tokens((int)$quote['id']);

        $pdo->commit();

        $tokenRow['status'] = 'revoked';
        $success = '您已拒絕此報價，業務窗口將儘速與您聯繫。';
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[quote-consent] reject failed: ' . $e->getMessage());
        $error = '操作失敗，請稍後再試。';
    }

    return [$success, $error];
}

function translate_token_status(string $status): string {
    return [
        'active' => '可使用',
        'consumed' => '已簽署',
        'revoked' => '已撤銷',
        'expired' => '已過期',
    ][$status] ?? '未知';
}
