<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/google_calendar.php';
require_role('superadmin');

$admin = current_user();
$pdo = Database::connect();

if (!empty($_GET['error'])) {
    set_flash('error', 'Google Calendar connection was cancelled or denied.');
    redirect('/superadmin/settings/index.php');
}

$code = $_GET['code'] ?? '';
if ($code === '') {
    set_flash('error', 'No authorization code received from Google.');
    redirect('/superadmin/settings/index.php');
}

$google = new GoogleCalendarClient();
$success = $google->exchangeCode($code);

if ($success) {
    log_audit($pdo, $admin['user_id'], 'google_calendar_connected');
    set_flash('success', 'Google Calendar connected successfully.');
} else {
    set_flash('error', 'Could not connect Google Calendar. Double-check your client_id/client_secret and redirect_uri in config/api_keys.php.');
}

redirect('/superadmin/settings/index.php');
