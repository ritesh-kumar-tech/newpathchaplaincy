<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$stmt = db()->query('SELECT step_number, title, description FROM training_modules ORDER BY sort_order, id');
json_response(['ok' => true, 'modules' => $stmt->fetchAll()]);
?>
