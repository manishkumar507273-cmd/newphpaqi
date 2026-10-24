<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth_guard.php';

// Strict administrator verification
requireAdminAuth();

$data_dir = __DIR__ . '/../data';
$requests_file = $data_dir . '/requests.json';
if (!file_exists($requests_file)) {
    $temp_file = sys_get_temp_dir() . '/phpAQI_requests.json';
    if (file_exists($temp_file)) {
        $requests_file = $temp_file;
    }
}
if (!file_exists($requests_file)) {
    echo json_encode([]);
    exit;
}

$requests = json_decode(file_get_contents($requests_file), true) ?: [];
echo json_encode($requests);
