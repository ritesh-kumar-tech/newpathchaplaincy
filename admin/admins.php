<?php
require_once __DIR__ . '/auth.php';
require_role(['Super Admin']);
$roles = roles(); $error=''; $success='';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)) {
    $action=$_POST['action']??''; $target=(int)($_POST['id']??0);
    if ($action==='create') {
        $email=strtolower(clean_text($_POST['email']??'',190)); $password=$_POST['password']??'';
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) $error='Valid email required.';
        elseif(find_admin_by_email($email)) $error='Admin account already exists.';
        elseif(!password_is_strong($password)) $error='Temporary password must meet the strength requirements.';
        else { $hash=password_hash($password,PASSWORD_DEFAULT); $stmt=db()->prepare('INSERT INTO admin_users (name,email,password_hash,role,two_factor_enabled,two_factor_code,must_change_password,active,created_at) VALUES (?,?,?,?,1,?,1,1,NOW())'); $stmt->execute([clean_text($_POST['name']??'Admin User',120),$email,$hash,in_array($_POST['role']??'',$roles,true)?$_POST['role']:'Membership Administrator',clean_text($_POST['two_factor_code']??'202626',20)]); remember_admin_password((int)db()->lastInsertId(),$hash); audit_log('ADMIN_CREATED',$email,$_SESSION['admin_email']); $success='Administrator created.'; }
    } elseif ($action==='toggle' && $target !== (int)$_SESSION['admin_id']) {
        db()->prepare('UPDATE admin_users SET active = 1 - active WHERE id=?')->execute([$target]); audit_log('ADMIN_STATUS_TOGGLED','id='.$target,$_SESSION['admin_email']); $success='Administrator status updated.';
    } elseif ($action==='reset') {
        $password=$_POST['reset_password']??'';
        if(!password_is_strong($password)) $error='Reset password must meet the strength requirements.';
        else { $hash=password_hash($password,PASSWORD_DEFAULT); db()->prepare('UPDATE admin_users SET password_hash=?, must_change_password=1 WHERE id=?')->execute([$hash,$target]); remember_admin_password($target,$hash); audit_log('ADMIN_PASSWORD_RESET','id='.$target,$_SESSION['admin_email']); $success='Password reset.'; }
    }
}
$users=db()->query('SELECT * FROM admin_users ORDER BY created_at DESC')->fetchAll();
admin_layout_start('Admin Users','admins');
?>
<div class="topbar"><div><p class="eyebrow">Super Admin</p><h1>Administrator Access</h1></div></div>
<?php if($error): ?><p class="form-note"><?php echo h($error); ?></p><?php endif; ?><?php if($success): ?><p class="form-note success"><?php echo h($success); ?></p><?php endif; ?>
<section class="panel"><h2>Create Administrator</h2><form method="post" class="admin-form"><input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>"><input type="hidden" name="action" value="create"><label>Name<input name="name" required></label><label>Email<input type="email" name="email" required></label><label>Role<select name="role"><?php foreach($roles as $role): ?><option><?php echo h($role); ?></option><?php endforeach; ?></select></label><label>Temporary Password<input type="password" name="password" required minlength="12"></label><label>2FA Code<input name="two_factor_code" value="202626"></label><button class="btn primary">Create Admin</button></form></section>
<br><section class="panel"><h2>Existing Administrators</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Password</th><th>Actions</th></tr></thead><tbody><?php foreach($users as $u): ?><tr><td><?php echo h($u['name']); ?></td><td><?php echo h($u['email']); ?></td><td><?php echo h($u['role']); ?></td><td><?php echo $u['active']?'Active':'Disabled'; ?></td><td><?php echo $u['must_change_password']?'Must change':'Current'; ?></td><td><form method="post" class="actions"><input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>"><input type="hidden" name="id" value="<?php echo h($u['id']); ?>"><button class="btn secondary" name="action" value="toggle" <?php echo (int)$u['id']===(int)$_SESSION['admin_id']?'disabled':''; ?>><?php echo $u['active']?'Disable':'Enable'; ?></button><input type="password" name="reset_password" placeholder="New temp password"><button class="btn secondary" name="action" value="reset">Reset</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php admin_layout_end(); ?>
