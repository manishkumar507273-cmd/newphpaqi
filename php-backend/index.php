<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, apikey');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Parse credentials from .env
$env_paths = [
    __DIR__ . '/.env',
    __DIR__ . '/../backend/.env',
    __DIR__ . '/../php-frontend/.env',
    __DIR__ . '/../.env',
    __DIR__ . '/../../.env'
];

$supabaseUrl = '';
$supabaseKey = '';

foreach ($env_paths as $env_path) {
    if (file_exists($env_path)) {
        $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                if ($name === 'VITE_SUPABASE_URL') $supabaseUrl = $value;
                if ($name === 'VITE_SUPABASE_ANON_KEY') $supabaseKey = $value;
                // Support python backend .env names if different
                if ($name === 'SUPABASE_URL') $supabaseUrl = $value;
                if ($name === 'SUPABASE_KEY') $supabaseKey = $value;
            }
        }
        if ($supabaseUrl && $supabaseKey) break;
    }
}

// Simple Router
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
// Strip any base path if running in a subfolder, assume we run from root of this script
$path = str_replace(dirname($_SERVER['SCRIPT_NAME']), '', $path);
$path = '/' . ltrim($path, '/');

if ($path === '/' || $path === '') {
    echo json_encode([
        "message" => "Smart AirNet API is running (PHP Edition)", 
        "docs" => "/docs (Not fully supported in PHP)"
    ]);
    exit;
}

function fetchSupabase($url, $key, $method = 'GET', $data = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $headers = [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'Connection: keep-alive'
    ];
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        // Prefer return representation for post
        $headers[] = 'Prefer: return=representation';
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true);
    }
    return ['error' => 'Supabase request failed', 'code' => $httpCode];
}

// AQI Router
if (strpos($path, '/aqi') === 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        $url = "{$supabaseUrl}/rest/v1/AQI_LIVE_NODE1?order=id.desc&limit={$limit}";
        echo json_encode(fetchSupabase($url, $supabaseKey));
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $url = "{$supabaseUrl}/rest/v1/AQI_LIVE_NODE1";
        echo json_encode(fetchSupabase($url, $supabaseKey, 'POST', $data));
    }
    exit;
}

// Weather Router
if (strpos($path, '/weather') === 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        $url = "{$supabaseUrl}/rest/v1/WEATHER_LIVE_NODE1?order=id.desc&limit={$limit}";
        echo json_encode(fetchSupabase($url, $supabaseKey));
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $url = "{$supabaseUrl}/rest/v1/WEATHER_LIVE_NODE1";
        echo json_encode(fetchSupabase($url, $supabaseKey, 'POST', $data));
    }
    exit;
}

// Cloud Router
if (strpos($path, '/cloud') === 0) {
    echo json_encode(["status" => "Cloud sync active"]);
    exit;
}

http_response_code(404);
echo json_encode(["detail" => "Not Found"]);
