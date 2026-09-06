<?php
require_once __DIR__ . '/../../config/app.php';
require_role('superadmin');

$pdo = Database::connect();
$backups = $pdo->query(
    "SELECT b.*, u.first_name, u.last_name FROM backup_logs b
     LEFT JOIN users u ON u.user_id = b.performed_by ORDER BY b.created_at DESC"
)->fetchAll();

$backupConfig = require BASE_PATH . '/config/backup.php';
$filesOnDisk = is_dir($backupConfig['backup_dir']) ? array_diff(scandir($backupConfig['backup_dir']), ['.', '..', '.htaccess']) : [];

$pageTitle = 'Backups — Super Admin';
$activeNav = 'backups';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header"><h1>Backups</h1></div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">
    <div class="card"><div class="card-body">
        <h3 style="font-size:15px;">Create Backup</h3>
        <p style="font-size:13px;color:#777;">Runs <code>mysqldump</code> and stores a timestamped <code>.sql</code> file in <code>database/backups/</code> (blocked from direct web access).</p>
        <form method="POST" action="<?= BASE_URL ?>/superadmin/backups/create.php">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-accent">Run Backup Now</button>
        </form>
    </div></div>

    <div class="card"><div class="card-body">
        <h3 style="font-size:15px;">Restore from Backup</h3>
        <p style="font-size:13px;color:#b3261e;">⚠ This will overwrite current data with the uploaded backup. This cannot be undone.</p>
        <form method="POST" action="<?= BASE_URL ?>/superadmin/backups/restore.php" enctype="multipart/form-data" onsubmit="return confirm('This will overwrite your current database. Continue?');">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="backup_file">Upload .sql File</label>
                <input class="form-control" type="file" id="backup_file" name="backup_file" accept=".sql" required>
            </div>
            <button type="submit" class="btn btn-outline" style="border-color:#b3261e;color:#b3261e;">Restore Database</button>
        </form>
    </div></div>
</div>

<div class="data-table-wrap" style="margin-top:24px;">
    <div style="padding:18px 20px;"><h3 style="margin:0;font-size:16px;">Backup History</h3></div>
    <table class="data-table">
        <thead><tr><th>File</th><th>Size</th><th>Type</th><th>By</th><th>When</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($backups as $b): ?>
            <tr>
                <td><?= e($b['file_name']) ?></td>
                <td><?= $b['file_size_kb'] ? number_format($b['file_size_kb']) . ' KB' : '—' ?></td>
                <td><span class="badge badge-progress"><?= ucfirst($b['type']) ?></span></td>
                <td><?= e($b['first_name'] ? $b['first_name'] . ' ' . $b['last_name'] : 'System') ?></td>
                <td style="font-size:13px;color:#888;"><?= date('M j, Y g:ia', strtotime($b['created_at'])) ?></td>
                <td>
                    <?php if (in_array($b['file_name'], $filesOnDisk, true)): ?>
                        <a href="<?= BASE_URL ?>/superadmin/backups/download.php?file=<?= urlencode($b['file_name']) ?>" class="action-link edit">Download</a>
                    <?php else: ?>
                        <span style="color:#bbb;font-size:12px;">File missing</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($backups)): ?>
            <tr><td colspan="6" class="empty-state">No backups yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
