<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
json_response([
    'ok' => true,
    'settings' => [
        'contact_email' => site_setting('contact_email', 'info@newpathchaplaincy.com'),
        'contact_phone' => site_setting('contact_phone', '(000) 000-0000'),
    ],
]);
?>
