<?php
/**
 * Helper Functions Library
 * 工具函式庫
 *
 * @version v2.0.0
 * @description 通用工具函式集合，包含安全、資料處理、格式化等函式
 * @遵循憲法原則I: 安全優先開發
 * @遵循憲法原則II: 精確財務資料處理
 */

// 防止直接訪問
if (!defined('QUOTABASE_SYSTEM')) {
    define('QUOTABASE_SYSTEM', true);
}

/**
 * ========================================
 * 安全防護函式 (Security Functions)
 * 遵循憲法原則I: 安全優先開發
 * ========================================
 */

/**
 * HTML轉義函式（防止XSS攻擊）
 * 必須用於所有動態輸出到HTML的內容
 *
 * @param string $string 待轉義的字串
 * @param string $encoding 字元編碼，預設UTF-8
 * @return string 轉義後的字串
 */
function h($string, $encoding = 'UTF-8') {
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_SUBSTITUTE, $encoding);
}

/**
 * 生成CSRF令牌
 *
 * @return string 64位十六進位制字串
 */
function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 驗證CSRF令牌
 *
 * @param string $token 待驗證的令牌
 * @return bool 驗證結果
 */
function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
}

/**
 * 檢查使用者是否已登入
 *
 * @return bool
 */
function is_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * 檢查會話是否過期
 *
 * @return bool
 */
function is_session_expired() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['login_time'])) {
        return true;
    }
    $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;
    return (time() - $_SESSION['login_time']) > $timeout;
}

/**
 * 獲取當前使用者ID
 *
 * @return int|null
 */
function get_current_user_id() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['user_id'] ?? null;
}

/**
 * 獲取當前組織ID
 *
 * @return int
 */
function get_current_org_id() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['org_id'] ?? (defined('DEFAULT_ORG_ID') ? DEFAULT_ORG_ID : 1);
}

/**
 * 生成隨機密碼
 *
 * @param int $length 密碼長度，預設12
 * @param bool $include_special 是否包含特殊字元，預設false
 * @return string 生成的密碼
 */
function generate_password($length = 12, $include_special = false) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    if ($include_special) {
        $chars .= '!@#$%^&*()_+-=[]{}|;:,.<>?';
    }

    $password = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    return $password;
}

/**
 * 驗證密碼強度
 *
 * @param string $password 密碼
 * @return array ['valid' => bool, 'message' => string]
 */
function validate_password_strength($password) {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = '密碼長度至少8位';
    }

    if (!preg_match('/[A-Za-z]/', $password)) {
        $errors[] = '密碼必須包含字母';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = '密碼必須包含數字';
    }

    return [
        'valid' => empty($errors),
        'message' => implode('；', $errors)
    ];
}

/**
 * ========================================
 * 財務資料處理函式 (Financial Data Functions)
 * 遵循憲法原則II: 精確財務資料處理
 * ========================================
 */

/**
 * 格式化金額（分 -> 貨幣格式顯示）
 *
 * @param int $cents 金額（單位：分）
 * @param string $currency 貨幣符號，預設NT$
 * @param int $decimals 小數位數，預設2
 * @return string 格式化後的金額字串
 *
 * @example
 * format_currency_cents(1000) // "NT$ 1,000.00"
 * format_currency_cents(500) // "NT$ 500.00"
 */
function format_currency_cents($cents, $currency = 'NT$', $decimals = 2) {
    $amount = number_format($cents / 100, $decimals, '.', ',');
    return $currency . '&nbsp;' . $amount;
}

/**
 * 格式化單價（從分轉換為元顯示）
 *
 * @param int $cents 單價（單位：分）
 * @param string $unit 單位，預設"元"
 * @param int $decimals 小數位數，預設2
 * @return string 格式化後的單價
 *
 * @example
 * format_unit_price(1500, '元') // "15.00 元"
 */
function format_unit_price($cents, $unit = '元', $decimals = 2) {
    $price = number_format($cents / 100, $decimals, '.', ',');
    return $price . ' ' . $unit;
}

/**
 * 計算未折扣前的行金額（數量 × 單價）
 *
 * @param float $qty 數量（支援小數，最多4位）
 * @param int $unit_price_cents 單價（分）
 * @return int 行原始金額（分）
 */
function calculate_line_gross($qty, $unit_price_cents) {
    return (int)round($qty * $unit_price_cents);
}

/**
 * 計算行小計（數量 × 單價 - 折扣）
 *
 * @param float $qty 數量（支援小數，最多4位）
 * @param int $unit_price_cents 單價（分）
 * @param int $discount_cents 折扣金額（分）
 * @return int 小計（分）
 */
function calculate_line_subtotal($qty, $unit_price_cents, $discount_cents = 0) {
    $gross = calculate_line_gross($qty, $unit_price_cents);
    $discount_cents = max(0, (int)$discount_cents);
    if ($discount_cents > $gross) {
        $discount_cents = $gross;
    }
    return $gross - $discount_cents;
}

/**
 * 計算折扣百分比
 *
 * @param int $discount_cents 折扣金額（分）
 * @param int $gross_cents 行原始金額（分）
 * @return float 折扣百分比，保留兩位
 */
function calculate_discount_percent($discount_cents, $gross_cents) {
    if ($gross_cents <= 0) {
        return 0.0;
    }
    return round(($discount_cents / $gross_cents) * 100, 2);
}

/**
 * 計算行稅額（小計 × 稅率）
 *
 * @param int $subtotal_cents 小計（分）
 * @param float $tax_rate 稅率（百分比，如5表示5%）
 * @return int 稅額（分）
 */
function calculate_line_tax($subtotal_cents, $tax_rate) {
    return (int)round($subtotal_cents * ($tax_rate / 100));
}

/**
 * 計算行總計（小計 + 稅額）
 *
 * @param int $subtotal_cents 小計（分）
 * @param int $tax_cents 稅額（分）
 * @return int 總計（分）
 */
function calculate_line_total($subtotal_cents, $tax_cents) {
    return $subtotal_cents + $tax_cents;
}

/**
 * 簡潔金額顯示（無小數）
 *
 * @param int $cents 金額（分）
 * @param string $currency 貨幣符號
 * @return string
 */
function format_currency_cents_compact($cents, $currency = 'NT$') {
    $amount = number_format($cents / 100, 0, '.', ',');
    return $currency . '&nbsp;' . $amount;
}

/**
 * 計算報價單總計
 *
 * @param array $items 報價專案陣列，每個元素包含line_subtotal_cents和line_tax_cents
 * @return array ['subtotal' => int, 'tax' => int, 'total' => int]
 */
function calculate_quote_total($items) {
    $subtotal = 0;
    $tax = 0;
    $total = 0;

    foreach ($items as $item) {
        $line_subtotal = $item['line_subtotal_cents'] ?? 0;
        $line_tax = $item['line_tax_cents'] ?? 0;
        $line_total = $item['line_total_cents'] ?? 0;

        $subtotal += $line_subtotal;
        $tax += $line_tax;
        $total += $line_total;
    }

    return [
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $total
    ];
}

/**
 * 將金額轉換為分（用於儲存）
 *
 * @param string|float $amount 金額字串或數字
 * @return int 金額（分）
 */
function amount_to_cents($amount) {
    // 移除貨幣符號和千位分隔符
    $amount = preg_replace('/[^\d.-]/', '', $amount);
    // 轉換為分
    return (int)round(floatval($amount) * 100);
}

/**
 * 格式化數量，避免多餘小數
 *
 * @param float $qty
 * @return string
 */
function format_quantity($qty) {
    if (fmod($qty, 1) === 0.0) {
        return number_format($qty, 0);
    }
    return rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
}

/**
 * ========================================
 * 資料驗證函式 (Validation Functions)
 * ========================================
 */

/**
 * 驗證郵箱格式
 *
 * @param string $email 郵箱地址
 * @return bool 驗證結果
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * 驗證稅務登記號格式（臺灣統一編號：8位數字）
 *
 * @param string $tax_id 稅務登記號
 * @return bool 驗證結果
 */
function is_valid_tax_id($tax_id) {
    return preg_match('/^\d{8}$/', $tax_id) === 1;
}

/**
 * 驗證SKU格式
 *
 * @param string $sku SKU編碼
 * @return bool 驗證結果
 */
function is_valid_sku($sku) {
    return preg_match('/^[A-Za-z0-9_-]{1,100}$/', $sku) === 1;
}

/**
 * 取得 SKU 字首
 *
 * @param string $type catalog type
 * @return string 字首
 */
function get_catalog_item_sku_prefix($type) {
    $type = $type === 'service' ? 'service' : 'product';
    $map = [
        'product' => 'PRO',
        'service' => 'SRV',
    ];
    return $map[$type] ?? 'CAT';
}

/**
 * 產生唯一 SKU（依型別 + 日期 + 流水號）
 *
 * @param string $type 型別（product/service）
 * @return string 生成的 SKU
 */
function generate_catalog_item_sku($type = 'product') {
    $type = $type === 'service' ? 'service' : 'product';
    $prefix = get_catalog_item_sku_prefix($type);
    $date_segment = date('Ymd');
    $org_id = get_current_org_id();
    $base = sprintf('%s-%s', $prefix, $date_segment);

    $latest = dbQueryOne(
        "SELECT sku FROM catalog_items WHERE org_id = ? AND sku LIKE ? ORDER BY sku DESC LIMIT 1",
        [$org_id, $base . '-%']
    );

    $sequence = 1;
    if ($latest && isset($latest['sku'])) {
        if (preg_match('/(\d+)$/', $latest['sku'], $matches)) {
            $sequence = intval($matches[1]) + 1;
        }
    }

    return sprintf('%s-%03d', $base, $sequence);
}

/**
 * 驗證URL格式
 *
 * @param string $url URL地址
 * @return bool 驗證結果
 */
function is_valid_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * 清理並驗證字串長度
 *
 * @param string $str 字串
 * @param int $max_length 最大長度
 * @param int $min_length 最小長度，預設0
 * @return bool 驗證結果
 */
function validate_string_length($str, $max_length, $min_length = 0) {
    $length = mb_strlen($str, 'UTF-8');
    return $length >= $min_length && $length <= $max_length;
}

/**
 * ========================================
 * 日期時間處理函式 (Date/Time Functions)
 * 遵循憲法原則II: UTC儲存、Asia/Taipei顯示
 * ========================================
 */

/**
 * 獲取當前日期（UTC）
 *
 * @param string $format 日期格式，預設Y-m-d
 * @return string 日期字串
 */
function get_current_date_utc($format = 'Y-m-d') {
    return gmdate($format);
}

/**
 * 獲取當前日期時間（UTC）
 *
 * @param string $format 日期時間格式，預設Y-m-d H:i:s
 * @return string 日期時間字串
 */
function get_current_datetime_utc($format = 'Y-m-d H:i:s') {
    return gmdate($format);
}

/**
 * 格式化日期（顯示時區）
 *
 * @param string $date 日期字串或日期時間
 * @param string $format 輸出格式，預設Y-m-d
 * @return string 格式化後的日期
 */
function format_date($date, $format = 'Y-m-d') {
    $display_tz = defined('DISPLAY_TIMEZONE') ? DISPLAY_TIMEZONE : 'Asia/Taipei';

    try {
        $dt = new DateTime($date, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone($display_tz));
        return $dt->format($format);
    } catch (Exception $e) {
        return $date; // 如果解析失敗，返回原始值
    }
}

/**
 * 格式化日期時間（顯示時區）
 *
 * @param string $datetime 日期時間字串
 * @param string $format 輸出格式，預設Y-m-d H:i
 * @return string 格式化後的日期時間
 */
function format_datetime($datetime, $format = 'Y-m-d H:i') {
    return format_date($datetime, $format);
}

/**
 * 將日期時間格式化為 ISO8601 UTC 字串
 *
 * @param string|null $datetime 原始日期時間
 * @return string|null ISO8601 字串或 null
 */
function format_iso8601_utc($datetime) {
    if (empty($datetime)) {
        return null;
    }

    try {
        $dt = new DateTime($datetime, new DateTimeZone('UTC'));
        return $dt->format(DateTime::ATOM);
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * 計算日期差
 *
 * @param string $date1 日期1
 * @param string $date2 日期2
 * @return int 天數差
 */
function date_diff_days($date1, $date2) {
    $dt1 = new DateTime($date1, new DateTimeZone('UTC'));
    $dt2 = new DateTime($date2, new DateTimeZone('UTC'));
    $diff = $dt1->diff($dt2);
    return $diff->days;
}

/**
 * ========================================
 * 業務邏輯函式 (Business Logic Functions)
 * ========================================
 */

/**
 * 生成分頁HTML
 *
 * @param int $current_page 當前頁碼
 * @param int $total_pages 總頁數
 * @param string $base_url 基礎URL
 * @return string HTML字串
 */
function generate_pagination($current_page, $total_pages, $base_url) {
    if ($total_pages <= 1) {
        return '';
    }

    $html = '<nav class="pagination"><ul>';

    // 上一頁
    if ($current_page > 1) {
        $html .= '<li><a href="' . h($base_url) . '?page=' . ($current_page - 1) . '">上一頁</a></li>';
    }

    // 頁碼
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $current_page) ? ' class="active"' : '';
        $html .= '<li' . $active . '><a href="' . h($base_url) . '?page=' . $i . '">' . $i . '</a></li>';
    }

    // 下一頁
    if ($current_page < $total_pages) {
        $html .= '<li><a href="' . h($base_url) . '?page=' . ($current_page + 1) . '">下一頁</a></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

/**
 * 獲取狀態標籤HTML
 *
 * @param string $status 狀態值
 * @return string HTML字串
 */
function get_status_badge($status) {
    $status_map = [
        'draft' => ['label' => '草稿', 'class' => 'badge-secondary'],
        'sent' => ['label' => '已傳送', 'class' => 'badge-info'],
        'accepted' => ['label' => '已接受', 'class' => 'badge-success'],
        'rejected' => ['label' => '已拒絕', 'class' => 'badge-danger'],
        'expired' => ['label' => '已過期', 'class' => 'badge-warning']
    ];

    $info = $status_map[$status] ?? ['label' => $status, 'class' => 'badge-secondary'];

    return '<span class="badge ' . h($info['class']) . '">' . h($info['label']) . '</span>';
}

/**
 * 獲取狀態中文標籤
 *
 * @param string $status 狀態值
 * @return string 中文標籤
 */
function get_status_label($status) {
    $status_map = [
        'draft' => '草稿',
        'sent' => '已傳送',
        'accepted' => '已接受',
        'rejected' => '已拒絕',
        'expired' => '已過期'
    ];

    return $status_map[$status] ?? $status;
}

/**
 * ========================================
 * 字串處理函式 (String Functions)
 * ========================================
 */

/**
 * 截斷字串（支援中文）
 *
 * @param string $str 原字串
 * @param int $length 截斷長度
 * @param string $suffix 字尾，預設"..."
 * @return string 截斷後的字串
 */
function truncate_string($str, $length, $suffix = '...') {
    $str = trim($str);
    if (mb_strlen($str, 'UTF-8') <= $length) {
        return $str;
    }
    return mb_substr($str, 0, $length, 'UTF-8') . $suffix;
}

/**
 * 生成隨機字串
 *
 * @param int $length 長度
 * @param string $charset 字元集，預設alphanumeric
 * @return string 隨機字串
 */
function random_string($length = 32, $charset = 'alphanumeric') {
    switch ($charset) {
        case 'numeric':
            $chars = '0123456789';
            break;
        case 'alphabetic':
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            break;
        case 'alphanumeric':
        default:
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            break;
    }

    $string = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $string .= $chars[random_int(0, $max)];
    }
    return $string;
}

/**
 * 清理字串（移除特殊字元）
 *
 * @param string $str 原始字串
 * @param bool $allow_spaces 是否允許空格，預設true
 * @param bool $allow_dashes 是否允許短橫線和下劃線，預設true
 * @return string 清理後的字串
 */
function clean_string($str, $allow_spaces = true, $allow_dashes = true) {
    $pattern = '/[^a-zA-Z0-9';
    if ($allow_spaces) $pattern .= '\s';
    if ($allow_dashes) $pattern .= '\-_';
    $pattern .= ']/';

    return preg_replace($pattern, '', $str);
}

/**
 * ========================================
 * 陣列處理函式 (Array Functions)
 * ========================================
 */

/**
 * 過濾陣列（移除空值）
 *
 * @param array $array 原始陣列
 * @param bool $remove_zero 是否移除0值，預設false
 * @return array 過濾後的陣列
 */
function filter_array($array, $remove_zero = false) {
    $filtered = [];
    foreach ($array as $key => $value) {
        if ($value !== null && $value !== '' && $value !== false) {
            if (!$remove_zero || $value !== 0) {
                $filtered[$key] = $value;
            }
        }
    }
    return $filtered;
}

/**
 * 從陣列中提取指定鍵的值
 *
 * @param array $array 原始陣列
 * @param string|array $keys 要提取的鍵
 * @param mixed $default 預設值
 * @return array 提取的結果
 */
function array_extract($array, $keys, $default = null) {
    $result = [];
    $key_array = (array)$keys;

    foreach ($key_array as $key) {
        $result[$key] = $array[$key] ?? $default;
    }

    return $result;
}

/**
 * ========================================
 * 除錯輔助函式 (Debug Functions)
 * ========================================
 */

/**
 * 除錯輸出（僅開發環境）
 *
 * @param mixed $data 要輸出的資料
 * @param bool $die 是否終止程式，預設false
 */
function debug($data, $die = false) {
    if ($_SERVER['SERVER_NAME'] === 'localhost' || isset($_GET['debug'])) {
        echo '<pre>';
        var_dump($data);
        echo '</pre>';

        if ($die) {
            die();
        }
    }
}

/**
 * 記錄除錯日誌
 *
 * @param string $message 日誌訊息
 * @param mixed $data 附加資料
 */
function debug_log($message, $data = null) {
    if ($_SERVER['SERVER_NAME'] === 'localhost' || isset($_GET['debug'])) {
        $log = '[DEBUG] ' . $message;
        if ($data !== null) {
            $log .= ': ' . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        error_log($log);
    }
}

/**
 * ========================================
 * 頁面輔助函式 (Page Helper Functions)
 * ========================================
 */

/**
 * 生成CSRF令牌輸入框HTML
 *
 * @return string HTML字串
 */
function csrf_input() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . h($token) . '">';
}

/**
 * 檢查當前頁面是否為列印頁
 *
 * @return bool
 */
function is_print_page() {
    return strpos($_SERVER['REQUEST_URI'], '/quotes/print.php') !== false;
}

/**
 * 獲取當前頁面名稱
 *
 * @return string 頁面名稱
 */
function get_current_page() {
    $uri = $_SERVER['REQUEST_URI'];
    if (strpos($uri, '/customers/') !== false) return 'customers';
    if (strpos($uri, '/products/') !== false) return 'products';
    if (strpos($uri, '/services/') !== false) return 'services';
    if (strpos($uri, '/quotes/') !== false) return 'quotes';
    if (strpos($uri, '/settings/') !== false) return 'settings';
    return 'quotes';
}

/**
 * ========================================
 * 檔案操作函式 (File Functions)
 * ========================================
 */

/**
 * 安全刪除檔案
 *
 * @param string $file_path 檔案路徑
 * @return bool 刪除結果
 */
function safe_delete_file($file_path) {
    // 檢查檔案路徑是否在允許的目錄內
    $allowed_dirs = [__DIR__ . '/../uploads'];
    $real_path = realpath($file_path);

    foreach ($allowed_dirs as $dir) {
        if (strpos($real_path, realpath($dir)) === 0) {
            return unlink($file_path);
        }
    }

    return false;
}

/**
 * 生成安全的檔名
 *
 * @param string $original_name 原始檔名
 * @param string $prefix 字首，預設''
 * @return string 安全檔名
 */
function generate_safe_filename($original_name, $prefix = '') {
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $basename = pathinfo($original_name, PATHINFO_FILENAME);

    // 清理檔名
    $basename = clean_string($basename, true, false);
    $basename = substr($basename, 0, 50);

    // 生成時間戳
    $timestamp = time();

    return $prefix . $timestamp . '_' . $basename . '.' . $extension;
}

/**
 * ========================================
 * 常用常量 (Common Constants)
 * ========================================
 */

// 稅率選項
define('TAX_RATES', [
    0.00 => '0%',
    5.00 => '5%',
    8.00 => '8%',
    10.00 => '10%'
]);

// 單位選項
define('PRODUCT_UNITS', [
    'pcs'   => '件',
    'piece' => '個',
    'unit'  => '臺',
    'set'   => '組',
    'box'   => '盒',
    'pack'  => '包',
    'bag'   => '袋',
    'bottle'=> '瓶',
    'case'  => '箱',
    'pair'  => '雙',
    'roll'  => '卷',
    'sheet' => '張'
]);

define('SERVICE_UNITS', [
    'time'  => '次',
    'hour'  => '時',
    'day'   => '日',
    'week'  => '週',
    'month' => '月',
    'year'  => '年'
]);

define('UNITS', array_merge(
    PRODUCT_UNITS,
    SERVICE_UNITS,
    [
        'times' => '次',
        'hours' => '時',
        'days' => '日',
        'weeks' => '週',
    ]
));

// 貨幣選項（預留多貨幣支援）
define('CURRENCIES', [
    'TWD' => '新臺幣'
]);

/**
 * ========================================
 * Customer 模型操作函式 (Customer Model Functions)
 * US2: 客戶管理
 * ========================================
 */

/**
 * 獲取客戶列表（分頁）
 *
 * @param int $page 頁碼，預設1
 * @param int $limit 每頁數量，預設20
 * @param string $search 搜尋關鍵詞
 * @return array ['data' => array, 'total' => int, 'pages' => int]
 */
function get_customers($page = 1, $limit = 20, $search = '') {
    $org_id = get_current_org_id();
    $offset = ($page - 1) * $limit;

    $where_conditions = ['org_id = ?', 'active = 1'];
    $params = [$org_id];

    if (!empty($search)) {
        $where_conditions[] = '(name LIKE ? OR tax_id LIKE ? OR email LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    $where_clause = implode(' AND ', $where_conditions);

    // 獲取總數
    $count_sql = "SELECT COUNT(*) as total FROM customers WHERE {$where_clause}";
    $total = dbQueryOne($count_sql, $params)['total'];
    $total_pages = ceil($total / $limit);

    // 獲取資料
    $sql = "
        SELECT id, name, tax_id, email, phone, billing_address, shipping_address, note, created_at
        FROM customers
        WHERE {$where_clause}
        ORDER BY name ASC, id DESC
        LIMIT ? OFFSET ?
    ";
    $params[] = $limit;
    $params[] = $offset;

    $data = dbQuery($sql, $params);

    return [
        'data' => $data,
        'total' => $total,
        'pages' => $total_pages,
        'current_page' => $page
    ];
}

/**
 * 獲取單個客戶資訊
 *
 * @param int $id 客戶ID
 * @return array|null 客戶資訊或null
 */
function get_customer($id) {
    $org_id = get_current_org_id();
    $sql = "SELECT * FROM customers WHERE id = ? AND org_id = ? AND active = 1";
    return dbQueryOne($sql, [$id, $org_id]);
}

/**
 * 建立新客戶
 *
 * @param array $data 客戶資料
 * @return array ['success' => bool, 'id' => int, 'error' => string]
 */
function create_customer($data) {
    try {
        // 驗證輸入
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = '客戶名稱不能為空';
        } elseif (!validate_string_length($data['name'], 255, 1)) {
            $errors[] = '客戶名稱長度不正確';
        }

        if (!empty($data['tax_id']) && !is_valid_tax_id($data['tax_id'])) {
            $errors[] = '稅務登記號格式不正確（應為8位數字）';
        }

        if (!empty($data['email']) && !is_valid_email($data['email'])) {
            $errors[] = '郵箱格式不正確';
        }

        if (!empty($errors)) {
            return ['success' => false, 'error' => implode('；', $errors)];
        }

        $org_id = get_current_org_id();

        $sql = "
            INSERT INTO customers (
                org_id, name, tax_id, email, phone,
                billing_address, shipping_address, note, active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ";

        $params = [
            $org_id,
            trim($data['name']),
            trim($data['tax_id'] ?? null),
            trim($data['email'] ?? null),
            trim($data['phone'] ?? null),
            trim($data['billing_address'] ?? null),
            trim($data['shipping_address'] ?? null),
            trim($data['note'] ?? null)
        ];

        dbExecute($sql, $params);
        $customer_id = dbLastInsertId();

        return [
            'success' => true,
            'id' => $customer_id,
            'message' => '客戶建立成功'
        ];

    } catch (Exception $e) {
        error_log("Create customer error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '建立客戶失敗，請稍後再試'
        ];
    }
}

/**
 * 更新客戶資訊
 *
 * @param int $id 客戶ID
 * @param array $data 客戶資料
 * @return array ['success' => bool, 'error' => string]
 */
function update_customer($id, $data) {
    try {
        // 驗證輸入
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = '客戶名稱不能為空';
        } elseif (!validate_string_length($data['name'], 255, 1)) {
            $errors[] = '客戶名稱長度不正確';
        }

        if (!empty($data['tax_id']) && !is_valid_tax_id($data['tax_id'])) {
            $errors[] = '稅務登記號格式不正確（應為8位數字）';
        }

        if (!empty($data['email']) && !is_valid_email($data['email'])) {
            $errors[] = '郵箱格式不正確';
        }

        if (!empty($errors)) {
            return ['success' => false, 'error' => implode('；', $errors)];
        }

        $org_id = get_current_org_id();

        // 檢查客戶是否存在
        $exists = get_customer($id);
        if (!$exists) {
            return ['success' => false, 'error' => '客戶不存在'];
        }

        $sql = "
            UPDATE customers
            SET name = ?, tax_id = ?, email = ?, phone = ?,
                billing_address = ?, shipping_address = ?, note = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND org_id = ?
        ";

        $params = [
            trim($data['name']),
            trim($data['tax_id'] ?? null),
            trim($data['email'] ?? null),
            trim($data['phone'] ?? null),
            trim($data['billing_address'] ?? null),
            trim($data['shipping_address'] ?? null),
            trim($data['note'] ?? null),
            $id,
            $org_id
        ];

        dbExecute($sql, $params);

        return [
            'success' => true,
            'message' => '客戶資訊更新成功'
        ];

    } catch (Exception $e) {
        error_log("Update customer error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '更新客戶失敗，請稍後再試'
        ];
    }
}

/**
 * 刪除客戶（軟刪除）
 *
 * @param int $id 客戶ID
 * @return array ['success' => bool, 'error' => string]
 */
function delete_customer($id) {
    try {
        $org_id = get_current_org_id();

        // 檢查客戶是否存在
        $customer = get_customer($id);
        if (!$customer) {
            return ['success' => false, 'error' => '客戶不存在'];
        }

        // 檢查是否有關聯的報價單
        $has_quotes = dbQueryOne(
            "SELECT COUNT(*) as count FROM quotes WHERE customer_id = ? AND org_id = ?",
            [$id, $org_id]
        );

        if ($has_quotes['count'] > 0) {
            return [
                'success' => false,
                'error' => '該客戶存在關聯的報價單，無法刪除'
            ];
        }

        // 軟刪除（設定為不啟用）
        dbExecute(
            "UPDATE customers SET active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND org_id = ?",
            [$id, $org_id]
        );

        return [
            'success' => true,
            'message' => '客戶刪除成功'
        ];

    } catch (Exception $e) {
        error_log("Delete customer error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '刪除客戶失敗，請稍後再試'
        ];
    }
}

/**
 * 恢復客戶（啟用）
 *
 * @param int $id 客戶ID
 * @return array ['success' => bool, 'error' => string]
 */
function restore_customer($id) {
    try {
        $org_id = get_current_org_id();

        dbExecute(
            "UPDATE customers SET active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND org_id = ?",
            [$id, $org_id]
        );

        return [
            'success' => true,
            'message' => '客戶恢復成功'
        ];

    } catch (Exception $e) {
        error_log("Restore customer error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '恢復客戶失敗，請稍後再試'
        ];
    }
}

/**
 * 獲取客戶列表（用於下拉選擇）
 *
 * @return array 客戶列表
 */
function get_customer_list() {
    $org_id = get_current_org_id();
    $sql = "SELECT id, name FROM customers WHERE org_id = ? AND active = 1 ORDER BY name ASC";
    return dbQuery($sql, [$org_id]);
}

/**
 * ========================================
 * CatalogItem 模型操作函式 (CatalogItem Model Functions)
 * US3: 產品/服務目錄管理
 * ========================================
 */

/**
 * 獲取產品/服務列表（分頁）
 *
 * @param string $type 型別 (product|service)，為空時獲取全部
 * @param int $page 頁碼，預設1
 * @param int $limit 每頁數量，預設20
 * @param string $search 搜尋關鍵詞
 * @param int|null $category_id 分類ID
 * @return array ['data' => array, 'total' => int, 'pages' => int]
 */
function get_catalog_items($type = '', $page = 1, $limit = 20, $search = '', $category_id = null) {
    $org_id = get_current_org_id();
    $offset = ($page - 1) * $limit;

    $where_conditions = ['org_id = ?', 'active = 1'];
    $params = [$org_id];

    if (!empty($type) && in_array($type, ['product', 'service'])) {
        $where_conditions[] = 'type = ?';
        $params[] = $type;
    }

    if (!empty($search)) {
        $where_conditions[] = '(sku LIKE ? OR name LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    $where_clause = implode(' AND ', $where_conditions);

    // 獲取總數
    $count_sql = "SELECT COUNT(*) as total FROM catalog_items WHERE {$where_clause}";
    $total = dbQueryOne($count_sql, $params)['total'];
    $total_pages = ceil($total / $limit);

    // 獲取資料
    $sql = "
        SELECT id, type, sku, name, unit, currency, unit_price_cents, tax_rate, category_id, created_at
        FROM catalog_items
        WHERE {$where_clause}
        ORDER BY category_id ASC, name ASC, id DESC
        LIMIT ? OFFSET ?
    ";
    $params[] = $limit;
    $params[] = $offset;

    $data = dbQuery($sql, $params);

    return [
        'data' => $data,
        'total' => $total,
        'pages' => $total_pages,
        'current_page' => $page,
        'type' => $type
    ];
}


/**
 * ========================================
 * Catalog Category 操作函式 (Catalog Category Model Functions)
 * ========================================
 */

/**
 * 獲取單一分類
 *
 * @param int $id 分類ID
 * @return array|null
 */
function get_catalog_category($id) {
    if (empty($id)) {
        return null;
    }

    $org_id = get_current_org_id();
    $sql = "SELECT * FROM catalog_categories WHERE id = ? AND org_id = ?";
    return dbQueryOne($sql, [$id, $org_id]);
}

/**
 * 根據父級獲取分類列表
 *
 * @param string $type 分類型別
 * @param int|null $parent_id 父分類ID
 * @return array
 */
function get_catalog_categories($type = 'product', $parent_id = null) {
    $org_id = get_current_org_id();
    $params = [$org_id, $type];
    $sql = "SELECT id, parent_id, level, name, sort_order FROM catalog_categories WHERE org_id = ? AND type = ?";

    if ($parent_id === null) {
        $sql .= " AND parent_id IS NULL";
    } else {
        $sql .= " AND parent_id = ?";
        $params[] = $parent_id;
    }

    $sql .= " ORDER BY sort_order ASC, name ASC";

    return dbQuery($sql, $params);
}

/**
 * 獲取分類樹
 *
 * @param string $type 分類型別
 * @param int|null $parent_id 父分類
 * @return array
 */
function get_catalog_categories_tree($type = 'product', $parent_id = null) {
    $children = get_catalog_categories($type, $parent_id);
    $tree = [];

    foreach ($children as $child) {
        $child['children'] = get_catalog_categories_tree($type, $child['id']);
        $tree[] = $child;
    }

    return $tree;
}

/**
 * 扁平化分類列表（帶層級）
 *
 * @param string $type 分類型別
 * @param int|null $parent_id
 * @param array $acc
 * @return array
 */
function get_catalog_category_flat_list($type = 'product', $parent_id = null, $acc = []) {
    $items = get_catalog_categories($type, $parent_id);

    foreach ($items as $item) {
        $acc[] = $item;
        $acc = get_catalog_category_flat_list($type, $item['id'], $acc);
    }

    return $acc;
}

/**
 * 獲取分類路徑（字串）
 *
 * @param int $category_id
 * @param string $separator
 * @return string
 */
function get_catalog_category_path($category_id, $separator = ' / ') {
    if (empty($category_id)) {
        return '';
    }

    $path = [];
    $current = get_catalog_category($category_id);

    while ($current) {
        array_unshift($path, $current['name']);
        if (empty($current['parent_id'])) {
            break;
        }
        $current = get_catalog_category($current['parent_id']);
    }

    return implode($separator, $path);
}

/**
 * 獲取分類路徑ID列表
 *
 * @param int $category_id
 * @return array
 */
function get_catalog_category_path_ids($category_id) {
    $ids = [];
    $current = get_catalog_category($category_id);

    while ($current) {
        array_unshift($ids, (int)$current['id']);
        if (empty($current['parent_id'])) {
            break;
        }
        $current = get_catalog_category($current['parent_id']);
    }

    return $ids;
}

/**
 * 批次獲取分類路徑
 *
 * @param array $category_ids
 * @param string $separator
 * @return array [category_id => path]
 */
function get_catalog_category_paths($category_ids, $separator = ' / ') {
    $paths = [];
    if (empty($category_ids)) {
        return $paths;
    }

    $category_ids = array_unique(array_filter(array_map('intval', $category_ids)));

    foreach ($category_ids as $category_id) {
        $paths[$category_id] = get_catalog_category_path($category_id, $separator);
    }

    return $paths;
}

/**
 * 獲取分類字典（id => [..]）
 *
 * @param string $type
 * @return array
 */
function get_catalog_category_map($type = 'product') {
    $list = get_catalog_category_flat_list($type);
    $map = [];
    foreach ($list as $item) {
        $map[$item['id']] = $item;
    }
    return $map;
}

/**
 * 獲取分類的所有子孫ID
 *
 * @param int $category_id
 * @return array
 */
function get_catalog_category_descendants($category_id) {
    $descendants = [];
    $queue = [$category_id];

    while (!empty($queue)) {
        $current = array_shift($queue);
        $children = dbQuery(
            "SELECT id FROM catalog_categories WHERE parent_id = ?",
            [$current]
        );

        foreach ($children as $child) {
            $child_id = (int)$child['id'];
            $descendants[] = $child_id;
            $queue[] = $child_id;
        }
    }

    return $descendants;
}

/**
 * 建立分類
 *
 * @param array $data
 * @return array
 */
function create_catalog_category($data) {
    try {
        $errors = [];
        $org_id = get_current_org_id();
        $type = $data['type'] ?? 'product';

        if (!in_array($type, ['product', 'service'], true)) {
            $errors[] = '分類型別無效';
        }

        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            $errors[] = '分類名稱不能為空';
        } elseif (!validate_string_length($name, 100, 1)) {
            $errors[] = '分類名稱長度需在1-100字元之間';
        }

        $sort_order = isset($data['sort_order']) ? intval($data['sort_order']) : 0;
        $parent_id = isset($data['parent_id']) && $data['parent_id'] !== '' ? intval($data['parent_id']) : null;

        $level = 1;
        if ($parent_id) {
            $parent = get_catalog_category($parent_id);
            if (!$parent) {
                $errors[] = '父級分類不存在';
            } elseif ($parent['type'] !== $type) {
                $errors[] = '父級分類型別不一致';
            } else {
                $level = (int)$parent['level'] + 1;
                if ($level > 3) {
                    $errors[] = '分類最多支援三級結構';
                }
            }
        }

        if (empty($errors)) {
            // 檢查重複名稱
            $params = [$org_id, $type];
            $sql = "SELECT id FROM catalog_categories WHERE org_id = ? AND type = ?";
            if ($parent_id) {
                $sql .= " AND parent_id = ?";
                $params[] = $parent_id;
            } else {
                $sql .= " AND parent_id IS NULL";
            }
            $sql .= " AND name = ?";
            $params[] = $name;

            $exists = dbQueryOne($sql, $params);
            if ($exists) {
                $errors[] = '相同層級下已存在同名分類';
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'error' => implode('；', $errors)];
        }

        dbExecute(
            "INSERT INTO catalog_categories (org_id, type, parent_id, level, name, sort_order) VALUES (?, ?, ?, ?, ?, ?)",
            [$org_id, $type, $parent_id, $level, $name, $sort_order]
        );

        return [
            'success' => true,
            'message' => '分類建立成功'
        ];

    } catch (Exception $e) {
        error_log("Create catalog category error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '建立分類失敗，請稍後再試'
        ];
    }
}

/**
 * 更新分類
 *
 * @param int $id
 * @param array $data
 * @return array
 */
function update_catalog_category($id, $data) {
    try {
        $category = get_catalog_category($id);
        if (!$category) {
            return ['success' => false, 'error' => '分類不存在'];
        }

        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            return ['success' => false, 'error' => '分類名稱不能為空'];
        }
        if (!validate_string_length($name, 100, 1)) {
            return ['success' => false, 'error' => '分類名稱長度需在1-100字元之間'];
        }

        $sort_order = isset($data['sort_order']) ? intval($data['sort_order']) : (int)$category['sort_order'];

        // 檢查同層級重複名稱
        $params = [$category['org_id'], $category['type']];
        $sql = "SELECT id FROM catalog_categories WHERE org_id = ? AND type = ?";
        if ($category['parent_id']) {
            $sql .= " AND parent_id = ?";
            $params[] = $category['parent_id'];
        } else {
            $sql .= " AND parent_id IS NULL";
        }
        $sql .= " AND name = ? AND id != ?";
        $params[] = $name;
        $params[] = $id;

        $exists = dbQueryOne($sql, $params);
        if ($exists) {
            return ['success' => false, 'error' => '相同層級下已存在同名分類'];
        }

        dbExecute(
            "UPDATE catalog_categories SET name = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND org_id = ?",
            [$name, $sort_order, $id, $category['org_id']]
        );

        return [
            'success' => true,
            'message' => '分類更新成功'
        ];

    } catch (Exception $e) {
        error_log("Update catalog category error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '更新分類失敗，請稍後再試'
        ];
    }
}

/**
 * 刪除分類
 *
 * @param int $id
 * @return array
 */
function delete_catalog_category($id) {
    try {
        $category = get_catalog_category($id);
        if (!$category) {
            return ['success' => false, 'error' => '分類不存在'];
        }

        $org_id = $category['org_id'];

        $descendants = get_catalog_category_descendants($id);
        $all_ids = array_merge([$id], $descendants);

        if (!empty($all_ids)) {
            $placeholders = implode(',', array_fill(0, count($all_ids), '?'));
            $params = array_merge([$org_id], $all_ids);

            dbExecute(
                "UPDATE catalog_items SET category_id = NULL WHERE org_id = ? AND category_id IN ($placeholders)",
                $params
            );
        }

        dbExecute("DELETE FROM catalog_categories WHERE id = ? AND org_id = ?", [$id, $org_id]);

        return [
            'success' => true,
            'message' => '分類已刪除'
        ];

    } catch (Exception $e) {
        error_log("Delete catalog category error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '刪除分類失敗，請稍後再試'
        ];
    }
}

/**
 * 獲取單個產品/服務資訊
 *
 * @param int $id 產品/服務ID
 * @return array|null 產品/服務資訊或null
 */
function get_catalog_item($id) {
    $org_id = get_current_org_id();
    $sql = "SELECT * FROM catalog_items WHERE id = ? AND org_id = ? AND active = 1";
    return dbQueryOne($sql, [$id, $org_id]);
}

/**
 * 建立新產品/服務
 *
 * @param array $data 產品/服務資料
 * @return array ['success' => bool, 'id' => int, 'error' => string]
 */
function create_catalog_item($data) {
    try {
        // 驗證輸入
        $errors = [];
        $data['type'] = $data['type'] ?? 'product';
        if (!in_array($data['type'], ['product', 'service'])) {
            $errors[] = '型別必須為 product 或 service';
        }

        $auto_generated_sku = false;
        if (empty($data['sku']) && empty($errors)) {
            $data['sku'] = generate_catalog_item_sku($data['type']);
            $auto_generated_sku = true;
        }

        $data['sku'] = trim($data['sku'] ?? '');

        if (empty($data['sku'])) {
            $errors[] = 'SKU不能為空';
        } elseif (!is_valid_sku($data['sku'])) {
            $errors[] = 'SKU格式不正確（只允許字母、數字、-和_）';
        }

        if (empty($data['name'])) {
            $errors[] = '名稱不能為空';
        } elseif (!validate_string_length($data['name'], 255, 1)) {
            $errors[] = '名稱長度不正確';
        }

        if (!isset($data['unit_price_cents']) || $data['unit_price_cents'] < 0) {
            $errors[] = '單價必須為非負整數';
        }

        if (!empty($data['tax_rate']) && ($data['tax_rate'] < 0 || $data['tax_rate'] > 100)) {
            $errors[] = '稅率必須在 0-100 之間';
        }

        $category_id = isset($data['category_id']) && intval($data['category_id']) > 0 ? intval($data['category_id']) : null;

        if ($category_id) {
            $category = get_catalog_category($category_id);
            if (!$category) {
                $errors[] = '選擇的分類不存在';
            } elseif ($category['type'] !== $data['type']) {
                $errors[] = '分類型別與產品型別不一致';
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'error' => implode('；', $errors)];
        }

        $org_id = get_current_org_id();

        if ($auto_generated_sku) {
            $attempts = 0;
            while (!is_sku_unique($data['sku']) && $attempts < 5) {
                $data['sku'] = generate_catalog_item_sku($data['type']);
                $attempts++;
            }
        }

        if (!is_sku_unique($data['sku'])) {
            $message = $auto_generated_sku
                ? '系統嘗試自動生成 SKU 但仍遇到衝突，請稍後再試。'
                : 'SKU已存在，請使用其他SKU';
            return ['success' => false, 'error' => $message];
        }

        $sql = "
            INSERT INTO catalog_items (
                org_id, type, sku, name, unit, currency,
                unit_price_cents, tax_rate, category_id, active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ";

        $params = [
            $org_id,
            $data['type'],
            trim($data['sku']),
            trim($data['name']),
            trim($data['unit'] ?? 'pcs'),
            $data['currency'] ?? 'TWD',
            (int)$data['unit_price_cents'],
            floatval($data['tax_rate'] ?? 0.00),
            $category_id
        ];

        dbExecute($sql, $params);
        $catalog_item_id = dbLastInsertId();

        return [
            'success' => true,
            'id' => $catalog_item_id,
            'message' => '產品/服務建立成功'
        ];

    } catch (Exception $e) {
        error_log("Create catalog item error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '建立產品/服務失敗，請稍後再試'
        ];
    }
}

/**
 * 更新產品/服務資訊
 *
 * @param int $id 產品/服務ID
 * @param array $data 產品/服務資料
 * @return array ['success' => bool, 'error' => string]
 */
function update_catalog_item($id, $data) {
    try {
        // 驗證輸入
        $errors = [];

        if (empty($data['sku'])) {
            $errors[] = 'SKU不能為空';
        } elseif (!is_valid_sku($data['sku'])) {
            $errors[] = 'SKU格式不正確（只允許字母、數字、-和_）';
        }

        if (empty($data['name'])) {
            $errors[] = '名稱不能為空';
        } elseif (!validate_string_length($data['name'], 255, 1)) {
            $errors[] = '名稱長度不正確';
        }

        if (!isset($data['unit_price_cents']) || $data['unit_price_cents'] < 0) {
            $errors[] = '單價必須為非負整數';
        }

        if (!empty($data['tax_rate']) && ($data['tax_rate'] < 0 || $data['tax_rate'] > 100)) {
            $errors[] = '稅率必須在 0-100 之間';
        }

        $category_id = isset($data['category_id']) && intval($data['category_id']) > 0 ? intval($data['category_id']) : null;

        if (!empty($errors)) {
            return ['success' => false, 'error' => implode('；', $errors)];
        }

        $org_id = get_current_org_id();

        // 檢查產品/服務是否存在
        $exists = get_catalog_item($id);
        if (!$exists) {
            return ['success' => false, 'error' => '產品/服務不存在'];
        }

        // 檢查SKU唯一性（排除當前記錄）
        $sku_exists = dbQueryOne(
            "SELECT id FROM catalog_items WHERE org_id = ? AND sku = ? AND id != ?",
            [$org_id, $data['sku'], $id]
        );

        if ($sku_exists) {
            return ['success' => false, 'error' => 'SKU已存在，請使用其他SKU'];
        }

        if ($category_id) {
            $category = get_catalog_category($category_id);
            if (!$category) {
                return ['success' => false, 'error' => '選擇的分類不存在'];
            }
            if ($category['type'] !== $exists['type']) {
                return ['success' => false, 'error' => '分類型別與產品型別不一致'];
            }
        }

        $sql = "
            UPDATE catalog_items
            SET sku = ?, name = ?, unit = ?, currency = ?,
                unit_price_cents = ?, tax_rate = ?, category_id = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND org_id = ?
        ";

        $params = [
            trim($data['sku']),
            trim($data['name']),
            trim($data['unit'] ?? 'pcs'),
            $data['currency'] ?? 'TWD',
            (int)$data['unit_price_cents'],
            floatval($data['tax_rate'] ?? 0.00),
            $category_id,
            $id,
            $org_id
        ];

        dbExecute($sql, $params);

        return [
            'success' => true,
            'message' => '產品/服務資訊更新成功'
        ];

    } catch (Exception $e) {
        error_log("Update catalog item error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '更新產品/服務失敗，請稍後再試'
        ];
    }
}

/**
 * 刪除產品/服務（軟刪除）
 *
 * @param int $id 產品/服務ID
 * @return array ['success' => bool, 'error' => string]
 */
function delete_catalog_item($id) {
    try {
        $org_id = get_current_org_id();

        // 檢查產品/服務是否存在
        $catalog_item = get_catalog_item($id);
        if (!$catalog_item) {
            return ['success' => false, 'error' => '產品/服務不存在'];
        }

        // 檢查是否有關聯的報價單專案
        $has_quote_items = dbQueryOne(
            "SELECT COUNT(*) as count FROM quote_items WHERE catalog_item_id = ?",
            [$id]
        );

        if ($has_quote_items['count'] > 0) {
            return [
                'success' => false,
                'error' => '該產品/服務存在關聯的報價單專案，無法刪除'
            ];
        }

        // 軟刪除（設定為不啟用）
        dbExecute(
            "UPDATE catalog_items SET active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND org_id = ?",
            [$id, $org_id]
        );

        return [
            'success' => true,
            'message' => '產品/服務刪除成功'
        ];

    } catch (Exception $e) {
        error_log("Delete catalog item error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '刪除產品/服務失敗，請稍後再試'
        ];
    }
}

/**
 * 恢復產品/服務（啟用）
 *
 * @param int $id 產品/服務ID
 * @return array ['success' => bool, 'error' => string]
 */
function restore_catalog_item($id) {
    try {
        $org_id = get_current_org_id();

        dbExecute(
            "UPDATE catalog_items SET active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND org_id = ?",
            [$id, $org_id]
        );

        return [
            'success' => true,
            'message' => '產品/服務恢復成功'
        ];

    } catch (Exception $e) {
        error_log("Restore catalog item error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '恢復產品/服務失敗，請稍後再試'
        ];
    }
}

/**
 * 獲取產品/服務列表（用於下拉選擇）
 *
 * @param string $type 型別 (product|service)，為空時獲取全部
 * @return array 產品/服務列表
 */
function get_catalog_item_list($type = '') {
    $org_id = get_current_org_id();
    $where_clause = "org_id = ? AND active = 1";
    $params = [$org_id];

    if (!empty($type) && in_array($type, ['product', 'service'])) {
        $where_clause .= " AND type = ?";
        $params[] = $type;
    }

    $sql = "
        SELECT id, type, sku, name, unit, unit_price_cents, tax_rate
        FROM catalog_items
        WHERE {$where_clause}
        ORDER BY type ASC, name ASC
    ";
    return dbQuery($sql, $params);
}

/**
 * 檢查SKU是否唯一
 *
 * @param string $sku SKU編碼
 * @param int $exclude_id 排除的ID（用於編輯時）
 * @return bool 是否唯一
 */
function is_sku_unique($sku, $exclude_id = 0) {
    $org_id = get_current_org_id();
    $sql = "SELECT id FROM catalog_items WHERE org_id = ? AND sku = ?";
    $params = [$org_id, $sku];

    if ($exclude_id > 0) {
        $sql .= " AND id != ?";
        $params[] = $exclude_id;
    }

    $result = dbQueryOne($sql, $params);
    return $result === null;
}

/**
 * 根據型別獲取產品或服務統計
 *
 * @return array 統計資訊
 */
function get_catalog_stats() {
    $org_id = get_current_org_id();

    $stats = [];

    // 產品數量
    $product_count = dbQueryOne(
        "SELECT COUNT(*) as count FROM catalog_items WHERE org_id = ? AND type = 'product' AND active = 1",
        [$org_id]
    );
    $stats['products'] = $product_count['count'];

    // 服務數量
    $service_count = dbQueryOne(
        "SELECT COUNT(*) as count FROM catalog_items WHERE org_id = ? AND type = 'service' AND active = 1",
        [$org_id]
    );
    $stats['services'] = $service_count['count'];

    // 總數
    $stats['total'] = $stats['products'] + $stats['services'];

    return $stats;
}

/**
 * ========================================
 * Quote 模型操作函式 (Quote Model Functions)
 * US4: 報價單建立與管理
 * ========================================
 */

/**
 * 獲取報價單列表（分頁）
 *
 * @param int $page 頁碼，預設1
 * @param int $limit 每頁數量，預設20
 * @param string $search 搜尋關鍵詞
 * @param string $status 狀態篩選
 * @return array ['data' => array, 'total' => int, 'pages' => int]
 */
function get_quotes($page = 1, $limit = 20, $search = '', $status = '') {
    $org_id = get_current_org_id();
    $offset = ($page - 1) * $limit;

    $where_conditions = ['q.org_id = ?'];
    $params = [$org_id];

    if (!empty($search)) {
        $where_conditions[] = '(q.quote_number LIKE ? OR customer.name LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    if (!empty($status)) {
        $where_conditions[] = 'q.status = ?';
        $params[] = $status;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // 獲取總數
    $count_sql = "
        SELECT COUNT(*) as total
        FROM quotes q
        LEFT JOIN customers customer ON q.customer_id = customer.id
        WHERE {$where_clause}
    ";
    $total = dbQueryOne($count_sql, $params)['total'];
    $total_pages = ceil($total / $limit);

    // 獲取資料
    $sql = "
        SELECT
            q.id, q.quote_number, q.status, q.issue_date, q.valid_until,
            q.subtotal_cents, q.tax_cents, q.total_cents,
            customer.name as customer_name,
            q.created_at
        FROM quotes q
        LEFT JOIN customers customer ON q.customer_id = customer.id
        WHERE {$where_clause}
        ORDER BY q.created_at DESC, q.id DESC
        LIMIT ? OFFSET ?
    ";
    $params[] = $limit;
    $params[] = $offset;

    $data = dbQuery($sql, $params);

    return [
        'data' => $data,
        'total' => $total,
        'pages' => $total_pages,
        'current_page' => $page
    ];
}

/**
 * 獲取單個報價單資訊（不包含明細）
 *
 * @param int $id 報價單ID
 * @return array|null 報價單資訊或null
 */
function get_quote($id) {
    $org_id = get_current_org_id();
    $sql = "
        SELECT q.*, q.notes as note, customer.name as customer_name, customer.tax_id, customer.email, customer.phone
        FROM quotes q
        LEFT JOIN customers customer ON q.customer_id = customer.id
        WHERE q.id = ? AND q.org_id = ?
    ";
    $quote = dbQueryOne($sql, [$id, $org_id]);

    if ($quote) {
        // 獲取報價單明細
        $quote['items'] = get_quote_items($id);
    }

    return $quote;
}

/**
 * 獲取報價單明細
 *
 * @param int $quote_id 報價單ID
 * @return array 明細列表
 */
function get_quote_items($quote_id) {
    $sql = "
        SELECT
            qi.id,
            qi.catalog_item_id,
            qi.description,
            qi.qty,
            qi.unit,
            qi.unit_price_cents,
            qi.discount_cents,
            qi.tax_rate,
            qi.line_subtotal_cents,
            qi.line_tax_cents,
            qi.line_total_cents,
            qi.line_order,
            catalog.sku,
            catalog.name AS catalog_name,
            catalog.unit AS catalog_unit,
            catalog.type AS catalog_type,
            catalog.category_id
        FROM quote_items qi
        LEFT JOIN catalog_items catalog ON qi.catalog_item_id = catalog.id
        WHERE qi.quote_id = ?
        ORDER BY qi.line_order ASC, qi.id ASC
    ";

    $items = dbQuery($sql, [$quote_id]);

    $category_cache = [];

    foreach ($items as &$item) {
        $item['qty'] = floatval($item['qty']);
        $item['quantity'] = $item['qty'];
        $item['unit_price_cents'] = (int)$item['unit_price_cents'];
        $item['discount_cents'] = (int)($item['discount_cents'] ?? 0);
        $item['line_subtotal_cents'] = (int)$item['line_subtotal_cents'];
        $item['line_tax_cents'] = (int)$item['line_tax_cents'];
        $item['line_total_cents'] = (int)$item['line_total_cents'];
        $item['category_id'] = isset($item['category_id']) ? (int)$item['category_id'] : null;
        $gross_cents = calculate_line_gross($item['qty'], $item['unit_price_cents']);
        $item['gross_cents'] = $gross_cents;
        $item['discount_percent'] = calculate_discount_percent($item['discount_cents'], $gross_cents);
        if (!empty($item['catalog_unit'])) {
            $item['unit'] = $item['catalog_unit'];
        } elseif (empty($item['unit'])) {
            $item['unit'] = ($item['catalog_type'] ?? '') === 'service' ? 'time' : '';
        }
        if (empty($item['unit'])) {
            $item['unit'] = 'pcs';
        }
        if (empty($item['description'])) {
            $item['description'] = $item['catalog_name'] ?? '';
        }
        $item['item_name'] = $item['description'];
        unset($item['catalog_unit']);
        if (!empty($item['category_id'])) {
            $category_id = (int)$item['category_id'];
            if (!array_key_exists($category_id, $category_cache)) {
                $category_cache[$category_id] = get_catalog_category_path($category_id, ' / ');
            }
            $item['category_path'] = $category_cache[$category_id];
        }
    }
    unset($item);

    return $items;
}

/**
 * 建立新報價單（使用事務）
 *
 * @param array $data 報價單資料
 * @param array $items 報價專案陣列
 * @return array ['success' => bool, 'id' => int, 'error' => string]
 */
function create_quote($data, $items) {
    try {
        // 驗證輸入
        $errors = [];

        if (empty($data['customer_id'])) {
            $errors[] = '請選擇客戶';
        }

        if (empty($items) || !is_array($items)) {
            $errors[] = '請新增報價專案';
        }

        if (!empty($errors)) {
            return ['success' => false, 'error' => implode('；', $errors)];
        }

        // 開始事務
        $pdo = getDB()->getConnection();
        $pdo->beginTransaction();

        try {
            // 1. 生成報價單編號
            $quote_number = generate_quote_number($pdo);
            if (!$quote_number['success']) {
                throw new Exception($quote_number['error']);
            }

            $org_id = get_current_org_id();
            $quote_number_str = $quote_number['quote_number'];

            // 2. 插入報價單主記錄
            $sql = "
                INSERT INTO quotes (
                    org_id, customer_id, quote_number, status,
                    issue_date, valid_until, notes,
                    subtotal_cents, tax_cents, total_cents
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $note = $data['note'] ?? ($data['notes'] ?? null);

            $params = [
                $org_id,
                $data['customer_id'],
                $quote_number_str,
                $data['status'] ?? 'draft',
                $data['issue_date'] ?? get_current_date_utc(),
                $data['valid_until'] ?? null,
                $note,
                0, // 臨時值，稍後更新
                0, // 臨時值，稍後更新
                0  // 臨時值，稍後更新
            ];

            dbExecute($sql, $params);
            $quote_id = dbLastInsertId();

            // 3. 插入報價單明細並計算總額
            $total_subtotal = 0;
            $total_tax = 0;
            $total_amount = 0;

            $line_order = 1;
            foreach ($items as $item) {
                if (empty($item['catalog_item_id'])) {
                    continue;
                }

                // 獲取目錄項資訊
                $catalog_item = get_catalog_item($item['catalog_item_id']);
                if (!$catalog_item) {
                    throw new Exception('目錄項不存在');
                }

                $quantity = floatval($item['qty'] ?? ($item['quantity'] ?? 0));
                if ($quantity <= 0) {
                    throw new Exception('請填寫有效的數量');
                }

                $description = trim($item['description'] ?? '') ?: ($catalog_item['name'] ?? '未命名專案');
                $unit = trim($item['unit'] ?? '') ?: ($catalog_item['unit'] ?? null);

                // 計算行金額
                $unit_price_cents = intval($item['unit_price_cents'] ?? $catalog_item['unit_price_cents']);
                $tax_rate = isset($item['tax_rate']) && $item['tax_rate'] !== ''
                    ? floatval($item['tax_rate'])
                    : ($catalog_item['tax_rate'] !== null && $catalog_item['tax_rate'] !== ''
                        ? floatval($catalog_item['tax_rate'])
                        : get_default_tax_rate());

                if ($unit_price_cents < 0) {
                    throw new Exception('單價必須為非負數');
                }
                if ($tax_rate < 0 || $tax_rate > 100) {
                    throw new Exception('稅率必須在 0-100 之間');
                }

                $discount_cents = 0;
                if (isset($item['discount_cents'])) {
                    $discount_cents = max(0, intval($item['discount_cents']));
                } elseif (isset($item['discount'])) {
                    $discount_cents = max(0, amount_to_cents($item['discount']));
                }

                $gross_cents = calculate_line_gross($quantity, $unit_price_cents);
                if ($discount_cents > $gross_cents) {
                    throw new Exception('折扣金額不能超過行金額');
                }

                $line_subtotal_cents = calculate_line_subtotal($quantity, $unit_price_cents, $discount_cents);
                $line_tax_cents = calculate_line_tax($line_subtotal_cents, $tax_rate);
                $line_total_cents = calculate_line_total($line_subtotal_cents, $line_tax_cents);

                // 插入明細
                $item_sql = "
                    INSERT INTO quote_items (
                        quote_id, catalog_item_id, description, qty, unit,
                        unit_price_cents, discount_cents, tax_rate,
                        line_subtotal_cents, line_tax_cents, line_total_cents, line_order
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";

                dbExecute($item_sql, [
                    $quote_id,
                    $item['catalog_item_id'],
                    $description,
                    $quantity,
                    $unit,
                    $unit_price_cents,
                    $discount_cents,
                    $tax_rate,
                    $line_subtotal_cents,
                    $line_tax_cents,
                    $line_total_cents,
                    $line_order
                ]);

                $total_subtotal += $line_subtotal_cents;
                $total_tax += $line_tax_cents;
                $total_amount += $line_total_cents;
                $line_order++;
            }

            // 4. 更新報價單總額
            $update_sql = "
                UPDATE quotes
                SET subtotal_cents = ?, tax_cents = ?, total_cents = ?
                WHERE id = ?
            ";
            dbExecute($update_sql, [$total_subtotal, $total_tax, $total_amount, $quote_id]);

            // 提交事務
            $pdo->commit();

            return [
                'success' => true,
                'id' => $quote_id,
                'quote_number' => $quote_number_str,
                'message' => '報價單建立成功'
            ];

        } catch (Exception $e) {
            // 回滾事務
            $pdo->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        error_log("Create quote error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '建立報價單失敗：' . $e->getMessage()
        ];
    }
}

/**
 * 生成報價單編號（使用儲存過程）
 *
 * @param PDO $pdo 資料庫連線
 * @return array ['success' => bool, 'quote_number' => string, 'error' => string]
 */
function generate_quote_number($pdo = null) {
    try {
        $org_id = get_current_org_id();

        if ($pdo === null) {
            $pdo = getDB()->getConnection();
        }

        // 呼叫儲存過程並獲取輸出引數
        $stmt = $pdo->prepare("CALL next_quote_number(?, @out_quote_number)");
        $stmt->execute([$org_id]);
        $stmt->closeCursor();

        $result_stmt = $pdo->query("SELECT @out_quote_number AS quote_number");
        $result = $result_stmt ? $result_stmt->fetch(PDO::FETCH_ASSOC) : null;

        if (!$result || empty($result['quote_number'])) {
            return ['success' => false, 'error' => '生成報價單編號失敗'];
        }

        return [
            'success' => true,
            'quote_number' => $result['quote_number']
        ];

    } catch (Exception $e) {
        error_log("Generate quote number error: " . $e->getMessage());
        return ['success' => false, 'error' => '生成報價單編號失敗'];
    }
}

/**
 * 更新報價單狀態
 *
 * @param int $id 報價單ID
 * @param string $status 新狀態
 * @return array ['success' => bool, 'error' => string]
 */
function update_quote_status($id, $status) {
    try {
        $org_id = get_current_org_id();
        $valid_statuses = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

        if (!in_array($status, $valid_statuses)) {
            return ['success' => false, 'error' => '無效的狀態值'];
        }

        // 檢查報價單是否存在
        $quote = get_quote($id);
        if (!$quote) {
            return ['success' => false, 'error' => '報價單不存在'];
        }

        // 更新狀態
        $sql = "
            UPDATE quotes
            SET status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND org_id = ?
        ";
        dbExecute($sql, [$status, $id, $org_id]);

        return [
            'success' => true,
            'message' => '狀態更新成功'
        ];

    } catch (Exception $e) {
        error_log("Update quote status error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '更新狀態失敗'
        ];
    }
}

/**
 * 刪除報價單（軟刪除）
 *
 * @param int $id 報價單ID
 * @return array ['success' => bool, 'error' => string]
 */
function delete_quote($id) {
    try {
        $org_id = get_current_org_id();

        // 檢查報價單是否存在
        $quote = get_quote($id);
        if (!$quote) {
            return ['success' => false, 'error' => '報價單不存在'];
        }

        // 軟刪除
        $sql = "
            UPDATE quotes
            SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND org_id = ?
        ";
        dbExecute($sql, [$id, $org_id]);

        return [
            'success' => true,
            'message' => '報價單刪除成功'
        ];

    } catch (Exception $e) {
        error_log("Delete quote error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '刪除報價單失敗'
        ];
    }
}

/**
 * 獲取報價單列表（用於下拉選擇）
 *
 * @return array 報價單列表
 */
function get_quote_list() {
    $org_id = get_current_org_id();
    $sql = "
        SELECT q.id, q.quote_number, q.total_cents, customer.name as customer_name
        FROM quotes q
        LEFT JOIN customers customer ON q.customer_id = customer.id
        WHERE q.org_id = ? AND q.status IN ('draft', 'sent', 'accepted')
        ORDER BY q.created_at DESC, q.id DESC
    ";
    return dbQuery($sql, [$org_id]);
}

/**
 * ========================================
 * QuoteItem 模型操作函式 (QuoteItem Model Functions)
 * ========================================
 */

/**
 * 更新報價專案
 *
 * @param int $id 報價專案ID
 * @param array $data 專案資料
 * @return array ['success' => bool, 'error' => string]
 */
function update_quote_item($id, $data) {
    try {
        // 獲取當前專案資訊
        $current_item = dbQueryOne("SELECT * FROM quote_items WHERE id = ?", [$id]);
        if (!$current_item) {
            return ['success' => false, 'error' => '專案不存在'];
        }

        $quantity_raw = $data['quantity'] ?? ($data['qty'] ?? null);
        if (!is_numeric($quantity_raw)) {
            return ['success' => false, 'error' => '數量格式不正確'];
        }
        // 重新計算金額
        $quantity = floatval($quantity_raw);
        if ($quantity <= 0) {
            return ['success' => false, 'error' => '數量必須大於 0'];
        }

        $unit_price_cents = null;
        if (isset($data['unit_price_cents']) && $data['unit_price_cents'] !== '') {
            if (!is_numeric($data['unit_price_cents'])) {
                return ['success' => false, 'error' => '單價格式不正確'];
            }
            $unit_price_cents = intval($data['unit_price_cents']);
        } elseif (isset($data['unit_price']) && $data['unit_price'] !== '') {
            $normalized_price = preg_replace('/[^\d.-]/', '', (string)$data['unit_price']);
            if ($normalized_price === '' || !preg_match('/^-?\d+(\.\d+)?$/', $normalized_price)) {
                return ['success' => false, 'error' => '單價格式不正確'];
            }
            $unit_price_cents = amount_to_cents($normalized_price);
        }

        if ($unit_price_cents === null) {
            $unit_price_cents = intval($current_item['unit_price_cents']);
        }

        if ($unit_price_cents < 0) {
            return ['success' => false, 'error' => '單價必須為非負數'];
        }

        $tax_rate_raw = $data['tax_rate'] ?? null;
        if (!is_numeric($tax_rate_raw)) {
            return ['success' => false, 'error' => '稅率格式不正確'];
        }
        $tax_rate = floatval($tax_rate_raw);
        if ($tax_rate < 0 || $tax_rate > 100) {
            return ['success' => false, 'error' => '稅率必須在 0-100 之間'];
        }

        $discount_cents = 0;
        if (isset($data['discount_cents']) && $data['discount_cents'] !== '') {
            if (!is_numeric($data['discount_cents'])) {
                return ['success' => false, 'error' => '折扣金額格式不正確'];
            }
            $discount_cents = intval($data['discount_cents']);
        } elseif (isset($data['discount']) && $data['discount'] !== '') {
            $normalized_discount = preg_replace('/[^\d.-]/', '', (string)$data['discount']);
            if ($normalized_discount === '' || !preg_match('/^-?\d+(\.\d+)?$/', $normalized_discount)) {
                return ['success' => false, 'error' => '折扣金額格式不正確'];
            }
            $discount_cents = amount_to_cents($normalized_discount);
        }

        if ($discount_cents < 0) {
            $discount_cents = 0;
        }

        $gross_cents = calculate_line_gross($quantity, $unit_price_cents);
        if ($discount_cents > $gross_cents) {
            return ['success' => false, 'error' => '折扣金額不能超過該行金額'];
        }

        $line_subtotal_cents = calculate_line_subtotal($quantity, $unit_price_cents, $discount_cents);
        $line_tax_cents = calculate_line_tax($line_subtotal_cents, $tax_rate);
        $line_total_cents = calculate_line_total($line_subtotal_cents, $line_tax_cents);

        $description = trim($data['description'] ?? $current_item['description']);
        if ($description !== '' && !validate_string_length($description, 500)) {
            return ['success' => false, 'error' => '專案描述長度超過限制'];
        }

        $unit = trim($data['unit'] ?? ($current_item['unit'] ?? ''));
        if ($unit !== '' && !validate_string_length($unit, 20)) {
            return ['success' => false, 'error' => '單位長度超過限制'];
        }

        // 更新專案
        $sql = "
            UPDATE quote_items
            SET description = ?, unit = ?, qty = ?, unit_price_cents = ?, discount_cents = ?, tax_rate = ?,
                line_subtotal_cents = ?, line_tax_cents = ?, line_total_cents = ?
            WHERE id = ?
        ";
        dbExecute($sql, [
            $description,
            $unit,
            $quantity,
            $unit_price_cents,
            $discount_cents,
            $tax_rate,
            $line_subtotal_cents,
            $line_tax_cents,
            $line_total_cents,
            $id
        ]);

        // 重新計算報價單總額
        recalculate_quote_total($current_item['quote_id']);

        return [
            'success' => true,
            'message' => '專案更新成功'
        ];

    } catch (Exception $e) {
        error_log("Update quote item error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '更新專案失敗'
        ];
    }
}

/**
 * 刪除報價專案
 *
 * @param int $id 報價專案ID
 * @return array ['success' => bool, 'error' => string]
 */
function delete_quote_item($id) {
    try {
        // 獲取專案資訊
        $item = dbQueryOne("SELECT quote_id FROM quote_items WHERE id = ?", [$id]);
        if (!$item) {
            return ['success' => false, 'error' => '專案不存在'];
        }

        // 刪除專案
        dbExecute("DELETE FROM quote_items WHERE id = ?", [$id]);

        // 重新計算報價單總額
        recalculate_quote_total($item['quote_id']);

        return [
            'success' => true,
            'message' => '專案刪除成功'
        ];

    } catch (Exception $e) {
        error_log("Delete quote item error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '刪除專案失敗'
        ];
    }
}

/**
 * 重新計算報價單總額
 *
 * @param int $quote_id 報價單ID
 */
function recalculate_quote_total($quote_id) {
    $sql = "
        SELECT
            SUM(line_subtotal_cents) as subtotal,
            SUM(line_tax_cents) as tax,
            SUM(line_total_cents) as total
        FROM quote_items
        WHERE quote_id = ?
    ";
    $totals = dbQueryOne($sql, [$quote_id]);

    $update_sql = "
        UPDATE quotes
        SET subtotal_cents = ?, tax_cents = ?, total_cents = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ";
    dbExecute($update_sql, [
        $totals['subtotal'] ?? 0,
        $totals['tax'] ?? 0,
        $totals['total'] ?? 0,
        $quote_id
    ]);
}

function get_next_quote_item_order($quote_id) {
    $row = dbQueryOne(
        "SELECT MAX(line_order) as max_order FROM quote_items WHERE quote_id = ?",
        [$quote_id]
    );
    return (int)($row['max_order'] ?? 0) + 1;
}

/**
 * 新增報價專案
 *
 * @param int $quote_id 報價單ID
 * @param array $item_data 專案資料
 * @return array ['success' => bool, 'id' => int, 'error' => string]
 */
function add_quote_item($quote_id, $item_data) {
    try {
        // 驗證資料
        $quantity = floatval($item_data['qty'] ?? ($item_data['quantity'] ?? 0));
        if (empty($item_data['catalog_item_id']) || $quantity <= 0) {
            return ['success' => false, 'error' => '缺少必要引數'];
        }

        // 獲取目錄項資訊
        $catalog_item = get_catalog_item($item_data['catalog_item_id']);
        if (!$catalog_item) {
            return ['success' => false, 'error' => '目錄項不存在'];
        }

        // 計算金額
        $unit_price_cents = $item_data['unit_price_cents'] ?? $catalog_item['unit_price_cents'];
        if (!is_numeric($unit_price_cents)) {
            $unit_price_cents = $catalog_item['unit_price_cents'];
        }
        $unit_price_cents = intval($unit_price_cents);
        $tax_rate = isset($item_data['tax_rate']) ? floatval($item_data['tax_rate']) : floatval($catalog_item['tax_rate']);
        $discount_cents = 0;
        if (isset($item_data['discount_cents']) && $item_data['discount_cents'] !== '') {
            if (is_numeric($item_data['discount_cents'])) {
                $discount_cents = intval($item_data['discount_cents']);
            }
        } elseif (isset($item_data['discount']) && $item_data['discount'] !== '') {
            $normalized_discount = preg_replace('/[^\d.-]/', '', (string)$item_data['discount']);
            if ($normalized_discount !== '' && preg_match('/^-?\d+(\.\d+)?$/', $normalized_discount)) {
                $discount_cents = amount_to_cents($normalized_discount);
            }
        }
        if ($discount_cents < 0) {
            $discount_cents = 0;
        }

        $description = trim($item_data['description'] ?? '') ?: ($catalog_item['name'] ?? '未命名專案');
        $unit = trim($catalog_item['unit'] ?? '');
        if ($unit === '') {
            $unit = ($catalog_item['type'] ?? '') === 'service' ? 'time' : 'pcs';
        }

        $gross_cents = calculate_line_gross($quantity, $unit_price_cents);
        if ($discount_cents > $gross_cents) {
            return ['success' => false, 'error' => '折扣金額不能超過行小計'];
        }

        $line_subtotal_cents = calculate_line_subtotal($quantity, $unit_price_cents, $discount_cents);
        $line_tax_cents = calculate_line_tax($line_subtotal_cents, $tax_rate);
        $line_total_cents = calculate_line_total($line_subtotal_cents, $line_tax_cents);

        $line_order = get_next_quote_item_order($quote_id);

        // 插入專案
        $sql = "
            INSERT INTO quote_items (
                quote_id, catalog_item_id, description, qty, unit,
                unit_price_cents, discount_cents, tax_rate,
                line_subtotal_cents, line_tax_cents, line_total_cents, line_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        dbExecute($sql, [
            $quote_id,
            $item_data['catalog_item_id'],
            $description,
            $quantity,
            $unit,
            $unit_price_cents,
            $discount_cents,
            $tax_rate,
            $line_subtotal_cents,
            $line_tax_cents,
            $line_total_cents,
            $line_order
        ]);

        $item_id = dbLastInsertId();

        // 重新計算報價單總額
        recalculate_quote_total($quote_id);

        return [
            'success' => true,
            'id' => $item_id,
            'message' => '專案新增成功'
        ];

    } catch (Exception $e) {
        error_log("Add quote item error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '新增專案失敗'
        ];
    }
}

/**
 * 以一組明細取代現有報價專案（草稿編輯用）
 *
 * @param int $quote_id
 * @param array $items
 * @return array
 */
function replace_quote_items($quote_id, $items) {
    $pdo = getDB()->getConnection();
    $pdo->beginTransaction();

    try {
        if (empty($items) || !is_array($items)) {
            throw new Exception('請至少新增一條報價專案');
        }

        $line_order = 1;
        $total_subtotal = 0;
        $total_tax = 0;
        $total_amount = 0;

        dbExecute("DELETE FROM quote_items WHERE quote_id = ?", [$quote_id]);

        foreach ($items as $item) {
            $quantity = floatval($item['qty'] ?? 0);
            if ($quantity <= 0) {
                throw new Exception('數量必須大於0');
            }

            $catalog_item_id = intval($item['catalog_item_id'] ?? 0);
            $catalog_item = get_catalog_item($catalog_item_id);
            if (!$catalog_item) {
                throw new Exception('所選產品/服務不存在');
            }

            $unit_price_cents = isset($item['unit_price_cents']) && $item['unit_price_cents'] !== ''
                ? intval($item['unit_price_cents'])
                : intval($catalog_item['unit_price_cents']);
            if ($unit_price_cents < 0) {
                throw new Exception('單價必須為非負數');
            }

            $tax_rate = isset($item['tax_rate']) && $item['tax_rate'] !== ''
                ? floatval($item['tax_rate'])
                : floatval($catalog_item['tax_rate']);
            if ($tax_rate < 0 || $tax_rate > 100) {
                throw new Exception('稅率必須在 0-100 之間');
            }

            $description = trim($item['description'] ?? '');
            if ($description === '') {
                $description = $catalog_item['name'] ?? '未命名專案';
            }

            $unit = trim($catalog_item['unit'] ?? '');
            if ($unit === '') {
                $unit = ($catalog_item['type'] ?? '') === 'service' ? 'time' : 'pcs';
            }

            $discount_cents = max(0, intval($item['discount_cents'] ?? 0));
            $gross_cents = calculate_line_gross($quantity, $unit_price_cents);
            if ($discount_cents > $gross_cents) {
                throw new Exception('折扣金額不能超過行金額');
            }

            $line_subtotal_cents = calculate_line_subtotal($quantity, $unit_price_cents, $discount_cents);
            $line_tax_cents = calculate_line_tax($line_subtotal_cents, $tax_rate);
            $line_total_cents = calculate_line_total($line_subtotal_cents, $line_tax_cents);

            dbExecute(
                "INSERT INTO quote_items (
                    quote_id, catalog_item_id, description, qty, unit,
                    unit_price_cents, discount_cents, tax_rate,
                    line_subtotal_cents, line_tax_cents, line_total_cents, line_order
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $quote_id,
                    $catalog_item_id,
                    $description,
                    $quantity,
                    $unit,
                    $unit_price_cents,
                    $discount_cents,
                    $tax_rate,
                    $line_subtotal_cents,
                    $line_tax_cents,
                    $line_total_cents,
                    $line_order
                ]
            );

            $total_subtotal += $line_subtotal_cents;
            $total_tax += $line_tax_cents;
            $total_amount += $line_total_cents;

            $line_order++;
        }

        if ($line_order === 1) {
            throw new Exception('請至少新增一條報價專案');
        }

        dbExecute(
            "UPDATE quotes
             SET subtotal_cents = ?, tax_cents = ?, total_cents = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            [$total_subtotal, $total_tax, $total_amount, $quote_id]
        );

        $pdo->commit();
        return ['success' => true];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Replace quote items error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * 處理報價單編輯提交（草稿專用）
 *
 * @param array $quote 當前報價單資料（需包含id與status）
 * @param array $post_data 表單提交資料
 * @return array ['success' => bool, 'error' => string]
 */
function process_quote_edit($quote, $post_data) {
    if (empty($quote) || !isset($quote['id'])) {
        return ['success' => false, 'error' => '報價單不存在'];
    }

    if (($quote['status'] ?? '') !== 'draft') {
        return ['success' => false, 'error' => '僅草稿狀態可編輯明細'];
    }

    $items_input = $post_data['items'] ?? [];
    if (!is_array($items_input)) {
        return ['success' => false, 'error' => '提交的明細格式不正確'];
    }

    $normalized_items = [];

    foreach ($items_input as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $catalog_item_id = intval($item['catalog_item_id'] ?? 0);
        $qty_raw = $item['qty'] ?? ($item['quantity'] ?? null);
        $qty = is_numeric($qty_raw) ? floatval($qty_raw) : null;
        $description = trim($item['description'] ?? '');
        $unit = trim($item['unit'] ?? '');

        // 判斷是否為空行（完全未填寫且無目錄項）
        $has_any_value = $catalog_item_id > 0
            || ($qty !== null && $qty > 0)
            || $description !== ''
            || $unit !== '';
        if (!$has_any_value) {
            continue;
        }

        if ($catalog_item_id <= 0) {
            return ['success' => false, 'error' => sprintf('第 %d 行未選擇產品或服務', $index + 1)];
        }

        if ($qty === null || $qty <= 0) {
            return ['success' => false, 'error' => sprintf('第 %d 行數量必須大於 0', $index + 1)];
        }

        if ($description !== '' && !validate_string_length($description, 500)) {
            return ['success' => false, 'error' => sprintf('第 %d 行描述長度超過限制', $index + 1)];
        }

        if ($unit !== '' && !validate_string_length($unit, 20)) {
            return ['success' => false, 'error' => sprintf('第 %d 行單位長度超過限制', $index + 1)];
        }

        // 單價（優先接收以分為單位的數值，若無則嘗試由元轉換）
        $unit_price_cents = null;
        if (isset($item['unit_price_cents']) && $item['unit_price_cents'] !== '') {
            if (!is_numeric($item['unit_price_cents'])) {
                return ['success' => false, 'error' => sprintf('第 %d 行單價格式不正確', $index + 1)];
            }
            $unit_price_cents = intval($item['unit_price_cents']);
        } elseif (isset($item['unit_price']) && $item['unit_price'] !== '') {
            $normalized_price = preg_replace('/[^\d.-]/', '', (string)$item['unit_price']);
            if ($normalized_price === '' || !preg_match('/^-?\d+(\.\d+)?$/', $normalized_price)) {
                return ['success' => false, 'error' => sprintf('第 %d 行單價格式不正確', $index + 1)];
            }
            $unit_price_cents = amount_to_cents($normalized_price);
        }

        if ($unit_price_cents !== null && $unit_price_cents < 0) {
            return ['success' => false, 'error' => sprintf('第 %d 行單價必須為非負數', $index + 1)];
        }

        // 折扣金額
        $discount_cents = 0;
        if (isset($item['discount_cents']) && $item['discount_cents'] !== '') {
            if (!is_numeric($item['discount_cents'])) {
                return ['success' => false, 'error' => sprintf('第 %d 行折扣金額格式不正確', $index + 1)];
            }
            $discount_cents = intval($item['discount_cents']);
        } elseif (isset($item['discount']) && $item['discount'] !== '') {
            $normalized_discount = preg_replace('/[^\d.-]/', '', (string)$item['discount']);
            if ($normalized_discount === '' || !preg_match('/^-?\d+(\.\d+)?$/', $normalized_discount)) {
                return ['success' => false, 'error' => sprintf('第 %d 行折扣金額格式不正確', $index + 1)];
            }
            $discount_cents = amount_to_cents($normalized_discount);
        }

        if ($discount_cents < 0) {
            $discount_cents = 0;
        }

        // 稅率
        $tax_rate_set = false;
        $tax_rate_value = null;
        if (isset($item['tax_rate']) && $item['tax_rate'] !== '') {
            $tax_rate_value = floatval($item['tax_rate']);
            if ($tax_rate_value < 0 || $tax_rate_value > 100) {
                return ['success' => false, 'error' => sprintf('第 %d 行稅率必須在 0-100 之間', $index + 1)];
            }
            $tax_rate_set = true;
        }

        $normalized = [
            'catalog_item_id' => $catalog_item_id,
            'qty' => $qty,
            'discount_cents' => $discount_cents,
        ];

        if ($unit_price_cents !== null) {
            $normalized['unit_price_cents'] = $unit_price_cents;
        }

        if ($tax_rate_set) {
            $normalized['tax_rate'] = $tax_rate_value;
        }

        if ($description !== '') {
            $normalized['description'] = $description;
        }

        if ($unit !== '') {
            $normalized['unit'] = $unit;
        }

        $normalized_items[] = $normalized;
    }

    if (empty($normalized_items)) {
        return ['success' => false, 'error' => '請至少保留一條有效的報價專案'];
    }

    return replace_quote_items($quote['id'], $normalized_items);
}

/**
 * ========================================
 * Settings 模型操作函式 (Settings Model Functions)
 * US5: 設定管理
 * ========================================
 */

/**
 * 獲取系統設定
 *
 * @return array|null 設定資訊或null
 */
function get_settings() {
    $org_id = get_current_org_id();
    $sql = "SELECT * FROM settings WHERE org_id = ?";
    $settings = dbQueryOne($sql, [$org_id]);

    // 如果沒有設定，建立預設設定
    if (!$settings) {
        $default_settings = [
            'company_name' => '',
            'company_address' => '',
            'company_contact' => '',
            'company_tax_id' => '',
            'quote_prefix' => 'Q',
            'default_tax_rate' => 0.00,
            'print_terms' => '',
            'timezone' => defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'Asia/Taipei'
        ];

        // 插入預設設定
        $insert_sql = "
            INSERT INTO settings (
                org_id, company_name, company_address, company_contact,
                quote_prefix, default_tax_rate, print_terms, timezone
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        dbExecute($insert_sql, array_merge([$org_id], array_values($default_settings)));

        // 返回剛插入的設定
        return array_merge(['id' => dbLastInsertId(), 'org_id' => $org_id], $default_settings);
    }

    return $settings;
}

/**
 * 更新系統設定
 *
 * @param array $data 設定資料
 * @return array ['success' => bool, 'error' => string]
 */
function update_settings($data) {
    try {
        $org_id = get_current_org_id();

        $errors = [];

        if (!empty($data['company_name']) && !validate_string_length($data['company_name'], 255)) {
            $errors[] = '公司名稱長度不正確';
        }

        if (!empty($data['company_address']) && !validate_string_length($data['company_address'], 2000)) {
            $errors[] = '公司地址長度不能超過2000個字元';
        }

        if (!empty($data['company_contact']) && !validate_string_length($data['company_contact'], 50)) {
            $errors[] = '聯絡電話長度不能超過50個字元';
        }

        if (!empty($data['company_tax_id']) && !validate_string_length($data['company_tax_id'], 50)) {
            $errors[] = '統一編號長度不能超過50個字元';
        }

        if (!empty($data['print_terms']) && !validate_string_length($data['print_terms'], 5000)) {
            $errors[] = '列印條款長度不能超過5000個字元';
        }

        if (!empty($errors)) {
            return ['success' => false, 'error' => implode('；', $errors)];
        }

        $defaultTimezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'Asia/Taipei';

        $fields = [
            'company_name' => trim($data['company_name'] ?? ''),
            'company_address' => trim($data['company_address'] ?? ''),
            'company_contact' => trim($data['company_contact'] ?? ''),
            'company_tax_id' => trim($data['company_tax_id'] ?? ''),
            'print_terms' => trim($data['print_terms'] ?? ''),
            'default_tax_rate' => 0.00,
            'quote_prefix' => 'Q',
            'timezone' => $defaultTimezone
        ];

        $exists = dbQueryOne("SELECT id FROM settings WHERE org_id = ?", [$org_id]);

        if ($exists) {
            $setParts = [];
            $params = [];
            foreach ($fields as $column => $value) {
                $setParts[] = "{$column} = ?";
                $params[] = $value;
            }
            $params[] = $org_id;
            $sql = "UPDATE settings SET " . implode(', ', $setParts) . " WHERE org_id = ?";
            dbExecute($sql, $params);
        } else {
            $insert_fields = array_merge(['org_id'], array_keys($fields));
            $placeholders = str_repeat('?,', count($insert_fields) - 1) . '?';
            $values = array_merge([$org_id], array_values($fields));
            $sql = "INSERT INTO settings (" . implode(', ', $insert_fields) . ") VALUES ($placeholders)";
            dbExecute($sql, $values);
        }

        $current_year = date('Y');
        $sequence_exists = dbQueryOne("SELECT id FROM quote_sequences WHERE org_id = ? AND year = ? LIMIT 1", [$org_id, $current_year]);
        if ($sequence_exists) {
            dbExecute(
                "UPDATE quote_sequences SET prefix = 'Q' WHERE org_id = ? AND year = ?",
                [$org_id, $current_year]
            );
        } else {
            dbExecute(
                "INSERT INTO quote_sequences (org_id, prefix, year, current_number) VALUES (?, 'Q', ?, 0)",
                [$org_id, $current_year]
            );
        }

        return [
            'success' => true,
            'message' => '設定更新成功'
        ];

    } catch (Exception $e) {
        error_log("Update settings error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '更新設定失敗'
        ];
    }
}

/**
 * 獲取公司資訊（用於列印）
 *
 * @return array 公司資訊
 */
function get_company_info() {
    $settings = get_settings();
    return [
        'name' => $settings['company_name'] ?? '',
        'address' => $settings['company_address'] ?? '',
        'contact' => $settings['company_contact'] ?? '',
        'tax_id' => $settings['company_tax_id'] ?? ''
    ];
}

/**
 * 獲取報價單編號字首
 *
 * @return string 字首
 */
function get_quote_prefix() {
    $settings = get_settings();
    return $settings['quote_prefix'] ?? 'Q';
}

/**
 * 獲取預設稅率
 *
 * @return float 預設稅率
 */
function get_default_tax_rate() {
    $settings = get_settings();
    return floatval($settings['default_tax_rate'] ?? 0);
}

/**
 * 獲取列印條款
 *
 * @return string 條款文字
 */
function get_print_terms() {
    $settings = get_settings();
    return $settings['print_terms'] ?? '';
}

/**
 * ========================================
 * 使用者管理 (User Management)
 * ========================================
 */

function get_user_by_username($username) {
    return dbQueryOne("SELECT * FROM users WHERE username = ? LIMIT 1", [$username]);
}

function get_user_by_id($userId) {
    return dbQueryOne("SELECT * FROM users WHERE id = ? LIMIT 1", [$userId]);
}

function update_user_login_activity($userId, $ip = null) {
    dbExecute(
        "UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?",
        [$ip, $userId]
    );
}

function update_user_password($userId, $newPassword) {
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    return dbExecute(
        "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?",
        [$hash, $userId]
    ) > 0;
}

function create_user($data) {
    $hash = password_hash($data['password'], PASSWORD_DEFAULT);
    return dbExecute(
        "INSERT INTO users (org_id, username, password_hash, email, role, status) VALUES (?, ?, ?, ?, ?, ?)",
        [
            $data['org_id'] ?? get_current_org_id(),
            $data['username'],
            $hash,
            $data['email'] ?? null,
            $data['role'] ?? 'staff',
            $data['status'] ?? 'active'
        ]
    );
}

function update_user_profile($userId, array $fields) {
    $set = [];
    $params = [];

    if (array_key_exists('username', $fields)) {
        $set[] = 'username = ?';
        $params[] = $fields['username'];
    }

    if (array_key_exists('email', $fields)) {
        $set[] = 'email = ?';
        $params[] = $fields['email'];
    }

    if (array_key_exists('role', $fields)) {
        $set[] = 'role = ?';
        $params[] = $fields['role'];
    }

    if (array_key_exists('status', $fields)) {
        $set[] = 'status = ?';
        $params[] = $fields['status'];
    }

    if (empty($set)) {
        return false;
    }

    $set[] = 'updated_at = NOW()';
    $params[] = $userId;

    return dbExecute(
        'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = ?',
        $params
    ) > 0;
}

function get_all_users() {
    return dbQuery("SELECT id, org_id, username, email, role, status, last_login_at, last_login_ip, created_at FROM users ORDER BY created_at ASC");
}

function set_user_status($userId, $status) {
    return update_user_profile($userId, ['status' => $status]);
}

/**
 * 判斷系統是否尚未初始化
 *
 * @return bool
 */
function requires_initial_setup() {
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    if (php_sapi_name() === 'cli') {
        $cached = false;
        return $cached;
    }

    if (defined('SKIP_INIT_REDIRECT') && SKIP_INIT_REDIRECT) {
        $cached = false;
        return $cached;
    }

    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($requestUri, PHP_URL_PATH) ?: '';

    if (strpos($path, 'init.php') !== false) {
        $cached = false;
        return $cached;
    }

    global $pdo;
    if (!$pdo instanceof PDO) {
        $cached = true;
        return $cached;
    }

    $requiredTables = ['organizations', 'settings', 'quote_sequences'];
    $requiredTables[] = 'users';

    try {
        $placeholders = implode(',', array_fill(0, count($requiredTables), '?'));
        $stmt = $pdo->prepare(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($placeholders)"
        );
        $stmt->execute($requiredTables);
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $missing = array_diff($requiredTables, $existing);
        if (!empty($missing)) {
            $cached = true;
            return $cached;
        }
    } catch (Throwable $e) {
        $cached = true;
        return $cached;
    }

    try {
        $orgCount = (int)$pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
        $settingsCount = (int)$pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
        $sequenceCount = (int)$pdo->query("SELECT COUNT(*) FROM quote_sequences")->fetchColumn();
        $userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

        $cached = ($orgCount === 0 || $settingsCount === 0 || $sequenceCount === 0 || $userCount === 0);
        return $cached;
    } catch (Throwable $e) {
        $cached = true;
        return $cached;
    }
}

/**
 * 若系統尚未初始化則導向初始化精靈
 *
 * @return void
 */
function redirect_to_init_if_needed() {
    if (php_sapi_name() === 'cli') {
        return;
    }

    if (defined('SKIP_INIT_REDIRECT') && SKIP_INIT_REDIRECT) {
        return;
    }

    if (headers_sent()) {
        return;
    }

    if (!requires_initial_setup()) {
        return;
    }

    header('Location: /init.php');
    exit;
}

// 檔案末尾不需要關閉PHP標籤，避免非預期輸出
