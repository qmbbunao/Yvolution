<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();
$packages = $pdo->query("SELECT * FROM packages ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'Packages — Admin';
$activeNav = 'packages';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header">
    <h1>Team Packages</h1>
    <a href="<?= BASE_URL ?>/admin/packages/form.php" class="btn btn-accent">+ Add Package</a>
</div>

<div class="data-table-wrap">
    <table class="data-table">
        <thead><tr><th>Name</th><th>Price</th><th>Min. Qty</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($packages as $p): ?>
            <tr>
                <td><strong><?= e($p['name']) ?></strong><p style="font-size:12px;color:#888;margin:2px 0 0;"><?= e(mb_strimwidth($p['description'] ?? '', 0, 70, '...')) ?></p></td>
                <td><?= money($p['price']) ?></td>
                <td><?= (int) $p['min_quantity'] ?></td>
                <td><span class="badge <?= $p['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($p['status']) ?></span></td>
                <td>
                    <a href="<?= BASE_URL ?>/admin/packages/form.php?id=<?= (int) $p['package_id'] ?>" class="action-link edit">Edit</a>
                    <a href="<?= BASE_URL ?>/admin/packages/delete.php?id=<?= (int) $p['package_id'] ?>" class="action-link danger" onclick="return confirm('Delete this package?');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($packages)): ?>
            <tr><td colspan="5" class="empty-state">No packages yet. <a href="<?= BASE_URL ?>/admin/packages/form.php" class="text-link">Add one</a>.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
