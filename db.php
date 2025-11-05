<?php
/**
 * Database Connection Module
 * 数据库连接模块
 *
 * @version v2.0.0
 * @description 独立的数据库连接模块，提供PDO连接和错误处理
 * @遵循宪法原则IV: 极简架构
 */

// 防止直接访问
if (!defined('QUOTABASE_SYSTEM')) {
    // 如果系统未定义，则尝试加载配置
    if (file_exists(__DIR__ . '/config.php')) {
        require_once __DIR__ . '/config.php';
    } elseif (file_exists(__DIR__ . '/config.php.sample')) {
        die('请先复制 config.php.sample 为 config.php 并配置数据库连接信息。');
    } else {
        die('配置文件不存在。');
    }
}

/**
 * 数据库连接类
 */
class Database {
    private static $instance = null;
    private $pdo;

    /**
     * 私有构造函数，实现单例模式
     */
    private function __construct() {
        $this->connect();
    }

    /**
     * 获取数据库实例（单例模式）
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
     * 建立数据库连接
     *
     * @throws PDOException 连接失败时抛出异常
     */
    private function connect() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

        $options = [
            // 异常模式：出错时抛出异常而不是静默失败
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

            // 默认获取模式：返回关联数组
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // 禁用模拟预处理：使用真实的PDO预处理语句
            PDO::ATTR_EMULATE_PREPARES   => false,

            // 启用字符编码设置
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,

            // 设置超时时间（秒）
            PDO::ATTR_TIMEOUT            => 30,
        ];

        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        $isCli = php_sapi_name() === 'cli';

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // 记录连接成功信息（仅开发环境）
            if ($isCli || $serverName === 'localhost' || isset($_GET['debug'])) {
                error_log("[DEBUG] Database connected successfully at " . date('Y-m-d H:i:s'));
            }

        } catch (PDOException $e) {
            // 记录错误日志
            $error_msg = "Database connection failed: " . $e->getMessage();
            error_log($error_msg);

            // 生产环境：显示友好错误信息
            if (!$isCli && $serverName !== 'localhost' && !isset($_GET['debug'])) {
                die('系统维护中，请稍后再试。');
            } else {
                // 开发环境：显示详细错误
                die('数据库连接失败: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
            }
        }
    }

    /**
     * 获取PDO实例
     *
     * @return PDO
     */
    public function getConnection() {
        return $this->pdo;
    }

    /**
     * 执行查询并返回结果
     *
     * @param string $sql SQL语句
     * @param array $params 参数数组
     * @return array 查询结果
     * @throws PDOException 查询失败时抛出异常
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
     * 执行查询并返回单行结果
     *
     * @param string $sql SQL语句
     * @param array $params 参数数组
     * @return array|null 单行结果或null
     * @throws PDOException 查询失败时抛出异常
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
     * 执行插入/更新/删除操作
     *
     * @param string $sql SQL语句
     * @param array $params 参数数组
     * @return int 影响的行数
     * @throws PDOException 执行失败时抛出异常
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
     * 获取最后插入的ID
     *
     * @return string 最后插入的ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * 开始事务
     *
     * @return bool
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * 提交事务
     *
     * @return bool
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * 回滚事务
     *
     * @return bool
     */
    public function rollback() {
        return $this->pdo->rollback();
    }

    /**
     * 检查是否在事务中
     *
     * @return bool
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }

    /**
     * 调用存储过程
     *
     * @param string $procedure 存储过程名
     * @param array $params 参数数组（按引用传递）
     * @return mixed 存储过程的返回值
     * @throws PDOException 调用失败时抛出异常
     */
    public function callProcedure($procedure, &$params = []) {
        try {
            $placeholders = str_repeat('?,', count($params) - 1) . '?';
            $sql = "CALL {$procedure}({$placeholders})";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            // 获取输出参数值
            $result = [];
            $outParams = [];
            foreach ($params as $key => &$param) {
                if (is_string($param) && strlen($param) === 64 && ctype_xdigit($param)) {
                    // 检测到可能是输出参数（64位十六进制字符串）
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
 * 便捷函数：获取数据库实例
 *
 * @return Database
 */
function getDB() {
    return Database::getInstance();
}

/**
 * 便捷函数：执行SQL查询
 *
 * @param string $sql
 * @param array $params
 * @return array
 */
function dbQuery($sql, $params = []) {
    return getDB()->query($sql, $params);
}

/**
 * 便捷函数：执行SQL查询（返回单行）
 *
 * @param string $sql
 * @param array $params
 * @return array|null
 */
function dbQueryOne($sql, $params = []) {
    return getDB()->queryOne($sql, $params);
}

/**
 * 便捷函数：执行SQL语句
 *
 * @param string $sql
 * @param array $params
 * @return int
 */
function dbExecute($sql, $params = []) {
    return getDB()->execute($sql, $params);
}

/**
 * 便捷函数：调用存储过程
 *
 * @param string $procedure
 * @param array $params
 * @return mixed
 */
function dbCallProcedure($procedure, &$params = []) {
    return getDB()->callProcedure($procedure, $params);
}

/**
 * 便捷函数：获取最后插入的ID
 *
 * @return string
 */
function dbLastInsertId() {
    return getDB()->lastInsertId();
}

// ========================================
// 安全验证
// ========================================

// 检查是否已加载配置
if (!defined('DB_HOST')) {
    if ($_SERVER['SERVER_NAME'] === 'localhost' || isset($_GET['debug'])) {
        die('错误：数据库配置未定义。请检查 config.php 文件。');
    } else {
        die('系统配置错误。');
    }
}

// ========================================
// 使用示例 (Usage Examples)
// ========================================

/*
// 1. 获取数据库实例
$db = getDB();

// 2. 执行查询
$customers = $db->query("SELECT * FROM customers WHERE active = ?", [1]);

// 3. 查询单行
$customer = $db->queryOne("SELECT * FROM customers WHERE id = ?", [123]);

// 4. 执行插入
$db->execute(
    "INSERT INTO customers (name, email, org_id) VALUES (?, ?, ?)",
    ['张三', 'zhangsan@example.com', 1]
);

// 5. 使用事务
$db->beginTransaction();
try {
    $db->execute("INSERT INTO quotes (number, customer_id, ...) VALUES (?, ?, ...)", [...]);
    $db->execute("INSERT INTO quote_items (...) VALUES (...)", [...]);
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    throw $e;
}

// 6. 调用存储过程
$params = [1, '']; // org_id=1, 输出参数
dbCallProcedure('next_quote_number', $params);
$quoteNumber = $params[1]; // 获取生成的编号
*/

?>
