<?php
require_once __DIR__ . '/../config/app.php';

$categoryId = (int) ($_GET['category'] ?? 0);
$pdo = Database::connect();

$stmt = $pdo->prepare(
    "SELECT c.name AS category_name, GROUP_CONCAT(DISTINCT p.available_sizes SEPARATOR ',') AS size_values
     FROM categories c
     INNER JOIN products p ON p.category_id = c.category_id AND p.status = 'active'
     WHERE c.category_id = ? AND c.type = 'product' AND c.status = 'active'
     GROUP BY c.category_id, c.name"
);
$stmt->execute([$categoryId]);
$guide = $stmt->fetch();

if (!$guide) {
    http_response_code(404);
    exit;
}

$categoryName = trim($guide['category_name']);
$sizes = [];
foreach (explode(',', (string) $guide['size_values']) as $size) {
    $size = trim($size);
    if ($size !== '' && !in_array($size, $sizes, true)) {
        $sizes[] = $size;
    }
}

$xml = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
};
$kind = strtolower($categoryName);
$isBottom = (bool) preg_match('/short|pant|bottom|jogger|skirt/', $kind);
$isOuterwear = (bool) preg_match('/hood|jacket|sweat|varsity/', $kind);
$title = $categoryName . ' Size Guide';
$sizeRows = '';
$startY = 535;
$rowHeight = 45;
foreach ($sizes as $index => $size) {
    $y = $startY + ($index * $rowHeight);
    $sizeRows .= '<rect x="525" y="' . $y . '" width="570" height="' . $rowHeight . '" fill="' . ($index % 2 ? '#f2f0ea' : '#ffffff') . '"/>';
    $sizeRows .= '<text x="565" y="' . ($y + 29) . '" class="table-text">' . $xml($size) . '</text>';
    $sizeRows .= '<text x="835" y="' . ($y + 29) . '" class="table-muted">Not configured</text>';
}

if ($isBottom) {
    $illustration = '<path d="M160 155 L285 155 L305 410 L260 410 L235 270 L210 410 L165 410 Z" class="garment"/>
        <path d="M160 155 L115 200 L140 240 L175 210 M285 155 L330 200 L305 240 L270 210" class="garment"/>
        <path d="M175 210 L160 440 L225 440 L235 270 L245 440 L310 440 L295 210" class="garment"/>
        <line x1="130" y1="240" x2="300" y2="240" class="measure-line"/><text x="315" y="246" class="measure-label">WAIST / HIP</text>
        <line x1="160" y1="440" x2="310" y2="440" class="measure-line"/><text x="315" y="446" class="measure-label">OUTSEAM</text>';
} elseif ($isOuterwear) {
    $illustration = '<path d="M175 145 L225 120 L275 145 L340 215 L305 255 L278 225 L295 455 L155 455 L172 225 L145 255 L110 215 Z" class="garment"/>
        <line x1="175" y1="180" x2="275" y2="180" class="measure-line"/><text x="290" y="186" class="measure-label">CHEST</text>
        <line x1="155" y1="455" x2="295" y2="455" class="measure-line"/><text x="310" y="461" class="measure-label">LENGTH</text>
        <line x1="110" y1="215" x2="145" y2="255" class="measure-line"/><text x="45" y="235" class="measure-label">SLEEVE</text>';
} else {
    $illustration = '<path d="M175 145 L225 120 L275 145 L340 215 L305 255 L278 225 L295 455 L155 455 L172 225 L145 255 L110 215 Z" class="garment"/>
        <path d="M205 130 Q225 165 245 130" fill="none" class="neckline"/>
        <line x1="175" y1="180" x2="275" y2="180" class="measure-line"/><text x="290" y="186" class="measure-label">CHEST</text>
        <line x1="155" y1="455" x2="295" y2="455" class="measure-line"/><text x="310" y="461" class="measure-label">LENGTH</text>
        <line x1="110" y1="215" x2="145" y2="255" class="measure-line"/><text x="45" y="235" class="measure-label">SLEEVE</text>';
}

header('Content-Type: image/svg+xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 <?= 590 + (count($sizes) * 45) ?>" role="img" aria-labelledby="title desc">
    <title id="title"><?= $xml($title) ?></title>
    <desc id="desc">Product-specific measurement illustration and available size labels for <?= $xml($categoryName) ?>.</desc>
    <style>
        .bg { fill: #f7f5ef; }
        .ink { fill: #0f1626; }
        .accent { fill: #ff4b23; }
        .muted { fill: #667085; }
        .garment { fill: #161f36; stroke: #ff4b23; stroke-width: 5; stroke-linejoin: round; }
        .neckline { stroke: #f5b942; stroke-width: 5; }
        .measure-line { stroke: #ff4b23; stroke-width: 2; stroke-dasharray: 7 6; }
        .measure-label { fill: #0f1626; font: 700 16px Arial, sans-serif; letter-spacing: 1px; }
        .eyebrow { fill: #ff4b23; font: 700 15px Arial, sans-serif; letter-spacing: 3px; }
        .heading { fill: #0f1626; font: 700 42px Arial, sans-serif; }
        .subheading { fill: #667085; font: 400 18px Arial, sans-serif; }
        .table-head { fill: #ffffff; }
        .table-title { fill: #ffffff; font: 700 15px Arial, sans-serif; letter-spacing: 2px; }
        .table-text { fill: #0f1626; font: 700 17px Arial, sans-serif; }
        .table-muted { fill: #667085; font: 400 15px Arial, sans-serif; }
        .note { fill: #0f1626; font: 400 15px Arial, sans-serif; }
    </style>
    <rect width="1200" height="100%" class="bg"/>
    <rect x="0" y="0" width="1200" height="18" class="accent"/>
    <text x="60" y="75" class="eyebrow">YVOLUTION / SIZE GUIDE</text>
    <text x="60" y="130" class="heading"><?= $xml($title) ?></text>
    <text x="60" y="165" class="subheading">Measure your garment flat. Compare with your own best-fitting piece.</text>
    <g transform="translate(0 5)"><?= $illustration ?></g>
    <rect x="525" y="465" width="570" height="48" class="accent"/>
    <text x="565" y="496" class="table-title">AVAILABLE SIZES</text>
    <text x="835" y="496" class="table-title">MEASUREMENT DATA</text>
    <?= $sizeRows ?>
    <text x="60" y="<?= 520 + (count($sizes) * 45) ?>" class="note">Measurements are not configured for this category yet. Contact Yvolution before ordering for exact garment measurements.</text>
    <text x="60" y="<?= 550 + (count($sizes) * 45) ?>" class="muted" style="font: 400 14px Arial, sans-serif;">Chest / waist: measure around the garment at the widest point. Length: measure from the highest shoulder point to the hem.</text>
</svg>
