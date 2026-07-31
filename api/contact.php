<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$data = $_POST;
if (!empty($data['website'] ?? '')) {
    json_response(['ok' => true, 'message' => 'Thank you. Your message was received.']);
}
if (!verify_csrf($data['csrf_token'] ?? null)) {
    json_response(['ok' => false, 'message' => 'Security check failed. Refresh and try again.'], 419);
}
if (!check_rate_limit('contact', 5, 60)) {
    json_response(['ok' => false, 'message' => 'Too many submissions. Please try again later.'], 429);
}

$errors = require_fields($data, [
    'full_name' => 'Full name',
    'email' => 'Email',
    'subject' => 'Subject',
    'message' => 'Message',
]);
if ($errors) {
    json_response(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors], 422);
}

$stmt = db()->prepare('INSERT INTO contact_messages (full_name, email, subject, message, is_read, archived, created_at) VALUES (?, ?, ?, ?, 0, 0, NOW())');
$stmt->execute([
    clean_text($data['full_name'], 160),
    clean_text($data['email'], 190),
    clean_text($data['subject'], 190),
    clean_text($data['message'], 1800),
]);
audit_log('CONTACT_SUBMITTED', clean_text($data['email'], 190));
json_response(['ok' => true, 'message' => 'Thank you. Your message was sent.']);
?>
