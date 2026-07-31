<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
start_secure_session();
json_response(['token' => csrf_token()]);
?>
