<?php
header('Content-Type: application/json');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Request ID is required']);
    exit;
}

$target_id = $input['id'];
$deleted = false;

// Check primary data directory
$data_dir = __DIR__ . '/../data';
$files_to_check = [
    $data_dir . '/requests.json',
    sys_get_temp_dir() . '/phpAQI_requests.json'
];

foreach ($files_to_check as $file_path) {
    if (file_exists($file_path)) {
        $requests = json_decode(file_get_contents($file_path), true) ?: [];
        $orig_count = count($requests);
        $requests = array_values(array_filter($requests, function($req) use ($target_id) {
            return isset($req['id']) && $req['id'] !== $target_id;
        }));
        
        if (count($requests) < $orig_count) {
            file_put_contents($file_path, json_encode($requests, JSON_PRETTY_PRINT));
            $deleted = true;
        }
    }
}

echo json_encode(['success' => true, 'deleted_from_backend' => $deleted]);
