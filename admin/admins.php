<?php
require_once __DIR__ . '/auth.php';
require_role(['Super Admin']);
$roles = roles(); $error=''; $success='';
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="admin-users.csv"');
    $out = fopen('php://output', 'w'); fputcsv($out, ['Date Created','Name','Email','Role','Status']);
    foreach (db()->query('SELECT * FROM admin_users ORDER BY created_at DESC') as $row) fputcsv($out, [$row['created_at'],$row['name'],$row['email'],$row['role'],$row['active']?'Active':'Disabled']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        admin_error_page('Security check failed', 'Please go back, reload the page, and try again.', 419);
        exit;
    }
    $action=$_POST['action']??''; $target=(int)($_POST['id']??0);
    if ($action==='create') {
        $email=strtolower(clean_text($_POST['email']??'',190)); $password=$_POST['password']??'';
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) $error='Valid email required.';
        elseif(find_admin_by_email($email)) $error='Admin account already exists.';
        elseif(!password_is_strong($password)) $error='Temporary password must meet the strength requirements.';
        else { $hash=password_hash($password,PASSWORD_DEFAULT); $stmt=db()->prepare('INSERT INTO admin_users (name,email,password_hash,role,must_change_password,active,created_at) VALUES (?,?,?,?,1,1,NOW())'); $stmt->execute([clean_text($_POST['name']??'Admin User',120),$email,$hash,in_array($_POST['role']??'',$roles,true)?$_POST['role']:'Membership Administrator']); remember_admin_password((int)db()->lastInsertId(),$hash); audit_log('ADMIN_CREATED',$email,$_SESSION['admin_email']); $success='Administrator created.'; }
    } elseif ($action==='toggle' && $target !== (int)$_SESSION['admin_id']) {
        db()->prepare('UPDATE admin_users SET active = 1 - active WHERE id=?')->execute([$target]); audit_log('ADMIN_STATUS_TOGGLED','id='.$target,$_SESSION['admin_email']); $success='Administrator status updated.';
    } elseif ($action==='reset') {
        $password=$_POST['reset_password']??'';
        if(!password_is_strong($password)) $error='Reset password must meet the strength requirements.';
        else { $hash=password_hash($password,PASSWORD_DEFAULT); db()->prepare('UPDATE admin_users SET password_hash=?, must_change_password=1 WHERE id=?')->execute([$hash,$target]); remember_admin_password($target,$hash); audit_log('ADMIN_PASSWORD_RESET','id='.$target,$_SESSION['admin_email']); $success='Password reset.'; }
    }
}
$userSearch=trim($_GET['q']??''); $userStatus=trim($_GET['status']??'');
$users=db()->query('SELECT * FROM admin_users ORDER BY created_at DESC')->fetchAll();
if ($userSearch !== '' || $userStatus !== '') {
    $users = array_values(array_filter($users, function ($u) use ($userSearch, $userStatus) {
        $matchesSearch = $userSearch === '' || stripos($u['name'] . ' ' . $u['email'] . ' ' . $u['role'], $userSearch) !== false;
        $matchesStatus = $userStatus === '' || ($userStatus === 'active' && !empty($u['active'])) || ($userStatus === 'disabled' && empty($u['active']));
        return $matchesSearch && $matchesStatus;
    }));
}
admin_layout_start('Admin Users','admins');
?>
<?php page_header('Super Admin', 'Administrator Access', 'Create administrators, manage roles, and reset temporary passwords.', count($users) . ' administrators shown', '<a class="btn secondary" href="admins.php?export=csv">Export CSV</a>'); ?>
<?php if($error): ?><p class="form-note"><?php echo h($error); ?></p><?php endif; ?><?php if($success): ?><p class="form-note success"><?php echo h($success); ?></p><?php endif; ?>
<section class="panel"><div class="panel-heading"><div><h2>Create Administrator</h2><p>Temporary passwords must meet the current security rules.</p></div></div><form method="post" class="admin-form admin-create-form" data-prevent-duplicate><input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>"><input type="hidden" name="action" value="create"><label>Name<input name="name" required autocomplete="name"></label><label>Email<input type="email" name="email" required autocomplete="email"></label><label>Role<select name="role"><?php foreach($roles as $role): ?><option><?php echo h($role); ?></option><?php endforeach; ?></select></label><label>Temporary Password<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><div class="form-actions"><button class="btn primary" data-loading-text="Creating...">Create Admin</button></div><p class="login-hint">The new admin must change this temporary password on first login.</p></form></section>
<section class="panel"><div class="panel-heading"><div><h2>Existing Administrators</h2><p>Disable, enable, or reset access for administrator accounts.</p></div></div><form class="toolbar" method="get"><label><span class="sr-only">Search administrators</span><input name="q" value="<?php echo h($userSearch); ?>" placeholder="Search name, email, or role"></label><label><span class="sr-only">Status</span><select name="status"><option value="">All statuses</option><option value="active" <?php echo $userStatus==='active'?'selected':''; ?>>Active</option><option value="disabled" <?php echo $userStatus==='disabled'?'selected':''; ?>>Disabled</option></select></label><button class="btn primary">Filter</button><a class="btn ghost" href="admins.php">Reset</a></form><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Password</th><th>Actions</th></tr></thead><tbody><?php if(!$users): ?><tr><td colspan="6"><div class="empty-state compact">No administrators found.</div></td></tr><?php endif; foreach($users as $u): ?><tr><td data-label="Name"><strong><?php echo h($u['name']); ?></strong></td><td data-label="Email"><?php echo h($u['email']); ?></td><td data-label="Role"><?php echo h($u['role']); ?></td><td data-label="Status"><?php echo status_badge($u['active']?'Active':'Disabled'); ?></td><td data-label="Password"><?php echo status_badge($u['must_change_password']?'Must change':'Current'); ?></td><td data-label="Actions"><form method="post" class="actions admin-actions" data-prevent-duplicate><input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>"><input type="hidden" name="id" value="<?php echo h($u['id']); ?>"><button class="btn secondary" name="action" value="toggle" data-confirm="<?php echo $u['active']?'Disable this administrator?':'Enable this administrator?'; ?>" <?php echo (int)$u['id']===(int)$_SESSION['admin_id']?'disabled title="You cannot disable your own account here"':''; ?>><?php echo $u['active']?'Disable':'Enable'; ?></button><input type="password" name="reset_password" placeholder="New temp password" autocomplete="new-password"><button class="btn secondary" name="action" value="reset">Reset</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php admin_layout_end(); ?>
