<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $id = (int) ($_POST['testimonial_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $pdo->prepare("UPDATE testimonials SET status = 'approved' WHERE testimonial_id = ?")->execute([$id]);
    } elseif ($action === 'hide') {
        $pdo->prepare("UPDATE testimonials SET status = 'hidden' WHERE testimonial_id = ?")->execute([$id]);
    } elseif ($action === 'feature') {
        $pdo->prepare("UPDATE testimonials SET is_featured = 1 WHERE testimonial_id = ?")->execute([$id]);
    } elseif ($action === 'unfeature') {
        $pdo->prepare("UPDATE testimonials SET is_featured = 0 WHERE testimonial_id = ?")->execute([$id]);
    }
    log_audit($pdo, $admin['user_id'], 'testimonial_' . $action, 'testimonials', $id);
    redirect('/admin/homepage/testimonials.php');
}

$testimonials = $pdo->query("SELECT * FROM testimonials ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'Testimonials — Admin';
$activeNav = 'homepage';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header"><h1>Homepage CMS</h1></div>
<div style="display:flex;gap:16px;margin-bottom:24px;">
    <a href="<?= BASE_URL ?>/admin/homepage/content.php" class="btn btn-outline btn-sm" style="border-color:#ccc;color:#333;">Hero & About</a>
    <a href="<?= BASE_URL ?>/admin/homepage/banners.php" class="btn btn-outline btn-sm" style="border-color:#ccc;color:#333;">Banners</a>
    <a href="<?= BASE_URL ?>/admin/homepage/testimonials.php" class="btn btn-dark btn-sm">Testimonials</a>
</div>

<div class="data-table-wrap">
    <table class="data-table">
        <thead><tr><th>Customer</th><th>Message</th><th>Rating</th><th>Status</th><th>Featured</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($testimonials as $t): ?>
            <tr>
                <td><strong><?= e($t['customer_name']) ?></strong></td>
                <td style="max-width:280px;font-size:13px;color:#666;"><?= e(mb_strimwidth($t['message'], 0, 90, '...')) ?></td>
                <td style="color:var(--c-gold);"><?= str_repeat('★', (int) $t['rating']) ?></td>
                <td><span class="badge <?= $t['status'] === 'approved' ? 'badge-success' : ($t['status'] === 'hidden' ? 'badge-danger' : 'badge-pending') ?>"><?= ucfirst($t['status']) ?></span></td>
                <td><?= $t['is_featured'] ? '⭐' : '—' ?></td>
                <td style="white-space:nowrap;">
                    <?php if ($t['status'] !== 'approved'): ?>
                        <form method="POST" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="testimonial_id" value="<?= (int) $t['testimonial_id'] ?>"><button type="submit" name="action" value="approve" class="action-link edit" style="background:none;border:none;cursor:pointer;">Approve</button></form>
                    <?php else: ?>
                        <form method="POST" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="testimonial_id" value="<?= (int) $t['testimonial_id'] ?>"><button type="submit" name="action" value="hide" class="action-link danger" style="background:none;border:none;cursor:pointer;">Hide</button></form>
                    <?php endif; ?>
                    <form method="POST" style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="testimonial_id" value="<?= (int) $t['testimonial_id'] ?>">
                        <button type="submit" name="action" value="<?= $t['is_featured'] ? 'unfeature' : 'feature' ?>" class="action-link edit" style="background:none;border:none;cursor:pointer;"><?= $t['is_featured'] ? 'Unfeature' : 'Feature' ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($testimonials)): ?>
            <tr><td colspan="6" class="empty-state">No testimonials yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
