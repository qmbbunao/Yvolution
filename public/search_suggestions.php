<?php
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=UTF-8');

$query = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['category'] ?? 0);
if (mb_strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$pdo = Database::connect();
$sql = "SELECT p.product_id, p.name, p.image_url, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.category_id = p.category_id
        WHERE p.status = 'active'
                    AND (p.name LIKE :name_query OR p.description LIKE :description_query OR c.name LIKE :category_query)";
$likeQuery = '%' . $query . '%';
$params = [
        ':name_query' => $likeQuery,
        ':description_query' => $likeQuery,
        ':category_query' => $likeQuery,
];

if ($categoryId > 0) {
    $sql .= ' AND p.category_id = :category_id';
    $params[':category_id'] = $categoryId;
}

$sql .= ' ORDER BY p.is_featured DESC, p.name LIMIT 6';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$results = array_map(static function (array $product): array {
    return [
        'id' => (int) $product['product_id'],
        'name' => $product['name'],
        'category' => $product['category_name'] ?: 'Uncategorized',
        'image' => $product['image_url'] ?: BASE_URL . '/assets/images/products/placeholder.jpg',
        'url' => BASE_URL . '/public/product_details.php?id=' . (int) $product['product_id'],
    ];
}, $stmt->fetchAll());

echo json_encode($results, JSON_UNESCAPED_SLASHES);
