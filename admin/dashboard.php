<?php
require_once __DIR__ . '/../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();

$orderStats = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'pending_review' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status IN ('quotation_accepted','in_production') THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) AS revenue
     FROM orders"
)->fetch();

$customerCount = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE role_id = 1")->fetch()['c'];

$lowStock = $pdo->query(
    "SELECT * FROM inventory WHERE quantity_on_hand <= reorder_level AND status = 'active' ORDER BY quantity_on_hand ASC LIMIT 5"
)->fetchAll();

$pendingDesigns = $pdo->query("SELECT COUNT(*) AS c FROM design_uploads WHERE status = 'pending'")->fetch()['c'];
$newInquiries = $pdo->query("SELECT COUNT(*) AS c FROM contact_inquiries WHERE status = 'new'")->fetch()['c'];

$recentOrders = $pdo->query(
    "SELECT o.*, u.first_name, u.last_name FROM orders o
     JOIN users u ON u.user_id = o.customer_id
     ORDER BY o.created_at DESC LIMIT 8"
)->fetchAll();

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

$pageTitle = 'Admin Dashboard — Yvolution Custom Apparel';
$activeNav = 'dashboard';
include __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-page-header">
    <h1>Dashboard</h1>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="value"><?= (int) $orderStats['total'] ?></div><div class="label">Total Orders</div></div>
    <div class="stat-card warning"><div class="value"><?= (int) $orderStats['pending'] ?></div><div class="label">Pending Review</div></div>
    <div class="stat-card accent"><div class="value"><?= (int) $orderStats['in_progress'] ?></div><div class="label">In Progress</div></div>
    <div class="stat-card success"><div class="value"><?= (int) $orderStats['completed'] ?></div><div class="label">Completed</div></div>
    <div class="stat-card success"><div class="value"><?= money((float) $orderStats['revenue']) ?></div><div class="label">Revenue (Completed)</div></div>
    <div class="stat-card"><div class="value"><?= (int) $customerCount ?></div><div class="label">Customers</div></div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start;">
    <div class="data-table-wrap">
        <div style="padding:18px 20px;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;font-size:16px;">Recent Orders</h3>
            <a href="<?= BASE_URL ?>/admin/orders/index.php" class="action-link edit">View All →</a>
        </div>
        <table class="data-table">
            <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Total</th></tr></thead>
            <tbody>
            <?php foreach ($recentOrders as $o): [$label, $badge] = $statusLabels[$o['status']] ?? [$o['status'], 'badge-pending']; ?>
                <tr onclick="window.location='<?= BASE_URL ?>/admin/orders/view.php?id=<?= (int) $o['order_id'] ?>'" style="cursor:pointer;">
                    <td><strong><?= e($o['order_code']) ?></strong></td>
                    <td><?= e($o['first_name'] . ' ' . $o['last_name']) ?></td>
                    <td><span class="badge <?= $badge ?>"><?= e($label) ?></span></td>
                    <td><?= money($o['total_amount']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($recentOrders)): ?>
                <tr><td colspan="4" class="empty-state">No orders yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div style="display:flex;flex-direction:column;gap:20px;">
        <div class="card">
            <div class="card-body">
                <h3 style="font-size:15px;">Needs Attention</h3>
                <p style="font-size:14px;margin:8px 0;">📋 <strong><?= (int) $pendingDesigns ?></strong> design(s) awaiting review</p>
                <p style="font-size:14px;margin:8px 0;">✉️ <strong><?= (int) $newInquiries ?></strong> new contact inquiries</p>
                <div style="display:flex;gap:8px;margin-top:8px;">
                    <a href="<?= BASE_URL ?>/admin/orders/index.php?status=pending_review" class="btn btn-dark btn-sm">Review Orders</a>
                    <a href="<?= BASE_URL ?>/admin/inquiries/index.php" class="btn btn-outline btn-sm" style="border-color:#ccc;color:#333;">View Inquiries</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 style="font-size:15px;">Low Stock Alerts</h3>
                <?php if (empty($lowStock)): ?>
                    <p style="font-size:13px;color:#888;">All inventory levels are healthy.</p>
                <?php else: ?>
                    <?php foreach ($lowStock as $item): ?>
                        <div style="display:flex;justify-content:space-between;font-size:13px;padding:6px 0;border-bottom:1px solid #f0f0f0;">
                            <span><?= e($item['item_name']) ?></span>
                            <strong style="color:var(--c-danger);"><?= (int) $item['quantity_on_hand'] ?> <?= e($item['unit']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <a href="<?= BASE_URL ?>/admin/inventory/index.php" class="action-link edit" style="display:inline-block;margin-top:10px;">Manage Inventory →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
