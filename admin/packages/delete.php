<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();
$packageId = (int) ($_GET['id'] ?? 0);

if ($packageId) {
    $check = $pdo->prepare("SELECT COUNT(*) AS c FROM order_items WHERE item_type = 'package' AND item_ref_id = ?");
    $check->execute([$packageId]);

    if ((int) $check->fetch()['c'] > 0) {
        $pdo->prepare("UPDATE packages SET status = 'inactive' WHERE package_id = ?")->execute([$packageId]);
        set_flash('success', 'Package has order history, so it was deactivated instead of deleted.');
    } else {
        $pdo->prepare("DELETE FROM packages WHERE package_id = ?")->execute([$packageId]);
        set_flash('success', 'Package deleted.');
    }
    log_audit($pdo, $admin['user_id'], 'package_deleted', 'packages', $packageId);
}

redirect('/admin/packages/index.php');
