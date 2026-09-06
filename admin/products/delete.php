<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();
$productId = (int) ($_GET['id'] ?? 0);

if (!$productId) {
    redirect('/admin/products/index.php');
}

// If this product is referenced by any order, we can't hard-delete it (keeps order history intact).
// Instead, just deactivate it so it disappears from the storefront.
$check = $pdo->prepare("SELECT COUNT(*) AS c FROM order_items WHERE item_type = 'product' AND item_ref_id = ?");
$check->execute([$productId]);

if ((int) $check->fetch()['c'] > 0) {
    $pdo->prepare("UPDATE products SET status = 'inactive' WHERE product_id = ?")->execute([$productId]);
    set_flash('success', 'Product has order history, so it was deactivated instead of deleted.');
} else {
    $pdo->prepare("DELETE FROM products WHERE product_id = ?")->execute([$productId]);
    set_flash('success', 'Product deleted.');
}

log_audit($pdo, $admin['user_id'], 'product_deleted', 'products', $productId);
redirect('/admin/products/index.php');
