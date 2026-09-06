<?php
require_once __DIR__ . '/../../config/app.php';

$code = trim($_GET['code'] ?? '');
$pdo = Database::connect();

$order = null;
if ($code !== '') {
    $stmt = $pdo->prepare(
        "SELECT o.order_code, o.status, o.created_at, GROUP_CONCAT(oi.item_name SEPARATOR ', ') AS items_summary
         FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.order_id
         WHERE o.order_code = ? GROUP BY o.order_id"
    );
    $stmt->execute([$code]);
    $order = $stmt->fetch();
}

$statusLabels = [
    'pending_review'     => ['Pending Review', 'badge-pending'],
    'quotation_sent'     => ['Quotation Sent', 'badge-progress'],
    'quotation_accepted' => ['Quotation Accepted', 'badge-progress'],
    'quotation_rejected' => ['Quotation Rejected', 'badge-danger'],
    'in_production'      => ['In Production', 'badge-progress'],
    'ready_for_pickup'   => ['Ready for Pickup', 'badge-success'],
    'completed'          => ['Completed', 'badge-success'],
    'cancelled'          => ['Cancelled', 'badge-danger'],
];

$pageTitle = 'Track Order — Yvolution Custom Apparel';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container" style="padding:60px 24px;max-width:480px;text-align:center;">
    <h1>Track Your Order</h1>

    <?php if (!$code): ?>
        <form method="GET" style="margin-top:24px;">
            <div class="form-group">
                <label for="code">Order Code</label>
                <input class="form-control" type="text" id="code" name="code" placeholder="YVO-2026-00001" required>
            </div>
            <button type="submit" class="btn btn-accent btn-block">Check Status</button>
        </form>
    <?php elseif (!$order): ?>
        <div class="alert alert-error" style="margin-top:20px;">No order found with code "<?= e($code) ?>".</div>
        <a href="<?= BASE_URL ?>/customer/orders/track.php" class="text-link">Try another code</a>
    <?php else: ?>
        <?php [$label, $badge] = $statusLabels[$order['status']] ?? [$order['status'], 'badge-pending']; ?>
        <div class="card" style="margin-top:24px;">
            <div class="card-body">
                <strong style="font-family:var(--f-display);font-size:20px;"><?= e($order['order_code']) ?></strong>
                <p style="color:#666;font-size:14px;margin:8px 0;"><?= e($order['items_summary'] ?: 'Custom Request') ?></p>
                <span class="badge <?= $badge ?>" style="font-size:13px;padding:8px 16px;"><?= e($label) ?></span>
                <p style="color:#999;font-size:12px;margin-top:12px;">Placed <?= date('M j, Y', strtotime($order['created_at'])) ?></p>
            </div>
        </div>
        <p style="font-size:13px;color:#777;margin-top:20px;">Log in to your account for full order details, quotations, and design files.</p>
        <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-dark" style="margin-top:8px;">Log In</a>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
