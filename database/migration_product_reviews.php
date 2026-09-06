<?php
/**
 * One-time migration: enable product-level reviews on feedback table.
 * Run: php database/migration_product_reviews.php
 */
require_once __DIR__ . '/../config/database.php';

$pdo = Database::connect();

$cols = $pdo->query('DESCRIBE feedback')->fetchAll(PDO::FETCH_COLUMN);
echo "Current columns: " . implode(', ', $cols) . PHP_EOL;

if (!in_array('product_id', $cols, true)) {
    // Make order_id nullable so product-only reviews work
    $pdo->exec('ALTER TABLE feedback MODIFY order_id INT DEFAULT NULL');
    echo "Made order_id nullable.\n";

    $pdo->exec('ALTER TABLE feedback ADD COLUMN product_id INT DEFAULT NULL AFTER order_id');
    echo "Added product_id column.\n";

    // Unique: one review per customer per product (NULLs allowed for order-only feedback)
    try {
        $pdo->exec('ALTER TABLE feedback ADD UNIQUE KEY uq_product_customer_review (product_id, customer_id)');
        echo "Added unique key uq_product_customer_review.\n";
    } catch (Throwable $e) {
        echo "Unique key note: " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec(
            'ALTER TABLE feedback ADD CONSTRAINT fk_feedback_product
             FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL'
        );
        echo "Added product foreign key.\n";
    } catch (Throwable $e) {
        echo "FK note: " . $e->getMessage() . "\n";
    }
} else {
    echo "product_id already exists — nothing to do.\n";
}

$cols = $pdo->query('DESCRIBE feedback')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "{$c['Field']} | {$c['Type']} | Null={$c['Null']}\n";
}

echo "Done.\n";
