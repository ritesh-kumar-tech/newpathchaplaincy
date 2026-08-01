<?php
require_once __DIR__ . '/auth.php';
$error = '';
if (is_admin_logged_in()) {
    header('Location: ' . (!empty($_SESSION['must_change_password']) ? 'change_password.php' : 'dashboard.php'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $user = find_admin_by_email($email);

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please reload and try again.';
        audit_log('CSRF_FAILED', 'admin login', $email);
    } elseif (!ip_allowed()) {
        $error = 'This admin portal is restricted from your current network.';
        audit_log('IP_BLOCKED', 'admin login', $email);
    } elseif (is_locked_out($email)) {
        $error = 'Too many failed login attempts. Please try again later.';
    } elseif (!$user || empty($user['active']) || !password_verify($password, $user['password_hash'])) {
        $error = 'Invalid email or password.';
        record_failed_login($email);
        audit_log('LOGIN_FAILED', 'admin login', $email);
    } else {
        clear_failed_login($email);
        session_regenerate_id(true);
        update_admin_login((int)$user['id']);
        $_SESSION['ngcn_admin'] = true;
        $_SESSION['admin_id'] = (int)$user['id'];
        $_SESSION['admin_email'] = $user['email'];
        $_SESSION['admin_name'] = $user['name'];
        $_SESSION['admin_role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        $_SESSION['must_change_password'] = admin_password_expired($user);
        audit_log('LOGIN_SUCCESS', '', $user['email']);
        header('Location: ' . ($_SESSION['must_change_password'] ? 'change_password.php' : 'dashboard.php'));
        exit;
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex,nofollow"><title>Secure Admin Login | Newpath Global Chaplaincy Network</title><link rel="stylesheet" href="../assets/css/styles.css"><script defer src="../assets/js/admin.js"></script></head><body class="login-page"><main class="login-card"><img src="../assets/img/logo.png" alt="Logo"><h1>Secure Admin Portal</h1><p class="login-hint">Authorized administrators only.</p><?php if ($error): ?><p class="form-note"><?php echo h($error); ?></p><?php endif; ?><form method="post" autocomplete="on" data-prevent-duplicate><input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>"><label>Email<input type="email" name="email" required autocomplete="username" placeholder="admin@newpathchaplaincy.com" value="<?php echo h($email ?? ''); ?>"></label><label>Password<input type="password" name="password" required autocomplete="current-password" placeholder="Enter admin password"></label><button class="btn primary" type="submit" data-loading-text="Checking...">Login Securely</button></form><p class="login-hint">Temporary-password users are redirected to change their password after login.</p></main></body></html>
