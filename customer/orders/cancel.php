<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/mailer.php';
require_role('customer');

$user = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request. Please try again.');
    redirect('/customer/orders/index.php');
}

$orderId = (int) ($_POST['order_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?");
$stmt->execute([$orderId, $user['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect('/customer/orders/index.php');
}

if (in_array($order['status'], ['completed', 'cancelled'], true)) {
    set_flash('error', 'This order can no longer be cancelled.');
    redirect('/customer/orders/view.php?id=' . $orderId);
}

$minutesSincePlaced = (time() - strtotime($order['created_at'])) / 60;
if ($minutesSincePlaced > 120) {
    set_flash('error', 'The 2-hour cancellation window for this order has passed. Please contact us directly if you still need to cancel.');
    redirect('/customer/orders/view.php?id=' . $orderId);
}

$pdo->prepare("UPDATE orders SET status = 'cancelled', cancelled_at = NOW() WHERE order_id = ?")->execute([$orderId]);
$pdo->prepare(
    "INSERT INTO order_status_history (order_id, status, notes, changed_by) VALUES (?, 'cancelled', 'Cancelled by customer.', ?)"
)->execute([$orderId, $user['user_id']]);

$mailer = new Mailer();
$body = email_template('Order Cancelled', "
    <p>Hi " . e($user['name']) . ",</p>
    <p>Your order <strong>{$order['order_code']}</strong> has been cancelled as requested.</p>
");
$mailer->send($user['email'], $user['name'], 'Order Cancelled — ' . $order['order_code'], $body);

log_audit($pdo, $user['user_id'], 'order_cancelled_by_customer', 'orders', $orderId);

set_flash('success', 'Your order has been cancelled.');
redirect('/customer/orders/view.php?id=' . $orderId);
