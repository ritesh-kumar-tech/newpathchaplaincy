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
    $twoFactor = trim($_POST['two_factor'] ?? '');
    $captcha = trim($_POST['captcha'] ?? '');
    $user = find_admin_by_email($email);

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please reload and try again.';
        audit_log('CSRF_FAILED', 'admin login', $email);
    } elseif (!ip_allowed()) {
        $error = 'This admin portal is restricted from your current network.';
        audit_log('IP_BLOCKED', 'admin login', $email);
    } elseif (is_locked_out($email)) {
        $error = 'Too many failed login attempts. Please try again later.';
    } elseif (!verify_captcha($captcha)) {
        $error = 'CAPTCHA answer was incorrect.';
        record_failed_login($email);
        audit_log('CAPTCHA_FAILED', 'admin login', $email);
    } elseif (!$user || empty($user['active']) || !password_verify($password, $user['password_hash'])) {
        $error = 'Access denied. Admin credentials are required.';
        record_failed_login($email);
        audit_log('LOGIN_FAILED', 'admin login', $email);
    } elseif (!empty($user['two_factor_enabled']) && !hash_equals((string)($user['two_factor_code'] ?? ''), $twoFactor)) {
        $error = 'Invalid two-factor authentication code.';
        record_failed_login($email);
        audit_log('TWO_FACTOR_FAILED', 'admin login', $email);
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
$question = captcha_question();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex,nofollow"><title>Secure Admin Login | Newpath Global Chaplaincy Network</title><link rel="stylesheet" href="../assets/css/styles.css"></head><body class="login-page"><main class="login-card"><img src="../assets/img/logo.png" alt="Logo"><h1>Secure Admin Portal</h1><p class="login-hint">Authorized administrators only.</p><?php if ($error): ?><p class="form-note"><?php echo h($error); ?></p><?php endif; ?><form method="post" autocomplete="off"><input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>"><label>Email<input type="email" name="email" required placeholder="admin@newpathchaplaincy.com"></label><label>Password<input type="password" name="password" required placeholder="Enter admin password"></label><label>2FA Code<input type="text" name="two_factor" placeholder="Enter admin 2FA code"></label><label>CAPTCHA: What is <?php echo h($question); ?>?<input type="text" name="captcha" required placeholder="Answer"></label><button class="btn primary" type="submit">Login Securely</button></form><p class="login-hint">First login redirects the admin to change the temporary password.</p></main></body></html>
