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

    return $pdo;
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
