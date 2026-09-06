<?php
require_once __DIR__ . '/../../config/app.php';
require_role('superadmin');

$superAdmin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Invalid request.');
    redirect('/superadmin/users/index.php');
}

$userId = (int) ($_POST['user_id'] ?? 0);
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$roleName = $_POST['role_name'] === 'superadmin' ? 'superadmin' : 'admin';
$password = $_POST['password'] ?? '';

if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_flash('error', 'Please provide a valid name and email.');
    redirect('/superadmin/users/form.php' . ($userId ? '?id=' . $userId : ''));
}

$roleId = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = ?");
$roleId->execute([$roleName]);
$roleId = $roleId->fetch()['role_id'];

// Check email uniqueness (excluding self when editing)
$check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
$check->execute([$email, $userId]);
if ($check->fetch()) {
    set_flash('error', 'That email is already in use by another account.');
    redirect('/superadmin/users/form.php' . ($userId ? '?id=' . $userId : ''));
}

if ($userId) {
    if ($password !== '') {
        if (strlen($password) < 8) {
            set_flash('error', 'Password must be at least 8 characters.');
            redirect('/superadmin/users/form.php?id=' . $userId);
        }
        $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, role_id=?, password_hash=? WHERE user_id=?")
            ->execute([$firstName, $lastName, $email, $roleId, password_hash($password, PASSWORD_DEFAULT), $userId]);
    } else {
        $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, role_id=? WHERE user_id=?")
            ->execute([$firstName, $lastName, $email, $roleId, $userId]);
    }
    log_audit($pdo, $superAdmin['user_id'], 'admin_account_updated', 'users', $userId, "{$firstName} {$lastName} ({$roleName})");
    set_flash('success', 'Account updated.');
} else {
    if (strlen($password) < 8) {
        set_flash('error', 'Password must be at least 8 characters.');
        redirect('/superadmin/users/form.php');
    }
    $stmt = $pdo->prepare(
        "INSERT INTO users (role_id, first_name, last_name, email, password_hash, status, email_verified)
         VALUES (?, ?, ?, ?, ?, 'active', 1)"
    );
    $stmt->execute([$roleId, $firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT)]);
    $newId = (int) $pdo->lastInsertId();
    log_audit($pdo, $superAdmin['user_id'], 'admin_account_created', 'users', $newId, "{$firstName} {$lastName} ({$roleName})");
    set_flash('success', 'Account created.');
}

redirect('/superadmin/users/index.php');
