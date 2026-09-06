<?php
require_once __DIR__ . '/../../config/app.php';
require_role('superadmin');

$pdo = Database::connect();

$search = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 40;
$offset = ($page - 1) * $perPage;

$sql = "SELECT a.*, u.first_name, u.last_name FROM audit_logs a LEFT JOIN users u ON u.user_id = a.user_id WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (a.action LIKE ? OR a.table_affected LIKE ? OR a.details LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $like = "%{$search}%";
    array_push($params, $like, $like, $like, $like, $like);
}

$countStmt = $pdo->prepare(str_replace('SELECT a.*, u.first_name, u.last_name', 'SELECT COUNT(*) AS c', $sql));
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetch()['c'];

$sql .= " ORDER BY a.created_at DESC LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$totalPages = max(1, (int) ceil($totalRows / $perPage));

$pageTitle = 'Audit Logs — Super Admin';
$activeNav = 'audit';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header"><h1>Audit Logs</h1></div>

<div class="table-toolbar">
    <form method="GET" style="display:flex;gap:10px;">
        <input class="form-control" type="text" name="q" placeholder="Search action, table, user..." value="<?= e($search) ?>">
        <button type="submit" class="btn btn-dark btn-sm">Search</button>
    </form>
</div>

<div class="data-table-wrap">
    <table class="data-table">
        <thead><tr><th>User</th><th>Action</th><th>Table</th><th>Details</th><th>IP</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td><?= e($l['first_name'] ? $l['first_name'] . ' ' . $l['last_name'] : 'System') ?></td>
                <td><?= e(str_replace('_', ' ', $l['action'])) ?></td>
                <td style="color:#888;font-size:13px;"><?= e($l['table_affected'] ?: '—') ?></td>
                <td style="max-width:220px;font-size:13px;color:#666;"><?= e(mb_strimwidth((string) $l['details'], 0, 60, '...')) ?></td>
                <td style="font-size:12px;color:#aaa;"><?= e($l['ip_address'] ?: '—') ?></td>
                <td style="font-size:13px;color:#888;white-space:nowrap;"><?= date('M j, Y g:ia', strtotime($l['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
            <tr><td colspan="6" class="empty-state">No matching activity found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <div style="display:flex;gap:8px;margin-top:16px;justify-content:center;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
               class="btn btn-sm <?= $i === $page ? 'btn-dark' : 'btn-outline' ?>" style="<?= $i === $page ? '' : 'border-color:#ccc;color:#333;' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
