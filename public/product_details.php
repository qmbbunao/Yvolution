<?php
require_once __DIR__ . '/../config/app.php';

$productId = (int) ($_GET['id'] ?? 0);
$pdo = Database::connect();

$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p
                        LEFT JOIN categories c ON p.category_id = c.category_id
                        WHERE p.product_id = ? AND p.status = 'active'");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product Not Found';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="container" style="padding:80px 24px;text-align:center;"><h1>Product Not Found</h1>
          <a href="' . BASE_URL . '/public/index.php" class="btn btn-dark">Back to Catalog</a></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$galleryStmt = $pdo->prepare(
    "SELECT image_url, is_primary, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order, image_id"
);
$galleryStmt->execute([$productId]);
$galleryImages = $galleryStmt->fetchAll();

if (empty($galleryImages) && !empty($product['image_url'])) {
    $galleryImages = [[
        'image_url' => $product['image_url'],
        'is_primary' => 1,
        'sort_order' => 1,
    ]];
}

$related = $pdo->prepare(
    "SELECT * FROM products WHERE category_id <=> ? AND product_id != ? AND status='active' LIMIT 4"
);
$related->execute([$product['category_id'], $productId]);
$relatedProducts = $related->fetchAll();

$avgRatingStmt = $pdo->prepare(
    "SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_reviews
     FROM feedback WHERE product_id = ?"
);
$avgRatingStmt->execute([$productId]);
$ratingSummary = $avgRatingStmt->fetch() ?: ['avg_rating' => 0, 'total_reviews' => 0];

$reviewsStmt = $pdo->prepare(
    "SELECT f.feedback_id, f.rating, f.comments, f.created_at,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS customer_name
     FROM feedback f
     LEFT JOIN users u ON u.user_id = f.customer_id
     WHERE f.product_id = ?
     ORDER BY f.created_at DESC"
);
$reviewsStmt->execute([$productId]);
$reviews = $reviewsStmt->fetchAll();

$userReview = null;
$canReview = false;
if (is_logged_in() && has_role('customer')) {
    $user = current_user();
    $mine = $pdo->prepare(
        "SELECT feedback_id, rating, comments FROM feedback WHERE product_id = ? AND customer_id = ?"
    );
    $mine->execute([$productId, $user['user_id']]);
    $userReview = $mine->fetch() ?: null;

    $orderedStmt = $pdo->prepare(
        "SELECT 1
         FROM orders o
         INNER JOIN order_items oi ON oi.order_id = o.order_id
         WHERE o.customer_id = ?
           AND oi.item_type = 'product'
           AND oi.item_ref_id = ?
           AND o.status != 'cancelled'
         LIMIT 1"
    );
    $orderedStmt->execute([$user['user_id'], $productId]);
    $canReview = (bool) $orderedStmt->fetchColumn();
}

$pageTitle = $product['name'] . ' — Yvolution Custom Apparel';
include __DIR__ . '/../includes/header.php';

$sizes = array_filter(array_map('trim', explode(',', $product['available_sizes'] ?? '')));
$colors = array_filter(array_map('trim', explode(',', $product['available_colors'] ?? '')));
$primaryImage = $galleryImages[0]['image_url'] ?? ($product['image_url'] ?: BASE_URL . '/assets/images/products/placeholder.jpg');
$avgRating = (float) ($ratingSummary['avg_rating'] ?? 0);
$totalReviews = (int) ($ratingSummary['total_reviews'] ?? 0);
$selectedRating = (int) ($userReview['rating'] ?? 5);
?>

<div class="container" style="padding:50px 24px;">
    <a href="<?= BASE_URL ?>/public/index.php#products" class="text-link" style="font-size:13px;">&larr; Back to Catalog</a>

    <div class="product-detail-layout reveal">
        <div class="product-gallery-layout">
            <div class="product-gallery-main">
                <img id="productMainImage" src="<?= e($primaryImage) ?>" alt="<?= e($product['name']) ?>">
            </div>

            <?php if (!empty($galleryImages)): ?>
                <div class="product-gallery-thumbs">
                    <?php foreach (array_slice($galleryImages, 0, 2) as $galleryImage): ?>
                        <button type="button" class="gallery-thumb-button" data-image="<?= e($galleryImage['image_url']) ?>">
                            <img src="<?= e($galleryImage['image_url']) ?>" alt="<?= e($product['name']) ?> thumbnail">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="product-detail-copy">
            <?php if ($product['category_name']): ?>
                <span class="badge badge-progress"><?= e($product['category_name']) ?></span>
            <?php endif; ?>
            <h1 style="margin-top:12px;"><?= e($product['name']) ?></h1>
            <p style="font-size:26px;color:var(--c-accent);font-family:var(--f-display); margin-bottom:18px;"><?= money($product['base_price']) ?></p>
            <p class="text-secondary" style="line-height:1.7;"><?= nl2br(e($product['description'])) ?></p>

            <?php if (!empty($sizes)): ?>
                <div style="margin-top:20px;">
                    <label style="font-size:12px;text-transform:uppercase;font-weight:700;color:#333;">Available Sizes</label>
                    <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
                        <?php foreach ($sizes as $size): ?>
                            <span class="badge badge-pending"><?= e($size) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($colors)): ?>
                <div style="margin-top:16px;">
                    <label style="font-size:12px;text-transform:uppercase;font-weight:700;color:#333;">Available Colors</label>
                    <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
                        <?php foreach ($colors as $color): ?>
                            <span class="badge badge-progress"><?= e($color) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <p style="margin-top:16px;font-size:13px;color:<?= $product['stock_qty'] > 0 ? '#1a7a44' : '#b3261e' ?>;">
                <?= $product['stock_qty'] > 0 ? 'In stock — ' . (int) $product['stock_qty'] . ' available' : 'Currently out of stock' ?>
            </p>

            <?php if ($totalReviews > 0): ?>
                <div class="product-rating-inline" aria-label="Average rating <?= number_format($avgRating, 1) ?> out of 5">
                    <span class="star-rating-display">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= (int) round($avgRating) ? 'filled' : '' ?>">★</span>
                        <?php endfor; ?>
                    </span>
                    <span class="rating-number"><?= number_format($avgRating, 1) ?></span>
                    <span class="review-count-inline">(<?= $totalReviews ?> review<?= $totalReviews === 1 ? '' : 's' ?>)</span>
                </div>
            <?php endif; ?>

            <?php if (is_logged_in() && has_role('customer')): ?>
                <a href="<?= BASE_URL ?>/customer/orders/create.php?product_id=<?= (int) $product['product_id'] ?>" class="btn btn-accent" style="margin-top:24px;">Order This Product</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-accent" style="margin-top:24px;">Log In to Order</a>
            <?php endif; ?>
        </div>
    </div>

    <section class="review-section reveal" id="reviews">
        <?php if ($msg = get_flash('success')): ?>
            <div class="alert alert-success"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = get_flash('error')): ?>
            <div class="alert alert-error"><?= e($msg) ?></div>
        <?php endif; ?>

        <div class="review-card">
            <div class="review-summary">
                <div>
                    <h2>Customer Reviews</h2>
                    <div class="star-rating-display" aria-label="Average rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= (int) round($avgRating) ? 'filled' : '' ?>">★</span>
                        <?php endfor; ?>
                        <span class="rating-number"><?= number_format($avgRating, 1) ?> / 5</span>
                    </div>
                    <p class="review-count"><?= $totalReviews ?> review<?= $totalReviews === 1 ? '' : 's' ?></p>
                </div>
            </div>

            <?php if ($canReview): ?>
                <form method="POST" action="<?= BASE_URL ?>/public/product_review_submit.php" class="review-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">
                    <label>Your rating</label>
                    <div class="star-picker" role="radiogroup" aria-label="Pick a rating">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" id="rating<?= $i ?>" value="<?= $i ?>" <?= $selectedRating === $i ? 'checked' : '' ?>>
                            <label for="rating<?= $i ?>" title="<?= $i ?> star<?= $i === 1 ? '' : 's' ?>">★</label>
                        <?php endfor; ?>
                    </div>
                    <label for="comments">Your comment</label>
                    <textarea class="form-control" id="comments" name="comments" rows="4" required placeholder="Share your experience with this product..."><?= e($userReview['comments'] ?? '') ?></textarea>
                    <button type="submit" class="btn btn-accent" style="margin-top:12px;">
                        <?= $userReview ? 'Update Review' : 'Submit Review' ?>
                    </button>
                </form>
            <?php elseif (is_logged_in() && has_role('customer')): ?>
                <p class="review-cta">
                    Order this product first to leave a rating and comment.
                    <a href="<?= BASE_URL ?>/customer/orders/create.php?product_id=<?= (int) $product['product_id'] ?>" class="text-link">Place an order</a>
                </p>
            <?php else: ?>
                <p class="review-cta">
                    <a href="<?= BASE_URL ?>/auth/login.php" class="text-link">Log in</a> and order this product to leave a rating and comment.
                </p>
            <?php endif; ?>

            <?php if (!empty($reviews)): ?>
                <div class="review-list">
                    <?php foreach ($reviews as $review): ?>
                        <article class="review-item">
                            <div class="review-item-header">
                                <strong><?= e(trim($review['customer_name'] ?? '') ?: 'Customer') ?></strong>
                                <span class="star-rating-display small">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star <?= $i <= (int) $review['rating'] ? 'filled' : '' ?>">★</span>
                                    <?php endfor; ?>
                                </span>
                            </div>
                            <p><?= nl2br(e($review['comments'] ?? '')) ?></p>
                            <time datetime="<?= e($review['created_at']) ?>">
                                <?= date('M j, Y', strtotime($review['created_at'])) ?>
                            </time>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="review-empty">No reviews yet. Be the first to share your experience.</p>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($relatedProducts)): ?>
        <h2 class="reveal" style="margin-top:80px;">You Might Also Like</h2>
        <div class="product-grid" style="margin-top:24px;">
            <?php foreach ($relatedProducts as $rp): ?>
                <a href="<?= BASE_URL ?>/public/product_details.php?id=<?= (int) $rp['product_id'] ?>" class="product-card reveal">
                    <div class="product-card-image">
                        <img src="<?= e($rp['image_url'] ?: BASE_URL . '/assets/images/products/placeholder.jpg') ?>" alt="<?= e($rp['name']) ?>">
                    </div>
                    <div class="product-card-body">
                        <div class="product-card-name"><?= e($rp['name']) ?></div>
                        <div class="product-card-footer">
                            <span class="product-card-price"><?= money($rp['base_price']) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.gallery-thumb-button').forEach((button) => {
    button.addEventListener('click', () => {
        const mainImage = document.getElementById('productMainImage');
        mainImage.src = button.dataset.image;
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
