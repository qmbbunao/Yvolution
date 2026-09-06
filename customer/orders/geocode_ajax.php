<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/geocode.php';
require_role('customer');

header('Content-Type: application/json');

$address = trim($_GET['address'] ?? '');
if ($address === '') {
    echo json_encode(['success' => false, 'message' => 'No address provided.']);
    exit;
}

$result = geocode_address($address);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Address not found. Try adding more detail (barangay, city).']);
    exit;
}

echo json_encode(['success' => true] + $result);
