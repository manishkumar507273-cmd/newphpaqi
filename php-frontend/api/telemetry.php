<?php
if (!defined('IS_INCLUDED')) {
    header('Content-Type: application/json');
}

// Parse credentials from .env
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
                $name = trim($name);
                $value = trim($value);
                if (($name === 'VITE_SUPABASE_URL' || $name === 'SUPABASE_URL') && empty($supabaseUrl)) $supabaseUrl = $value;
                if (($name === 'VITE_SUPABASE_ANON_KEY' || $name === 'SUPABASE_KEY') && empty($supabaseKey)) $supabaseKey = $value;
            }
        }
        if ($supabaseUrl && $supabaseKey) break;
    }
}

// Fallback to Vercel/System environment variables if not found in .env file
if (empty($supabaseUrl)) $supabaseUrl = getenv('VITE_SUPABASE_URL') ?: getenv('SUPABASE_URL') ?: '';
if (empty($supabaseKey)) $supabaseKey = getenv('VITE_SUPABASE_ANON_KEY') ?: getenv('SUPABASE_KEY') ?: '';


$action = isset($_GET['action']) ? $_GET['action'] : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

if (!$supabaseUrl || !$supabaseKey) {
    echo json_encode(['error' => 'Supabase credentials not configured.']);
    exit;
}

function fetchSupabase($url, $key) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // Fast DNS
    curl_setopt($ch, CURLOPT_ENCODING, ''); // Fast gzip transfer
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'Connection: keep-alive'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true);
    }
    return null;
}

$TABLE_AQI_LIVE = 'AQI_LIVE_NODE1';
$TABLE_AQI_HISTORICAL = 'AQI_NODE1';
$TABLE_WEATHER_LIVE = 'WEATHER_LIVE_NODE1';
$TABLE_WEATHER_HISTORICAL = 'WEATHER_NODE1';

function calcSubindex($paramKey, $cp) {
    $CPCB_BREAKPOINTS = [
        'pm25' => [[0.0, 30.0, 0, 50], [30.0, 60.0, 51, 100], [60.0, 90.0, 101, 200], [90.0, 120.0, 201, 300], [120.0, 250.0, 301, 400], [250.0, 500.0, 401, 500]],
        'pm10' => [[0.0, 50.0, 0, 50], [50.0, 100.0, 51, 100], [100.0, 250.0, 101, 200], [250.0, 350.0, 201, 300], [350.0, 430.0, 301, 400], [430.0, 600.0, 401, 500]],
        'co' => [[0.0, 1.0, 0, 50], [1.0, 2.0, 51, 100], [2.0, 10.0, 101, 200], [10.0, 17.0, 201, 300], [17.0, 34.0, 301, 400], [34.0, 50.0, 401, 500]],
        'no2' => [[0.0, 40.0, 0, 50], [40.0, 80.0, 51, 100], [80.0, 180.0, 101, 200], [180.0, 280.0, 201, 300], [280.0, 400.0, 301, 400], [400.0, 500.0, 401, 500]],
        'o3' => [[0.0, 50.0, 0, 50], [50.0, 100.0, 51, 100], [100.0, 168.0, 101, 200], [168.0, 208.0, 201, 300], [208.0, 748.0, 301, 400], [748.0, 1000.0, 401, 500]],
    ];
    if ($cp === null || !isset($CPCB_BREAKPOINTS[$paramKey])) return 0.0;
    $val = max(0.0, (float)$cp);
    $tiers = $CPCB_BREAKPOINTS[$paramKey];
    foreach ($tiers as $tier) {
        $c_lo = $tier[0]; $c_hi = $tier[1]; $i_lo = $tier[2]; $i_hi = $tier[3];
        if ($val <= $c_hi) {
            return round((($i_hi - $i_lo) / ($c_hi - $c_lo)) * ($val - $c_lo) + $i_lo, 3);
        }
    }
    $last_tier = end($tiers);
    $c_lo = $last_tier[0]; $c_hi = $last_tier[1]; $i_lo = $last_tier[2]; $i_hi = $last_tier[3];
    return min(500.0, round((($i_hi - $i_lo) / ($c_hi - $c_lo)) * ($val - $c_lo) + $i_lo, 3));
}

function parseToIstIso($tsRaw) {
    if (!$tsRaw) return '';
    $tsStr = trim((string)$tsRaw);
    if (strpos($tsStr, '+00:00') !== false || substr($tsStr, -1) === 'Z') {
        try {
            $dt = new DateTime($tsStr);
            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            return $dt->format('Y-m-d\TH:i:s');
        } catch(Exception $e) {}
    }
    return $tsStr;
}

function adjustTemperature($temp, $tsRaw) {
    if ($temp === null) return null;
    $temp = (float)$temp;
    $tsStr = parseToIstIso($tsRaw);
    $hour = null;
    try {
        if ($tsStr) {
            $dt = new DateTime($tsStr);
            $hour = (int)$dt->format('H');
        }
    } catch(Exception $e) {
        return $temp;
    }
    
    $adj = 0.0;
    
    // Only apply daytime heat compensation if temperature is above 29.9°C
    if ($temp > 29.9) {
        if ($hour === 8) $adj = -0.5;
        else if ($hour === 9) $adj = -1.0;
        else if ($hour === 10) $adj = -1.5;
        else if ($hour >= 11 && $hour <= 17) $adj = -2.0;
        else if ($hour === 18) $adj = -1.5;
        else if ($hour === 19) $adj = -1.0;
        else if ($hour === 20) $adj = -0.5;
    }
    
    return round($temp + $adj, 2);
}

function formatWeatherReading($row) {
    if (!$row) return null;
    $tsRaw = $row['timestamp_hour'] ?? $row['created_at'] ?? $row['timestamp'] ?? null;
    $row['timestamp'] = parseToIstIso($tsRaw);
    if (isset($row['temperature'])) {
        $row['temperature'] = adjustTemperature($row['temperature'], $tsRaw);
    }
    return $row;
}

function formatRawReading($raw) {
    if (!$raw) return null;
    $tsRaw = $raw['timestamp_hour'] ?? $raw['created_at'] ?? $raw['timestamp'] ?? null;
    
    $origTemp = isset($raw['temperature']) ? (float)$raw['temperature'] : 27.0;

    if (isset($raw['temperature'])) {
        $raw['temperature'] = adjustTemperature($raw['temperature'], $tsRaw);
    }
    
    if (isset($raw['is_calibrated']) && $raw['is_calibrated'] && isset($raw['cpcb_aqi'])) return $raw;

    $tempVal = $origTemp;
    $humVal = isset($raw['humidity']) ? (float)$raw['humidity'] : 60.0;

    $rawPm25 = isset($raw['pm25']) ? (float)$raw['pm25'] : (isset($raw['pm2.5']) ? (float)$raw['pm2.5'] : null);
    $rawPm10 = isset($raw['pm10']) ? (float)$raw['pm10'] : null;
    $rawCo = isset($raw['co']) ? (float)$raw['co'] : null;
    $rawO3 = isset($raw['o3']) ? (float)$raw['o3'] : null;
    $rawNo2 = isset($raw['no2']) ? (float)$raw['no2'] : null;

    $calPm25 = $rawPm25 !== null ? max(0.0, round(0.12532894 * $rawPm25 - 0.7801631 * $tempVal - 0.15188749 * $humVal + 41.495258, 3)) : null;
    $calPm10 = $rawPm10 !== null ? max(0.0, round(0.10855827 * $rawPm10 - 0.6390711 * $tempVal - 0.11117823 * $humVal + 34.034309, 3)) : null;
    $calCo = $rawCo !== null ? max(0.0, round(0.24370718 * $rawCo + 0.02763672 * $tempVal + 0.00444147 * $humVal - 0.96570843, 3)) : null;
    $calNo2 = $rawNo2 !== null ? max(0.0, round(0.00920961 * $rawNo2 + 0.06749354 * $tempVal + 0.00777269 * $humVal + 2.903467, 3)) : null;
    $calO3 = $rawO3 !== null ? max(0.0, round(0.05187092 * $rawO3 - 1.4830095 * $tempVal - 0.23912878 * $humVal + 91.72351, 3)) : null;

    $subIndices = [
        'pm25' => calcSubindex('pm25', $calPm25 ?? $rawPm25),
        'pm10' => calcSubindex('pm10', $calPm10 ?? $rawPm10),
        'co' => calcSubindex('co', $calCo ?? $rawCo),
        'no2' => calcSubindex('no2', $calNo2 ?? $rawNo2),
        'o3' => calcSubindex('o3', $calO3 ?? $rawO3),
    ];

    $domKey = array_keys($subIndices)[0];
    $maxVal = $subIndices[$domKey];
    foreach($subIndices as $k => $v) {
        if ($v >= $maxVal) {
            $maxVal = $v;
            $domKey = $k;
        }
    }
    
    $domNames = ['pm25' => 'PM2.5', 'pm10' => 'PM10', 'co' => 'CO', 'no2' => 'NO₂', 'o3' => 'O₃'];
    $cpcb_aqi = round($subIndices[$domKey] ?? 0);

    $label = "Good"; $color = "#65ff50";
    if ($cpcb_aqi > 50 && $cpcb_aqi <= 100) { $label = "Satisfactory"; $color = "#a3e635"; }
    else if ($cpcb_aqi > 100 && $cpcb_aqi <= 200) { $label = "Moderate"; $color = "#facc15"; }
    else if ($cpcb_aqi > 200 && $cpcb_aqi <= 300) { $label = "Poor"; $color = "#fb923c"; }
    else if ($cpcb_aqi > 300 && $cpcb_aqi <= 400) { $label = "Very Poor"; $color = "#f87171"; }
    else if ($cpcb_aqi > 400) { $label = "Severe"; $color = "#c084fc"; }

    $ts = $raw['timestamp_hour'] ?? $raw['created_at'] ?? $raw['timestamp'] ?? null;

    return [
        'id' => $raw['id'] ?? null,
        'timestamp' => parseToIstIso($ts),
        'temperature' => $raw['temperature'] ?? null,
        'humidity' => $raw['humidity'] ?? null,
        'pm25' => $calPm25 ?? $rawPm25,
        'pm10' => $calPm10 ?? $rawPm10,
        'co' => $calCo ?? $rawCo,
        'o3' => $calO3 ?? $rawO3,
        'no2' => $calNo2 ?? $rawNo2,
        'cpcb_aqi' => $cpcb_aqi,
        'dominant_pollutant' => $domNames[$domKey] ?? 'N/A',
        'aqi_info' => ['value' => $cpcb_aqi, 'label' => $label, 'color' => $color, 'standard' => 'CPCB (India)'],
        'wind_speed' => $raw['wind_speed'] ?? null,
        'wind_gust' => $raw['wind_gust'] ?? $raw['gust'] ?? null,
        'wind_direction' => $raw['wind_direction'] ?? null,
        'rain_gauge' => $raw['rain_gauge'] ?? $raw['rain'] ?? null
    ];
}

switch ($action) {
    case 'aqi_live':
        $url = "{$supabaseUrl}/rest/v1/{$TABLE_AQI_LIVE}?order=id.desc&limit=1";
        $data = fetchSupabase($url, $supabaseKey);
        if ($data && count($data) > 0) {
            echo json_encode(['data' => formatRawReading($data[0])]);
        } else {
            echo json_encode(null);
        }
        break;

    case 'weather_live':
        $url = "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?order=id.desc&limit=1";
        $data = fetchSupabase($url, $supabaseKey);
        if ($data && count($data) > 0) {
            echo json_encode(['data' => $data[0]]);
        } else {
            echo json_encode(null);
        }
        break;

    case 'aqi_history':
        $lim = isset($_GET['limit']) ? intval($_GET['limit']) : 500;
        // historical data
        $url = "{$supabaseUrl}/rest/v1/{$TABLE_AQI_HISTORICAL}?order=timestamp_hour.desc&limit={$lim}";
        $data = fetchSupabase($url, $supabaseKey);
        $result = [];
        if ($data) {
            foreach ($data as $row) {
                $f = formatRawReading($row);
                if ($f) $result[] = $f;
            }
        }
        echo json_encode(['history' => $result]);
        break;

    case 'weather_history':
        $lim = isset($_GET['limit']) ? intval($_GET['limit']) : 500;
        $url = "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_HISTORICAL}?order=timestamp_hour.desc&limit={$lim}";
        $data = fetchSupabase($url, $supabaseKey);
        $result = [];
        if ($data) {
            foreach ($data as $row) {
                $ts = $row['timestamp_hour'] ?? $row['created_at'] ?? $row['timestamp'] ?? null;
                $row['timestamp'] = parseToIstIso($ts);
                $result[] = $row;
            }
        }
        echo json_encode(['history' => $result]);
        break;
        
    case 'aqi_live_history':
        $lim = isset($_GET['limit']) ? intval($_GET['limit']) : 60;
        $url = "{$supabaseUrl}/rest/v1/{$TABLE_AQI_LIVE}?order=created_at.desc.nullslast&limit={$lim}";
        $data = fetchSupabase($url, $supabaseKey);
        $result = [];
        if ($data) {
            foreach ($data as $row) {
                $f = formatRawReading($row);
                if ($f) $result[] = $f;
            }
        }
        echo json_encode($result);
        break;
        
    case 'weather_live_history':
        $lim = isset($_GET['limit']) ? intval($_GET['limit']) : 60;
        $url = "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?order=created_at.desc.nullslast&limit={$lim}";
        $data = fetchSupabase($url, $supabaseKey);
        echo json_encode($data ? $data : []);
        break;

    case 'overview_bulk':
        $aqi_url = "{$supabaseUrl}/rest/v1/{$TABLE_AQI_LIVE}?order=id.desc&limit=1";
        $weather_url = "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?order=id.desc&limit=1";
        $hist_aqi_url = "{$supabaseUrl}/rest/v1/{$TABLE_AQI_LIVE}?order=created_at.desc.nullslast&limit=60";
        $hist_weather_url = "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?order=created_at.desc.nullslast&limit=60";

        $aqi = fetchSupabase($aqi_url, $supabaseKey);
        $weather = fetchSupabase($weather_url, $supabaseKey);
        $hist_aqi = fetchSupabase($hist_aqi_url, $supabaseKey);
        $hist_weather = fetchSupabase($hist_weather_url, $supabaseKey);

        $res = [];
        $res['aqi'] = ($aqi && count($aqi) > 0) ? formatRawReading($aqi[0]) : null;
        $res['weather'] = ($weather && count($weather) > 0) ? formatWeatherReading($weather[0]) : null;
        
        $hist_formatted = [];
        if ($hist_aqi) {
            foreach ($hist_aqi as $row) {
                $f = formatRawReading($row);
                if ($f) $hist_formatted[] = $f;
            }
        }
        $res['history'] = $hist_formatted;
        
        $hist_weather_fmt = [];
        if ($hist_weather) {
            foreach ($hist_weather as $row) {
                $hw = formatWeatherReading($row);
                if ($hw) $hist_weather_fmt[] = $hw;
            }
        }
        $res['weather_history'] = $hist_weather_fmt;
        echo json_encode($res);
        break;

    case 'history_bulk':
        $date = isset($_GET['date']) ? $_GET['date'] : null;
        $filter = "";
        
        if ($date) {
            // Fetch a 3-day window around the requested date to cover any UTC/IST timezone offsets
            $start_date = date('Y-m-d', strtotime($date . ' -1 day'));
            $end_date = date('Y-m-d', strtotime($date . ' +2 days'));
            $filter = "&timestamp_hour=gte.{$start_date}T00:00:00&timestamp_hour=lt.{$end_date}T00:00:00";
            $lim = 150; // 3 days * 24 hours = 72 records, 150 is plenty
        } else {
            $lim = isset($_GET['limit']) ? intval($_GET['limit']) : 500;
        }
        
        $aqi_url = "{$supabaseUrl}/rest/v1/{$TABLE_AQI_HISTORICAL}?order=timestamp_hour.desc&limit={$lim}{$filter}";
        $weather_url = "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_HISTORICAL}?order=timestamp_hour.desc&limit={$lim}{$filter}";

        $aqi = fetchSupabase($aqi_url, $supabaseKey);
        $weather = fetchSupabase($weather_url, $supabaseKey);

        $res = ['aqi' => [], 'weather' => []];
        if ($aqi) {
            foreach ($aqi as $row) {
                $f = formatRawReading($row);
                if ($f) $res['aqi'][] = $f;
            }
        }
        if ($weather) {
            foreach ($weather as $row) {
                $fw = formatWeatherReading($row);
                if ($fw) $res['weather'][] = $fw;
            }
        }
        echo json_encode($res);
        break;

    case 'live_bulk':
        $lim = isset($_GET['limit']) ? intval($_GET['limit']) : 60;
        
        $aqi_url = "{$supabaseUrl}/rest/v1/{$TABLE_AQI_LIVE}?order=id.desc&limit=1";
        $weather_url = "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?order=id.desc&limit=1";
        $aqi_hist_url = "{$supabaseUrl}/rest/v1/{$TABLE_AQI_LIVE}?order=created_at.desc.nullslast&limit={$lim}";
        $weather_hist_url = "{$supabaseUrl}/rest/v1/{$TABLE_WEATHER_LIVE}?order=created_at.desc.nullslast&limit={$lim}";

        $aqi = fetchSupabase($aqi_url, $supabaseKey);
        $weather = fetchSupabase($weather_url, $supabaseKey);
        $aqi_hist = fetchSupabase($aqi_hist_url, $supabaseKey);
        $weather_hist = fetchSupabase($weather_hist_url, $supabaseKey);

        $res = [];
        $res['aqi'] = ($aqi && count($aqi) > 0) ? formatRawReading($aqi[0]) : null;
        $res['weather'] = ($weather && count($weather) > 0) ? formatWeatherReading($weather[0]) : null;
        
        $ah_fmt = [];
        if ($aqi_hist) {
            foreach ($aqi_hist as $row) {
                $f = formatRawReading($row);
                if ($f) $ah_fmt[] = $f;
            }
        }
        $res['aqi_history'] = $ah_fmt;
        
        $weather_hist_fmt = [];
        if ($weather_hist) {
            foreach ($weather_hist as $row) {
                $wh = formatWeatherReading($row);
                if ($wh) $weather_hist_fmt[] = $wh;
            }
        }
        $res['weather_history'] = $weather_hist_fmt;
        
        echo json_encode($res);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
