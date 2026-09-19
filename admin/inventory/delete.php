<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request.');
    redirect('/admin/inventory/index.php');
}

$inventoryId = (int) ($_POST['inventory_id'] ?? 0);
if (!$inventoryId) {
    set_flash('error', 'Invalid inventory item.');
    redirect('/admin/inventory/index.php');
}

try {
    $stmt = $pdo->prepare('SELECT item_name FROM inventory WHERE inventory_id = ?');
    $stmt->execute([$inventoryId]);
    $item = $stmt->fetch();

    if (!$item) {
        set_flash('error', 'Inventory item not found.');
        redirect('/admin/inventory/index.php');
    }

    $pdo->prepare('DELETE FROM inventory WHERE inventory_id = ?')->execute([$inventoryId]);
    log_audit($pdo, $admin['user_id'], 'inventory_item_deleted', 'inventory', $inventoryId, $item['item_name']);
    set_flash('success', 'Inventory item deleted.');
} catch (PDOException $e) {
    error_log('Inventory item deletion failed: ' . $e->getMessage());
    set_flash('error', 'Could not delete the inventory item.');
}

redirect('/admin/inventory/index.php');
