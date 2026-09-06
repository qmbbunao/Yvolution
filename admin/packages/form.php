<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();
$packageId = (int) ($_GET['id'] ?? 0);
$package = null;

if ($packageId) {
    $stmt = $pdo->prepare("SELECT * FROM packages WHERE package_id = ?");
    $stmt->execute([$packageId]);
    $package = $stmt->fetch();
    if (!$package) { set_flash('error', 'Package not found.'); redirect('/admin/packages/index.php'); }
}

$pageTitle = ($package ? 'Edit' : 'Add') . ' Package — Admin';
$activeNav = 'packages';
include __DIR__ . '/../../includes/admin_header.php';
?>
<a href="<?= BASE_URL ?>/admin/packages/index.php" class="text-link" style="font-size:13px;">&larr; Back to Packages</a>
<h1 style="margin-top:12px;"><?= $package ? 'Edit Package' : 'Add Package' ?></h1>

<div class="card" style="max-width:600px;margin-top:20px;">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/admin/packages/save.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="package_id" value="<?= (int) ($package['package_id'] ?? 0) ?>">

            <div class="form-group">
                <label for="name">Package Name</label>
                <input class="form-control" type="text" id="name" name="name" value="<?= e($package['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= e($package['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="includes">What's Included</label>
                <textarea class="form-control" id="includes" name="includes" rows="3" placeholder="e.g. Jersey + shorts + name printing + team logo"><?= e($package['includes'] ?? '') ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="price">Package Price (₱)</label>
                    <input class="form-control" type="number" step="0.01" id="price" name="price" value="<?= e((string) ($package['price'] ?? '0')) ?>" required>
                </div>
                <div class="form-group">
                    <label for="min_quantity">Minimum Quantity</label>
                    <input class="form-control" type="number" id="min_quantity" name="min_quantity" value="<?= (int) ($package['min_quantity'] ?? 1) ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="image">Package Image</label>
                <?php if (!empty($package['image_url'])): ?>
                    <img src="<?= e($package['image_url']) ?>" class="thumb" style="width:80px;height:80px;margin-bottom:8px;">
                <?php endif; ?>
                <input class="form-control" type="file" id="image" name="image" accept="image/*">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select class="form-control" id="status" name="status">
                    <option value="active" <?= ($package['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($package['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <button type="submit" class="btn btn-accent">Save Package</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
