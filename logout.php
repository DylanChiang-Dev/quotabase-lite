<?php
/**
 * 登出处理
 * Logout Handler
 *
 * @version v2.0.0
 * @description 用户登出处理
 * @遵循宪法原则I: 安全优先开发
 */

// 防止直接访问
define('QUOTABASE_SYSTEM', true);

// 加载配置
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/functions.php';

// 处理登出请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header('HTTP/1.1 400 Bad Request');
        echo '无效的请求';
        exit;
    }

    // 记录登出日志
    $username = $_SESSION['user_name'] ?? 'unknown';
    error_log("User logged out: " . $username . " from " . ($_SERVER['REMOTE_ADDR'] ?? ''));

    // 清除所有会话数据
    $_SESSION = [];

    // 删除会话cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // 销毁会话
    session_destroy();

    // 重定向到登录页
    header('Location: /login.php');
    exit;
} else {
    // GET请求也允许登出（通过链接）
    header('Location: /login.php');
    exit;
}
