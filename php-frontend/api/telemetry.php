<?php
if (!defined('IS_INCLUDED')) {
    header('Content-Type: application/json');
    $reqAction = isset($_GET['action']) ? $_GET['action'] : '';
    switch ($reqAction) {
        case 'forecast':
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            break;
        case 'history_bulk':
        case 'aqi_history':
        case 'weather_history':
            header('Cache-Control: public, max-age=30, s-maxage=60, stale-while-revalidate=600');
            break;
        case 'live_bulk':
        case 'overview_bulk':
        case 'aqi_live':
        case 'weather_live':
            header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            break;
        default:
            header('Cache-Control: public, max-age=5, s-maxage=15');
            break;
    }
}
date_default_timezone_set('Asia/Kolkata');

// ── .env loader ──────────────────────────────────────────────────────────────
$env_paths = [
    __DIR__ . '/../.env',
    __DIR__ . '/../../.env',
    __DIR__ . '/../../../.env',
    __DIR__ . '/../../php-backend/.env',
    __DIR__ . '/../../backend/.env'
];

$supabaseUrl = '';
$supabaseKey = '';
$supabaseUrlNode1 = '';
$supabaseKeyNode1 = '';
$supabaseUrlNode2 = '';
$supabaseKeyNode2 = '';

$TABLE_AQI_LIVE        = 'AQI_LIVE_NODE1';
$TABLE_AQI_HISTORICAL  = 'AQI_NODE1';
$TABLE_WEATHER_LIVE    = 'WEATHER_LIVE_NODE1';
$TABLE_WEATHER_HIST    = 'WEATHER_NODE1';

// Load Node Config Registry
require_once __DIR__ . '/../config/nodes.php';
$reqNode = smartairnet_get_active_node_id();
$nodeConfig = smartairnet_get_node_config($reqNode);
$isNode2 = ($reqNode === '2');

foreach ($env_paths as $env_path) {
    if (file_exists($env_path)) {
        $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name  = trim($name);
                $value = trim($value);
                if (($name === 'VITE_SUPABASE_URL'      || $name === 'SUPABASE_URL')            && empty($supabaseUrlNode1)) $supabaseUrlNode1 = $value;
                if (($name === 'VITE_SUPABASE_ANON_KEY' || $name === 'SUPABASE_KEY')            && empty($supabaseKeyNode1)) $supabaseKeyNode1 = $value;
                if (($name === 'VITE_SUPABASE_URL_NODE2' || $name === 'SUPABASE_URL_NODE2')     && empty($supabaseUrlNode2)) $supabaseUrlNode2 = $value;
                if (($name === 'SUPABASE_KEY_NODE2' || $name === 'SUPABASE_ANON_KEY_NODE2' || $name === 'VITE_SUPABASE_ANON_KEY_NODE2') && empty($supabaseKeyNode2)) {
                    $supabaseKeyNode2 = $value;
                }
            }
        }
    }
}

// Fallback to system environment variables (Vercel serverless / hosting)
$get_v_env = function($key) {
    return $_ENV[$key] ?? ($_SERVER[$key] ?? (getenv($key) ?: ''));
};

if ($isNode2) {
    $supabaseUrl = $supabaseUrlNode2 ?: ($get_v_env('SUPABASE_URL_NODE2') ?: ($get_v_env('VITE_SUPABASE_URL_NODE2') ?: ($nodeConfig['supabase']['url'] ?? 'https://nahabhzcaeppbteuxogg.supabase.co')));
    $supabaseKey = $supabaseKeyNode2 ?: ($get_v_env('SUPABASE_KEY_NODE2') ?: ($get_v_env('SUPABASE_ANON_KEY_NODE2') ?: ($get_v_env('VITE_SUPABASE_ANON_KEY_NODE2') ?: ($nodeConfig['supabase']['secret_key'] ?? ($nodeConfig['supabase']['anon_key'] ?? '')))));
    $TABLE_AQI_LIVE        = $get_v_env('TABLE_AQI_LIVE_NODE2') ?: ($nodeConfig['tables']['aqi_live'] ?? 'AQI_LIVE_NODE2');
    $TABLE_AQI_HISTORICAL  = $get_v_env('TABLE_AQI_HISTORICAL_NODE2') ?: ($nodeConfig['tables']['aqi_historical'] ?? 'AQI_NODE2');
    $TABLE_WEATHER_LIVE    = $get_v_env('TABLE_WEATHER_LIVE_NODE2') ?: ($get_v_env('TABLE_SOIL_LIVE_NODE2') ?: ($nodeConfig['tables']['weather_live'] ?? 'SOIL_LIVE_NODE2'));
    $TABLE_WEATHER_HIST    = $get_v_env('TABLE_WEATHER_HISTORICAL_NODE2') ?: ($get_v_env('TABLE_SOIL_HISTORICAL_NODE2') ?: ($nodeConfig['tables']['weather_historical'] ?? 'SOIL_NODE2'));
} else {
    $supabaseUrl = $supabaseUrlNode1 ?: ($get_v_env('VITE_SUPABASE_URL') ?: $get_v_env('SUPABASE_URL'));
    $supabaseKey = $supabaseKeyNode1 ?: ($get_v_env('VITE_SUPABASE_ANON_KEY') ?: $get_v_env('SUPABASE_KEY'));
    $TABLE_AQI_LIVE        = $get_v_env('TABLE_AQI_LIVE') ?: ($nodeConfig['tables']['aqi_live'] ?? 'AQI_LIVE_NODE1');
    $TABLE_AQI_HISTORICAL  = $get_v_env('TABLE_AQI_HISTORICAL') ?: ($get_v_env('SOURCE_AQI_TABLE') ?: ($nodeConfig['tables']['aqi_historical'] ?? 'AQI_NODE1'));
    $TABLE_WEATHER_LIVE    = $get_v_env('TABLE_WEATHER_LIVE') ?: ($nodeConfig['tables']['weather_live'] ?? 'WEATHER_LIVE_NODE1');
    $TABLE_WEATHER_HIST    = $get_v_env('TABLE_WEATHER_HISTORICAL') ?: ($nodeConfig['tables']['weather_historical'] ?? 'WEATHER_NODE1');
}

$orderColLive = $isNode2 ? 'id.desc' : 'created_at.desc.nullslast';
$orderColHist = $isNode2 ? 'timestamp.desc.nullslast' : 'timestamp_hour.desc';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$limit  = isset($_GET['limit'])  ? intval($_GET['limit'])  : 50;

if (!$supabaseUrl || !$supabaseKey) {
    echo json_encode(['error' => 'Supabase credentials not configured.']);
    exit;
}

// ── Server-side file cache ────────────────────────────────────────────────────
// Stores Supabase responses on disk to avoid redundant network round-trips.
// Live endpoints: 30 s TTL.  Historical endpoints: 5 min TTL.
$CACHE_DIR = sys_get_temp_dir() . '/smartairnet_cache';
if (!is_dir($CACHE_DIR)) @mkdir($CACHE_DIR, 0755, true);

function cacheKey(string $key): string {
    global $CACHE_DIR;
    return $CACHE_DIR . '/' . md5($key) . '.json';
}

function cacheGet(string $key, int $ttl) {
    $file = cacheKey($key);
    if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
        $raw = @file_get_contents($file);
        if ($raw !== false && strlen($raw) > 50) return json_decode($raw, true);
    }
    return null;
}

function cacheGetStale(string $key) {
    $file = cacheKey($key);
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false && strlen($raw) > 50) return json_decode($raw, true);
    }
    return null;
}

function cacheSet(string $key, $data): void {
    if ($data === null) return;
    if (is_array($data) && isset($data['aqi']) && $data['aqi'] === null && isset($data['weather']) && $data['weather'] === null) {
        // Do not corrupt cache with empty/failed response
        return;
    }
    file_put_contents(cacheKey($key), json_encode($data), LOCK_EX);
}

// ── Single cURL helper ────────────────────────────────────────────────────────
function makeCurlHandle(string $url, string $key): \CurlHandle|false {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_ENCODING       => '',           // accept gzip
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TCP_KEEPALIVE  => 1,            // reuse TCP connections
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => [
            'apikey: '        . $key,
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Accept-Encoding: gzip, deflate',
        ],
    ]);
    return $ch;
}

/**
 * Fire multiple Supabase URLs in parallel using curl_multi.
 * Returns an array keyed by the same keys as $urls.
 * Each value is the decoded JSON array or null on error.
 */
function fetchParallel(array $urls, string $key): array {
    $mh      = curl_multi_init();
    $handles = [];

    foreach ($urls as $label => $url) {
        $ch = makeCurlHandle($url, $key);
        curl_multi_add_handle($mh, $ch);
        $handles[$label] = $ch;
    }

    // Execute all handles simultaneously
    $running = null;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running > 0) {
            $select = curl_multi_select($mh, 0.05);
            if ($select === -1) {
                usleep(5000); // 5ms sleep if descriptor returned -1
            }
        }
    } while ($running > 0 && $status === CURLM_OK);

    $results = [];
    foreach ($handles as $label => $ch) {
        $body     = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        $results[$label] = ($httpCode >= 200 && $httpCode < 300 && $body)
            ? json_decode($body, true)
            : null;
    }

    curl_multi_close($mh);
    return $results;
}

/** Single fetch (used for actions that only need one URL). */
function fetchSupabase(string $url, string $key) {
    $results = fetchParallel(['r' => $url], $key);
    return $results['r'];
}

// ── Calibration / formatting helpers ─────────────────────────────────────────
$CPCB_BREAKPOINTS = [
    'pm25' => [[0.0,30.0,0,50],[30.0,60.0,51,100],[60.0,90.0,101,200],[90.0,120.0,201,300],[120.0,250.0,301,400],[250.0,500.0,401,500]],
    'pm10' => [[0.0,50.0,0,50],[50.0,100.0,51,100],[100.0,250.0,101,200],[250.0,350.0,201,300],[350.0,430.0,301,400],[430.0,600.0,401,500]],
    'co'   => [[0.0,1.0,0,50],[1.0,2.0,51,100],[2.0,10.0,101,200],[10.0,17.0,201,300],[17.0,34.0,301,400],[34.0,50.0,401,500]],
    'no2'  => [[0.0,40.0,0,50],[40.0,80.0,51,100],[80.0,180.0,101,200],[180.0,280.0,201,300],[280.0,400.0,301,400],[400.0,500.0,401,500]],
    'o3'   => [[0.0,50.0,0,50],[50.0,100.0,51,100],[100.0,168.0,101,200],[168.0,208.0,201,300],[208.0,748.0,301,400],[748.0,1000.0,401,500]],
];

function calcSubindex(string $paramKey, $cp): float {
    global $CPCB_BREAKPOINTS;
    if ($cp === null || !isset($CPCB_BREAKPOINTS[$paramKey])) return 0.0;
    $val   = max(0.0, (float)$cp);
    $tiers = $CPCB_BREAKPOINTS[$paramKey];
    foreach ($tiers as [$c_lo, $c_hi, $i_lo, $i_hi]) {
        if ($val <= $c_hi) return round((($i_hi-$i_lo)/($c_hi-$c_lo))*($val-$c_lo)+$i_lo, 3);
    }
    [$c_lo,$c_hi,$i_lo,$i_hi] = end($tiers);
    return round((($i_hi-$i_lo)/($c_hi-$c_lo))*($val-$c_lo)+$i_lo, 3);
}

function parseToIstIso($tsRaw): string {
    if (!$tsRaw) return '';
    $tsStr = trim((string)$tsRaw);
    if (strpos($tsStr, '+00') !== false || substr($tsStr, -1) === 'Z') {
        try {
            $dt = new DateTime($tsStr);
            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            return $dt->format('Y-m-d\TH:i:sP');
        } catch (Exception $e) {}
    }
    if (preg_match('/([+-]\d{2}):?(\d{2})$/', $tsStr)) {
        try {
            $dt = new DateTime($tsStr);
            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            return $dt->format('Y-m-d\TH:i:sP');
        } catch (Exception $e) {}
    }
    return str_replace(' ', 'T', $tsStr);
}

function adjustTemperature($temp, $tsRaw) {
    if ($temp === null || $temp === '') return null;
    return round((float)$temp, 1);
}

function formatCoordsDmsPhp($lat, $lon) {
    if ($lat === null || $lon === null || !is_numeric($lat) || !is_numeric($lon)) return null;
    $convert = function($val, $posChar, $negChar) {
        $dir = $val >= 0 ? $posChar : $negChar;
        $abs = abs((float)$val);
        $deg = floor($abs);
        $minFloat = ($abs - $deg) * 60;
        $min = floor($minFloat);
        $sec = round(($minFloat - $min) * 60);
        return sprintf("%d°%02d'%02d\" %s", $deg, $min, $sec, $dir);
    };
    return $convert($lat, 'N', 'S') . ', ' . $convert($lon, 'E', 'W');
}

function formatWeatherReading($row) {
    if (!$row) return null;
    $tsRaw = $row['timestamp_hour'] ?? $row['created_at'] ?? $row['timestamp'] ?? null;
    $row['timestamp'] = parseToIstIso($tsRaw);
    if (isset($row['temperature']) && $row['temperature'] !== null) $row['temperature'] = adjustTemperature($row['temperature'], $tsRaw);
    if (isset($row['humidity']) && $row['humidity'] !== null) $row['humidity'] = round((float)$row['humidity'], 2);
    if (isset($row['wind_speed']) && $row['wind_speed'] !== null) $row['wind_speed'] = round((float)$row['wind_speed'], 2);
    if (isset($row['wind_gust']) && $row['wind_gust'] !== null) $row['wind_gust'] = round((float)$row['wind_gust'], 2);
    if (isset($row['rain_gauge']) && $row['rain_gauge'] !== null) $row['rain_gauge'] = round((float)$row['rain_gauge'], 2);
    if (isset($row['pressure']) && $row['pressure'] !== null) {
        $row['pressure'] = round((float)$row['pressure'], 1);
    } elseif (isset($row['barometric_pressure']) && $row['barometric_pressure'] !== null) {
        $row['pressure'] = round((float)$row['barometric_pressure'], 1);
    }
    if (isset($row['uv_index']) && $row['uv_index'] !== null) {
        $row['uv_index'] = round((float)$row['uv_index'], 1);
    } elseif (isset($row['uv_radiation']) && $row['uv_radiation'] !== null) {
        $row['uv_index'] = round((float)$row['uv_radiation'], 1);
    } elseif (isset($row['uv']) && $row['uv'] !== null) {
        $row['uv_index'] = round((float)$row['uv'], 1);
    }
    if (isset($row['solar_radiation']) && $row['solar_radiation'] !== null) {
        $row['solar_radiation'] = round((float)$row['solar_radiation'], 1);
    } elseif (isset($row['solar']) && $row['solar'] !== null) {
        $row['solar_radiation'] = round((float)$row['solar'], 1);
    } elseif (isset($row['radiation']) && $row['radiation'] !== null) {
        $row['solar_radiation'] = round((float)$row['radiation'], 1);
    }
    if (isset($row['soil_temperature']) && $row['soil_temperature'] !== null) $row['soil_temperature'] = round((float)$row['soil_temperature'], 1);
    $soil_hum = $row['soil_moisture'] ?? ($row['soil_humidity'] ?? null);
    if ($soil_hum !== null && $soil_hum !== '') {
        $row['soil_humidity'] = round((float)$soil_hum, 1);
        $row['soil_moisture'] = round((float)$soil_hum, 1);
    }
    // Node 2 fallback: if ambient temperature or humidity are empty but soil sensors exist, map them gracefully
    if ((!isset($row['temperature']) || $row['temperature'] === null) && isset($row['soil_temperature']) && $row['soil_temperature'] !== null) {
        $row['temperature'] = $row['soil_temperature'];
    }
    if ((!isset($row['humidity']) || $row['humidity'] === null) && $soil_hum !== null && $soil_hum !== '') {
        $row['humidity'] = round((float)$soil_hum, 1);
    }
    if (isset($row['soil_ph']) && $row['soil_ph'] !== null) $row['soil_ph'] = round((float)$row['soil_ph'], 2);
    if (isset($row['soil_conductivity']) && $row['soil_conductivity'] !== null) $row['soil_conductivity'] = round((float)$row['soil_conductivity'], 1);
    if (isset($row['latitude']) && $row['latitude'] !== null && $row['latitude'] !== '') $row['latitude'] = round((float)$row['latitude'], 4);
    if (isset($row['longitude']) && $row['longitude'] !== null && $row['longitude'] !== '') $row['longitude'] = round((float)$row['longitude'], 4);
    if (isset($row['coords_display']) && $row['coords_display'] !== null && $row['coords_display'] !== '') {
        $row['coords_display'] = (string)$row['coords_display'];
    } elseif (!empty($row['latitude']) && !empty($row['longitude'])) {
        $row['coords_display'] = formatCoordsDmsPhp($row['latitude'], $row['longitude']) ?: (round((float)$row['latitude'], 4) . '° N, ' . round((float)$row['longitude'], 4) . '° E');
    }
    return $row;
}

function formatRawReading($raw) {
    if (!$raw) return null;
    $tsRaw = $raw['timestamp_hour'] ?? $raw['created_at'] ?? $raw['timestamp'] ?? null;

    $rawPm25 = (isset($raw['pm2_5']) && $raw['pm2_5'] !== null && $raw['pm2_5'] !== '') ? (float)$raw['pm2_5'] : ((isset($raw['pm25']) && $raw['pm25'] !== null && $raw['pm25'] !== '') ? (float)$raw['pm25'] : ((isset($raw['pm2.5']) && $raw['pm2.5'] !== null && $raw['pm2.5'] !== '') ? (float)$raw['pm2.5'] : null));
    $rawPm10 = (isset($raw['pm10']) && $raw['pm10'] !== null && $raw['pm10'] !== '') ? (float)$raw['pm10'] : null;
    $rawCo   = (isset($raw['co']) && $raw['co'] !== null && $raw['co'] !== '') ? (float)$raw['co'] : null;
    $rawO3   = (isset($raw['o3']) && $raw['o3'] !== null && $raw['o3'] !== '') ? (float)$raw['o3'] : null;
    $rawNo2  = (isset($raw['no2']) && $raw['no2'] !== null && $raw['no2'] !== '') ? (float)$raw['no2'] : null;

    // Use exact CPCB AQI and dominant pollutant from Supabase if present
    if (isset($raw['cpcb_aqi']) && $raw['cpcb_aqi'] !== null && $raw['cpcb_aqi'] !== '') {
        $cpcb_aqi = (int)round((float)$raw['cpcb_aqi']);
        $dom      = $raw['dominant_pollutant'] ?? 'O3';
        $domUpper = strtoupper(trim((string)$dom));
        if ($domUpper === 'PM25' || $domUpper === 'PM2.5') $dom = 'PM2.5';
        elseif ($domUpper === 'PM10') $dom = 'PM10';
        elseif ($domUpper === 'CO') $dom = 'CO';
        elseif ($domUpper === 'NO2' || $domUpper === 'NO₂') $dom = 'NO₂';
        elseif ($domUpper === 'O3' || $domUpper === 'O₃') $dom = 'O₃';
    } else {
        // Standard CPCB sub-index calculation directly from raw concentrations
        $subIndices = [
            'pm25' => $rawPm25 !== null ? calcSubindex('pm25', $rawPm25) : 0,
            'pm10' => $rawPm10 !== null ? calcSubindex('pm10', $rawPm10) : 0,
            'co'   => $rawCo !== null ? calcSubindex('co', $rawCo) : 0,
            'no2'  => $rawNo2 !== null ? calcSubindex('no2', $rawNo2) : 0,
            'o3'   => $rawO3 !== null ? calcSubindex('o3', $rawO3) : 0,
        ];

        $domKey = 'pm25';
        $maxVal = -1;
        foreach ($subIndices as $k => $v) { if ($v >= $maxVal) { $maxVal = $v; $domKey = $k; } }
        $domNames = ['pm25'=>'PM2.5','pm10'=>'PM10','co'=>'CO','no2'=>'NO₂','o3'=>'O₃'];
        $cpcb_aqi = round($maxVal > 0 ? $maxVal : 0);
        $dom = $domNames[$domKey] ?? 'PM2.5';
    }

    $label = 'Good'; $color = '#59b61f';
    if ($cpcb_aqi > 50  && $cpcb_aqi <= 100) { $label = 'Moderate';     $color = '#eec732'; }
    elseif ($cpcb_aqi > 100 && $cpcb_aqi <= 200) { $label = 'Poor';     $color = '#ea8c34'; }
    elseif ($cpcb_aqi > 200 && $cpcb_aqi <= 300) { $label = 'Unhealthy';$color = '#e95478'; }
    elseif ($cpcb_aqi > 300 && $cpcb_aqi <= 400) { $label = 'Severe';   $color = '#b33fba'; }
    elseif ($cpcb_aqi > 400)                      { $label = 'Hazardous';$color = '#c92033'; }

    $ts = $raw['timestamp_hour'] ?? $raw['created_at'] ?? $raw['timestamp'] ?? null;

    $rawLat = (isset($raw['latitude']) && $raw['latitude'] !== null && $raw['latitude'] !== '') ? round((float)$raw['latitude'], 4) : ((isset($raw['lat']) && $raw['lat'] !== null && $raw['lat'] !== '') ? round((float)$raw['lat'], 4) : null);
    $rawLon = (isset($raw['longitude']) && $raw['longitude'] !== null && $raw['longitude'] !== '') ? round((float)$raw['longitude'], 4) : ((isset($raw['lon']) && $raw['lon'] !== null && $raw['lon'] !== '') ? round((float)$raw['lon'], 4) : ((isset($raw['lng']) && $raw['lng'] !== null && $raw['lng'] !== '') ? round((float)$raw['lng'], 4) : null));
    $rawCoordsDisp = (isset($raw['coords_display']) && $raw['coords_display'] !== null && $raw['coords_display'] !== '') ? (string)$raw['coords_display'] : (($rawLat !== null && $rawLon !== null) ? (formatCoordsDmsPhp($rawLat, $rawLon) ?: "{$rawLat}° N, {$rawLon}° E") : null);

    return [
        'id'                => $raw['id'] ?? null,
        'created_at'        => $raw['created_at'] ?? ($raw['timestamp'] ?? null),
        'timestamp'         => parseToIstIso($ts),
        'temperature'       => isset($raw['temperature']) && $raw['temperature'] !== null ? adjustTemperature($raw['temperature'], $ts) : null,
        'humidity'          => isset($raw['humidity']) && $raw['humidity'] !== null ? round((float)$raw['humidity'], 2) : null,
        'pm25'              => $rawPm25,
        'pm2.5'             => $rawPm25,
        'pm2_5'             => $rawPm25,
        'pm10'              => $rawPm10,
        'co'                => $rawCo,
        'o3'                => $rawO3,
        'no2'               => $rawNo2,
        'cpcb_aqi'          => $cpcb_aqi,
        'dominant_pollutant'=> $dom,
        'aqi_info'          => ['value'=>$cpcb_aqi,'label'=>$label,'color'=>$color,'standard'=>'CPCB (India)'],
        'wind_speed'        => isset($raw['wind_speed']) && $raw['wind_speed'] !== null ? round((float)$raw['wind_speed'], 2) : null,
        'wind_gust'         => isset($raw['wind_gust']) && $raw['wind_gust'] !== null ? round((float)$raw['wind_gust'], 2) : (isset($raw['gust']) ? round((float)$raw['gust'], 2) : null),
        'wind_direction'    => $raw['wind_direction'] ?? null,
        'rain_gauge'        => isset($raw['rain_gauge']) && $raw['rain_gauge'] !== null ? round((float)$raw['rain_gauge'], 2) : (isset($raw['rain']) ? round((float)$raw['rain'], 2) : null),
        'latitude'          => $rawLat,
        'longitude'         => $rawLon,
        'coords_display'    => $rawCoordsDisp,
    ];
}

function getForecastWeatherIcon(int $hour, float $temp, float $humidity, int $aqi): string {
    $is_night = ($hour >= 19 || $hour < 6);
    if ($humidity > 85) return $humidity > 90 ? '🌧️' : '🌦️';
    if ($aqi > 100) return '🌫️';
    if ($is_night) return $humidity < 75 ? '🌙' : '☁️';
    if ($temp > 32) return '☀️';
    if ($humidity > 70) return '⛅';
    return '🌤️';
}

function buildForecastPayloadFromDbRows(array $rows, ?array $weatherRows = null): ?array {
    if (empty($rows)) return null;

    // Build index of weather rows by date & hour if available
    $weatherMap = [];
    if (!empty($weatherRows)) {
        foreach ($weatherRows as $wr) {
            $wTs = $wr['forecast_for_time'] ?? '';
            try {
                $wDt = new DateTime($wTs);
                $wDt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                $weatherMap[$wDt->format('Y-m-d H')] = $wr;
            } catch (Exception $e) {}
        }
    }

    // Retain rows for the current clock hour and upcoming hours (drops only when the full clock hour has elapsed)
    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $currentHourStartTs = (clone $now)->setTime((int)$now->format('H'), 0, 0)->getTimestamp();

    $futureRows = [];
    foreach ($rows as $r) {
        $tsStr = $r['forecast_for_time'] ?? '';
        try {
            $dt = new DateTime($tsStr);
            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            if ($dt->getTimestamp() >= $currentHourStartTs) {
                $futureRows[] = $r;
            }
        } catch (Exception $e) {
            $futureRows[] = $r;
        }
    }
    if (!empty($futureRows)) {
        $rows = array_values($futureRows);
    }

    $domNames = ['pm25' => 'PM2.5', 'pm10' => 'PM10', 'no2' => 'NO₂', 'co' => 'CO', 'o3' => 'O₃'];
    $hourlyList = [];
    $allTemps = [];
    $pm25Vals = []; $pm10Vals = []; $no2Vals = []; $coVals = []; $o3Vals = []; $tempVals = []; $humVals = [];
    $tierUsed = 168;

    foreach ($rows as $i => $r) {
        $tsStr = $r['forecast_for_time'] ?? '';
        try {
            $dt = new DateTime($tsStr);
            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
        } catch (Exception $e) {
            $dt = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        }

        $hKey = $dt->format('Y-m-d H');
        $wMatch = $weatherMap[$hKey] ?? null;
        $wRainMm = isset($wMatch['rain_gauge']) ? (float)$wMatch['rain_gauge'] : 0.0;

        $p_pm25 = max(0.5, round((float)($r['pm2_5_ug_m3'] ?? 15), 2));
        $p_pm10 = max(1.0, round((float)($r['pm10_ug_m3'] ?? 25), 2));
        $p_no2  = max(0.1, round((float)($r['no2_ug_m3'] ?? 5), 2));
        $p_co   = max(0.01, round((float)($r['co_mg_m3'] ?? 0.2), 3));
        $p_o3   = max(1.0, round((float)($r['ozone_ug_m3'] ?? 30), 2));
        $p_temp = isset($wMatch['temperature']) && $wMatch['temperature'] !== null
            ? round((float)$wMatch['temperature'], 1)
            : round((float)($r['temperature_c'] ?? 28), 1);
        $p_hum  = isset($wMatch['humidity']) && $wMatch['humidity'] !== null
            ? min(100.0, max(20.0, round((float)$wMatch['humidity'], 1)))
            : min(100.0, max(20.0, round((float)($r['humidity_pct'] ?? 70), 1)));

        if (isset($r['tier_used']) && !empty($r['tier_used'])) $tierUsed = (int)$r['tier_used'];

        $pm25Vals[] = $p_pm25; $pm10Vals[] = $p_pm10; $no2Vals[] = $p_no2;
        $coVals[] = $p_co; $o3Vals[] = $p_o3; $tempVals[] = $p_temp; $humVals[] = $p_hum;

        $sub = [
            'pm25' => calcSubindex('pm25', $p_pm25),
            'pm10' => calcSubindex('pm10', $p_pm10),
            'no2'  => calcSubindex('no2',  $p_no2),
            'co'   => calcSubindex('co',   $p_co),
            'o3'   => calcSubindex('o3',   $p_o3),
        ];

        $domKey = 'pm25'; $maxSub = $sub['pm25'];
        foreach ($sub as $k => $v) {
            if ($v >= $maxSub) { $maxSub = $v; $domKey = $k; }
        }
        $aqiVal = (int)round($maxSub);
        $allTemps[] = $p_temp;

        $cat = 'Good'; $sev = 'GOOD'; $col = '#16a34a';
        if ($aqiVal > 50 && $aqiVal <= 100) { $cat = 'Moderate'; $sev = 'MODERATE'; $col = '#ca8a04'; }
        elseif ($aqiVal > 100 && $aqiVal <= 200) { $cat = 'Poor'; $sev = 'POOR'; $col = '#ea580c'; }
        elseif ($aqiVal > 200 && $aqiVal <= 300) { $cat = 'Unhealthy'; $sev = 'UNHEALTHY'; $col = '#e11d48'; }
        elseif ($aqiVal > 300 && $aqiVal <= 400) { $cat = 'Severe'; $sev = 'SEVERE'; $col = '#9333ea'; }
        elseif ($aqiVal > 400) { $cat = 'Hazardous'; $sev = 'HAZARDOUS'; $col = '#b91c1c'; }

        $hour = (int)$dt->format('G');
        $label = $dt->format('g A');
        $icon = getForecastWeatherIcon($hour, $p_temp, $p_hum, $aqiVal);

        $hourlyList[] = [
            'index' => $i,
            'hour' => $hour,
            'label' => $label,
            'iso' => $dt->format('c'),
            'aqi' => $aqiVal,
            'dominant' => $domNames[$domKey] ?? 'PM2.5',
            'category' => $cat,
            'severity' => $sev,
            'color' => $col,
            'sub_indices' => [
                'PM2.5' => $sub['pm25'],
                'PM10'  => $sub['pm10'],
                'NO₂'   => $sub['no2'],
                'CO'    => $sub['co'],
                'O₃'    => $sub['o3'],
            ],
            'pm25' => $p_pm25,
            'pm10' => $p_pm10,
            'no2'  => $p_no2,
            'co'   => $p_co,
            'o3'   => $p_o3,
            'temp' => $p_temp,
            'humidity' => $p_hum,
            'rain_prob' => ($wRainMm > 0) ? min(100.0, max(50.0, round($wRainMm * 15, 1))) : 5.0,
            'rain_mm' => $wRainMm,
            'is_rain' => ($wRainMm > 0.0),
            'rain_intensity' => ($wRainMm > 5.0) ? 'Heavy' : (($wRainMm > 0.5) ? 'Moderate' : 'None'),
            'icon' => $icon,
            'isNow' => ($i === 0)
        ];
    }

    if (empty($hourlyList)) return null;

    $h0 = $hourlyList[0];
    $curAqi = $h0['aqi'];
    $curDom = $h0['dominant'];

    $calcStats = function(array $arr, float $limit, float $curSub) {
        $c = count($arr);
        $mean = $c > 0 ? round(array_sum($arr) / $c, 2) : 0;
        return [
            'current' => $arr[0] ?? 0,
            'min' => !empty($arr) ? min($arr) : 0,
            'max' => !empty($arr) ? max($arr) : 0,
            'mean' => $mean,
            'safe_limit' => $limit,
            'current_subindex' => $curSub
        ];
    };

    $parameters = [
        'pm25'     => $calcStats($pm25Vals, 60.0,  $h0['sub_indices']['PM2.5']),
        'pm10'     => $calcStats($pm10Vals, 100.0, $h0['sub_indices']['PM10']),
        'no2'      => $calcStats($no2Vals,  80.0,  $h0['sub_indices']['NO₂']),
        'co'       => $calcStats($coVals,   2.0,   $h0['sub_indices']['CO']),
        'o3'       => $calcStats($o3Vals,   100.0, $h0['sub_indices']['O₃']),
        'temp'     => $calcStats($tempVals, 0.0, 0.0),
        'humidity' => $calcStats($humVals,  0.0, 0.0),
    ];

    // Enrich with 24h Rainfall Model forecast if available on disk
    $rfJsonPath = __DIR__ . '/rainfall_forecast_24h.json';
    $rfData = null;
    if (file_exists($rfJsonPath)) {
        $rfRaw = @file_get_contents($rfJsonPath);
        if ($rfRaw) {
            $rfDecoded = json_decode($rfRaw, true);
            if (is_array($rfDecoded) && !empty($rfDecoded['hourly'])) {
                $rfData = $rfDecoded;
                $rfHourlyMap = [];
                foreach ($rfData['hourly'] as $rfH) {
                    if (isset($rfH['hour'])) {
                        $rfHourlyMap[(int)$rfH['hour']] = $rfH;
                    }
                }
                $allTemps = [];
                $tempVals = [];
                $humVals  = [];
                foreach ($hourlyList as $idx => &$hItem) {
                    $hr = (int)($hItem['hour'] ?? -1);
                    $rfMatch = $rfHourlyMap[$hr] ?? ($rfData['hourly'][$idx] ?? null);
                    if ($rfMatch) {
                        $hItem['rain_prob'] = $rfMatch['rain_prob_pct'] ?? 0.0;
                        $hItem['rain_mm'] = $rfMatch['rain_mm'] ?? 0.0;
                        $hItem['is_rain'] = $rfMatch['is_rain'] ?? false;
                        $hItem['rain_intensity'] = $rfMatch['rain_intensity'] ?? 'Dry';
                        if (isset($rfMatch['temp']) && $rfMatch['temp'] !== null) {
                            $hItem['temp'] = (float)$rfMatch['temp'];
                        }
                        if (isset($rfMatch['humidity']) && $rfMatch['humidity'] !== null) {
                            $hItem['humidity'] = (float)$rfMatch['humidity'];
                        }
                    }
                    $allTemps[] = (float)$hItem['temp'];
                    $tempVals[] = (float)$hItem['temp'];
                    $humVals[]  = (float)$hItem['humidity'];
                }
                unset($hItem);

                // Update parameters with the multi-weather temperature and humidity
                $parameters['temp']     = $calcStats($tempVals, 0.0, 0.0);
                $parameters['humidity'] = $calcStats($humVals,  0.0, 0.0);

                $h0 = $hourlyList[0];
                $parameters['rainfall'] = [
                    'current' => $h0['rain_mm'] ?? 0.0,
                    'unit' => 'mm',
                    'name' => 'Rainfall Rate',
                    'status' => $h0['rain_intensity'] ?? 'Dry',
                    'prob' => $h0['rain_prob'] ?? 0.0,
                    'total_24h' => $rfData['total_24h_rainfall_mm'] ?? 0.0,
                    'safe_limit' => ($rfData['rain_risk_level'] ?? 'Low') . ' Risk',
                    'mean' => 'Peak ' . ($rfData['peak_hourly_rate_mm'] ?? 0) . ' mm/h'
                ];
            }
        }
    }

    // Ensure parameters['rainfall'] is ALWAYS populated even if rainfall_forecast_24h.json is missing on cloud server
    if (!isset($parameters['rainfall'])) {
        $totRain = array_sum(array_column($hourlyList, 'rain_mm'));
        $maxRain = !empty($hourlyList) ? max(array_column($hourlyList, 'rain_mm')) : 0.0;
        $parameters['rainfall'] = [
            'current' => $h0['rain_mm'] ?? 0.0,
            'unit' => 'mm',
            'name' => 'Rainfall Rate',
            'status' => $h0['rain_intensity'] ?? 'Dry',
            'prob' => $h0['rain_prob'] ?? 5.0,
            'total_24h' => round($totRain, 2),
            'safe_limit' => 'Low Risk',
            'mean' => 'Peak ' . round($maxRain, 1) . ' mm/h'
        ];
    }

    // Ensure rfData fallback is never null so client code runs safely
    if (!$rfData) {
        $totRain = array_sum(array_column($hourlyList, 'rain_mm'));
        $maxRain = !empty($hourlyList) ? max(array_column($hourlyList, 'rain_mm')) : 0.0;
        $rfData = [
            'model_name' => "SMVITM Multi-Weather Wavelet Attention LSTM",
            'total_24h_rainfall_mm' => round($totRain, 2),
            'peak_hourly_rate_mm' => round($maxRain, 1),
            'peak_hour_label' => 'N/A',
            'rain_risk_level' => 'None',
            'rain_risk_summary' => 'Dry Conditions Expected (No Significant Rain)'
        ];
    }

    if ($curAqi <= 50) {
        $advTitle = 'Air Quality Ideal (Good) · Minimal Health Impact';
        $advDesc = "Predicted ambient AQI is {$curAqi} (Dominant: {$curDom}). All criteria pollutant concentrations are within safe limits.";
        $sevLabel = 'GOOD';
        $healthImpact = 'Minimal impact';
        $sportsAdv = 'Optimal for athletic training and sports';
        $ventAdv = 'Full campus natural cross-ventilation advised';
        $hydrAdv = 'Standard hydration (2.5L/day recommended)';
    } elseif ($curAqi <= 100) {
        $advTitle = 'Air Quality Moderate · Minor Sensitive Group Impact';
        $advDesc = "Predicted ambient AQI is {$curAqi} (Dominant: {$curDom}). Minimal impact on healthy individuals.";
        $sevLabel = 'MODERATE';
        $healthImpact = 'Minor breathing discomfort to sensitive people';
        $sportsAdv = 'Standard outdoor sports and exercise permitted';
        $ventAdv = 'Normal classroom and lab ventilation';
        $hydrAdv = 'Keep hydrated during warm afternoon peaks';
    } elseif ($curAqi <= 200) {
        $advTitle = 'Air Quality Poor · Breathing Discomfort Advisory';
        $advDesc = "Predicted ambient AQI is {$curAqi} dominated by {$curDom}. Breathing discomfort to people with lung, asthma and heart diseases.";
        $sevLabel = 'POOR';
        $healthImpact = 'Breathing discomfort to people with lungs, asthma and heart diseases';
        $sportsAdv = 'Consider scheduling strenuous sports in early morning';
        $ventAdv = 'Use filtered ventilation during peak hours';
        $hydrAdv = 'Adequate fluid intake recommended';
    } elseif ($curAqi <= 300) {
        $advTitle = 'Air Quality Unhealthy · General Public Advisory';
        $advDesc = "Predicted ambient AQI is {$curAqi} dominated by {$curDom}. Breathing discomfort to most people on prolonged exposure.";
        $sevLabel = 'UNHEALTHY';
        $healthImpact = 'Breathing discomfort to most people on prolonged exposure';
        $sportsAdv = 'Avoid intense outdoor physical activities';
        $ventAdv = 'Keep windows closed; operate indoor air purifiers';
        $hydrAdv = 'Increase fluid intake and avoid prolonged exertion';
    } elseif ($curAqi <= 400) {
        $advTitle = 'Air Quality Severe · Respiratory Health Warning';
        $advDesc = "Predicted ambient AQI is {$curAqi} dominated by {$curDom}. Respiratory illness on prolonged exposure.";
        $sevLabel = 'SEVERE';
        $healthImpact = 'Respiratory illness on prolonged exposure';
        $sportsAdv = 'Suspend outdoor athletic events and training';
        $ventAdv = 'Seal outdoor air dampers; run high-efficiency filters';
        $hydrAdv = 'Remain indoors in conditioned spaces';
    } else {
        $advTitle = 'Air Quality Hazardous · Emergency Health Warning';
        $advDesc = "Predicted ambient AQI is {$curAqi} dominated by {$curDom}. Serious risk of respiratory effects in general population.";
        $sevLabel = 'HAZARDOUS';
        $healthImpact = 'Affects healthy people and seriously impacts those with existing diseases';
        $sportsAdv = 'Strictly prohibit outdoor physical activities';
        $ventAdv = 'Full campus indoor lockdown of ventilation intakes';
        $hydrAdv = 'Stay indoors and seek medical advice if symptomatic';
    }

    // Compute 4 diurnal phases for the 24-hour accordion
    $phases = [];
    $phaseDefs = [
        ['Late Afternoon / Evening', 0, 6, '🌅'],
        ['Night to Midnight', 6, 12, '🌙'],
        ['Early Morning Transition', 12, 18, '🌤️'],
        ['Daytime Afternoon Peak', 18, 24, '☀️']
    ];
    $dayMinT = !empty($allTemps) ? min($allTemps) : 20.0;
    $dayMaxT = !empty($allTemps) ? max($allTemps) : 35.0;
    $tRange  = max(1.0, $dayMaxT - $dayMinT);

    foreach ($phaseDefs as $pIdx => [$pName, $sIdx, $eIdx, $pIcon]) {
        $pSlice = array_slice($hourlyList, $sIdx, $eIdx - $sIdx);
        if (empty($pSlice)) continue;
        $pTemps = array_column($pSlice, 'temp');
        $pMinT = min($pTemps);
        $pMaxT = max($pTemps);
        $pAqis = array_column($pSlice, 'aqi');
        $pAvgAqi = (int)round(array_sum($pAqis) / count($pAqis));
        $ph0 = $pSlice[0];

        $leftPct = max(0, min(100, (int)round((($pMinT - $dayMinT) / $tRange) * 100)));
        $widthPct = max(10, min(100 - $leftPct, (int)round((($pMaxT - $pMinT) / $tRange) * 100)));

        $hSpan = "{$pSlice[0]['label']} – {$pSlice[count($pSlice)-1]['label']}";
        $phases[] = [
            'index' => $pIdx,
            'phaseName' => $pName,
            'hoursSpan' => $hSpan,
            'minTemp' => $pMinT,
            'maxTemp' => $pMaxT,
            'leftPct' => (string)$leftPct,
            'widthPct' => (string)$widthPct,
            'condition' => 'Clean & Partly Cloudy',
            'icon' => $pIcon,
            'aqi' => $pAvgAqi,
            'dominant' => $ph0['dominant'],
            'humidity' => $ph0['humidity'],
            'pm25' => $ph0['pm25'],
            'pm10' => $ph0['pm10'],
            'co' => $ph0['co'],
            'no2' => $ph0['no2'],
            'o3' => $ph0['o3'],
            'advice' => $pAvgAqi <= 50 ? 'Optimal air quality. Perfect window for campus athletics and natural classroom airflow.' : 'Standard outdoor sports and exercise permitted.'
        ];
    }

    return [
        'status' => 'success',
        'type' => 'aqi_forecast',
        'source' => 'supabase_aqi_forecasts_24h',
        'model_name' => "Air Quality Neural Forecast Engine",
        'standard' => 'National Air Quality Index (NAQI)',
        'generated_at' => (function() use ($rows) {
            if (!empty($rows[0]['forecast_generated_at'])) {
                try {
                    $genDt = new DateTime($rows[0]['forecast_generated_at']);
                    $genDt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                    return $genDt->format('Y-m-d H:i:s T');
                } catch (Exception $e) {}
            }
            return date('Y-m-d H:i:s T');
        })(),
        'hero' => [
            'aqi' => $curAqi,
            'dominant' => $curDom,
            'category' => $h0['category'],
            'severity' => $h0['severity'],
            'color' => $h0['color'],
            'health_impact' => $healthImpact,
            'sub_indices' => $h0['sub_indices'],
            'temp' => $h0['temp'],
            'humidity' => $h0['humidity'],
            'dayLow' => !empty($allTemps) ? min($allTemps) : 0,
            'dayHigh' => !empty($allTemps) ? max($allTemps) : 0,
            'pm25' => $h0['pm25'],
            'pm10' => $h0['pm10'],
            'no2' => $h0['no2'],
            'co' => $h0['co'],
            'o3' => $h0['o3'],
            'rain_mm' => $h0['rain_mm'] ?? 0.0,
            'rain_prob' => $h0['rain_prob'] ?? 0.0,
            'rain_24h_mm' => $rfData['total_24h_rainfall_mm'] ?? ($h0['rain_mm'] ?? 0.0),
            'rain_max_mm_h' => $rfData['peak_hourly_rate_mm'] ?? 0.0,
            'rain_status' => $rfData['rain_risk_summary'] ?? 'Dry / Safe',
            'rain_prob_next' => $h0['rain_prob'] ?? 0.0,
            'confidence' => 'Calibrated Machine Learning Output'
        ],
        'parameters' => $parameters,
        'rainfall' => $rfData,
        'advisory' => [
            'title' => $advTitle,
            'severity' => $sevLabel,
            'desc' => $advDesc,
            'sports' => $sportsAdv,
            'ventilation' => $ventAdv,
            'hydration' => $hydrAdv
        ],
        'hourly' => $hourlyList,
        'phases' => $phases
    ];
}

// ── Route ─────────────────────────────────────────────────────────────────────
if ($isNode2 && $action === 'forecast') {
    echo json_encode([
        'status'  => 'standby',
        'node'    => '2',
        'message' => 'Forecast model in standby for Node 2. No forecasted values are generated or passed.',
        'hourly'  => [],
        'phases'  => [],
        'hero'    => null
    ]);
    exit;
}

switch ($action) {

    // ── Single-endpoint reads (still use parallel internally but only 1 URL) ──
    case 'aqi_live': {
        $ckey = "aqi_live_node_{$reqNode}";
        $cached = cacheGet($ckey, 30);
        if ($cached !== null && !empty($cached['data'])) { echo json_encode($cached); break; }

        $data = fetchSupabase("{$supabaseUrl}/rest/v1/{$TABLE_AQI_LIVE}?order=id.desc&limit=1", $supabaseKey);
        $out  = ($data && count($data) > 0) ? ['data' => formatRawReading($data[0])] : null;
        if ($out !== null) {
            cacheSet($ckey, $out);
        } else {
            $stale = cacheGetStale($ckey);
            if ($stale !== null) $out = $stale;
        }
        echo json_encode($out);
        break;
    }

    case 'weather_live': {
        $ckey = "weather_live_node_{$reqNode}";
        $cached = cacheGet($ckey, 30);
        if ($cached !== null && !empty($cached['data'])) { echo json_encode($cached); break; }

        $data = fetchSupabase("{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?order=id.desc&limit=1", $supabaseKey);
        $out  = ($data && count($data) > 0) ? ['data' => formatWeatherReading($data[0])] : null;
        if ($out !== null) {
            cacheSet($ckey, $out);
        } else {
            $stale = cacheGetStale($ckey);
            if ($stale !== null) $out = $stale;
        }
        echo json_encode($out);
        break;
    }

    case 'aqi_live_history': {
        $lim   = min($limit, 120);
        $ckey  = "aqi_live_history_node_{$reqNode}_{$lim}";
        $cached = cacheGet($ckey, 30);
        if ($cached !== null) { echo json_encode($cached); break; }

        $data   = fetchSupabase("{$supabaseUrl}/rest/v1/{$TABLE_AQI_LIVE}?order={$orderColLive}&limit={$lim}", $supabaseKey);
        $result = [];
        if ($data) { foreach ($data as $row) { $f = formatRawReading($row); if ($f) $result[] = $f; } }
        cacheSet($ckey, $result);
        echo json_encode($result);
        break;
    }

    case 'weather_live_history': {
        $lim   = min($limit, 120);
        $ckey  = "weather_live_history_node_{$reqNode}_{$lim}";
        $cached = cacheGet($ckey, 30);
        if ($cached !== null) { echo json_encode($cached); break; }

        $data = fetchSupabase("{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?order={$orderColLive}&limit={$lim}", $supabaseKey);
        $out  = [];
        if ($data) { foreach ($data as $row) { $w = formatWeatherReading($row); if ($w) $out[] = $w; } }
        cacheSet($ckey, $out);
        echo json_encode($out);
        break;
    }

    case 'aqi_history': {
        $lim   = isset($_GET['limit']) ? intval($_GET['limit']) : 500;
        $ckey  = "aqi_history_node_{$reqNode}_{$lim}";
        $cached = cacheGet($ckey, 300); // 5 min TTL — historical data is hourly
        if ($cached !== null) { echo json_encode($cached); break; }

        $data   = fetchSupabase("{$supabaseUrl}/rest/v1/{$TABLE_AQI_HISTORICAL}?order={$orderColHist}&limit={$lim}", $supabaseKey);
        $result = [];
        if ($data) { foreach ($data as $row) { $f = formatRawReading($row); if ($f) $result[] = $f; } }
        $out = ['history' => $result];
        cacheSet($ckey, $out);
        echo json_encode($out);
        break;
    }

    case 'weather_history': {
        $lim   = isset($_GET['limit']) ? intval($_GET['limit']) : 500;
        $ckey  = "weather_history_node_{$reqNode}_{$lim}";
        $cached = cacheGet($ckey, 300);
        if ($cached !== null) { echo json_encode($cached); break; }

        $data   = fetchSupabase("{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_HIST}?order={$orderColHist}&limit={$lim}", $supabaseKey);
        if (empty($data) && $isNode2) {
            $data = fetchSupabase("{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?order={$orderColLive}&limit={$lim}", $supabaseKey);
        }
        $result = [];
        if ($data) {
            foreach ($data as $row) {
                $w = formatWeatherReading($row);
                if ($w) $result[] = $w;
            }
        }
        $out = ['history' => $result];
        cacheSet($ckey, $out);
        echo json_encode($out);
        break;
    }

    // ── Bulk endpoints — all Supabase calls fire IN PARALLEL via curl_multi ──

    case 'live_bulk': {
        $lim   = min($limit, 120);
        $ckey  = "live_bulk_node_{$reqNode}_{$lim}";
        $cached = cacheGet($ckey, 3);
        if ($cached !== null) { echo json_encode($cached); break; }

        $dtMidnightIst = new DateTime('today midnight', new DateTimeZone('Asia/Kolkata'));
        $dtMidnightIst->setTimezone(new DateTimeZone('UTC'));
        $today_utc = $dtMidnightIst->format('Y-m-d\TH:i:s\Z');

        $cachedRainSum = $isNode2 ? null : cacheGet('today_rain_sum_node_' . $reqNode, 60);

        // Fire parallel requests; omit expensive midnight rain query if cached within 60s
        $fetchUrls = [
            'aqi_hist'     => "{$supabaseUrl}/rest/v1/{$TABLE_AQI_LIVE}?order={$orderColLive}&limit={$lim}",
            'weather_hist' => "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?select=*&order={$orderColLive}&limit={$lim}",
        ];
        if (!$isNode2 && $cachedRainSum === null) {
            $fetchUrls['today_rain_rows'] = "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?select=rain_gauge&created_at=gte.{$today_utc}&rain_gauge=gt.0&limit=100";
        }

        $raw = fetchParallel($fetchUrls, $supabaseKey);

        $res = [];
        $res['node']    = $reqNode;
        $res['status']  = 'ONLINE';
        $res['aqi']     = ($raw['aqi_hist']     && count($raw['aqi_hist'])     > 0) ? formatRawReading($raw['aqi_hist'][0])     : null;
        $res['weather'] = ($raw['weather_hist'] && count($raw['weather_hist']) > 0) ? formatWeatherReading($raw['weather_hist'][0]) : null;
        if ($res['aqi']) {
            if (!$res['weather']) $res['weather'] = $res['aqi'];
            else {
                foreach (['temperature', 'humidity', 'wind_speed', 'wind_gust', 'wind_direction', 'rain_gauge'] as $fk) {
                    if (!isset($res['weather'][$fk]) || $res['weather'][$fk] === null) {
                        if (isset($res['aqi'][$fk]) && $res['aqi'][$fk] !== null) $res['weather'][$fk] = $res['aqi'][$fk];
                    }
                }
            }
        }

        $ah = [];
        if ($raw['aqi_hist']) { foreach ($raw['aqi_hist'] as $row) { $f = formatRawReading($row); if ($f) $ah[] = $f; } }
        $res['aqi_history'] = $ah;
        $res['history']     = $ah;

        $wh = [];
        if ($raw['weather_hist']) { foreach ($raw['weather_hist'] as $row) { $w = formatWeatherReading($row); if ($w) $wh[] = $w; } }
        $res['weather_history'] = $wh;

        // Resolve latest coordinates from Supabase records
        $foundLat = null;
        $foundLon = null;
        $foundCoordsDisp = null;
        if (!empty($wh)) {
            foreach ($wh as $wItem) {
                if (!empty($wItem['latitude']) && !empty($wItem['longitude'])) {
                    $foundLat = $wItem['latitude'];
                    $foundLon = $wItem['longitude'];
                    $foundCoordsDisp = $wItem['coords_display'] ?? (formatCoordsDmsPhp($foundLat, $foundLon) ?: "{$foundLat}° N, {$foundLon}° E");
                    break;
                }
            }
        }
        $aqiList = !empty($ah) ? $ah : (!empty($hist) ? $hist : []);
        if ($foundLat === null && !empty($aqiList)) {
            foreach ($aqiList as $aItem) {
                if (!empty($aItem['latitude']) && !empty($aItem['longitude'])) {
                    $foundLat = $aItem['latitude'];
                    $foundLon = $aItem['longitude'];
                    $foundCoordsDisp = $aItem['coords_display'] ?? (formatCoordsDmsPhp($foundLat, $foundLon) ?: "{$foundLat}° N, {$foundLon}° E");
                    break;
                }
            }
        }
        if ($foundLat !== null && $foundLon !== null) {
            $res['latitude'] = $foundLat;
            $res['longitude'] = $foundLon;
            $res['coords_display'] = $foundCoordsDisp;
            if ($res['weather']) {
                if (empty($res['weather']['latitude'])) $res['weather']['latitude'] = $foundLat;
                if (empty($res['weather']['longitude'])) $res['weather']['longitude'] = $foundLon;
                if (empty($res['weather']['coords_display'])) $res['weather']['coords_display'] = $foundCoordsDisp;
            }
            if ($res['aqi']) {
                if (empty($res['aqi']['latitude'])) $res['aqi']['latitude'] = $foundLat;
                if (empty($res['aqi']['longitude'])) $res['aqi']['longitude'] = $foundLon;
                if (empty($res['aqi']['coords_display'])) $res['aqi']['coords_display'] = $foundCoordsDisp;
            }
        }

        $today_rain_sum = 0.0;
        if (isset($raw['today_rain_rows'])) {
            if (!empty($raw['today_rain_rows']) && is_array($raw['today_rain_rows'])) {
                foreach ($raw['today_rain_rows'] as $r) {
                    $rg = (float)($r['rain_gauge'] ?? 0);
                    if ($rg > 0) $today_rain_sum += $rg;
                }
            }
            cacheSet('today_rain_sum_node_' . $reqNode, ['total' => round($today_rain_sum, 2)]);
        } elseif ($cachedRainSum !== null && isset($cachedRainSum['total'])) {
            $today_rain_sum = (float)$cachedRainSum['total'];
        }
        if ($res['weather'] && isset($res['weather']['rain_gauge'])) {
            $curRain = (float)$res['weather']['rain_gauge'];
            if ($curRain > 0 && $today_rain_sum < $curRain) {
                $today_rain_sum = $curRain;
            }
        }
        $res['today_rain_total'] = round($today_rain_sum, 2);

        $n2OtherUrl = ($supabaseUrlNode2 ?: ($get_v_env('SUPABASE_URL_NODE2') ?: ($get_v_env('VITE_SUPABASE_URL_NODE2') ?: 'https://nahabhzcaeppbteuxogg.supabase.co'))) . "/rest/v1/WEATHER_LIVE_NODE2?select=*&order=id.desc&limit=60";
        $n2OtherKey = $supabaseKeyNode2 ?: ($get_v_env('SUPABASE_KEY_NODE2') ?: ($get_v_env('SUPABASE_ANON_KEY_NODE2') ?: ($get_v_env('VITE_SUPABASE_ANON_KEY_NODE2') ?: 'sb_publishable_RXM8H-D8m6qWz2Yy2xA6VQ_TjxxY3bJ')));
        $otherHist = cacheGet('other_weather_node2_60', 15);
        if ($otherHist === null) {
            $rawOther = fetchSupabase($n2OtherUrl, $n2OtherKey);
            $otherHist = [];
            if ($rawOther && is_array($rawOther)) {
                foreach ($rawOther as $orow) {
                    $fo = formatWeatherReading($orow);
                    if ($fo) $otherHist[] = $fo;
                }
            }
            cacheSet('other_weather_node2_60', $otherHist);
        }
        $latestOther = !empty($otherHist) ? $otherHist[0] : null;
        if ($latestOther) {
            if (!$res['weather']) $res['weather'] = $latestOther;
            else {
                $mergeKeys = $isNode2
                    ? ['pressure', 'uv_index', 'solar_radiation', 'wind_speed', 'wind_gust', 'wind_direction', 'rain_gauge', 'temperature', 'humidity']
                    : ['pressure', 'uv_index', 'solar_radiation'];
                foreach ($mergeKeys as $ok) {
                    if ((!isset($res['weather'][$ok]) || $res['weather'][$ok] === null) && isset($latestOther[$ok]) && $latestOther[$ok] !== null) {
                        $res['weather'][$ok] = $latestOther[$ok];
                    }
                }
            }
        }
        if ($isNode2 && $res['aqi']) {
            if (!isset($res['weather'])) $res['weather'] = [];
            if (isset($res['aqi']['temperature']) && $res['aqi']['temperature'] !== null) {
                $res['weather']['temperature'] = $res['aqi']['temperature'];
            }
            if (isset($res['aqi']['humidity']) && $res['aqi']['humidity'] !== null) {
                $res['weather']['humidity'] = $res['aqi']['humidity'];
            }
        }
        $res['other_weather'] = $latestOther;
        $res['other_weather_history'] = $otherHist;

        cacheSet($ckey, $res);
        cacheSet("overview_bulk_node_{$reqNode}", $res);
        if (!$isNode2) {
            cacheSet('overview_bulk', $res);
            cacheSet('live_bulk_60', $res);
            cacheSet('live_bulk_50', $res);
        }
        if ($res['aqi'] && isset($res['aqi']['cpcb_aqi'])) {
            @file_put_contents($CACHE_DIR . "/last_known_hero_node_{$reqNode}.json", json_encode([
                'cpcb_aqi' => $res['aqi']['cpcb_aqi'],
                'pm25' => $res['aqi']['pm25'] ?? $res['aqi']['pm2.5'] ?? null,
                'pm10' => $res['aqi']['pm10'] ?? null,
                'timestamp' => $res['aqi']['timestamp'] ?? null,
                'time' => time()
            ]));
        }
        echo json_encode($res);
        break;
    }

    case 'overview_bulk': {
        $lim   = 60;
        $ckey  = "overview_bulk_node_{$reqNode}";
        $cached = cacheGet($ckey, 3);
        if ($cached !== null && (!empty($cached['aqi']) || !empty($cached['weather']))) { echo json_encode($cached); break; }

        $dtMidnightIst = new DateTime('today midnight', new DateTimeZone('Asia/Kolkata'));
        $dtMidnightIst->setTimezone(new DateTimeZone('UTC'));
        $today_utc = $dtMidnightIst->format('Y-m-d\TH:i:s\Z');

        $cachedRainSum = $isNode2 ? null : cacheGet('today_rain_sum_node_' . $reqNode, 60);

        $fetchUrls = [
            'aqi_hist'     => "{$supabaseUrl}/rest/v1/{$TABLE_AQI_LIVE}?order={$orderColLive}&limit={$lim}",
            'weather_hist' => "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?select=*&order={$orderColLive}&limit={$lim}",
        ];
        if (!$isNode2 && $cachedRainSum === null) {
            $fetchUrls['today_rain_rows'] = "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?select=rain_gauge&created_at=gte.{$today_utc}&rain_gauge=gt.0&limit=100";
        }

        $raw = fetchParallel($fetchUrls, $supabaseKey);

        $res = [];
        $res['node']    = $reqNode;
        $res['status']  = 'ONLINE';
        $res['aqi']     = ($raw['aqi_hist']     && count($raw['aqi_hist'])     > 0) ? formatRawReading($raw['aqi_hist'][0])     : null;
        $res['weather'] = ($raw['weather_hist'] && count($raw['weather_hist']) > 0) ? formatWeatherReading($raw['weather_hist'][0]) : null;
        if ($res['aqi']) {
            if (!$res['weather']) $res['weather'] = $res['aqi'];
            else {
                foreach (['temperature', 'humidity', 'wind_speed', 'wind_gust', 'wind_direction', 'rain_gauge'] as $fk) {
                    if (!isset($res['weather'][$fk]) || $res['weather'][$fk] === null) {
                        if (isset($res['aqi'][$fk]) && $res['aqi'][$fk] !== null) $res['weather'][$fk] = $res['aqi'][$fk];
                    }
                }
            }
        }

        // If momentary network blip occurred and both are null, fall back to last known good cached reading
        if ($res['aqi'] === null && $res['weather'] === null) {
            $stale = cacheGetStale($ckey);
            if ($stale !== null && (!empty($stale['aqi']) || !empty($stale['weather']))) {
                echo json_encode($stale);
                break;
            }
        }

        $hist = [];
        if ($raw['aqi_hist']) { foreach ($raw['aqi_hist'] as $row) { $f = formatRawReading($row); if ($f) $hist[] = $f; } }
        $res['history']     = $hist;
        $res['aqi_history'] = $hist;

        $wh = [];
        if ($raw['weather_hist']) { foreach ($raw['weather_hist'] as $row) { $w = formatWeatherReading($row); if ($w) $wh[] = $w; } }
        $res['weather_history'] = $wh;

        // Resolve latest coordinates from Supabase records
        $foundLat = null;
        $foundLon = null;
        $foundCoordsDisp = null;
        if (!empty($wh)) {
            foreach ($wh as $wItem) {
                if (!empty($wItem['latitude']) && !empty($wItem['longitude'])) {
                    $foundLat = $wItem['latitude'];
                    $foundLon = $wItem['longitude'];
                    $foundCoordsDisp = $wItem['coords_display'] ?? (formatCoordsDmsPhp($foundLat, $foundLon) ?: "{$foundLat}° N, {$foundLon}° E");
                    break;
                }
            }
        }
        $aqiList = !empty($ah) ? $ah : (!empty($hist) ? $hist : []);
        if ($foundLat === null && !empty($aqiList)) {
            foreach ($aqiList as $aItem) {
                if (!empty($aItem['latitude']) && !empty($aItem['longitude'])) {
                    $foundLat = $aItem['latitude'];
                    $foundLon = $aItem['longitude'];
                    $foundCoordsDisp = $aItem['coords_display'] ?? (formatCoordsDmsPhp($foundLat, $foundLon) ?: "{$foundLat}° N, {$foundLon}° E");
                    break;
                }
            }
        }
        if ($foundLat !== null && $foundLon !== null) {
            $res['latitude'] = $foundLat;
            $res['longitude'] = $foundLon;
            $res['coords_display'] = $foundCoordsDisp;
            if ($res['weather']) {
                if (empty($res['weather']['latitude'])) $res['weather']['latitude'] = $foundLat;
                if (empty($res['weather']['longitude'])) $res['weather']['longitude'] = $foundLon;
                if (empty($res['weather']['coords_display'])) $res['weather']['coords_display'] = $foundCoordsDisp;
            }
            if ($res['aqi']) {
                if (empty($res['aqi']['latitude'])) $res['aqi']['latitude'] = $foundLat;
                if (empty($res['aqi']['longitude'])) $res['aqi']['longitude'] = $foundLon;
                if (empty($res['aqi']['coords_display'])) $res['aqi']['coords_display'] = $foundCoordsDisp;
            }
        }

        $today_rain_sum = 0.0;
        if (isset($raw['today_rain_rows'])) {
            if (!empty($raw['today_rain_rows']) && is_array($raw['today_rain_rows'])) {
                foreach ($raw['today_rain_rows'] as $r) {
                    $rg = (float)($r['rain_gauge'] ?? 0);
                    if ($rg > 0) $today_rain_sum += $rg;
                }
            }
            cacheSet('today_rain_sum_node_' . $reqNode, ['total' => round($today_rain_sum, 2)]);
        } elseif ($cachedRainSum !== null && isset($cachedRainSum['total'])) {
            $today_rain_sum = (float)$cachedRainSum['total'];
        }
        if ($res['weather'] && isset($res['weather']['rain_gauge'])) {
            $curRain = (float)$res['weather']['rain_gauge'];
            if ($curRain > 0 && $today_rain_sum < $curRain) {
                $today_rain_sum = $curRain;
            }
        }
        $res['today_rain_total'] = round($today_rain_sum, 2);

        $n2OtherUrl = ($supabaseUrlNode2 ?: ($get_v_env('SUPABASE_URL_NODE2') ?: ($get_v_env('VITE_SUPABASE_URL_NODE2') ?: 'https://nahabhzcaeppbteuxogg.supabase.co'))) . "/rest/v1/WEATHER_LIVE_NODE2?select=*&order=id.desc&limit=60";
        $n2OtherKey = $supabaseKeyNode2 ?: ($get_v_env('SUPABASE_KEY_NODE2') ?: ($get_v_env('SUPABASE_ANON_KEY_NODE2') ?: ($get_v_env('VITE_SUPABASE_ANON_KEY_NODE2') ?: 'sb_publishable_RXM8H-D8m6qWz2Yy2xA6VQ_TjxxY3bJ')));
        $otherHist = cacheGet('other_weather_node2_60', 15);
        if ($otherHist === null) {
            $rawOther = fetchSupabase($n2OtherUrl, $n2OtherKey);
            $otherHist = [];
            if ($rawOther && is_array($rawOther)) {
                foreach ($rawOther as $orow) {
                    $fo = formatWeatherReading($orow);
                    if ($fo) $otherHist[] = $fo;
                }
            }
            cacheSet('other_weather_node2_60', $otherHist);
        }
        $latestOther = !empty($otherHist) ? $otherHist[0] : null;
        if ($latestOther) {
            if (!$res['weather']) $res['weather'] = $latestOther;
            else {
                $mergeKeys = $isNode2
                    ? ['pressure', 'uv_index', 'solar_radiation', 'wind_speed', 'wind_gust', 'wind_direction', 'rain_gauge', 'temperature', 'humidity']
                    : ['pressure', 'uv_index', 'solar_radiation'];
                foreach ($mergeKeys as $ok) {
                    if ((!isset($res['weather'][$ok]) || $res['weather'][$ok] === null) && isset($latestOther[$ok]) && $latestOther[$ok] !== null) {
                        $res['weather'][$ok] = $latestOther[$ok];
                    }
                }
            }
        }
        if ($isNode2 && $res['aqi']) {
            if (!isset($res['weather'])) $res['weather'] = [];
            if (isset($res['aqi']['temperature']) && $res['aqi']['temperature'] !== null) {
                $res['weather']['temperature'] = $res['aqi']['temperature'];
            }
            if (isset($res['aqi']['humidity']) && $res['aqi']['humidity'] !== null) {
                $res['weather']['humidity'] = $res['aqi']['humidity'];
            }
        }
        $res['other_weather'] = $latestOther;
        $res['other_weather_history'] = $otherHist;

        if ($res['aqi'] !== null || $res['weather'] !== null) {
            cacheSet($ckey, $res);
            if (!$isNode2) {
                cacheSet('overview_bulk', $res);
                cacheSet('live_bulk_60', $res);
                cacheSet('live_bulk_50', $res);
            }
            if ($res['aqi'] && isset($res['aqi']['cpcb_aqi'])) {
                @file_put_contents($CACHE_DIR . "/last_known_hero_node_{$reqNode}.json", json_encode([
                    'cpcb_aqi' => $res['aqi']['cpcb_aqi'],
                    'pm25' => $res['aqi']['pm25'] ?? $res['aqi']['pm2.5'] ?? null,
                    'pm10' => $res['aqi']['pm10'] ?? null,
                    'timestamp' => $res['aqi']['timestamp'] ?? null,
                    'time' => time()
                ]));
            }
        }
        echo json_encode($res);
        break;
    }

    case 'history_bulk': {
        $days = isset($_GET['days']) ? max(1, min(60, intval($_GET['days']))) : 1;
        $date = isset($_GET['date']) ? $_GET['date'] : null;
        $today_ist = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
        if (!$date) $date = $today_ist;
        $is_today = (!$date || ($date === $today_ist && $days === 1));
        $filter = '';
        $tsCol = $isNode2 ? 'timestamp' : 'timestamp_hour';

        if ($days > 1) {
            $start_date = date('Y-m-d', strtotime($date . ' -' . $days . ' days'));
            $end_date   = date('Y-m-d', strtotime($date . ' +2 days'));
            $filter = "&{$tsCol}=gte.{$start_date}T00:00:00&{$tsCol}=lt.{$end_date}T00:00:00";
            $lim = min(1500, max(150, ($days * 35)));
        } else if ($date) {
            $start_date = date('Y-m-d', strtotime($date . ' -1 day'));
            $end_date   = date('Y-m-d', strtotime($date . ' +2 days'));
            $filter = "&{$tsCol}=gte.{$start_date}T00:00:00&{$tsCol}=lt.{$end_date}T00:00:00";
            $lim = 150;
        } else {
            $lim = isset($_GET['limit']) ? intval($_GET['limit']) : 500;
        }

        $ckey   = "history_bulk_node_{$reqNode}_" . md5($date . '_' . $days . '_' . $lim);
        $ttl    = $is_today ? 15 : 300; // 15s TTL for today so live telemetry updates dynamically
        $cached = cacheGet($ckey, $ttl);
        if ($cached !== null) { echo json_encode($cached); break; }

        $n2HistUrlBase = $supabaseUrlNode2 ?: ($get_v_env('SUPABASE_URL_NODE2') ?: ($get_v_env('VITE_SUPABASE_URL_NODE2') ?: 'https://nahabhzcaeppbteuxogg.supabase.co'));
        $n2HistKey     = $supabaseKeyNode2 ?: ($get_v_env('SUPABASE_KEY_NODE2') ?: ($get_v_env('SUPABASE_ANON_KEY_NODE2') ?: 'sb_publishable_RXM8H-D8m6qWz2Yy2xA6VQ_TjxxY3bJ'));
        $n2Filter      = str_replace('timestamp_hour', 'timestamp', $filter);

        $urls = [
            'aqi'     => "{$supabaseUrl}/rest/v1/{$TABLE_AQI_HISTORICAL}?order={$tsCol}.desc&limit={$lim}{$filter}",
            'weather' => "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_HIST}?order={$tsCol}.desc&limit={$lim}{$filter}",
        ];

        // Fire requests in parallel
        $raw = fetchParallel($urls, $supabaseKey);
        $rawOther = fetchSupabase("{$n2HistUrlBase}/rest/v1/WEATHER_NODE2?select=*&order=timestamp.desc&limit={$lim}{$n2Filter}", $n2HistKey);
        if (empty($rawOther)) {
            $rawOther = fetchSupabase("{$n2HistUrlBase}/rest/v1/WEATHER_LIVE_NODE2?select=*&order=id.desc&limit=60", $n2HistKey);
        }

        if ($isNode2 && empty($raw['weather'])) {
            $raw['weather'] = fetchSupabase("{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?order={$tsCol}.desc&limit={$lim}", $supabaseKey);
        }

        $res = ['status' => 'success', 'node' => $reqNode, 'aqi' => [], 'weather' => [], 'other_weather' => []];
        if (!empty($raw['aqi']))     { foreach ($raw['aqi']     as $row) { $f = formatRawReading($row);    if ($f) $res['aqi'][]     = $f; } }
        if (!empty($raw['weather'])) { foreach ($raw['weather'] as $row) { $w = formatWeatherReading($row); if ($w) $res['weather'][] = $w; } }
        if (!empty($rawOther) && is_array($rawOther)) {
            foreach ($rawOther as $row) { $ow = formatWeatherReading($row); if ($ow) $res['other_weather'][] = $ow; }
        }

        cacheSet($ckey, $res);
        echo json_encode($res);
        break;
    }

    case 'forecast': {
        @set_time_limit(15);
        $force = isset($_GET['force']) && $_GET['force'] === '1';
        $jsonFile = __DIR__ . '/forecast_data.json';

        // 1. FAST PATH: Read from local forecast_data.json on disk if fresh (< 60s) and has upcoming hours
        $cacheTtlSeconds = 60;
        $isFresh = file_exists($jsonFile) && (time() - @filemtime($jsonFile) < $cacheTtlSeconds);

        if (!$force && $isFresh) {
            $raw = @file_get_contents($jsonFile);
            if ($raw) {
                $payload = json_decode($raw, true);
                if (is_array($payload) && !empty($payload['hourly'])) {
                    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
                    $currentHourStartTs = (clone $now)->setTime((int)$now->format('H'), 0, 0)->getTimestamp();
                    $filteredHourly = [];

                    foreach ($payload['hourly'] as $item) {
                        try {
                            $dt = new DateTime($item['iso'] ?? '');
                            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                            if ($dt->getTimestamp() >= $currentHourStartTs) {
                                $filteredHourly[] = $item;
                            }
                        } catch (Exception $e) {
                            $filteredHourly[] = $item;
                        }
                    }

                    // Only serve from disk if it actually has upcoming hours! If expired, fall through to Supabase query!
                    if (!empty($filteredHourly)) {
                        foreach ($filteredHourly as $idx => &$item) {
                            $item['index'] = $idx;
                            $item['isNext'] = ($idx === 0);
                            if (!empty($item['iso'])) {
                                try {
                                    $dt = new DateTime($item['iso']);
                                    $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                    $item['label'] = $dt->format('g A');
                                } catch (Exception $e) {}
                            }
                            // Normalize AQI category and colors as per the Live page bar
                            if (isset($item['aqi'])) {
                                $av = (int)round($item['aqi']);
                                if ($av <= 50) {
                                    $item['category'] = 'Good'; $item['severity'] = 'GOOD'; $item['color'] = '#16a34a';
                                } elseif ($av <= 100) {
                                    $item['category'] = 'Moderate'; $item['severity'] = 'MODERATE'; $item['color'] = '#ca8a04';
                                } elseif ($av <= 200) {
                                    $item['category'] = 'Poor'; $item['severity'] = 'POOR'; $item['color'] = '#ea580c';
                                } elseif ($av <= 300) {
                                    $item['category'] = 'Unhealthy'; $item['severity'] = 'UNHEALTHY'; $item['color'] = '#e11d48';
                                } elseif ($av <= 400) {
                                    $item['category'] = 'Severe'; $item['severity'] = 'SEVERE'; $item['color'] = '#9333ea';
                                } else {
                                    $item['category'] = 'Hazardous'; $item['severity'] = 'HAZARDOUS'; $item['color'] = '#b91c1c';
                                }
                            }
                        }
                        unset($item);
                        $payload['hourly'] = $filteredHourly;

                        // Synchronize hero card with the next active upcoming timestamp
                        $h0 = $filteredHourly[0];
                        if (!isset($payload['hero']) || !is_array($payload['hero'])) {
                            $payload['hero'] = [];
                        }
                        $payload['hero']['aqi'] = $h0['aqi'];
                        $payload['hero']['dominant'] = $h0['dominant'];
                        $payload['hero']['category'] = $h0['category'];
                        $payload['hero']['severity'] = $h0['severity'];
                        $payload['hero']['color'] = $h0['color'];
                        $payload['hero']['sub_indices'] = $h0['sub_indices'] ?? [];
                        $payload['hero']['temp'] = $h0['temp'];
                        $payload['hero']['humidity'] = $h0['humidity'];
                        $payload['hero']['pm25'] = $h0['pm25'];
                        $payload['hero']['pm10'] = $h0['pm10'];
                        $payload['hero']['no2'] = $h0['no2'];
                        $payload['hero']['co'] = $h0['co'];
                        $payload['hero']['o3'] = $h0['o3'];
                        if (isset($h0['rain_mm'])) {
                            $payload['hero']['rain_mm'] = $h0['rain_mm'];
                            $payload['hero']['rain_prob'] = $h0['rain_prob'] ?? 0;
                            $payload['hero']['is_rain'] = $h0['is_rain'] ?? false;
                            $payload['hero']['rain_intensity'] = $h0['rain_intensity'] ?? 'Dry';
                        }

                        if (isset($payload['parameters'])) {
                            if (isset($payload['parameters']['pm25'])) $payload['parameters']['pm25']['current'] = $h0['pm25'];
                            if (isset($payload['parameters']['pm10'])) $payload['parameters']['pm10']['current'] = $h0['pm10'];
                            if (isset($payload['parameters']['no2']))  $payload['parameters']['no2']['current']  = $h0['no2'];
                            if (isset($payload['parameters']['co']))   $payload['parameters']['co']['current']   = $h0['co'];
                            if (isset($payload['parameters']['o3']))   $payload['parameters']['o3']['current']   = $h0['o3'];
                            if (isset($payload['parameters']['temp'])) $payload['parameters']['temp']['current'] = $h0['temp'];
                            if (isset($payload['parameters']['humidity'])) $payload['parameters']['humidity']['current'] = $h0['humidity'];
                            if (isset($payload['parameters']['rainfall'])) {
                                $payload['parameters']['rainfall']['current'] = $h0['rain_mm'] ?? 0.0;
                                $payload['parameters']['rainfall']['prob'] = $h0['rain_prob'] ?? 0.0;
                                $payload['parameters']['rainfall']['status'] = $h0['rain_intensity'] ?? 'Dry';
                            }
                        }

                        echo json_encode($payload);
                        break;
                    }
                }
            }
        }

        // 2. SECONDARY: If local cache missing or forced, query Supabase aqi_forecasts_24h & weather_forecasts_24h
        $sbForecastUrls = [
            'aqi'     => "{$supabaseUrl}/rest/v1/aqi_forecasts_24h?node_id=eq.node_1&order=forecast_for_time.asc&limit=24",
            'weather' => "{$supabaseUrl}/rest/v1/weather_forecasts_24h?node_id=eq.node_1&order=forecast_for_time.asc&limit=24"
        ];
        $sbData = fetchParallel($sbForecastUrls, $supabaseKey);
        $sbRows = $sbData['aqi'] ?? null;
        $sbWeatherRows = $sbData['weather'] ?? null;

        if (!empty($sbRows)) {
            $payload = buildForecastPayloadFromDbRows($sbRows, $sbWeatherRows);
            if ($payload) {
                @file_put_contents($jsonFile, json_encode($payload));
                echo json_encode($payload);
                break;
            }
        }

        // 3. FALLBACK: Serve raw local file if available
        if (file_exists($jsonFile)) {
            $raw = file_get_contents($jsonFile);
            echo $raw ?: json_encode(['error' => 'Empty forecast data']);
        } else {
            echo json_encode(['error' => 'Forecast data not available']);
        }
        break;
    }

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
