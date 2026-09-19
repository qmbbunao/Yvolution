<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();
$serviceId = (int) ($_POST['service_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid delete request.');
    redirect('/admin/services/index.php');
}

if (!$serviceId) {
    set_flash('error', 'Invalid service.');
    redirect('/admin/services/index.php');
}

$deletedSuccessfully = false;

try {
    $deleted = $pdo->prepare("DELETE FROM services WHERE service_id = ?");
    $deleted->execute([$serviceId]);

    if ($deleted->rowCount() !== 1) {
        throw new RuntimeException('Service not found.');
    }

    $deletedSuccessfully = true;
    set_flash('success', 'Service deleted.');
} catch (Throwable $e) {
    error_log('Service deletion failed: ' . $e->getMessage());
    set_flash('error', 'Could not delete the service.');
}

try {
    if ($deletedSuccessfully) {
        log_audit($pdo, $admin['user_id'], 'service_deleted', 'services', $serviceId);
    }
} catch (Throwable $e) {
    error_log('Service deletion audit failed: ' . $e->getMessage());
}

redirect('/admin/services/index.php');
