<?php
require_once __DIR__ . '/config.php';
start_secure_session();

$ADMIN_ALLOWED_IPS = [];

function find_admin_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE email = ? LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function find_admin_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function update_admin_login(int $id): void
{
    $stmt = db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?');
    $stmt->execute([$id]);
}

function admin_password_history(int $adminId): array
{
    $stmt = db()->prepare('SELECT password_hash FROM admin_password_history WHERE admin_user_id = ? ORDER BY created_at DESC, id DESC LIMIT 5');
    $stmt->execute([$adminId]);
    return array_column($stmt->fetchAll(), 'password_hash');
}

function remember_admin_password(int $adminId, string $hash): void
{
    $stmt = db()->prepare('INSERT INTO admin_password_history (admin_user_id, password_hash, created_at) VALUES (?, ?, NOW())');
    $stmt->execute([$adminId, $hash]);
}

function is_locked_out(string $email): bool
{
    $key = 'login|' . strtolower(trim($email)) . '|' . client_ip();
    $stmt = db()->prepare('SELECT locked_until FROM login_attempts WHERE attempt_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $lockedUntil = $stmt->fetchColumn();
    return $lockedUntil !== false && $lockedUntil !== null && strtotime((string)$lockedUntil) > time();
}

function record_failed_login(string $email): void
{
    $key = 'login|' . strtolower(trim($email)) . '|' . client_ip();
    $stmt = db()->prepare('SELECT attempts, updated_at FROM login_attempts WHERE attempt_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    $attempts = (!$row || strtotime((string)$row['updated_at']) < (time() - LOCKOUT_MINUTES * 60)) ? 1 : ((int)$row['attempts'] + 1);
    $lockedUntil = $attempts >= MAX_LOGIN_ATTEMPTS ? date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60) : null;
    $stmt = db()->prepare('REPLACE INTO login_attempts (attempt_key, attempts, locked_until, updated_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$key, $attempts, $lockedUntil]);
    if ($lockedUntil) {
        audit_log('LOCKOUT_TRIGGERED', strtolower(trim($email)), strtolower(trim($email)));
    }
}

function clear_failed_login(string $email): void
{
    $key = 'login|' . strtolower(trim($email)) . '|' . client_ip();
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE attempt_key = ?');
    $stmt->execute([$key]);
}

function captcha_question(): string
{
    if (!isset($_SESSION['captcha_answer'])) {
        $a = random_int(2, 9);
        $b = random_int(2, 9);
        $_SESSION['captcha_answer'] = (string)($a + $b);
        $_SESSION['captcha_question'] = $a . ' + ' . $b;
    }
    return $_SESSION['captcha_question'];
}

function verify_captcha(string $answer): bool
{
    $ok = isset($_SESSION['captcha_answer']) && hash_equals($_SESSION['captcha_answer'], trim($answer));
    unset($_SESSION['captcha_answer'], $_SESSION['captcha_question']);
    return $ok;
}

function ip_allowed(): bool
{
    global $ADMIN_ALLOWED_IPS;
    return empty($ADMIN_ALLOWED_IPS) || in_array(client_ip(), $ADMIN_ALLOWED_IPS, true);
}

function password_is_strong(string $password): bool
{
    return strlen($password) >= 12
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

function password_reused(string $password, array $history): bool
{
    foreach ($history as $oldHash) {
        if (password_verify($password, $oldHash)) {
            return true;
        }
    }
    return false;
}

function admin_password_expired(array $user): bool
{
    if (!empty($user['must_change_password'])) {
        return true;
    }
    if (empty($user['password_changed_at'])) {
        return false;
    }
    return strtotime((string)$user['password_changed_at']) < strtotime('-' . PASSWORD_EXPIRY_DAYS . ' days');
}

function is_admin_logged_in(): bool
{
    if (empty($_SESSION['ngcn_admin']) || $_SESSION['ngcn_admin'] !== true) {
        return false;
    }
    if (!isset($_SESSION['last_activity']) || (time() - (int)$_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {
        audit_log('SESSION_TIMEOUT', '', $_SESSION['admin_email'] ?? null);
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

function require_admin(bool $allowPasswordChange = false): void
{
    if (!is_admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
    if (!$allowPasswordChange && !empty($_SESSION['must_change_password'])) {
        header('Location: change_password.php');
        exit;
    }
}

function current_admin_role(): string
{
    return $_SESSION['admin_role'] ?? 'Admin';
}

function require_role(array $allowedRoles): void
{
    require_admin();
    if (!in_array(current_admin_role(), $allowedRoles, true)) {
        http_response_code(403);
        echo 'Access denied for this admin role.';
        exit;
    }
}

function roles(): array
{
    return ['Super Admin', 'Membership Administrator', 'Licensing Administrator', 'Training Administrator', 'Finance Administrator', 'Communications Administrator'];
}

function role_can(string $module): bool
{
    $role = current_admin_role();
    if ($role === 'Super Admin') {
        return true;
    }
    return match ($module) {
        'membership' => $role === 'Membership Administrator',
        'licensing' => $role === 'Licensing Administrator',
        'training' => $role === 'Training Administrator',
        'messages' => $role === 'Communications Administrator',
        default => false,
    };
}

function admin_nav(string $active): string
{
    $items = [
        'dashboard.php' => ['Dashboard', 'dashboard', 'D'],
        'membership.php' => ['Membership', 'membership', 'M'],
        'licensing.php' => ['Licensing', 'licensing', 'L'],
        'messages.php' => ['Messages', 'messages', 'C'],
        'training.php' => ['Training CMS', 'training', 'T'],
        'settings.php' => ['Settings', 'settings', 'S'],
        'admins.php' => ['Admin Users', 'admins', 'U'],
        'audit.php' => ['Audit Log', 'audit', 'A'],
    ];
    $html = '';
    foreach ($items as $href => [$label, $key, $icon]) {
        if (in_array($key, ['admins', 'audit'], true) && current_admin_role() !== 'Super Admin') {
            continue;
        }
        if (in_array($key, ['membership', 'licensing', 'training', 'messages'], true) && !role_can($key)) {
            continue;
        }
        $class = $active === $key ? ' class="active"' : '';
        $html .= '<a' . $class . ' href="' . h($href) . '" data-icon="' . h($icon) . '"><span class="nav-label">' . h($label) . '</span></a>';
    }
    return $html . '<a href="../index.html" data-icon="W"><span class="nav-label">View Website</span></a><a href="logout.php" data-icon="Q"><span class="nav-label">Logout</span></a>';
}

function admin_layout_start(string $title, string $active = ''): void
{
    $initials = strtoupper(substr((string)($_SESSION['admin_name'] ?? 'A'), 0, 1));
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex,nofollow"><title>' . h($title) . '</title><link rel="stylesheet" href="../assets/css/styles.css"><script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script><script defer src="../assets/js/admin.js"></script></head><body class="admin-body"><div class="admin-shell"><aside class="sidebar"><div class="side-brand"><img src="../assets/img/logo.png" alt="Logo"><h2>NGCN Admin</h2></div><p>' . h($_SESSION['admin_name'] ?? '') . '<br><span>' . h(current_admin_role()) . '</span></p><nav>' . admin_nav($active) . '</nav></aside><header class="admin-topbar"><div class="admin-left"><button class="admin-menu-button" type="button" aria-label="Toggle admin menu">☰</button><div><div class="breadcrumb">' . h($title) . '</div><small>' . h(date('M j, Y')) . '</small></div></div><input class="global-search" type="search" placeholder="Search this workspace" aria-label="Global search"><div class="admin-avatar"><span>' . h($initials) . '</span><strong>' . h($_SESSION['admin_name'] ?? 'Admin') . '</strong></div></header><main class="admin-main">';
}

function admin_layout_end(): void
{
    echo '</main></div></body></html>';
}

function query_params(array $overrides): string
{
    return http_build_query(array_merge($_GET, $overrides));
}
?>
