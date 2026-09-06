<?php
require_once __DIR__ . '/../config/app.php';

$config = (require BASE_PATH . '/config/api_keys.php')['facebook_login'];

if (str_starts_with($config['app_id'], 'your-')) {
    set_flash('error', 'Facebook Login is not configured yet. Please use email/password, or contact the site admin.');
    redirect('/auth/login.php');
}

$_SESSION['oauth_state'] = bin2hex(random_bytes(16));

$params = [
    'client_id'     => $config['app_id'],
    'redirect_uri'  => $config['redirect_uri'],
    'response_type' => 'code',
    'scope'         => 'email,public_profile',
    'state'         => $_SESSION['oauth_state'],
];

header('Location: https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query($params));
exit;
