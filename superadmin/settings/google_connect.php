<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/google_calendar.php';
require_role('superadmin');

$apiKeys = require BASE_PATH . '/config/api_keys.php';
if (str_starts_with($apiKeys['google_calendar']['client_id'], 'your-')) {
    set_flash('error', 'Add your Google client_id and client_secret to config/api_keys.php first.');
    redirect('/superadmin/settings/index.php');
}

$google = new GoogleCalendarClient();
header('Location: ' . $google->getAuthUrl());
exit;
