<?php
require_once __DIR__ . '/auth.php';
require_role(['Super Admin']);
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="audit-log.csv"');
    $out = fopen('php://output', 'w'); fputcsv($out, ['Date','Admin','Action','Details','IP Address']);
    foreach (db()->query('SELECT * FROM audit_log ORDER BY created_at DESC') as $row) fputcsv($out, [$row['created_at'],$row['admin_email'],$row['action'],$row['details'],$row['ip_address']]);
    exit;
}
$q=trim($_GET['q']??''); $action=trim($_GET['action']??''); $admin=trim($_GET['admin']??''); $date=trim($_GET['date']??''); $page=max(1,(int)($_GET['page']??1)); $perPage=25; $offset=($page-1)*$perPage; $params=[]; $where=[];
if($q!==''){ $where[]='(admin_email LIKE ? OR action LIKE ? OR details LIKE ? OR ip_address LIKE ?)'; $params[]="%{$q}%"; $params[]="%{$q}%"; $params[]="%{$q}%"; $params[]="%{$q}%"; }
if($action!==''){ $where[]='action = ?'; $params[]=$action; }
if($admin!==''){ $where[]='admin_email LIKE ?'; $params[]="%{$admin}%"; }
if($date!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){ $where[]='DATE(created_at) = ?'; $params[]=$date; }
$whereSql=$where?'WHERE '.implode(' AND ',$where):'';
$countStmt=db()->prepare("SELECT COUNT(*) FROM audit_log {$whereSql}"); $countStmt->execute($params); $total=(int)$countStmt->fetchColumn(); $pages=max(1,(int)ceil($total/$perPage));
$stmt=db()->prepare("SELECT * FROM audit_log {$whereSql} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"); $stmt->execute($params); $rows=$stmt->fetchAll();
$actions=db()->query('SELECT DISTINCT action FROM audit_log ORDER BY action')->fetchAll();
admin_layout_start('Audit Log','audit');
?>
<?php page_header('Security', 'Audit Log', 'Search admin activity and submission events.', $total . ' entries found', '<a class="btn secondary" href="audit.php?export=csv">Export CSV</a>'); ?>
<section class="panel"><form class="toolbar audit-toolbar" method="get"><label><span class="sr-only">Search audit entries</span><input name="q" value="<?php echo h($q); ?>" placeholder="Search audit entries"></label><label><span class="sr-only">Admin email</span><input name="admin" value="<?php echo h($admin); ?>" placeholder="Admin email"></label><label><span class="sr-only">Action</span><select name="action"><option value="">All actions</option><?php foreach($actions as $a): ?><option value="<?php echo h($a['action']); ?>" <?php echo $action===$a['action']?'selected':''; ?>><?php echo h(readable_action($a['action'])); ?></option><?php endforeach; ?></select></label><label><span class="sr-only">Date</span><input type="date" name="date" value="<?php echo h($date); ?>"></label><button class="btn primary">Search</button><a class="btn ghost" href="audit.php">Reset</a></form><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Admin</th><th>Action</th><th>Details</th><th>IP</th></tr></thead><tbody><?php if(!$rows): ?><tr><td colspan="5"><div class="empty-state compact">No audit entries found.</div></td></tr><?php endif; foreach($rows as $row): ?><tr><td data-label="Date"><?php echo h(date('M j, Y g:i A', strtotime((string)$row['created_at']))); ?></td><td data-label="Admin"><?php echo h($row['admin_email']); ?></td><td data-label="Action"><?php echo status_badge(readable_action($row['action'])); ?></td><td data-label="Details"><div class="details-wrap"><?php echo h($row['details']); ?></div></td><td data-label="IP"><?php echo h($row['ip_address']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php echo pagination_links('audit.php', $page, $pages, ['q'=>$q,'admin'=>$admin,'action'=>$action,'date'=>$date]); ?></section>
<?php admin_layout_end(); ?>
