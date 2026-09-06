<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();
$productId = (int) ($_GET['id'] ?? 0);
$product = null;
$productGallery = [];

if ($productId) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) {
        set_flash('error', 'Product not found.');
        redirect('/admin/products/index.php');
    }

    $galleryStmt = $pdo->prepare(
        "SELECT image_id, image_url, is_primary, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order, image_id"
    );
    $galleryStmt->execute([$productId]);
    $productGallery = $galleryStmt->fetchAll();
}

$categories = $pdo->query("SELECT * FROM categories WHERE type = 'product' ORDER BY name")->fetchAll();

$pageTitle = ($product ? 'Edit' : 'Add') . ' Product — Admin';
$activeNav = 'products';
include __DIR__ . '/../../includes/admin_header.php';
?>

<a href="<?= BASE_URL ?>/admin/products/index.php" class="text-link" style="font-size:13px;">&larr; Back to Products</a>
<h1 style="margin-top:12px;"><?= $product ? 'Edit Product' : 'Add Product' ?></h1>

<div class="card" style="max-width:740px;margin-top:20px;">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/admin/products/save.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) ($product['product_id'] ?? 0) ?>">

            <div class="form-group">
                <label for="name">Product Name</label>
                <input class="form-control" type="text" id="name" name="name" value="<?= e($product['name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="category_id">Category</label>
                <select class="form-control" id="category_id" name="category_id">
                    <option value="">— None —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['category_id'] ?>" <?= (int) ($product['category_id'] ?? 0) === (int) $c['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4"><?= e($product['description'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="base_price">Base Price (₱)</label>
                    <input class="form-control" type="number" step="0.01" id="base_price" name="base_price" value="<?= e((string) ($product['base_price'] ?? '0')) ?>" required>
                </div>
                <div class="form-group">
                    <label for="stock_qty">Stock Quantity</label>
                    <input class="form-control" type="number" id="stock_qty" name="stock_qty" value="<?= (int) ($product['stock_qty'] ?? 0) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="available_sizes">Available Sizes (comma-separated)</label>
                    <input class="form-control" type="text" id="available_sizes" name="available_sizes" value="<?= e($product['available_sizes'] ?? 'S,M,L,XL,XXL') ?>">
                </div>
                <div class="form-group">
                    <label for="available_colors">Available Colors (comma-separated)</label>
                    <input class="form-control" type="text" id="available_colors" name="available_colors" value="<?= e($product['available_colors'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="image">Primary Product Image</label>
                <?php if (!empty($product['image_url'])): ?>
                    <img src="<?= e($product['image_url']) ?>" class="thumb" style="width:80px;height:80px;margin-bottom:8px;">
                <?php endif; ?>
                <input class="form-control" type="file" id="image" name="image" accept="image/*">
                <p class="helper-text">Choose the main image here. After you add the first photo, more upload slots can appear below.</p>
            </div>

            <div class="form-group">
                <label>Add More Product Images</label>
                <p class="helper-text">Upload the first image to reveal another upload slot. You can keep adding as many images as you need.</p>
                <div id="galleryUploadList" class="gallery-upload-list">
                    <div class="gallery-upload-row">
                        <input class="form-control gallery-upload-input" type="file" name="gallery_images[]" accept="image/*">
                    </div>
                </div>
                <div id="galleryUploadControls" class="gallery-upload-controls" style="display:none;">
                    <button type="button" id="addGalleryUpload" class="btn btn-outline btn-sm">Add another image</button>
                </div>

                <?php if (!empty($productGallery)): ?>
                    <div class="gallery-preview-wrap">
                        <label style="margin-bottom:8px;">Current gallery</label>
                        <div class="gallery-preview-grid">
                            <?php foreach ($productGallery as $galleryItem): ?>
                                <div class="gallery-preview-item">
                                    <img src="<?= e($galleryItem['image_url']) ?>" alt="Product gallery image">
                                    <label class="gallery-primary-option">
                                        <input type="radio" name="existing_primary_image" value="<?= (int) $galleryItem['image_id'] ?>" <?= !empty($galleryItem['is_primary']) ? 'checked' : '' ?>>
                                        <span>Main</span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group" style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" id="is_featured" name="is_featured" value="1" <?= !empty($product['is_featured']) ? 'checked' : '' ?> style="width:auto;">
                <label for="is_featured" style="margin:0;">Feature on homepage</label>
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select class="form-control" id="status" name="status">
                    <option value="active" <?= ($product['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($product['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <button type="submit" class="btn btn-accent">Save Product</button>
        </form>
    </div>
</div>

<script>
const galleryUploadList = document.getElementById('galleryUploadList');
const galleryUploadControls = document.getElementById('galleryUploadControls');
const addGalleryUpload = document.getElementById('addGalleryUpload');

function galleryHasSelection() {
    const primaryImageSelected = document.getElementById('image')?.files?.length > 0;
    const gallerySelected = Array.from(document.querySelectorAll('.gallery-upload-input')).some((input) => input.files && input.files.length > 0);
    return primaryImageSelected || gallerySelected;
}

function updateGalleryControls() {
    galleryUploadControls.style.display = galleryHasSelection() ? 'block' : 'none';
}

addGalleryUpload?.addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'gallery-upload-row';
    row.innerHTML = '<input class="form-control gallery-upload-input" type="file" name="gallery_images[]" accept="image/*">';
    galleryUploadList.appendChild(row);
    updateGalleryControls();
});

galleryUploadList?.addEventListener('change', (event) => {
    if (event.target.classList.contains('gallery-upload-input')) {
        updateGalleryControls();
    }
});
</script>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
