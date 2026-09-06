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

$quotationId = (int) ($_POST['quotation_id'] ?? 0);
$orderId     = (int) ($_POST['order_id'] ?? 0);
$response    = $_POST['response'] ?? '';

if (!in_array($response, ['accepted', 'rejected'], true)) {
    set_flash('error', 'Invalid response.');
    redirect('/customer/orders/view.php?id=' . $orderId);
}

// Verify the order belongs to this customer
$check = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?");
$check->execute([$orderId, $user['user_id']]);
$order = $check->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect('/customer/orders/index.php');
}

$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE quotations SET status = ?, responded_at = NOW() WHERE quotation_id = ? AND order_id = ?")
        ->execute([$response, $quotationId, $orderId]);

    $newStatus = $response === 'accepted' ? 'quotation_accepted' : 'quotation_rejected';
    update_order_status($pdo, $orderId, $newStatus, $user['user_id'],
        $response === 'accepted' ? 'Customer accepted the quotation.' : 'Customer declined the quotation.');

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Quotation response failed: ' . $e->getMessage());
    set_flash('error', 'Something went wrong. Please try again.');
    redirect('/customer/orders/view.php?id=' . $orderId);
}

log_audit($pdo, $user['user_id'], 'quotation_' . $response, 'quotations', $quotationId);

set_flash('success', $response === 'accepted'
    ? 'Quotation accepted! Your order will move into production soon.'
    : 'Quotation declined. Our team will follow up with you.');
redirect('/customer/orders/view.php?id=' . $orderId);
