<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();
$serviceId = (int) ($_GET['id'] ?? 0);
$service = null;

if ($serviceId) {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE service_id = ?");
    $stmt->execute([$serviceId]);
    $service = $stmt->fetch();
    if (!$service) { set_flash('error', 'Service not found.'); redirect('/admin/services/index.php'); }
}

$pageTitle = ($service ? 'Edit' : 'Add') . ' Service — Admin';
$activeNav = 'services';
include __DIR__ . '/../../includes/admin_header.php';
?>
<a href="<?= BASE_URL ?>/admin/services/index.php" class="text-link" style="font-size:13px;">&larr; Back to Services</a>
<h1 style="margin-top:12px;"><?= $service ? 'Edit Service' : 'Add Service' ?></h1>

<div class="card" style="max-width:600px;margin-top:20px;">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/admin/services/save.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="service_id" value="<?= (int) ($service['service_id'] ?? 0) ?>">

            <div class="form-group">
                <label for="name">Service Name</label>
                <input class="form-control" type="text" id="name" name="name" value="<?= e($service['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4"><?= e($service['description'] ?? '') ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="price">Price (₱)</label>
                    <input class="form-control" type="number" step="0.01" id="price" name="price" value="<?= e((string) ($service['price'] ?? '0')) ?>" required>
                </div>
                <div class="form-group">
                    <label for="price_unit">Price Unit</label>
                    <input class="form-control" type="text" id="price_unit" name="price_unit" value="<?= e($service['price_unit'] ?? 'per piece') ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="image">Service Image</label>
                <?php if (!empty($service['image_url'])): ?>
                    <img src="<?= e($service['image_url']) ?>" class="thumb" style="width:80px;height:80px;margin-bottom:8px;">
                <?php endif; ?>
                <input class="form-control" type="file" id="image" name="image" accept="image/*">
            </div>
            <div class="form-group" style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" id="requires_tarp_options" name="requires_tarp_options" value="1" <?= !empty($service['requires_tarp_options']) ? 'checked' : '' ?> style="width:auto;">
                <label for="requires_tarp_options" style="margin:0;">Show event printing options at checkout (size, event type, design source) — for tarpaulins, banners, etc.</label>
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select class="form-control" id="status" name="status">
                    <option value="active" <?= ($service['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($service['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <button type="submit" class="btn btn-accent">Save Service</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
