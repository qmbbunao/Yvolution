<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT o.*, u.first_name, u.last_name, u.email,
        GROUP_CONCAT(oi.item_name SEPARATOR ', ') AS items_summary
        FROM orders o
        JOIN users u ON u.user_id = o.customer_id
        LEFT JOIN order_items oi ON oi.order_id = o.order_id
        WHERE 1=1";
$params = [];

if ($statusFilter !== '') {
    $sql .= " AND o.status = ?";
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= " AND (o.order_code LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $like = "%{$search}%";
    array_push($params, $like, $like, $like, $like);
}

$sql .= " GROUP BY o.order_id ORDER BY o.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
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

$pageTitle = 'Orders — Admin';
$activeNav = 'orders';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header"><h1>Orders</h1></div>

<div class="table-toolbar">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select class="form-control" name="status" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <?php foreach ($statusLabels as $key => [$label, $badge]): ?>
                <option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <input class="form-control" type="text" name="q" placeholder="Search order code, customer..." value="<?= e($search) ?>">
        <button type="submit" class="btn btn-dark btn-sm">Search</button>
    </form>
</div>

<div class="data-table-wrap">
    <table class="data-table">
        <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Status</th><th>Total</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): [$label, $badge] = $statusLabels[$o['status']] ?? [$o['status'], 'badge-pending']; ?>
            <tr onclick="window.location='<?= BASE_URL ?>/admin/orders/view.php?id=<?= (int) $o['order_id'] ?>'" style="cursor:pointer;">
                <td><strong><?= e($o['order_code']) ?></strong></td>
                <td><?= e($o['first_name'] . ' ' . $o['last_name']) ?><br><span style="font-size:12px;color:#999;"><?= e($o['email']) ?></span></td>
                <td style="max-width:220px;font-size:13px;color:#666;"><?= e(mb_strimwidth($o['items_summary'] ?: 'Custom Request', 0, 50, '...')) ?></td>
                <td><span class="badge <?= $badge ?>"><?= e($label) ?></span></td>
                <td><?= money($o['total_amount']) ?></td>
                <td style="font-size:13px;color:#888;"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($orders)): ?>
            <tr><td colspan="6" class="empty-state">No orders match your filters.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
