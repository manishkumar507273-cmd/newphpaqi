<?php
// Retrieve cached bulk telemetry for 0ms instant first-paint on both desktop and mobile
$cache_file = sys_get_temp_dir() . '/smartairnet_cache/' . md5('overview_bulk') . '.json';
$init_bulk = null;
if (file_exists($cache_file)) {
    $raw_cache = @file_get_contents($cache_file);
    if ($raw_cache && strlen($raw_cache) > 20) {
        $init_bulk = @json_decode($raw_cache, true);
    }
}

// AQI pre-calculated fields
$init_aqi = null;
$init_aqi_color = '#16a34a';
$init_aqi_bg = '#f0fdf4';
$init_aqi_border = '#86efac';
$init_aqi_text = '#15803d';
$init_aqi_label = 'Good';
$init_aqi_dom = 'O₃';
$init_aqi_bar_width = '6%';

if (!empty($init_bulk['aqi'])) {
    $aqi_data = $init_bulk['aqi'];
    if (isset($aqi_data['cpcb_aqi'])) {
        $init_aqi = (int)round($aqi_data['cpcb_aqi']);
        $init_aqi_dom = !empty($aqi_data['dominant_pollutant']) ? htmlspecialchars($aqi_data['dominant_pollutant']) : 'O₃';
        
        if ($init_aqi <= 50) {
            $init_aqi_label = 'Good'; $init_aqi_color = '#16a34a'; $init_aqi_bg = '#f0fdf4'; $init_aqi_border = '#86efac'; $init_aqi_text = '#15803d';
        } elseif ($init_aqi <= 100) {
            $init_aqi_label = 'Satisfactory'; $init_aqi_color = '#65a30d'; $init_aqi_bg = '#f7fee7'; $init_aqi_border = '#bef264'; $init_aqi_text = '#3f6212';
        } elseif ($init_aqi <= 200) {
            $init_aqi_label = 'Moderate'; $init_aqi_color = '#d97706'; $init_aqi_bg = '#fffbeb'; $init_aqi_border = '#fde68a'; $init_aqi_text = '#92400e';
        } elseif ($init_aqi <= 300) {
            $init_aqi_label = 'Poor'; $init_aqi_color = '#ea580c'; $init_aqi_bg = '#fff7ed'; $init_aqi_border = '#fed7aa'; $init_aqi_text = '#9a3412';
        } elseif ($init_aqi <= 400) {
            $init_aqi_label = 'Very Poor'; $init_aqi_color = '#dc2626'; $init_aqi_bg = '#fef2f2'; $init_aqi_border = '#fecaca'; $init_aqi_text = '#991b1b';
        } else {
            $init_aqi_label = 'Severe'; $init_aqi_color = '#9333ea'; $init_aqi_bg = '#faf5ff'; $init_aqi_border = '#e9d5ff'; $init_aqi_text = '#6b21a8';
        }
        $init_aqi_bar_width = min(100, max(4, round(($init_aqi / 500) * 100))) . '%';
    }
}

// Weather pre-calculated fields
$init_temp = '--';
$init_temp_badge = 'Pleasant';
$init_temp_bar_width = '0%';

$init_hum = '--';
$init_hum_badge = 'Comfort';
$init_hum_bar_width = '0%';

$init_wind = '--';
$init_wind_badge = 'Calm';
$init_wind_bar_width = '0%';
$init_wind_kmh = 0.0;

$init_live_rain = '--';
$init_today_rain = '--';

if (!empty($init_bulk['weather'])) {
    $w = $init_bulk['weather'];
    if (isset($w['temperature']) && $w['temperature'] !== null) {
        $init_temp = number_format((float)$w['temperature'], 1, '.', '');
        $init_temp_badge = ((float)$w['temperature'] > 30) ? 'Warm' : (((float)$w['temperature'] < 22) ? 'Cool' : 'Pleasant');
        $init_temp_bar_width = min(100, max(5, round((((float)$w['temperature'] - 10) / 35) * 100))) . '%';
    }
    if (isset($w['humidity']) && $w['humidity'] !== null) {
        $init_hum = number_format((float)$w['humidity'], 1, '.', '');
        $init_hum_badge = ((float)$w['humidity'] >= 60) ? '☁️ Cloudy' : '☀️ Sunny';
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

if (isset($init_bulk['today_rain_total']) && $init_bulk['today_rain_total'] !== null) {
    $init_today_rain = number_format((float)$init_bulk['today_rain_total'], 1, '.', '');
}
?>
<div class="overview-page-root" style="position: relative; min-height: 85vh; border-radius: 24px; overflow: hidden; padding: clamp(16px, 3vw, 28px); background: radial-gradient(ellipse 120% 80% at 50% -10%, #e2e8f0 0%, #f1f5f9 60%, #e2e8f0 100%); color: #0f172a; font-family: var(--font-sans); border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04); animation: fade-in 0.4s ease-out;">
  
  <!-- Dynamic Ambient Canvas -->
  <canvas id="ambient-canvas" style="position: absolute; inset: 0; pointer-events: none; z-index: 1; opacity: 0.9;"></canvas>

  <!-- Floating Environmental Pastel Auras -->
  <div id="aura-top" style="position: absolute; top: -10%; right: -5%; width: 45vw; height: 45vw; max-width: 500px; max-height: 500px; border-radius: 50%; background: radial-gradient(circle, <?= $init_aqi_color ?>15 0%, rgba(241, 245, 249, 0) 70%); filter: blur(60px); pointer-events: none; z-index: 0; animation: breathAtmosphere 10s ease-in-out infinite alternate;"></div>
  
  <div style="position: absolute; bottom: -10%; left: -5%; width: 45vw; height: 45vw; max-width: 500px; max-height: 500px; border-radius: 50%; background: radial-gradient(circle, rgba(14, 165, 233, 0.12) 0%, rgba(241, 245, 249, 0) 70%); filter: blur(60px); pointer-events: none; z-index: 0;"></div>

  <!-- Content Container -->
  <div style="position: relative; z-index: 2; display: flex; flex-direction: column; gap: 20px;">
    
    <!-- Compact Toolbar -->
    <div class="overview-toolbar" style="display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-wrap: wrap; margin-bottom: 6px;">
      <div style="display: flex; align-items: center; gap: 8px;">
        <div style="display: flex; background-color: #ffffff; border-radius: 999px; padding: 3px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04); gap: 2px;">
          <button id="btn-temp" onclick="toggleTemp()" style="min-height: 38px; min-width: 44px; padding: 6px 14px; border-radius: 999px; border: none; font-size: 13px; font-weight: 700; cursor: pointer; background-color: #f1f5f9; color: #475569; transition: all 0.15s ease;">°C</button>
          <button id="btn-wind" onclick="toggleWind()" style="min-height: 38px; min-width: 52px; padding: 6px 14px; border-radius: 999px; border: none; font-size: 13px; font-weight: 700; cursor: pointer; background-color: #f1f5f9; color: #475569; transition: all 0.15s ease;">km/h</button>
          <button id="btn-rain" onclick="toggleRain()" title="Toggle rain unit: mm or cm" style="min-height: 38px; min-width: 48px; padding: 6px 14px; border-radius: 999px; border: none; font-size: 13px; font-weight: 700; cursor: pointer; background-color: #f1f5f9; color: #475569; transition: all 0.15s ease;">mm</button>
        </div>
        <button id="btn-refresh" onclick="refreshData(false)" class="overview-refresh-btn" style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; cursor: pointer; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04); transition: all 0.2s ease;">
          <i data-lucide="refresh-cw" id="icon-refresh" style="width: 17px; height: 17px; color: #00bfa5;"></i>
        </button>
      </div>
    </div>

    <!-- 5 Core Telemetry Cards -->
    <div class="overview-grid">
      
      <!-- CARD 1: AIR QUALITY INDEX -->
      <div class="overview-core-card" id="card-aqi" style="border-color: <?= $init_aqi_border ?>;">
        <div id="aqi-grad" style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, <?= $init_aqi_color ?>, #06b6d4);"></div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
          <div style="display: flex; align-items: center; gap: 7px;">
            <div id="aqi-icon-bg" class="overview-card-icon" style="width: 28px; height: 28px; border-radius: 8px; background-color: <?= $init_aqi_bg ?>; display: flex; align-items: center; justify-content: center; color: <?= $init_aqi_color ?>; border: 1px solid <?= $init_aqi_border ?>;">
              <i data-lucide="gauge" style="width: 15px; height: 15px;"></i>
            </div>
            <h4 class="overview-card-title" style="margin: 0; font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.15;">Air Quality</h4>
          </div>
          <span id="aqi-badge" class="overview-card-badge" style="padding: 2px 7px; border-radius: 999px; font-size: 10px; font-weight: 700; background-color: <?= $init_aqi_bg ?>; color: <?= $init_aqi_text ?>; border: 1px solid <?= $init_aqi_border ?>; display: inline-flex; align-items: center; gap: 4px;">
            <span id="aqi-badge-dot" style="width: 5px; height: 5px; border-radius: 50%; background-color: <?= $init_aqi_color ?>;"></span>
            <span id="aqi-badge-text"><?= $init_aqi_label ?></span>
          </span>
        </div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin: 4px 0;">
          <div>
            <div style="display: flex; align-items: baseline; gap: 3px;">
              <span id="aqi-val" class="overview-card-val" style="font-size: 32px; font-weight: 900; line-height: 1; letter-spacing: -0.03em; color: <?= $init_aqi_color ?>; font-family: var(--font-mono);"><?= $init_aqi !== null ? $init_aqi : '--' ?></span>
              <span style="font-size: 11px; font-weight: 700; color: #94a3b8;">/ 500</span>
            </div>
            <div style="margin-top: 3px; font-size: 10px; color: #64748b;">
              Dominant: <strong id="aqi-dom" style="color: #0f172a;"><?= $init_aqi_dom ?></strong>
            </div>
          </div>
        </div>
        <div style="margin-top: auto; padding-top: 8px;">
          <div style="height: 5px; border-radius: 999px; background: #f1f5f9; overflow: hidden; position: relative; margin-bottom: 4px;">
            <div id="aqi-bar" style="height: 100%; width: <?= $init_aqi_bar_width ?>; background: linear-gradient(90deg, #10b981 0%, <?= $init_aqi_color ?> 100%); border-radius: 999px; transition: width 0.8s ease;"></div>
          </div>
          <div class="overview-bar-scale">
            <span>0</span><span style="color: #cbd5e1;">•</span><span>100</span><span style="color: #cbd5e1;">•</span><span>500</span>
          </div>
        </div>
      </div>

      <!-- CARD 2: AMBIENT TEMPERATURE -->
      <div class="overview-core-card" style="border-color: #fed7aa;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #ea580c, #facc15);"></div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
          <div style="display: flex; align-items: center; gap: 7px;">
            <div class="overview-card-icon" style="width: 28px; height: 28px; border-radius: 8px; background-color: #fff7ed; display: flex; align-items: center; justify-content: center; color: #ea580c; border: 1px solid #fed7aa;">
              <i data-lucide="thermometer" style="width: 15px; height: 15px;"></i>
            </div>
            <h4 class="overview-card-title" style="margin: 0; font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.15;">Temperature</h4>
          </div>
          <span id="temp-badge" class="overview-card-badge" style="padding: 2px 7px; border-radius: 999px; font-size: 10px; font-weight: 700; background-color: #fff7ed; color: #c2410c; border: 1px solid #fed7aa;">
            <?= $init_temp_badge ?>
          </span>
        </div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin: 4px 0;">
          <div style="display: flex; align-items: baseline; gap: 2px;">
            <span id="temp-val" class="overview-card-val" style="font-size: 32px; font-weight: 900; line-height: 1; letter-spacing: -0.03em; color: #ea580c; font-family: var(--font-mono);"><?= $init_temp ?></span>
            <span id="temp-unit-display" style="font-size: 14px; font-weight: 700; color: #94a3b8;">°C</span>
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
      <div class="overview-core-card" style="border-color: #bae6fd;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #0284c7, #38bdf8);"></div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
          <div style="display: flex; align-items: center; gap: 7px;">
            <div class="overview-card-icon" style="width: 28px; height: 28px; border-radius: 8px; background-color: #f0f9ff; display: flex; align-items: center; justify-content: center; color: #0284c7; border: 1px solid #bae6fd;">
              <i data-lucide="droplets" style="width: 15px; height: 15px;"></i>
            </div>
            <h4 class="overview-card-title" style="margin: 0; font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.15;">Humidity</h4>
          </div>
          <span id="hum-badge" class="overview-card-badge" style="padding: 2px 7px; border-radius: 999px; font-size: 10px; font-weight: 700; background-color: <?= ($init_hum !== '--' && (float)$init_hum >= 60) ? '#f1f5f9' : '#fef9c3' ?>; color: <?= ($init_hum !== '--' && (float)$init_hum >= 60) ? '#475569' : '#854d0e' ?>; border: 1px solid <?= ($init_hum !== '--' && (float)$init_hum >= 60) ? '#cbd5e1' : '#fde047' ?>;">
            <?= $init_hum_badge ?>
          </span>
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
            <span>&lt; 60% Sunny</span><span style="color: #cbd5e1;">•</span><span>60%</span><span style="color: #cbd5e1;">•</span><span>≥ 60% Cloudy</span>
          </div>
        </div>
      </div>

      <!-- CARD 4: WIND SPEED -->
      <div class="overview-core-card" style="border-color: #a7f3d0;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #059669, #10b981);"></div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
          <div style="display: flex; align-items: center; gap: 7px;">
            <div class="overview-card-icon" style="width: 28px; height: 28px; border-radius: 8px; background-color: #ecfdf5; display: flex; align-items: center; justify-content: center; color: #059669; border: 1px solid #a7f3d0;">
              <i data-lucide="wind" style="width: 15px; height: 15px;"></i>
            </div>
            <h4 class="overview-card-title" style="margin: 0; font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.15;">Wind Speed</h4>
          </div>
          <span id="wind-badge" class="overview-card-badge" style="padding: 2px 7px; border-radius: 999px; font-size: 10px; font-weight: 700; background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;">
            <?= $init_wind_badge ?>
          </span>
        </div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin: 4px 0;">
          <div style="display: flex; align-items: baseline; gap: 2px;">
            <span id="wind-val" class="overview-card-val" style="font-size: 32px; font-weight: 900; line-height: 1; letter-spacing: -0.03em; color: #059669; font-family: var(--font-mono);"><?= $init_wind ?></span>
            <span id="wind-unit-display" style="font-size: 12px; font-weight: 700; color: #94a3b8;">km/h</span>
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
      <div class="overview-core-card" id="card-rain" style="border-color: #c7d2fe;">
        <div id="rain-grad" style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #6366f1, #0284c7);"></div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
          <div style="display: flex; align-items: center; gap: 7px;">
            <div id="rain-icon-bg" class="overview-card-icon" style="width: 28px; height: 28px; border-radius: 8px; background-color: #eef2ff; display: flex; align-items: center; justify-content: center; color: #4f46e5; border: 1px solid #c7d2fe;">
              <i data-lucide="cloud-rain" style="width: 15px; height: 15px;"></i>
            </div>
            <h4 class="overview-card-title" style="margin: 0; font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.15;">Rainfall</h4>
          </div>
        </div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: auto; margin-bottom: auto; padding: 6px 0;">
          <div>
            <div style="font-size: 10.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 3px;">Live</div>
            <div style="display: flex; align-items: baseline; gap: 2px;">
              <span id="rain-val" class="overview-card-val" style="font-size: 26px; font-weight: 900; line-height: 1; letter-spacing: -0.03em; color: #4f46e5; font-family: var(--font-mono);"><?= $init_live_rain ?></span>
              <span id="rain-unit-live" onclick="toggleRain()" title="Click to toggle mm / cm" style="font-size: 11px; font-weight: 700; color: #94a3b8; cursor: pointer; padding: 1px 4px; border-radius: 4px; background: rgba(0,0,0,0.03); transition: all 0.15s ease;">mm</span>
            </div>
          </div>
          <div style="width: 1px; height: 36px; background: #e2e8f0; margin: 0 4px;"></div>
          <div style="text-align: right;">
            <div style="font-size: 10.5px; font-weight: 700; color: #0284c7; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 3px;">Today's Rain</div>
            <div style="display: flex; align-items: baseline; justify-content: flex-end; gap: 2px;">
              <span id="total-rain-val" class="overview-card-val" style="font-size: 26px; font-weight: 900; line-height: 1; letter-spacing: -0.03em; color: #0284c7; font-family: var(--font-mono);"><?= $init_today_rain ?></span>
              <span id="rain-unit-total" onclick="toggleRain()" title="Click to toggle mm / cm" style="font-size: 11px; font-weight: 700; color: #0284c7; cursor: pointer; padding: 1px 4px; border-radius: 4px; background: rgba(2, 132, 199, 0.08); transition: all 0.15s ease;">mm</span>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Horizontal Scrollable Charts Container -->
    <div class="flex overflow-x-auto snap-x snap-mandatory gap-4 pb-2" style="scrollbar-width: none; -ms-overflow-style: none;">
      <!-- CSS to hide scrollbar for webkit is below in style block -->
      
      <!-- AQI Line Plot Card -->
      <div class="w-full shrink-0 snap-center" style="background-color: #ffffff; border-radius: 20px; padding: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05); display: flex; flex-direction: column; gap: 16px;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px;">
              <i data-lucide="activity" style="width: 16px; height: 16px; color: #00bfa5;"></i>
              AQI Line Plot
            </div>
            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
              Real-time Air Quality progression
            </div>
          </div>
          <div style="background-color: rgba(0, 191, 165, 0.1); border: 1px solid rgba(0, 191, 165, 0.25); padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; color: #00bfa5; font-family: var(--font-mono);">
            Current: <span id="overview-chart-current">--</span>
          </div>
        </div>

        <div style="width: 100%; height: 250px; position: relative;">
          <canvas id="overview-aqi-chart"></canvas>
        </div>

        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px;" id="overview-chart-stats">
          <div style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; background-color: #ecfdf5; border: 1px solid #a7f3d0; font-size: 11.5px; font-weight: 700; color: #047857; font-family: var(--font-mono);">
            <i data-lucide="arrow-down-right" style="width: 14px; height: 14px;"></i>
            <span id="overview-chart-lowest">Lowest: --</span>
          </div>
          <div style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; background-color: #fff7ed; border: 1px solid #fed7aa; font-size: 11.5px; font-weight: 700; color: #c2410c; font-family: var(--font-mono);">
            <i data-lucide="arrow-up-right" style="width: 14px; height: 14px;"></i>
            <span id="overview-chart-highest">Highest: --</span>
          </div>
        </div>
      </div>

      <!-- Temperature Plot Card -->
      <div class="w-full shrink-0 snap-center" style="background-color: #ffffff; border-radius: 20px; padding: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05); display: flex; flex-direction: column; gap: 16px;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px;">
              <i data-lucide="thermometer" style="width: 16px; height: 16px; color: #f97316;"></i>
              Temperature Plot
            </div>
            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
              Real-time Temperature progression
            </div>
          </div>
        </div>
        <div style="width: 100%; height: 250px; position: relative;">
          <canvas id="overview-temp-chart"></canvas>
        </div>
      </div>

      <!-- Humidity Plot Card -->
      <div class="w-full shrink-0 snap-center" style="background-color: #ffffff; border-radius: 20px; padding: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05); display: flex; flex-direction: column; gap: 16px;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px;">
              <i data-lucide="droplets" style="width: 16px; height: 16px; color: #0ea5e9;"></i>
              Humidity Plot
            </div>
            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
              Real-time Humidity progression
            </div>
          </div>
        </div>
        <div style="width: 100%; height: 250px; position: relative;">
          <canvas id="overview-hum-chart"></canvas>
        </div>
      </div>

      <!-- Wind Speed Plot Card -->
      <div class="w-full shrink-0 snap-center" style="background-color: #ffffff; border-radius: 20px; padding: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05); display: flex; flex-direction: column; gap: 16px;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px;">
              <i data-lucide="wind" style="width: 16px; height: 16px; color: #10b981;"></i>
              Wind Speed Plot
            </div>
            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
              Real-time Wind Speed progression
            </div>
          </div>
        </div>
        <div style="width: 100%; height: 250px; position: relative;">
          <canvas id="overview-wind-chart"></canvas>
        </div>
      </div>

      <!-- Rainfall Plot Card -->
      <div class="w-full shrink-0 snap-center" style="background-color: #ffffff; border-radius: 20px; padding: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05); display: flex; flex-direction: column; gap: 16px;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px;">
              <i data-lucide="cloud-rain" style="width: 16px; height: 16px; color: #6366f1;"></i>
              Rainfall Plot
            </div>
            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
              Real-time Rainfall progression
            </div>
          </div>
        </div>
        <div style="width: 100%; height: 250px; position: relative;">
          <canvas id="overview-rain-chart"></canvas>
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
    @media (max-width: 1024px) {
      .overview-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
      }
    }
    @media (max-width: 640px) {
      .overview-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 8px !important;
      }
      .overview-grid > *:nth-child(5) {
        grid-column: span 2 !important;
      }
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
</div>

<script>
var tempUnit = 'C';
var windUnit = 'kmh';
var rainUnit = 'mm';
var rawTemp = <?= isset($init_bulk['weather']['temperature']) && $init_bulk['weather']['temperature'] !== null ? json_encode((float)$init_bulk['weather']['temperature']) : 'null' ?>;
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
    if (v <= 50) return { label: 'Good', color: '#16a34a', bg: '#f0fdf4', border: '#86efac', text: '#15803d' };
    if (v <= 100) return { label: 'Satisfactory', color: '#65a30d', bg: '#f7fee7', border: '#bef264', text: '#3f6212' };
    if (v <= 200) return { label: 'Moderate', color: '#d97706', bg: '#fffbeb', border: '#fde68a', text: '#92400e' };
    if (v <= 300) return { label: 'Poor', color: '#ea580c', bg: '#fff7ed', border: '#fed7aa', text: '#9a3412' };
    if (v <= 400) return { label: 'Very Poor', color: '#dc2626', bg: '#fef2f2', border: '#fecaca', text: '#991b1b' };
    return { label: 'Severe', color: '#9333ea', bg: '#faf5ff', border: '#e9d5ff', text: '#6b21a8' };
};

function toggleTemp() {
    tempUnit = tempUnit === 'C' ? 'F' : 'C';
    document.getElementById('btn-temp').innerText = '°' + tempUnit;
    updateTempDisplay();
}

function toggleWind() {
    windUnit = windUnit === 'kmh' ? 'ms' : 'kmh';
    document.getElementById('btn-wind').innerText = windUnit === 'kmh' ? 'km/h' : 'm/s';
    updateWindDisplay();
}

function toggleRain() {
    rainUnit = rainUnit === 'mm' ? 'cm' : 'mm';
    updateRainDisplay();
    if (cachedWeatherHistory) {
        renderWeatherHistoryCharts(cachedWeatherHistory);
    }
}

function updateRainDisplay() {
    const liveEl = document.getElementById('rain-val');
    const totalEl = document.getElementById('total-rain-val');
    const unitLiveEl = document.getElementById('rain-unit-live');
    const unitTotalEl = document.getElementById('rain-unit-total');
    const btn = document.getElementById('btn-rain');

    if (btn) btn.innerText = rainUnit;
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
}

function updateTempDisplay() {
    if (rawTemp == null) return;
    const val = tempUnit === 'C' ? Number(rawTemp).toFixed(1) : ((Number(rawTemp) * 9/5) + 32).toFixed(1);
    document.getElementById('temp-val').innerText = val;
    document.getElementById('temp-unit-display').innerText = '°' + tempUnit;
    
    document.getElementById('temp-scale-0').innerText = tempUnit === 'C' ? '15°' : '59°';
    document.getElementById('temp-scale-50').innerText = tempUnit === 'C' ? '28°' : '82°';
    document.getElementById('temp-scale-100').innerText = tempUnit === 'C' ? '40°' : '104°';
    
    const pct = Math.min(100, Math.max(5, ((Number(rawTemp) - 10) / 35) * 100));
    document.getElementById('temp-bar').style.width = pct + '%';
    
    const badge = document.getElementById('temp-badge');
    badge.innerText = val > (tempUnit==='C'?30:86) ? 'Warm' : (val < (tempUnit==='C'?22:71.6) ? 'Cool' : 'Pleasant');
}

function updateWindDisplay() {
    if (rawWind == null) return;
    const val = windUnit === 'kmh' ? Number(rawWind).toFixed(1) : (Number(rawWind) / 3.6).toFixed(1);
    document.getElementById('wind-val').innerText = val;
    document.getElementById('wind-unit-display').innerText = windUnit === 'kmh' ? 'km/h' : 'm/s';
    
    document.getElementById('wind-scale-50').innerText = windUnit === 'kmh' ? '15' : '4.2';
    document.getElementById('wind-scale-100').innerText = windUnit === 'kmh' ? '30 km/h' : '8.3 m/s';
    
    const pct = Math.min(100, Math.max(4, (Number(val) / (windUnit === 'ms' ? 8.3 : 30)) * 100));
    document.getElementById('wind-bar').style.width = pct + '%';
}

var overviewChartInstance = null;

async function refreshData(isSilent = false) {
    const icon = document.getElementById('icon-refresh');
    if (!isSilent && icon) {
        icon.style.animation = 'spin 0.8s linear infinite';
    }
    
    try {
        let bulkData;
        let usedInitialData = false;
        if (window.INITIAL_DATA) {
            bulkData = window.INITIAL_DATA;
            window.INITIAL_DATA = null;
            usedInitialData = true;
        } else {
            const response = await fetch(`api/telemetry.php?action=overview_bulk`);
            bulkData = await response.json();
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
            
            document.getElementById('overview-chart-current').innerText = aqi;
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
            const isCloudy = hum != null && Number(hum) >= 60;
            const humBadge = document.getElementById('hum-badge');
            if (humBadge) {
                humBadge.innerHTML = isCloudy ? '☁️ Cloudy' : '☀️ Sunny';
                humBadge.style.backgroundColor = isCloudy ? '#f1f5f9' : '#fef9c3';
                humBadge.style.color = isCloudy ? '#475569' : '#854d0e';
                humBadge.style.borderColor = isCloudy ? '#cbd5e1' : '#fde047';
            }
            
            // Wind EQ
            const wkmh = Number(rawWind || 0);
            document.getElementById('wind-badge').innerText = wkmh > 15 ? 'Breezy' : (wkmh > 2 ? 'Gentle' : 'Light');
            for(let i=1; i<=4; i++) {
                document.getElementById('eq-'+i).style.backgroundColor = wkmh >= (i*2.5) ? '#059669' : '#e2e8f0';
            }
            
            // Rain (Live & Today's Rain in one card)
            const isRaining = rain != null && Number(rain) > 0;
            isRainingGlobal = isRaining;
            rawLiveRain = rain != null ? Number(rain) : 0;
            rawTodayRain = (bulkData && bulkData.today_rain_total != null) ? Number(bulkData.today_rain_total) : 0;
            
            updateRainDisplay();
            
            document.getElementById('card-rain').style.borderColor = isRaining ? '#7dd3fc' : '#c7d2fe';
            document.getElementById('rain-grad').style.background = isRaining ? 'linear-gradient(90deg, #0284c7, #38bdf8)' : 'linear-gradient(90deg, #6366f1, #0284c7)';
            document.getElementById('rain-icon-bg').style.backgroundColor = isRaining ? '#f0f9ff' : '#eef2ff';
            document.getElementById('rain-icon-bg').style.color = isRaining ? '#0284c7' : '#4f46e5';
            document.getElementById('rain-icon-bg').style.borderColor = isRaining ? '#bae6fd' : '#c7d2fe';
            const rainBadgeEl = document.getElementById('rain-badge');
            if (rainBadgeEl) {
                rainBadgeEl.innerText = isRaining ? '🌧️ Rain' : 'Live';
                rainBadgeEl.style.backgroundColor = isRaining ? '#e0f2fe' : '#eef2ff';
                rainBadgeEl.style.color = isRaining ? '#0369a1' : '#4338ca';
                rainBadgeEl.style.borderColor = isRaining ? '#bae6fd' : '#c7d2fe';
            }
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
                const timeStr = dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'}).toLowerCase();
                
                labels.push(timeStr);
                data.push(aqi);
            });

            if (minAqi !== 9999) {
                const minDt = new Date(minTime).toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'}).toLowerCase();
                document.getElementById('overview-chart-lowest').innerText = `Lowest: ${minAqi} @ ${minDt}`;
            }
            if (maxAqi !== -1) {
                const maxDt = new Date(maxTime).toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'}).toLowerCase();
                document.getElementById('overview-chart-highest').innerText = `Highest: ${maxAqi} @ ${maxDt}`;
            }

            renderGenericChart('overview-aqi-chart', labels, data, 'AQI', '#00bfa5');
        }

        // Weather History Charts
        const weatherHistoryRes = bulkData.weather_history;
        if (weatherHistoryRes && weatherHistoryRes.length > 0) {
            cachedWeatherHistory = weatherHistoryRes;
            renderWeatherHistoryCharts(weatherHistoryRes);
        }

        // Telemetry rendered cleanly from initial payload
        
    } catch(e) {
        console.error(e);
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

    wHistory.forEach(row => {
        const dt = new Date(row.timestamp || row.created_at || new Date());
        wLabels.push(dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'}).toLowerCase());
        tempData.push(row.temperature || 0);
        humData.push(row.humidity || 0);
        windData.push(row.wind_speed || 0);
        const rVal = row.rain_gauge || 0;
        rainData.push(rainUnit === 'cm' ? Number((rVal / 10).toFixed(2)) : rVal);
    });

    renderGenericChart('overview-temp-chart', wLabels, tempData, 'Temperature (°C)', '#f97316');
    renderGenericChart('overview-hum-chart', wLabels, humData, 'Humidity (%)', '#0ea5e9');
    renderGenericChart('overview-wind-chart', wLabels, windData, 'Wind Speed (km/h)', '#10b981');
    renderGenericChart('overview-rain-chart', wLabels, rainData, `Rainfall (${rainUnit})`, '#6366f1');
}

var chartInstances = window.__chartInstances || {};
window.__chartInstances = chartInstances;

function renderGenericChart(canvasId, labels, data, labelName, colorHex) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    
    // In-place silent update without destroying or flickering
    if (chartInstances[canvasId]) {
        const existingChart = chartInstances[canvasId];
        existingChart.data.labels = labels;
        if (existingChart.data.datasets && existingChart.data.datasets[0]) {
            existingChart.data.datasets[0].data = data;
        }
        existingChart.update('none');
        return;
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

function startAutoRefreshOverview() {
    if (autoRefreshOverviewTimer) clearInterval(autoRefreshOverviewTimer);
    autoRefreshOverviewTimer = setInterval(() => {
        if (!document.hidden) {
            refreshData(true);
        }
    }, 12000);
}

window.__cleanupCurrentPage = function() {
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
    Object.keys(chartInstances).forEach(k => {
        if (chartInstances[k]) {
            try { chartInstances[k].destroy(); } catch(e) {}
        }
    });
    chartInstances = {};
};

if (document.readyState === 'loading') {
    document.addEventListener("DOMContentLoaded", () => {
        refreshData(false);
        startAutoRefreshOverview();
    });
} else {
    refreshData(false);
    startAutoRefreshOverview();
}
</script>
