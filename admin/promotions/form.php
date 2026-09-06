<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();
$promoId = (int) ($_GET['id'] ?? 0);
$promo = null;

if ($promoId) {
    $stmt = $pdo->prepare("SELECT * FROM promotions WHERE promo_id = ?");
    $stmt->execute([$promoId]);
    $promo = $stmt->fetch();
    if (!$promo) { set_flash('error', 'Promotion not found.'); redirect('/admin/promotions/index.php'); }
}

$pageTitle = ($promo ? 'Edit' : 'Add') . ' Promotion — Admin';
$activeNav = 'promotions';
include __DIR__ . '/../../includes/admin_header.php';
?>
<a href="<?= BASE_URL ?>/admin/promotions/index.php" class="text-link" style="font-size:13px;">&larr; Back to Promotions</a>
<h1 style="margin-top:12px;"><?= $promo ? 'Edit Promotion' : 'Add Promotion' ?></h1>

<div class="card" style="max-width:600px;margin-top:20px;">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/admin/promotions/save.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="promo_id" value="<?= (int) ($promo['promo_id'] ?? 0) ?>">

            <div class="form-group">
                <label for="title">Title</label>
                <input class="form-control" type="text" id="title" name="title" value="<?= e($promo['title'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= e($promo['description'] ?? '') ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="discount_type">Discount Type</label>
                    <select class="form-control" id="discount_type" name="discount_type">
                        <option value="percent" <?= ($promo['discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>Percentage (%)</option>
                        <option value="fixed" <?= ($promo['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed Amount (₱)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="discount_value">Discount Value</label>
                    <input class="form-control" type="number" step="0.01" id="discount_value" name="discount_value" value="<?= e((string) ($promo['discount_value'] ?? '0')) ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="promo_code">Promo Code (optional)</label>
                <input class="form-control" type="text" id="promo_code" name="promo_code" value="<?= e($promo['promo_code'] ?? '') ?>" placeholder="e.g. TEAMUP20">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input class="form-control" type="date" id="start_date" name="start_date" value="<?= e($promo['start_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input class="form-control" type="date" id="end_date" name="end_date" value="<?= e($promo['end_date'] ?? date('Y-m-d', strtotime('+30 days'))) ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="image">Promo Image</label>
                <?php if (!empty($promo['image_url'])): ?>
                    <img src="<?= e($promo['image_url']) ?>" class="thumb" style="width:80px;height:80px;margin-bottom:8px;">
                <?php endif; ?>
                <input class="form-control" type="file" id="image" name="image" accept="image/*">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select class="form-control" id="status" name="status">
                    <option value="active" <?= ($promo['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($promo['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <button type="submit" class="btn btn-accent">Save Promotion</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
