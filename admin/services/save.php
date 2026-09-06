<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/cloudinary.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request.');
    redirect('/admin/services/index.php');
}

$serviceId = (int) ($_POST['service_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$price = (float) ($_POST['price'] ?? 0);
$priceUnit = trim($_POST['price_unit'] ?? 'per piece');
$requiresTarpOptions = !empty($_POST['requires_tarp_options']) ? 1 : 0;
$status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

if ($name === '' || $price < 0) {
    set_flash('error', 'Please provide a valid name and price.');
    redirect('/admin/services/form.php' . ($serviceId ? '?id=' . $serviceId : ''));
}

$imageUrl = null;
if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    try {
        $cloud = new CloudinaryUploader();
        $imageUrl = $cloud->upload($_FILES['image']['tmp_name'], 'yvolution/services')['secure_url'];
    } catch (Exception $e) {
        error_log('Service image upload failed: ' . $e->getMessage());
        set_flash('error', 'Service saved, but image upload failed: ' . $e->getMessage());
    }
} elseif (!empty($_FILES['image']['error']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    set_flash('error', 'Service saved, but image was not uploaded: ' . upload_error_message($_FILES['image']['error']));
}

if ($serviceId) {
    if ($imageUrl) {
        $pdo->prepare("UPDATE services SET name=?, description=?, price=?, price_unit=?, image_url=?, status=?, requires_tarp_options=? WHERE service_id=?")
            ->execute([$name, $description, $price, $priceUnit, $imageUrl, $status, $requiresTarpOptions, $serviceId]);
    } else {
        $pdo->prepare("UPDATE services SET name=?, description=?, price=?, price_unit=?, status=?, requires_tarp_options=? WHERE service_id=?")
            ->execute([$name, $description, $price, $priceUnit, $status, $requiresTarpOptions, $serviceId]);
    }
    log_audit($pdo, $admin['user_id'], 'service_updated', 'services', $serviceId, $name);
    set_flash('success', 'Service updated.');
} else {
    $stmt = $pdo->prepare("INSERT INTO services (name, description, price, price_unit, image_url, status, requires_tarp_options) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $description, $price, $priceUnit, $imageUrl, $status, $requiresTarpOptions]);
    log_audit($pdo, $admin['user_id'], 'service_created', 'services', (int) $pdo->lastInsertId(), $name);
    set_flash('success', 'Service added.');
}

redirect('/admin/services/index.php');
