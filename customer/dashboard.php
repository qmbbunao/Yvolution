<?php
require_once __DIR__ . '/../config/app.php';
require_role('customer');

$user = current_user();
$pdo = Database::connect();

$stats = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN status IN ('pending_review','quotation_sent') THEN 1 ELSE 0 END) AS awaiting,
        SUM(CASE WHEN status IN ('quotation_accepted','in_production') THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed
     FROM orders WHERE customer_id = ?"
);
$stats->execute([$user['user_id']]);
$stats = $stats->fetch();

$recentOrders = $pdo->prepare(
    "SELECT o.*, GROUP_CONCAT(oi.item_name SEPARATOR ', ') AS items_summary
     FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.order_id
     WHERE o.customer_id = ? GROUP BY o.order_id ORDER BY o.created_at DESC LIMIT 5"
);
$recentOrders->execute([$user['user_id']]);
$recentOrders = $recentOrders->fetchAll();

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

$pageTitle = 'My Account — Yvolution Custom Apparel';
include __DIR__ . '/../includes/header.php';
?>
<div class="container" style="padding:50px 24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
            <h1 style="margin-bottom:4px;">Welcome, <?= e(explode(' ', $user['name'])[0]) ?></h1>
            <p style="color:#777;">Here's what's happening with your orders.</p>
        </div>
        <a href="<?= BASE_URL ?>/customer/orders/create.php" class="btn btn-accent">+ New Order</a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-top:32px;">
        <div class="card"><div class="card-body" style="text-align:center;">
            <div style="font-family:var(--f-display);font-size:32px;color:var(--c-navy-900);"><?= (int) $stats['total_orders'] ?></div>
            <div style="font-size:12px;color:#888;text-transform:uppercase;">Total Orders</div>
        </div></div>
        <div class="card"><div class="card-body" style="text-align:center;">
            <div style="font-family:var(--f-display);font-size:32px;color:#916b00;"><?= (int) $stats['awaiting'] ?></div>
            <div style="font-size:12px;color:#888;text-transform:uppercase;">Awaiting Review</div>
        </div></div>
        <div class="card"><div class="card-body" style="text-align:center;">
            <div style="font-family:var(--f-display);font-size:32px;color:#1c4fb0;"><?= (int) $stats['in_progress'] ?></div>
            <div style="font-size:12px;color:#888;text-transform:uppercase;">In Progress</div>
        </div></div>
        <div class="card"><div class="card-body" style="text-align:center;">
            <div style="font-family:var(--f-display);font-size:32px;color:#1a7a44;"><?= (int) $stats['completed'] ?></div>
            <div style="font-size:12px;color:#888;text-transform:uppercase;">Completed</div>
        </div></div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:44px;">
        <h2 style="margin:0;">Recent Orders</h2>
        <a href="<?= BASE_URL ?>/customer/orders/index.php" class="text-link" style="font-size:14px;">View All &rarr;</a>
    </div>

    <?php if (empty($recentOrders)): ?>
        <div class="card" style="margin-top:16px;"><div class="card-body" style="text-align:center;padding:40px;color:#777;">
            No orders yet. <a href="<?= BASE_URL ?>/customer/orders/create.php" class="text-link">Place your first order</a>.
        </div></div>
    <?php else: ?>
        <div style="margin-top:16px;display:flex;flex-direction:column;gap:12px;">
            <?php foreach ($recentOrders as $o): [$label, $badge] = $statusLabels[$o['status']] ?? [$o['status'], 'badge-pending']; ?>
                <a href="<?= BASE_URL ?>/customer/orders/view.php?id=<?= (int) $o['order_id'] ?>" class="card" style="display:block;">
                    <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                        <div>
                            <strong><?= e($o['order_code']) ?></strong>
                            <p style="color:#666;font-size:13px;margin:2px 0 0;"><?= e($o['items_summary'] ?: 'Custom Request') ?></p>
                        </div>
                        <span class="badge <?= $badge ?>"><?= e($label) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="margin-top:40px;">
        <a href="<?= BASE_URL ?>/customer/profile/edit.php" class="text-link">Manage Profile &rarr;</a>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
