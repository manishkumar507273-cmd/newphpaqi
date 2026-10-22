<?php
if (!defined('IS_INCLUDED')) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
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

$TABLE_AQI_LIVE        = 'AQI_LIVE_NODE1';
$TABLE_AQI_HISTORICAL  = 'AQI_NODE1';
$TABLE_WEATHER_LIVE    = 'WEATHER_LIVE_NODE1';
$TABLE_WEATHER_HIST    = 'WEATHER_NODE1';

foreach ($env_paths as $env_path) {
    if (file_exists($env_path)) {
        $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name  = trim($name);
                $value = trim($value);
                if (($name === 'VITE_SUPABASE_URL'      || $name === 'SUPABASE_URL')      && empty($supabaseUrl)) $supabaseUrl = $value;
                if (($name === 'VITE_SUPABASE_ANON_KEY' || $name === 'SUPABASE_KEY')      && empty($supabaseKey)) $supabaseKey = $value;
                if ($name === 'TABLE_AQI_LIVE') $TABLE_AQI_LIVE = $value;
                if ($name === 'TABLE_AQI_HISTORICAL' || $name === 'SOURCE_AQI_TABLE') $TABLE_AQI_HISTORICAL = $value;
                if ($name === 'TABLE_WEATHER_LIVE') $TABLE_WEATHER_LIVE = $value;
                if ($name === 'TABLE_WEATHER_HISTORICAL') $TABLE_WEATHER_HIST = $value;
            }
        }
        if ($supabaseUrl && $supabaseKey) break;
    }
}

// Fallback to system environment variables (Vercel / hosting) or project default
if (empty($supabaseUrl)) $supabaseUrl = getenv('VITE_SUPABASE_URL') ?: getenv('SUPABASE_URL') ?: 'https://sgkdpliqlhgiqsabxzxe.supabase.co';
if (empty($supabaseKey)) $supabaseKey = getenv('VITE_SUPABASE_ANON_KEY') ?: getenv('SUPABASE_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNna2RwbGlxbGhnaXFzYWJ4enhlIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQ4NzgzMTIsImV4cCI6MjEwMDQ1NDMxMn0.vMtbXomFdmOcBkhhSoiyYyp_vFxOhg4MYCFCw9-pL30';
$TABLE_AQI_LIVE        = getenv('TABLE_AQI_LIVE') ?: $TABLE_AQI_LIVE;
$TABLE_AQI_HISTORICAL  = getenv('TABLE_AQI_HISTORICAL') ?: (getenv('SOURCE_AQI_TABLE') ?: $TABLE_AQI_HISTORICAL);
$TABLE_WEATHER_LIVE    = getenv('TABLE_WEATHER_LIVE') ?: $TABLE_WEATHER_LIVE;
$TABLE_WEATHER_HIST    = getenv('TABLE_WEATHER_HISTORICAL') ?: $TABLE_WEATHER_HIST;

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
        $raw = file_get_contents($file);
        if ($raw !== false) return json_decode($raw, true);
    }
    return null;
}

function cacheSet(string $key, $data): void {
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
        if ($running) curl_multi_select($mh, 0.5);
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
    return min(500.0, round((($i_hi-$i_lo)/($c_hi-$c_lo))*($val-$c_lo)+$i_lo, 3));
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
    if ($temp === null) return null;
    return round((float)$temp, 2);
}

function formatWeatherReading($row) {
    if (!$row) return null;
    $tsRaw = $row['timestamp_hour'] ?? $row['created_at'] ?? $row['timestamp'] ?? null;
    $row['timestamp'] = parseToIstIso($tsRaw);
    if (isset($row['temperature']) && $row['temperature'] !== null) $row['temperature'] = round((float)$row['temperature'], 2);
    if (isset($row['humidity']) && $row['humidity'] !== null) $row['humidity'] = round((float)$row['humidity'], 2);
    if (isset($row['wind_speed']) && $row['wind_speed'] !== null) $row['wind_speed'] = round((float)$row['wind_speed'], 2);
    if (isset($row['wind_gust']) && $row['wind_gust'] !== null) $row['wind_gust'] = round((float)$row['wind_gust'], 2);
    if (isset($row['rain_gauge']) && $row['rain_gauge'] !== null) $row['rain_gauge'] = round((float)$row['rain_gauge'], 2);
    return $row;
}

function formatRawReading($raw) {
    if (!$raw) return null;
    $tsRaw = $raw['timestamp_hour'] ?? $raw['created_at'] ?? $raw['timestamp'] ?? null;

    $rawPm25 = (isset($raw['pm25']) && $raw['pm25'] !== null && $raw['pm25'] !== '') ? (float)$raw['pm25'] : ((isset($raw['pm2.5']) && $raw['pm2.5'] !== null && $raw['pm2.5'] !== '') ? (float)$raw['pm2.5'] : null);
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

    $label = 'Good'; $color = '#65ff50';
    if ($cpcb_aqi > 50  && $cpcb_aqi <= 100) { $label = 'Satisfactory'; $color = '#a3e635'; }
    elseif ($cpcb_aqi > 100 && $cpcb_aqi <= 200) { $label = 'Moderate';     $color = '#facc15'; }
    elseif ($cpcb_aqi > 200 && $cpcb_aqi <= 300) { $label = 'Poor';         $color = '#fb923c'; }
    elseif ($cpcb_aqi > 300 && $cpcb_aqi <= 400) { $label = 'Very Poor';    $color = '#f87171'; }
    elseif ($cpcb_aqi > 400)                      { $label = 'Severe';       $color = '#c084fc'; }

    $ts = $raw['timestamp_hour'] ?? $raw['created_at'] ?? $raw['timestamp'] ?? null;

    return [
        'id'                => $raw['id'] ?? null,
        'created_at'        => $raw['created_at'] ?? ($raw['timestamp'] ?? null),
        'timestamp'         => parseToIstIso($ts),
        'temperature'       => isset($raw['temperature']) && $raw['temperature'] !== null ? round((float)$raw['temperature'], 2) : null,
        'humidity'          => isset($raw['humidity']) && $raw['humidity'] !== null ? round((float)$raw['humidity'], 2) : null,
        'pm25'              => $rawPm25,
        'pm2.5'             => $rawPm25,
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

function buildForecastPayloadFromDbRows(array $rows): ?array {
    if (empty($rows)) return null;

    // Discard any rows that have already elapsed in the past
    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $nowTs = $now->getTimestamp();

    $futureRows = [];
    foreach ($rows as $r) {
        $tsStr = $r['forecast_for_time'] ?? '';
        try {
            $dt = new DateTime($tsStr);
            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            if ($dt->getTimestamp() > $nowTs) {
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

        $p_pm25 = max(0.5, round((float)($r['pm2_5_ug_m3'] ?? 15), 2));
        $p_pm10 = max(1.0, round((float)($r['pm10_ug_m3'] ?? 25), 2));
        $p_no2  = max(0.1, round((float)($r['no2_ug_m3'] ?? 5), 2));
        $p_co   = max(0.01, round((float)($r['co_mg_m3'] ?? 0.2), 3));
        $p_o3   = max(1.0, round((float)($r['ozone_ug_m3'] ?? 30), 2));
        $p_temp = round((float)($r['temperature_c'] ?? 28), 1);
        $p_hum  = min(100.0, max(20.0, round((float)($r['humidity_pct'] ?? 70), 1)));

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

        $cat = 'Good'; $sev = 'GOOD'; $col = '#00bfa5';
        if ($aqiVal > 50 && $aqiVal <= 100) { $cat = 'Satisfactory'; $sev = 'SATISFACTORY'; $col = '#84cc16'; }
        elseif ($aqiVal > 100 && $aqiVal <= 200) { $cat = 'Moderate'; $sev = 'MODERATE'; $col = '#f59e0b'; }
        elseif ($aqiVal > 200 && $aqiVal <= 300) { $cat = 'Poor'; $sev = 'POOR'; $col = '#f97316'; }
        elseif ($aqiVal > 300 && $aqiVal <= 400) { $cat = 'Very Poor'; $sev = 'VERY_POOR'; $col = '#ef4444'; }
        elseif ($aqiVal > 400) { $cat = 'Severe'; $sev = 'SEVERE'; $col = '#7f1d1d'; }

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
        'pm25'     => $calcStats($pm25Vals, 60.0, $h0['sub_indices']['PM2.5']),
        'pm10'     => $calcStats($pm10Vals, 100.0, $h0['sub_indices']['PM10']),
        'no2'      => $calcStats($no2Vals,  80.0, $h0['sub_indices']['NO₂']),
        'co'       => $calcStats($coVals,   2.0,  $h0['sub_indices']['CO']),
        'o3'       => $calcStats($o3Vals,   100.0,$h0['sub_indices']['O₃']),
        'temp'     => $calcStats($tempVals, 0.0, 0.0),
        'humidity' => $calcStats($humVals,  0.0, 0.0),
    ];

    if ($curAqi <= 50) {
        $advTitle = 'Air Quality Ideal (Good) · Minimal Health Impact';
        $advDesc = "Predicted ambient AQI is {$curAqi} (Dominant: {$curDom}). All criteria pollutant concentrations are within safe limits.";
        $sevLabel = 'GOOD';
        $sportsAdv = 'Optimal for athletic training and sports';
        $ventAdv = 'Full campus natural cross-ventilation advised';
        $hydrAdv = 'Standard hydration (2.5L/day recommended)';
    } elseif ($curAqi <= 100) {
        $advTitle = 'Air Quality Satisfactory · Minor Sensitive Group Impact';
        $advDesc = "Predicted ambient AQI is {$curAqi} (Dominant: {$curDom}). Minimal impact on healthy individuals.";
        $sevLabel = 'SATISFACTORY';
        $sportsAdv = 'Standard outdoor sports and exercise permitted';
        $ventAdv = 'Normal classroom and lab ventilation';
        $hydrAdv = 'Keep hydrated during warm afternoon peaks';
    } else {
        $advTitle = 'Air Quality Moderate · Sensitive Group Advisory';
        $advDesc = "Predicted ambient AQI is {$curAqi} dominated by {$curDom}. Moderate prolonged outdoor exposure if sensitive.";
        $sevLabel = 'MODERATE';
        $sportsAdv = 'Consider scheduling strenuous sports in early morning';
        $ventAdv = 'Use filtered ventilation during peak hours';
        $hydrAdv = 'Adequate fluid intake recommended';
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
        'generated_at' => date('Y-m-d H:i:s T'),
        'hero' => [
            'aqi' => $curAqi,
            'dominant' => $curDom,
            'category' => $h0['category'],
            'severity' => $h0['severity'],
            'color' => $h0['color'],
            'health_impact' => ($curAqi <= 50 ? 'Minimal Impact' : ($curAqi <= 100 ? 'Minor breathing discomfort to sensitive people' : 'Breathing discomfort to people with lungs, asthma and heart diseases')),
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
            'confidence' => 'Calibrated Machine Learning Output'
        ],
        'parameters' => $parameters,
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
switch ($action) {

    // ── Single-endpoint reads (still use parallel internally but only 1 URL) ──
    case 'aqi_live': {
        $ckey = "aqi_live";
        $cached = cacheGet($ckey, 30);
        if ($cached !== null) { echo json_encode($cached); break; }

        $data = fetchSupabase("{$supabaseUrl}/rest/v1/{$TABLE_AQI_LIVE}?order=id.desc&limit=1", $supabaseKey);
        $out  = ($data && count($data) > 0) ? ['data' => formatRawReading($data[0])] : null;
        cacheSet($ckey, $out);
        echo json_encode($out);
        break;
    }

    case 'weather_live': {
        $ckey = "weather_live";
        $cached = cacheGet($ckey, 30);
        if ($cached !== null) { echo json_encode($cached); break; }

        $data = fetchSupabase("{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?order=id.desc&limit=1", $supabaseKey);
        $out  = ($data && count($data) > 0) ? ['data' => $data[0]] : null;
        cacheSet($ckey, $out);
        echo json_encode($out);
        break;
    }

    case 'aqi_live_history': {
        $lim   = min($limit, 120);
        $ckey  = "aqi_live_history_{$lim}";
        $cached = cacheGet($ckey, 30);
        if ($cached !== null) { echo json_encode($cached); break; }

        $data   = fetchSupabase("{$supabaseUrl}/rest/v1/{$TABLE_AQI_LIVE}?order=created_at.desc.nullslast&limit={$lim}", $supabaseKey);
        $result = [];
        if ($data) { foreach ($data as $row) { $f = formatRawReading($row); if ($f) $result[] = $f; } }
        cacheSet($ckey, $result);
        echo json_encode($result);
        break;
    }

    case 'weather_live_history': {
        $lim   = min($limit, 120);
        $ckey  = "weather_live_history_{$lim}";
        $cached = cacheGet($ckey, 30);
        if ($cached !== null) { echo json_encode($cached); break; }

        $data = fetchSupabase("{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?order=created_at.desc.nullslast&limit={$lim}", $supabaseKey);
        $out  = $data ?: [];
        cacheSet($ckey, $out);
        echo json_encode($out);
        break;
    }

    case 'aqi_history': {
        $lim   = isset($_GET['limit']) ? intval($_GET['limit']) : 500;
        $ckey  = "aqi_history_{$lim}";
        $cached = cacheGet($ckey, 300); // 5 min TTL — historical data is hourly
        if ($cached !== null) { echo json_encode($cached); break; }

        $data   = fetchSupabase("{$supabaseUrl}/rest/v1/{$TABLE_AQI_HISTORICAL}?order=timestamp_hour.desc&limit={$lim}", $supabaseKey);
        $result = [];
        if ($data) { foreach ($data as $row) { $f = formatRawReading($row); if ($f) $result[] = $f; } }
        $out = ['history' => $result];
        cacheSet($ckey, $out);
        echo json_encode($out);
        break;
    }

    case 'weather_history': {
        $lim   = isset($_GET['limit']) ? intval($_GET['limit']) : 500;
        $ckey  = "weather_history_{$lim}";
        $cached = cacheGet($ckey, 300);
        if ($cached !== null) { echo json_encode($cached); break; }

        $data   = fetchSupabase("{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_HIST}?order=timestamp_hour.desc&limit={$lim}", $supabaseKey);
        $result = [];
        if ($data) {
            foreach ($data as $row) {
                $ts = $row['timestamp_hour'] ?? $row['created_at'] ?? $row['timestamp'] ?? null;
                $row['timestamp'] = parseToIstIso($ts);
                $result[] = $row;
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
        $ckey  = "live_bulk_{$lim}";
        $cached = cacheGet($ckey, 3);
        if ($cached !== null) { echo json_encode($cached); break; }

        $dtMidnightIst = new DateTime('today midnight', new DateTimeZone('Asia/Kolkata'));
        $dtMidnightIst->setTimezone(new DateTimeZone('UTC'));
        $today_utc = $dtMidnightIst->format('Y-m-d\TH:i:s\Z');

        // Fire all requests simultaneously with live rain rows
        $raw = fetchParallel([
            'aqi_hist'        => "{$supabaseUrl}/rest/v1/{$TABLE_AQI_LIVE}?order=created_at.desc.nullslast&limit={$lim}",
            'weather_hist'    => "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?select=id,temperature,humidity,wind_speed,rain_gauge,created_at,wind_direction,wind_gust&order=created_at.desc.nullslast&limit={$lim}",
            'today_rain_rows' => "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?select=rain_gauge&created_at=gte.{$today_utc}&rain_gauge=gt.0",
        ], $supabaseKey);

        $res = [];
        $res['aqi']     = ($raw['aqi_hist']     && count($raw['aqi_hist'])     > 0) ? formatRawReading($raw['aqi_hist'][0])     : null;
        $res['weather'] = ($raw['weather_hist'] && count($raw['weather_hist']) > 0) ? formatWeatherReading($raw['weather_hist'][0]) : null;

        $ah = [];
        if ($raw['aqi_hist']) { foreach ($raw['aqi_hist'] as $row) { $f = formatRawReading($row); if ($f) $ah[] = $f; } }
        $res['aqi_history'] = $ah;

        $wh = [];
        if ($raw['weather_hist']) { foreach ($raw['weather_hist'] as $row) { $w = formatWeatherReading($row); if ($w) $wh[] = $w; } }
        $res['weather_history'] = $wh;

        $today_rain_sum = 0.0;
        if (!empty($raw['today_rain_rows']) && is_array($raw['today_rain_rows'])) {
            foreach ($raw['today_rain_rows'] as $r) {
                $rg = (float)($r['rain_gauge'] ?? 0);
                if ($rg > 0) $today_rain_sum += $rg;
            }
        }
        if ($res['weather'] && isset($res['weather']['rain_gauge'])) {
            $curRain = (float)$res['weather']['rain_gauge'];
            if ($curRain > 0 && $today_rain_sum < $curRain) {
                $today_rain_sum = $curRain;
            }
        }
        $res['today_rain_total'] = round($today_rain_sum, 2);

        cacheSet($ckey, $res);
        echo json_encode($res);
        break;
    }

    case 'overview_bulk': {
        $lim   = 60;
        $ckey  = "overview_bulk";
        $cached = cacheGet($ckey, 3);
        if ($cached !== null) { echo json_encode($cached); break; }

        $dtMidnightIst = new DateTime('today midnight', new DateTimeZone('Asia/Kolkata'));
        $dtMidnightIst->setTimezone(new DateTimeZone('UTC'));
        $today_utc = $dtMidnightIst->format('Y-m-d\TH:i:s\Z');

        $raw = fetchParallel([
            'aqi_hist'        => "{$supabaseUrl}/rest/v1/{$TABLE_AQI_LIVE}?order=created_at.desc.nullslast&limit={$lim}",
            'weather_hist'    => "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?select=id,temperature,humidity,wind_speed,rain_gauge,created_at,wind_direction,wind_gust&order=created_at.desc.nullslast&limit={$lim}",
            'today_rain_rows' => "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?select=rain_gauge&created_at=gte.{$today_utc}&rain_gauge=gt.0",
        ], $supabaseKey);

        $res = [];
        $res['aqi']     = ($raw['aqi_hist']     && count($raw['aqi_hist'])     > 0) ? formatRawReading($raw['aqi_hist'][0])     : null;
        $res['weather'] = ($raw['weather_hist'] && count($raw['weather_hist']) > 0) ? formatWeatherReading($raw['weather_hist'][0]) : null;

        $hist = [];
        if ($raw['aqi_hist']) { foreach ($raw['aqi_hist'] as $row) { $f = formatRawReading($row); if ($f) $hist[] = $f; } }
        $res['history'] = $hist;

        $wh = [];
        if ($raw['weather_hist']) { foreach ($raw['weather_hist'] as $row) { $w = formatWeatherReading($row); if ($w) $wh[] = $w; } }
        $res['weather_history'] = $wh;

        $today_rain_sum = 0.0;
        if (!empty($raw['today_rain_rows']) && is_array($raw['today_rain_rows'])) {
            foreach ($raw['today_rain_rows'] as $r) {
                $rg = (float)($r['rain_gauge'] ?? 0);
                if ($rg > 0) $today_rain_sum += $rg;
            }
        }
        if ($res['weather'] && isset($res['weather']['rain_gauge'])) {
            $curRain = (float)$res['weather']['rain_gauge'];
            if ($curRain > 0 && $today_rain_sum < $curRain) {
                $today_rain_sum = $curRain;
            }
        }
        $res['today_rain_total'] = round($today_rain_sum, 2);

        cacheSet($ckey, $res);
        echo json_encode($res);
        break;
    }

    case 'history_bulk': {
        $date = isset($_GET['date']) ? $_GET['date'] : null;
        $today_ist = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
        $is_today = (!$date || $date === $today_ist);
        $filter = '';

        if ($date) {
            $start_date = date('Y-m-d', strtotime($date . ' -1 day'));
            $end_date   = date('Y-m-d', strtotime($date . ' +2 days'));
            $filter = "&timestamp_hour=gte.{$start_date}T00:00:00&timestamp_hour=lt.{$end_date}T00:00:00";
            $lim = 150;
        } else {
            $lim = isset($_GET['limit']) ? intval($_GET['limit']) : 500;
        }

        $ckey   = "history_bulk_" . md5($date . $lim);
        $ttl    = $is_today ? 15 : 300; // 15s TTL for today so live telemetry updates dynamically
        $cached = cacheGet($ckey, $ttl);
        if ($cached !== null) { echo json_encode($cached); break; }

        $urls = [
            'aqi'     => "{$supabaseUrl}/rest/v1/{$TABLE_AQI_HISTORICAL}?order=timestamp_hour.desc&limit={$lim}{$filter}",
            'weather' => "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_HIST}?order=timestamp_hour.desc&limit={$lim}{$filter}",
        ];

        // Fire requests in parallel
        $raw = fetchParallel($urls, $supabaseKey);

        $res = ['aqi' => [], 'weather' => []];
        if ($raw['aqi'])     { foreach ($raw['aqi']     as $row) { $f = formatRawReading($row);    if ($f) $res['aqi'][]     = $f; } }
        if ($raw['weather']) { foreach ($raw['weather'] as $row) { $f = formatWeatherReading($row); if ($f) $res['weather'][] = $f; } }

        cacheSet($ckey, $res);
        echo json_encode($res);
        break;
    }

    case 'forecast': {
        @set_time_limit(15);
        $force = isset($_GET['force']) && $_GET['force'] === '1';
        $jsonFile = __DIR__ . '/forecast_data.json';

        // 1. FAST PATH: Read from local forecast_data.json on disk (< 1ms execution time)
        if (!$force && file_exists($jsonFile)) {
            $raw = @file_get_contents($jsonFile);
            if ($raw) {
                $payload = json_decode($raw, true);
                if (is_array($payload) && !empty($payload['hourly'])) {
                    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
                    $nowTs = $now->getTimestamp();
                    $filteredHourly = [];

                    foreach ($payload['hourly'] as $item) {
                        try {
                            $dt = new DateTime($item['iso'] ?? '');
                            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                            if ($dt->getTimestamp() > $nowTs) {
                                $filteredHourly[] = $item;
                            }
                        } catch (Exception $e) {
                            $filteredHourly[] = $item;
                        }
                    }

                    if (empty($filteredHourly)) {
                        $nextHourDt = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
                        $nextHourDt->modify('+1 hour')->setTime((int)$nextHourDt->format('H'), 0, 0);
                        foreach ($payload['hourly'] as $idx => $item) {
                            $itemDt = clone $nextHourDt;
                            $itemDt->modify("+{$idx} hour");
                            $item['iso'] = $itemDt->format(DateTime::ATOM);
                            $filteredHourly[] = $item;
                        }
                    }

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

                        if (isset($payload['parameters'])) {
                            if (isset($payload['parameters']['pm25'])) $payload['parameters']['pm25']['current'] = $h0['pm25'];
                            if (isset($payload['parameters']['pm10'])) $payload['parameters']['pm10']['current'] = $h0['pm10'];
                            if (isset($payload['parameters']['no2']))  $payload['parameters']['no2']['current']  = $h0['no2'];
                            if (isset($payload['parameters']['co']))   $payload['parameters']['co']['current']   = $h0['co'];
                            if (isset($payload['parameters']['o3']))   $payload['parameters']['o3']['current']   = $h0['o3'];
                            if (isset($payload['parameters']['temp'])) $payload['parameters']['temp']['current'] = $h0['temp'];
                            if (isset($payload['parameters']['humidity'])) $payload['parameters']['humidity']['current'] = $h0['humidity'];
                        }

                        echo json_encode($payload);
                        break;
                    }
                }
            }
        }

        // 2. SECONDARY: If local cache missing or forced, query Supabase aqi_forecasts_24h
        $sbForecastUrl = "{$supabaseUrl}/rest/v1/aqi_forecasts_24h?node_id=eq.node_1&order=forecast_for_time.asc&limit=24";
        $sbRows = fetchSupabase($sbForecastUrl, $supabaseKey);

        if (!empty($sbRows)) {
            $payload = buildForecastPayloadFromDbRows($sbRows);
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
