<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/cloudinary.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request.');
    redirect('/admin/products/index.php');
}

$productId = (int) ($_POST['product_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$categoryId = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
$description = trim($_POST['description'] ?? '');
$basePrice = (float) ($_POST['base_price'] ?? 0);
$stockQty = (int) ($_POST['stock_qty'] ?? 0);
$sizes = trim($_POST['available_sizes'] ?? '');
$colors = trim($_POST['available_colors'] ?? '');
$isFeatured = !empty($_POST['is_featured']) ? 1 : 0;
$status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';
$existingPrimaryImageId = !empty($_POST['existing_primary_image']) ? (int) $_POST['existing_primary_image'] : 0;

if ($name === '' || $basePrice < 0) {
    set_flash('error', 'Please provide a valid name and price.');
    redirect('/admin/products/form.php' . ($productId ? '?id=' . $productId : ''));
}

$imageUrl = null;
if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    try {
        $cloud = new CloudinaryUploader();
        $result = $cloud->upload($_FILES['image']['tmp_name'], 'yvolution/products');
        $imageUrl = $result['secure_url'];
    } catch (Exception $e) {
        error_log('Product image upload failed: ' . $e->getMessage());
        set_flash('error', 'Product saved, but image upload failed: ' . $e->getMessage());
    }
} elseif (!empty($_FILES['image']['error']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    set_flash('error', 'Product saved, but image was not uploaded: ' . upload_error_message($_FILES['image']['error']));
}

$galleryUrls = [];
$galleryFiles = $_FILES['gallery_images']['tmp_name'] ?? [];
$galleryErrors = $_FILES['gallery_images']['error'] ?? [];
if (!empty($galleryFiles)) {
    try {
        $cloud = new CloudinaryUploader();
        foreach ($galleryFiles as $index => $tmpPath) {
            if (!is_string($tmpPath) || $tmpPath === '' || ($galleryErrors[$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $result = $cloud->upload($tmpPath, 'yvolution/products');
            $galleryUrls[] = $result['secure_url'];
        }
    } catch (Exception $e) {
        error_log('Gallery upload failed: ' . $e->getMessage());
        set_flash('error', 'Some additional product images could not be uploaded.');
    }
}

$galleryUrls = array_values(array_unique(array_filter($galleryUrls)));
$primaryGalleryUrl = $imageUrl ?: ($galleryUrls[0] ?? null);

if ($productId) {
    if ($imageUrl) {
        $pdo->prepare(
            "UPDATE products SET category_id=?, name=?, description=?, base_price=?, available_sizes=?, available_colors=?, image_url=?, stock_qty=?, status=?, is_featured=? WHERE product_id=?"
        )->execute([$categoryId, $name, $description, $basePrice, $sizes, $colors, $imageUrl, $stockQty, $status, $isFeatured, $productId]);
    } else {
        $pdo->prepare(
            "UPDATE products SET category_id=?, name=?, description=?, base_price=?, available_sizes=?, available_colors=?, stock_qty=?, status=?, is_featured=? WHERE product_id=?"
        )->execute([$categoryId, $name, $description, $basePrice, $sizes, $colors, $stockQty, $status, $isFeatured, $productId]);
    }

    $galleryStmt = $pdo->prepare(
        "SELECT image_id, image_url, is_primary, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order, image_id"
    );
    $galleryStmt->execute([$productId]);
    $existingGallery = $galleryStmt->fetchAll();

    $existingUrls = array_column($existingGallery, 'image_url');
    $sortOrder = count($existingGallery) + 1;
    foreach ($galleryUrls as $galleryUrl) {
        if (!in_array($galleryUrl, $existingUrls, true)) {
            $pdo->prepare(
                "INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)"
            )->execute([$productId, $galleryUrl, 0, $sortOrder]);
            $existingUrls[] = $galleryUrl;
            $sortOrder++;
        }
    }

    if ($existingPrimaryImageId > 0) {
        $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?")->execute([$productId]);
        $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE product_id = ? AND image_id = ?")
            ->execute([$productId, $existingPrimaryImageId]);

        $primaryStmt = $pdo->prepare(
            "SELECT image_url FROM product_images WHERE product_id = ? AND image_id = ? LIMIT 1"
        );
        $primaryStmt->execute([$productId, $existingPrimaryImageId]);
        $primaryRow = $primaryStmt->fetch();
        if ($primaryRow) {
            $imageUrl = $primaryRow['image_url'];
            $pdo->prepare("UPDATE products SET image_url = ? WHERE product_id = ?")->execute([$imageUrl, $productId]);
        }
    } elseif ($primaryGalleryUrl) {
        $imageUrl = $primaryGalleryUrl;
        $pdo->prepare("UPDATE products SET image_url = ? WHERE product_id = ?")->execute([$primaryGalleryUrl, $productId]);
        $productImagesStmt = $pdo->prepare(
            "SELECT image_id FROM product_images WHERE product_id = ? AND image_url = ? LIMIT 1"
        );
        $productImagesStmt->execute([$productId, $primaryGalleryUrl]);
        $productImageRow = $productImagesStmt->fetch();
        if ($productImageRow) {
            $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE product_id = ? AND image_id = ?")
                ->execute([$productId, $productImageRow['image_id']]);
        }
    }

    log_audit($pdo, $admin['user_id'], 'product_updated', 'products', $productId, $name);
    set_flash('success', 'Product updated.');
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO products (category_id, name, description, base_price, available_sizes, available_colors, image_url, stock_qty, status, is_featured, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$categoryId, $name, $description, $basePrice, $sizes, $colors, $primaryGalleryUrl, $stockQty, $status, $isFeatured, $admin['user_id']]);
    $productId = (int) $pdo->lastInsertId();

    $sortOrder = 1;
    foreach ($galleryUrls as $galleryUrl) {
        $pdo->prepare(
            "INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)"
        )->execute([$productId, $galleryUrl, $sortOrder === 1 ? 1 : 0, $sortOrder]);
        $sortOrder++;
    }

    if ($imageUrl) {
        $pdo->prepare(
            "INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)"
        )->execute([$productId, $imageUrl, 1, 1]);
    }

    log_audit($pdo, $admin['user_id'], 'product_created', 'products', $productId, $name);
    set_flash('success', 'Product added.');
}

redirect('/admin/products/index.php');
