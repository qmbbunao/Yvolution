<?php
require_once __DIR__ . '/../../config/app.php';
require_role('customer');

$user = current_user();
$pdo = Database::connect();
$orderId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?");
$stmt->execute([$orderId, $user['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect('/customer/orders/index.php');
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

$history = $pdo->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY changed_at ASC");
$history->execute([$orderId]);
$history = $history->fetchAll();

$feedback = $pdo->prepare("SELECT * FROM feedback WHERE order_id = ? AND customer_id = ?");
$feedback->execute([$orderId, $user['user_id']]);
$feedback = $feedback->fetch();

$returnRequest = $pdo->prepare("SELECT * FROM returns WHERE order_id = ? ORDER BY requested_at DESC LIMIT 1");
$returnRequest->execute([$orderId]);
$returnRequest = $returnRequest->fetch();

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

$canCancel = !in_array($order['status'], ['completed', 'cancelled'], true)
    && (time() - strtotime($order['created_at'])) <= 7200;

$successMsg = get_flash('success');
$errorMsg = get_flash('error');
$pageTitle = $order['order_code'] . ' — Yvolution Custom Apparel';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container" style="padding:50px 24px;max-width:820px;">
    <a href="<?= BASE_URL ?>/customer/orders/index.php" class="text-link" style="font-size:13px;">&larr; Back to My Orders</a>

    <?php if ($successMsg): ?><div class="alert alert-success" style="margin-top:20px;"><?= e($successMsg) ?></div><?php endif; ?>
    <?php if ($errorMsg): ?><div class="alert alert-error" style="margin-top:20px;"><?= e($errorMsg) ?></div><?php endif; ?>

    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-top:16px;">
        <h1 style="margin:0;"><?= e($order['order_code']) ?></h1>
        <span class="badge <?= $statusBadge ?>" style="font-size:13px;padding:8px 16px;"><?= e($statusLabel) ?></span>
    </div>
    <p style="color:#888;font-size:13px;">Placed on <?= date('F j, Y g:ia', strtotime($order['created_at'])) ?></p>

    <?php if ($canCancel): ?>
        <button type="button" class="btn btn-outline btn-sm" style="border-color:#b3261e;color:#b3261e;margin-top:10px;" onclick="document.getElementById('cancelOrderModal').classList.add('open')">Cancel This Order</button>
        <p style="font-size:12px;color:#999;margin-top:4px;">Free cancellation within 2 hours of placing your order.</p>
    <?php endif; ?>

    <!-- Order Items -->
    <div class="card" style="margin-top:24px;">
        <div class="card-body">
            <h3 style="font-size:16px;">Order Items</h3>
            <?php foreach ($items as $it): ?>
                <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #eee;">
                    <div>
                        <strong><?= e($it['item_name']) ?></strong>
                        <p style="font-size:13px;color:#777;margin:2px 0 0;">
                            <?= $it['size'] ? 'Size: ' . e($it['size']) . ' &middot; ' : '' ?>
                            <?= $it['color'] ? 'Color: ' . e($it['color']) . ' &middot; ' : '' ?>
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
                <p style="margin-top:12px;font-size:14px;color:#555;"><em>Notes:</em> <?= nl2br(e($order['notes'])) ?></p>
            <?php endif; ?>
            <div style="text-align:right;margin-top:12px;font-family:var(--f-display);font-size:18px;color:var(--c-accent);">
                Total: <?= money($order['total_amount']) ?>
            </div>
        </div>
    </div>

    <!-- Payment -->
    <?php $business = require BASE_PATH . '/config/business.php'; ?>
    <div class="card" style="margin-top:20px;">
        <div class="card-body">
            <h3 style="font-size:16px;">Payment</h3>

            <?php if ($order['payment_status'] === 'paid'): ?>
                <span class="badge badge-success">Payment Verified</span>
                <p style="font-size:12px;color:#999;margin-top:6px;">Verified on <?= date('M j, Y', strtotime($order['payment_verified_at'])) ?></p>

            <?php elseif ($order['payment_status'] === 'pending_verification'): ?>
                <span class="badge badge-pending">Awaiting Verification</span>
                <p style="font-size:13px;color:#666;margin-top:8px;">We received your payment proof and will verify it shortly.</p>
                <?php if ($order['payment_proof_url']): ?>
                    <a href="<?= e($order['payment_proof_url']) ?>" target="_blank">
                        <img src="<?= e($order['payment_proof_url']) ?>" style="max-width:160px;border-radius:6px;margin-top:10px;">
                    </a>
                <?php endif; ?>

            <?php else: ?>
                <?php if ($order['payment_status'] === 'rejected'): ?>
                    <div class="alert alert-error">Your last payment proof could not be verified. Please submit a clearer screenshot or receipt below.</div>
                <?php endif; ?>

                <p style="font-size:13px;color:#555;margin-bottom:12px;">Pay via GCash or bank transfer, then upload your proof of payment below.</p>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                    <div style="background:#f7f8fb;padding:12px;border-radius:8px;">
                        <strong style="font-size:13px;">GCash</strong>
                        <p style="font-size:13px;margin:4px 0 0;"><?= e($business['payment']['gcash']['number']) ?></p>
                        <p style="font-size:12px;color:#888;"><?= e($business['payment']['gcash']['account_name']) ?></p>
                    </div>
                    <div style="background:#f7f8fb;padding:12px;border-radius:8px;">
                        <strong style="font-size:13px;">Bank Transfer</strong>
                        <p style="font-size:13px;margin:4px 0 0;"><?= e($business['payment']['bank']['bank_name']) ?></p>
                        <p style="font-size:12px;color:#888;"><?= e($business['payment']['bank']['account_number']) ?> · <?= e($business['payment']['bank']['account_name']) ?></p>
                    </div>
                </div>

                <form method="POST" action="<?= BASE_URL ?>/customer/orders/payment_submit.php" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select class="form-control" id="payment_method" name="payment_method" required>
                            <option value="gcash">GCash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment_reference">Reference Number (optional)</label>
                        <input class="form-control" type="text" id="payment_reference" name="payment_reference">
                    </div>
                    <div class="form-group">
                        <label for="payment_proof">Upload Proof of Payment</label>
                        <input class="form-control" type="file" id="payment_proof" name="payment_proof" accept="image/*" required>
                    </div>
                    <button type="submit" class="btn btn-accent btn-sm">Submit Payment Proof</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quotation -->
    <?php if ($quotation): ?>
    <div class="card" style="margin-top:20px;">
        <div class="card-body">
            <h3 style="font-size:16px;">Quotation</h3>
            <p style="font-size:24px;font-family:var(--f-display);color:var(--c-accent);"><?= money($quotation['amount']) ?></p>
            <?php if ($quotation['breakdown']): ?>
                <p style="font-size:14px;color:#555;white-space:pre-line;"><?= e($quotation['breakdown']) ?></p>
            <?php endif; ?>
            <?php if ($quotation['valid_until']): ?>
                <p style="font-size:12px;color:#999;">Valid until <?= date('M j, Y', strtotime($quotation['valid_until'])) ?></p>
            <?php endif; ?>

            <?php if ($quotation['status'] === 'sent'): ?>
                <form method="POST" action="<?= BASE_URL ?>/customer/orders/quotation_respond.php" style="display:flex;gap:10px;margin-top:16px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="quotation_id" value="<?= (int) $quotation['quotation_id'] ?>">
                    <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
                    <button type="submit" name="response" value="accepted" class="btn btn-accent btn-sm">Accept Quotation</button>
                    <button type="submit" name="response" value="rejected" class="btn btn-outline btn-sm" style="border-color:#b3261e;color:#b3261e;">Decline</button>
                </form>
            <?php else: ?>
                <span class="badge <?= $quotation['status'] === 'accepted' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($quotation['status']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Design Uploads -->
    <div class="card" style="margin-top:20px;">
        <div class="card-body">
            <h3 style="font-size:16px;">Design Files</h3>
            <?php if (empty($designs)): ?>
                <p style="color:#888;font-size:14px;">No files uploaded yet.</p>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px;">
                    <?php foreach ($designs as $d): ?>
                        <a href="<?= e($d['file_url']) ?>" target="_blank" style="display:block;text-align:center;">
                            <?php if (in_array($d['file_type'], ['jpg','jpeg','png'])): ?>
                                <img src="<?= e($d['file_url']) ?>" style="height:90px;width:100%;object-fit:cover;border-radius:6px;">
                            <?php else: ?>
                                <div style="height:90px;display:flex;align-items:center;justify-content:center;background:#eee;border-radius:6px;font-size:12px;">PDF</div>
                            <?php endif; ?>
                            <span class="badge <?= $d['status'] === 'approved' ? 'badge-success' : ($d['status'] === 'rejected' ? 'badge-danger' : 'badge-pending') ?>" style="margin-top:6px;"><?= ucfirst($d['status']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- QR Tracking Code -->
    <?php if ($order['qr_code_url']): ?>
    <div class="card" style="margin-top:20px;">
        <div class="card-body" style="text-align:center;">
            <h3 style="font-size:16px;">Order Tracking QR</h3>
            <img src="<?= e($order['qr_code_url']) ?>" alt="Order QR code" style="margin:0 auto;">
            <p style="font-size:12px;color:#888;">Scan to check this order's status anytime.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Status History -->
    <div class="card" style="margin-top:20px;">
        <div class="card-body">
            <h3 style="font-size:16px;">Status History</h3>
            <?php foreach (array_reverse($history) as $h): [$hLabel] = $statusLabels[$h['status']] ?? [$h['status']]; ?>
                <div style="display:flex;gap:12px;padding:8px 0;border-bottom:1px solid #f0f0f0;font-size:13px;">
                    <span style="color:#999;min-width:140px;"><?= date('M j, g:ia', strtotime($h['changed_at'])) ?></span>
                    <span><strong><?= e($hLabel) ?></strong><?= $h['notes'] ? ' — ' . e($h['notes']) : '' ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Return / Refund -->
    <?php if ($order['status'] === 'completed'): ?>
    <div class="card" style="margin-top:20px;">
        <div class="card-body">
            <h3 style="font-size:16px;">Return / Refund</h3>

            <?php if ($returnRequest): ?>
                <?php
                    $returnBadges = ['pending' => 'badge-pending', 'approved' => 'badge-progress', 'rejected' => 'badge-danger', 'refunded' => 'badge-success'];
                    $reasonLabels = ['damaged' => 'Item Damaged', 'lost_in_delivery' => 'Lost in Delivery', 'wrong_item' => 'Wrong Item', 'other' => 'Other'];
                ?>
                <span class="badge <?= $returnBadges[$returnRequest['status']] ?? 'badge-pending' ?>"><?= ucfirst($returnRequest['status']) ?></span>
                <p style="font-size:13px;color:#666;margin-top:8px;"><strong><?= e($reasonLabels[$returnRequest['reason']] ?? $returnRequest['reason']) ?>:</strong> <?= e($returnRequest['description']) ?></p>
                <?php if ($returnRequest['proof_url']): ?>
                    <a href="<?= e($returnRequest['proof_url']) ?>" target="_blank"><img src="<?= e($returnRequest['proof_url']) ?>" style="max-width:140px;border-radius:6px;margin-top:8px;"></a>
                <?php endif; ?>
                <?php if ($returnRequest['admin_notes']): ?>
                    <p style="font-size:13px;color:#666;margin-top:8px;background:#f7f8fb;padding:10px;border-radius:6px;"><em>Shop response:</em> <?= e($returnRequest['admin_notes']) ?></p>
                <?php endif; ?>
                <?php if ($returnRequest['status'] === 'refunded' && $returnRequest['refund_amount']): ?>
                    <p style="font-size:14px;color:#1a7a44;margin-top:8px;font-weight:600;">Refunded: <?= money($returnRequest['refund_amount']) ?></p>
                <?php endif; ?>

            <?php else: ?>
                <p style="font-size:13px;color:#666;margin-bottom:12px;">If your item arrived damaged, was lost during delivery, or something else went wrong, let us know here.</p>
                <form method="POST" action="<?= BASE_URL ?>/customer/orders/return_request.php" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
                    <div class="form-group">
                        <label for="reason">Reason</label>
                        <select class="form-control" id="reason" name="reason" required>
                            <option value="damaged">Item Damaged</option>
                            <option value="lost_in_delivery">Lost in Delivery</option>
                            <option value="wrong_item">Wrong Item</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="description">Describe the Issue</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="proof">Upload Proof Photo (optional but recommended)</label>
                        <input class="form-control" type="file" id="proof" name="proof" accept="image/*">
                    </div>
                    <button type="submit" class="btn btn-outline btn-sm" style="border-color:#b3261e;color:#b3261e;">Submit Return/Refund Request</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Feedback -->
    <?php if ($order['status'] === 'completed'): ?>
    <div class="card" style="margin-top:20px;">
        <div class="card-body">
            <h3 style="font-size:16px;">Your Feedback</h3>
            <?php if ($feedback): ?>
                <div style="color:var(--c-gold);"><?= str_repeat('&#9733;', (int) $feedback['rating']) ?></div>
                <p style="font-size:14px;color:#555;"><?= e($feedback['comments']) ?></p>
            <?php else: ?>
                <form method="POST" action="<?= BASE_URL ?>/customer/orders/feedback_submit.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
                    <div class="form-group">
                        <label for="rating">Rating</label>
                        <select class="form-control" name="rating" id="rating">
                            <option value="5">5 - Excellent</option>
                            <option value="4">4 - Great</option>
                            <option value="3">3 - Good</option>
                            <option value="2">2 - Fair</option>
                            <option value="1">1 - Poor</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="comments">Comments</label>
                        <textarea class="form-control" name="comments" id="comments" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-accent btn-sm">Submit Feedback</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($canCancel): ?>
<div class="modal-overlay" id="cancelOrderModal">
    <div class="modal-box">
        <h3>Cancel This Order?</h3>
        <p>This can't be undone. Your order <?= e($order['order_code']) ?> will be marked as cancelled.</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-outline btn-sm" style="border-color:#ccc;color:#333;" onclick="document.getElementById('cancelOrderModal').classList.remove('open')">Keep Order</button>
            <form method="POST" action="<?= BASE_URL ?>/customer/orders/cancel.php">
                <?= csrf_field() ?>
                <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
                <button type="submit" class="btn btn-accent btn-sm" style="background:#b3261e;">Yes, Cancel Order</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
