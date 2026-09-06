<?php
require_once __DIR__ . '/../config/app.php';

$pdo = Database::connect();
$query = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['category'] ?? 0);

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
$sql .= ' ORDER BY p.is_featured DESC, p.name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categoryStmt = $pdo->query(
    "SELECT category_id, name FROM categories WHERE type = 'product' AND status = 'active' ORDER BY name"
);
$categories = $categoryStmt->fetchAll();
$pageTitle = ($query !== '' ? 'Search: ' . $query : 'Browse Products') . ' — Yvolution Custom Apparel';
include __DIR__ . '/../includes/header.php';
?>

<main class="container" style="padding:64px 24px 90px;">
    <div style="display:flex;justify-content:space-between;align-items:end;gap:20px;flex-wrap:wrap;margin-bottom:30px;">
        <div>
            <p class="text-muted-tone" style="margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;">Catalog</p>
            <h1><?= $query !== '' ? 'Search Results' : 'Browse Products' ?></h1>
            <?php if ($query !== ''): ?><p class="text-secondary" style="margin:0;">Showing matches for “<?= e($query) ?>”.</p><?php endif; ?>
        </div>
        <form class="catalog-results-filter" method="GET">
            <select class="form-control" name="category" aria-label="Filter by category">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['category_id'] ?>" <?= $categoryId === (int) $category['category_id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-accent" type="submit">Search</button>
        </form>
    </div>

    <?php if (empty($products)): ?>
        <div class="card"><div class="card-body" style="text-align:center;padding:56px 24px;">
            <h2>No products found</h2>
            <p class="text-secondary">Try another product name or category.</p>
        </div></div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <a href="<?= BASE_URL ?>/public/product_details.php?id=<?= (int) $product['product_id'] ?>" class="product-card">
                    <div class="product-card-image">
                        <?php if ($product['is_featured']): ?><span class="badge badge-progress">Featured</span><?php endif; ?>
                        <img src="<?= e($product['image_url'] ?: BASE_URL . '/assets/images/products/placeholder.jpg') ?>" alt="<?= e($product['name']) ?>">
                    </div>
                    <div class="product-card-body">
                        <div class="product-card-name"><?= e($product['name']) ?></div>
                        <div class="product-card-category"><?= e($product['category_name'] ?: 'Uncategorized') ?></div>
                        <div class="product-card-desc"><?= e(mb_strimwidth($product['description'] ?? '', 0, 75, '...')) ?></div>
                        <div class="product-card-footer">
                            <span class="product-card-price"><?= money($product['base_price']) ?></span>
                            <span class="product-card-cta">Details</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
