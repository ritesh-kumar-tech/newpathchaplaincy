<?php
require_once __DIR__ . '/auth.php';
require_admin();
if (!role_can('membership')) { http_response_code(403); exit('Access denied.'); }
$statuses = ['New', 'In Review', 'Approved', 'Rejected'];
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="membership-applications.csv"');
    $out = fopen('php://output', 'w'); fputcsv($out, ['Date','Name','Email','Phone','Type','Status','Message','Notes']);
    foreach (db()->query('SELECT * FROM membership_applications ORDER BY created_at DESC') as $row) fputcsv($out, [$row['created_at'],$row['full_name'],$row['email'],$row['phone'],$row['membership_type'],$row['status'],$row['message'],$row['internal_notes']]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)) {
    $id = (int)($_POST['id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', $statuses, true) ? $_POST['status'] : 'New';
    $notes = clean_text($_POST['internal_notes'] ?? '', 2000);
    $stmt = db()->prepare('UPDATE membership_applications SET status = ?, internal_notes = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$status, $notes, $id]);
    audit_log('MEMBERSHIP_UPDATED', 'id=' . $id, $_SESSION['admin_email']);
    header('Location: membership.php?updated=1'); exit;
}
$search = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$where = []; $params = [];
if ($search !== '') { $where[] = '(full_name LIKE ? OR email LIKE ?)'; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
if (in_array($status, $statuses, true)) { $where[] = 'status = ?'; $params[] = $status; }
$sql = 'SELECT * FROM membership_applications' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC';
$stmt = db()->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
admin_layout_start('Membership Applications', 'membership');
?>
<div class="topbar"><div><p class="eyebrow">Membership</p><h1>Applications</h1></div><a class="btn secondary" href="membership.php?export=csv">Export CSV</a></div>
<?php if (!empty($_GET['updated'])): ?><p class="form-note success">Application updated.</p><?php endif; ?>
<section class="panel"><form class="toolbar" method="get"><input name="q" value="<?php echo h($search); ?>" placeholder="Search name or email"><select name="status"><option value="">All statuses</option><?php foreach ($statuses as $s): ?><option <?php echo $status===$s?'selected':''; ?>><?php echo h($s); ?></option><?php endforeach; ?></select><button class="btn primary">Filter</button></form><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Name</th><th>Email</th><th>Phone</th><th>Type</th><th>Status</th><th>Review</th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="7">No applications found.</td></tr><?php endif; foreach ($rows as $row): ?><tr><td><?php echo h($row['created_at']); ?></td><td><?php echo h($row['full_name']); ?><div class="note-box"><?php echo h($row['message']); ?></div></td><td><?php echo h($row['email']); ?></td><td><?php echo h($row['phone']); ?></td><td><?php echo h($row['membership_type']); ?></td><td><span class="badge"><?php echo h($row['status']); ?></span></td><td><form method="post" class="admin-form"><input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>"><input type="hidden" name="id" value="<?php echo h($row['id']); ?>"><select name="status"><?php foreach ($statuses as $s): ?><option <?php echo $row['status']===$s?'selected':''; ?>><?php echo h($s); ?></option><?php endforeach; ?></select><textarea name="internal_notes" placeholder="Internal note"><?php echo h($row['internal_notes']); ?></textarea><button class="btn secondary">Save</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php admin_layout_end(); ?>
