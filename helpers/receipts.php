<?php
/**
 * Receipts Helper
 * 個人收據產出與查驗共用函式
 */

if (!defined('QUOTABASE_SYSTEM')) {
    define('QUOTABASE_SYSTEM', true);
}

require_once __DIR__ . '/../vendor/autoload.php';

use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\QRCode;

/**
 * 確保與收據相關的資料表存在（舊站升級時自動補齊）
 */
function ensure_receipt_tables_exist(): void {
    static $ready = false;
    if ($ready) {
        return;
    }

    try {
        $pdo = getDB()->getConnection();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS receipt_consents (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
                quote_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                method ENUM('checkbox', 'email', 'other') NOT NULL DEFAULT 'checkbox',
                counterparty_ip VARCHAR(45) NULL,
                recorded_ip VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                evidence_ref VARCHAR(255) NULL,
                notes TEXT NULL,
                consented_at DATETIME NOT NULL,
                recorded_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_receipt_consents_quote (quote_id),
                INDEX idx_receipt_consents_customer (customer_id),
                INDEX idx_receipt_consents_method (method),
                CONSTRAINT fk_receipt_consents_quote FOREIGN KEY (quote_id) REFERENCES quotes(id),
                CONSTRAINT fk_receipt_consents_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
                CONSTRAINT fk_receipt_consents_user FOREIGN KEY (recorded_by) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS receipts (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
                quote_id BIGINT UNSIGNED NOT NULL,
                consent_id BIGINT UNSIGNED NULL,
                serial VARCHAR(100) NOT NULL,
                pdf_path VARCHAR(255) NOT NULL,
                amount_cents BIGINT UNSIGNED NOT NULL,
                currency VARCHAR(3) NOT NULL DEFAULT 'TWD',
                issued_on DATE NOT NULL,
                hash_full CHAR(64) NOT NULL,
                hash_short CHAR(20) NOT NULL,
                qr_payload TEXT NOT NULL,
                qr_token VARCHAR(128) NOT NULL,
                qr_secret_version VARCHAR(32) NOT NULL,
                status ENUM('issued', 'revoked') NOT NULL DEFAULT 'issued',
                expires_at DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_receipts_quote (quote_id),
                UNIQUE KEY uq_receipts_serial (org_id, serial),
                INDEX idx_receipts_org (org_id),
                INDEX idx_receipts_status (status),
                CONSTRAINT fk_receipts_quote FOREIGN KEY (quote_id) REFERENCES quotes(id),
                CONSTRAINT fk_receipts_consent FOREIGN KEY (consent_id) REFERENCES receipt_consents(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS receipt_verifications (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                receipt_id BIGINT UNSIGNED NOT NULL,
                serial VARCHAR(100) NOT NULL,
                status ENUM('passed', 'failed', 'expired') NOT NULL,
                failure_reason VARCHAR(50) NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_receipt_verifications_receipt (receipt_id),
                INDEX idx_receipt_verifications_serial (serial),
                CONSTRAINT fk_receipt_verifications_receipt FOREIGN KEY (receipt_id) REFERENCES receipts(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $ready = true;
    } catch (Throwable $e) {
        error_log('[receipts] ensure schema failed: ' . $e->getMessage());
    }
}

/**
 * 取得個人收據儲存根目錄
 */
function receipt_storage_root(): string {
    $path = getenv('RECEIPT_STORAGE_PATH') ?: (defined('RECEIPT_STORAGE_PATH') ? RECEIPT_STORAGE_PATH : (__DIR__ . '/../storage/receipts'));
    return rtrim($path, '/');
}

/**
 * 取得保留年限
 */
function receipt_retention_years(): int {
    $years = getenv('RECEIPT_RETENTION_YEARS') ?: (defined('RECEIPT_RETENTION_YEARS') ? RECEIPT_RETENTION_YEARS : 5);
    return max(1, (int)$years);
}

/**
 * 取得預設時區
 */
function receipt_timezone(): DateTimeZone {
    $tz = getenv('DEFAULT_TIMEZONE') ?: (defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'Asia/Taipei');
    return new DateTimeZone($tz);
}

/**
 * 確保路徑存在並套用權限
 */
function ensure_receipt_path(string $path, int $mode = 0750): void {
    if (!is_dir($path)) {
        @mkdir($path, $mode, true);
    }
}

/**
 * 取得/建立收據列印檔案的實際與相對路徑
 */
function build_receipt_paths(string $serial, DateTimeImmutable $issuedOn): array {
    $root = receipt_storage_root();
    $subDir = $issuedOn->format('Y/m');
    $absoluteDir = $root . '/' . $subDir;
    ensure_receipt_path($absoluteDir, 0750);

    $fileName = preg_replace('/[^A-Za-z0-9\\-_]/', '_', $serial) . '.html';
    $absolutePath = $absoluteDir . '/' . $fileName;

    $relative = ltrim(str_replace(rtrim(realpath(__DIR__ . '/..'), '/'), '', realpath($absoluteDir) ?: $absoluteDir), '/');
    if ($relative === '') {
        $relative = 'storage/receipts/' . $subDir;
    }
    $relativePath = $relative . '/' . $fileName;

    return [$absolutePath, $relativePath];
}

/**
 * 取得 server secret map
 *
 * @return array<string,string>
 */
function get_receipt_secret_map(): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $secrets = [];
    $json = getenv('RECEIPT_SERVER_SECRETS_JSON');
    if ($json) {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            foreach ($decoded as $version => $secret) {
                if (is_string($version) && is_string($secret) && $secret !== '') {
                    $secrets[$version] = $secret;
                }
            }
        }
    }

    $singleSecret = getenv('RECEIPT_SERVER_SECRET');
    if ($singleSecret) {
        $version = getenv('RECEIPT_SECRET_VERSION') ?: 'v1';
        $secrets[$version] = $singleSecret;
    }

    if (defined('RECEIPT_SERVER_SECRET') && RECEIPT_SERVER_SECRET) {
        $version = defined('RECEIPT_SECRET_VERSION') ? RECEIPT_SECRET_VERSION : 'v1';
        $secrets[$version] = RECEIPT_SERVER_SECRET;
    }

    return $cache = $secrets;
}

/**
 * 取得目前 active secret 版本
 */
function get_receipt_secret_version(): string {
    if (getenv('RECEIPT_SECRET_VERSION')) {
        return getenv('RECEIPT_SECRET_VERSION');
    }
    if (defined('RECEIPT_SECRET_VERSION')) {
        return RECEIPT_SECRET_VERSION;
    }
    return 'v1';
}

/**
 * 取得指定版本 secret，若不存在則回傳 map 中第一筆
 */
function resolve_receipt_secret(string $version, array $map): array {
    if (isset($map[$version])) {
        return [$version, $map[$version]];
    }
    $fallbackVersion = array_key_first($map);
    if ($fallbackVersion !== null) {
        return [$fallbackVersion, $map[$fallbackVersion]];
    }
    throw new RuntimeException('尚未設定 RECEIPT_SERVER_SECRET，請先更新 config。');
}

/**
 * 遮罩身分證或統編
 */
function mask_identity(?string $value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $length = mb_strlen($value, 'UTF-8');
    if ($length <= 4) {
        return str_repeat('＊', $length);
    }
    $visible = mb_substr($value, -4, null, 'UTF-8');
    return str_repeat('＊', $length - 4) . $visible;
}

/**
 * 取得最新同意紀錄
 */
function get_latest_receipt_consent(int $quoteId) {
    ensure_receipt_tables_exist();
    $orgId = get_current_org_id();
    return dbQueryOne(
        "SELECT * FROM receipt_consents WHERE quote_id = ? AND org_id = ? ORDER BY consented_at DESC, id DESC LIMIT 1",
        [$quoteId, $orgId]
    );
}

/**
 * 取得所有同意紀錄
 */
function get_receipt_consents(int $quoteId): array {
    ensure_receipt_tables_exist();
    $orgId = get_current_org_id();
    return dbQuery(
        "SELECT rc.*, u.username AS recorded_by_username
         FROM receipt_consents rc
         LEFT JOIN users u ON rc.recorded_by = u.id
         WHERE rc.quote_id = ? AND rc.org_id = ?
         ORDER BY rc.consented_at DESC, rc.id DESC",
        [$quoteId, $orgId]
    );
}

/**
 * 建立新的同意紀錄
 */
function create_receipt_consent(int $quoteId, array $payload): array {
    ensure_receipt_tables_exist();
    $quote = get_quote($quoteId);
    if (!$quote) {
        return ['success' => false, 'error' => '報價單不存在'];
    }

    $orgId = get_current_org_id();
    $userId = get_current_user_id();
    $method = $payload['method'] ?? 'checkbox';
    $consentedAt = $payload['consented_at'] ?? null;

    if (!in_array($method, ['checkbox', 'email', 'other'], true)) {
        return ['success' => false, 'error' => '無效的同意方式'];
    }

    if (!$consentedAt) {
        return ['success' => false, 'error' => '請填寫同意時間'];
    }

    try {
        $consentTime = new DateTimeImmutable($consentedAt, receipt_timezone());
    } catch (Throwable $e) {
        return ['success' => false, 'error' => '同意時間格式錯誤'];
    }

    $sql = "
        INSERT INTO receipt_consents (
            org_id, quote_id, customer_id, method,
            counterparty_ip, recorded_ip, user_agent,
            evidence_ref, notes, consented_at, recorded_by, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ";

    $counterpartyIp = trim($payload['counterparty_ip'] ?? '');
    $evidence = trim($payload['evidence_ref'] ?? '');
    $notes = trim($payload['notes'] ?? '');

    $recordedIp = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    dbExecute($sql, [
        $orgId,
        $quoteId,
        $quote['customer_id'],
        $method,
        $counterpartyIp ?: null,
        $recordedIp,
        $userAgent,
        $evidence ?: null,
        $notes ?: null,
        $consentTime->format('Y-m-d H:i:s'),
        $userId,
    ]);

    return ['success' => true];
}

/**
 * 取得報價單最新收據資料
 */
function get_receipt_by_quote(int $quoteId) {
    ensure_receipt_tables_exist();
    $orgId = get_current_org_id();
    return dbQueryOne("SELECT * FROM receipts WHERE quote_id = ? AND org_id = ?", [$quoteId, $orgId]);
}

function get_receipt_by_serial(string $serial) {
    ensure_receipt_tables_exist();
    $orgId = get_current_org_id();
    return dbQueryOne("SELECT * FROM receipts WHERE serial = ? AND org_id = ?", [$serial, $orgId]);
}

/**
 * 生成個人收據列印檔 (HTML)
 */
function generate_personal_receipt(int $quoteId, ?int $consentId = null): array {
    ensure_receipt_tables_exist();
    $quote = get_quote($quoteId);
    if (!$quote) {
        return ['success' => false, 'error' => '報價單不存在'];
    }

    $orgId = get_current_org_id();
    $consent = null;
    if ($consentId) {
        $consent = dbQueryOne(
            "SELECT * FROM receipt_consents WHERE id = ? AND quote_id = ? AND org_id = ?",
            [$consentId, $quoteId, $orgId]
        );
    } else {
        $consent = get_latest_receipt_consent($quoteId);
    }

    if (!$consent) {
        return ['success' => false, 'error' => '尚未記錄電子同意，無法產生收據。'];
    }

    if (empty($quote['items'])) {
        $quote['items'] = get_quote_items($quoteId);
    }

    $issuedOn = new DateTimeImmutable('now', receipt_timezone());
    $displayDate = $issuedOn->format('Y/m/d');
    $dbDate = $issuedOn->format('Y-m-d');
    $expiresAt = $issuedOn->modify('+' . receipt_retention_years() . ' years')->format('Y-m-d');

    $totalCents = (int)($quote['total_cents'] ?? 0);
    $subtotalCents = (int)($quote['subtotal_cents'] ?? 0);
    $taxCents = (int)($quote['tax_cents'] ?? 0);
    $amountTwd = number_format($totalCents / 100, 2, '.', '');

    $secretMap = get_receipt_secret_map();
    if (empty($secretMap)) {
        return [
            'success' => false,
            'error' => '尚未設定 RECEIPT_SERVER_SECRET，請於 config.php 或環境變數設定後再試。'
        ];
    }

    try {
        [$secretVersion, $secretValue] = resolve_receipt_secret(get_receipt_secret_version(), $secretMap);
    } catch (RuntimeException $e) {
        return [
            'success' => false,
            'error' => 'Secret 版本不存在，請檢查 RECEIPT_SECRET_VERSION 設定。'
        ];
    }

    $token = hash_hmac('sha256', $quote['quote_number'] . $amountTwd . $displayDate, $secretValue);
    $qrPayload = implode('|', [
        $quote['quote_number'],
        $amountTwd,
        $displayDate,
        $secretVersion,
        $token,
    ]);

    $itemsPayload = array_map(function ($item) {
        return [
            'description' => $item['description'] ?? '',
            'qty' => (float)($item['qty'] ?? $item['quantity'] ?? 0),
            'unit' => $item['unit'] ?? '',
            'unit_price_cents' => (int)($item['unit_price_cents'] ?? 0),
            'line_total_cents' => (int)($item['line_total_cents'] ?? 0),
        ];
    }, $quote['items']);

    $hashPayload = [
        'serial' => $quote['quote_number'],
        'quote_id' => $quoteId,
        'total_cents' => $totalCents,
        'issued_on' => $dbDate,
        'customer' => [
            'name' => $quote['customer_name'] ?? '',
            'tax_id_masked' => mask_identity($quote['tax_id'] ?? ''),
        ],
        'items' => $itemsPayload,
        'consent_id' => $consent['id'],
    ];

    $hashFull = hash('sha256', json_encode($hashPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $hashShort = build_receipt_hash_short($hashFull);

    $company = get_company_info();
    $printTerms = get_print_terms();

    $qrImage = build_receipt_qr_image($qrPayload);
    $stampImage = load_receipt_stamp_image();

    $verifyUrl = build_verify_url($quote['quote_number'], $token);

    $html = render_receipt_template([
        'company' => $company,
        'quote' => $quote,
        'items' => $quote['items'],
        'subtotal_cents' => $subtotalCents,
        'tax_cents' => $taxCents,
        'total_cents' => $totalCents,
        'issued_on_display' => $displayDate,
        'customer_tax_masked' => mask_identity($quote['tax_id'] ?? ''),
        'qr_image' => $qrImage,
        'verify_url' => $verifyUrl,
        'hash_short' => $hashShort,
        'hash_full' => $hashFull,
        'qr_payload' => $qrPayload,
        'stamp_image' => $stampImage,
        'print_terms' => $printTerms,
        'consent' => $consent,
        'amount_uppercase' => convert_amount_to_chinese($totalCents / 100),
    ]);

    $document = build_receipt_print_document($quote['quote_number'], $html);

    [$absolutePath, $relativePath] = build_receipt_paths($quote['quote_number'], $issuedOn);

    $written = @file_put_contents($absolutePath, $document, LOCK_EX);
    if ($written === false) {
        return ['success' => false, 'error' => '列印檔案寫入失敗，請檢查 storage/receipts/ 目錄權限。'];
    }

    @chmod($absolutePath, 0640);

    $existing = get_receipt_by_quote($quoteId);
    if ($existing && !empty($existing['pdf_path'])) {
        $oldPath = __DIR__ . '/../' . ltrim($existing['pdf_path'], '/');
        if (is_file($oldPath) && realpath($oldPath) !== realpath($absolutePath)) {
            @unlink($oldPath);
        }
    }

    if ($existing) {
        dbExecute(
            "UPDATE receipts
             SET consent_id = ?, pdf_path = ?, amount_cents = ?, currency = ?, issued_on = ?, hash_full = ?, hash_short = ?,
                 qr_payload = ?, qr_token = ?, qr_secret_version = ?, expires_at = ?, status = 'issued', updated_at = NOW()
             WHERE id = ?",
            [
                $consent['id'],
                $relativePath,
                $totalCents,
                $quote['currency'] ?? 'TWD',
                $dbDate,
                $hashFull,
                $hashShort,
                $qrPayload,
                $token,
                $secretVersion,
                $expiresAt,
                $existing['id'],
            ]
        );
    } else {
        dbExecute(
            "INSERT INTO receipts (
                org_id, quote_id, consent_id, serial, pdf_path, amount_cents, currency,
                issued_on, hash_full, hash_short, qr_payload, qr_token, qr_secret_version,
                status, expires_at, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'issued', ?, NOW(), NOW())",
            [
                $orgId,
                $quoteId,
                $consent['id'],
                $quote['quote_number'],
                $relativePath,
                $totalCents,
                $quote['currency'] ?? 'TWD',
                $dbDate,
                $hashFull,
                $hashShort,
                $qrPayload,
                $token,
                $secretVersion,
                $expiresAt,
            ]
        );
    }

    return [
        'success' => true,
        'path' => $absolutePath,
        'relative_path' => $relativePath,
        'hash_short' => $hashShort,
        'token' => $token,
        'serial' => $quote['quote_number'],
        'verify_url' => $verifyUrl,
    ];
}

/**
 * 渲染列印 HTML 內容
 */
function render_receipt_template(array $context): string {
    ob_start();
    $data = $context;
    include __DIR__ . '/../partials/receipts/personal-receipt-template.php';
    return (string)ob_get_clean();
}

/**
 * 包裝列印 HTML 文件
 */
function build_receipt_print_document(string $serial, string $content): string {
    $safeSerial = htmlspecialchars($serial, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $title = htmlspecialchars('個人收據 - ' . $serial, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $printControls = <<<HTML
<div class="print-actions">
    <div class="print-meta">序號：{$safeSerial}</div>
    <div class="print-buttons">
        <button type="button" onclick="window.print()">列印</button>
    </div>
</div>
HTML;

    $printStyles = <<<CSS
body {
    margin: 0;
    background: #f5f5f5;
}
.print-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    background: #fff;
    border-bottom: 1px solid #eee;
}
.print-actions button {
    padding: 6px 16px;
    border: none;
    border-radius: 4px;
    background: #1e88e5;
    color: #fff;
    font-size: 14px;
    cursor: pointer;
}
.print-meta {
    font-size: 14px;
    color: #333;
    font-weight: 600;
}
.print-content {
    max-width: 210mm;
    margin: 16px auto;
    background: #fff;
    padding: 12mm 10mm 14mm;
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
}
@media print {
    body {
        background: #fff;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .print-actions {
        display: none;
    }
    .print-content {
        box-shadow: none;
        margin: 0;
        padding: 0;
    }
}
CSS;

    return <<<HTML
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        {$printStyles}
    </style>
</head>
<body>
    {$printControls}
    <div class="print-content">
        {$content}
    </div>
</body>
</html>
HTML;
}

/**
 * 建立 QR 圖片（base64）
 */
function build_receipt_qr_image(string $payload): string {
    $options = new QROptions([
        'version' => QRCode::VERSION_AUTO,
        'scale' => 8,
        'outputType' => QRCode::OUTPUT_MARKUP_SVG,
        'eccLevel' => QRCode::ECC_H,
        'imageBase64' => true,
        'addQuietzone' => true,
    ]);

    $qr = new QRCode($options);
    return $qr->render($payload);
}

/**
 * 嘗試載入公司圖章
 */
function load_receipt_stamp_image(): ?string {
    $path = getenv('RECEIPT_STAMP_PATH') ?: (defined('RECEIPT_STAMP_PATH') ? RECEIPT_STAMP_PATH : null);
    if ($path && is_readable($path)) {
        $data = @file_get_contents($path);
        if ($data !== false) {
            $mime = mime_content_type($path) ?: 'image/png';
            return 'data:' . $mime . ';base64,' . base64_encode($data);
        }
    }
    return null;
}

/**
 * 建立 hash short
 */
function build_receipt_hash_short(string $hashFull): string {
    $raw = hex2bin($hashFull);
    if ($raw === false) {
        return '';
    }
    $firstTen = substr($raw, 0, 10);
    return strtoupper(base32_encode_binary($firstTen));
}

function base32_encode_binary(string $binary): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($binary) as $char) {
        $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $padLength = (5 - (strlen($bits) % 5)) % 5;
    if ($padLength > 0) {
        $bits = str_pad($bits, strlen($bits) + $padLength, '0', STR_PAD_RIGHT);
    }

    $output = '';
    foreach (str_split($bits, 5) as $chunk) {
        $output .= $alphabet[bindec($chunk)];
    }

    return $output;
}

/**
 * 中文大寫金額
 */
function convert_amount_to_chinese(float $amount): string {
    $units = ['元', '拾', '佰', '仟', '萬', '拾', '佰', '仟', '億', '拾', '佰', '仟', '兆'];
    $nums = ['零', '壹', '貳', '參', '肆', '伍', '陸', '柒', '捌', '玖'];

    $value = sprintf('%.2f', $amount);
    [$integer, $decimal] = explode('.', $value);

    $integer = ltrim($integer, '0');
    if ($integer === '') {
        $integer = '0';
    }

    $chars = '';
    $len = strlen($integer);
    $zero = false;
    for ($i = 0; $i < $len; $i++) {
        $num = (int)$integer[$len - $i - 1];
        $unit = $units[$i] ?? '';
        if ($num === 0) {
            if (!$zero) {
                $zero = true;
                $chars = $nums[0] . $chars;
            }
            if ($i % 4 === 0) {
                $chars = mb_substr($unit, 0, 1, 'UTF-8') . $chars;
            }
        } else {
            $zero = false;
            $chars = $nums[$num] . $unit . $chars;
        }
    }
    $chars = preg_replace('/零(拾|佰|仟)/u', '零', $chars);
    $chars = preg_replace('/零{2,}/u', '零', $chars);
    $chars = preg_replace('/零(萬|億|兆)/u', '$1', $chars);
    $chars = preg_replace('/零元/u', '元', $chars);
    if (mb_substr($chars, 0, 1, 'UTF-8') === '元') {
        $chars = '零元';
    }

    $dec = '';
    $jiao = (int)$decimal[0];
    $fen = (int)$decimal[1];
    if ($jiao > 0) {
        $dec .= $nums[$jiao] . '角';
    }
    if ($fen > 0) {
        $dec .= $nums[$fen] . '分';
    }

    return $chars . ($dec === '' ? '整' : $dec);
}

/**
 * 同意方式顯示名稱
 */
function receipt_method_label(?string $method): string {
    $map = [
        'checkbox' => '頁面勾選',
        'email' => 'Email 同意',
        'other' => '其他',
    ];

    $key = strtolower((string)$method);
    return $map[$key] ?? '其他';
}

/**
 * 單位顯示名稱
 */
function receipt_unit_label(?string $unit): string {
    if ($unit === null) {
        return '';
    }

    $map = [
        'piece' => '件',
        'pieces' => '件',
        'pc' => '件',
        'pcs' => '件',
        'unit' => '單位',
        'units' => '單位',
        'set' => '組',
        'sets' => '組',
        'time' => '次',
        'times' => '次',
        'hour' => '小時',
        'hours' => '小時',
        'day' => '天',
        'days' => '天',
        'month' => '個月',
        'months' => '個月',
        'year' => '年',
        'years' => '年',
        'service' => '項',
        'services' => '項',
    ];

    $normalized = strtolower(trim($unit));

    if ($normalized === '') {
        return '';
    }

    return $map[$normalized] ?? $unit;
}

/**
 * 建立查驗 URL（含 token）
 */
function build_verify_url(string $serial, string $token): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = $scheme . '://' . $host;
    return $base . '/verify?serial=' . urlencode($serial) . '&token=' . urlencode($token);
}

/**
 * 記錄查驗事件
 */
function log_receipt_verification(int $receiptId, string $serial, string $status, ?string $reason = null): void {
    ensure_receipt_tables_exist();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    dbExecute(
        "INSERT INTO receipt_verifications (receipt_id, serial, status, failure_reason, ip_address, user_agent, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())",
        [$receiptId, $serial, $status, $reason, $ip, $agent]
    );
}

/**
 * 驗證 QR token
 */
function verify_receipt_token(string $serial, string $token): array {
    ensure_receipt_tables_exist();
    $receipt = dbQueryOne("SELECT * FROM receipts WHERE serial = ?", [$serial]);
    if (!$receipt) {
        return ['success' => false, 'code' => 'NOT_FOUND'];
    }

    $secretMap = get_receipt_secret_map();
    if (empty($secretMap)) {
        return ['success' => false, 'code' => 'SECRET_MISSING'];
    }

    $version = $receipt['qr_secret_version'] ?? get_receipt_secret_version();
    if (!isset($secretMap[$version])) {
        return ['success' => false, 'code' => 'SECRET_VERSION_MISSING'];
    }

    $issued = DateTimeImmutable::createFromFormat('Y-m-d', $receipt['issued_on'], receipt_timezone());
    $displayDate = $issued ? $issued->format('Y/m/d') : '';
    $amountTwd = number_format(((int)$receipt['amount_cents']) / 100, 2, '.', '');
    $expected = hash_hmac('sha256', $receipt['serial'] . $amountTwd . $displayDate, $secretMap[$version]);

    $isExpired = false;
    if (!empty($receipt['expires_at'])) {
        $expires = DateTimeImmutable::createFromFormat('Y-m-d', $receipt['expires_at'], receipt_timezone());
        if ($expires && $expires < new DateTimeImmutable('today', receipt_timezone())) {
            $isExpired = true;
        }
    }

    if ($isExpired) {
        log_receipt_verification((int)$receipt['id'], $serial, 'expired', 'RECORD_EXPIRED');
        return ['success' => false, 'code' => 'RECORD_EXPIRED', 'receipt' => $receipt];
    }

    if (!hash_equals($expected, $token)) {
        log_receipt_verification((int)$receipt['id'], $serial, 'failed', 'TOKEN_INVALID');
        return ['success' => false, 'code' => 'TOKEN_INVALID', 'receipt' => $receipt];
    }

    log_receipt_verification((int)$receipt['id'], $serial, 'passed', null);
    return ['success' => true, 'receipt' => $receipt];
}
