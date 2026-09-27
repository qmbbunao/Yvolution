<?php
require_once __DIR__ . '/../config/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Your cart could not be updated. Please try again.');
    redirect('/public/cart.php');
}

$action = $_POST['action'] ?? '';
$cart = $_SESSION['cart'] ?? [];
$returnTo = trim($_POST['return_to'] ?? '');
$safeReturn = str_starts_with($returnTo, '/public/') && !str_contains($returnTo, '://') ? $returnTo : '/public/cart.php';

if ($action === 'add') {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
    $stmt = Database::connect()->prepare(
        "SELECT product_id, name, base_price, image_url, available_sizes, available_colors, stock_qty
         FROM products WHERE product_id = ? AND status = 'active' LIMIT 1"
    );
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product || (int) $product['stock_qty'] < 1) {
        set_flash('error', 'This product is unavailable or out of stock.');
        redirect($safeReturn);
    }

    $size = trim($_POST['size'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $sizes = array_filter(array_map('trim', explode(',', $product['available_sizes'] ?? '')));
    $colors = array_filter(array_map('trim', explode(',', $product['available_colors'] ?? '')));
    if (($sizes && ($size === '' || !in_array($size, $sizes, true))) || ($colors && ($color === '' || !in_array($color, $colors, true)))) {
        set_flash('error', 'Choose an available size and color before adding this product.');
        redirect($safeReturn);
    }

    $key = hash('sha256', $productId . "\0" . $size . "\0" . $color);
    $quantity += (int) ($cart[$key]['quantity'] ?? 0);
    if ($quantity > (int) $product['stock_qty']) {
        set_flash('error', 'The requested quantity exceeds available stock.');
        redirect($safeReturn);
    }

    $cart[$key] = [
        'product_id' => $productId,
        'name' => $product['name'],
        'image_url' => $product['image_url'],
        'price' => (float) $product['base_price'],
        'size' => $size,
        'color' => $color,
        'quantity' => $quantity,
    ];
    $_SESSION['cart'] = $cart;
    set_flash('success', $product['name'] . ' added to your cart.');
    redirect($safeReturn);
}

$key = (string) ($_POST['key'] ?? '');
if (!isset($cart[$key])) {
    set_flash('error', 'That cart item could not be found.');
    redirect('/public/cart.php');
}

if ($action === 'remove') {
    unset($cart[$key]);
    $_SESSION['cart'] = $cart;
    set_flash('success', 'Item removed from your cart.');
} elseif ($action === 'update') {
    $quantity = (int) ($_POST['quantity'] ?? 1);
    if ($quantity < 1) {
        unset($cart[$key]);
    } else {
        $stmt = Database::connect()->prepare('SELECT stock_qty FROM products WHERE product_id = ? AND status = \'active\'');
        $stmt->execute([(int) $cart[$key]['product_id']]);
        $stock = (int) $stmt->fetchColumn();
        if ($stock < 1 || $quantity > $stock) {
            set_flash('error', 'The requested quantity is not currently available.');
            redirect('/public/cart.php');
        }
        $cart[$key]['quantity'] = $quantity;
    }
    $_SESSION['cart'] = $cart;
    set_flash('success', 'Cart quantity updated.');
} else {
    redirect('/public/cart.php');
}

redirect('/public/cart.php');