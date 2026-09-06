<?php
/**
 * Shared helper functions.
 */

/** Translate a PHP $_FILES[...]['error'] code into a human-readable message. */
function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That file is too large.',
        UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary folder for uploads.',
        UPLOAD_ERR_CANT_WRITE => 'Server failed to write the uploaded file to disk.',
        UPLOAD_ERR_EXTENSION => 'A server extension blocked this upload.',
        default => 'Unknown upload error (code ' . $code . ').',
    };
}

/**
 * Shared find-or-create-and-log-in logic for social auth (Google/Facebook).
 * Ends the request via redirect() — never returns.
 */
function social_login_or_register(PDO $pdo, string $provider, string $providerId, string $email, string $firstName, string $lastName): void
{
    $providerColumn = $provider . '_id'; // 'google_id' or 'facebook_id'

    // 1. Already linked to this provider?
    $stmt = $pdo->prepare(
        "SELECT u.user_id, u.first_name, u.last_name, u.email, u.status, r.role_name
         FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.{$providerColumn} = ?"
    );
    $stmt->execute([$providerId]);
    $account = $stmt->fetch();

    if (!$account) {
        // 2. Existing local account with the same email? Link this provider to it.
        $stmt = $pdo->prepare(
            "SELECT u.user_id, u.first_name, u.last_name, u.email, u.status, r.role_name
             FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.email = ?"
        );
        $stmt->execute([$email]);
        $account = $stmt->fetch();

        if ($account) {
            $pdo->prepare("UPDATE users SET {$providerColumn} = ? WHERE user_id = ?")->execute([$providerId, $account['user_id']]);
        } else {
            // 3. Brand new customer account
            $stmt = $pdo->prepare(
                "INSERT INTO users (role_id, first_name, last_name, email, password_hash, auth_provider, {$providerColumn}, status, email_verified)
                 VALUES (1, ?, ?, ?, NULL, ?, ?, 'active', 1)"
            );
            $stmt->execute([$firstName, $lastName ?: '.', $email, $provider, $providerId]);
            $newId = (int) $pdo->lastInsertId();
            log_audit($pdo, $newId, 'account_registered_via_' . $provider, 'users', $newId);

            $account = ['user_id' => $newId, 'first_name' => $firstName, 'last_name' => $lastName, 'email' => $email, 'status' => 'active', 'role_name' => 'customer'];
        }
    }

    if ($account['status'] !== 'active') {
        set_flash('error', 'This account is currently inactive. Please contact support.');
        redirect('/auth/login.php');
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'user_id' => (int) $account['user_id'],
        'role'    => $account['role_name'],
        'name'    => trim($account['first_name'] . ' ' . $account['last_name']),
        'email'   => $account['email'],
    ];

    log_audit($pdo, (int) $account['user_id'], 'login_via_' . $provider);

    match ($account['role_name']) {
        'superadmin' => redirect('/superadmin/dashboard.php'),
        'admin'      => redirect('/admin/dashboard.php'),
        default      => redirect('/public/index.php'),
    };
}

/** Escape output safely for HTML. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Build an asset URL with a cache-busting version based on the file's last modified time. */
function asset_url(string $relativePath): string
{
    $fullPath = BASE_PATH . $relativePath;
    $version = file_exists($fullPath) ? filemtime($fullPath) : time();
    return BASE_URL . $relativePath . '?v=' . $version;
}

/** Redirect helper. */
function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/** Flash message helpers (one-time session messages). */
function set_flash(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function get_flash(string $key): ?string
{
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

/** CSRF token helpers. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/** Generate a unique order code, e.g. YVO-2026-00123 */
function generate_order_code(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM orders WHERE YEAR(created_at) = $year");
    $count = (int) $stmt->fetch()['cnt'] + 1;
    return sprintf('YVO-%s-%05d', $year, $count);
}

/** Write an audit log entry (super admin visibility). */
function log_audit(PDO $pdo, ?int $userId, string $action, ?string $table = null, ?int $recordId = null, ?string $details = null): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, table_affected, record_id, details, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $action, $table, $recordId, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
}

/** Format currency (PHP Peso). */
function money(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

/** Wrap plain content in a simple branded HTML email shell. */
function email_template(string $heading, string $bodyHtml): string
{
    return '
    <div style="font-family: Arial, sans-serif; max-width:560px; margin:0 auto;">
        <div style="background:#0a0e1a; padding:24px; text-align:center;">
            <h1 style="color:#ff4b23; margin:0; font-size:22px; letter-spacing:1px;">YVOLUTION CUSTOM APPAREL</h1>
        </div>
        <div style="padding:28px; background:#f4f2ed; color:#0f1626;">
            <h2 style="margin-top:0;">' . $heading . '</h2>
            ' . $bodyHtml . '
        </div>
        <div style="padding:16px; text-align:center; font-size:12px; color:#9aa3b8;">
            &copy; ' . date('Y') . ' Yvolution Custom Apparel
        </div>
    </div>';
}

/** Record an order status change into history + update the order row. */
function update_order_status(PDO $pdo, int $orderId, string $status, ?int $changedBy = null, ?string $notes = null): void
{
    $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?")->execute([$status, $orderId]);
    $pdo->prepare(
        "INSERT INTO order_status_history (order_id, status, notes, changed_by) VALUES (?, ?, ?, ?)"
    )->execute([$orderId, $status, $notes, $changedBy]);
}
