<?php
header('Content-Type: application/json');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['email']) || !isset($input['scope'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Request Data']);
    exit;
}

$data_dir = __DIR__ . '/../data';
$requests_file = $data_dir . '/requests.json';
if (!is_dir($data_dir)) {
    @mkdir($data_dir, 0777, true);
}
if (!is_dir($data_dir) || !is_writable($data_dir)) {
    $requests_file = sys_get_temp_dir() . '/phpAQI_requests.json';
}

$requests = [];
if (file_exists($requests_file)) {
    $requests = json_decode(file_get_contents($requests_file), true) ?: [];
}

$new_request = [
    'id' => uniqid('req_'),
    'name' => $input['name'] ?? 'Unknown',
    'email' => $input['email'],
    'org' => $input['org'] ?? 'Unknown',
    'industry' => $input['industry'] ?? 'Unknown',
    'use_case' => $input['use_case'] ?? '',
    'scope' => $input['scope'],
    'status' => 'pending',
    'created_at' => date('Y-m-d H:i:s'),
    'user_id' => $_SESSION['user_id'] ?? 'anonymous'
];

array_unshift($requests, $new_request);

if (file_put_contents($requests_file, json_encode($requests, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true, 'request_id' => $new_request['id']]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save request']);
}
