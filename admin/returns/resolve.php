<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/mailer.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request.');
    redirect('/admin/returns/index.php');
}

$returnId = (int) ($_POST['return_id'] ?? 0);
$decision = $_POST['decision'] ?? '';
$adminNotes = trim($_POST['admin_notes'] ?? '');
$refundAmount = $_POST['refund_amount'] !== '' ? (float) $_POST['refund_amount'] : null;

if (!in_array($decision, ['approved', 'rejected', 'refunded'], true)) {
    set_flash('error', 'Invalid decision.');
    redirect('/admin/returns/index.php');
}

$stmt = $pdo->prepare(
    "SELECT r.*, o.order_code, u.first_name, u.email FROM returns r
     JOIN orders o ON o.order_id = r.order_id JOIN users u ON u.user_id = r.customer_id
     WHERE r.return_id = ?"
);
$stmt->execute([$returnId]);
$ret = $stmt->fetch();

if (!$ret) {
    set_flash('error', 'Return request not found.');
    redirect('/admin/returns/index.php');
}

if ($decision === 'refunded') {
    $pdo->prepare(
        "UPDATE returns SET status = 'refunded', admin_notes = ?, refund_amount = ?, resolved_by = ?, resolved_at = NOW() WHERE return_id = ?"
    )->execute([$adminNotes, $refundAmount, $admin['user_id'], $returnId]);
    $pdo->prepare("UPDATE orders SET payment_status = 'refunded' WHERE order_id = ?")->execute([$ret['order_id']]);
} else {
    $pdo->prepare(
        "UPDATE returns SET status = ?, admin_notes = ?, resolved_by = ?, resolved_at = NOW() WHERE return_id = ?"
    )->execute([$decision, $adminNotes, $admin['user_id'], $returnId]);
}

log_audit($pdo, $admin['user_id'], 'return_' . $decision, 'returns', $returnId, $ret['order_code']);

$mailer = new Mailer();
$statusLabel = ['approved' => 'Approved', 'rejected' => 'Rejected', 'refunded' => 'Refunded'][$decision];
$body = email_template('Return/Refund Update', "
    <p>Hi " . e($ret['first_name']) . ",</p>
    <p>Your return/refund request for order <strong>{$ret['order_code']}</strong> has been updated to:</p>
    <p style='font-size:20px;color:#ff4b23;font-weight:bold;'>{$statusLabel}</p>
    " . ($adminNotes ? '<p>' . nl2br(e($adminNotes)) . '</p>' : '') . "
    " . ($decision === 'refunded' && $refundAmount ? '<p>Refund amount: ' . money($refundAmount) . '</p>' : '') . "
");
$mailer->send($ret['email'], $ret['first_name'], 'Return/Refund ' . $statusLabel . ' — ' . $ret['order_code'], $body);

set_flash('success', 'Return request updated.');
redirect('/admin/returns/view.php?id=' . $returnId);
