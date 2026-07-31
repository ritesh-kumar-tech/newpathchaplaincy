<?php
require_once __DIR__ . '/auth.php';
require_role(['Super Admin']);
$q=trim($_GET['q']??''); $page=max(1,(int)($_GET['page']??1)); $perPage=25; $offset=($page-1)*$perPage; $params=[]; $where='';
if($q!==''){ $where='WHERE admin_email LIKE ? OR action LIKE ? OR details LIKE ? OR ip_address LIKE ?'; $params=["%{$q}%","%{$q}%","%{$q}%","%{$q}%"]; }
$countStmt=db()->prepare("SELECT COUNT(*) FROM audit_log {$where}"); $countStmt->execute($params); $total=(int)$countStmt->fetchColumn(); $pages=max(1,(int)ceil($total/$perPage));
$stmt=db()->prepare("SELECT * FROM audit_log {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"); $stmt->execute($params); $rows=$stmt->fetchAll();
admin_layout_start('Audit Log','audit');
?>
<div class="topbar"><div><p class="eyebrow">Security</p><h1>Audit Log</h1></div></div>
<section class="panel"><form class="toolbar" method="get"><input name="q" value="<?php echo h($q); ?>" placeholder="Search audit entries"><span></span><button class="btn primary">Search</button></form><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Admin</th><th>Action</th><th>Details</th><th>IP</th></tr></thead><tbody><?php if(!$rows): ?><tr><td colspan="5">No audit entries found.</td></tr><?php endif; foreach($rows as $row): ?><tr><td><?php echo h($row['created_at']); ?></td><td><?php echo h($row['admin_email']); ?></td><td><?php echo h($row['action']); ?></td><td><?php echo h($row['details']); ?></td><td><?php echo h($row['ip_address']); ?></td></tr><?php endforeach; ?></tbody></table></div><nav class="pagination" aria-label="Audit pagination"><?php for($i=1;$i<=$pages;$i++): ?><a class="<?php echo $i===$page?'active':''; ?>" href="audit.php?<?php echo h(http_build_query(['q'=>$q,'page'=>$i])); ?>"><?php echo h($i); ?></a><?php endfor; ?></nav></section>
<?php admin_layout_end(); ?>
