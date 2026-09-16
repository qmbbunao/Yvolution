<?php
require_once __DIR__ . '/../config/app.php';

$pdo = Database::connect();

$hero  = $pdo->query("SELECT * FROM homepage_content WHERE section_key = 'hero' AND status='active' LIMIT 1")->fetch();
$about = $pdo->query("SELECT * FROM homepage_content WHERE section_key = 'about' AND status='active' LIMIT 1")->fetch();

$products = $pdo->query(
    "SELECT * FROM products WHERE status='active' ORDER BY is_featured DESC, created_at DESC LIMIT 8"
)->fetchAll();

$services = $pdo->query("SELECT * FROM services WHERE status='active' ORDER BY name LIMIT 6")->fetchAll();

$packages = $pdo->query("SELECT * FROM packages WHERE status='active' ORDER BY price ASC LIMIT 6")->fetchAll();

$promotions = $pdo->query(
    "SELECT * FROM promotions WHERE status='active' AND CURDATE() BETWEEN start_date AND end_date ORDER BY start_date DESC LIMIT 4"
)->fetchAll();

$testimonials = $pdo->query(
    "SELECT * FROM testimonials WHERE status='approved' ORDER BY is_featured DESC, created_at DESC LIMIT 6"
)->fetchAll();

$pageTitle = 'Yvolution Custom Apparel — Wear Your Game';
$successMsg = get_flash('success');
$errorMsg = get_flash('error');

include __DIR__ . '/../includes/header.php';
?>

<!-- ===================== HERO ===================== -->
<section class="hero reveal is-visible" style="position:relative;background:var(--c-navy-950);color:#fff;overflow:hidden;min-height:100vh;display:flex;align-items:center;">
    <video autoplay muted loop playsinline poster="<?= BASE_URL ?>/assets/images/banners/hero-poster.jpg"
           style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0.35;">
        <?php if (!empty($hero['video_url'])): ?>
            <source src="<?= e($hero['video_url']) ?>" type="video/mp4">
        <?php else: ?>
            <source src="<?= BASE_URL ?>/assets/videos/hero.mp4" type="video/mp4">
        <?php endif; ?>
    </video>
    <div style="position:absolute;inset:0;background:linear-gradient(90deg, rgba(10,14,26,0.85) 0%, rgba(10,14,26,0.55) 55%, rgba(10,14,26,0.25) 100%);"></div>
    <div class="container" style="position:relative;padding:24px;text-align:left;">
        <div style="max-width:640px;">
            <h1 style="font-size:clamp(40px,6.5vw,76px);color:var(--c-chalk);">Yvolution<br>Custom Apparel</h1>
            <p style="max-width:480px;margin:0 0 36px;color:var(--c-muted);font-size:19px;">
                <?= e($hero['subtitle'] ?? 'Custom apparel built for teams that play to win.') ?>
            </p>
            <div style="display:flex;gap:16px;flex-wrap:wrap;">
                <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-accent">Start Your Order</a>
                <a href="#products" class="btn btn-outline">View Catalog</a>
            </div>
        </div>
    </div>
</section>

<div class="brand-marquee" aria-label="Yvolution custom apparel categories">
    <div class="brand-marquee-track"><span>TEAMWEAR</span><b>/</b><span>PERFORMANCE</span><b>/</b><span>IDENTITY</span><b>/</b><span>BUILT TO BELONG</span><b>/</b><span>TEAMWEAR</span><b>/</b><span>PERFORMANCE</span><b>/</b><span>IDENTITY</span><b>/</b></div>
</div>

<!-- ===================== ABOUT / WHY US ===================== -->
<?php if ($about): ?>
<section class="about-intro container reveal" style="padding:80px 24px;text-align:center;">
    <p class="section-kicker">01 / WHY YVOLUTION</p>
    <h2><?= e($about['title']) ?></h2>
    <p class="text-secondary" style="max-width:640px;margin:0 auto;font-size:16px;"><?= e($about['content_text']) ?></p>
</section>
<?php endif; ?>

<!-- ===================== SERVICES ===================== -->
<section id="services" class="reveal" style="background:var(--c-navy-900);color:#fff;padding:80px 0;">
    <div class="container">
        <p class="section-kicker section-kicker-light">02 / WHAT WE MAKE</p>
        <h2 style="color:var(--c-chalk);">Our Services</h2>
        <p style="color:var(--c-muted);margin-bottom:36px;">From single jerseys to full-team orders.</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:24px;">
            <?php if (empty($services)): ?>
                <p style="color:var(--c-muted);">Services will appear here once added by our team.</p>
            <?php endif; ?>
            <?php foreach ($services as $s): ?>
                <div class="card" style="background:var(--c-navy-800);color:#fff;">
                    <?php if ($s['image_url']): ?>
                        <img src="<?= e($s['image_url']) ?>" alt="<?= e($s['name']) ?>" style="height:160px;width:100%;object-fit:cover;">
                    <?php endif; ?>
                    <div class="card-body">
                        <h3 style="font-size:18px;color:var(--c-chalk);"><?= e($s['name']) ?></h3>
                        <p style="color:var(--c-muted);font-size:14px;min-height:40px;"><?= e($s['description']) ?></p>
                        <strong style="color:var(--c-gold);"><?= money($s['price']) ?> <span style="font-size:12px;color:var(--c-muted);"><?= e($s['price_unit']) ?></span></strong>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===================== PRODUCTS ===================== -->
<section id="products" class="container" style="padding:80px 24px;">
    <p class="section-kicker">03 / THE CATALOG</p>
    <h2 class="reveal">Featured Products</h2>
    <p class="text-secondary reveal" style="margin-bottom:36px;">Jerseys, tees, hoodies, and uniforms — fully customizable.</p>
    <div class="product-grid">
        <?php if (empty($products)): ?>
            <p class="text-muted-tone">Products will appear here once added by our team.</p>
        <?php endif; ?>
        <?php foreach ($products as $p): ?>
            <a href="<?= BASE_URL ?>/public/product_details.php?id=<?= (int) $p['product_id'] ?>" class="product-card reveal">
                <div class="product-card-image">
                    <?php if ($p['is_featured']): ?><span class="badge badge-progress">Featured</span><?php endif; ?>
                    <img src="<?= e($p['image_url'] ?: BASE_URL . '/assets/images/products/placeholder.jpg') ?>" alt="<?= e($p['name']) ?>">
                </div>
                <div class="product-card-body">
                    <div class="product-card-name"><?= e($p['name']) ?></div>
                    <div class="product-card-desc"><?= e(mb_strimwidth($p['description'] ?? '', 0, 60, '...')) ?></div>
                    <div class="product-card-footer">
                        <span class="product-card-price"><?= money($p['base_price']) ?></span>
                        <span class="product-card-cta">Details</span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ===================== PACKAGES ===================== -->
<section id="packages" class="surface-alt reveal" style="border-top:1px solid var(--border-subtle);border-bottom:1px solid var(--border-subtle);padding:80px 0;">
    <div class="container">
        <p class="section-kicker">04 / BUILT FOR TEAMS</p>
        <h2>Team Packages</h2>
        <p class="text-secondary" style="margin-bottom:36px;">Bundled pricing for full-team and bulk orders.</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px;">
            <?php if (empty($packages)): ?>
                <p class="text-muted-tone">Packages will appear here once added by our team.</p>
            <?php endif; ?>
            <?php foreach ($packages as $pkg): ?>
                <div class="card">
                    <?php if (!empty($pkg['image_url'])): ?>
                        <img src="<?= e($pkg['image_url']) ?>" alt="<?= e($pkg['name']) ?>" style="display:block;width:100%;height:190px;object-fit:cover;">
                    <?php endif; ?>
                    <div class="card-body">
                        <h3><?= e($pkg['name']) ?></h3>
                        <p class="text-secondary" style="font-size:14px;"><?= e($pkg['description']) ?></p>
                        <p class="text-muted-tone" style="font-size:13px;"><em>Includes:</em> <?= e($pkg['includes']) ?></p>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
                            <strong style="color:var(--c-accent);font-size:18px;"><?= money($pkg['price']) ?></strong>
                            <span class="text-muted-tone" style="font-size:12px;">Min. <?= (int) $pkg['min_quantity'] ?> pcs</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===================== PROMOTIONS ===================== -->
<?php if (!empty($promotions)): ?>
<section id="promotions" class="container reveal" style="padding:80px 24px;">
    <p class="section-kicker">05 / THE GOOD STUFF</p>
    <h2>Current Promotions</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px;margin-top:24px;">
        <?php foreach ($promotions as $promo): ?>
            <div class="card" style="border-left:6px solid var(--c-accent);">
                <?php if (!empty($promo['image_url'])): ?>
                    <img src="<?= e($promo['image_url']) ?>" alt="<?= e($promo['title']) ?>" style="display:block;width:100%;height:190px;object-fit:cover;">
                <?php endif; ?>
                <div class="card-body">
                    <h3 style="font-size:17px;"><?= e($promo['title']) ?></h3>
                    <p class="text-secondary" style="font-size:14px;"><?= e($promo['description']) ?></p>
                    <strong style="color:var(--c-accent-dark);">
                        <?= $promo['discount_type'] === 'percent' ? (int) $promo['discount_value'] . '% OFF' : money($promo['discount_value']) . ' OFF' ?>
                    </strong>
                    <?php if ($promo['promo_code']): ?>
                        <p class="text-muted-tone" style="font-size:12px;margin-top:6px;">Code: <strong><?= e($promo['promo_code']) ?></strong></p>
                    <?php endif; ?>
                    <p class="text-muted-tone" style="font-size:12px;">Valid until <?= date('M j, Y', strtotime($promo['end_date'])) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ===================== TESTIMONIALS ===================== -->
<section class="reveal" style="background:var(--c-navy-950);color:#fff;padding:80px 0;">
    <div class="container">
        <p class="section-kicker section-kicker-light">06 / FROM THE COMMUNITY</p>
        <h2 style="color:var(--c-chalk);text-align:center;">What Teams Are Saying</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px;margin-top:36px;">
            <?php if (empty($testimonials)): ?>
                <p style="color:var(--c-muted);text-align:center;grid-column:1/-1;">Be the first to leave feedback after your order!</p>
            <?php endif; ?>
            <?php foreach ($testimonials as $t): ?>
                <div class="card" style="background:var(--c-navy-800);color:#fff;">
                    <div class="card-body">
                        <div style="color:var(--c-gold);margin-bottom:8px;"><?= str_repeat('&#9733;', (int) $t['rating']) ?></div>
                        <p style="color:var(--c-chalk);font-size:14px;">&ldquo;<?= e($t['message']) ?>&rdquo;</p>
                        <p style="color:var(--c-muted);font-size:13px;margin-top:12px;">&mdash; <?= e($t['customer_name']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===================== CONTACT ===================== -->
<?php $business = require BASE_PATH . '/config/business.php'; ?>
<section id="contact" class="container reveal" style="padding:80px 24px;max-width:640px;">
    <p class="section-kicker" style="text-align:center;">07 / LET'S TALK</p>
    <h2 style="text-align:center;">Get In Touch</h2>
    <p class="text-secondary" style="text-align:center;margin-bottom:12px;">Questions about pricing, bulk orders, or turnaround time? Send us a message.</p>
    <p class="text-muted-tone" style="text-align:center;font-size:14px;margin-bottom:32px;">📍 <?= e($business['location']['address']) ?></p>

    <div id="shopMap" style="height:240px;border-radius:var(--radius-md);margin-bottom:32px;"></div>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const shopMap = L.map('shopMap').setView([<?= (float) $business['location']['lat'] ?>, <?= (float) $business['location']['lng'] ?>], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(shopMap);
        L.marker([<?= (float) $business['location']['lat'] ?>, <?= (float) $business['location']['lng'] ?>]).addTo(shopMap)
            .bindPopup('<?= e(addslashes($business['location']['address'])) ?>').openPopup();
    </script>

    <?php if ($successMsg): ?><div class="alert alert-success"><?= e($successMsg) ?></div><?php endif; ?>
    <?php if ($errorMsg): ?><div class="alert alert-error"><?= e($errorMsg) ?></div><?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/public/contact_submit.php">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input class="form-control" type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input class="form-control" type="email" id="email" name="email" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input class="form-control" type="text" id="phone" name="phone">
            </div>
            <div class="form-group">
                <label for="subject">Subject</label>
                <input class="form-control" type="text" id="subject" name="subject" placeholder="e.g. Bulk order inquiry">
            </div>
        </div>
        <div class="form-group">
            <label for="message">Message</label>
            <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
        </div>
        <button type="submit" class="btn btn-accent btn-block">Send Message</button>
    </form>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
