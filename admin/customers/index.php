<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();
$search = trim($_GET['q'] ?? '');

$sql = "SELECT u.*, COUNT(o.order_id) AS order_count, COALESCE(SUM(CASE WHEN o.status='completed' THEN o.total_amount ELSE 0 END),0) AS total_spent
        FROM users u LEFT JOIN orders o ON o.customer_id = u.user_id
        WHERE u.role_id = 1";
$params = [];
if ($search !== '') {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $like = "%{$search}%";
    array_push($params, $like, $like, $like);
}
$sql .= " GROUP BY u.user_id ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$pageTitle = 'Customers — Admin';
$activeNav = 'customers';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header"><h1>Customers</h1></div>

<div class="table-toolbar">
    <form method="GET" style="display:flex;gap:10px;">
        <input class="form-control" type="text" name="q" placeholder="Search name or email..." value="<?= e($search) ?>">
        <button type="submit" class="btn btn-dark btn-sm">Search</button>
    </form>
</div>

<div class="data-table-wrap">
    <table class="data-table">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Total Spent</th><th>Status</th><th>Joined</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
            <tr>
                <td><strong><?= e($c['first_name'] . ' ' . $c['last_name']) ?></strong></td>
                <td><?= e($c['email']) ?></td>
                <td><?= e($c['phone'] ?: '—') ?></td>
                <td><?= (int) $c['order_count'] ?></td>
                <td><?= money($c['total_spent']) ?></td>
                <td><span class="badge <?= $c['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($c['status']) ?></span></td>
                <td style="font-size:13px;color:#888;"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/admin/customers/toggle_status.php?id=<?= (int) $c['user_id'] ?>" class="action-link <?= $c['status'] === 'active' ? 'danger' : 'edit' ?>"
                       onclick="return confirm('<?= $c['status'] === 'active' ? 'Deactivate' : 'Activate' ?> this customer account?');">
                        <?= $c['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($customers)): ?>
            <tr><td colspan="8" class="empty-state">No customers found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
