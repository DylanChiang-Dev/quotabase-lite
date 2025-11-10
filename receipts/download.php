<?php
/**
 * 個人收據下載
 */

define('QUOTABASE_SYSTEM', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../db.php';

$quoteId = intval($_GET['quote_id'] ?? 0);
$serial = trim($_GET['serial'] ?? '');
$token = trim($_GET['token'] ?? '');

$receipt = null;

if ($quoteId > 0) {
    $receipt = get_receipt_by_quote($quoteId);
} elseif ($serial !== '') {
    $receipt = dbQueryOne("SELECT * FROM receipts WHERE serial = ?", [$serial]);
}

if (!$receipt) {
    http_response_code(404);
    echo '收據不存在';
    exit;
}

$allowed = false;

if (is_logged_in()) {
    $allowed = true;
} elseif ($token !== '') {
    $verification = verify_receipt_token($receipt['serial'], $token);
    $allowed = $verification['success'];
}

if (!$allowed) {
    http_response_code(403);
    echo '未授權下載';
    exit;
}

$customerTaxId = '';
$companyTaxId = '';
$stampImage = load_receipt_stamp_image();
if (!empty($receipt['quote_id'])) {
    $orgId = (int)($receipt['org_id'] ?? get_current_org_id());
    $customerRow = dbQueryOne(
        "SELECT c.tax_id AS customer_tax_id
         FROM quotes q
         LEFT JOIN customers c ON q.customer_id = c.id
         WHERE q.id = ? AND q.org_id = ?",
        [$receipt['quote_id'], $orgId]
    );
    if ($customerRow && !empty($customerRow['customer_tax_id'])) {
        $customerTaxId = trim((string)$customerRow['customer_tax_id']);
    }
}
$companyInfo = get_company_info();
$companyTaxId = trim((string)($companyInfo['tax_id'] ?? ''));

$basePath = realpath(__DIR__ . '/..');
$relativePath = '/' . ltrim($receipt['pdf_path'], '/');
$absolutePath = realpath($basePath . $relativePath);

if ($absolutePath === false || strpos($absolutePath, $basePath) !== 0 || !is_file($absolutePath)) {
    http_response_code(404);
    echo '檔案不存在或已移除';
    exit;
}

$mime = mime_content_type($absolutePath) ?: 'application/octet-stream';
$filename = basename($absolutePath);
$isHtml = stripos($mime, 'text/html') === 0 || preg_match('/\\.html?$/i', $filename);

if ($isHtml) {
    $mime = 'text/html; charset=UTF-8';
}

if ($isHtml) {
    $content = @file_get_contents($absolutePath);
    if ($content === false) {
        http_response_code(500);
        echo '收據內容讀取失敗';
        exit;
    }
    $originalContent = $content;

    if ($customerTaxId !== '') {
        $escapedTaxId = htmlspecialchars($customerTaxId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $replacements = 0;
        $content = preg_replace_callback(
            '/(<div class="info-block">\s*<h3>受款人資訊<\/h3>)(.*?)(<\/div>)/us',
            function ($matches) use ($escapedTaxId) {
                $inner = $matches[2];
                $inner = preg_replace('/\n[ \t]*<p[^>]*>\s*(?:證號|統編)：[^<]*<\/p>\s*/u', "\n", $inner);
                $inner = rtrim($inner, "\n") . "\n";
                $indent = '';
                if (preg_match('/\n([ \t]+)<p>客戶：/', $matches[2], $indentMatch)) {
                    $indent = "\n" . $indentMatch[1];
                } else {
                    $indent = "\n                ";
                }
                $inner .= $indent . '<p>統編：' . $escapedTaxId . '</p>' . "\n";
                return $matches[1] . $inner . $matches[3];
            },
            $content,
            1,
            $replacements
        ) ?: $content;

        if ($replacements === 0) {
            $content = preg_replace(
                '/(<h3>受款人資訊<\/h3>\s*<p>客戶：[^<]*<\/p>(?:\s*<p>[^<]*<\/p>)*)/u',
                '$1<p>統編：' . $escapedTaxId . '</p>',
                $content,
                1
            ) ?: $content;
        }
    }
    if ($companyTaxId !== '') {
        $escapedCompanyTaxId = htmlspecialchars($companyTaxId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $content = preg_replace_callback(
            '/(<div class="info-block">\s*<h3>開立人<\/h3>)(.*?)(<\/div>)/us',
            function ($matches) use ($escapedCompanyTaxId) {
                $inner = $matches[2];
                if (preg_match('/(<p[^>]*>\s*)(?:統編|證號)：[^<]*(<\/p>)/u', $inner)) {
                    $inner = preg_replace(
                        '/(<p[^>]*>\s*)(?:統編|證號)：[^<]*(<\/p>)/u',
                        '$1證號：' . $escapedCompanyTaxId . '$2',
                        $inner,
                        1
                    );
                } else {
                    $inner = rtrim($inner, "\n") . "\n";
                    if (preg_match('/\n([ \t]+)<p>/', $matches[2], $indentMatch)) {
                        $indent = "\n" . $indentMatch[1];
                    } else {
                        $indent = "\n                ";
                    }
                    $inner .= $indent . '<p>證號：' . $escapedCompanyTaxId . '</p>' . "\n";
                }
                return $matches[1] . $inner . $matches[3];
            },
            $content,
            1
        ) ?: $content;
    }
    if (!empty($stampImage)) {
        $escapedStamp = htmlspecialchars($stampImage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $content = preg_replace(
            '/<div class="stamp-area">\s*(?:<div[^>]*>\s*公司印章缺漏\s*<\/div>)\s*<\/div>/us',
            '<div class="stamp-area"><img src="' . $escapedStamp . '" alt="公司圖章"></div>',
            $content,
            1
        ) ?: $content;
    }
    $stampCssBlock = "    .stamp-area img {\n        width: 15mm;\n        height: auto;\n    }";
    $content = preg_replace(
        '/(\.stamp-area\s*img\s*\{\s*)([^}]*?)(\})/is',
        "\n" . $stampCssBlock,
        $content,
        1,
        $stampCssReplaced
    ) ?: $content;
    if ($stampCssReplaced === 0) {
        $content = preg_replace(
            '/(\.stamp-area\s*\{[^}]*\})/is',
            "$1\n" . $stampCssBlock,
            $content,
            1
        ) ?: $content;
    }
    $content = preg_replace(
        '/\n\s*[0-9.]+mm;\s*\n\s*height:\s*auto;\s*\n\s*\}/i',
        "\n",
        $content
    ) ?: $content;
    $content = preg_replace(
        '/<div class="signature-block">\s*<div class="signature-area">.*?<\/div>\s*(<div class="stamp-area">.*?<\/div>)\s*<\/div>/us',
        "\n    $1\n",
        $content
    ) ?: $content;
    if ($content !== $originalContent) {
        @file_put_contents($absolutePath, $content, LOCK_EX);
        @chmod($absolutePath, 0640);
    }

    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    header('X-Content-Type-Options: nosniff');
    echo $content;
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($absolutePath));
header('X-Content-Type-Options: nosniff');
readfile($absolutePath);
exit;
