<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/cloudinary.php';
require_once BASE_PATH . '/includes/qr.php';
require_once BASE_PATH . '/includes/mailer.php';
require_role('customer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request. Please try again.');
    redirect('/customer/orders/create.php');
}

$user = current_user();
$pdo = Database::connect();

$orderType   = $_POST['order_type'] ?? 'custom';
$itemRefId   = !empty($_POST['item_ref_id']) ? (int) $_POST['item_ref_id'] : null;
$customName  = trim($_POST['item_name'] ?? '');
$size        = trim($_POST['size'] ?? '');
$color       = trim($_POST['color'] ?? '');
$quantity    = max(1, (int) ($_POST['quantity'] ?? 1));
$notes       = trim($_POST['notes'] ?? '');
$address     = trim($_POST['delivery_address'] ?? '');
$lat         = $_POST['delivery_lat'] !== '' ? (float) $_POST['delivery_lat'] : null;
$lng         = $_POST['delivery_lng'] !== '' ? (float) $_POST['delivery_lng'] : null;

// Tarpaulin / event printing options (only present when the selected service requires them)
$eventType     = trim($_POST['event_type'] ?? '') ?: null;
$tarpSizePreset = trim($_POST['tarp_size_preset'] ?? '');
$tarpSizeCustom = trim($_POST['tarp_size_custom'] ?? '');
$tarpSize = $tarpSizePreset === 'custom' ? $tarpSizeCustom : $tarpSizePreset;
$designSource = $_POST['design_source'] ?? 'upload';
$templateId = !empty($_POST['template_id']) ? (int) $_POST['template_id'] : null;

// If this order came through the tarp options panel, the tarp size takes over the generic size field
if ($eventType !== null && $tarpSize !== '') {
    $size = $tarpSize;
}

if (!in_array($orderType, ['product', 'service', 'package', 'custom'], true)) {
    set_flash('error', 'Invalid order type.');
    redirect('/customer/orders/create.php');
}

// Resolve item name + unit price
$itemName = $customName ?: 'Custom Request';
$unitPrice = 0;

if ($orderType === 'product' && $itemRefId) {
    $stmt = $pdo->prepare("SELECT name, base_price FROM products WHERE product_id = ?");
    $stmt->execute([$itemRefId]);
    if ($row = $stmt->fetch()) { $itemName = $row['name']; $unitPrice = (float) $row['base_price']; }
} elseif ($orderType === 'service' && $itemRefId) {
    $stmt = $pdo->prepare("SELECT name, price FROM services WHERE service_id = ?");
    $stmt->execute([$itemRefId]);
    if ($row = $stmt->fetch()) { $itemName = $row['name']; $unitPrice = (float) $row['price']; }
} elseif ($orderType === 'package' && $itemRefId) {
    $stmt = $pdo->prepare("SELECT name, price FROM packages WHERE package_id = ?");
    $stmt->execute([$itemRefId]);
    if ($row = $stmt->fetch()) { $itemName = $row['name']; $unitPrice = (float) $row['price']; }
}

$subtotal = $unitPrice * $quantity;

try {
    $pdo->beginTransaction();

    $orderCode = generate_order_code($pdo);

    $stmt = $pdo->prepare(
        "INSERT INTO orders (order_code, customer_id, order_type, event_type, status, total_amount, notes, delivery_address, delivery_lat, delivery_lng)
         VALUES (?, ?, ?, ?, 'pending_review', ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$orderCode, $user['user_id'], $orderType, $eventType, $subtotal, $notes, $address ?: null, $lat, $lng]);
    $orderId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO order_items (order_id, item_type, item_ref_id, item_name, size, color, quantity, unit_price, subtotal)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([$orderId, $orderType, $itemRefId, $itemName, $size ?: null, $color ?: null, $quantity, $unitPrice, $subtotal]);

    $pdo->prepare(
        "INSERT INTO order_status_history (order_id, status, notes, changed_by) VALUES (?, 'pending_review', 'Order submitted by customer.', ?)"
    )->execute([$orderId, $user['user_id']]);

    // Generate a tracking QR code for this order
    $qrUrl = generate_order_qr_url($orderCode);
    $pdo->prepare("UPDATE orders SET qr_code_url = ? WHERE order_id = ?")->execute([$qrUrl, $orderId]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Order creation failed: ' . $e->getMessage());
    set_flash('error', 'We could not process your order. Please try again.');
    redirect('/customer/orders/create.php');
}

// --- Chosen shop design template (tarpaulin flow) ---
if ($designSource === 'template' && $templateId) {
    $tmpl = $pdo->prepare("SELECT name, image_url FROM design_templates WHERE template_id = ? AND status = 'active'");
    $tmpl->execute([$templateId]);
    if ($tmplRow = $tmpl->fetch()) {
        $pdo->prepare(
            "INSERT INTO design_uploads (order_id, customer_id, file_url, template_id, file_type, label, status)
             VALUES (?, ?, ?, ?, 'template', ?, 'approved')"
        )->execute([$orderId, $user['user_id'], $tmplRow['image_url'], $templateId, 'Shop design: ' . $tmplRow['name']]);
    }
}

// --- Handle design file uploads (best-effort; order already saved even if this fails) ---
if (!empty($_FILES['design_files']) && is_array($_FILES['design_files']['tmp_name'])) {
    $cloud = new CloudinaryUploader();
    $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];

    foreach ($_FILES['design_files']['tmp_name'] as $i => $tmpPath) {
        if ($_FILES['design_files']['error'][$i] !== UPLOAD_ERR_OK || $tmpPath === '') {
            continue;
        }
        $originalName = $_FILES['design_files']['name'][$i];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $size = $_FILES['design_files']['size'][$i];

        if (!in_array($ext, $allowedExt, true) || $size > 10 * 1024 * 1024) {
            continue; // skip invalid/oversized files silently; order still succeeds
        }

        try {
            $result = $cloud->upload($tmpPath, 'yvolution/designs/' . $orderCode);
            $pdo->prepare(
                "INSERT INTO design_uploads (order_id, customer_id, file_url, file_public_id, file_type, label)
                 VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([$orderId, $user['user_id'], $result['secure_url'], $result['public_id'], $ext, $originalName]);
        } catch (Exception $e) {
            error_log('Design upload failed: ' . $e->getMessage());
            // Continue — don't block the order over one failed upload
        }
    }
}

// --- Confirmation email (best-effort) ---
$mailer = new Mailer();
$body = email_template('Order Received!', "
    <p>Hi " . e($user['name']) . ",</p>
    <p>We've received your order <strong>{$orderCode}</strong> for <strong>" . e($itemName) . "</strong> (x{$quantity}).</p>
    <p>Our team will review it and send you a quotation shortly. You can track your order status anytime from your dashboard.</p>
");
$mailer->send($user['email'], $user['name'], 'Order Received — ' . $orderCode, $body);

log_audit($pdo, $user['user_id'], 'order_created', 'orders', $orderId, $orderCode);

set_flash('success', "Order {$orderCode} submitted! We'll notify you once it's reviewed.");
redirect('/customer/orders/view.php?id=' . $orderId);
