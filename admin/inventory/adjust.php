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
$changeQty = (int) ($_POST['change_qty'] ?? 0);

if (!$inventoryId || $changeQty === 0) {
    set_flash('error', 'Enter a non-zero quantity to adjust.');
    redirect('/admin/inventory/index.php');
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("SELECT quantity_on_hand FROM inventory WHERE inventory_id = ? FOR UPDATE");
    $stmt->execute([$inventoryId]);
    $current = $stmt->fetch();

    if (!$current) {
        throw new RuntimeException('Item not found.');
    }

    $newQty = max(0, (int) $current['quantity_on_hand'] + $changeQty);

    $pdo->prepare("UPDATE inventory SET quantity_on_hand = ? WHERE inventory_id = ?")->execute([$newQty, $inventoryId]);
    $pdo->prepare("INSERT INTO inventory_logs (inventory_id, change_qty, reason, changed_by) VALUES (?, ?, ?, ?)")
        ->execute([$inventoryId, $changeQty, $changeQty > 0 ? 'Stock in (manual)' : 'Stock out (manual)', $admin['user_id']]);

    $pdo->commit();
    set_flash('success', 'Stock updated.');
} catch (Exception $e) {
    $pdo->rollBack();
    set_flash('error', 'Could not update stock: ' . $e->getMessage());
}

redirect('/admin/inventory/index.php');
