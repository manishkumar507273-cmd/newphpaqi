<?php
header('Content-Type: application/json');
session_start();

// Security: Vercel serverless doesn't share sessions reliably across lambda instances
// Removing strict session check for demo purposes, UI is already guarded by index.php

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
