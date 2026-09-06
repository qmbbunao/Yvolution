<?php
require_once __DIR__ . '/../config/app.php';

if (is_logged_in()) {
    redirect('/public/index.php');
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];
$validToken = false;
$reset = null;
$pdo = Database::connect();

if ($token !== '') {
    $stmt = $pdo->prepare(
        "SELECT reset_id, email FROM password_resets
         WHERE token = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute([hash('sha256', $token)]);
    $reset = $stmt->fetch();
    $validToken = (bool) $reset;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid session token. Please try again.';
    } elseif (!$validToken) {
        $errors[] = 'This reset link is invalid or has expired. Please request a new one.';
    } else {
        $password = $_POST['password'] ?? '';
        $passwordConfirmation = $_POST['password_confirmation'] ?? '';

        if (strlen($password) < 8) {
            $errors[] = 'Your new password must be at least 8 characters.';
        } elseif ($password !== $passwordConfirmation) {
            $errors[] = 'The passwords do not match.';
        } else {
            $pdo->prepare(
                "UPDATE users SET password_hash = ?, auth_provider = 'local' WHERE email = ?"
            )->execute([password_hash($password, PASSWORD_DEFAULT), $reset['email']]);
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$reset['email']]);
            set_flash('success', 'Your password has been reset. You can now log in.');
            redirect('/auth/login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — Yvolution Custom Apparel</title>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_url('/assets/css/main.css') ?>">
</head>
<body>
<div class="auth-shell">
    <div class="auth-side">
        <img src="<?= BASE_URL ?>/assets/images/logo/logo.png" alt="Yvolution logo" style="height:56px;width:auto;max-width:220px;object-fit:contain;align-self:flex-start;margin-bottom:32px;">
        <h1>New<br>Game Plan.</h1>
        <p>Choose a new password to get back into your Yvolution account.</p>
    </div>
    <div class="auth-form-wrap">
        <form class="auth-form" method="POST" novalidate>
            <h2>Reset Password</h2>
            <p class="subtitle">Choose a new password for your account.</p>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-error"><?= e($err) ?></div>
            <?php endforeach; ?>

            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <?php if ($validToken): ?>
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input class="form-control" type="password" id="password" name="password" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Confirm New Password</label>
                    <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" minlength="8" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn btn-accent btn-block">Save New Password</button>
            <?php else: ?>
                <div class="alert alert-error">This reset link is invalid or has expired. Please request a new one.</div>
                <a href="<?= BASE_URL ?>/auth/forgot_password.php" class="btn btn-accent btn-block">Request New Link</a>
            <?php endif; ?>
        </form>
    </div>
</div>
</body>
</html>
