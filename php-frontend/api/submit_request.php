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

$req_node = trim((string)($input['node'] ?? (stripos($input['scope'] ?? '', 'Node 2') !== false ? '2' : '1'))) ?: '1';
$telemetry_type = strtolower(trim((string)($input['telemetry_type'] ?? 'aqi')));
$target_date = trim((string)($input['target_date'] ?? date('Y-m-d', strtotime('-1 day'))));
$include_uv_pressure_solar = !empty($input['include_uv_pressure_solar']) || ($req_node === '1' && $telemetry_type === 'weather');

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
                    if (trim($k) === $key) return trim(trim($v), "\"'\r\n\t ");
                }
            }
        }
    }
    return $default;
};

/**
 * Compile Node 1 Weather + Node 2 UV Index, Pressure & Solar Radiation CSV for requested date.
 */
function buildMergedNode1WeatherCsv(string $targetDate, callable $get_env_fn): string {
    $fetchMulti = function(array $specs): array {
        $mh = @curl_multi_init();
        $handles = [];
        $results = [];
        foreach ($specs as $k => $sp) {
            $results[$k] = [];
            if (empty($sp['url']) || empty($sp['key']) || !$mh) continue;
            $ch = @curl_init($sp['url']);
            if (!$ch) continue;
            @curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER     => [
                    "apikey: {$sp['key']}",
                    "Authorization: Bearer {$sp['key']}",
                    "Content-Type: application/json"
                ]
            ]);
            @curl_multi_add_handle($mh, $ch);
            $handles[$k] = $ch;
        }
        if ($mh && !empty($handles)) {
            $running = null;
            do {
                @curl_multi_exec($mh, $running);
                @curl_multi_select($mh, 0.05);
            } while ($running > 0);
            foreach ($handles as $k => $ch) {
                $raw = @curl_multi_getcontent($ch);
                @curl_multi_remove_handle($mh, $ch);
                @curl_close($ch);
                $parsed = $raw ? json_decode($raw, true) : null;
                $results[$k] = is_array($parsed) ? $parsed : [];
            }
            @curl_multi_close($mh);
        }
        return $results;
    };

    $toIst = function($ts) {
        $tz = new DateTimeZone('Asia/Kolkata');
        $tsStr = trim((string)$ts);
        if ($tsStr === '') return null;
        try {
            $dt = new DateTime($tsStr, $tz);
            $dt->setTimezone($tz);
            return $dt;
        } catch (\Exception $e) {
            return null;
        }
    };

    $n1Url = rtrim($get_env_fn('SUPABASE_URL', '') ?: ($get_env_fn('VITE_SUPABASE_URL', '') ?: 'https://sgkdpliqlhgiqsabxzxe.supabase.co'), '/');
    $n1Key = $get_env_fn('SUPABASE_KEY', '') ?: ($get_env_fn('SUPABASE_ANON_KEY', '') ?: ($get_env_fn('VITE_SUPABASE_ANON_KEY', '') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNna2RwbGlxbGhnaXFzYWJ4enhlIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQ4NzgzMTIsImV4cCI6MjEwMDQ1NDMxMn0.vMtbXomFdmOcBkhhSoiyYyp_vFxOhg4MYCFCw9-pL30'));
    $n2Url = rtrim($get_env_fn('SUPABASE_URL_NODE2', '') ?: ($get_env_fn('VITE_SUPABASE_URL_NODE2', '') ?: 'https://nahabhzcaeppbteuxogg.supabase.co'), '/');
    $n2Key = $get_env_fn('SUPABASE_KEY_NODE2', '') ?: ($get_env_fn('SUPABASE_ANON_KEY_NODE2', '') ?: ($get_env_fn('VITE_SUPABASE_ANON_KEY_NODE2', '') ?: 'sb_publishable_RXM8H-D8m6qWz2Yy2xA6VQ_TjxxY3bJ'));

    if (empty($n1Url) || empty($n1Key)) return '';

    $startDate = date('Y-m-d', strtotime($targetDate . ' -1 day'));
    $endDate   = date('Y-m-d', strtotime($targetDate . ' +2 days'));

    $fetched = $fetchMulti([
        'n1' => [
            'url' => "{$n1Url}/rest/v1/WEATHER_NODE1?order=timestamp_hour.desc&limit=150&timestamp_hour=gte.{$startDate}T00:00:00&timestamp_hour=lt.{$endDate}T00:00:00",
            'key' => $n1Key,
        ],
        'n2Hist' => [
            'url' => "{$n2Url}/rest/v1/WEATHER_NODE2?select=*&order=timestamp.desc&limit=200&timestamp=gte.{$startDate}T00:00:00&timestamp=lt.{$endDate}T00:00:00",
            'key' => $n2Key,
        ],
        'n2Live' => [
            'url' => "{$n2Url}/rest/v1/WEATHER_LIVE_NODE2?select=timestamp,barometric_pressure,uv_radiation,solar_radiation&order=id.desc&limit=500",
            'key' => $n2Key,
        ],
    ]);

    $n1Rows = $fetched['n1'] ?? [];
    if (empty($n1Rows)) return '';

    $n2Hist = $fetched['n2Hist'] ?? [];
    $n2Live = $fetched['n2Live'] ?? [];

    $extraByHour = [];
    foreach (array_merge($n2Hist, $n2Live) as $n2r) {
        if (!is_array($n2r)) continue;
        $dt = $toIst($n2r['timestamp_hour'] ?? $n2r['created_at'] ?? $n2r['timestamp'] ?? '');
        if (!$dt) continue;
        $hrKey = $dt->format('Y-m-d H');
        $press = $n2r['pressure'] ?? ($n2r['barometric_pressure'] ?? null);
        $uv    = $n2r['uv_index'] ?? ($n2r['uv_radiation'] ?? ($n2r['uv'] ?? null));
        $solar = $n2r['solar_radiation'] ?? ($n2r['solar'] ?? ($n2r['radiation'] ?? null));
        if (!isset($extraByHour[$hrKey])) {
            $extraByHour[$hrKey] = ['pressure' => null, 'uv_index' => null, 'solar_radiation' => null];
        }
        if ($extraByHour[$hrKey]['pressure'] === null && $press !== null && $press !== '') $extraByHour[$hrKey]['pressure'] = (float)$press;
        if ($extraByHour[$hrKey]['uv_index'] === null && $uv !== null && $uv !== '') $extraByHour[$hrKey]['uv_index'] = (float)$uv;
        if ($extraByHour[$hrKey]['solar_radiation'] === null && $solar !== null && $solar !== '') $extraByHour[$hrKey]['solar_radiation'] = (float)$solar;
    }

    $tz = new DateTimeZone('Asia/Kolkata');
    $winStart = new DateTime("{$targetDate} 08:00:00", $tz);
    $winEnd   = (clone $winStart)->modify('+24 hours')->modify('-1 second');

    $hourlyRows = [];
    foreach ($n1Rows as $row) {
        if (!is_array($row)) continue;
        $dt = $toIst($row['timestamp_hour'] ?? $row['timestamp'] ?? $row['created_at'] ?? '');
        if (!$dt || $dt < $winStart || $dt > $winEnd) continue;
        $hrKey = $dt->format('Y-m-d H');
        if (!isset($hourlyRows[$hrKey])) {
            $hourlyRows[$hrKey] = ['dt' => $dt, 'row' => $row];
        }
    }
    if (empty($hourlyRows)) return '';
    ksort($hourlyRows);
    $hourlyRows = array_slice($hourlyRows, 0, 24, true);

    $csv = "Date,Time,Temperature (deg C),Humidity (%),Pressure (mb),UV Index,Solar Radiation (W/m2),Wind Speed (km/h),Wind Gust (km/h),Wind Direction,Rain Gauge (mm)\n";
    foreach ($hourlyRows as $hrKey => $entry) {
        $dt = $entry['dt'];
        $row = $entry['row'];
        $dateStr = $dt->format('d/m/Y');
        $timeStr = $dt->format('h:i A');
        $ex = isset($extraByHour[$hrKey]) ? $extraByHour[$hrKey] : ['pressure' => null, 'uv_index' => null, 'solar_radiation' => null];

        $rawPress = $row['pressure'] ?? ($row['barometric_pressure'] ?? $ex['pressure']);
        $rawUv    = $row['uv_index'] ?? ($row['uv_radiation'] ?? ($row['uv'] ?? $ex['uv_index']));
        $rawSolar = $row['solar_radiation'] ?? ($row['solar'] ?? ($row['radiation'] ?? $ex['solar_radiation']));

        $temp  = (isset($row['temperature']) && $row['temperature'] !== '') ? number_format((float)$row['temperature'], 1, '.', '') : '--';
        $hum   = (isset($row['humidity']) && $row['humidity'] !== '')       ? number_format((float)$row['humidity'], 1, '.', '') : '--';
        $press = ($rawPress !== null && $rawPress !== '') ? number_format((float)$rawPress, 1, '.', '') : '--';
        $uv    = ($rawUv !== null && $rawUv !== '')       ? number_format((float)$rawUv, 1, '.', '') : '--';
        $solar = ($rawSolar !== null && $rawSolar !== '') ? number_format((float)$rawSolar, 1, '.', '') : '--';
        $wind  = (isset($row['wind_speed']) && $row['wind_speed'] !== '')   ? number_format((float)$row['wind_speed'], 2, '.', '') : '0.00';
        $gust  = (isset($row['wind_gust']) && $row['wind_gust'] !== '')     ? number_format((float)$row['wind_gust'], 2, '.', '') : '0.00';
        $dir   = (isset($row['wind_direction']) && $row['wind_direction'] !== '') ? $row['wind_direction'] : '--';
        $rain  = (isset($row['rain_gauge']) && $row['rain_gauge'] !== '')   ? number_format((float)$row['rain_gauge'], 2, '.', '') : '0.00';

        $csv .= "{$dateStr},{$timeStr},{$temp},{$hum},{$press},{$uv},{$solar},{$wind},{$gust},\"{$dir}\",{$rain}\n";
    }
    return $csv;
}

$dataset_csv = ($telemetry_type === 'weather' && $req_node === '1')
    ? buildMergedNode1WeatherCsv($target_date, $get_env_val)
    : '';

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
    'target_date'    => $target_date,
    'telemetry_type' => $telemetry_type,
    'node'           => $req_node,
    'include_uv_pressure_solar' => $include_uv_pressure_solar,
    'dataset_csv'    => $dataset_csv,
    'status'         => 'pending',
    'created_at'     => !empty($input['created_at']) ? $input['created_at'] : date('c'),
    'user_id'        => $_SESSION['user_id'] ?? 'anonymous'
];

array_unshift($requests, $new_request);

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
        'target_date'    => $target_date,
        'telemetry_type' => $telemetry_type,
        'node'           => $req_node,
        'include_uv_pressure_solar' => $include_uv_pressure_solar,
        'dataset_csv'    => $dataset_csv,
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
    'id_number'          => $id_number,
    'node'               => $req_node,
    'include_uv_pressure_solar' => $include_uv_pressure_solar,
    'dataset_csv'        => $dataset_csv
]);
