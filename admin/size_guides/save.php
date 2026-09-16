<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/cloudinary.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request.');
    redirect('/admin/size_guides/index.php');
}

$guideId = (int) ($_POST['guide_id'] ?? 0);
$stmt = $pdo->prepare('SELECT guide_key, name, image_public_id FROM size_guides WHERE guide_id = ?');
$stmt->execute([$guideId]);
$guide = $stmt->fetch();

if (!$guide) {
    set_flash('error', 'Size guide not found.');
    redirect('/admin/size_guides/index.php');
}

if (empty($_FILES['image']['tmp_name']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $error = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
    set_flash('error', $error === UPLOAD_ERR_NO_FILE ? 'Please choose an image.' : 'Image was not uploaded: ' . upload_error_message($error));
    redirect('/admin/size_guides/index.php');
}

try {
    $cloud = new CloudinaryUploader();
    $uploaded = $cloud->upload($_FILES['image']['tmp_name'], 'yvolution/size_guides');
    $pdo->prepare('UPDATE size_guides SET image_url = ?, image_public_id = ?, status = ? WHERE guide_id = ?')
        ->execute([$uploaded['secure_url'], $uploaded['public_id'], 'active', $guideId]);

    if (!empty($guide['image_public_id'])) {
        try {
            $cloud->destroy($guide['image_public_id']);
        } catch (Exception $e) {
            error_log('Old size guide image cleanup failed: ' . $e->getMessage());
        }
    }

    log_audit($pdo, $admin['user_id'], 'size_guide_updated', 'size_guides', $guideId, $guide['name']);
    set_flash('success', $guide['name'] . ' size guide uploaded.');
} catch (Exception $e) {
    error_log('Size guide image upload failed: ' . $e->getMessage());
    set_flash('error', 'Image upload failed: ' . $e->getMessage());
}

redirect('/admin/size_guides/index.php');
