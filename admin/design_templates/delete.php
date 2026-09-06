<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();
$templateId = (int) ($_GET['id'] ?? 0);

if ($templateId) {
    $pdo->prepare("UPDATE design_templates SET status = 'inactive' WHERE template_id = ?")->execute([$templateId]);
    // Soft-delete only: past orders may still reference this template_id via design_uploads
    log_audit($pdo, $admin['user_id'], 'design_template_deleted', 'design_templates', $templateId);
    set_flash('success', 'Design removed from the catalog.');
}

redirect('/admin/design_templates/index.php');
