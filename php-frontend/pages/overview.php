<?php
// Retrieve cached bulk telemetry for 0ms instant first-paint on both desktop and mobile
$cache_candidates = [
    sys_get_temp_dir() . '/smartairnet_cache/' . md5('overview_bulk') . '.json',
    sys_get_temp_dir() . '/smartairnet_cache/' . md5('live_bulk_60') . '.json',
    sys_get_temp_dir() . '/smartairnet_cache/last_known_hero.json',
];
$init_bulk = null;
$best_epoch = 0;
foreach ($cache_candidates as $cf) {
    if (file_exists($cf)) {
        $raw_cache = @file_get_contents($cf);
        if ($raw_cache && strlen($raw_cache) > 20) {
            $parsed = @json_decode($raw_cache, true);
            if ($parsed) {
                if (isset($parsed['cpcb_aqi']) && !isset($parsed['aqi'])) {
                    $parsed = ['aqi' => $parsed];
                }
                $aqi_item = null;
                if (!empty($parsed['aqi'])) {
                    if (isset($parsed['aqi'][0]) && is_array($parsed['aqi'][0])) {
                        $aqi_item = $parsed['aqi'][0];
                    } elseif (is_array($parsed['aqi'])) {
                        $aqi_item = $parsed['aqi'];
                    }
                }
                $ts_val = $aqi_item['timestamp'] ?? $aqi_item['created_at'] ?? null;
                $e = $ts_val ? strtotime($ts_val) : (isset($parsed['time']) ? (int)$parsed['time'] : filemtime($cf));
                if ($e > $best_epoch && $aqi_item) {
                    $best_epoch = $e;
                    $init_bulk = $parsed;
                    if (isset($init_bulk['aqi'][0])) {
                        $init_bulk['aqi'] = $init_bulk['aqi'][0];
                    }
                }
            }
        }
    }
}

// AQI pre-calculated fields
$init_aqi = null;
$init_aqi_color = '#ea8c34';
$init_aqi_bg = '#fff7ed';
$init_aqi_border = 'rgba(234, 140, 52, 0.45)';
$init_aqi_text = '#9a3412';
$init_aqi_label = 'Poor';
$init_aqi_dom = 'PM2.5';
$init_aqi_bar_width = '30%';

if (!empty($init_bulk['aqi'])) {
    $aqi_data = $init_bulk['aqi'];
    if (isset($aqi_data[0]) && is_array($aqi_data[0])) $aqi_data = $aqi_data[0];
    $calc_v = isset($aqi_data['cpcb_aqi']) ? (int)round($aqi_data['cpcb_aqi']) : (isset($aqi_data['aqi_info']['value']) ? (int)round($aqi_data['aqi_info']['value']) : null);
    if ($calc_v !== null) {
        $init_aqi = $calc_v;
        $init_aqi_dom = !empty($aqi_data['dominant_pollutant']) ? htmlspecialchars($aqi_data['dominant_pollutant']) : 'PM2.5';
        
        if ($init_aqi <= 50) {
            $init_aqi_label = 'Good'; $init_aqi_color = '#59b61f'; $init_aqi_bg = '#f0fdf4'; $init_aqi_border = 'rgba(89, 182, 31, 0.4)'; $init_aqi_text = '#15803d';
        } elseif ($init_aqi <= 100) {
            $init_aqi_label = 'Moderate'; $init_aqi_color = '#eec732'; $init_aqi_bg = '#fefce8'; $init_aqi_border = 'rgba(238, 199, 50, 0.45)'; $init_aqi_text = '#854d0e';
        } elseif ($init_aqi <= 200) {
            $init_aqi_label = 'Poor'; $init_aqi_color = '#ea8c34'; $init_aqi_bg = '#fff7ed'; $init_aqi_border = 'rgba(234, 140, 52, 0.45)'; $init_aqi_text = '#9a3412';
        } elseif ($init_aqi <= 300) {
            $init_aqi_label = 'Unhealthy'; $init_aqi_color = '#e95478'; $init_aqi_bg = '#fff1f2'; $init_aqi_border = 'rgba(233, 84, 120, 0.45)'; $init_aqi_text = '#991b1b';
        } elseif ($init_aqi <= 400) {
            $init_aqi_label = 'Severe'; $init_aqi_color = '#b33fba'; $init_aqi_bg = '#faf5ff'; $init_aqi_border = 'rgba(179, 63, 186, 0.45)'; $init_aqi_text = '#6b21a8';
        } else {
            $init_aqi_label = 'Hazardous'; $init_aqi_color = '#c92033'; $init_aqi_bg = '#fef2f2'; $init_aqi_border = 'rgba(201, 32, 51, 0.45)'; $init_aqi_text = '#9f1239';
        }
        $init_aqi_bar_width = min(100, max(4, round(($init_aqi / 500) * 100))) . '%';
    }
}

// Weather pre-calculated fields
$init_temp = '--';
$init_temp_bar_width = '0%';

$init_hum = '--';
$init_hum_badge = '';
$init_hum_bar_width = '0%';

$init_wind = '--';
$init_wind_badge = 'Calm';
$init_wind_bar_width = '0%';
$init_wind_kmh = 0.0;

$init_live_rain = '--';
$init_today_rain = '--';

if (!function_exists('adjustTemperatureOverview')) {
    function adjustTemperatureOverview($temp, $tsRaw) {
        if ($temp === null || $temp === '') return null;
        $temp = (float)$temp;
        if (!$tsRaw) return round($temp, 1);
        try {
            $tsStr = trim((string)$tsRaw);
            if (strpos($tsStr, '+00') !== false || substr($tsStr, -1) === 'Z') {
                $dt = new DateTime($tsStr);
                $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            } elseif (preg_match('/([+-]\d{2}):?(\d{2})$/', $tsStr)) {
                $dt = new DateTime($tsStr);
                $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            } else {
                $dt = new DateTime($tsStr, new DateTimeZone('Asia/Kolkata'));
            }
            $hour = (int)$dt->format('G');
            $offset = 0.0;
            if ($hour === 8)                    $offset = -0.5;
            elseif ($hour === 9)                $offset = -1.0;
            elseif ($hour === 10)               $offset = -1.5;
            elseif ($hour >= 11 && $hour <= 16) $offset = -2.0;
            elseif ($hour === 17)               $offset = -1.5;
            elseif ($hour === 18)               $offset = -1.0;
            elseif ($hour === 19)               $offset = -0.5;
            return round($temp + $offset, 1);
        } catch (Exception $e) {
            return round($temp, 1);
        }
    }
}

if (!empty($init_bulk['weather'])) {
    $w = $init_bulk['weather'];
    if (isset($w['temperature']) && $w['temperature'] !== null) {
        $w_ts = $w['timestamp'] ?? $w['created_at'] ?? null;
        $adj_t = adjustTemperatureOverview($w['temperature'], $w_ts);
        $init_temp = number_format((float)$adj_t, 1, '.', '');
        $init_temp_bar_width = min(100, max(5, round((((float)$adj_t - 10) / 35) * 100))) . '%';
    }
    if (isset($w['humidity']) && $w['humidity'] !== null) {
        $init_hum = number_format((float)$w['humidity'], 1, '.', '');
        $init_hum_bar_width = min(100, max(5, round((float)$w['humidity']))) . '%';
    }
    if (isset($w['wind_speed']) && $w['wind_speed'] !== null) {
        $init_wind_kmh = (float)$w['wind_speed'];
        $init_wind = number_format($init_wind_kmh, 1, '.', '');
        $init_wind_badge = ($init_wind_kmh > 15) ? 'Breezy' : (($init_wind_kmh > 2) ? 'Gentle' : 'Light');
        $init_wind_bar_width = min(100, max(4, round(($init_wind_kmh / 30) * 100))) . '%';
    }
    if (isset($w['rain_gauge']) && $w['rain_gauge'] !== null) {
        $init_live_rain = number_format((float)$w['rain_gauge'], 1, '.', '');
    }
}

$init_is_raining = (!empty($init_bulk['weather']['rain_gauge']) && (float)$init_bulk['weather']['rain_gauge'] > 0);
$init_rain_bar_width = '0%';
if ($init_is_raining) {
    $init_rain_val = (float)$init_bulk['weather']['rain_gauge'];
    $init_rain_bar_width = min(100, max(8, round(($init_rain_val / 15) * 100))) . '%';
}

if (isset($init_bulk['today_rain_total']) && $init_bulk['today_rain_total'] !== null) {
    $init_today_rain = number_format((float)$init_bulk['today_rain_total'], 1, '.', '');
}

$aqi_ts = $init_bulk['aqi']['timestamp'] ?? ($init_bulk['aqi']['created_at'] ?? null);
$weather_ts = $init_bulk['weather']['timestamp'] ?? ($init_bulk['weather']['created_at'] ?? null);
$aqi_epoch = $aqi_ts ? strtotime($aqi_ts) : 0;
$weather_epoch = $weather_ts ? strtotime($weather_ts) : 0;
$latest_epoch = max($aqi_epoch, $weather_epoch);
$init_ts = $latest_epoch > 0 ? date('Y-m-d\TH:i:sP', $latest_epoch) : null;
$is_overview_offline = false;
$overview_offline_time_str = '--';
if ($latest_epoch > 0) {
    $diff_mins = max(0, (int)floor((time() - $latest_epoch) / 60));
    $time_ago = $diff_mins >= 1440 ? floor($diff_mins/1440) . 'd ago' : ($diff_mins >= 60 ? floor($diff_mins/60) . 'h ago' : $diff_mins . 'm ago');
    $overview_offline_time_str = date('h:i a', $latest_epoch) . ' • ' . $time_ago;
}
?>
<div class="overview-page-root" style="position: relative; min-height: auto; border-radius: 24px; overflow: hidden; padding: clamp(14px, 2.5vw, 20px); margin-bottom: 6px; background: radial-gradient(ellipse 120% 80% at 50% -10%, #e2e8f0 0%, #f1f5f9 60%, #e2e8f0 100%); color: #0f172a; font-family: var(--font-sans); border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04); animation: fade-in 0.4s ease-out;">
  
  <!-- Dynamic Ambient Canvas -->
  <canvas id="ambient-canvas" style="position: absolute; inset: 0; pointer-events: none; z-index: 1; opacity: 0.9;"></canvas>

  <!-- Floating Environmental Pastel Auras -->
  <div id="aura-top" style="position: absolute; top: -10%; right: -5%; width: 45vw; height: 45vw; max-width: 500px; max-height: 500px; border-radius: 50%; background: radial-gradient(circle, <?= $init_aqi_color ?>15 0%, rgba(241, 245, 249, 0) 70%); filter: blur(60px); pointer-events: none; z-index: 0; animation: breathAtmosphere 10s ease-in-out infinite alternate;"></div>
  
  <div style="position: absolute; bottom: -10%; left: -5%; width: 45vw; height: 45vw; max-width: 500px; max-height: 500px; border-radius: 50%; background: radial-gradient(circle, rgba(14, 165, 233, 0.12) 0%, rgba(241, 245, 249, 0) 70%); filter: blur(60px); pointer-events: none; z-index: 0;"></div>

  <!-- Content Container -->
  <div class="overview-content-container" style="position: relative; z-index: 2; display: flex; flex-direction: column; gap: 16px;">

    <!-- Overview Header: Title & Last Updated Status -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 10px; margin-bottom: 2px;">
      <div style="width: 100%;">
        <h1 class="overview-page-title" style="font-size: clamp(20px, 2.5vw, 26px); font-weight: 800; color: #0f172a; font-family: var(--font-sans); letter-spacing: -0.02em; margin: 0;">
          Campus Telemetry Overview
        </h1>
        <div class="overview-page-meta" style="font-size: 13px; color: #475569; margin-top: 6px; font-family: var(--font-sans); display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
          <span style="display: inline-flex; align-items: center; gap: 5px; font-weight: 600;">
            <i data-lucide="clock" style="width: 14px; height: 14px; color: #00bfa5;"></i>
            <span>Last Updated:</span>
            <span id="overview-latest-time" style="color: #0f172a; font-weight: 700; font-family: var(--font-mono); background: #ffffff; padding: 2px 8px; border-radius: 6px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(15,23,42,0.04);"><?= $latest_epoch > 0 ? date('h:i A', $latest_epoch) : 'Live Stream' ?></span>
          </span>
          <span id="overview-live-badge" style="display: inline-flex; align-items: center; gap: 5px; padding: 2.5px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; background-color: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;">
            <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10b981;"></span>
            LIVE
          </span>
        </div>
      </div>
    </div>

    <!-- Core Telemetry Cards Grid (5 Cards: AQI, Temp, Humidity, Wind, Rain) -->
    <div class="overview-grid <?= $init_is_raining ? 'rain-active' : '' ?>">
      
      <!-- CARD 1: AIR QUALITY INDEX (HERO ON MOBILE) -->
      <div class="overview-core-card overview-card-hero" id="card-aqi" style="border-color: <?= $init_aqi_border ?>;">
        <div id="aqi-grad" style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, <?= $init_aqi_color ?>, #06b6d4);"></div>
        <div class="overview-aqi-header-row" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
          <div style="display: flex; align-items: center; gap: 7px; min-width: 0;">
            <div id="aqi-icon-bg" class="overview-card-icon" style="width: 28px; height: 28px; border-radius: 8px; background-color: <?= $init_aqi_bg ?>; display: flex; align-items: center; justify-content: center; color: <?= $init_aqi_color ?>; border: 1px solid <?= $init_aqi_border ?>; flex-shrink: 0;">
              <i data-lucide="gauge" style="width: 15px; height: 15px;"></i>
            </div>
            <h4 class="overview-card-title" style="margin: 0; font-size: 13.5px; font-weight: 700; color: #0f172a; line-height: 1.15;">
              <span class="aqi-title-long">Air Quality Index</span>
              <span class="aqi-title-short" style="display: none;">Air Quality</span>
            </h4>
          </div>
          <span id="aqi-badge" class="overview-card-badge" style="padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; background-color: <?= $init_aqi_bg ?>; color: <?= $init_aqi_text ?>; border: 1px solid <?= $init_aqi_border ?>; display: inline-flex; align-items: center; gap: 5px; flex-shrink: 0;">
            <span id="aqi-badge-dot" style="width: 6px; height: 6px; border-radius: 50%; background-color: <?= $init_aqi_color ?>;"></span>
            <span id="aqi-badge-text"><?= $init_aqi_label ?></span>
          </span>
        </div>
        <div class="overview-aqi-val-row" style="display: flex; align-items: center; justify-content: space-between; margin: 6px 0; flex-wrap: wrap; gap: 8px;">
          <div style="display: flex; align-items: baseline; gap: 4px;">
            <span id="aqi-val" class="overview-card-val" style="font-size: 34px; font-weight: 900; line-height: 1; letter-spacing: -0.03em; color: <?= $init_aqi_color ?>; font-family: var(--font-mono);"><?= $init_aqi !== null ? $init_aqi : '--' ?></span>
            <span style="font-size: 12px; font-weight: 700; color: #94a3b8;">/ 500</span>
          </div>
          <div class="overview-aqi-dom-wrap" style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0; font-size: 11px; color: #64748b;">
            <span>Dominant:</span>
            <strong id="aqi-dom" style="color: #0f172a; font-family: var(--font-mono); font-weight: 800;"><?= $init_aqi_dom ?></strong>
          </div>
        </div>
        <div class="overview-aqi-bar-cont" style="margin-top: auto; padding-top: 8px;">
          <div style="height: 6px; border-radius: 999px; background: #f1f5f9; overflow: hidden; position: relative; margin-bottom: 4px;">
            <div id="aqi-bar" style="height: 100%; width: <?= $init_aqi_bar_width ?>; background: linear-gradient(90deg, #10b981 0%, #eab308 40%, <?= $init_aqi_color ?> 100%); border-radius: 999px; transition: width 0.8s ease;"></div>
          </div>
          <div class="overview-bar-scale" style="display: flex; justify-content: space-between; font-size: 10px; font-weight: 700; color: #64748b; font-family: var(--font-mono); margin-top: 4px;">
            <span>0 Good</span><span style="color: #cbd5e1;">•</span><span>100 Moderate</span><span style="color: #cbd5e1;">•</span><span>500 Hazardous</span>
          </div>
        </div>
      </div>

      <!-- CARD 2: AMBIENT TEMPERATURE -->
      <div class="overview-core-card" id="card-temp" style="border-color: #fed7aa;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #ea580c, #facc15);"></div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
          <div style="display: flex; align-items: center; gap: 6px; min-width: 0;">
            <div class="overview-card-icon" style="width: 26px; height: 26px; border-radius: 8px; background-color: #fff7ed; display: flex; align-items: center; justify-content: center; color: #ea580c; border: 1px solid #fed7aa; flex-shrink: 0;">
              <i data-lucide="thermometer" style="width: 14px; height: 14px;"></i>
            </div>
            <h4 class="overview-card-title" style="margin: 0; font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.15; white-space: nowrap;">Temperature</h4>
          </div>
          <button type="button" id="btn-temp" onclick="toggleTemp()" title="Switch temperature unit: °C / °F" class="segmented-unit-btn" style="background-color: #fff7ed; border-color: #fed7aa; color: #c2410c;">
            <span class="unit-opt active" id="unit-opt-c">°C</span>
            <span class="unit-opt" id="unit-opt-f">°F</span>
          </button>
        </div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin: 4px 0;">
          <div style="display: flex; align-items: baseline; gap: 3px;">
            <span id="temp-val" class="overview-card-val" style="font-size: 32px; font-weight: 900; line-height: 1; letter-spacing: -0.03em; color: #ea580c; font-family: var(--font-mono);"><?= $init_temp ?></span>
            <span id="temp-unit-display" style="font-size: 13px; font-weight: 700; color: #fdba74;">°C</span>
          </div>
        </div>
        <div style="margin-top: auto; padding-top: 8px;">
          <div style="height: 5px; border-radius: 999px; background: #f1f5f9; overflow: hidden; position: relative; margin-bottom: 4px;">
            <div id="temp-bar" style="height: 100%; width: <?= $init_temp_bar_width ?>; background: linear-gradient(90deg, #38bdf8 0%, #facc15 50%, #ea580c 100%); border-radius: 999px; transition: width 0.8s ease;"></div>
          </div>
          <div class="overview-bar-scale">
            <span id="temp-scale-0">15°</span><span style="color: #cbd5e1;">•</span><span id="temp-scale-50">28°</span><span style="color: #cbd5e1;">•</span><span id="temp-scale-100">40°</span>
          </div>
        </div>
      </div>

      <!-- CARD 3: HUMIDITY -->
      <div class="overview-core-card" id="card-hum" style="border-color: #bae6fd;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #0284c7, #38bdf8);"></div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
          <div style="display: flex; align-items: center; gap: 6px; min-width: 0;">
            <div class="overview-card-icon" style="width: 26px; height: 26px; border-radius: 8px; background-color: #f0f9ff; display: flex; align-items: center; justify-content: center; color: #0284c7; border: 1px solid #bae6fd; flex-shrink: 0;">
              <i data-lucide="droplets" style="width: 14px; height: 14px;"></i>
            </div>
            <h4 class="overview-card-title" style="margin: 0; font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.15; white-space: nowrap;">Humidity</h4>
          </div>
        </div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin: 4px 0;">
          <div style="display: flex; align-items: baseline; gap: 2px;">
            <span id="hum-val" class="overview-card-val" style="font-size: 32px; font-weight: 900; line-height: 1; letter-spacing: -0.03em; color: #0284c7; font-family: var(--font-mono);"><?= $init_hum ?></span>
            <span style="font-size: 14px; font-weight: 700; color: #94a3b8;">%</span>
          </div>
        </div>
        <div style="margin-top: auto; padding-top: 8px;">
          <div style="height: 5px; border-radius: 999px; background: #f1f5f9; overflow: hidden; position: relative; margin-bottom: 4px;">
            <div id="hum-bar" style="height: 100%; width: <?= $init_hum_bar_width ?>; background: linear-gradient(90deg, #38bdf8 0%, #0284c7 100%); border-radius: 999px; transition: width 0.8s ease;"></div>
          </div>
          <div class="overview-bar-scale">
            <span>0%</span><span style="color: #cbd5e1;">•</span><span>50%</span><span style="color: #cbd5e1;">•</span><span>100%</span>
          </div>
        </div>
      </div>

      <!-- CARD 4: WIND SPEED -->
      <div class="overview-core-card" id="card-wind" style="border-color: #a7f3d0;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #059669, #10b981);"></div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; gap: 6px;">
          <div style="display: flex; align-items: center; gap: 6px; min-width: 0;">
            <div class="overview-card-icon" style="width: 26px; height: 26px; border-radius: 8px; background-color: #ecfdf5; display: flex; align-items: center; justify-content: center; color: #059669; border: 1px solid #a7f3d0; flex-shrink: 0;">
              <i data-lucide="wind" style="width: 14px; height: 14px;"></i>
            </div>
            <h4 class="overview-card-title" style="margin: 0; font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.15;">Wind Speed</h4>
          </div>
          <button type="button" id="btn-wind" onclick="toggleWind()" title="Switch wind unit: km/h / m/s" class="segmented-unit-btn" style="background-color: #ecfdf5; border-color: #a7f3d0; color: #047857; flex-shrink: 0;">
            <span class="unit-opt active" id="unit-opt-kmh">km/h</span>
            <span class="unit-opt" id="unit-opt-ms">m/s</span>
          </button>
        </div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin: 4px 0;">
          <div style="display: flex; align-items: center; gap: 6px;">
            <span id="wind-val" class="overview-card-val" style="font-size: 32px; font-weight: 900; line-height: 1; letter-spacing: -0.03em; color: #059669; font-family: var(--font-mono);"><?= $init_wind ?></span>
          </div>
          <div class="overview-wind-equalizer" style="display: flex; align-items: flex-end; gap: 3px; height: 16px;">
            <div id="eq-1" style="width: 3.5px; height: 8px; border-radius: 2px; background-color: <?= $init_wind_kmh >= 2.5 ? '#059669' : '#e2e8f0' ?>; transition: background-color 0.3s ease;"></div>
            <div id="eq-2" style="width: 3.5px; height: 11px; border-radius: 2px; background-color: <?= $init_wind_kmh >= 5.0 ? '#059669' : '#e2e8f0' ?>; transition: background-color 0.3s ease;"></div>
            <div id="eq-3" style="width: 3.5px; height: 14px; border-radius: 2px; background-color: <?= $init_wind_kmh >= 7.5 ? '#059669' : '#e2e8f0' ?>; transition: background-color 0.3s ease;"></div>
            <div id="eq-4" style="width: 3.5px; height: 17px; border-radius: 2px; background-color: <?= $init_wind_kmh >= 10.0 ? '#059669' : '#e2e8f0' ?>; transition: background-color 0.3s ease;"></div>
          </div>
        </div>
        <div style="margin-top: auto; padding-top: 8px;">
          <div style="height: 5px; border-radius: 999px; background: #f1f5f9; overflow: hidden; position: relative; margin-bottom: 4px;">
            <div id="wind-bar" style="height: 100%; width: <?= $init_wind_bar_width ?>; background: linear-gradient(90deg, #34d399 0%, #059669 100%); border-radius: 999px; transition: width 0.8s ease;"></div>
          </div>
          <div class="overview-bar-scale">
            <span>0</span><span style="color: #cbd5e1;">•</span><span id="wind-scale-50">15</span><span style="color: #cbd5e1;">•</span><span id="wind-scale-100">30 km/h</span>
          </div>
        </div>
      </div>

      <!-- CARD 5: RAINFALL (LIVE & TODAY'S RAIN) -->
      <div class="overview-core-card" id="card-rain" style="border-color: <?= $init_is_raining ? '#7dd3fc' : '#c7d2fe' ?>;">
        <div id="rain-grad" style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: <?= $init_is_raining ? 'linear-gradient(90deg, #0284c7, #38bdf8)' : 'linear-gradient(90deg, #6366f1, #0284c7)' ?>;"></div>
        <div class="overview-rain-header-row" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
          <div style="display: flex; align-items: center; gap: 6px; min-width: 0;">
            <div id="rain-icon-bg" class="overview-card-icon" style="width: 26px; height: 26px; border-radius: 8px; background-color: <?= $init_is_raining ? '#f0f9ff' : '#eef2ff' ?>; display: flex; align-items: center; justify-content: center; color: <?= $init_is_raining ? '#0284c7' : '#4f46e5' ?>; border: 1px solid <?= $init_is_raining ? '#bae6fd' : '#c7d2fe' ?>; flex-shrink: 0;">
              <i data-lucide="cloud-rain" style="width: 14px; height: 14px;"></i>
            </div>
            <h4 class="overview-card-title" style="margin: 0; font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.15; white-space: nowrap;">Rainfall</h4>
          </div>
          <div style="display: flex; align-items: center; gap: 6px;">
            <span id="rain-live-badge" class="overview-card-badge" style="display: none; padding: 2px 7px; border-radius: 999px; font-size: 10px; font-weight: 700; background-color: #e0f2fe; color: #0284c7; border: 1px solid #7dd3fc; align-items: center; gap: 4px;">
              <span style="width: 5px; height: 5px; border-radius: 50%; background-color: #0284c7; display: inline-block;"></span>
              <span>Raining Now</span>
            </span>
            <button type="button" id="btn-rain" onclick="toggleRain()" title="Switch rainfall unit: mm / cm" class="segmented-unit-btn" style="background-color: #eef2ff; border-color: #c7d2fe; color: #4338ca;">
              <span class="unit-opt active" id="unit-opt-mm">mm</span>
              <span class="unit-opt" id="unit-opt-cm">cm</span>
            </button>
          </div>
        </div>
        <div class="overview-rain-container" style="display: flex; align-items: center; justify-content: space-between; margin-top: auto; margin-bottom: auto; padding: 4px 0;">
          <div>
            <div class="overview-rain-label" style="font-size: 10.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 2px;">Rain Now</div>
            <div style="display: flex; align-items: baseline; gap: 3px;">
              <span id="rain-val" class="overview-card-val overview-card-val-sm" style="font-size: 24px; font-weight: 900; line-height: 1; letter-spacing: -0.03em; color: #4f46e5; font-family: var(--font-mono);"><?= $init_live_rain ?></span>
              <span id="rain-unit-live" class="overview-rain-unit" style="font-size: 11px; font-weight: 700; color: #818cf8;">mm</span>
            </div>
          </div>
          <div class="overview-rain-divider" style="width: 1px; height: 32px; background: #e2e8f0; margin: 0 4px;"></div>
          <div style="text-align: right;">
            <div class="overview-rain-label" style="font-size: 10.5px; font-weight: 700; color: #0284c7; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 2px;">Today's Rain</div>
            <div style="display: flex; align-items: baseline; justify-content: flex-end; gap: 3px;">
              <span id="total-rain-val" class="overview-card-val overview-card-val-sm" style="font-size: 24px; font-weight: 900; line-height: 1; letter-spacing: -0.03em; color: #0284c7; font-family: var(--font-mono);"><?= $init_today_rain ?></span>
              <span id="rain-unit-total" class="overview-rain-unit" style="font-size: 11px; font-weight: 700; color: #38bdf8;">mm</span>
            </div>
          </div>
        </div>
        <div id="rain-bar-container" style="display: none; margin-top: auto; padding-top: 8px;">
          <div style="height: 5px; border-radius: 999px; background: #f1f5f9; overflow: hidden; position: relative; margin-bottom: 4px;">
            <div id="rain-bar" style="height: 100%; width: <?= $init_rain_bar_width ?>; background: linear-gradient(90deg, #38bdf8 0%, #0284c7 50%, #4f46e5 100%); border-radius: 999px; transition: width 0.8s ease;"></div>
          </div>
          <div class="overview-bar-scale" style="display: flex; justify-content: space-between; font-size: 10px; font-weight: 700; color: #64748b; font-family: var(--font-mono); margin-top: 4px;">
            <span>0 Light</span><span style="color: #cbd5e1;">•</span><span>5 Mod</span><span style="color: #cbd5e1;">•</span><span>15+ Heavy mm/h</span>
          </div>
        </div>
      </div>

    </div>

    <!-- ── Chart Carousel Section with Swipe & Arrow Controls ── -->
    <div class="overview-chart-carousel-section" style="margin-top: 10px;">
      
      <!-- Carousel Header Controls: Title, Indicators & Small Arrows -->
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; padding: 0 4px; gap: 10px; flex-wrap: wrap;">
        
        <!-- Left: Carousel Title -->
        <div style="display: flex; align-items: center; gap: 6px; font-size: 13.5px; font-weight: 800; color: #0f172a;">
          <i data-lucide="line-chart" style="width: 16px; height: 16px; color: #00bfa5;"></i>
          <span>Microclimate Trends</span>
        </div>

        <!-- Right: Small Arrow Buttons & Slide Counter -->
        <div style="display: flex; align-items: center; gap: 6px; margin-left: auto;">
          <span id="chart-slide-counter" style="font-size: 11px; font-weight: 700; color: #64748b; font-family: var(--font-mono); padding: 3px 8px; border-radius: 999px; background: #ffffff; border: 1px solid #e2e8f0;">
            1 / 5
          </span>
          <button type="button" id="btn-chart-prev" onclick="navChartPrev()" title="Previous Graph" style="display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; border: 1.5px solid #cbd5e1; background: #ffffff; color: #334155; cursor: pointer; box-shadow: 0 2px 6px rgba(15,23,42,0.06); transition: all 0.15s ease;">
            <i data-lucide="chevron-left" style="width: 16px; height: 16px;"></i>
          </button>
          <button type="button" id="btn-chart-next" onclick="navChartNext()" title="Next Graph" style="display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; border: 1.5px solid #cbd5e1; background: #ffffff; color: #334155; cursor: pointer; box-shadow: 0 2px 6px rgba(15,23,42,0.06); transition: all 0.15s ease;">
            <i data-lucide="chevron-right" style="width: 16px; height: 16px;"></i>
          </button>
        </div>

      </div>

      <!-- Horizontal Swipeable Carousel (One graph visible at a time on mobile/tablet/desktop) -->
      <div id="overview-chart-carousel" class="overview-chart-carousel" style="display: flex; overflow-x: auto; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; scroll-behavior: smooth; gap: 16px; padding-bottom: 6px; scrollbar-width: none;">
        
        <!-- SLIDE 0: AQI Line Plot Card -->
        <div class="chart-slide-card snap-center" style="flex: 0 0 100%; min-width: 100%; box-sizing: border-box; background-color: #ffffff; border-radius: 20px; padding: clamp(14px, 3vw, 24px); border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05); display: flex; flex-direction: column; gap: 14px;">
          <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
              <div style="font-size: 14px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                <i data-lucide="activity" style="width: 16px; height: 16px; color: #00bfa5;"></i>
                AQI Line Plot
              </div>
              <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">
                Real-time Air Quality progression
              </div>
            </div>
            <div style="background-color: rgba(0, 191, 165, 0.1); border: 1px solid rgba(0, 191, 165, 0.25); padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700; color: #00bfa5; font-family: var(--font-mono);">
              Current: <span id="overview-chart-current">--</span>
            </div>
          </div>

          <div style="width: 100%; height: clamp(200px, 32vh, 260px); position: relative;">
            <canvas id="overview-aqi-chart"></canvas>
          </div>
        </div>

        <!-- SLIDE 1: Temperature Plot Card -->
        <div class="chart-slide-card snap-center" style="flex: 0 0 100%; min-width: 100%; box-sizing: border-box; background-color: #ffffff; border-radius: 20px; padding: clamp(14px, 3vw, 24px); border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05); display: flex; flex-direction: column; gap: 14px;">
          <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
              <div style="font-size: 14px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                <i data-lucide="thermometer" style="width: 16px; height: 16px; color: #f97316;"></i>
                Temperature Plot
              </div>
              <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">
                Real-time Temperature progression
              </div>
            </div>
            <div id="overview-temp-current-badge" style="background-color: rgba(249, 115, 22, 0.1); border: 1px solid rgba(249, 115, 22, 0.25); padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700; color: #f97316; font-family: var(--font-mono);">
              Current: <span id="overview-temp-current">--</span>
            </div>
          </div>
          <div style="width: 100%; height: clamp(200px, 32vh, 260px); position: relative;">
            <canvas id="overview-temp-chart"></canvas>
          </div>
        </div>

        <!-- SLIDE 2: Humidity Plot Card -->
        <div class="chart-slide-card snap-center" style="flex: 0 0 100%; min-width: 100%; box-sizing: border-box; background-color: #ffffff; border-radius: 20px; padding: clamp(14px, 3vw, 24px); border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05); display: flex; flex-direction: column; gap: 14px;">
          <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
              <div style="font-size: 14px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                <i data-lucide="droplets" style="width: 16px; height: 16px; color: #0ea5e9;"></i>
                Humidity Plot
              </div>
              <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">
                Real-time Humidity progression
              </div>
            </div>
            <div id="overview-hum-current-badge" style="background-color: rgba(14, 165, 233, 0.1); border: 1px solid rgba(14, 165, 233, 0.25); padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700; color: #0ea5e9; font-family: var(--font-mono);">
              Current: <span id="overview-hum-current">--</span>
            </div>
          </div>
          <div style="width: 100%; height: clamp(200px, 32vh, 260px); position: relative;">
            <canvas id="overview-hum-chart"></canvas>
          </div>
        </div>

        <!-- SLIDE 3: Wind Speed Plot Card -->
        <div class="chart-slide-card snap-center" style="flex: 0 0 100%; min-width: 100%; box-sizing: border-box; background-color: #ffffff; border-radius: 20px; padding: clamp(14px, 3vw, 24px); border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05); display: flex; flex-direction: column; gap: 14px;">
          <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
              <div style="font-size: 14px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                <i data-lucide="wind" style="width: 16px; height: 16px; color: #10b981;"></i>
                Wind Speed Plot
              </div>
              <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">
                Real-time Wind Speed progression
              </div>
            </div>
            <div id="overview-wind-current-badge" style="background-color: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700; color: #10b981; font-family: var(--font-mono);">
              Current: <span id="overview-wind-current">--</span>
            </div>
          </div>
          <div style="width: 100%; height: clamp(200px, 32vh, 260px); position: relative;">
            <canvas id="overview-wind-chart"></canvas>
          </div>
        </div>

        <!-- SLIDE 4: Rainfall Plot Card -->
        <div class="chart-slide-card snap-center" style="flex: 0 0 100%; min-width: 100%; box-sizing: border-box; background-color: #ffffff; border-radius: 20px; padding: clamp(14px, 3vw, 24px); border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05); display: flex; flex-direction: column; gap: 14px;">
          <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
              <div style="font-size: 14px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                <i data-lucide="cloud-rain" style="width: 16px; height: 16px; color: #6366f1;"></i>
                Rainfall Plot
              </div>
              <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">
                Real-time Rainfall progression
              </div>
            </div>
            <div id="overview-rain-current-badge" style="background-color: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.25); padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700; color: #6366f1; font-family: var(--font-mono);">
              Current: <span id="overview-rain-current">--</span>
            </div>
          </div>
          <div style="width: 100%; height: clamp(200px, 32vh, 260px); position: relative;">
            <canvas id="overview-rain-chart"></canvas>
          </div>
        </div>

      </div>

      <!-- Carousel Dot Pagination Indicators -->
      <div id="overview-chart-dots" style="display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 12px;">
        <button type="button" onclick="scrollChartTo(0)" class="chart-dot-item" id="chart-dot-0" title="AQI Chart" style="width: 22px; height: 6px; border-radius: 999px; background: #00bfa5; border: none; cursor: pointer; transition: all 0.2s cubic-bezier(0.16,1,0.3,1);"></button>
        <button type="button" onclick="scrollChartTo(1)" class="chart-dot-item" id="chart-dot-1" title="Temperature Chart" style="width: 6px; height: 6px; border-radius: 999px; background: #cbd5e1; border: none; cursor: pointer; transition: all 0.2s cubic-bezier(0.16,1,0.3,1);"></button>
        <button type="button" onclick="scrollChartTo(2)" class="chart-dot-item" id="chart-dot-2" title="Humidity Chart" style="width: 6px; height: 6px; border-radius: 999px; background: #cbd5e1; border: none; cursor: pointer; transition: all 0.2s cubic-bezier(0.16,1,0.3,1);"></button>
        <button type="button" onclick="scrollChartTo(3)" class="chart-dot-item" id="chart-dot-3" title="Wind Speed Chart" style="width: 6px; height: 6px; border-radius: 999px; background: #cbd5e1; border: none; cursor: pointer; transition: all 0.2s cubic-bezier(0.16,1,0.3,1);"></button>
        <button type="button" onclick="scrollChartTo(4)" class="chart-dot-item" id="chart-dot-4" title="Rainfall Chart" style="width: 6px; height: 6px; border-radius: 999px; background: #cbd5e1; border: none; cursor: pointer; transition: all 0.2s cubic-bezier(0.16,1,0.3,1);"></button>
      </div>

    </div>

  </div>
</div>
  <style>
    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
    @keyframes breathAtmosphere {
      0% { opacity: 0.4; transform: scale(0.96) translate(0, 0); }
      50% { opacity: 0.7; transform: scale(1.04) translate(-10px, 10px); }
      100% { opacity: 0.4; transform: scale(0.96) translate(0, 0); }
    }
    .overview-grid {
      display: grid !important;
      grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
      gap: 12px !important;
      margin-bottom: 8px !important;
    }
    #rain-live-badge,
    #rain-bar-container {
      display: none !important;
    }
    @media (max-width: 1024px) {
      .overview-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
      }
    }
    @media (max-width: 768px) {
      .overview-page-root {
        padding: 8px 10px 14px !important;
        min-height: auto !important;
        border-radius: 16px !important;
      }
      .overview-content-container {
        gap: 10px !important;
      }
      .overview-page-title {
        font-size: 16px !important;
        line-height: 1.25 !important;
      }
      .overview-page-meta {
        font-size: 11px !important;
        margin-top: 3px !important;
        gap: 6px !important;
      }
      .overview-page-meta #overview-latest-time {
        padding: 1.5px 6px !important;
        font-size: 10px !important;
      }
      .overview-page-meta #overview-live-badge {
        padding: 1.5px 6px !important;
        font-size: 9.5px !important;
      }
      .overview-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 8px !important;
        margin-bottom: 4px !important;
      }
      /* Card 1: Air Quality is the full-width dominant hero card on mobile */
      .overview-grid > *:nth-child(1) {
        grid-column: span 2 !important;
      }
      /* Secondary weather cards sit in clean 2x2 grid */
      .overview-grid > *:nth-child(5) {
        grid-column: span 1 !important;
      }
      .overview-card-title {
        font-size: 11.5px !important;
        letter-spacing: -0.01em !important;
      }
      #card-wind .overview-card-title {
        font-size: 11px !important;
        white-space: normal !important;
        line-height: 1.15 !important;
        max-width: 58px;
      }
      .overview-card-icon {
        width: 20px !important;
        height: 20px !important;
        border-radius: 6px !important;
      }
      .overview-card-icon svg {
        width: 11px !important;
        height: 11px !important;
      }
      .segmented-unit-btn {
        padding: 1px !important;
        flex-shrink: 0 !important;
      }
      .segmented-unit-btn .unit-opt {
        padding: 1px 3.5px !important;
        font-size: 7.5px !important;
      }
      #btn-wind .unit-opt {
        padding: 1px 3px !important;
        font-size: 7.5px !important;
      }
      .overview-core-card {
        padding: 7px 9px 7px !important;
        min-height: 80px !important;
        border-radius: 13px !important;
      }
      .overview-card-hero {
        padding: 9px 11px 9px !important;
        min-height: auto !important;
        border-radius: 14px !important;
      }
      .overview-card-val {
        font-size: 19px !important;
        line-height: 1 !important;
      }
      .overview-card-hero .overview-card-val {
        font-size: 24px !important;
        line-height: 1 !important;
      }
      .overview-card-hero #aqi-val + span {
        font-size: 10px !important;
      }
      .overview-card-hero #aqi-dom {
        font-size: 10px !important;
      }
      .overview-card-hero [style*="padding: 3px 9px"] {
        padding: 2px 6px !important;
        font-size: 9.5px !important;
      }
      .overview-card-hero #aqi-bar {
        height: 4px !important;
      }
      .overview-core-card #temp-bar,
      .overview-core-card #hum-bar,
      .overview-core-card #wind-bar {
        height: 3px !important;
      }
      .overview-wind-equalizer {
        height: 11px !important;
        gap: 2px !important;
      }
      .overview-wind-equalizer > div {
        width: 2.5px !important;
      }
      /* Clean uncluttered gauges on mobile secondary cards */
      .overview-bar-scale {
        display: none !important;
      }
      /* Card 5 Rainfall mobile compact formatting */
      .overview-rain-container {
        padding: 1px 0 !important;
        margin: auto 0 0 !important;
      }
      .overview-rain-divider {
        height: 22px !important;
      }
      .overview-rain-label {
        font-size: 8.5px !important;
        margin-bottom: 1px !important;
      }
      .overview-card-val-sm {
        font-size: 16px !important;
      }
      .overview-rain-unit {
        font-size: 9px !important;
      }
      /* Rain Active (Raining Mode: Only on Mobile, Rainfall switches with AQI to take hero slot 1) */
      .overview-grid.rain-active #card-rain {
        order: -1 !important;
        grid-column: span 2 !important;
        padding: 9px 11px 9px !important;
        min-height: auto !important;
        border-radius: 14px !important;
      }
      .overview-grid.rain-active #card-rain .overview-card-val-sm {
        font-size: 24px !important;
        line-height: 1 !important;
      }
      .overview-grid.rain-active #card-rain .overview-rain-unit {
        font-size: 11px !important;
      }
      .overview-grid.rain-active #card-rain .overview-rain-label {
        font-size: 9.5px !important;
      }
      .overview-grid.rain-active #card-rain .overview-rain-divider {
        height: 28px !important;
      }
      .overview-grid.rain-active #card-rain #rain-bar {
        height: 4px !important;
      }
      .overview-grid.rain-active #card-rain #rain-live-badge {
        display: inline-flex !important;
      }
      .overview-grid.rain-active #card-rain #rain-bar-container {
        display: block !important;
      }

      .overview-grid.rain-active #card-temp { order: 1 !important; }
      .overview-grid.rain-active #card-hum  { order: 2 !important; }
      .overview-grid.rain-active #card-wind { order: 3 !important; }

      .overview-grid.rain-active #card-aqi {
        order: 5 !important;
        grid-column: span 1 !important;
        padding: 7px 9px 7px !important;
        min-height: 80px !important;
        border-radius: 13px !important;
      }
      .overview-grid.rain-active #card-aqi .overview-aqi-header-row {
        margin-bottom: 4px !important;
      }
      .overview-grid.rain-active #card-aqi .overview-card-title {
        font-size: 11px !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        max-width: 65px !important;
      }
      .overview-grid.rain-active #card-aqi .aqi-title-long {
        display: none !important;
      }
      .overview-grid.rain-active #card-aqi .aqi-title-short {
        display: inline !important;
        font-size: 11px !important;
      }
      .overview-grid.rain-active #card-aqi .overview-card-icon {
        width: 20px !important;
        height: 20px !important;
        border-radius: 6px !important;
      }
      .overview-grid.rain-active #card-aqi .overview-card-icon svg {
        width: 11px !important;
        height: 11px !important;
      }
      .overview-grid.rain-active #card-aqi #aqi-badge {
        padding: 1.5px 5px !important;
        font-size: 8px !important;
        gap: 3px !important;
      }
      .overview-grid.rain-active #card-aqi #aqi-badge-dot {
        width: 4px !important;
        height: 4px !important;
      }
      .overview-grid.rain-active #card-aqi .overview-aqi-val-row {
        margin: 3px 0 !important;
        gap: 4px !important;
      }
      .overview-grid.rain-active #card-aqi .overview-card-val {
        font-size: 19px !important;
        line-height: 1 !important;
      }
      .overview-grid.rain-active #card-aqi #aqi-val + span {
        font-size: 8.5px !important;
      }
      .overview-grid.rain-active #card-aqi .overview-aqi-dom-wrap {
        padding: 1.5px 4px !important;
        font-size: 8px !important;
        gap: 2px !important;
      }
      .overview-grid.rain-active #card-aqi #aqi-dom {
        font-size: 8.5px !important;
      }
      .overview-grid.rain-active #card-aqi .overview-aqi-bar-cont {
        padding-top: 4px !important;
      }
      .overview-grid.rain-active #card-aqi #aqi-bar {
        height: 3px !important;
      }
      /* Microclimate trends carousel adjustments */
      .overview-chart-carousel-section {
        margin-top: 4px !important;
      }
      .overview-chart-carousel-section .chart-slide-card {
        padding: 9px 11px !important;
        border-radius: 13px !important;
        gap: 6px !important;
      }
      .overview-chart-carousel-section [style*="height: clamp(200px"] {
        height: 155px !important;
      }
      #btn-chart-prev, #btn-chart-next {
        width: 26px !important;
        height: 26px !important;
      }
      #btn-chart-prev svg, #btn-chart-next svg {
        width: 13px !important;
        height: 13px !important;
      }
      #chart-slide-counter {
        font-size: 9.5px !important;
        padding: 2px 6px !important;
      }
    }
    .overview-chart-carousel::-webkit-scrollbar {
      display: none;
    }

    .overview-core-card {
      background: #ffffff;
      border-radius: 16px;
      padding: 13px 13px 12px;
      border: 1.5px solid #e2e8f0;
      box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      min-height: 136px;
      box-sizing: border-box;
      transition: transform 0.18s cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 0.18s cubic-bezier(0.2, 0.8, 0.2, 1);
    }
    .overview-core-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(15, 23, 42, 0.07) !important;
    }
    .segmented-unit-btn {
      display: inline-flex;
      align-items: center;
      padding: 1.5px;
      border-radius: 999px;
      cursor: pointer;
      user-select: none;
      -webkit-user-select: none;
      transition: all 0.15s ease;
      line-height: 1;
      flex-shrink: 0;
      border: 1px solid transparent;
      outline: none;
      background: transparent;
    }
    .segmented-unit-btn .unit-opt {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 2px 6.5px;
      border-radius: 999px;
      font-size: 9.5px;
      font-weight: 700;
      transition: all 0.15s ease;
      color: inherit;
      opacity: 0.6;
    }
    .segmented-unit-btn .unit-opt.active {
      background: #ffffff;
      opacity: 1;
      font-weight: 800;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
    }
    .segmented-unit-btn:hover {
      transform: translateY(-1px);
    }
    .segmented-unit-btn:active {
      transform: translateY(0);
    }
    .overview-bar-scale {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 10px;
      font-weight: 700;
      color: #64748b;
      font-family: var(--font-mono);
      line-height: 1;
      margin-top: 5px;
    }
    .overview-sub-badge {
      display: flex;
      align-items: center;
      gap: 3px;
      padding: 3px 8px;
      border-radius: 6px;
      font-size: 10.5px;
      font-weight: 700;
      white-space: nowrap;
    }
  </style>

<script>
var tempUnit = (function() { try { return localStorage.getItem('aqi_temp_unit') || 'C'; } catch(e) { return 'C'; } })();
var windUnit = (function() { try { return localStorage.getItem('aqi_wind_unit') || 'kmh'; } catch(e) { return 'kmh'; } })();
var rainUnit = (function() { try { return localStorage.getItem('aqi_rain_unit') || 'mm'; } catch(e) { return 'mm'; } })();
var rawTemp = <?= isset($init_bulk['weather']['temperature']) && $init_bulk['weather']['temperature'] !== null ? json_encode((float)adjustTemperatureOverview($init_bulk['weather']['temperature'], $init_bulk['weather']['timestamp'] ?? $init_bulk['weather']['created_at'] ?? null)) : 'null' ?>;
var rawWind = <?= isset($init_bulk['weather']['wind_speed']) && $init_bulk['weather']['wind_speed'] !== null ? json_encode((float)$init_bulk['weather']['wind_speed']) : 'null' ?>;
var rawLiveRain = <?= isset($init_bulk['weather']['rain_gauge']) && $init_bulk['weather']['rain_gauge'] !== null ? json_encode((float)$init_bulk['weather']['rain_gauge']) : '0' ?>;
var rawTodayRain = <?= isset($init_bulk['today_rain_total']) && $init_bulk['today_rain_total'] !== null ? json_encode((float)$init_bulk['today_rain_total']) : '0' ?>;
var cachedWeatherHistory = null;
var isRainingGlobal = <?= !empty($init_bulk['weather']['rain_gauge']) && (float)$init_bulk['weather']['rain_gauge'] > 0 ? 'true' : 'false' ?>;
var animationFrameId = null;
var canvasResizeHandler = null;

if (!window.INITIAL_DATA && <?= $init_bulk ? 'true' : 'false' ?>) {
    window.INITIAL_DATA = <?= json_encode($init_bulk) ?>;
}

var getAqiCategory = function(val) {
    const v = Number(val);
    if (v <= 50) return { label: 'Good', color: '#59b61f', bg: '#f0fdf4', border: 'rgba(89, 182, 31, 0.4)', text: '#15803d' };
    if (v <= 100) return { label: 'Moderate', color: '#eec732', bg: '#fefce8', border: 'rgba(238, 199, 50, 0.45)', text: '#854d0e' };
    if (v <= 200) return { label: 'Poor', color: '#ea8c34', bg: '#fff7ed', border: 'rgba(234, 140, 52, 0.45)', text: '#9a3412' };
    if (v <= 300) return { label: 'Unhealthy', color: '#e95478', bg: '#fff1f2', border: 'rgba(233, 84, 120, 0.45)', text: '#991b1b' };
    if (v <= 400) return { label: 'Severe', color: '#b33fba', bg: '#faf5ff', border: 'rgba(179, 63, 186, 0.45)', text: '#6b21a8' };
    return { label: 'Hazardous', color: '#c92033', bg: '#fef2f2', border: 'rgba(201, 32, 51, 0.45)', text: '#9f1239' };
};

function toggleTemp() {
    tempUnit = tempUnit === 'C' ? 'F' : 'C';
    try { localStorage.setItem('aqi_temp_unit', tempUnit); } catch(e) {}
    updateTempDisplay();
    if (typeof updateMobileHero === 'function') updateMobileHero();
    if (cachedWeatherHistory) {
        renderWeatherHistoryCharts(cachedWeatherHistory);
    }
}

function toggleWind() {
    windUnit = windUnit === 'kmh' ? 'ms' : 'kmh';
    try { localStorage.setItem('aqi_wind_unit', windUnit); } catch(e) {}
    updateWindDisplay();
    if (typeof updateMobileHero === 'function') updateMobileHero();
    if (cachedWeatherHistory) {
        renderWeatherHistoryCharts(cachedWeatherHistory);
    }
}

function toggleRain() {
    rainUnit = rainUnit === 'mm' ? 'cm' : 'mm';
    try { localStorage.setItem('aqi_rain_unit', rainUnit); } catch(e) {}
    updateRainDisplay();
    if (typeof updateMobileHero === 'function') updateMobileHero();
    if (cachedWeatherHistory) {
        renderWeatherHistoryCharts(cachedWeatherHistory);
    }
}

function updateRainDisplay() {
    const liveEl = document.getElementById('rain-val');
    const totalEl = document.getElementById('total-rain-val');
    const unitLiveEl = document.getElementById('rain-unit-live');
    const unitTotalEl = document.getElementById('rain-unit-total');
    const optMm = document.getElementById('unit-opt-mm');
    const optCm = document.getElementById('unit-opt-cm');

    if (optMm && optCm) {
        optMm.classList.toggle('active', rainUnit === 'mm');
        optCm.classList.toggle('active', rainUnit === 'cm');
    }
    if (unitLiveEl) unitLiveEl.innerText = rainUnit;
    if (unitTotalEl) unitTotalEl.innerText = rainUnit;

    if (rainUnit === 'mm') {
        if (liveEl) liveEl.innerText = rawLiveRain != null ? Number(rawLiveRain).toFixed(1) : '--';
        if (totalEl) totalEl.innerText = rawTodayRain != null ? Number(rawTodayRain).toFixed(1) : '--';
    } else {
        // In cm: 1 cm = 10 mm
        if (liveEl) liveEl.innerText = rawLiveRain != null ? (Number(rawLiveRain) / 10).toFixed(2) : '--';
        if (totalEl) totalEl.innerText = rawTodayRain != null ? (Number(rawTodayRain) / 10).toFixed(2) : '--';
    }

    const rainBar = document.getElementById('rain-bar');
    if (rainBar && typeof isRainingGlobal !== 'undefined' && isRainingGlobal) {
        const pct = Math.min(100, Math.max(8, Math.round(((rawLiveRain || 0) / 15) * 100)));
        rainBar.style.width = pct + '%';
    }
}

function updateTempDisplay() {
    const optC = document.getElementById('unit-opt-c');
    const optF = document.getElementById('unit-opt-f');
    if (optC && optF) {
        optC.classList.toggle('active', tempUnit === 'C');
        optF.classList.toggle('active', tempUnit === 'F');
    }
    if (rawTemp == null) return;
    const val = tempUnit === 'C' ? Number(rawTemp).toFixed(1) : ((Number(rawTemp) * 9/5) + 32).toFixed(1);
    const tempValEl = document.getElementById('temp-val');
    if (tempValEl) tempValEl.innerText = val;
    const unitEl = document.getElementById('temp-unit-display');
    if (unitEl) unitEl.innerText = '°' + tempUnit;
    
    const s0 = document.getElementById('temp-scale-0');
    const s50 = document.getElementById('temp-scale-50');
    const s100 = document.getElementById('temp-scale-100');
    if (s0) s0.innerText = tempUnit === 'C' ? '15°' : '59°';
    if (s50) s50.innerText = tempUnit === 'C' ? '28°' : '82°';
    if (s100) s100.innerText = tempUnit === 'C' ? '40°' : '104°';
    
    const pct = Math.min(100, Math.max(5, ((Number(rawTemp) - 10) / 35) * 100));
    const tBar = document.getElementById('temp-bar');
    if (tBar) tBar.style.width = pct + '%';
}

function updateWindDisplay() {
    const optKmh = document.getElementById('unit-opt-kmh');
    const optMs = document.getElementById('unit-opt-ms');
    if (optKmh && optMs) {
        optKmh.classList.toggle('active', windUnit === 'kmh');
        optMs.classList.toggle('active', windUnit === 'ms');
    }
    if (rawWind == null) return;
    const val = windUnit === 'kmh' ? Number(rawWind).toFixed(1) : (Number(rawWind) / 3.6).toFixed(1);
    const windValEl = document.getElementById('wind-val');
    if (windValEl) windValEl.innerText = val;
    const unitEl = document.getElementById('wind-unit-display');
    if (unitEl) unitEl.innerText = windUnit === 'kmh' ? 'km/h' : 'm/s';
    
    const s50 = document.getElementById('wind-scale-50');
    const s100 = document.getElementById('wind-scale-100');
    if (s50) s50.innerText = windUnit === 'kmh' ? '15' : '4.2';
    if (s100) s100.innerText = windUnit === 'kmh' ? '30 km/h' : '8.3 m/s';
    
    const pct = Math.min(100, Math.max(4, (Number(val) / (windUnit === 'ms' ? 8.3 : 30)) * 100));
    const wBar = document.getElementById('wind-bar');
    if (wBar) wBar.style.width = pct + '%';
}

/* ── Chart Carousel Navigation (Swipe & Arrows) ── */
window.currentChartSlideIndex = 0;
window.TOTAL_CHART_SLIDES = 5;

function scrollChartTo(index, smooth = true) {
    const carousel = document.getElementById('overview-chart-carousel');
    if (!carousel) return;
    const slides = carousel.querySelectorAll('.chart-slide-card');
    if (slides && slides[index]) {
        updateChartCarouselIndicators(index);
        const slideLeft = slides[index].offsetLeft - (slides[0] ? slides[0].offsetLeft : 0);
        carousel.scrollTo({ left: slideLeft, behavior: smooth ? 'smooth' : 'auto' });
        setTimeout(resizeActiveChart, 150);
    }
}

function navChartPrev() {
    let nextIdx = (window.currentChartSlideIndex || 0) - 1;
    if (nextIdx < 0) nextIdx = window.TOTAL_CHART_SLIDES - 1;
    scrollChartTo(nextIdx);
}

function navChartNext() {
    let nextIdx = (window.currentChartSlideIndex || 0) + 1;
    if (nextIdx >= window.TOTAL_CHART_SLIDES) nextIdx = 0;
    scrollChartTo(nextIdx);
}

function updateChartCarouselIndicators(activeIdx) {
    if (activeIdx < 0 || activeIdx >= window.TOTAL_CHART_SLIDES) return;
    window.currentChartSlideIndex = activeIdx;
    try {
        sessionStorage.setItem('overview_active_slide', String(activeIdx));
        const url = new URL(window.location.href);
        if (activeIdx > 0) {
            url.searchParams.set('slide', activeIdx);
        } else {
            url.searchParams.delete('slide');
        }
        history.replaceState(null, '', url.toString());
    } catch(e) {}
    const counter = document.getElementById('chart-slide-counter');
    if (counter) counter.innerText = `${activeIdx + 1} / ${window.TOTAL_CHART_SLIDES}`;
    for (let i = 0; i < window.TOTAL_CHART_SLIDES; i++) {
        const dot = document.getElementById(`chart-dot-${i}`);
        if (dot) {
            if (i === activeIdx) {
                dot.style.width = '22px';
                dot.style.backgroundColor = '#00bfa5';
            } else {
                dot.style.width = '6px';
                dot.style.backgroundColor = '#cbd5e1';
            }
        }
    }
    if (window.triggerHaptic) window.triggerHaptic('light');
}

function updateCarouselOnScroll() {
    const carousel = document.getElementById('overview-chart-carousel');
    if (!carousel) return;
    const slides = carousel.querySelectorAll('.chart-slide-card');
    if (!slides || !slides.length) return;

    const scrollLeft = carousel.scrollLeft;
    let step = carousel.offsetWidth;
    if (slides.length > 1) {
        const measuredStep = slides[1].offsetLeft - slides[0].offsetLeft;
        if (measuredStep > 0) step = measuredStep;
    }
    const activeIdx = Math.max(0, Math.min(window.TOTAL_CHART_SLIDES - 1, Math.round(scrollLeft / step)));
    if (activeIdx !== window.currentChartSlideIndex) {
        updateChartCarouselIndicators(activeIdx);
        resizeActiveChart();
    }
}

function setupCarouselScrollListener() {
    const carousel = document.getElementById('overview-chart-carousel');
    if (!carousel || carousel.dataset.listenerAttached) return;
    carousel.dataset.listenerAttached = 'true';

    let isScrolling = false;
    carousel.addEventListener('scroll', () => {
        if (!isScrolling) {
            window.requestAnimationFrame(() => {
                updateCarouselOnScroll();
                isScrolling = false;
            });
            isScrolling = true;
        }
    }, { passive: true });

    carousel.addEventListener('touchmove', () => {
        updateCarouselOnScroll();
    }, { passive: true });
}

function resizeActiveChart() {
    const canvasIds = ['overview-aqi-chart', 'overview-temp-chart', 'overview-hum-chart', 'overview-wind-chart', 'overview-rain-chart'];
    const activeIdx = window.currentChartSlideIndex || 0;
    const activeCid = canvasIds[activeIdx];
    if (activeCid && window.__chartInstances && window.__chartInstances[activeCid]) {
        try {
            window.__chartInstances[activeCid].resize();
        } catch(e) {}
    }
}

function applyRainHeroSwitch(isRaining) {
    const grid = document.querySelector('.overview-grid');
    if (!grid) return;

    if (isRaining) {
        grid.classList.add('rain-active');
        const rainBar = document.getElementById('rain-bar');
        if (rainBar) {
            const rainVal = (typeof rawLiveRain !== 'undefined' && rawLiveRain != null) ? Number(rawLiveRain) : 0;
            const pct = Math.min(100, Math.max(8, Math.round((rainVal / 15) * 100)));
            rainBar.style.width = pct + '%';
        }
    } else {
        grid.classList.remove('rain-active');
    }
}

function updateMobileHero() {
    if (typeof isRainingGlobal !== 'undefined') {
        applyRainHeroSwitch(isRainingGlobal);
    }
}

async function refreshData(isSilent = false, forceNetwork = false) {
    if (!document.getElementById('overview-latest-time')) return;
    const icon = document.getElementById('icon-refresh');
    if (!isSilent && icon) {
        icon.style.animation = 'spin 0.8s linear infinite';
    }
    
    try {
        let bulkData;
        let usedInitialData = false;
        const memoryCache = window.INITIAL_DATA || (window.__apiCache && (window.__apiCache.liveBulk || window.__apiCache.overviewBulk)
            ? (window.__apiCache.liveBulk || window.__apiCache.overviewBulk).data
            : null);

        if (!forceNetwork && memoryCache && (memoryCache.aqi || memoryCache.weather)) {
            bulkData = memoryCache;
            window.INITIAL_DATA = null;
            usedInitialData = true;
            if (window.__apiCache && bulkData) {
                window.__apiCache.overviewBulk = { data: bulkData, time: Date.now() };
                window.__apiCache.liveBulk = { data: bulkData, time: Date.now() };
            }
            // Trigger a single background network sync to refresh with live data
            setTimeout(() => {
                refreshData(true, true);
            }, 60);
        } else {
            const response = await fetch(`api/telemetry.php?action=overview_bulk`);
            bulkData = await response.json();
            if (window.__apiCache && bulkData) {
                window.__apiCache.overviewBulk = { data: bulkData, time: Date.now() };
                window.__apiCache.liveBulk = { data: bulkData, time: Date.now() };
            }
        }

        const aqiDate = (bulkData && bulkData.aqi) ? (window.safeParseDate ? window.safeParseDate(bulkData.aqi.timestamp || bulkData.aqi.created_at) : new Date(bulkData.aqi.timestamp || bulkData.aqi.created_at)) : null;
        const weatherDate = (bulkData && bulkData.weather) ? (window.safeParseDate ? window.safeParseDate(bulkData.weather.timestamp || bulkData.weather.created_at) : new Date(bulkData.weather.timestamp || bulkData.weather.created_at)) : null;
        const latestDate = (aqiDate && weatherDate) 
            ? (aqiDate.getTime() >= weatherDate.getTime() ? aqiDate : weatherDate)
            : (aqiDate || weatherDate);

        let isLive = false;
        let diffMins = 9999;
        let timeFormatted = '--';
        let shortTime = '--';

        if (latestDate && !isNaN(latestDate.getTime())) {
            diffMins = Math.floor((Date.now() - latestDate.getTime()) / 60000);
            isLive = !isNaN(diffMins) && diffMins >= -10 && diffMins <= 90;
            timeFormatted = latestDate.toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', hour12: true });
            shortTime = timeFormatted;
        }

        if (window.updateGlobalLiveBadge) {
            window.updateGlobalLiveBadge(isLive, isLive ? 'Live telemetry streaming from cloud' : 'Sensor node offline: No live telemetry received in cloud for >90 mins');
        }
        if (window.evaluateAndSyncLiveBadge) {
            window.evaluateAndSyncLiveBadge(bulkData ? (bulkData.aqi || bulkData.weather) : null);
        }

        const ovBanner = document.getElementById('overview-offline-banner');
        if (ovBanner) {
            ovBanner.style.display = isLive ? 'none' : 'flex';
        }

        const ovTimeEl = document.getElementById('overview-latest-time');
        const ovBadge = document.getElementById('overview-live-badge');
        if (ovTimeEl && timeFormatted !== '--') {
            ovTimeEl.innerText = timeFormatted;
        }

        if (ovBadge) {
            ovBadge.style.backgroundColor = '#ecfdf5';
            ovBadge.style.borderColor = '#a7f3d0';
            ovBadge.style.color = '#059669';
            ovBadge.innerHTML = '<span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10b981;"></span>LIVE';
        }

        const aqiRes = { data: bulkData.aqi };
        const weatherRes = { data: bulkData.weather };
        const liveHistoryRes = bulkData.history;
        
        // AQI
        if (aqiRes && aqiRes.data) {
            const aqi = aqiRes.data.cpcb_aqi ?? 0;
            const cat = getAqiCategory(aqi);
            
            document.getElementById('aqi-val').innerText = aqi;
            document.getElementById('aqi-val').style.color = cat.color;
            document.getElementById('card-aqi').style.borderColor = cat.color + '40';
            document.getElementById('aqi-grad').style.background = `linear-gradient(90deg, ${cat.color}, #06b6d4)`;
            document.getElementById('aqi-icon-bg').style.backgroundColor = cat.bg;
            document.getElementById('aqi-icon-bg').style.color = cat.color;
            document.getElementById('aqi-icon-bg').style.borderColor = cat.border;
            document.getElementById('aqi-badge').style.backgroundColor = cat.bg;
            document.getElementById('aqi-badge').style.color = cat.text;
            document.getElementById('aqi-badge').style.borderColor = cat.border;
            document.getElementById('aqi-badge-dot').style.backgroundColor = cat.color;
            document.getElementById('aqi-badge-text').innerText = cat.label;
            document.getElementById('aqi-dom').innerText = aqiRes.data.dominant_pollutant || 'O₃';
            
            document.getElementById('aqi-bar').style.background = `linear-gradient(90deg, #10b981 0%, ${cat.color} 100%)`;
            document.getElementById('aqi-bar').style.width = Math.min(100, Math.max(4, (aqi / 500) * 100)) + '%';
            
            document.getElementById('aura-top').style.background = `radial-gradient(circle, ${cat.color}15 0%, rgba(241, 245, 249, 0) 70%)`;
            
            const deskChartCur = document.getElementById('overview-chart-current');
            if (deskChartCur) deskChartCur.innerText = aqi;
        }

        // Weather
        if (weatherRes && weatherRes.data) {
            const w = weatherRes.data;
            rawTemp = w.temperature;
            rawWind = w.wind_speed;
            const hum = w.humidity;
            const rain = w.rain_gauge;
            
            updateTempDisplay();
            updateWindDisplay();
            
            // Hum
            document.getElementById('hum-val').innerText = hum != null ? Number(hum).toFixed(1) : '--';
            document.getElementById('hum-bar').style.width = hum != null ? Math.min(100, Math.max(5, Number(hum))) + '%' : '0%';
            
            // Wind EQ
            const wkmh = Number(rawWind || 0);

            for(let i=1; i<=4; i++) {
                document.getElementById('eq-'+i).style.backgroundColor = wkmh >= (i*2.5) ? '#059669' : '#e2e8f0';
            }
            
            // Rain (Live & Today's Rain in one card)
            const isRaining = rain != null && Number(rain) > 0;
            isRainingGlobal = isRaining;
            rawLiveRain = rain != null ? Number(rain) : 0;
            let currentTodayRain = (bulkData && bulkData.today_rain_total != null) ? Number(bulkData.today_rain_total) : 0;
            if (rawLiveRain > 0 && currentTodayRain < rawLiveRain) {
                currentTodayRain = rawLiveRain;
            }
            rawTodayRain = currentTodayRain;
            
            updateRainDisplay();
            
            document.getElementById('card-rain').style.borderColor = isRaining ? '#7dd3fc' : '#c7d2fe';
            document.getElementById('rain-grad').style.background = isRaining ? 'linear-gradient(90deg, #0284c7, #38bdf8)' : 'linear-gradient(90deg, #6366f1, #0284c7)';
            document.getElementById('rain-icon-bg').style.backgroundColor = isRaining ? '#f0f9ff' : '#eef2ff';
            document.getElementById('rain-icon-bg').style.color = isRaining ? '#0284c7' : '#4f46e5';
            document.getElementById('rain-icon-bg').style.borderColor = isRaining ? '#bae6fd' : '#c7d2fe';

            applyRainHeroSwitch(isRaining);

        }

        // Live History Chart
        if (liveHistoryRes && liveHistoryRes.length > 0) {
            // Sort ascending by time
            const history = liveHistoryRes.slice().reverse();
            
            let minAqi = 9999, maxAqi = -1;
            let minTime = '', maxTime = '';

            const labels = [];
            const data = [];

            history.forEach(row => {
                const aqi = Math.round(row.cpcb_aqi || 0);
                if (aqi < minAqi) { minAqi = aqi; minTime = row.timestamp; }
                if (aqi > maxAqi) { maxAqi = aqi; maxTime = row.timestamp; }
                
                const dt = new Date(row.timestamp);
                const timeStr = dt.toLocaleTimeString('en-US', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute:'2-digit' }).toLowerCase();
                
                labels.push(timeStr);
                data.push(aqi);
            });

            renderGenericChart('overview-aqi-chart', labels, data, 'AQI', '#00bfa5');
        }

        // Weather History Charts
        const weatherHistoryRes = bulkData.weather_history;
        if (weatherHistoryRes && weatherHistoryRes.length > 0) {
            cachedWeatherHistory = weatherHistoryRes;
            renderWeatherHistoryCharts(weatherHistoryRes);
        }

        setupCarouselScrollListener();
        
    } catch(e) {
        console.error(e);
        if (window.updateGlobalLiveBadge) {
            window.updateGlobalLiveBadge(false, 'Unable to connect to cloud telemetry');
        }
    } finally {
        if (!isSilent && icon) {
            icon.style.animation = 'none';
        }
        if (!animationFrameId) {
            initCanvas();
        }
        if (window.lucide) { lucide.createIcons(); }
    }
}

function renderWeatherHistoryCharts(weatherHistoryRes) {
    if (!weatherHistoryRes || !weatherHistoryRes.length) return;
    const wHistory = weatherHistoryRes.slice().reverse();
    const wLabels = [];
    const tempData = [], humData = [], windData = [], rainData = [];

    let minTemp = 999, maxTemp = -999;
    let minHum = 999, maxHum = -999;
    let minWind = 999, maxWind = -999;
    let minRain = 999, maxRain = -999;

    wHistory.forEach(row => {
        const dt = new Date(row.timestamp || row.created_at || new Date());
        wLabels.push(dt.toLocaleTimeString('en-US', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute:'2-digit' }).toLowerCase());
        
        let tVal = row.temperature != null ? Number(row.temperature) : 0;
        if (tempUnit === 'F') tVal = Number(((tVal * 9/5) + 32).toFixed(1));
        else tVal = Number(tVal.toFixed(1));

        const hVal = row.humidity != null ? Number(Number(row.humidity).toFixed(1)) : 0;
        
        let wVal = row.wind_speed != null ? Number(row.wind_speed) : 0;
        if (windUnit === 'ms') wVal = Number((wVal / 3.6).toFixed(1));
        else wVal = Number(wVal.toFixed(1));

        const rawR = row.rain_gauge != null ? Number(row.rain_gauge) : 0;
        const rVal = rainUnit === 'cm' ? Number((rawR / 10).toFixed(2)) : Number(rawR.toFixed(1));

        tempData.push(tVal);
        humData.push(hVal);
        windData.push(wVal);
        rainData.push(rVal);

        if (tVal < minTemp) minTemp = tVal;
        if (tVal > maxTemp) maxTemp = tVal;
        if (hVal < minHum) minHum = hVal;
        if (hVal > maxHum) maxHum = hVal;
        if (wVal < minWind) minWind = wVal;
        if (wVal > maxWind) maxWind = wVal;
        if (rVal < minRain) minRain = rVal;
        if (rVal > maxRain) maxRain = rVal;
    });

    // Update Current Value Badges on Chart Slides
    const tCur = document.getElementById('overview-temp-current');
    if (tCur) tCur.innerText = tempData.length ? `${tempData[tempData.length - 1]} °${tempUnit}` : '--';
    const hCur = document.getElementById('overview-hum-current');
    if (hCur) hCur.innerText = humData.length ? `${humData[humData.length - 1]}%` : '--';
    const wCur = document.getElementById('overview-wind-current');
    if (wCur) wCur.innerText = windData.length ? `${windData[windData.length - 1]} ${windUnit === 'kmh' ? 'km/h' : 'm/s'}` : '--';
    const rCur = document.getElementById('overview-rain-current');
    if (rCur) rCur.innerText = rainData.length ? `${rainData[rainData.length - 1]} ${rainUnit}` : '--';

    renderGenericChart('overview-temp-chart', wLabels, tempData, `Temperature (°${tempUnit})`, '#f97316');
    renderGenericChart('overview-hum-chart', wLabels, humData, 'Humidity (%)', '#0ea5e9');
    renderGenericChart('overview-wind-chart', wLabels, windData, `Wind Speed (${windUnit === 'kmh' ? 'km/h' : 'm/s'})`, '#10b981');
    renderGenericChart('overview-rain-chart', wLabels, rainData, `Rainfall (${rainUnit})`, '#6366f1');

    setupCarouselScrollListener();
}

var chartInstances = window.__chartInstances || {};
window.__chartInstances = chartInstances;

function renderGenericChart(canvasId, labels, data, labelName, colorHex) {
    if (typeof Chart === 'undefined') {
        setTimeout(() => renderGenericChart(canvasId, labels, data, labelName, colorHex), 100);
        return;
    }
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    
    // In-place silent update without destroying or flickering
    if (chartInstances[canvasId]) {
        try {
            const existingChart = chartInstances[canvasId];
            if (existingChart && existingChart.ctx) {
                existingChart.data.labels = labels;
                if (existingChart.data.datasets && existingChart.data.datasets[0]) {
                    existingChart.data.datasets[0].data = data;
                }
                existingChart.update('none');
                return;
            }
        } catch(e) {
            try { chartInstances[canvasId].destroy(); } catch(err) {}
            delete chartInstances[canvasId];
        }
    }
    
    // Create gradient
    const gradient = ctx.createLinearGradient(0, 0, 0, 250);
    // hex to rgb
    let hex = colorHex.replace('#', '');
    let r = parseInt(hex.substring(0, 2), 16);
    let g = parseInt(hex.substring(2, 4), 16);
    let b = parseInt(hex.substring(4, 6), 16);
    
    gradient.addColorStop(0, `rgba(${r}, ${g}, ${b}, 0.4)`);
    gradient.addColorStop(1, `rgba(${r}, ${g}, ${b}, 0.0)`);

    chartInstances[canvasId] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: labelName,
                data: data,
                borderColor: colorHex,
                backgroundColor: gradient,
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: colorHex,
                pointHoverBorderColor: '#ffffff',
                pointHoverBorderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index',
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#ffffff',
                    titleColor: '#64748b',
                    bodyColor: '#0f172a',
                    bodyFont: { size: 16, weight: 'bold' },
                    borderColor: '#cbd5e1',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return labelName + ': ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: '#64748b',
                        font: { size: 10 },
                        maxTicksLimit: 6
                    }
                },
                y: {
                    grid: {
                        color: '#e2e8f0',
                        borderDash: [3, 3]
                    },
                    ticks: {
                        color: '#64748b',
                        font: { size: 10 }
                    },
                    beginAtZero: true
                }
            }
        }
    });
}

function initCanvas() {
    const canvas = document.getElementById('ambient-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    
    if (animationFrameId) cancelAnimationFrame(animationFrameId);

    if (canvasResizeHandler) {
        window.removeEventListener('resize', canvasResizeHandler);
    }
    function resize() {
        if (!canvas || !canvas.parentElement) return;
        canvas.width = canvas.parentElement.offsetWidth || window.innerWidth;
        canvas.height = canvas.parentElement.offsetHeight || window.innerHeight;
    }
    canvasResizeHandler = resize;
    window.addEventListener('resize', canvasResizeHandler);
    resize();

    const speedFactor = Math.max(0.6, (Number(rawWind || 3.5) / 10.0));

    const particles = Array.from({ length: 50 }, () => ({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        radius: Math.random() * 3 + 1.5,
        speedX: (0.4 + Math.random() * 0.8) * speedFactor,
        speedY: (Math.random() - 0.5) * 0.3,
        alpha: Math.random() * 0.25 + 0.1,
        angle: Math.random() * Math.PI * 2,
        waveFreq: Math.random() * 0.02 + 0.01,
        waveAmp: Math.random() * 1.5 + 0.5,
    }));

    const streamRibbons = Array.from({ length: 4 }, (_, i) => ({
        y: (canvas.height / 5) * (i + 1) + (Math.random() - 0.5) * 40,
        length: canvas.width * 0.45 + Math.random() * 100,
        x: Math.random() * canvas.width,
        speed: (1.2 + i * 0.4) * speedFactor,
        thickness: 1.2 + Math.random() * 1.2,
        opacity: 0.12 + Math.random() * 0.12,
    }));

    let tick = 0;

    function render() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        tick += 0.02;

        for (const rib of streamRibbons) {
            ctx.beginPath();
            const grad = ctx.createLinearGradient(rib.x, rib.y, rib.x + rib.length, rib.y);
            grad.addColorStop(0, 'rgba(0, 191, 165, 0)');
            grad.addColorStop(0.5, `rgba(14, 165, 233, ${rib.opacity})`);
            grad.addColorStop(1, 'rgba(0, 191, 165, 0)');

            ctx.strokeStyle = grad;
            ctx.lineWidth = rib.thickness;
            ctx.lineCap = 'round';

            ctx.moveTo(rib.x, rib.y);
            for (let x = 0; x < rib.length; x += 20) {
                const cy = rib.y + Math.sin((rib.x + x) * 0.01 + tick) * 8;
                ctx.lineTo(rib.x + x, cy);
            }
            ctx.stroke();

            rib.x += rib.speed;
            if (rib.x > canvas.width + 100) {
                rib.x = -rib.length - 50;
                rib.y = (canvas.height / 5) * (Math.floor(Math.random() * 4) + 1);
            }
        }

        for (const p of particles) {
            ctx.beginPath();
            p.angle += p.waveFreq;
            const currentY = p.y + Math.sin(p.angle) * p.waveAmp;

            if (isRainingGlobal) {
                ctx.strokeStyle = `rgba(56, 189, 248, ${p.alpha * 1.5})`;
                ctx.lineWidth = 1.6;
                ctx.moveTo(p.x, currentY);
                ctx.lineTo(p.x + p.speedX * 0.8, currentY + 12);
                ctx.stroke();
                p.y += 9 + Math.random() * 4;
            } else {
                const particleGrad = ctx.createRadialGradient(p.x, currentY, 0, p.x, currentY, p.radius * 2);
                particleGrad.addColorStop(0, `rgba(0, 191, 165, ${p.alpha})`);
                particleGrad.addColorStop(0.7, `rgba(56, 189, 248, ${p.alpha * 0.6})`);
                particleGrad.addColorStop(1, 'rgba(255, 255, 255, 0)');

                ctx.fillStyle = particleGrad;
                ctx.arc(p.x, currentY, p.radius * 2, 0, Math.PI * 2);
                ctx.fill();
                p.y += p.speedY;
            }

            p.x += p.speedX;

            if (p.x > canvas.width + 20) p.x = -20;
            if (p.y > canvas.height + 20) p.y = -20;
            if (p.y < -20) p.y = canvas.height + 20;
        }

        animationFrameId = requestAnimationFrame(render);
    }
    render();
}

var autoRefreshOverviewTimer = null;
var onOverviewVisibilityChange = function() {
    if (!document.hidden) {
        refreshData(true);
    }
};

function startAutoRefreshOverview() {
    if (autoRefreshOverviewTimer) clearInterval(autoRefreshOverviewTimer);
    autoRefreshOverviewTimer = setInterval(() => {
        if (!document.hidden) {
            refreshData(true);
        }
    }, 4000);
    document.addEventListener('visibilitychange', onOverviewVisibilityChange);
}

window.__cleanupCurrentPage = function() {
    document.removeEventListener('visibilitychange', onOverviewVisibilityChange);
    if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
        animationFrameId = null;
    }
    if (canvasResizeHandler) {
        window.removeEventListener('resize', canvasResizeHandler);
        canvasResizeHandler = null;
    }
    if (autoRefreshOverviewTimer) {
        clearInterval(autoRefreshOverviewTimer);
        autoRefreshOverviewTimer = null;
    }
    if (typeof mobileChartInstance !== 'undefined' && mobileChartInstance) {
        try { mobileChartInstance.destroy(); } catch(e) {}
        mobileChartInstance = null;
    }
    if (window.__chartInstances) {
        Object.keys(window.__chartInstances).forEach(k => {
            if (window.__chartInstances[k]) {
                try { window.__chartInstances[k].destroy(); } catch(e) {}
            }
        });
        window.__chartInstances = {};
    }
    chartInstances = {};
};

function restoreOverviewState() {
    try {
        updateTempDisplay();
        updateWindDisplay();
        updateRainDisplay();

        const urlParams = new URLSearchParams(window.location.search);
        let slideIdx = parseInt(urlParams.get('slide'), 10);
        if (isNaN(slideIdx)) {
            slideIdx = parseInt(sessionStorage.getItem('overview_active_slide'), 10);
        }
        if (!isNaN(slideIdx) && slideIdx >= 0 && slideIdx < window.TOTAL_CHART_SLIDES) {
            window.currentChartSlideIndex = slideIdx;
            setTimeout(() => {
                scrollChartTo(slideIdx, false);
            }, 60);
            setTimeout(() => {
                scrollChartTo(slideIdx, false);
            }, 260);
        }
    } catch(e) {}
}

window.addEventListener('resize', () => {
    if (typeof window.currentChartSlideIndex === 'number' && window.currentChartSlideIndex > 0) {
        scrollChartTo(window.currentChartSlideIndex, false);
    }
});

if (document.readyState === 'loading') {
    document.addEventListener("DOMContentLoaded", () => {
        applyRainHeroSwitch(<?= $init_is_raining ? 'true' : 'false' ?>);
        setupCarouselScrollListener();
        restoreOverviewState();
        refreshData(false);
        startAutoRefreshOverview();
    });
} else {
    applyRainHeroSwitch(<?= $init_is_raining ? 'true' : 'false' ?>);
    setupCarouselScrollListener();
    restoreOverviewState();
    refreshData(false);
    startAutoRefreshOverview();
}
</script>
