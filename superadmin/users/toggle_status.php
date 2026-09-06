<?php
require_once __DIR__ . '/../../config/app.php';
require_role('superadmin');

$superAdmin = current_user();
$pdo = Database::connect();
$targetId = (int) ($_GET['id'] ?? 0);

if ($targetId === $superAdmin['user_id']) {
    set_flash('error', 'You cannot deactivate your own account.');
    redirect('/superadmin/users/index.php');
}

$stmt = $pdo->prepare("SELECT status FROM users WHERE user_id = ?");
$stmt->execute([$targetId]);
$target = $stmt->fetch();

if ($target) {
    $newStatus = $target['status'] === 'active' ? 'inactive' : 'active';
    $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?")->execute([$newStatus, $targetId]);
    log_audit($pdo, $superAdmin['user_id'], 'user_status_toggled', 'users', $targetId, $newStatus);
    set_flash('success', 'Account ' . $newStatus . '.');
}

redirect('/superadmin/users/index.php');
