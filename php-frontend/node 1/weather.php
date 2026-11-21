<?php
/**
 * Smart WeatherNet - Dedicated Live Weather Conditions Page
 * Real-time meteorological telemetry stream: Temperature, Humidity,
 * Wind Speed, Gust, Direction, and Rainfall.
 */

require_once __DIR__ . '/../config/nodes.php';
$active_node = smartairnet_get_active_node_id();
$node_config = smartairnet_get_node_config($active_node);
$is_node_2 = ($active_node === '2');

// ── PHP Pre-hydration for instant first-paint ──
$init_weather_temp = '--';
$init_weather_hum = '--';
$init_weather_wind = '--';
$init_weather_gust = '--';
$init_weather_rain = '--';
$init_weather_dir = '--';
$init_weather_today_rain = '--';
$init_weather_min = '--';
$init_weather_max = '--';
$init_weather_avg = '--';
$init_weather_comfort = 'Comfortable';
$init_weather_time_str = 'Live Stream';
$init_is_offline = false;
$init_weather_rows = [];

if (!$is_node_2) {
    // Node 1: read from server-side cache
    $cache_candidates = [
        sys_get_temp_dir() . '/smartairnet_cache/' . md5('live_bulk_60') . '.json',
        sys_get_temp_dir() . '/smartairnet_cache/' . md5('overview_bulk') . '.json',
        sys_get_temp_dir() . '/smartairnet_cache/last_known_hero.json',
    ];
    $init_bulk = null;
    foreach ($cache_candidates as $cf) {
        if (file_exists($cf)) {
            $raw_cache = @file_get_contents($cf);
            if ($raw_cache && strlen($raw_cache) > 20) {
                $parsed = @json_decode($raw_cache, true);
                if (!empty($parsed['weather'])) {
                    $init_bulk = $parsed;
                    break;
                }
            }
        }
    }

    if (!empty($init_bulk['weather'])) {
        $w = $init_bulk['weather'];
        if (isset($w['temperature']) && is_numeric($w['temperature'])) {
            $init_weather_temp = number_format((float)$w['temperature'], 1);
        }
        if (isset($w['humidity']) && is_numeric($w['humidity'])) {
            $init_weather_hum = number_format((float)$w['humidity'], 1) . '%';
        }
        if (isset($w['wind_speed']) && is_numeric($w['wind_speed'])) {
            $init_weather_wind = number_format((float)$w['wind_speed'], 1) . ' km/h';
        }
        if (isset($w['wind_gust']) && is_numeric($w['wind_gust'])) {
            $init_weather_gust = number_format((float)$w['wind_gust'], 1) . ' km/h';
        }
        if (isset($w['rain_gauge']) && is_numeric($w['rain_gauge'])) {
            $init_weather_rain = number_format((float)$w['rain_gauge'], 1) . ' mm';
        }
        if (!empty($w['wind_direction'])) {
            $init_weather_dir = (string)$w['wind_direction'];
        }
        if (isset($init_bulk['today_rain_total'])) {
            $init_weather_today_rain = number_format((float)$init_bulk['today_rain_total'], 1) . ' mm';
        } elseif (isset($w['rain_gauge'])) {
            $init_weather_today_rain = number_format((float)$w['rain_gauge'], 1) . ' mm';
        }

        $w_ts = $w['timestamp'] ?? ($w['created_at'] ?? null);
        if ($w_ts) {
            $epoch = strtotime($w_ts);
            if ($epoch > 0) {
                $init_weather_time_str = date('h:i A', $epoch);
                $diff = (time() - $epoch) / 60;
                if ($diff > 10) {
                    $init_is_offline = true;
                }
            }
        }
    }

    if (!empty($init_bulk['weather_history']) && is_array($init_bulk['weather_history'])) {
        $init_weather_rows = array_slice($init_bulk['weather_history'], 0, 30);
        $temps = [];
        foreach ($init_bulk['weather_history'] as $wh) {
            if (isset($wh['temperature']) && is_numeric($wh['temperature'])) {
                $temps[] = (float)$wh['temperature'];
            }
        }
        if (!empty($temps)) {
            $init_weather_min = number_format(min($temps), 1);
            $init_weather_max = number_format(max($temps), 1);
            $init_weather_avg = number_format(array_sum($temps) / count($temps), 1);
        }
    }
    if ($init_weather_min === '--' && is_numeric($init_weather_temp)) {
        $init_weather_min = $init_weather_temp;
        $init_weather_max = $init_weather_temp;
        $init_weather_avg = $init_weather_temp;
    }
}
?>

<div id="weather-page-container" class="weather-page-container" style="width: 100%; max-width: 1200px; margin: 0 auto; padding: 12px 14px 60px 14px; animation: fade-in 0.3s ease-out;">

  <!-- Offline Notice Banner -->
  <?php if (!$is_node_2 && $init_is_offline): ?>
    <div id="weather-offline-banner" style="display: flex; background-color: #fff7ed; border: 1.5px solid #fed7aa; border-left: 4px solid #f97316; border-radius: 14px; padding: 12px 16px; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; box-shadow: 0 2px 8px rgba(249, 115, 22, 0.06);">
      <div style="display: flex; align-items: flex-start; gap: 10px; font-size: 13px; color: #475569;">
        <i data-lucide="alert-triangle" style="width: 18px; height: 18px; color: #f97316; margin-top: 2px; flex-shrink: 0;"></i>
        <span><strong style="color: #c2410c;">Weather Telemetry Delay:</strong> Live data stream paused or delayed (>10 mins). Showing last recorded cloud metrics below.</span>
      </div>
      <div style="background-color: #ffedd5; color: #c2410c; font-size: 11px; font-weight: 800; padding: 4px 12px; border-radius: 999px; letter-spacing: 0.05em; font-family: var(--font-mono); white-space: nowrap;">
        OFFLINE
      </div>
    </div>
  <?php endif; ?>

  <!-- Page Header Row -->
  <div style="display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 16px;">
    <div>
      <div style="display: flex; align-items: center; gap: 8px;">
        <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">Live Weather Conditions</h1>
        <span style="font-size: 11px; font-weight: 700; background: <?= $is_node_2 ? '#f1f5f9' : '#ecfdf5' ?>; color: <?= $is_node_2 ? '#64748b' : '#059669' ?>; border: 1px solid <?= $is_node_2 ? '#cbd5e1' : '#a7f3d0' ?>; padding: 2px 8px; border-radius: 999px;">
          <?= htmlspecialchars($node_config['short_name'] ?? 'Node 1') ?>
        </span>
      </div>
      <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 5px;">
        <div style="display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; background: #ffffff; border: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #475569; font-family: var(--font-mono);">
          <i data-lucide="map-pin" style="width: 12px; height: 12px; color: #00bfa5;"></i>
          <span>Lat: <strong id="weather-header-lat"><?= htmlspecialchars($node_config['location']['latitude'] ?? '13.2611') ?>° N</strong></span>
          <span>•</span>
          <span>Lon: <strong id="weather-header-lon"><?= htmlspecialchars($node_config['location']['longitude'] ?? '74.7869') ?>° E</strong></span>
        </div>
        <div style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px; background: rgba(0, 191, 165, 0.08); border: 1px solid rgba(0, 191, 165, 0.2); font-size: 10.5px; font-weight: 700; color: #0f766e; font-family: var(--font-mono);">
          <i data-lucide="compass" style="width: 11px; height: 11px; color: #00bfa5;"></i>
          <span id="weather-header-dms"><?= htmlspecialchars($node_config['location']['coords_display'] ?? "13°15'40\" N, 74°47'13\" E") ?></span>
        </div>
      </div>
      <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b; font-weight: 500;">
        Real-time ambient weather metrics, thermal comfort, precipitation, and wind vector telemetry.
      </p>
    </div>

    <div style="font-size: 12px; color: #64748b; font-family: var(--font-mono); display: flex; align-items: center; gap: 8px;">
      <span>Last Updated: <strong id="weather-latest-time" style="color: <?= $is_node_2 ? '#64748b' : '#00bfa5' ?>; font-weight: 700;"><?= htmlspecialchars($is_node_2 ? '--' : $init_weather_time_str) ?></strong></span>
      <span id="weather-live-badge" style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; background-color: <?= $is_node_2 ? '#f1f5f9' : ($init_is_offline ? '#fff7ed' : '#ecfdf5') ?>; color: <?= $is_node_2 ? '#64748b' : ($init_is_offline ? '#c2410c' : '#059669') ?>; border: 1px solid <?= $is_node_2 ? '#e2e8f0' : ($init_is_offline ? '#fed7aa' : '#a7f3d0') ?>;">
        <span style="width: 6px; height: 6px; border-radius: 50%; background-color: <?= $is_node_2 ? '#94a3b8' : ($init_is_offline ? '#f97316' : '#10b981') ?>;" class="<?= (!$is_node_2 && !$init_is_offline) ? 'live-dot-pulse' : '' ?>"></span>
        <span><?= $is_node_2 ? 'STANDBY' : ($init_is_offline ? 'OFFLINE' : 'LIVE') ?></span>
      </span>
    </div>
  </div>

  <!-- Hero Container -->
  <div class="bg-white rounded-[20px] p-5 sm:p-8 border border-slate-200 shadow-[0_4px_24px_rgba(15,23,42,0.05)] mb-6 flex flex-col lg:flex-row gap-6 lg:items-center lg:justify-between" style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
    
    <!-- 1. Left (Hero Temp & Date) -->
    <div class="flex-1 min-w-[280px]">
      <div id="weather-date-str" class="text-[13px] text-slate-500 mb-2 font-semibold flex items-center gap-2">
        <i data-lucide="calendar" class="w-4 h-4 text-[#00bfa5]"></i>
        <span><?= date('l, d F Y') ?> (IST)</span>
      </div>
      
      <div class="flex items-baseline gap-2 mb-3">
        <span id="weather-hero-temp" class="font-mono text-[56px] sm:text-[72px] font-extrabold leading-none tracking-tight text-slate-900"><?= $init_weather_temp ?></span>
        <span class="font-mono text-2xl sm:text-3xl font-bold text-slate-400">°C</span>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <span id="weather-hero-comfort-badge" class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold flex items-center gap-1.5">
          <span><?= $is_node_2 ? 'Standby Mode' : 'Thermal: ' . htmlspecialchars($init_weather_comfort) ?></span>
        </span>
        <span class="px-3 py-1 rounded-full bg-slate-50 text-slate-600 border border-slate-200 text-xs font-semibold">
          <?= htmlspecialchars($node_config['name'] ?? 'Node 1') ?>
        </span>
      </div>
    </div>

    <!-- Divider for Mobile -->
    <hr class="border-slate-200 lg:hidden">

    <!-- 2. Right (Today's Temp Range) -->
    <div class="flex-1 min-w-[260px] flex flex-col items-center justify-center lg:border-l lg:border-slate-200 lg:pl-8">
      <div class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-1.5">
        <i data-lucide="thermometer" class="w-3.5 h-3.5 text-orange-500"></i>
        <span>Today's Temperature Profile</span>
      </div>

      <div class="flex items-center justify-between w-full max-w-[280px] text-center bg-slate-50 p-4 rounded-2xl border border-slate-200">
        <div>
          <div id="weather-low-temp" class="font-mono text-2xl sm:text-3xl font-extrabold text-sky-500"><?= $init_weather_min ?>°</div>
          <div class="text-[10px] sm:text-[11px] font-extrabold text-slate-400 mt-1">LOW</div>
        </div>
        <div style="border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; padding: 0 16px;">
          <div id="weather-avg-temp" class="font-mono text-2xl sm:text-3xl font-extrabold text-emerald-500"><?= $init_weather_avg ?>°</div>
          <div class="text-[10px] sm:text-[11px] font-extrabold text-slate-400 mt-1">AVG</div>
        </div>
        <div>
          <div id="weather-high-temp" class="font-mono text-2xl sm:text-3xl font-extrabold text-orange-500"><?= $init_weather_max ?>°</div>
          <div class="text-[10px] sm:text-[11px] font-extrabold text-slate-400 mt-1">HIGH</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Weather Cards Grid (6 Parameter Cards) -->
  <div style="margin-bottom: 24px;">
    <h2 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
      <i data-lucide="activity" style="width: 17px; height: 17px; color: #00bfa5;"></i>
      <span>Atmospheric Telemetry Sensors</span>
    </h2>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-3 gap-3 sm:gap-4">
      
      <!-- 1. Temperature -->
      <div class="bg-white border border-slate-200 rounded-[18px] p-4 sm:p-5 shadow-[0_2px_12px_rgba(15,23,42,0.03)] flex flex-col justify-between min-h-[120px] transition-all hover:border-[#00bfa5]">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center text-xl shrink-0">🌡️</div>
          <div>
            <div class="text-sm font-bold text-slate-900 leading-tight">Temperature</div>
            <div class="text-[11px] text-slate-400 font-medium">Ambient Dry Bulb</div>
          </div>
        </div>
        <div class="flex items-baseline gap-1 mt-3">
          <span id="card-temp" class="font-mono text-2xl sm:text-3xl font-extrabold text-orange-600"><?= $init_weather_temp ?></span>
          <span class="font-mono text-sm font-bold text-slate-400">°C</span>
        </div>
      </div>

      <!-- 2. Humidity -->
      <div class="bg-white border border-slate-200 rounded-[18px] p-4 sm:p-5 shadow-[0_2px_12px_rgba(15,23,42,0.03)] flex flex-col justify-between min-h-[120px] transition-all hover:border-[#00bfa5]">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-xl shrink-0">💧</div>
          <div>
            <div class="text-sm font-bold text-slate-900 leading-tight">Relative Humidity</div>
            <div class="text-[11px] text-slate-400 font-medium">BME280 Digital Sensor</div>
          </div>
        </div>
        <div class="flex items-baseline gap-1 mt-3">
          <span id="card-hum" class="font-mono text-2xl sm:text-3xl font-extrabold text-sky-600"><?= $init_weather_hum ?></span>
        </div>
      </div>

      <!-- 3. Wind Speed -->
      <div class="bg-white border border-slate-200 rounded-[18px] p-4 sm:p-5 shadow-[0_2px_12px_rgba(15,23,42,0.03)] flex flex-col justify-between min-h-[120px] transition-all hover:border-[#00bfa5]">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl shrink-0">💨</div>
          <div>
            <div class="text-sm font-bold text-slate-900 leading-tight">Wind Velocity</div>
            <div class="text-[11px] text-slate-400 font-medium">Pulse Digital Anemometer</div>
          </div>
        </div>
        <div class="flex items-baseline gap-1 mt-3">
          <span id="card-wind" class="font-mono text-2xl sm:text-3xl font-extrabold text-indigo-600"><?= $init_weather_wind ?></span>
        </div>
      </div>

      <!-- 4. Wind Gust -->
      <div class="bg-white border border-slate-200 rounded-[18px] p-4 sm:p-5 shadow-[0_2px_12px_rgba(15,23,42,0.03)] flex flex-col justify-between min-h-[120px] transition-all hover:border-[#00bfa5]">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl shrink-0">🌪️</div>
          <div>
            <div class="text-sm font-bold text-slate-900 leading-tight">Peak Wind Gust</div>
            <div class="text-[11px] text-slate-400 font-medium">Instantaneous Vector</div>
          </div>
        </div>
        <div class="flex items-baseline gap-1 mt-3">
          <span id="card-gust" class="font-mono text-2xl sm:text-3xl font-extrabold text-purple-600"><?= $init_weather_gust ?></span>
        </div>
      </div>

      <!-- 5. Rainfall -->
      <div class="bg-white border border-slate-200 rounded-[18px] p-4 sm:p-5 shadow-[0_2px_12px_rgba(15,23,42,0.03)] flex flex-col justify-between min-h-[120px] transition-all hover:border-[#00bfa5]">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center text-xl shrink-0">🌧️</div>
          <div>
            <div class="text-sm font-bold text-slate-900 leading-tight">Rainfall Accumulation</div>
            <div class="text-[11px] text-slate-400 font-medium">Tipping Bucket Gauge</div>
          </div>
        </div>
        <div class="flex items-end justify-between mt-3 flex-wrap gap-2">
          <span id="card-rain" class="font-mono text-2xl sm:text-3xl font-extrabold text-cyan-600"><?= $init_weather_rain ?></span>
          <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
            Today: <strong id="card-today-rain"><?= $init_weather_today_rain ?></strong>
          </span>
        </div>
      </div>

      <!-- 6. Wind Direction -->
      <div class="bg-white border border-slate-200 rounded-[18px] p-4 sm:p-5 shadow-[0_2px_12px_rgba(15,23,42,0.03)] flex flex-col justify-between min-h-[120px] transition-all hover:border-[#00bfa5]">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl shrink-0">🧭</div>
          <div>
            <div class="text-sm font-bold text-slate-900 leading-tight">Wind Direction</div>
            <div class="text-[11px] text-slate-400 font-medium">Potentiometer Vane</div>
          </div>
        </div>
        <div class="mt-3">
          <span id="card-dir" class="font-sans text-xl sm:text-2xl font-extrabold text-slate-700"><?= $init_weather_dir ?></span>
        </div>
      </div>

    </div>
  </div>

  <!-- Telemetry Stream Table -->
  <div style="margin-top: 24px;">
    <div style="margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
      <h2 style="font-size: 17px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
        <i data-lucide="table" style="width: 18px; height: 18px; color: #00bfa5;"></i>
        <span>Live Temperature &amp; Weather Telemetry Stream</span>
      </h2>
      <span style="font-size: 11px; font-weight: 600; color: #64748b;">
        Displaying latest sensor cycles
      </span>
    </div>

    <div style="background-color: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);">
      <!-- Mobile swipe indicator -->
      <div class="block sm:hidden px-4 py-2 bg-slate-50 border-b border-slate-100 text-[11px] text-slate-500 font-medium flex items-center justify-between">
        <span>👈 Swipe horizontally to view all weather columns</span>
        <span class="font-mono text-[10px] bg-sky-100 text-sky-700 px-1.5 py-0.5 rounded font-bold">STREAM</span>
      </div>
      <div style="overflow-x: auto; max-height: 440px; -webkit-overflow-scrolling: touch;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; font-family: var(--font-sans); min-width: 1100px;">
          <thead style="position: sticky; top: 0; z-index: 1; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
            <tr>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Timestamp (IST)</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Temperature (°C)</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Humidity (%)</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Wind Speed (km/h)</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Wind Gust (km/h)</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Wind Direction</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Rain Gauge (mm)</th>
            </tr>
          </thead>
          <tbody id="weather-tbody">
            <?php if ($is_node_2): ?>
              <tr>
                <td colspan="7" style="padding: 48px 20px; text-align: center; color: #64748b; font-size: 13px;">
                  <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;">
                    <i data-lucide="radio" style="width: 32px; height: 32px; color: #94a3b8;"></i>
                    <strong style="color: #334155;">Currently in Standby Mode</strong>
                    <span style="color: #64748b; font-size: 12px;">No live weather telemetry has been recorded yet.</span>
                  </div>
                </td>
              </tr>
            <?php elseif (!empty($init_weather_rows)): ?>
              <?php foreach ($init_weather_rows as $row): ?>
                <?php
                  $ts = $row['timestamp'] ?? ($row['created_at'] ?? '--');
                  $formatted_ts = $ts;
                  if ($ts !== '--') {
                      $ep = strtotime($ts);
                      if ($ep > 0) $formatted_ts = date('d M, h:i A', $ep);
                  }
                  $tVal = isset($row['temperature']) && is_numeric($row['temperature']) ? number_format((float)$row['temperature'], 1) : '--';
                  $hVal = isset($row['humidity']) && is_numeric($row['humidity']) ? number_format((float)$row['humidity'], 1) : '--';
                  $wsVal = isset($row['wind_speed']) && is_numeric($row['wind_speed']) ? number_format((float)$row['wind_speed'], 1) : '--';
                  $wgVal = isset($row['wind_gust']) && is_numeric($row['wind_gust']) ? number_format((float)$row['wind_gust'], 1) : '--';
                  $wdVal = !empty($row['wind_direction']) ? htmlspecialchars($row['wind_direction']) : '--';
                  $rgVal = isset($row['rain_gauge']) && is_numeric($row['rain_gauge']) ? number_format((float)$row['rain_gauge'], 1) : '0.0';
                ?>
                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
                  <td style="padding: 10px 16px; font-family: var(--font-mono); font-size: 12px; color: #334155; white-space: nowrap;"><?= htmlspecialchars($formatted_ts) ?></td>
                  <td style="padding: 10px 16px; font-family: var(--font-mono); font-weight: 700; color: #ea580c; white-space: nowrap;"><?= $tVal ?>°C</td>
                  <td style="padding: 10px 16px; font-family: var(--font-mono); font-weight: 700; color: #0284c7; white-space: nowrap;"><?= $hVal ?>%</td>
                  <td style="padding: 10px 16px; font-family: var(--font-mono); font-weight: 700; color: #4f46e5; white-space: nowrap;"><?= $wsVal ?> km/h</td>
                  <td style="padding: 10px 16px; font-family: var(--font-mono); font-weight: 700; color: #9333ea; white-space: nowrap;"><?= $wgVal ?> km/h</td>
                  <td style="padding: 10px 16px; font-weight: 600; color: #475569; white-space: nowrap;"><?= $wdVal ?></td>
                  <td style="padding: 10px 16px; font-family: var(--font-mono); font-weight: 700; color: #0891b2; white-space: nowrap;"><?= $rgVal ?> mm</td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="7" style="padding: 40px; text-align: center; color: #64748b;">Loading telemetry...</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<script>
(function() {
    const isNode2 = <?= $is_node_2 ? 'true' : 'false' ?>;
    let weatherTimer = null;

    function renderEmptyNode2() {
        // Set all weather numbers to '--'
        const elIds = ['weather-hero-temp', 'weather-low-temp', 'weather-avg-temp', 'weather-high-temp', 
                       'card-temp', 'card-hum', 'card-wind', 'card-gust', 'card-rain', 'card-dir'];
        elIds.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerText = '--';
        });
        const todayRain = document.getElementById('card-today-rain');
        if (todayRain) todayRain.innerText = '--';
        const timeEl = document.getElementById('weather-latest-time');
        if (timeEl) timeEl.innerText = '--';
    }

    async function updateWeatherTelemetry() {
        if (isNode2) {
            renderEmptyNode2();
            return;
        }

        try {
            // Fetch live bulk data
            let bulk = null;
            if (typeof fetchLiveBulk === 'function') {
                bulk = await fetchLiveBulk(true);
            } else {
                const res = await fetch('api/telemetry.php?action=live_bulk&limit=60&_t=' + Date.now());
                bulk = await res.json();
            }

            if (!bulk || !bulk.weather) return;
            const w = bulk.weather;

            // Update Hero
            if (w.temperature != null) {
                const tHero = document.getElementById('weather-hero-temp');
                if (tHero) tHero.innerText = Number(w.temperature).toFixed(1);
                const cTemp = document.getElementById('card-temp');
                if (cTemp) cTemp.innerText = Number(w.temperature).toFixed(1);
            }
            if (w.humidity != null) {
                const cHum = document.getElementById('card-hum');
                if (cHum) cHum.innerText = Number(w.humidity).toFixed(1) + '%';
            }
            if (w.wind_speed != null) {
                const cWind = document.getElementById('card-wind');
                if (cWind) cWind.innerText = Number(w.wind_speed).toFixed(1) + ' km/h';
            }
            if (w.wind_gust != null) {
                const cGust = document.getElementById('card-gust');
                if (cGust) cGust.innerText = Number(w.wind_gust).toFixed(1) + ' km/h';
            }
            if (w.rain_gauge != null) {
                const cRain = document.getElementById('card-rain');
                if (cRain) cRain.innerText = Number(w.rain_gauge).toFixed(1) + ' mm';
            }
            if (bulk.today_rain_total != null) {
                const cTodayRain = document.getElementById('card-today-rain');
                if (cTodayRain) cTodayRain.innerText = Number(bulk.today_rain_total).toFixed(1) + ' mm';
            }
            if (w.wind_direction) {
                const cDir = document.getElementById('card-dir');
                if (cDir) cDir.innerText = String(w.wind_direction);
            }

            // Update Time
            const ts = w.timestamp || w.created_at;
            if (ts) {
                const dt = new Date(ts);
                if (!isNaN(dt.getTime())) {
                    const timeFormatted = dt.toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', hour12: true });
                    const tEl = document.getElementById('weather-latest-time');
                    if (tEl) tEl.innerText = timeFormatted;
                }
            }

            // Dynamically hydrate coordinates from telemetry
            const latVal = (w.latitude != null) ? w.latitude : (bulk.latitude != null ? bulk.latitude : null);
            const lonVal = (w.longitude != null) ? w.longitude : (bulk.longitude != null ? bulk.longitude : null);
            const coordsDisplay = w.coords_display || bulk.coords_display || null;
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
                    navCoordsEl.style.display = 'inline-block';
                }
                if (navCoordsMobileEl) {
                    navCoordsMobileEl.innerText = dmsStr;
                    navCoordsMobileEl.style.display = 'inline';
                }
            } else if (coordsDisplay && coordsDisplay !== '--') {
                if (dmsEl) dmsEl.innerText = coordsDisplay;
                if (navCoordsEl) {
                    navCoordsEl.innerText = coordsDisplay;
                    navCoordsEl.style.display = 'inline-block';
                }
                if (navCoordsMobileEl) {
                    navCoordsMobileEl.innerText = coordsDisplay;
                    navCoordsMobileEl.style.display = 'inline';
                }
            }

            // Update Min / Avg / Max
            if (bulk.weather_history && Array.isArray(bulk.weather_history) && bulk.weather_history.length > 0) {
                const temps = bulk.weather_history
                    .map(r => r.temperature != null ? Number(r.temperature) : null)
                    .filter(t => t !== null && !isNaN(t));
                if (temps.length > 0) {
                    const minT = Math.min(...temps).toFixed(1);
                    const maxT = Math.max(...temps).toFixed(1);
                    const avgT = (temps.reduce((a, b) => a + b, 0) / temps.length).toFixed(1);
                    const elMin = document.getElementById('weather-low-temp');
                    if (elMin) elMin.innerText = minT + '°';
                    const elAvg = document.getElementById('weather-avg-temp');
                    if (elAvg) elAvg.innerText = avgT + '°';
                    const elMax = document.getElementById('weather-high-temp');
                    if (elMax) elMax.innerText = maxT + '°';
                }

                // Render Table
                const tbody = document.getElementById('weather-tbody');
                if (tbody) {
                    const rowsHtml = bulk.weather_history.slice(0, 30).map(row => {
                        const rowTs = row.timestamp || row.created_at || '--';
                        let fTs = rowTs;
                        if (rowTs !== '--') {
                            const d = new Date(rowTs);
                            if (!isNaN(d.getTime())) {
                                fTs = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) + ', ' +
                                      d.toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', hour12: true });
                            }
                        }
                        const tVal = (row.temperature != null) ? Number(row.temperature).toFixed(1) + '°C' : '--';
                        const hVal = (row.humidity != null) ? Number(row.humidity).toFixed(1) + '%' : '--';
                        const wsVal = (row.wind_speed != null) ? Number(row.wind_speed).toFixed(1) + ' km/h' : '--';
                        const wgVal = (row.wind_gust != null) ? Number(row.wind_gust).toFixed(1) + ' km/h' : '--';
                        const wdVal = row.wind_direction ? String(row.wind_direction) : '--';
                        const rgVal = (row.rain_gauge != null) ? Number(row.rain_gauge).toFixed(1) + ' mm' : '0.0 mm';

                        return `<tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
                          <td style="padding: 10px 16px; font-family: var(--font-mono); font-size: 12px; color: #334155; white-space: nowrap;">${fTs}</td>
                          <td style="padding: 10px 16px; font-family: var(--font-mono); font-weight: 700; color: #ea580c; white-space: nowrap;">${tVal}</td>
                          <td style="padding: 10px 16px; font-family: var(--font-mono); font-weight: 700; color: #0284c7; white-space: nowrap;">${hVal}</td>
                          <td style="padding: 10px 16px; font-family: var(--font-mono); font-weight: 700; color: #4f46e5; white-space: nowrap;">${wsVal}</td>
                          <td style="padding: 10px 16px; font-family: var(--font-mono); font-weight: 700; color: #9333ea; white-space: nowrap;">${wgVal}</td>
                          <td style="padding: 10px 16px; font-weight: 600; color: #475569; white-space: nowrap;">${wdVal}</td>
                          <td style="padding: 10px 16px; font-family: var(--font-mono); font-weight: 700; color: #0891b2; white-space: nowrap;">${rgVal}</td>
                        </tr>`;
                    }).join('');
                    tbody.innerHTML = rowsHtml;
                }
            }
        } catch (e) {
            console.warn('Weather telemetry update error:', e);
        }
    }

    // Initialize icons
    if (window.lucide && typeof lucide.createIcons === 'function') {
        lucide.createIcons();
    }

    if (!isNode2) {
        // Poll every 6 seconds for fresh telemetry
        weatherTimer = setInterval(updateWeatherTelemetry, 6000);
    } else {
        renderEmptyNode2();
    }

    window.__cleanupCurrentPage = function() {
        if (weatherTimer) {
            clearInterval(weatherTimer);
            weatherTimer = null;
        }
    };
})();
</script>
