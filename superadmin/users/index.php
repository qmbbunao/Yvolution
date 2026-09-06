<?php
require_once __DIR__ . '/../../config/app.php';
require_role('superadmin');

$pdo = Database::connect();
$roleFilter = $_GET['role'] ?? '';

$sql = "SELECT u.*, r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE 1=1";
$params = [];
if ($roleFilter !== '') {
    $sql .= " AND r.role_name = ?";
    $params[] = $roleFilter;
}
$sql .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Users & Roles — Super Admin';
$activeNav = 'users';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header">
    <h1>Users & Roles</h1>
    <a href="<?= BASE_URL ?>/superadmin/users/form.php" class="btn btn-accent">+ Add Admin/Super Admin</a>
</div>

<div class="table-toolbar">
    <form method="GET" style="display:flex;gap:10px;">
        <select class="form-control" name="role" onchange="this.form.submit()">
            <option value="">All Roles</option>
            <option value="customer" <?= $roleFilter === 'customer' ? 'selected' : '' ?>>Customer</option>
            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="superadmin" <?= $roleFilter === 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
        </select>
    </form>
</div>

<div class="data-table-wrap">
    <table class="data-table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><strong><?= e($u['first_name'] . ' ' . $u['last_name']) ?></strong></td>
                <td><?= e($u['email']) ?></td>
                <td><span class="badge <?= $u['role_name'] === 'superadmin' ? 'badge-danger' : ($u['role_name'] === 'admin' ? 'badge-progress' : 'badge-pending') ?>"><?= ucfirst($u['role_name']) ?></span></td>
                <td><span class="badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($u['status']) ?></span></td>
                <td style="font-size:13px;color:#888;"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                <td style="white-space:nowrap;">
                    <?php if ($u['role_name'] !== 'customer'): ?>
                        <a href="<?= BASE_URL ?>/superadmin/users/form.php?id=<?= (int) $u['user_id'] ?>" class="action-link edit">Edit</a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/superadmin/users/toggle_status.php?id=<?= (int) $u['user_id'] ?>" class="action-link <?= $u['status'] === 'active' ? 'danger' : 'edit' ?>"
                       onclick="return confirm('<?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?> this account?');">
                        <?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
            <tr><td colspan="6" class="empty-state">No users found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
