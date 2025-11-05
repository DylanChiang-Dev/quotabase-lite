<?php
/**
 * System Initialization
 * 系统初始化脚本
 *
 * @version v2.0.0
 * @description 系统初始化，创建默认数据
 * @遵循宪法原则IV: 极简架构
 */

// 防止直接访问
define('QUOTABASE_SYSTEM', true);

// 加载依赖
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/db.php';

/**
 * 检查系统是否已初始化
 *
 * @return bool 是否已初始化
 */
function is_system_initialized() {
    try {
        // 检查组织表
        $org_count = dbQueryOne("SELECT COUNT(*) as count FROM organizations");
        if ($org_count['count'] == 0) {
            return false;
        }

        // 检查设置表
        $settings_count = dbQueryOne("SELECT COUNT(*) as count FROM settings");
        if ($settings_count['count'] == 0) {
            return false;
        }

        // 检查报价序号表
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
 * 初始化系统数据
 *
 * @return array ['success' => bool, 'message' => string]
 */
function initialize_system() {
    try {
        $db = getDB();
        $db->beginTransaction();

        // 1. 创建默认组织
        $org_exists = dbQueryOne("SELECT id FROM organizations WHERE id = ?", [DEFAULT_ORG_ID]);
        if (!$org_exists) {
            dbExecute(
                "INSERT INTO organizations (id, name) VALUES (?, ?)",
                [DEFAULT_ORG_ID, '默认组织']
            );
            error_log("Created default organization: ID=" . DEFAULT_ORG_ID);
        }

        // 2. 创建默认设置
        $settings_exists = dbQueryOne("SELECT id FROM settings WHERE org_id = ?", [DEFAULT_ORG_ID]);
        if (!$settings_exists) {
            dbExecute(
                "INSERT INTO settings (org_id, company_name, company_address, company_contact, quote_prefix, default_tax_rate, timezone) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    DEFAULT_ORG_ID,
                    '您的公司名称',
                    '公司地址',
                    '联系电话',
                    'Q',
                    5.00,
                    'Asia/Taipei'
                ]
            );
            error_log("Created default settings for org_id=" . DEFAULT_ORG_ID);
        }

        // 3. 初始化报价序号表
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
            'message' => '系统初始化完成！'
        ];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }

        error_log("System initialization failed: " . $e->getMessage());

        return [
            'success' => false,
            'message' => '系统初始化失败: ' . $e->getMessage()
        ];
    }
}

/**
 * 重置系统数据（谨慎使用）
 *
 * @return array
 */
function reset_system() {
    try {
        $db = getDB();
        $db->beginTransaction();

        // 删除所有数据（保留表结构）
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
            'message' => '系统重置完成！'
        ];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }

        return [
            'success' => false,
            'message' => '系统重置失败: ' . $e->getMessage()
        ];
    }
}

/**
 * 获取系统状态信息
 *
 * @return array 系统状态
 */
function get_system_status() {
    $status = [];

    // 检查数据库连接
    try {
        $pdo = getDB()->getConnection();
        $status['database'] = 'OK';
    } catch (Exception $e) {
        $status['database'] = 'ERROR: ' . $e->getMessage();
    }

    // 检查组织数据
    try {
        $org_count = dbQueryOne("SELECT COUNT(*) as count FROM organizations");
        $status['organizations'] = $org_count['count'];
    } catch (Exception $e) {
        $status['organizations'] = 'ERROR';
    }

    // 检查设置数据
    try {
        $settings_count = dbQueryOne("SELECT COUNT(*) as count FROM settings");
        $status['settings'] = $settings_count['count'];
    } catch (Exception $e) {
        $status['settings'] = 'ERROR';
    }

    // 检查报价序号数据
    try {
        $sequence_count = dbQueryOne("SELECT COUNT(*) as count FROM quote_sequences");
        $status['sequences'] = $sequence_count['count'];
    } catch (Exception $e) {
        $status['sequences'] = 'ERROR';
    }

    // 检查存储过程
    try {
        $result = dbQueryOne("SHOW CREATE PROCEDURE next_quote_number");
        $status['stored_procedure'] = isset($result) ? 'OK' : 'MISSING';
    } catch (Exception $e) {
        $status['stored_procedure'] = 'ERROR';
    }

    // 系统版本信息
    $status['app_version'] = APP_VERSION;
    $status['php_version'] = PHP_VERSION;
    $status['initialized'] = is_system_initialized();

    return $status;
}

// 命令行调用
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'status';

    switch ($action) {
        case 'init':
            if (is_system_initialized()) {
                echo "系统已初始化，无需重复初始化。\n";
                exit(0);
            }

            $result = initialize_system();
            echo $result['message'] . "\n";
            exit($result['success'] ? 0 : 1);

        case 'reset':
            echo "警告：这将删除所有数据！\n";
            echo "确认重置系统？(输入 'YES' 确认): ";
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
            echo "系统状态:\n";
            echo "  数据库: " . $status['database'] . "\n";
            echo "  组织数量: " . $status['organizations'] . "\n";
            echo "  设置数量: " . $status['settings'] . "\n";
            echo "  序号数量: " . $status['sequences'] . "\n";
            echo "  存储过程: " . $status['stored_procedure'] . "\n";
            echo "  应用版本: " . $status['app_version'] . "\n";
            echo "  PHP版本: " . $status['php_version'] . "\n";
            echo "  已初始化: " . ($status['initialized'] ? '是' : '否') . "\n";
            exit(0);
    }
}

// Web调用
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 显示系统状态页面
    $status = get_system_status();

    ?>
    <!DOCTYPE html>
    <html lang="zh-TW">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>系统初始化 - <?php echo APP_NAME; ?></title>
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
                max-width: 800px;
                margin: 0 auto;
            }

            .card {
                background: white;
                border-radius: 12px;
                padding: 30px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                margin-bottom: 20px;
            }

            h1 {
                color: #333;
                margin-bottom: 20px;
                font-size: 28px;
            }

            h2 {
                color: #555;
                margin-bottom: 15px;
                font-size: 20px;
            }

            .status-item {
                display: flex;
                justify-content: space-between;
                padding: 12px 0;
                border-bottom: 1px solid #eee;
            }

            .status-item:last-child {
                border-bottom: none;
            }

            .status-label {
                color: #666;
                font-weight: 500;
            }

            .status-value {
                color: #333;
            }

            .status-ok {
                color: #28a745;
            }

            .status-error {
                color: #dc3545;
            }

            .btn {
                padding: 12px 24px;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                margin-right: 10px;
                margin-top: 10px;
            }

            .btn-primary {
                background: #007bff;
                color: white;
            }

            .btn-success {
                background: #28a745;
                color: white;
            }

            .btn-danger {
                background: #dc3545;
                color: white;
            }

            .alert {
                padding: 12px 16px;
                border-radius: 8px;
                margin-bottom: 20px;
            }

            .alert-success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }

            .alert-warning {
                background: #fff3cd;
                color: #856404;
                border: 1px solid #ffeaa7;
            }

            .alert-danger {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }

            pre {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
                overflow-x: auto;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <h1>系统初始化</h1>

                <?php if (isset($_GET['message'])): ?>
                    <div class="alert alert-<?php echo $_GET['type'] ?? 'info'; ?>">
                        <?php echo htmlspecialchars($_GET['message']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($status['initialized']): ?>
                    <div class="alert alert-success">
                        系统已成功初始化，所有组件正常工作。
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        系统尚未初始化，请点击下方按钮进行初始化。
                    </div>
                <?php endif; ?>

                <h2>系统状态</h2>
                <div class="status-item">
                    <span class="status-label">数据库连接</span>
                    <span class="status-value <?php echo strpos($status['database'], 'OK') === 0 ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $status['database']; ?>
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">组织数量</span>
                    <span class="status-value"><?php echo $status['organizations']; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label">设置数量</span>
                    <span class="status-value"><?php echo $status['settings']; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label">序号数量</span>
                    <span class="status-value"><?php echo $status['sequences']; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label">存储过程</span>
                    <span class="status-value <?php echo $status['stored_procedure'] === 'OK' ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $status['stored_procedure']; ?>
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">应用版本</span>
                    <span class="status-value"><?php echo $status['app_version']; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label">PHP版本</span>
                    <span class="status-value"><?php echo $status['php_version']; ?></span>
                </div>

                <?php if (!$status['initialized']): ?>
                    <a href="?action=init" class="btn btn-primary" onclick="return confirm('确定要初始化系统吗？')">
                        初始化系统
                    </a>
                <?php else: ?>
                    <a href="/login.php" class="btn btn-success">
                        进入系统
                    </a>
                <?php endif; ?>

                <a href="?action=status&refresh=1" class="btn">
                    刷新状态
                </a>
            </div>

            <div class="card">
                <h2>命令行使用</h2>
                <p>您也可以通过命令行运行初始化脚本：</p>
                <pre>php init.php status   # 查看状态
php init.php init    # 初始化系统
php init.php reset   # 重置系统（谨慎使用）</pre>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 处理初始化请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'init':
            if (is_system_initialized()) {
                header('Location: ?message=' . urlencode('系统已初始化，无需重复初始化。') . '&type=warning');
                exit;
            }

            $result = initialize_system();
            $type = $result['success'] ? 'success' : 'danger';
            header('Location: ?message=' . urlencode($result['message']) . '&type=' . $type);
            exit;

        case 'reset':
            // 简单的CSRF保护
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                header('Location: ?message=' . urlencode('无效的请求') . '&type=danger');
                exit;
            }

            $result = reset_system();
            $type = $result['success'] ? 'success' : 'danger';
            header('Location: ?message=' . urlencode($result['message']) . '&type=' . $type);
            exit;

        default:
            header('Location: ?message=' . urlencode('无效的操作') . '&type=danger');
            exit;
    }
}
?>
