<?php
require_once __DIR__ . '/../../config/app.php';
require_role('superadmin');

$pdo = Database::connect();

// Revenue by month (last 6 months, completed orders)
$revenueByMonth = $pdo->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, DATE_FORMAT(created_at, '%b %Y') AS label, SUM(total_amount) AS revenue
     FROM orders WHERE status = 'completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY ym ORDER BY ym ASC"
)->fetchAll();
$maxRevenue = max(1, ...array_map(fn($r) => (float) $r['revenue'], $revenueByMonth ?: [['revenue' => 1]]));

// Order status breakdown
$statusBreakdown = $pdo->query(
    "SELECT status, COUNT(*) AS c FROM orders GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$totalOrders = max(1, array_sum($statusBreakdown));

// Top products by quantity ordered
$topProducts = $pdo->query(
    "SELECT item_name, SUM(quantity) AS total_qty, SUM(subtotal) AS total_revenue
     FROM order_items WHERE item_type = 'product' GROUP BY item_name ORDER BY total_qty DESC LIMIT 5"
)->fetchAll();

// Customer growth (last 6 months)
$customerGrowth = $pdo->query(
    "SELECT DATE_FORMAT(created_at, '%b %Y') AS label, COUNT(*) AS c FROM users
     WHERE role_id = 1 AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC"
)->fetchAll();
$maxCustomers = max(1, ...array_map(fn($r) => (int) $r['c'], $customerGrowth ?: [['c' => 1]]));

$statusLabels = [
    'pending_review' => 'Pending Review', 'quotation_sent' => 'Quotation Sent',
    'quotation_accepted' => 'Quotation Accepted', 'quotation_rejected' => 'Quotation Rejected',
    'in_production' => 'In Production', 'ready_for_pickup' => 'Ready for Pickup',
    'completed' => 'Completed', 'cancelled' => 'Cancelled',
];

$pageTitle = 'Analytics — Super Admin';
$activeNav = 'analytics';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header"><h1>Analytics</h1></div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

    <!-- Revenue Trend -->
    <div class="card"><div class="card-body">
        <h3 style="font-size:15px;">Revenue — Last 6 Months (Completed Orders)</h3>
        <?php if (empty($revenueByMonth)): ?>
            <p style="color:#888;font-size:13px;">No completed orders yet.</p>
        <?php else: ?>
            <div style="display:flex;align-items:flex-end;gap:12px;height:160px;margin-top:20px;">
                <?php foreach ($revenueByMonth as $r): ?>
                    <div style="flex:1;text-align:center;">
                        <div style="background:var(--c-accent);border-radius:4px 4px 0 0;height:<?= max(4, (int) (($r['revenue'] / $maxRevenue) * 130)) ?>px;" title="<?= money($r['revenue']) ?>"></div>
                        <div style="font-size:11px;color:#888;margin-top:6px;"><?= e($r['label']) ?></div>
                        <div style="font-size:11px;font-weight:700;color:var(--c-navy-900);"><?= money($r['revenue']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div></div>

    <!-- Order Status Breakdown -->
    <div class="card"><div class="card-body">
        <h3 style="font-size:15px;">Order Status Breakdown</h3>
        <?php foreach ($statusLabels as $key => $label):
            $count = (int) ($statusBreakdown[$key] ?? 0);
            $pct = round(($count / $totalOrders) * 100);
        ?>
            <div style="margin:10px 0;">
                <div style="display:flex;justify-content:space-between;font-size:12px;color:#666;margin-bottom:4px;">
                    <span><?= e($label) ?></span><span><?= $count ?> (<?= $pct ?>%)</span>
                </div>
                <div style="background:#eee;border-radius:4px;height:8px;">
                    <div style="background:var(--c-navy-800);width:<?= $pct ?>%;height:8px;border-radius:4px;"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div></div>

    <!-- Top Products -->
    <div class="card"><div class="card-body">
        <h3 style="font-size:15px;">Top Products by Quantity Ordered</h3>
        <?php if (empty($topProducts)): ?>
            <p style="color:#888;font-size:13px;">No product orders yet.</p>
        <?php else: ?>
            <?php foreach ($topProducts as $p): ?>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f2f2f2;font-size:13px;">
                    <span><?= e($p['item_name']) ?></span>
                    <span><strong><?= (int) $p['total_qty'] ?></strong> units &middot; <?= money($p['total_revenue']) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div></div>

    <!-- Customer Growth -->
    <div class="card"><div class="card-body">
        <h3 style="font-size:15px;">New Customers — Last 6 Months</h3>
        <?php if (empty($customerGrowth)): ?>
            <p style="color:#888;font-size:13px;">No new customers in this period.</p>
        <?php else: ?>
            <div style="display:flex;align-items:flex-end;gap:12px;height:140px;margin-top:20px;">
                <?php foreach ($customerGrowth as $c): ?>
                    <div style="flex:1;text-align:center;">
                        <div style="background:#1c4fb0;border-radius:4px 4px 0 0;height:<?= max(4, (int) (($c['c'] / $maxCustomers) * 110)) ?>px;"></div>
                        <div style="font-size:11px;color:#888;margin-top:6px;"><?= e($c['label']) ?></div>
                        <div style="font-size:11px;font-weight:700;"><?= (int) $c['c'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div></div>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
