<?php
declare(strict_types=1);

const SESSION_NAME = 'NGCN_ADMIN_SESSION';
const SESSION_TIMEOUT_SECONDS = 1800;
const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_MINUTES = 15;
const PASSWORD_EXPIRY_DAYS = 90;

function load_env_file(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

load_env_file(dirname(__DIR__) . '/.env');

function env_value(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);
    return $value === false || $value === null || $value === '' ? $default : (string)$value;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env_value('DB_HOST', '127.0.0.1');
    $port = env_value('DB_PORT', '3306');
    $name = env_value('DB_NAME', 'newpath_chaplaincy');
    $user = env_value('DB_USER', 'root');
    $pass = env_value('DB_PASS', '');
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    ensure_app_schema($pdo);

    return $pdo;
}

function ensure_app_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(120) NOT NULL,
      email VARCHAR(190) NOT NULL UNIQUE,
      password_hash VARCHAR(255) NOT NULL,
      role VARCHAR(80) NOT NULL,
      two_factor_enabled TINYINT(1) NOT NULL DEFAULT 1,
      two_factor_code VARCHAR(20) DEFAULT NULL,
      must_change_password TINYINT(1) NOT NULL DEFAULT 1,
      active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL,
      password_changed_at DATETIME DEFAULT NULL,
      last_login_at DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_password_history (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      admin_user_id INT UNSIGNED NOT NULL,
      password_hash VARCHAR(255) NOT NULL,
      created_at DATETIME NOT NULL,
      INDEX idx_password_history_admin (admin_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS membership_applications (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      full_name VARCHAR(160) NOT NULL,
      email VARCHAR(190) NOT NULL,
      phone VARCHAR(80) NOT NULL,
      membership_type VARCHAR(120) NOT NULL,
      message TEXT,
      status VARCHAR(40) NOT NULL DEFAULT 'New',
      internal_notes TEXT,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      INDEX idx_membership_status (status),
      INDEX idx_membership_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS licensing_requests (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      full_name VARCHAR(160) NOT NULL,
      email VARCHAR(190) NOT NULL,
      affiliation VARCHAR(190) NOT NULL,
      license_type VARCHAR(120) NOT NULL,
      experience TEXT NOT NULL,
      status VARCHAR(40) NOT NULL DEFAULT 'Received',
      internal_notes TEXT,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      INDEX idx_licensing_status (status),
      INDEX idx_licensing_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      full_name VARCHAR(160) NOT NULL,
      email VARCHAR(190) NOT NULL,
      subject VARCHAR(190) NOT NULL,
      message TEXT NOT NULL,
      is_read TINYINT(1) NOT NULL DEFAULT 0,
      archived TINYINT(1) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL,
      INDEX idx_contact_read (is_read),
      INDEX idx_contact_archived (archived),
      INDEX idx_contact_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS training_modules (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      sort_order INT NOT NULL,
      step_number INT NOT NULL,
      title VARCHAR(160) NOT NULL,
      description TEXT NOT NULL,
      updated_at DATETIME NOT NULL,
      INDEX idx_training_sort (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      setting_key VARCHAR(120) NOT NULL UNIQUE,
      setting_value TEXT,
      updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      admin_email VARCHAR(190) NOT NULL,
      action VARCHAR(120) NOT NULL,
      details TEXT,
      ip_address VARCHAR(80) NOT NULL,
      created_at DATETIME NOT NULL,
      INDEX idx_audit_created (created_at),
      INDEX idx_audit_admin (admin_email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
      attempt_key VARCHAR(255) PRIMARY KEY,
      attempts INT NOT NULL DEFAULT 0,
      locked_until DATETIME DEFAULT NULL,
      updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
      rate_key VARCHAR(255) PRIMARY KEY,
      attempts INT NOT NULL DEFAULT 0,
      locked_until DATETIME DEFAULT NULL,
      updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    ensure_column($pdo, 'contact_messages', 'archived', "ALTER TABLE contact_messages ADD archived TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read");
    ensure_column($pdo, 'admin_users', 'last_login_at', "ALTER TABLE admin_users ADD last_login_at DATETIME DEFAULT NULL");
    ensure_column($pdo, 'admin_users', 'password_changed_at', "ALTER TABLE admin_users ADD password_changed_at DATETIME DEFAULT NULL");

    $adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($adminCount === 0) {
        $hash = '$2y$12$QDGG6faQsWpyZF7O1nQnSO6L6MqbYY9QlCheYqmY9Nyt1Zm/44z3K';
        $stmt = $pdo->prepare('INSERT INTO admin_users (name, email, password_hash, role, two_factor_enabled, two_factor_code, must_change_password, active, created_at) VALUES (?, ?, ?, ?, 1, ?, 1, 1, NOW())');
        $stmt->execute(['Newpath Super Admin', 'admin@newpathchaplaincy.com', $hash, 'Super Admin', '202626']);
        $adminId = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO admin_password_history (admin_user_id, password_hash, created_at) VALUES (?, ?, NOW())');
        $stmt->execute([$adminId, $hash]);
    }

    $moduleCount = (int)$pdo->query('SELECT COUNT(*) FROM training_modules')->fetchColumn();
    if ($moduleCount === 0) {
        $stmt = $pdo->prepare('INSERT INTO training_modules (sort_order, step_number, title, description, updated_at) VALUES (?, ?, ?, ?, NOW())');
        $modules = [
            [1, 1, 'Foundations of Chaplaincy', 'Calling, ethics, confidentiality, presence, listening, and pastoral identity.'],
            [2, 2, 'Crisis & Trauma Care', 'Emergency response, grief support, psychological first aid, and referral protocols.'],
            [3, 3, 'Specialized Chaplaincy', 'Healthcare, military/veterans, corrections, community, workplace, and first-responder care.'],
            [4, 4, 'Digital Chaplaincy', 'Online care, virtual ministry, secure records, and ethical technology use.'],
        ];
        foreach ($modules as $module) {
            $stmt->execute($module);
        }
    }

    $settings = [
        'contact_email' => 'info@newpathchaplaincy.com',
        'contact_phone' => '(000) 000-0000',
    ];
    $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_key = setting_key');
    foreach ($settings as $key => $value) {
        $stmt->execute([$key, $value]);
    }
}

function ensure_column(PDO $pdo, string $table, string $column, string $sql): void
{
    $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE " . $pdo->quote($column));
    if (!$stmt->fetch()) {
        $pdo->exec($sql);
    }
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_name(SESSION_NAME);
    session_start();
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    start_secure_session();
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function audit_log(string $action, string $details = '', ?string $adminEmail = null): void
{
    try {
        $stmt = db()->prepare('INSERT INTO audit_log (admin_email, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$adminEmail ?? ($_SESSION['admin_email'] ?? 'public'), $action, $details, client_ip()]);
    } catch (Throwable $e) {
        error_log('audit_log failed: ' . $e->getMessage());
    }
}

function site_setting(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string)$value;
}

function set_site_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()');
    $stmt->execute([$key, $value]);
}

function check_rate_limit(string $bucket, int $limit = 5, int $minutes = 60): bool
{
    $pdo = db();
    $key = $bucket . '|' . client_ip();
    $stmt = $pdo->prepare('SELECT attempts, locked_until, updated_at FROM rate_limits WHERE rate_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    $now = time();

    if ($row && !empty($row['locked_until']) && strtotime($row['locked_until']) > $now) {
        return false;
    }

    $windowExpired = $row && strtotime((string)$row['updated_at']) < ($now - ($minutes * 60));
    if (!$row || $windowExpired || (!empty($row['locked_until']) && strtotime($row['locked_until']) <= $now)) {
        $stmt = $pdo->prepare('REPLACE INTO rate_limits (rate_key, attempts, locked_until, updated_at) VALUES (?, 1, NULL, NOW())');
        $stmt->execute([$key]);
        return true;
    }

    $attempts = (int)$row['attempts'] + 1;
    $lockedUntil = $attempts > $limit ? date('Y-m-d H:i:s', $now + ($minutes * 60)) : null;
    $stmt = $pdo->prepare('UPDATE rate_limits SET attempts = ?, locked_until = ?, updated_at = NOW() WHERE rate_key = ?');
    $stmt->execute([$attempts, $lockedUntil, $key]);
    return $lockedUntil === null;
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function require_fields(array $data, array $fields): array
{
    $errors = [];
    foreach ($fields as $field => $label) {
        if (trim((string)($data[$field] ?? '')) === '') {
            $errors[$field] = $label . ' is required.';
        }
    }
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    return $errors;
}

function clean_text(?string $value, int $max = 1200): string
{
    $value = trim(strip_tags((string)$value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}
?>
