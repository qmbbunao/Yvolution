<?php
/**
 * ONE-TIME SETUP SCRIPT
 * Run this once in your browser (e.g. http://localhost/yvolution/database/seed_superadmin.php)
 * right after importing schema.sql. It creates the first Super Admin account.
 *
 * DELETE THIS FILE after you've run it — do not leave it on a live server.
 */

require_once __DIR__ . '/../config/database.php';

$email    = 'superadmin@yvolution.com';
$password = 'SuperAdmin123!'; // change this after first login
$first    = 'Super';
$last     = 'Admin';

try {
    $pdo = Database::connect();

    $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->execute([$email]);

    if ($check->fetch()) {
        echo "A user with email {$email} already exists. Nothing was created.<br>";
        echo "Delete this file (database/seed_superadmin.php) now.";
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status, email_verified)
         VALUES (3, ?, ?, ?, ?, 'active', 1)"
    );
    $stmt->execute([$first, $last, $email, $hash]);

    echo "<h2>Super Admin created successfully.</h2>";
    echo "<p><strong>Email:</strong> {$email}<br>";
    echo "<strong>Temporary password:</strong> {$password}</p>";
    echo "<p style='color:red;font-weight:bold;'>IMPORTANT: Log in, change this password immediately,
          then DELETE this file (database/seed_superadmin.php).</p>";

} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
