<?php
require_once __DIR__ . '/../config/app.php';
require_once BASE_PATH . '/includes/mailer.php';

if (is_logged_in()) {
    redirect('/public/index.php');
}

$errors = [];
$sent = false;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $pdo = Database::connect();
            $stmt = $pdo->prepare(
                "SELECT user_id, first_name, last_name, email, password_hash
                 FROM users WHERE email = ? LIMIT 1"
            );
            $stmt->execute([$email]);
            $account = $stmt->fetch();

            if ($account) {
                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                $pdo->prepare("DELETE FROM password_resets WHERE email = ? OR expires_at < NOW()")
                    ->execute([$email]);
                $pdo->prepare(
                    "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)"
                )->execute([$email, $tokenHash, $expiresAt]);

                $resetUrl = APP_URL . '/auth/reset_password.php?token=' . urlencode($rawToken);
                $recipientName = trim($account['first_name'] . ' ' . $account['last_name']);
                $body = email_template(
                    'Reset your password',
                    '<p>We received a request to reset your Yvolution account password.</p>'
                    . '<p><a href="' . e($resetUrl) . '" style="display:inline-block;padding:12px 20px;background:#ff4b23;color:#fff;text-decoration:none;font-weight:bold;">Reset Password</a></p>'
                    . '<p>This link expires in one hour. If you did not request this, you can ignore this email.</p>'
                );

                $mailSent = (new Mailer())->send(
                    $account['email'],
                    $recipientName,
                    'Reset your Yvolution password',
                    $body
                );
                if (!$mailSent) {
                    error_log('Password reset email could not be sent to ' . $account['email']);
                }
            }

            // Do not reveal whether an email is registered.
            $sent = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — Yvolution Custom Apparel</title>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_url('/assets/css/main.css') ?>">
</head>
<body>
<div class="auth-shell">
    <div class="auth-side">
        <img src="<?= BASE_URL ?>/assets/images/logo/logo.png" alt="Yvolution logo" style="height:56px;width:auto;max-width:220px;object-fit:contain;align-self:flex-start;margin-bottom:32px;">
        <h1>Reset<br>and Reload.</h1>
        <p>We will send a secure password reset link to your email address.</p>
    </div>
    <div class="auth-form-wrap">
        <form class="auth-form" method="POST" novalidate>
            <h2>Forgot Password?</h2>
            <p class="subtitle">Enter the email connected to your account.</p>

            <?php if ($sent): ?>
                <div class="alert alert-success">If an account matches that email, a password reset link has been sent. Check your Gmail inbox and spam folder.</div>
            <?php endif; ?>
            <?php foreach ($errors as $err): ?>
                <div class="alert alert-error"><?= e($err) ?></div>
            <?php endforeach; ?>

            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input class="form-control" type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="email" required>
            </div>
            <button type="submit" class="btn btn-accent btn-block">Send Reset Link</button>
            <p style="margin-top:20px;text-align:center;font-size:14px;"><a href="<?= BASE_URL ?>/auth/login.php" class="text-link">Back to Log In</a></p>
        </form>
    </div>
</div>
</body>
</html>
