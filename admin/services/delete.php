<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();
$serviceId = (int) ($_GET['id'] ?? 0);

if ($serviceId) {
    $check = $pdo->prepare("SELECT COUNT(*) AS c FROM order_items WHERE item_type = 'service' AND item_ref_id = ?");
    $check->execute([$serviceId]);

    if ((int) $check->fetch()['c'] > 0) {
        $pdo->prepare("UPDATE services SET status = 'inactive' WHERE service_id = ?")->execute([$serviceId]);
        set_flash('success', 'Service has order history, so it was deactivated instead of deleted.');
    } else {
        $pdo->prepare("DELETE FROM services WHERE service_id = ?")->execute([$serviceId]);
        set_flash('success', 'Service deleted.');
    }
    log_audit($pdo, $admin['user_id'], 'service_deleted', 'services', $serviceId);
}

redirect('/admin/services/index.php');
