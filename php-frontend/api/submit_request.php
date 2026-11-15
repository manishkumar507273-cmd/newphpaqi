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

// Fallback to temp dir if local data directory is truly read-only (standard on Vercel Serverless)
$can_write_data = (file_exists($requests_file) && is_writable($requests_file)) || (!file_exists($requests_file) && @touch($requests_file));
if (!$can_write_data) {
    $probe = $data_dir . '/.write_probe_' . uniqid();
    if (@file_put_contents($probe, '1') !== false) {
        @unlink($probe);
        $can_write_data = true;
    }
}

if (!$can_write_data) {
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
    // 1. Strict extension whitelist validation
    $ext = strtolower(pathinfo($proof_name, PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    if (!in_array($ext, $allowed_exts, true)) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid file format. Only JPG, PNG, WEBP, and PDF documents are permitted.']);
        exit;
    }

    if (strpos($proof_data, ';base64,') !== false) {
        list(, $data_part) = explode(';base64,', $proof_data);
    } else {
        $data_part = $proof_data;
    }
    
    $binary = base64_decode($data_part);
    if ($binary !== false) {
        // Enforce max upload limit of 10MB
        if (strlen($binary) > 10 * 1024 * 1024) {
            http_response_code(422);
            echo json_encode(['error' => 'Uploaded file exceeds the maximum 10MB limit.']);
            exit;
        }

        $safe_base = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($proof_name, PATHINFO_FILENAME));
        if (empty($safe_base)) $safe_base = 'id_proof';
        $unique_name = $new_id . '_' . substr($safe_base, 0, 40) . '.' . $ext;
        $save_path = $proofs_dir . '/' . $unique_name;
        if (file_put_contents($save_path, $binary)) {
            $proof_file_path = 'data/proofs/' . $unique_name;
            $clean_name = $unique_name;
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

// ─────────────────────────────────────────────────────────────
// Environment Variable Resolver for submit_request.php
// ─────────────────────────────────────────────────────────────
$get_env_val = function($key, $default = '') {
    $val = $_ENV[$key] ?? ($_SERVER[$key] ?? (getenv($key) ?: ''));
    if (!empty($val)) return $val;
    $env_paths = [__DIR__ . '/../.env', __DIR__ . '/../../.env', __DIR__ . '/../../php-backend/.env'];
    foreach ($env_paths as $ep) {
        if (file_exists($ep)) {
            $lines = file($ep, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($k, $v) = explode('=', $line, 2);
                    if (trim($k) === $key) return trim($v);
                }
            }
        }
    }
    return $default;
};

// Push directly to Firebase Realtime Database using Environment Configuration
function pushRequestToFirebase($requestData, $get_env_fn) {
    $apiKey = $get_env_fn('FIREBASE_API_KEY') ?: $get_env_fn('VITE_FIREBASE_API_KEY');
    $dbUrl = rtrim($get_env_fn('FIREBASE_DATABASE_URL') ?: ($get_env_fn('VITE_FIREBASE_DATABASE_URL') ?: ''), '/');
    if (empty($dbUrl)) return false;

    $authEmail = $get_env_fn('FIREBASE_AUTH_EMAIL') ?: $get_env_fn('ADMIN_EMAIL');
    $authPassword = $get_env_fn('FIREBASE_AUTH_PASSWORD') ?: $get_env_fn('ADMIN_PASSWORD');

    $idToken = '';
    if (!empty($apiKey) && !empty($authEmail) && !empty($authPassword)) {
        $authUrl = "https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key={$apiKey}";
        $authPayload = json_encode([
            'email' => $authEmail,
            'password' => $authPassword,
            'returnSecureToken' => true
        ]);
        
        $ch = curl_init($authUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $authPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        $authRes = curl_exec($ch);
        curl_close($ch);
        
        if ($authRes) {
            $authData = json_decode($authRes, true);
            $idToken = $authData['idToken'] ?? '';
        }
    }

    $rtdbUrl = $dbUrl . '/requests.json' . (!empty($idToken) ? ('?auth=' . urlencode($idToken)) : '');
    $ch2 = curl_init($rtdbUrl);
    curl_setopt_array($ch2, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    $res = curl_exec($ch2);
    $code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    return ($code >= 200 && $code < 300);
}
@pushRequestToFirebase($new_request, $get_env_val);


$saved = file_put_contents($requests_file, json_encode($requests, JSON_PRETTY_PRINT));

if (!$saved) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save request']);
    exit;
}

// ─────────────────────────────────────────────────────────────
// Forward request to External Admin Portal (Site B)
// ─────────────────────────────────────────────────────────────

$webhook_url = $get_env_val('EXTERNAL_ADMIN_WEBHOOK_URL', 'https://smart-airnet-admin.vercel.app/api/webhook_receive.php');
$webhook_key = $get_env_val('EXTERNAL_ADMIN_API_KEY', 'sec_admin_token_2026_xyz');

$forwarded_to_admin = false;
$webhook_error = null;

if (!empty($webhook_url)) {
    $webhook_payload = [
        'id'             => $new_id,
        'name'           => $input['name'] ?? 'Unknown',
        'email'          => $input['email'],
        'org'            => $input['org'] ?? 'Unknown',
        'industry'       => $industry ?: 'Unknown',
        'id_number'      => $id_number,
        'scope'          => $input['scope'],
        'target_date'    => $input['target_date'] ?? date('Y-m-d', strtotime('-1 day')),
        'telemetry_type' => $input['telemetry_type'] ?? 'aqi',
        'use_case'       => $use_case,
        'description'    => $use_case,
        'proof_name'     => $clean_name ?: $proof_name,
        'proof_base64'   => $proof_data,
        'created_at'     => $new_request['created_at']
    ];

    $ch = curl_init($webhook_url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($webhook_payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 4,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $webhook_key
        ]
    ]);
    $webhook_res = curl_exec($ch);
    $curl_err = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($webhook_res && $http_code >= 200 && $http_code < 300) {
        $forwarded_to_admin = true;
    } else {
        $webhook_error = $curl_err ?: ("HTTP code " . $http_code);
    }
}

echo json_encode([
    'success'            => true,
    'request_id'         => $new_request['id'],
    'forwarded_to_admin' => $forwarded_to_admin,
    'webhook_error'      => $webhook_error,
    'proof_file'         => $proof_file_path,
    'proof_name'         => $new_request['proof_name'],
    'use_case'           => $use_case,
    'description'        => $use_case,
    'id_number'          => $id_number
]);
