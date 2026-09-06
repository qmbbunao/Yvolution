<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request.');
    redirect('/admin/inventory/index.php');
}

$itemName = trim($_POST['item_name'] ?? '');
$sku = trim($_POST['sku'] ?? '') ?: null;
$category = trim($_POST['category'] ?? '');
$qty = (int) ($_POST['quantity_on_hand'] ?? 0);
$reorder = (int) ($_POST['reorder_level'] ?? 10);
$unit = trim($_POST['unit'] ?? 'pcs');

if ($itemName === '') {
    set_flash('error', 'Item name is required.');
    redirect('/admin/inventory/index.php');
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO inventory (item_name, sku, category, unit, quantity_on_hand, reorder_level) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$itemName, $sku, $category, $unit, $qty, $reorder]);
    log_audit($pdo, $admin['user_id'], 'inventory_item_created', 'inventory', (int) $pdo->lastInsertId(), $itemName);
    set_flash('success', 'Inventory item added.');
} catch (PDOException $e) {
    set_flash('error', str_contains($e->getMessage(), 'sku') ? 'That SKU is already in use.' : 'Something went wrong.');
}

redirect('/admin/inventory/index.php');
