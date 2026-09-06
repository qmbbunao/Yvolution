<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/mailer.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request.');
    redirect('/admin/orders/index.php');
}

$orderId = (int) ($_POST['order_id'] ?? 0);
$amount = (float) ($_POST['amount'] ?? 0);
$breakdown = trim($_POST['breakdown'] ?? '');
$validUntil = $_POST['valid_until'] ?: null;

$stmt = $pdo->prepare(
    "SELECT o.*, u.first_name, u.last_name, u.email FROM orders o
     JOIN users u ON u.user_id = o.customer_id WHERE o.order_id = ?"
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order || $amount <= 0) {
    set_flash('error', 'Could not create quotation. Please check the amount.');
    redirect('/admin/orders/view.php?id=' . $orderId);
}

$pdo->beginTransaction();
try {
    $pdo->prepare(
        "INSERT INTO quotations (order_id, amount, breakdown, valid_until, status, created_by)
         VALUES (?, ?, ?, ?, 'sent', ?)"
    )->execute([$orderId, $amount, $breakdown, $validUntil, $admin['user_id']]);

    $pdo->prepare("UPDATE orders SET total_amount = ? WHERE order_id = ?")->execute([$amount, $orderId]);

    update_order_status($pdo, $orderId, 'quotation_sent', $admin['user_id'], 'Quotation sent: ' . money($amount));

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Quotation creation failed: ' . $e->getMessage());
    set_flash('error', 'Something went wrong creating the quotation.');
    redirect('/admin/orders/view.php?id=' . $orderId);
}

$mailer = new Mailer();
$body = email_template('Your Quotation is Ready', "
    <p>Hi " . e($order['first_name']) . ",</p>
    <p>Here's the quotation for order <strong>{$order['order_code']}</strong>:</p>
    <p style='font-size:22px;color:#ff4b23;font-weight:bold;'>" . money($amount) . "</p>
    " . ($breakdown ? '<p>' . nl2br(e($breakdown)) . '</p>' : '') . "
    <p>Log in to your account to accept or decline this quotation.</p>
");
$mailer->send($order['email'], $order['first_name'], 'Quotation Ready — ' . $order['order_code'], $body);

log_audit($pdo, $admin['user_id'], 'quotation_sent', 'orders', $orderId, money($amount));

set_flash('success', 'Quotation sent to customer.');
redirect('/admin/orders/view.php?id=' . $orderId);
