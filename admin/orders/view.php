<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();
$orderId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT o.*, u.first_name, u.last_name, u.email, u.phone
     FROM orders o JOIN users u ON u.user_id = o.customer_id WHERE o.order_id = ?"
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect('/admin/orders/index.php');
}

$items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items->execute([$orderId]);
$items = $items->fetchAll();

$designs = $pdo->prepare("SELECT * FROM design_uploads WHERE order_id = ? ORDER BY uploaded_at DESC");
$designs->execute([$orderId]);
$designs = $designs->fetchAll();

$quotation = $pdo->prepare("SELECT * FROM quotations WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
$quotation->execute([$orderId]);
$quotation = $quotation->fetch();

$history = $pdo->prepare("SELECT h.*, u.first_name, u.last_name FROM order_status_history h
                           LEFT JOIN users u ON u.user_id = h.changed_by
                           WHERE order_id = ? ORDER BY changed_at ASC");
$history->execute([$orderId]);
$history = $history->fetchAll();

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
[$statusLabel, $statusBadge] = $statusLabels[$order['status']] ?? [$order['status'], 'badge-pending'];

$successMsg = get_flash('success');
$errorMsg = get_flash('error');
$pageTitle = $order['order_code'] . ' — Admin';
$activeNav = 'orders';
include __DIR__ . '/../../includes/admin_header.php';
?>

<a href="<?= BASE_URL ?>/admin/orders/index.php" class="text-link" style="font-size:13px;">&larr; Back to Orders</a>

<?php if ($successMsg): ?><div class="alert alert-success" style="margin-top:16px;"><?= e($successMsg) ?></div><?php endif; ?>
<?php if ($errorMsg): ?><div class="alert alert-error" style="margin-top:16px;"><?= e($errorMsg) ?></div><?php endif; ?>

<div class="admin-page-header" style="margin-top:16px;">
    <div>
        <h1 style="margin-bottom:4px;"><?= e($order['order_code']) ?></h1>
        <p style="color:#888;font-size:13px;">
            <?= e($order['first_name'] . ' ' . $order['last_name']) ?> &middot; <?= e($order['email']) ?>
            <?= $order['phone'] ? ' &middot; ' . e($order['phone']) : '' ?>
        </p>
    </div>
    <span class="badge <?= $statusBadge ?>" style="font-size:13px;padding:8px 16px;"><?= e($statusLabel) ?></span>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start;">
    <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Items -->
        <div class="card"><div class="card-body">
            <h3 style="font-size:15px;">Order Items</h3>
            <?php foreach ($items as $it): ?>
                <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #eee;">
                    <div>
                        <strong><?= e($it['item_name']) ?></strong>
                        <p style="font-size:13px;color:#777;margin:2px 0 0;">
                            <?= $it['size'] ? 'Size: ' . e($it['size']) . ' · ' : '' ?>
                            <?= $it['color'] ? 'Color: ' . e($it['color']) . ' · ' : '' ?>
                            Qty: <?= (int) $it['quantity'] ?>
                        </p>
                    </div>
                    <strong><?= money($it['subtotal']) ?></strong>
                </div>
            <?php endforeach; ?>
            <?php if ($order['event_type']): ?>
                <span class="badge badge-progress">Event: <?= e($order['event_type']) ?></span>
            <?php endif; ?>
            <?php if ($order['notes']): ?>
                <p style="margin-top:12px;font-size:14px;color:#555;background:#f7f8fb;padding:10px;border-radius:6px;"><em>Customer notes:</em> <?= nl2br(e($order['notes'])) ?></p>
            <?php endif; ?>
            <?php if ($order['delivery_address']): ?>
                <p style="margin-top:8px;font-size:13px;color:#666;">📍 <?= e($order['delivery_address']) ?></p>
            <?php endif; ?>
        </div></div>

        <!-- Design Review -->
        <div class="card"><div class="card-body">
            <h3 style="font-size:15px;">Design Files</h3>
            <?php if (empty($designs)): ?>
                <p style="color:#888;font-size:14px;">No files uploaded.</p>
            <?php endif; ?>
            <?php foreach ($designs as $d): ?>
                <div style="display:flex;gap:16px;align-items:center;padding:12px 0;border-bottom:1px solid #f0f0f0;">
                    <a href="<?= e($d['file_url']) ?>" target="_blank">
                        <?php if (in_array($d['file_type'], ['jpg','jpeg','png'])): ?>
                            <img src="<?= e($d['file_url']) ?>" class="thumb" style="width:60px;height:60px;">
                        <?php else: ?>
                            <div class="thumb" style="width:60px;height:60px;display:flex;align-items:center;justify-content:center;background:#eee;font-size:11px;">PDF</div>
                        <?php endif; ?>
                    </a>
                    <div style="flex:1;">
                        <strong style="font-size:13px;"><?= e($d['label']) ?></strong>
                        <div><span class="badge <?= $d['status'] === 'approved' ? 'badge-success' : ($d['status'] === 'rejected' ? 'badge-danger' : 'badge-pending') ?>"><?= ucfirst($d['status']) ?></span></div>
                    </div>
                    <?php if ($d['status'] === 'pending'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/orders/design_review.php" style="display:flex;gap:6px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="upload_id" value="<?= (int) $d['upload_id'] ?>">
                            <input type="hidden" name="order_id" value="<?= (int) $orderId ?>">
                            <button type="submit" name="decision" value="approved" class="btn btn-accent btn-sm">Approve</button>
                            <button type="submit" name="decision" value="rejected" class="btn btn-outline btn-sm" style="border-color:#b3261e;color:#b3261e;">Reject</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div></div>

        <!-- Payment -->
        <div class="card"><div class="card-body">
            <h3 style="font-size:15px;">Payment</h3>
            <?php
                $paymentBadges = [
                    'unpaid' => 'badge-pending', 'pending_verification' => 'badge-progress',
                    'paid' => 'badge-success', 'rejected' => 'badge-danger', 'refunded' => 'badge-danger',
                ];
            ?>
            <span class="badge <?= $paymentBadges[$order['payment_status']] ?? 'badge-pending' ?>"><?= ucwords(str_replace('_', ' ', $order['payment_status'])) ?></span>

            <?php if ($order['payment_proof_url']): ?>
                <div style="margin-top:12px;">
                    <a href="<?= e($order['payment_proof_url']) ?>" target="_blank">
                        <img src="<?= e($order['payment_proof_url']) ?>" style="max-width:200px;border-radius:6px;">
                    </a>
                    <p style="font-size:13px;color:#666;margin-top:6px;">
                        Method: <?= e(ucfirst(str_replace('_', ' ', (string) $order['payment_method']))) ?>
                        <?= $order['payment_reference'] ? ' · Ref: ' . e($order['payment_reference']) : '' ?>
                    </p>
                    <p style="font-size:12px;color:#999;">Submitted <?= date('M j, Y g:ia', strtotime($order['payment_submitted_at'])) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($order['payment_status'] === 'pending_verification'): ?>
                <form method="POST" action="<?= BASE_URL ?>/admin/orders/payment_verify.php" style="display:flex;gap:8px;margin-top:14px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= (int) $orderId ?>">
                    <button type="submit" name="decision" value="paid" class="btn btn-accent btn-sm">Verify Payment</button>
                    <button type="submit" name="decision" value="rejected" class="btn btn-outline btn-sm" style="border-color:#b3261e;color:#b3261e;">Reject</button>
                </form>
            <?php endif; ?>
        </div></div>

        <!-- Quotation -->
        <div class="card"><div class="card-body">
            <h3 style="font-size:15px;">Quotation</h3>
            <?php if ($quotation): ?>
                <p style="font-size:22px;font-family:var(--f-display);color:var(--c-accent);"><?= money($quotation['amount']) ?></p>
                <p style="font-size:13px;color:#666;white-space:pre-line;"><?= e($quotation['breakdown']) ?></p>
                <span class="badge <?= $quotation['status'] === 'accepted' ? 'badge-success' : ($quotation['status'] === 'rejected' ? 'badge-danger' : 'badge-progress') ?>"><?= ucfirst($quotation['status']) ?></span>
            <?php else: ?>
                <p style="font-size:13px;color:#888;">No quotation sent yet.</p>
            <?php endif; ?>

            <?php if (!$quotation || $quotation['status'] === 'rejected'): ?>
                <form method="POST" action="<?= BASE_URL ?>/admin/orders/quotation_create.php" style="margin-top:16px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= (int) $orderId ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="amount">Quoted Amount (₱)</label>
                            <input class="form-control" type="number" step="0.01" id="amount" name="amount" value="<?= (float) $order['total_amount'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="valid_until">Valid Until</label>
                            <input class="form-control" type="date" id="valid_until" name="valid_until">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="breakdown">Cost Breakdown / Notes</label>
                        <textarea class="form-control" id="breakdown" name="breakdown" rows="3" placeholder="e.g. 15 jerseys x ₱450 + rush fee ₱500"></textarea>
                    </div>
                    <button type="submit" class="btn btn-accent btn-sm">Send Quotation</button>
                </form>
            <?php endif; ?>
        </div></div>

        <!-- Status History -->
        <div class="card"><div class="card-body">
            <h3 style="font-size:15px;">Status History</h3>
            <?php foreach (array_reverse($history) as $h): [$hLabel] = $statusLabels[$h['status']] ?? [$h['status']]; ?>
                <div style="display:flex;gap:12px;padding:8px 0;border-bottom:1px solid #f5f5f5;font-size:13px;">
                    <span style="color:#999;min-width:130px;"><?= date('M j, g:ia', strtotime($h['changed_at'])) ?></span>
                    <span><strong><?= e($hLabel) ?></strong><?= $h['notes'] ? ' — ' . e($h['notes']) : '' ?>
                        <?= $h['first_name'] ? ' <em style="color:#aaa;">by ' . e($h['first_name']) . '</em>' : '' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div></div>
    </div>

    <!-- Sidebar: status control -->
    <div class="card">
        <div class="card-body">
            <h3 style="font-size:15px;">Update Status</h3>
            <form method="POST" action="<?= BASE_URL ?>/admin/orders/status_update.php">
                <?= csrf_field() ?>
                <input type="hidden" name="order_id" value="<?= (int) $orderId ?>">
                <div class="form-group">
                    <label for="status">Production Status</label>
                    <select class="form-control" id="status" name="status">
                        <?php foreach ($statusLabels as $key => [$label, $badge]): ?>
                            <option value="<?= e($key) ?>" <?= $order['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="notes">Note (optional)</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="e.g. Printing started, ETA 3 days"></textarea>
                </div>
                <div class="form-group">
                    <label for="production_date">Production Date (optional)</label>
                    <input class="form-control" type="date" id="production_date" name="production_date">
                    <p style="font-size:11px;color:#888;margin-top:4px;">If set while status is "In Production" and Google Calendar is connected, this creates a calendar event.</p>
                </div>
                <button type="submit" class="btn btn-dark btn-block">Update Status</button>
            </form>
            <?php if ($order['production_event_id']): ?>
                <p style="font-size:12px;color:#1a7a44;margin-top:10px;">📅 Scheduled on Google Calendar</p>
            <?php endif; ?>
            <?php if ($order['qr_code_url']): ?>
                <div style="text-align:center;margin-top:20px;">
                    <img src="<?= e($order['qr_code_url']) ?>" style="width:140px;">
                    <p style="font-size:11px;color:#999;">Tracking QR</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
