<?php
/**
 * 登出處理
 * Logout Handler
 *
 * @version v2.0.0
 * @description 使用者登出處理
 * @遵循憲法原則I: 安全優先開發
 */

// 防止直接訪問
define('QUOTABASE_SYSTEM', true);

// 載入配置
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/functions.php';

// 處理登出請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 驗證CSRF令牌
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header('HTTP/1.1 400 Bad Request');
        echo '無效的請求';
        exit;
    }

    // 記錄登出日誌
    $username = $_SESSION['user_name'] ?? 'unknown';
    error_log("User logged out: " . $username . " from " . ($_SERVER['REMOTE_ADDR'] ?? ''));

    // 清除所有會話資料
    $_SESSION = [];

    // 刪除會話cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // 銷燬會話
    session_destroy();

    // 重定向到登入頁
    header('Location: /login.php');
    exit;
} else {
    // GET請求也允許登出（透過連結）
    header('Location: /login.php');
    exit;
}
