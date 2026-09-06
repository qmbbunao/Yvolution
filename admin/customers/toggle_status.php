<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();
$customerId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT status FROM users WHERE user_id = ? AND role_id = 1");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if ($customer) {
    $newStatus = $customer['status'] === 'active' ? 'inactive' : 'active';
    $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?")->execute([$newStatus, $customerId]);
    log_audit($pdo, $admin['user_id'], 'customer_status_toggled', 'users', $customerId, $newStatus);
    set_flash('success', 'Customer account ' . $newStatus . '.');
}

redirect('/admin/customers/index.php');
