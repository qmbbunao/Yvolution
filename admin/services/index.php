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
        <thead><tr><th>Name</th><th>Price</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($services as $s): ?>
            <tr>
                <td><strong><?= e($s['name']) ?></strong><p style="font-size:12px;color:#888;margin:2px 0 0;"><?= e(mb_strimwidth($s['description'] ?? '', 0, 70, '...')) ?></p></td>
                <td><?= money($s['price']) ?> <span style="font-size:12px;color:#999;"><?= e($s['price_unit']) ?></span></td>
                <td><span class="badge <?= $s['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($s['status']) ?></span></td>
                <td>
                    <a href="<?= BASE_URL ?>/admin/services/form.php?id=<?= (int) $s['service_id'] ?>" class="action-link edit">Edit</a>
                    <a href="<?= BASE_URL ?>/admin/services/delete.php?id=<?= (int) $s['service_id'] ?>" class="action-link danger" onclick="return confirm('Delete this service?');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($services)): ?>
            <tr><td colspan="4" class="empty-state">No services yet. <a href="<?= BASE_URL ?>/admin/services/form.php" class="text-link">Add one</a>.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
