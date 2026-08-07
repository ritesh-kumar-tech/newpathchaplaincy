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

function login_attempt_key(string $email): string
{
    return 'login|' . hash('sha256', strtolower(trim($email))) . '|' . client_ip();
}

function is_locked_out(string $email): bool
{
    $stmt = db()->prepare('SELECT locked_until FROM login_attempts WHERE attempt_key = ? LIMIT 1');
    $stmt->execute([login_attempt_key($email)]);
    $lockedUntil = $stmt->fetchColumn();
    return $lockedUntil !== false && $lockedUntil !== null && strtotime((string)$lockedUntil) > time();
}

function record_failed_login(string $email): void
{
    $key = login_attempt_key($email);
    $stmt = db()->prepare('SELECT attempts, updated_at FROM login_attempts WHERE attempt_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    $attempts = (!$row || strtotime((string)$row['updated_at']) < (time() - LOCKOUT_MINUTES * 60)) ? 1 : ((int)$row['attempts'] + 1);
    $lockedUntil = $attempts >= MAX_LOGIN_ATTEMPTS ? date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60) : null;
    $stmt = db()->prepare('REPLACE INTO login_attempts (attempt_key, attempts, locked_until, updated_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$key, $attempts, $lockedUntil]);
    if ($lockedUntil) {
        audit_log('LOCKOUT_TRIGGERED', 'login rate limit reached', strtolower(trim($email)));
    }
}

function clear_failed_login(string $email): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE attempt_key = ?');
    $stmt->execute([login_attempt_key($email)]);
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
    return !empty($user['must_change_password']);
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
    $user = find_admin_by_id((int)($_SESSION['admin_id'] ?? 0));
    if ($user) {
        $_SESSION['must_change_password'] = admin_password_expired($user);
        $_SESSION['admin_role'] = $user['role'];
        $_SESSION['admin_name'] = $user['name'];
        $_SESSION['admin_email'] = $user['email'];
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
        admin_error_page('Access denied', 'You do not have permission to open this admin page.', 403);
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

function admin_icon(string $key): string
{
    $paths = [
        'dashboard' => '<path d="M4 5h7v7H4V5Zm9 0h7v14h-7V5ZM4 14h7v5H4v-5Z"/>',
        'membership' => '<path d="M8 11a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7Zm8 0a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7ZM3 20c.3-3.1 2.4-5 5-5s4.7 1.9 5 5H3Zm10 0c.2-1.7.9-3.1 2-4 .4-.2.8-.3 1.3-.3 2.4 0 4.4 1.7 4.7 4.3h-8Z"/>',
        'licensing' => '<path d="M6 2h9l5 5v15H6V2Zm8 1.5V8h4.5L14 3.5ZM8 12h10v2H8v-2Zm0 4h7v2H8v-2Z"/>',
        'messages' => '<path d="M3 5h18v14H3V5Zm2 3.2V17h14V8.2l-7 4.4-7-4.4ZM6.3 7l5.7 3.6L17.7 7H6.3Z"/>',
        'training' => '<path d="M4 4h16v13H7l-3 3V4Zm3 4h10V6H7v2Zm0 4h7v-2H7v2Z"/>',
        'settings' => '<path d="M12 8a4 4 0 1 1 0 8 4 4 0 0 1 0-8Zm8.6 5.5c.1-.5.1-1 .1-1.5s0-1-.1-1.5l2-1.5-2-3.5-2.4 1a8 8 0 0 0-2.5-1.5L15.3 2h-6.6l-.4 3a8 8 0 0 0-2.5 1.5l-2.4-1-2 3.5 2 1.5A8.7 8.7 0 0 0 3.3 12c0 .5 0 1 .1 1.5l-2 1.5 2 3.5 2.4-1a8 8 0 0 0 2.5 1.5l.4 3h6.6l.4-3a8 8 0 0 0 2.5-1.5l2.4 1 2-3.5-2-1.5Z"/>',
        'admins' => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 8c0-3.3 3.6-6 8-6s8 2.7 8 6H4Zm15-8v-2h-2V8h2V6h2v2h2v2h-2v2h-2Z"/>',
        'audit' => '<path d="M5 3h14v18H5V3Zm3 4h8V5H8v2Zm0 4h8V9H8v2Zm0 4h5v-2H8v2Z"/>',
        'website' => '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm6.9 9h-3.1a15 15 0 0 0-1.1-5 8.1 8.1 0 0 1 4.2 5ZM12 4.1c.7 1 1.4 2.9 1.7 4.9h-3.4c.3-2 1-3.9 1.7-4.9ZM4.3 13h3.1c.1 1.8.5 3.5 1.1 5a8.1 8.1 0 0 1-4.2-5Zm3.1-2H4.3a8.1 8.1 0 0 1 4.2-5 15 15 0 0 0-1.1 5Zm4.6 8.9c-.7-1-1.4-2.9-1.7-4.9h3.4c-.3 2-1 3.9-1.7 4.9Zm2.1-6.9H9.9a12.8 12.8 0 0 1 0-2h4.2a12.8 12.8 0 0 1 0 2Zm1.4 5c.6-1.5 1-3.2 1.1-5h3.1a8.1 8.1 0 0 1-4.2 5Z"/>',
        'logout' => '<path d="M10 3h9v18h-9v-2h7V5h-7V3Zm-1 5 1.4 1.4L8.8 11H21v2H8.8l1.6 1.6L9 16l-4-4 4-4Z"/>',
    ];
    return '<svg class="nav-svg" aria-hidden="true" viewBox="0 0 24 24" focusable="false">' . ($paths[$key] ?? $paths['dashboard']) . '</svg>';
}

function readable_action(string $action): string
{
    $known = [
        'LOGIN_SUCCESS' => 'Login Successful',
        'CONTACT_SUBMITTED' => 'Contact Submitted',
        'MEMBERSHIP_SUBMITTED' => 'Membership Submitted',
        'LICENSING_SUBMITTED' => 'Licensing Submitted',
    ];
    return $known[$action] ?? ucwords(strtolower(str_replace('_', ' ', $action)));
}

function status_badge(string $status): string
{
    $class = match (strtolower(trim($status))) {
        'approved', 'active', 'read' => 'green',
        'received', 'under review', 'in review' => 'blue',
        'new', 'unread' => 'red',
        'denied', 'rejected', 'disabled' => 'danger',
        default => 'gray',
    };
    return '<span class="badge ' . h($class) . '">' . h($status) . '</span>';
}

function page_header(string $eyebrow, string $title, string $description = '', string $meta = '', string $actionHtml = ''): void
{
    echo '<div class="page-header"><div><p class="eyebrow">' . h($eyebrow) . '</p><h1>' . h($title) . '</h1>';
    if ($description !== '') echo '<p class="page-description">' . h($description) . '</p>';
    if ($meta !== '') echo '<p class="page-meta">' . h($meta) . '</p>';
    echo '</div>';
    if ($actionHtml !== '') echo '<div class="page-actions">' . $actionHtml . '</div>';
    echo '</div>';
}

function admin_nav(string $active): string
{
    $items = [
        'dashboard.php' => ['Dashboard', 'dashboard'],
        'membership.php' => ['Membership', 'membership'],
        'licensing.php' => ['Licensing', 'licensing'],
        'messages.php' => ['Messages', 'messages'],
        'training.php' => ['Training CMS', 'training'],
        'settings.php' => ['Settings', 'settings'],
        'admins.php' => ['Admin Users', 'admins'],
        'audit.php' => ['Audit Log', 'audit'],
    ];
    $html = '';
    foreach ($items as $href => [$label, $key]) {
        if (in_array($key, ['admins', 'audit'], true) && current_admin_role() !== 'Super Admin') {
            continue;
        }
        if (in_array($key, ['membership', 'licensing', 'training', 'messages'], true) && !role_can($key)) {
            continue;
        }
        $class = $active === $key ? ' class="active"' : '';
        $current = $active === $key ? ' aria-current="page"' : '';
        $html .= '<a' . $class . $current . ' href="' . h($href) . '" title="' . h($label) . '">' . admin_icon($key) . '<span class="nav-label">' . h($label) . '</span></a>';
    }
    return '<div class="nav-primary">' . $html . '</div><div class="nav-secondary"><a href="../index.html" target="_blank" rel="noopener" title="View Website">' . admin_icon('website') . '<span class="nav-label">View Website</span></a><a href="logout.php" title="Logout">' . admin_icon('logout') . '<span class="nav-label">Logout</span></a></div>';
}

function admin_layout_start(string $title, string $active = ''): void
{
    $initials = strtoupper(substr((string)($_SESSION['admin_name'] ?? 'A'), 0, 1));
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex,nofollow"><title>' . h($title) . '</title><link rel="stylesheet" href="../assets/css/styles.css"><script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script><script defer src="../assets/js/admin.js"></script></head><body class="admin-body"><div class="admin-overlay" tabindex="-1"></div><div class="admin-shell"><aside class="sidebar" id="adminSidebar" aria-label="Admin navigation"><div class="side-brand"><img src="../assets/img/logo.png" alt="Newpath logo"><h2>NGCN Admin</h2><button class="sidebar-close" type="button" aria-label="Close admin menu">&times;</button></div><div class="admin-identity"><strong>' . h($_SESSION['admin_name'] ?? '') . '</strong><span>' . h(current_admin_role()) . '</span></div><nav>' . admin_nav($active) . '</nav></aside><header class="admin-topbar"><div class="admin-left"><button class="admin-menu-button" type="button" aria-label="Toggle admin menu" aria-controls="adminSidebar" aria-expanded="false"><span></span><span></span><span></span></button><div><div class="breadcrumb">' . h($title) . '</div><small>' . h(date('M j, Y')) . '</small></div></div><div class="profile-menu"><button class="profile-trigger" type="button" aria-haspopup="true" aria-expanded="false"><span class="admin-initials">' . h($initials) . '</span><span class="profile-copy"><strong>' . h($_SESSION['admin_name'] ?? 'Admin') . '</strong><small>' . h(current_admin_role()) . '</small></span></button><div class="profile-dropdown" role="menu"><a href="settings.php" role="menuitem">Profile Settings</a><a href="logout.php" role="menuitem">Logout</a></div></div></header><main class="admin-main">';
}

function admin_layout_end(): void
{
    echo '</main></div></body></html>';
}

function query_params(array $overrides): string
{
    return http_build_query(array_merge($_GET, $overrides));
}

function pagination_links(string $base, int $page, int $pages, array $params = []): string
{
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav class="pagination" aria-label="Pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $query = http_build_query(array_merge($params, ['page' => $i]));
        $class = $i === $page ? ' class="active"' : '';
        $html .= '<a' . $class . ' href="' . h($base . '?' . $query) . '">' . h($i) . '</a>';
    }
    return $html . '</nav>';
}

function admin_error_page(string $title, string $message, int $status = 400): void
{
    http_response_code($status);
    admin_layout_start($title, '');
    echo '<section class="panel empty-state"><h1>' . h($title) . '</h1><p>' . h($message) . '</p><a class="btn primary" href="dashboard.php">Back to Dashboard</a></section>';
    admin_layout_end();
}
?>
