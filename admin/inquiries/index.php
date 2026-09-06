<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT * FROM contact_inquiries WHERE 1=1";
$params = [];
if ($statusFilter !== '') {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inquiries = $stmt->fetchAll();

$pageTitle = 'Inquiries — Admin';
$activeNav = 'inquiries';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header"><h1>Contact Inquiries</h1></div>

<div class="table-toolbar">
    <form method="GET" style="display:flex;gap:10px;">
        <select class="form-control" name="status" onchange="this.form.submit()">
            <option value="">All</option>
            <option value="new" <?= $statusFilter === 'new' ? 'selected' : '' ?>>New</option>
            <option value="read" <?= $statusFilter === 'read' ? 'selected' : '' ?>>Read</option>
            <option value="responded" <?= $statusFilter === 'responded' ? 'selected' : '' ?>>Responded</option>
        </select>
    </form>
</div>

<div class="data-table-wrap">
    <table class="data-table">
        <thead><tr><th>From</th><th>Subject</th><th>Message</th><th>Status</th><th>Received</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($inquiries as $i): ?>
            <tr onclick="window.location='<?= BASE_URL ?>/admin/inquiries/view.php?id=<?= (int) $i['inquiry_id'] ?>'" style="cursor:pointer;<?= $i['status'] === 'new' ? 'font-weight:600;' : '' ?>">
                <td><?= e($i['name']) ?><br><span style="font-size:12px;color:#999;font-weight:normal;"><?= e($i['email']) ?></span></td>
                <td><?= e($i['subject'] ?: 'General Inquiry') ?></td>
                <td style="max-width:260px;font-size:13px;color:#666;font-weight:normal;"><?= e(mb_strimwidth($i['message'], 0, 70, '...')) ?></td>
                <td><span class="badge <?= $i['status'] === 'new' ? 'badge-pending' : ($i['status'] === 'responded' ? 'badge-success' : 'badge-progress') ?>"><?= ucfirst($i['status']) ?></span></td>
                <td style="font-size:13px;color:#888;font-weight:normal;"><?= date('M j, Y g:ia', strtotime($i['created_at'])) ?></td>
                <td>&rarr;</td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($inquiries)): ?>
            <tr><td colspan="6" class="empty-state">No inquiries yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
