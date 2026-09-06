<?php
require_once __DIR__ . '/../../config/app.php';
require_role('superadmin');

$superAdmin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request.');
    redirect('/superadmin/backups/index.php');
}

if (empty($_FILES['backup_file']['tmp_name']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    set_flash('error', 'Please choose a valid .sql file to restore.');
    redirect('/superadmin/backups/index.php');
}

$ext = strtolower(pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION));
if ($ext !== 'sql') {
    set_flash('error', 'Only .sql files are accepted.');
    redirect('/superadmin/backups/index.php');
}

$backupConfig = require BASE_PATH . '/config/backup.php';
$dbHost = '127.0.0.1';
$dbName = 'yvolution_db';
$dbUser = 'root';
$dbPass = '';

$uploadedPath = $_FILES['backup_file']['tmp_name'];

$mysql = $backupConfig['mysql_path'];
$cmd = sprintf(
    '%s --host=%s --user=%s %s %s < %s 2>&1',
    escapeshellarg($mysql),
    escapeshellarg($dbHost),
    escapeshellarg($dbUser),
    $dbPass !== '' ? '--password=' . escapeshellarg($dbPass) : '',
    escapeshellarg($dbName),
    escapeshellarg($uploadedPath)
);

exec($cmd, $output, $returnCode);

if ($returnCode !== 0) {
    error_log('Restore failed: ' . implode("\n", $output));
    set_flash('error', 'Restore failed. Make sure the mysql client path in config/backup.php is correct and the file is a valid SQL dump.');
    redirect('/superadmin/backups/index.php');
}

log_audit($pdo, $superAdmin['user_id'], 'database_restored', null, null, $_FILES['backup_file']['name']);

set_flash('success', 'Database restored successfully. You may need to log in again.');
redirect('/superadmin/backups/index.php');
