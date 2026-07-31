<?php
require_once __DIR__ . '/auth.php';
audit_log('LOGOUT', '', $_SESSION['admin_email'] ?? null);
session_unset();
session_destroy();
header('Location: login.php');
exit;
?>
