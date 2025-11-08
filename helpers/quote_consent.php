<?php
/**
 * Quote Consent Helper
 * 報價電子簽署 Token 產製與驗證
 */

if (!defined('QUOTABASE_SYSTEM')) {
    define('QUOTABASE_SYSTEM', true);
}

require_once __DIR__ . '/../vendor/autoload.php';

use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\QRCode;

/**
 * 確保 quote_consent_tokens 表存在
 */
function ensure_quote_consent_schema(): void {
    static $ready = false;
    if ($ready) {
        return;
    }

    try {
        $pdo = getDB()->getConnection();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS quote_consent_tokens (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
                quote_id BIGINT UNSIGNED NOT NULL,
                token VARCHAR(128) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                status ENUM('active', 'consumed', 'revoked', 'expired') NOT NULL DEFAULT 'active',
                expires_at DATETIME NOT NULL,
                consumed_at DATETIME NULL,
                consent_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_quote_consent_token_hash (token_hash),
                INDEX idx_quote_consent_quote (quote_id),
                INDEX idx_quote_consent_status (status),
                INDEX idx_quote_consent_expires (expires_at),
                CONSTRAINT fk_quote_consent_quote FOREIGN KEY (quote_id) REFERENCES quotes(id),
                CONSTRAINT fk_quote_consent_consent FOREIGN KEY (consent_id) REFERENCES receipt_consents(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $ready = true;
    } catch (Throwable $e) {
        error_log('[quote-consent] ensure schema failed: ' . $e->getMessage());
    }
}

function quote_consent_token_ttl_days(): int {
    $value = getenv('QUOTE_CONSENT_TOKEN_TTL_DAYS');
    if ($value === false || $value === null || $value === '') {
        $value = defined('QUOTE_CONSENT_TOKEN_TTL_DAYS') ? QUOTE_CONSENT_TOKEN_TTL_DAYS : 30;
    }
    return max(1, (int)$value);
}

function quote_consent_now(): DateTimeImmutable {
    return new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

function get_active_quote_consent_token(int $quoteId, ?int $orgId = null): ?array {
    ensure_quote_consent_schema();
    $orgId = $orgId ?? get_current_org_id();

    return dbQueryOne(
        "SELECT * FROM quote_consent_tokens
         WHERE quote_id = ? AND org_id = ? AND status = 'active' AND expires_at > NOW()
         ORDER BY created_at DESC
         LIMIT 1",
        [$quoteId, $orgId]
    );
}

function create_quote_consent_token(int $quoteId, ?int $orgId = null, ?DateTimeImmutable $expiresAt = null): ?array {
    ensure_quote_consent_schema();
    $orgId = $orgId ?? get_current_org_id();
    $tokenValue = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $tokenValue);
    $expiresAt = $expiresAt ?: quote_consent_now()->modify('+' . quote_consent_token_ttl_days() . ' days');

    $pdo = getDB()->getConnection();
    $stmt = $pdo->prepare("
        INSERT INTO quote_consent_tokens (org_id, quote_id, token, token_hash, status, expires_at)
        VALUES (?, ?, ?, ?, 'active', ?)
    ");
    $stmt->execute([$orgId, $quoteId, $tokenValue, $tokenHash, $expiresAt->format('Y-m-d H:i:s')]);

    $id = (int)$pdo->lastInsertId();
    return dbQueryOne("SELECT * FROM quote_consent_tokens WHERE id = ?", [$id]);
}

function get_or_create_quote_consent_token(int $quoteId, ?int $orgId = null): ?array {
    $existing = get_active_quote_consent_token($quoteId, $orgId);
    if ($existing) {
        return $existing;
    }
    return create_quote_consent_token($quoteId, $orgId);
}

function find_quote_consent_token_by_value(string $tokenValue): ?array {
    ensure_quote_consent_schema();
    $tokenValue = trim($tokenValue);
    if ($tokenValue === '') {
        return null;
    }
    $hash = hash('sha256', $tokenValue);
    $row = dbQueryOne("SELECT * FROM quote_consent_tokens WHERE token_hash = ? LIMIT 1", [$hash]);
    if (!$row) {
        return null;
    }
    return hash_equals($row['token'], $tokenValue) ? $row : null;
}

function get_latest_quote_consent_token(int $quoteId, ?int $orgId = null): ?array {
    ensure_quote_consent_schema();
    $orgId = $orgId ?? get_current_org_id();

    return dbQueryOne(
        "SELECT *
         FROM quote_consent_tokens
         WHERE quote_id = ? AND org_id = ?
         ORDER BY created_at DESC
         LIMIT 1",
        [$quoteId, $orgId]
    );
}

function quote_consent_token_url(array $tokenRow): string {
    $tokenValue = $tokenRow['token'] ?? '';
    return app_url('/quotes/consent.php', ['token' => $tokenValue]);
}

function quote_consent_display_token(string $url): string {
    $parts = parse_url($url);
    if (!$parts || empty($parts['query'])) {
        return $url;
    }

    parse_str($parts['query'], $query);
    $token = $query['token'] ?? '';
    if (!$token) {
        return $url;
    }

    return substr($token, 0, 12) . '…' . substr($token, -8);
}

function build_quote_consent_qr_image(string $payload): string {
    $options = new QROptions([
        'version' => QRCode::VERSION_AUTO,
        'scale' => 6,
        'outputType' => QRCode::OUTPUT_MARKUP_SVG,
        'eccLevel' => QRCode::ECC_H,
        'imageBase64' => true,
        'addQuietzone' => true,
    ]);

    $qr = new QRCode($options);
    return $qr->render($payload);
}

function expire_quote_consent_token(int $tokenId): void {
    ensure_quote_consent_schema();
    dbExecute("UPDATE quote_consent_tokens SET status = 'expired' WHERE id = ? AND status = 'active'", [$tokenId]);
}

function consume_quote_consent_token(int $tokenId, int $consentId): void {
    ensure_quote_consent_schema();
    dbExecute(
        "UPDATE quote_consent_tokens
         SET status = 'consumed', consent_id = ?, consumed_at = NOW()
         WHERE id = ?",
        [$consentId, $tokenId]
    );
}

function revoke_quote_consent_tokens(int $quoteId): void {
    ensure_quote_consent_schema();
    dbExecute(
        "UPDATE quote_consent_tokens SET status = 'revoked'
         WHERE quote_id = ? AND status = 'active'",
        [$quoteId]
    );
}

function quote_consent_status_label(?string $status): string {
    $map = [
        'active' => '可使用',
        'consumed' => '已簽署',
        'revoked' => '已撤銷',
        'expired' => '已過期',
    ];
    $key = strtolower((string)$status);
    return $map[$key] ?? '未知';
}
