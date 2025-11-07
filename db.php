<?php
/**
 * Database Connection Module
 * 資料庫連線模組
 *
 * @version v2.0.0
 * @description 獨立的資料庫連線模組，提供PDO連線和錯誤處理
 * @遵循憲法原則IV: 極簡架構
 */

// 防止直接訪問
if (!defined('QUOTABASE_SYSTEM')) {
    // 如果系統未定義，則嘗試載入配置
    if (file_exists(__DIR__ . '/config.php')) {
        require_once __DIR__ . '/config.php';
    } elseif (file_exists(__DIR__ . '/config.php.sample')) {
        die('請先複製 config.php.sample 為 config.php 並配置資料庫連線資訊。');
    } else {
        die('配置檔案不存在。');
    }
}

/**
 * 資料庫連線類
 */
class Database {
    private static $instance = null;
    private $pdo;

    /**
     * 私有建構函式，實現單例模式
     */
    private function __construct() {
        $this->connect();
    }

    /**
     * 獲取資料庫例項（單例模式）
     *
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 建立資料庫連線
     *
     * @throws PDOException 連線失敗時丟擲異常
     */
    private function connect() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

        $options = [
            // 異常模式：出錯時丟擲異常而不是靜默失敗
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

            // 預設獲取模式：返回關聯陣列
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // 停用模擬預處理：使用真實的PDO預處理語句
            PDO::ATTR_EMULATE_PREPARES   => false,

            // 啟用字元編碼設定
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,

            // 設定超時時間（秒）
            PDO::ATTR_TIMEOUT            => 30,
        ];

        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        $isCli = php_sapi_name() === 'cli';

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // 記錄連線成功資訊（僅開發環境）
            if ($isCli || $serverName === 'localhost' || isset($_GET['debug'])) {
                error_log("[DEBUG] Database connected successfully at " . date('Y-m-d H:i:s'));
            }

        } catch (PDOException $e) {
            // 記錄錯誤日誌
            $error_msg = "Database connection failed: " . $e->getMessage();
            error_log($error_msg);

            // 生產環境：顯示友好錯誤資訊
            if (!$isCli && $serverName !== 'localhost' && !isset($_GET['debug'])) {
                die('系統維護中，請稍後再試。');
            } else {
                // 開發環境：顯示詳細錯誤
                die('資料庫連線失敗: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
            }
        }
    }

    /**
     * 獲取PDO例項
     *
     * @return PDO
     */
    public function getConnection() {
        return $this->pdo;
    }

    /**
     * 執行查詢並返回結果
     *
     * @param string $sql SQL語句
     * @param array $params 引數陣列
     * @return array 查詢結果
     * @throws PDOException 查詢失敗時丟擲異常
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * 執行查詢並返回單行結果
     *
     * @param string $sql SQL語句
     * @param array $params 引數陣列
     * @return array|null 單行結果或null
     * @throws PDOException 查詢失敗時丟擲異常
     */
    public function queryOne($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * 執行插入/更新/刪除操作
     *
     * @param string $sql SQL語句
     * @param array $params 引數陣列
     * @return int 影響的行數
     * @throws PDOException 執行失敗時丟擲異常
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Execute failed: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * 獲取最後插入的ID
     *
     * @return string 最後插入的ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * 開始事務
     *
     * @return bool
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * 提交事務
     *
     * @return bool
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * 回滾事務
     *
     * @return bool
     */
    public function rollback() {
        return $this->pdo->rollback();
    }

    /**
     * 檢查是否在事務中
     *
     * @return bool
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }

    /**
     * 呼叫儲存過程
     *
     * @param string $procedure 儲存過程名
     * @param array $params 引數陣列（按引用傳遞）
     * @return mixed 儲存過程的返回值
     * @throws PDOException 呼叫失敗時丟擲異常
     */
    public function callProcedure($procedure, &$params = []) {
        try {
            $placeholders = str_repeat('?,', count($params) - 1) . '?';
            $sql = "CALL {$procedure}({$placeholders})";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            // 獲取輸出引數值
            $result = [];
            $outParams = [];
            foreach ($params as $key => &$param) {
                if (is_string($param) && strlen($param) === 64 && ctype_xdigit($param)) {
                    // 檢測到可能是輸出引數（64位十六進位制字串）
                    $outParams[] = $param;
                }
            }

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Call procedure failed: " . $e->getMessage() . " | Procedure: " . $procedure);
            throw $e;
        }
    }

    /**
     * 克隆方法私有化，防止克隆
     */
    private function __clone() {}

    /**
     * 反序列化方法私有化，防止反序列化
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * 便捷函式：獲取資料庫例項
 *
 * @return Database
 */
function getDB() {
    return Database::getInstance();
}

/**
 * 便捷函式：執行SQL查詢
 *
 * @param string $sql
 * @param array $params
 * @return array
 */
function dbQuery($sql, $params = []) {
    return getDB()->query($sql, $params);
}

/**
 * 便捷函式：執行SQL查詢（返回單行）
 *
 * @param string $sql
 * @param array $params
 * @return array|null
 */
function dbQueryOne($sql, $params = []) {
    return getDB()->queryOne($sql, $params);
}

/**
 * 便捷函式：執行SQL語句
 *
 * @param string $sql
 * @param array $params
 * @return int
 */
function dbExecute($sql, $params = []) {
    return getDB()->execute($sql, $params);
}

/**
 * 便捷函式：呼叫儲存過程
 *
 * @param string $procedure
 * @param array $params
 * @return mixed
 */
function dbCallProcedure($procedure, &$params = []) {
    return getDB()->callProcedure($procedure, $params);
}

/**
 * 便捷函式：獲取最後插入的ID
 *
 * @return string
 */
function dbLastInsertId() {
    return getDB()->lastInsertId();
}

// ========================================
// 安全驗證
// ========================================

// 檢查是否已載入配置
if (!defined('DB_HOST')) {
    if ($_SERVER['SERVER_NAME'] === 'localhost' || isset($_GET['debug'])) {
        die('錯誤：資料庫配置未定義。請檢查 config.php 檔案。');
    } else {
        die('系統配置錯誤。');
    }
}

// ========================================
// 使用示例 (Usage Examples)
// ========================================

/*
// 1. 獲取資料庫例項
$db = getDB();

// 2. 執行查詢
$customers = $db->query("SELECT * FROM customers WHERE active = ?", [1]);

// 3. 查詢單行
$customer = $db->queryOne("SELECT * FROM customers WHERE id = ?", [123]);

// 4. 執行插入
$db->execute(
    "INSERT INTO customers (name, email, org_id) VALUES (?, ?, ?)",
    ['張三', 'zhangsan@example.com', 1]
);

// 5. 使用事務
$db->beginTransaction();
try {
    $db->execute("INSERT INTO quotes (number, customer_id, ...) VALUES (?, ?, ...)", [...]);
    $db->execute("INSERT INTO quote_items (...) VALUES (...)", [...]);
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    throw $e;
}

// 6. 呼叫儲存過程
$params = [1, '']; // org_id=1, 輸出引數
dbCallProcedure('next_quote_number', $params);
$quoteNumber = $params[1]; // 獲取生成的編號
*/

?>
