<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/cloudinary.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'add') {
    $title = trim($_POST['title'] ?? '');
    $linkUrl = trim($_POST['link_url'] ?? '');
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);

    if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        try {
            $cloud = new CloudinaryUploader();
            $imageUrl = $cloud->upload($_FILES['image']['tmp_name'], 'yvolution/banners')['secure_url'];
            $pdo->prepare("INSERT INTO banners (image_url, title, link_url, sort_order) VALUES (?, ?, ?, ?)")
                ->execute([$imageUrl, $title, $linkUrl, $sortOrder]);
            log_audit($pdo, $admin['user_id'], 'banner_created', 'banners', (int) $pdo->lastInsertId(), $title);
            set_flash('success', 'Banner added.');
        } catch (Exception $e) {
            set_flash('error', 'Banner image upload failed: ' . $e->getMessage());
        }
    } else {
        set_flash('error', 'Please choose a banner image.');
    }
    redirect('/admin/homepage/banners.php');
}

$banners = $pdo->query("SELECT * FROM banners ORDER BY sort_order ASC, created_at DESC")->fetchAll();

$pageTitle = 'Banners — Admin';
$activeNav = 'homepage';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header"><h1>Homepage CMS</h1></div>
<div style="display:flex;gap:16px;margin-bottom:24px;">
    <a href="<?= BASE_URL ?>/admin/homepage/content.php" class="btn btn-outline btn-sm" style="border-color:#ccc;color:#333;">Hero & About</a>
    <a href="<?= BASE_URL ?>/admin/homepage/banners.php" class="btn btn-dark btn-sm">Banners</a>
    <a href="<?= BASE_URL ?>/admin/homepage/testimonials.php" class="btn btn-outline btn-sm" style="border-color:#ccc;color:#333;">Testimonials</a>
</div>

<div class="card" style="max-width:500px;margin-bottom:24px;">
    <div class="card-body">
        <h3 style="font-size:15px;">Add Banner</h3>
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label for="title">Title</label>
                <input class="form-control" type="text" id="title" name="title">
            </div>
            <div class="form-group">
                <label for="link_url">Link URL (optional)</label>
                <input class="form-control" type="text" id="link_url" name="link_url" placeholder="#promotions">
            </div>
            <div class="form-group">
                <label for="sort_order">Sort Order</label>
                <input class="form-control" type="number" id="sort_order" name="sort_order" value="0">
            </div>
            <div class="form-group">
                <label for="image">Banner Image</label>
                <input class="form-control" type="file" id="image" name="image" accept="image/*" required>
            </div>
            <button type="submit" class="btn btn-accent">Add Banner</button>
        </form>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;">
    <?php foreach ($banners as $b): ?>
        <div class="card">
            <img src="<?= e($b['image_url']) ?>" style="height:120px;width:100%;object-fit:cover;">
            <div class="card-body">
                <strong style="font-size:13px;"><?= e($b['title'] ?: 'Untitled') ?></strong>
                <div style="margin-top:8px;">
                    <a href="<?= BASE_URL ?>/admin/homepage/banner_delete.php?id=<?= (int) $b['banner_id'] ?>" class="action-link danger" onclick="return confirm('Delete this banner?');">Delete</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($banners)): ?>
        <p style="color:#888;">No banners yet.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
