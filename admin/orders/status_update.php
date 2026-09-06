<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/mailer.php';
require_once BASE_PATH . '/includes/google_calendar.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request.');
    redirect('/admin/orders/index.php');
}

$orderId = (int) ($_POST['order_id'] ?? 0);
$status  = $_POST['status'] ?? '';
$notes   = trim($_POST['notes'] ?? '');
$productionDate = trim($_POST['production_date'] ?? '');

$validStatuses = ['pending_review','quotation_sent','quotation_accepted','quotation_rejected','in_production','ready_for_pickup','completed','cancelled'];
if (!in_array($status, $validStatuses, true)) {
    set_flash('error', 'Invalid status.');
    redirect('/admin/orders/view.php?id=' . $orderId);
}

$stmt = $pdo->prepare(
    "SELECT o.*, u.first_name, u.email FROM orders o JOIN users u ON u.user_id = o.customer_id WHERE o.order_id = ?"
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect('/admin/orders/index.php');
}

update_order_status($pdo, $orderId, $status, $admin['user_id'], $notes ?: null);

// --- Google Calendar sync (best-effort; never blocks the status update) ---
$google = new GoogleCalendarClient();
if ($google->isConnected()) {
    if ($status === 'in_production' && $productionDate !== '') {
        $eventId = $google->createEvent(
            'Production: ' . $order['order_code'],
            "Order {$order['order_code']} for {$order['first_name']}." . ($notes ? "\n\n{$notes}" : ''),
            $productionDate
        );
        if ($eventId) {
            $pdo->prepare("UPDATE orders SET production_event_id = ? WHERE order_id = ?")->execute([$eventId, $orderId]);
        }
    } elseif (in_array($status, ['completed', 'cancelled'], true) && !empty($order['production_event_id'])) {
        $google->deleteEvent($order['production_event_id']);
        $pdo->prepare("UPDATE orders SET production_event_id = NULL WHERE order_id = ?")->execute([$orderId]);
    }
}

$statusLabels = [
    'pending_review' => 'Pending Review', 'quotation_sent' => 'Quotation Sent',
    'quotation_accepted' => 'Quotation Accepted', 'quotation_rejected' => 'Quotation Rejected',
    'in_production' => 'In Production', 'ready_for_pickup' => 'Ready for Pickup',
    'completed' => 'Completed', 'cancelled' => 'Cancelled',
];

// Notify customer on meaningful production milestones
if (in_array($status, ['in_production', 'ready_for_pickup', 'completed', 'cancelled'], true)) {
    $mailer = new Mailer();
    $body = email_template('Order Status Update', "
        <p>Hi " . e($order['first_name']) . ",</p>
        <p>Your order <strong>{$order['order_code']}</strong> is now:</p>
        <p style='font-size:20px;color:#ff4b23;font-weight:bold;'>" . e($statusLabels[$status]) . "</p>
        " . ($notes ? '<p>' . nl2br(e($notes)) . '</p>' : '') . "
    ");
    $mailer->send($order['email'], $order['first_name'], 'Order Update — ' . $order['order_code'], $body);
}

log_audit($pdo, $admin['user_id'], 'order_status_updated', 'orders', $orderId, $status);

set_flash('success', 'Order status updated.');
redirect('/admin/orders/view.php?id=' . $orderId);
