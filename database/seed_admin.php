<?php
/**
 * ONE-TIME SETUP SCRIPT
 * Run this once in your browser (e.g. http://localhost/yvolution/database/seed_admin.php)
 * to create a test Admin account. Admin is a lower-privilege role than Super
 * Admin — it can manage orders/catalog/inventory/customers/homepage content,
 * but cannot manage users, API settings, analytics, audit logs, or backups.
 *
 * DELETE THIS FILE after you've run it — do not leave it on a live server.
 */

require_once __DIR__ . '/../config/database.php';

$email    = 'admin@yvolution.com';
$password = 'Admin123!'; // change this after first login
$first    = 'Shop';
$last     = 'Admin';

try {
    $pdo = Database::connect();

    $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->execute([$email]);

    if ($check->fetch()) {
        echo "A user with email {$email} already exists. Nothing was created.<br>";
        echo "Delete this file (database/seed_admin.php) now.";
        exit;
    }

    $roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'admin'");
    $roleStmt->execute();
    $roleId = $roleStmt->fetch()['role_id'] ?? 2;

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status, email_verified)
         VALUES (?, ?, ?, ?, ?, 'active', 1)"
    );
    $stmt->execute([$roleId, $first, $last, $email, $hash]);

    echo "<h2>Admin account created successfully.</h2>";
    echo "<p><strong>Email:</strong> {$email}<br>";
    echo "<strong>Temporary password:</strong> {$password}</p>";
    echo "<p style='color:red;font-weight:bold;'>IMPORTANT: Log in, change this password immediately,
          then DELETE this file (database/seed_admin.php).</p>";

} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
