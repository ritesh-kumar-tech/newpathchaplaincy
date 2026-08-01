<?php
require_once __DIR__ . '/auth.php';
require_admin();
$pdo = db();
$metrics = [
    'members' => (int)$pdo->query('SELECT COUNT(*) FROM membership_applications')->fetchColumn(),
    'pending_members' => (int)$pdo->query("SELECT COUNT(*) FROM membership_applications WHERE status IN ('New','In Review')")->fetchColumn(),
    'pending_licenses' => (int)$pdo->query("SELECT COUNT(*) FROM licensing_requests WHERE status IN ('Received','Under Review')")->fetchColumn(),
    'unread_messages' => (int)$pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn(),
];
$activity = $pdo->query('SELECT admin_email, action, details, ip_address, created_at FROM audit_log ORDER BY created_at DESC LIMIT 8')->fetchAll();
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-{$i} months"));
    $months[$key] = 0;
}
$stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') month_key, COUNT(*) count_rows FROM membership_applications WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY month_key");
foreach ($stmt->fetchAll() as $row) {
    if (isset($months[$row['month_key']])) $months[$row['month_key']] = (int)$row['count_rows'];
}
admin_layout_start('Admin Dashboard', 'dashboard');
?>
<div class="topbar"><div><p class="eyebrow">Administrative Back-End</p><h1>Dashboard</h1><p>Welcome, <?php echo h($_SESSION['admin_name']); ?>.</p></div><a class="btn secondary" href="logout.php">Logout</a></div>
<?php if (!empty($_GET['password_changed'])): ?><p class="form-note success">Password changed successfully.</p><?php endif; ?>
<section class="metric-grid">
  <a class="metric" href="membership.php"><span>Total members</span><strong><?php echo h($metrics['members']); ?></strong></a>
  <a class="metric" href="membership.php?status=New"><span>Pending applications</span><strong><?php echo h($metrics['pending_members']); ?></strong></a>
  <a class="metric" href="licensing.php?status=Received"><span>Pending licensing</span><strong><?php echo h($metrics['pending_licenses']); ?></strong></a>
  <a class="metric" href="messages.php"><span>Unread messages</span><strong><?php echo h($metrics['unread_messages']); ?></strong></a>
</section>
<section class="module-grid" aria-label="Admin modules">
  <a class="module-card" href="membership.php"><strong>Membership Applications</strong><span>Review, search, update status, notes, export CSV.</span></a>
  <a class="module-card" href="licensing.php"><strong>Licensing Requests</strong><span>Manage review pipeline and internal notes.</span></a>
  <a class="module-card" href="messages.php"><strong>Contact Inbox</strong><span>Read, archive, delete, and reply by email.</span></a>
  <a class="module-card" href="training.php"><strong>Training CMS</strong><span>Edit the public training cards instantly.</span></a>
  <a class="module-card" href="settings.php"><strong>Settings</strong><span>Update profile and frontend contact details.</span></a>
  <?php if (current_admin_role() === 'Super Admin'): ?><a class="module-card" href="admins.php"><strong>Admin Users</strong><span>Create, disable, enable, and reset admins.</span></a><a class="module-card" href="audit.php"><strong>Audit Log</strong><span>Search paginated security and action records.</span></a><?php endif; ?>
</section>
<section class="panel chart-panel"><h2>Applications Per Month</h2><canvas id="applicationsChart" data-labels='<?php echo h(json_encode(array_keys($months))); ?>' data-values='<?php echo h(json_encode(array_values($months))); ?>'></canvas></section>
<br><section class="panel"><h2>Recent Activity</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Admin</th><th>Action</th><th>Details</th><th>IP</th></tr></thead><tbody><?php if (!$activity): ?><tr><td colspan="5">No recent activity yet.</td></tr><?php endif; foreach ($activity as $row): ?><tr><td data-label="Date"><?php echo h($row['created_at']); ?></td><td data-label="Admin"><?php echo h($row['admin_email']); ?></td><td data-label="Action"><?php echo h($row['action']); ?></td><td data-label="Details"><?php echo h($row['details']); ?></td><td data-label="IP"><?php echo h($row['ip_address']); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php admin_layout_end(); ?>
