<?php require_once __DIR__ . '/../config/app.php'; $pageTitle = 'Access Denied'; include __DIR__ . '/../includes/header.php'; ?>
<div class="container" style="padding:80px 24px;text-align:center;">
    <h1 style="font-size:64px;color:var(--c-accent);">403</h1>
    <p style="font-size:18px;">You don't have permission to view that page.</p>
    <a href="<?= BASE_URL ?>/public/index.php" class="btn btn-dark" style="margin-top:20px;">Back to Home</a>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
