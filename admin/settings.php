<?php
require_once __DIR__ . '/auth.php';
require_admin();
$user = find_admin_by_id((int)$_SESSION['admin_id']);
$error=''; $success='';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        admin_error_page('Security check failed', 'Please go back, reload the page, and try again.', 419);
        exit;
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $name = clean_text($_POST['name'] ?? '', 120); $email = strtolower(clean_text($_POST['email'] ?? '', 190));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error='Please enter a valid email.';
        else {
            $stmt=db()->prepare('UPDATE admin_users SET name=?, email=? WHERE id=?');
            $stmt->execute([$name,$email,(int)$user['id']]);
            $_SESSION['admin_name']=$name; $_SESSION['admin_email']=$email; audit_log('ACCOUNT_SETTINGS_UPDATED','', $email); $success='Profile saved.';
            $user=find_admin_by_id((int)$_SESSION['admin_id']);
        }
    }
    if ($action === 'site') {
        set_site_setting('contact_email', clean_text($_POST['contact_email'] ?? '', 190));
        set_site_setting('contact_phone', clean_text($_POST['contact_phone'] ?? '', 80));
        audit_log('SITE_SETTINGS_UPDATED','', $_SESSION['admin_email']); $success='Site settings saved.';
    }
}
admin_layout_start('Settings','settings');
?>
<?php page_header('Admin Profile', 'Settings', 'Manage your administrator profile and public contact details.', '', '<a class="btn secondary" href="change_password.php">Change Password</a>'); ?>
<?php if($error): ?><p class="form-note"><?php echo h($error); ?></p><?php endif; ?><?php if($success): ?><p class="form-note success"><?php echo h($success); ?></p><?php endif; ?>
<div class="settings-grid"><section class="panel"><div class="panel-heading"><div><h2>Profile</h2><p>Used in the admin header and audit trail.</p></div></div><form method="post" class="admin-form" data-prevent-duplicate><input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>"><input type="hidden" name="action" value="profile"><label>Name <span class="required">Required</span><input name="name" value="<?php echo h($user['name']); ?>" required autocomplete="name"></label><label>Email <span class="required">Required</span><input type="email" name="email" value="<?php echo h($user['email']); ?>" required autocomplete="email"></label><div class="form-actions"><button class="btn primary" data-loading-text="Saving...">Save Profile</button></div></form></section>
<section class="panel"><div class="panel-heading"><div><h2>Public Contact Details</h2><p>Displayed on the public website contact areas.</p></div></div><form method="post" class="admin-form" data-prevent-duplicate><input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>"><input type="hidden" name="action" value="site"><label>Contact Email<input type="email" name="contact_email" value="<?php echo h(site_setting('contact_email','info@newpathchaplaincy.com')); ?>"></label><label>Contact Phone<input name="contact_phone" value="<?php echo h(site_setting('contact_phone','(000) 000-0000')); ?>"></label><div class="form-actions"><button class="btn primary" data-loading-text="Saving...">Save Site Settings</button></div></form></section></div>
<?php admin_layout_end(); ?>
