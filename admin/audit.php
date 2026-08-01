<?php
require_once __DIR__ . '/auth.php';
require_role(['Super Admin']);
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
<div class="topbar"><div><p class="eyebrow">Security</p><h1>Audit Log</h1></div></div>
<section class="panel"><form class="toolbar audit-toolbar" method="get"><input name="q" value="<?php echo h($q); ?>" placeholder="Search audit entries"><input name="admin" value="<?php echo h($admin); ?>" placeholder="Admin email"><select name="action"><option value="">All actions</option><?php foreach($actions as $a): ?><option value="<?php echo h($a['action']); ?>" <?php echo $action===$a['action']?'selected':''; ?>><?php echo h($a['action']); ?></option><?php endforeach; ?></select><input type="date" name="date" value="<?php echo h($date); ?>"><button class="btn primary">Search</button></form><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Admin</th><th>Action</th><th>Details</th><th>IP</th></tr></thead><tbody><?php if(!$rows): ?><tr><td colspan="5">No audit entries found.</td></tr><?php endif; foreach($rows as $row): ?><tr><td data-label="Date"><?php echo h($row['created_at']); ?></td><td data-label="Admin"><?php echo h($row['admin_email']); ?></td><td data-label="Action"><?php echo h($row['action']); ?></td><td data-label="Details"><?php echo h($row['details']); ?></td><td data-label="IP"><?php echo h($row['ip_address']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php echo pagination_links('audit.php', $page, $pages, ['q'=>$q,'admin'=>$admin,'action'=>$action,'date'=>$date]); ?></section>
<?php admin_layout_end(); ?>
