<?php
/**
 * Session-based authentication & role guard helpers.
 * Session shape once logged in:
 *   $_SESSION['user'] = [
 *       'user_id' => int,
 *       'role'    => 'customer' | 'admin' | 'superadmin',
 *       'name'    => string,
 *       'email'   => string,
 *   ]
 */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function has_role(string ...$roles): bool
{
    $user = current_user();
    return $user !== null && in_array($user['role'], $roles, true);
}

/** Call at the top of any page that requires login. */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect('/auth/login.php');
    }
}

/** Call at the top of any page restricted to specific roles. */
function require_role(string ...$roles): void
{
    require_login();
    if (!has_role(...$roles)) {
        http_response_code(403);
        set_flash('error', 'You do not have permission to access that page.');
        redirect('/public/403.php');
    }
}
