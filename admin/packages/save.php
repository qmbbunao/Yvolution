<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/cloudinary.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request.');
    redirect('/admin/packages/index.php');
}

$packageId = (int) ($_POST['package_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$includes = trim($_POST['includes'] ?? '');
$price = (float) ($_POST['price'] ?? 0);
$minQty = max(1, (int) ($_POST['min_quantity'] ?? 1));
$status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

if ($name === '' || $price < 0) {
    set_flash('error', 'Please provide a valid name and price.');
    redirect('/admin/packages/form.php' . ($packageId ? '?id=' . $packageId : ''));
}

$imageUrl = null;
if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    try {
        $cloud = new CloudinaryUploader();
        $imageUrl = $cloud->upload($_FILES['image']['tmp_name'], 'yvolution/packages')['secure_url'];
    } catch (Exception $e) {
        error_log('Package image upload failed: ' . $e->getMessage());
        set_flash('error', 'Package saved, but image upload failed: ' . $e->getMessage());
    }
} elseif (!empty($_FILES['image']['error']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    set_flash('error', 'Package saved, but image was not uploaded: ' . upload_error_message($_FILES['image']['error']));
}

if ($packageId) {
    if ($imageUrl) {
        $pdo->prepare("UPDATE packages SET name=?, description=?, includes=?, price=?, min_quantity=?, image_url=?, status=? WHERE package_id=?")
            ->execute([$name, $description, $includes, $price, $minQty, $imageUrl, $status, $packageId]);
    } else {
        $pdo->prepare("UPDATE packages SET name=?, description=?, includes=?, price=?, min_quantity=?, status=? WHERE package_id=?")
            ->execute([$name, $description, $includes, $price, $minQty, $status, $packageId]);
    }
    log_audit($pdo, $admin['user_id'], 'package_updated', 'packages', $packageId, $name);
    set_flash('success', 'Package updated.');
} else {
    $stmt = $pdo->prepare("INSERT INTO packages (name, description, includes, price, min_quantity, image_url, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $description, $includes, $price, $minQty, $imageUrl, $status]);
    log_audit($pdo, $admin['user_id'], 'package_created', 'packages', (int) $pdo->lastInsertId(), $name);
    set_flash('success', 'Package added.');
}

redirect('/admin/packages/index.php');
