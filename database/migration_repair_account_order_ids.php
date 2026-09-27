<?php
/**
 * Repair legacy tables created without primary keys or AUTO_INCREMENT.
 * Run once from the project root: php database/migration_repair_account_order_ids.php
 */
require_once __DIR__ . '/../config/app.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this migration from the command line.');
}

$pdo = Database::connect();
$backupSuffix = 'before_account_order_id_repair';
$tablesToBackup = [
    'users', 'orders', 'order_items', 'order_status_history',
    'audit_logs', 'design_uploads', 'feedback', 'returns', 'testimonials',
];

function has_primary_key(PDO $pdo, string $table): bool
{
    $stmt = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = 'PRIMARY'");
    return (bool) $stmt->fetch();
}

function has_auto_increment(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    $columnInfo = $stmt->fetch();
    return $columnInfo && stripos($columnInfo['Extra'], 'auto_increment') !== false;
}

if (has_primary_key($pdo, 'users') && has_auto_increment($pdo, 'users', 'user_id')
    && has_primary_key($pdo, 'orders') && has_auto_increment($pdo, 'orders', 'order_id')) {
    echo "Account and order IDs are already repaired.\n";
    exit(0);
}

foreach ($tablesToBackup as $table) {
    $backup = $table . '_' . $backupSuffix;
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$backup}` LIKE `{$table}`");
    $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$backup}`")->fetchColumn();
    if ($count === 0) {
        $pdo->exec("INSERT INTO `{$backup}` SELECT * FROM `{$table}`");
    }
}

$users = $pdo->query(
    "SELECT u.user_id, u.email, u.created_at, r.role_name
     FROM users u JOIN roles r ON r.role_id = u.role_id
     ORDER BY u.created_at, u.email"
)->fetchAll();
$orders = $pdo->query(
    'SELECT order_id, order_code, customer_id, created_at FROM orders ORDER BY created_at, order_code'
)->fetchAll();

if (!$users || !$orders) {
    throw new RuntimeException('Expected existing users and orders; no records were changed.');
}
if (count(array_unique(array_column($users, 'email'))) !== count($users)) {
    throw new RuntimeException('Duplicate user emails prevent safe account ID repair.');
}
if (count(array_unique(array_column($orders, 'order_code'))) !== count($orders)) {
    throw new RuntimeException('Duplicate order codes prevent safe order ID repair.');
}
foreach (array_merge($users, $orders) as $row) {
    $idColumn = array_key_exists('email', $row) ? 'user_id' : 'order_id';
    if ((int) $row[$idColumn] !== 0) {
        throw new RuntimeException('Unexpected nonzero IDs found; migration stopped without changing records.');
    }
}

$itemRows = $pdo->query(
    'SELECT order_item_id, order_id, item_type, item_ref_id, item_name, size, color, quantity, unit_price, subtotal
     FROM order_items ORDER BY order_item_id, item_name'
)->fetchAll();
$historyRows = $pdo->query(
    'SELECT history_id, order_id, status, notes, changed_at
     FROM order_status_history ORDER BY changed_at, history_id'
)->fetchAll();

if (count($itemRows) !== count($orders) || count($historyRows) !== count($orders)) {
    throw new RuntimeException('Order item/history rows cannot be matched one-to-one; migration stopped without changing records.');
}
foreach ($itemRows as $item) {
    if ((int) $item['order_item_id'] !== 0 || (int) $item['order_id'] !== 0) {
        throw new RuntimeException('Unexpected order item IDs/references found; migration stopped.');
    }
}
foreach ($historyRows as $history) {
    if ((int) $history['history_id'] !== 0 || (int) $history['order_id'] !== 0) {
        throw new RuntimeException('Unexpected order history IDs/references found; migration stopped.');
    }
}
$itemSignatures = array_unique(array_map(
    static fn(array $item): string => json_encode([
        $item['item_type'], $item['item_ref_id'], $item['item_name'], $item['size'], $item['color'],
        $item['quantity'], $item['unit_price'], $item['subtotal'],
    ]),
    $itemRows
));
if (count($itemSignatures) > 1) {
    throw new RuntimeException('Different zero-ID order items cannot be safely paired; migration stopped.');
}

$customers = array_values(array_filter($users, static fn(array $user): bool => $user['role_name'] === 'customer'));
if (!$customers) {
    throw new RuntimeException('No customer account is available to repair legacy order ownership.');
}

$pdo->beginTransaction();
try {
    $userIdByEmail = [];
    foreach ($users as $index => $user) {
        $temporaryId = -($index + 1);
        $stmt = $pdo->prepare('UPDATE users SET user_id = ? WHERE email = ? AND user_id = 0');
        $stmt->execute([$temporaryId, $user['email']]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Could not isolate a user row by email.');
        }
        $userIdByEmail[$user['email']] = $index + 1;
    }
    foreach ($users as $index => $user) {
        $pdo->prepare('UPDATE users SET user_id = ? WHERE user_id = ?')
            ->execute([$index + 1, -($index + 1)]);
    }

    $customerForOrder = [];
    $orderIdByCode = [];
    foreach ($orders as $index => $order) {
        $owner = $customers[0];
        foreach ($customers as $customer) {
            if ($customer['created_at'] <= $order['created_at']) {
                $owner = $customer;
            }
        }
        $newOrderId = $index + 1;
        $temporaryId = -$newOrderId;
        $stmt = $pdo->prepare('UPDATE orders SET order_id = ? WHERE order_code = ? AND order_id = 0');
        $stmt->execute([$temporaryId, $order['order_code']]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Could not isolate an order row by its order code.');
        }
        $pdo->prepare('UPDATE orders SET order_id = ?, customer_id = ? WHERE order_id = ?')
            ->execute([$newOrderId, $userIdByEmail[$owner['email']], $temporaryId]);
        $customerForOrder[$order['order_code']] = $userIdByEmail[$owner['email']];
        $orderIdByCode[$order['order_code']] = $newOrderId;
    }

    foreach ($itemRows as $index => $item) {
        $temporaryId = -($index + 1);
        $newOrderId = $index + 1;
        $stmt = $pdo->query('SELECT order_item_id FROM order_items WHERE order_item_id = 0 LIMIT 1');
        if (!$stmt->fetch()) {
            throw new RuntimeException('Could not isolate an order item row.');
        }
        $pdo->prepare('UPDATE order_items SET order_item_id = ? WHERE order_item_id = 0 LIMIT 1')
            ->execute([$temporaryId]);
        $pdo->prepare('UPDATE order_items SET order_item_id = ?, order_id = ? WHERE order_item_id = ?')
            ->execute([$index + 1, $newOrderId, $temporaryId]);
    }

    foreach ($historyRows as $index => $history) {
        $closestOrder = null;
        $closestDistance = PHP_INT_MAX;
        foreach ($orders as $order) {
            $distance = abs(strtotime($history['changed_at']) - strtotime($order['created_at']));
            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $closestOrder = $order;
            }
        }
        if ($closestDistance > 300) {
            throw new RuntimeException('An order history timestamp is too far from any order; migration stopped.');
        }
        $temporaryId = -($index + 1);
        $pdo->prepare('UPDATE order_status_history SET history_id = ? WHERE history_id = 0 LIMIT 1')
            ->execute([$temporaryId]);
        $pdo->prepare('UPDATE order_status_history SET history_id = ?, order_id = ? WHERE history_id = ?')
            ->execute([$index + 1, $orderIdByCode[$closestOrder['order_code']], $temporaryId]);
    }

    $pdo->exec('UPDATE audit_logs SET user_id = NULL WHERE user_id = 0');
    $pdo->exec('UPDATE testimonials SET customer_id = NULL WHERE customer_id = 0');
    foreach (['design_uploads', 'feedback', 'returns'] as $table) {
        $pdo->exec(
            "UPDATE `{$table}` child JOIN orders o ON o.order_id = child.order_id
             SET child.customer_id = o.customer_id
             WHERE child.customer_id = 0 AND child.order_id > 0"
        );
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$pdo->exec('ALTER TABLE users MODIFY user_id INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (user_id), ADD UNIQUE KEY uq_users_email (email)');
$pdo->exec('ALTER TABLE orders MODIFY order_id INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (order_id), ADD UNIQUE KEY uq_orders_order_code (order_code)');
$pdo->exec('ALTER TABLE order_items MODIFY order_item_id INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (order_item_id)');
$pdo->exec('ALTER TABLE order_status_history MODIFY history_id INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (history_id)');

echo "Account/order IDs repaired. Existing orders were assigned to the most recently registered customer at each order timestamp.\n";
echo "Backups were created with suffix: _{$backupSuffix}\n";