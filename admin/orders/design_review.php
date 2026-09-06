<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request.');
    redirect('/admin/orders/index.php');
}

$uploadId = (int) ($_POST['upload_id'] ?? 0);
$orderId  = (int) ($_POST['order_id'] ?? 0);
$decision = $_POST['decision'] ?? '';

if (!in_array($decision, ['approved', 'rejected'], true)) {
    set_flash('error', 'Invalid decision.');
    redirect('/admin/orders/view.php?id=' . $orderId);
}

$pdo->prepare(
    "UPDATE design_uploads SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE upload_id = ? AND order_id = ?"
)->execute([$decision, $admin['user_id'], $uploadId, $orderId]);

log_audit($pdo, $admin['user_id'], 'design_' . $decision, 'design_uploads', $uploadId);

set_flash('success', 'Design ' . $decision . '.');
redirect('/admin/orders/view.php?id=' . $orderId);
