<?php
require_once __DIR__ . '/../config/app.php';

$pdo = Database::connect();
$guides = $pdo->query("SELECT * FROM size_guides WHERE status = 'active' ORDER BY guide_id")->fetchAll();
$products = $pdo->query(
    "SELECT p.available_sizes, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.category_id = p.category_id
     WHERE p.status = 'active'"
)->fetchAll();

foreach ($guides as &$category) {
    $localGuideNumbers = [
        'jersey' => 2,
        't-shirt' => 1,
        'polo-shirt' => 4,
        'long-sleeve' => 3,
        'shorts' => 5,
    ];
    $localGuideNumber = $localGuideNumbers[$category['guide_key']] ?? null;
    $localGuidePath = $localGuideNumber ? BASE_PATH . '/assets/images/sizeguide/sizeguide' . $localGuideNumber . '.jpg' : null;
    $category['display_image_url'] = !empty($category['image_url'])
        ? $category['image_url']
        : ($localGuideNumber && is_file($localGuidePath) ? BASE_URL . '/assets/images/sizeguide/sizeguide' . $localGuideNumber . '.jpg' : null);
    $sizes = [];
    $productCount = 0;
    foreach ($products as $product) {
        $productCategory = strtolower((string) ($product['category_name'] ?? ''));
        $guideKey = strtolower((string) $category['guide_key']);
        $matches = ($guideKey === 'jersey' && str_contains($productCategory, 'jersey'))
            || ($guideKey === 't-shirt' && (str_contains($productCategory, 't-shirt') || str_contains($productCategory, 'shirt')))
            || ($guideKey === 'polo-shirt' && str_contains($productCategory, 'polo'))
            || ($guideKey === 'long-sleeve' && (str_contains($productCategory, 'long sleeve') || str_contains($productCategory, 'long-sleeve')))
            || ($guideKey === 'shorts' && (str_contains($productCategory, 'short') || str_contains($productCategory, 'bottom')));
        if ($matches) {
            $productCount++;
            foreach (explode(',', (string) $product['available_sizes']) as $size) {
                $size = trim($size);
                if ($size !== '' && !in_array($size, $sizes, true)) {
                    $sizes[] = $size;
                }
            }
        }
    }
    $category['sizes'] = $sizes;
    $category['product_count'] = $productCount;
}
unset($category);

$selectedId = (int) ($_GET['category'] ?? ($guides[0]['guide_id'] ?? 0));
$selectedCategory = null;
foreach ($guides as $category) {
    if ((int) $category['guide_id'] === $selectedId) {
        $selectedCategory = $category;
        break;
    }
}
if (!$selectedCategory && !empty($guides)) {
    $selectedCategory = $guides[0];
    $selectedId = (int) $selectedCategory['guide_id'];
}

$pageTitle = 'Size Guide — Yvolution Custom Apparel';
include __DIR__ . '/../includes/header.php';
?>

<main class="size-guide-page">
    <section class="size-guide-hero">
        <img class="size-guide-hero-media" src="<?= BASE_URL ?>/assets/images/banners/sizeguidehero.png" alt="" aria-hidden="true">
        <div class="size-guide-hero-overlay" aria-hidden="true"></div>
        <div class="container">
            <p class="section-kicker section-kicker-light">FIT MADE SIMPLE / YVOLUTION</p>
            <h1>Size<br><span>Guide</span></h1>
            <p>Find the right fit before placing your order.<br class="size-guide-hero-break"> Choose your product, compare the chart with a garment you already own, <br class="size-guide-hero-break"> and contact us whenever you are between sizes.</p>
            <a class="btn btn-accent size-guide-hero-cta" href="#sizeCharts">View all charts</a>
        </div>
    </section>

    <section id="sizeCharts" class="container size-guide-content">
        <?php if (empty($guides)): ?>
            <div class="size-guide-empty">
                <p class="section-kicker">SIZE GUIDES</p>
                <h2>No product categories yet</h2>
                <p>Size guides will appear here after active products are assigned to product categories.</p>
            </div>
        <?php else: ?>
            <div class="size-guide-layout">
                <aside class="size-guide-sidebar" aria-label="Product categories">
                    <p class="section-kicker">01 / CHOOSE A PRODUCT</p>
                    <div class="size-category-list" role="tablist" aria-label="Size guide categories">
                           <?php foreach ($guides as $category): ?>
                                     <a class="size-category-tab <?= (int) $category['guide_id'] === $selectedId ? 'is-active' : '' ?>"
                               href="<?= BASE_URL ?>/public/size_guide.php?category=<?= (int) $category['guide_id'] ?>"
                                         data-guide-id="<?= (int) $category['guide_id'] ?>"
                               role="tab" aria-selected="<?= (int) $category['guide_id'] === $selectedId ? 'true' : 'false' ?>">
                                <span><?= e($category['name']) ?></span>
                                <small><?= count($category['sizes']) ?> sizes</small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="size-guide-tip">
                        <strong>Measure flat.</strong>
                        <p>Use a garment you already love. Lay it flat, smooth the fabric, and compare the same points shown in the guide.</p>
                    </div>
                </aside>

                <div class="size-guide-main">
                    <div class="size-guide-heading">
                        <div>
                            <p class="section-kicker">02 / SELECTED GUIDE</p>
                            <h2 id="selectedGuideName"><?= e($selectedCategory['name']) ?></h2>
                        </div>
                        <span class="size-guide-count" id="selectedGuideCount"><?= (int) $selectedCategory['product_count'] ?> active <?= (int) $selectedCategory['product_count'] === 1 ? 'product' : 'products' ?></span>
                    </div>
                    <div class="size-guide-image-frame" id="sizeGuideImageFrame">
                        <?php if (!empty($selectedCategory['display_image_url'])): ?>
                            <img id="selectedGuideImage" src="<?= e($selectedCategory['display_image_url']) ?>" alt="<?= e($selectedCategory['name']) ?> size guide" loading="eager">
                        <?php else: ?>
                            <div id="selectedGuideImage" class="size-guide-upload-prompt">The <?= e($selectedCategory['name']) ?> size guide image has not been uploaded yet.</div>
                        <?php endif; ?>
                    </div>
                    <p class="size-guide-disclaimer">The chart above reflects the sizes currently configured for this product category. Numeric measurement data has not been entered in the catalog yet, so request exact garment measurements before placing a size-sensitive order.</p>
                </div>
            </div>

            <section class="find-size-section" aria-labelledby="find-size-title">
                <div class="find-size-copy">
                    <p class="section-kicker">QUICK RECOMMENDATION</p>
                    <h2 id="find-size-title">Find my size</h2>
                    <p>Enter your height and weight for a general Yvolution apparel size suggestion, then confirm it against the product chart below.</p>
                </div>
                <div class="find-size-panel">
                    <div class="find-size-panel-heading">Find my size <span aria-hidden="true">⌃</span></div>
                    <form class="find-size-form" id="findSizeForm">
                        <label for="findSizeCategory">Product category</label>
                        <select id="findSizeCategory" name="category">
                            <?php foreach ($guides as $category): ?>
                                <option value="<?= (int) $category['guide_id'] ?>" data-sizes="<?= e(implode('|', $category['sizes'])) ?>" <?= (int) $category['guide_id'] === $selectedId ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="find-size-fields">
                            <label>Height<input id="findHeight" type="number" min="80" max="240" step="1" value="170" required>
                                <span class="find-size-unit-toggle"><button type="button" data-height-unit="ft">FT/IN</button><button type="button" data-height-unit="cm" class="is-active">CM</button></span>
                            </label>
                            <label>Weight<input id="findWeight" type="number" min="20" max="250" step="1" value="70" required>
                                <span class="find-size-unit-toggle"><button type="button" data-weight-unit="lb">LBS</button><button type="button" data-weight-unit="kg" class="is-active">KG</button></span>
                            </label>
                        </div>
                        <button class="find-size-submit" type="submit">Suggest my size</button>
                        <output class="find-size-result" id="findSizeResult" aria-live="polite"></output>
                    </form>
                </div>
            </section>
        <?php endif; ?>
    </section>
</main>

<script type="application/json" id="sizeGuideData"><?= json_encode(array_map(static function (array $guide): array {
    return [
        'id' => (int) $guide['guide_id'],
        'name' => $guide['name'],
        'image' => $guide['display_image_url'],
        'count' => (int) $guide['product_count'],
        'sizes' => $guide['sizes'],
    ];
}, $guides), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<script>
(function () {
    const guideDataNode = document.getElementById('sizeGuideData');
    const guideData = guideDataNode ? JSON.parse(guideDataNode.textContent) : [];
    const guideById = new Map(guideData.map(guide => [String(guide.id), guide]));
    const guideImageFrame = document.getElementById('sizeGuideImageFrame');
    const selectedGuideName = document.getElementById('selectedGuideName');
    const selectedGuideCount = document.getElementById('selectedGuideCount');
    const categoryTabs = document.querySelectorAll('.size-category-tab');
    const categorySelect = document.getElementById('findSizeCategory');

    function selectGuide(id, updateUrl) {
        const guide = guideById.get(String(id));
        if (!guide) return;
        selectedGuideName.textContent = guide.name;
        selectedGuideCount.textContent = guide.count + ' active ' + (guide.count === 1 ? 'product' : 'products');
        guideImageFrame.innerHTML = guide.image
            ? '<img id="selectedGuideImage" src="' + guide.image.replace(/&/g, '&amp;').replace(/"/g, '&quot;') + '" alt="' + guide.name.replace(/"/g, '&quot;') + ' size guide" loading="eager">'
            : '<div id="selectedGuideImage" class="size-guide-upload-prompt">The ' + guide.name + ' size guide image has not been uploaded yet.</div>';
        categoryTabs.forEach(tab => {
            const active = tab.dataset.guideId === String(id);
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (categorySelect) categorySelect.value = String(id);
        if (updateUrl) history.pushState({ category: id }, '', '<?= BASE_URL ?>/public/size_guide.php?category=' + encodeURIComponent(id));
    }

    categoryTabs.forEach(tab => tab.addEventListener('click', function (event) {
        event.preventDefault();
        selectGuide(tab.dataset.guideId, true);
    }));
    window.addEventListener('popstate', () => selectGuide(new URLSearchParams(window.location.search).get('category') || <?= (int) $selectedId ?>, false));

    const form = document.getElementById('findSizeForm');
    if (!form) return;
    const category = document.getElementById('findSizeCategory');
    const height = document.getElementById('findHeight');
    const weight = document.getElementById('findWeight');
    const result = document.getElementById('findSizeResult');
    let heightUnit = 'cm';
    let weightUnit = 'kg';

    form.querySelectorAll('[data-height-unit]').forEach(function (button) {
        button.addEventListener('click', function () {
            const nextUnit = button.dataset.heightUnit;
            if (nextUnit === heightUnit) return;
            const value = Number(height.value);
            if (value) height.value = nextUnit === 'ft' ? Math.round((value / 30.48) * 12) / 12 : Math.round(value * 30.48);
            heightUnit = nextUnit;
            form.querySelectorAll('[data-height-unit]').forEach(item => item.classList.toggle('is-active', item.dataset.heightUnit === nextUnit));
        });
    });
    form.querySelectorAll('[data-weight-unit]').forEach(function (button) {
        button.addEventListener('click', function () {
            const nextUnit = button.dataset.weightUnit;
            if (nextUnit === weightUnit) return;
            const value = Number(weight.value);
            if (value) weight.value = nextUnit === 'lb' ? Math.round(value * 2.20462) : Math.round(value / 2.20462);
            weightUnit = nextUnit;
            form.querySelectorAll('[data-weight-unit]').forEach(item => item.classList.toggle('is-active', item.dataset.weightUnit === nextUnit));
        });
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const selected = category.options[category.selectedIndex];
        const sizes = selected.dataset.sizes.split('|').filter(Boolean);
        const heightValue = heightUnit === 'ft' ? Number(height.value) * 30.48 : Number(height.value);
        const weightValue = weightUnit === 'lb' ? Number(weight.value) / 2.20462 : Number(weight.value);
        if (!sizes.length || !heightValue || !weightValue) return;

        const bodyIndex = ((heightValue - 145) / 55) * 0.4 + ((weightValue - 40) / 90) * 0.6;
        const position = Math.max(0, Math.min(sizes.length - 1, Math.round(bodyIndex * (sizes.length - 1))));
        result.textContent = 'Estimated starting size: ' + sizes[position] + ' for ' + selected.textContent.trim() + '. Please verify against the complete chart.';
        result.classList.add('is-visible');
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
