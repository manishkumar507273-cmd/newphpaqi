<?php
if (!defined('IS_INCLUDED')) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
}

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
            }
        }
        if ($supabaseUrl && $supabaseKey) break;
    }
}

// Fallback to system environment variables (Vercel / hosting)
if (empty($supabaseUrl)) $supabaseUrl = getenv('VITE_SUPABASE_URL') ?: getenv('SUPABASE_URL') ?: '';
if (empty($supabaseKey)) $supabaseKey = getenv('VITE_SUPABASE_ANON_KEY') ?: getenv('SUPABASE_KEY') ?: '';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$limit  = isset($_GET['limit'])  ? intval($_GET['limit'])  : 50;

if (!$supabaseUrl || !$supabaseKey) {
    echo json_encode(['error' => 'Supabase credentials not configured.']);
    exit;
}

// ── Table names ───────────────────────────────────────────────────────────────
$TABLE_AQI_LIVE        = 'AQI_LIVE_NODE1';
$TABLE_AQI_HISTORICAL  = 'AQI_NODE1';
$TABLE_WEATHER_LIVE    = 'WEATHER_LIVE_NODE1';
$TABLE_WEATHER_HIST    = 'WEATHER_NODE1';

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
    if (strpos($tsStr, '+00:00') !== false || substr($tsStr,-1) === 'Z') {
        try {
            $dt = new DateTime($tsStr);
            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            return $dt->format('Y-m-d\TH:i:s');
        } catch (Exception $e) {}
    }
    return $tsStr;
}

function adjustTemperature($temp, $tsRaw) {
    if ($temp === null) return null;
    $temp  = (float)$temp;
    $tsStr = parseToIstIso($tsRaw);
    $hour  = null;
    try { if ($tsStr) { $dt = new DateTime($tsStr); $hour = (int)$dt->format('H'); } }
    catch (Exception $e) { return $temp; }
    $adj = 0.0;
    if ($temp > 29.9) {
        if ($hour === 8)                       $adj = -0.5;
        elseif ($hour === 9)                   $adj = -1.0;
        elseif ($hour === 10)                  $adj = -1.5;
        elseif ($hour >= 11 && $hour <= 17)    $adj = -2.0;
        elseif ($hour === 18)                  $adj = -1.5;
        elseif ($hour === 19)                  $adj = -1.0;
        elseif ($hour === 20)                  $adj = -0.5;
    }
    return round($temp + $adj, 2);
}

function formatWeatherReading($row) {
    if (!$row) return null;
    $tsRaw = $row['timestamp_hour'] ?? $row['created_at'] ?? $row['timestamp'] ?? null;
    $row['timestamp'] = parseToIstIso($tsRaw);
    if (isset($row['temperature'])) $row['temperature'] = adjustTemperature($row['temperature'], $tsRaw);
    return $row;
}

function formatRawReading($raw) {
    if (!$raw) return null;
    $tsRaw   = $raw['timestamp_hour'] ?? $raw['created_at'] ?? $raw['timestamp'] ?? null;
    $origTemp = isset($raw['temperature']) ? (float)$raw['temperature'] : 27.0;
    if (isset($raw['temperature'])) $raw['temperature'] = adjustTemperature($raw['temperature'], $tsRaw);

    if (isset($raw['is_calibrated']) && $raw['is_calibrated'] && isset($raw['cpcb_aqi'])) return $raw;

    $tempVal = $origTemp;
    $humVal  = isset($raw['humidity']) ? (float)$raw['humidity'] : 60.0;

    $rawPm25 = (isset($raw['pm25']) && $raw['pm25'] !== null && $raw['pm25'] !== '') ? (float)$raw['pm25'] : ((isset($raw['pm2.5']) && $raw['pm2.5'] !== null && $raw['pm2.5'] !== '') ? (float)$raw['pm2.5'] : null);
    $rawPm10 = (isset($raw['pm10']) && $raw['pm10'] !== null && $raw['pm10'] !== '') ? (float)$raw['pm10'] : null;

    // If Supabase states 0, show 0 everywhere. Do not treat 0 as null.
    $rawCoRaw = (isset($raw['co']) && $raw['co'] !== null && $raw['co'] !== '') ? (float)$raw['co'] : null;
    $rawCo    = $rawCoRaw !== null ? $rawCoRaw : 0.0;

    $rawO3   = (isset($raw['o3']) && $raw['o3'] !== null && $raw['o3'] !== '') ? (float)$raw['o3'] : null;
    $rawNo2  = (isset($raw['no2']) && $raw['no2'] !== null && $raw['no2'] !== '') ? (float)$raw['no2'] : null;

    $calPm25 = $rawPm25 !== null ? ($rawPm25 == 0.0 ? 0.0 : max(0.0, round(0.12532894*$rawPm25 - 0.7801631*$tempVal - 0.15188749*$humVal + 41.495258, 3))) : 0.0;
    $calPm10 = $rawPm10 !== null ? ($rawPm10 == 0.0 ? 0.0 : max(0.0, round(0.10855827*$rawPm10 - 0.6390711*$tempVal - 0.11117823*$humVal + 34.034309, 3))) : 0.0;
    $calCo   = $rawCo   !== null ? ($rawCo   == 0.0 ? 0.0 : max(0.0, round(0.24370718*$rawCo   + 0.02763672*$tempVal + 0.00444147*$humVal - 0.96570843, 3))) : 0.0;
    $calNo2  = $rawNo2  !== null ? ($rawNo2  == 0.0 ? 0.0 : max(0.0, round(0.00920961*$rawNo2  + 0.06749354*$tempVal + 0.00777269*$humVal + 2.903467, 3)))  : 0.0;
    $calO3   = $rawO3   !== null ? ($rawO3   == 0.0 ? 0.0 : max(0.0, round(0.05187092*$rawO3   - 1.4830095*$tempVal  - 0.23912878*$humVal + 91.72351, 3))) : 0.0;

    $subIndices = [
        'pm25' => calcSubindex('pm25', $calPm25),
        'pm10' => calcSubindex('pm10', $calPm10),
        'co'   => calcSubindex('co',   $calCo),
        'no2'  => calcSubindex('no2',  $calNo2),
        'o3'   => calcSubindex('o3',   $calO3),
    ];

    $domKey = array_keys($subIndices)[0];
    $maxVal = $subIndices[$domKey];
    foreach ($subIndices as $k => $v) { if ($v >= $maxVal) { $maxVal = $v; $domKey = $k; } }

    $domNames = ['pm25'=>'PM2.5','pm10'=>'PM10','co'=>'CO','no2'=>'NO₂','o3'=>'O₃'];
    $cpcb_aqi = round($subIndices[$domKey] ?? 0);

    $label = 'Good'; $color = '#65ff50';
    if ($cpcb_aqi > 50  && $cpcb_aqi <= 100) { $label = 'Satisfactory'; $color = '#a3e635'; }
    elseif ($cpcb_aqi > 100 && $cpcb_aqi <= 200) { $label = 'Moderate';     $color = '#facc15'; }
    elseif ($cpcb_aqi > 200 && $cpcb_aqi <= 300) { $label = 'Poor';         $color = '#fb923c'; }
    elseif ($cpcb_aqi > 300 && $cpcb_aqi <= 400) { $label = 'Very Poor';    $color = '#f87171'; }
    elseif ($cpcb_aqi > 400)                      { $label = 'Severe';       $color = '#c084fc'; }

    $ts = $raw['timestamp_hour'] ?? $raw['created_at'] ?? $raw['timestamp'] ?? null;

    return [
        'id'                => $raw['id'] ?? null,
        'timestamp'         => parseToIstIso($ts),
        'temperature'       => $raw['temperature'] ?? null,
        'humidity'          => $raw['humidity']    ?? null,
        'pm25'              => $calPm25 ?? $rawPm25,
        'pm10'              => $calPm10 ?? $rawPm10,
        'co'                => $calCo   ?? $rawCo,
        'o3'                => $calO3   ?? $rawO3,
        'no2'               => $calNo2  ?? $rawNo2,
        'cpcb_aqi'          => $cpcb_aqi,
        'dominant_pollutant'=> $domNames[$domKey] ?? 'N/A',
        'aqi_info'          => ['value'=>$cpcb_aqi,'label'=>$label,'color'=>$color,'standard'=>'CPCB (India)'],
        'wind_speed'        => $raw['wind_speed']    ?? null,
        'wind_gust'         => $raw['wind_gust']     ?? $raw['gust'] ?? null,
        'wind_direction'    => $raw['wind_direction'] ?? null,
        'rain_gauge'        => $raw['rain_gauge']    ?? $raw['rain'] ?? null,
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
        $today_ist = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
        $ckey  = "live_bulk_{$lim}";
        $cached = cacheGet($ckey, 15);
        if ($cached !== null) { echo json_encode($cached); break; }

        // Fire all requests simultaneously with identical ordering and selection as overview_bulk
        $raw = fetchParallel([
            'aqi_hist'     => "{$supabaseUrl}/rest/v1/{$TABLE_AQI_LIVE}?order=created_at.desc.nullslast&limit={$lim}",
            'weather_hist' => "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?select=id,temperature,humidity,wind_speed,rain_gauge,created_at,wind_direction,wind_gust&order=created_at.desc.nullslast&limit={$lim}",
            'today_weather'=> "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_HIST}?select=id,rain_gauge,timestamp_hour&order=id.desc&limit=30",
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
        if (!empty($raw['today_weather']) && is_array($raw['today_weather'])) {
            $today_ist = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
            $start_8am = new DateTime("{$today_ist} 08:00:00", new DateTimeZone('Asia/Kolkata'));
            $end_8am   = (clone $start_8am)->modify('+24 hours');
            $start_ts  = $start_8am->getTimestamp();
            $end_ts    = $end_8am->getTimestamp();

            foreach ($raw['today_weather'] as $row) {
                $tsRaw = $row['timestamp_hour'] ?? '';
                if ($tsRaw) {
                    try {
                        $dt = new DateTime($tsRaw);
                        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                        $t = $dt->getTimestamp();
                        if ($t >= $start_ts && $t < $end_ts) {
                            $r = (float)($row['rain_gauge'] ?? 0);
                            if ($r > 0) $today_rain_sum += $r;
                        }
                    } catch (Exception $e) {}
                }
            }
        }
        $res['today_rain_total'] = round($today_rain_sum, 2);

        cacheSet($ckey, $res);
        echo json_encode($res);
        break;
    }

    case 'overview_bulk': {
        $lim   = 60;
        $today_ist = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
        $ckey  = "overview_bulk";
        $cached = cacheGet($ckey, 15);
        if ($cached !== null) { echo json_encode($cached); break; }

        $raw = fetchParallel([
            'aqi_hist'     => "{$supabaseUrl}/rest/v1/{$TABLE_AQI_LIVE}?order=created_at.desc.nullslast&limit={$lim}",
            'weather_hist' => "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?select=id,temperature,humidity,wind_speed,rain_gauge,created_at,wind_direction,wind_gust&order=created_at.desc.nullslast&limit={$lim}",
            'today_weather'=> "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_HIST}?select=id,rain_gauge,timestamp_hour&order=id.desc&limit=30",
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
        if (!empty($raw['today_weather']) && is_array($raw['today_weather'])) {
            $today_ist = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
            $start_8am = new DateTime("{$today_ist} 08:00:00", new DateTimeZone('Asia/Kolkata'));
            $end_8am   = (clone $start_8am)->modify('+24 hours');
            $start_ts  = $start_8am->getTimestamp();
            $end_ts    = $end_8am->getTimestamp();

            foreach ($raw['today_weather'] as $row) {
                $tsRaw = $row['timestamp_hour'] ?? '';
                if ($tsRaw) {
                    try {
                        $dt = new DateTime($tsRaw);
                        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                        $t = $dt->getTimestamp();
                        if ($t >= $start_ts && $t < $end_ts) {
                            $r = (float)($row['rain_gauge'] ?? 0);
                            if ($r > 0) $today_rain_sum += $r;
                        }
                    } catch (Exception $e) {}
                }
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
        if ($is_today) {
            $urls['aqi_live']     = "{$supabaseUrl}/rest/v1/{$TABLE_AQI_LIVE}?order=id.desc&limit=1";
            $urls['weather_live'] = "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?order=id.desc&limit=1";
        }

        // Fire requests in parallel
        $raw = fetchParallel($urls, $supabaseKey);

        $res = ['aqi' => [], 'weather' => []];
        if ($raw['aqi'])     { foreach ($raw['aqi']     as $row) { $f = formatRawReading($row);    if ($f) $res['aqi'][]     = $f; } }
        if ($raw['weather']) { foreach ($raw['weather'] as $row) { $f = formatWeatherReading($row); if ($f) $res['weather'][] = $f; } }

        // If today, dynamically inject the latest ongoing live telemetry reading if newer than the latest hourly archived point
        if ($is_today) {
            if (!empty($raw['weather_live']) && count($raw['weather_live']) > 0) {
                $liveW = formatWeatherReading($raw['weather_live'][0]);
                if ($liveW) {
                    $liveW['is_live'] = true;
                    $newestArchivedTime = !empty($res['weather']) ? strtotime($res['weather'][0]['timestamp'] ?? '') : 0;
                    $newestArchivedHour = !empty($res['weather']) ? substr($res['weather'][0]['timestamp'] ?? '', 0, 13) : '';
                    $liveTime = strtotime($liveW['timestamp'] ?? '');
                    $liveHour = substr($liveW['timestamp'] ?? '', 0, 13);
                    if ($liveHour && $liveHour === $newestArchivedHour) {
                        $res['weather'][0] = $liveW;
                    } else if ($liveTime - $newestArchivedTime > 300) {
                        array_unshift($res['weather'], $liveW);
                    }
                }
            }
            if (!empty($raw['aqi_live']) && count($raw['aqi_live']) > 0) {
                $liveA = formatRawReading($raw['aqi_live'][0]);
                if ($liveA) {
                    $liveA['is_live'] = true;
                    $newestArchivedTime = !empty($res['aqi']) ? strtotime($res['aqi'][0]['timestamp'] ?? '') : 0;
                    $newestArchivedHour = !empty($res['aqi']) ? substr($res['aqi'][0]['timestamp'] ?? '', 0, 13) : '';
                    $liveTime = strtotime($liveA['timestamp'] ?? '');
                    $liveHour = substr($liveA['timestamp'] ?? '', 0, 13);
                    if ($liveHour && $liveHour === $newestArchivedHour) {
                        $res['aqi'][0] = $liveA;
                    } else if ($liveTime - $newestArchivedTime > 300) {
                        array_unshift($res['aqi'], $liveA);
                    }
                }
            }
        }

        cacheSet($ckey, $res);
        echo json_encode($res);
        break;
    }

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
