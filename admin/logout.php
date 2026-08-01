<?php
require_once __DIR__ . '/auth.php';
audit_log('LOGOUT', '', $_SESSION['admin_email'] ?? null);
session_unset();
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
}
session_destroy();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Location: login.php');
exit;
?>
