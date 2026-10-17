<?php
header('Content-Type: application/json');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

$target_ids = [];
$target_keys = [];

// Single ID / fb_key
if (!empty($input['id'])) {
    if (is_array($input['id'])) {
        $target_ids = array_merge($target_ids, $input['id']);
    } else {
        $target_ids[] = trim((string)$input['id']);
    }
}
if (!empty($input['fb_key'])) {
    if (is_array($input['fb_key'])) {
        $target_keys = array_merge($target_keys, $input['fb_key']);
    } else {
        $target_keys[] = trim((string)$input['fb_key']);
    }
}

// Bulk arrays: ids / fb_keys
if (!empty($input['ids']) && is_array($input['ids'])) {
    foreach ($input['ids'] as $id) {
        if (!empty($id)) $target_ids[] = trim((string)$id);
    }
}
if (!empty($input['fb_keys']) && is_array($input['fb_keys'])) {
    foreach ($input['fb_keys'] as $key) {
        if (!empty($key)) $target_keys[] = trim((string)$key);
    }
}

// Bulk items array of objects [{id, fb_key}, ...]
if (!empty($input['items']) && is_array($input['items'])) {
    foreach ($input['items'] as $item) {
        if (!empty($item['id'])) $target_ids[] = trim((string)$item['id']);
        $fbK = $item['fb_key'] ?? $item['fbKey'] ?? null;
        if (!empty($fbK)) $target_keys[] = trim((string)$fbK);
    }
}

$target_ids = array_values(array_unique(array_filter($target_ids)));
$target_keys = array_values(array_unique(array_filter($target_keys)));

if (empty($target_ids) && empty($target_keys)) {
    http_response_code(400);
    echo json_encode(['error' => 'No request IDs or keys specified for deletion']);
    exit;
}

$deleted = false;
$fb_deleted = 0;

// 1. Delete from local JSON files
$data_dir = __DIR__ . '/../data';
$files_to_check = [
    $data_dir . '/requests.json',
    sys_get_temp_dir() . '/phpAQI_requests.json'
];

foreach ($files_to_check as $file_path) {
    if (file_exists($file_path)) {
        $requests = json_decode(file_get_contents($file_path), true) ?: [];
        $orig_count = count($requests);
        $remaining = [];
        foreach ($requests as $req) {
            $req_id = isset($req['id']) ? (string)$req['id'] : '';
            $req_key = isset($req['_fbKey']) ? (string)$req['_fbKey'] : '';
            $match = ($req_id !== '' && (in_array($req_id, $target_ids) || in_array($req_id, $target_keys)))
                  || ($req_key !== '' && (in_array($req_key, $target_ids) || in_array($req_key, $target_keys)));
            if ($match) {
                if (!empty($req['proof_file'])) {
                    $p_path = __DIR__ . '/../' . ltrim($req['proof_file'], '/\\');
                    if (file_exists($p_path) && is_file($p_path)) @unlink($p_path);
                    $p_tmp = sys_get_temp_dir() . '/phpAQI_proofs/' . basename($req['proof_file']);
                    if (file_exists($p_tmp) && is_file($p_tmp)) @unlink($p_tmp);
                }
            } else {
                $remaining[] = $req;
            }
        }
        $requests = $remaining;
        
        if (count($requests) < $orig_count) {
            file_put_contents($file_path, json_encode($requests, JSON_PRETTY_PRINT));
            $deleted = true;
        }
    }
}

// 2. Delete from Firebase Realtime Database
$firebase_db_url = 'https://smart-air-net-default-rtdb.firebaseio.com';
$deleted_fb_keys = [];

// Direct Firebase keys execution
foreach ($target_keys as $fb_key) {
    if (!empty($fb_key) && !in_array($fb_key, $deleted_fb_keys)) {
        $del_ch = curl_init("{$firebase_db_url}/requests/{$fb_key}.json");
        curl_setopt($del_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($del_ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($del_ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($del_ch, CURLOPT_TIMEOUT, 6);
        $res = curl_exec($del_ch);
        $code = curl_getinfo($del_ch, CURLINFO_HTTP_CODE);
        curl_close($del_ch);
        if ($code === 200) {
            $fb_deleted++;
            $deleted_fb_keys[] = $fb_key;
        }
    }
}

// Query Firebase requests node to match target_ids or any missed target_keys
$ch = curl_init("{$firebase_db_url}/requests.json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 6);
$fb_response = curl_exec($ch);
$fb_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($fb_code === 200 && $fb_response) {
    $fb_data = json_decode($fb_response, true);
    if (is_array($fb_data)) {
        foreach ($fb_data as $key => $entry) {
            if (!$entry) continue;
            if (in_array($key, $deleted_fb_keys)) continue;
            
            $entry_id = isset($entry['id']) ? (string)$entry['id'] : '';
            $matches = in_array((string)$key, $target_ids) || in_array((string)$key, $target_keys)
                    || ($entry_id !== '' && (in_array($entry_id, $target_ids) || in_array($entry_id, $target_keys)));
            
            if ($matches) {
                $del_ch = curl_init("{$firebase_db_url}/requests/{$key}.json");
                curl_setopt($del_ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($del_ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($del_ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($del_ch, CURLOPT_TIMEOUT, 6);
                $del_res = curl_exec($del_ch);
                $del_code = curl_getinfo($del_ch, CURLINFO_HTTP_CODE);
                curl_close($del_ch);
                if ($del_code === 200) {
                    $fb_deleted++;
                    $deleted_fb_keys[] = $key;
                }
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'deleted_from_backend' => $deleted,
    'deleted_from_firebase' => $fb_deleted > 0,
    'firebase_deleted_count' => $fb_deleted,
    'deleted_targets_count' => count($target_ids) + count($target_keys)
]);
