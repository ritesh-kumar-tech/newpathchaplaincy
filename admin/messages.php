<?php
require_once __DIR__ . '/auth.php';
require_admin();
if (!role_can('messages')) { http_response_code(403); exit('Access denied.'); }
$columns = db()->query("SHOW COLUMNS FROM contact_messages LIKE 'archived'")->fetchAll();
if (!$columns) {
    db()->exec('ALTER TABLE contact_messages ADD archived TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read, ADD INDEX idx_contact_archived (archived)');
}
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="messages.csv"');
    $out = fopen('php://output', 'w'); fputcsv($out, ['Date','Name','Email','Subject','Message','Status','Archived']);
    foreach (db()->query('SELECT * FROM contact_messages ORDER BY created_at DESC') as $row) fputcsv($out, [$row['created_at'],$row['full_name'],$row['email'],$row['subject'],$row['message'],$row['is_read']?'Read':'Unread',$row['archived']?'Yes':'No']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        admin_error_page('Security check failed', 'Please go back, reload the page, and try again.', 419);
        exit;
    }
    $id=(int)($_POST['id']??0); $action=$_POST['action']??'';
    if ($action === 'delete') db()->prepare('DELETE FROM contact_messages WHERE id=?')->execute([$id]);
    elseif ($action === 'read') db()->prepare('UPDATE contact_messages SET is_read=1 WHERE id=?')->execute([$id]);
    elseif ($action === 'archive') db()->prepare('UPDATE contact_messages SET archived=1, is_read=1 WHERE id=?')->execute([$id]);
    elseif ($action === 'restore') db()->prepare('UPDATE contact_messages SET archived=0 WHERE id=?')->execute([$id]);
    audit_log('MESSAGE_'.$action, 'id='.$id, $_SESSION['admin_email']); header('Location: messages.php'); exit;
}
$q=trim($_GET['q']??''); $view=($_GET['view']??'inbox') === 'archive' ? 'archive' : 'inbox'; $page=max(1,(int)($_GET['page']??1)); $perPage=12; $offset=($page-1)*$perPage; $params=[]; $where=['archived = ?']; $params[]=$view === 'archive' ? 1 : 0;
if($q!==''){ $where[]='(full_name LIKE ? OR email LIKE ? OR subject LIKE ?)'; $params[]="%{$q}%"; $params[]="%{$q}%"; $params[]="%{$q}%"; }
$whereSql=' WHERE '.implode(' AND ', $where); $countStmt=db()->prepare('SELECT COUNT(*) FROM contact_messages'.$whereSql); $countStmt->execute($params); $total=(int)$countStmt->fetchColumn(); $pages=max(1,(int)ceil($total/$perPage));
$stmt=db()->prepare('SELECT * FROM contact_messages'.$whereSql." ORDER BY is_read ASC, created_at DESC LIMIT {$perPage} OFFSET {$offset}"); $stmt->execute($params); $rows=$stmt->fetchAll();
admin_layout_start('Contact Messages','messages');
?>
<?php page_header('Communications', 'Contact Inbox', 'Search, read, archive, delete, and reply to website messages.', $total . ' messages in ' . ($view === 'archive' ? 'archive' : 'inbox'), '<a class="btn secondary" href="messages.php?export=csv">Export CSV</a>'); ?>
<section class="panel"><form class="toolbar" method="get"><label><span class="sr-only">Search messages</span><input name="q" value="<?php echo h($q); ?>" placeholder="Search sender, email, or subject"></label><label><span class="sr-only">Inbox view</span><select name="view"><option value="inbox" <?php echo $view==='inbox'?'selected':''; ?>>Inbox</option><option value="archive" <?php echo $view==='archive'?'selected':''; ?>>Archive</option></select></label><button class="btn primary">Search</button><a class="btn ghost" href="messages.php">Reset</a></form><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Sender</th><th>Subject</th><th>Message</th><th>Actions</th></tr></thead><tbody><?php if(!$rows): ?><tr><td colspan="5"><div class="empty-state compact">No messages found.</div></td></tr><?php endif; foreach($rows as $row): ?><tr class="<?php echo $row['is_read']?'':'unread-row'; ?>"><td data-label="Date"><?php echo h(date('M j, Y g:i A', strtotime((string)$row['created_at']))); ?></td><td data-label="Sender"><strong><?php echo h($row['full_name']); ?></strong><br><a href="mailto:<?php echo h($row['email']); ?>"><?php echo h($row['email']); ?></a></td><td data-label="Subject"><?php echo status_badge($row['is_read']?'Read':'Unread'); ?><br><strong><?php echo h($row['subject']); ?></strong></td><td data-label="Message"><div class="message-preview"><?php echo h($row['message']); ?></div></td><td data-label="Actions"><form method="post" class="actions" data-prevent-duplicate><input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>"><input type="hidden" name="id" value="<?php echo h($row['id']); ?>"><a class="btn secondary" href="mailto:<?php echo h($row['email']); ?>">Reply</a><button class="btn secondary" name="action" value="read">Mark Read</button><button class="btn secondary" name="action" value="<?php echo $view==='archive'?'restore':'archive'; ?>" data-confirm="<?php echo $view==='archive'?'Restore this message?':'Archive this message?'; ?>"><?php echo $view==='archive'?'Restore':'Archive'; ?></button><button class="btn danger" name="action" value="delete" data-confirm="Delete this message?">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php echo pagination_links('messages.php', $page, $pages, ['q'=>$q,'view'=>$view]); ?></section>
<?php admin_layout_end(); ?>
