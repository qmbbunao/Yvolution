<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();
$packageId = (int) ($_POST['package_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid delete request.');
    redirect('/admin/packages/index.php');
}

if (!$packageId) {
    set_flash('error', 'Invalid package.');
    redirect('/admin/packages/index.php');
}

$deletedSuccessfully = false;

try {
    $deleted = $pdo->prepare("DELETE FROM packages WHERE package_id = ?");
    $deleted->execute([$packageId]);

    if ($deleted->rowCount() !== 1) {
        throw new RuntimeException('Package not found.');
    }

    $deletedSuccessfully = true;
    set_flash('success', 'Package deleted.');
} catch (Throwable $e) {
    error_log('Package deletion failed: ' . $e->getMessage());
    set_flash('error', 'Could not delete the package.');
}

try {
    if ($deletedSuccessfully) {
        log_audit($pdo, $admin['user_id'], 'package_deleted', 'packages', $packageId);
    }
} catch (Throwable $e) {
    error_log('Package deletion audit failed: ' . $e->getMessage());
}

redirect('/admin/packages/index.php');
