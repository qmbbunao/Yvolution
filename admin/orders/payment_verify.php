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
$decision = $_POST['decision'] ?? '';

if (!in_array($decision, ['paid', 'rejected'], true)) {
    set_flash('error', 'Invalid decision.');
    redirect('/admin/orders/view.php?id=' . $orderId);
}

$stmt = $pdo->prepare(
    "SELECT o.*, u.first_name, u.email FROM orders o JOIN users u ON u.user_id = o.customer_id WHERE o.order_id = ?"
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect('/admin/orders/index.php');
}

$pdo->prepare(
    "UPDATE orders SET payment_status = ?, payment_verified_by = ?, payment_verified_at = NOW() WHERE order_id = ?"
)->execute([$decision, $admin['user_id'], $orderId]);

log_audit($pdo, $admin['user_id'], 'payment_' . $decision, 'orders', $orderId);

$mailer = new Mailer();
if ($decision === 'paid') {
    $body = email_template('Payment Verified', "
        <p>Hi " . e($order['first_name']) . ",</p>
        <p>We've verified your payment for order <strong>{$order['order_code']}</strong>. Thank you!</p>
    ");
    $mailer->send($order['email'], $order['first_name'], 'Payment Verified — ' . $order['order_code'], $body);
    set_flash('success', 'Payment marked as verified.');
} else {
    $body = email_template('Payment Could Not Be Verified', "
        <p>Hi " . e($order['first_name']) . ",</p>
        <p>We couldn't verify the payment proof submitted for order <strong>{$order['order_code']}</strong>.
        Please log in and re-submit a clear screenshot or receipt, or contact us for help.</p>
    ");
    $mailer->send($order['email'], $order['first_name'], 'Payment Issue — ' . $order['order_code'], $body);
    set_flash('success', 'Payment marked as rejected. Customer has been notified.');
}

redirect('/admin/orders/view.php?id=' . $orderId);
