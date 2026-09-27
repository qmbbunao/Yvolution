<?php
require_once __DIR__ . '/../config/app.php';

$cart = $_SESSION['cart'] ?? [];
$itemCount = cart_item_count();
$subtotal = 0.0;
foreach ($cart as $item) {
    $subtotal += (float) $item['price'] * (int) $item['quantity'];
}
$pageTitle = 'Your Cart — Yvolution Custom Apparel';
include __DIR__ . '/../includes/header.php';
?>

<main class="container cart-page">
    <p class="section-kicker">YOUR SELECTION</p>
    <h1>Your Cart <span class="cart-page-count"><?= $itemCount ?> item<?= $itemCount === 1 ? '' : 's' ?></span></h1>

    <?php if ($message = get_flash('success')): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
    <?php if ($message = get_flash('error')): ?><div class="alert alert-error"><?= e($message) ?></div><?php endif; ?>

    <?php if (!$cart): ?>
        <section class="cart-empty-state">
            <h2>Your cart is empty</h2>
            <p class="text-secondary">Browse the catalog and add products you want to order.</p>
            <a href="<?= BASE_URL ?>/public/search.php" class="btn btn-accent">Continue Shopping</a>
        </section>
    <?php else: ?>
        <div class="cart-layout">
            <div class="cart-items-list">
                <?php foreach ($cart as $key => $item): ?>
                    <article class="cart-item">
                        <a href="<?= BASE_URL ?>/public/product_details.php?id=<?= (int) $item['product_id'] ?>" class="cart-item-image">
                            <img src="<?= e($item['image_url'] ?: BASE_URL . '/assets/images/products/placeholder.jpg') ?>" alt="<?= e($item['name']) ?>">
                        </a>
                        <div class="cart-item-info">
                            <a href="<?= BASE_URL ?>/public/product_details.php?id=<?= (int) $item['product_id'] ?>" class="cart-item-name"><?= e($item['name']) ?></a>
                            <?php if (!empty($item['size']) || !empty($item['color'])): ?><p class="cart-item-options"><?php if (!empty($item['size'])): ?>Size: <?= e($item['size']) ?><?php endif; ?><?php if (!empty($item['size']) && !empty($item['color'])): ?> · <?php endif; ?><?php if (!empty($item['color'])): ?>Color: <?= e($item['color']) ?><?php endif; ?></p><?php endif; ?>
                            <p class="cart-item-price"><?= money((float) $item['price']) ?> each</p>
                            <div class="cart-item-controls">
                                <form method="POST" action="<?= BASE_URL ?>/public/cart_action.php" class="cart-quantity-form">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="key" value="<?= e($key) ?>">
                                    <button type="submit" name="quantity" value="<?= max(1, (int) $item['quantity'] - 1) ?>" aria-label="Decrease quantity" <?= (int) $item['quantity'] <= 1 ? 'disabled' : '' ?>>−</button>
                                    <span><?= (int) $item['quantity'] ?></span>
                                    <button type="submit" name="quantity" value="<?= (int) $item['quantity'] + 1 ?>" aria-label="Increase quantity">+</button>
                                </form>
                                <form method="POST" action="<?= BASE_URL ?>/public/cart_action.php">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="key" value="<?= e($key) ?>">
                                    <button type="submit" class="cart-remove-button">Remove</button>
                                </form>
                            </div>
                        </div>
                        <strong class="cart-line-total"><?= money((float) $item['price'] * (int) $item['quantity']) ?></strong>
                    </article>
                <?php endforeach; ?>
            </div>
            <aside class="cart-summary">
                <h2>Order Summary</h2>
                <div><span>Total items</span><strong><?= $itemCount ?></strong></div>
                <div class="cart-summary-total"><span>Subtotal</span><strong><?= money($subtotal) ?></strong></div>
                <p class="text-muted-tone">Final pricing and delivery details are confirmed with your order quotation.</p>
                <a href="<?= BASE_URL ?>/customer/orders/create.php?cart=1" class="btn btn-accent btn-block">Proceed to Checkout</a>
                <a href="<?= BASE_URL ?>/public/search.php" class="btn btn-outline btn-block">Continue Shopping</a>
            </aside>
        </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>