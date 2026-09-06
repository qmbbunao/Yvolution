<?php
require_once __DIR__ . '/../../config/app.php';
require_role('customer');

$user = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request. Please try again.');
    redirect('/customer/orders/index.php');
}

$orderId  = (int) ($_POST['order_id'] ?? 0);
$rating   = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
$comments = trim($_POST['comments'] ?? '');

$check = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND customer_id = ? AND status = 'completed'");
$check->execute([$orderId, $user['user_id']]);
$order = $check->fetch();

if (!$order) {
    set_flash('error', 'This order is not eligible for feedback.');
    redirect('/customer/orders/index.php');
}

$existing = $pdo->prepare("SELECT feedback_id FROM feedback WHERE order_id = ? AND customer_id = ?");
$existing->execute([$orderId, $user['user_id']]);

if ($existing->fetch()) {
    set_flash('error', 'You already left feedback for this order.');
    redirect('/customer/orders/view.php?id=' . $orderId);
}

$pdo->prepare("INSERT INTO feedback (order_id, customer_id, rating, comments) VALUES (?, ?, ?, ?)")
    ->execute([$orderId, $user['user_id'], $rating, $comments]);

// Also drop it into testimonials as pending, so admin can feature great reviews on the homepage
$pdo->prepare(
    "INSERT INTO testimonials (customer_id, customer_name, message, rating, status)
     VALUES (?, ?, ?, ?, 'pending')"
)->execute([$user['user_id'], $user['name'], $comments ?: 'Great service!', $rating]);

log_audit($pdo, $user['user_id'], 'feedback_submitted', 'feedback', $orderId);

set_flash('success', 'Thanks for your feedback!');
redirect('/customer/orders/view.php?id=' . $orderId);
