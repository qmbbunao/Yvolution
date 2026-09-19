<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();
$services = $pdo->query("SELECT * FROM services ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'Services — Admin';
$activeNav = 'services';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header">
    <h1>Services</h1>
    <a href="<?= BASE_URL ?>/admin/services/form.php" class="btn btn-accent">+ Add Service</a>
</div>

<div class="data-table-wrap">
    <table class="data-table">
        <thead><tr><th>Image</th><th>Name</th><th>Price</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($services as $s): ?>
            <tr>
                <td>
                    <img src="<?= e($s['image_url'] ?: BASE_URL . '/assets/images/products/placeholder.jpg') ?>" alt="<?= e($s['name']) ?>" style="display:block;width:64px;height:48px;object-fit:cover;">
                </td>
                <td><strong><?= e($s['name']) ?></strong><p style="font-size:12px;color:#888;margin:2px 0 0;"><?= e(mb_strimwidth($s['description'] ?? '', 0, 70, '...')) ?></p></td>
                <td><?= money($s['price']) ?> <span style="font-size:12px;color:#999;"><?= e($s['price_unit']) ?></span></td>
                <td><span class="badge <?= $s['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($s['status']) ?></span></td>
                <td>
                    <a href="<?= BASE_URL ?>/admin/services/form.php?id=<?= (int) $s['service_id'] ?>" class="action-link edit">Edit</a>
                    <form method="POST" action="<?= BASE_URL ?>/admin/services/delete.php" style="display:inline;" onsubmit="return confirm('Delete this service?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="service_id" value="<?= (int) $s['service_id'] ?>">
                        <button type="submit" class="action-link danger" style="background:none;border:none;cursor:pointer;">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($services)): ?>
            <tr><td colspan="5" class="empty-state">No services yet. <a href="<?= BASE_URL ?>/admin/services/form.php" class="text-link">Add one</a>.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
