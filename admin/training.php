<?php
require_once __DIR__ . '/auth.php';
require_admin();
if (!role_can('training')) { http_response_code(403); exit('Access denied.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        admin_error_page('Security check failed', 'Please go back, reload the page, and try again.', 419);
        exit;
    }
    foreach ($_POST['modules'] ?? [] as $id => $module) {
        $stmt = db()->prepare('UPDATE training_modules SET title = ?, description = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([clean_text($module['title'] ?? '', 160), clean_text($module['description'] ?? '', 900), (int)$id]);
    }
    audit_log('TRAINING_UPDATED', 'training modules edited', $_SESSION['admin_email']);
    header('Location: training.php?saved=1'); exit;
}
$modules = db()->query('SELECT * FROM training_modules ORDER BY sort_order, id')->fetchAll();
admin_layout_start('Training CMS', 'training');
?>
<?php page_header('Lightweight CMS', 'Training Content', 'Edit the training cards that are pulled into the public website.', count($modules) . ' training cards'); ?>
<?php if (!empty($_GET['saved'])): ?><p class="form-note success">Training content saved.</p><?php endif; ?>
<section class="panel"><form method="post" class="training-editor" data-prevent-duplicate><input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>"><div class="cms-grid"><?php foreach ($modules as $module): ?><article class="cms-card"><div class="cms-card-head"><span>Step <?php echo h($module['step_number']); ?></span><?php echo status_badge('Active'); ?></div><label>Title<input name="modules[<?php echo h($module['id']); ?>][title]" value="<?php echo h($module['title']); ?>" required maxlength="160"></label><label>Description<textarea name="modules[<?php echo h($module['id']); ?>][description]" required maxlength="900"><?php echo h($module['description']); ?></textarea></label></article><?php endforeach; ?></div><div class="form-actions"><a class="btn secondary" href="../index.html#training" target="_blank" rel="noopener">Preview</a><button class="btn primary" data-loading-text="Saving...">Save Training Cards</button></div></form></section>
<?php admin_layout_end(); ?>
