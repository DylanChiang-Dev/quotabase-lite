<?php
/**
 * Account Security Settings
 * 帳號安全設定頁
 *
 * @version v2.1.0
 * @description 允許登入使用者更新電子郵件與登入密碼
 */

define('QUOTABASE_SYSTEM', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../partials/ui.php';

if (!is_logged_in()) {
    header('Location: /login.php');
    exit;
}

$userId = get_current_user_id();
$user = get_user_by_id($userId);

if (!$user) {
    die('找不到使用者資料。');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '無效的請求，請重新嘗試。';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $changes = [];

        if ($username === '') {
            $error = '登入帳號不可為空。';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            $error = '帳號需為3-50字的英數字或底線組合。';
        } elseif ($username !== $user['username']) {
            $existing = get_user_by_username($username);
            if ($existing && (int)$existing['id'] !== (int)$user['id']) {
                $error = '帳號已被使用，請選擇其他名稱。';
            } else {
                $changes['username'] = $username;
            }
        }

        if ($email !== ($user['email'] ?? '')) {
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = '請輸入有效的電子郵件。';
            } else {
                $changes['email'] = $email !== '' ? $email : null;
            }
        }

        $changePassword = ($currentPassword !== '') || ($newPassword !== '') || ($confirmPassword !== '');

        if (!$error && $changePassword) {
            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                $error = '請完整填寫目前密碼與新密碼欄位。';
            } elseif (!password_verify($currentPassword, $user['password_hash'])) {
                $error = '目前密碼不正確。';
            } elseif ($newPassword !== $confirmPassword) {
                $error = '兩次輸入的新密碼不一致。';
            } else {
                $strength = validate_password_strength($newPassword);
                if (!$strength['valid']) {
                    $error = $strength['message'];
                }
            }
        }

        if (!$error && !empty($changes)) {
            update_user_profile($userId, $changes);
            if (isset($changes['username'])) {
                $_SESSION['user_name'] = $changes['username'];
            }
        }

        if (!$error && $changePassword) {
            if (update_user_password($userId, $newPassword)) {
                $success = '密碼已更新。';
            } else {
                $error = '更新密碼時發生錯誤，請稍後再試。';
            }
        } elseif (!$error && !empty($changes)) {
            $success = '帳號資訊已更新。';
        }

        if (!$error) {
            $user = get_user_by_id($userId);
        }
    }
}

html_start('帳號與安全');

page_header('帳號與安全', [
    ['label' => '首页', 'url' => '/'],
    ['label' => '系统设置', 'url' => '/settings/'],
    ['label' => '帳號與安全', 'url' => '/settings/account.php']
]);
?>

<div class="main-content">
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span class="alert-message"><?php echo h($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <span class="alert-message"><?php echo h($success); ?></span>
        </div>
    <?php endif; ?>

    <?php card_start('登入帳號資訊'); ?>
        <form method="post" action="/settings/account.php">
            <?php echo csrf_input(); ?>

            <div style="margin-bottom: 24px;">
                <?php
                form_field('username', '登入帳號', 'text', [], [
                    'value' => $user['username'],
                    'required' => true,
                    'placeholder' => '3-50 字，僅限英數字與底線'
                ]);
                ?>
            </div>

            <div style="margin-bottom: 32px;">
                <?php
                form_field('email', '電子郵件', 'text', [], [
                    'placeholder' => 'example@company.com',
                    'value' => $user['email'] ?? '',
                    'help' => '可留空，僅用於通知與密碼重設。'
                ]);
                ?>
            </div>

            <div style="margin-bottom: 24px;">
                <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 12px; color: var(--text-primary);">更新密碼</h3>
                <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 16px;">
                    若需更改密碼，請輸入目前密碼與新的密碼。密碼需至少8碼，包含字母與數字。
                </p>

                <div style="display: grid; gap: 20px;">
                    <?php
                    form_field('current_password', '目前密碼', 'password', [], [
                        'placeholder' => '請輸入目前使用的密碼'
                    ]);

                    form_field('new_password', '新密碼', 'password', [], [
                        'placeholder' => '請輸入新密碼'
                    ]);

                    form_field('confirm_password', '確認新密碼', 'password', [], [
                        'placeholder' => '再次輸入新密碼'
                    ]);
                    ?>
                </div>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="/settings/" class="btn btn-secondary">返回設定</a>
                <button type="submit" class="btn btn-primary">保存變更</button>
            </div>
        </form>
    <?php card_end(); ?>
</div>

<?php
bottom_tab_navigation();
html_end();
?>
