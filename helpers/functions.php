<?php
/**
 * Helper Functions Library
 * 工具函数库
 *
 * @version v2.0.0
 * @description 通用工具函数集合，包含安全、数据处理、格式化等函数
 * @遵循宪法原则I: 安全优先开发
 * @遵循宪法原则II: 精确财务数据处理
 */

// 防止直接访问
if (!defined('QUOTABASE_SYSTEM')) {
    define('QUOTABASE_SYSTEM', true);
}

/**
 * ========================================
 * 安全防护函数 (Security Functions)
 * 遵循宪法原则I: 安全优先开发
 * ========================================
 */

/**
 * HTML转义函数（防止XSS攻击）
 * 必须用于所有动态输出到HTML的内容
 *
 * @param string $string 待转义的字符串
 * @param string $encoding 字符编码，默认UTF-8
 * @return string 转义后的字符串
 */
function h($string, $encoding = 'UTF-8') {
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_SUBSTITUTE, $encoding);
}

/**
 * 生成CSRF令牌
 *
 * @return string 64位十六进制字符串
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
 * 验证CSRF令牌
 *
 * @param string $token 待验证的令牌
 * @return bool 验证结果
 */
function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
}

/**
 * 生成随机密码
 *
 * @param int $length 密码长度，默认12
 * @param bool $include_special 是否包含特殊字符，默认false
 * @return string 生成的密码
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
 * 验证密码强度
 *
 * @param string $password 密码
 * @return array ['valid' => bool, 'message' => string]
 */
function validate_password_strength($password) {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = '密码长度至少8位';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = '密码必须包含小写字母';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = '密码必须包含大写字母';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = '密码必须包含数字';
    }

    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = '密码必须包含特殊字符';
    }

    return [
        'valid' => empty($errors),
        'message' => implode('；', $errors)
    ];
}

/**
 * ========================================
 * 财务数据处理函数 (Financial Data Functions)
 * 遵循宪法原则II: 精确财务数据处理
 * ========================================
 */

/**
 * 格式化金额（分 -> 货币格式显示）
 *
 * @param int $cents 金额（单位：分）
 * @param string $currency 货币符号，默认NT$
 * @param int $decimals 小数位数，默认2
 * @return string 格式化后的金额字符串
 *
 * @example
 * format_currency_cents(1000) // "NT$ 1,000.00"
 * format_currency_cents(500) // "NT$ 500.00"
 */
function format_currency_cents($cents, $currency = 'NT$', $decimals = 2) {
    $amount = number_format($cents / 100, $decimals, '.', ',');
    return $currency . ' ' . $amount;
}

/**
 * 格式化单价（从分转换为元显示）
 *
 * @param int $cents 单价（单位：分）
 * @param string $unit 单位，默认"元"
 * @param int $decimals 小数位数，默认2
 * @return string 格式化后的单价
 *
 * @example
 * format_unit_price(1500, '元') // "15.00 元"
 */
function format_unit_price($cents, $unit = '元', $decimals = 2) {
    $price = number_format($cents / 100, $decimals, '.', ',');
    return $price . ' ' . $unit;
}

/**
 * 计算行小计（数量 × 单价）
 *
 * @param float $qty 数量（支持小数，最多4位）
 * @param int $unit_price_cents 单价（分）
 * @return int 小计（分）
 */
function calculate_line_subtotal($qty, $unit_price_cents) {
    return (int)round($qty * $unit_price_cents);
}

/**
 * 计算行税额（小计 × 税率）
 *
 * @param int $subtotal_cents 小计（分）
 * @param float $tax_rate 税率（百分比，如5表示5%）
 * @return int 税额（分）
 */
function calculate_line_tax($subtotal_cents, $tax_rate) {
    return (int)round($subtotal_cents * ($tax_rate / 100));
}

/**
 * 计算行总计（小计 + 税额）
 *
 * @param int $subtotal_cents 小计（分）
 * @param int $tax_cents 税额（分）
 * @return int 总计（分）
 */
function calculate_line_total($subtotal_cents, $tax_cents) {
    return $subtotal_cents + $tax_cents;
}

/**
 * 计算报价单总计
 *
 * @param array $items 报价项目数组，每个元素包含line_subtotal_cents和line_tax_cents
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
 * 将金额转换为分（用于存储）
 *
 * @param string|float $amount 金额字符串或数字
 * @return int 金额（分）
 */
function amount_to_cents($amount) {
    // 移除货币符号和千位分隔符
    $amount = preg_replace('/[^\d.-]/', '', $amount);
    // 转换为分
    return (int)round(floatval($amount) * 100);
}

/**
 * ========================================
 * 数据验证函数 (Validation Functions)
 * ========================================
 */

/**
 * 验证邮箱格式
 *
 * @param string $email 邮箱地址
 * @return bool 验证结果
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * 验证税务登记号格式（台湾统一编号：8位数字）
 *
 * @param string $tax_id 税务登记号
 * @return bool 验证结果
 */
function is_valid_tax_id($tax_id) {
    return preg_match('/^\d{8}$/', $tax_id) === 1;
}

/**
 * 验证SKU格式
 *
 * @param string $sku SKU编码
 * @return bool 验证结果
 */
function is_valid_sku($sku) {
    return preg_match('/^[A-Za-z0-9_-]{1,100}$/', $sku) === 1;
}

/**
 * 取得 SKU 前綴
 *
 * @param string $type catalog type
 * @return string 前綴
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
 * 產生唯一 SKU（依類型 + 日期 + 流水號）
 *
 * @param string $type 類型（product/service）
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
 * 验证URL格式
 *
 * @param string $url URL地址
 * @return bool 验证结果
 */
function is_valid_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * 清理并验证字符串长度
 *
 * @param string $str 字符串
 * @param int $max_length 最大长度
 * @param int $min_length 最小长度，默认0
 * @return bool 验证结果
 */
function validate_string_length($str, $max_length, $min_length = 0) {
    $length = mb_strlen($str, 'UTF-8');
    return $length >= $min_length && $length <= $max_length;
}

/**
 * ========================================
 * 日期时间处理函数 (Date/Time Functions)
 * 遵循宪法原则II: UTC存储、Asia/Taipei显示
 * ========================================
 */

/**
 * 获取当前日期（UTC）
 *
 * @param string $format 日期格式，默认Y-m-d
 * @return string 日期字符串
 */
function get_current_date_utc($format = 'Y-m-d') {
    return gmdate($format);
}

/**
 * 获取当前日期时间（UTC）
 *
 * @param string $format 日期时间格式，默认Y-m-d H:i:s
 * @return string 日期时间字符串
 */
function get_current_datetime_utc($format = 'Y-m-d H:i:s') {
    return gmdate($format);
}

/**
 * 格式化日期（显示时区）
 *
 * @param string $date 日期字符串或日期时间
 * @param string $format 输出格式，默认Y-m-d
 * @return string 格式化后的日期
 */
function format_date($date, $format = 'Y-m-d') {
    $display_tz = defined('DISPLAY_TIMEZONE') ? DISPLAY_TIMEZONE : 'Asia/Taipei';

    try {
        $dt = new DateTime($date, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone($display_tz));
        return $dt->format($format);
    } catch (Exception $e) {
        return $date; // 如果解析失败，返回原始值
    }
}

/**
 * 格式化日期时间（显示时区）
 *
 * @param string $datetime 日期时间字符串
 * @param string $format 输出格式，默认Y-m-d H:i
 * @return string 格式化后的日期时间
 */
function format_datetime($datetime, $format = 'Y-m-d H:i') {
    return format_date($datetime, $format);
}

/**
 * 将日期时间格式化为 ISO8601 UTC 字符串
 *
 * @param string|null $datetime 原始日期时间
 * @return string|null ISO8601 字符串或 null
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
 * 计算日期差
 *
 * @param string $date1 日期1
 * @param string $date2 日期2
 * @return int 天数差
 */
function date_diff_days($date1, $date2) {
    $dt1 = new DateTime($date1, new DateTimeZone('UTC'));
    $dt2 = new DateTime($date2, new DateTimeZone('UTC'));
    $diff = $dt1->diff($dt2);
    return $diff->days;
}

/**
 * ========================================
 * 业务逻辑函数 (Business Logic Functions)
 * ========================================
 */

/**
 * 生成分页HTML
 *
 * @param int $current_page 当前页码
 * @param int $total_pages 总页数
 * @param string $base_url 基础URL
 * @return string HTML字符串
 */
function generate_pagination($current_page, $total_pages, $base_url) {
    if ($total_pages <= 1) {
        return '';
    }

    $html = '<nav class="pagination"><ul>';

    // 上一页
    if ($current_page > 1) {
        $html .= '<li><a href="' . h($base_url) . '?page=' . ($current_page - 1) . '">上一页</a></li>';
    }

    // 页码
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $current_page) ? ' class="active"' : '';
        $html .= '<li' . $active . '><a href="' . h($base_url) . '?page=' . $i . '">' . $i . '</a></li>';
    }

    // 下一页
    if ($current_page < $total_pages) {
        $html .= '<li><a href="' . h($base_url) . '?page=' . ($current_page + 1) . '">下一页</a></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

/**
 * 获取状态标签HTML
 *
 * @param string $status 状态值
 * @return string HTML字符串
 */
function get_status_badge($status) {
    $status_map = [
        'draft' => ['label' => '草稿', 'class' => 'badge-secondary'],
        'sent' => ['label' => '已发送', 'class' => 'badge-info'],
        'accepted' => ['label' => '已接受', 'class' => 'badge-success'],
        'rejected' => ['label' => '已拒绝', 'class' => 'badge-danger'],
        'expired' => ['label' => '已过期', 'class' => 'badge-warning']
    ];

    $info = $status_map[$status] ?? ['label' => $status, 'class' => 'badge-secondary'];

    return '<span class="badge ' . h($info['class']) . '">' . h($info['label']) . '</span>';
}

/**
 * 获取状态中文标签
 *
 * @param string $status 状态值
 * @return string 中文标签
 */
function get_status_label($status) {
    $status_map = [
        'draft' => '草稿',
        'sent' => '已发送',
        'accepted' => '已接受',
        'rejected' => '已拒绝',
        'expired' => '已过期'
    ];

    return $status_map[$status] ?? $status;
}

/**
 * ========================================
 * 字符串处理函数 (String Functions)
 * ========================================
 */

/**
 * 截断字符串（支持中文）
 *
 * @param string $str 原字符串
 * @param int $length 截断长度
 * @param string $suffix 后缀，默认"..."
 * @return string 截断后的字符串
 */
function truncate_string($str, $length, $suffix = '...') {
    $str = trim($str);
    if (mb_strlen($str, 'UTF-8') <= $length) {
        return $str;
    }
    return mb_substr($str, 0, $length, 'UTF-8') . $suffix;
}

/**
 * 生成随机字符串
 *
 * @param int $length 长度
 * @param string $charset 字符集，默认alphanumeric
 * @return string 随机字符串
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
 * 清理字符串（移除特殊字符）
 *
 * @param string $str 原始字符串
 * @param bool $allow_spaces 是否允许空格，默认true
 * @param bool $allow_dashes 是否允许短横线和下划线，默认true
 * @return string 清理后的字符串
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
 * 数组处理函数 (Array Functions)
 * ========================================
 */

/**
 * 过滤数组（移除空值）
 *
 * @param array $array 原始数组
 * @param bool $remove_zero 是否移除0值，默认false
 * @return array 过滤后的数组
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
 * 从数组中提取指定键的值
 *
 * @param array $array 原始数组
 * @param string|array $keys 要提取的键
 * @param mixed $default 默认值
 * @return array 提取的结果
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
 * 调试辅助函数 (Debug Functions)
 * ========================================
 */

/**
 * 调试输出（仅开发环境）
 *
 * @param mixed $data 要输出的数据
 * @param bool $die 是否终止程序，默认false
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
 * 记录调试日志
 *
 * @param string $message 日志消息
 * @param mixed $data 附加数据
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
 * 页面辅助函数 (Page Helper Functions)
 * ========================================
 */

/**
 * 生成CSRF令牌输入框HTML
 *
 * @return string HTML字符串
 */
function csrf_input() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . h($token) . '">';
}

/**
 * 检查当前页面是否为打印页
 *
 * @return bool
 */
function is_print_page() {
    return strpos($_SERVER['REQUEST_URI'], '/quotes/print.php') !== false;
}

/**
 * 获取当前页面名称
 *
 * @return string 页面名称
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
 * 文件操作函数 (File Functions)
 * ========================================
 */

/**
 * 安全删除文件
 *
 * @param string $file_path 文件路径
 * @return bool 删除结果
 */
function safe_delete_file($file_path) {
    // 检查文件路径是否在允许的目录内
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
 * 生成安全的文件名
 *
 * @param string $original_name 原始文件名
 * @param string $prefix 前缀，默认''
 * @return string 安全文件名
 */
function generate_safe_filename($original_name, $prefix = '') {
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $basename = pathinfo($original_name, PATHINFO_FILENAME);

    // 清理文件名
    $basename = clean_string($basename, true, false);
    $basename = substr($basename, 0, 50);

    // 生成时间戳
    $timestamp = time();

    return $prefix . $timestamp . '_' . $basename . '.' . $extension;
}

/**
 * ========================================
 * 常用常量 (Common Constants)
 * ========================================
 */

// 税率选项
define('TAX_RATES', [
    0.00 => '0%',
    5.00 => '5%',
    8.00 => '8%',
    10.00 => '10%'
]);

// 单位选项
define('UNITS', [
    'pcs' => '次',
    'hour' => '小时',
    'day' => '天',
    'month' => '月',
    'year' => '年'
]);

// 货币选项（预留多货币支持）
define('CURRENCIES', [
    'TWD' => '新台币'
]);

/**
 * ========================================
 * Customer 模型操作函数 (Customer Model Functions)
 * US2: 客户管理
 * ========================================
 */

/**
 * 获取客户列表（分页）
 *
 * @param int $page 页码，默认1
 * @param int $limit 每页数量，默认20
 * @param string $search 搜索关键词
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

    // 获取总数
    $count_sql = "SELECT COUNT(*) as total FROM customers WHERE {$where_clause}";
    $total = dbQueryOne($count_sql, $params)['total'];
    $total_pages = ceil($total / $limit);

    // 获取数据
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
 * 获取单个客户信息
 *
 * @param int $id 客户ID
 * @return array|null 客户信息或null
 */
function get_customer($id) {
    $org_id = get_current_org_id();
    $sql = "SELECT * FROM customers WHERE id = ? AND org_id = ? AND active = 1";
    return dbQueryOne($sql, [$id, $org_id]);
}

/**
 * 创建新客户
 *
 * @param array $data 客户数据
 * @return array ['success' => bool, 'id' => int, 'error' => string]
 */
function create_customer($data) {
    try {
        // 验证输入
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = '客户名称不能为空';
        } elseif (!validate_string_length($data['name'], 255, 1)) {
            $errors[] = '客户名称长度不正确';
        }

        if (!empty($data['tax_id']) && !is_valid_tax_id($data['tax_id'])) {
            $errors[] = '税务登记号格式不正确（应为8位数字）';
        }

        if (!empty($data['email']) && !is_valid_email($data['email'])) {
            $errors[] = '邮箱格式不正确';
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
            'message' => '客户创建成功'
        ];

    } catch (Exception $e) {
        error_log("Create customer error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '创建客户失败，请稍后再试'
        ];
    }
}

/**
 * 更新客户信息
 *
 * @param int $id 客户ID
 * @param array $data 客户数据
 * @return array ['success' => bool, 'error' => string]
 */
function update_customer($id, $data) {
    try {
        // 验证输入
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = '客户名称不能为空';
        } elseif (!validate_string_length($data['name'], 255, 1)) {
            $errors[] = '客户名称长度不正确';
        }

        if (!empty($data['tax_id']) && !is_valid_tax_id($data['tax_id'])) {
            $errors[] = '税务登记号格式不正确（应为8位数字）';
        }

        if (!empty($data['email']) && !is_valid_email($data['email'])) {
            $errors[] = '邮箱格式不正确';
        }

        if (!empty($errors)) {
            return ['success' => false, 'error' => implode('；', $errors)];
        }

        $org_id = get_current_org_id();

        // 检查客户是否存在
        $exists = get_customer($id);
        if (!$exists) {
            return ['success' => false, 'error' => '客户不存在'];
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
            'message' => '客户信息更新成功'
        ];

    } catch (Exception $e) {
        error_log("Update customer error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '更新客户失败，请稍后再试'
        ];
    }
}

/**
 * 删除客户（软删除）
 *
 * @param int $id 客户ID
 * @return array ['success' => bool, 'error' => string]
 */
function delete_customer($id) {
    try {
        $org_id = get_current_org_id();

        // 检查客户是否存在
        $customer = get_customer($id);
        if (!$customer) {
            return ['success' => false, 'error' => '客户不存在'];
        }

        // 检查是否有关联的报价单
        $has_quotes = dbQueryOne(
            "SELECT COUNT(*) as count FROM quotes WHERE customer_id = ? AND org_id = ?",
            [$id, $org_id]
        );

        if ($has_quotes['count'] > 0) {
            return [
                'success' => false,
                'error' => '该客户存在关联的报价单，无法删除'
            ];
        }

        // 软删除（设置为不激活）
        dbExecute(
            "UPDATE customers SET active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND org_id = ?",
            [$id, $org_id]
        );

        return [
            'success' => true,
            'message' => '客户删除成功'
        ];

    } catch (Exception $e) {
        error_log("Delete customer error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '删除客户失败，请稍后再试'
        ];
    }
}

/**
 * 恢复客户（激活）
 *
 * @param int $id 客户ID
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
            'message' => '客户恢复成功'
        ];

    } catch (Exception $e) {
        error_log("Restore customer error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '恢复客户失败，请稍后再试'
        ];
    }
}

/**
 * 获取客户列表（用于下拉选择）
 *
 * @return array 客户列表
 */
function get_customer_list() {
    $org_id = get_current_org_id();
    $sql = "SELECT id, name FROM customers WHERE org_id = ? AND active = 1 ORDER BY name ASC";
    return dbQuery($sql, [$org_id]);
}

/**
 * ========================================
 * CatalogItem 模型操作函数 (CatalogItem Model Functions)
 * US3: 产品/服务目录管理
 * ========================================
 */

/**
 * 获取产品/服务列表（分页）
 *
 * @param string $type 类型 (product|service)，为空时获取全部
 * @param int $page 页码，默认1
 * @param int $limit 每页数量，默认20
 * @param string $search 搜索关键词
 * @param int|null $category_id 分类ID
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

    // 获取总数
    $count_sql = "SELECT COUNT(*) as total FROM catalog_items WHERE {$where_clause}";
    $total = dbQueryOne($count_sql, $params)['total'];
    $total_pages = ceil($total / $limit);

    // 获取数据
    $sql = "
        SELECT id, type, sku, name, unit, currency, unit_price_cents, tax_rate, category_id, created_at
        FROM catalog_items
        WHERE {$where_clause}
        ORDER BY type ASC, name ASC, id DESC
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
 * Catalog Category 操作函数 (Catalog Category Model Functions)
 * ========================================
 */

/**
 * 获取单一分类
 *
 * @param int $id 分类ID
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
 * 根据父级获取分类列表
 *
 * @param string $type 分类类型
 * @param int|null $parent_id 父分类ID
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
 * 获取分类树
 *
 * @param string $type 分类类型
 * @param int|null $parent_id 父分类
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
 * 扁平化分类列表（带层级）
 *
 * @param string $type 分类类型
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
 * 获取分类路径（字符串）
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
 * 获取分类路径ID列表
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
 * 批量获取分类路径
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
 * 获取分类字典（id => [..]）
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
 * 获取分类的所有子孙ID
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
 * 创建分类
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
            $errors[] = '分类类型无效';
        }

        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            $errors[] = '分类名称不能为空';
        } elseif (!validate_string_length($name, 100, 1)) {
            $errors[] = '分类名称长度需在1-100字符之间';
        }

        $sort_order = isset($data['sort_order']) ? intval($data['sort_order']) : 0;
        $parent_id = isset($data['parent_id']) && $data['parent_id'] !== '' ? intval($data['parent_id']) : null;

        $level = 1;
        if ($parent_id) {
            $parent = get_catalog_category($parent_id);
            if (!$parent) {
                $errors[] = '父级分类不存在';
            } elseif ($parent['type'] !== $type) {
                $errors[] = '父级分类类型不一致';
            } else {
                $level = (int)$parent['level'] + 1;
                if ($level > 3) {
                    $errors[] = '分类最多支持三级结构';
                }
            }
        }

        if (empty($errors)) {
            // 检查重复名称
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
                $errors[] = '相同层级下已存在同名分类';
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
            'message' => '分类创建成功'
        ];

    } catch (Exception $e) {
        error_log("Create catalog category error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '创建分类失败，请稍后再试'
        ];
    }
}

/**
 * 更新分类
 *
 * @param int $id
 * @param array $data
 * @return array
 */
function update_catalog_category($id, $data) {
    try {
        $category = get_catalog_category($id);
        if (!$category) {
            return ['success' => false, 'error' => '分类不存在'];
        }

        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            return ['success' => false, 'error' => '分类名称不能为空'];
        }
        if (!validate_string_length($name, 100, 1)) {
            return ['success' => false, 'error' => '分类名称长度需在1-100字符之间'];
        }

        $sort_order = isset($data['sort_order']) ? intval($data['sort_order']) : (int)$category['sort_order'];

        // 检查同层级重复名称
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
            return ['success' => false, 'error' => '相同层级下已存在同名分类'];
        }

        dbExecute(
            "UPDATE catalog_categories SET name = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND org_id = ?",
            [$name, $sort_order, $id, $category['org_id']]
        );

        return [
            'success' => true,
            'message' => '分类更新成功'
        ];

    } catch (Exception $e) {
        error_log("Update catalog category error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '更新分类失败，请稍后再试'
        ];
    }
}

/**
 * 删除分类
 *
 * @param int $id
 * @return array
 */
function delete_catalog_category($id) {
    try {
        $category = get_catalog_category($id);
        if (!$category) {
            return ['success' => false, 'error' => '分类不存在'];
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
            'message' => '分类已删除'
        ];

    } catch (Exception $e) {
        error_log("Delete catalog category error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '删除分类失败，请稍后再试'
        ];
    }
}

/**
 * 获取单个产品/服务信息
 *
 * @param int $id 产品/服务ID
 * @return array|null 产品/服务信息或null
 */
function get_catalog_item($id) {
    $org_id = get_current_org_id();
    $sql = "SELECT * FROM catalog_items WHERE id = ? AND org_id = ? AND active = 1";
    return dbQueryOne($sql, [$id, $org_id]);
}

/**
 * 创建新产品/服务
 *
 * @param array $data 产品/服务数据
 * @return array ['success' => bool, 'id' => int, 'error' => string]
 */
function create_catalog_item($data) {
    try {
        // 验证输入
        $errors = [];
        $data['type'] = $data['type'] ?? 'product';
        if (!in_array($data['type'], ['product', 'service'])) {
            $errors[] = '类型必须为 product 或 service';
        }

        $auto_generated_sku = false;
        if (empty($data['sku']) && empty($errors)) {
            $data['sku'] = generate_catalog_item_sku($data['type']);
            $auto_generated_sku = true;
        }

        $data['sku'] = trim($data['sku'] ?? '');

        if (empty($data['sku'])) {
            $errors[] = 'SKU不能为空';
        } elseif (!is_valid_sku($data['sku'])) {
            $errors[] = 'SKU格式不正确（只允许字母、数字、-和_）';
        }

        if (empty($data['name'])) {
            $errors[] = '名称不能为空';
        } elseif (!validate_string_length($data['name'], 255, 1)) {
            $errors[] = '名称长度不正确';
        }

        if (!isset($data['unit_price_cents']) || $data['unit_price_cents'] < 0) {
            $errors[] = '单价必须为非负整数';
        }

        if (!empty($data['tax_rate']) && ($data['tax_rate'] < 0 || $data['tax_rate'] > 100)) {
            $errors[] = '税率必须在 0-100 之间';
        }

        $category_id = isset($data['category_id']) && intval($data['category_id']) > 0 ? intval($data['category_id']) : null;

        if ($category_id) {
            $category = get_catalog_category($category_id);
            if (!$category) {
                $errors[] = '选择的分类不存在';
            } elseif ($category['type'] !== $data['type']) {
                $errors[] = '分类类型与产品类型不一致';
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
                ? '系统尝试自动生成 SKU 但仍遇到冲突，请稍后再试。'
                : 'SKU已存在，请使用其他SKU';
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
            'message' => '产品/服务创建成功'
        ];

    } catch (Exception $e) {
        error_log("Create catalog item error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '创建产品/服务失败，请稍后再试'
        ];
    }
}

/**
 * 更新产品/服务信息
 *
 * @param int $id 产品/服务ID
 * @param array $data 产品/服务数据
 * @return array ['success' => bool, 'error' => string]
 */
function update_catalog_item($id, $data) {
    try {
        // 验证输入
        $errors = [];

        if (empty($data['sku'])) {
            $errors[] = 'SKU不能为空';
        } elseif (!is_valid_sku($data['sku'])) {
            $errors[] = 'SKU格式不正确（只允许字母、数字、-和_）';
        }

        if (empty($data['name'])) {
            $errors[] = '名称不能为空';
        } elseif (!validate_string_length($data['name'], 255, 1)) {
            $errors[] = '名称长度不正确';
        }

        if (!isset($data['unit_price_cents']) || $data['unit_price_cents'] < 0) {
            $errors[] = '单价必须为非负整数';
        }

        if (!empty($data['tax_rate']) && ($data['tax_rate'] < 0 || $data['tax_rate'] > 100)) {
            $errors[] = '税率必须在 0-100 之间';
        }

        $category_id = isset($data['category_id']) && intval($data['category_id']) > 0 ? intval($data['category_id']) : null;

        if (!empty($errors)) {
            return ['success' => false, 'error' => implode('；', $errors)];
        }

        $org_id = get_current_org_id();

        // 检查产品/服务是否存在
        $exists = get_catalog_item($id);
        if (!$exists) {
            return ['success' => false, 'error' => '产品/服务不存在'];
        }

        // 检查SKU唯一性（排除当前记录）
        $sku_exists = dbQueryOne(
            "SELECT id FROM catalog_items WHERE org_id = ? AND sku = ? AND id != ?",
            [$org_id, $data['sku'], $id]
        );

        if ($sku_exists) {
            return ['success' => false, 'error' => 'SKU已存在，请使用其他SKU'];
        }

        if ($category_id) {
            $category = get_catalog_category($category_id);
            if (!$category) {
                return ['success' => false, 'error' => '选择的分类不存在'];
            }
            if ($category['type'] !== $exists['type']) {
                return ['success' => false, 'error' => '分类类型与产品类型不一致'];
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
            'message' => '产品/服务信息更新成功'
        ];

    } catch (Exception $e) {
        error_log("Update catalog item error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '更新产品/服务失败，请稍后再试'
        ];
    }
}

/**
 * 删除产品/服务（软删除）
 *
 * @param int $id 产品/服务ID
 * @return array ['success' => bool, 'error' => string]
 */
function delete_catalog_item($id) {
    try {
        $org_id = get_current_org_id();

        // 检查产品/服务是否存在
        $catalog_item = get_catalog_item($id);
        if (!$catalog_item) {
            return ['success' => false, 'error' => '产品/服务不存在'];
        }

        // 检查是否有关联的报价单项目
        $has_quote_items = dbQueryOne(
            "SELECT COUNT(*) as count FROM quote_items WHERE catalog_item_id = ?",
            [$id]
        );

        if ($has_quote_items['count'] > 0) {
            return [
                'success' => false,
                'error' => '该产品/服务存在关联的报价单项目，无法删除'
            ];
        }

        // 软删除（设置为不激活）
        dbExecute(
            "UPDATE catalog_items SET active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND org_id = ?",
            [$id, $org_id]
        );

        return [
            'success' => true,
            'message' => '产品/服务删除成功'
        ];

    } catch (Exception $e) {
        error_log("Delete catalog item error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '删除产品/服务失败，请稍后再试'
        ];
    }
}

/**
 * 恢复产品/服务（激活）
 *
 * @param int $id 产品/服务ID
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
            'message' => '产品/服务恢复成功'
        ];

    } catch (Exception $e) {
        error_log("Restore catalog item error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '恢复产品/服务失败，请稍后再试'
        ];
    }
}

/**
 * 获取产品/服务列表（用于下拉选择）
 *
 * @param string $type 类型 (product|service)，为空时获取全部
 * @return array 产品/服务列表
 */
function get_catalog_item_list($type = '') {
    $org_id = get_current_org_id();
    $where_clause = "org_id = ? AND active = 1";
    $params = [$org_id];

    if (!empty($type) && in_array($type, ['product', 'service'])) {
        $where_clause .= " AND type = ?";
        $params[] = $type;
    }

    $sql = "SELECT id, type, sku, name, unit_price_cents FROM catalog_items WHERE {$where_clause} ORDER BY type ASC, name ASC";
    return dbQuery($sql, $params);
}

/**
 * 检查SKU是否唯一
 *
 * @param string $sku SKU编码
 * @param int $exclude_id 排除的ID（用于编辑时）
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
 * 根据类型获取产品或服务统计
 *
 * @return array 统计信息
 */
function get_catalog_stats() {
    $org_id = get_current_org_id();

    $stats = [];

    // 产品数量
    $product_count = dbQueryOne(
        "SELECT COUNT(*) as count FROM catalog_items WHERE org_id = ? AND type = 'product' AND active = 1",
        [$org_id]
    );
    $stats['products'] = $product_count['count'];

    // 服务数量
    $service_count = dbQueryOne(
        "SELECT COUNT(*) as count FROM catalog_items WHERE org_id = ? AND type = 'service' AND active = 1",
        [$org_id]
    );
    $stats['services'] = $service_count['count'];

    // 总数
    $stats['total'] = $stats['products'] + $stats['services'];

    return $stats;
}

/**
 * ========================================
 * Quote 模型操作函数 (Quote Model Functions)
 * US4: 报价单创建与管理
 * ========================================
 */

/**
 * 获取报价单列表（分页）
 *
 * @param int $page 页码，默认1
 * @param int $limit 每页数量，默认20
 * @param string $search 搜索关键词
 * @param string $status 状态筛选
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

    // 获取总数
    $count_sql = "
        SELECT COUNT(*) as total
        FROM quotes q
        LEFT JOIN customers customer ON q.customer_id = customer.id
        WHERE {$where_clause}
    ";
    $total = dbQueryOne($count_sql, $params)['total'];
    $total_pages = ceil($total / $limit);

    // 获取数据
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
 * 获取单个报价单信息（不包含明细）
 *
 * @param int $id 报价单ID
 * @return array|null 报价单信息或null
 */
function get_quote($id) {
    $org_id = get_current_org_id();
    $sql = "
        SELECT q.*, customer.name as customer_name, customer.tax_id, customer.email, customer.phone
        FROM quotes q
        LEFT JOIN customers customer ON q.customer_id = customer.id
        WHERE q.id = ? AND q.org_id = ?
    ";
    $quote = dbQueryOne($sql, [$id, $org_id]);

    if ($quote) {
        // 获取报价单明细
        $quote['items'] = get_quote_items($id);
    }

    return $quote;
}

/**
 * 获取报价单明细
 *
 * @param int $quote_id 报价单ID
 * @return array 明细列表
 */
function get_quote_items($quote_id) {
    $sql = "
        SELECT
            qi.id, qi.quantity, qi.unit_price_cents,
            qi.line_subtotal_cents, qi.tax_rate, qi.line_tax_cents, qi.line_total_cents,
            catalog.sku, catalog.name as item_name, catalog.unit
        FROM quote_items qi
        LEFT JOIN catalog_items catalog ON qi.catalog_item_id = catalog.id
        WHERE qi.quote_id = ?
        ORDER BY qi.id ASC
    ";
    return dbQuery($sql, [$quote_id]);
}

/**
 * 创建新报价单（使用事务）
 *
 * @param array $data 报价单数据
 * @param array $items 报价项目数组
 * @return array ['success' => bool, 'id' => int, 'error' => string]
 */
function create_quote($data, $items) {
    try {
        // 验证输入
        $errors = [];

        if (empty($data['customer_id'])) {
            $errors[] = '请选择客户';
        }

        if (empty($items) || !is_array($items)) {
            $errors[] = '请添加报价项目';
        }

        if (!empty($errors)) {
            return ['success' => false, 'error' => implode('；', $errors)];
        }

        // 开始事务
        $pdo = getDB()->getConnection();
        $pdo->beginTransaction();

        try {
            // 1. 生成报价单编号
            $quote_number = generate_quote_number($pdo);
            if (!$quote_number['success']) {
                throw new Exception($quote_number['error']);
            }

            $org_id = get_current_org_id();
            $quote_number_str = $quote_number['quote_number'];

            // 2. 插入报价单主记录
            $sql = "
                INSERT INTO quotes (
                    org_id, customer_id, quote_number, status,
                    issue_date, valid_until, note,
                    subtotal_cents, tax_cents, total_cents
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $params = [
                $org_id,
                $data['customer_id'],
                $quote_number_str,
                $data['status'] ?? 'draft',
                $data['issue_date'] ?? get_current_date_utc(),
                $data['valid_until'] ?? null,
                $data['note'] ?? null,
                0, // 临时值，稍后更新
                0, // 临时值，稍后更新
                0  // 临时值，稍后更新
            ];

            dbExecute($sql, $params);
            $quote_id = dbLastInsertId();

            // 3. 插入报价单明细并计算总额
            $total_subtotal = 0;
            $total_tax = 0;
            $total_amount = 0;

            foreach ($items as $item) {
                if (empty($item['catalog_item_id']) || empty($item['quantity'])) {
                    continue;
                }

                // 获取目录项信息
                $catalog_item = get_catalog_item($item['catalog_item_id']);
                if (!$catalog_item) {
                    throw new Exception('目录项不存在');
                }

                // 计算行金额
                $quantity = floatval($item['quantity']);
                $unit_price_cents = intval($catalog_item['unit_price_cents']);
                $tax_rate = $catalog_item['tax_rate'] !== null && $catalog_item['tax_rate'] !== ''
                    ? floatval($catalog_item['tax_rate'])
                    : get_default_tax_rate();

                $line_subtotal_cents = calculate_line_subtotal($quantity, $unit_price_cents);
                $line_tax_cents = calculate_line_tax($line_subtotal_cents, $tax_rate);
                $line_total_cents = calculate_line_total($line_subtotal_cents, $line_tax_cents);

                // 插入明细
                $item_sql = "
                    INSERT INTO quote_items (
                        quote_id, catalog_item_id, quantity, unit_price_cents,
                        tax_rate, line_subtotal_cents, line_tax_cents, line_total_cents
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ";

                dbExecute($item_sql, [
                    $quote_id,
                    $item['catalog_item_id'],
                    $quantity,
                    $unit_price_cents,
                    $tax_rate,
                    $line_subtotal_cents,
                    $line_tax_cents,
                    $line_total_cents
                ]);

                $total_subtotal += $line_subtotal_cents;
                $total_tax += $line_tax_cents;
                $total_amount += $line_total_cents;
            }

            // 4. 更新报价单总额
            $update_sql = "
                UPDATE quotes
                SET subtotal_cents = ?, tax_cents = ?, total_cents = ?
                WHERE id = ?
            ";
            dbExecute($update_sql, [$total_subtotal, $total_tax, $total_amount, $quote_id]);

            // 提交事务
            $pdo->commit();

            return [
                'success' => true,
                'id' => $quote_id,
                'quote_number' => $quote_number_str,
                'message' => '报价单创建成功'
            ];

        } catch (Exception $e) {
            // 回滚事务
            $pdo->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        error_log("Create quote error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '创建报价单失败：' . $e->getMessage()
        ];
    }
}

/**
 * 生成报价单编号（使用存储过程）
 *
 * @param PDO $pdo 数据库连接
 * @return array ['success' => bool, 'quote_number' => string, 'error' => string]
 */
function generate_quote_number($pdo = null) {
    try {
        $org_id = get_current_org_id();

        if ($pdo === null) {
            $pdo = getDB()->getConnection();
        }

        // 调用存储过程并获取输出参数
        $stmt = $pdo->prepare("CALL next_quote_number(?, @out_quote_number)");
        $stmt->execute([$org_id]);
        $stmt->closeCursor();

        $result_stmt = $pdo->query("SELECT @out_quote_number AS quote_number");
        $result = $result_stmt ? $result_stmt->fetch(PDO::FETCH_ASSOC) : null;

        if (!$result || empty($result['quote_number'])) {
            return ['success' => false, 'error' => '生成报价单编号失败'];
        }

        return [
            'success' => true,
            'quote_number' => $result['quote_number']
        ];

    } catch (Exception $e) {
        error_log("Generate quote number error: " . $e->getMessage());
        return ['success' => false, 'error' => '生成报价单编号失败'];
    }
}

/**
 * 更新报价单状态
 *
 * @param int $id 报价单ID
 * @param string $status 新状态
 * @return array ['success' => bool, 'error' => string]
 */
function update_quote_status($id, $status) {
    try {
        $org_id = get_current_org_id();
        $valid_statuses = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

        if (!in_array($status, $valid_statuses)) {
            return ['success' => false, 'error' => '无效的状态值'];
        }

        // 检查报价单是否存在
        $quote = get_quote($id);
        if (!$quote) {
            return ['success' => false, 'error' => '报价单不存在'];
        }

        // 更新状态
        $sql = "
            UPDATE quotes
            SET status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND org_id = ?
        ";
        dbExecute($sql, [$status, $id, $org_id]);

        return [
            'success' => true,
            'message' => '状态更新成功'
        ];

    } catch (Exception $e) {
        error_log("Update quote status error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '更新状态失败'
        ];
    }
}

/**
 * 删除报价单（软删除）
 *
 * @param int $id 报价单ID
 * @return array ['success' => bool, 'error' => string]
 */
function delete_quote($id) {
    try {
        $org_id = get_current_org_id();

        // 检查报价单是否存在
        $quote = get_quote($id);
        if (!$quote) {
            return ['success' => false, 'error' => '报价单不存在'];
        }

        // 软删除
        $sql = "
            UPDATE quotes
            SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND org_id = ?
        ";
        dbExecute($sql, [$id, $org_id]);

        return [
            'success' => true,
            'message' => '报价单删除成功'
        ];

    } catch (Exception $e) {
        error_log("Delete quote error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '删除报价单失败'
        ];
    }
}

/**
 * 获取报价单列表（用于下拉选择）
 *
 * @return array 报价单列表
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
 * QuoteItem 模型操作函数 (QuoteItem Model Functions)
 * ========================================
 */

/**
 * 更新报价项目
 *
 * @param int $id 报价项目ID
 * @param array $data 项目数据
 * @return array ['success' => bool, 'error' => string]
 */
function update_quote_item($id, $data) {
    try {
        // 获取当前项目信息
        $current_item = dbQueryOne("SELECT * FROM quote_items WHERE id = ?", [$id]);
        if (!$current_item) {
            return ['success' => false, 'error' => '项目不存在'];
        }

        // 重新计算金额
        $quantity = floatval($data['quantity']);
        $unit_price_cents = intval($data['unit_price_cents']);
        $tax_rate = floatval($data['tax_rate']);

        $line_subtotal_cents = calculate_line_subtotal($quantity, $unit_price_cents);
        $line_tax_cents = calculate_line_tax($line_subtotal_cents, $tax_rate);
        $line_total_cents = calculate_line_total($line_subtotal_cents, $line_tax_cents);

        // 更新项目
        $sql = "
            UPDATE quote_items
            SET quantity = ?, unit_price_cents = ?, tax_rate = ?,
                line_subtotal_cents = ?, line_tax_cents = ?, line_total_cents = ?
            WHERE id = ?
        ";
        dbExecute($sql, [
            $quantity,
            $unit_price_cents,
            $tax_rate,
            $line_subtotal_cents,
            $line_tax_cents,
            $line_total_cents,
            $id
        ]);

        // 重新计算报价单总额
        recalculate_quote_total($current_item['quote_id']);

        return [
            'success' => true,
            'message' => '项目更新成功'
        ];

    } catch (Exception $e) {
        error_log("Update quote item error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '更新项目失败'
        ];
    }
}

/**
 * 删除报价项目
 *
 * @param int $id 报价项目ID
 * @return array ['success' => bool, 'error' => string]
 */
function delete_quote_item($id) {
    try {
        // 获取项目信息
        $item = dbQueryOne("SELECT quote_id FROM quote_items WHERE id = ?", [$id]);
        if (!$item) {
            return ['success' => false, 'error' => '项目不存在'];
        }

        // 删除项目
        dbExecute("DELETE FROM quote_items WHERE id = ?", [$id]);

        // 重新计算报价单总额
        recalculate_quote_total($item['quote_id']);

        return [
            'success' => true,
            'message' => '项目删除成功'
        ];

    } catch (Exception $e) {
        error_log("Delete quote item error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '删除项目失败'
        ];
    }
}

/**
 * 重新计算报价单总额
 *
 * @param int $quote_id 报价单ID
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

/**
 * 添加报价项目
 *
 * @param int $quote_id 报价单ID
 * @param array $item_data 项目数据
 * @return array ['success' => bool, 'id' => int, 'error' => string]
 */
function add_quote_item($quote_id, $item_data) {
    try {
        // 验证数据
        if (empty($item_data['catalog_item_id']) || empty($item_data['quantity'])) {
            return ['success' => false, 'error' => '缺少必要参数'];
        }

        // 获取目录项信息
        $catalog_item = get_catalog_item($item_data['catalog_item_id']);
        if (!$catalog_item) {
            return ['success' => false, 'error' => '目录项不存在'];
        }

        // 计算金额
        $quantity = floatval($item_data['quantity']);
        $unit_price_cents = intval($catalog_item['unit_price_cents']);
        $tax_rate = floatval($catalog_item['tax_rate']);

        $line_subtotal_cents = calculate_line_subtotal($quantity, $unit_price_cents);
        $line_tax_cents = calculate_line_tax($line_subtotal_cents, $tax_rate);
        $line_total_cents = calculate_line_total($line_subtotal_cents, $line_tax_cents);

        // 插入项目
        $sql = "
            INSERT INTO quote_items (
                quote_id, catalog_item_id, quantity, unit_price_cents,
                tax_rate, line_subtotal_cents, line_tax_cents, line_total_cents
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        dbExecute($sql, [
            $quote_id,
            $item_data['catalog_item_id'],
            $quantity,
            $unit_price_cents,
            $tax_rate,
            $line_subtotal_cents,
            $line_tax_cents,
            $line_total_cents
        ]);

        $item_id = dbLastInsertId();

        // 重新计算报价单总额
        recalculate_quote_total($quote_id);

        return [
            'success' => true,
            'id' => $item_id,
            'message' => '项目添加成功'
        ];

    } catch (Exception $e) {
        error_log("Add quote item error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '添加项目失败'
        ];
    }
}

/**
 * ========================================
 * Settings 模型操作函数 (Settings Model Functions)
 * US5: 设置管理
 * ========================================
 */

/**
 * 获取系统设置
 *
 * @return array|null 设置信息或null
 */
function get_settings() {
    $org_id = get_current_org_id();
    $sql = "SELECT * FROM settings WHERE org_id = ?";
    $settings = dbQueryOne($sql, [$org_id]);

    // 如果没有设置，创建默认设置
    if (!$settings) {
        $default_settings = [
            'company_name' => '',
            'company_address' => '',
            'company_contact' => '',
            'quote_prefix' => 'Q',
            'default_tax_rate' => 0.00,
            'print_terms' => '',
            'timezone' => defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'Asia/Taipei'
        ];

        // 插入默认设置
        $insert_sql = "
            INSERT INTO settings (
                org_id, company_name, company_address, company_contact,
                quote_prefix, default_tax_rate, print_terms, timezone
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        dbExecute($insert_sql, array_merge([$org_id], array_values($default_settings)));

        // 返回刚插入的设置
        return array_merge(['id' => dbLastInsertId(), 'org_id' => $org_id], $default_settings);
    }

    return $settings;
}

/**
 * 更新系统设置
 *
 * @param array $data 设置数据
 * @return array ['success' => bool, 'error' => string]
 */
function update_settings($data) {
    try {
        $org_id = get_current_org_id();

        if (array_key_exists('quote_prefix', $data)) {
            $data['quote_prefix'] = strtoupper(trim($data['quote_prefix']));
            if ($data['quote_prefix'] === '') {
                $data['quote_prefix'] = 'Q';
            }
        }

        // 验证输入
        $errors = [];

        if (!empty($data['company_name']) && !validate_string_length($data['company_name'], 255)) {
            $errors[] = '公司名称长度不正确';
        }

        if (!empty($data['quote_prefix']) && !preg_match('/^[A-Z]{1,10}$/', $data['quote_prefix'])) {
            $errors[] = '编号前缀必须为1-10个大写字母';
        }

        if (!empty($data['company_address']) && !validate_string_length($data['company_address'], 2000)) {
            $errors[] = '公司地址长度不能超过2000个字符';
        }

        if (!empty($data['company_contact']) && !validate_string_length($data['company_contact'], 255)) {
            $errors[] = '公司联系方式长度不能超过255个字符';
        }

        if (!empty($data['default_tax_rate']) && ($data['default_tax_rate'] < 0 || $data['default_tax_rate'] > 100)) {
            $errors[] = '默认税率必须在0-100之间';
        }

        if (!empty($data['print_terms']) && !validate_string_length($data['print_terms'], 5000)) {
            $errors[] = '打印条款长度不能超过5000个字符';
        }

        if (!empty($data['timezone'])) {
            if (!validate_string_length($data['timezone'], 50)) {
                $errors[] = '时区长度不能超过50个字符';
            } elseif (!in_array($data['timezone'], timezone_identifiers_list())) {
                $errors[] = '请输入有效的时区标识符';
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'error' => implode('；', $errors)];
        }

        // 构建更新SQL
        $update_fields = [];
        $params = [];

        $allowed_fields = [
            'company_name',
            'company_address',
            'quote_prefix',
            'default_tax_rate',
            'print_terms',
            'company_contact',
            'timezone'
        ];

        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $data)) {
                $update_fields[] = "$field = ?";
                if ($field === 'default_tax_rate') {
                    $params[] = floatval($data[$field]);
                } elseif ($field === 'timezone') {
                    $params[] = $data[$field] ?: (defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'Asia/Taipei');
                } elseif ($field === 'quote_prefix') {
                    $params[] = $data[$field] ?: 'Q';
                } else {
                    $params[] = trim($data[$field]);
                }
            }
        }

        if (empty($update_fields)) {
            return ['success' => false, 'error' => '没有要更新的字段'];
        }

        // 检查设置是否存在
        $exists = dbQueryOne("SELECT id FROM settings WHERE org_id = ?", [$org_id]);

        if ($exists) {
            // 更新现有设置
            $sql = "UPDATE settings SET " . implode(', ', $update_fields) . " WHERE org_id = ?";
            $params[] = $org_id;
            dbExecute($sql, $params);
        } else {
            // 创建新设置
            $insert_fields = array_merge(['org_id'], array_keys(array_intersect_key($data, array_flip($allowed_fields))));
            $insert_values = array_merge([$org_id], array_map(function($key) use ($data) {
                if ($key === 'default_tax_rate') {
                    return floatval($data[$key]);
                }
                if ($key === 'timezone') {
                    return $data[$key] ?: (defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'Asia/Taipei');
                }
                if ($key === 'quote_prefix') {
                    return $data[$key] ?: 'Q';
                }
                return trim($data[$key]);
            }, array_keys(array_intersect_key($data, array_flip($allowed_fields)))));

            $placeholders = str_repeat('?,', count($insert_fields) - 1) . '?';
            $sql = "INSERT INTO settings (" . implode(', ', $insert_fields) . ") VALUES ($placeholders)";
            dbExecute($sql, $insert_values);
        }

        // 同步年度编号前缀
        $prefix_for_sync = 'Q';
        if (array_key_exists('quote_prefix', $data) && $data['quote_prefix'] !== '') {
            $prefix_for_sync = $data['quote_prefix'];
        } else {
            $existing_settings = get_settings();
            $prefix_for_sync = !empty($existing_settings['quote_prefix']) ? $existing_settings['quote_prefix'] : 'Q';
        }
        dbExecute("UPDATE quote_sequences SET prefix = ? WHERE org_id = ?", [$prefix_for_sync, $org_id]);

        return [
            'success' => true,
            'message' => '设置更新成功'
        ];

    } catch (Exception $e) {
        error_log("Update settings error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => '更新设置失败'
        ];
    }
}

/**
 * 获取公司信息（用于打印）
 *
 * @return array 公司信息
 */
function get_company_info() {
    $settings = get_settings();
    return [
        'name' => $settings['company_name'] ?? '',
        'address' => $settings['company_address'] ?? '',
        'contact' => $settings['company_contact'] ?? ''
    ];
}

/**
 * 获取报价单编号前缀
 *
 * @return string 前缀
 */
function get_quote_prefix() {
    $settings = get_settings();
    return $settings['quote_prefix'] ?? 'Q';
}

/**
 * 获取默认税率
 *
 * @return float 默认税率
 */
function get_default_tax_rate() {
    $settings = get_settings();
    return floatval($settings['default_tax_rate'] ?? 0);
}

/**
 * 获取打印条款
 *
 * @return string 条款文字
 */
function get_print_terms() {
    $settings = get_settings();
    return $settings['print_terms'] ?? '';
}

// 文件末尾不需要关闭PHP标签，避免非预期输出
