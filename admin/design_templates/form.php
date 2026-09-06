<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();
$templateId = (int) ($_GET['id'] ?? 0);
$template = null;

if ($templateId) {
    $stmt = $pdo->prepare("SELECT * FROM design_templates WHERE template_id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch();
    if (!$template) { set_flash('error', 'Design not found.'); redirect('/admin/design_templates/index.php'); }
}

$pageTitle = ($template ? 'Edit' : 'Add') . ' Design Template — Admin';
$activeNav = 'design_templates';
include __DIR__ . '/../../includes/admin_header.php';
?>
<a href="<?= BASE_URL ?>/admin/design_templates/index.php" class="text-link" style="font-size:13px;">&larr; Back to Design Templates</a>
<h1 style="margin-top:12px;"><?= $template ? 'Edit Design' : 'Add Design' ?></h1>

<div class="card" style="max-width:500px;margin-top:20px;">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/admin/design_templates/save.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="template_id" value="<?= (int) ($template['template_id'] ?? 0) ?>">

            <div class="form-group">
                <label for="name">Design Name</label>
                <input class="form-control" type="text" id="name" name="name" value="<?= e($template['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="event_type">Event Type</label>
                <select class="form-control" id="event_type" name="event_type">
                    <?php foreach (['Birthday', 'Graduation', 'Wedding', 'Corporate', 'Other'] as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= ($template['event_type'] ?? '') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="image">Design Image <?= $template ? '(leave blank to keep current)' : '' ?></label>
                <?php if (!empty($template['image_url'])): ?>
                    <img src="<?= e($template['image_url']) ?>" class="thumb" style="width:80px;height:80px;margin-bottom:8px;">
                <?php endif; ?>
                <input class="form-control" type="file" id="image" name="image" accept="image/*" <?= $template ? '' : 'required' ?>>
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select class="form-control" id="status" name="status">
                    <option value="active" <?= ($template['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($template['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <button type="submit" class="btn btn-accent">Save Design</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
