<?php
require_once __DIR__ . '/../config/app.php';
require_role('customer');

$user = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request. Please try again.');
    redirect('/public/index.php');
}

$productId = (int) ($_POST['product_id'] ?? 0);
$rating    = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
$comments  = trim($_POST['comments'] ?? '');

$product = $pdo->prepare("SELECT product_id, name FROM products WHERE product_id = ? AND status = 'active'");
$product->execute([$productId]);
$product = $product->fetch();

if (!$product) {
    set_flash('error', 'Product not found.');
    redirect('/public/index.php');
}

$redirectPath = '/public/product_details.php?id=' . $productId . '#reviews';

$orderedStmt = $pdo->prepare(
    "SELECT 1
     FROM orders o
     INNER JOIN order_items oi ON oi.order_id = o.order_id
     WHERE o.customer_id = ?
       AND oi.item_type = 'product'
       AND oi.item_ref_id = ?
       AND o.status != 'cancelled'
     LIMIT 1"
);
$orderedStmt->execute([$user['user_id'], $productId]);

if (!$orderedStmt->fetchColumn()) {
    set_flash('error', 'You can only review products you have ordered.');
    redirect($redirectPath);
}

if ($comments === '') {
    set_flash('error', 'Please add a short comment with your rating.');
    redirect($redirectPath);
}

$existing = $pdo->prepare(
    "SELECT feedback_id FROM feedback WHERE product_id = ? AND customer_id = ?"
);
$existing->execute([$productId, $user['user_id']]);
$existingId = $existing->fetchColumn();

if ($existingId) {
    $pdo->prepare(
        "UPDATE feedback SET rating = ?, comments = ? WHERE feedback_id = ? AND customer_id = ?"
    )->execute([$rating, $comments, $existingId, $user['user_id']]);
    log_audit($pdo, $user['user_id'], 'product_review_updated', 'feedback', (int) $existingId, $product['name']);
    set_flash('success', 'Your review was updated.');
} else {
    $pdo->prepare(
        "INSERT INTO feedback (order_id, product_id, customer_id, rating, comments)
         VALUES (NULL, ?, ?, ?, ?)"
    )->execute([$productId, $user['user_id'], $rating, $comments]);
    $feedbackId = (int) $pdo->lastInsertId();
    log_audit($pdo, $user['user_id'], 'product_review_submitted', 'feedback', $feedbackId, $product['name']);
    set_flash('success', 'Thanks for your review!');
}

redirect($redirectPath);
