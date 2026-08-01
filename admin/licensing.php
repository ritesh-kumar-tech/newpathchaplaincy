<?php
require_once __DIR__ . '/auth.php';
require_admin();
if (!role_can('licensing')) { http_response_code(403); exit('Access denied.'); }
$statuses = ['Received', 'Under Review', 'Approved', 'Denied'];
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="licensing-requests.csv"');
    $out = fopen('php://output', 'w'); fputcsv($out, ['Date','Name','Email','Affiliation','Type','Status','Experience','Notes']);
    foreach (db()->query('SELECT * FROM licensing_requests ORDER BY created_at DESC') as $row) fputcsv($out, [$row['created_at'],$row['full_name'],$row['email'],$row['affiliation'],$row['license_type'],$row['status'],$row['experience'],$row['internal_notes']]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        admin_error_page('Security check failed', 'Please go back, reload the page, and try again.', 419);
        exit;
    }
    $id = (int)($_POST['id'] ?? 0); $status = in_array($_POST['status'] ?? '', $statuses, true) ? $_POST['status'] : 'Received';
    $stmt = db()->prepare('UPDATE licensing_requests SET status = ?, internal_notes = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$status, clean_text($_POST['internal_notes'] ?? '', 2000), $id]);
    audit_log('LICENSING_UPDATED', 'id=' . $id, $_SESSION['admin_email']); header('Location: licensing.php?updated=1'); exit;
}
$search = trim($_GET['q'] ?? ''); $status = trim($_GET['status'] ?? ''); $page=max(1,(int)($_GET['page']??1)); $perPage=12; $offset=($page-1)*$perPage; $where=[]; $params=[];
if ($search !== '') { $where[]='(full_name LIKE ? OR email LIKE ?)'; $params[]="%{$search}%"; $params[]="%{$search}%"; }
if (in_array($status,$statuses,true)) { $where[]='status = ?'; $params[]=$status; }
$whereSql=$where?' WHERE '.implode(' AND ',$where):''; $countStmt=db()->prepare('SELECT COUNT(*) FROM licensing_requests'.$whereSql); $countStmt->execute($params); $total=(int)$countStmt->fetchColumn(); $pages=max(1,(int)ceil($total/$perPage));
$stmt=db()->prepare('SELECT * FROM licensing_requests'.$whereSql." ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"); $stmt->execute($params); $rows=$stmt->fetchAll();
admin_layout_start('Licensing Requests','licensing');
?>
<div class="topbar"><div><p class="eyebrow">Licensing</p><h1>Requests</h1></div><a class="btn secondary" href="licensing.php?export=csv">Export CSV</a></div>
<?php if (!empty($_GET['updated'])): ?><p class="form-note success">Licensing request updated.</p><?php endif; ?>
<section class="panel"><form class="toolbar" method="get"><input name="q" value="<?php echo h($search); ?>" placeholder="Search name or email"><select name="status"><option value="">All statuses</option><?php foreach ($statuses as $s): ?><option <?php echo $status===$s?'selected':''; ?>><?php echo h($s); ?></option><?php endforeach; ?></select><button class="btn primary">Filter</button></form><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Name</th><th>Affiliation</th><th>Type</th><th>Status</th><th>Review</th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="6">No licensing requests found.</td></tr><?php endif; foreach ($rows as $row): ?><tr><td data-label="Date"><?php echo h($row['created_at']); ?></td><td data-label="Name"><?php echo h($row['full_name']); ?><br><?php echo h($row['email']); ?><div class="note-box"><?php echo h($row['experience']); ?></div></td><td data-label="Affiliation"><?php echo h($row['affiliation']); ?></td><td data-label="Type"><?php echo h($row['license_type']); ?></td><td data-label="Status"><span class="badge blue"><?php echo h($row['status']); ?></span></td><td data-label="Review"><form method="post" class="admin-form"><input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>"><input type="hidden" name="id" value="<?php echo h($row['id']); ?>"><select name="status" aria-label="Licensing status"><?php foreach ($statuses as $s): ?><option <?php echo $row['status']===$s?'selected':''; ?>><?php echo h($s); ?></option><?php endforeach; ?></select><textarea name="internal_notes" placeholder="Internal note"><?php echo h($row['internal_notes']); ?></textarea><button class="btn secondary" data-loading-text="Saving...">Save</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php echo pagination_links('licensing.php', $page, $pages, ['q'=>$search,'status'=>$status]); ?></section>
<?php admin_layout_end(); ?>
