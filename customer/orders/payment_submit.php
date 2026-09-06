<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/cloudinary.php';
require_once BASE_PATH . '/includes/mailer.php';
require_role('customer');

$user = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request. Please try again.');
    redirect('/customer/orders/index.php');
}

$orderId = (int) ($_POST['order_id'] ?? 0);
$method = $_POST['payment_method'] ?? '';
$reference = trim($_POST['payment_reference'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?");
$stmt->execute([$orderId, $user['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect('/customer/orders/index.php');
}

if (!in_array($method, ['gcash', 'bank_transfer'], true)) {
    set_flash('error', 'Please select a payment method.');
    redirect('/customer/orders/view.php?id=' . $orderId);
}

if (empty($_FILES['payment_proof']['tmp_name']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
    $reason = !empty($_FILES['payment_proof']['error'])
        ? upload_error_message($_FILES['payment_proof']['error'])
        : 'Please attach a screenshot or photo of your payment.';
    set_flash('error', $reason);
    redirect('/customer/orders/view.php?id=' . $orderId);
}

try {
    $cloud = new CloudinaryUploader();
    $result = $cloud->upload($_FILES['payment_proof']['tmp_name'], 'yvolution/payments/' . $order['order_code']);

    $pdo->prepare(
        "UPDATE orders SET payment_status = 'pending_verification', payment_method = ?, payment_reference = ?,
         payment_proof_url = ?, payment_submitted_at = NOW() WHERE order_id = ?"
    )->execute([$method, $reference ?: null, $result['secure_url'], $orderId]);

    log_audit($pdo, $user['user_id'], 'payment_proof_submitted', 'orders', $orderId);
    set_flash('success', 'Payment proof submitted! Our team will verify it shortly.');
} catch (Exception $e) {
    error_log('Payment proof upload failed: ' . $e->getMessage());
    set_flash('error', 'Upload failed: ' . $e->getMessage());
}

redirect('/customer/orders/view.php?id=' . $orderId);
