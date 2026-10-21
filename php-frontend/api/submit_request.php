<?php
date_default_timezone_set('Asia/Kolkata');
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

$industry   = trim($input['industry'] ?? '');
$id_number  = trim($input['id_number'] ?? '');
$proof_name = trim($input['proof_name'] ?? '');
$proof_data = $input['proof_data'] ?? '';

// Mandatory Official Verification: ID Proof document required for Industry categories
if (!empty($industry)) {
    if (empty($proof_data)) {
        http_response_code(422);
        echo json_encode(['error' => 'Official ID card or proof document upload is strictly mandatory.']);
        exit;
    }
}

// Intended Use Case Validation
$use_case = trim($input['use_case'] ?? '');
if (empty($use_case)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please provide an intended use case describing your operational or research requirements.']);
    exit;
}

$data_dir = __DIR__ . '/../data';
$proofs_dir = $data_dir . '/proofs';
$requests_file = $data_dir . '/requests.json';

if (!is_dir($data_dir)) {
    @mkdir($data_dir, 0777, true);
}
if (!is_dir($proofs_dir)) {
    @mkdir($proofs_dir, 0777, true);
}

// Fallback to temp dir only if local data directory cannot be written to
if (!file_exists($requests_file) && !@touch($requests_file)) {
    $requests_file = sys_get_temp_dir() . '/phpAQI_requests.json';
    $proofs_dir = sys_get_temp_dir() . '/phpAQI_proofs';
    if (!is_dir($proofs_dir)) {
        @mkdir($proofs_dir, 0777, true);
    }
}

$new_id = uniqid('req_');
$proof_file_path = '';
$clean_name = '';

// Decode and save proof document securely
if (!empty($proof_data) && !empty($proof_name)) {
    if (strpos($proof_data, ';base64,') !== false) {
        list(, $data_part) = explode(';base64,', $proof_data);
    } else {
        $data_part = $proof_data;
    }
    
    $binary = base64_decode($data_part);
    if ($binary !== false) {
        $clean_name = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $proof_name);
        $unique_name = $new_id . '_' . $clean_name;
        $save_path = $proofs_dir . '/' . $unique_name;
        if (file_put_contents($save_path, $binary)) {
            $proof_file_path = 'data/proofs/' . $unique_name;
        }
    }
}

$requests = [];
if (file_exists($requests_file)) {
    $requests = json_decode(file_get_contents($requests_file), true) ?: [];
}

$new_request = [
    'id'             => $new_id,
    'name'           => $input['name'] ?? 'Unknown',
    'email'          => $input['email'],
    'org'            => $input['org'] ?? 'Unknown',
    'industry'       => $industry ?: 'Unknown',
    'id_number'      => $id_number,
    'proof_file'     => $proof_file_path,
    'proof_name'     => $clean_name ?: $proof_name,
    'proof_data'     => $proof_data,
    'use_case'       => $use_case,
    'description'    => $use_case,
    'scope'          => $input['scope'],
    'target_date'    => $input['target_date'] ?? date('Y-m-d', strtotime('-1 day')),
    'telemetry_type' => $input['telemetry_type'] ?? 'aqi',
    'status'         => 'pending',
    'created_at'     => !empty($input['created_at']) ? $input['created_at'] : date('c'),
    'user_id'        => $_SESSION['user_id'] ?? 'anonymous'
];

array_unshift($requests, $new_request);

$saved = file_put_contents($requests_file, json_encode($requests, JSON_PRETTY_PRINT));

if (!$saved) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save request']);
    exit;
}

echo json_encode([
    'success'     => true,
    'request_id'  => $new_request['id'],
    'proof_file'  => $proof_file_path,
    'proof_name'  => $new_request['proof_name'],
    'use_case'    => $use_case,
    'description' => $use_case,
    'id_number'   => $id_number
]);
