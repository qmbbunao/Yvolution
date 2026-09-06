<?php
require_once __DIR__ . '/../config/app.php';
require_role('superadmin');

$pdo = Database::connect();

$userCounts = $pdo->query(
    "SELECT r.role_name, COUNT(*) AS c FROM users u JOIN roles r ON r.role_id = u.role_id GROUP BY r.role_name"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$orderStats = $pdo->query(
    "SELECT COUNT(*) AS total, SUM(CASE WHEN status='completed' THEN total_amount ELSE 0 END) AS revenue FROM orders"
)->fetch();

$recentAudit = $pdo->query(
    "SELECT a.*, u.first_name, u.last_name FROM audit_logs a
     LEFT JOIN users u ON u.user_id = a.user_id ORDER BY a.created_at DESC LIMIT 8"
)->fetchAll();

$pageTitle = 'Super Admin — Yvolution Custom Apparel';
$activeNav = 'dashboard';
include __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-page-header"><h1>Super Admin Console</h1></div>

<div class="stat-grid">
    <div class="stat-card"><div class="value"><?= (int) ($userCounts['customer'] ?? 0) ?></div><div class="label">Customers</div></div>
    <div class="stat-card"><div class="value"><?= (int) ($userCounts['admin'] ?? 0) ?></div><div class="label">Admins</div></div>
    <div class="stat-card"><div class="value"><?= (int) ($userCounts['superadmin'] ?? 0) ?></div><div class="label">Super Admins</div></div>
    <div class="stat-card"><div class="value"><?= (int) $orderStats['total'] ?></div><div class="label">Total Orders</div></div>
    <div class="stat-card success"><div class="value"><?= money((float) $orderStats['revenue']) ?></div><div class="label">Total Revenue</div></div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:32px;">
    <a href="<?= BASE_URL ?>/superadmin/users/index.php" class="card" style="display:block;"><div class="card-body"><strong>👥 Users & Roles</strong><p style="font-size:13px;color:#777;margin:6px 0 0;">Manage admin/superadmin accounts</p></div></a>
    <a href="<?= BASE_URL ?>/superadmin/settings/index.php" class="card" style="display:block;"><div class="card-body"><strong>🔌 API Settings</strong><p style="font-size:13px;color:#777;margin:6px 0 0;">Enable/disable integrations</p></div></a>
    <a href="<?= BASE_URL ?>/superadmin/analytics/index.php" class="card" style="display:block;"><div class="card-body"><strong>📊 Analytics</strong><p style="font-size:13px;color:#777;margin:6px 0 0;">Revenue, orders, top products</p></div></a>
    <a href="<?= BASE_URL ?>/superadmin/analytics/audit_logs.php" class="card" style="display:block;"><div class="card-body"><strong>📜 Audit Logs</strong><p style="font-size:13px;color:#777;margin:6px 0 0;">Full system activity trail</p></div></a>
    <a href="<?= BASE_URL ?>/superadmin/backups/index.php" class="card" style="display:block;"><div class="card-body"><strong>💾 Backups</strong><p style="font-size:13px;color:#777;margin:6px 0 0;">Backup & restore the database</p></div></a>
</div>

<div class="data-table-wrap">
    <div style="padding:18px 20px;display:flex;justify-content:space-between;align-items:center;">
        <h3 style="margin:0;font-size:16px;">Recent Activity</h3>
        <a href="<?= BASE_URL ?>/superadmin/analytics/audit_logs.php" class="action-link edit">View All →</a>
    </div>
    <table class="data-table">
        <thead><tr><th>User</th><th>Action</th><th>Details</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($recentAudit as $a): ?>
            <tr>
                <td><?= e($a['first_name'] ? $a['first_name'] . ' ' . $a['last_name'] : 'System') ?></td>
                <td><?= e(str_replace('_', ' ', $a['action'])) ?></td>
                <td style="font-size:13px;color:#888;"><?= e($a['details'] ?? '—') ?></td>
                <td style="font-size:13px;color:#888;"><?= date('M j, g:ia', strtotime($a['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($recentAudit)): ?>
            <tr><td colspan="4" class="empty-state">No activity recorded yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
