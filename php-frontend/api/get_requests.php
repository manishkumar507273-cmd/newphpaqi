<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth_guard.php';

// Strict administrator verification
requireAdminAuth();

$data_dir = __DIR__ . '/../data';
$requests_file = $data_dir . '/requests.json';
if (!file_exists($requests_file) || (file_exists($requests_file) && filesize($requests_file) < 5)) {
    $temp_file = sys_get_temp_dir() . '/phpAQI_requests.json';
    if (file_exists($temp_file) && filesize($temp_file) > 5) {
        $requests_file = $temp_file;
    } elseif (file_exists($data_dir . '/requests.example.json') && filesize($data_dir . '/requests.example.json') > 5) {
        $requests_file = $data_dir . '/requests.example.json';
    }
}
if (!file_exists($requests_file)) {
    echo json_encode([]);
    exit;
}

$requests = json_decode(file_get_contents($requests_file), true) ?: [];
echo json_encode($requests);
