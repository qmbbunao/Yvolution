<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/google_calendar.php';
require_role('superadmin');

$admin = current_user();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    (new GoogleCalendarClient())->disconnect();
    log_audit($pdo, $admin['user_id'], 'google_calendar_disconnected');
    set_flash('success', 'Google Calendar disconnected.');
}

redirect('/superadmin/settings/index.php');
