<?php
require_once __DIR__ . '/../config/app.php';

$pdo = Database::connect();
$query = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['category'] ?? 0);
$minPrice = is_numeric($_GET['min_price'] ?? null) ? max(0, (float) $_GET['min_price']) : null;
$maxPrice = is_numeric($_GET['max_price'] ?? null) ? max(0, (float) $_GET['max_price']) : null;
$availability = $_GET['availability'] ?? '';
$selectedSize = trim($_GET['size'] ?? '');
$selectedColor = trim($_GET['color'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$sql = "SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.category_id = p.category_id
        WHERE p.status = 'active'";
$params = [];

if ($query !== '') {
    $sql .= " AND (p.name LIKE :name_query OR p.description LIKE :description_query OR c.name LIKE :category_query)";
    $likeQuery = '%' . $query . '%';
    $params[':name_query'] = $likeQuery;
    $params[':description_query'] = $likeQuery;
    $params[':category_query'] = $likeQuery;
}
if ($categoryId > 0) {
    $sql .= ' AND p.category_id = :category_id';
    $params[':category_id'] = $categoryId;
}
$sql .= ' ORDER BY p.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$allSizes = [];
$allColors = [];
foreach ($products as $product) {
    $allSizes = array_merge($allSizes, array_map('trim', explode(',', $product['available_sizes'] ?? '')));
    $allColors = array_merge($allColors, array_map('trim', explode(',', $product['available_colors'] ?? '')));
}
$allSizes = array_values(array_unique(array_filter($allSizes)));
$allColors = array_values(array_unique(array_filter($allColors)));

$products = array_values(array_filter($products, static function (array $product) use ($minPrice, $maxPrice, $availability, $selectedSize, $selectedColor): bool {
    $price = (float) $product['base_price'];
    if ($minPrice !== null && $price < $minPrice) return false;
    if ($maxPrice !== null && $price > $maxPrice) return false;
    if ($availability === 'in_stock' && (int) $product['stock_qty'] < 1) return false;
    if ($availability === 'out_of_stock' && (int) $product['stock_qty'] > 0) return false;
    $sizes = array_filter(array_map('trim', explode(',', $product['available_sizes'] ?? '')));
    $colors = array_filter(array_map('trim', explode(',', $product['available_colors'] ?? '')));
    if ($selectedSize !== '' && !in_array($selectedSize, $sizes, true)) return false;
    if ($selectedColor !== '' && !in_array($selectedColor, $colors, true)) return false;
    return true;
}));

$sortOrders = [
    'price_asc' => static fn (array $a, array $b): int => (float) $a['base_price'] <=> (float) $b['base_price'],
    'price_desc' => static fn (array $a, array $b): int => (float) $b['base_price'] <=> (float) $a['base_price'],
    'name_asc' => static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']),
    'name_desc' => static fn (array $a, array $b): int => strcasecmp($b['name'], $a['name']),
    'popular' => static fn (array $a, array $b): int => ((int) $b['is_featured'] <=> (int) $a['is_featured']) ?: (strtotime($b['created_at']) <=> strtotime($a['created_at'])),
    'newest' => static fn (array $a, array $b): int => strtotime($b['created_at']) <=> strtotime($a['created_at']),
];
if (!isset($sortOrders[$sort])) $sort = 'newest';
usort($products, $sortOrders[$sort]);

$categoryStmt = $pdo->query(
    "SELECT category_id, name FROM categories WHERE type = 'product' AND status = 'active' ORDER BY name"
);
$categories = $categoryStmt->fetchAll();
$pageTitle = ($query !== '' ? 'Search: ' . $query : 'All Products') . ' — Yvolution Custom Apparel';
include __DIR__ . '/../includes/header.php';
?>

<main class="container catalog-page" style="padding:64px 24px 90px;">
    <div class="catalog-heading">
        <div>
            <p class="text-muted-tone" style="margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;">Catalog</p>
            <h1><?= $query !== '' ? 'Search Results' : 'All Products' ?></h1>
            <?php if ($query !== ''): ?><p class="text-secondary" style="margin:0;">Showing matches for “<?= e($query) ?>”.</p><?php endif; ?>
        </div>
        <span class="text-secondary"><?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?></span>
    </div>

    <button type="button" class="btn btn-outline catalog-filter-toggle" id="catalogFilterToggle" aria-expanded="false">Filter Products</button>
    <div class="catalog-layout">
        <aside class="catalog-filter-sidebar" id="catalogFilters">
            <form method="GET" class="catalog-filter-form">
                <h2>Filters</h2>
                <label>Search
                    <input class="form-control" type="search" name="q" value="<?= e($query) ?>" placeholder="Product name or details">
                </label>
                <label>Category
                    <select class="form-control" name="category">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['category_id'] ?>" <?= $categoryId === (int) $category['category_id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="catalog-filter-row">
                    <label>Min price<input class="form-control" type="number" min="0" step="0.01" name="min_price" value="<?= e(isset($_GET['min_price']) ? (string) $_GET['min_price'] : '') ?>"></label>
                    <label>Max price<input class="form-control" type="number" min="0" step="0.01" name="max_price" value="<?= e(isset($_GET['max_price']) ? (string) $_GET['max_price'] : '') ?>"></label>
                </div>
                <label>Availability
                    <select class="form-control" name="availability">
                        <option value="">Any availability</option>
                        <option value="in_stock" <?= $availability === 'in_stock' ? 'selected' : '' ?>>In stock</option>
                        <option value="out_of_stock" <?= $availability === 'out_of_stock' ? 'selected' : '' ?>>Out of stock</option>
                    </select>
                </label>
                <?php if ($allSizes): ?><label>Size<select class="form-control" name="size"><option value="">Any size</option><?php foreach ($allSizes as $size): ?><option value="<?= e($size) ?>" <?= $selectedSize === $size ? 'selected' : '' ?>><?= e($size) ?></option><?php endforeach; ?></select></label><?php endif; ?>
                <?php if ($allColors): ?><label>Color<select class="form-control" name="color"><option value="">Any color</option><?php foreach ($allColors as $color): ?><option value="<?= e($color) ?>" <?= $selectedColor === $color ? 'selected' : '' ?>><?= e($color) ?></option><?php endforeach; ?></select></label><?php endif; ?>
                <label>Sort by
                    <select class="form-control" name="sort">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name: A-Z</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name: Z-A</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Featured</option>
                    </select>
                </label>
                <div class="catalog-filter-actions"><button class="btn btn-accent" type="submit">Apply Filters</button><a class="btn btn-outline" href="<?= BASE_URL ?>/public/search.php">Clear</a></div>
            </form>
        </aside>

        <?php if (empty($products)): ?>
            <div class="card catalog-empty"><div class="card-body" style="text-align:center;padding:56px 24px;">
                <h2>No products found</h2>
                <p class="text-secondary">Try adjusting your search or filters.</p>
                <a href="<?= BASE_URL ?>/public/search.php" class="btn btn-outline">Clear Filters</a>
            </div></div>
        <?php else: ?>
            <div class="product-grid catalog-product-grid">
            <?php foreach ($products as $product): ?>
                <article class="product-card">
                    <div class="product-card-image">
                        <?php if ($product['is_featured']): ?><span class="badge badge-progress">Featured</span><?php endif; ?>
                        <a href="<?= BASE_URL ?>/public/product_details.php?id=<?= (int) $product['product_id'] ?>"><img src="<?= e($product['image_url'] ?: BASE_URL . '/assets/images/products/placeholder.jpg') ?>" alt="<?= e($product['name']) ?>"></a>
                    </div>
                    <div class="product-card-body">
                        <a class="product-card-name" href="<?= BASE_URL ?>/public/product_details.php?id=<?= (int) $product['product_id'] ?>"><?= e($product['name']) ?></a>
                        <div class="product-card-category"><?= e($product['category_name'] ?: 'Uncategorized') ?></div>
                        <div class="product-card-desc"><?= e(mb_strimwidth($product['description'] ?? '', 0, 75, '...')) ?></div>
                        <div class="product-card-footer">
                            <span class="product-card-price"><?= money($product['base_price']) ?></span>
                            <span class="text-muted-tone" style="font-size:12px;"><?= (int) $product['stock_qty'] > 0 ? 'In stock' : 'Out of stock' ?></span>
                        </div>
                        <?php $cardSizes = array_filter(array_map('trim', explode(',', $product['available_sizes'] ?? ''))); $cardColors = array_filter(array_map('trim', explode(',', $product['available_colors'] ?? ''))); ?>
                        <div class="catalog-card-actions">
                            <a href="<?= BASE_URL ?>/public/product_details.php?id=<?= (int) $product['product_id'] ?>" class="btn btn-outline">View Details</a>
                            <form method="POST" action="<?= BASE_URL ?>/public/cart_action.php">
                                <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>"><input type="hidden" name="quantity" value="1"><input type="hidden" name="return_to" value="/public/search.php?<?= e(http_build_query($_GET)) ?>">
                                <?php if ($cardSizes): ?><label class="sr-only" for="card-size-<?= (int) $product['product_id'] ?>">Size for <?= e($product['name']) ?></label><select class="form-control" name="size" id="card-size-<?= (int) $product['product_id'] ?>" required><option value="">Size</option><?php foreach ($cardSizes as $size): ?><option value="<?= e($size) ?>"><?= e($size) ?></option><?php endforeach; ?></select><?php endif; ?>
                                <?php if ($cardColors): ?><label class="sr-only" for="card-color-<?= (int) $product['product_id'] ?>">Color for <?= e($product['name']) ?></label><select class="form-control" name="color" id="card-color-<?= (int) $product['product_id'] ?>" required><option value="">Color</option><?php foreach ($cardColors as $color): ?><option value="<?= e($color) ?>"><?= e($color) ?></option><?php endforeach; ?></select><?php endif; ?>
                                <button type="submit" class="btn btn-accent" <?= (int) $product['stock_qty'] < 1 ? 'disabled' : '' ?>>Add to Cart</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
document.getElementById('catalogFilterToggle')?.addEventListener('click', function () {
    const filters = document.getElementById('catalogFilters');
    const open = filters.classList.toggle('is-open');
    this.setAttribute('aria-expanded', open ? 'true' : 'false');
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
