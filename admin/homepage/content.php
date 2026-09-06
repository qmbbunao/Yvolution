<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/cloudinary.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $sectionKey = $_POST['section_key'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $contentText = trim($_POST['content_text'] ?? '');
    $videoUrl = trim($_POST['video_url'] ?? '');

    if (in_array($sectionKey, ['hero', 'about'], true)) {
        $imageUrl = null;
        if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            try {
                $cloud = new CloudinaryUploader();
                $imageUrl = $cloud->upload($_FILES['image']['tmp_name'], 'yvolution/homepage')['secure_url'];
            } catch (Exception $e) {
                error_log('Homepage image upload failed: ' . $e->getMessage());
                set_flash('error', 'Section saved, but image upload failed: ' . $e->getMessage());
            }
        } elseif (!empty($_FILES['image']['error']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            set_flash('error', 'Section saved, but image was not uploaded: ' . upload_error_message($_FILES['image']['error']));
        }

        $existing = $pdo->prepare("SELECT content_id FROM homepage_content WHERE section_key = ?");
        $existing->execute([$sectionKey]);

        if ($row = $existing->fetch()) {
            if ($imageUrl) {
                $pdo->prepare("UPDATE homepage_content SET title=?, subtitle=?, content_text=?, video_url=?, image_url=?, updated_by=? WHERE content_id=?")
                    ->execute([$title, $subtitle, $contentText, $videoUrl ?: null, $imageUrl, $admin['user_id'], $row['content_id']]);
            } else {
                $pdo->prepare("UPDATE homepage_content SET title=?, subtitle=?, content_text=?, video_url=?, updated_by=? WHERE content_id=?")
                    ->execute([$title, $subtitle, $contentText, $videoUrl ?: null, $admin['user_id'], $row['content_id']]);
            }
        } else {
            $pdo->prepare("INSERT INTO homepage_content (section_key, title, subtitle, content_text, video_url, image_url, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$sectionKey, $title, $subtitle, $contentText, $videoUrl ?: null, $imageUrl, $admin['user_id']]);
        }

        log_audit($pdo, $admin['user_id'], 'homepage_content_updated', 'homepage_content', null, $sectionKey);
        set_flash('success', ucfirst($sectionKey) . ' section updated.');
        redirect('/admin/homepage/content.php');
    }
}

$hero = $pdo->query("SELECT * FROM homepage_content WHERE section_key = 'hero'")->fetch() ?: [];
$about = $pdo->query("SELECT * FROM homepage_content WHERE section_key = 'about'")->fetch() ?: [];

$pageTitle = 'Homepage Content — Admin';
$activeNav = 'homepage';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header"><h1>Homepage CMS</h1></div>

<div style="display:flex;gap:16px;margin-bottom:24px;">
    <a href="<?= BASE_URL ?>/admin/homepage/content.php" class="btn btn-dark btn-sm">Hero & About</a>
    <a href="<?= BASE_URL ?>/admin/homepage/banners.php" class="btn btn-outline btn-sm" style="border-color:#ccc;color:#333;">Banners</a>
    <a href="<?= BASE_URL ?>/admin/homepage/testimonials.php" class="btn btn-outline btn-sm" style="border-color:#ccc;color:#333;">Testimonials</a>
</div>

<div class="card" style="max-width:640px;">
    <div class="card-body">
        <h3 style="font-size:16px;">Hero Section</h3>
        <p style="font-size:13px;color:#888;">Note: the hero currently displays "PrintEase Custom Apparel" as a fixed heading — the Title field below feeds the subtitle/tagline area of the page.</p>
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="section_key" value="hero">
            <div class="form-group">
                <label for="hero_title">Title (internal label)</label>
                <input class="form-control" type="text" id="hero_title" name="title" value="<?= e($hero['title'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="hero_subtitle">Tagline</label>
                <input class="form-control" type="text" id="hero_subtitle" name="subtitle" value="<?= e($hero['subtitle'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="hero_video">Hero Video URL (mp4)</label>
                <input class="form-control" type="text" id="hero_video" name="video_url" value="<?= e($hero['video_url'] ?? '') ?>" placeholder="Leave blank to use assets/videos/hero.mp4">
            </div>
            <button type="submit" class="btn btn-accent">Save Hero</button>
        </form>
    </div>
</div>

<div class="card" style="max-width:640px;margin-top:20px;">
    <div class="card-body">
        <h3 style="font-size:16px;">About Section</h3>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="section_key" value="about">
            <div class="form-group">
                <label for="about_title">Title</label>
                <input class="form-control" type="text" id="about_title" name="title" value="<?= e($about['title'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="about_content">Content</label>
                <textarea class="form-control" id="about_content" name="content_text" rows="4"><?= e($about['content_text'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-accent">Save About</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
