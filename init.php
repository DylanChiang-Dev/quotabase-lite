<?php
/**
 * System Initialization
 * 系統初始化指令碼
 *
 * @version v2.0.0
 * @description 系統初始化，建立預設資料
 * @遵循憲法原則IV: 極簡架構
 */

// 防止直接訪問
define('QUOTABASE_SYSTEM', true);

if (!defined('SKIP_INIT_REDIRECT')) {
    define('SKIP_INIT_REDIRECT', true);
}

$configPath = __DIR__ . '/config.php';

if (!file_exists($configPath)) {
    handle_config_file_setup($configPath);
    exit;
}

/**
 * ========================================
 * 設定檔建立精靈 (Config Setup Wizard)
 * ========================================
 */

function handle_config_file_setup($configPath) {
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $samplePath = __DIR__ . '/config.php.sample';
    $defaults = [
        'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
        'db_name' => getenv('DB_NAME') ?: 'quotabase_lite',
        'db_user' => getenv('DB_USER') ?: 'root',
        'db_pass' => '',
        'timezone' => getenv('DEFAULT_TIMEZONE') ?: 'Asia/Taipei',
        'encryption_key' => '',
        'receipt_secret' => '',
        'receipt_secret_version' => getenv('RECEIPT_SECRET_VERSION') ?: 'v1',
    ];

    $formData = $defaults;
    $formData['encryption_key'] = generate_setup_encryption_key();
    $formData['receipt_secret'] = generate_receipt_secret_key();

    $errors = [];
    $generalError = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'create_config') {
            if (!config_setup_verify_csrf_token($_POST['csrf_token'] ?? '')) {
                $generalError = '驗證失敗，請重新提交表單。';
            } else {
                $formData['db_host'] = trim($_POST['db_host'] ?? '');
                $formData['db_name'] = trim($_POST['db_name'] ?? '');
                $formData['db_user'] = trim($_POST['db_user'] ?? '');
                $formData['db_pass'] = $_POST['db_pass'] ?? '';
                $formData['timezone'] = trim($_POST['timezone'] ?? '') ?: 'Asia/Taipei';
                $formData['encryption_key'] = trim($_POST['encryption_key'] ?? '');

                if ($formData['db_host'] === '') {
                    $errors['db_host'] = '請輸入資料庫主機。';
                }
                if ($formData['db_name'] === '') {
                    $errors['db_name'] = '請輸入資料庫名稱。';
                }
                if ($formData['db_user'] === '') {
                    $errors['db_user'] = '請輸入資料庫使用者。';
                }

                if ($formData['encryption_key'] === '') {
                    $formData['encryption_key'] = generate_setup_encryption_key();
                } elseif (!preg_match('/^[a-f0-9]{32,}$/i', $formData['encryption_key'])) {
                    $errors['encryption_key'] = '加密金鑰必須為至少32位的十六進位字串（例如 64 個字元）。';
                }

                $formData['receipt_secret'] = trim($_POST['receipt_secret'] ?? '');
                $formData['receipt_secret_version'] = trim($_POST['receipt_secret_version'] ?? '') ?: 'v1';

                if ($formData['receipt_secret'] === '') {
                    $formData['receipt_secret'] = generate_receipt_secret_key();
                } elseif (!preg_match('/^[a-f0-9]{64,}$/i', $formData['receipt_secret'])) {
                    $errors['receipt_secret'] = 'server secret 至少需64位十六進位字串。';
                }

                if (!preg_match('/^[A-Za-z0-9._-]{1,32}$/', $formData['receipt_secret_version'])) {
                    $errors['receipt_secret_version'] = 'secret 版本僅能包含英數、點、底線或連字號，且長度不超過32字元。';
                }

                if (empty($errors)) {
                    [$connectionOk, $connectionError] = config_setup_test_connection($formData);
                    if (!$connectionOk) {
                        $generalError = '資料庫連線失敗：' . $connectionError;
                    } else {
                        [$writeOk, $writeError] = config_setup_write_config($configPath, $samplePath, $formData);
                        if ($writeOk) {
                            $message = '設定檔建立完成，請繼續執行初始化流程。';
                            header('Location: /init.php?message=' . urlencode($message) . '&type=success');
                            exit;
                        }
                        $generalError = $writeError ?: '無法寫入設定檔，請確認目錄許可權。';
                    }
                }
            }
        }
    }

    $csrfToken = config_setup_generate_csrf_token();
    $hasSample = is_readable($samplePath);

    ?>
    <!DOCTYPE html>
    <html lang="zh-TW">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>初始設定 - 建立 config.php</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans TC', sans-serif;
                background: #f5f5f5;
                padding: 40px 20px;
            }

            .container {
                max-width: 720px;
                margin: 0 auto;
            }

            .card {
                background: white;
                border-radius: 18px;
                padding: 36px;
                box-shadow: 0 20px 60px rgba(15, 23, 42, 0.12);
                margin-bottom: 24px;
            }

            h1 {
                font-size: 30px;
                color: #111827;
                margin-bottom: 12px;
            }

            .subtitle {
                color: #6b7280;
                margin-bottom: 28px;
                line-height: 1.7;
            }

            .form-group {
                margin-bottom: 22px;
            }

            label {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-weight: 600;
                color: #1f2937;
                margin-bottom: 8px;
                font-size: 15px;
            }

            label span {
                font-weight: 400;
                font-size: 13px;
                color: #9ca3af;
                margin-left: 12px;
            }

            input[type="text"],
            input[type="password"] {
                width: 100%;
                padding: 14px 16px;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                font-size: 15px;
                background: #f9fafb;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            input[type="text"]:focus,
            input[type="password"]:focus {
                outline: none;
                border-color: #6366f1;
                box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
                background: #ffffff;
            }

            .error-message {
                color: #b91c1c;
                font-size: 13px;
                margin-top: 6px;
            }

            .alert {
                padding: 16px 18px;
                border-radius: 12px;
                font-size: 14px;
                margin-bottom: 24px;
                line-height: 1.6;
            }

            .alert-danger {
                background: #fee2e2;
                border: 1px solid #fecaca;
                color: #b91c1c;
            }

            .alert-warning {
                background: #fef3c7;
                border: 1px solid #fde68a;
                color: #92400e;
            }

            .btn-row {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-top: 28px;
            }

            button {
                border: none;
                border-radius: 10px;
                font-size: 16px;
                padding: 12px 20px;
                cursor: pointer;
                font-weight: 600;
                transition: transform 0.15s ease, box-shadow 0.15s ease;
            }

            .btn-primary {
                background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
                color: white;
                box-shadow: 0 12px 30px rgba(79, 70, 229, 0.25);
            }

            .btn-secondary {
                background: #e5e7eb;
                color: #374151;
            }

            button:disabled {
                background: #d1d5db;
                color: #7b8190;
                cursor: not-allowed;
                box-shadow: none;
            }

            button:hover:not(:disabled) {
                transform: translateY(-1px);
                box-shadow: 0 16px 36px rgba(79, 70, 229, 0.35);
            }

            code {
                background: #eef2ff;
                color: #4338ca;
                padding: 2px 6px;
                border-radius: 6px;
                font-size: 13px;
            }

            .note {
                font-size: 13px;
                color: #6b7280;
                margin-top: 8px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <h1>歡迎使用 Quotabase-Lite</h1>
                <p class="subtitle">
                    只需輸入一次資料庫連線資訊，即可建立 <code>config.php</code> 設定檔。系統會測試連線、寫入設定並自動帶你進入初始化精靈。
                </p>

                <?php if ($generalError): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($generalError, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <?php if (!$hasSample): ?>
                    <div class="alert alert-warning">
                        找不到 <code>config.php.sample</code>，系統會改用預設模板生成設定檔。建議確認檔案是否完整存在。
                    </div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="create_config">

                    <div class="form-group">
                        <label for="db_host">資料庫主機 <span>例如 127.0.0.1 或 localhost</span></label>
                        <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($formData['db_host'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <?php if (isset($errors['db_host'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($errors['db_host'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="db_name">資料庫名稱 <span>需先在 MySQL 建立</span></label>
                        <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($formData['db_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <?php if (isset($errors['db_name'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($errors['db_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="db_user">資料庫使用者</label>
                        <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($formData['db_user'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <?php if (isset($errors['db_user'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($errors['db_user'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="db_pass">資料庫密碼</label>
                        <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($formData['db_pass'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="timezone">預設時區 <span>留空則預設為 Asia/Taipei</span></label>
                        <input type="text" id="timezone" name="timezone" value="<?php echo htmlspecialchars($formData['timezone'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="encryption_key">加密金鑰 <span>至少32位十六進位字串</span></label>
                        <div style="display:flex; gap:12px;">
                            <input type="text" id="encryption_key" name="encryption_key" value="<?php echo htmlspecialchars($formData['encryption_key'], ENT_QUOTES, 'UTF-8'); ?>" style="flex:1;">
                            <button type="button" class="btn-secondary" id="generateKeyBtn">重新產生</button>
                        </div>
                        <?php if (isset($errors['encryption_key'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($errors['encryption_key'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <div class="note">若無特別需求，可直接使用系統產生的隨機金鑰。</div>
                    </div>

                    <div class="form-group">
                        <label for="receipt_secret_version">收據 server secret 版本 <span>預設 v1，可自行定義</span></label>
                        <input type="text" id="receipt_secret_version" name="receipt_secret_version" value="<?php echo htmlspecialchars($formData['receipt_secret_version'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <?php if (isset($errors['receipt_secret_version'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($errors['receipt_secret_version'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="receipt_secret">收據 server secret <span>至少64位十六進位字串</span></label>
                        <div style="display:flex; gap:12px;">
                            <input type="text" id="receipt_secret" name="receipt_secret" value="<?php echo htmlspecialchars($formData['receipt_secret'], ENT_QUOTES, 'UTF-8'); ?>" style="flex:1;">
                            <button type="button" class="btn-secondary" id="generateReceiptSecretBtn">重新產生</button>
                        </div>
                        <?php if (isset($errors['receipt_secret'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($errors['receipt_secret'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <div class="note">此 secret 用於 HMAC token，請妥善保存於安全位置並定期輪換。</div>
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btn-primary">建立設定檔並繼續</button>
                    </div>
                </form>

                <div class="note" style="margin-top: 24px;">
                    提示：如果提交後顯示無法寫入，請檢查網站根目錄（<?php echo htmlspecialchars(dirname($configPath), ENT_QUOTES, 'UTF-8'); ?>）的寫入許可權。
                </div>
            </div>
        </div>

        <script>
            (function() {
                const generateKeyBtn = document.getElementById('generateKeyBtn');
                const keyField = document.getElementById('encryption_key');
                const receiptSecretBtn = document.getElementById('generateReceiptSecretBtn');
                const receiptSecretField = document.getElementById('receipt_secret');

                function randomHex(byteLength) {
                    if (window.crypto && window.crypto.getRandomValues) {
                        const bytes = new Uint8Array(byteLength);
                        window.crypto.getRandomValues(bytes);
                        let value = '';
                        bytes.forEach(function(byte) {
                            value += ('0' + byte.toString(16)).slice(-2);
                        });
                        return value;
                    }
                    return '<?php echo bin2hex(random_bytes(32)); ?>';
                }

                function handleGenerate(button, target, bytes) {
                    if (!button || !target) {
                        return;
                    }
                    button.addEventListener('click', function(event) {
                        event.preventDefault();
                        target.value = randomHex(bytes);
                    });
                }

                handleGenerate(generateKeyBtn, keyField, 32);
                handleGenerate(receiptSecretBtn, receiptSecretField, 48);
            })();
        </script>
    </body>
    </html>
    <?php
}

function config_setup_generate_csrf_token() {
    if (!isset($_SESSION['config_setup_csrf'])) {
        $_SESSION['config_setup_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['config_setup_csrf'];
}

function config_setup_verify_csrf_token($token) {
    return isset($_SESSION['config_setup_csrf']) && hash_equals($_SESSION['config_setup_csrf'], $token ?? '');
}

function generate_setup_encryption_key() {
    return bin2hex(random_bytes(32));
}

function generate_receipt_secret_key() {
    return bin2hex(random_bytes(48));
}

function config_setup_test_connection(array $formData) {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $formData['db_host'],
        $formData['db_name']
    );

    try {
        $pdo = new PDO($dsn, $formData['db_user'], $formData['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->query('SELECT 1');
        return [true, null];
    } catch (PDOException $e) {
        return [false, $e->getMessage()];
    }
}

function config_setup_write_config($targetPath, $samplePath, array $formData) {
    if (file_exists($targetPath)) {
        return [true, null];
    }

    if (is_readable($samplePath)) {
        $content = file_get_contents($samplePath);
        if ($content === false) {
            return [false, '無法讀取 config.php.sample。'];
        }
    } else {
        $content = config_setup_default_template();
    }

    $content = config_setup_replace_define($content, 'DB_HOST', var_export($formData['db_host'], true));
    $content = config_setup_replace_define($content, 'DB_NAME', var_export($formData['db_name'], true));
    $content = config_setup_replace_define($content, 'DB_USER', var_export($formData['db_user'], true));
    $content = config_setup_replace_define($content, 'DB_PASS', var_export($formData['db_pass'], true));
    $timezoneLiteral = var_export($formData['timezone'] ?: 'Asia/Taipei', true);
    $content = config_setup_replace_define($content, 'DEFAULT_TIMEZONE', $timezoneLiteral, true);
    $content = config_setup_replace_define($content, 'DISPLAY_TIMEZONE', $timezoneLiteral, true);
    $content = config_setup_replace_define($content, 'ENCRYPTION_KEY', var_export($formData['encryption_key'], true));
    $content = config_setup_replace_define($content, 'RECEIPT_SERVER_SECRET', var_export($formData['receipt_secret'], true));
    $content = config_setup_replace_define($content, 'RECEIPT_SECRET_VERSION', var_export($formData['receipt_secret_version'], true));

    if ($content === null) {
        return [false, '產生設定檔內容時發生錯誤。'];
    }

    if (@file_put_contents($targetPath, $content) === false) {
        return [false, '無法寫入設定檔，請確認資料夾具備寫入許可權。'];
    }

    @chmod($targetPath, 0640);
    return [true, null];
}

function config_setup_replace_define($content, $name, $value, $raw = false) {
    $literal = $raw ? $value : $value;
    $pattern = "~define\\('" . preg_quote($name, '~') . "',\\s*.*?\\);~s";
    $replacement = "define('{$name}', {$literal});";

    $updated = preg_replace($pattern, $replacement, $content, 1, $count);
    if ($count === 0) {
        $injection = $replacement . "\n";
        $updated = preg_replace("/(<\\?php\\s+)/", '$1' . $injection, $content, 1, $count);
        if ($count === 0) {
            $updated = $injection . $content;
        }
    }

    return $updated;
}

function config_setup_default_template() {
    return <<<'PHP'
<?php
/**
 * 自動產生的設定檔
 * 若需調整，請手動編輯此檔案。
 */

if (!defined('QUOTABASE_SYSTEM')) {
    define('QUOTABASE_SYSTEM', true);
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'quotabase_lite');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('SESSION_TIMEOUT', 3600);
define('CSRF_TOKEN_NAME', 'csrf_token');
define('ENCRYPTION_KEY', 'secure_key');

define('DEFAULT_TIMEZONE', 'Asia/Taipei');
define('DISPLAY_TIMEZONE', 'Asia/Taipei');

define('APP_NAME', 'Quotabase-Lite');
define('APP_VERSION', '2.0.0');
define('DEFAULT_ORG_ID', 1);
define('DEFAULT_PAGE_SIZE', 20);

define('RECEIPT_SERVER_SECRET', 'change_me_to_secure_value');
define('RECEIPT_SECRET_VERSION', 'v1');
define('RECEIPT_STORAGE_PATH', __DIR__ . '/storage/receipts');
define('RECEIPT_RETENTION_YEARS', 5);
define('RECEIPT_STAMP_PATH', __DIR__ . '/assets/stamps/company-stamp.png');

$serverName = $_SERVER['SERVER_NAME'] ?? '';
$isCli = php_sapi_name() === 'cli';

if (!$isCli && $serverName !== 'localhost' && !isset($_GET['debug'])) {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    session_start();
}

require_once __DIR__ . '/helpers/functions.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    if ($_SERVER['SERVER_NAME'] !== 'localhost') {
        error_log("Database connection failed: " . $e->getMessage());
        die("系統維護中，請稍後再試。");
    } else {
        die("資料庫連線失敗: " . $e->getMessage());
    }
}

function get_org_settings($org_id = DEFAULT_ORG_ID) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM settings WHERE org_id = ?");
        $stmt->execute([$org_id]);
        return $stmt->fetch() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

$org_settings = get_org_settings();

if (!defined('SKIP_INIT_REDIRECT')) {
    redirect_to_init_if_needed();
}
PHP;
}

// 載入依賴
require_once $configPath;
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/db.php';

/**
 * 檢查系統是否已初始化
 *
 * @return bool 是否已初始化
 */
function is_system_initialized() {
    try {
        // 檢查組織表
        $org_count = dbQueryOne("SELECT COUNT(*) as count FROM organizations");
        if ($org_count['count'] == 0) {
            return false;
        }

        // 檢查設定表
        $settings_count = dbQueryOne("SELECT COUNT(*) as count FROM settings");
        if ($settings_count['count'] == 0) {
            return false;
        }

        // 檢查報價序號表
        $sequence_count = dbQueryOne("SELECT COUNT(*) as count FROM quote_sequences");
        if ($sequence_count['count'] == 0) {
            return false;
        }

        return true;
    } catch (Exception $e) {
        error_log("Initialization check failed: " . $e->getMessage());
        return false;
    }
}

/**
 * 初始化系統資料
 *
 * @return array ['success' => bool, 'message' => string]
 */
function initialize_system() {
    try {
        $db = getDB();

        // 先確保使用者表及預設管理員存在（避免在交易中執行DDL）
        ensure_default_admin_user($db->getConnection());

        $db->beginTransaction();

        // 1. 建立預設組織
        $org_exists = dbQueryOne("SELECT id FROM organizations WHERE id = ?", [DEFAULT_ORG_ID]);
        if (!$org_exists) {
            dbExecute(
                "INSERT INTO organizations (id, name) VALUES (?, ?)",
                [DEFAULT_ORG_ID, '預設組織']
            );
            error_log("Created default organization: ID=" . DEFAULT_ORG_ID);
        }

        // 2. 建立預設設定
        $settings_exists = dbQueryOne("SELECT id FROM settings WHERE org_id = ?", [DEFAULT_ORG_ID]);
        if (!$settings_exists) {
            dbExecute(
                "INSERT INTO settings (org_id, company_name, company_address, company_contact, quote_prefix, default_tax_rate, timezone) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    DEFAULT_ORG_ID,
                    '您的公司名稱',
                    '公司地址',
                    '聯絡電話',
                    'Q',
                    5.00,
                    'Asia/Taipei'
                ]
            );
            error_log("Created default settings for org_id=" . DEFAULT_ORG_ID);
        }

        // 3. 初始化報價序號表
        $current_year = date('Y');
        $sequence_exists = dbQueryOne(
            "SELECT id FROM quote_sequences WHERE org_id = ? AND year = ?",
            [DEFAULT_ORG_ID, $current_year]
        );
        if (!$sequence_exists) {
            dbExecute(
                "INSERT INTO quote_sequences (org_id, prefix, year, current_number) VALUES (?, ?, ?, ?)",
                [DEFAULT_ORG_ID, 'Q', $current_year, 0]
            );
            error_log("Created quote sequence for year=" . $current_year);
        }

        $db->commit();

        return [
            'success' => true,
            'message' => '系統初始化完成！'
        ];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }

        error_log("System initialization failed: " . $e->getMessage());

        return [
            'success' => false,
            'message' => '系統初始化失敗: ' . $e->getMessage()
        ];
    }
}

/**
 * 重置系統資料（謹慎使用）
 *
 * @return array
 */
function reset_system() {
    try {
        $db = getDB();
        $db->beginTransaction();

        // 刪除所有資料（保留表結構）
        dbExecute("SET FOREIGN_KEY_CHECKS = 0");

        dbExecute("TRUNCATE TABLE quote_items");
        dbExecute("TRUNCATE TABLE quotes");
        dbExecute("TRUNCATE TABLE catalog_items");
        dbExecute("TRUNCATE TABLE customers");
        dbExecute("TRUNCATE TABLE quote_sequences");
        dbExecute("TRUNCATE TABLE settings");
        dbExecute("TRUNCATE TABLE organizations");

        dbExecute("SET FOREIGN_KEY_CHECKS = 1");

        // 重新初始化
        $result = initialize_system();

        $db->commit();

        return [
            'success' => true,
            'message' => '系統重置完成！'
        ];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }

        return [
            'success' => false,
            'message' => '系統重置失敗: ' . $e->getMessage()
        ];
    }
}

/**
 * 取得資料庫模式狀態
 *
 * @return array
 */
function get_schema_status() {
    $status = [
        'ready' => false,
        'missing_tables' => [],
        'existing_tables' => [],
        'missing_columns' => [],
        'has_procedure' => false,
        'error' => null,
    ];

    try {
        $pdo = getDB()->getConnection();
    } catch (Throwable $e) {
        $status['error'] = $e->getMessage();
        return $status;
    }

    $requiredTables = [
        'organizations' => 'organizations（組織）',
        'customers' => 'customers（客戶）',
        'catalog_categories' => 'catalog_categories（分類）',
        'catalog_items' => 'catalog_items（產品/服務）',
        'quotes' => 'quotes（報價單）',
        'quote_items' => 'quote_items（報價專案）',
        'quote_sequences' => 'quote_sequences（年度序號）',
        'settings' => 'settings（系統設定）',
        'users' => 'users（使用者）',
        'receipt_consents' => 'receipt_consents（電子同意）',
        'receipts' => 'receipts（個人收據）',
        'receipt_verifications' => 'receipt_verifications（收據查驗）',
    ];

    foreach ($requiredTables as $table => $label) {
        if (table_exists($pdo, $table)) {
            $status['existing_tables'][] = $label;
        } else {
            $status['missing_tables'][] = $label;
        }
    }

    if (table_exists($pdo, 'quote_items') && !column_exists($pdo, 'quote_items', 'discount_cents')) {
        $status['missing_columns'][] = 'quote_items.discount_cents';
    }

    $status['has_procedure'] = procedure_exists($pdo, 'next_quote_number');
    $status['ready'] = empty($status['missing_tables']) && empty($status['missing_columns']) && $status['has_procedure'] && $status['error'] === null;

    return $status;
}

/**
 * 判斷指定資料表是否存在
 *
 * @param PDO $pdo
 * @param string $table
 * @return bool
 */
function table_exists(PDO $pdo, $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * 判斷資料表欄位是否存在
 *
 * @param PDO $pdo
 * @param string $table
 * @param string $column
 * @return bool
 */
function column_exists(PDO $pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * 判斷儲存程式是否存在
 *
 * @param PDO $pdo
 * @param string $procedure
 * @return bool
 */
function procedure_exists(PDO $pdo, $procedure) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = ? AND ROUTINE_TYPE = 'PROCEDURE'");
    $stmt->execute([$procedure]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * 判斷外部索引鍵是否存在
 *
 * @param PDO $pdo
 * @param string $table
 * @param string $constraint
 * @return bool
 */
function foreign_key_exists(PDO $pdo, $table, $constraint) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
    $stmt->execute([$table, $constraint]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * 安裝或更新資料庫結構
 *
 * @return array
 */
function install_database_schema() {
    try {
        $db = getDB();
        $pdo = $db->getConnection();

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        $tableStatements = [
            "CREATE TABLE IF NOT EXISTS organizations (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL COMMENT '組織名稱',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
                INDEX idx_org_name (name)
            ) ENGINE=InnoDB COMMENT='組織表（預留多租戶）'",

            "CREATE TABLE IF NOT EXISTS customers (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID',
                name VARCHAR(255) NOT NULL COMMENT '客戶名稱（必填）',
                tax_id VARCHAR(50) NULL COMMENT '稅務登記號',
                email VARCHAR(255) NULL COMMENT '郵箱',
                phone VARCHAR(50) NULL COMMENT '電話',
                billing_address TEXT NULL COMMENT '賬單地址',
                shipping_address TEXT NULL COMMENT '收貨地址',
                note TEXT NULL COMMENT '備註',
                active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '軟刪除標記（1=啟用，0=停用）',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
                INDEX idx_customers_org_id (org_id),
                INDEX idx_customers_active (active),
                INDEX idx_customers_name (name)
            ) ENGINE=InnoDB COMMENT='客戶表'",

            "CREATE TABLE IF NOT EXISTS catalog_categories (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID',
                type ENUM('product', 'service') NOT NULL DEFAULT 'product' COMMENT '分類型別',
                parent_id BIGINT UNSIGNED NULL COMMENT '父級分類ID（NULL為頂級）',
                level TINYINT UNSIGNED NOT NULL COMMENT '分類層級（1-3）',
                name VARCHAR(100) NOT NULL COMMENT '分類名稱',
                sort_order INT NOT NULL DEFAULT 0 COMMENT '排序值',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
                UNIQUE KEY uq_category_name (org_id, type, parent_id, name),
                INDEX idx_category_org_type (org_id, type),
                INDEX idx_category_parent (parent_id),
                INDEX idx_category_level (org_id, level),
                CONSTRAINT fk_category_parent FOREIGN KEY (parent_id) REFERENCES catalog_categories(id) ON DELETE CASCADE
            ) ENGINE=InnoDB COMMENT='產品/服務分類表'",

            "CREATE TABLE IF NOT EXISTS catalog_items (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID',
                type ENUM('product', 'service') NOT NULL COMMENT '型別：產品或服務',
                sku VARCHAR(100) NOT NULL COMMENT 'SKU編碼（同一org_id下唯一）',
                name VARCHAR(255) NOT NULL COMMENT '名稱（必填）',
                unit VARCHAR(20) NOT NULL DEFAULT 'pcs' COMMENT '單位',
                currency VARCHAR(3) NOT NULL DEFAULT 'TWD' COMMENT '幣種（僅支援TWD）',
                unit_price_cents BIGINT UNSIGNED NOT NULL COMMENT '單價（單位：分）',
                tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '稅率（%）',
                category_id BIGINT UNSIGNED NULL COMMENT '分類ID（三級分類）',
                active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '狀態（1=啟用，0=停用）',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
                INDEX idx_catalog_org_type (org_id, type),
                INDEX idx_catalog_sku (org_id, sku),
                INDEX idx_catalog_active (active),
                INDEX idx_catalog_category (category_id),
                UNIQUE KEY uq_catalog_sku (org_id, sku)
            ) ENGINE=InnoDB COMMENT='目錄項表（產品/服務統一）'",

            "CREATE TABLE IF NOT EXISTS quotes (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID',
                quote_number VARCHAR(50) NOT NULL COMMENT '報價單編號（格式：字首-YYYY-000001）',
                customer_id BIGINT UNSIGNED NOT NULL COMMENT '客戶ID（外部索引鍵）',
                issue_date DATE NOT NULL COMMENT '發出日期（UTC）',
                valid_until DATE NULL COMMENT '有效期至（UTC）',
                currency VARCHAR(3) NOT NULL DEFAULT 'TWD' COMMENT '幣種（僅支援TWD）',
                status ENUM('draft', 'sent', 'accepted', 'rejected', 'expired') NOT NULL DEFAULT 'draft' COMMENT '狀態',
                title VARCHAR(255) NULL COMMENT '報價單標題',
                notes TEXT NULL COMMENT '備註',
                subtotal_cents BIGINT UNSIGNED NOT NULL COMMENT '小計（分）',
                tax_cents BIGINT UNSIGNED NOT NULL COMMENT '稅額（分）',
                total_cents BIGINT UNSIGNED NOT NULL COMMENT '總計（分）',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
                INDEX idx_quotes_org_customer_date (org_id, customer_id, issue_date),
                INDEX idx_quotes_number (quote_number),
                INDEX idx_quotes_status (status),
                INDEX idx_quotes_issue_date (issue_date),
                UNIQUE KEY uq_quotes_number (quote_number)
            ) ENGINE=InnoDB COMMENT='報價單主檔表'",

            "CREATE TABLE IF NOT EXISTS quote_items (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                quote_id BIGINT UNSIGNED NOT NULL COMMENT '報價單ID（外部索引鍵）',
                catalog_item_id BIGINT UNSIGNED NULL COMMENT '關聯的目錄項ID（可為空）',
                description VARCHAR(500) NOT NULL COMMENT '專案描述',
                qty DECIMAL(18,4) NOT NULL COMMENT '數量（精確到0.0001）',
                unit VARCHAR(20) NULL COMMENT '單位',
                unit_price_cents BIGINT UNSIGNED NOT NULL COMMENT '單價（分）',
                discount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '行折扣金額（分）',
                tax_rate DECIMAL(5,2) NOT NULL COMMENT '稅率（%）',
                line_subtotal_cents BIGINT UNSIGNED NOT NULL COMMENT '行小計（分）',
                line_tax_cents BIGINT UNSIGNED NOT NULL COMMENT '行稅額（分）',
                line_total_cents BIGINT UNSIGNED NOT NULL COMMENT '行總計（分）',
                line_order INT NOT NULL COMMENT '行順序（排序用）',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
                INDEX idx_quote_items_quote_id (quote_id),
                INDEX idx_quote_items_order (quote_id, line_order),
                CONSTRAINT fk_quote_items_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
            ) ENGINE=InnoDB COMMENT='報價專案明細表'",

            "CREATE TABLE IF NOT EXISTS quote_sequences (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID（每個組織一條記錄）',
                prefix VARCHAR(10) NOT NULL DEFAULT 'Q' COMMENT '編號字首',
                year INT NOT NULL COMMENT '年度',
                current_number INT NOT NULL DEFAULT 0 COMMENT '當前編號',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
                UNIQUE KEY uq_quote_sequences_org_year (org_id, year)
            ) ENGINE=InnoDB COMMENT='年度編號序列表'",

            "CREATE TABLE IF NOT EXISTS settings (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID（每個組織一條記錄）',
                company_name VARCHAR(255) NULL COMMENT '公司名稱',
                company_address TEXT NULL COMMENT '公司地址',
                company_contact VARCHAR(255) NULL COMMENT '公司聯絡方式',
                quote_prefix VARCHAR(10) NOT NULL DEFAULT 'Q' COMMENT '報價單編號字首',
                default_tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '預設稅率（%）',
                print_terms TEXT NULL COMMENT '列印條款文字',
                timezone VARCHAR(50) NOT NULL DEFAULT 'Asia/Taipei' COMMENT '時區',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
                UNIQUE KEY uq_settings_org_id (org_id)
            ) ENGINE=InnoDB COMMENT='系統設定表'",

            "CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID',
                username VARCHAR(100) NOT NULL COMMENT '登入帳號（唯一）',
                password_hash VARCHAR(255) NOT NULL COMMENT '密碼雜湊',
                email VARCHAR(255) NULL COMMENT '電子郵件',
                role ENUM('admin', 'staff') NOT NULL DEFAULT 'admin' COMMENT '角色',
                status ENUM('active', 'suspended') NOT NULL DEFAULT 'active' COMMENT '狀態',
                last_login_at DATETIME NULL COMMENT '最後登入時間',
                last_login_ip VARCHAR(45) NULL COMMENT '最後登入IP',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
                UNIQUE KEY uq_users_username (username),
                UNIQUE KEY uq_users_email (email),
                INDEX idx_users_org (org_id),
                INDEX idx_users_status (status),
                INDEX idx_users_role (role)
            ) ENGINE=InnoDB COMMENT='系統使用者'",

            "CREATE TABLE IF NOT EXISTS receipt_consents (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID',
                quote_id BIGINT UNSIGNED NOT NULL COMMENT '報價單ID',
                customer_id BIGINT UNSIGNED NOT NULL COMMENT '客戶ID',
                method ENUM('checkbox', 'email', 'other') NOT NULL DEFAULT 'checkbox' COMMENT '同意方式',
                counterparty_ip VARCHAR(45) NULL COMMENT '相對人IP',
                recorded_ip VARCHAR(45) NULL COMMENT '記錄者IP',
                user_agent VARCHAR(255) NULL COMMENT '記錄者UA',
                evidence_ref VARCHAR(255) NULL COMMENT '佐證資訊（如郵件ID）',
                notes TEXT NULL COMMENT '備註',
                consented_at DATETIME NOT NULL COMMENT '相對人同意時間',
                recorded_by BIGINT UNSIGNED NULL COMMENT '紀錄者使用者ID',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
                INDEX idx_receipt_consents_quote (quote_id),
                INDEX idx_receipt_consents_customer (customer_id),
                INDEX idx_receipt_consents_method (method),
                CONSTRAINT fk_receipt_consents_quote FOREIGN KEY (quote_id) REFERENCES quotes(id),
                CONSTRAINT fk_receipt_consents_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
                CONSTRAINT fk_receipt_consents_user FOREIGN KEY (recorded_by) REFERENCES users(id)
            ) ENGINE=InnoDB COMMENT='電子同意紀錄'",

            "CREATE TABLE IF NOT EXISTS receipts (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID',
                quote_id BIGINT UNSIGNED NOT NULL COMMENT '報價單ID',
                consent_id BIGINT UNSIGNED NULL COMMENT '電子同意紀錄ID',
                serial VARCHAR(100) NOT NULL COMMENT '收據序號',
                pdf_path VARCHAR(255) NOT NULL COMMENT 'PDF 檔案相對路徑',
                amount_cents BIGINT UNSIGNED NOT NULL COMMENT '總金額（分）',
                currency VARCHAR(3) NOT NULL DEFAULT 'TWD' COMMENT '幣別',
                issued_on DATE NOT NULL COMMENT '開立日期',
                hash_full CHAR(64) NOT NULL COMMENT 'SHA-256 雜湊',
                hash_short CHAR(20) NOT NULL COMMENT 'hash_short（Base32）',
                qr_payload TEXT NOT NULL COMMENT 'QR 原始字串',
                qr_token VARCHAR(128) NOT NULL COMMENT 'HMAC token',
                qr_secret_version VARCHAR(32) NOT NULL COMMENT 'Secret 版本',
                status ENUM('issued', 'revoked') NOT NULL DEFAULT 'issued' COMMENT '狀態',
                expires_at DATE NOT NULL COMMENT '保存期限（issued_on + 5 年）',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
                UNIQUE KEY uq_receipts_quote (quote_id),
                UNIQUE KEY uq_receipts_serial (org_id, serial),
                INDEX idx_receipts_org (org_id),
                INDEX idx_receipts_status (status),
                CONSTRAINT fk_receipts_quote FOREIGN KEY (quote_id) REFERENCES quotes(id),
                CONSTRAINT fk_receipts_consent FOREIGN KEY (consent_id) REFERENCES receipt_consents(id)
            ) ENGINE=InnoDB COMMENT='個人收據紀錄'",

            "CREATE TABLE IF NOT EXISTS receipt_verifications (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                receipt_id BIGINT UNSIGNED NOT NULL COMMENT '收據ID',
                serial VARCHAR(100) NOT NULL COMMENT '收據序號',
                status ENUM('passed', 'failed', 'expired') NOT NULL COMMENT '查驗狀態',
                failure_reason VARCHAR(50) NULL COMMENT '失敗原因代碼',
                ip_address VARCHAR(45) NULL COMMENT '查驗IP',
                user_agent VARCHAR(255) NULL COMMENT '查驗來源 UA',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
                INDEX idx_receipt_verifications_receipt (receipt_id),
                INDEX idx_receipt_verifications_serial (serial),
                CONSTRAINT fk_receipt_verifications_receipt FOREIGN KEY (receipt_id) REFERENCES receipts(id)
            ) ENGINE=InnoDB COMMENT='收據查驗紀錄'",
        ];

        foreach ($tableStatements as $sql) {
            $pdo->exec($sql);
        }

        // 確保外部索引鍵存在
        if (!foreign_key_exists($pdo, 'quotes', 'fk_quotes_customer')) {
            $pdo->exec("ALTER TABLE quotes
                ADD CONSTRAINT fk_quotes_customer
                FOREIGN KEY (customer_id) REFERENCES customers(id)");
        }

        // 更新既有欄位
        ensure_schema_upgrades($pdo);
        ensure_default_admin_user($pdo);

        // 重新建立儲存程式
        $pdo->exec("DROP PROCEDURE IF EXISTS next_quote_number");
        $pdo->exec("
            CREATE PROCEDURE next_quote_number(
                IN p_org_id BIGINT,
                OUT p_out_number VARCHAR(50)
            )
            BEGIN
                DECLARE v_year INT;
                DECLARE v_prefix VARCHAR(10);
                DECLARE v_current INT;
                DECLARE v_next INT;

                SET v_year = YEAR(NOW());
                SET v_prefix = (
                    SELECT COALESCE(s.quote_prefix, 'Q')
                    FROM settings s
                    WHERE s.org_id = p_org_id
                    LIMIT 1
                );

                IF v_prefix IS NULL OR v_prefix = '' THEN
                    SET v_prefix = 'Q';
                END IF;

                SELECT current_number INTO v_current
                FROM quote_sequences
                WHERE org_id = p_org_id AND year = v_year
                FOR UPDATE;

                IF v_current IS NULL THEN
                    INSERT INTO quote_sequences (org_id, year, prefix, current_number)
                    VALUES (p_org_id, v_year, v_prefix, 0);
                    SET v_current = 0;
                END IF;

                SET v_next = v_current + 1;

                UPDATE quote_sequences
                SET current_number = v_next,
                    prefix = v_prefix
                WHERE org_id = p_org_id AND year = v_year;

                SET p_out_number = CONCAT(v_prefix, '-', v_year, '-', LPAD(v_next, 6, '0'));
            END
        ");

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        return [
            'success' => true,
            'message' => '資料庫結構已建立或更新完成。'
        ];
    } catch (Throwable $e) {
        if (isset($pdo)) {
            try {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            } catch (Throwable $inner) {
                // ignore
            }
        }

        error_log("Schema installation failed: " . $e->getMessage());

        return [
            'success' => false,
            'message' => '資料庫結構建立失敗: ' . $e->getMessage()
        ];
    }
}

/**
 * 資料庫升級維護
 *
 * @param PDO $pdo
 * @return void
 */
function ensure_schema_upgrades(PDO $pdo) {
    if (!table_exists($pdo, 'users')) {
        create_users_table($pdo);
    }

    if (table_exists($pdo, 'quote_items') && !column_exists($pdo, 'quote_items', 'discount_cents')) {
        $pdo->exec("ALTER TABLE quote_items
            ADD COLUMN discount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '行折扣金額（分）' AFTER unit_price_cents");
    }

    if (table_exists($pdo, 'catalog_categories') && !foreign_key_exists($pdo, 'catalog_categories', 'fk_category_parent')) {
        $pdo->exec("ALTER TABLE catalog_categories
            ADD CONSTRAINT fk_category_parent
            FOREIGN KEY (parent_id) REFERENCES catalog_categories(id) ON DELETE CASCADE");
    }

    if (table_exists($pdo, 'quotes') && !foreign_key_exists($pdo, 'quotes', 'fk_quotes_customer')) {
        $pdo->exec("ALTER TABLE quotes
            ADD CONSTRAINT fk_quotes_customer
            FOREIGN KEY (customer_id) REFERENCES customers(id)");
    }

    if (table_exists($pdo, 'settings') && !column_exists($pdo, 'settings', 'company_tax_id')) {
        $pdo->exec("ALTER TABLE settings
            ADD COLUMN company_tax_id VARCHAR(50) NULL COMMENT '公司統一編號' AFTER company_contact");
    }

    if (!table_exists($pdo, 'receipt_consents')) {
        create_receipt_consents_table($pdo);
    }

    if (!table_exists($pdo, 'receipts')) {
        create_receipts_table($pdo);
    }

    if (!table_exists($pdo, 'receipt_verifications')) {
        create_receipt_verifications_table($pdo);
    }
}

function create_users_table(PDO $pdo) {
    $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID',
            username VARCHAR(100) NOT NULL COMMENT '登入帳號（唯一）',
            password_hash VARCHAR(255) NOT NULL COMMENT '密碼雜湊',
            email VARCHAR(255) NULL COMMENT '電子郵件',
            role ENUM('admin', 'staff') NOT NULL DEFAULT 'admin' COMMENT '角色',
            status ENUM('active', 'suspended') NOT NULL DEFAULT 'active' COMMENT '狀態',
            last_login_at DATETIME NULL COMMENT '最後登入時間',
            last_login_ip VARCHAR(45) NULL COMMENT '最後登入IP',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
            UNIQUE KEY uq_users_username (username),
            UNIQUE KEY uq_users_email (email),
            INDEX idx_users_org (org_id),
            INDEX idx_users_status (status),
            INDEX idx_users_role (role)
        ) ENGINE=InnoDB COMMENT='系統使用者'
    ";

    $pdo->exec($sql);
}

function create_receipt_consents_table(PDO $pdo) {
    $sql = "
        CREATE TABLE receipt_consents (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID',
            quote_id BIGINT UNSIGNED NOT NULL COMMENT '報價單ID',
            customer_id BIGINT UNSIGNED NOT NULL COMMENT '客戶ID',
            method ENUM('checkbox', 'email', 'other') NOT NULL DEFAULT 'checkbox' COMMENT '同意方式',
            counterparty_ip VARCHAR(45) NULL COMMENT '相對人IP',
            recorded_ip VARCHAR(45) NULL COMMENT '記錄者IP',
            user_agent VARCHAR(255) NULL COMMENT '記錄者UA',
            evidence_ref VARCHAR(255) NULL COMMENT '佐證資訊（如郵件ID）',
            notes TEXT NULL COMMENT '備註',
            consented_at DATETIME NOT NULL COMMENT '相對人同意時間',
            recorded_by BIGINT UNSIGNED NULL COMMENT '紀錄者使用者ID',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
            INDEX idx_receipt_consents_quote (quote_id),
            INDEX idx_receipt_consents_customer (customer_id),
            INDEX idx_receipt_consents_method (method),
            CONSTRAINT fk_receipt_consents_quote FOREIGN KEY (quote_id) REFERENCES quotes(id),
            CONSTRAINT fk_receipt_consents_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
            CONSTRAINT fk_receipt_consents_user FOREIGN KEY (recorded_by) REFERENCES users(id)
        ) ENGINE=InnoDB COMMENT='電子同意紀錄'
    ";

    $pdo->exec($sql);
}

function create_receipts_table(PDO $pdo) {
    $sql = "
        CREATE TABLE receipts (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID',
            quote_id BIGINT UNSIGNED NOT NULL COMMENT '報價單ID',
            consent_id BIGINT UNSIGNED NULL COMMENT '電子同意紀錄ID',
            serial VARCHAR(100) NOT NULL COMMENT '收據序號',
            pdf_path VARCHAR(255) NOT NULL COMMENT 'PDF 檔案相對路徑',
            amount_cents BIGINT UNSIGNED NOT NULL COMMENT '總金額（分）',
            currency VARCHAR(3) NOT NULL DEFAULT 'TWD' COMMENT '幣別',
            issued_on DATE NOT NULL COMMENT '開立日期',
            hash_full CHAR(64) NOT NULL COMMENT 'SHA-256 雜湊',
            hash_short CHAR(20) NOT NULL COMMENT 'hash_short（Base32）',
            qr_payload TEXT NOT NULL COMMENT 'QR 原始字串',
            qr_token VARCHAR(128) NOT NULL COMMENT 'HMAC token',
            qr_secret_version VARCHAR(32) NOT NULL COMMENT 'Secret 版本',
            status ENUM('issued', 'revoked') NOT NULL DEFAULT 'issued' COMMENT '狀態',
            expires_at DATE NOT NULL COMMENT '保存期限（issued_on + 5 年）',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
            UNIQUE KEY uq_receipts_quote (quote_id),
            UNIQUE KEY uq_receipts_serial (org_id, serial),
            INDEX idx_receipts_org (org_id),
            INDEX idx_receipts_status (status),
            CONSTRAINT fk_receipts_quote FOREIGN KEY (quote_id) REFERENCES quotes(id),
            CONSTRAINT fk_receipts_consent FOREIGN KEY (consent_id) REFERENCES receipt_consents(id)
        ) ENGINE=InnoDB COMMENT='個人收據紀錄'
    ";

    $pdo->exec($sql);
}

function create_receipt_verifications_table(PDO $pdo) {
    $sql = "
        CREATE TABLE receipt_verifications (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            receipt_id BIGINT UNSIGNED NOT NULL COMMENT '收據ID',
            serial VARCHAR(100) NOT NULL COMMENT '收據序號',
            status ENUM('passed', 'failed', 'expired') NOT NULL COMMENT '查驗狀態',
            failure_reason VARCHAR(50) NULL COMMENT '失敗原因代碼',
            ip_address VARCHAR(45) NULL COMMENT '查驗IP',
            user_agent VARCHAR(255) NULL COMMENT '查驗來源 UA',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
            INDEX idx_receipt_verifications_receipt (receipt_id),
            INDEX idx_receipt_verifications_serial (serial),
            CONSTRAINT fk_receipt_verifications_receipt FOREIGN KEY (receipt_id) REFERENCES receipts(id)
        ) ENGINE=InnoDB COMMENT='收據查驗紀錄'
    ";

    $pdo->exec($sql);
}

function ensure_default_admin_user(PDO $pdo) {
    create_users_table($pdo);

    $defaultPassword = getenv('DEFAULT_ADMIN_PASSWORD') ?: 'Admin1234';
    $passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute(['admin']);
        $existing = $stmt->fetch();

        if ($existing) {
            $update = $pdo->prepare("
                UPDATE users
                SET password_hash = ?, role = 'admin', status = 'active', updated_at = NOW()
                WHERE id = ?
            ");
            $update->execute([$passwordHash, $existing['id']]);
        } else {
            $insert = $pdo->prepare("
                INSERT INTO users (org_id, username, password_hash, email, role, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert->execute([
                1,
                'admin',
                $passwordHash,
                null,
                'admin',
                'active'
            ]);
        }
    } catch (Throwable $e) {
        error_log('Ensure default admin failed: ' . $e->getMessage());
    }
}

/**
 * 獲取系統狀態資訊
 *
 * @return array 系統狀態
 */
function get_system_status() {
    $status = [];

    // 檢查資料庫連線
    try {
        $pdo = getDB()->getConnection();
        $status['database'] = 'OK';
    } catch (Exception $e) {
        $status['database'] = 'ERROR: ' . $e->getMessage();
    }

    // 檢查組織資料
    try {
        $org_count = dbQueryOne("SELECT COUNT(*) as count FROM organizations");
        $status['organizations'] = $org_count['count'];
    } catch (Exception $e) {
        $status['organizations'] = 'ERROR';
    }

    // 檢查設定資料
    try {
        $settings_count = dbQueryOne("SELECT COUNT(*) as count FROM settings");
        $status['settings'] = $settings_count['count'];
    } catch (Exception $e) {
        $status['settings'] = 'ERROR';
    }

    // 檢查使用者
    try {
        $users_count = dbQueryOne("SELECT COUNT(*) as count FROM users");
        $status['users'] = $users_count['count'];
    } catch (Exception $e) {
        $status['users'] = 'ERROR';
    }

    // 檢查報價序號資料
    try {
        $sequence_count = dbQueryOne("SELECT COUNT(*) as count FROM quote_sequences");
        $status['sequences'] = $sequence_count['count'];
    } catch (Exception $e) {
        $status['sequences'] = 'ERROR';
    }

    // 系統版本資訊
    $status['app_version'] = APP_VERSION;
    $status['php_version'] = PHP_VERSION;
    $status['initialized'] = is_system_initialized();

    // 資料庫模式狀態
    $schemaStatus = get_schema_status();
    $status['schema_ready'] = $schemaStatus['ready'];
    $status['schema_missing_tables'] = $schemaStatus['missing_tables'];
    $status['schema_missing_columns'] = $schemaStatus['missing_columns'];
    $status['schema_has_procedure'] = $schemaStatus['has_procedure'];
    $status['schema_error'] = $schemaStatus['error'];
    if (!empty($schemaStatus['error'])) {
        $status['stored_procedure'] = 'ERROR';
    } elseif ($schemaStatus['has_procedure']) {
        $status['stored_procedure'] = 'OK';
    } else {
        $status['stored_procedure'] = 'MISSING';
    }

    return $status;
}

// 命令列呼叫
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'status';

    switch ($action) {
        case 'install':
            $result = install_database_schema();
            echo $result['message'] . "\n";
            exit($result['success'] ? 0 : 1);

        case 'init':
            if (is_system_initialized()) {
                echo "系統已初始化，無需重複初始化。\n";
                exit(0);
            }

            $result = initialize_system();
            echo $result['message'] . "\n";
            exit($result['success'] ? 0 : 1);

        case 'reset':
            echo "警告：這將刪除所有資料！\n";
            echo "確認重置系統？(輸入 'YES' 確認): ";
            $confirm = trim(fgets(STDIN));

            if ($confirm !== 'YES') {
                echo "操作已取消。\n";
                exit(0);
            }

            $result = reset_system();
            echo $result['message'] . "\n";
            exit($result['success'] ? 0 : 1);

        case 'status':
        default:
            $status = get_system_status();
            echo "系統狀態:\n";
            echo "  資料庫: " . $status['database'] . "\n";
            echo "  組織數量: " . $status['organizations'] . "\n";
            echo "  設定數量: " . $status['settings'] . "\n";
            echo "  使用者數量: " . $status['users'] . "\n";
            echo "  序號數量: " . $status['sequences'] . "\n";
            echo "  儲存過程: " . $status['stored_procedure'] . "\n";
            echo "  資料表狀態: " . ($status['schema_ready'] ? '完整' : '需要處理') . "\n";
            if (!$status['schema_ready']) {
                if (!empty($status['schema_missing_tables'])) {
                    echo "    缺少資料表: " . implode(', ', $status['schema_missing_tables']) . "\n";
                }
                if (!empty($status['schema_missing_columns'])) {
                    echo "    缺少欄位: " . implode(', ', $status['schema_missing_columns']) . "\n";
                }
                if (!empty($status['schema_error'])) {
                    echo "    模式檢查錯誤: " . $status['schema_error'] . "\n";
                }
            }
            echo "  應用版本: " . $status['app_version'] . "\n";
            echo "  PHP版本: " . $status['php_version'] . "\n";
            echo "  已初始化: " . ($status['initialized'] ? '是' : '否') . "\n";
            exit(0);
    }
}

if (php_sapi_name() !== 'cli') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            header('Location: ?message=' . urlencode('無效的請求，請重新操作。') . '&type=danger');
            exit;
        }

        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'install_schema':
                $result = install_database_schema();
                $type = $result['success'] ? 'success' : 'danger';
                header('Location: ?message=' . urlencode($result['message']) . '&type=' . $type);
                exit;

            case 'init_system':
                if (is_system_initialized()) {
                    header('Location: ?message=' . urlencode('系統已初始化，無需重複操作。') . '&type=warning');
                    exit;
                }

                $schemaStatus = get_schema_status();
                if (!$schemaStatus['ready']) {
                    header('Location: ?message=' . urlencode('請先建立或更新資料表，再進行初始化。') . '&type=warning');
                    exit;
                }

                $result = initialize_system();
                $type = $result['success'] ? 'success' : 'danger';
                header('Location: ?message=' . urlencode($result['message']) . '&type=' . $type);
                exit;

            case 'reset_system':
                $result = reset_system();
                $type = $result['success'] ? 'success' : 'danger';
                header('Location: ?message=' . urlencode($result['message']) . '&type=' . $type);
                exit;

            default:
                header('Location: ?message=' . urlencode('無效的操作。') . '&type=danger');
                exit;
        }
    }

    $status = get_system_status();
    $connectionOk = isset($status['database']) && strpos($status['database'], 'OK') === 0;
    $schemaReady = $status['schema_ready'] ?? false;
    $initialized = $status['initialized'] ?? false;
    $csrfToken = generate_csrf_token();

    $missingTables = $status['schema_missing_tables'] ?? [];
    $missingColumns = $status['schema_missing_columns'] ?? [];
    $schemaError = $status['schema_error'] ?? null;
    $hasProcedure = $status['schema_has_procedure'] ?? false;

    ?>
    <!DOCTYPE html>
    <html lang="zh-TW">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>系統初始化精靈 - <?php echo APP_NAME; ?></title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans TC', sans-serif;
                background: #f5f5f5;
                padding: 40px 20px;
            }

            .container {
                max-width: 960px;
                margin: 0 auto;
            }

            .card {
                background: white;
                border-radius: 16px;
                padding: 32px;
                box-shadow: 0 20px 60px rgba(15, 23, 42, 0.15);
                margin-bottom: 24px;
            }

            h1 {
                font-size: 30px;
                color: #1f2937;
                margin-bottom: 12px;
            }

            .subtitle {
                color: #6b7280;
                margin-bottom: 24px;
            }

            code {
                background: #eef2ff;
                color: #4338ca;
                padding: 2px 6px;
                border-radius: 6px;
                font-size: 14px;
            }

            .step {
                margin-bottom: 32px;
                padding-bottom: 24px;
                border-bottom: 1px solid #e5e7eb;
            }

            .step:last-of-type {
                border-bottom: none;
                padding-bottom: 0;
            }

            .step-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                align-items: center;
            }

            .step-actions form {
                margin: 0;
            }

            .step-actions .btn {
                margin-top: 0;
            }

            .step-header {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 12px;
            }

            .step-number {
                width: 36px;
                height: 36px;
                border-radius: 12px;
                background: #e0e7ff;
                color: #4338ca;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                font-size: 18px;
            }

            .step-title {
                font-size: 20px;
                font-weight: 600;
                color: #1f2937;
            }

            .status-chip {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                border-radius: 999px;
                font-size: 14px;
                font-weight: 500;
                background: #e5e7eb;
                color: #374151;
                margin-top: 6px;
            }

            .status-chip.ok {
                background: #dcfce7;
                color: #166534;
            }

            .status-chip.warn {
                background: #fef3c7;
                color: #92400e;
            }

            .status-chip.error {
                background: #fee2e2;
                color: #b91c1c;
            }

            .status-list {
                margin-top: 12px;
                padding-left: 20px;
                color: #4b5563;
                line-height: 1.6;
            }

            .btn {
                padding: 12px 22px;
                border: none;
                border-radius: 10px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
                margin-right: 10px;
                margin-top: 12px;
                font-family: inherit;
            }

            .btn-primary {
                background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
                color: white;
                box-shadow: 0 10px 25px rgba(79, 70, 229, 0.25);
            }

            .btn-secondary {
                background: #e5e7eb;
                color: #374151;
            }

            .btn-danger {
                background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
                color: white;
                box-shadow: 0 10px 25px rgba(234, 88, 12, 0.25);
            }

            .btn:disabled {
                background: #d1d5db;
                color: #7b8190;
                cursor: not-allowed;
                box-shadow: none;
            }

            .alert {
                padding: 14px 18px;
                border-radius: 12px;
                margin-bottom: 20px;
                font-size: 15px;
                line-height: 1.6;
            }

            .alert-success {
                background: #dcfce7;
                border: 1px solid #bbf7d0;
                color: #166534;
            }

            .alert-warning {
                background: #fef3c7;
                border: 1px solid #fde68a;
                color: #92400e;
            }

            .alert-danger {
                background: #fee2e2;
                border: 1px solid #fecaca;
                color: #b91c1c;
            }

            .footer-note {
                margin-top: 24px;
                font-size: 14px;
                color: #6b7280;
            }

            .link {
                color: #4338ca;
                text-decoration: none;
                font-weight: 600;
            }

            .link:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <h1>系統初始化精靈</h1>
                <p class="subtitle">依照步驟完成資料庫建置與預設資料匯入，首次使用即可透過此頁完成部署。</p>

                <?php if (isset($_GET['message'])): ?>
                    <div class="alert alert-<?php echo h($_GET['type'] ?? 'info'); ?>">
                        <?php echo h($_GET['message']); ?>
                    </div>
                <?php endif; ?>

                <!-- Step 1 -->
                <div class="step">
                    <div class="step-header">
                        <div class="step-number">1</div>
                        <div>
                            <div class="step-title">確認資料庫連線</div>
                            <div class="status-chip <?php echo $connectionOk ? 'ok' : 'error'; ?>">
                                <?php
                                if ($connectionOk) {
                                    echo '連線成功';
                                } else {
                                    $message = str_replace('ERROR: ', '', (string)($status['database'] ?? '未知錯誤'));
                                    echo '連線失敗：' . h($message);
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <p class="subtitle">系統會使用 <code><?php echo h(DB_NAME); ?></code> 資料庫，請先在伺服器上建立好資料庫與許可權。</p>
                </div>

                <!-- Step 2 -->
                <div class="step">
                    <div class="step-header">
                        <div class="step-number">2</div>
                        <div>
                            <div class="step-title">建立或更新資料表</div>
                            <div class="status-chip <?php echo $schemaReady ? 'ok' : ($connectionOk ? 'warn' : 'error'); ?>">
                                <?php
                                if ($schemaReady) {
                                    echo '資料表已就緒';
                                } elseif (!$connectionOk) {
                                    echo '尚未連線到資料庫';
                                } else {
                                    echo '需要建立或更新資料表';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!$schemaReady && $connectionOk): ?>
                        <?php if (!empty($missingTables)): ?>
                            <ul class="status-list">
                                <?php foreach ($missingTables as $table): ?>
                                    <li>缺少資料表：<?php echo h($table); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!empty($missingColumns)): ?>
                            <ul class="status-list">
                                <?php foreach ($missingColumns as $column): ?>
                                    <li>缺少欄位：<?php echo h($column); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!$hasProcedure): ?>
                            <ul class="status-list">
                                <li>缺少儲存程式：next_quote_number</li>
                            </ul>
                        <?php endif; ?>
                        <?php if ($schemaError): ?>
                            <ul class="status-list">
                                <li>檢查錯誤：<?php echo h($schemaError); ?></li>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                        <input type="hidden" name="action" value="install_schema">
                        <button type="submit" class="btn btn-primary" <?php echo $connectionOk ? '' : 'disabled'; ?>>
                            建立 / 更新資料表
                        </button>
                    </form>
                </div>

                <!-- Step 3 -->
                <div class="step">
                    <div class="step-header">
                        <div class="step-number">3</div>
                        <div>
                            <div class="step-title">初始化預設資料</div>
                            <div class="status-chip <?php echo $initialized ? 'ok' : ($schemaReady ? 'warn' : 'error'); ?>">
                                <?php
                                if ($initialized) {
                                    echo '系統已完成初始化';
                                } elseif (!$schemaReady) {
                                    echo '請先建立資料表';
                                } else {
                                    echo '尚未初始化';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <p class="subtitle">此步驟會建立預設的組織、系統設定與年度編號序列，並確保折扣欄位等最新結構就緒。</p>

                    <div class="step-actions">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                            <input type="hidden" name="action" value="init_system">
                            <button type="submit" class="btn btn-primary" <?php echo ($schemaReady && !$initialized) ? '' : 'disabled'; ?>>
                                初始化系統資料
                            </button>
                        </form>
                        <a href="?" class="btn btn-secondary">重新整理狀態</a>
                    </div>
                </div>

                <?php if ($initialized): ?>
                    <div class="footer-note">
                        初始化完成！立即前往 <a class="link" href="/login.php">登入頁面</a> 開始使用系統。
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="step-header">
                    <div class="step-number">CLI</div>
                    <div class="step-title">指令模式快速操作</div>
                </div>
                <p class="subtitle">若你習慣使用終端機，也可以透過以下指令完成相同步驟：</p>
                <pre>php init.php status    # 檢視狀態
php init.php install   # 建立 / 更新資料表
php init.php init      # 初始化系統
php init.php reset     # 重置系統（會清空資料）</pre>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
