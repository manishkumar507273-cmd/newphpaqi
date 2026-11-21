<?php
/**
 * Smart WeatherNet - Node 1 REST API
 * Primary Active Telemetry Station (SMVITM Campus)
 * Path: php-backend/node 1/index.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, apikey');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Load Node 1 configuration
$node_config = require __DIR__ . '/config.php';

// Locate and parse .env
$env_paths = [
    __DIR__ . '/../.env',
    __DIR__ . '/../../php-frontend/.env',
    __DIR__ . '/../../.env'
];

$supabaseUrl = '';
$supabaseKey = '';

foreach ($env_paths as $env_path) {
    if (file_exists($env_path)) {
        $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                if ($name === 'VITE_SUPABASE_URL' || $name === 'SUPABASE_URL') $supabaseUrl = $value;
                if ($name === 'VITE_SUPABASE_ANON_KEY' || $name === 'SUPABASE_KEY') $supabaseKey = $value;
            }
        }
        if ($supabaseUrl && $supabaseKey) break;
    }
}

function fetchSupabase($url, $key, $method = 'GET', $data = null) {
    if (empty($url) || empty($key)) {
        return ['error' => 'Supabase credentials not configured', 'code' => 500];
    }

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

// Router
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($request_uri, PHP_URL_PATH) ?? '/';

// Normalize path relative to script
$script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
if ($script_dir !== '/' && $script_dir !== '\\' && !empty($script_dir)) {
    $path = str_replace($script_dir, '', $path);
}
// Strip potential prefix like /node1 or /node 1
$path = preg_replace('#^/node\s*1#i', '', $path);
$path = '/' . ltrim($path, '/');

$action = $_GET['action'] ?? '';
if (!empty($action)) {
    $path = '/' . ltrim($action, '/');
}

// Base route / Info
if ($path === '/' || $path === '') {
    echo json_encode([
        'message' => 'Smart WeatherNet API - Node 1 (SMVITM Campus)',
        'node_id' => '1',
        'status'  => 'ONLINE',
        'tables'  => $node_config['tables']
    ]);
    exit;
}

// AQI Live Router
if (strpos($path, '/aqi') === 0) {
    $table = $node_config['tables']['aqi_live'];
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        $url = "{$supabaseUrl}/rest/v1/{$table}?order=id.desc&limit={$limit}";
        echo json_encode(fetchSupabase($url, $supabaseKey));
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $url = "{$supabaseUrl}/rest/v1/{$table}";
        echo json_encode(fetchSupabase($url, $supabaseKey, 'POST', $data));
    }
    exit;
}

// Weather Live Router
if (strpos($path, '/weather') === 0) {
    $table = $node_config['tables']['weather_live'];
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        $url = "{$supabaseUrl}/rest/v1/{$table}?order=id.desc&limit={$limit}";
        echo json_encode(fetchSupabase($url, $supabaseKey));
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $url = "{$supabaseUrl}/rest/v1/{$table}";
        echo json_encode(fetchSupabase($url, $supabaseKey, 'POST', $data));
    }
    exit;
}

// Historical Router
if (strpos($path, '/historical') === 0) {
    $type = $_GET['type'] ?? (strpos($path, '/historical/weather') === 0 ? 'weather' : 'aqi');
    $table = ($type === 'weather') ? $node_config['tables']['weather_historical'] : $node_config['tables']['aqi_historical'];
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $url = "{$supabaseUrl}/rest/v1/{$table}?order=id.desc&limit={$limit}";
    echo json_encode(fetchSupabase($url, $supabaseKey));
    exit;
}

// Forecast Router
if (strpos($path, '/forecast') === 0 || strpos($path, '/predictive') === 0) {
    $table = $node_config['tables']['forecast_24h'];
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 24;
    $url = "{$supabaseUrl}/rest/v1/{$table}?order=id.desc&limit={$limit}";
    echo json_encode(fetchSupabase($url, $supabaseKey));
    exit;
}

// Cloud Status Router
if (strpos($path, '/cloud') === 0) {
    echo json_encode([
        'status' => 'Cloud sync active',
        'node'   => 1,
        'station'=> 'SMVITM Campus'
    ]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Endpoint not found', 'path' => $path, 'node' => 1]);
