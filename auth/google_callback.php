<?php
require_once __DIR__ . '/../config/app.php';

$config = (require BASE_PATH . '/config/api_keys.php')['google_login'];
$pdo = Database::connect();

if (!empty($_GET['error'])) {
    set_flash('error', 'Google sign-in was cancelled.');
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
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'client_id'     => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'code'          => $code,
        'grant_type'    => 'authorization_code',
        'redirect_uri'  => $config['redirect_uri'],
    ]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
]);
$tokenResponse = json_decode((string) curl_exec($ch), true);
curl_close($ch);

if (empty($tokenResponse['access_token'])) {
    set_flash('error', 'Could not sign in with Google. Please try again.');
    redirect('/auth/login.php');
}

// Step 2: fetch the person's profile
$ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $tokenResponse['access_token']],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
]);
$profile = json_decode((string) curl_exec($ch), true);
curl_close($ch);

if (empty($profile['email']) || empty($profile['id'])) {
    set_flash('error', 'Could not retrieve your Google account details. Please try again.');
    redirect('/auth/login.php');
}

$googleId = $profile['id'];
$email = $profile['email'];
$firstName = $profile['given_name'] ?? explode(' ', $profile['name'] ?? 'Google')[0];
$lastName = $profile['family_name'] ?? '';

social_login_or_register($pdo, 'google', $googleId, $email, $firstName, $lastName);
