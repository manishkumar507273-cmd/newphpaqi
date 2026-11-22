<?php
if (!function_exists('smartairnet_get_active_node_id')) {
    require_once __DIR__ . '/../config/nodes.php';
}
$active_node_id = '2';
$is_node_2 = true;
$raw_tab = $_GET['tab'] ?? ($_GET['subtab'] ?? 'aqi');
$active_live_subtab = in_array($raw_tab, ['aqi', 'weather', 'soil']) ? $raw_tab : 'aqi';

// Load Node 2 configuration if not already available
if (!isset($node_config) || empty($node_config) || !is_array($node_config)) {
    if (function_exists('smartairnet_get_node_config')) {
        $node_config = smartairnet_get_node_config('2');
    }
    if (empty($node_config) && file_exists(__DIR__ . '/config.php')) {
        $node_config = require __DIR__ . '/config.php';
    }
}

// Load Node 2 cached live telemetry if fresh (< 25s)
$CACHE_DIR = sys_get_temp_dir() . '/smartairnet_cache';
$cache_candidates = [
    $CACHE_DIR . '/' . md5('live_bulk_node_2_60') . '.json',
    $CACHE_DIR . '/' . md5('live_bulk_node_2_50') . '.json',
    $CACHE_DIR . '/' . md5('overview_bulk_node_2') . '.json',
    $CACHE_DIR . '/last_known_hero_node_2.json',
    $CACHE_DIR . '/live_bulk_node_2_60.json',
    $CACHE_DIR . '/overview_bulk_node_2.json',
];
$init_bulk = null;
foreach ($cache_candidates as $cf) {
    if (file_exists($cf) && (time() - filemtime($cf)) < 25) {
        $raw_cache = @file_get_contents($cf);
        if ($raw_cache && strlen($raw_cache) > 20) {
            $parsed = @json_decode($raw_cache, true);
            if ($parsed && !empty($parsed['aqi_history'])) {
                $init_bulk = $parsed;
                break;
            }
        }
    }
}

// If no fresh cache exists (< 25s) or cache lacks aqi_history, query Supabase directly for Node 2 so server-render is never stale
if ($init_bulk === null || empty($init_bulk['aqi_history'])) {
    $sb_url = !empty($node_config['supabase']['url']) ? rtrim($node_config['supabase']['url'], '/') : 'https://nahabhzcaeppbteuxogg.supabase.co';
    $sb_key = !empty($node_config['supabase']['secret_key']) ? $node_config['supabase']['secret_key'] : (!empty($node_config['supabase']['anon_key']) ? $node_config['supabase']['anon_key'] : 'sb_publishable_RXM8H-D8m6qWz2Yy2xA6VQ_TjxxY3bJ');
    $tbl_aqi = $node_config['tables']['aqi_live'] ?? 'AQI_LIVE_NODE2';
    $tbl_soil = $node_config['tables']['weather_live'] ?? ($node_config['tables']['soil_live'] ?? 'SOIL_LIVE_NODE2');
    
    $aqi_rows = [];
    $ch = @curl_init("{$sb_url}/rest/v1/{$tbl_aqi}?order=id.desc&limit=60");
    if ($ch) {
        @curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "apikey: {$sb_key}",
                "Authorization: Bearer {$sb_key}"
            ],
            CURLOPT_TIMEOUT_MS     => 2500,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $raw_resp = @curl_exec($ch);
        $http_code = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);
        if ($http_code === 200 && $raw_resp) {
            $aqi_rows = @json_decode($raw_resp, true) ?: [];
        }
    }

    $soil_rows = [];
    $ch_s = @curl_init("{$sb_url}/rest/v1/{$tbl_soil}?select=*&order=id.desc&limit=60");
    if ($ch_s) {
        @curl_setopt_array($ch_s, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "apikey: {$sb_key}",
                "Authorization: Bearer {$sb_key}"
            ],
            CURLOPT_TIMEOUT_MS     => 2000,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $raw_soil = @curl_exec($ch_s);
        $http_s_code = @curl_getinfo($ch_s, CURLINFO_HTTP_CODE);
        @curl_close($ch_s);
        if ($http_s_code === 200 && $raw_soil) {
            $soil_rows = @json_decode($raw_soil, true) ?: [];
        }
    }

    $formatted_aqi = [];
    foreach ($aqi_rows as $row) {
        $rawPm25 = (isset($row['pm25']) && $row['pm25'] !== '') ? (float)$row['pm25'] : ((isset($row['pm2.5']) && $row['pm2.5'] !== '') ? (float)$row['pm2.5'] : ((isset($row['pm2_5']) && $row['pm2_5'] !== '') ? (float)$row['pm2_5'] : null));
        $rawPm10 = (isset($row['pm10']) && $row['pm10'] !== '') ? (float)$row['pm10'] : null;
        $rawCo   = (isset($row['co']) && $row['co'] !== '') ? (float)$row['co'] : null;
        $rawNo2  = (isset($row['no2']) && $row['no2'] !== '') ? (float)$row['no2'] : null;
        $rawO3   = (isset($row['o3']) && $row['o3'] !== '') ? (float)$row['o3'] : null;
        
        $cpcb_val = isset($row['cpcb_aqi']) ? (int)$row['cpcb_aqi'] : null;
        if ($cpcb_val === null && $rawPm25 !== null) {
            if ($rawPm25 <= 30) $cpcb_val = round($rawPm25 * 50 / 30);
            elseif ($rawPm25 <= 60) $cpcb_val = round(50 + ($rawPm25 - 30) * 50 / 30);
            elseif ($rawPm25 <= 90) $cpcb_val = round(100 + ($rawPm25 - 60) * 100 / 30);
            elseif ($rawPm25 <= 120) $cpcb_val = round(200 + ($rawPm25 - 90) * 100 / 30);
            elseif ($rawPm25 <= 250) $cpcb_val = round(300 + ($rawPm25 - 120) * 100 / 130);
            else $cpcb_val = round(400 + ($rawPm25 - 250) * 100 / 130);
        }

        $formatted_aqi[] = [
            'id' => $row['id'] ?? null,
            'timestamp' => $row['timestamp'] ?? ($row['created_at'] ?? date('c')),
            'created_at' => $row['created_at'] ?? ($row['timestamp'] ?? date('c')),
            'cpcb_aqi' => $cpcb_val ?? '--',
            'pm25' => $rawPm25,
            'pm2.5' => $rawPm25,
            'pm2_5' => $rawPm25,
            'pm10' => $rawPm10,
            'co' => $rawCo,
            'no2' => $rawNo2,
            'o3' => $rawO3,
        ];
    }

    $formatted_soil = [];
    foreach ($soil_rows as $srow) {
        $sHum = isset($srow['soil_humidity']) ? (float)$srow['soil_humidity'] : (isset($srow['soil_moisture']) ? (float)$srow['soil_moisture'] : null);
        $sTemp = isset($srow['soil_temperature']) ? (float)$srow['soil_temperature'] : null;
        $formatted_soil[] = [
            'id' => $srow['id'] ?? null,
            'timestamp' => $srow['timestamp'] ?? ($srow['created_at'] ?? date('c')),
            'created_at' => $srow['created_at'] ?? ($srow['timestamp'] ?? date('c')),
            'temperature' => $sTemp,
            'humidity' => $sHum,
            'soil_temperature' => $sTemp,
            'soil_humidity' => $sHum,
            'soil_moisture' => $sHum,
            'soil_ph' => isset($srow['soil_ph']) ? (float)$srow['soil_ph'] : null,
            'soil_conductivity' => isset($srow['soil_conductivity']) ? (float)$srow['soil_conductivity'] : null,
            'latitude' => $srow['latitude'] ?? null,
            'longitude' => $srow['longitude'] ?? null,
        ];
    }

    if (!empty($formatted_aqi) || !empty($formatted_soil)) {
        $init_bulk = [
            'node' => '2',
            'status' => 'ONLINE',
            'aqi' => !empty($formatted_aqi) ? $formatted_aqi[0] : null,
            'weather' => !empty($formatted_soil) ? $formatted_soil[0] : null,
            'aqi_history' => $formatted_aqi,
            'weather_history' => $formatted_soil,
            'history' => $formatted_aqi,
        ];
        if (!is_dir($CACHE_DIR)) @mkdir($CACHE_DIR, 0755, true);
        @file_put_contents($CACHE_DIR . '/' . md5('live_bulk_node_2_60') . '.json', json_encode($init_bulk));
        @file_put_contents($CACHE_DIR . '/' . md5('live_bulk_node_2_50') . '.json', json_encode($init_bulk));
        @file_put_contents($CACHE_DIR . '/live_bulk_node_2_60.json', json_encode($init_bulk));
    }
}

// AQI pre-calculated fields for Node 2
$init_aqi_val = '--';
$init_aqi_label = 'Standby';
$init_aqi_accent = '#94a3b8';
$init_aqi_bg = 'linear-gradient(155deg, #ffffff 0%, #f8fafc 50%, #f1f5f9 100%)';
$init_aqi_border = 'rgba(148, 163, 184, 0.35)';
$init_aqi_shadow = 'rgba(148, 163, 184, 0.15)';
$init_aqi_badge_bg = 'rgba(255, 255, 255, 0.85)';
$init_aqi_badge_border = 'rgba(148, 163, 184, 0.38)';
$init_aqi_badge_text = '#64748b';
$init_scale_pct = '0%';
$init_pm25 = '--';
$init_pm10 = '--';

if (!empty($init_bulk['aqi'])) {
    $aqi_d = $init_bulk['aqi'];
    $init_aqi_val = $aqi_d['cpcb_aqi'] ?? ($aqi_d['aqi_info']['value'] ?? '--');
    if (is_numeric($init_aqi_val)) {
        $v = (int)$init_aqi_val;
        if ($v <= 50) {
            $init_aqi_label = 'Good';
            $init_aqi_accent = '#14532d';
            $init_aqi_bg = 'linear-gradient(155deg, #86efac 0%, #4ade80 25%, #22c55e 55%, #16a34a 80%, #15803d 95%, #166534 100%)';
            $init_aqi_border = 'rgba(21, 128, 61, 0.45)';
            $init_aqi_shadow = 'rgba(22, 163, 74, 0.28)';
            $init_aqi_badge_bg = 'rgba(255, 255, 255, 0.88)';
            $init_aqi_badge_border = 'rgba(21, 128, 61, 0.45)';
            $init_aqi_badge_text = '#14532d';
            $init_scale_pct = max(2, min(98, ($v / 50) * 16.666)) . '%';
        } elseif ($v <= 100) {
            $init_aqi_label = 'Moderate';
            $init_aqi_accent = '#78350f';
            $init_aqi_bg = 'linear-gradient(155deg, #fde047 0%, #facc15 22%, #eab308 50%, #ca8a04 75%, #b45309 90%, #92400e 100%)';
            $init_aqi_border = 'rgba(180, 83, 9, 0.45)';
            $init_aqi_shadow = 'rgba(202, 138, 4, 0.28)';
            $init_aqi_badge_bg = 'rgba(255, 255, 255, 0.88)';
            $init_aqi_badge_border = 'rgba(180, 83, 9, 0.45)';
            $init_aqi_badge_text = '#78350f';
            $init_scale_pct = (16.666 + (($v - 50) / 50) * 16.667) . '%';
        } elseif ($v <= 200) {
            $init_aqi_label = 'Poor';
            $init_aqi_accent = '#7c2d12';
            $init_aqi_bg = 'linear-gradient(155deg, #fdba74 0%, #fb923c 25%, #f97316 52%, #ea580c 78%, #c2410c 92%, #9a3412 100%)';
            $init_aqi_border = 'rgba(194, 65, 12, 0.45)';
            $init_aqi_shadow = 'rgba(234, 88, 12, 0.28)';
            $init_aqi_badge_bg = 'rgba(255, 255, 255, 0.88)';
            $init_aqi_badge_border = 'rgba(194, 65, 12, 0.45)';
            $init_aqi_badge_text = '#7c2d12';
            $init_scale_pct = (33.333 + (($v - 100) / 100) * 16.667) . '%';
        } else {
            $init_aqi_label = 'Unhealthy';
            $init_aqi_accent = '#881337';
            $init_aqi_bg = 'linear-gradient(155deg, #fda4af 0%, #fb7185 25%, #f43f5e 52%, #e11d48 78%, #be123c 92%, #881337 100%)';
            $init_aqi_border = 'rgba(190, 18, 60, 0.45)';
            $init_aqi_shadow = 'rgba(225, 29, 72, 0.28)';
            $init_aqi_badge_bg = 'rgba(255, 255, 255, 0.88)';
            $init_aqi_badge_border = 'rgba(190, 18, 60, 0.45)';
            $init_aqi_badge_text = '#881337';
            $init_scale_pct = '50%';
        }
    }
    $pm25_val = $aqi_d['pm25'] ?? ($aqi_d['pm2.5'] ?? ($aqi_d['pm2_5'] ?? null));
    $init_pm25 = ($pm25_val !== null && is_numeric($pm25_val)) ? number_format((float)$pm25_val, 1) : '--';
    $init_pm10 = (isset($aqi_d['pm10']) && is_numeric($aqi_d['pm10'])) ? number_format((float)$aqi_d['pm10'], 1) : '--';
}

// Weather pre-calculated fields
$w_d = !empty($init_bulk['weather']) ? $init_bulk['weather'] : [];
$init_weather_temp = (isset($w_d['temperature']) && is_numeric($w_d['temperature'])) ? number_format((float)$w_d['temperature'], 1) : '--';
$init_weather_hum = (isset($w_d['humidity']) && is_numeric($w_d['humidity'])) ? number_format((float)$w_d['humidity'], 1) . '%' : '--%';
$init_weather_rain = isset($w_d['rain_gauge']) && is_numeric($w_d['rain_gauge']) ? number_format((float)$w_d['rain_gauge'], 1) . ' mm' : '--';
$init_weather_wind = isset($w_d['wind_speed']) && is_numeric($w_d['wind_speed']) ? number_format((float)$w_d['wind_speed'], 1) . ' km/h' : '--';
$init_weather_gust = isset($w_d['wind_gust']) && is_numeric($w_d['wind_gust']) ? number_format((float)$w_d['wind_gust'], 1) . ' m/s' : '--';

// Telemetry-driven Latitude & Longitude coordinate resolution
$init_lat = null;
$init_lon = null;
$init_dms = null;
if (!empty($w_d['latitude']) && !empty($w_d['longitude']) && is_numeric($w_d['latitude']) && is_numeric($w_d['longitude'])) {
    $init_lat = number_format((float)$w_d['latitude'], 4);
    $init_lon = number_format((float)$w_d['longitude'], 4);
    $init_dms = $w_d['coords_display'] ?? null;
} elseif (!empty($init_bulk['latitude']) && !empty($init_bulk['longitude']) && is_numeric($init_bulk['latitude']) && is_numeric($init_bulk['longitude'])) {
    $init_lat = number_format((float)$init_bulk['latitude'], 4);
    $init_lon = number_format((float)$init_bulk['longitude'], 4);
    $init_dms = $init_bulk['coords_display'] ?? null;
}
if ($init_lat === null && !empty($node_config['location']['latitude']) && !empty($node_config['location']['longitude'])) {
    $init_lat = number_format((float)$node_config['location']['latitude'], 4);
    $init_lon = number_format((float)$node_config['location']['longitude'], 4);
    $init_dms = $node_config['location']['coords_display'] ?? null;
}
$has_coords = ($init_lat !== null && $init_lon !== null);

if (!function_exists('smartairnet_parse_wind_direction')) {
    function smartairnet_parse_wind_direction($raw) {
        if ($raw === null || $raw === '' || $raw === '--') {
            return null;
        }
        $cardinals = [
            'n' => 0, 'north' => 0,
            'nne' => 22.5, 'north-northeast' => 22.5, 'north northeast' => 22.5,
            'ne' => 45, 'northeast' => 45,
            'ene' => 67.5, 'east-northeast' => 67.5, 'east northeast' => 67.5,
            'e' => 90, 'east' => 90,
            'ese' => 112.5, 'east-southeast' => 112.5, 'east southeast' => 112.5,
            'se' => 135, 'southeast' => 135,
            'sse' => 157.5, 'south-southeast' => 157.5, 'south southeast' => 157.5,
            's' => 180, 'south' => 180,
            'ssw' => 202.5, 'south-southwest' => 202.5, 'south southwest' => 202.5,
            'sw' => 225, 'southwest' => 225,
            'wsw' => 247.5, 'west-southwest' => 247.5, 'west southwest' => 247.5,
            'w' => 270, 'west' => 270,
            'wnw' => 292.5, 'west-northwest' => 292.5, 'west northwest' => 292.5,
            'nw' => 315, 'northwest' => 315,
            'nnw' => 337.5, 'north-northwest' => 337.5, 'north northwest' => 337.5
        ];
        $dirs = ['n','nne','ne','ene','e','ese','se','sse','s','ssw','sw','wsw','w','wnw','nw','nnw'];
        $str = trim(strtolower((string)$raw));
        if (preg_match('/(-?\d+(?:\.\d+)?)/', $str, $m)) {
            $deg = (float)$m[1];
            $deg = fmod($deg, 360);
            if ($deg < 0) $deg += 360;
            $deg_round = (int)round($deg);
            $card = $dirs[(int)round($deg / 22.5) % 16];
            return [
                'deg' => $deg_round,
                'cardinal' => $card,
                'display' => "{$deg_round}° {$card}"
            ];
        }
        $clean = preg_replace('/[^a-z\- ]/', '', $str);
        if (isset($cardinals[$clean])) {
            $deg = $cardinals[$clean];
            $deg_round = (int)round($deg);
            $card = $dirs[(int)round($deg / 22.5) % 16];
            return [
                'deg' => $deg_round,
                'cardinal' => $card,
                'display' => "{$deg_round}° {$card}"
            ];
        }
        return null;
    }
}

$parsed_wind = !empty($w_d['wind_direction']) ? smartairnet_parse_wind_direction($w_d['wind_direction']) : null;
if ($parsed_wind) {
    $compass_angle = $parsed_wind['deg'];
    $init_weather_cardinal = $parsed_wind['cardinal'];
    $init_weather_dir_deg = $parsed_wind['display'];
    $init_weather_dir = $parsed_wind['display'];
    $has_wind_dir = true;
} else {
    $compass_angle = 0;
    $init_weather_cardinal = '--';
    $init_weather_dir_deg = '--';
    $init_weather_dir = '--';
    $has_wind_dir = false;
}

// Pressure & UV Index for Node 2 Meteorological Dashboard
$has_pressure = isset($w_d['pressure']) && is_numeric($w_d['pressure']);
$init_weather_pressure = $has_pressure ? round((float)$w_d['pressure']) . ' mb' : '--';
$pressure_num = $has_pressure ? (float)$w_d['pressure'] : null;
$pressure_pct = $has_pressure ? max(5, min(95, round((($pressure_num - 995) / (1025 - 995)) * 100))) : 50;
$pressure_angle = $has_pressure ? round(($pressure_num - 1011) * 30) : 60;

$has_uv = isset($w_d['uv_index']) && is_numeric($w_d['uv_index']);
if ($has_uv) {
    $init_weather_uv_num = number_format((float)$w_d['uv_index'], 1);
    $uv_f = (float)$init_weather_uv_num;
    if ($uv_f < 3) $uv_level = 'Low';
    elseif ($uv_f < 6) $uv_level = 'Moderate';
    elseif ($uv_f < 8) $uv_level = 'High';
    elseif ($uv_f < 11) $uv_level = 'Very High';
    else $uv_level = 'Extreme';
    $init_weather_uv_display = "{$init_weather_uv_num} {$uv_level}";
    $uv_pct = max(5, min(95, round(($uv_f / 11) * 100)));
} else {
    $init_weather_uv_num = '--';
    $init_weather_uv_display = '--';
    $uv_pct = 50;
}

// Soil Parameters for Node 2 Agrimet & Environmental Telemetry
$has_soil_temp = isset($w_d['soil_temperature']) && is_numeric($w_d['soil_temperature']);
$init_soil_temp = $has_soil_temp ? number_format((float)$w_d['soil_temperature'], 1) . ' °C' : '--';
$soil_temp_num = $has_soil_temp ? (float)$w_d['soil_temperature'] : null;
$soil_temp_pct = $has_soil_temp ? max(5, min(95, round((($soil_temp_num - 10) / (40 - 10)) * 100))) : 50;

$has_soil_hum = (isset($w_d['soil_moisture']) && is_numeric($w_d['soil_moisture'])) || (isset($w_d['soil_humidity']) && is_numeric($w_d['soil_humidity']));
$soil_hum_num = (isset($w_d['soil_moisture']) && is_numeric($w_d['soil_moisture'])) ? (float)$w_d['soil_moisture'] : ((isset($w_d['soil_humidity']) && is_numeric($w_d['soil_humidity'])) ? (float)$w_d['soil_humidity'] : null);
$init_soil_hum = ($soil_hum_num !== null) ? number_format((float)$soil_hum_num, 1) . '%' : '--';
$soil_hum_pct = ($soil_hum_num !== null) ? max(5, min(95, round($soil_hum_num))) : 50;

$has_soil_ph = isset($w_d['soil_ph']) && is_numeric($w_d['soil_ph']);
$init_soil_ph = $has_soil_ph ? number_format((float)$w_d['soil_ph'], 2) : '--';
$soil_ph_num = $has_soil_ph ? (float)$w_d['soil_ph'] : null;
$soil_ph_pct = $has_soil_ph ? max(5, min(95, round((($soil_ph_num - 3.0) / (10.0 - 3.0)) * 100))) : 50;
$soil_ph_status = $has_soil_ph ? (($soil_ph_num < 6.0) ? 'Slightly Acidic' : (($soil_ph_num > 7.5) ? 'Slightly Alkaline' : 'Optimal Neutral')) : '--';

$has_soil_ec = isset($w_d['soil_conductivity']) && is_numeric($w_d['soil_conductivity']);
$soil_ec_num = $has_soil_ec ? (float)$w_d['soil_conductivity'] : null;
$init_soil_ec = $has_soil_ec ? round((float)$w_d['soil_conductivity']) . ' µS/cm' : '--';
$soil_ec_pct = ($soil_ec_num !== null) ? max(5, min(95, round(($soil_ec_num / 200.0) * 100))) : 50;
$soil_ec_status = $has_soil_ec ? (($soil_ec_num < 40) ? 'Low Salinity (Good)' : (($soil_ec_num > 150) ? 'Elevated Salinity' : 'Balanced Fertility')) : '--';

$ambient_temp_num = is_numeric($init_weather_temp) ? (float)$init_weather_temp : null;
$ambient_temp_pct = ($ambient_temp_num !== null) ? max(5, min(95, round((($ambient_temp_num - 15) / (45 - 15)) * 100))) : 50;
$ambient_hum_num = is_numeric(str_replace('%', '', $init_weather_hum)) ? (float)str_replace('%', '', $init_weather_hum) : null;
$ambient_hum_pct = ($ambient_hum_num !== null) ? max(5, min(95, round($ambient_hum_num))) : 50;

$init_weather_today_rain = isset($init_bulk['today_rain_total']) ? number_format((float)$init_bulk['today_rain_total'], 1) . ' mm' : (isset($w_d['rain_gauge']) && is_numeric($w_d['rain_gauge']) ? number_format((float)$w_d['rain_gauge'], 1) . ' mm' : '--');

// Determine initial dynamic weather backdrop theme (Node 2)
// User rule: Cloud animation if temperature is low and humidity is high, else sunny bright animation
$t_val = (isset($w_d['temperature']) && is_numeric($w_d['temperature'])) ? (float)$w_d['temperature'] : null;
$h_val = (isset($w_d['humidity']) && is_numeric($w_d['humidity'])) ? (float)$w_d['humidity'] : null;
$r_val = (isset($w_d['rain_gauge']) && is_numeric($w_d['rain_gauge'])) ? (float)$w_d['rain_gauge'] : 0.0;

$is_cloudy_initial = true; // Natural atmospheric drifting sunset cloud sky (no sun overlay)

// Weather Min / Max calculation from history
$init_weather_min = '--';
$init_weather_max = '--';
if (!empty($init_bulk['weather_history']) && is_array($init_bulk['weather_history'])) {
    $w_temps = [];
    foreach ($init_bulk['weather_history'] as $wh) {
        if (isset($wh['temperature']) && is_numeric($wh['temperature'])) {
            $w_temps[] = (float)$wh['temperature'];
        }
    }
    if (!empty($w_temps)) {
        $init_weather_min = number_format(min($w_temps), 1);
        $init_weather_max = number_format(max($w_temps), 1);
    }
}
if ($init_weather_min === '--' && is_numeric($init_weather_temp)) {
    $init_weather_min = $init_weather_temp;
    $init_weather_max = $init_weather_temp;
}

// Thermal Comfort Badge text
$init_weather_comfort = 'Pleasant';
if (is_numeric($init_weather_temp)) {
    $t = (float)$init_weather_temp;
    if ($t < 18) $init_weather_comfort = 'Cool';
    elseif ($t <= 25) $init_weather_comfort = 'Pleasant';
    elseif ($t <= 30) $init_weather_comfort = 'Warm';
    elseif ($t <= 36) $init_weather_comfort = 'Hot';
    else $init_weather_comfort = 'Very Hot';
}

$init_is_aqi_offline = false;
$init_aqi_ts = $init_bulk['aqi']['timestamp'] ?? ($init_bulk['aqi']['created_at'] ?? null);
$init_weather_ts = $init_bulk['weather']['timestamp'] ?? ($init_bulk['weather']['created_at'] ?? null);
$init_aqi_epoch = $init_aqi_ts ? strtotime($init_aqi_ts) : 0;
$init_weather_epoch = $init_weather_ts ? strtotime($init_weather_ts) : 0;
$init_latest_epoch = $init_aqi_epoch > 0 ? $init_aqi_epoch : $init_weather_epoch;
if ($init_latest_epoch > 0 && (time() - $init_latest_epoch) > 300) {
    $init_is_aqi_offline = true;
}
$init_time_str = $init_latest_epoch > 0 ? date('h:i A', $init_latest_epoch) : 'Live Stream';

$aqi_d_sub = !empty($init_bulk['aqi']) ? $init_bulk['aqi'] : [];
$init_pollutants = [
    'pm25' => isset($aqi_d_sub['pm25']) ? number_format((float)$aqi_d_sub['pm25'], 3) : (isset($aqi_d_sub['pm2.5']) ? number_format((float)$aqi_d_sub['pm2.5'], 3) : '--'),
    'pm10' => isset($aqi_d_sub['pm10']) ? number_format((float)$aqi_d_sub['pm10'], 3) : '--',
    'co'   => isset($aqi_d_sub['co']) && $aqi_d_sub['co'] !== null ? number_format((float)$aqi_d_sub['co'], 3) : '--',
    'no2'  => isset($aqi_d_sub['no2']) && $aqi_d_sub['no2'] !== null ? number_format((float)$aqi_d_sub['no2'], 3) : '--',
    'o3'   => isset($aqi_d_sub['o3']) && $aqi_d_sub['o3'] !== null ? number_format((float)$aqi_d_sub['o3'], 3) : '--',
];

$init_tags = [];
$pollutant_limits = ['pm25' => 60, 'pm10' => 100, 'co' => 2, 'no2' => 80, 'o3' => 100];
foreach (['pm25', 'pm10', 'co', 'no2', 'o3'] as $p) {
    $rawVal = $aqi_d_sub[$p] ?? ($p === 'pm25' ? ($aqi_d_sub['pm2.5'] ?? null) : null);
    if ($rawVal !== null && is_numeric($rawVal)) {
        $isSafe = (float)$rawVal <= $pollutant_limits[$p];
        $init_tags[$p] = [
            'show' => true,
            'isSafe' => $isSafe,
            'text' => $isSafe ? 'Normal' : 'High',
            'bg' => $isSafe ? '#dcfce7' : '#fee2e2',
            'border' => $isSafe ? '#bbf7d0' : '#fecaca',
            'color' => $isSafe ? '#15803d' : '#b91c1c',
            'dot' => $isSafe ? '#16a34a' : '#dc2626'
        ];
    } else {
        $init_tags[$p] = ['show' => false, 'text' => 'Load', 'bg' => '#f1f5f9', 'border' => '#e2e8f0', 'color' => '#64748b', 'dot' => '#94a3b8'];
    }
}

if (!function_exists('get_param_border_color')) {
    function get_param_border_color($param, $val) {
        if ($val === null || !is_numeric($val)) {
            if ($param === 'wind_dir') return '#0d9488';
            return '#cbd5e1';
        }
        $v = (float)$val;
        // Standard CPCB NAQI 6-tier colors matching the concentration scale bar exactly:
        // Tier 0: Good      -> #59b61f (Green)
        // Tier 1: Moderate  -> #eec732 (Yellow)
        // Tier 2: Poor      -> #ea8c34 (Orange)
        // Tier 3: Unhealthy -> #e95478 (Red)
        // Tier 4: Severe    -> #b33fba (Purple)
        // Tier 5: Hazardous -> #c92033 (Maroon)
        $colors = ['#59b61f', '#eec732', '#ea8c34', '#e95478', '#b33fba', '#c92033'];
        
        $scales = [
            'pm25' => [30, 60, 90, 120, 250],
            'pm10' => [50, 100, 250, 350, 430],
            'co'   => [1.0, 2.0, 10.0, 17.0, 34.0],
            'no2'  => [40, 80, 180, 280, 400],
            'o3'   => [50, 100, 168, 208, 748],
        ];

        if (isset($scales[$param])) {
            $s = $scales[$param];
            if ($v <= $s[0]) return $colors[0]; // Good (Green)
            if ($v <= $s[1]) return $colors[1]; // Moderate (Yellow)
            if ($v <= $s[2]) return $colors[2]; // Poor (Orange)
            if ($v <= $s[3]) return $colors[3]; // Unhealthy (Red)
            if ($v <= $s[4]) return $colors[4]; // Severe (Purple)
            return $colors[5];                  // Hazardous (Maroon)
        }

        switch ($param) {
            case 'wind_dir':
                return '#0d9488';
            case 'gust':
                if ($v <= 20) return $colors[0];
                if ($v <= 40) return $colors[1];
                if ($v <= 60) return $colors[2];
                return $colors[5];
            case 'rain':
                if ($v <= 0.1) return '#0284c7';
                if ($v <= 10) return '#0891b2';
                if ($v <= 35) return '#2563eb';
                return '#7c3aed';
            default:
                return $colors[0];
        }
    }
}

$init_border_colors = [
    'pm25' => get_param_border_color('pm25', $aqi_d_sub['pm25'] ?? ($aqi_d_sub['pm2.5'] ?? null)),
    'pm10' => get_param_border_color('pm10', $aqi_d_sub['pm10'] ?? null),
    'co'   => get_param_border_color('co', $aqi_d_sub['co'] ?? null),
    'no2'  => get_param_border_color('no2', $aqi_d_sub['no2'] ?? null),
    'o3'   => get_param_border_color('o3', $aqi_d_sub['o3'] ?? null),
];
?>
<style>
.pollutant-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-top-color 0.2s ease, border-right-color 0.2s ease, border-bottom-color 0.2s ease !important;
}
.pollutant-card:hover {
  border-top-color: #00bfa5 !important;
  border-right-color: #00bfa5 !important;
  border-bottom-color: #00bfa5 !important;
}
.pollutant-card.cursor-default:hover {
  border-color: #e2e8f0 !important;
  transform: none !important;
}
.pollutant-chevron {
  display: none !important;
}
@media (min-width: 768px) {
  .hero-flex-row {
    display: flex !important;
    flex-direction: row !important;
    align-items: stretch !important;
    justify-content: space-between !important;
    width: 100% !important;
  }
  .hero-weather-col {
    margin-left: auto !important;
    margin-right: 0 !important;
    max-width: 320px !important;
    width: 100% !important;
    flex-shrink: 0 !important;
  }
}
@media (max-width: 767px) {
  .hero-flex-row {
    display: flex !important;
    flex-direction: column !important;
    width: 100% !important;
    gap: 8px !important;
  }
  .hero-aqi-col {
    max-width: 100% !important;
    width: 100% !important;
  }
  .hero-weather-col {
    margin-left: 0 !important;
    margin-right: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
  }
}

#hero-card {
  max-width: 560px !important;
  width: 100% !important;
  margin: 0 auto 16px auto !important;
}

@media (max-width: 768px) {
  /* Mobile compact layout for Unified Live Telemetry Hero Card */
  #hero-card {
    max-width: 100% !important;
    width: 100% !important;
    padding: 10px 12px 9px 12px !important;
    border-radius: 18px !important;
    margin-bottom: 8px !important;
    box-shadow: 0 6px 20px rgba(234, 179, 8, 0.16), 0 1px 3px rgba(15,23,42,0.04) !important;
  }
  .hero-skyline-silhouette {
    height: 40px !important;
    opacity: 0.08 !important;
  }

  /* Left: AQI Top Header Row */
  .hero-header-row {
    margin-bottom: 5px !important;
    gap: 8px !important;
    align-items: flex-end !important;
  }
  #hero-aqi-val {
    font-size: 44px !important;
    line-height: 0.9 !important;
  }
  #hero-aqi-subtext {
    font-size: 10.5px !important;
    margin-bottom: 2px !important;
  }
  #hero-aqi-badge {
    min-width: 76px !important;
    padding: 3px 10px !important;
    border-radius: 8px !important;
    background: rgba(255, 255, 255, 0.85) !important;
  }
  #hero-aqi-label {
    font-size: 13.5px !important;
    font-weight: 800 !important;
  }
  #hero-aqi-title {
    font-size: 10.5px !important;
    margin-bottom: 2px !important;
  }

  /* Middle: Frosted Glass PM Quick Stats Row */
  .hero-pm-row {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    margin-bottom: 6px !important;
    padding: 4px 10px !important;
    background: rgba(255, 255, 255, 0.45) !important;
    border-radius: 8px !important;
    border: 1px solid rgba(255, 255, 255, 0.6) !important;
    font-size: 11px !important;
  }
  .hero-pm-row strong,
  #hero-pm25, #hero-pm10 {
    font-size: 13px !important;
    font-weight: 800 !important;
  }
  .hero-pm-row span.hero-pm-unit {
    font-size: 9.5px !important;
    color: #475569 !important;
    font-weight: 600 !important;
  }
  .hero-pm-divider {
    display: block !important;
    width: 1px !important;
    height: 11px !important;
    background: rgba(203, 213, 225, 0.8) !important;
  }

  /* Scale Bar Layout & Overlap Prevention */
  .hero-scale-bar-container {
    margin-bottom: 6px !important;
  }
  .hero-scale-labels {
    display: grid !important;
    grid-template-columns: repeat(6, 1fr) !important;
    margin-bottom: 2px !important;
    padding: 0 !important;
    gap: 1px !important;
  }
  .hero-scale-labels .scale-label {
    font-size: 7.5px !important;
    font-weight: 700 !important;
    letter-spacing: -0.02em !important;
    line-height: 1 !important;
    text-align: center !important;
    white-space: nowrap !important;
  }
  .hero-scale-bar-track {
    height: 5px !important;
    margin-bottom: 2px !important;
  }
  #hero-scale-indicator {
    width: 13px !important;
    height: 13px !important;
    border-width: 3px !important;
  }
  .hero-scale-ticks {
    height: 11px !important;
  }
  .hero-scale-ticks span {
    font-size: 8px !important;
    font-weight: 700 !important;
    color: #475569 !important;
    line-height: 1 !important;
  }

  /* Weather & Comfort Section Mobile Refinement (Ultra-Compact) */
  .live-page-container #weather-hero-box {
    background: rgba(255, 255, 255, 0.88) !important;
    backdrop-filter: blur(16px) !important;
    -webkit-backdrop-filter: blur(16px) !important;
    border: 1.5px solid rgba(255, 255, 255, 0.95) !important;
    border-radius: 12px !important;
    padding: 6px 8px 6px 8px !important;
    margin-bottom: 0 !important;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04) !important;
  }
  .weather-box-header {
    margin-bottom: 2px !important;
  }
  .weather-box-header span {
    font-size: 9.5px !important;
  }
  .weather-box-header i,
  .weather-box-header svg {
    width: 12px !important;
    height: 12px !important;
  }
  .weather-box-main {
    justify-content: flex-start !important;
    margin-bottom: 4px !important;
    gap: 6px !important;
  }
  .weather-temp-row {
    margin-bottom: 0 !important;
    gap: 2px !important;
  }
  #weather-hero-temp {
    font-size: 22px !important;
    line-height: 1 !important;
  }
  .weather-temp-unit {
    font-size: 12px !important;
  }
  .weather-arrows-wrap {
    font-size: 10px !important;
    gap: 5px !important;
  }
  #weather-hero-temp-max, #weather-hero-temp-min {
    font-size: 10px !important;
  }
  .weather-metrics-grid {
    display: flex !important;
    gap: 4px !important;
    padding-top: 4px !important;
    border-top: 1px solid rgba(226, 232, 240, 0.75) !important;
    width: 100% !important;
  }
  .weather-micro-card {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 8px !important;
    padding: 3px 10px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: flex-start !important;
    width: fit-content !important;
    gap: 8px !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03) !important;
  }
  .weather-micro-label {
    font-size: 9px !important;
    font-weight: 700 !important;
    color: #64748b !important;
    display: flex !important;
    align-items: center !important;
    gap: 3px !important;
    margin-bottom: 0 !important;
  }
  .weather-micro-label svg {
    width: 10px !important;
    height: 10px !important;
  }
  #weather-box-humidity {
    font-size: 12px !important;
    font-weight: 800 !important;
    line-height: 1.1 !important;
  }
  
  /* Pollutants Grid Mobile Refinements (Ultra-compact 2-column layout matching reference) */
  .pollutants-grid-responsive {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 8px !important;
  }
  .pollutant-card {
    display: grid !important;
    grid-template-columns: 28px 1fr auto !important;
    grid-template-rows: auto auto !important;
    grid-template-areas:
      "icon title   chevron"
      "icon valwrap chevron" !important;
    align-items: center !important;
    column-gap: 8px !important;
    row-gap: 2px !important;
    padding: 7px 10px 7px 10px !important;
    min-height: 48px !important;
    border-radius: 14px !important;
    box-shadow: 0 1px 3px rgba(15,23,42,0.04) !important;
  }
  .pollutant-card-top,
  .pollutant-card-bottom,
  .pollutant-card-top > .flex {
    display: contents !important;
  }
  .pollutant-card .pollutant-icon-box {
    grid-area: icon !important;
    width: 28px !important;
    height: 28px !important;
    font-size: 13px !important;
    border-radius: 8px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin: 0 !important;
  }
  .pollutant-card .pollutant-title-box {
    grid-area: title !important;
    min-width: 0 !important;
  }
  .pollutant-card .pollutant-name {
    font-size: 12px !important;
    font-weight: 700 !important;
    line-height: 1.15 !important;
    color: #1e293b !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
  }
  .pollutant-card .pollutant-who {
    display: none !important;
  }
  .pollutant-card .pollutant-val-wrap {
    grid-area: valwrap !important;
    display: flex !important;
    flex-direction: row !important;
    align-items: baseline !important;
    gap: 3.5px !important;
    line-height: 1 !important;
    min-width: 0 !important;
  }
  .pollutant-card [id^="card-"] {
    font-size: 13.5px !important;
    font-weight: 800 !important;
    line-height: 1 !important;
  }
  .pollutant-card .pollutant-unit {
    font-size: 9.5px !important;
    font-weight: 600 !important;
    color: #64748b !important;
    margin: 0 !important;
    line-height: 1 !important;
    font-family: ui-monospace, monospace !important;
  }
  #card-box-dir .pollutant-unit {
    display: none !important;
  }
  .pollutant-chevron {
    grid-area: chevron !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: #94a3b8 !important;
    padding-left: 2px !important;
  }
  .pollutant-chevron i,
  .pollutant-chevron svg {
    width: 14px !important;
    height: 14px !important;
  }
  .pollutant-card .pollutant-info-icon,
  .pollutant-card [id^="tag-"] {
    display: none !important;
  }

  /* Pollutant Detail Modal Mobile Optimization */
  #pollutant-modal {
    padding: 10px !important;
  }
  .pollutant-modal-card {
    border-radius: 18px !important;
    max-height: 92vh !important;
    width: 100% !important;
  }
  .pollutant-modal-scroll {
    padding: 16px 14px !important;
  }
  .pollutant-modal-close {
    top: 14px !important;
    right: 14px !important;
    width: 36px !important;
    height: 36px !important;
    min-width: 36px !important;
    min-height: 36px !important;
    z-index: 50 !important;
    cursor: pointer !important;
  }
  #pm-modal-icon-bg {
    width: 44px !important;
    height: 44px !important;
    font-size: 20px !important;
  }
  #pm-modal-limit {
    font-size: 12px !important;
  }
  .pm-reading-box {
    padding: 12px 14px !important;
    margin-bottom: 14px !important;
    border-radius: 12px !important;
    gap: 10px !important;
  }
  #pm-modal-val {
    font-size: 28px !important;
  }
  #pm-modal-unit {
    font-size: 15px !important;
  }
  #pm-modal-status {
    font-size: 11px !important;
    padding: 5px 10px !important;
    border-radius: 6px !important;
  }
  .pm-scale-box {
    padding: 12px 14px !important;
    margin-bottom: 16px !important;
    border-radius: 12px !important;
  }
  .pm-scale-title {
    font-size: 10px !important;
  }
  .pm-scale-badge {
    font-size: 10px !important;
  }
  .pm-scale-labels {
    margin-bottom: 6px !important;
  }
  .pm-scale-labels > div {
    font-size: 8px !important;
    font-weight: 700 !important;
    letter-spacing: -0.02em !important;
    line-height: 1 !important;
    white-space: nowrap !important;
  }
  .pm-scale-track {
    height: 5px !important;
    margin-bottom: 6px !important;
  }
  #pm-modal-marker {
    width: 13px !important;
    height: 13px !important;
    border-width: 2.5px !important;
  }
  #pm-modal-ticks {
    height: 14px !important;
  }
  #pm-modal-ticks span {
    font-size: 8.5px !important;
  }

  /* AQI Reference Scale List Responsive */
  .aqi-scale-item {
    padding: 9px 12px !important;
    border-radius: 12px !important;
    gap: 8px !important;
  }
  .aqi-scale-item .scale-dot {
    width: 10px !important;
    height: 10px !important;
  }
  .aqi-scale-item .scale-title {
    font-size: 13px !important;
  }
  .aqi-scale-item .scale-range {
    font-size: 11px !important;
  }
  .aqi-scale-item .scale-desc {
    font-size: 11px !important;
    line-height: 1.35 !important;
  }
  
  .page-heading {
    font-size: 18px !important;
  }
  .live-page-title {
    font-size: 16px !important;
    line-height: 1.25 !important;
    text-align: center !important;
  }
  .live-page-meta {
    font-size: 11px !important;
    margin-top: 3px !important;
    gap: 6px !important;
    display: flex !important;
    flex-wrap: wrap !important;
    align-items: center !important;
  }
  .live-page-meta #latest-time {
    padding: 1.5px 6px !important;
    font-size: 10px !important;
  }
  .live-page-meta #live-beacon-badge {
    padding: 1.5px 6px !important;
    font-size: 9.5px !important;
  }
  #hero-live-time-chip {
    font-size: 10px !important;
    padding: 1px 6px !important;
  }

  /* Comparison Chart & Filter Chips Mobile Optimization */
  .comparison-chart-card {
    padding: 14px 14px 12px 14px !important;
    border-radius: 16px !important;
    gap: 10px !important;
  }
  #pollutant-filters {
    gap: 4px !important;
    padding-bottom: 2px !important;
  }
  #pollutant-filters button {
    min-height: 25px !important;
    padding: 2.5px 8px !important;
    font-size: 10.5px !important;
    gap: 4px !important;
    border-width: 1px !important;
  }
  #pollutant-filters button span {
    width: 5px !important;
    height: 5px !important;
  }
}

@media (max-width: 380px) {
  .live-page-title {
    font-size: 14.5px !important;
    text-align: center !important;
  }
  .live-page-meta {
    font-size: 10px !important;
    gap: 4px !important;
  }
  .live-page-meta #latest-time {
    font-size: 9.5px !important;
    padding: 1px 5px !important;
  }
  .hero-scale-labels .scale-label {
    font-size: 7.2px !important;
    letter-spacing: -0.03em !important;
  }
  .hero-scale-ticks span {
    font-size: 7.5px !important;
  }
  .pm-scale-labels > div {
    font-size: 7.2px !important;
    letter-spacing: -0.03em !important;
  }
  #pm-modal-ticks span {
    font-size: 7.5px !important;
  }
  #pm-modal-val {
    font-size: 24px !important;
  }
}

/* Luminous Pulsing Beacon for Active Status */
@keyframes status-beacon-halo {
  0%   { transform: scale(0.95); opacity: 0.8; }
  50%  { transform: scale(1.45); opacity: 0.25; }
  100% { transform: scale(0.95); opacity: 0.8; }
}

@keyframes status-beacon-core {
  0%   { transform: scale(1); filter: brightness(1); }
  50%  { transform: scale(1.15); filter: brightness(1.25); }
  100% { transform: scale(1); filter: brightness(1); }
}

.status-dot-glowing #status-dot-halo {
  animation: status-beacon-halo 2.2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.status-dot-glowing #status-dot {
  animation: status-beacon-core 2.2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
  box-shadow: 0 0 8px currentColor;
}

.status-dot-offline #status-dot-halo {
  animation: none !important;
  opacity: 0.4 !important;
}

.status-dot-offline #status-dot {
  animation: none !important;
  box-shadow: none !important;
}

@keyframes modal-pop {
  0% { transform: scale(0.94); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}

/* ─────────────────────────────────────────────────────────────
   ANIMATED METEOROLOGICAL WEATHER DASHBOARD (NODE 2)
   Matches Reference: Compass, Windmill, Gust Tree, Rain, Pressure, UV
   ───────────────────────────────────────────────────────────── */
.weather-card-dark {
  background: #334155;
  background: linear-gradient(135deg, rgba(62, 75, 88, 0.88) 0%, rgba(40, 50, 62, 0.92) 100%);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 22px;
  padding: 22px 24px;
  box-shadow: 0 16px 36px rgba(15, 23, 42, 0.30);
  color: #ffffff;
  position: relative;
  overflow: hidden;
  transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.weather-card-dark:hover {
  transform: translateY(-2px);
  box-shadow: 0 20px 40px -4px rgba(15, 23, 42, 0.38);
}
.weather-card-uv {
  background: linear-gradient(135deg, rgba(34, 197, 94, 0.22) 0%, rgba(20, 83, 45, 0.32) 100%),
              linear-gradient(135deg, rgba(62, 75, 88, 0.90) 0%, rgba(40, 50, 62, 0.94) 100%) !important;
}

.weather-grid-params {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

/* Animations */
@keyframes spinTurbine {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.wind-turbine-blades {
  animation: spinTurbine 3s linear infinite;
  transform-origin: 30px 35px;
}

@keyframes windGustBlowTree {
  0%, 100% { transform: rotate(0deg) skewX(0deg); }
  25% { transform: rotate(2deg) skewX(-1.5deg); }
  50% { transform: rotate(5.5deg) skewX(-3.5deg); }
  75% { transform: rotate(2.5deg) skewX(-1.8deg); }
}
.swaying-tree {
  animation: windGustBlowTree 2.6s cubic-bezier(0.45, 0.05, 0.55, 0.95) infinite;
  transform-origin: 102px 58px;
}

@keyframes canopyFlutter {
  0%, 100% { transform: scale(1) rotate(0deg); }
  40% { transform: scale(1.03, 0.97) rotate(1.5deg); }
  70% { transform: scale(1.05, 0.95) rotate(2deg); }
}
.tree-canopy-group {
  animation: canopyFlutter 1.8s ease-in-out infinite;
  transform-origin: 105px 36px;
}

/* ══ Dynamic Twister Whirlwind Animations (Matches Reference Tornado Icon) ══ */
@keyframes twisterFunnelSway {
  0%, 100% {
    transform: rotate(0deg) skewX(0deg);
  }
  25% {
    transform: rotate(-4.5deg) skewX(3.5deg) translateX(-2px);
  }
  70% {
    transform: rotate(4deg) skewX(-3deg) translateX(2.5px);
  }
}

@keyframes vortexBandSpin {
  0% {
    stroke-dashoffset: 0;
  }
  100% {
    stroke-dashoffset: -120;
  }
}

@keyframes vortexBandPulse {
  0%, 100% {
    transform: scaleX(1);
    opacity: 0.95;
  }
  50% {
    transform: scaleX(0.96) scaleY(1.03);
    opacity: 1;
  }
}

@keyframes debrisOrbitUp1 {
  0% {
    transform: translate(0, 0) scale(0.4);
    opacity: 0;
  }
  20% {
    opacity: 1;
    transform: translate(-10px, -12px) scale(0.9);
  }
  60% {
    opacity: 0.95;
    transform: translate(12px, -28px) scale(1.1);
  }
  90% {
    opacity: 0.7;
    transform: translate(-14px, -45px) scale(0.8);
  }
  100% {
    transform: translate(8px, -58px) scale(0.3);
    opacity: 0;
  }
}

@keyframes debrisOrbitUp2 {
  0% {
    transform: translate(0, 0) scale(0.4);
    opacity: 0;
  }
  25% {
    opacity: 1;
    transform: translate(12px, -10px) scale(0.95);
  }
  65% {
    opacity: 0.95;
    transform: translate(-12px, -26px) scale(1.05);
  }
  85% {
    opacity: 0.8;
    transform: translate(14px, -42px) scale(0.85);
  }
  100% {
    transform: translate(-8px, -55px) scale(0.3);
    opacity: 0;
  }
}

.twister-container {
  animation: twisterFunnelSway 1.9s ease-in-out infinite;
  transform-origin: 50px 75px;
}

.vortex-band {
  animation: vortexBandSpin 1.4s linear infinite, vortexBandPulse 2.1s ease-in-out infinite;
  stroke-dasharray: 24 8 16 6;
}
.band-1 { animation-duration: 1.1s, 1.8s; }
.band-2 { animation-duration: 1.25s, 1.9s; animation-delay: -0.2s; }
.band-3 { animation-duration: 1.35s, 2.0s; animation-delay: -0.4s; }
.band-4 { animation-duration: 1.45s, 2.1s; animation-delay: -0.6s; }
.band-5 { animation-duration: 1.55s, 2.2s; animation-delay: -0.8s; }
.band-6 { animation-duration: 1.65s, 2.3s; animation-delay: -1.0s; }

.deb-orbit-1 {
  animation: debrisOrbitUp1 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}
.deb-orbit-2 {
  animation: debrisOrbitUp2 1.8s cubic-bezier(0.4, 0, 0.2, 1) infinite 0.4s;
}
.deb-orbit-3 {
  animation: debrisOrbitUp1 1.7s cubic-bezier(0.4, 0, 0.2, 1) infinite 0.85s;
}
.deb-orbit-4 {
  animation: debrisOrbitUp2 1.9s cubic-bezier(0.4, 0, 0.2, 1) infinite 1.2s;
}

@keyframes leafFlutterFly {
  0% { transform: translate(0, 0) rotate(0deg) scale(0.9); opacity: 0; }
  15% { opacity: 1; }
  50% { transform: translate(10px, -2px) rotate(100deg) scale(1); opacity: 0.95; }
  85% { transform: translate(20px, 4px) rotate(220deg) scale(0.95); opacity: 0.8; }
  100% { transform: translate(30px, 8px) rotate(340deg) scale(0.7); opacity: 0; }
}
.flying-leaf {
  animation: leafFlutterFly 2s cubic-bezier(0.25, 0.46, 0.45, 0.94) infinite;
}
.flying-leaf-1 { animation-delay: 0s; }
.flying-leaf-2 { animation-delay: 0.35s; }
.flying-leaf-3 { animation-delay: 0.7s; }
.flying-leaf-4 { animation-delay: 1.05s; }
.flying-leaf-5 { animation-delay: 1.4s; }
.flying-leaf-6 { animation-delay: 1.75s; }

@keyframes cloudFloatLeftRight {
  0% { transform: translateX(0px); }
  25% { transform: translateX(6px) translateY(-1px); }
  50% { transform: translateX(0px); }
  75% { transform: translateX(-6px) translateY(1px); }
  100% { transform: translateX(0px); }
}
.floating-rain-cloud {
  animation: cloudFloatLeftRight 4.5s ease-in-out infinite;
}

@keyframes sunPuffGlow {
  0%, 100% { transform: scale(1); opacity: 0.92; }
  50% { transform: scale(1.08); opacity: 1; }
}
.sun-puff-behind {
  animation: sunPuffGlow 3.2s ease-in-out infinite;
  transform-origin: 50px 20px;
}

@keyframes rainDropFall {
  0% { transform: translateY(-4px); opacity: 0; }
  35% { opacity: 1; }
  100% { transform: translateY(14px); opacity: 0; }
}
.drop-1 { animation: rainDropFall 1.1s linear infinite; }
.drop-2 { animation: rainDropFall 1.1s linear infinite 0.35s; }
.drop-3 { animation: rainDropFall 1.1s linear infinite 0.7s; }

/* ══ Active Moving Spectrum Bar & Slider Dot System ══ */
.live-slider-track {
  position: relative;
  border-radius: 999px;
  background-size: 200% 100% !important;
  animation: trackGradientShimmer 4s ease-in-out infinite alternate !important;
  overflow: visible !important;
  cursor: pointer;
}

.live-slider-track::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  border-radius: 999px;
  background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.45) 50%, transparent 100%);
  background-size: 40% 100%;
  background-repeat: no-repeat;
  animation: barTrackLightSweep 3s ease-in-out infinite;
  pointer-events: none;
}

@keyframes trackGradientShimmer {
  0% { background-position: 0% 50%; }
  100% { background-position: 100% 50%; }
}

@keyframes barTrackLightSweep {
  0% { background-position: -50% 0; }
  100% { background-position: 150% 0; }
}

/* Standby Mode Active Scanning (when awaiting live stream / telemetry) */
@keyframes barDotStandbyScan {
  0% { left: 16%; }
  50% { left: 84%; }
  100% { left: 16%; }
}

.bar-dot-standby-scan {
  animation: barDotStandbyScan 4s ease-in-out infinite !important;
  transition: none !important;
}

/* ══ Live Dynamic Moving Indicator Dots (Horizontal Sway + Breathing Glow) ══ */
@keyframes uvDotLiveMotion {
  0%, 100% {
    transform: translate(calc(-50% - 4px), -50%) scale(1);
    box-shadow: 0 0 6px rgba(244, 63, 94, 0.8), 0 0 12px rgba(244, 63, 94, 0.4);
  }
  50% {
    transform: translate(calc(-50% + 4px), -50%) scale(1.12);
    box-shadow: 0 0 14px rgba(244, 63, 94, 1), 0 0 22px rgba(244, 63, 94, 0.75);
  }
}
.uv-pulse-dot {
  animation: uvDotLiveMotion 3s ease-in-out infinite !important;
  will-change: transform, left;
  transition: left 1.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes pressureDotLiveMotion {
  0%, 100% {
    transform: translate(calc(-50% - 4px), -50%) scale(1);
    box-shadow: 0 0 6px rgba(236, 72, 153, 0.8), 0 0 12px rgba(236, 72, 153, 0.4);
  }
  50% {
    transform: translate(calc(-50% + 4px), -50%) scale(1.12);
    box-shadow: 0 0 14px rgba(236, 72, 153, 1), 0 0 22px rgba(236, 72, 153, 0.75);
  }
}
.pressure-pulse-dot {
  animation: pressureDotLiveMotion 3.2s ease-in-out infinite !important;
  will-change: transform, left;
  transition: left 1.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes soilTempDotLiveMotion {
  0%, 100% {
    transform: translate(calc(-50% - 4px), -50%) scale(1);
    box-shadow: 0 0 6px rgba(245, 158, 11, 0.8), 0 0 12px rgba(245, 158, 11, 0.4);
  }
  50% {
    transform: translate(calc(-50% + 4px), -50%) scale(1.12);
    box-shadow: 0 0 14px rgba(245, 158, 11, 1), 0 0 22px rgba(245, 158, 11, 0.75);
  }
}
.soil-temp-pulse-dot {
  animation: soilTempDotLiveMotion 3.4s ease-in-out infinite !important;
  will-change: transform, left;
  transition: left 1.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes soilHumDotLiveMotion {
  0%, 100% {
    transform: translate(calc(-50% - 4px), -50%) scale(1);
    box-shadow: 0 0 6px rgba(2, 132, 199, 0.8), 0 0 12px rgba(2, 132, 199, 0.4);
  }
  50% {
    transform: translate(calc(-50% + 4px), -50%) scale(1.12);
    box-shadow: 0 0 14px rgba(2, 132, 199, 1), 0 0 22px rgba(2, 132, 199, 0.75);
  }
}
.soil-hum-pulse-dot {
  animation: soilHumDotLiveMotion 2.9s ease-in-out infinite !important;
  will-change: transform, left;
  transition: left 1.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes soilPhDotLiveMotion {
  0%, 100% {
    transform: translate(calc(-50% - 4px), -50%) scale(1);
    box-shadow: 0 0 6px rgba(16, 185, 129, 0.8), 0 0 12px rgba(16, 185, 129, 0.4);
  }
  50% {
    transform: translate(calc(-50% + 4px), -50%) scale(1.12);
    box-shadow: 0 0 14px rgba(16, 185, 129, 1), 0 0 22px rgba(16, 185, 129, 0.75);
  }
}
.soil-ph-pulse-dot {
  animation: soilPhDotLiveMotion 3.1s ease-in-out infinite !important;
  will-change: transform, left;
  transition: left 1.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes soilEcDotLiveMotion {
  0%, 100% {
    transform: translate(calc(-50% - 4px), -50%) scale(1);
    box-shadow: 0 0 6px rgba(139, 92, 246, 0.8), 0 0 12px rgba(139, 92, 246, 0.4);
  }
  50% {
    transform: translate(calc(-50% + 4px), -50%) scale(1.12);
    box-shadow: 0 0 14px rgba(139, 92, 246, 1), 0 0 22px rgba(139, 92, 246, 0.75);
  }
}
.soil-ec-pulse-dot {
  animation: soilEcDotLiveMotion 3.3s ease-in-out infinite !important;
  will-change: transform, left;
  transition: left 1.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes heroScaleDotLiveMotion {
  0%, 100% {
    transform: translate(calc(-50% - 4px), -50%) scale(1);
    box-shadow: 0 1px 4px rgba(0,0,0,0.25), 0 0 8px rgba(255, 255, 255, 0.6);
  }
  50% {
    transform: translate(calc(-50% + 4px), -50%) scale(1.1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.3), 0 0 14px rgba(255, 255, 255, 0.95);
  }
}
#hero-scale-indicator, #pm-modal-marker {
  animation: heroScaleDotLiveMotion 3.5s ease-in-out infinite !important;
  will-change: transform, left;
  transition: left 0.8s cubic-bezier(0.34, 1.56, 0.64, 1), border-color 0.3s ease !important;
}

@media (max-width: 900px) {
  .weather-row-top {
    grid-template-columns: 1fr !important;
    gap: 12px !important;
  }
  .weather-grid-params {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 12px !important;
  }
  .weather-card-dark {
    padding: 16px 18px !important;
    border-radius: 18px !important;
  }
}

@media (max-width: 640px) {
  /* Strict mobile layout: eliminate all horizontal overflow */
  html, body {
    overflow-x: hidden !important;
    max-width: 100vw !important;
    width: 100% !important;
  }
  #app-layout-root,
  main,
  #page-content-wrapper,
  .live-page-root,
  .live-content-container,
  #node2-view-weather {
    max-width: 100% !important;
    min-width: 0 !important;
    width: 100% !important;
    overflow-x: hidden !important;
    box-sizing: border-box !important;
  }

  /* Mobile header safe spacing */
  header {
    padding-left: 10px !important;
    padding-right: 10px !important;
    gap: 4px !important;
    max-width: 100vw !important;
    box-sizing: border-box !important;
  }
  #mobile-top-bar {
    gap: 4px !important;
    min-width: 0 !important;
    flex-shrink: 1 !important;
  }
  #mobile-brand-overview {
    min-width: 0 !important;
    flex-shrink: 1 !important;
    gap: 6px !important;
  }
  #mobile-user-initial-tag {
    display: none !important;
  }

  /* Sub-navigation selector pills on mobile */
  #node2-live-subnav {
    width: 100% !important;
    padding: 0 4px !important;
    box-sizing: border-box !important;
  }
  #node2-live-subnav > div {
    width: 100% !important;
    max-width: 320px !important;
    margin: 0 auto !important;
    box-sizing: border-box !important;
  }
  #node2-tab-aqi, #node2-tab-weather, #node2-tab-soil {
    padding: 4px 10px !important;
    font-size: 11px !important;
    min-height: 28px !important;
    gap: 4px !important;
  }

  /* Title font on mobile */
  .live-page-title {
    font-size: 17px !important;
  }

  /* Row 1: Direction & Speed (~60%) + Gust (~40%) Side-by-Side */
  .weather-row-top {
    display: grid !important;
    grid-template-columns: 1.55fr 1fr !important;
    gap: 8px !important;
    width: 100% !important;
    box-sizing: border-box !important;
  }

  /* Parameter Grid: 6 cards in 2 equal columns */
  .weather-grid-params {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 8px !important;
    width: 100% !important;
    box-sizing: border-box !important;
  }

  .weather-card-dark {
    padding: 11px 12px !important;
    border-radius: 18px !important;
    min-height: 128px !important;
    box-sizing: border-box !important;
    width: 100% !important;
    max-width: 100% !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: space-between !important;
  }

  /* Card 1: Side-by-Side Compass + Stacked Metrics (Direction on top, Wind Speed on bottom) */
  .weather-card-wind {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: flex-start !important;
    padding: 10px 10px !important;
    gap: 8px !important;
    flex-wrap: nowrap !important;
  }
  .compass-widget-wrap {
    width: 54px !important;
    height: 54px !important;
    flex-shrink: 0 !important;
  }
  .weather-wind-metrics-group {
    display: flex !important;
    flex-direction: column !important;
    justify-content: center !important;
    gap: 6px !important;
    min-width: 0 !important;
    flex: 1 !important;
  }
  .weather-wind-divider {
    display: none !important;
  }
  .wind-turbine-wrap {
    display: flex !important;
    width: 36px !important;
    height: 54px !important;
    flex-shrink: 0 !important;
    align-items: center !important;
    justify-content: center !important;
  }
  .weather-metric-block {
    display: block !important;
    margin: 0 !important;
  }
  .weather-metric-large {
    font-size: 17px !important;
    line-height: 1.1 !important;
    font-weight: 800 !important;
    white-space: nowrap !important;
  }
  .weather-metric-label {
    font-size: 10.5px !important;
    color: #94a3b8 !important;
    margin-top: 1px !important;
    white-space: nowrap !important;
  }

  /* Card 2: Gust Speed */
  .weather-card-gust {
    padding: 10px 10px !important;
    text-align: center !important;
  }
  .weather-gust-graphic-wrap {
    height: 44px !important;
  }
  .weather-gust-val {
    font-size: 17px !important;
    line-height: 1.1 !important;
  }

  /* Parameter cards typography & sizing */
  .weather-card-number {
    font-size: 18px !important;
    line-height: 1.1 !important;
  }
  .weather-precip-icon-wrap,
  .weather-soil-icon-wrap {
    width: 34px !important;
    height: 34px !important;
  }
  .weather-pressure-dial-wrap {
    width: 58px !important;
    height: 58px !important;
  }
  .weather-card-divider {
    margin: 8px 0 6px 0 !important;
  }
  .weather-metric-note {
    font-size: 9.5px !important;
    line-height: 1.25 !important;
    margin-top: 6px !important;
    color: #94a3b8 !important;
  }

  /* Weather coords header */
  .weather-coords-header {
    gap: 5px !important;
    margin-top: 2px !important;
  }
  .weather-coords-header > div {
    padding: 3px 10px !important;
    font-size: 10.5px !important;
  }

  /* Weather Telemetry Table Container & Scroll on mobile */
  #live-weather-table-container {
    border-radius: 16px !important;
    margin-top: 6px !important;
    width: 100% !important;
    max-width: 100% !important;
    overflow: hidden !important;
    box-sizing: border-box !important;
  }
  #live-weather-table-container > div:last-child {
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    box-sizing: border-box !important;
  }
  #live-weather-table-container table {
    min-width: 680px !important;
  }
}

/* ═══════════════════════════════════════════════════════════════
   NODE 2 ATMOSPHERIC DYNAMIC WEATHER BACKDROP (FULL-BLEED)
   Matches Reference: Moving Sunset Clouds + Radiant Sky
   ═══════════════════════════════════════════════════════════════ */
body.weather-backdrop-active {
  background-color: #1e293b !important;
  background-image: url('assets/sunset_clouds_backdrop.jpg'), url('/assets/sunset_clouds_backdrop.jpg') !important;
  background-size: cover !important;
  background-position: center top !important;
  background-attachment: fixed !important;
  background-repeat: no-repeat !important;
}

body.weather-backdrop-active #app-layout-root,
body.weather-backdrop-active #page-content-wrapper,
body.weather-backdrop-active main {
  background: transparent !important;
  background-image: none !important;
}

/* Translucent Glassmorphic Header on Weather Live */
body.weather-backdrop-active header {
  background-color: rgba(15, 23, 42, 0.45) !important;
  backdrop-filter: blur(16px) !important;
  -webkit-backdrop-filter: blur(16px) !important;
  border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.25) !important;
}

body.weather-backdrop-active header .header-campus-title,
body.weather-backdrop-active header a,
body.weather-backdrop-active header span:not(.campus-status-dot) {
  color: #ffffff !important;
}

/* Translucent Glassmorphic Footer on Weather Live */
body.weather-backdrop-active #global-footer {
  background: rgba(15, 23, 42, 0.65) !important;
  backdrop-filter: blur(14px) !important;
  -webkit-backdrop-filter: blur(14px) !important;
  border-top: 1px solid rgba(255, 255, 255, 0.12) !important;
  box-shadow: 0 -4px 24px rgba(0, 0, 0, 0.25) !important;
}

body.weather-backdrop-active #node2-live-subnav > div {
  background: rgba(15, 23, 42, 0.55) !important;
  backdrop-filter: blur(14px) !important;
  -webkit-backdrop-filter: blur(14px) !important;
  border: 1px solid rgba(255, 255, 255, 0.20) !important;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25) !important;
}

body.weather-backdrop-active #node2-tab-aqi:not(.bg-\[\#00bfa5\]),
body.weather-backdrop-active #node2-tab-soil:not(.bg-\[\#00bfa5\]) {
  color: rgba(255, 255, 255, 0.82) !important;
}

body.weather-backdrop-active #node2-tab-aqi:not(.bg-\[\#00bfa5\]):hover,
body.weather-backdrop-active #node2-tab-soil:not(.bg-\[\#00bfa5\]):hover {
  color: #ffffff !important;
  background-color: rgba(255, 255, 255, 0.12) !important;
}

.node2-weather-backdrop {
  position: fixed;
  inset: 0;
  width: 100vw;
  height: 100vh;
  z-index: 0;
  pointer-events: none;
  overflow: hidden;
  transition: opacity 0.8s ease;
  background-color: transparent;
}

/* Infinite GPU-accelerated horizontal cloud drift */
@keyframes continuousCloudDrift {
  0% { transform: translate3d(0, 0, 0); }
  100% { transform: translate3d(-50%, 0, 0); }
}

.cloud-drift-track {
  position: absolute;
  top: 0;
  left: 0;
  display: flex;
  width: 400vw;
  height: 100%;
  animation: continuousCloudDrift 60s linear infinite;
  will-change: transform;
}

.cloud-drift-panel {
  width: 100vw;
  height: 100%;
  flex-shrink: 0;
  background-color: transparent;
  background-image: url('assets/sunset_clouds_backdrop.jpg');
  background-size: cover;
  background-position: center top;
  background-repeat: no-repeat;
  opacity: 0.82;
}

.cloud-drift-panel.mirrored {
  transform: none; /* Avoid unnatural reversed mirror seams across clouds */
}

.sky-ambient-vignette {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  z-index: 1;
  pointer-events: none;
  background: radial-gradient(ellipse 110% 75% at 50% 12%, rgba(20, 60, 120, 0.10) 0%, rgba(0, 0, 0, 0) 55%),
              linear-gradient(180deg, rgba(15, 23, 42, 0.12) 0%, rgba(0, 0, 0, 0) 35%, rgba(245, 158, 11, 0.08) 85%, rgba(249, 115, 22, 0.16) 100%);
}

#weather-sky-canvas {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  z-index: 2;
  pointer-events: none;
}

/* ═══════════════════════════════════════════════════════════════
   METEOROLOGICAL & AIR QUALITY TREND GRAPH (MATCHING REFERENCE)
   ═══════════════════════════════════════════════════════════════ */
.trend-graph-card {
  background: #252a37;
  background: linear-gradient(135deg, #2b3040 0%, #1e2230 100%);
  border: 1px solid rgba(255, 255, 255, 0.10);
  border-radius: 24px;
  padding: 22px 26px;
  box-shadow: 0 16px 36px rgba(15, 23, 42, 0.28);
  color: #ffffff;
  position: relative;
  overflow: visible;
  margin-top: 10px;
}
.trend-graph-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 14px;
  margin-bottom: 20px;
}
.trend-card-title {
  font-size: clamp(17px, 2vw, 21px);
  font-weight: 700;
  color: #ffffff;
  margin: 0;
  font-family: var(--font-sans);
  letter-spacing: -0.01em;
  display: flex;
  align-items: center;
  gap: 8px;
}
.trend-controls-cluster {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.trend-pill-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 7px 15px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.18);
  color: #ffffff;
  font-size: 13px;
  font-weight: 600;
  font-family: var(--font-sans);
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.18);
  position: relative;
}
.trend-pill-btn:hover {
  background: rgba(255, 255, 255, 0.15);
  border-color: rgba(255, 255, 255, 0.32);
}
.trend-date-input-overlay {
  position: absolute;
  inset: 0;
  opacity: 0;
  width: 100%;
  height: 100%;
  cursor: pointer;
  z-index: 5;
}
.trend-dropdown-menu {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  z-index: 60;
  min-width: 205px;
  background: #1e2230;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 18px;
  padding: 6px;
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.06);
  display: flex;
  flex-direction: column;
  gap: 3px;
  animation: modal-pop 0.16s cubic-bezier(0.16, 1, 0.3, 1);
}
.trend-dropdown-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border-radius: 11px;
  font-size: 13px;
  font-weight: 600;
  color: #cbd5e1;
  cursor: pointer;
  transition: all 0.15s ease;
}
.trend-dropdown-item:hover {
  background: rgba(255, 255, 255, 0.08);
  color: #ffffff;
}
.trend-dropdown-item.active {
  background: rgba(56, 189, 248, 0.15);
  color: #38bdf8;
}
.trend-dropdown-item .trend-check {
  width: 17px;
  height: 17px;
  border-radius: 4px;
  border: 1.5px solid rgba(255, 255, 255, 0.30);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 800;
  color: transparent;
  flex-shrink: 0;
  transition: all 0.15s ease;
}
.trend-dropdown-item.active .trend-check {
  background: #0284c7;
  border-color: #0284c7;
  color: #ffffff;
}
.trend-legend-bar {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 16px;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  font-size: 12px;
  color: #cbd5e1;
  font-family: var(--font-sans);
}
.trend-legend-item {
  display: flex;
  align-items: center;
  gap: 7px;
  font-weight: 600;
}
.trend-legend-swatch-solid {
  width: 16px;
  height: 3px;
  border-radius: 2px;
}
.trend-legend-swatch-dotted {
  width: 16px;
  height: 0px;
  border-top: 2px dashed rgba(255, 255, 255, 0.65);
}
</style>

  <!-- ═══════════════════════════════════════════════════════════════
       ATMOSPHERIC DYNAMIC WEATHER BACKDROP (NODE 2)
       Matches Reference: Rolling Green Hills + Dynamic Animated Sky
       (Drifting Cloud Animation if Temp Low & Hum High, else Sunny Bright)
       ═══════════════════════════════════════════════════════════════ -->
  <div id="node2-weather-backdrop" class="node2-weather-backdrop <?= $is_cloudy_initial ? 'cloudy' : 'sunny' ?>" style="<?= ($is_node_2 && $active_live_subtab !== 'weather') ? 'display: none;' : '' ?>" aria-hidden="true">
    <!-- Seamless Infinite Horizontal Drifting Panoramic Cloud Track -->
    <div class="cloud-drift-track">
      <div class="cloud-drift-panel"></div>
      <div class="cloud-drift-panel mirrored"></div>
      <div class="cloud-drift-panel"></div>
      <div class="cloud-drift-panel mirrored"></div>
    </div>
    <div class="sky-ambient-vignette"></div>
    <canvas id="weather-sky-canvas"></canvas>
  </div>

  <!-- Live Content Container -->
  <div class="live-page-root live-content-container p-2 sm:p-6" style="position: relative; z-index: 2; display: flex; flex-direction: column; gap: 14px; width: 100%; max-width: 1200px; margin: 0 auto; min-width: 0; box-sizing: border-box; overflow-x: hidden;">

<!-- Offline Banner -->
<div id="live-offline-banner" style="display: none; background-color: #fff7ed; border: 1px solid #fed7aa; border-left: 4px solid #f97316; border-radius: 12px; padding: 12px 16px; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 12px;">
  <div style="display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: #475569;">
    <i data-lucide="alert-triangle" style="width: 16px; height: 16px; color: #f97316; margin-top: 2px; flex-shrink: 0;"></i>
    <span><strong style="color: #c2410c;">Sensor Node Offline:</strong> No new live telemetry received in cloud for &gt;5 mins (Last update: <strong style="color: #f97316;" id="live-offline-last-update">--</strong>). Showing last recorded cloud values below.</span>
  </div>
  <div id="live-offline-badge-text" style="background-color: #ffedd5; color: #c2410c; font-size: 10px; font-weight: 800; padding: 4px 10px; border-radius: 999px; letter-spacing: 0.05em; font-family: var(--font-mono); white-space: nowrap;">OFFLINE</div>
</div>

<?php if ($is_node_2): ?>
  <!-- Instant Switcher Script to ensure 100% reliable click response -->
  <script>
  window.switchNode2LiveTab = function(tab) {
      if (window.triggerHaptic) window.triggerHaptic('light');
      const aqiView = document.getElementById('node2-view-aqi');
      const weatherView = document.getElementById('node2-view-weather');
      const soilView = document.getElementById('node2-view-soil');
      const btnAqi = document.getElementById('node2-tab-aqi');
      const btnWeather = document.getElementById('node2-tab-weather');
      const btnSoil = document.getElementById('node2-tab-soil');
      const backdrop = document.getElementById('node2-weather-backdrop');

      const activeCls = 'flex items-center justify-center gap-1.5 py-1 sm:py-1 px-3 sm:px-3.5 rounded-full font-bold text-xs cursor-pointer transition-all bg-[#00bfa5] text-white shadow-sm min-h-[28px] sm:min-h-[30px] whitespace-nowrap';
      const inactiveCls = 'flex items-center justify-center gap-1.5 py-1 sm:py-1 px-3 sm:px-3.5 rounded-full font-bold text-xs cursor-pointer transition-all bg-transparent text-slate-500 hover:text-slate-800 min-h-[28px] sm:min-h-[30px] whitespace-nowrap';

      const layoutRoot = document.getElementById('app-layout-root');

      if (tab === 'soil') {
          if (aqiView) aqiView.style.display = 'none';
          if (weatherView) weatherView.style.display = 'none';
          if (soilView) soilView.style.display = 'flex';
          if (btnSoil) btnSoil.className = activeCls;
          if (btnAqi) btnAqi.className = inactiveCls;
          if (btnWeather) btnWeather.className = inactiveCls;
          if (backdrop) backdrop.style.display = 'none';
          document.body.classList.remove('weather-backdrop-active');
          if (layoutRoot) {
              layoutRoot.style.removeProperty('background');
              layoutRoot.style.removeProperty('background-image');
              layoutRoot.style.backgroundColor = '#f0fdf4';
              layoutRoot.style.backgroundImage = 'radial-gradient(ellipse 120% 70% at 50% -10%, #dcfce7 0%, #ecfdf5 35%, #f0fdfa 65%, #f8fafc 100%)';
          }
          if (typeof renderSoilLiveTrendChart === 'function') setTimeout(renderSoilLiveTrendChart, 50);
      } else if (tab === 'weather') {
          if (aqiView) aqiView.style.display = 'none';
          if (soilView) soilView.style.display = 'none';
          if (weatherView) weatherView.style.display = 'flex';
          if (btnWeather) btnWeather.className = activeCls;
          if (btnAqi) btnAqi.className = inactiveCls;
          if (btnSoil) btnSoil.className = inactiveCls;
          if (backdrop) backdrop.style.display = 'block';
          document.body.classList.add('weather-backdrop-active');
          if (layoutRoot) {
              layoutRoot.style.setProperty('background', 'transparent', 'important');
              layoutRoot.style.setProperty('background-image', 'none', 'important');
          }
          if (typeof initLiveAmbientCanvas === 'function') initLiveAmbientCanvas();
      } else {
          if (weatherView) weatherView.style.display = 'none';
          if (soilView) soilView.style.display = 'none';
          if (aqiView) aqiView.style.display = 'flex';
          if (btnAqi) btnAqi.className = activeCls;
          if (btnWeather) btnWeather.className = inactiveCls;
          if (btnSoil) btnSoil.className = inactiveCls;
          if (backdrop) backdrop.style.display = 'none';
          document.body.classList.remove('weather-backdrop-active');
          if (layoutRoot) {
              layoutRoot.style.removeProperty('background');
              layoutRoot.style.removeProperty('background-image');
              layoutRoot.style.backgroundColor = (window.currentAQITheme && window.currentAQITheme.pageColor) ? window.currentAQITheme.pageColor : '#fde68a';
              layoutRoot.style.backgroundImage = (window.currentAQITheme && window.currentAQITheme.pageBg) ? window.currentAQITheme.pageBg : 'radial-gradient(ellipse 120% 70% at 50% -10%, #fde047 0%, #fde68a 35%, #fef08a 65%, #fde68a 100%)';
          }
          if (typeof renderComparisonChart === 'function') setTimeout(renderComparisonChart, 50);
      }

      try {
          const url = new URL(window.location.href);
          url.searchParams.set('tab', tab);
          window.history.replaceState({ page: 'live', node: '2', tab: tab }, '', url.toString());
      } catch (e) {}

      if (window.lucide && lucide.createIcons) {
          lucide.createIcons();
      }
  };

  if (<?= json_encode($active_live_subtab === 'weather') ?>) {
      document.body.classList.add('weather-backdrop-active');
      const lr = document.getElementById('app-layout-root');
      if (lr) {
          lr.style.setProperty('background', 'transparent', 'important');
          lr.style.setProperty('background-image', 'none', 'important');
      }
  } else if (<?= json_encode($active_live_subtab === 'soil') ?>) {
      document.body.classList.remove('weather-backdrop-active');
      const lr = document.getElementById('app-layout-root');
      if (lr) {
          lr.style.removeProperty('background');
          lr.style.removeProperty('background-image');
          lr.style.backgroundColor = '#dcfce7';
          lr.style.backgroundImage = 'radial-gradient(ellipse 120% 70% at 50% -10%, #86efac 0%, #bbf7d0 35%, #dcfce7 65%, #bbf7d0 100%)';
      }
  } else {
      document.body.classList.remove('weather-backdrop-active');
      const lr = document.getElementById('app-layout-root');
      if (lr) {
          lr.style.removeProperty('background');
          lr.style.removeProperty('background-image');
          lr.style.backgroundColor = '#fde68a';
          lr.style.backgroundImage = 'radial-gradient(ellipse 120% 70% at 50% -10%, #fde047 0%, #fde68a 35%, #fef08a 65%, #fde68a 100%)';
      }
  }

  // Pre-check and guarantee cloud drift background image path
  (function() {
      const testImg = new Image();
      testImg.src = 'assets/sunset_clouds_backdrop.jpg';
      testImg.onerror = function() {
          document.querySelectorAll('.cloud-drift-panel').forEach(function(p) {
              p.style.backgroundImage = "url('/assets/sunset_clouds_backdrop.jpg')";
          });
      };
  })();
  </script>

  <!-- Sub-Navigation Selector for Node 2: Live AQI vs Live Weather vs Live Soil (Compact Pill Switcher) -->
  <div class="flex items-center justify-center mb-1 w-full px-1" id="node2-live-subnav">
    <div class="inline-flex items-center bg-white p-0.5 sm:p-1 rounded-full border border-slate-300 shadow-sm gap-1">
      <button id="node2-tab-aqi" type="button" onclick="switchNode2LiveTab('aqi')" class="flex items-center justify-center gap-1.5 py-1 sm:py-1 px-3 sm:px-3.5 rounded-full font-bold text-xs cursor-pointer transition-all <?= ($active_live_subtab === 'aqi') ? 'bg-[#00bfa5] text-white shadow-sm' : 'bg-transparent text-slate-500 hover:text-slate-800' ?> min-h-[28px] sm:min-h-[30px] whitespace-nowrap">
        <i data-lucide="activity" class="w-3.5 h-3.5 shrink-0"></i>
        <span>AQI Live</span>
      </button>
      <button id="node2-tab-weather" type="button" onclick="switchNode2LiveTab('weather')" class="flex items-center justify-center gap-1.5 py-1 sm:py-1 px-3 sm:px-3.5 rounded-full font-bold text-xs cursor-pointer transition-all <?= ($active_live_subtab === 'weather') ? 'bg-[#00bfa5] text-white shadow-sm' : 'bg-transparent text-slate-500 hover:text-slate-800' ?> min-h-[28px] sm:min-h-[30px] whitespace-nowrap">
        <i data-lucide="cloud-sun" class="w-3.5 h-3.5 shrink-0"></i>
        <span>Weather Live</span>
      </button>
      <button id="node2-tab-soil" type="button" onclick="switchNode2LiveTab('soil')" class="flex items-center justify-center gap-1.5 py-1 sm:py-1 px-3 sm:px-3.5 rounded-full font-bold text-xs cursor-pointer transition-all <?= ($active_live_subtab === 'soil') ? 'bg-[#00bfa5] text-white shadow-sm' : 'bg-transparent text-slate-500 hover:text-slate-800' ?> min-h-[28px] sm:min-h-[30px] whitespace-nowrap">
        <i data-lucide="sprout" class="w-3.5 h-3.5 shrink-0"></i>
        <span>Soil Live</span>
      </button>
    </div>
  </div>
<?php endif; ?>

<div id="node2-view-aqi" style="<?= ($is_node_2 && $active_live_subtab !== 'aqi') ? 'display: none;' : 'display: flex;' ?> width: 100%; flex-direction: column; gap: 14px;">

  <!-- Page Header Titles -->
  <div style="display: flex; justify-content: center; align-items: center; text-align: center; width: 100%; margin-top: 10px; margin-bottom: 6px;">
    <div style="width: 100%; text-align: center;">
      <h1 class="page-heading live-page-title" style="font-size: clamp(20px, 2.5vw, 24px); font-weight: 800; color: #0f172a; font-family: var(--font-sans); letter-spacing: -0.02em; margin: 0; text-align: center;">
        Real-time Air Quality &amp; Weather Telemetry
      </h1>
      <span id="latest-time" style="display: none;"><?= htmlspecialchars($init_time_str) ?></span>
      <span id="live-beacon-badge" style="display: none;"></span>
    </div>
  </div>

  <!-- UNIFIED HERO CARD (AQI Box - Half Width Centered) -->
  <div id="hero-card" class="hero-card-responsive" style="max-width: 560px; width: 100%; margin: 0 auto 16px auto; background: <?= $init_aqi_bg ?>; border-radius: 24px; padding: 22px 24px; color: #0f172a; border: 1.5px solid <?= $init_aqi_border ?>; box-shadow: 0 10px 30px <?= $init_aqi_shadow ?>, 0 1px 3px rgba(15,23,42,0.04); position: relative; overflow: hidden; transition: background 0.6s ease, border-color 0.5s ease, box-shadow 0.5s ease;">
    <!-- Indian Monuments Silhouette Watermark -->
    <div class="hero-skyline-silhouette" style="position: absolute; bottom: 0; left: 0; right: 0; height: 68px; pointer-events: none; z-index: 0; opacity: 0.14; overflow: hidden; display: flex; align-items: flex-end;">
      <svg id="hero-skyline-svg" viewBox="0 0 1000 90" preserveAspectRatio="none" style="width: 100%; height: 100%; fill: <?= $init_aqi_accent ?>; transition: fill 0.5s ease;">
        <path d="M40 90 L40 38 L48 38 L48 30 L55 30 L55 24 L105 24 L105 30 L112 30 L112 38 L120 38 L120 90 L98 90 L98 52 C98 42 62 42 62 52 L62 90 Z" />
        <path d="M190 90 L190 32 L193 32 L193 25 L190 25 L192 18 L194 25 L195 90 Z" />
        <path d="M210 90 L210 50 L242 50 C242 42 248 36 254 36 C260 36 266 42 266 50 L274 50 C274 38 282 28 290 14 C290 8 291 8 291 14 C299 28 307 38 307 50 L315 50 C315 42 321 36 327 36 C333 36 339 42 339 50 L370 50 L370 90 L318 90 L318 64 C318 56 262 56 262 64 L262 90 Z" />
        <path d="M390 90 L390 32 L393 32 L393 25 L390 25 L392 18 L394 25 L395 90 Z" />
        <path d="M500 90 C520 62 548 38 565 24 C582 38 610 62 630 90 L595 90 C585 72 575 50 565 42 C555 50 545 72 535 90 Z" />
        <path d="M480 90 C495 72 515 55 532 46 C525 60 520 75 515 90 Z" />
        <path d="M650 90 C635 72 615 55 598 46 C605 60 610 75 615 90 Z" />
        <path d="M740 90 L740 22 L744 22 L744 14 L741 14 L743 8 L745 14 L746 90 Z" />
        <path d="M840 90 L840 22 L844 22 L844 14 L841 14 L843 8 L845 14 L846 90 Z" />
        <path d="M750 90 L750 42 L836 42 L836 90 L812 90 L812 60 C812 52 774 52 774 60 L774 90 Z" />
        <path d="M890 28 Q894 24 898 28 Q902 24 906 28 Q902 26 898 29 Q894 26 890 28 Z" />
        <path d="M915 22 Q918 19 922 22 Q925 19 929 22 Q925 20 922 23 Q918 20 915 22 Z" />
      </svg>
    </div>

    <div style="position: relative; z-index: 1; width: 100%;">
      <div class="hero-flex-row flex flex-col items-stretch w-full" style="width: 100%;">
        
        <!-- AQI Hero Card: Index, Category Badge, PM Quick Row & Scale Bar (Full Width) -->
        <div class="w-full flex flex-col justify-between hero-aqi-col" style="width: 100%;">
          
          <!-- Top Row: Live AQI & Big Value + Air Quality is & Badge -->
          <div class="hero-header-row" style="display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; margin-bottom: 14px; flex-wrap: wrap;">
            <!-- Left: Live AQI & Big Value -->
            <div style="display: flex; flex-direction: column;">
              <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px; flex-wrap: wrap;">
                <div id="status-dot-container" class="<?= ($init_is_aqi_offline) ? 'status-dot-offline' : 'status-dot-glowing' ?>" style="display: flex; align-items: center; gap: 7px; color: <?= $init_aqi_accent ?>;">
                  <span id="status-dot-halo" style="display: inline-flex; align-items: center; justify-content: center; width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; background-color: <?= ($init_is_aqi_offline) ? 'rgba(148, 163, 184, 0.20)' : $init_aqi_accent . '33' ?>; color: <?= ($init_is_aqi_offline) ? '#94a3b8' : $init_aqi_accent ?>; transition: background-color 0.5s ease, color 0.5s ease;">
                    <span id="status-dot" style="width: 6.5px; height: 6.5px; border-radius: 50%; background-color: <?= ($init_is_aqi_offline) ? '#94a3b8' : $init_aqi_accent ?>; color: <?= ($init_is_aqi_offline) ? '#94a3b8' : $init_aqi_accent ?>; display: inline-block; transition: background-color 0.5s ease, color 0.5s ease;"></span>
                  </span>
                  <span id="hero-stream-text" style="font-size: 13px; font-weight: 700; color: #334155; font-family: var(--font-sans); letter-spacing: -0.01em;"><?= ($init_is_aqi_offline ? 'Cached AQI' : 'Live AQI') ?></span>
                </div>
                <span id="hero-live-time-chip" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; font-family: var(--font-mono); color: #475569; background: rgba(255, 255, 255, 0.85); border: 1px solid rgba(226, 232, 240, 0.9); padding: 1.5px 7px; border-radius: 6px; box-shadow: 0 1px 2px rgba(15,23,42,0.03);">
                  <i data-lucide="clock" id="hero-clock-icon" style="width: 11px; height: 11px; color: <?= $init_aqi_accent ?>;"></i>
                  <span id="hero-latest-time"><?= htmlspecialchars($init_time_str) ?></span>
                </span>
              </div>
              <div style="display: flex; align-items: baseline; gap: 8px;">
                <div id="hero-aqi-val" style="font-family: var(--font-mono); font-size: 64px; font-weight: 900; line-height: 0.95; letter-spacing: -0.04em; color: <?= $init_aqi_accent ?>; transition: color 0.5s ease;">
                  <?= $init_aqi_val ?>
                </div>
                <span id="hero-aqi-subtext" style="font-size: 13px; font-weight: 700; color: #64748b; font-family: var(--font-mono); text-transform: uppercase; letter-spacing: 0.02em; white-space: nowrap;">AQI (IN)</span>
              </div>
            </div>

            <!-- Right: Air Quality is & Rounded Status Box -->
            <div style="display: flex; flex-direction: column; align-items: flex-end; justify-content: flex-end;">
              <div id="hero-aqi-title" style="font-size: 12.5px; font-weight: 700; color: #475569; margin-bottom: 4px; font-family: var(--font-sans);">Air Quality is</div>
              <div id="hero-aqi-badge" style="display: inline-flex; align-items: center; justify-content: center; min-width: 100px; padding: 6px 16px; border-radius: 12px; background: <?= $init_aqi_badge_bg ?>; border: 1.5px solid <?= $init_aqi_badge_border ?>; box-shadow: 0 2px 8px rgba(0,0,0,0.03); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); transition: background 0.5s ease, border-color 0.5s ease;">
                <span id="hero-aqi-label" style="font-size: 17px; font-weight: 800; color: <?= $init_aqi_badge_text ?>; font-family: var(--font-sans); line-height: 1.15; letter-spacing: -0.01em; transition: color 0.5s ease;"><?= $init_aqi_label ?></span>
              </div>
            </div>
          </div>

          <!-- Quick PM2.5 & PM10 Row -->
          <div class="hero-pm-row" style="display: flex; align-items: baseline; justify-content: space-between; max-width: 100%; margin-bottom: 14px; font-size: 13.5px; font-weight: 700;">
            <div style="display: flex; align-items: baseline; gap: 6px;">
              <span style="color: #334155; font-weight: 800; letter-spacing: -0.01em;">PM2.5 :</span>
              <strong id="hero-pm25" style="color: #0f172a; font-size: 15.5px; font-family: var(--font-mono); font-weight: 800;"><?= $init_pm25 ?></strong>
              <span class="hero-pm-unit" style="font-size: 11px; color: #475569; font-weight: 600;">µg/m³</span>
            </div>
            <div class="hero-pm-divider" style="display: none; width: 1px; height: 12px; background: rgba(203, 213, 225, 0.8);"></div>
            <div style="display: flex; align-items: baseline; gap: 6px;">
              <span style="color: #334155; font-weight: 800; letter-spacing: -0.01em;">PM10 :</span>
              <strong id="hero-pm10" style="color: #0f172a; font-size: 15.5px; font-family: var(--font-mono); font-weight: 800;"><?= $init_pm10 ?></strong>
              <span class="hero-pm-unit" style="font-size: 11px; color: #475569; font-weight: 600;">µg/m³</span>
            </div>
          </div>

          <!-- Overall AQI Scale Bar -->
          <div class="hero-scale-bar-container" style="max-width: 100%; width: 100%;">
            <div class="hero-scale-labels" style="display: grid; grid-template-columns: repeat(6, 1fr); padding: 0; margin-bottom: 8px;">
              <span class="scale-label" style="font-size: 11px; font-weight: 700; color: #334155; line-height: 1.1; text-align: center; white-space: nowrap;">Good</span>
              <span class="scale-label" style="font-size: 11px; font-weight: 700; color: #334155; line-height: 1.1; text-align: center; white-space: nowrap;">Moderate</span>
              <span class="scale-label" style="font-size: 11px; font-weight: 700; color: #334155; line-height: 1.1; text-align: center; white-space: nowrap;">Poor</span>
              <span class="scale-label" style="font-size: 11px; font-weight: 700; color: #334155; line-height: 1.1; text-align: center; white-space: nowrap;">Unhealthy</span>
              <span class="scale-label" style="font-size: 11px; font-weight: 700; color: #334155; line-height: 1.1; text-align: center; white-space: nowrap;">Severe</span>
              <span class="scale-label" style="font-size: 11px; font-weight: 700; color: #334155; line-height: 1.1; text-align: center; white-space: nowrap;">Hazardous</span>
            </div>

            <div id="hero-scale-track" class="hero-scale-bar-track live-slider-track" style="position: relative; height: 7px; border-radius: 999px; background-color: #e2e8f0; margin-bottom: 8px;">
              <div style="width: 100%; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #22c55e 0%, #22c55e 16.666%, #eab308 16.666%, #eab308 33.333%, #f97316 33.333%, #f97316 50%, #ef4444 50%, #ef4444 66.666%, #a855f7 66.666%, #a855f7 83.333%, #b91c1c 83.333%, #b91c1c 100%); box-shadow: inset 0 1px 2px rgba(0,0,0,0.06);"></div>
              <div id="hero-scale-indicator" style="position: absolute; top: 50%; left: <?= $init_scale_pct ?>; transform: translate(-50%, -50%); width: 17px; height: 17px; border-radius: 50%; background-color: #ffffff; border: 3.5px solid <?= $init_aqi_accent ?>; box-shadow: 0 1px 4px rgba(0,0,0,0.25); z-index: 3; transition: left 0.5s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.3s ease;"></div>
            </div>

            <div class="hero-scale-ticks" style="position: relative; height: 16px;">
              <span style="position: absolute; left: 0%; font-size: 10.5px; font-weight: 700; color: #475569; font-family: var(--font-mono); line-height: 1;">0</span>
              <span style="position: absolute; left: 16.666%; transform: translateX(-50%); font-size: 10.5px; font-weight: 700; color: #475569; font-family: var(--font-mono); line-height: 1;">50</span>
              <span style="position: absolute; left: 33.333%; transform: translateX(-50%); font-size: 10.5px; font-weight: 700; color: #475569; font-family: var(--font-mono); line-height: 1;">100</span>
              <span style="position: absolute; left: 50%; transform: translateX(-50%); font-size: 10.5px; font-weight: 700; color: #475569; font-family: var(--font-mono); line-height: 1;">200</span>
              <span style="position: absolute; left: 66.666%; transform: translateX(-50%); font-size: 10.5px; font-weight: 700; color: #475569; font-family: var(--font-mono); line-height: 1;">300</span>
              <span style="position: absolute; left: 83.333%; transform: translateX(-50%); font-size: 10.5px; font-weight: 700; color: #475569; font-family: var(--font-mono); line-height: 1;">400</span>
              <span style="position: absolute; right: 0%; font-size: 10.5px; font-weight: 700; color: #475569; font-family: var(--font-mono); line-height: 1;">500+</span>
            </div>
          </div>

        </div>



      </div>
    </div>
  </div>



  <!-- PARAMETERS GRID (Air Quality Pollutants - 5 Criteria Parameters) -->
  <div style="margin-top: 14px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
      <div>
        <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; font-family: var(--font-sans);">
          Air Quality Pollutants
        </h2>
      </div>
    </div>

    <!-- 5 Criteria Pollutant Cards Responsive Grid -->
    <div class="pollutants-grid-responsive grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5 sm:gap-4">
      
      <!-- 1. PM2.5 -->
      <div id="card-box-pm25" class="pollutant-card flex flex-col justify-between gap-2.5 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 cursor-pointer shadow-[0_4px_16px_rgba(15,23,42,0.04)] hover:-translate-y-1 transition-all duration-200 relative overflow-hidden" style="border-left: 5px solid <?= $init_border_colors['pm25'] ?>;" onclick="openPollutantModal('pm25')">
        <div class="pollutant-card-top flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <div class="pollutant-icon-box w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-sky-50 text-sky-500 flex items-center justify-center text-sm sm:text-lg shrink-0">🌫️</div>
            <div class="pollutant-title-box">
              <div class="pollutant-name text-[13px] sm:text-[15px] font-bold font-sans text-slate-900 leading-tight">PM₂.₅</div>
              <div class="pollutant-who text-[9px] sm:text-[10.5px] text-slate-500 mt-[2px] whitespace-nowrap">CPCB: ≤60</div>
            </div>
          </div>
          <div class="pollutant-info-icon w-6 h-6 rounded-full bg-slate-50 border border-slate-300 flex items-center justify-center text-slate-500 shrink-0">
            <i data-lucide="info" class="w-3 h-3"></i>
          </div>
        </div>
        <div class="pollutant-card-bottom flex items-end justify-between w-full mt-1">
          <div class="pollutant-val-wrap flex flex-col">
            <div id="card-pm25" class="font-mono text-lg sm:text-2xl font-extrabold text-sky-500 leading-none"><?= $init_pollutants['pm25'] ?></div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">µg/m³</div>
          </div>
          <div id="tag-pm25" class="pollutant-tag px-2 py-1 rounded-full text-[9px] sm:text-[11px] font-bold flex items-center gap-1" style="<?= $init_tags['pm25']['show'] ? '' : 'display: none;' ?> background-color: <?= $init_tags['pm25']['bg'] ?>; border: 1px solid <?= $init_tags['pm25']['border'] ?>; color: <?= $init_tags['pm25']['color'] ?>;">
            <span class="w-1.5 h-1.5 rounded-full" style="background-color: <?= $init_tags['pm25']['dot'] ?>;"></span>
            <span><?= $init_tags['pm25']['text'] ?></span>
          </div>
        </div>
        <div class="pollutant-chevron text-slate-400 shrink-0">
          <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        </div>
      </div>

      <!-- 2. PM10 -->
      <div id="card-box-pm10" class="pollutant-card flex flex-col justify-between gap-2.5 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 cursor-pointer shadow-[0_4px_16px_rgba(15,23,42,0.04)] hover:-translate-y-1 transition-all duration-200 relative overflow-hidden" style="border-left: 5px solid <?= $init_border_colors['pm10'] ?>;" onclick="openPollutantModal('pm10')">
        <div class="pollutant-card-top flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <div class="pollutant-icon-box w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-indigo-50 text-indigo-500 flex items-center justify-center text-sm sm:text-lg shrink-0">☁️</div>
            <div class="pollutant-title-box">
              <div class="pollutant-name text-[13px] sm:text-[15px] font-bold font-sans text-slate-900 leading-tight">PM₁₀</div>
              <div class="pollutant-who text-[9px] sm:text-[10.5px] text-slate-500 mt-[2px] whitespace-nowrap">CPCB: ≤100</div>
            </div>
          </div>
          <div class="pollutant-info-icon w-6 h-6 rounded-full bg-slate-50 border border-slate-300 flex items-center justify-center text-slate-500 shrink-0">
            <i data-lucide="info" class="w-3 h-3"></i>
          </div>
        </div>
        <div class="pollutant-card-bottom flex items-end justify-between w-full mt-1">
          <div class="pollutant-val-wrap flex flex-col">
            <div id="card-pm10" class="font-mono text-lg sm:text-2xl font-extrabold text-indigo-500 leading-none"><?= $init_pollutants['pm10'] ?></div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">µg/m³</div>
          </div>
          <div id="tag-pm10" class="pollutant-tag px-2 py-1 rounded-full text-[9px] sm:text-[11px] font-bold flex items-center gap-1" style="<?= $init_tags['pm10']['show'] ? '' : 'display: none;' ?> background-color: <?= $init_tags['pm10']['bg'] ?>; border: 1px solid <?= $init_tags['pm10']['border'] ?>; color: <?= $init_tags['pm10']['color'] ?>;">
            <span class="w-1.5 h-1.5 rounded-full" style="background-color: <?= $init_tags['pm10']['dot'] ?>;"></span>
            <span><?= $init_tags['pm10']['text'] ?></span>
          </div>
        </div>
        <div class="pollutant-chevron text-slate-400 shrink-0">
          <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        </div>
      </div>

      <!-- 3. CO -->
      <div id="card-box-co" class="pollutant-card flex flex-col justify-between gap-2.5 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 cursor-pointer shadow-[0_4px_16px_rgba(15,23,42,0.04)] hover:-translate-y-1 transition-all duration-200 relative overflow-hidden" style="border-left: 5px solid <?= $init_border_colors['co'] ?>;" onclick="openPollutantModal('co')">
        <div class="pollutant-card-top flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <div class="pollutant-icon-box w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-slate-50 text-slate-600 flex items-center justify-center text-sm sm:text-lg shrink-0">💨</div>
            <div class="pollutant-title-box">
              <div class="pollutant-name text-[13px] sm:text-[15px] font-bold font-sans text-slate-900 leading-tight">CO</div>
              <div class="pollutant-who text-[9px] sm:text-[10.5px] text-slate-500 mt-[2px] whitespace-nowrap">CPCB: ≤2</div>
            </div>
          </div>
          <div class="pollutant-info-icon w-6 h-6 rounded-full bg-slate-50 border border-slate-300 flex items-center justify-center text-slate-500 shrink-0">
            <i data-lucide="info" class="w-3 h-3"></i>
          </div>
        </div>
        <div class="pollutant-card-bottom flex items-end justify-between w-full mt-1">
          <div class="pollutant-val-wrap flex flex-col">
            <div id="card-co" class="font-mono text-lg sm:text-2xl font-extrabold text-slate-600 leading-none"><?= $init_pollutants['co'] ?></div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">mg/m³</div>
          </div>
          <div id="tag-co" class="pollutant-tag px-2 py-1 rounded-full text-[9px] sm:text-[11px] font-bold flex items-center gap-1" style="<?= $init_tags['co']['show'] ? '' : 'display: none;' ?> background-color: <?= $init_tags['co']['bg'] ?>; border: 1px solid <?= $init_tags['co']['border'] ?>; color: <?= $init_tags['co']['color'] ?>;">
            <span class="w-1.5 h-1.5 rounded-full" style="background-color: <?= $init_tags['co']['dot'] ?>;"></span>
            <span><?= $init_tags['co']['text'] ?></span>
          </div>
        </div>
        <div class="pollutant-chevron text-slate-400 shrink-0">
          <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        </div>
      </div>

      <!-- 4. NO2 -->
      <div id="card-box-no2" class="pollutant-card flex flex-col justify-between gap-2.5 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 cursor-pointer shadow-[0_4px_16px_rgba(15,23,42,0.04)] hover:-translate-y-1 transition-all duration-200 relative overflow-hidden" style="border-left: 5px solid <?= $init_border_colors['no2'] ?>;" onclick="openPollutantModal('no2')">
        <div class="pollutant-card-top flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <div class="pollutant-icon-box w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-purple-50 text-purple-500 flex items-center justify-center text-sm sm:text-lg shrink-0">🏭</div>
            <div class="pollutant-title-box">
              <div class="pollutant-name text-[13px] sm:text-[15px] font-bold font-sans text-slate-900 leading-tight">NO₂</div>
              <div class="pollutant-who text-[9px] sm:text-[10.5px] text-slate-500 mt-[2px] whitespace-nowrap">CPCB: ≤80</div>
            </div>
          </div>
          <div class="pollutant-info-icon w-6 h-6 rounded-full bg-slate-50 border border-slate-300 flex items-center justify-center text-slate-500 shrink-0">
            <i data-lucide="info" class="w-3 h-3"></i>
          </div>
        </div>
        <div class="pollutant-card-bottom flex items-end justify-between w-full mt-1">
          <div class="pollutant-val-wrap flex flex-col">
            <div id="card-no2" class="font-mono text-lg sm:text-2xl font-extrabold text-purple-500 leading-none"><?= $init_pollutants['no2'] ?></div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">µg/m³</div>
          </div>
          <div id="tag-no2" class="pollutant-tag px-2 py-1 rounded-full text-[9px] sm:text-[11px] font-bold flex items-center gap-1" style="<?= $init_tags['no2']['show'] ? '' : 'display: none;' ?> background-color: <?= $init_tags['no2']['bg'] ?>; border: 1px solid <?= $init_tags['no2']['border'] ?>; color: <?= $init_tags['no2']['color'] ?>;">
            <span class="w-1.5 h-1.5 rounded-full" style="background-color: <?= $init_tags['no2']['dot'] ?>;"></span>
            <span><?= $init_tags['no2']['text'] ?></span>
          </div>
        </div>
        <div class="pollutant-chevron text-slate-400 shrink-0">
          <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        </div>
      </div>

      <!-- 5. O3 -->
      <div id="card-box-o3" class="pollutant-card flex flex-col justify-between gap-2.5 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 cursor-pointer shadow-[0_4px_16px_rgba(15,23,42,0.04)] hover:-translate-y-1 transition-all duration-200 relative overflow-hidden" style="border-left: 5px solid <?= $init_border_colors['o3'] ?>;" onclick="openPollutantModal('o3')">
        <div class="pollutant-card-top flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <div class="pollutant-icon-box w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center text-sm sm:text-lg shrink-0">☀️</div>
            <div class="pollutant-title-box">
              <div class="pollutant-name text-[13px] sm:text-[15px] font-bold font-sans text-slate-900 leading-tight">O₃</div>
              <div class="pollutant-who text-[9px] sm:text-[10.5px] text-slate-500 mt-[2px] whitespace-nowrap">CPCB: ≤100</div>
            </div>
          </div>
          <div class="pollutant-info-icon w-6 h-6 rounded-full bg-slate-50 border border-slate-300 flex items-center justify-center text-slate-500 shrink-0">
            <i data-lucide="info" class="w-3 h-3"></i>
          </div>
        </div>
        <div class="pollutant-card-bottom flex items-end justify-between w-full mt-1">
          <div class="pollutant-val-wrap flex flex-col">
            <div id="card-o3" class="font-mono text-lg sm:text-2xl font-extrabold text-amber-500 leading-none"><?= $init_pollutants['o3'] ?></div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">µg/m³</div>
          </div>
          <div id="tag-o3" class="pollutant-tag px-2 py-1 rounded-full text-[9px] sm:text-[11px] font-bold flex items-center gap-1" style="<?= $init_tags['o3']['show'] ? '' : 'display: none;' ?> background-color: <?= $init_tags['o3']['bg'] ?>; border: 1px solid <?= $init_tags['o3']['border'] ?>; color: <?= $init_tags['o3']['color'] ?>;">
            <span class="w-1.5 h-1.5 rounded-full" style="background-color: <?= $init_tags['o3']['dot'] ?>;"></span>
            <span><?= $init_tags['o3']['text'] ?></span>
          </div>
        </div>
        <div class="pollutant-chevron text-slate-400 shrink-0">
          <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        </div>
      </div>

    </div>
  </div>

  <!-- AIR QUALITY VISUALIZATIONS -->
  <div style="display: flex; flex-direction: column; gap: 20px; margin-top: 8px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
      <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; font-family: var(--font-sans);">
        <i data-lucide="trending-up" style="width: 20px; height: 20px; color: #00bfa5;"></i>
        Air Quality Visualizations
      </h2>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr)); gap: 20px;">
      <!-- AQI Parameters Comparison Lineplot Card -->
      <div class="comparison-chart-card" style="background-color: #ffffff; border-radius: 20px; padding: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05); display: flex; flex-direction: column; gap: 16px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
          <div>
            <div style="font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px;">
              <i data-lucide="layers" style="width: 16px; height: 16px; color: #0284c7;"></i>
              AQI Parameters Comparison
            </div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">
              All AQI pollutants on a single graph (excl. temp, humidity, wind & rain)
            </div>
          </div>
        </div>

        <!-- Pollutant Filter Chips (Sleek Compact Design) -->
        <div style="display: flex; gap: 6px; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 4px; scrollbar-width: none;" class="sm:flex-wrap" id="pollutant-filters">
          <button onclick="togglePollutant('pm25')" id="filter-pm25" data-active="true" data-color="#0284c7" style="min-height: 28px; padding: 3px 10px; border-radius: 999px; border: 1.2px solid #0284c7; background-color: rgba(2, 132, 199, 0.12); color: #0284c7; font-size: 11.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: all 0.15s ease; white-space: nowrap; flex-shrink: 0;">
            <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #0284c7;"></span> PM2.5
          </button>
          <button onclick="togglePollutant('pm10')" id="filter-pm10" data-active="true" data-color="#6366f1" style="min-height: 28px; padding: 3px 10px; border-radius: 999px; border: 1.2px solid #6366f1; background-color: rgba(99, 102, 241, 0.12); color: #6366f1; font-size: 11.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: all 0.15s ease; white-space: nowrap; flex-shrink: 0;">
            <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #6366f1;"></span> PM10
          </button>
          <button onclick="togglePollutant('co')" id="filter-co" data-active="true" data-color="#64748b" style="min-height: 28px; padding: 3px 10px; border-radius: 999px; border: 1.2px solid #64748b; background-color: rgba(100, 116, 139, 0.12); color: #64748b; font-size: 11.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: all 0.15s ease; white-space: nowrap; flex-shrink: 0;">
            <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #64748b;"></span> CO
          </button>
          <button onclick="togglePollutant('no2')" id="filter-no2" data-active="true" data-color="#9333ea" style="min-height: 28px; padding: 3px 10px; border-radius: 999px; border: 1.2px solid #9333ea; background-color: rgba(147, 51, 234, 0.12); color: #9333ea; font-size: 11.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: all 0.15s ease; white-space: nowrap; flex-shrink: 0;">
            <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #9333ea;"></span> NO₂
          </button>
          <button onclick="togglePollutant('o3')" id="filter-o3" data-active="true" data-color="#d97706" style="min-height: 28px; padding: 3px 10px; border-radius: 999px; border: 1.2px solid #d97706; background-color: rgba(217, 119, 6, 0.12); color: #d97706; font-size: 11.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: all 0.15s ease; white-space: nowrap; flex-shrink: 0;">
            <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #d97706;"></span> O₃
          </button>
        </div>

        <div style="width: 100%; height: 250px; position: relative;">
          <canvas id="comparison-chart"></canvas>
          <div id="comparison-chart-empty" style="position: absolute; inset: 0; display: <?= (!empty($init_bulk['aqi_history'])) ? 'none' : 'flex' ?>; align-items: center; justify-content: center; background: rgba(255,255,255,0.8); color: #64748b; font-size: 13px;">
            <?= (!empty($init_bulk['aqi_history'])) ? '' : ($is_node_2 ? 'Currently in standby mode. Chart active when live stream begins.' : 'Loading comparison graph...') ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- NETWORK READINGS & STATUS SECTION (AQI Stream) -->
  <div style="margin-top: 14px;">
    <div style="margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
      <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; font-family: var(--font-sans);">
        <i data-lucide="table" style="width: 18px; height: 18px; color: #00bfa5;"></i>
        <span>Air Quality Telemetry Records</span>
      </h2>
    </div>

    <!-- AQI Telemetry Table Container -->
    <div id="live-aqi-table-container" style="background-color: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05); transition: opacity 0.2s ease;">
      <!-- Mobile swipe indicator -->
      <div class="block sm:hidden px-4 py-2 bg-slate-50 border-b border-slate-100 text-[11px] text-slate-500 font-medium flex items-center justify-between">
        <span>👈 Swipe horizontally to view all air quality parameters</span>
        <span class="font-mono text-[10px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded font-bold">AQI STREAM</span>
      </div>

      <div style="overflow-x: auto; max-height: 420px; -webkit-overflow-scrolling: touch;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; font-family: var(--font-sans); min-width: 800px;">
          <thead style="position: sticky; top: 0; z-index: 1; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
            <tr>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Timestamp (IST)</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">AQI</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">PM2.5 (µg/m³)</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">PM10 (µg/m³)</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">CO (mg/m³)</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">NO₂ (µg/m³)</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">O₃ (µg/m³)</th>
            </tr>
          </thead>
          <tbody id="live-table-body">
            <?php if (!empty($init_bulk['aqi_history'])): ?>
              <?php foreach ($init_bulk['aqi_history'] as $idx => $row): ?>
                <?php
                  $raw_ts = $row['timestamp'] ?? ($row['created_at'] ?? '');
                  $dt = $raw_ts ? new DateTime($raw_ts) : new DateTime();
                  $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                  $time_disp = $dt->format('d M, h:i:s A');
                  $aqi_disp = isset($row['cpcb_aqi']) ? round($row['cpcb_aqi']) : '--';
                  $pm25_disp = isset($row['pm25']) && $row['pm25'] !== null ? number_format((float)$row['pm25'], 1) : (isset($row['pm2.5']) && $row['pm2.5'] !== null ? number_format((float)$row['pm2.5'], 1) : '--');
                  $pm10_disp = isset($row['pm10']) && $row['pm10'] !== null ? number_format((float)$row['pm10'], 1) : '--';
                  $co_disp = isset($row['co']) && $row['co'] !== null ? number_format((float)$row['co'], 2) : '--';
                  $no2_disp = isset($row['no2']) && $row['no2'] !== null ? number_format((float)$row['no2'], 1) : '--';
                  $o3_disp = isset($row['o3']) && $row['o3'] !== null ? number_format((float)$row['o3'], 1) : '--';
                  $bg_row = ($idx % 2 === 0) ? '#ffffff' : '#f8fafc';
                ?>
                <tr style="border-bottom: 1px solid #f1f5f9; background-color: <?= $bg_row ?>;">
                  <td style="padding: 11px 16px; font-family: var(--font-mono); font-size: 12px; color: #1e293b; white-space: nowrap; font-weight: 600;"><?= htmlspecialchars($time_disp) ?></td>
                  <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 800; color: #0f172a; font-size: 14px;"><?= htmlspecialchars($aqi_disp) ?></td>
                  <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #334155;"><?= htmlspecialchars($pm25_disp) ?></td>
                  <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #334155;"><?= htmlspecialchars($pm10_disp) ?></td>
                  <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: <?= ($co_disp === '--' ? '#94a3b8' : '#334155') ?>;"><?= htmlspecialchars($co_disp) ?></td>
                  <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #334155;"><?= htmlspecialchars($no2_disp) ?></td>
                  <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #334155;"><?= htmlspecialchars($o3_disp) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="7" style="padding: 40px; text-align: center; color: #64748b;">
                  <?= $is_node_2 ? 'Currently in standby mode. No live telemetry recorded yet.' : 'Loading stream...' ?>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Air Quality Index (AQI) Scale Card -->
  <div style="background-color: #ffffff; border-radius: 20px; padding: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05); display: flex; flex-direction: column; gap: 16px; margin-top: 14px;">
    <div>
      <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; font-family: var(--font-sans);">
        <i data-lucide="shield-alert" style="width: 20px; height: 20px; color: #00bfa5;"></i>
        Air Quality Index (AQI) Scale
      </h2>
      <p style="font-size: 12.5px; color: #64748b; margin-top: 4px; margin-bottom: 0;">
        Know what each category of the Air Quality Index implies for health and ambient safety.
      </p>
    </div>

    <div style="display: flex; flex-direction: column; gap: 10px;">
      <!-- Scale Items -->
      <div class="aqi-scale-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #f0fdf4; border: 1px solid #bbf7d0; gap: 12px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <span class="scale-dot" style="width: 12px; height: 12px; border-radius: 4px; background-color: #59b61f; flex-shrink: 0;"></span>
          <div>
            <div style="display: flex; align-items: center; gap: 8px;">
              <span class="scale-title" style="font-size: 14px; font-weight: 700; color: #0f172a;">Good</span>
              <span class="scale-range" style="font-size: 12px; font-weight: 700; color: #59b61f; font-family: var(--font-mono);">(0 to 50)</span>
            </div>
            <div class="scale-desc" style="font-size: 11.5px; color: #475569; margin-top: 2px;">Air quality is considered satisfactory, and air pollution poses little or no risk.</div>
          </div>
        </div>
      </div>
      
      <div class="aqi-scale-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #fefce8; border: 1px solid #fef08a; gap: 12px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <span class="scale-dot" style="width: 12px; height: 12px; border-radius: 4px; background-color: #eec732; flex-shrink: 0;"></span>
          <div>
            <div style="display: flex; align-items: center; gap: 8px;">
              <span class="scale-title" style="font-size: 14px; font-weight: 700; color: #0f172a;">Moderate</span>
              <span class="scale-range" style="font-size: 12px; font-weight: 700; color: #854d0e; font-family: var(--font-mono);">(51 to 100)</span>
            </div>
            <div class="scale-desc" style="font-size: 11.5px; color: #475569; margin-top: 2px;">Acceptable air quality; minor breathing discomfort may occur for sensitive individuals.</div>
          </div>
        </div>
      </div>

      <div class="aqi-scale-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #fff7ed; border: 1px solid #fed7aa; gap: 12px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <span class="scale-dot" style="width: 12px; height: 12px; border-radius: 4px; background-color: #ea8c34; flex-shrink: 0;"></span>
          <div>
            <div style="display: flex; align-items: center; gap: 8px;">
              <span class="scale-title" style="font-size: 14px; font-weight: 700; color: #0f172a;">Poor</span>
              <span class="scale-range" style="font-size: 12px; font-weight: 700; color: #ea8c34; font-family: var(--font-mono);">(101 to 200)</span>
            </div>
            <div class="scale-desc" style="font-size: 11.5px; color: #475569; margin-top: 2px;">Breathing discomfort to people with lungs, asthma, and heart diseases.</div>
          </div>
        </div>
      </div>

      <div class="aqi-scale-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #fef2f2; border: 1px solid #fecaca; gap: 12px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <span class="scale-dot" style="width: 12px; height: 12px; border-radius: 4px; background-color: #e95478; flex-shrink: 0;"></span>
          <div>
            <div style="display: flex; align-items: center; gap: 8px;">
              <span class="scale-title" style="font-size: 14px; font-weight: 700; color: #0f172a;">Unhealthy</span>
              <span class="scale-range" style="font-size: 12px; font-weight: 700; color: #e95478; font-family: var(--font-mono);">(201 to 300)</span>
            </div>
            <div class="scale-desc" style="font-size: 11.5px; color: #475569; margin-top: 2px;">Breathing discomfort to most people on prolonged exposure. Limit strenuous outdoor exertion.</div>
          </div>
        </div>
      </div>

      <div class="aqi-scale-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #faf5ff; border: 1px solid #e9d5ff; gap: 12px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <span class="scale-dot" style="width: 12px; height: 12px; border-radius: 4px; background-color: #b33fba; flex-shrink: 0;"></span>
          <div>
            <div style="display: flex; align-items: center; gap: 8px;">
              <span class="scale-title" style="font-size: 14px; font-weight: 700; color: #0f172a;">Severe</span>
              <span class="scale-range" style="font-size: 12px; font-weight: 700; color: #b33fba; font-family: var(--font-mono);">(301 to 400)</span>
            </div>
            <div class="scale-desc" style="font-size: 11.5px; color: #475569; margin-top: 2px;">Respiratory illness on prolonged exposure; significantly impacts people with existing ailments.</div>
          </div>
        </div>
      </div>

      <div class="aqi-scale-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #fef2f2; border: 1px solid #fecaca; gap: 12px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <span class="scale-dot" style="width: 12px; height: 12px; border-radius: 4px; background-color: #c92033; flex-shrink: 0;"></span>
          <div>
            <div style="display: flex; align-items: center; gap: 8px;">
              <span class="scale-title" style="font-size: 14px; font-weight: 700; color: #0f172a;">Hazardous</span>
              <span class="scale-range" style="font-size: 12px; font-weight: 700; color: #c92033; font-family: var(--font-mono);">(401 to 500+)</span>
            </div>
            <div class="scale-desc" style="font-size: 11.5px; color: #475569; margin-top: 2px;">Emergency conditions. The entire population is likely to be affected. Serious risk of respiratory effects.</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  </div> <!-- End #node2-view-aqi -->

  <?php if ($is_node_2): ?>
  <!-- ═══════════════════════════════════════════════════════════════
       NODE 2: DEDICATED LIVE WEATHER VIEW (STANDBY MODE)
       ═══════════════════════════════════════════════════════════════ -->
  <div id="node2-view-weather" style="<?= ($active_live_subtab === 'weather') ? 'display: flex;' : 'display: none;' ?> width: 100%; max-width: 100%; min-width: 0; flex-direction: column; gap: 14px; box-sizing: border-box;">
    
    <!-- Page Header Row (matching Node 1 weather header layout) -->
    <div style="display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-top: 6px; margin-bottom: 12px; width: 100%;">
      <div style="display: flex; flex-direction: column; gap: 6px;">
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
          <h1 class="page-heading live-page-title" style="font-size: clamp(20px, 2.5vw, 24px); font-weight: 800; color: #ffffff; text-shadow: 0 2px 10px rgba(15, 23, 42, 0.6); font-family: var(--font-sans); letter-spacing: -0.02em; margin: 0;">
            Live Weather Conditions
          </h1>
        </div>
      </div>

      <div style="font-size: 12px; color: #ffffff; text-shadow: 0 1px 4px rgba(0,0,0,0.5); font-family: var(--font-mono); display: flex; align-items: center; gap: 8px;">
        <span>Last Updated: <strong id="weather-latest-time" style="color: #ffffff; font-weight: 700; text-shadow: 0 1px 4px rgba(0,0,0,0.6);"><?= htmlspecialchars($init_time_str) ?></strong></span>
        <span id="weather-live-badge" style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; background-color: rgba(255, 255, 255, 0.92); color: #15803d; border: 1px solid rgba(255, 255, 255, 0.7); box-shadow: 0 2px 8px rgba(15, 23, 42, 0.12); backdrop-filter: blur(10px);">
          <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #22c55e;"></span>
          <span>LIVE</span>
        </span>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         ANIMATED WEATHER DASHBOARD (Matches Reference Designs 1 & 2)
         ═══════════════════════════════════════════════════════════════ -->
    <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 10px;">

      <!-- ROW 1: Direction & Wind Speed (Wide Card) + Gust Speed Card -->
      <div class="weather-row-top" style="display: grid; grid-template-columns: 1.85fr 1fr; gap: 16px;">

        <!-- Card 1: Combined Direction & Wind Speed -->
        <div class="weather-card-dark weather-card-wind" style="display: flex; align-items: center; justify-content: space-between; gap: 20px;">
          <!-- Left: Compass Widget -->
          <div class="compass-widget-wrap" style="position: relative; width: 84px; height: 84px; flex-shrink: 0;">
            <svg viewBox="0 0 100 100" style="width: 100%; height: 100%;">
              <!-- Outer circle dashed ticks -->
              <circle cx="50" cy="50" r="34" fill="none" stroke="rgba(255,255,255,0.25)" stroke-width="1.6" stroke-dasharray="2 3.5" />
              <!-- 4 Cardinal ticks crossing the ring -->
              <line x1="50" y1="29" x2="50" y2="38" stroke="rgba(255,255,255,0.7)" stroke-width="1.8" stroke-linecap="round" />
              <line x1="50" y1="62" x2="50" y2="71" stroke="rgba(255,255,255,0.7)" stroke-width="1.8" stroke-linecap="round" />
              <line x1="29" y1="50" x2="38" y2="50" stroke="rgba(255,255,255,0.7)" stroke-width="1.8" stroke-linecap="round" />
              <line x1="62" y1="50" x2="71" y2="50" stroke="rgba(255,255,255,0.7)" stroke-width="1.8" stroke-linecap="round" />
              <!-- Cardinal text N, E, S, W -->
              <text x="50" y="17" font-size="8.5" font-weight="700" fill="#cbd5e1" text-anchor="middle" dominant-baseline="central">N</text>
              <text x="83" y="50" font-size="8.5" font-weight="700" fill="#cbd5e1" text-anchor="middle" dominant-baseline="central">E</text>
              <text x="50" y="83" font-size="8.5" font-weight="700" fill="#cbd5e1" text-anchor="middle" dominant-baseline="central">S</text>
              <text x="17" y="50" font-size="8.5" font-weight="700" fill="#cbd5e1" text-anchor="middle" dominant-baseline="central">W</text>
              <!-- Center circle disc -->
              <circle cx="50" cy="50" r="18.5" fill="#607282" />
              <text id="compass-cardinal-label" x="50" y="50.5" font-size="12" font-weight="700" fill="#ffffff" text-anchor="middle" dominant-baseline="central" style="letter-spacing: -0.01em; font-family: var(--font-sans); text-transform: lowercase;"><?= htmlspecialchars(strtolower($init_weather_cardinal)) ?></text>
              <!-- Rotating blue pointer arrow sitting on track between center disc and outer ticks -->
              <g id="compass-needle-group" class="<?= $has_wind_dir ? '' : 'compass-idle-scan' ?>" style="transform-origin: 50px 50px; transform: rotate(<?= $compass_angle ?>deg); transition: transform 1.2s cubic-bezier(0.34, 1.56, 0.64, 1);">
                <polygon points="50,18 45.8,29.5 54.2,29.5" fill="#38bdf8" stroke="#38bdf8" stroke-width="0.8" stroke-linejoin="round" filter="drop-shadow(0 0 4px rgba(56, 189, 248, 0.9))" />
              </g>
            </svg>
          </div>

          <!-- Metrics Container: Row on desktop, vertical stacked column on mobile -->
          <div class="weather-wind-metrics-group" style="display: flex; align-items: center; justify-content: space-between; flex: 1; gap: 18px; min-width: 0;">
            <!-- Direction Text Block -->
            <div class="weather-metric-block weather-dir-block">
              <div id="card-dir-val" class="weather-metric-large" style="font-family: var(--font-sans); font-size: 28px; font-weight: 800; color: #ffffff; line-height: 1.1; letter-spacing: -0.02em;">
                <?= htmlspecialchars($init_weather_dir_deg) ?>
              </div>
              <div class="weather-metric-label" style="font-size: 13px; font-weight: 500; color: #cbd5e1; margin-top: 4px;">
                Direction
              </div>
            </div>

            <!-- Divider (Desktop Only) -->
            <div class="weather-wind-divider" style="width: 1px; height: 55px; background: rgba(255,255,255,0.14);"></div>

            <!-- Animated Wind Turbine SVG (Desktop Only) -->
            <div class="wind-turbine-wrap" style="width: 50px; height: 75px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
              <svg viewBox="0 0 60 90" style="width: 100%; height: 100%; overflow: visible;">
                <!-- Mast -->
                <path d="M28 85 L29 36 L31 36 L32 85 Z" fill="#94a3b8" opacity="0.8" />
                <path d="M24 85 L36 85" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round" />
                <!-- Nacelle -->
                <circle cx="30" cy="35" r="4" fill="#e2e8f0" />
                <!-- Rotating 3-blade rotor -->
                <g class="wind-turbine-blades" style="transform-origin: 30px 35px;">
                  <!-- Blade 1 -->
                  <path d="M30 35 C28.5 24 28.5 10 30 4 C31.5 10 31.5 24 30 35" fill="#f8fafc" />
                  <!-- Blade 2 -->
                  <path d="M30 35 C39 40 50 47 57 51 C50 54 39 48 30 35" fill="#f8fafc" />
                  <!-- Blade 3 -->
                  <path d="M30 35 C21 40 10 47 3 51 C10 54 21 48 30 35" fill="#f8fafc" />
                  <!-- Center green pivot dot -->
                  <circle cx="30" cy="35" r="2.8" fill="#22c55e" />
                </g>
              </svg>
            </div>

            <!-- Wind Speed Text Block -->
            <div class="weather-metric-block weather-speed-block">
              <div id="card-wind-val" class="weather-metric-large" style="font-family: var(--font-sans); font-size: 28px; font-weight: 800; color: #ffffff; line-height: 1.1; letter-spacing: -0.02em;">
                <?= $init_weather_wind ?>
              </div>
              <div class="weather-metric-label" style="font-size: 13px; font-weight: 500; color: #cbd5e1; margin-top: 4px;">
                Wind Speed
              </div>
            </div>
          </div>
        </div>

        <!-- Card 2: Gust Speed with Animated Swaying Tree in Wind (Matches Reference Design) -->
        <div class="weather-card-dark weather-card-gust" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 140px;">
          <!-- Graphic: Wind stream dashes & snowflakes + Swaying Autumn Tree with Flying Leaves -->
          <div class="weather-gust-graphic-wrap" style="display: flex; align-items: center; justify-content: center; height: 75px; width: 100%;">
            <svg viewBox="0 0 100 80" style="width: 100%; height: 100%; max-width: 140px; overflow: visible;">
              <defs>
                <!-- Gradient for main rotating bands: White highlight to Cyan glow to Slate outline -->
                <linearGradient id="bandGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                  <stop offset="0%" stop-color="#38bdf8" />
                  <stop offset="25%" stop-color="#ffffff" />
                  <stop offset="60%" stop-color="#e0f2fe" />
                  <stop offset="85%" stop-color="#0284c7" />
                  <stop offset="100%" stop-color="#38bdf8" />
                </linearGradient>
              </defs>

              <!-- ══ Animated Funnel Body ══ -->
              <g class="twister-container">
                <!-- Translucent cone volume giving subtle 3D vortex depth without overhang -->
                <path d="M 22 15 Q 36 45 46 67 Q 50 74 51 67 Q 62 45 78 15 Q 50 11 22 15 Z" fill="rgba(56, 189, 248, 0.08)" />

                <!-- ── Top Rim (Tilted Elliptical Mouth) ── -->
                <g transform="rotate(-8 50 14)">
                  <!-- Outer Lip -->
                  <ellipse class="vortex-band band-1" cx="50" cy="14" rx="30" ry="8" fill="none" stroke="url(#bandGrad)" stroke-width="3" stroke-linecap="round" />
                  <!-- Inner Rim -->
                  <ellipse cx="50" cy="14" rx="21" ry="5.5" fill="none" stroke="#ffffff" stroke-width="1.6" opacity="0.85" />
                </g>

                <!-- ── Tier 2 Band (Upper Funnel Ring) ── -->
                <g transform="rotate(-5 50 24)">
                  <ellipse class="vortex-band band-2" cx="49" cy="24" rx="25" ry="6.8" fill="none" stroke="url(#bandGrad)" stroke-width="3" stroke-linecap="round" />
                  <path d="M 28 25 Q 49 20 70 25" stroke="#ffffff" stroke-width="1.8" fill="none" stroke-linecap="round" opacity="0.9" />
                </g>

                <!-- ── Tier 3 Band (Mid-Upper Ring) ── -->
                <g transform="rotate(-2 49 35)">
                  <ellipse class="vortex-band band-3" cx="49" cy="35" rx="20" ry="5.5" fill="none" stroke="url(#bandGrad)" stroke-width="2.8" stroke-linecap="round" />
                  <path d="M 32 36 Q 49 32 66 36" stroke="#ffffff" stroke-width="1.6" fill="none" stroke-linecap="round" opacity="0.9" />
                </g>

                <!-- ── Tier 4 Band (Mid-Lower Ring) ── -->
                <g transform="rotate(2 49 46)">
                  <ellipse class="vortex-band band-4" cx="49" cy="46" rx="15" ry="4.2" fill="none" stroke="url(#bandGrad)" stroke-width="2.6" stroke-linecap="round" />
                  <path d="M 37 47 Q 49 43 61 47" stroke="#ffffff" stroke-width="1.5" fill="none" stroke-linecap="round" opacity="0.9" />
                </g>

                <!-- ── Tier 5 Band (Lower Ring) ── -->
                <g transform="rotate(5 50 56)">
                  <ellipse class="vortex-band band-5" cx="50" cy="56" rx="10.5" ry="3.2" fill="none" stroke="url(#bandGrad)" stroke-width="2.4" stroke-linecap="round" />
                  <path d="M 42 57 Q 50 54 58 57" stroke="#ffffff" stroke-width="1.4" fill="none" stroke-linecap="round" opacity="0.9" />
                </g>

                <!-- ── Tier 6 Band (Near-Tip Ring) ── -->
                <ellipse class="vortex-band band-6" cx="50" cy="65" rx="6.5" ry="2.2" fill="none" stroke="url(#bandGrad)" stroke-width="2" stroke-linecap="round" />

                <!-- ── Whipping Touchdown Tail Tip (Exact match to reference S-tip) ── -->
                <path d="M 50 67 
                         C 51 70, 48 72, 47 74 
                         C 46 76, 49 77, 51 77 
                         C 53 77, 54 79, 52 80" 
                      stroke="url(#bandGrad)" stroke-width="2.2" stroke-linecap="round" fill="none" />
              </g>

              <!-- ══ Orbiting Wind Particles & Diamond Sparkles ══ -->
              <g transform="translate(50, 72)">
                <g class="deb-orbit-1">
                  <circle cx="0" cy="0" r="1.8" fill="#38bdf8" />
                </g>
              </g>
              <g transform="translate(50, 70)">
                <g class="deb-orbit-2">
                  <polygon points="0,-3.5 2.5,0 0,3.5 -2.5,0" fill="#7dd3fc" />
                </g>
              </g>
              <g transform="translate(50, 68)">
                <g class="deb-orbit-3">
                  <circle cx="0" cy="0" r="1.5" fill="#ffffff" />
                </g>
              </g>
              <g transform="translate(50, 66)">
                <g class="deb-orbit-4">
                  <polygon points="0,-3 2,0 0,3 -2,0" fill="#38bdf8" />
                </g>
              </g>

              </svg>
          </div>

          <!-- Gust Speed Text -->
          <div style="text-align: center; margin-top: 6px;">
            <div class="weather-metric-label" style="font-size: 14px; font-weight: 500; color: #cbd5e1; margin-bottom: 3px;">
              Gust Speed
            </div>
            <div id="card-gust-val" class="weather-gust-val" style="font-family: var(--font-sans); font-size: 32px; font-weight: 800; color: #ffffff; line-height: 1.1; letter-spacing: -0.02em;">
              <?= $init_weather_gust ?>
            </div>
          </div>
        </div>

      </div>

      <!-- PARAMS GRID: 6 Cards (Precipitation, Pressure, UV Index, Soil Temp, Soil Humidity, Soil pH) -->
      <!-- Desktop: 2 rows of 3 cards | Mobile: 3 rows of 2 cards (Matches Reference) -->
      <div class="weather-grid-params">

        <!-- Card 3: Precipitation with Animated Left-Right Floating Rain Cloud (Matches Reference Design) -->
        <div class="weather-card-dark weather-card-precip" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 140px;">
          <!-- Top: Animated Floating Rain Cloud + Value -->
          <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px;">
            <!-- Cloud & Falling Rain SVG -->
            <div class="weather-precip-icon-wrap" style="width: 72px; height: 56px; flex-shrink: 0;">
              <svg viewBox="0 0 84 62" style="width: 100%; height: 100%; overflow: visible;">
                <!-- Tiny sparkle star dot -->
                <circle cx="16" cy="27" r="1.5" fill="#ffffff" opacity="0.85" />

                <!-- Glowing Sun puff behind cloud -->
                <g class="sun-puff-behind">
                  <circle cx="43" cy="11" r="2.2" fill="#f97316" />
                  <circle cx="44" cy="18" r="6" fill="#f97316" />
                  <circle cx="51" cy="20" r="5.5" fill="#fb923c" />
                  <circle cx="57" cy="23" r="4.5" fill="#f97316" />
                  <circle cx="50" cy="20" r="2.4" fill="#ffffff" />
                </g>

                <!-- Floating Rain Cloud (Smoothly moves left & right) -->
                <g class="floating-rain-cloud">
                  <!-- Cloud body -->
                  <path d="M 22 36 
                           C 18 36, 15 32, 16 28 
                           C 17 24, 21 22, 24 23 
                           C 26 18, 31 15, 36 16 
                           C 42 17, 45 20, 46 24 
                           C 50 23, 54 26, 54 30 
                           C 54 34, 51 36, 48 36 Z" 
                        fill="#ffffff" 
                        filter="drop-shadow(0 2px 5px rgba(0,0,0,0.22))" />

                  <!-- Falling Raindrops beneath cloud -->
                  <ellipse cx="24" cy="45" rx="1.6" ry="2.2" fill="#38bdf8" class="drop-1" />
                  <ellipse cx="32" cy="46" rx="1.6" ry="2.2" fill="#38bdf8" class="drop-2" />
                  <ellipse cx="40" cy="45" rx="1.6" ry="2.2" fill="#38bdf8" class="drop-3" />
                </g>
              </svg>
            </div>

            <!-- Text: Precipitation / 0 mm -->
            <div style="text-align: right;">
              <div class="weather-metric-label" style="font-size: 14px; font-weight: 500; color: #cbd5e1; margin-bottom: 3px;">
                Precipitation
              </div>
              <div id="card-rain-val" class="weather-card-number" style="font-family: var(--font-sans); font-size: 32px; font-weight: 800; color: #ffffff; line-height: 1.1; letter-spacing: -0.02em;">
                <?= $init_weather_rain ?>
              </div>
            </div>
          </div>

          <!-- Divider line -->
          <div style="height: 1px; width: 100%; background: rgba(255, 255, 255, 0.08); margin: 12px 0 10px 0;"></div>

          <!-- Bottom note -->
          <div class="weather-metric-note" style="font-size: 12px; color: #cbd5e1; line-height: 1.35; font-weight: 400;">
            Current precipitation chances sit at <span id="precipitation-note-val"><?= $init_weather_rain ?></span>
          </div>
        </div>

        <!-- Card 4: Pressure with Circular Gauge & Gradient Bar -->
        <div class="weather-card-dark weather-card-pressure" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 140px;">
          <!-- Top: Barometer Dial + Value -->
          <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px;">
            <!-- Circular Dial Gauge SVG (Precision Barometer Dial) -->
            <div class="weather-pressure-dial-wrap" style="width: 82px; height: 82px; flex-shrink: 0;">
              <svg viewBox="0 0 100 100" style="width: 100%; height: 100%; filter: drop-shadow(0 2px 8px rgba(0,0,0,0.25));">
                <!-- Barometer Dial Face Background -->
                <circle cx="50" cy="50" r="48" fill="#1e293b" fill-opacity="0.6" stroke="rgba(255,255,255,0.1)" stroke-width="1" />
                
                <!-- Minor Ticks (2 between each major tick mark) -->
                <g stroke="rgba(148, 163, 184, 0.45)" stroke-width="1.2" stroke-linecap="round">
                  <line x1="57.03" y1="10.12" x2="57.64" y2="6.67" />
                  <line x1="63.85" y1="11.94" x2="65.05" y2="8.65" />
                  <line x1="76.03" y1="18.98" x2="78.28" y2="16.29" />
                  <line x1="81.02" y1="23.97" x2="83.71" y2="21.72" />
                  <line x1="88.06" y1="36.15" x2="91.35" y2="34.95" />
                  <line x1="89.88" y1="42.97" x2="93.33" y2="42.36" />
                  <line x1="89.88" y1="57.03" x2="93.33" y2="57.64" />
                  <line x1="88.06" y1="63.85" x2="91.35" y2="65.05" />
                  <line x1="81.02" y1="76.03" x2="83.71" y2="78.28" />
                  <line x1="76.03" y1="81.02" x2="78.28" y2="83.71" />
                  <line x1="63.85" y1="88.06" x2="65.05" y2="91.35" />
                  <line x1="57.03" y1="89.88" x2="57.64" y2="93.33" />
                  <line x1="42.97" y1="89.88" x2="42.36" y2="93.33" />
                  <line x1="36.15" y1="88.06" x2="34.95" y2="91.35" />
                  <line x1="23.97" y1="81.02" x2="21.72" y2="83.71" />
                  <line x1="18.98" y1="76.03" x2="16.29" y2="78.28" />
                  <line x1="11.94" y1="63.85" x2="8.65" y2="65.05" />
                  <line x1="10.12" y1="57.03" x2="6.67" y2="57.64" />
                  <line x1="10.12" y1="42.97" x2="6.67" y2="42.36" />
                  <line x1="11.94" y1="36.15" x2="8.65" y2="34.95" />
                  <line x1="18.98" y1="23.97" x2="16.29" y2="21.72" />
                  <line x1="23.97" y1="18.98" x2="21.72" y2="16.29" />
                  <line x1="36.15" y1="11.94" x2="34.95" y2="8.65" />
                  <line x1="42.97" y1="10.12" x2="42.36" y2="6.67" />
                </g>

                <!-- Major Ticks (at every hour position) -->
                <g stroke="#ffffff" stroke-width="2.2" stroke-linecap="round">
                  <line x1="50.00" y1="12.00" x2="50.00" y2="5.50" />
                  <line x1="69.00" y1="17.09" x2="72.25" y2="11.46" />
                  <line x1="82.91" y1="31.00" x2="88.54" y2="27.75" />
                  <line x1="88.00" y1="50.00" x2="94.50" y2="50.00" />
                  <line x1="82.91" y1="69.00" x2="88.54" y2="72.25" />
                  <line x1="69.00" y1="82.91" x2="72.25" y2="88.54" />
                  <line x1="50.00" y1="88.00" x2="50.00" y2="94.50" />
                  <line x1="31.00" y1="82.91" x2="27.75" y2="88.54" />
                  <line x1="17.09" y1="69.00" x2="11.46" y2="72.25" />
                  <line x1="12.00" y1="50.00" x2="5.50" y2="50.00" />
                  <line x1="17.09" y1="31.00" x2="11.46" y2="27.75" />
                  <line x1="31.00" y1="17.09" x2="27.75" y2="11.46" />
                </g>

                <!-- Pressure Dial Inscribed Values (1008 to 1019 hPa) -->
                <g font-size="4.4" font-weight="700" fill="#cbd5e1" font-family="system-ui, -apple-system, sans-serif" text-anchor="middle" dominant-baseline="central">
                  <text x="50.00" y="22.00">1011</text>
                  <text x="64.00" y="25.75">1012</text>
                  <text x="74.25" y="36.00">1013</text>
                  <text x="78.00" y="50.00">1014</text>
                  <text x="74.25" y="64.00">1015</text>
                  <text x="64.00" y="74.25">1016</text>
                  <text x="50.00" y="78.00">1017</text>
                  <text x="36.00" y="74.25">1018</text>
                  <text x="25.75" y="64.00">1019</text>
                  <text x="22.00" y="50.00">1008</text>
                  <text x="25.75" y="36.00">1009</text>
                  <text x="36.00" y="25.75">1010</text>
                </g>

                <!-- Precision Barometer Needle (Pink Arrowhead Pointer) -->
                <g id="pressure-needle" class="<?= $has_pressure ? '' : 'pressure-needle-breathe' ?>" style="transform-origin: 50px 50px; transform: rotate(<?= $has_pressure ? $pressure_angle : 60 ?>deg); transition: transform 1.2s cubic-bezier(0.34, 1.56, 0.64, 1);">
                  <polygon points="50,22 53.5,47.5 50,52 46.5,47.5" fill="#ec4899" filter="drop-shadow(0 1px 3px rgba(236,72,153,0.5))" />
                  <circle cx="50" cy="50" r="2.5" fill="#db2777" />
                  <circle cx="50" cy="50" r="1.1" fill="#ffffff" />
                </g>
              </svg>
            </div>

            <!-- Text: Pressure -->
            <div style="text-align: right;">
              <div class="weather-metric-label" style="font-size: 13px; font-weight: 500; color: #cbd5e1; margin-bottom: 2px;">
                Pressure
              </div>
              <div id="card-pressure-val" class="weather-card-number" style="font-family: var(--font-sans); font-size: 28px; font-weight: 800; color: #ffffff; line-height: 1.1; letter-spacing: -0.02em;">
                <?= $init_weather_pressure ?>
              </div>
            </div>
          </div>

          <!-- Middle: Gradient track + indicator dot -->
          <div style="margin-top: 10px; width: 100%;">
            <div class="live-slider-track" style="height: 5px; width: 100%; border-radius: 999px; background: linear-gradient(90deg, #22c55e 0%, #eab308 35%, #ef4444 70%, #ec4899 100%); position: relative;">
              <div id="pressure-slider-dot" class="pressure-pulse-dot <?= $has_pressure ? '' : 'bar-dot-standby-scan' ?>" style="position: absolute; top: 50%; left: <?= $has_pressure ? $pressure_pct : 50 ?>%; transform: translate(-50%, -50%); width: 12px; height: 12px; border-radius: 50%; background: #ffffff; border: 2px solid #ec4899; box-shadow: 0 0 8px rgba(236,72,153,0.9); transition: left 1s ease-in-out;"></div>
            </div>
          </div>

          <!-- Bottom note -->
          <div class="weather-metric-note" style="margin-top: 10px; font-size: 12px; color: #cbd5e1; line-height: 1.35; font-weight: 400;">
            Current pressure level is <span id="pressure-note-val"><?= $init_weather_pressure ?></span>.
          </div>
        </div>

        <!-- Card 5: UV Index with Rose Gradient Accent & Bar Slider -->
        <div class="weather-card-dark weather-card-uv" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 140px;">
          <!-- Top: Label + Value -->
          <div>
            <div class="weather-metric-label" style="font-size: 13px; font-weight: 500; color: #cbd5e1; margin-bottom: 3px;">
              UV Index
            </div>
            <div id="card-uv-val" class="weather-card-number" style="font-family: var(--font-sans); font-size: 26px; font-weight: 800; color: #ffffff; line-height: 1.1; letter-spacing: -0.02em;">
              <?= $init_weather_uv_display ?>
            </div>
          </div>

          <!-- Middle: Gradient track + glowing indicator dot -->
          <div style="margin-top: 14px; width: 100%;">
            <div class="live-slider-track" style="height: 5px; width: 100%; border-radius: 999px; background: linear-gradient(90deg, #22c55e 0%, #eab308 25%, #f97316 50%, #ef4444 75%, #a855f7 100%); position: relative;">
              <div id="uv-slider-dot" class="uv-pulse-dot <?= $has_uv ? '' : 'bar-dot-standby-scan' ?>" style="position: absolute; top: 50%; left: <?= $has_uv ? $uv_pct : 8 ?>%; transform: translate(-50%, -50%); width: 12px; height: 12px; border-radius: 50%; background: #ffffff; border: 2px solid #f43f5e; box-shadow: 0 0 8px rgba(244,63,94,0.95); transition: left 1s ease-in-out;"></div>
            </div>
          </div>

          <!-- Bottom note -->
          <div class="weather-metric-note" style="margin-top: 10px; font-size: 12px; color: #cbd5e1; line-height: 1.35; font-weight: 400;">
            The present UV index is <span id="uv-note-val"><?= $init_weather_uv_num ?></span><?= $has_uv ? ', consider suggestions for the same!' : '' ?>
          </div>
        </div>

        <!-- Card 6: Ambient Temperature -->
        <div class="weather-card-dark weather-card-ambient-temp" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 140px;">
          <!-- Top: Thermometer SVG + Value -->
          <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px;">
            <div class="weather-soil-icon-wrap" style="width: 58px; height: 58px; flex-shrink: 0;">
              <svg viewBox="0 0 64 64" style="width: 100%; height: 100%;">
                <rect x="27" y="10" width="10" height="30" rx="5" fill="rgba(255,255,255,0.12)" stroke="rgba(255,255,255,0.6)" stroke-width="1.8" />
                <circle cx="32" cy="44" r="9" fill="#f97316" filter="drop-shadow(0 0 5px rgba(249, 115, 22, 0.6))" />
                <rect x="29" y="20" width="6" height="24" rx="3" fill="#f97316" />
                <line x1="39" y1="16" x2="43" y2="16" stroke="#ffffff" stroke-width="1.4" stroke-linecap="round" opacity="0.85" />
                <line x1="39" y1="23" x2="44" y2="23" stroke="#ffffff" stroke-width="1.4" stroke-linecap="round" opacity="0.85" />
                <line x1="39" y1="30" x2="43" y2="30" stroke="#ffffff" stroke-width="1.4" stroke-linecap="round" opacity="0.85" />
              </svg>
            </div>

            <!-- Value Text -->
            <div style="text-align: right;">
              <div class="weather-metric-label" style="font-size: 13px; font-weight: 500; color: #cbd5e1; margin-bottom: 2px;">
                Ambient Temperature
              </div>
              <div id="card-weather-temp-val" class="weather-card-number" style="font-family: var(--font-sans); font-size: 28px; font-weight: 800; color: #ffffff; line-height: 1.1; letter-spacing: -0.02em;">
                <?= $init_weather_temp ?> <?= is_numeric($init_weather_temp) ? '°C' : '' ?>
              </div>
            </div>
          </div>

          <!-- Middle: Thermal Spectrum Bar + Indicator Dot -->
          <div style="margin-top: 10px; width: 100%;">
            <div class="live-slider-track" style="height: 5px; width: 100%; border-radius: 999px; background: linear-gradient(90deg, #0284c7 0%, #10b981 35%, #f59e0b 70%, #ef4444 100%); position: relative;">
              <div id="weather-temp-slider-dot" class="soil-temp-pulse-dot <?= is_numeric($init_weather_temp) ? '' : 'bar-dot-standby-scan' ?>" style="position: absolute; top: 50%; left: <?= $ambient_temp_pct ?>%; transform: translate(-50%, -50%); width: 12px; height: 12px; border-radius: 50%; background: #ffffff; border: 2px solid #f97316; box-shadow: 0 0 8px rgba(249,115,22,0.9); transition: left 1s ease-in-out;"></div>
            </div>
          </div>

          <!-- Bottom note -->
          <div class="weather-metric-note" style="margin-top: 10px; font-size: 12px; color: #cbd5e1; line-height: 1.35; font-weight: 400;">
            Thermal reading: <span id="weather-temp-note-val" style="color: #fed7aa; font-weight: 600;"><?= is_numeric($init_weather_temp) ? "{$init_weather_temp} °C (Ambient)" : '--' ?></span>
          </div>
        </div>

        <!-- Card 7: Ambient Humidity -->
        <div class="weather-card-dark weather-card-ambient-hum" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 140px;">
          <!-- Top: Humidity Droplet SVG + Value -->
          <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px;">
            <div class="weather-soil-icon-wrap" style="width: 58px; height: 58px; flex-shrink: 0;">
              <svg viewBox="0 0 64 64" style="width: 100%; height: 100%;">
                <g class="water-drop-anim">
                  <path d="M 32 10 C 32 10 16 28 16 38 C 16 46.8 23.2 54 32 54 C 40.8 54 48 46.8 48 38 C 48 28 32 10 32 10 Z" fill="url(#ambHumGrad)" filter="drop-shadow(0 2px 6px rgba(14, 165, 233, 0.5))" />
                  <ellipse cx="25" cy="35" rx="3.5" ry="7" transform="rotate(-25 25 35)" fill="#ffffff" opacity="0.55" />
                </g>
                <defs>
                  <linearGradient id="ambHumGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#38bdf8" />
                    <stop offset="100%" stop-color="#0284c7" />
                  </linearGradient>
                </defs>
              </svg>
            </div>

            <!-- Value Text -->
            <div style="text-align: right;">
              <div class="weather-metric-label" style="font-size: 13px; font-weight: 500; color: #cbd5e1; margin-bottom: 2px;">
                Relative Humidity
              </div>
              <div id="card-weather-hum-val" class="weather-card-number" style="font-family: var(--font-sans); font-size: 28px; font-weight: 800; color: #ffffff; line-height: 1.1; letter-spacing: -0.02em;">
                <?= $init_weather_hum ?>
              </div>
            </div>
          </div>

          <!-- Middle: Moisture Gradient Bar + Indicator Dot -->
          <div style="margin-top: 10px; width: 100%;">
            <div class="live-slider-track" style="height: 5px; width: 100%; border-radius: 999px; background: linear-gradient(90deg, #eab308 0%, #00bfa5 45%, #0284c7 100%); position: relative;">
              <div id="weather-hum-slider-dot" class="soil-hum-pulse-dot <?= $init_weather_hum !== '--%' ? '' : 'bar-dot-standby-scan' ?>" style="position: absolute; top: 50%; left: <?= $ambient_hum_pct ?>%; transform: translate(-50%, -50%); width: 12px; height: 12px; border-radius: 50%; background: #ffffff; border: 2px solid #0284c7; box-shadow: 0 0 8px rgba(2,132,199,0.9); transition: left 1s ease-in-out;"></div>
            </div>
          </div>

          <!-- Bottom note -->
          <div class="weather-metric-note" style="margin-top: 10px; font-size: 12px; color: #cbd5e1; line-height: 1.35; font-weight: 400;">
            Atmospheric moisture: <span id="weather-hum-note-val" style="color: #bae6fd; font-weight: 600;"><?= $init_weather_hum !== '--%' ? "Relative RH {$init_weather_hum}" : '--' ?></span>
          </div>
        </div>

      </div>

    </div>

    <!-- Weather Telemetry Table Section -->
    <div style="margin-top: 14px;">
      <div style="margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
        <h2 style="font-size: 18px; font-weight: 700; color: #ffffff; text-shadow: 0 2px 10px rgba(15, 23, 42, 0.6); margin: 0; display: flex; align-items: center; gap: 8px; font-family: var(--font-sans);">
          <i data-lucide="cloud-sun" style="width: 18px; height: 18px; color: #00bfa5;"></i>
          <span>Live Weather Telemetry Records</span>
        </h2>
      </div>

      <div id="live-weather-table-container" style="background: rgba(255, 255, 255, 0.94); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.7); overflow: hidden; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12); transition: opacity 0.2s ease; width: 100%; max-width: 100%; min-width: 0; box-sizing: border-box;">
        <!-- Mobile swipe indicator -->
        <div class="block sm:hidden px-4 py-2 bg-slate-50 border-b border-slate-100 text-[11px] text-slate-500 font-medium flex items-center justify-between">
          <span>👈 Swipe horizontally to view all weather parameters</span>
          <span class="font-mono text-[10px] bg-sky-100 text-sky-700 px-1.5 py-0.5 rounded font-bold">WEATHER STREAM</span>
        </div>

        <div style="overflow-x: auto; max-height: 420px; -webkit-overflow-scrolling: touch;">
          <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; font-family: var(--font-sans); min-width: 650px;">
            <thead style="position: sticky; top: 0; z-index: 1; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
              <tr>
                <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Timestamp (IST)</th>
                <th style="padding: 12px 16px; font-weight: 700; color: #b45309; font-size: 12px; white-space: nowrap;">Temp (°C)</th>
                <th style="padding: 12px 16px; font-weight: 700; color: #0369a1; font-size: 12px; white-space: nowrap;">Humidity (%)</th>
                <th style="padding: 12px 16px; font-weight: 700; color: #047857; font-size: 12px; white-space: nowrap;">Wind Speed</th>
                <th style="padding: 12px 16px; font-weight: 700; color: #0d9488; font-size: 12px; white-space: nowrap;">Wind Gust</th>
                <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Wind Dir</th>
                <th style="padding: 12px 16px; font-weight: 700; color: #2563eb; font-size: 12px; white-space: nowrap;">Rain (mm)</th>
              </tr>
            </thead>
            <tbody id="weather-tbody">
              <tr>
                <td colspan="7" style="padding: 40px; text-align: center; color: #64748b;">
                  Loading ambient weather telemetry...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div> <!-- End #node2-view-weather -->

  <!-- ════════════════════════════════════════════════════════════════════════════
       NODE 2 DEDICATED SOIL LIVE VIEW (ONLY SOIL PARAMETERS)
       Design: Frosted glass KPI cards + Dark trend graph + Live streaming table
       ════════════════════════════════════════════════════════════════════════════ -->
  <div id="node2-view-soil" style="<?= ($active_live_subtab === 'soil') ? 'display: flex;' : 'display: none;' ?> width: 100%; flex-direction: column; gap: 14px;">

    <!-- Soil Header / Location / Live status row -->
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-top: 4px;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); display: flex; align-items: center; justify-content: center; color: #ffffff; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);">
          <i data-lucide="sprout" style="width: 22px; height: 22px;"></i>
        </div>
        <div>
          <h1 style="font-size: clamp(18px, 2.2vw, 22px); font-weight: 800; color: #0f172a; margin: 0; font-family: var(--font-sans); letter-spacing: -0.01em;">
            Subsurface Soil Telemetry &amp; Agrimet Grid
          </h1>
          <p style="font-size: 12px; color: #64748b; margin: 2px 0 0 0; font-weight: 500;">
            Precision root-zone sensing &bull; Field Station 2
          </p>
        </div>
      </div>

      <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        <!-- Coordinates Badge (shows only when coordinates available from Supabase) -->
        <div id="soil-station-coords-badge" style="display: <?= $has_coords ? 'inline-flex' : 'none' ?>; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; background: #ffffff; border: 1.5px solid #a7f3d0; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.08); font-size: 11.5px; font-weight: 700; color: #065f46;">
          <i data-lucide="map-pin" style="width: 13px; height: 13px; color: #10b981;"></i>
          <span id="soil-coords-display"><?= $has_coords ? htmlspecialchars($init_dms ?? ($init_lat . '° N, ' . $init_lon . '° E')) : '' ?></span>
        </div>

        <!-- Last Updated Badge -->
        <div style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; background: #ffffff; border: 1.5px solid #e2e8f0; font-size: 11.5px; font-weight: 600; color: #475569;">
          <span>Last Updated: <strong id="soil-last-updated-val" style="color: #0f172a; font-family: var(--font-mono);"><?= date('h:i A') ?></strong></span>
        </div>

        <!-- Live Pulse Pill -->
        <div style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; background: #ecfdf5; border: 1.5px solid #a7f3d0; color: #059669; font-size: 11.5px; font-weight: 800; letter-spacing: 0.04em;">
          <span style="width: 7px; height: 7px; border-radius: 50%; background-color: #10b981; animation: pulse 2s infinite;"></span>
          <span>LIVE</span>
        </div>
      </div>
    </div>

    <!-- 4 Soil Frosted Glass KPI Cards (Soil Moisture, Temperature, pH, EC) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-3.5">

      <!-- Card 1: Soil Moisture / Humidity -->
      <div class="weather-card-dark" style="background: rgba(255, 255, 255, 0.94); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border: 1.5px solid #bae6fd; border-radius: 20px; padding: 18px 20px; box-shadow: 0 8px 24px rgba(2, 132, 199, 0.08); display: flex; flex-direction: column; justify-content: space-between; min-height: 146px;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px;">
          <div style="width: 54px; height: 54px; border-radius: 14px; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3); flex-shrink: 0;">
            <svg viewBox="0 0 64 64" style="width: 34px; height: 34px;">
              <g class="water-drop-anim">
                <path d="M 32 12 C 32 12 18 28 18 36 C 18 43.7 24.3 50 32 50 C 39.7 50 46 43.7 46 36 C 46 28 32 12 32 12 Z" fill="#ffffff" />
                <ellipse cx="26" cy="34" rx="3.5" ry="6" transform="rotate(-28 26 34)" fill="rgba(255,255,255,0.7)" />
              </g>
            </svg>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 2px;">
              Soil Moisture
            </div>
            <div id="soil-live-hum-val" style="font-family: var(--font-sans); font-size: 28px; font-weight: 800; color: #0369a1; line-height: 1.1; letter-spacing: -0.02em;">
              <?= $init_soil_hum ?>
            </div>
          </div>
        </div>

        <div style="margin-top: 12px; width: 100%;">
          <div class="live-slider-track" style="height: 6px; width: 100%; border-radius: 999px; background: linear-gradient(90deg, #eab308 0%, #10b981 45%, #0284c7 100%); position: relative;">
            <div id="soil-live-hum-slider-dot" class="soil-hum-pulse-dot <?= $has_soil_hum ? '' : 'bar-dot-standby-scan' ?>" style="position: absolute; top: 50%; left: <?= $soil_hum_pct ?>%; transform: translate(-50%, -50%); width: 13px; height: 13px; border-radius: 50%; background: #ffffff; border: 2.5px solid #0284c7; box-shadow: 0 0 8px rgba(2, 132, 199, 0.9); transition: left 1s ease-in-out;"></div>
          </div>
        </div>

        <div style="margin-top: 10px; font-size: 12px; color: #64748b; line-height: 1.35; font-weight: 500;">
          Hydration status: <span id="soil-live-hum-note-val" style="color: #0284c7; font-weight: 700;"><?= $has_soil_hum ? "Adequate Field Capacity ({$init_soil_hum})" : '--' ?></span>
        </div>
      </div>

      <!-- Card 2: Soil Temperature -->
      <div class="weather-card-dark" style="background: rgba(255, 255, 255, 0.94); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border: 1.5px solid #fed7aa; border-radius: 20px; padding: 18px 20px; box-shadow: 0 8px 24px rgba(234, 88, 12, 0.08); display: flex; flex-direction: column; justify-content: space-between; min-height: 146px;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px;">
          <div style="width: 54px; height: 54px; border-radius: 14px; background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(234, 88, 12, 0.3); flex-shrink: 0;">
            <svg viewBox="0 0 64 64" style="width: 34px; height: 34px;">
              <rect x="28" y="10" width="8" height="26" rx="4" fill="rgba(255,255,255,0.3)" stroke="#ffffff" stroke-width="1.6" />
              <circle cx="32" cy="38" r="7.5" fill="#ffffff" />
              <rect x="30" y="18" width="4" height="18" rx="2" fill="#ea580c" />
              <line x1="38" y1="14" x2="41" y2="14" stroke="#ffffff" stroke-width="1.4" stroke-linecap="round" />
              <line x1="38" y1="20" x2="42" y2="20" stroke="#ffffff" stroke-width="1.4" stroke-linecap="round" />
              <line x1="38" y1="26" x2="41" y2="26" stroke="#ffffff" stroke-width="1.4" stroke-linecap="round" />
            </svg>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 2px;">
              Soil Temperature
            </div>
            <div id="soil-live-temp-val" style="font-family: var(--font-sans); font-size: 28px; font-weight: 800; color: #c2410c; line-height: 1.1; letter-spacing: -0.02em;">
              <?= $init_soil_temp ?>
            </div>
          </div>
        </div>

        <div style="margin-top: 12px; width: 100%;">
          <div class="live-slider-track" style="height: 6px; width: 100%; border-radius: 999px; background: linear-gradient(90deg, #0284c7 0%, #10b981 35%, #f59e0b 70%, #ef4444 100%); position: relative;">
            <div id="soil-live-temp-slider-dot" class="soil-temp-pulse-dot <?= $has_soil_temp ? '' : 'bar-dot-standby-scan' ?>" style="position: absolute; top: 50%; left: <?= $soil_temp_pct ?>%; transform: translate(-50%, -50%); width: 13px; height: 13px; border-radius: 50%; background: #ffffff; border: 2.5px solid #ea580c; box-shadow: 0 0 8px rgba(234, 88, 12, 0.9); transition: left 1s ease-in-out;"></div>
          </div>
        </div>

        <div style="margin-top: 10px; font-size: 12px; color: #64748b; line-height: 1.35; font-weight: 500;">
          Root zone: <span id="soil-live-temp-note-val" style="color: #ea580c; font-weight: 700;"><?= $has_soil_temp ? "{$init_soil_temp} (Optimal Range)" : '--' ?></span>
        </div>
      </div>

      <!-- Card 3: Soil pH -->
      <div class="weather-card-dark" style="background: rgba(255, 255, 255, 0.94); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border: 1.5px solid #a7f3d0; border-radius: 20px; padding: 18px 20px; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.08); display: flex; flex-direction: column; justify-content: space-between; min-height: 146px;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px;">
          <div style="width: 54px; height: 54px; border-radius: 14px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); flex-shrink: 0;">
            <svg viewBox="0 0 64 64" style="width: 34px; height: 34px;">
              <circle cx="32" cy="32" r="24" fill="none" stroke="rgba(255,255,255,0.4)" stroke-width="2" />
              <circle cx="32" cy="32" r="16" fill="#ffffff" />
              <text x="32" y="33" font-size="11" font-weight="900" fill="#059669" text-anchor="middle" dominant-baseline="central" font-family="var(--font-sans)">pH</text>
            </svg>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 2px;">
              Soil Reaction (pH)
            </div>
            <div id="soil-live-ph-val" style="font-family: var(--font-sans); font-size: 28px; font-weight: 800; color: #047857; line-height: 1.1; letter-spacing: -0.02em;">
              <?= $init_soil_ph ?>
            </div>
          </div>
        </div>

        <div style="margin-top: 12px; width: 100%;">
          <div class="live-slider-track" style="height: 6px; width: 100%; border-radius: 999px; background: linear-gradient(90deg, #ef4444 0%, #f59e0b 30%, #10b981 55%, #06b6d4 75%, #8b5cf6 100%); position: relative;">
            <div id="soil-live-ph-slider-dot" class="soil-ph-pulse-dot <?= $has_soil_ph ? '' : 'bar-dot-standby-scan' ?>" style="position: absolute; top: 50%; left: <?= $soil_ph_pct ?>%; transform: translate(-50%, -50%); width: 13px; height: 13px; border-radius: 50%; background: #ffffff; border: 2.5px solid #10b981; box-shadow: 0 0 8px rgba(16, 185, 129, 0.9); transition: left 1s ease-in-out;"></div>
          </div>
        </div>

        <div style="margin-top: 10px; font-size: 12px; color: #64748b; line-height: 1.35; font-weight: 500;">
          Acidity: <span id="soil-live-ph-note-val" style="color: #059669; font-weight: 700;"><?= $has_soil_ph ? "{$soil_ph_status} ({$init_soil_ph})" : '--' ?></span>
        </div>
      </div>

      <!-- Card 4: Soil Electrical Conductivity (EC) -->
      <div class="weather-card-dark" style="background: rgba(255, 255, 255, 0.94); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border: 1.5px solid #e9d5ff; border-radius: 20px; padding: 18px 20px; box-shadow: 0 8px 24px rgba(139, 92, 246, 0.08); display: flex; flex-direction: column; justify-content: space-between; min-height: 146px;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px;">
          <div style="width: 54px; height: 54px; border-radius: 14px; background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3); flex-shrink: 0;">
            <svg viewBox="0 0 64 64" style="width: 34px; height: 34px;">
              <path d="M 34 10 L 22 34 L 32 34 L 28 54 L 44 28 L 34 28 Z" fill="#ffffff" filter="drop-shadow(0 2px 4px rgba(0,0,0,0.2))" />
            </svg>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 2px;">
              Soil Conductivity (EC)
            </div>
            <div id="soil-live-ec-val" style="font-family: var(--font-sans); font-size: 28px; font-weight: 800; color: #6d28d9; line-height: 1.1; letter-spacing: -0.02em;">
              <?= $init_soil_ec ?>
            </div>
          </div>
        </div>

        <div style="margin-top: 12px; width: 100%;">
          <div class="live-slider-track" style="height: 6px; width: 100%; border-radius: 999px; background: linear-gradient(90deg, #10b981 0%, #06b6d4 40%, #8b5cf6 75%, #ef4444 100%); position: relative;">
            <div id="soil-live-ec-slider-dot" class="soil-ec-pulse-dot <?= $has_soil_ec ? '' : 'bar-dot-standby-scan' ?>" style="position: absolute; top: 50%; left: <?= $soil_ec_pct ?>%; transform: translate(-50%, -50%); width: 13px; height: 13px; border-radius: 50%; background: #ffffff; border: 2.5px solid #8b5cf6; box-shadow: 0 0 8px rgba(139, 92, 246, 0.9); transition: left 1s ease-in-out;"></div>
          </div>
        </div>

        <div style="margin-top: 10px; font-size: 12px; color: #64748b; line-height: 1.35; font-weight: 500;">
          Salinity / Ion level: <span id="soil-live-ec-note-val" style="color: #7c3aed; font-weight: 700;"><?= $has_soil_ec ? "{$soil_ec_status} ({$init_soil_ec})" : '--' ?></span>
        </div>
      </div>

    </div>

    <!-- Soil Telemetry Trend Graph Card (Matching Sleek Dark Design) -->
    <div class="trend-graph-card" style="margin-top: 8px;">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
        <div>
          <div style="font-size: 16px; font-weight: 700; color: #ffffff; display: flex; align-items: center; gap: 8px;">
            <i data-lucide="trending-up" style="width: 17px; height: 17px; color: #10b981;"></i>
            <span id="soil-trend-chart-title">Soil Subsurface Trend Graph</span>
          </div>
          <div style="font-size: 12px; color: #94a3b8; margin-top: 2px;" id="soil-trend-chart-subtitle">
            Hourly soil moisture, temperature, pH and electrical conductivity diurnal progression
          </div>
        </div>

        <!-- Parameter Switcher Buttons -->
        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
          <button type="button" onclick="switchSoilLiveChartParam('soil_moisture')" id="btn-soil-param-moisture" class="trend-pill-btn" style="background: rgba(16, 185, 129, 0.25); border-color: #10b981; color: #34d399;">
            <span>💧 Moisture (%)</span>
          </button>
          <button type="button" onclick="switchSoilLiveChartParam('soil_temperature')" id="btn-soil-param-temp" class="trend-pill-btn">
            <span>🌱 Temp (°C)</span>
          </button>
          <button type="button" onclick="switchSoilLiveChartParam('soil_ph')" id="btn-soil-param-ph" class="trend-pill-btn">
            <span>🧪 pH</span>
          </button>
          <button type="button" onclick="switchSoilLiveChartParam('soil_conductivity')" id="btn-soil-param-ec" class="trend-pill-btn">
            <span>⚡ EC (µS/cm)</span>
          </button>
        </div>
      </div>

      <!-- Chart Canvas Container -->
      <div style="position: relative; height: 320px; width: 100%;">
        <canvas id="soil-live-trend-chart"></canvas>
        <div id="soil-live-chart-empty" style="position: absolute; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(22, 28, 40, 0.85); color: #94a3b8; font-size: 13px; font-weight: 600;">
          Waiting for live soil telemetry stream...
        </div>
      </div>

      <!-- Diurnal Legend and Stats Bar -->
      <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-top: 14px; padding-top: 12px; border-top: 1px solid rgba(255, 255, 255, 0.08); font-size: 12px; color: #cbd5e1;">
        <div style="display: flex; align-items: center; gap: 8px;">
          <span id="soil-chart-legend-swatch" style="width: 18px; height: 3.5px; border-radius: 2px; background: #0284c7; display: inline-block;"></span>
          <span id="soil-chart-legend-label" style="font-weight: 700; color: #ffffff;">Soil Moisture (Recorded Telemetry)</span>
        </div>
        <div style="display: flex; align-items: center; gap: 16px; font-family: var(--font-mono); font-size: 11.5px; color: #94a3b8; flex-wrap: wrap;">
          <span>Recorded: <strong id="soil-stat-recorded" style="color: #ffffff;">--</strong></span>
          <span>Peak: <strong id="soil-stat-peak" style="color: #ffffff;">--</strong></span>
          <span>Min: <strong id="soil-stat-min" style="color: #ffffff;">--</strong></span>
          <span>Avg: <strong id="soil-stat-avg" style="color: #ffffff;">--</strong></span>
        </div>
      </div>
    </div>

    <!-- Live Soil Telemetry Table Section -->
    <div style="margin-top: 14px;">
      <div style="margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
        <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; font-family: var(--font-sans);">
          <i data-lucide="sprout" style="width: 18px; height: 18px; color: #10b981;"></i>
          <span>Live Soil Telemetry Stream Records</span>
        </h2>
      </div>

      <div id="soil-live-table-container" style="background: rgba(255, 255, 255, 0.96); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border-radius: 20px; border: 1.5px solid #d1fae5; overflow: hidden; box-shadow: 0 10px 30px rgba(16, 185, 129, 0.08); width: 100%; box-sizing: border-box;">
        <div class="block sm:hidden px-4 py-2 bg-emerald-50 border-b border-emerald-100 text-[11px] text-emerald-800 font-medium flex items-center justify-between">
          <span>👈 Swipe horizontally to view all soil metrics</span>
          <span class="font-mono text-[10px] bg-emerald-100 text-emerald-800 px-1.5 py-0.5 rounded font-bold">SOIL STREAM</span>
        </div>

        <div style="overflow-x: auto; max-height: 420px; -webkit-overflow-scrolling: touch;">
          <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; font-family: var(--font-sans); min-width: 650px;">
            <thead style="position: sticky; top: 0; z-index: 1; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
              <tr>
                <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Timestamp (IST)</th>
                <th style="padding: 12px 16px; font-weight: 700; color: #0369a1; font-size: 12px; white-space: nowrap;">Soil Moisture (%)</th>
                <th style="padding: 12px 16px; font-weight: 700; color: #b45309; font-size: 12px; white-space: nowrap;">Soil Temp (°C)</th>
                <th style="padding: 12px 16px; font-weight: 700; color: #047857; font-size: 12px; white-space: nowrap;">Soil pH</th>
                <th style="padding: 12px 16px; font-weight: 700; color: #7c3aed; font-size: 12px; white-space: nowrap;">Soil EC (µS/cm)</th>
                <th style="padding: 12px 16px; font-weight: 700; color: #0f172a; font-size: 12px; white-space: nowrap;">Agronomic Status</th>
              </tr>
            </thead>
            <tbody id="soil-live-tbody">
              <tr>
                <td colspan="6" style="padding: 40px; text-align: center; color: #64748b;">
                  Loading subsurface soil telemetry...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div> <!-- End #node2-view-soil -->
  <?php endif; ?>
</div> <!-- End .live-content-container -->

<?php if (!empty($init_bulk)): ?>
<script>
window.INITIAL_DATA = <?= json_encode($init_bulk, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
if (window.INITIAL_DATA && window.INITIAL_DATA.aqi_history && window.INITIAL_DATA.aqi_history.length > 0) {
    window.__liveDataStream = window.INITIAL_DATA.aqi_history;
}
if (window.INITIAL_DATA && window.INITIAL_DATA.weather_history && window.INITIAL_DATA.weather_history.length > 0) {
    window.weatherHistory = window.INITIAL_DATA.weather_history;
}
</script>
<?php endif; ?>

<script>
// Stream tables are now cleanly separated per view: AQI table in node2-view-aqi, Weather table in node2-view-weather
function switchLiveTableStream(type) {
    if (window.triggerHaptic) window.triggerHaptic('light');
    if (window.lucide && lucide.createIcons) lucide.createIcons();
}

function updateTag(elementId, value, limit) {
    const el = document.getElementById(elementId);
    if (!el) return;
    if (value == null || value === 'N/A' || value === '--') {
        el.style.display = 'none';
        return;
    }
    el.style.display = 'inline-flex';
    const isSafe = parseFloat(value) <= limit;
    
    if (isSafe) {
        el.style.backgroundColor = '#dcfce7';
        el.style.borderColor = '#bbf7d0';
        el.style.color = '#15803d';
        el.innerHTML = `<span style="width: 6px; height: 6px; border-radius: 50%; background-color: #16a34a;"></span><span>Normal</span>`;
    } else {
        el.style.backgroundColor = '#fee2e2';
        el.style.borderColor = '#fecaca';
        el.style.color = '#b91c1c';
        el.innerHTML = `<span style="width: 6px; height: 6px; border-radius: 50%; background-color: #dc2626;"></span><span>High</span>`;
    }
}

function getParamStatusColor(param, value) {
    if (value == null || value === 'N/A' || value === '--' || isNaN(parseFloat(value))) {
        if (param === 'wind_dir') return '#0d9488';
        return '#cbd5e1';
    }
    if (param === 'wind_dir') return '#0d9488';
    
    // Standard CPCB NAQI 6-tier colors matching the concentration scale bar exactly:
    // Tier 0: Good      -> #59b61f (Green)
    // Tier 1: Moderate  -> #eec732 (Yellow)
    // Tier 2: Poor      -> #ea8c34 (Orange)
    // Tier 3: Unhealthy -> #e95478 (Red)
    // Tier 4: Severe    -> #b33fba (Purple)
    // Tier 5: Hazardous -> #c92033 (Maroon)
    const colors = ['#59b61f', '#eec732', '#ea8c34', '#e95478', '#b33fba', '#c92033'];
    const scales = {
        pm25: [30, 60, 90, 120, 250],
        pm10: [50, 100, 250, 350, 430],
        co:   [1.0, 2.0, 10.0, 17.0, 34.0],
        no2:  [40, 80, 180, 280, 400],
        o3:   [50, 100, 168, 208, 748]
    };
    const v = parseFloat(value);
    if (scales[param]) {
        const s = scales[param];
        if (v <= s[0]) return colors[0]; // Good (Green)
        if (v <= s[1]) return colors[1]; // Moderate (Yellow)
        if (v <= s[2]) return colors[2]; // Poor (Orange)
        if (v <= s[3]) return colors[3]; // Unhealthy (Red)
        if (v <= s[4]) return colors[4]; // Severe (Purple)
        return colors[5];                  // Hazardous (Maroon)
    }
    switch (param) {
        case 'gust':
            if (v <= 20) return colors[0];
            if (v <= 40) return colors[1];
            if (v <= 60) return colors[2];
            return colors[5];
        case 'rain':
            if (v <= 0.1) return '#0284c7';
            if (v <= 10) return '#0891b2';
            if (v <= 35) return '#2563eb';
            return '#7c3aed';
        default:
            return colors[0];
    }
}

function updateCardBorderAccent(cardId, param, val) {
    const el = document.getElementById(cardId);
    if (!el) return;
    const color = getParamStatusColor(param, val);
    el.style.borderLeft = `5px solid ${color}`;
}

// Thermal comfort classifier
function getComfortLevel(temp) {
    const t = Number(temp);
    if (isNaN(t)) return { text: 'Pleasant', bg: '#e0f2fe', color: '#0369a1', border: '#bae6fd' };
    if (t < 18) return { text: 'Cool', bg: '#e0f2fe', color: '#0369a1', border: '#bae6fd' };
    if (t <= 25) return { text: 'Pleasant', bg: '#ecfdf5', color: '#047857', border: '#a7f3d0' };
    if (t <= 30) return { text: 'Warm', bg: '#fef3c7', color: '#b45309', border: '#fde68a' };
    if (t <= 36) return { text: 'Hot', bg: '#ffedd5', color: '#c2410c', border: '#fed7aa' };
    return { text: 'Very Hot', bg: '#fee2e2', color: '#b91c1c', border: '#fecaca' };
}

var pollutantDetails = {
    pm25: {
        id: 'pm25', title: 'Particulate Matter 2.5 (PM2.5)', icon: '🌫️', limit: 60, unit: 'µg/m³',
        scale: [30, 60, 90, 120, 250],
        maxTick: '250+',
        desc: 'Fine inhalable particles with diameters 2.5 micrometers or smaller (about 30 times smaller than a human hair). Due to their minute size, they penetrate deep into lung alveoli and enter the circulatory system.',
        health: 'Causes respiratory inflammation, triggers asthma attacks, increases risk of heart disease, stroke, and lung cancer. Short-term exposure causes eye, nose, and throat irritation.'
    },
    pm10: {
        id: 'pm10', title: 'Particulate Matter 10 (PM10)', icon: '☁️', limit: 100, unit: 'µg/m³',
        scale: [50, 100, 250, 350, 430],
        maxTick: '430+',
        desc: 'Inhalable particles with diameters that are generally 10 micrometers and smaller. Sources include crushing or grinding operations and dust stirred up by vehicles on roads.',
        health: 'Can irritate the eyes, nose, and throat. Reduces lung function, exacerbates asthma and other respiratory diseases, and increases susceptibility to respiratory infections.'
    },
    co: {
        id: 'co', title: 'Carbon Monoxide (CO)', icon: '💨', limit: 2, unit: 'mg/m³',
        scale: [1, 2, 10, 17, 34],
        maxTick: '34+',
        desc: 'A colorless, odorless gas that can be harmful when inhaled in large amounts. CO is released when something is burned, primarily from vehicle exhaust.',
        health: 'Reduces oxygen delivery to the body\'s organs (like the heart and brain). Can cause headaches, dizziness, vomiting, and nausea. High levels can cause unconsciousness or death.'
    },
    no2: {
        id: 'no2', title: 'Nitrogen Dioxide (NO₂)', icon: '🏭', limit: 80, unit: 'µg/m³',
        scale: [40, 80, 180, 280, 400],
        maxTick: '400+',
        desc: 'A highly reactive gas that forms quickly from emissions from cars, trucks and buses, power plants, and off-road equipment.',
        health: 'Irritates airways in the human respiratory system. Aggravates respiratory diseases, particularly asthma, leading to coughing, wheezing or difficulty breathing.'
    },
    o3: {
        id: 'o3', title: 'Ground-level Ozone (O₃)', icon: '☀️', limit: 100, unit: 'µg/m³',
        scale: [50, 100, 168, 208, 748],
        maxTick: '748+',
        desc: 'Not emitted directly into the air, but created by chemical reactions between oxides of nitrogen (NOx) and volatile organic compounds (VOC) in the presence of sunlight.',
        health: 'Can trigger a variety of health problems including chest pain, coughing, throat irritation, and airway inflammation. It can worsen bronchitis, emphysema, and asthma.'
    }
};

var pollutantCategoryRanges = {
    pm25: {
        title: 'PM2.5 Scale (NAAQS Standard)',
        sub: 'National Ambient Air Quality Standard limits for PM2.5 in µg/m³.',
        unit: 'µg/m³',
        ranges: [
            { name: 'Good', range: '0 to 30', dot: '#59b61f', bg: '#f0fdf4', border: '#bbf7d0', textCol: '#59b61f', desc: 'Air quality is considered satisfactory, and air pollution poses little or no risk.' },
            { name: 'Moderate', range: '31 to 60', dot: '#eec732', bg: '#fefce8', border: '#fef08a', textCol: '#854d0e', desc: 'Acceptable air quality; minor breathing discomfort may occur for sensitive individuals.' },
            { name: 'Poor', range: '61 to 90', dot: '#ea8c34', bg: '#fff7ed', border: '#fed7aa', textCol: '#ea8c34', desc: 'Breathing discomfort to people with lungs, asthma, and heart diseases.' },
            { name: 'Unhealthy', range: '91 to 120', dot: '#e95478', bg: '#fef2f2', border: '#fecaca', textCol: '#e95478', desc: 'Breathing discomfort to most people on prolonged exposure. Limit strenuous outdoor exertion.' },
            { name: 'Severe', range: '121 to 250', dot: '#b33fba', bg: '#faf5ff', border: '#e9d5ff', textCol: '#b33fba', desc: 'Respiratory illness on prolonged exposure; significantly impacts people with existing ailments.' },
            { name: 'Hazardous', range: '250+', dot: '#c92033', bg: '#fef2f2', border: '#fecaca', textCol: '#c92033', desc: 'Emergency conditions. The entire population is likely to be affected. Serious risk of respiratory effects.' }
        ]
    },
    pm10: {
        title: 'PM10 Scale (NAAQS Standard)',
        sub: 'National Ambient Air Quality Standard limits for PM10 in µg/m³.',
        unit: 'µg/m³',
        ranges: [
            { name: 'Good', range: '0 to 50', dot: '#59b61f', bg: '#f0fdf4', border: '#bbf7d0', textCol: '#59b61f', desc: 'Air quality is considered satisfactory, and air pollution poses little or no risk.' },
            { name: 'Moderate', range: '51 to 100', dot: '#eec732', bg: '#fefce8', border: '#fef08a', textCol: '#854d0e', desc: 'Acceptable air quality; minor breathing discomfort may occur for sensitive individuals.' },
            { name: 'Poor', range: '101 to 250', dot: '#ea8c34', bg: '#fff7ed', border: '#fed7aa', textCol: '#ea8c34', desc: 'Breathing discomfort to people with lungs, asthma, and heart diseases.' },
            { name: 'Unhealthy', range: '251 to 350', dot: '#e95478', bg: '#fef2f2', border: '#fecaca', textCol: '#e95478', desc: 'Breathing discomfort to most people on prolonged exposure. Limit strenuous outdoor exertion.' },
            { name: 'Severe', range: '351 to 430', dot: '#b33fba', bg: '#faf5ff', border: '#e9d5ff', textCol: '#b33fba', desc: 'Respiratory illness on prolonged exposure; significantly impacts people with existing ailments.' },
            { name: 'Hazardous', range: '430+', dot: '#c92033', bg: '#fef2f2', border: '#fecaca', textCol: '#c92033', desc: 'Emergency conditions. The entire population is likely to be affected. Serious risk of respiratory effects.' }
        ]
    },
    co: {
        title: 'Carbon Monoxide (CO) Scale',
        sub: 'National Ambient Air Quality Standard limits for CO in mg/m³.',
        unit: 'mg/m³',
        ranges: [
            { name: 'Good', range: '0.0 to 1.0', dot: '#59b61f', bg: '#f0fdf4', border: '#bbf7d0', textCol: '#59b61f', desc: 'Air quality is considered satisfactory, and air pollution poses little or no risk.' },
            { name: 'Moderate', range: '1.1 to 2.0', dot: '#eec732', bg: '#fefce8', border: '#fef08a', textCol: '#854d0e', desc: 'Acceptable air quality; minor breathing discomfort may occur for sensitive individuals.' },
            { name: 'Poor', range: '2.1 to 10.0', dot: '#ea8c34', bg: '#fff7ed', border: '#fed7aa', textCol: '#ea8c34', desc: 'Breathing discomfort to people with lungs, asthma, and heart diseases.' },
            { name: 'Unhealthy', range: '10.1 to 17.0', dot: '#e95478', bg: '#fef2f2', border: '#fecaca', textCol: '#e95478', desc: 'Breathing discomfort to most people on prolonged exposure. Limit strenuous outdoor exertion.' },
            { name: 'Severe', range: '17.1 to 34.0', dot: '#b33fba', bg: '#faf5ff', border: '#e9d5ff', textCol: '#b33fba', desc: 'Respiratory illness on prolonged exposure; significantly impacts people with existing ailments.' },
            { name: 'Hazardous', range: '34.0+', dot: '#c92033', bg: '#fef2f2', border: '#fecaca', textCol: '#c92033', desc: 'Emergency conditions. The entire population is likely to be affected. Serious risk of respiratory effects.' }
        ]
    },
    no2: {
        title: 'Nitrogen Dioxide (NO₂) Scale',
        sub: 'National Ambient Air Quality Standard limits for NO₂ in µg/m³.',
        unit: 'µg/m³',
        ranges: [
            { name: 'Good', range: '0 to 40', dot: '#59b61f', bg: '#f0fdf4', border: '#bbf7d0', textCol: '#59b61f', desc: 'Air quality is considered satisfactory, and air pollution poses little or no risk.' },
            { name: 'Moderate', range: '41 to 80', dot: '#eec732', bg: '#fefce8', border: '#fef08a', textCol: '#854d0e', desc: 'Acceptable air quality; minor breathing discomfort may occur for sensitive individuals.' },
            { name: 'Poor', range: '81 to 180', dot: '#ea8c34', bg: '#fff7ed', border: '#fed7aa', textCol: '#ea8c34', desc: 'Breathing discomfort to people with lungs, asthma, and heart diseases.' },
            { name: 'Unhealthy', range: '181 to 280', dot: '#e95478', bg: '#fef2f2', border: '#fecaca', textCol: '#e95478', desc: 'Breathing discomfort to most people on prolonged exposure. Limit strenuous outdoor exertion.' },
            { name: 'Severe', range: '281 to 400', dot: '#b33fba', bg: '#faf5ff', border: '#e9d5ff', textCol: '#b33fba', desc: 'Respiratory illness on prolonged exposure; significantly impacts people with existing ailments.' },
            { name: 'Hazardous', range: '400+', dot: '#c92033', bg: '#fef2f2', border: '#fecaca', textCol: '#c92033', desc: 'Emergency conditions. The entire population is likely to be affected. Serious risk of respiratory effects.' }
        ]
    },
    o3: {
        title: 'Ground-level Ozone (O₃) Scale',
        sub: 'National Ambient Air Quality Standard limits for O₃ in µg/m³.',
        unit: 'µg/m³',
        ranges: [
            { name: 'Good', range: '0 to 50', dot: '#59b61f', bg: '#f0fdf4', border: '#bbf7d0', textCol: '#59b61f', desc: 'Air quality is considered satisfactory, and air pollution poses little or no risk.' },
            { name: 'Moderate', range: '51 to 100', dot: '#eec732', bg: '#fefce8', border: '#fef08a', textCol: '#854d0e', desc: 'Acceptable air quality; minor breathing discomfort may occur for sensitive individuals.' },
            { name: 'Poor', range: '101 to 168', dot: '#ea8c34', bg: '#fff7ed', border: '#fed7aa', textCol: '#ea8c34', desc: 'Breathing discomfort to people with lungs, asthma, and heart diseases.' },
            { name: 'Unhealthy', range: '169 to 208', dot: '#e95478', bg: '#fef2f2', border: '#fecaca', textCol: '#e95478', desc: 'Breathing discomfort to most people on prolonged exposure. Limit strenuous outdoor exertion.' },
            { name: 'Severe', range: '209 to 748', dot: '#b33fba', bg: '#faf5ff', border: '#e9d5ff', textCol: '#b33fba', desc: 'Respiratory illness on prolonged exposure; significantly impacts people with existing ailments.' },
            { name: 'Hazardous', range: '748+', dot: '#c92033', bg: '#fef2f2', border: '#fecaca', textCol: '#c92033', desc: 'Emergency conditions. The entire population is likely to be affected. Serious risk of respiratory effects.' }
        ]
    }
};

function getPollutantScaleInfo(param, value) {
    const data = pollutantDetails[param];
    if (!data || value == null || isNaN(parseFloat(value))) {
        return { pct: 8, color: '#59b61f', label: 'Good' };
    }
    const v = parseFloat(value);
    const s = data.scale;
    const colors = ['#59b61f', '#eec732', '#ea8c34', '#e95478', '#b33fba', '#c92033'];
    const labels = ['Good', 'Moderate', 'Poor', 'Unhealthy', 'Severe', 'Hazardous'];
    let pct = 0;
    let tier = 0;
    
    if (v <= s[0]) {
        pct = (v / s[0]) * 16.666;
        tier = 0;
    } else if (v <= s[1]) {
        pct = 16.666 + ((v - s[0]) / (s[1] - s[0])) * 16.667;
        tier = 1;
    } else if (v <= s[2]) {
        pct = 33.333 + ((v - s[1]) / (s[2] - s[1])) * 16.667;
        tier = 2;
    } else if (v <= s[3]) {
        pct = 50.0 + ((v - s[2]) / (s[3] - s[2])) * 16.666;
        tier = 3;
    } else if (v <= s[4]) {
        pct = 66.666 + ((v - s[3]) / (s[4] - s[3])) * 16.667;
        tier = 4;
    } else {
        pct = 83.333 + Math.min(1.0, (v - s[4]) / s[4]) * 15.667;
        tier = 5;
    }
    
    return {
        pct: Math.min(98.0, Math.max(2.0, pct)),
        color: colors[tier],
        label: labels[tier]
    };
}

// ═══════════════════════════════════════════════════════════════
// LIVE AQI COMPARISON CHART CONTROLLER (ORIGINAL GRAPH)
// ═══════════════════════════════════════════════════════════════
var comparisonChartInstance = window.__liveComparisonChartInstance || null;
var activePollutants = window.__liveActivePollutants || {
    pm25: true,
    pm10: true,
    co: true,
    no2: true,
    o3: true
};
window.__liveActivePollutants = activePollutants;

function togglePollutant(pollutant) {
    if (window.triggerHaptic) window.triggerHaptic('selection');
    activePollutants[pollutant] = !activePollutants[pollutant];
    const btn = document.getElementById(`filter-${pollutant}`);
    if (btn) {
        const color = btn.getAttribute('data-color');
        if (activePollutants[pollutant]) {
            btn.style.backgroundColor = `${color}25`;
            btn.style.borderColor = color;
            btn.style.color = color;
            btn.querySelector('span').style.backgroundColor = color;
        } else {
            btn.style.backgroundColor = '#f1f5f9';
            btn.style.borderColor = '#e2e8f0';
            btn.style.color = '#94a3b8';
            btn.querySelector('span').style.backgroundColor = '#94a3b8';
        }
    }
    renderComparisonChart();
}

function renderComparisonChart() {
    const canvas = document.getElementById('comparison-chart');
    if (!canvas) return;

    if (typeof Chart === 'undefined') {
        setTimeout(renderComparisonChart, 100);
        return;
    }

    if (!liveDataStream || liveDataStream.length === 0) {
        if (window.__liveDataStream && window.__liveDataStream.length > 0) {
            liveDataStream = window.__liveDataStream;
        } else if (window.INITIAL_DATA && Array.isArray(window.INITIAL_DATA.aqi_history) && window.INITIAL_DATA.aqi_history.length > 0) {
            liveDataStream = window.INITIAL_DATA.aqi_history;
            window.__liveDataStream = liveDataStream;
        }
    }

    if (!liveDataStream || liveDataStream.length === 0) {
        const empty = document.getElementById('comparison-chart-empty');
        if (empty) empty.style.display = 'flex';
        return;
    }
    const empty = document.getElementById('comparison-chart-empty');
    if (empty) empty.style.display = 'none';

    // Reverse so chronologically left-to-right (oldest -> newest)
    const dataPoints = [...liveDataStream].reverse();

    const labels = dataPoints.map(d => {
        const ts = d.timestamp || d.created_at;
        if (!ts) return '';
        const dt = (typeof safeParseDate === 'function') ? safeParseDate(ts) : new Date(ts);
        return isNaN(dt.getTime()) ? '' : dt.toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', hour12: true });
    });

    const datasets = [
        {
            key: 'pm25',
            label: 'PM2.5',
            data: dataPoints.map(d => (d.pm25 != null ? d.pm25 : (d['pm2.5'] != null ? d['pm2.5'] : null))),
            borderColor: '#0284c7',
            backgroundColor: 'rgba(2, 132, 199, 0.08)',
            fill: false,
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 0
        },
        {
            key: 'pm10',
            label: 'PM10',
            data: dataPoints.map(d => (d.pm10 != null ? d.pm10 : null)),
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.08)',
            fill: false,
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 0
        },
        {
            key: 'co',
            label: 'CO',
            data: dataPoints.map(d => (d.co != null ? d.co : null)),
            borderColor: '#64748b',
            backgroundColor: 'rgba(100, 116, 139, 0.08)',
            fill: false,
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 0
        },
        {
            key: 'no2',
            label: 'NO₂',
            data: dataPoints.map(d => (d.no2 != null ? d.no2 : null)),
            borderColor: '#9333ea',
            backgroundColor: 'rgba(147, 51, 234, 0.08)',
            fill: false,
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 0
        },
        {
            key: 'o3',
            label: 'O₃',
            data: dataPoints.map(d => (d.o3 != null ? d.o3 : null)),
            borderColor: '#d97706',
            backgroundColor: 'rgba(217, 119, 6, 0.08)',
            fill: false,
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 0
        }
    ].filter(ds => activePollutants[ds.key]);

    try {
        if (comparisonChartInstance && comparisonChartInstance.ctx && comparisonChartInstance.canvas === canvas) {
            comparisonChartInstance.data.labels = labels;
            comparisonChartInstance.data.datasets = datasets;
            if (comparisonChartInstance.options && comparisonChartInstance.options.scales && comparisonChartInstance.options.scales.y) {
                comparisonChartInstance.options.scales.y.min = 0;
                comparisonChartInstance.options.scales.y.suggestedMin = 0;
                comparisonChartInstance.options.scales.y.beginAtZero = true;
            }
            comparisonChartInstance.update('none');
        } else {
            if (comparisonChartInstance) {
                try { comparisonChartInstance.destroy(); } catch(e) {}
            }
            const ctx = canvas.getContext('2d');
            comparisonChartInstance = new Chart(ctx, {
                type: 'line',
                data: { labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.92)',
                            titleColor: '#ffffff',
                            bodyColor: '#cbd5e1',
                            padding: 10,
                            cornerRadius: 8,
                            boxPadding: 4,
                            callbacks: {
                                label: function(ctx) {
                                    const val = ctx.parsed.y;
                                    const unit = ctx.dataset.label === 'CO' ? ' mg/m³' : ' µg/m³';
                                    return `${ctx.dataset.label}: ${val != null ? val.toFixed(2) : '--'}${unit}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { family: 'var(--font-mono)', size: 10 },
                                color: '#94a3b8',
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 7
                            }
                        },
                        y: {
                            min: 0,
                            suggestedMin: 0,
                            beginAtZero: true,
                            grid: { color: 'rgba(226, 232, 240, 0.6)' },
                            ticks: {
                                font: { family: 'var(--font-mono)', size: 10 },
                                color: '#94a3b8'
                            }
                        }
                    }
                }
            });
            window.__liveComparisonChartInstance = comparisonChartInstance;
        }
    } catch(e) {
        console.warn('Comparison chart render error:', e);
    }
}
window.renderComparisonChart = renderComparisonChart;

// ── Node 2 Soil Live Trend Chart & Parameter Switching ────────────────────────
let currentSoilLiveChartParam = 'soil_moisture';
let soilLiveChartInstance = null;

function switchSoilLiveChartParam(param) {
    if (window.triggerHaptic) window.triggerHaptic('light');
    currentSoilLiveChartParam = param;
    
    // Update button states
    const btnIds = {
        'soil_moisture': 'btn-soil-param-moisture',
        'soil_temperature': 'btn-soil-param-temp',
        'soil_ph': 'btn-soil-param-ph',
        'soil_conductivity': 'btn-soil-param-ec'
    };
    Object.keys(btnIds).forEach(k => {
        const btn = document.getElementById(btnIds[k]);
        if (!btn) return;
        if (k === param) {
            btn.style.backgroundColor = 'rgba(16, 185, 129, 0.25)';
            btn.style.borderColor = '#10b981';
            btn.style.color = '#34d399';
        } else {
            btn.style.backgroundColor = 'rgba(255, 255, 255, 0.08)';
            btn.style.borderColor = 'rgba(255, 255, 255, 0.14)';
            btn.style.color = '#f1f5f9';
        }
    });

    renderSoilLiveTrendChart();
}
window.switchSoilLiveChartParam = switchSoilLiveChartParam;

function renderSoilLiveTrendChart() {
    const canvas = document.getElementById('soil-live-trend-chart');
    if (!canvas) return;

    const stream = (window.weatherHistory && window.weatherHistory.length > 0) 
        ? window.weatherHistory 
        : ((window.__liveDataStream && window.__liveDataStream.length > 0) ? window.__liveDataStream : []);

    const empty = document.getElementById('soil-live-chart-empty');
    if (!stream || stream.length === 0) {
        if (empty) empty.style.display = 'flex';
        return;
    }
    if (empty) empty.style.display = 'none';

    // Reverse so chronologically left-to-right (oldest -> newest)
    const dataPoints = [...stream].reverse();

    const labels = dataPoints.map(d => {
        const ts = d.timestamp || d.created_at;
        if (!ts) return '';
        const dt = (typeof safeParseDate === 'function') ? safeParseDate(ts) : new Date(ts);
        return isNaN(dt.getTime()) ? '' : dt.toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', hour12: true });
    });

    const paramConfig = {
        'soil_moisture': {
            label: 'Soil Moisture (Recorded Telemetry)',
            unit: '%',
            color: '#0284c7',
            bgColor: 'rgba(2, 132, 199, 0.14)',
            extractor: d => (d.soil_moisture != null ? Number(d.soil_moisture) : (d.soil_humidity != null ? Number(d.soil_humidity) : null))
        },
        'soil_temperature': {
            label: 'Soil Temperature (Recorded Telemetry)',
            unit: '°C',
            color: '#ea580c',
            bgColor: 'rgba(234, 88, 12, 0.14)',
            extractor: d => (d.soil_temperature != null ? Number(d.soil_temperature) : (d.soil_temp != null ? Number(d.soil_temp) : null))
        },
        'soil_ph': {
            label: 'Soil pH (Recorded Telemetry)',
            unit: '',
            color: '#10b981',
            bgColor: 'rgba(16, 185, 129, 0.14)',
            extractor: d => (d.soil_ph != null ? Number(d.soil_ph) : (d.ph != null ? Number(d.ph) : null))
        },
        'soil_conductivity': {
            label: 'Soil Conductivity (Recorded Telemetry)',
            unit: ' µS/cm',
            color: '#8b5cf6',
            bgColor: 'rgba(139, 92, 246, 0.14)',
            extractor: d => (d.soil_conductivity != null ? Number(d.soil_conductivity) : (d.ec != null ? Number(d.ec) : null))
        }
    };

    const cfg = paramConfig[currentSoilLiveChartParam] || paramConfig['soil_moisture'];
    const values = dataPoints.map(cfg.extractor);

    // Compute stats
    const validVals = values.filter(v => v !== null && !isNaN(v));
    const statRec = document.getElementById('soil-stat-recorded');
    const statPeak = document.getElementById('soil-stat-peak');
    const statMin = document.getElementById('soil-stat-min');
    const statAvg = document.getElementById('soil-stat-avg');
    const legLabel = document.getElementById('soil-chart-legend-label');
    const legSwatch = document.getElementById('soil-chart-legend-swatch');

    if (legLabel) legLabel.innerText = cfg.label;
    if (legSwatch) legSwatch.style.backgroundColor = cfg.color;

    if (validVals.length > 0) {
        const lastVal = validVals[validVals.length - 1];
        const peakVal = Math.max(...validVals);
        const minVal = Math.min(...validVals);
        const avgVal = validVals.reduce((a, b) => a + b, 0) / validVals.length;

        const isPh = currentSoilLiveChartParam === 'soil_ph';
        const decimals = isPh ? 2 : 1;
        if (statRec) statRec.innerText = `${lastVal.toFixed(decimals)}${cfg.unit}`;
        if (statPeak) statPeak.innerText = `${peakVal.toFixed(decimals)}${cfg.unit}`;
        if (statMin) statMin.innerText = `${minVal.toFixed(decimals)}${cfg.unit}`;
        if (statAvg) statAvg.innerText = `${avgVal.toFixed(decimals)}${cfg.unit}`;
    } else {
        if (statRec) statRec.innerText = '--';
        if (statPeak) statPeak.innerText = '--';
        if (statMin) statMin.innerText = '--';
        if (statAvg) statAvg.innerText = '--';
    }

    try {
        if (soilLiveChartInstance && soilLiveChartInstance.ctx && soilLiveChartInstance.canvas === canvas) {
            soilLiveChartInstance.data.labels = labels;
            soilLiveChartInstance.data.datasets[0].label = cfg.label;
            soilLiveChartInstance.data.datasets[0].data = values;
            soilLiveChartInstance.data.datasets[0].unit = cfg.unit;
            soilLiveChartInstance.data.datasets[0].borderColor = cfg.color;
            soilLiveChartInstance.data.datasets[0].backgroundColor = cfg.bgColor;
            soilLiveChartInstance.data.datasets[0].pointBorderColor = cfg.color;
            soilLiveChartInstance.update('none');
        } else {
            if (soilLiveChartInstance) {
                try { soilLiveChartInstance.destroy(); } catch(e) {}
            }
            const ctx = canvas.getContext('2d');
            soilLiveChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: cfg.label,
                        data: values,
                        unit: cfg.unit,
                        borderColor: cfg.color,
                        backgroundColor: cfg.bgColor,
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: cfg.color,
                        pointBorderWidth: 2,
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.92)',
                            titleColor: '#ffffff',
                            bodyColor: '#cbd5e1',
                            padding: 10,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(ctx) {
                                    const val = ctx.parsed.y;
                                    const ds = ctx.dataset || {};
                                    const u = ds.unit !== undefined ? ds.unit : cfg.unit;
                                    const lbl = (ds.label || cfg.label).split(' ')[0];
                                    return `${lbl}: ${val != null ? val.toFixed(currentSoilLiveChartParam === 'soil_ph' ? 2 : 1) : '--'}${u}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { family: 'var(--font-mono)', size: 10 },
                                color: '#94a3b8',
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 7
                            }
                        },
                        y: {
                            min: 0,
                            suggestedMin: 0,
                            beginAtZero: true,
                            grid: { color: 'rgba(255, 255, 255, 0.08)' },
                            ticks: {
                                font: { family: 'var(--font-mono)', size: 10 },
                                color: '#94a3b8'
                            }
                        }
                    }
                }
            });
            window.__soilLiveChartInstance = soilLiveChartInstance;
        }
    } catch(e) {
        console.warn('Soil live chart render error:', e);
    }
}
window.renderSoilLiveTrendChart = renderSoilLiveTrendChart;

// Data Stream Formatting
var liveDataStream = (window.__liveDataStream && window.__liveDataStream.length > 0)
    ? window.__liveDataStream
    : ((window.INITIAL_DATA && Array.isArray(window.INITIAL_DATA.aqi_history) && window.INITIAL_DATA.aqi_history.length > 0)
        ? window.INITIAL_DATA.aqi_history
        : []);
window.__liveDataStream = liveDataStream;

function formatStreamTimestamp(row) {
    const rawTs = row.timestamp || row.created_at;
    if (!rawTs) return '--';
    const dt = (typeof safeParseDate === 'function') ? safeParseDate(rawTs) : new Date(rawTs);
    if (isNaN(dt.getTime())) return '--';
    return dt.toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', hour12: true });
}

function formatSupabaseValue(val, unit = '') {
    if (val === null || val === undefined || val === '') return '--';
    const num = parseFloat(val);
    return isNaN(num) ? '--' : num.toFixed(3) + unit;
}

function formatWeatherOneDecimal(val, unit = '') {
    if (val === null || val === undefined || val === '') return '--';
    const num = parseFloat(val);
    return isNaN(num) ? '--' : num.toFixed(1) + unit;
}

function parseWindDirection(raw) {
    if (raw === null || raw === undefined || raw === '' || raw === '--') {
        return null;
    }
    const cardinals = {
        'n': 0, 'north': 0,
        'nne': 22.5, 'north-northeast': 22.5, 'north northeast': 22.5,
        'ne': 45, 'northeast': 45,
        'ene': 67.5, 'east-northeast': 67.5, 'east northeast': 67.5,
        'e': 90, 'east': 90,
        'ese': 112.5, 'east-southeast': 112.5, 'east southeast': 112.5,
        'se': 135, 'southeast': 135,
        'sse': 157.5, 'south-southeast': 157.5, 'south southeast': 157.5,
        's': 180, 'south': 180,
        'ssw': 202.5, 'south-southwest': 202.5, 'south southwest': 202.5,
        'sw': 225, 'southwest': 225,
        'wsw': 247.5, 'west-southwest': 247.5, 'west southwest': 247.5,
        'w': 270, 'west': 270,
        'wnw': 292.5, 'west-northwest': 292.5, 'west northwest': 292.5,
        'nw': 315, 'northwest': 315,
        'nnw': 337.5, 'north-northwest': 337.5, 'north northwest': 337.5
    };
    const dirs = ['n','nne','ne','ene','e','ese','se','sse','s','ssw','sw','wsw','w','wnw','nw','nnw'];
    const str = String(raw).trim().toLowerCase();
    const match = str.match(/(-?\d+(?:\.\d+)?)/);
    if (match) {
        let deg = parseFloat(match[1]) % 360;
        if (deg < 0) deg += 360;
        const degRound = Math.round(deg);
        const card = dirs[Math.round(deg / 22.5) % 16];
        return {
            deg: degRound,
            cardinal: card,
            display: `${degRound}° ${card}`
        };
    }
    const clean = str.replace(/[^a-z\- ]/g, '').trim();
    if (cardinals.hasOwnProperty(clean)) {
        const deg = cardinals[clean];
        const degRound = Math.round(deg);
        const card = dirs[Math.round(deg / 22.5) % 16];
        return {
            deg: degRound,
            cardinal: card,
            display: `${degRound}° ${card}`
        };
    }
    return null;
}

function getCompassDir(val) {
    if (val == null || val === '') return '--';
    const p = parseWindDirection(val);
    return p ? p.display : String(val);
}

function renderLiveTable() {
    const tbody = document.getElementById('live-table-body');
    if (!tbody) return;
    
    if (liveDataStream.length === 0) {
        if (window.__liveDataStream && window.__liveDataStream.length > 0) {
            liveDataStream = window.__liveDataStream;
        } else if (window.INITIAL_DATA && Array.isArray(window.INITIAL_DATA.aqi_history) && window.INITIAL_DATA.aqi_history.length > 0) {
            liveDataStream = window.INITIAL_DATA.aqi_history;
            window.__liveDataStream = liveDataStream;
        }
    }

    if (liveDataStream.length === 0) {
        if (tbody.children.length > 1 || (tbody.children.length === 1 && !tbody.innerText.includes('mode') && !tbody.innerText.includes('Loading'))) {
            return;
        }
        tbody.innerHTML = `<tr><td colspan="7" style="padding: 40px; text-align: center; color: #64748b;">No air quality telemetry stream data.</td></tr>`;
        return;
    }

    tbody.innerHTML = liveDataStream.map((d, i) => {
        const timeStr = formatStreamTimestamp(d);
        const aqiVal = d.cpcb_aqi != null ? Math.round(d.cpcb_aqi) : '--';
        const pm25 = formatSupabaseValue(d.pm2_5 ?? d.pm25 ?? d['pm2.5']);
        const pm10 = formatSupabaseValue(d.pm10);
        const co = formatSupabaseValue(d.co);
        const no2 = formatSupabaseValue(d.no2);
        const o3 = formatSupabaseValue(d.o3);

        return `<tr style="border-bottom: 1px solid #f1f5f9; background-color: ${i % 2 === 0 ? '#ffffff' : '#f8fafc'};">
            <td style="padding: 11px 16px; font-family: var(--font-mono); font-size: 12px; color: #1e293b; white-space: nowrap; font-weight: 600;">${timeStr}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 800; color: #0f172a; font-size: 14px;">${aqiVal}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #334155;">${pm25}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #334155;">${pm10}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: ${co === '--' ? '#94a3b8' : '#334155'};">${co}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #334155;">${no2}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #334155;">${o3}</td>
        </tr>`;
    }).join('');
}

async function refreshDashboard(forceNetwork = false) {
    try {
        let bulkData;
        const activeNode = (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));

        if (typeof window.fetchLiveBulk === 'function') {
            bulkData = await window.fetchLiveBulk(forceNetwork);
        } else {
            const endpoint = `telemetry.php?action=live_bulk&node=${activeNode}&limit=60`;
            const fetchUrl = (typeof window.getApiUrl === 'function') ? window.getApiUrl(endpoint) : ('api/' + endpoint);
            const res = await fetch(fetchUrl);
            bulkData = await res.json();
        }

        if (!bulkData || (!bulkData.aqi && !bulkData.weather) || (bulkData && bulkData.status === 'STANDBY')) {
            const valEl = document.getElementById('hero-aqi-val') || document.getElementById('hero-aqi-value');
            if (valEl) {
                valEl.innerText = '--';
                valEl.style.color = '#94a3b8';
            }
            const labelEl = document.getElementById('hero-aqi-label');
            if (labelEl) {
                labelEl.innerText = 'Standby';
                labelEl.style.color = '#64748b';
            }
            const badgeEl = document.getElementById('hero-aqi-badge');
            if (badgeEl) {
                badgeEl.style.background = 'rgba(255, 255, 255, 0.85)';
                badgeEl.style.borderColor = 'rgba(148, 163, 184, 0.38)';
            }
            const heroCard = document.getElementById('hero-card');
            if (heroCard) {
                heroCard.style.background = 'linear-gradient(155deg, #ffffff 0%, #f8fafc 50%, #f1f5f9 100%)';
                heroCard.style.borderColor = 'rgba(148, 163, 184, 0.35)';
                heroCard.style.boxShadow = '0 10px 30px rgba(148, 163, 184, 0.15), 0 1px 3px rgba(15,23,42,0.04)';
            }
            const scaleInd = document.getElementById('hero-scale-indicator');
            if (scaleInd) {
                scaleInd.style.left = '0%';
                scaleInd.style.borderColor = '#94a3b8';
            }
            const skyline = document.getElementById('hero-skyline-svg');
            if (skyline) skyline.style.fill = '#94a3b8';

            const streamText = document.getElementById('hero-stream-text');
            if (streamText) streamText.innerText = 'Standby Mode';
            const latestTime = document.getElementById('hero-latest-time');
            if (latestTime) latestTime.innerText = 'Standby';

            const pm25El = document.getElementById('hero-pm25');
            if (pm25El) pm25El.innerText = '--';
            const pm10El = document.getElementById('hero-pm10');
            if (pm10El) pm10El.innerText = '--';

            ['card-pm25', 'card-pm10', 'card-co', 'card-no2', 'card-o3'].forEach(id => {
                const vel = document.getElementById(id);
                if (vel) vel.innerText = '--';
            });
            ['val-pm25', 'val-pm10', 'val-co', 'val-no2', 'val-o3'].forEach(id => {
                const vel = document.getElementById(id);
                if (vel) vel.innerText = '--';
            });
            ['pm25-val', 'pm10-val', 'co-val', 'no2-val', 'o3-val', 'weather-temp-val', 'weather-hum-val', 'weather-wind-val', 'weather-gust-val', 'weather-rain-val', 'weather-dir-val'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.innerText = '--';
            });
            const wHeroTemp = document.getElementById('weather-hero-temp');
            if (wHeroTemp) wHeroTemp.innerText = '--';
            const wMaxEl = document.getElementById('weather-hero-temp-max');
            if (wMaxEl) wMaxEl.innerText = '--°C';
            const wMinEl = document.getElementById('weather-hero-temp-min');
            if (wMinEl) wMinEl.innerText = '--°C';
            const wBoxHum = document.getElementById('weather-box-humidity');
            if (wBoxHum) wBoxHum.innerText = '--%';
            const wBoxRain = document.getElementById('weather-box-rain');
            if (wBoxRain) wBoxRain.innerText = '-- mm';
            const wBoxWind = document.getElementById('weather-box-wind');
            if (wBoxWind) wBoxWind.innerText = '-- km/h';
            const tbody = document.getElementById('live-table-body');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="7" style="padding: 40px; text-align: center; color: #64748b; font-size: 13px;">Currently in standby mode. No live telemetry recorded yet.</td></tr>';
            }
            const weatherTbody = document.getElementById('weather-tbody');
            if (weatherTbody) {
                weatherTbody.innerHTML = '<tr><td colspan="10" style="padding: 40px; text-align: center; color: #64748b; font-size: 13px;">Currently in standby mode. No weather telemetry recorded yet.</td></tr>';
            }
            const compEmpty = document.getElementById('comparison-chart-empty');
            if (compEmpty) {
                compEmpty.innerText = 'Comparison chart in standby mode.';
                compEmpty.style.display = 'flex';
            }
            if (window.updateGlobalLiveBadge) {
                window.updateGlobalLiveBadge(false, 'Currently in standby mode.', 'Standby');
            }
            if (window.evaluateNode2WeatherTheme) {
                window.evaluateNode2WeatherTheme(null, null, null);
            }
            return;
        }

        if (!bulkData || (!bulkData.aqi && !bulkData.weather)) {
            return;
        }

        if (!forceNetwork && !window.__liveNetworkSyncedOnce) {
            window.__liveNetworkSyncedOnce = true;
            setTimeout(() => {
                refreshDashboard(true);
            }, 60);
        }
        
        const aqiData = bulkData.aqi;
        const weatherData = bulkData.weather;
        const weatherHistory = bulkData.weather_history;
        const historyData = bulkData.aqi_history || bulkData.history;
        let currentAQITheme = null;

        if (window.updateGlobalLiveBadge) {
            window.updateGlobalLiveBadge(true, 'Live telemetry streaming from Node 2 Field Station.', 'LIVE');
        }
        const streamText = document.getElementById('hero-stream-text');
        if (streamText) streamText.innerText = 'Live Stream';

        function getAQIScalePercentage(aqi) {
            const v = Number(aqi);
            if (isNaN(v) || v <= 0) return 2;
            if (v <= 50)  return Math.max(2, (v / 50) * 16.666);
            if (v <= 100) return 16.666 + ((v - 50) / 50) * 16.667;
            if (v <= 200) return 33.333 + ((v - 100) / 100) * 16.667;
            if (v <= 300) return 50.0 + ((v - 200) / 100) * 16.666;
            if (v <= 400) return 66.666 + ((v - 300) / 100) * 16.667;
            return Math.min(98, 83.333 + Math.min((v - 400) / 100, 1) * 15.667);
        }

        function getAQITheme(aqi) {
            const v = Number(aqi);
            if (isNaN(v) || v <= 50) {
                return {
                    label: 'Good',
                    pageColor: '#dcfce7',
                    pageBg: 'radial-gradient(ellipse 120% 70% at 50% -10%, #86efac 0%, #bbf7d0 35%, #dcfce7 65%, #bbf7d0 100%)',
                    bg: 'linear-gradient(155deg, #86efac 0%, #4ade80 25%, #22c55e 55%, #16a34a 80%, #15803d 95%, #166534 100%)',
                    border: 'rgba(21, 128, 61, 0.45)',
                    shadow: 'rgba(22, 163, 74, 0.28)',
                    accent: '#14532d',
                    badgeBg: 'rgba(255, 255, 255, 0.88)',
                    badgeBorder: 'rgba(21, 128, 61, 0.45)',
                    badgeText: '#14532d'
                };
            }
            if (v <= 100) {
                return {
                    label: 'Moderate',
                    pageColor: '#fde68a',
                    pageBg: 'radial-gradient(ellipse 120% 70% at 50% -10%, #fde047 0%, #fde68a 35%, #fef08a 65%, #fde68a 100%)',
                    bg: 'linear-gradient(155deg, #fde047 0%, #facc15 22%, #eab308 50%, #ca8a04 75%, #b45309 90%, #92400e 100%)',
                    border: 'rgba(180, 83, 9, 0.45)',
                    shadow: 'rgba(202, 138, 4, 0.28)',
                    accent: '#78350f',
                    badgeBg: 'rgba(255, 255, 255, 0.88)',
                    badgeBorder: 'rgba(180, 83, 9, 0.45)',
                    badgeText: '#78350f'
                };
            }
            if (v <= 200) {
                return {
                    label: 'Poor',
                    pageColor: '#fed7aa',
                    pageBg: 'radial-gradient(ellipse 120% 70% at 50% -10%, #fdba74 0%, #fed7aa 35%, #ffedd5 65%, #fed7aa 100%)',
                    bg: 'linear-gradient(155deg, #fdba74 0%, #fb923c 25%, #f97316 52%, #ea580c 78%, #c2410c 92%, #9a3412 100%)',
                    border: 'rgba(194, 65, 12, 0.45)',
                    shadow: 'rgba(234, 88, 12, 0.28)',
                    accent: '#7c2d12',
                    badgeBg: 'rgba(255, 255, 255, 0.88)',
                    badgeBorder: 'rgba(194, 65, 12, 0.45)',
                    badgeText: '#7c2d12'
                };
            }
            if (v <= 300) {
                return {
                    label: 'Unhealthy',
                    pageColor: '#fecdd3',
                    pageBg: 'radial-gradient(ellipse 120% 70% at 50% -10%, #fda4af 0%, #fecdd3 35%, #ffe4e6 65%, #fecdd3 100%)',
                    bg: 'linear-gradient(155deg, #fda4af 0%, #fb7185 25%, #f43f5e 52%, #e11d48 78%, #be123c 92%, #881337 100%)',
                    border: 'rgba(190, 18, 60, 0.45)',
                    shadow: 'rgba(225, 29, 72, 0.28)',
                    accent: '#881337',
                    badgeBg: 'rgba(255, 255, 255, 0.88)',
                    badgeBorder: 'rgba(190, 18, 60, 0.45)',
                    badgeText: '#881337'
                };
            }
            if (v <= 400) {
                return {
                    label: 'Severe',
                    pageColor: '#e9d5ff',
                    pageBg: 'radial-gradient(ellipse 120% 70% at 50% -10%, #d8b4fe 0%, #e9d5ff 35%, #f3e8ff 65%, #e9d5ff 100%)',
                    bg: 'linear-gradient(155deg, #d8b4fe 0%, #c084fc 25%, #a855f7 52%, #9333ea 78%, #7e22ce 92%, #581c87 100%)',
                    border: 'rgba(126, 34, 206, 0.45)',
                    shadow: 'rgba(147, 51, 234, 0.28)',
                    accent: '#581c87',
                    badgeBg: 'rgba(255, 255, 255, 0.88)',
                    badgeBorder: 'rgba(126, 34, 206, 0.45)',
                    badgeText: '#581c87'
                };
            }
            return {
                label: 'Hazardous',
                pageColor: '#fecaca',
                pageBg: 'radial-gradient(ellipse 120% 70% at 50% -10%, #fca5a5 0%, #fecaca 35%, #fee2e2 65%, #fecaca 100%)',
                bg: 'linear-gradient(155deg, #fca5a5 0%, #f87171 25%, #ef4444 52%, #dc2626 78%, #b91c1c 92%, #7f1d1d 100%)',
                border: 'rgba(185, 28, 28, 0.50)',
                shadow: 'rgba(220, 38, 38, 0.32)',
                accent: '#7f1d1d',
                badgeBg: 'rgba(255, 255, 255, 0.88)',
                badgeBorder: 'rgba(185, 28, 28, 0.50)',
                badgeText: '#7f1d1d'
            };
        }

        // AQI POPULATION
        if (aqiData) {
            const d = aqiData;
            const aqiValue = d.cpcb_aqi != null ? Math.round(d.cpcb_aqi) : (d.aqi_info && d.aqi_info.value ? Math.round(d.aqi_info.value) : '--');
            const theme = getAQITheme(aqiValue);
            currentAQITheme = theme;

            // Page layout root background synced with AQI theme when on AQI tab
            const layoutRoot = document.getElementById('app-layout-root');
            if (layoutRoot && theme.pageColor && theme.pageBg) {
                const aqiView = document.getElementById('node2-view-aqi');
                if (!aqiView || aqiView.style.display !== 'none') {
                    layoutRoot.style.backgroundColor = theme.pageColor;
                    layoutRoot.style.backgroundImage = theme.pageBg;
                }
            }

            // Hero card background and colors (darker richer than whole page)
            const hero = document.getElementById('hero-card');
            const badge = document.getElementById('hero-aqi-badge');

            if (hero) {
                hero.style.background  = theme.bg;
                hero.style.borderColor = theme.border;
                hero.style.boxShadow   = `0 10px 30px ${theme.shadow}, 0 1px 3px rgba(15,23,42,0.04)`;
            }

            const valEl = document.getElementById('hero-aqi-val');
            if (valEl) {
                valEl.innerText = aqiValue;
                valEl.style.color = theme.accent;
            }

            const labelEl = document.getElementById('hero-aqi-label');
            if (labelEl) {
                labelEl.innerText = theme.label;
                labelEl.style.color = theme.badgeText;
            }

            if (badge) {
                badge.style.background  = theme.badgeBg;
                badge.style.borderColor = theme.badgeBorder;
            }

            const skyline = document.getElementById('hero-skyline-svg');
            if (skyline) {
                skyline.style.fill = theme.accent;
            }

            const clockIcon = document.getElementById('hero-clock-icon');
            if (clockIcon && theme.accent) {
                clockIcon.style.color = theme.accent;
            }

            const scalePct = getAQIScalePercentage(aqiValue);
            const indicator = document.getElementById('hero-scale-indicator');
            if (indicator) {
                indicator.style.left = `${scalePct}%`;
                indicator.style.borderColor = theme.accent;
            }

            // Quick PM2.5 & PM10 row
            const heroPm25 = document.getElementById('hero-pm25');
            const heroPm10 = document.getElementById('hero-pm10');
            const pm25Raw = d.pm2_5 ?? d.pm25 ?? d['pm2.5'];
            if (heroPm25) heroPm25.innerText = pm25Raw != null && !isNaN(pm25Raw) ? Math.round(pm25Raw) : (pm25Raw || '--');
            if (heroPm10) heroPm10.innerText = d.pm10 != null && !isNaN(d.pm10) ? Math.round(d.pm10) : (d.pm10 || '--');

            // Cards: Raw values
            const cardPm25 = document.getElementById('card-pm25');
            if (cardPm25) cardPm25.innerText = formatSupabaseValue(d.pm2_5 ?? d.pm25 ?? d['pm2.5']);
            const cardPm10 = document.getElementById('card-pm10');
            if (cardPm10) cardPm10.innerText = formatSupabaseValue(d.pm10);
            const cardCo = document.getElementById('card-co');
            if (cardCo) cardCo.innerText = formatSupabaseValue(d.co);
            const cardNo2 = document.getElementById('card-no2');
            if (cardNo2) cardNo2.innerText = formatSupabaseValue(d.no2);
            const cardO3 = document.getElementById('card-o3');
            if (cardO3) cardO3.innerText = formatSupabaseValue(d.o3);

            updateTag('tag-pm25', d.pm2_5 ?? d.pm25 ?? d['pm2.5'], 60);
            updateTag('tag-pm10', d.pm10, 100);
            updateTag('tag-co', d.co, 2);
            updateTag('tag-no2', d.no2, 80);
            updateTag('tag-o3', d.o3, 100);

            updateCardBorderAccent('card-box-pm25', 'pm25', d.pm2_5 ?? d.pm25 ?? d['pm2.5']);
            updateCardBorderAccent('card-box-pm10', 'pm10', d.pm10);
            updateCardBorderAccent('card-box-co', 'co', d.co);
            updateCardBorderAccent('card-box-no2', 'no2', d.no2);
            updateCardBorderAccent('card-box-o3', 'o3', d.o3);
        }
        
        // WEATHER DATA POPULATION
        let wTempFormatted = '--';
        let wComfortObj = { text: 'Pleasant' };
        let wHumFormatted = '--%';
        let wRainFormatted = '-- mm';
        let wWindFormatted = '-- km/h';

        if (weatherData) {
            const w = weatherData;
            window.__liveRainGauge = (w.rain_gauge != null) ? Number(w.rain_gauge) : 0;
            wTempFormatted = formatWeatherOneDecimal(w.temperature);
            wHumFormatted = formatWeatherOneDecimal(w.humidity, '%');
            wRainFormatted = formatWeatherOneDecimal(w.rain_gauge, ' mm');
            wWindFormatted = formatWeatherOneDecimal(w.wind_speed, ' km/h');

            // Dynamically update Node 2 Atmospheric Backdrop:
            // Rule: Cloud animation if temperature is low and humidity is high, else sunny bright animation
            if (window.evaluateNode2WeatherTheme) {
                window.evaluateNode2WeatherTheme(w.temperature, w.humidity, w.rain_gauge);
            }
            
            // Hero weather box
            const wHeroTemp = document.getElementById('weather-hero-temp');
            if (wHeroTemp) wHeroTemp.innerText = wTempFormatted;
            
            const wBoxHum = document.getElementById('weather-box-humidity');
            if (wBoxHum) wBoxHum.innerText = wHumFormatted;

            const wBoxRain = document.getElementById('weather-box-rain');
            if (wBoxRain) wBoxRain.innerText = wRainFormatted;

            const wBoxWind = document.getElementById('weather-box-wind');
            if (wBoxWind) wBoxWind.innerText = wWindFormatted;

            // Cards: Wind Gust, Wind Direction, Rain, Pressure, UV Index
            const cardGust = document.getElementById('card-gust');
            if (cardGust) cardGust.innerText = formatWeatherOneDecimal(w.wind_gust);
            
            const cardRain = document.getElementById('card-rain');
            if (cardRain) cardRain.innerText = wRainFormatted;

            // Animated Meteorological Cards Updates (Node 2)
            const cardWindVal = document.getElementById('card-wind-val');
            if (cardWindVal) {
                cardWindVal.innerText = (w.wind_speed != null && !isNaN(Number(w.wind_speed))) ? (Number(w.wind_speed).toFixed(1) + ' km/h') : '--';
            }

            const cardGustVal = document.getElementById('card-gust-val');
            if (cardGustVal) {
                cardGustVal.innerText = (w.wind_gust != null && !isNaN(Number(w.wind_gust))) ? (Number(w.wind_gust).toFixed(1) + ' m/s') : '--';
            }

            const cardRainVal = document.getElementById('card-rain-val');
            const rainNoteVal = document.getElementById('precipitation-note-val');
            const rainStr = (w.rain_gauge != null && !isNaN(Number(w.rain_gauge))) ? (Number(w.rain_gauge).toFixed(1) + ' mm') : '--';
            if (cardRainVal) cardRainVal.innerText = rainStr;
            if (rainNoteVal) rainNoteVal.innerText = rainStr;

            const cardDirVal = document.getElementById('card-dir-val');
            const compassNeedle = document.getElementById('compass-needle-group');
            const compassCardinal = document.getElementById('compass-cardinal-label');
            const parsedDir = parseWindDirection(w.wind_direction);
            if (parsedDir) {
                if (cardDirVal) cardDirVal.innerText = parsedDir.display;
                if (compassCardinal) compassCardinal.innerText = parsedDir.cardinal;
                if (compassNeedle) {
                    compassNeedle.classList.remove('compass-idle-scan');
                    compassNeedle.style.display = '';
                    compassNeedle.style.transform = `rotate(${parsedDir.deg}deg)`;
                }
            } else if (w.wind_direction != null && w.wind_direction !== '') {
                if (cardDirVal) cardDirVal.innerText = String(w.wind_direction);
                if (compassCardinal) compassCardinal.innerText = '--';
                if (compassNeedle) {
                    compassNeedle.classList.add('compass-idle-scan');
                    compassNeedle.style.display = '';
                }
            } else {
                if (cardDirVal) cardDirVal.innerText = '--';
                if (compassCardinal) compassCardinal.innerText = '--';
                if (compassNeedle) {
                    compassNeedle.classList.add('compass-idle-scan');
                    compassNeedle.style.display = '';
                }
            }

            const pressEl = document.getElementById('card-pressure-val');
            const pressNote = document.getElementById('pressure-note-val');
            const pressNeedle = document.getElementById('pressure-needle');
            const pressDot = document.getElementById('pressure-slider-dot');
            if (w.pressure != null && !isNaN(Number(w.pressure))) {
                const pressVal = Math.round(Number(w.pressure));
                if (pressEl) pressEl.innerText = `${pressVal} mb`;
                if (pressNote) pressNote.innerText = `${pressVal} mb`;
                if (pressNeedle) {
                    pressNeedle.classList.remove('pressure-needle-breathe');
                    pressNeedle.style.display = '';
                    const angle = (Number(w.pressure) - 1011) * 30;
                    pressNeedle.style.transform = `rotate(${angle.toFixed(1)}deg)`;
                }
                if (pressDot) {
                    pressDot.classList.remove('bar-dot-standby-scan');
                    pressDot.style.display = '';
                    pressDot.style.left = `${Math.max(5, Math.min(95, Math.round(((pressVal - 995) / (1025 - 995)) * 100)))}%`;
                }
            } else {
                if (pressEl) pressEl.innerText = '--';
                if (pressNote) pressNote.innerText = '--';
                if (pressNeedle) {
                    pressNeedle.classList.add('pressure-needle-breathe');
                    pressNeedle.style.display = '';
                    pressNeedle.style.transform = 'rotate(60deg)';
                }
                if (pressDot) {
                    pressDot.classList.add('bar-dot-standby-scan');
                    pressDot.style.display = '';
                }
            }

            const uvEl = document.getElementById('card-uv-val');
            const uvNote = document.getElementById('uv-note-val');
            const uvDot = document.getElementById('uv-slider-dot');
            if (w.uv_index != null && !isNaN(Number(w.uv_index))) {
                const uvVal = Number(w.uv_index).toFixed(1);
                let uvLvl = 'Low';
                const uvF = Number(uvVal);
                if (uvF >= 11) uvLvl = 'Extreme';
                else if (uvF >= 8) uvLvl = 'Very High';
                else if (uvF >= 6) uvLvl = 'High';
                else if (uvF >= 3) uvLvl = 'Moderate';
                if (uvEl) uvEl.innerText = `${uvVal} ${uvLvl}`;
                if (uvNote) uvNote.innerText = uvVal;
                if (uvDot) {
                    uvDot.classList.remove('bar-dot-standby-scan');
                    uvDot.style.display = '';
                    uvDot.style.left = `${Math.max(5, Math.min(95, Math.round((uvF / 11) * 100)))}%`;
                }
            } else {
                if (uvEl) uvEl.innerText = '--';
                if (uvNote) uvNote.innerText = '--';
                if (uvDot) {
                    uvDot.classList.add('bar-dot-standby-scan');
                    uvDot.style.display = '';
                }
            }

            // Ambient Weather Temperature & Humidity
            const ambTempEl = document.getElementById('card-weather-temp-val');
            const ambTempNote = document.getElementById('weather-temp-note-val');
            const ambTempDot = document.getElementById('weather-temp-slider-dot');
            if (w.temperature != null && !isNaN(Number(w.temperature))) {
                const aT = Number(w.temperature).toFixed(1);
                if (ambTempEl) ambTempEl.innerText = `${aT} °C`;
                if (ambTempNote) ambTempNote.innerText = `${aT} °C (Ambient)`;
                if (ambTempDot) {
                    ambTempDot.classList.remove('bar-dot-standby-scan');
                    ambTempDot.style.display = '';
                    ambTempDot.style.left = `${Math.max(5, Math.min(95, Math.round(((Number(aT) - 15) / (45 - 15)) * 100)))}%`;
                }
            } else {
                if (ambTempEl) ambTempEl.innerText = '--';
                if (ambTempNote) ambTempNote.innerText = '--';
                if (ambTempDot) {
                    ambTempDot.classList.add('bar-dot-standby-scan');
                    ambTempDot.style.display = '';
                }
            }

            const ambHumEl = document.getElementById('card-weather-hum-val');
            const ambHumNote = document.getElementById('weather-hum-note-val');
            const ambHumDot = document.getElementById('weather-hum-slider-dot');
            if (w.humidity != null && !isNaN(Number(w.humidity))) {
                const aH = Number(w.humidity).toFixed(1);
                if (ambHumEl) ambHumEl.innerText = `${aH}%`;
                if (ambHumNote) ambHumNote.innerText = `Relative RH ${aH}%`;
                if (ambHumDot) {
                    ambHumDot.classList.remove('bar-dot-standby-scan');
                    ambHumDot.style.display = '';
                    ambHumDot.style.left = `${Math.max(5, Math.min(95, Math.round(Number(aH))))}%`;
                }
            } else {
                if (ambHumEl) ambHumEl.innerText = '--';
                if (ambHumNote) ambHumNote.innerText = '--';
                if (ambHumDot) {
                    ambHumDot.classList.add('bar-dot-standby-scan');
                    ambHumDot.style.display = '';
                }
            }

            // Dedicated Soil View Cards (Soil Temperature, Moisture, pH, EC)
            const soilLastUpdated = document.getElementById('soil-last-updated-val');
            if (soilLastUpdated) soilLastUpdated.innerText = currentFormattedTime || new Date().toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', hour12: true });

            const soilTempEl = document.getElementById('soil-live-temp-val');
            const soilTempNote = document.getElementById('soil-live-temp-note-val');
            const soilTempDot = document.getElementById('soil-live-temp-slider-dot');
            if (w.soil_temperature != null && !isNaN(Number(w.soil_temperature))) {
                const sT = Number(w.soil_temperature).toFixed(1);
                if (soilTempEl) soilTempEl.innerText = `${sT} °C`;
                if (soilTempNote) soilTempNote.innerText = `${sT} °C (Optimal Range)`;
                if (soilTempDot) {
                    soilTempDot.classList.remove('bar-dot-standby-scan');
                    soilTempDot.style.display = '';
                    soilTempDot.style.left = `${Math.max(5, Math.min(95, Math.round(((Number(sT) - 10) / (40 - 10)) * 100)))}%`;
                }
            } else {
                if (soilTempEl) soilTempEl.innerText = '--';
                if (soilTempNote) soilTempNote.innerText = '--';
                if (soilTempDot) {
                    soilTempDot.classList.add('bar-dot-standby-scan');
                    soilTempDot.style.display = '';
                }
            }

            const soilHumEl = document.getElementById('soil-live-hum-val');
            const soilHumNote = document.getElementById('soil-live-hum-note-val');
            const soilHumDot = document.getElementById('soil-live-hum-slider-dot');
            const rawSoilHum = w.soil_humidity != null ? w.soil_humidity : (w.soil_moisture != null ? w.soil_moisture : null);
            if (rawSoilHum != null && !isNaN(Number(rawSoilHum))) {
                const sH = Number(rawSoilHum).toFixed(1);
                if (soilHumEl) soilHumEl.innerText = `${sH}%`;
                if (soilHumNote) soilHumNote.innerText = `Adequate Field Capacity (${sH}%)`;
                if (soilHumDot) {
                    soilHumDot.classList.remove('bar-dot-standby-scan');
                    soilHumDot.style.display = '';
                    soilHumDot.style.left = `${Math.max(5, Math.min(95, Math.round(Number(sH))))}%`;
                }
            } else {
                if (soilHumEl) soilHumEl.innerText = '--';
                if (soilHumNote) soilHumNote.innerText = '--';
                if (soilHumDot) {
                    soilHumDot.classList.add('bar-dot-standby-scan');
                    soilHumDot.style.display = '';
                }
            }

            const soilPhEl = document.getElementById('soil-live-ph-val');
            const soilPhNote = document.getElementById('soil-live-ph-note-val');
            const soilPhDot = document.getElementById('soil-live-ph-slider-dot');
            if (w.soil_ph != null && !isNaN(Number(w.soil_ph))) {
                const sPh = Number(w.soil_ph).toFixed(2);
                if (soilPhEl) soilPhEl.innerText = `${sPh}`;
                const phNum = Number(sPh);
                const phStatus = (phNum < 6.0) ? 'Slightly Acidic' : ((phNum > 7.5) ? 'Slightly Alkaline' : 'Optimal Neutral');
                if (soilPhNote) soilPhNote.innerText = `${phStatus} (${sPh})`;
                if (soilPhDot) {
                    soilPhDot.classList.remove('bar-dot-standby-scan');
                    soilPhDot.style.display = '';
                    soilPhDot.style.left = `${Math.max(5, Math.min(95, Math.round(((phNum - 3.0) / (10.0 - 3.0)) * 100)))}%`;
                }
            } else {
                if (soilPhEl) soilPhEl.innerText = '--';
                if (soilPhNote) soilPhNote.innerText = '--';
                if (soilPhDot) {
                    soilPhDot.classList.add('bar-dot-standby-scan');
                    soilPhDot.style.display = '';
                }
            }

            const soilEcEl = document.getElementById('soil-live-ec-val');
            const soilEcNote = document.getElementById('soil-live-ec-note-val');
            const soilEcDot = document.getElementById('soil-live-ec-slider-dot');
            if (w.soil_conductivity != null && !isNaN(Number(w.soil_conductivity))) {
                const sEc = Number(w.soil_conductivity).toFixed(1);
                if (soilEcEl) soilEcEl.innerText = `${sEc} µS/cm`;
                const ecNum = Number(sEc);
                const ecStatus = (ecNum < 40) ? 'Low Salinity (Good)' : ((ecNum > 150) ? 'Elevated Salinity' : 'Balanced Fertility');
                if (soilEcNote) soilEcNote.innerText = `${ecStatus} (${sEc})`;
                if (soilEcDot) {
                    soilEcDot.classList.remove('bar-dot-standby-scan');
                    soilEcDot.style.display = '';
                    soilEcDot.style.left = `${Math.max(5, Math.min(95, Math.round((ecNum / 200.0) * 100)))}%`;
                }
            } else {
                if (soilEcEl) soilEcEl.innerText = '--';
                if (soilEcNote) soilEcNote.innerText = '--';
                if (soilEcDot) {
                    soilEcDot.classList.add('bar-dot-standby-scan');
                    soilEcDot.style.display = '';
                }
            }
                if (soilEcDot) {
                    soilEcDot.style.display = '';
                    soilEcDot.style.left = '50%';
                }
            }
            
            const todayRainEl = document.getElementById('card-today-rain');
            if (todayRainEl) {
                let trVal = (bulkData && bulkData.today_rain_total != null) ? Number(bulkData.today_rain_total) : 0;
                if (w.rain_gauge != null && Number(w.rain_gauge) > 0 && trVal < Number(w.rain_gauge)) {
                    trVal = Number(w.rain_gauge);
                }
                todayRainEl.innerText = trVal.toFixed(1) + ' mm';
            }

            const dir = (w.wind_direction != null && w.wind_direction !== '') ? getCompassDir(w.wind_direction) : '--';
            const cardDir = document.getElementById('card-dir');
            if (cardDir) cardDir.innerText = dir;

            // Dynamically hydrate coordinates from cloud telemetry
            const latVal = (w.latitude != null) ? w.latitude : (bulkData && bulkData.latitude != null ? bulkData.latitude : null);
            const lonVal = (w.longitude != null) ? w.longitude : (bulkData && bulkData.longitude != null ? bulkData.longitude : null);
            const coordsDisplay = w.coords_display || (bulkData && bulkData.coords_display) || null;

            const latEl = document.getElementById('weather-header-lat');
            const lonEl = document.getElementById('weather-header-lon');
            const dmsEl = document.getElementById('weather-header-dms');
            const navCoordsEl = document.getElementById('navbar-station-coords');
            const navCoordsMobileEl = document.getElementById('navbar-station-coords-mobile');

            if (latVal != null && lonVal != null && !isNaN(Number(latVal)) && !isNaN(Number(lonVal))) {
                const latFormatted = `${Number(latVal).toFixed(4)}° N`;
                const lonFormatted = `${Number(lonVal).toFixed(4)}° E`;
                if (latEl) latEl.innerText = latFormatted;
                if (lonEl) lonEl.innerText = lonFormatted;
                const dmsStr = coordsDisplay || (typeof formatCoordsDms === 'function' ? formatCoordsDms(latVal, lonVal) : `${latFormatted}, ${lonFormatted}`);
                if (dmsEl) dmsEl.innerText = dmsStr;
                if (navCoordsEl) {
                    navCoordsEl.innerText = dmsStr;
                    navCoordsEl.style.borderLeft = '1px solid #e2e8f0';
                    navCoordsEl.style.display = 'inline-block';
                }
                if (navCoordsMobileEl) {
                    navCoordsMobileEl.innerText = dmsStr;
                    navCoordsMobileEl.style.borderLeft = '1px solid #e2e8f0';
                    navCoordsMobileEl.style.display = 'inline';
                }
                const soilBadge = document.getElementById('soil-station-coords-badge');
                const soilDisp = document.getElementById('soil-coords-display');
                if (soilBadge && soilDisp) {
                    soilDisp.innerText = dmsStr;
                    soilBadge.style.display = 'inline-flex';
                }
            } else if (coordsDisplay && coordsDisplay !== '--') {
                if (dmsEl) dmsEl.innerText = coordsDisplay;
                if (navCoordsEl) {
                    navCoordsEl.innerText = coordsDisplay;
                    navCoordsEl.style.borderLeft = '1px solid #e2e8f0';
                    navCoordsEl.style.display = 'inline-block';
                }
                if (navCoordsMobileEl) {
                    navCoordsMobileEl.innerText = coordsDisplay;
                    navCoordsMobileEl.style.borderLeft = '1px solid #e2e8f0';
                    navCoordsMobileEl.style.display = 'inline';
                }
            } else {
                if (navCoordsEl) {
                    navCoordsEl.innerText = '';
                    navCoordsEl.style.display = 'none';
                }
                if (navCoordsMobileEl) {
                    navCoordsMobileEl.innerText = '';
                    navCoordsMobileEl.style.display = 'none';
                }
                const soilBadge = document.getElementById('soil-station-coords-badge');
                if (soilBadge) soilBadge.style.display = 'none';
            }
        } else {
            // Standby or no weather telemetry - check if bulkData has coordinates
            const latVal = (bulkData && bulkData.latitude != null) ? bulkData.latitude : null;
            const lonVal = (bulkData && bulkData.longitude != null) ? bulkData.longitude : null;
            const coordsDisplay = (bulkData && bulkData.coords_display) ? bulkData.coords_display : null;
            const latEl = document.getElementById('weather-header-lat');
            const lonEl = document.getElementById('weather-header-lon');
            const dmsEl = document.getElementById('weather-header-dms');
            const navCoordsEl = document.getElementById('navbar-station-coords');
            const navCoordsMobileEl = document.getElementById('navbar-station-coords-mobile');

            if (latVal != null && lonVal != null && !isNaN(Number(latVal)) && !isNaN(Number(lonVal))) {
                const latFormatted = `${Number(latVal).toFixed(4)}° N`;
                const lonFormatted = `${Number(lonVal).toFixed(4)}° E`;
                if (latEl) latEl.innerText = latFormatted;
                if (lonEl) lonEl.innerText = lonFormatted;
                const dmsStr = coordsDisplay || (typeof formatCoordsDms === 'function' ? formatCoordsDms(latVal, lonVal) : `${latFormatted}, ${lonFormatted}`);
                if (dmsEl) dmsEl.innerText = dmsStr;
                if (navCoordsEl) {
                    navCoordsEl.innerText = dmsStr;
                    navCoordsEl.style.borderLeft = '1px solid #e2e8f0';
                    navCoordsEl.style.display = 'inline-block';
                }
                if (navCoordsMobileEl) {
                    navCoordsMobileEl.innerText = dmsStr;
                    navCoordsMobileEl.style.borderLeft = '1px solid #e2e8f0';
                    navCoordsMobileEl.style.display = 'inline';
                }
                const soilBadge = document.getElementById('soil-station-coords-badge');
                const soilDisp = document.getElementById('soil-coords-display');
                if (soilBadge && soilDisp) {
                    soilDisp.innerText = dmsStr;
                    soilBadge.style.display = 'inline-flex';
                }
            } else if (coordsDisplay && coordsDisplay !== '--') {
                if (dmsEl) dmsEl.innerText = coordsDisplay;
                if (navCoordsEl) {
                    navCoordsEl.innerText = coordsDisplay;
                    navCoordsEl.style.borderLeft = '1px solid #e2e8f0';
                    navCoordsEl.style.display = 'inline-block';
                }
                if (navCoordsMobileEl) {
                    navCoordsMobileEl.innerText = coordsDisplay;
                    navCoordsMobileEl.style.borderLeft = '1px solid #e2e8f0';
                    navCoordsMobileEl.style.display = 'inline';
                }
            } else {
                if (latEl) latEl.innerText = '--';
                if (lonEl) lonEl.innerText = '--';
                if (dmsEl) dmsEl.innerText = 'Awaiting GPS Sync';
                if (navCoordsEl) {
                    navCoordsEl.innerText = '';
                    navCoordsEl.style.display = 'none';
                }
                if (navCoordsMobileEl) {
                    navCoordsMobileEl.innerText = '';
                    navCoordsMobileEl.style.display = 'none';
                }
                const soilBadge = document.getElementById('soil-station-coords-badge');
                if (soilBadge) soilBadge.style.display = 'none';
            }

            const cardWindVal = document.getElementById('card-wind-val');
            if (cardWindVal) cardWindVal.innerText = '--';
            const cardGustVal = document.getElementById('card-gust-val');
            if (cardGustVal) cardGustVal.innerText = '--';
            const cardRainVal = document.getElementById('card-rain-val');
            if (cardRainVal) cardRainVal.innerText = '--';
            const rainNoteVal = document.getElementById('precipitation-note-val');
            if (rainNoteVal) rainNoteVal.innerText = '--';
            const cardDirVal = document.getElementById('card-dir-val');
            if (cardDirVal) cardDirVal.innerText = '--';
            const compassNeedle = document.getElementById('compass-needle-group');
            if (compassNeedle) {
                compassNeedle.classList.add('compass-idle-scan');
                compassNeedle.style.display = '';
            }
            const compassCardinal = document.getElementById('compass-cardinal-label');
            if (compassCardinal) compassCardinal.innerText = '--';
            const pressEl = document.getElementById('card-pressure-val');
            if (pressEl) pressEl.innerText = '--';
            const pressNote = document.getElementById('pressure-note-val');
            if (pressNote) pressNote.innerText = '--';
            const pressNeedle = document.getElementById('pressure-needle');
            if (pressNeedle) {
                pressNeedle.style.transform = 'rotate(60deg)';
                pressNeedle.classList.add('pressure-needle-breathe');
                pressNeedle.style.display = '';
            }
            const pressDot = document.getElementById('pressure-slider-dot');
            if (pressDot) {
                pressDot.classList.add('bar-dot-standby-scan');
                pressDot.style.display = '';
            }
            const uvEl = document.getElementById('card-uv-val');
            if (uvEl) uvEl.innerText = '--';
            const uvNote = document.getElementById('uv-note-val');
            if (uvNote) uvNote.innerText = '--';
            const uvDot = document.getElementById('uv-slider-dot');
            if (uvDot) {
                uvDot.classList.add('bar-dot-standby-scan');
                uvDot.style.display = '';
            }
            const ambTempEl = document.getElementById('card-weather-temp-val');
            if (ambTempEl) ambTempEl.innerText = '--';
            const ambTempDot = document.getElementById('weather-temp-slider-dot');
            if (ambTempDot) {
                ambTempDot.classList.add('bar-dot-standby-scan');
                ambTempDot.style.display = '';
            }
            const ambHumEl = document.getElementById('card-weather-hum-val');
            if (ambHumEl) ambHumEl.innerText = '--';
            const ambHumDot = document.getElementById('weather-hum-slider-dot');
            if (ambHumDot) {
                ambHumDot.classList.add('bar-dot-standby-scan');
                ambHumDot.style.display = '';
            }
            const soilTempEl = document.getElementById('soil-live-temp-val');
            if (soilTempEl) soilTempEl.innerText = '--';
            const soilTempDot = document.getElementById('soil-live-temp-slider-dot');
            if (soilTempDot) {
                soilTempDot.classList.add('bar-dot-standby-scan');
                soilTempDot.style.display = '';
            }
            const soilHumEl = document.getElementById('soil-live-hum-val');
            if (soilHumEl) soilHumEl.innerText = '--';
            const soilHumDot = document.getElementById('soil-live-hum-slider-dot');
            if (soilHumDot) {
                soilHumDot.classList.add('bar-dot-standby-scan');
                soilHumDot.style.display = '';
            }
            const soilPhEl = document.getElementById('soil-live-ph-val');
            if (soilPhEl) soilPhEl.innerText = '--';
            const soilPhDot = document.getElementById('soil-live-ph-slider-dot');
            if (soilPhDot) {
                soilPhDot.classList.add('bar-dot-standby-scan');
                soilPhDot.style.display = '';
            }
            const soilEcEl = document.getElementById('soil-live-ec-val');
            if (soilEcEl) soilEcEl.innerText = '--';
            const soilEcDot = document.getElementById('soil-live-ec-slider-dot');
            if (soilEcDot) {
                soilEcDot.classList.add('bar-dot-standby-scan');
                soilEcDot.style.display = '';
            }
        }
        
        // WEATHER & SOIL TABLES
        let wMinFormatted = '--';
        let wMaxFormatted = '--';
        const tbodyWeather = document.getElementById('weather-tbody');
        const tbodySoil = document.getElementById('soil-live-tbody');

        if (weatherHistory && weatherHistory.length > 0) {
            let temps = [];
            
            // 1. Ambient Weather Table
            if (tbodyWeather) {
                tbodyWeather.innerHTML = weatherHistory.map((row, i) => {
                    const timeStr = formatStreamTimestamp(row);
                    const temp = (row.temperature != null && !isNaN(Number(row.temperature))) ? `${Number(row.temperature).toFixed(1)} °C` : '--';
                    const hum = (row.humidity != null && !isNaN(Number(row.humidity))) ? `${Number(row.humidity).toFixed(1)}%` : '--';
                    const wSpd = (row.wind_speed != null && !isNaN(Number(row.wind_speed))) ? `${Number(row.wind_speed).toFixed(1)} km/h` : '--';
                    const gust = (row.wind_gust != null && !isNaN(Number(row.wind_gust))) ? `${Number(row.wind_gust).toFixed(1)} m/s` : '--';
                    const dir = (row.wind_direction != null && row.wind_direction !== '') ? getCompassDir(row.wind_direction) : '--';
                    const rain = (row.rain_gauge != null && !isNaN(Number(row.rain_gauge))) ? `${Number(row.rain_gauge).toFixed(1)} mm` : '--';

                    if (row.temperature != null && !isNaN(Number(row.temperature))) {
                        temps.push(Number(row.temperature));
                    }
                    
                    return `<tr style="border-bottom: 1px solid #f1f5f9; background-color: ${i % 2 === 0 ? '#ffffff' : '#f8fafc'};">
                      <td style="padding: 11px 16px; font-family: var(--font-mono); font-size: 12px; color: #1e293b; white-space: nowrap; font-weight: 600;">${timeStr}</td>
                      <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #b45309;">${temp}</td>
                      <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #0369a1;">${hum}</td>
                      <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #047857;">${wSpd}</td>
                      <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #0d9488;">${gust}</td>
                      <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #475569;">${dir}</td>
                      <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #2563eb;">${rain}</td>
                    </tr>`;
                }).join('');
            }

            // 2. Subsurface Soil Telemetry Table
            if (tbodySoil) {
                tbodySoil.innerHTML = weatherHistory.map((row, i) => {
                    const timeStr = formatStreamTimestamp(row);
                    const sHumRaw = (row.soil_moisture != null) ? row.soil_moisture : (row.soil_humidity != null ? row.soil_humidity : null);
                    const sHum = (sHumRaw != null && !isNaN(Number(sHumRaw))) ? `${Number(sHumRaw).toFixed(1)}%` : '--';
                    const sTemp = (row.soil_temperature != null && !isNaN(Number(row.soil_temperature))) 
                        ? `${Number(row.soil_temperature).toFixed(1)} °C` 
                        : (row.soil_temp != null && !isNaN(Number(row.soil_temp)) ? `${Number(row.soil_temp).toFixed(1)} °C` : '--');
                    const sPh = (row.soil_ph != null && !isNaN(Number(row.soil_ph))) 
                        ? Number(row.soil_ph).toFixed(2) 
                        : (row.ph != null && !isNaN(Number(row.ph)) ? Number(row.ph).toFixed(2) : '--');
                    const sEc = (row.soil_conductivity != null && !isNaN(Number(row.soil_conductivity))) 
                        ? `${Number(row.soil_conductivity).toFixed(1)}` 
                        : '--';

                    let badge = '<span style="display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;">Optimal</span>';
                    if (sPh !== '--' && (Number(sPh) < 6.0 || Number(sPh) > 8.0)) {
                        badge = '<span style="display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa;">Attn pH</span>';
                    }

                    return `<tr style="border-bottom: 1px solid #f1f5f9; background-color: ${i % 2 === 0 ? '#ffffff' : '#f8fafc'};">
                      <td style="padding: 11px 16px; font-family: var(--font-mono); font-size: 12px; color: #1e293b; white-space: nowrap; font-weight: 600;">${timeStr}</td>
                      <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #0369a1;">${sHum}</td>
                      <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #b45309;">${sTemp}</td>
                      <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #047857;">${sPh}</td>
                      <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #7c3aed;">${sEc}</td>
                      <td style="padding: 11px 16px;">${badge}</td>
                    </tr>`;
                }).join('');
            }
            
            if (temps.length > 0) {
                const min = Math.min(...temps).toFixed(1);
                const max = Math.max(...temps).toFixed(1);
                wMinFormatted = min;
                wMaxFormatted = max;
                const wMinEl = document.getElementById('weather-hero-temp-min');
                if (wMinEl) wMinEl.innerText = `${min}°C`;
                const wMaxEl = document.getElementById('weather-hero-temp-max');
                if (wMaxEl) wMaxEl.innerText = `${max}°C`;
            }
        } else {
            if (tbodyWeather) tbodyWeather.innerHTML = '<tr><td colspan="7" style="padding: 40px; text-align: center; color: #64748b;">No live weather telemetry found.</td></tr>';
            if (tbodySoil) tbodySoil.innerHTML = '<tr><td colspan="6" style="padding: 40px; text-align: center; color: #64748b;">No live soil telemetry found.</td></tr>';
        }

        // Cache unified hero state for 0ms instant reload
        try {
            const heroState = {
                aqi: aqiData && aqiData.cpcb_aqi != null ? Math.round(aqiData.cpcb_aqi) : '',
                label: currentAQITheme ? currentAQITheme.label : '',
                bg: currentAQITheme ? currentAQITheme.bg : '',
                border: currentAQITheme ? currentAQITheme.border : '',
                shadow: currentAQITheme ? currentAQITheme.shadow : '',
                accent: currentAQITheme ? currentAQITheme.accent : '',
                badgeBg: currentAQITheme ? currentAQITheme.badgeBg : '',
                badgeBorder: currentAQITheme ? currentAQITheme.badgeBorder : '',
                badgeText: currentAQITheme ? currentAQITheme.badgeText : '',
                scalePct: aqiData && aqiData.cpcb_aqi != null ? `${getAQIScalePercentage(aqiData.cpcb_aqi)}%` : '25%',
                pm25: document.getElementById('hero-pm25') ? document.getElementById('hero-pm25').innerText : '',
                pm10: document.getElementById('hero-pm10') ? document.getElementById('hero-pm10').innerText : '',
                weatherTemp: wTempFormatted,
                weatherMin: wMinFormatted,
                weatherMax: wMaxFormatted,
                weatherComfort: wComfortObj.text,
                weatherHum: wHumFormatted,
                weatherRain: wRainFormatted,
                weatherWind: wWindFormatted,
                timestamp: (aqiData && aqiData.timestamp) || (weatherData && weatherData.timestamp) || ''
            };
            localStorage.setItem('aqi_last_known_hero', JSON.stringify(heroState));
            document.cookie = "aqi_last_hero=" + encodeURIComponent(JSON.stringify(heroState)) + "; path=/; max-age=31536000; SameSite=Lax";
        } catch(e) {}

        const now = new Date();
        
        // --- Offline Check & Immediate Timestamp Calculation ---
        let isAqiOffline = false;
        let aqiOfflineMinutes = 0;
        let aqiLastUpdateTimeStr = "--";
        
        if (aqiData && (aqiData.timestamp || aqiData.created_at)) {
            const rawTs = aqiData.timestamp || aqiData.created_at;
            const lastUpdate = (typeof safeParseDate === 'function') ? safeParseDate(rawTs) : new Date(rawTs);
            if (lastUpdate && !isNaN(lastUpdate.getTime())) {
                const diffMs = now - lastUpdate;
                aqiOfflineMinutes = Math.floor(diffMs / 60000);
                isAqiOffline = aqiOfflineMinutes > 5;
                const timePart = lastUpdate.toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', hour12: true });
                aqiLastUpdateTimeStr = aqiOfflineMinutes >= 1440
                    ? lastUpdate.toLocaleDateString('en-IN', { timeZone: 'Asia/Kolkata', month: 'short', day: 'numeric' }) + ', ' + timePart
                    : timePart;
            }
        }

        let isWeatherOffline = false;
        let weatherOfflineMinutes = 0;
        let weatherLastUpdateTimeStr = "--";

        if (weatherData && (weatherData.timestamp || weatherData.created_at)) {
            const wRawTs = weatherData.timestamp || weatherData.created_at;
            const wLastUpdate = (typeof safeParseDate === 'function') ? safeParseDate(wRawTs) : new Date(wRawTs);
            if (wLastUpdate && !isNaN(wLastUpdate.getTime())) {
                const wDiffMs = now - wLastUpdate;
                weatherOfflineMinutes = Math.floor(wDiffMs / 60000);
                isWeatherOffline = weatherOfflineMinutes > 5;
                const wTimePart = wLastUpdate.toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', hour12: true });
                weatherLastUpdateTimeStr = weatherOfflineMinutes >= 1440
                    ? wLastUpdate.toLocaleDateString('en-IN', { timeZone: 'Asia/Kolkata', month: 'short', day: 'numeric' }) + ', ' + wTimePart
                    : wTimePart;
            }
        }

        const isOverallOffline = isAqiOffline && isWeatherOffline;
        const liveBanner = document.getElementById('live-offline-banner');
        if (liveBanner) liveBanner.style.display = isOverallOffline ? 'flex' : 'none';
        
        const liveOffTime = document.getElementById('live-offline-last-update');
        if (liveOffTime) liveOffTime.innerText = aqiLastUpdateTimeStr !== '--' ? aqiLastUpdateTimeStr : weatherLastUpdateTimeStr;

        const liveBeaconBadge = document.getElementById('live-beacon-badge');
        if (liveBeaconBadge) {
            if (isOverallOffline) {
                liveBeaconBadge.style.backgroundColor = '#fff7ed';
                liveBeaconBadge.style.color = '#c2410c';
                liveBeaconBadge.style.borderColor = '#fed7aa';
                liveBeaconBadge.innerHTML = `<span style="width: 6px; height: 6px; border-radius: 50%; background-color: #f97316;"></span> OFFLINE`;
            } else {
                liveBeaconBadge.style.backgroundColor = '#ecfdf5';
                liveBeaconBadge.style.color = '#059669';
                liveBeaconBadge.style.borderColor = '#a7f3d0';
                liveBeaconBadge.innerHTML = `<span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10b981;"></span> LIVE`;
            }
        }

        const liveAccent = (currentAQITheme && currentAQITheme.accent) ? currentAQITheme.accent : '#59b61f';
        const statusDotContainer = document.getElementById('status-dot-container');
        const heroDot = document.getElementById('status-dot');
        const heroDotHalo = document.getElementById('status-dot-halo');
        const heroStreamText = document.getElementById('hero-stream-text');

        if (isAqiOffline) {
            if (statusDotContainer) {
                statusDotContainer.classList.remove('status-dot-glowing');
                statusDotContainer.classList.add('status-dot-offline');
                statusDotContainer.style.color = '#94a3b8';
            }
            if (heroDot) {
                heroDot.style.backgroundColor = '#94a3b8';
                heroDot.style.color = '#94a3b8';
            }
            if (heroDotHalo) {
                heroDotHalo.style.backgroundColor = 'rgba(148, 163, 184, 0.20)';
                heroDotHalo.style.color = '#94a3b8';
            }
            if (heroStreamText) heroStreamText.innerText = 'Cached AQI';
        } else {
            if (statusDotContainer) {
                statusDotContainer.classList.remove('status-dot-offline');
                statusDotContainer.classList.add('status-dot-glowing');
                statusDotContainer.style.color = liveAccent;
            }
            if (heroDot) {
                heroDot.style.backgroundColor = liveAccent;
                heroDot.style.color = liveAccent;
            }
            if (heroDotHalo) {
                heroDotHalo.style.backgroundColor = liveAccent + '33';
                heroDotHalo.style.color = liveAccent;
            }
            if (heroStreamText) heroStreamText.innerText = 'Live AQI';
        }

        const latestTimeEl = document.getElementById('latest-time');
        const heroLatestTimeEl = document.getElementById('hero-latest-time');
        const heroTimeChip = document.getElementById('hero-live-time-chip');
        const liveActiveTimeStr = aqiLastUpdateTimeStr !== '--' ? aqiLastUpdateTimeStr : weatherLastUpdateTimeStr;
        if (latestTimeEl && liveActiveTimeStr !== '--') {
            latestTimeEl.innerText = liveActiveTimeStr;
        }
        if (heroLatestTimeEl && liveActiveTimeStr !== '--') {
            heroLatestTimeEl.innerText = liveActiveTimeStr;
        }
        if (heroTimeChip) {
            if (isOverallOffline) {
                heroTimeChip.style.color = '#9a3412';
                heroTimeChip.style.borderColor = '#fed7aa';
                heroTimeChip.style.background = 'rgba(255, 247, 237, 0.9)';
            } else {
                heroTimeChip.style.color = '#475569';
                heroTimeChip.style.borderColor = 'rgba(226, 232, 240, 0.9)';
                heroTimeChip.style.background = 'rgba(255, 255, 255, 0.85)';
            }
        }

        const isOverallLive = (!isAqiOffline || !isWeatherOffline);
        const minOfflineMins = Math.min(
            (aqiData && (aqiData.timestamp || aqiData.created_at)) ? aqiOfflineMinutes : 99999,
            (weatherData && (weatherData.timestamp || weatherData.created_at)) ? weatherOfflineMinutes : 99999
        );
        const tip = isOverallLive
            ? 'Live telemetry streaming from cloud'
            : `Sensor offline: Last received ${minOfflineMins >= 1440 ? Math.floor(minOfflineMins/1440) + 'd ago' : (minOfflineMins >= 60 ? Math.floor(minOfflineMins/60) + 'h ago' : minOfflineMins + 'm ago')}`;
        if (window.updateGlobalLiveBadge) {
            window.updateGlobalLiveBadge(isOverallLive, tip);
        }

        // LIVE AQI HISTORY (for chart and table)
        const hist = historyData || bulkData.aqi_history || bulkData.history;
        if (hist && hist.length > 0) {
            liveDataStream = hist;
            window.__liveDataStream = liveDataStream;
        }
        if (weatherHistory && weatherHistory.length > 0) {
            window.weatherHistory = weatherHistory;
        }
        try {
            if (typeof renderComparisonChart === 'function') renderComparisonChart();
        } catch(chartErr) {
            console.warn("Comparison chart error:", chartErr);
        }
        try {
            if (typeof renderSoilLiveTrendChart === 'function') renderSoilLiveTrendChart();
        } catch(soilChartErr) {
            console.warn("Soil live chart error:", soilChartErr);
        }
        try {
            renderLiveTable();
        } catch(tableErr) {
            console.warn("Live table error:", tableErr);
        }

        if (window.lucide && lucide.createIcons) {
            lucide.createIcons();
        }
    } catch (e) {
        console.error("Live page error:", e);
    }
}

var autoRefreshInterval = null;
var onLiveVisibilityChange = function() {
    if (!document.hidden) {
        refreshDashboard(true);
    }
};

window.__cleanupCurrentPage = function() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
    document.removeEventListener('visibilitychange', onLiveVisibilityChange);
    if (weatherTrendChartInstance) {
        try { weatherTrendChartInstance.destroy(); } catch(e) {}
        weatherTrendChartInstance = null;
        window.__weatherTrendChartInstance = null;
    }
    if (aqiTrendChartInstance) {
        try { aqiTrendChartInstance.destroy(); } catch(e) {}
        aqiTrendChartInstance = null;
        window.__aqiTrendChartInstance = null;
    }
    if (soilLiveChartInstance) {
        try { soilLiveChartInstance.destroy(); } catch(e) {}
        soilLiveChartInstance = null;
        window.__soilLiveChartInstance = null;
    }
    if (window.__chartInstances) {
        Object.keys(window.__chartInstances).forEach(k => {
            if (window.__chartInstances[k]) {
                try { window.__chartInstances[k].destroy(); } catch(e) {}
            }
        });
        window.__chartInstances = {};
    }
    if (liveCanvasAnimId) {
        cancelAnimationFrame(liveCanvasAnimId);
        liveCanvasAnimId = null;
    }
    if (liveCanvasResizeHandler) {
        window.removeEventListener('resize', liveCanvasResizeHandler);
        liveCanvasResizeHandler = null;
    }
    const bodyModals = document.querySelectorAll('body > #pollutant-modal');
    bodyModals.forEach(m => m.remove());
    document.body.style.overflow = '';
    document.body.classList.remove('weather-backdrop-active');
    const lr = document.getElementById('app-layout-root');
    if (lr) {
        lr.style.removeProperty('background');
        lr.style.removeProperty('background-image');
        lr.style.backgroundColor = '#fde68a';
        lr.style.backgroundImage = 'radial-gradient(ellipse 120% 70% at 50% -10%, #fde047 0%, #fde68a 35%, #fef08a 65%, #fde68a 100%)';
    }
};

var liveCanvasAnimId = null;
var liveCanvasResizeHandler = null;
window.__node2WeatherTheme = 'cloudy';
window.__node2TargetBlend = 1.0;
window.__node2CurrentBlend = 1.0;

window.setWeatherBackdropTheme = function(theme) {
    window.__node2WeatherTheme = 'cloudy';
    window.__node2TargetBlend = 1.0;

    const backdrop = document.getElementById('node2-weather-backdrop');
    if (backdrop) {
        backdrop.classList.remove('sunny');
        backdrop.classList.add('cloudy');
    }

    const pillIcon = document.getElementById('weather-ambient-mode-icon');
    const pillText = document.getElementById('weather-ambient-mode-text');
    const pillBadge = document.getElementById('weather-ambient-mode-badge');
    if (pillIcon) pillIcon.innerText = '☁️';
    if (pillText) pillText.innerText = 'Atmosphere: Dynamic Sunset Sky';
    if (pillBadge) {
        pillBadge.innerText = 'NATURAL SKY';
        pillBadge.style.backgroundColor = '#e2e8f0';
        pillBadge.style.color = '#475569';
    }
};

window.toggleWeatherBackdropTheme = function() {
    window.setWeatherBackdropTheme('cloudy');
};

window.evaluateNode2WeatherTheme = function(temp, hum, rain) {
    window.setWeatherBackdropTheme('cloudy');
};

function initNode2WeatherBackdrop() {
    const canvas = document.getElementById('weather-sky-canvas') || document.getElementById('live-ambient-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    if (liveCanvasAnimId) cancelAnimationFrame(liveCanvasAnimId);
    if (liveCanvasResizeHandler) {
        window.removeEventListener('resize', liveCanvasResizeHandler);
    }

    function resize() {
        if (!canvas) return;
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        initElements();
    }
    liveCanvasResizeHandler = resize;
    window.addEventListener('resize', liveCanvasResizeHandler);

    // ─────────────────────────────────────────────────────────────
    // REALISTIC VOLUMETRIC SUNSET CLOUD ENGINE (Cloudy Mode)
    // Matches Reference Design: Volumetric Cumulus + Glowing Crests
    // ─────────────────────────────────────────────────────────────
    let clouds = [];
    let wisps = [];
    let twilightMotes = [];

    // Pre-render soft Gaussian cloud puff brushes (Zero sharp circle edges)
    function makeGaussianPuff(r, g, b, maxAlpha) {
        const s = 140;
        const off = document.createElement('canvas');
        off.width = s;
        off.height = s;
        const octx = off.getContext('2d');
        const imgData = octx.createImageData(s, s);
        const data = imgData.data;
        const half = s / 2;
        for (let y = 0; y < s; y++) {
            for (let x = 0; x < s; x++) {
                const dx = (x - half) / half;
                const dy = (y - half) / half;
                const distSq = dx * dx + dy * dy;
                if (distSq < 1.0) {
                    const d = Math.sqrt(distSq);
                    const falloff = 0.5 * (1 + Math.cos(Math.PI * d));
                    const a = falloff * falloff * maxAlpha;
                    const idx = (y * s + x) * 4;
                    data[idx] = r;
                    data[idx + 1] = g;
                    data[idx + 2] = b;
                    data[idx + 3] = Math.round(a * 255);
                }
            }
        }
        octx.putImageData(imgData, 0, 0);
        return off;
    }

    const sprCrest = makeGaussianPuff(255, 185, 138, 0.40);
    const sprPeak = makeGaussianPuff(255, 238, 220, 0.46);
    const sprMauve = makeGaussianPuff(182, 135, 150, 0.38);
    const sprBase = makeGaussianPuff(48, 54, 70, 0.48);

    class DriftingSunsetCloud {
        constructor(layer, initSpread = true) {
            this.layer = layer; // 0 = distant haze, 1 = midground, 2 = foreground
            this.reset(initSpread);
        }

        reset(initSpread = false) {
            const w = canvas.width;
            const h = canvas.height;
            const isMobile = (w < 640);
            const scale = (isMobile ? 0.70 : 1.0) * ((this.layer === 0) ? (0.75 + Math.random() * 0.25) :
                          (this.layer === 1) ? (1.05 + Math.random() * 0.35) :
                                               (1.40 + Math.random() * 0.40));

            this.width = (320 + Math.random() * 240) * scale;
            this.height = this.width * (0.32 + Math.random() * 0.08);
            this.x = initSpread ? (Math.random() * (w + this.width * 2) - this.width) : (-this.width - Math.random() * 120);

            if (this.layer === 0) {
                this.y = (0.14 + Math.random() * 0.26) * h;
                this.speed = 0.16 + Math.random() * 0.08;
                this.alpha = 0.55 + Math.random() * 0.15;
            } else if (this.layer === 1) {
                this.y = (0.26 + Math.random() * 0.30) * h;
                this.speed = 0.28 + Math.random() * 0.10;
                this.alpha = 0.75 + Math.random() * 0.12;
            } else {
                this.y = (0.38 + Math.random() * 0.30) * h;
                this.speed = 0.42 + Math.random() * 0.12;
                this.alpha = 0.88 + Math.random() * 0.10;
            }

            this.phase = Math.random() * Math.PI * 2;
            this.puffs = [];

            // 1. Underbelly base puffs (flat condensation base with dark slate shade)
            const nBase = isMobile ? 14 : 22;
            for (let i = 0; i < nBase; i++) {
                const rx = (i / (nBase - 1) - 0.5) * (this.width * 0.86) + (Math.random() - 0.5) * 25;
                const ry = this.height * 0.16 + (Math.random() - 0.5) * (this.height * 0.12);
                const sz = (this.height * 0.55) + Math.random() * (this.height * 0.30);
                this.puffs.push({ x: rx, y: ry, size: sz, spr: sprBase, phase: Math.random() * Math.PI * 2, pulse: sz * 0.05 });
            }

            // 2. Mid volumetric body puffs
            const nMid = isMobile ? 18 : 28;
            for (let i = 0; i < nMid; i++) {
                const rx = ((i + 0.5) / nMid - 0.5) * (this.width * 0.74) + (Math.random() - 0.5) * 28;
                const ry = (Math.random() - 0.5) * (this.height * 0.18);
                const sz = (this.height * 0.60) + Math.random() * (this.height * 0.35);
                this.puffs.push({ x: rx, y: ry, size: sz, spr: sprMauve, phase: Math.random() * Math.PI * 2, pulse: sz * 0.06 });
            }

            // 3. Sunlit cauliflower top crests (golden peach & apricot)
            const nCrest = isMobile ? 14 : 20;
            for (let i = 0; i < nCrest; i++) {
                const rx = ((i + 0.5) / nCrest - 0.5) * (this.width * 0.66) + (Math.random() - 0.5) * 22;
                const ry = -this.height * 0.18 - Math.random() * (this.height * 0.24);
                const sz = (this.height * 0.50) + Math.random() * (this.height * 0.30);
                this.puffs.push({ x: rx, y: ry, size: sz, spr: (Math.random() > 0.35) ? sprCrest : sprPeak, phase: Math.random() * Math.PI * 2, pulse: sz * 0.05 });
            }
        }

        update(t) {
            this.x += this.speed;
            this.phase += 0.005;
            for (const p of this.puffs) {
                p.curSize = p.size + Math.sin(p.phase + t * 0.8) * p.pulse;
            }
            if (this.x > canvas.width + this.width) {
                this.reset(false);
            }
        }

        draw(ctx, opacity) {
            const effAlpha = this.alpha * opacity;
            if (effAlpha <= 0.02) return;
            const cy = this.y + Math.sin(this.phase) * 3;
            ctx.save();
            ctx.globalAlpha = effAlpha;
            for (const p of this.puffs) {
                const s = p.curSize || p.size;
                ctx.drawImage(p.spr, this.x + p.x - s * 0.5, cy + p.y - s * 0.5, s, s);
            }
            ctx.restore();
        }
    }

    // Horizontal Sunset Stratus Wisps (Matches low horizon streaks in reference)
    class SunsetStratusWisp {
        constructor(initSpread = true) {
            this.reset(initSpread);
        }
        reset(initSpread = false) {
            const w = canvas.width;
            const h = canvas.height;
            const isMobile = (w < 640);
            this.width = (isMobile ? 200 : 260) + Math.random() * (isMobile ? 180 : 300);
            this.height = (isMobile ? 12 : 16) + Math.random() * (isMobile ? 12 : 18);
            this.x = initSpread ? (Math.random() * (w + this.width) - this.width * 0.5) : (-this.width - Math.random() * 80);
            this.y = (0.50 + Math.random() * 0.38) * h;
            this.speed = 0.44 + Math.random() * 0.18;
            this.alpha = 0.50 + Math.random() * 0.22;
            this.phase = Math.random() * Math.PI * 2;
        }
        update(t) {
            this.x += this.speed;
            this.phase += 0.006;
            if (this.x > canvas.width + this.width) this.reset(false);
        }
        draw(ctx, opacity) {
            const effAlpha = this.alpha * opacity;
            if (effAlpha <= 0.02) return;
            const cy = this.y + Math.sin(this.phase) * 2;
            ctx.save();
            ctx.globalAlpha = effAlpha;
            const steps = 12;
            for (let i = 0; i < steps; i++) {
                const rx = (i / (steps - 1) - 0.5) * (this.width * 0.85);
                const puffH = this.height * (0.8 + Math.random() * 0.4);
                const puffW = puffH * 3.6;
                ctx.drawImage(sprCrest, this.x + rx - puffW * 0.5, cy - puffH * 0.5, puffW, puffH);
            }
            ctx.restore();
        }
    }

    function initElements() {
        clouds = [];
        const isMobile = (canvas.width < 640);
        const numPerLayer = isMobile ? 2 : 3;
        for (let l = 0; l < 3; l++) {
            for (let c = 0; c < numPerLayer; c++) {
                clouds.push(new DriftingSunsetCloud(l, true));
            }
        }

        wisps = [];
        const numWisps = isMobile ? 4 : 7;
        for (let s = 0; s < numWisps; s++) {
            wisps.push(new SunsetStratusWisp(true));
        }

        twilightMotes = Array.from({ length: isMobile ? 20 : 35 }, () => ({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height,
            r: Math.random() * 2.2 + 0.8,
            speedX: (Math.random() - 0.3) * 0.4,
            speedY: -(Math.random() * 0.35 + 0.15),
            alpha: Math.random() * 0.6 + 0.2,
            pulse: Math.random() * Math.PI * 2
        }));
    }

    resize();

    let tick = 0;
    function render() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        tick += 0.02;

        // Render natural sunset cumulus clouds & stratus wisps across sky (no sun overlay)
        const cloudyWeight = 1.0;
        if (cloudyWeight > 0.015) {
            for (const cloud of clouds) {
                cloud.update(tick);
                cloud.draw(ctx, cloudyWeight);
            }
            for (const wisp of wisps) {
                wisp.update(tick);
                wisp.draw(ctx, cloudyWeight);
            }
            // Ambient twilight floating motes
            ctx.save();
            for (const tm of twilightMotes) {
                tm.pulse += 0.025;
                const alpha = (tm.alpha + Math.sin(tm.pulse) * 0.15) * cloudyWeight;
                if (alpha > 0.04) {
                    ctx.beginPath();
                    ctx.arc(tm.x, tm.y, tm.r, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(254, 215, 170, ${alpha * 0.75})`;
                    ctx.fill();
                }
                tm.x += tm.speedX;
                tm.y += tm.speedY;
                if (tm.y < -10) { tm.y = canvas.height + 10; tm.x = Math.random() * canvas.width; }
                if (tm.x < -10) tm.x = canvas.width + 10;
                if (tm.x > canvas.width + 10) tm.x = -10;
            }
            ctx.restore();
        }

        liveCanvasAnimId = requestAnimationFrame(render);
    }
    render();
}

window.initLiveAmbientCanvas = initNode2WeatherBackdrop;

setTimeout(() => {
    refreshDashboard();
    initLiveAmbientCanvas();
    if (typeof renderComparisonChart === 'function') setTimeout(renderComparisonChart, 50);
    if (typeof renderLiveTable === 'function') setTimeout(renderLiveTable, 50);
    
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    autoRefreshInterval = setInterval(() => {
        if (!document.hidden) {
            refreshDashboard(true);
        }
    }, 7000);

    document.addEventListener('visibilitychange', onLiveVisibilityChange);
}, 0);

window.openPollutantModal = function(type) {
    const data = pollutantDetails[type];
    if(!data) return;
    
    const allModals = document.querySelectorAll('#pollutant-modal');
    if (allModals.length > 1) {
        for (let i = 0; i < allModals.length - 1; i++) {
            allModals[i].remove();
        }
    }
    
    const modal = document.getElementById('pollutant-modal');
    if (!modal) return;
    
    const iconBg = modal.querySelector('#pm-modal-icon-bg');
    if (iconBg) iconBg.innerText = data.icon;
    const titleEl = modal.querySelector('#pm-modal-title');
    if (titleEl) titleEl.innerText = data.title;
    const limitEl = modal.querySelector('#pm-modal-limit');
    if (limitEl) limitEl.innerText = `${data.limit} ${data.unit}`;
    
    let currentValStr = '--';
    const el = document.getElementById(`card-${type}`);
    currentValStr = el ? el.innerText : '--';
    const currentVal = parseFloat(currentValStr);
    
    const valEl = modal.querySelector('#pm-modal-val');
    if (valEl) valEl.innerText = isNaN(currentVal) ? currentValStr : currentVal.toFixed(2);
    const unitEl = modal.querySelector('#pm-modal-unit');
    if (unitEl) unitEl.innerText = data.unit;
    const shortEl = modal.querySelector('#pm-modal-short');
    if (shortEl) shortEl.innerText = data.id.toUpperCase();
    const qEl = modal.querySelector('#pm-modal-q');
    if (qEl) qEl.innerText = data.id.toUpperCase();
    const descEl = modal.querySelector('#pm-modal-desc');
    if (descEl) descEl.innerText = data.desc;
    const healthEl = modal.querySelector('#pm-modal-health');
    if (healthEl) healthEl.innerText = data.health;
    
    const t1 = modal.querySelector('#pm-t1'); if (t1) t1.innerText = data.scale[0];
    const t2 = modal.querySelector('#pm-t2'); if (t2) t2.innerText = data.scale[1];
    const t3 = modal.querySelector('#pm-t3'); if (t3) t3.innerText = data.scale[2];
    const t4 = modal.querySelector('#pm-t4'); if (t4) t4.innerText = data.scale[3];
    const t5 = modal.querySelector('#pm-t5'); if (t5) t5.innerText = data.scale[4];
    const t6 = modal.querySelector('#pm-t6'); if (t6) t6.innerText = data.maxTick;
    
    const statusBox = modal.querySelector('#pm-modal-status');
    if (statusBox) {
        if(isNaN(currentVal)) {
            statusBox.style.display = 'none';
        } else {
            statusBox.style.display = 'flex';
            if(currentVal <= data.limit) {
                statusBox.innerHTML = '<i data-lucide="check-circle-2" style="width: 17px; height: 17px;"></i> Optimal / Normal Range';
                statusBox.style.background = '#dcfce7';
                statusBox.style.color = '#16a34a';
            } else {
                statusBox.innerHTML = '<i data-lucide="alert-triangle" style="width: 17px; height: 17px;"></i> Elevated / Above Standard';
                statusBox.style.background = '#fee2e2';
                statusBox.style.color = '#dc2626';
            }
        }
    }
    
    const marker = modal.querySelector('#pm-modal-marker');
    const scaleInfo = getPollutantScaleInfo(type, currentVal);
    if (marker) {
        marker.style.left = `clamp(8px, ${scaleInfo.pct}%, calc(100% - 8px))`;
        marker.style.borderColor = scaleInfo.color;
    }
    
    // Render pollutant's own limits and categories inside the modal
    const scaleConfig = pollutantCategoryRanges[type];
    const scaleHeading = modal.querySelector('#pm-modal-scale-heading');
    const scaleSub = modal.querySelector('#pm-modal-scale-sub');
    const scaleList = modal.querySelector('#pm-modal-scale-list');
    
    if (scaleConfig && scaleHeading && scaleSub && scaleList) {
        scaleHeading.innerText = scaleConfig.title;
        scaleSub.innerText = scaleConfig.sub;
        scaleList.innerHTML = scaleConfig.ranges.map(item => `
            <div class="aqi-scale-item" style="display: flex; align-items: center; padding: 11px 13px; border-radius: 14px; background-color: ${item.bg}; border: 1px solid ${item.border}; gap: 10px;">
                <span class="scale-dot" style="width: 10px; height: 10px; border-radius: 3px; background-color: ${item.dot}; flex-shrink: 0;"></span>
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span class="scale-title" style="font-size: 13.5px; font-weight: 700; color: #0f172a;">${item.name}</span>
                        <span class="scale-range" style="font-size: 12px; font-weight: 700; color: ${item.textCol}; font-family: var(--font-mono);">(${item.range} ${scaleConfig.unit})</span>
                    </div>
                    <div class="scale-desc" style="font-size: 11.5px; color: #475569; margin-top: 2px; line-height: 1.4;">${item.desc}</div>
                </div>
            </div>
        `).join('');
    }
    
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    const scrollBox = modal.querySelector('.pollutant-modal-scroll');
    if (scrollBox) scrollBox.scrollTop = 0;
    
    if (window.lucide && lucide.createIcons) lucide.createIcons({ root: modal });
};
var openPollutantModal = window.openPollutantModal;

window.closePollutantModal = function(e) {
    if (e) {
        if (typeof e.preventDefault === 'function') e.preventDefault();
        if (typeof e.stopPropagation === 'function') e.stopPropagation();
    }
    const modals = document.querySelectorAll('#pollutant-modal');
    modals.forEach(m => {
        m.style.display = 'none';
    });
    document.body.style.overflow = '';
};
var closePollutantModal = window.closePollutantModal;

if (!window.__pollutantModalListenersBound) {
    window.__pollutantModalListenersBound = true;
    
    const handlePollutantCloseTrigger = function(e) {
        const closeBtn = e.target && e.target.closest && e.target.closest('.pollutant-modal-close');
        const backdrop = e.target && (
            (e.target.classList && e.target.classList.contains('pollutant-modal-backdrop')) ||
            e.target.id === 'pollutant-modal-backdrop'
        );
        if (closeBtn || backdrop) {
            if (typeof e.preventDefault === 'function') e.preventDefault();
            if (typeof e.stopPropagation === 'function') e.stopPropagation();
            if (typeof window.closePollutantModal === 'function') {
                window.closePollutantModal(e);
            }
        }
    };

    document.addEventListener('click', handlePollutantCloseTrigger, true);
    document.addEventListener('touchend', handlePollutantCloseTrigger, { capture: true, passive: false });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            if (typeof window.closePollutantModal === 'function') {
                window.closePollutantModal(e);
            }
        }
    });
}

// ══ Interactive Pointer & Touch Scrubbing for All Moving Gradient Bars ══
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.live-slider-track').forEach(function(track) {
        let isScrubbing = false;
        const dot = track.querySelector('[id$="-dot"], #hero-scale-indicator, #pm-modal-marker');
        if (!dot) return;

        function updatePosition(e) {
            const rect = track.getBoundingClientRect();
            if (rect.width <= 0) return;
            const clientX = (e.touches && e.touches.length > 0) ? e.touches[0].clientX : e.clientX;
            const pct = Math.max(5, Math.min(95, Math.round(((clientX - rect.left) / rect.width) * 100)));
            dot.classList.remove('bar-dot-standby-scan');
            dot.style.left = `${pct}%`;
        }

        track.addEventListener('mousedown', function(e) {
            isScrubbing = true;
            updatePosition(e);
        });

        window.addEventListener('mousemove', function(e) {
            if (!isScrubbing) return;
            updatePosition(e);
        });

        window.addEventListener('mouseup', function() {
            if (isScrubbing) {
                isScrubbing = false;
            }
        });

        track.addEventListener('touchstart', function(e) {
            isScrubbing = true;
            updatePosition(e);
        }, { passive: true });

        window.addEventListener('touchmove', function(e) {
            if (!isScrubbing) return;
            updatePosition(e);
        }, { passive: true });

        window.addEventListener('touchend', function() {
            isScrubbing = false;
        });
    });
});
</script>

<!-- Pollutant Detail Modal (Mounted directly to viewport) -->
<div id="pollutant-modal" style="display: none; position: fixed; inset: 0; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; z-index: 99999; align-items: center; justify-content: center; padding: clamp(12px, 3vw, 24px); box-sizing: border-box;">
  <!-- Backdrop Click to Close -->
  <div id="pollutant-modal-backdrop" class="pollutant-modal-backdrop" style="position: absolute; inset: 0; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); cursor: pointer;" onclick="window.closePollutantModal(event)"></div>

  <!-- Modal Dialog Card -->
  <div class="pollutant-modal-card" style="position: relative; z-index: 1; background: #ffffff; width: 100%; max-width: 580px; max-height: min(88vh, 720px); border-radius: 24px; box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.3); border: 1px solid #e2e8f0; overflow: hidden; display: flex; flex-direction: column; margin: auto; animation: modal-pop 0.22s cubic-bezier(0.16, 1, 0.3, 1);">
    <button type="button" class="pollutant-modal-close" onclick="window.closePollutantModal(event)" title="Close details" aria-label="Close" style="position: absolute; top: 16px; right: 16px; z-index: 50; width: 36px; height: 36px; min-width: 36px; min-height: 36px; border-radius: 50%; border: 1px solid #e2e8f0; background: #ffffff; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: all 0.2s ease; touch-action: manipulation; -webkit-tap-highlight-color: transparent; pointer-events: auto;" onmouseover="this.style.background='#f8fafc'; this.style.color='#0f172a';" onmouseout="this.style.background='#ffffff'; this.style.color='#64748b';">
      <i data-lucide="x" style="width: 18px; height: 18px; pointer-events: none;"></i>
    </button>
    
    <div class="pollutant-modal-scroll" style="padding: clamp(20px, 4vw, 32px); overflow-y: auto; overscroll-behavior: contain; flex: 1;">
        <!-- Header -->
        <div style="display: flex; gap: 16px; align-items: flex-start; margin-bottom: 22px; padding-right: 36px;">
            <div id="pm-modal-icon-bg" style="width: 54px; height: 54px; border-radius: 16px; background: #f0fdf4; display: flex; align-items: center; justify-content: center; font-size: 26px; flex-shrink: 0; box-shadow: inset 0 2px 4px rgba(255,255,255,0.5); border: 1px solid #e2e8f0;">🌫️</div>
            <div>
                <h2 id="pm-modal-title" style="margin: 0 0 4px 0; font-size: 20px; font-weight: 700; color: #0f172a; font-family: var(--font-sans); line-height: 1.25;">Particulate Matter 2.5 (PM2.5)</h2>
                <div style="font-size: 13.5px; font-weight: 600; color: #64748b;">Indian Standard (CPCB Limit): <span id="pm-modal-limit" style="color: #00bfa5;">60 µg/m³</span></div>
            </div>
        </div>

        <!-- Current Reading Box -->
        <div class="pm-reading-box" style="border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); background: #ffffff;">
            <div>
                <div style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">CURRENT READING</div>
                <div style="display: flex; align-items: baseline; gap: 8px;">
                    <div id="pm-modal-val" style="font-size: 38px; font-weight: 900; color: #0f172a; line-height: 1; font-family: var(--font-mono);">7.09</div>
                    <div id="pm-modal-unit" style="font-size: 20px; font-weight: 700; color: #0f172a; font-family: var(--font-mono);">µg/m³</div>
                </div>
            </div>
            <div id="pm-modal-status" style="display: flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; background: #dcfce7; color: #16a34a; font-size: 13px; font-weight: 700;">
                <i data-lucide="check-circle-2" style="width: 17px; height: 17px;"></i> Optimal / Normal Range
            </div>
        </div>

        <!-- Concentration Scale Box -->
        <div class="pm-scale-box" style="border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px 20px; margin-bottom: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); background: #ffffff;">
            <div class="pm-scale-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; gap: 8px; flex-wrap: wrap;">
                <div class="pm-scale-title" style="font-size: 11.5px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; font-family: var(--font-mono);"><span id="pm-modal-short">PM2.5</span> CONCENTRATION SCALE (CPCB)</div>
                <div class="pm-scale-badge" style="font-size: 11.5px; font-weight: 700; color: #00bfa5;">Indian NAQI Standard</div>
            </div>
            
            <div style="position: relative; width: 100%;">
                <div class="pm-scale-labels" style="display: grid; grid-template-columns: repeat(6, 1fr); margin-bottom: 8px; font-size: 9.5px; font-weight: 700; line-height: 1;">
                    <div style="color: #475569; text-align: left;">Good</div>
                    <div style="color: #475569; text-align: center;">Moderate</div>
                    <div style="color: #475569; text-align: center;">Poor</div>
                    <div style="color: #475569; text-align: center;">Unhealthy</div>
                    <div style="color: #475569; text-align: center;">Severe</div>
                    <div style="color: #475569; text-align: right;">Hazardous</div>
                </div>
                
                <div class="pm-scale-track live-slider-track" style="position: relative; height: 6px; border-radius: 999px; background-color: #e2e8f0; margin-bottom: 8px;">
                    <div style="width: 100%; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #59b61f 0%, #59b61f 16.666%, #eec732 16.666%, #eec732 33.333%, #ea8c34 33.333%, #ea8c34 50%, #e95478 50%, #e95478 66.666%, #b33fba 66.666%, #b33fba 83.333%, #c92033 83.333%, #c92033 100%); box-shadow: inset 0 1px 2px rgba(0,0,0,0.06);"></div>
                    <div id="pm-modal-marker" style="position: absolute; top: 50%; left: 10%; transform: translate(-50%, -50%); width: 16px; height: 16px; border-radius: 50%; background: #ffffff; border: 3.5px solid #59b61f; box-shadow: 0 1px 4px rgba(0,0,0,0.25); z-index: 3; transition: left 0.4s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.3s ease;"></div>
                </div>
                
                <div id="pm-modal-ticks" class="pm-scale-ticks" style="position: relative; height: 16px; font-size: 10px; font-weight: 600; color: #64748b; font-family: var(--font-mono);">
                    <span style="position: absolute; left: 0%; line-height: 1;">0</span>
                    <span id="pm-t1" style="position: absolute; left: 16.666%; transform: translateX(-50%); line-height: 1;">30</span>
                    <span id="pm-t2" style="position: absolute; left: 33.333%; transform: translateX(-50%); line-height: 1;">60</span>
                    <span id="pm-t3" style="position: absolute; left: 50%; transform: translateX(-50%); line-height: 1;">90</span>
                    <span id="pm-t4" style="position: absolute; left: 66.666%; transform: translateX(-50%); line-height: 1;">120</span>
                    <span id="pm-t5" style="position: absolute; left: 83.333%; transform: translateX(-50%); line-height: 1;">250</span>
                    <span id="pm-t6" style="position: absolute; right: 0%; line-height: 1;">250+</span>
                </div>
            </div>
        </div>

        <!-- Info sections -->
        <div style="margin-bottom: 20px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
            <h3 style="font-size: 14.5px; font-weight: 700; color: #0f172a; margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="info" style="width: 16px; height: 16px; color: #00bfa5;"></i> What is <span id="pm-modal-q">PM2.5</span>?
            </h3>
            <p id="pm-modal-desc" style="font-size: 13.5px; color: #475569; line-height: 1.6; margin: 0;">
                Fine inhalable particles with diameters 2.5 micrometers or smaller.
            </p>
        </div>

        <div>
            <h3 style="font-size: 14.5px; font-weight: 700; color: #0f172a; margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="heart-pulse" style="width: 16px; height: 16px; color: #ef4444;"></i> Health &amp; Environmental Impact
            </h3>
            <p id="pm-modal-health" style="font-size: 13.5px; color: #475569; line-height: 1.6; margin: 0;">
                Causes respiratory inflammation and impacts sensitive individuals on prolonged exposure.
            </p>
        </div>

        <!-- Dynamic Pollutant Concentration Scale Section (Inside Modal) -->
        <div id="pm-modal-scale-section" style="margin-top: 22px; border-top: 1px solid #e2e8f0; padding-top: 18px;">
            <div style="margin-bottom: 12px;">
                <h3 style="font-size: 15.5px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; font-family: var(--font-sans);">
                    <i data-lucide="shield-alert" style="width: 18px; height: 18px; color: #00bfa5;"></i>
                    <span id="pm-modal-scale-heading">PM2.5 Scale (NAAQS Standard)</span>
                </h3>
                <p id="pm-modal-scale-sub" style="font-size: 12px; color: #64748b; margin-top: 4px; margin-bottom: 0; line-height: 1.4;">
                    National Ambient Air Quality Standard limits for PM2.5 in µg/m³.
                </p>
            </div>

            <div id="pm-modal-scale-list" style="display: flex; flex-direction: column; gap: 9px;">
                <!-- Populated dynamically per pollutant in openPollutantModal -->
            </div>
        </div>

    </div>
  </div>
</div>
