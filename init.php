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

if (!defined('SKIP_INIT_REDIRECT')) {
    define('SKIP_INIT_REDIRECT', true);
}

$configPath = __DIR__ . '/config.php';

if (!file_exists($configPath)) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="zh-TW">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>系統初始化 - 缺少設定檔</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans TC', sans-serif;
                background: #f5f5f5;
                margin: 0;
                padding: 40px 20px;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                padding: 30px;
            }
            h1 {
                font-size: 24px;
                margin-bottom: 16px;
                color: #333;
            }
            p {
                color: #555;
                line-height: 1.6;
                margin-bottom: 12px;
            }
            code {
                background: #f0f0f0;
                padding: 4px 6px;
                border-radius: 4px;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>缺少設定檔</h1>
            <p>找不到 <code>config.php</code> 檔案。請先將 <code>config.php.sample</code> 複製為 <code>config.php</code>，並填入資料庫連線等必要資訊。</p>
            <p>完成設定後重新整理本頁，即可繼續進行初始化流程。</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 加载依赖
require_once $configPath;
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
        'quote_items' => 'quote_items（報價項目）',
        'quote_sequences' => 'quote_sequences（年度序號）',
        'settings' => 'settings（系統設定）',
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
 * 判斷儲存程序是否存在
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
 * 判斷外鍵是否存在
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
                name VARCHAR(255) NOT NULL COMMENT '组织名称',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                INDEX idx_org_name (name)
            ) ENGINE=InnoDB COMMENT='组织表（预留多租户）'",

            "CREATE TABLE IF NOT EXISTS customers (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '组织ID',
                name VARCHAR(255) NOT NULL COMMENT '客户名称（必填）',
                tax_id VARCHAR(50) NULL COMMENT '税务登记号',
                email VARCHAR(255) NULL COMMENT '邮箱',
                phone VARCHAR(50) NULL COMMENT '电话',
                billing_address TEXT NULL COMMENT '账单地址',
                shipping_address TEXT NULL COMMENT '收货地址',
                note TEXT NULL COMMENT '备注',
                active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '软删除标记（1=激活，0=禁用）',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                INDEX idx_customers_org_id (org_id),
                INDEX idx_customers_active (active),
                INDEX idx_customers_name (name)
            ) ENGINE=InnoDB COMMENT='客户表'",

            "CREATE TABLE IF NOT EXISTS catalog_categories (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '组织ID',
                type ENUM('product', 'service') NOT NULL DEFAULT 'product' COMMENT '分类类型',
                parent_id BIGINT UNSIGNED NULL COMMENT '父级分类ID（NULL为顶级）',
                level TINYINT UNSIGNED NOT NULL COMMENT '分类层级（1-3）',
                name VARCHAR(100) NOT NULL COMMENT '分类名称',
                sort_order INT NOT NULL DEFAULT 0 COMMENT '排序值',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                UNIQUE KEY uq_category_name (org_id, type, parent_id, name),
                INDEX idx_category_org_type (org_id, type),
                INDEX idx_category_parent (parent_id),
                INDEX idx_category_level (org_id, level),
                CONSTRAINT fk_category_parent FOREIGN KEY (parent_id) REFERENCES catalog_categories(id) ON DELETE CASCADE
            ) ENGINE=InnoDB COMMENT='产品/服务分类表'",

            "CREATE TABLE IF NOT EXISTS catalog_items (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '组织ID',
                type ENUM('product', 'service') NOT NULL COMMENT '类型：产品或服务',
                sku VARCHAR(100) NOT NULL COMMENT 'SKU编码（同一org_id下唯一）',
                name VARCHAR(255) NOT NULL COMMENT '名称（必填）',
                unit VARCHAR(20) NOT NULL DEFAULT 'pcs' COMMENT '单位',
                currency VARCHAR(3) NOT NULL DEFAULT 'TWD' COMMENT '币种（仅支持TWD）',
                unit_price_cents BIGINT UNSIGNED NOT NULL COMMENT '单价（单位：分）',
                tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '税率（%）',
                category_id BIGINT UNSIGNED NULL COMMENT '分类ID（三级分类）',
                active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态（1=启用，0=禁用）',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                INDEX idx_catalog_org_type (org_id, type),
                INDEX idx_catalog_sku (org_id, sku),
                INDEX idx_catalog_active (active),
                INDEX idx_catalog_category (category_id),
                UNIQUE KEY uq_catalog_sku (org_id, sku)
            ) ENGINE=InnoDB COMMENT='目录项表（产品/服务统一）'",

            "CREATE TABLE IF NOT EXISTS quotes (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '组织ID',
                quote_number VARCHAR(50) NOT NULL COMMENT '报价单编号（格式：前缀-YYYY-000001）',
                customer_id BIGINT UNSIGNED NOT NULL COMMENT '客户ID（外键）',
                issue_date DATE NOT NULL COMMENT '发出日期（UTC）',
                valid_until DATE NULL COMMENT '有效期至（UTC）',
                currency VARCHAR(3) NOT NULL DEFAULT 'TWD' COMMENT '币种（仅支持TWD）',
                status ENUM('draft', 'sent', 'accepted', 'rejected', 'expired') NOT NULL DEFAULT 'draft' COMMENT '状态',
                title VARCHAR(255) NULL COMMENT '报价单标题',
                notes TEXT NULL COMMENT '备注',
                subtotal_cents BIGINT UNSIGNED NOT NULL COMMENT '小计（分）',
                tax_cents BIGINT UNSIGNED NOT NULL COMMENT '税额（分）',
                total_cents BIGINT UNSIGNED NOT NULL COMMENT '总计（分）',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                INDEX idx_quotes_org_customer_date (org_id, customer_id, issue_date),
                INDEX idx_quotes_number (quote_number),
                INDEX idx_quotes_status (status),
                INDEX idx_quotes_issue_date (issue_date),
                UNIQUE KEY uq_quotes_number (quote_number)
            ) ENGINE=InnoDB COMMENT='报价单主档表'",

            "CREATE TABLE IF NOT EXISTS quote_items (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                quote_id BIGINT UNSIGNED NOT NULL COMMENT '报价单ID（外键）',
                catalog_item_id BIGINT UNSIGNED NULL COMMENT '关联的目录项ID（可为空）',
                description VARCHAR(500) NOT NULL COMMENT '项目描述',
                qty DECIMAL(18,4) NOT NULL COMMENT '数量（精确到0.0001）',
                unit VARCHAR(20) NULL COMMENT '单位',
                unit_price_cents BIGINT UNSIGNED NOT NULL COMMENT '单价（分）',
                discount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '行折扣金额（分）',
                tax_rate DECIMAL(5,2) NOT NULL COMMENT '税率（%）',
                line_subtotal_cents BIGINT UNSIGNED NOT NULL COMMENT '行小计（分）',
                line_tax_cents BIGINT UNSIGNED NOT NULL COMMENT '行税额（分）',
                line_total_cents BIGINT UNSIGNED NOT NULL COMMENT '行总计（分）',
                line_order INT NOT NULL COMMENT '行顺序（排序用）',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                INDEX idx_quote_items_quote_id (quote_id),
                INDEX idx_quote_items_order (quote_id, line_order),
                CONSTRAINT fk_quote_items_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
            ) ENGINE=InnoDB COMMENT='报价项目明细表'",

            "CREATE TABLE IF NOT EXISTS quote_sequences (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '组织ID（每个组织一条记录）',
                prefix VARCHAR(10) NOT NULL DEFAULT 'Q' COMMENT '编号前缀',
                year INT NOT NULL COMMENT '年度',
                current_number INT NOT NULL DEFAULT 0 COMMENT '当前编号',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                UNIQUE KEY uq_quote_sequences_org_year (org_id, year)
            ) ENGINE=InnoDB COMMENT='年度编号序列表'",

            "CREATE TABLE IF NOT EXISTS settings (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '组织ID（每个组织一条记录）',
                company_name VARCHAR(255) NULL COMMENT '公司名称',
                company_address TEXT NULL COMMENT '公司地址',
                company_contact VARCHAR(255) NULL COMMENT '公司联系方式',
                quote_prefix VARCHAR(10) NOT NULL DEFAULT 'Q' COMMENT '报价单编号前缀',
                default_tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '默认税率（%）',
                print_terms TEXT NULL COMMENT '打印条款文字',
                timezone VARCHAR(50) NOT NULL DEFAULT 'Asia/Taipei' COMMENT '时区',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                UNIQUE KEY uq_settings_org_id (org_id)
            ) ENGINE=InnoDB COMMENT='系统设置表'",
        ];

        foreach ($tableStatements as $sql) {
            $pdo->exec($sql);
        }

        // 确保外键存在
        if (!foreign_key_exists($pdo, 'quotes', 'fk_quotes_customer')) {
            $pdo->exec("ALTER TABLE quotes
                ADD CONSTRAINT fk_quotes_customer
                FOREIGN KEY (customer_id) REFERENCES customers(id)");
        }

        // 更新既有欄位
        ensure_schema_upgrades($pdo);

        // 重新建立儲存程序
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
    if (table_exists($pdo, 'quote_items') && !column_exists($pdo, 'quote_items', 'discount_cents')) {
        $pdo->exec("ALTER TABLE quote_items
            ADD COLUMN discount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '行折扣金额（分）' AFTER unit_price_cents");
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

    // 系统版本信息
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

// 命令行调用
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'status';

    switch ($action) {
        case 'install':
            $result = install_database_schema();
            echo $result['message'] . "\n";
            exit($result['success'] ? 0 : 1);

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
            echo "  应用版本: " . $status['app_version'] . "\n";
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
                    <p class="subtitle">系統會使用 <code><?php echo h(DB_NAME); ?></code> 資料庫，請先在伺服器上建立好資料庫與權限。</p>
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
                                <li>缺少儲存程序：next_quote_number</li>
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
                <pre>php init.php status    # 查看狀態
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
