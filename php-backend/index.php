<?php
/**
 * Smart WeatherNet - Master Backend API Router & Dispatcher
 * Dispatches requests between Node 1 (SMVITM Campus) and Node 2 (Standby Station)
 * Path: php-backend/index.php
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, apikey, X-Node-ID');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Populate $_GET in CLI environments if QUERY_STRING is present
if (empty($_GET) && !empty($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $_GET);
}

// Determine active node target
$node = $_GET['node'] ?? null;

// Also check custom header
if ($node === null && isset($_SERVER['HTTP_X_NODE_ID'])) {
    $node = trim($_SERVER['HTTP_X_NODE_ID']);
}

// Also check request URI path for /node1 or /node2 prefixes
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$url_path = parse_url($request_uri, PHP_URL_PATH) ?? '';

if ($node === null) {
    if (preg_match('#/node[\s_-]*2#i', $url_path)) {
        $node = '2';
    } elseif (preg_match('#/node[\s_-]*1#i', $url_path)) {
        $node = '1';
    } else {
        $node = '1'; // Default to active primary station (Node 1)
    }
}

// Clean and normalize node ID
$node = ($node === '2' || $node === 2) ? '2' : '1';

// Dispatch to corresponding Node module
if ($node === '2') {
    $target = __DIR__ . '/node 2/index.php';
    if (!file_exists($target)) {
        $target = __DIR__ . '/node2/index.php';
    }
} else {
    $target = __DIR__ . '/node 1/index.php';
    if (!file_exists($target)) {
        $target = __DIR__ . '/node1/index.php';
    }
}

if (file_exists($target)) {
    require $target;
} else {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'error' => "Backend handler for Node {$node} not found.",
        'node' => $node
    ]);
}
