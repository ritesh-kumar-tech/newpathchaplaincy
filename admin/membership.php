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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        admin_error_page('Security check failed', 'Please go back, reload the page, and try again.', 419);
        exit;
    }
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
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;
$where = []; $params = [];
if ($search !== '') { $where[] = '(full_name LIKE ? OR email LIKE ?)'; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
if (in_array($status, $statuses, true)) { $where[] = 'status = ?'; $params[] = $status; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$countStmt = db()->prepare('SELECT COUNT(*) FROM membership_applications' . $whereSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$sql = 'SELECT * FROM membership_applications' . $whereSql . " ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
$stmt = db()->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
admin_layout_start('Membership Applications', 'membership');
?>
<?php page_header('Membership', 'Applications', 'Review applications and internal notes.', $total . ' records found', '<a class="btn secondary" href="membership.php?export=csv">Export CSV</a>'); ?>
<?php if (!empty($_GET['updated'])): ?><p class="form-note success">Application updated.</p><?php endif; ?>
<section class="panel"><form class="toolbar" method="get"><label><span class="sr-only">Search name or email</span><input name="q" value="<?php echo h($search); ?>" placeholder="Search name or email"></label><label><span class="sr-only">Status</span><select name="status"><option value="">All statuses</option><?php foreach ($statuses as $s): ?><option <?php echo $status===$s?'selected':''; ?>><?php echo h($s); ?></option><?php endforeach; ?></select></label><button class="btn primary">Filter</button><a class="btn ghost" href="membership.php">Reset</a></form><div class="admin-table-wrap"><table class="admin-table review-table"><thead><tr><th>Date</th><th>Applicant</th><th>Email</th><th>Phone</th><th>Type</th><th>Status</th><th>Review</th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="7"><div class="empty-state compact">No applications found.</div></td></tr><?php endif; foreach ($rows as $row): ?><tr><td data-label="Date"><?php echo h(date('M j, Y g:i A', strtotime((string)$row['created_at']))); ?></td><td data-label="Applicant"><strong><?php echo h($row['full_name']); ?></strong><div class="note-box"><?php echo h($row['message']); ?></div></td><td data-label="Email"><a href="mailto:<?php echo h($row['email']); ?>"><?php echo h($row['email']); ?></a></td><td data-label="Phone"><?php echo h($row['phone']); ?></td><td data-label="Type"><?php echo h($row['membership_type']); ?></td><td data-label="Status"><?php echo status_badge($row['status']); ?></td><td data-label="Review"><form method="post" class="admin-form review-panel" data-change-submit><input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>"><input type="hidden" name="id" value="<?php echo h($row['id']); ?>"><label>Status<select name="status" aria-label="Membership status"><?php foreach ($statuses as $s): ?><option <?php echo $row['status']===$s?'selected':''; ?>><?php echo h($s); ?></option><?php endforeach; ?></select></label><label>Internal note<textarea name="internal_notes" placeholder="Internal note"><?php echo h($row['internal_notes']); ?></textarea></label><button class="btn secondary" data-loading-text="Saving..." disabled>Save</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php echo pagination_links('membership.php', $page, $pages, ['q'=>$search,'status'=>$status]); ?></section>
<?php admin_layout_end(); ?>
