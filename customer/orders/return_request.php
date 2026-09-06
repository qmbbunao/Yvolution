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
$reason = $_POST['reason'] ?? '';
$description = trim($_POST['description'] ?? '');

$validReasons = ['damaged', 'lost_in_delivery', 'wrong_item', 'other'];

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND customer_id = ? AND status = 'completed'");
$stmt->execute([$orderId, $user['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'This order is not eligible for a return or refund request.');
    redirect('/customer/orders/index.php');
}

if (!in_array($reason, $validReasons, true) || $description === '') {
    set_flash('error', 'Please select a reason and describe the issue.');
    redirect('/customer/orders/view.php?id=' . $orderId);
}

// Only one open request per order at a time
$existing = $pdo->prepare("SELECT return_id FROM returns WHERE order_id = ? AND status IN ('pending','approved')");
$existing->execute([$orderId]);
if ($existing->fetch()) {
    set_flash('error', 'You already have an active return/refund request for this order.');
    redirect('/customer/orders/view.php?id=' . $orderId);
}

$proofUrl = null;
if (!empty($_FILES['proof']['tmp_name']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
    try {
        $cloud = new CloudinaryUploader();
        $proofUrl = $cloud->upload($_FILES['proof']['tmp_name'], 'yvolution/returns/' . $order['order_code'])['secure_url'];
    } catch (Exception $e) {
        error_log('Return proof upload failed: ' . $e->getMessage());
        set_flash('error', 'Could not upload your proof photo: ' . $e->getMessage() . '. Please try again.');
        redirect('/customer/orders/view.php?id=' . $orderId);
    }
} elseif (!empty($_FILES['proof']['error']) && $_FILES['proof']['error'] !== UPLOAD_ERR_NO_FILE) {
    set_flash('error', 'Proof photo was not uploaded: ' . upload_error_message($_FILES['proof']['error']));
    redirect('/customer/orders/view.php?id=' . $orderId);
}

$pdo->prepare(
    "INSERT INTO returns (order_id, customer_id, reason, description, proof_url) VALUES (?, ?, ?, ?, ?)"
)->execute([$orderId, $user['user_id'], $reason, $description, $proofUrl]);

log_audit($pdo, $user['user_id'], 'return_requested', 'returns', (int) $pdo->lastInsertId(), $order['order_code']);

set_flash('success', 'Your return/refund request has been submitted. Our team will review it shortly.');
redirect('/customer/orders/view.php?id=' . $orderId);
