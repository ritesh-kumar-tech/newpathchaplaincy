<?php
require_once __DIR__ . '/auth.php';
require_admin();
if (!role_can('messages')) { http_response_code(403); exit('Access denied.'); }
$columns = db()->query("SHOW COLUMNS FROM contact_messages LIKE 'archived'")->fetchAll();
if (!$columns) {
    db()->exec('ALTER TABLE contact_messages ADD archived TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read, ADD INDEX idx_contact_archived (archived)');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)) {
    $id=(int)($_POST['id']??0); $action=$_POST['action']??'';
    if ($action === 'delete') db()->prepare('DELETE FROM contact_messages WHERE id=?')->execute([$id]);
    if ($action === 'read') db()->prepare('UPDATE contact_messages SET is_read=1 WHERE id=?')->execute([$id]);
    if ($action === 'archive') db()->prepare('UPDATE contact_messages SET archived=1, is_read=1 WHERE id=?')->execute([$id]);
    if ($action === 'restore') db()->prepare('UPDATE contact_messages SET archived=0 WHERE id=?')->execute([$id]);
    audit_log('MESSAGE_'.$action, 'id='.$id, $_SESSION['admin_email']); header('Location: messages.php'); exit;
}
$q=trim($_GET['q']??''); $view=($_GET['view']??'inbox') === 'archive' ? 'archive' : 'inbox'; $params=[]; $where=['archived = ?']; $params[]=$view === 'archive' ? 1 : 0;
if($q!==''){ $where[]='(full_name LIKE ? OR email LIKE ? OR subject LIKE ?)'; $params[]="%{$q}%"; $params[]="%{$q}%"; $params[]="%{$q}%"; }
$stmt=db()->prepare('SELECT * FROM contact_messages WHERE '.implode(' AND ', $where).' ORDER BY is_read ASC, created_at DESC'); $stmt->execute($params); $rows=$stmt->fetchAll();
admin_layout_start('Contact Messages','messages');
?>
<div class="topbar"><div><p class="eyebrow">Communications</p><h1>Contact Inbox</h1></div></div>
<section class="panel"><form class="toolbar" method="get"><input name="q" value="<?php echo h($q); ?>" placeholder="Search messages"><select name="view"><option value="inbox" <?php echo $view==='inbox'?'selected':''; ?>>Inbox</option><option value="archive" <?php echo $view==='archive'?'selected':''; ?>>Archive</option></select><button class="btn primary">Search</button></form><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Sender</th><th>Subject</th><th>Message</th><th>Actions</th></tr></thead><tbody><?php if(!$rows): ?><tr><td colspan="5">No messages found.</td></tr><?php endif; foreach($rows as $row): ?><tr><td><?php echo h($row['created_at']); ?></td><td><?php echo h($row['full_name']); ?><br><a href="mailto:<?php echo h($row['email']); ?>"><?php echo h($row['email']); ?></a></td><td><span class="badge <?php echo $row['is_read']?'gray':'green'; ?>"><?php echo $row['is_read']?'Read':'Unread'; ?></span><br><?php echo h($row['subject']); ?></td><td><?php echo h($row['message']); ?></td><td class="actions"><form method="post"><input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>"><input type="hidden" name="id" value="<?php echo h($row['id']); ?>"><a class="btn secondary" href="mailto:<?php echo h($row['email']); ?>">Reply</a><button class="btn secondary" name="action" value="read">Mark Read</button><button class="btn secondary" name="action" value="<?php echo $view==='archive'?'restore':'archive'; ?>"><?php echo $view==='archive'?'Restore':'Archive'; ?></button><button class="btn secondary" name="action" value="delete" onclick="return confirm('Delete this message?')">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php admin_layout_end(); ?>
