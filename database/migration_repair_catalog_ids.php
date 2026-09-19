<?php
require_once __DIR__ . '/../config/app.php';

$pdo = Database::connect();
$tables = [
    'products' => 'product_id',
    'services' => 'service_id',
    'packages' => 'package_id',
];

try {
    foreach ($tables as $table => $idColumn) {
        $stmt = $pdo->query("SELECT {$idColumn} FROM {$table} ORDER BY created_at, {$idColumn}");
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!$ids) {
            continue;
        }

        // Move existing values out of the way before assigning unique IDs.
        foreach ($ids as $index => $oldId) {
            $temporaryId = -($index + 1);
            $pdo->prepare("UPDATE {$table} SET {$idColumn} = ? WHERE {$idColumn} = ? LIMIT 1")
                ->execute([$temporaryId, $oldId]);
        }

        foreach ($ids as $index => $oldId) {
            $temporaryId = -($index + 1);
            $newId = $index + 1;
            $pdo->prepare("UPDATE {$table} SET {$idColumn} = ? WHERE {$idColumn} = ? LIMIT 1")
                ->execute([$newId, $temporaryId]);
        }

        $key = $pdo->query("SHOW INDEX FROM {$table} WHERE Key_name = 'PRIMARY'")->fetch();
        if (!$key) {
            $pdo->exec("ALTER TABLE {$table} ADD PRIMARY KEY ({$idColumn})");
        }

        $pdo->exec("ALTER TABLE {$table} MODIFY {$idColumn} INT NOT NULL AUTO_INCREMENT");
    }

    // Restore catalog references in order snapshots using their stored item names.
    foreach (['products' => 'product', 'services' => 'service', 'packages' => 'package'] as $table => $type) {
        $idColumn = rtrim($table, 's') . '_id';
        $pdo->prepare(
            "UPDATE order_items oi JOIN {$table} c ON c.name = oi.item_name
             SET oi.item_ref_id = c.{$idColumn}
             WHERE oi.item_type = ? AND (oi.item_ref_id IS NULL OR oi.item_ref_id <= 0)"
        )->execute([$type]);
    }

    // Invalid legacy review references cannot identify the original product.
    $pdo->exec('UPDATE feedback SET product_id = NULL WHERE product_id <= 0');

    echo "Catalog IDs repaired successfully.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Catalog ID repair failed: {$e->getMessage()}\n");
    exit(1);
}
