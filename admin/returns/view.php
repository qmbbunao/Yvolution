<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();
$returnId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT r.*, o.order_code, o.total_amount, u.first_name, u.last_name, u.email
     FROM returns r JOIN orders o ON o.order_id = r.order_id JOIN users u ON u.user_id = r.customer_id
     WHERE r.return_id = ?"
);
$stmt->execute([$returnId]);
$ret = $stmt->fetch();

if (!$ret) {
    set_flash('error', 'Return request not found.');
    redirect('/admin/returns/index.php');
}

$reasonLabels = ['damaged' => 'Item Damaged', 'lost_in_delivery' => 'Lost in Delivery', 'wrong_item' => 'Wrong Item', 'other' => 'Other'];

$pageTitle = 'Return Request — Admin';
$activeNav = 'returns';
include __DIR__ . '/../../includes/admin_header.php';
?>

<a href="<?= BASE_URL ?>/admin/returns/index.php" class="text-link" style="font-size:13px;">&larr; Back to Returns</a>

<div class="admin-page-header" style="margin-top:16px;">
    <h1 style="font-size:22px;">Return Request — <?= e($ret['order_code']) ?></h1>
    <span class="badge <?= $ret['status'] === 'pending' ? 'badge-pending' : ($ret['status'] === 'refunded' ? 'badge-success' : ($ret['status'] === 'rejected' ? 'badge-danger' : 'badge-progress')) ?>"><?= ucfirst($ret['status']) ?></span>
</div>

<div class="card" style="max-width:600px;">
    <div class="card-body">
        <p style="font-size:13px;color:#888;">
            <?= e($ret['first_name'] . ' ' . $ret['last_name']) ?> &lt;<?= e($ret['email']) ?>&gt;
            &middot; Order total: <?= money($ret['total_amount']) ?>
            &middot; Requested <?= date('M j, Y g:ia', strtotime($ret['requested_at'])) ?>
        </p>
        <p style="margin-top:12px;"><strong><?= e($reasonLabels[$ret['reason']] ?? $ret['reason']) ?></strong></p>
        <p style="margin-top:6px;color:#555;white-space:pre-line;"><?= e($ret['description']) ?></p>

        <?php if ($ret['proof_url']): ?>
            <a href="<?= e($ret['proof_url']) ?>" target="_blank">
                <img src="<?= e($ret['proof_url']) ?>" style="max-width:300px;border-radius:8px;margin-top:14px;">
            </a>
        <?php else: ?>
            <p style="font-size:13px;color:#999;margin-top:10px;">No proof photo was attached.</p>
        <?php endif; ?>

        <?php if ($ret['admin_notes']): ?>
            <p style="margin-top:14px;font-size:13px;background:#f7f8fb;padding:10px;border-radius:6px;"><em>Previous response:</em> <?= e($ret['admin_notes']) ?></p>
        <?php endif; ?>
    </div>
</div>

<?php if (in_array($ret['status'], ['pending', 'approved'], true)): ?>
<div class="card" style="max-width:600px;margin-top:20px;">
    <div class="card-body">
        <h3 style="font-size:15px;">Resolve This Request</h3>
        <form method="POST" action="<?= BASE_URL ?>/admin/returns/resolve.php">
            <?= csrf_field() ?>
            <input type="hidden" name="return_id" value="<?= (int) $ret['return_id'] ?>">

            <div class="form-group">
                <label for="admin_notes">Response to Customer</label>
                <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" placeholder="e.g. Approved — refund will be sent to your GCash within 3 business days."></textarea>
            </div>

            <div class="form-group">
                <label for="refund_amount">Refund Amount (₱) — required only if refunding</label>
                <input class="form-control" type="number" step="0.01" id="refund_amount" name="refund_amount" value="<?= (float) $ret['total_amount'] ?>">
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php if ($ret['status'] === 'pending'): ?>
                    <button type="submit" name="decision" value="approved" class="btn btn-dark btn-sm">Approve</button>
                    <button type="submit" name="decision" value="rejected" class="btn btn-outline btn-sm" style="border-color:#b3261e;color:#b3261e;">Reject</button>
                <?php endif; ?>
                <button type="submit" name="decision" value="refunded" class="btn btn-accent btn-sm">Mark as Refunded</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
