<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();
$promotions = $pdo->query("SELECT * FROM promotions ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'Promotions — Admin';
$activeNav = 'promotions';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header">
    <h1>Promotions</h1>
    <a href="<?= BASE_URL ?>/admin/promotions/form.php" class="btn btn-accent">+ Add Promotion</a>
</div>

<div class="data-table-wrap">
    <table class="data-table">
        <thead><tr><th>Title</th><th>Discount</th><th>Code</th><th>Dates</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($promotions as $p):
            $isExpired = strtotime($p['end_date']) < strtotime('today');
        ?>
            <tr>
                <td><strong><?= e($p['title']) ?></strong></td>
                <td><?= $p['discount_type'] === 'percent' ? (int) $p['discount_value'] . '% OFF' : money($p['discount_value']) . ' OFF' ?></td>
                <td><?= e($p['promo_code'] ?: '—') ?></td>
                <td style="font-size:13px;color:#777;"><?= date('M j', strtotime($p['start_date'])) ?> – <?= date('M j, Y', strtotime($p['end_date'])) ?></td>
                <td>
                    <?php if ($isExpired): ?>
                        <span class="badge badge-danger">Expired</span>
                    <?php else: ?>
                        <span class="badge <?= $p['status'] === 'active' ? 'badge-success' : 'badge-pending' ?>"><?= ucfirst($p['status']) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?= BASE_URL ?>/admin/promotions/form.php?id=<?= (int) $p['promo_id'] ?>" class="action-link edit">Edit</a>
                    <a href="<?= BASE_URL ?>/admin/promotions/delete.php?id=<?= (int) $p['promo_id'] ?>" class="action-link danger" onclick="return confirm('Delete this promotion?');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($promotions)): ?>
            <tr><td colspan="6" class="empty-state">No promotions yet. <a href="<?= BASE_URL ?>/admin/promotions/form.php" class="text-link">Add one</a>.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
