<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();
$templates = $pdo->query("SELECT * FROM design_templates ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'Design Templates — Admin';
$activeNav = 'design_templates';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header">
    <h1>Design Templates</h1>
    <a href="<?= BASE_URL ?>/admin/design_templates/form.php" class="btn btn-accent">+ Add Design</a>
</div>
<p style="color:#777;margin-top:-16px;margin-bottom:24px;">Ready-made designs customers can pick instead of uploading their own — shown when ordering Tarpaulin Printing or similar event-printing services.</p>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;">
    <?php foreach ($templates as $t): ?>
        <div class="card">
            <img src="<?= e($t['image_url']) ?>" style="height:140px;width:100%;object-fit:cover;">
            <div class="card-body">
                <strong style="font-size:13px;"><?= e($t['name']) ?></strong>
                <p style="font-size:12px;color:#888;margin:4px 0;"><?= e($t['event_type'] ?: 'General') ?></p>
                <span class="badge <?= $t['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($t['status']) ?></span>
                <div style="margin-top:8px;">
                    <a href="<?= BASE_URL ?>/admin/design_templates/form.php?id=<?= (int) $t['template_id'] ?>" class="action-link edit">Edit</a>
                    <a href="<?= BASE_URL ?>/admin/design_templates/delete.php?id=<?= (int) $t['template_id'] ?>" class="action-link danger" onclick="return confirm('Delete this design?');">Delete</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($templates)): ?>
        <p style="color:#888;">No design templates yet. <a href="<?= BASE_URL ?>/admin/design_templates/form.php" class="text-link">Add one</a>.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
