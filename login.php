<?php
/**
 * 登录页面
 * Login Page
 *
 * @version v2.0.0
 * @description 用户登录认证页面
 * @遵循宪法原则I: 安全优先开发
 */

// 防止直接访问
define('QUOTABASE_SYSTEM', true);

// 加载配置和工具函数
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/db.php';

// 如果已登录，重定向到首页
if (is_logged_in()) {
    header('Location: /');
    exit;
}

$error = '';
$success = '';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '无效的请求，请重新提交。';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // 验证输入
        if (empty($username) || empty($password)) {
            $error = '请输入用户名和密码。';
        } elseif (!validate_string_length($username, 50, 1)) {
            $error = '用户名长度不正确。';
        } elseif (!validate_string_length($password, 100, 6)) {
            $error = '密码长度至少6位。';
        } else {
            try {
                $user = get_user_by_username($username);

                if (!$user) {
                    $error = '用户名或密码错误。';
                    error_log("Failed login attempt (not found): " . $username . " from " . ($_SERVER['REMOTE_ADDR'] ?? ''));
                } elseif ($user['status'] !== 'active') {
                    $error = '账号已被停用，请联系管理员。';
                    error_log("Blocked login attempt (suspended): " . $username . " from " . ($_SERVER['REMOTE_ADDR'] ?? ''));
                } elseif (!password_verify($password, $user['password_hash'])) {
                    $error = '用户名或密码错误。';
                    error_log("Failed login attempt (bad password): " . $username . " from " . ($_SERVER['REMOTE_ADDR'] ?? ''));
                } else {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['user_name'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['org_id'] = (int)($user['org_id'] ?? DEFAULT_ORG_ID);
                    $_SESSION['login_time'] = time();
                    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';

                    update_user_login_activity($user['id'], $_SESSION['ip_address']);

                    error_log("User logged in: " . $username . " from " . $_SESSION['ip_address']);

                    header('Location: /');
                    exit;
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $error = '登录失败，请稍后再试。';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - <?php echo APP_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans TC', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }

        .login-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .login-form {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .error-message {
            color: #dc3545;
            font-size: 14px;
            margin-top: 8px;
            padding: 8px 12px;
            background: #ffe6e6;
            border-radius: 6px;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 14px;
        }

        .demo-info {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 12px;
            margin-top: 20px;
            font-size: 14px;
            color: #004085;
        }

        .demo-info strong {
            display: block;
            margin-bottom: 4px;
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 10px;
            }

            .login-form {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><?php echo APP_NAME; ?></h1>
            <p>集成报价管理系统</p>
        </div>

        <form class="login-form" method="POST" action="/login.php">
            <?php echo csrf_input(); ?>

            <?php if ($error): ?>
                <div class="error-message"><?php echo h($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message" style="color: #28a745; margin-bottom: 16px; padding: 8px 12px; background: #e6ffe6; border-radius: 6px;">
                    <?php echo h($success); ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label" for="username">用户名</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    class="form-control"
                    placeholder="请输入用户名"
                    required
                    maxlength="50"
                    value="<?php echo h($_POST['username'] ?? ''); ?>"
                    autocomplete="username"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="password">密码</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="请输入密码"
                    required
                    minlength="6"
                    maxlength="100"
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn-login">
                登录系统
            </button>

            <div class="demo-info">
                <strong>演示账号</strong>
                用户名: admin<br>
                密码: admin123
            </div>
        </form>

        <div class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
            <p style="margin-top: 5px; font-size: 12px;">v<?php echo APP_VERSION; ?></p>
        </div>
    </div>
</body>
</html>
