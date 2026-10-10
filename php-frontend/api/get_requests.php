<?php
header('Content-Type: application/json');
session_start();

// Security: Check if admin
if (!isset($_SESSION['user_name']) || strpos(strtolower($_SESSION['user_name']), 'admin') === false) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized Access']);
    exit;
}

$data_dir = __DIR__ . '/../data';
$requests_file = $data_dir . '/requests.json';
if (!is_dir($data_dir) || !is_writable($data_dir)) {
    $requests_file = sys_get_temp_dir() . '/phpAQI_requests.json';
}
if (!file_exists($requests_file)) {
    echo json_encode([]);
    exit;
}

$requests = json_decode(file_get_contents($requests_file), true) ?: [];
echo json_encode($requests);
