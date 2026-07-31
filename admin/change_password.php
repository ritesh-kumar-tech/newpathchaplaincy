<?php
require_once __DIR__ . '/auth.php';
require_admin(true);
$user = find_admin_by_id((int)$_SESSION['admin_id']);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? ''; $new = $_POST['new_password'] ?? ''; $confirm = $_POST['confirm_password'] ?? '';
    if (!verify_csrf($_POST['csrf_token'] ?? null)) $error = 'Security check failed. Please try again.';
    elseif (!$user || !password_verify($current, $user['password_hash'])) $error = 'Current password is incorrect.';
    elseif ($new !== $confirm) $error = 'New password and confirmation do not match.';
    elseif (!password_is_strong($new)) $error = 'Password must be at least 12 characters and include uppercase, lowercase, number, and special character.';
    elseif (password_reused($new, admin_password_history((int)$user['id']))) $error = 'You cannot reuse one of your last five passwords.';
    else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        db()->prepare('UPDATE admin_users SET password_hash=?, must_change_password=0, password_changed_at=NOW() WHERE id=?')->execute([$hash,(int)$user['id']]);
        remember_admin_password((int)$user['id'], $hash);
        $_SESSION['must_change_password'] = false;
        audit_log('PASSWORD_CHANGED', '', $_SESSION['admin_email']);
        header('Location: dashboard.php?password_changed=1'); exit;
    }
}
admin_layout_start('Change Password','settings');
?>
<div class="topbar"><div><p class="eyebrow">Account Security</p><h1>Change Password</h1></div></div>
<section class="panel security-card"><div class="security-context"><h2>Password Reset</h2><p>Complete this required security step before entering the dashboard. Password history is checked against the last five stored hashes.</p></div><form method="post" class="admin-form" autocomplete="off"><?php if (!empty($_SESSION['must_change_password'])): ?><p class="form-note">You must change the temporary password before accessing the dashboard.</p><?php endif; ?><?php if ($error): ?><p class="form-note"><?php echo h($error); ?></p><?php endif; ?><input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>"><label>Current Password<input type="password" name="current_password" required></label><label>New Password<input type="password" name="new_password" required minlength="12"></label><label>Confirm New Password<input type="password" name="confirm_password" required minlength="12"></label><button class="btn primary">Save New Password</button><p class="login-hint">Minimum 12 characters with uppercase, lowercase, number, and special character. The last five passwords cannot be reused.</p></form></section>
<?php admin_layout_end(); ?>
