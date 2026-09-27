<?php
/**
 * Repair legacy product and gallery rows whose IDs were stored as zero.
 * Run once from the project root: php database/migration_repair_product_detail_ids.php
 */
require_once __DIR__ . '/../config/app.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this migration from the command line.');
}

$pdo = Database::connect();
$productsPrimaryKey = (bool) $pdo->query("SHOW INDEX FROM products WHERE Key_name = 'PRIMARY'")->fetch();
$productsAutoIncrement = stripos(
    (string) $pdo->query("SHOW COLUMNS FROM products LIKE 'product_id'")->fetch()['Extra'],
    'auto_increment'
) !== false;
$imagesPrimaryKey = (bool) $pdo->query("SHOW INDEX FROM product_images WHERE Key_name = 'PRIMARY'")->fetch();
$imagesAutoIncrement = stripos(
    (string) $pdo->query("SHOW COLUMNS FROM product_images LIKE 'image_id'")->fetch()['Extra'],
    'auto_increment'
) !== false;

if ($productsPrimaryKey && $productsAutoIncrement && $imagesPrimaryKey && $imagesAutoIncrement) {
    echo "Product and gallery IDs are already repaired.\n";
    exit(0);
}

foreach (['products', 'product_images'] as $table) {
    $backup = $table . '_before_detail_id_repair';
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$backup}` LIKE `{$table}`");
    if ((int) $pdo->query("SELECT COUNT(*) FROM `{$backup}`")->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO `{$backup}` SELECT * FROM `{$table}`");
    }
}

$products = $pdo->query('SELECT product_id, name, image_url, created_at FROM products ORDER BY created_at, name')->fetchAll();
$images = $pdo->query('SELECT image_id, product_id, image_url, created_at FROM product_images ORDER BY created_at, image_url')->fetchAll();

if (!$products || !$images) {
    throw new RuntimeException('Expected product and gallery rows; no records were changed.');
}
foreach ($products as $product) {
    if ((int) $product['product_id'] !== 0) {
        throw new RuntimeException('Unexpected nonzero product IDs found; migration stopped.');
    }
}
foreach ($images as $image) {
    if ((int) $image['image_id'] !== 0 || (int) $image['product_id'] !== 0) {
        throw new RuntimeException('Unexpected gallery IDs or product references found; migration stopped.');
    }
}
if (count(array_unique(array_column($products, 'name'))) !== count($products)) {
    throw new RuntimeException('Duplicate product names prevent safe product ID repair.');
}
if (count(array_unique(array_column($images, 'image_url'))) !== count($images)) {
    throw new RuntimeException('Duplicate gallery URLs prevent safe image ID repair.');
}

$productIdByName = [];
$productIdByCreatedAt = [];
foreach ($products as $index => $product) {
    $newId = $index + 1;
    $productIdByName[$product['name']] = $newId;
    $productIdByCreatedAt[$product['created_at']] = $newId;
}

$imageProductByUrl = [];
foreach ($products as $product) {
    if ($product['image_url']) {
        $imageProductByUrl[$product['image_url']] = $productIdByName[$product['name']];
    }
}

try {
    $pdo->beginTransaction();
    foreach ($products as $index => $product) {
        $newId = $index + 1;
        $temporaryId = -$newId;
        $stmt = $pdo->prepare('UPDATE products SET product_id = ? WHERE name = ? AND created_at = ? AND product_id = 0');
        $stmt->execute([$temporaryId, $product['name'], $product['created_at']]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Could not isolate a product row using its name and creation time.');
        }
    }
    foreach ($products as $index => $product) {
        $newId = $index + 1;
        $pdo->prepare('UPDATE products SET product_id = ? WHERE product_id = ?')
            ->execute([$newId, -$newId]);
    }

    foreach ($images as $index => $image) {
        $productId = $imageProductByUrl[$image['image_url']] ?? $productIdByCreatedAt[$image['created_at']] ?? null;
        if ($productId === null) {
            throw new RuntimeException('Could not associate a gallery image with a product.');
        }
        $temporaryId = -($index + 1);
        $stmt = $pdo->prepare('UPDATE product_images SET image_id = ?, product_id = ? WHERE image_url = ? AND image_id = 0');
        $stmt->execute([$temporaryId, $productId, $image['image_url']]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Could not isolate a gallery image by URL.');
        }
    }

    foreach ($images as $index => $image) {
        $temporaryId = -($index + 1);
        $pdo->prepare('UPDATE product_images SET image_id = ? WHERE image_id = ?')
            ->execute([$index + 1, $temporaryId]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$pdo->exec('ALTER TABLE products MODIFY product_id INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (product_id)');
$pdo->exec('ALTER TABLE product_images MODIFY image_id INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (image_id)');

echo "Repaired " . count($products) . " products and " . count($images) . " gallery images.\n";
echo "Backups: products_before_detail_id_repair, product_images_before_detail_id_repair\n";