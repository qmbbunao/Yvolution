<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();
$guides = $pdo->query("SELECT * FROM size_guides ORDER BY guide_id")->fetchAll();

$pageTitle = 'Size Guides — Admin';
$activeNav = 'size_guides';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header">
    <h1>Size Guide Images</h1>
</div>
<p style="color:#777;margin-top:-16px;margin-bottom:24px;">Upload one separate, high-resolution image for each product type. These images appear on the public Size Guide page.</p>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;">
    <?php foreach ($guides as $guide): ?>
        <div class="card">
            <?php if (!empty($guide['image_url'])): ?>
                <img src="<?= e($guide['image_url']) ?>" alt="<?= e($guide['name']) ?> size guide" style="display:block;width:100%;height:220px;object-fit:contain;background:#f4f2ed;">
            <?php else: ?>
                <div style="height:220px;display:flex;align-items:center;justify-content:center;background:#f4f2ed;color:#888;font-size:13px;">No image uploaded</div>
            <?php endif; ?>
            <div class="card-body">
                <h3 style="font-size:20px;"><?= e($guide['name']) ?></h3>
                <form method="POST" action="<?= BASE_URL ?>/admin/size_guides/save.php" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="guide_id" value="<?= (int) $guide['guide_id'] ?>">
                    <label for="image-<?= (int) $guide['guide_id'] ?>" style="display:block;font-size:12px;font-weight:700;margin-bottom:6px;">Replace image</label>
                    <input class="form-control" type="file" id="image-<?= (int) $guide['guide_id'] ?>" name="image" accept="image/*" required>
                    <button type="submit" class="btn btn-accent btn-sm" style="margin-top:12px;">Upload <?= e($guide['name']) ?></button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
