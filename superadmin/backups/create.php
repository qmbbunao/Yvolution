<?php
require_once __DIR__ . '/../../config/app.php';
require_role('superadmin');

$superAdmin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request.');
    redirect('/superadmin/backups/index.php');
}

$backupConfig = require BASE_PATH . '/config/backup.php';

if (!is_dir($backupConfig['backup_dir'])) {
    mkdir($backupConfig['backup_dir'], 0755, true);
}

$fileName = 'yvolution_backup_' . date('Ymd_His') . '.sql';
$filePath = $backupConfig['backup_dir'] . '/' . $fileName;

// Pull DB credentials the same way config/database.php does (kept in sync manually here since
// Database::connect() doesn't expose raw credentials by design).
$dbHost = '127.0.0.1';
$dbName = 'yvolution_db';
$dbUser = 'root';
$dbPass = '';

$mysqldump = $backupConfig['mysqldump_path'];
$cmd = sprintf(
    '%s --host=%s --user=%s %s %s > %s 2>&1',
    escapeshellarg($mysqldump),
    escapeshellarg($dbHost),
    escapeshellarg($dbUser),
    $dbPass !== '' ? '--password=' . escapeshellarg($dbPass) : '',
    escapeshellarg($dbName),
    escapeshellarg($filePath)
);

exec($cmd, $output, $returnCode);

if ($returnCode !== 0 || !file_exists($filePath) || filesize($filePath) === 0) {
    @unlink($filePath);
    error_log('Backup failed: ' . implode("\n", $output));
    set_flash('error', 'Backup failed. Make sure mysqldump is installed and the path in config/backup.php is correct. Check your PHP error log for details.');
    redirect('/superadmin/backups/index.php');
}

$sizeKb = (int) round(filesize($filePath) / 1024);

$pdo->prepare("INSERT INTO backup_logs (file_name, file_size_kb, type, performed_by) VALUES (?, ?, 'manual', ?)")
    ->execute([$fileName, $sizeKb, $superAdmin['user_id']]);

log_audit($pdo, $superAdmin['user_id'], 'backup_created', 'backup_logs', null, $fileName);

set_flash('success', "Backup created: {$fileName} ({$sizeKb} KB)");
redirect('/superadmin/backups/index.php');
