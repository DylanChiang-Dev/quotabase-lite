<?php
/**
 * User Management
 * 使用者管理頁
 *
 * @version v2.1.0
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

$role = $_SESSION['user_role'] ?? 'staff';
if ($role !== 'admin') {
    header('Location: /settings/');
    exit;
}

$error = '';
$success = '';
$generatedPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '無效的請求，請重新嘗試。';
    } else {
        switch ($action) {
            case 'create_user':
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $userRole = $_POST['role'] ?? 'staff';
                $password = $_POST['password'] ?? '';
                $confirm = $_POST['confirm_password'] ?? '';

                if ($username === '' || !preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
                    $error = '請輸入3-50字且僅包含字母、數字或底線的帳號。';
                } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = '請輸入有效的電子郵件。';
                } elseif ($password === '' || $confirm === '') {
                    $error = '請輸入密碼並再次確認。';
                } elseif ($password !== $confirm) {
                    $error = '兩次輸入的密碼不一致。';
                } else {
                    $strength = validate_password_strength($password);
                    if (!$strength['valid']) {
                        $error = $strength['message'];
                    }
                }

                if (!$error) {
                    if (get_user_by_username($username)) {
                        $error = '此帳號已存在，請使用其他名稱。';
                    }
                }

                if (!$error) {
                    create_user([
                        'username' => $username,
                        'email' => $email !== '' ? $email : null,
                        'role' => in_array($userRole, ['admin', 'staff'], true) ? $userRole : 'staff',
                        'status' => 'active',
                        'password' => $password
                    ]);
                    $success = '使用者已建立。';
                }
                break;

            case 'toggle_status':
                $userId = (int)($_POST['user_id'] ?? 0);
                $targetStatus = $_POST['target_status'] ?? 'active';

                if ($userId === get_current_user_id()) {
                    $error = '無法停用目前登入的帳號。';
                } elseif (!in_array($targetStatus, ['active', 'suspended'], true)) {
                    $error = '狀態不正確。';
                } else {
                    $targetUser = get_user_by_id($userId);
                    if (!$targetUser) {
                        $error = '找不到指定的使用者。';
                    } else {
                        set_user_status($userId, $targetStatus);
                        $success = '使用者狀態已更新。';
                    }
                }
                break;

            case 'reset_password':
                $userId = (int)($_POST['user_id'] ?? 0);
                $targetUser = get_user_by_id($userId);
                if (!$targetUser) {
                    $error = '找不到指定的使用者。';
                } else {
                    $newPassword = generate_password(12, true);
                    if (update_user_password($userId, $newPassword)) {
                        $success = '已為 ' . $targetUser['username'] . ' 重設密碼。請盡快通知使用者更改密碼。';
                        $generatedPassword = $newPassword;
                    } else {
                        $error = '重設密碼時發生問題，請稍後再試。';
                    }
                }
                break;

            default:
                $error = '未知的操作。';
        }
    }
}

$users = get_all_users();

html_start('使用者管理');

page_header('使用者管理', [
    ['label' => '首页', 'url' => '/'],
    ['label' => '系统设置', 'url' => '/settings/'],
    ['label' => '使用者管理', 'url' => '/settings/users.php']
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
            <?php if ($generatedPassword): ?>
                <div style="margin-top: 8px; font-family: monospace; font-size: 14px;">
                    新密碼：<?php echo h($generatedPassword); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php card_start('新增使用者'); ?>
        <form method="post" action="/settings/users.php" style="display: grid; gap: 20px;">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="create_user">

            <div style="display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
                <?php
                form_field('username', '登入帳號', 'text', [], [
                    'placeholder' => '例如：sales01',
                    'required' => true
                ]);

                form_field('email', '電子郵件', 'text', [], [
                    'placeholder' => 'example@company.com'
                ]);

                form_field('role', '角色', 'select', [
                    'staff' => '一般使用者',
                    'admin' => '系統管理員'
                ], [
                    'selected' => 'staff'
                ]);
                ?>
            </div>

            <div style="display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
                <?php
                form_field('password', '初始密碼', 'password', [], [
                    'placeholder' => '至少8碼，含大小寫、數字、符號',
                    'required' => true
                ]);
                form_field('confirm_password', '確認密碼', 'password', [], [
                    'placeholder' => '再次輸入密碼',
                    'required' => true
                ]);
                ?>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="submit" class="btn btn-primary">建立使用者</button>
            </div>
        </form>
    <?php card_end(); ?>

    <?php card_start('使用者列表'); ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>帳號</th>
                        <th>電子郵件</th>
                        <th>角色</th>
                        <th>狀態</th>
                        <th>最後登入</th>
                        <th>動作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $row): ?>
                        <tr>
                            <td><?php echo h($row['username']); ?></td>
                            <td><?php echo h($row['email'] ?? '—'); ?></td>
                            <td><?php echo $row['role'] === 'admin' ? '管理員' : '一般使用者'; ?></td>
                            <td>
                                <?php if ($row['status'] === 'active'): ?>
                                    <span style="color: #16a34a; font-weight: 600;">啟用</span>
                                <?php else: ?>
                                    <span style="color: #b45309; font-weight: 600;">停用</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                echo $row['last_login_at']
                                    ? h($row['last_login_at']) . '<br><small>' . h($row['last_login_ip'] ?? '') . '</small>'
                                    : '尚未登入';
                                ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <?php if ($row['id'] !== $userId): ?>
                                        <form method="post" action="/settings/users.php" onsubmit="return confirm('確定要變更帳號狀態嗎？');">
                                            <?php echo csrf_input(); ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo h($row['id']); ?>">
                                            <input type="hidden" name="target_status" value="<?php echo $row['status'] === 'active' ? 'suspended' : 'active'; ?>">
                                            <button type="submit" class="btn <?php echo $row['status'] === 'active' ? 'btn-secondary' : 'btn-primary'; ?>">
                                                <?php echo $row['status'] === 'active' ? '停用' : '啟用'; ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="/settings/users.php" onsubmit="return confirm('確定要重設此使用者的密碼？');">
                                        <?php echo csrf_input(); ?>
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="user_id" value="<?php echo h($row['id']); ?>">
                                        <button type="submit" class="btn btn-secondary">重設密碼</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php card_end(); ?>
</div>

<?php
bottom_tab_navigation();
html_end();
?>
