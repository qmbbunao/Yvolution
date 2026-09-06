<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();
$bannerId = (int) ($_GET['id'] ?? 0);

if ($bannerId) {
    $pdo->prepare("DELETE FROM banners WHERE banner_id = ?")->execute([$bannerId]);
    log_audit($pdo, $admin['user_id'], 'banner_deleted', 'banners', $bannerId);
    set_flash('success', 'Banner deleted.');
}

redirect('/admin/homepage/banners.php');
