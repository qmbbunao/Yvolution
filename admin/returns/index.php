<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT r.*, o.order_code, u.first_name, u.last_name
        FROM returns r
        JOIN orders o ON o.order_id = r.order_id
        JOIN users u ON u.user_id = r.customer_id
        WHERE 1=1";
$params = [];
if ($statusFilter !== '') {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY r.requested_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$returns = $stmt->fetchAll();

$reasonLabels = ['damaged' => 'Item Damaged', 'lost_in_delivery' => 'Lost in Delivery', 'wrong_item' => 'Wrong Item', 'other' => 'Other'];

$pageTitle = 'Returns & Refunds — Admin';
$activeNav = 'returns';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header"><h1>Returns & Refunds</h1></div>

<div class="table-toolbar">
    <form method="GET" style="display:flex;gap:10px;">
        <select class="form-control" name="status" onchange="this.form.submit()">
            <option value="">All</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>
    </form>
</div>

<div class="data-table-wrap">
    <table class="data-table">
        <thead><tr><th>Order</th><th>Customer</th><th>Reason</th><th>Status</th><th>Requested</th></tr></thead>
        <tbody>
        <?php foreach ($returns as $r): ?>
            <tr onclick="window.location='<?= BASE_URL ?>/admin/returns/view.php?id=<?= (int) $r['return_id'] ?>'" style="cursor:pointer;">
                <td><strong><?= e($r['order_code']) ?></strong></td>
                <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                <td><?= e($reasonLabels[$r['reason']] ?? $r['reason']) ?></td>
                <td><span class="badge <?= $r['status'] === 'pending' ? 'badge-pending' : ($r['status'] === 'refunded' ? 'badge-success' : ($r['status'] === 'rejected' ? 'badge-danger' : 'badge-progress')) ?>"><?= ucfirst($r['status']) ?></span></td>
                <td style="font-size:13px;color:#888;"><?= date('M j, Y g:ia', strtotime($r['requested_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($returns)): ?>
            <tr><td colspan="5" class="empty-state">No return/refund requests.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
