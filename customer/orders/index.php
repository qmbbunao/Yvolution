<?php
require_once __DIR__ . '/../../config/app.php';
require_role('customer');

$user = current_user();
$pdo = Database::connect();

$stmt = $pdo->prepare(
    "SELECT o.*, GROUP_CONCAT(oi.item_name SEPARATOR ', ') AS items_summary
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.order_id
     WHERE o.customer_id = ?
     GROUP BY o.order_id
     ORDER BY o.created_at DESC"
);
$stmt->execute([$user['user_id']]);
$orders = $stmt->fetchAll();

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

$pageTitle = 'My Orders — Yvolution Custom Apparel';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container" style="padding:50px 24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <h1>My Orders</h1>
        <a href="<?= BASE_URL ?>/customer/orders/create.php" class="btn btn-accent">+ New Order</a>
    </div>

    <?php if (empty($orders)): ?>
        <div class="card" style="margin-top:24px;">
            <div class="card-body" style="text-align:center;padding:50px;">
                <p style="color:#777;">You haven't placed any orders yet.</p>
                <a href="<?= BASE_URL ?>/customer/orders/create.php" class="btn btn-accent" style="margin-top:12px;">Place Your First Order</a>
            </div>
        </div>
    <?php else: ?>
        <div style="margin-top:24px;display:flex;flex-direction:column;gap:14px;">
            <?php foreach ($orders as $o): [$label, $badgeClass] = $statusLabels[$o['status']] ?? [$o['status'], 'badge-pending']; ?>
                <a href="<?= BASE_URL ?>/customer/orders/view.php?id=<?= (int) $o['order_id'] ?>" class="card" style="display:block;">
                    <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                        <div>
                            <strong style="font-family:var(--f-display);letter-spacing:0.03em;"><?= e($o['order_code']) ?></strong>
                            <p style="color:#666;font-size:14px;margin:4px 0 0;"><?= e($o['items_summary'] ?: 'Custom Request') ?></p>
                            <p style="color:#999;font-size:12px;margin:2px 0 0;"><?= date('M j, Y g:ia', strtotime($o['created_at'])) ?></p>
                        </div>
                        <div style="text-align:right;">
                            <span class="badge <?= $badgeClass ?>"><?= e($label) ?></span>
                            <p style="font-family:var(--f-display);color:var(--c-accent);margin:6px 0 0;font-size:16px;"><?= money($o['total_amount']) ?></p>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
