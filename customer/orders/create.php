<?php
require_once __DIR__ . '/../../config/app.php';
require_role('customer');

$pdo = Database::connect();
$user = current_user();

$account = $pdo->prepare("SELECT home_address, home_lat, home_lng FROM users WHERE user_id = ?");
$account->execute([$user['user_id']]);
$account = $account->fetch();

$products = $pdo->query("SELECT product_id, name, base_price, available_sizes, available_colors, image_url FROM products WHERE status='active' ORDER BY name")->fetchAll();
$services = $pdo->query("SELECT service_id, name, price, requires_tarp_options FROM services WHERE status='active' ORDER BY name")->fetchAll();
$packages = $pdo->query("SELECT package_id, name, price FROM packages WHERE status='active' ORDER BY name")->fetchAll();
$templates = $pdo->query("SELECT template_id, name, event_type, image_url FROM design_templates WHERE status='active' ORDER BY event_type, name")->fetchAll();

$preselectType = 'custom';
$preselectId = 0;
if (!empty($_GET['product_id'])) {
    $preselectType = 'product';
    $preselectId = (int) $_GET['product_id'];
}
if (!empty($_GET['service_id'])) {
    $preselectType = 'service';
    $preselectId = (int) $_GET['service_id'];
}

$errors = [];
$pageTitle = 'Place an Order — Yvolution Custom Apparel';
include __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="container" style="padding:50px 24px;max-width:760px;">
    <h1>Place an Order</h1>
    <p style="color:#555;margin-bottom:30px;">Tell us what you need — our team will review and send you a quotation.</p>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="<?= BASE_URL ?>/customer/orders/store.php" enctype="multipart/form-data" id="orderForm">
        <?= csrf_field() ?>

        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <h3 style="font-size:16px;">1. What are you ordering?</h3>

                <div class="form-group">
                    <label for="order_type">Order Type</label>
                    <select class="form-control" id="order_type" name="order_type" required>
                        <option value="product" <?= $preselectType === 'product' ? 'selected' : '' ?>>Product (catalog item)</option>
                        <option value="service" <?= $preselectType === 'service' ? 'selected' : '' ?>>Service</option>
                        <option value="package">Team Package</option>
                        <option value="custom">Custom Request (describe below)</option>
                    </select>
                </div>

                <div class="form-group" id="itemRefGroup">
                    <label for="item_ref_id">Select Item</label>
                    <select class="form-control" id="item_ref_id" name="item_ref_id">
                        <option value="">— Select —</option>
                    </select>
                </div>

                <!-- Product image preview -->
                <div id="productPreview" style="display:none;margin-bottom:16px;">
                    <img id="productPreviewImg" src="" alt="" style="width:140px;height:140px;object-fit:cover;border-radius:var(--radius-sm);box-shadow:var(--shadow-card);">
                </div>

                <div class="form-group" id="customNameGroup" style="display:none;">
                    <label for="item_name">Describe the item</label>
                    <input class="form-control" type="text" id="item_name" name="item_name" placeholder="e.g. Custom sublimated basketball jersey">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="size">Size</label>
                        <input class="form-control" type="text" id="size" name="size" placeholder="e.g. M, L, or 'Assorted'">
                    </div>
                    <div class="form-group">
                        <label for="color">Color</label>
                        <input class="form-control" type="text" id="color" name="color" placeholder="e.g. Navy / Orange">
                    </div>
                    <div class="form-group">
                        <label for="quantity">Quantity</label>
                        <input class="form-control" type="number" id="quantity" name="quantity" value="1" min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Order Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Design details, deadline, special instructions..."></textarea>
                </div>
            </div>
        </div>

        <!-- Tarpaulin / event printing options (shown only for services flagged as such) -->
        <div class="card" style="margin-bottom:20px;display:none;" id="tarpOptionsCard">
            <div class="card-body">
                <h3 style="font-size:16px;">Event Printing Details</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="tarp_size">Tarpaulin Size</label>
                        <select class="form-control" id="tarp_size" name="tarp_size_preset">
                            <option value="2ft x 3ft">2ft x 3ft</option>
                            <option value="3ft x 4ft">3ft x 4ft</option>
                            <option value="3ft x 5ft" selected>3ft x 5ft</option>
                            <option value="4ft x 6ft">4ft x 6ft</option>
                            <option value="4ft x 8ft">4ft x 8ft</option>
                            <option value="custom">Custom size...</option>
                        </select>
                        <input class="form-control" type="text" id="tarp_size_custom" name="tarp_size_custom" placeholder="e.g. 5ft x 10ft" style="display:none;margin-top:8px;">
                    </div>
                    <div class="form-group">
                        <label for="event_type">Event Type</label>
                        <select class="form-control" id="event_type" name="event_type">
                            <option value="Birthday">Birthday</option>
                            <option value="Graduation">Graduation</option>
                            <option value="Wedding">Wedding</option>
                            <option value="Corporate">Corporate</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Design Source</label>
                    <div style="display:flex;gap:16px;font-size:14px;margin-top:6px;">
                        <label style="display:flex;align-items:center;gap:6px;font-size:14px;text-transform:none;font-weight:normal;">
                            <input type="radio" name="design_source" value="upload" checked style="width:auto;"> Upload my own design
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;font-size:14px;text-transform:none;font-weight:normal;">
                            <input type="radio" name="design_source" value="template" style="width:auto;"> Choose from shop designs
                        </label>
                    </div>
                </div>

                <div id="templateGallery" style="display:none;">
                    <?php if (empty($templates)): ?>
                        <p style="font-size:13px;color:#888;">No ready-made designs available yet — please upload your own below instead.</p>
                    <?php else: ?>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px;">
                            <?php foreach ($templates as $t): ?>
                                <label style="cursor:pointer;text-align:center;">
                                    <input type="radio" name="template_id" value="<?= (int) $t['template_id'] ?>" style="display:none;" class="template-radio">
                                    <img src="<?= e($t['image_url']) ?>" alt="<?= e($t['name']) ?>" class="template-thumb" style="width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:6px;border:2px solid transparent;">
                                    <span style="font-size:11px;display:block;margin-top:4px;color:#666;"><?= e($t['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <h3 style="font-size:16px;">2. Upload Design Files or References</h3>
                <p style="font-size:13px;color:#777;" id="uploadHint">Logos, sketches, or reference images (JPG, PNG, PDF — up to 5 files, 10MB each).</p>
                <input class="form-control" type="file" name="design_files[]" id="designFilesInput" multiple accept=".jpg,.jpeg,.png,.pdf">
            </div>
        </div>

        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <h3 style="font-size:16px;">3. Delivery Address</h3>

                <?php if (!empty($account['home_address'])): ?>
                    <button type="button" id="useHomeAddressBtn" class="btn btn-outline btn-sm" style="border-color:#ccc;color:#333;margin-bottom:14px;">📍 Use My Home Address</button>
                <?php endif; ?>

                <div class="form-group">
                    <label for="delivery_address">Address</label>
                    <div style="display:flex;gap:8px;">
                        <input class="form-control" type="text" id="delivery_address" name="delivery_address" placeholder="Street, Barangay, City">
                        <button type="button" id="locateBtn" class="btn btn-dark btn-sm" style="white-space:nowrap;">Locate</button>
                    </div>
                    <p id="geocodeStatus" style="font-size:12px;color:#888;margin-top:6px;"></p>
                </div>
                <div id="map" style="height:260px;border-radius:var(--radius-sm);display:none;"></div>
                <input type="hidden" id="delivery_lat" name="delivery_lat">
                <input type="hidden" id="delivery_lng" name="delivery_lng">
            </div>
        </div>

        <button type="submit" class="btn btn-accent btn-block">Submit Order Request</button>
    </form>
</div>

<script>
const products = <?= json_encode($products) ?>;
const services = <?= json_encode($services) ?>;
const packages = <?= json_encode($packages) ?>;
const preselectType = <?= json_encode($preselectType) ?>;
const preselectId = <?= json_encode($preselectId) ?>;
const homeAddress = <?= json_encode($account['home_address'] ?? null) ?>;
const homeLat = <?= json_encode($account['home_lat'] ?? null) ?>;
const homeLng = <?= json_encode($account['home_lng'] ?? null) ?>;

const orderType = document.getElementById('order_type');
const itemRefGroup = document.getElementById('itemRefGroup');
const itemRefSelect = document.getElementById('item_ref_id');
const customNameGroup = document.getElementById('customNameGroup');
const productPreview = document.getElementById('productPreview');
const productPreviewImg = document.getElementById('productPreviewImg');
const tarpCard = document.getElementById('tarpOptionsCard');

function populateItems(type) {
    itemRefSelect.innerHTML = '<option value="">— Select —</option>';
    let list = [];
    if (type === 'product') list = products.map(p => ({ id: p.product_id, label: `${p.name} — ₱${parseFloat(p.base_price).toFixed(2)}` }));
    if (type === 'service') list = services.map(s => ({ id: s.service_id, label: `${s.name} — ₱${parseFloat(s.price).toFixed(2)}` }));
    if (type === 'package') list = packages.map(pk => ({ id: pk.package_id, label: `${pk.name} — ₱${parseFloat(pk.price).toFixed(2)}` }));

    list.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = item.label;
        itemRefSelect.appendChild(opt);
    });
}

function updateProductPreview() {
    if (orderType.value !== 'product' || !itemRefSelect.value) {
        productPreview.style.display = 'none';
        return;
    }
    const product = products.find(p => String(p.product_id) === itemRefSelect.value);
    if (product && product.image_url) {
        productPreviewImg.src = product.image_url;
        productPreviewImg.alt = product.name;
        productPreview.style.display = 'block';
    } else {
        productPreview.style.display = 'none';
    }
}

function updateTarpOptions() {
    if (orderType.value !== 'service' || !itemRefSelect.value) {
        tarpCard.style.display = 'none';
        return;
    }
    const service = services.find(s => String(s.service_id) === itemRefSelect.value);
    tarpCard.style.display = (service && Number(service.requires_tarp_options) === 1) ? 'block' : 'none';
}

function toggleFields() {
    const type = orderType.value;
    if (type === 'custom') {
        itemRefGroup.style.display = 'none';
        customNameGroup.style.display = 'block';
        productPreview.style.display = 'none';
        tarpCard.style.display = 'none';
    } else {
        itemRefGroup.style.display = 'block';
        customNameGroup.style.display = 'none';
        populateItems(type);
        updateProductPreview();
        updateTarpOptions();
    }
}

orderType.addEventListener('change', toggleFields);
itemRefSelect.addEventListener('change', () => { updateProductPreview(); updateTarpOptions(); });
toggleFields();
if (preselectType !== 'custom' && preselectId) {
    setTimeout(() => {
        itemRefSelect.value = preselectId;
        updateProductPreview();
        updateTarpOptions();
    }, 100);
}

// --- Tarp size custom toggle ---
const tarpSizeSelect = document.getElementById('tarp_size');
const tarpSizeCustom = document.getElementById('tarp_size_custom');
tarpSizeSelect?.addEventListener('change', () => {
    tarpSizeCustom.style.display = tarpSizeSelect.value === 'custom' ? 'block' : 'none';
});

// --- Design source toggle (upload vs template gallery) ---
const designSourceRadios = document.querySelectorAll('input[name="design_source"]');
const templateGallery = document.getElementById('templateGallery');
const designFilesInput = document.getElementById('designFilesInput');
designSourceRadios.forEach(radio => {
    radio.addEventListener('change', () => {
        const useTemplate = document.querySelector('input[name="design_source"]:checked').value === 'template';
        templateGallery.style.display = useTemplate ? 'block' : 'none';
    });
});

document.querySelectorAll('.template-radio').forEach(radio => {
    radio.addEventListener('change', () => {
        document.querySelectorAll('.template-thumb').forEach(img => img.style.borderColor = 'transparent');
        radio.parentElement.querySelector('.template-thumb').style.borderColor = 'var(--c-accent)';
    });
});

// --- Home address quick-fill ---
document.getElementById('useHomeAddressBtn')?.addEventListener('click', () => {
    document.getElementById('delivery_address').value = homeAddress || '';
    if (homeLat && homeLng) {
        document.getElementById('delivery_lat').value = homeLat;
        document.getElementById('delivery_lng').value = homeLng;
        document.getElementById('geocodeStatus').textContent = 'Using your saved home address.';

        const mapDiv = document.getElementById('map');
        mapDiv.style.display = 'block';
        if (!map) {
            map = L.map('map').setView([homeLat, homeLng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
            marker = L.marker([homeLat, homeLng]).addTo(map);
        } else {
            map.setView([homeLat, homeLng], 15);
            marker.setLatLng([homeLat, homeLng]);
        }
    }
});

// --- Map / geocoding ---
let map, marker;
document.getElementById('locateBtn').addEventListener('click', async () => {
    const address = document.getElementById('delivery_address').value.trim();
    const status = document.getElementById('geocodeStatus');
    if (!address) { status.textContent = 'Enter an address first.'; return; }
    status.textContent = 'Searching...';

    try {
        const res = await fetch(`<?= BASE_URL ?>/customer/orders/geocode_ajax.php?address=${encodeURIComponent(address)}`);
        const data = await res.json();
        if (!data.success) { status.textContent = data.message; return; }

        document.getElementById('delivery_lat').value = data.lat;
        document.getElementById('delivery_lng').value = data.lng;
        status.textContent = 'Found: ' + data.display_name;

        const mapDiv = document.getElementById('map');
        mapDiv.style.display = 'block';

        if (!map) {
            map = L.map('map').setView([data.lat, data.lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);
            marker = L.marker([data.lat, data.lng]).addTo(map);
        } else {
            map.setView([data.lat, data.lng], 15);
            marker.setLatLng([data.lat, data.lng]);
        }
    } catch (err) {
        status.textContent = 'Something went wrong. Please try again.';
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
