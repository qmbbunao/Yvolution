<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/cloudinary.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request.');
    redirect('/admin/promotions/index.php');
}

$promoId = (int) ($_POST['promo_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$discountType = $_POST['discount_type'] === 'fixed' ? 'fixed' : 'percent';
$discountValue = (float) ($_POST['discount_value'] ?? 0);
$promoCode = trim($_POST['promo_code'] ?? '') ?: null;
$startDate = $_POST['start_date'] ?? date('Y-m-d');
$endDate = $_POST['end_date'] ?? date('Y-m-d');
$status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

if ($title === '' || $discountValue <= 0 || strtotime($endDate) < strtotime($startDate)) {
    set_flash('error', 'Please check the title, discount value, and date range.');
    redirect('/admin/promotions/form.php' . ($promoId ? '?id=' . $promoId : ''));
}

$imageUrl = null;
if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    try {
        $cloud = new CloudinaryUploader();
        $imageUrl = $cloud->upload($_FILES['image']['tmp_name'], 'yvolution/promotions')['secure_url'];
    } catch (Exception $e) {
        error_log('Promotion image upload failed: ' . $e->getMessage());
        set_flash('error', 'Promotion saved, but image upload failed: ' . $e->getMessage());
    }
} elseif (!empty($_FILES['image']['error']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    set_flash('error', 'Promotion saved, but image was not uploaded: ' . upload_error_message($_FILES['image']['error']));
}

try {
    if ($promoId) {
        if ($imageUrl) {
            $pdo->prepare(
                "UPDATE promotions SET title=?, description=?, discount_type=?, discount_value=?, promo_code=?, start_date=?, end_date=?, image_url=?, status=? WHERE promo_id=?"
            )->execute([$title, $description, $discountType, $discountValue, $promoCode, $startDate, $endDate, $imageUrl, $status, $promoId]);
        } else {
            $pdo->prepare(
                "UPDATE promotions SET title=?, description=?, discount_type=?, discount_value=?, promo_code=?, start_date=?, end_date=?, status=? WHERE promo_id=?"
            )->execute([$title, $description, $discountType, $discountValue, $promoCode, $startDate, $endDate, $status, $promoId]);
        }
        log_audit($pdo, $admin['user_id'], 'promotion_updated', 'promotions', $promoId, $title);
        set_flash('success', 'Promotion updated.');
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO promotions (title, description, discount_type, discount_value, promo_code, start_date, end_date, image_url, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$title, $description, $discountType, $discountValue, $promoCode, $startDate, $endDate, $imageUrl, $status, $admin['user_id']]);
        log_audit($pdo, $admin['user_id'], 'promotion_created', 'promotions', (int) $pdo->lastInsertId(), $title);
        set_flash('success', 'Promotion added.');
    }
} catch (PDOException $e) {
    set_flash('error', str_contains($e->getMessage(), 'promo_code') ? 'That promo code is already in use.' : 'Something went wrong saving the promotion.');
}

redirect('/admin/promotions/index.php');
