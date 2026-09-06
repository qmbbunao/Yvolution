<?php
require_once __DIR__ . '/../config/app.php';

if (is_logged_in()) {
    redirect('/customer/dashboard.php');
}

$errors = [];
$old = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $old['first_name'] = trim($_POST['first_name'] ?? '');
        $old['last_name']  = trim($_POST['last_name'] ?? '');
        $old['email']      = trim($_POST['email'] ?? '');
        $old['phone']      = trim($_POST['phone'] ?? '');
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        if ($old['first_name'] === '' || $old['last_name'] === '') {
            $errors[] = 'First and last name are required.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $pdo = Database::connect();
            $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $check->execute([$old['email']]);
            if ($check->fetch()) {
                $errors[] = 'An account with that email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users (role_id, first_name, last_name, email, password_hash, phone, status)
                     VALUES (1, ?, ?, ?, ?, ?, 'active')"
                );
                $stmt->execute([$old['first_name'], $old['last_name'], $old['email'], $hash, $old['phone']]);

                $userId = (int) $pdo->lastInsertId();
                log_audit($pdo, $userId, 'account_registered', 'users', $userId);

                set_flash('success', 'Account created! You can now log in.');
                redirect('/auth/login.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account — Yvolution Custom Apparel</title>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_url('/assets/css/main.css') ?>">
</head>
<body>
<div class="auth-shell">
    <div class="auth-side">
        <img src="<?= BASE_URL ?>/assets/images/logo/logo.png" alt="Yvolution logo" style="height:56px;width:auto;max-width:220px;object-fit:contain;align-self:flex-start;margin-bottom:32px;">
        <h1>Join the<br>Roster.</h1>
        <p>Create an account to upload your designs, request quotations, and track every order from print to pickup.</p>
    </div>
    <div class="auth-form-wrap">
        <form class="auth-form" method="POST" novalidate>
            <h2>Create Account</h2>
            <p class="subtitle">It only takes a minute.</p>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-error"><?= e($err) ?></div>
            <?php endforeach; ?>

            <?= csrf_field() ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input class="form-control" type="text" id="first_name" name="first_name" value="<?= e($old['first_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input class="form-control" type="text" id="last_name" name="last_name" value="<?= e($old['last_name']) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input class="form-control" type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input class="form-control" type="text" id="phone" name="phone" value="<?= e($old['phone']) ?>" placeholder="09XXXXXXXXX">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input class="form-control" type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
            </div>

            <button type="submit" class="btn btn-accent btn-block">Create Account</button>

            <div class="auth-divider">or sign up with</div>

            <a href="<?= BASE_URL ?>/auth/google_login.php" class="btn-social">
                <svg viewBox="0 0 24 24"><path fill="#4285F4" d="M23.52 12.27c0-.85-.08-1.67-.22-2.45H12v4.64h6.47c-.28 1.5-1.13 2.77-2.4 3.62v3h3.88c2.27-2.09 3.57-5.17 3.57-8.81z"/><path fill="#34A853" d="M12 24c3.24 0 5.95-1.07 7.93-2.92l-3.88-3c-1.08.72-2.46 1.15-4.05 1.15-3.11 0-5.75-2.1-6.69-4.92H1.3v3.09C3.26 21.3 7.3 24 12 24z"/><path fill="#FBBC05" d="M5.31 14.31c-.24-.72-.38-1.49-.38-2.28s.14-1.56.38-2.28V6.66H1.3C.47 8.24 0 10.06 0 12s.47 3.76 1.3 5.34l4.01-3.03z"/><path fill="#EA4335" d="M12 4.77c1.76 0 3.34.6 4.59 1.79l3.44-3.44C17.94 1.19 15.24 0 12 0 7.3 0 3.26 2.7 1.3 6.66l4.01 3.09C6.25 6.86 8.89 4.77 12 4.77z"/></svg>
                Continue with Google
            </a>
            <a href="<?= BASE_URL ?>/auth/facebook_login.php" class="btn-social">
                <svg viewBox="0 0 24 24"><path fill="#1877F2" d="M24 12.07C24 5.4 18.6 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.7 4.53-4.7 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.95.93-1.95 1.89v2.26h3.32l-.53 3.49h-2.79V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
                Continue with Facebook
            </a>

            <p style="margin-top:20px;font-size:14px;text-align:center;">
                Already have an account? <a href="<?= BASE_URL ?>/auth/login.php" class="text-link">Log in</a>
            </p>
        </form>
    </div>
</div>
</body>
</html>
