<?php
require_once __DIR__ . '/../config/app.php';

$config = (require BASE_PATH . '/config/api_keys.php')['google_login'];

if (str_starts_with($config['client_id'], 'your-')) {
    set_flash('error', 'Google Sign-In is not configured yet. Please use email/password, or contact the site admin.');
    redirect('/auth/login.php');
}

$_SESSION['oauth_state'] = bin2hex(random_bytes(16));

$params = [
    'client_id'     => $config['client_id'],
    'redirect_uri'  => $config['redirect_uri'],
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $_SESSION['oauth_state'],
    'prompt'        => 'select_account',
];

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit;
