<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$data = $_POST;
if (!empty($data['website'] ?? '')) {
    json_response(['ok' => true, 'message' => 'Thank you. Your application was received.']);
}
if (!verify_csrf($data['csrf_token'] ?? null)) {
    json_response(['ok' => false, 'message' => 'Security check failed. Refresh and try again.'], 419);
}
if (!check_rate_limit('membership', 5, 60)) {
    json_response(['ok' => false, 'message' => 'Too many submissions. Please try again later.'], 429);
}

$errors = require_fields($data, [
    'full_name' => 'Full name',
    'email' => 'Email',
    'phone' => 'Phone',
    'membership_type' => 'Membership type',
]);
if ($errors) {
    json_response(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors], 422);
}

$stmt = db()->prepare('INSERT INTO membership_applications (full_name, email, phone, membership_type, message, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, "New", NOW(), NOW())');
$stmt->execute([
    clean_text($data['full_name'], 160),
    clean_text($data['email'], 190),
    clean_text($data['phone'], 80),
    clean_text($data['membership_type'], 120),
    clean_text($data['message'] ?? '', 1600),
]);
audit_log('MEMBERSHIP_SUBMITTED', clean_text($data['email'], 190));
json_response(['ok' => true, 'message' => 'Thank you. Your membership application was received.']);
?>
