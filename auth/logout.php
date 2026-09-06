<?php
require_once __DIR__ . '/../config/app.php';

if (is_logged_in()) {
    log_audit(Database::connect(), current_user()['user_id'], 'logout');
}

$_SESSION = [];
session_destroy();

header('Location: ' . BASE_URL . '/public/index.php');
exit;
