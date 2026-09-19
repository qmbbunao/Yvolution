<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();
$productId = (int) ($_POST['product_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid delete request.');
    redirect('/admin/products/index.php');
}

if (!$productId) {
    redirect('/admin/products/index.php');
}

$deletedSuccessfully = false;

try {
    $pdo->beginTransaction();

    // Keep order_items as historical snapshots while removing catalog references.
    $pdo->prepare("UPDATE feedback SET product_id = NULL WHERE product_id = ?")->execute([$productId]);
    $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$productId]);
    $deleted = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
    $deleted->execute([$productId]);

    if ($deleted->rowCount() !== 1) {
        throw new RuntimeException('Product not found.');
    }

    $pdo->commit();
    $deletedSuccessfully = true;
    set_flash('success', 'Product deleted.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Product deletion failed: ' . $e->getMessage());
    set_flash('error', 'Could not delete the product.');
}

try {
    if ($deletedSuccessfully) {
        log_audit($pdo, $admin['user_id'], 'product_deleted', 'products', $productId);
    }
} catch (Throwable $e) {
    error_log('Product deletion audit failed: ' . $e->getMessage());
}

redirect('/admin/products/index.php');
