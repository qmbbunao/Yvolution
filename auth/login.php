<?php
require_once __DIR__ . '/../config/app.php';

if (is_logged_in()) {
    redirect('/public/index.php');
}

$errors = [];
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $oldEmail = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($oldEmail === '' || $password === '') {
            $errors[] = 'Please enter both email and password.';
        } else {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                "SELECT u.user_id, u.first_name, u.last_name, u.email, u.password_hash, u.status, u.auth_provider, r.role_name
                 FROM users u JOIN roles r ON u.role_id = r.role_id
                 WHERE u.email = ?"
            );
            $stmt->execute([$oldEmail]);
            $account = $stmt->fetch();

            if (!$account) {
                $errors[] = 'Incorrect email or password.';
            } elseif ($account['password_hash'] === null) {
                $provider = ucfirst($account['auth_provider']);
                $errors[] = "This account was created with {$provider} Sign-In. Please use the \"{$provider}\" button below instead.";
            } elseif (!password_verify($password, $account['password_hash'])) {
                $errors[] = 'Incorrect email or password.';
            } elseif ($account['status'] !== 'active') {
                $errors[] = 'This account is currently inactive. Please contact support.';
            } else {
                // Regenerate session id on privilege change to prevent fixation
                session_regenerate_id(true);

                $_SESSION['user'] = [
                    'user_id' => (int) $account['user_id'],
                    'role'    => $account['role_name'],
                    'name'    => $account['first_name'] . ' ' . $account['last_name'],
                    'email'   => $account['email'],
                ];

                log_audit($pdo, (int) $account['user_id'], 'login');

                switch ($account['role_name']) {
                    case 'superadmin':
                        redirect('/superadmin/dashboard.php');
                        break;
                    case 'admin':
                        redirect('/admin/dashboard.php');
                        break;
                    default:
                        redirect('/public/index.php');
                }
            }
        }
    }
}

$successMsg = get_flash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log In — Yvolution Custom Apparel</title>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_url('/assets/css/main.css') ?>">
</head>
<body>
<div class="auth-shell">
    <div class="auth-side">
        <img src="<?= BASE_URL ?>/assets/images/logo/logo.png" alt="Yvolution logo" style="height:56px;width:auto;max-width:220px;object-fit:contain;align-self:flex-start;margin-bottom:32px;">
        <h1>Back in<br>the Game.</h1>
        <p>Log in to track your orders, review quotations, and manage your custom apparel requests.</p>
    </div>
    <div class="auth-form-wrap">
        <form class="auth-form" method="POST" novalidate>
            <h2>Log In</h2>
            <p class="subtitle">Welcome back.</p>

            <?php if ($successMsg): ?>
                <div class="alert alert-success"><?= e($successMsg) ?></div>
            <?php endif; ?>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-error"><?= e($err) ?></div>
            <?php endforeach; ?>

            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input class="form-control" type="email" id="email" name="email" value="<?= e($oldEmail) ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input class="form-control" type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-accent btn-block">Log In</button>
            <a href="<?= BASE_URL ?>/auth/forgot_password.php" class="auth-forgot-link">Forgot your password?</a>

            <div class="auth-divider">or continue with</div>

            <a href="<?= BASE_URL ?>/auth/google_login.php" class="btn-social">
                <svg viewBox="0 0 24 24"><path fill="#4285F4" d="M23.52 12.27c0-.85-.08-1.67-.22-2.45H12v4.64h6.47c-.28 1.5-1.13 2.77-2.4 3.62v3h3.88c2.27-2.09 3.57-5.17 3.57-8.81z"/><path fill="#34A853" d="M12 24c3.24 0 5.95-1.07 7.93-2.92l-3.88-3c-1.08.72-2.46 1.15-4.05 1.15-3.11 0-5.75-2.1-6.69-4.92H1.3v3.09C3.26 21.3 7.3 24 12 24z"/><path fill="#FBBC05" d="M5.31 14.31c-.24-.72-.38-1.49-.38-2.28s.14-1.56.38-2.28V6.66H1.3C.47 8.24 0 10.06 0 12s.47 3.76 1.3 5.34l4.01-3.03z"/><path fill="#EA4335" d="M12 4.77c1.76 0 3.34.6 4.59 1.79l3.44-3.44C17.94 1.19 15.24 0 12 0 7.3 0 3.26 2.7 1.3 6.66l4.01 3.09C6.25 6.86 8.89 4.77 12 4.77z"/></svg>
                Continue with Google
            </a>
            <a href="<?= BASE_URL ?>/auth/facebook_login.php" class="btn-social">
                <svg viewBox="0 0 24 24"><path fill="#1877F2" d="M24 12.07C24 5.4 18.6 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.7 4.53-4.7 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.95.93-1.95 1.89v2.26h3.32l-.53 3.49h-2.79V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
                Continue with Facebook
            </a>

            <p style="margin-top:20px;font-size:14px;text-align:center;">
                Don't have an account? <a href="<?= BASE_URL ?>/auth/register.php" class="text-link">Sign up</a>
            </p>
        </form>
    </div>
</div>
</body>
</html>
