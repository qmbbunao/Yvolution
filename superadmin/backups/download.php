<?php
require_once __DIR__ . '/../../config/app.php';
require_role('superadmin');

$backupConfig = require BASE_PATH . '/config/backup.php';

$fileName = basename($_GET['file'] ?? ''); // basename() strips any path traversal attempt
$filePath = $backupConfig['backup_dir'] . '/' . $fileName;

if ($fileName === '' || !str_ends_with($fileName, '.sql') || !file_exists($filePath)) {
    http_response_code(404);
    die('Backup file not found.');
}

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
