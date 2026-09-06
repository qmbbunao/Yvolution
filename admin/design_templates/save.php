<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/cloudinary.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request.');
    redirect('/admin/design_templates/index.php');
}

$templateId = (int) ($_POST['template_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$eventType = trim($_POST['event_type'] ?? '') ?: null;
$status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

if ($name === '') {
    set_flash('error', 'Please provide a design name.');
    redirect('/admin/design_templates/form.php' . ($templateId ? '?id=' . $templateId : ''));
}

$imageUrl = null;
if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    try {
        $cloud = new CloudinaryUploader();
        $imageUrl = $cloud->upload($_FILES['image']['tmp_name'], 'yvolution/design_templates')['secure_url'];
    } catch (Exception $e) {
        error_log('Design template image upload failed: ' . $e->getMessage());
        set_flash('error', 'Image upload failed: ' . $e->getMessage());
        redirect('/admin/design_templates/form.php' . ($templateId ? '?id=' . $templateId : ''));
    }
} elseif (!empty($_FILES['image']['error']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    set_flash('error', 'Image was not uploaded: ' . upload_error_message($_FILES['image']['error']));
    redirect('/admin/design_templates/form.php' . ($templateId ? '?id=' . $templateId : ''));
} elseif (!$templateId) {
    set_flash('error', 'Please choose an image for this design.');
    redirect('/admin/design_templates/form.php');
}

if ($templateId) {
    if ($imageUrl) {
        $pdo->prepare("UPDATE design_templates SET name=?, event_type=?, image_url=?, status=? WHERE template_id=?")
            ->execute([$name, $eventType, $imageUrl, $status, $templateId]);
    } else {
        $pdo->prepare("UPDATE design_templates SET name=?, event_type=?, status=? WHERE template_id=?")
            ->execute([$name, $eventType, $status, $templateId]);
    }
    log_audit($pdo, $admin['user_id'], 'design_template_updated', 'design_templates', $templateId, $name);
    set_flash('success', 'Design updated.');
} else {
    $stmt = $pdo->prepare("INSERT INTO design_templates (name, event_type, image_url, status, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $eventType, $imageUrl, $status, $admin['user_id']]);
    log_audit($pdo, $admin['user_id'], 'design_template_created', 'design_templates', (int) $pdo->lastInsertId(), $name);
    set_flash('success', 'Design added.');
}

redirect('/admin/design_templates/index.php');
