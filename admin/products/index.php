<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();
$products = $pdo->query(
    "SELECT p.*, c.name AS category_name FROM products p
     LEFT JOIN categories c ON c.category_id = p.category_id
     ORDER BY p.created_at DESC"
)->fetchAll();

$pageTitle = 'Products — Admin';
$activeNav = 'products';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header">
    <h1>Products</h1>
    <a href="<?= BASE_URL ?>/admin/products/form.php" class="btn btn-accent">+ Add Product</a>
</div>

<div class="data-table-wrap">
    <table class="data-table">
        <thead><tr><th></th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Featured</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td><img src="<?= e($p['image_url'] ?: BASE_URL . '/assets/images/products/placeholder.jpg') ?>" class="thumb"></td>
                <td><strong><?= e($p['name']) ?></strong></td>
                <td><?= e($p['category_name'] ?? '—') ?></td>
                <td><?= money($p['base_price']) ?></td>
                <td><?= (int) $p['stock_qty'] ?></td>
                <td><span class="badge <?= $p['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($p['status']) ?></span></td>
                <td><?= $p['is_featured'] ? '⭐' : '—' ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/admin/products/form.php?id=<?= (int) $p['product_id'] ?>" class="action-link edit">Edit</a>
                    <form method="POST" action="<?= BASE_URL ?>/admin/products/delete.php" style="display:inline;" onsubmit="return confirm('Delete this product?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= (int) $p['product_id'] ?>">
                        <button type="submit" class="action-link danger" style="background:none;border:none;cursor:pointer;">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($products)): ?>
            <tr><td colspan="8" class="empty-state">No products yet. <a href="<?= BASE_URL ?>/admin/products/form.php" class="text-link">Add your first one</a>.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
