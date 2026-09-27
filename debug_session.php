<?php
session_name('YVOLUTION_SID');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
header('X-Session-Id: ' . session_id());
header('X-Session-Start: ' . (session_start() ? 'true' : 'false'));
header('X-Save-Path: ' . session_save_path());
header('X-Save-Path-Writable: ' . (is_writable(session_save_path()) ? 'true' : 'false'));
header('X-Use-Cookies: ' . (ini_get('session.use_cookies') ? 'true' : 'false'));
header('X-Use-Trans-Sid: ' . (ini_get('session.use_trans_sid') ? 'true' : 'false'));
header('X-Cookie-Params: ' . json_encode(session_get_cookie_params()));
$_SESSION['debug'] = 'ok';
echo 'session_ok';
