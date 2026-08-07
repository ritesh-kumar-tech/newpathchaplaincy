<?php
require_once __DIR__ . '/auth.php';
require_admin(true);

$user = find_admin_by_id((int)$_SESSION['admin_id']);
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Security check failed. Please reload and try again.';
    } elseif (!$user || !password_verify($current, $user['password_hash'])) {
        $errors['current_password'] = 'Current password is incorrect.';
    } elseif (password_verify($new, $user['password_hash'])) {
        $errors['new_password'] = 'New password cannot match your current password.';
    } elseif ($new !== $confirm) {
        $errors['confirm_password'] = 'New password and confirmation do not match.';
    } elseif (!password_is_strong($new)) {
        $errors['new_password'] = 'Use at least 12 characters with uppercase, lowercase, number, and special character.';
    } elseif (password_reused($new, admin_password_history((int)$user['id']))) {
        $errors['new_password'] = 'You cannot reuse one of your last five passwords.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        db()->prepare('UPDATE admin_users SET password_hash = ?, must_change_password = 0, password_changed_at = NOW() WHERE id = ?')->execute([$hash, (int)$user['id']]);
        remember_admin_password((int)$user['id'], $hash);
        session_regenerate_id(true);
        $_SESSION['must_change_password'] = false;
        $_SESSION['last_activity'] = time();
        audit_log('PASSWORD_CHANGED', '', $_SESSION['admin_email']);
        header('Location: dashboard.php?password_changed=1');
        exit;
    }
}

admin_layout_start('Change Password', 'settings');
?>
<?php page_header('Account Security', 'Change Password', 'Update your password using the current strength and history requirements.'); ?>
<section class="panel security-card">
  <div class="security-context">
    <h2>Password Reset</h2>
    <p>Complete this required security step before entering the dashboard. Password history is checked against the last five stored hashes.</p>
    <ul class="password-rules">
      <li data-rule="length">Minimum 12 characters</li>
      <li data-rule="upper">At least one uppercase letter</li>
      <li data-rule="lower">At least one lowercase letter</li>
      <li data-rule="number">At least one number</li>
      <li data-rule="special">At least one special character</li>
    </ul>
  </div>
  <form method="post" class="admin-form password-form" autocomplete="on" data-prevent-duplicate>
    <?php if (!empty($_SESSION['must_change_password'])): ?><p class="form-note">You must change the temporary password before accessing the dashboard.</p><?php endif; ?>
    <?php if (!empty($errors['form'])): ?><p class="form-note"><?php echo h($errors['form']); ?></p><?php endif; ?>
    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
    <label>Current Password
      <span class="password-input"><input type="password" name="current_password" required autocomplete="current-password"><button type="button" class="password-toggle" aria-label="Show current password">Show</button></span>
      <?php if (!empty($errors['current_password'])): ?><small class="field-message"><?php echo h($errors['current_password']); ?></small><?php endif; ?>
    </label>
    <label>New Password
      <span class="password-input"><input type="password" name="new_password" required minlength="12" autocomplete="new-password"><button type="button" class="password-toggle" aria-label="Show new password">Show</button></span>
      <?php if (!empty($errors['new_password'])): ?><small class="field-message"><?php echo h($errors['new_password']); ?></small><?php endif; ?>
    </label>
    <label>Confirm New Password
      <span class="password-input"><input type="password" name="confirm_password" required minlength="12" autocomplete="new-password"><button type="button" class="password-toggle" aria-label="Show confirm password">Show</button></span>
      <?php if (!empty($errors['confirm_password'])): ?><small class="field-message"><?php echo h($errors['confirm_password']); ?></small><?php endif; ?>
    </label>
    <button class="btn primary" data-loading-text="Saving...">Save New Password</button>
  </form>
</section>
<?php admin_layout_end(); ?>
