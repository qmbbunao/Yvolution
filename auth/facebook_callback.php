<?php
require_once __DIR__ . '/../config/app.php';

$config = (require BASE_PATH . '/config/api_keys.php')['facebook_login'];
$pdo = Database::connect();

if (!empty($_GET['error'])) {
    set_flash('error', 'Facebook sign-in was cancelled.');
    redirect('/auth/login.php');
}

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if ($code === '' || empty($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state']) {
    set_flash('error', 'Invalid sign-in attempt. Please try again.');
    redirect('/auth/login.php');
}
unset($_SESSION['oauth_state']);

// Step 1: exchange the authorization code for an access token
$tokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
    'client_id'     => $config['app_id'],
    'client_secret' => $config['app_secret'],
    'redirect_uri'  => $config['redirect_uri'],
    'code'          => $code,
]);
$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
$tokenResponse = json_decode((string) curl_exec($ch), true);
curl_close($ch);

if (empty($tokenResponse['access_token'])) {
    set_flash('error', 'Could not sign in with Facebook. Please try again.');
    redirect('/auth/login.php');
}

// Step 2: fetch the person's profile (id, name, email)
$profileUrl = 'https://graph.facebook.com/me?' . http_build_query([
    'fields'       => 'id,first_name,last_name,email',
    'access_token' => $tokenResponse['access_token'],
]);
$ch = curl_init($profileUrl);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
$profile = json_decode((string) curl_exec($ch), true);
curl_close($ch);

if (empty($profile['id'])) {
    set_flash('error', 'Could not retrieve your Facebook account details. Please try again.');
    redirect('/auth/login.php');
}

// Some Facebook accounts (e.g. sign-up without a verified email) may not return an email.
// Fall back to a placeholder tied to their Facebook ID so the account can still be created.
$email = $profile['email'] ?? ('fb_' . $profile['id'] . '@placeholder.yvolution.local');
$firstName = $profile['first_name'] ?? 'Facebook';
$lastName = $profile['last_name'] ?? '';

social_login_or_register($pdo, 'facebook', $profile['id'], $email, $firstName, $lastName);
