<?php
$today_ist = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
$req_subtab = (isset($_GET['tab']) && in_array($_GET['tab'], ['aqi', 'weather'])) ? $_GET['tab'] : 'aqi';
$req_range_days = (isset($_GET['days']) && in_array((int)$_GET['days'], [1, 3, 7, 30])) ? (int)$_GET['days'] : 1;
$valid_params = ['cpcb_aqi', 'pm25', 'pm10', 'co', 'no2', 'o3', 'temperature', 'humidity', 'wind_speed', 'wind_gust', 'rain_gauge'];
$req_param = (isset($_GET['param']) && in_array($_GET['param'], $valid_params)) ? $_GET['param'] : ($req_subtab === 'weather' ? 'temperature' : 'cpcb_aqi');
$req_type = (isset($_GET['type']) && in_array($_GET['type'], ['bar', 'line'])) ? $_GET['type'] : 'bar';
$req_date = (isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) ? $_GET['date'] : $today_ist;

// 0ms instant pre-hydration from disk cache for today's historical records
$init_history_json = 'null';
$cache_file_today = sys_get_temp_dir() . '/smartairnet_cache/' . md5("history_bulk_" . md5($today_ist . '_1_150')) . '.json';
if (file_exists($cache_file_today)) {
    $raw_cache_today = @file_get_contents($cache_file_today);
    if ($raw_cache_today && strlen($raw_cache_today) > 50) {
        $init_history_json = $raw_cache_today;
    }
}
?>
<div class="p-2.5 sm:p-6 flex flex-col gap-3.5 sm:gap-6 max-w-[1200px] mx-auto text-slate-900 font-sans">
  
  <!-- Header -->
  <div class="flex justify-between items-end mb-1 sm:mb-4">
    <div>
      <h1 class="text-xl sm:text-[28px] font-extrabold text-slate-900 m-0 tracking-tight">Analytics Archive</h1>
      <p class="m-0 text-slate-500 text-[11px] sm:text-sm font-medium mt-0.5">Access and analyze historical telemetry logs.</p>
    </div>
  </div>

  <!-- Sub-Navigation Selector: AQI Historical vs Weather Historical -->
  <div class="flex items-center justify-center mb-1">
    <div class="w-full sm:w-auto grid grid-cols-2 sm:flex items-center bg-white p-1 sm:p-1.5 rounded-2xl sm:rounded-full border border-slate-300 shadow-sm gap-1 sm:gap-1.5">
      <button id="tab-aqi" onclick="switchHistTab('aqi')" class="flex items-center justify-center gap-2 py-2.5 sm:py-2 px-3 sm:px-6 rounded-xl sm:rounded-full font-bold text-xs sm:text-sm cursor-pointer transition-all <?= $req_subtab === 'aqi' ? 'bg-[#00bfa5] text-white shadow-sm' : 'bg-transparent text-slate-500 hover:text-slate-800' ?> min-h-[42px]">
        <i data-lucide="activity" class="w-4 h-4 shrink-0"></i>
        <span>AQI <span class="hidden min-[400px]:inline">Historical</span></span>
      </button>
      <button id="tab-weather" onclick="switchHistTab('weather')" class="flex items-center justify-center gap-2 py-2.5 sm:py-2 px-3 sm:px-6 rounded-xl sm:rounded-full font-bold text-xs sm:text-sm cursor-pointer transition-all <?= $req_subtab === 'weather' ? 'bg-[#00bfa5] text-white shadow-sm' : 'bg-transparent text-slate-500 hover:text-slate-800' ?> min-h-[42px]">
        <i data-lucide="cloud-sun" class="w-4 h-4 shrink-0"></i>
        <span>Weather <span class="hidden min-[400px]:inline">Historical</span></span>
      </button>
    </div>
  </div>

  <!-- ── Analytics Date & Range Picker Card ── -->
  <div class="bg-white rounded-[16px] sm:rounded-[24px] p-3 sm:p-5 border-[1.5px] border-[#00bfa5] shadow-[0_4px_20px_rgba(0,191,165,0.08)]">

    <!-- Top Header Row: Date & Range Selector Controls -->
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
      
      <!-- Left Title & Status -->
      <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 40px; height: 40px; border-radius: 12px; background-color: #ecfdf5; border: 1px solid #a7f3d0; display: flex; align-items: center; justify-content: center; color: #00bfa5; flex-shrink: 0;">
          <i data-lucide="calendar" style="width: 20px; height: 20px;"></i>
        </div>
        <div>
          <div style="font-size: 14px; sm:font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
            <span id="range-header-title">Select Date (24-Hour Telemetry)</span>
          </div>
          <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
            <span id="date-subtitle-desktop">Hourly records: 12:00 AM – 12:00 AM</span>
          </div>
        </div>
      </div>

      <!-- Right Action Controls: Range Dropdown (24h, 3D, 7D, 30D) & Date Picker -->
      <div class="flex items-center gap-2 sm:gap-3 flex-wrap w-full sm:w-auto justify-between sm:justify-end">
        <!-- Range Selector Dropdown -->
        <div style="position: relative; display: inline-flex; align-items: center;">
          <select id="range-select" onchange="setRangeDays(this.value)" class="min-h-[38px] sm:min-h-[42px] cursor-pointer text-xs sm:text-[13px] font-bold text-slate-800 bg-[#f8fafc] hover:bg-white hover:border-[#00bfa5] focus:border-[#00bfa5] transition-all duration-200" style="padding: 7px 32px 7px 32px; border-radius: 12px; border: 1.5px solid #cbd5e1; outline: none; appearance: none; -webkit-appearance: none; -moz-appearance: none; font-family: var(--font-sans); box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <option value="1" <?= $req_range_days === 1 ? 'selected' : '' ?>>24h</option>
            <option value="3" <?= $req_range_days === 3 ? 'selected' : '' ?>>3 Days</option>
            <option value="7" <?= $req_range_days === 7 ? 'selected' : '' ?>>7 Days</option>
            <option value="30" <?= $req_range_days === 30 ? 'selected' : '' ?>>30 Days</option>
          </select>
          <i data-lucide="clock-4" style="position: absolute; left: 10px; width: 14px; height: 14px; color: #00bfa5; pointer-events: none;"></i>
          <i data-lucide="chevron-down" style="position: absolute; right: 10px; width: 14px; height: 14px; color: #64748b; pointer-events: none;"></i>
        </div>

        <!-- Date Picker Input -->
        <div style="position: relative; display: inline-flex; align-items: center;">
          <input type="date" id="history-date-picker" value="<?= htmlspecialchars($req_date) ?>" max="<?= htmlspecialchars($today_ist) ?>" class="min-h-[38px] sm:min-h-[42px]" style="padding: 7px 12px 7px 32px; border-radius: 12px; border: 1.5px solid #cbd5e1; font-size: 13px; font-weight: 600; color: #0f172a; background-color: #f8fafc; cursor: pointer; font-family: var(--font-sans); outline: none;">
          <i data-lucide="calendar" style="position: absolute; left: 10px; width: 14px; height: 14px; color: #00bfa5; pointer-events: none;"></i>
        </div>
      </div>
    </div>

    <!-- Bottom Integrated Section: 24-Hour Dynamic Metrics (Shown for 1 Day range) -->
    <div id="summary-section" style="margin-top: 18px; padding-top: 16px; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; transition: max-height 0.32s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.22s ease, margin 0.25s ease, padding 0.25s ease; overflow: hidden; max-height: 380px; opacity: 1;">
      
      <!-- Left: Date & Logged Count -->
      <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 38px; height: 38px; border-radius: 12px; background-color: #00bfa5; display: flex; align-items: center; justify-content: center; color: #ffffff; box-shadow: 0 2px 10px rgba(0, 191, 165, 0.25); flex-shrink: 0;">
          <i data-lucide="clock" style="width: 19px; height: 19px;"></i>
        </div>
        <div>
          <div id="summary-date-display" style="font-size: 15px; font-weight: 800; color: #0f172a;">--</div>
          <div style="font-size: 12px; color: #64748b; margin-top: 2px; display: flex; align-items: center; gap: 6px;">
            <span style="font-weight: 700; color: #00bfa5;">24 Hourly Points (<span id="log-count">0</span> Logged)</span>
          </div>
        </div>
      </div>

      <!-- Right: Dynamic Average Section -->
      <div id="summary-aqi-block" style="display: <?= $req_subtab === 'weather' ? 'none' : 'flex' ?>; align-items: center; gap: 12px; flex-wrap: wrap;">
        <!-- Avg AQI Card Badge -->
        <div id="avg-aqi-badge-container" style="display: flex; align-items: center; gap: 14px; padding: 8px 16px; border-radius: 16px; background-color: #f8fafc; border: 1.5px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,0.03); transition: all 0.25s ease;">
          <div style="display: flex; flex-direction: column;">
            <div id="avg-aqi-title" style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; display: flex; align-items: center; gap: 5px;">
              <span id="avg-aqi-dot" style="width: 7px; height: 7px; border-radius: 50%; background-color: #94a3b8; display: inline-block;"></span>
              Avg AQI
            </div>
            <div style="display: flex; align-items: baseline; gap: 6px; margin-top: 2px;">
              <span id="avg-aqi-val" style="font-size: 24px; font-weight: 900; color: #64748b; font-family: var(--font-mono); line-height: 1;">--</span>
              <span id="avg-aqi-label" style="display: none; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 999px; background-color: #e2e8f0; color: #475569; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">--</span>
            </div>
          </div>
          <div id="min-max-aqi" style="display: none; border-left: 1px solid #e2e8f0; padding-left: 12px; flex-direction: column; gap: 2px; font-size: 11px; font-weight: 600; color: #64748b;">
            <div>Min: <strong id="min-aqi-val" style="font-family: var(--font-mono);">--</strong></div>
            <div>Peak: <strong id="max-aqi-val" style="font-family: var(--font-mono);">--</strong></div>
          </div>
        </div>

        <!-- Pollutants Mini-Chips -->
        <div id="mini-chips-container" style="display: none; align-items: center; gap: 6px; flex-wrap: wrap;">
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 5px 9px; font-size: 11px;">
            <span style="color: #64748b; font-weight: 600;">PM2.5: </span><strong id="avg-pm25" style="color: #0284c7; font-family: var(--font-mono);">--</strong>
          </div>
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 5px 9px; font-size: 11px;">
            <span style="color: #64748b; font-weight: 600;">PM10: </span><strong id="avg-pm10" style="color: #6366f1; font-family: var(--font-mono);">--</strong>
          </div>
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 5px 9px; font-size: 11px;">
            <span style="color: #64748b; font-weight: 600;">CO: </span><strong id="avg-co" style="color: #16a34a; font-family: var(--font-mono);">--</strong>
          </div>
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 5px 9px; font-size: 11px;">
            <span style="color: #64748b; font-weight: 600;">NO₂: </span><strong id="avg-no2" style="color: #9333ea; font-family: var(--font-mono);">--</strong>
          </div>
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 5px 9px; font-size: 11px;">
            <span style="color: #64748b; font-weight: 600;">O₃: </span><strong id="avg-o3" style="color: #d97706; font-family: var(--font-mono);">--</strong>
          </div>
        </div>
      </div>

      <div id="summary-weather-block" style="display: <?= $req_subtab === 'weather' ? 'flex' : 'none' ?>; align-items: center; gap: 8px; flex-wrap: wrap;">
        <div style="background: #fff7ed; border: 1px solid #fed7aa; border-radius: 12px; padding: 6px 12px; font-size: 11.5px;">
          <span style="color: #9a3412; font-weight: 600;">Avg Temp: </span>
          <strong id="w-avg-temp" style="color: #ea580c; font-family: var(--font-mono);">--</strong>
        </div>
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 6px 12px; font-size: 11.5px;">
          <span style="color: #065f46; font-weight: 600;">Avg Hum: </span>
          <strong id="w-avg-hum" style="color: #00bfa5; font-family: var(--font-mono);">--</strong>
        </div>
        <div style="background: #e0e7ff; border: 1px solid #c7d2fe; border-radius: 12px; padding: 6px 12px; font-size: 11.5px;">
          <span style="color: #3730a3; font-weight: 600;">Avg Wind: </span>
          <strong id="w-avg-wind" style="color: #4f46e5; font-family: var(--font-mono);">--</strong>
        </div>
        <div style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 12px; padding: 6px 12px; font-size: 11.5px;">
          <span style="color: #6b21a8; font-weight: 600;">Max Wind Gust: </span>
          <strong id="w-max-gust" style="color: #8b5cf6; font-family: var(--font-mono);">--</strong>
        </div>
        <div style="background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 12px; padding: 6px 12px; font-size: 11.5px;">
          <span style="color: #115e59; font-weight: 600;">Avg Wind Dir: </span>
          <strong id="w-mode-dir" style="color: #0d9488; font-family: var(--font-mono);">--</strong>
        </div>
        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 6px 12px; font-size: 11.5px;">
          <span style="color: #1e40af; font-weight: 600;">Total Rain: </span>
          <strong id="w-total-rain" style="color: #2563eb; font-family: var(--font-mono);">--</strong>
        </div>
      </div>
      
    </div>
  </div>

  <!-- CHART SECTION -->
  <div class="bg-white rounded-[20px] sm:rounded-[24px] p-4 sm:p-6 border border-slate-200 shadow-[0_4px_20px_rgba(15,23,42,0.05)]">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
      <div>
        <h2 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 6px;">
          <i data-lucide="layers" style="width: 16px; height: 16px; color: #00bfa5;" id="chart-title-icon"></i>
          <span id="chart-main-title">Air Quality Hourly Trend Analysis (12:00 AM – 12:00 AM)</span>
        </h2>
        <p style="font-size: 12px; color: #64748b; margin-top: 2px; margin-bottom: 0;">
          <span id="chart-sub-title">24 Hourly data points progression</span>
        </p>
      </div>

      <div class="flex items-center gap-2 sm:gap-2.5 flex-wrap w-full lg:w-auto">
        <!-- Chart Type Toggle -->
        <div style="display: inline-flex; background: #f1f5f9; padding: 3px; border-radius: 999px; border: 1px solid #e2e8f0;">
          <button onclick="setChartType('line')" id="btn-type-line" style="display: flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 999px; border: none; font-size: 11.5px; font-weight: 600; cursor: pointer; <?= $req_type === 'line' ? 'background-color: #ffffff; color: #0f172a; box-shadow: 0 1px 4px rgba(0,0,0,0.06);' : 'background-color: transparent; color: #64748b; box-shadow: none;' ?> transition: all 0.15s ease; min-height: 36px;">
            <i data-lucide="trending-up" style="width: 13px; height: 13px; color: <?= $req_type === 'line' ? '#00bfa5' : '#64748b' ?>;" id="icon-type-line"></i>
            <span>Curve</span>
          </button>
          <button onclick="setChartType('bar')" id="btn-type-bar" style="display: flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 999px; border: none; font-size: 11.5px; font-weight: 600; cursor: pointer; <?= $req_type === 'bar' ? 'background-color: #ffffff; color: #0f172a; box-shadow: 0 1px 4px rgba(0,0,0,0.06);' : 'background-color: transparent; color: #64748b; box-shadow: none;' ?> transition: all 0.15s ease; min-height: 36px;">
            <i data-lucide="bar-chart-2" style="width: 13px; height: 13px; color: <?= $req_type === 'bar' ? '#00bfa5' : '#64748b' ?>;" id="icon-type-bar"></i>
            <span>Bars</span>
          </button>
        </div>

        <!-- Parameter Filter Chips (AQI) - horizontally scrollable on mobile -->
        <div id="chips-aqi" class="<?= $req_subtab === 'weather' ? 'hidden' : 'flex' ?> gap-1.5 overflow-x-auto no-scrollbar py-1 max-w-full flex-nowrap bg-slate-50 p-1 rounded-full border border-slate-200">
          <button onclick="setParam('cpcb_aqi')" id="btn-cpcb_aqi" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer <?= $req_param === 'cpcb_aqi' ? 'bg-[#00bfa5] text-white' : 'bg-transparent text-slate-500 hover:text-slate-800' ?> transition-all">AQI</button>
          <button onclick="setParam('pm25')" id="btn-pm25" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer <?= $req_param === 'pm25' ? 'bg-[#00bfa5] text-white' : 'bg-transparent text-slate-500 hover:text-slate-800' ?> transition-all">PM2.5</button>
          <button onclick="setParam('pm10')" id="btn-pm10" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer <?= $req_param === 'pm10' ? 'bg-[#00bfa5] text-white' : 'bg-transparent text-slate-500 hover:text-slate-800' ?> transition-all">PM10</button>
          <button onclick="setParam('co')" id="btn-co" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer <?= $req_param === 'co' ? 'bg-[#00bfa5] text-white' : 'bg-transparent text-slate-500 hover:text-slate-800' ?> transition-all">CO</button>
          <button onclick="setParam('no2')" id="btn-no2" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer <?= $req_param === 'no2' ? 'bg-[#00bfa5] text-white' : 'bg-transparent text-slate-500 hover:text-slate-800' ?> transition-all">NO₂</button>
          <button onclick="setParam('o3')" id="btn-o3" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer <?= $req_param === 'o3' ? 'bg-[#00bfa5] text-white' : 'bg-transparent text-slate-500 hover:text-slate-800' ?> transition-all">O₃</button>
        </div>

        <!-- Parameter Filter Chips (Weather) - horizontally scrollable on mobile -->
        <div id="chips-weather" class="<?= $req_subtab === 'weather' ? 'flex' : 'hidden' ?> gap-1.5 overflow-x-auto no-scrollbar py-1 max-w-full flex-nowrap bg-slate-50 p-1 rounded-full border border-slate-200">
          <button onclick="setParam('temperature')" id="btn-temperature" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer <?= $req_param === 'temperature' ? 'bg-[#00bfa5] text-white' : 'bg-transparent text-slate-500 hover:text-slate-800' ?> transition-all">Temp</button>
          <button onclick="setParam('humidity')" id="btn-humidity" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer <?= $req_param === 'humidity' ? 'bg-[#00bfa5] text-white' : 'bg-transparent text-slate-500 hover:text-slate-800' ?> transition-all">Hum.</button>
          <button onclick="setParam('wind_speed')" id="btn-wind_speed" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer <?= $req_param === 'wind_speed' ? 'bg-[#00bfa5] text-white' : 'bg-transparent text-slate-500 hover:text-slate-800' ?> transition-all">Wind Spd</button>
          <button onclick="setParam('wind_gust')" id="btn-wind_gust" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer <?= $req_param === 'wind_gust' ? 'bg-[#00bfa5] text-white' : 'bg-transparent text-slate-500 hover:text-slate-800' ?> transition-all">Wind Gust</button>
          <button onclick="setParam('rain_gauge')" id="btn-rain_gauge" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer <?= $req_param === 'rain_gauge' ? 'bg-[#00bfa5] text-white' : 'bg-transparent text-slate-500 hover:text-slate-800' ?> transition-all">Rain</button>
        </div>
      </div>
    </div>

    <!-- Chart Canvas Container -->
    <div style="width: 100%; height: 300px; position: relative;">
      <canvas id="historical-chart" style="transition: opacity 0.2s ease-in-out;"></canvas>
      <!-- Chart Loading Indicator (Clean spinner, never flashes false empty) -->
      <div id="chart-loading" style="display: none; position: absolute; inset: 0; flex-direction: column; align-items: center; justify-content: center; background: rgba(255,255,255,0.85); gap: 10px; color: #00bfa5; z-index: 4;">
        <div style="width: 28px; height: 28px; border: 3px solid #00bfa5; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
        <span style="font-size: 12px; font-weight: 600; color: #64748b;">Loading telemetry data...</span>
      </div>
      <div id="chart-empty" style="display: none; position: absolute; inset: 0; flex-direction: column; align-items: center; justify-content: center; background: rgba(255,255,255,0.85); gap: 8px; color: #64748b; z-index: 5;">
        <i data-lucide="info" style="width: 24px; height: 24px; color: #94a3b8;"></i>
        <p style="margin: 0; font-size: 13.5px; font-weight: 600;" id="chart-empty-text">No records found.</p>
      </div>
    </div>
  </div>

  <!-- TABLE SECTION -->
  <div class="bg-white rounded-[20px] sm:rounded-[24px] border border-slate-200 overflow-hidden shadow-[0_4px_20px_rgba(15,23,42,0.05)]">
    <div class="p-4 sm:p-5 flex items-center justify-between flex-wrap gap-3 border-b border-slate-100">
      <div>
        <h2 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="table" style="width: 18px; height: 18px; color: #00bfa5;"></i>
          <span id="table-title">AQI Historical Telemetry Table (12:00 AM – 12:00 AM)</span>
        </h2>
        <p style="font-size: 12px; color: #64748b; margin-top: 2px; margin-bottom: 0;">
          <span id="table-subtitle">24 Hourly Telemetry Rows for Date</span>
        </p>
      </div>
      <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <button type="button" onclick="openDatasetModal()" style="display: inline-flex; align-items: center; gap: 7px; padding: 7px 16px; border-radius: 999px; border: none; background: #00bfa5; color: #ffffff; font-size: 12.5px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 10px rgba(0, 191, 165, 0.28); transition: all 0.15s ease;">
          <i data-lucide="file-text" style="width: 14px; height: 14px;"></i>
          <span>Request Dataset</span>
        </button>
      </div>
    </div>

    <!-- Mobile swipe indicator -->
    <div class="block sm:hidden px-4 py-2 bg-slate-50 border-b border-slate-100 text-[11px] text-slate-500 font-medium flex items-center justify-between">
      <span>👈 Swipe horizontally to view all hourly readings</span>
      <span id="mobile-table-badge" class="font-mono text-[10px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded font-bold">24 HRS</span>
    </div>

    <div style="overflow-x: auto; max-height: 440px; -webkit-overflow-scrolling: touch;">
      <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; min-width: 750px;">
        <thead style="position: sticky; top: 0; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; z-index: 2;">
          <tr style="color: #475569; font-size: 12px;" id="table-head-row">
            <!-- Headers injected by JS -->
          </tr>
        </thead>
        <tbody id="table-body">
          <!-- Rows injected by JS -->
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Enterprise Data Access Request Modal -->
<div id="dataset-modal" style="display: none; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center; padding: 24px; background-color: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px); opacity: 0; transition: opacity 0.3s ease;">
  <div id="dataset-backdrop" onclick="closeDatasetModal()" style="position: absolute; inset: 0;"></div>
  
  <div id="dataset-modal-content" style="position: relative; z-index: 10; width: 100%; max-width: 520px; max-height: 90vh; background-color: #ffffff; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); transform: scale(0.95); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; overflow: hidden;">
    
    <!-- Dark Header -->
    <div style="background-color: #1e293b; padding: 24px; position: relative; flex-shrink: 0;">
      <button type="button" onclick="closeDatasetModal()" style="position: absolute; top: 16px; right: 16px; width: 32px; height: 32px; border-radius: 8px; border: none; background: rgba(255, 255, 255, 0.1); color: #94a3b8; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease;" onmouseenter="this.style.background='rgba(255, 255, 255, 0.2)'; this.style.color='#ffffff';" onmouseleave="this.style.background='rgba(255, 255, 255, 0.1)'; this.style.color='#94a3b8';">
        <i data-lucide="x" style="width: 16px; height: 16px;"></i>
      </button>
      
      <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
        <i data-lucide="shield-check" style="color: #00bfa5; width: 24px; height: 24px;"></i>
        <h2 style="font-size: 20px; font-weight: 700; color: #ffffff; margin: 0; font-family: var(--font-sans);">Enterprise Data Access Request</h2>
      </div>
      <div style="display: flex; align-items: center; gap: 6px; padding-left: 2px;">
        <i data-lucide="lock" style="color: #94a3b8; width: 14px; height: 14px;"></i>
        <span style="font-size: 13px; color: #cbd5e1; font-weight: 500;">Secure Authorization Portal</span>
      </div>
    </div>

    <!-- Body -->
    <div style="padding: 24px; overflow-y: auto; flex-grow: 1;">
      <form id="dataset-form" onsubmit="submitDatasetRequest(event)" style="display: flex; flex-direction: column; gap: 20px;">
        
        <!-- Scope Selection -->
        <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
          <label style="display: block; font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px;">Requested Dataset Scope</label>
          <div id="modal-dataset-scope" style="font-size: 14px; color: #64748b; font-weight: 400;">
            AQI Telemetry — Data provided is strictly limited to 24 hours of the previous day.
            <div style="margin-top: 8px; padding-top: 6px; border-top: 1px dashed #e2e8f0; font-size: 12.5px; color: #475569; font-weight: 500; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
              <span>For more dataset</span>
              <span style="color: #94a3b8;">→</span>
              <button type="button" onclick="closeDatasetModal(); document.getElementById('contact-modal').style.display='flex'; if (window.lucide) { lucide.createIcons(); }" style="background: none; border: none; font-size: 12.5px; font-weight: 700; color: #00bfa5; cursor: pointer; text-decoration: underline; text-decoration-color: rgba(0,191,165,0.4); text-underline-offset: 3px; display: inline-flex; align-items: center; gap: 2px; padding: 0; transition: all 0.2s;" onmouseover="this.style.color='#0d9488';" onmouseout="this.style.color='#00bfa5';">
                Contact Us
              </button>
            </div>
          </div>
        </div>

        <!-- Full Name -->
        <div>
          <label style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 8px;">Full Name *</label>
          <div style="position: relative;">
            <i data-lucide="user" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 18px; height: 18px;"></i>
            <input type="text" name="fullName" required placeholder="Enter your full name" value="<?= (!empty($_SESSION['user_name']) && $_SESSION['user_name'] !== 'Manish B') ? htmlspecialchars($_SESSION['user_name']) : '' ?>" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px 12px 42px; font-size: 14px; font-weight: 500; color: #0f172a; outline: none; transition: all 0.2s; background-color: #f8fafc;" onfocus="this.style.borderColor='#00bfa5'; this.style.backgroundColor='#ffffff'; this.style.boxShadow='0 0 0 3px rgba(0, 191, 165, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.backgroundColor='#f8fafc'; this.style.boxShadow='none';">
          </div>
        </div>

        <!-- Email -->
        <div>
          <label style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 8px;">Email *</label>
          <div style="position: relative;">
            <i data-lucide="mail" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 18px; height: 18px;"></i>
            <input type="email" id="dataset-email-input" name="email" required placeholder="Enter your email" value="<?= (!empty($_SESSION['user_email']) && $_SESSION['user_email'] !== 'manishb@sode-edu.in' && $_SESSION['user_email'] !== 'manish@sode-edu.in') ? htmlspecialchars($_SESSION['user_email']) : '' ?>" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px 12px 42px; font-size: 14px; font-weight: 500; color: #0f172a; outline: none; transition: all 0.2s; background-color: #f8fafc;" onfocus="this.style.borderColor='#00bfa5'; this.style.backgroundColor='#ffffff'; this.style.boxShadow='0 0 0 3px rgba(0, 191, 165, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.backgroundColor='#f8fafc'; this.style.boxShadow='none';">
          </div>
        </div>

        <!-- Organization -->
        <div>
          <label style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 8px;">Organization / Institution *</label>
          <div style="position: relative;">
            <i data-lucide="building" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 18px; height: 18px;"></i>
            <input type="text" name="org" required placeholder="University / Company / Agency" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px 12px 42px; font-size: 14px; font-weight: 500; color: #0f172a; outline: none; transition: all 0.2s; background-color: #f8fafc;" onfocus="this.style.borderColor='#00bfa5'; this.style.backgroundColor='#ffffff'; this.style.boxShadow='0 0 0 3px rgba(0, 191, 165, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.backgroundColor='#f8fafc'; this.style.boxShadow='none';">
          </div>
        </div>

        <!-- Industry -->
        <div>
          <label style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 8px;">Industry *</label>
          <select name="industry" id="industry-select" onchange="handleIndustryProofRequirement(this.value)" required style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; font-size: 14px; font-weight: 500; color: #0f172a; outline: none; transition: all 0.2s; background-color: #f8fafc; cursor: pointer; appearance: none;" onfocus="this.style.borderColor='#00bfa5'; this.style.backgroundColor='#ffffff'; this.style.boxShadow='0 0 0 3px rgba(0, 191, 165, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.backgroundColor='#f8fafc'; this.style.boxShadow='none';">
            <option value="" disabled selected hidden>Select Industry</option>
            <option value="Student">Student</option>
            <option value="Academic / Research">Academic / Research</option>
            <option value="Government / Public Sector">Government / Public Sector</option>
            <option value="Environmental Agency">Environmental Agency</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <!-- Mandatory Official Identity Proof Upload -->
        <div id="industry-proof-section" style="display: none;">
          <label id="proof-file-label" style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 8px;">
            Upload Official ID Card / Proof Document *
          </label>
          
          <input type="file" id="proof-file-input" accept=".pdf,.png,.jpg,.jpeg,.webp" style="display: none;" onchange="handleProofFileSelected(this)">
          
          <div id="proof-dropzone" onclick="document.getElementById('proof-file-input').click()" style="border: 2px dashed #e2e8f0; border-radius: 8px; background-color: #f8fafc; padding: 20px 16px; text-align: center; cursor: pointer; transition: all 0.2s;" onmouseenter="this.style.borderColor='#00bfa5'; this.style.backgroundColor='#ffffff';" onmouseleave="if(!window.__selectedProofFile) { this.style.borderColor='#e2e8f0'; this.style.backgroundColor='#f8fafc'; }">
            <div id="proof-dropzone-content" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px;">
              <div style="width: 40px; height: 40px; border-radius: 50%; background-color: #e6fffa; color: #00bfa5; display: flex; align-items: center; justify-content: center;">
                <i data-lucide="upload-cloud" style="width: 22px; height: 22px;"></i>
              </div>
              <div style="font-size: 13.5px; font-weight: 700; color: #0f172a;">
                Click to upload official ID Card / Proof Document
              </div>
              <div id="proof-file-hint" style="font-size: 12px; color: #64748b;">
                Supports PDF, PNG, JPG, WEBP (Max 5MB)
              </div>
            </div>
          </div>

          <!-- Uploaded File Badge / Preview -->
          <div id="proof-file-preview" style="display: none; background-color: #ffffff; border: 1px solid #00bfa5; border-radius: 8px; padding: 12px 14px; align-items: center; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
              <div style="width: 34px; height: 34px; border-radius: 8px; background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #16a34a; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i data-lucide="file-check" style="width: 20px; height: 20px;"></i>
              </div>
              <div style="min-width: 0;">
                <div id="proof-filename" style="font-size: 13px; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 260px;"></div>
                <div style="display: flex; align-items: center; gap: 8px; margin-top: 2px;">
                  <span id="proof-filesize" style="font-size: 11.5px; color: #64748b; font-family: var(--font-mono);"></span>
                  <span style="font-size: 10.5px; font-weight: 700; color: #16a34a; background: #dcfce7; padding: 1px 6px; border-radius: 4px;">Ready</span>
                </div>
              </div>
            </div>
            <button type="button" onclick="removeSelectedProofFile(event)" style="background: #fef2f2; border: 1px solid #fee2e2; color: #ef4444; cursor: pointer; padding: 6px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.15s;" onmouseenter="this.style.backgroundColor='#fee2e2';" onmouseleave="this.style.backgroundColor='#fef2f2';" title="Remove document and select another">
              <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
            </button>
          </div>
        </div>

        <!-- Intended Use Case -->
        <div>
          <div style="margin-bottom: 8px;">
            <label style="font-size: 13px; font-weight: 700; color: #334155;">Intended Use Case *</label>
          </div>
          <textarea name="use_case" id="use-case-textarea" required placeholder="Describe your operational or analytical requirements for this telemetry data..." style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; font-size: 14px; font-weight: 500; color: #0f172a; outline: none; transition: all 0.2s; background-color: #f8fafc; resize: none; min-height: 85px; font-family: var(--font-sans);" onfocus="this.style.borderColor='#00bfa5'; this.style.backgroundColor='#ffffff'; this.style.boxShadow='0 0 0 3px rgba(0, 191, 165, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.backgroundColor='#f8fafc'; this.style.boxShadow='none';"></textarea>
        </div>

        <!-- Agreement -->
        <div style="background-color: #f8fafc; border-radius: 8px; border-left: 4px solid #00bfa5; padding: 16px; margin-top: 4px;">
          <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
            <input type="checkbox" required style="width: 16px; height: 16px; margin-top: 3px; accent-color: #00bfa5; cursor: pointer;">
            <span style="font-size: 13px; color: #64748b; line-height: 1.5;">
              <strong style="color: #1e293b; font-weight: 700;">Data Governance Agreement:</strong> I acknowledge that this dataset is proprietary. I agree to abide by the Data Privacy Framework and confirm that this data will not be redistributed, monetized, or utilized for competitive benchmarking without explicit written consent.
            </span>
          </label>
        </div>

        <!-- Actions -->
        <div style="display: flex; gap: 12px; margin-top: 8px;">
          <button type="submit" id="dataset-submit-btn" style="flex: 1; padding: 14px; border-radius: 8px; border: none; background: #00bfa5; color: white; font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(0, 191, 165, 0.2);" onmouseenter="this.style.background='#00d6b9'; this.style.boxShadow='0 6px 8px -1px rgba(0, 191, 165, 0.3)';" onmouseleave="this.style.background='#00bfa5'; this.style.boxShadow='0 4px 6px -1px rgba(0, 191, 165, 0.2)';">
            Submit Request securely
          </button>
          <button type="button" onclick="closeDatasetModal()" style="flex: 1; padding: 14px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s;" onmouseenter="this.style.background='#f8fafc'; this.style.color='#1e293b';" onmouseleave="this.style.background='white'; this.style.color='#64748b';">
            Cancel
          </button>
        </div>
      </form>

      <!-- Success State -->
      <div id="dataset-success" style="display: none; flex-direction: column; align-items: center; text-align: center; padding: 20px 0;">
        <div style="width: 64px; height: 64px; border-radius: 50%; background: #dcfce7; display: flex; align-items: center; justify-content: center; margin-bottom: 24px;">
          <i data-lucide="check" style="color: #16a34a; width: 32px; height: 32px;"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 12px;">Request Submitted</h3>
        <p style="font-size: 15px; color: #64748b; line-height: 1.5; margin: 0 0 32px; max-width: 400px;">
          Your enterprise data request has been logged. An administrator will review your submission shortly. Once approved, you will receive an encrypted payload via email.
        </p>
        <button type="button" onclick="closeDatasetModal()" style="padding: 10px 24px; border-radius: 8px; border: none; background: #f1f5f9; color: #475569; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s;" onmouseenter="this.style.background='#e2e8f0'; this.style.color='#0f172a';" onmouseleave="this.style.background='#f1f5f9'; this.style.color='#475569';">
          Close Window
        </button>
      </div>

    </div>
  </div>
</div>



<script>
function getHistoricalInitialState() {
    let tab = '<?= $req_subtab ?>';
    let param = tab === 'aqi' ? 'cpcb_aqi' : 'temperature';
    let days = <?= $req_range_days ?>;
    let type = 'bar';
    let date = '';
    try {
        const urlParams = new URLSearchParams(window.location.search);
        let saved = null;
        try {
            const raw = sessionStorage.getItem('historical_view_state');
            if (raw) saved = JSON.parse(raw);
        } catch(e) {}

        const candidateTab = urlParams.get('tab') || saved?.tab;
        if (candidateTab && ['aqi', 'weather'].includes(candidateTab)) tab = candidateTab;

        const candidateDays = Number(urlParams.get('days') || saved?.days);
        if ([1, 3, 7, 30].includes(candidateDays)) days = candidateDays;

        const candidateParam = urlParams.get('param') || saved?.param;
        if (candidateParam) param = candidateParam;

        const candidateType = urlParams.get('type') || saved?.type;
        if (candidateType && ['bar', 'line'].includes(candidateType)) type = candidateType;

        date = urlParams.get('date') || saved?.date || '<?= $today_ist ?>';
    } catch(e) {}
    return { tab, param, days, type, date };
}

window.INITIAL_HISTORY = <?= $init_history_json ?>;
const _initState = getHistoricalInitialState();
var currentSubTab = _initState.tab;
var currentParam = _initState.param;
var currentChartType = _initState.type;
var currentRangeDays = _initState.days;
var historicalDataAqi = [];
var historicalDataWeather = [];
var chartInstance = null;
var filteredDayData = [];

function persistHistoricalState() {
    try {
        const dp = document.getElementById('history-date-picker');
        const state = {
            tab: currentSubTab || 'aqi',
            param: currentParam || 'cpcb_aqi',
            days: currentRangeDays || 1,
            type: currentChartType || 'bar',
            date: dp ? dp.value : ''
        };
        sessionStorage.setItem('historical_view_state', JSON.stringify(state));
        const url = new URL(window.location.href);
        url.searchParams.set('tab', state.tab);
        url.searchParams.set('param', state.param);
        url.searchParams.set('days', state.days);
        url.searchParams.set('type', state.type);
        if (state.date) {
            url.searchParams.set('date', state.date);
        } else {
            url.searchParams.delete('date');
        }
        history.replaceState(null, '', url.toString());
    } catch(e) {}
}

// High-performance in-memory cache for instant 0ms range switching
const clientRangeCache = window.__historicalCache = window.__historicalCache || {};
const pendingRangeFetches = window.__pendingHistFetches = window.__pendingHistFetches || {};
let summaryCollapseTimer = null;

function getCacheKey(date, days) {
    return `${date}_${days}`;
}

function animateSummarySection(show) {
    const summarySection = document.getElementById('summary-section');
    if (!summarySection) return;
    if (summaryCollapseTimer) {
        clearTimeout(summaryCollapseTimer);
        summaryCollapseTimer = null;
    }
    if (show) {
        summarySection.style.display = 'flex';
        requestAnimationFrame(() => {
            summarySection.style.maxHeight = '380px';
            summarySection.style.opacity = '1';
            summarySection.style.marginTop = '18px';
            summarySection.style.paddingTop = '16px';
            summarySection.style.borderTopWidth = '1px';
            summarySection.style.pointerEvents = 'auto';
        });
    } else {
        summarySection.style.maxHeight = '0px';
        summarySection.style.opacity = '0';
        summarySection.style.marginTop = '0px';
        summarySection.style.paddingTop = '0px';
        summarySection.style.borderTopWidth = '0px';
        summarySection.style.pointerEvents = 'none';
        summaryCollapseTimer = setTimeout(() => {
            if (currentRangeDays > 1 && summarySection) {
                summarySection.style.display = 'none';
            }
        }, 320);
    }
}

function setRangeDays(days, skipFetch = false) {
    currentRangeDays = Number(days) || 1;
    
    // Sync Range Dropdown
    const rangeSelect = document.getElementById('range-select');
    if (rangeSelect && Number(rangeSelect.value) !== currentRangeDays) {
        rangeSelect.value = currentRangeDays;
    }

    // Tactile & smooth pill transitions (if present)
    [1, 3, 7, 30].forEach(d => {
        const btn = document.getElementById(`btn-range-${d}`);
        if (btn) {
            if (d === currentRangeDays) {
                btn.className = 'px-3 sm:px-3.5 py-1.5 rounded-full font-bold text-xs cursor-pointer transition-all duration-200 ease-out bg-[#00bfa5] text-white shadow-[0_2px_8px_rgba(0,191,165,0.35)] scale-[1.02] min-h-[34px]';
            } else {
                btn.className = 'px-3 sm:px-3.5 py-1.5 rounded-full font-semibold text-xs cursor-pointer transition-all duration-200 ease-out bg-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-200/50 active:scale-95 min-h-[34px]';
            }
        }
    });

    const headerTitle = document.getElementById('range-header-title');
    if (headerTitle) {
        if (currentRangeDays === 1) {
            headerTitle.innerText = 'Select Date (24-Hour Telemetry)';
        } else {
            headerTitle.innerText = `Telemetry Trend (${currentRangeDays}-Day Historical)`;
        }
    }
    
    const mobileBadge = document.getElementById('mobile-table-badge');
    if (mobileBadge) {
        mobileBadge.innerText = currentRangeDays === 1 ? '24 HRS' : `${currentRangeDays} DAYS`;
    }

    animateSummarySection(currentRangeDays === 1);

    persistHistoricalState();
    if (!skipFetch) fetchAllHistory();
}

var PARAM_COLORS = {
    cpcb_aqi: '#00bfa5', pm25: '#0284c7', pm10: '#6366f1',
    co: '#16a34a', no2: '#9333ea', o3: '#d97706',
    temperature: '#00bfa5', humidity: '#00bfa5',
    wind_speed: '#4f46e5', wind_gust: '#8b5cf6',
    wind_direction: '#0284c7', rain_gauge: '#0891b2'
};

var getAqiCategory = function(val) {
    const v = Number(val);
    if (v <= 50) return { label: 'Good', color: '#59b61f', bg: '#f0fdf4', border: 'rgba(89, 182, 31, 0.4)', text: '#15803d', badgeBg: '#59b61f', badgeText: '#ffffff' };
    if (v <= 100) return { label: 'Moderate', color: '#eec732', bg: '#fefce8', border: 'rgba(238, 199, 50, 0.45)', text: '#854d0e', badgeBg: '#eec732', badgeText: '#ffffff' };
    if (v <= 200) return { label: 'Poor', color: '#ea8c34', bg: '#fff7ed', border: 'rgba(234, 140, 52, 0.45)', text: '#9a3412', badgeBg: '#ea8c34', badgeText: '#ffffff' };
    if (v <= 300) return { label: 'Unhealthy', color: '#e95478', bg: '#fff1f2', border: 'rgba(233, 84, 120, 0.45)', text: '#991b1b', badgeBg: '#e95478', badgeText: '#ffffff' };
    if (v <= 400) return { label: 'Severe', color: '#b33fba', bg: '#faf5ff', border: 'rgba(179, 63, 186, 0.45)', text: '#6b21a8', badgeBg: '#b33fba', badgeText: '#ffffff' };
    return { label: 'Hazardous', color: '#c92033', bg: '#fef2f2', border: 'rgba(201, 32, 51, 0.45)', text: '#9f1239', badgeBg: '#c92033', badgeText: '#ffffff' };
};

// 6-Tier scales matching the Live page bar standards for each air pollutant
var POLLUTANT_SCALES = {
    cpcb_aqi: [50, 100, 200, 300, 400],
    aqi:      [50, 100, 200, 300, 400],
    pm25:     [30, 60, 90, 120, 250],
    pm10:     [50, 100, 250, 350, 430],
    co:       [1, 2, 10, 17, 34],
    no2:      [40, 80, 180, 280, 400],
    o3:       [50, 100, 168, 208, 748]
};

var TIER_COLORS = ['#22c55e', '#eab308', '#f97316', '#ef4444', '#a855f7', '#b91c1c'];
var TIER_LABELS = ['Good', 'Moderate', 'Poor', 'Unhealthy', 'Severe', 'Hazardous'];

var PARAM_UNITS = {
    cpcb_aqi: '', aqi: '', pm25: 'µg/m³', pm10: 'µg/m³',
    co: 'mg/m³', no2: 'µg/m³', o3: 'µg/m³',
    temperature: '°C', humidity: '%',
    wind_speed: 'km/h', wind_gust: 'km/h', wind_direction: '°', rain_gauge: 'mm'
};

function getParamTierInfo(param, val) {
    const v = Number(val);
    if (isNaN(v) || val == null) return { label: 'Unknown', color: '#94a3b8', tier: -1 };
    const s = POLLUTANT_SCALES[param];
    if (!s) {
        return { label: '', color: PARAM_COLORS[param] || '#00bfa5', tier: -1 };
    }
    let tier = 0;
    if (v <= s[0]) tier = 0;
    else if (v <= s[1]) tier = 1;
    else if (v <= s[2]) tier = 2;
    else if (v <= s[3]) tier = 3;
    else if (v <= s[4]) tier = 4;
    else tier = 5;

    return {
        label: TIER_LABELS[tier],
        color: TIER_COLORS[tier],
        tier: tier
    };
}

function getParamColor(param, val) {
    return getParamTierInfo(param, val).color;
}

function getParamDisplayName(param) {
    if (param === 'cpcb_aqi' || param === 'aqi') return 'AQI';
    if (param === 'pm25') return 'PM2.5';
    if (param === 'pm10') return 'PM10';
    if (param === 'co') return 'CO';
    if (param === 'no2') return 'NO₂';
    if (param === 'o3') return 'O₃';
    if (param === 'temperature') return 'Temperature';
    if (param === 'humidity') return 'Humidity';
    if (param === 'wind_speed') return 'Wind Speed';
    if (param === 'wind_gust') return 'Wind Gust';
    if (param === 'rain_gauge') return 'Rainfall';
    return param ? param.toUpperCase() : '';
}

var getCompassDir = function(val) {
    if (val == null) return '--';
    const deg = Math.round(Number(val) % 360);
    if (isNaN(deg)) return val;
    const dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
    return dirs[Math.round(deg / 22.5) % 16];
};

var getLocalDateStr = function(d = new Date()) {
    const yr = d.getFullYear();
    const mo = String(d.getMonth() + 1).padStart(2, '0');
    const da = String(d.getDate()).padStart(2, '0');
    return `${yr}-${mo}-${da}`;
};

async function fetchRangeData(queryDate, days) {
    const key = getCacheKey(queryDate, days);
    if (clientRangeCache[key]) {
        return clientRangeCache[key];
    }
    if (pendingRangeFetches[key]) {
        return pendingRangeFetches[key];
    }
    const fetchPromise = (async () => {
        try {
            const response = await fetch(`api/telemetry.php?action=history_bulk&date=${queryDate}&days=${days}`);
            const data = await response.json();
            const payload = {
                aqi: data.aqi || [],
                weather: data.weather || [],
                fetchedAt: Date.now()
            };
            clientRangeCache[key] = payload;
            try {
                if (typeof window.setLocalCache === 'function') {
                    window.setLocalCache('smartairnet_hist_' + key, payload);
                }
            } catch(e) {}
            return payload;
        } catch(e) {
            console.error(`Failed to fetch history for ${key}:`, e);
            return { aqi: [], weather: [], fetchedAt: Date.now() };
        } finally {
            delete pendingRangeFetches[key];
        }
    })();
    pendingRangeFetches[key] = fetchPromise;
    return fetchPromise;
}

function prefetchOtherRanges(queryDate) {
    if (!queryDate) return;
    // Only prefetch 3-day range after 2.5 seconds of user idle time, avoiding connection hogging
    const key3 = getCacheKey(queryDate, 3);
    if (currentRangeDays === 1 && !clientRangeCache[key3] && !pendingRangeFetches[key3]) {
        setTimeout(() => {
            if (currentRangeDays === 1 && !clientRangeCache[key3] && !pendingRangeFetches[key3]) {
                fetchRangeData(queryDate, 3).catch(() => {});
            }
        }, 2500);
    }
}

async function fetchAllHistory(forcedDate = null, skipCache = false) {
    try {
        const datePicker = document.getElementById('history-date-picker');
        let queryDate = forcedDate || (datePicker ? datePicker.value : null);
        
        // Default date if none selected (use local date YYYY-MM-DD)
        if (!queryDate) {
            queryDate = getLocalDateStr();
            if (datePicker) {
                datePicker.value = queryDate;
                datePicker.max = queryDate;
            }
        } else if (datePicker && !datePicker.max) {
            datePicker.max = getLocalDateStr();
        }

        const days = currentRangeDays || 1;
        const key = getCacheKey(queryDate, days);

        // Instant 0ms pre-hydration from initial server payload
        if (!skipCache && window.INITIAL_HISTORY && queryDate === '<?= $today_ist ?>' && days === 1) {
            clientRangeCache[key] = window.INITIAL_HISTORY;
            historicalDataAqi = window.INITIAL_HISTORY.aqi || [];
            historicalDataWeather = window.INITIAL_HISTORY.weather || [];
            window.INITIAL_HISTORY = null;
            try {
                if (typeof window.setLocalCache === 'function') {
                    window.setLocalCache('smartairnet_hist_' + key, clientRangeCache[key]);
                }
            } catch(e) {}
            renderUI();
            prefetchOtherRanges(queryDate);
            return;
        }

        // Instant render from client memory or localStorage cache (0ms latency!)
        let cached = (!skipCache && clientRangeCache[key]) ? clientRangeCache[key] : null;
        if (!cached && !skipCache && typeof window.getLocalCache === 'function') {
            const stored = window.getLocalCache('smartairnet_hist_' + key);
            if (stored && (stored.aqi || stored.weather)) {
                cached = stored;
                clientRangeCache[key] = stored;
            }
        }

        if (cached) {
            historicalDataAqi = cached.aqi || [];
            historicalDataWeather = cached.weather || [];
            renderUI();
            prefetchOtherRanges(queryDate);

            // Silently revalidate in background if records are for today or older than 45s
            const isToday = (queryDate === getLocalDateStr());
            const ageMs = Date.now() - (cached.fetchedAt || 0);
            if (isToday || ageMs > 45000) {
                fetchRangeData(queryDate, days).then(freshData => {
                    if (freshData && (freshData.aqi?.length || freshData.weather?.length)) {
                        historicalDataAqi = freshData.aqi || [];
                        historicalDataWeather = freshData.weather || [];
                        renderUI();
                    }
                }).catch(() => {});
            }
            return;
        }

        const chartCanvas = document.getElementById('historical-chart');
        const chartLoading = document.getElementById('chart-loading');
        const chartEmpty = document.getElementById('chart-empty');
        if (chartCanvas) chartCanvas.style.opacity = '0.7';
        if (chartLoading) chartLoading.style.display = 'flex';
        if (chartEmpty) chartEmpty.style.display = 'none';

        const data = await fetchRangeData(queryDate, days);
        
        historicalDataAqi = data.aqi || [];
        historicalDataWeather = data.weather || [];
        
        if (chartLoading) chartLoading.style.display = 'none';
        if (chartCanvas) chartCanvas.style.opacity = '1';

        renderUI();
        prefetchOtherRanges(queryDate);
    } catch(e) {
        console.error("Error fetching historical data", e);
        const chartCanvas = document.getElementById('historical-chart');
        const chartLoading = document.getElementById('chart-loading');
        if (chartLoading) chartLoading.style.display = 'none';
        if (chartCanvas) chartCanvas.style.opacity = '1';
    }
}

function switchHistTab(tab, skipRender = false) {
    currentSubTab = tab;
    if (tab === 'aqi') {
        if (!['cpcb_aqi', 'pm25', 'pm10', 'co', 'no2', 'o3'].includes(currentParam)) {
            currentParam = 'cpcb_aqi';
        }
    } else {
        if (!['temperature', 'humidity', 'wind_speed', 'wind_gust', 'rain_gauge'].includes(currentParam)) {
            currentParam = 'temperature';
        }
    }
    
    // Switch styles
    const tabAqi = document.getElementById('tab-aqi');
    const tabWeather = document.getElementById('tab-weather');
    const days = currentRangeDays || 1;
    if(tab === 'aqi') {
        tabAqi.style.backgroundColor = '#00bfa5';
        tabAqi.style.color = '#ffffff';
        tabWeather.style.backgroundColor = 'transparent';
        tabWeather.style.color = '#64748b';
        
        document.getElementById('chips-aqi').style.display = 'flex';
        document.getElementById('chips-weather').style.display = 'none';
        
        const aqiBlock = document.getElementById('summary-aqi-block');
        const weatherBlock = document.getElementById('summary-weather-block');
        if (aqiBlock) aqiBlock.style.display = 'flex';
        if (weatherBlock) weatherBlock.style.display = 'none';

        document.getElementById('chart-main-title').innerText = days === 1 ? 'Air Quality Hourly Trend Analysis (12:00 AM – 12:00 AM)' : `Air Quality ${days}-Day Trend Analysis`;
        document.getElementById('table-title').innerText = days === 1 ? 'AQI Historical Telemetry Table (12:00 AM – 12:00 AM)' : `AQI Historical Telemetry Table (${days} Days)`;
    } else {
        tabWeather.style.backgroundColor = '#00bfa5';
        tabWeather.style.color = '#ffffff';
        tabAqi.style.backgroundColor = 'transparent';
        tabAqi.style.color = '#64748b';
        
        document.getElementById('chips-aqi').style.display = 'none';
        document.getElementById('chips-weather').style.display = 'flex';
        
        const aqiBlock = document.getElementById('summary-aqi-block');
        const weatherBlock = document.getElementById('summary-weather-block');
        if (aqiBlock) aqiBlock.style.display = 'none';
        if (weatherBlock) weatherBlock.style.display = 'flex';

        document.getElementById('chart-main-title').innerText = days === 1 ? 'Weather Hourly Trend Analysis (12:00 AM – 12:00 AM)' : `Weather ${days}-Day Trend Analysis`;
        document.getElementById('table-title').innerText = days === 1 ? 'Weather Historical Telemetry Table (12:00 AM – 12:00 AM)' : `Weather Historical Telemetry Table (${days} Days)`;
    }
    
    setParam(currentParam, skipRender);
    persistHistoricalState();
}

function setChartType(type, skipRender = false) {
    currentChartType = type;
    const btnLine = document.getElementById('btn-type-line');
    const iconLine = document.getElementById('icon-type-line');
    const btnBar = document.getElementById('btn-type-bar');
    const iconBar = document.getElementById('icon-type-bar');
    
    if(type === 'line') {
        btnLine.style.backgroundColor = '#ffffff';
        btnLine.style.color = '#0f172a';
        btnLine.style.boxShadow = '0 1px 4px rgba(0,0,0,0.06)';
        iconLine.style.color = '#00bfa5';
        
        btnBar.style.backgroundColor = 'transparent';
        btnBar.style.color = '#64748b';
        btnBar.style.boxShadow = 'none';
        iconBar.style.color = '#64748b';
    } else {
        btnBar.style.backgroundColor = '#ffffff';
        btnBar.style.color = '#0f172a';
        btnBar.style.boxShadow = '0 1px 4px rgba(0,0,0,0.06)';
        iconBar.style.color = '#00bfa5';
        
        btnLine.style.backgroundColor = 'transparent';
        btnLine.style.color = '#64748b';
        btnLine.style.boxShadow = 'none';
        iconLine.style.color = '#64748b';
    }
    persistHistoricalState();
    if (!skipRender) renderUI();
}

function setParam(param, skipRender = false) {
    currentParam = param;
    const allBtns = ['cpcb_aqi', 'pm25', 'pm10', 'co', 'no2', 'o3', 'temperature', 'humidity', 'wind_speed', 'wind_gust', 'rain_gauge'];
    allBtns.forEach(b => {
        const el = document.getElementById(`btn-${b}`);
        if(el) {
            if(b === param) {
                el.style.background = PARAM_COLORS[b];
                el.style.color = '#ffffff';
            } else {
                el.style.background = 'transparent';
                el.style.color = '#64748b';
            }
        }
    });
    const titleIcon = document.getElementById('chart-title-icon');
    if (titleIcon && PARAM_COLORS[param]) {
        titleIcon.style.color = PARAM_COLORS[param];
    }
    persistHistoricalState();
    if (!skipRender) renderUI();
}

function renderUI(skipDayFilter = false) {
    const datePicker = document.getElementById('history-date-picker');
    if (!datePicker) return;
    const dateInput = datePicker.value;
    if (!dateInput) return;

    const parseIsoLocal = (str) => {
        if (!str) return 0;
        const cleanStr = String(str).replace(/[Zz]|[+-]\d{2}(:\d{2})?$/, '');
        const parts = cleanStr.split('T');
        if (parts.length !== 2) return new Date(str).getTime();
        const [yr, mo, da] = parts[0].split('-').map(Number);
        const timeParts = parts[1].split(':');
        const hr = Number(timeParts[0]) || 0;
        const mn = Number(timeParts[1]) || 0;
        const sc = Number(timeParts[2]) || 0;
        return new Date(yr, mo - 1, da, hr, mn, sc).getTime();
    };

    let rangeLabel = '';
    const days = currentRangeDays || 1;
    if (!skipDayFilter) {
        const sourceData = currentSubTab === 'aqi' ? historicalDataAqi : historicalDataWeather;

        const [y, m, d] = dateInput.split('-').map(Number);
        let startWindow, endWindow;
        if (days === 1) {
            startWindow = new Date(y, m - 1, d, 0, 0, 0, 0).getTime();
            endWindow = startWindow + (24 * 60 * 60 * 1000);
            const startDateObj = new Date(y, m - 1, d);
            const dateStrLong = startDateObj.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
            rangeLabel = `${dateStrLong} (12:00 AM – 12:00 AM)`;
        } else {
            const startD = new Date(y, m - 1, d - (days - 1), 0, 0, 0, 0);
            const endD = new Date(y, m - 1, d, 23, 59, 59, 999);
            startWindow = startD.getTime();
            endWindow = endD.getTime();
            const startStr = startD.toLocaleDateString('en-US', { day: 'numeric', month: 'short' });
            const endStr = new Date(y, m - 1, d).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
            rangeLabel = `${startStr} – ${endStr}`;
        }
        
        // Filter data strictly within [startWindow, endWindow]
        filteredDayData = sourceData.filter(item => {
            if (!item.timestamp) return false;
            const t = parseIsoLocal(item.timestamp);
            return t >= startWindow && t <= endWindow;
        });

        // Deduplicate any points sharing the same hour
        const hourlyMap = new Map();
        filteredDayData.forEach(item => {
            const parts = (item.timestamp || '').split('T');
            let hrKey = '';
            if (parts.length === 2) {
                hrKey = parts[0] + ' ' + parts[1].slice(0, 2);
            } else {
                const dt = new Date(item.timestamp);
                hrKey = dt.getFullYear() + '-' + (dt.getMonth() + 1) + '-' + dt.getDate() + ' ' + dt.getHours();
            }
            hourlyMap.set(hrKey, item);
        });
        filteredDayData = Array.from(hourlyMap.values());
        filteredDayData.sort((a,b) => parseIsoLocal(a.timestamp) - parseIsoLocal(b.timestamp));
        
        const subtitleEl = document.getElementById('date-subtitle-desktop');
        if (subtitleEl) {
            subtitleEl.innerText = `Showing ${filteredDayData.length} records (${rangeLabel})`;
        }
        
        const isAqi = currentSubTab === 'aqi';
        const chartTitle = document.getElementById('chart-main-title');
        if (chartTitle) {
            chartTitle.innerText = days === 1
                ? (isAqi ? 'Air Quality Hourly Trend Analysis (12:00 AM – 12:00 AM)' : 'Weather Hourly Trend Analysis (12:00 AM – 12:00 AM)')
                : (isAqi ? `Air Quality ${days}-Day Trend Analysis (${rangeLabel})` : `Weather ${days}-Day Trend Analysis (${rangeLabel})`);
        }
        
        const chartSubTitle = document.getElementById('chart-sub-title');
        if (chartSubTitle) {
            chartSubTitle.innerText = `${days === 1 ? '24-Hour progression' : days + '-Day progression'}: ${rangeLabel}`;
        }
        
        const tableTitle = document.getElementById('table-title');
        if (tableTitle) {
            tableTitle.innerText = days === 1
                ? (isAqi ? 'AQI Historical Telemetry Table (12:00 AM – 12:00 AM)' : 'Weather Historical Telemetry Table (12:00 AM – 12:00 AM)')
                : (isAqi ? `AQI Historical Telemetry Table (${days} Days: ${rangeLabel})` : `Weather Historical Telemetry Table (${days} Days: ${rangeLabel})`);
        }
        
        const tableSubtitle = document.getElementById('table-subtitle');
        if (tableSubtitle) {
            tableSubtitle.innerText = `${days === 1 ? '24 Hourly Telemetry Rows' : days + '-Day Telemetry Rows'} (${rangeLabel}) — ${filteredDayData.length} Logged Readings`;
        }
    }
    
    const summarySection = document.getElementById('summary-section');
    if (summarySection) {
        animateSummarySection(days === 1);
    }
    
    if (filteredDayData.length === 0) {
        if (days === 1) {
            const logCountEl = document.getElementById('log-count');
            if (logCountEl) logCountEl.innerText = '0';
            const avgAqiVal = document.getElementById('avg-aqi-val');
            if (avgAqiVal) avgAqiVal.innerText = '--';
            const label = document.getElementById('avg-aqi-label');
            if (label) label.style.display = 'none';
            const minMaxAqi = document.getElementById('min-max-aqi');
            if (minMaxAqi) minMaxAqi.style.display = 'none';
            const miniChips = document.getElementById('mini-chips-container');
            if (miniChips) miniChips.style.display = 'none';
            const elTemp = document.getElementById('w-avg-temp');
            if (elTemp) elTemp.innerText = '--';
            const elHum = document.getElementById('w-avg-hum');
            if (elHum) elHum.innerText = '--';
            const elWind = document.getElementById('w-avg-wind');
            if (elWind) elWind.innerText = '--';
            const elGust = document.getElementById('w-max-gust');
            if (elGust) elGust.innerText = '--';
            const elDir = document.getElementById('w-mode-dir');
            if (elDir) elDir.innerText = '--';
            const elRain = document.getElementById('w-total-rain');
            if (elRain) elRain.innerText = '--';
        }
        document.getElementById('chart-empty').style.display = 'flex';
        document.getElementById('chart-empty-text').innerText = `No records found for ${rangeLabel}.`;
        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }
        document.getElementById('table-body').innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 36px 20px; color: #64748b;">No telemetry records available for ${rangeLabel}.</td></tr>`;
        return;
    }

    document.getElementById('chart-empty').style.display = 'none';

    // ── Summary Calculations for 1-Day View ──
    if (days === 1) {
        let sumAqi = 0, countAqi = 0;
        let sumPm25 = 0, countPm25 = 0;
        let sumPm10 = 0, countPm10 = 0;
        let sumCo = 0, countCo = 0;
        let sumNo2 = 0, countNo2 = 0;
        let sumO3 = 0, countO3 = 0;
        let minAqi = 9999, maxAqi = -1;
        let sumTemp = 0, countTemp = 0;
        let sumHum = 0, countHum = 0;
        let sumWind = 0, countWind = 0;
        let maxGust = -1, sumRain = 0;
        let windDirs = [];

        filteredDayData.forEach(d => {
            if (currentSubTab === 'aqi') {
                if (d.cpcb_aqi != null) { sumAqi += d.cpcb_aqi; countAqi++; if (d.cpcb_aqi < minAqi) minAqi = d.cpcb_aqi; if (d.cpcb_aqi > maxAqi) maxAqi = d.cpcb_aqi; }
                if (d.pm25 != null)    { sumPm25 += d.pm25; countPm25++; }
                if (d.pm10 != null)    { sumPm10 += d.pm10; countPm10++; }
                if (d.co != null)      { sumCo   += d.co;   countCo++;   }
                if (d.no2 != null)     { sumNo2  += d.no2;  countNo2++;  }
                if (d.o3 != null)      { sumO3   += d.o3;   countO3++;   }
            } else {
                if (d.temperature != null) { sumTemp += d.temperature; countTemp++; }
                if (d.humidity != null)    { sumHum  += d.humidity;    countHum++;  }
                if (d.wind_speed != null)  { sumWind += d.wind_speed;  countWind++; }
                if (d.wind_gust != null && d.wind_gust > maxGust) maxGust = d.wind_gust;
                if (d.rain_gauge != null)  sumRain += d.rain_gauge;
                if (d.wind_direction != null) windDirs.push(d.wind_direction);
            }
        });

        const summaryDateEl = document.getElementById('summary-date-display');
        if (summaryDateEl) {
            const [y, m, d] = dateInput.split('-').map(Number);
            const dtObj = new Date(y, m - 1, d);
            summaryDateEl.innerText = dtObj.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
        }
        const logCountEl = document.getElementById('log-count');
        if (logCountEl) {
            logCountEl.innerText = filteredDayData.length;
        }

        const aqiBlock = document.getElementById('summary-aqi-block');
        const weatherBlock = document.getElementById('summary-weather-block');
        if (aqiBlock) aqiBlock.style.display = currentSubTab === 'aqi' ? 'flex' : 'none';
        if (weatherBlock) weatherBlock.style.display = currentSubTab === 'weather' ? 'flex' : 'none';

        if (currentSubTab === 'aqi') {
            const avgAqi = countAqi > 0 ? Math.round(sumAqi / countAqi) : 0;
            const aqiCat = getAqiCategory(avgAqi);
            const avgAqiVal = document.getElementById('avg-aqi-val');
            if (avgAqiVal) {
                avgAqiVal.innerText = countAqi > 0 ? avgAqi : '--';
                avgAqiVal.style.color = countAqi > 0 ? aqiCat.text : '#64748b';
            }

            const label = document.getElementById('avg-aqi-label');
            if (label) {
                if (countAqi > 0) {
                    label.innerText = aqiCat.label;
                    label.style.display = 'inline-block';
                    label.style.backgroundColor = aqiCat.badgeBg;
                    label.style.color = aqiCat.badgeText;
                } else {
                    label.style.display = 'none';
                }
            }

            const minAqiVal = document.getElementById('min-aqi-val');
            const maxAqiVal = document.getElementById('max-aqi-val');
            const minMaxAqi = document.getElementById('min-max-aqi');
            if (minAqiVal) minAqiVal.innerText = countAqi > 0 ? minAqi : '--';
            if (maxAqiVal) maxAqiVal.innerText = countAqi > 0 ? maxAqi : '--';
            if (minMaxAqi) minMaxAqi.style.display = countAqi > 0 ? 'flex' : 'none';

            const badge = document.getElementById('avg-aqi-badge-container');
            if (badge) {
                badge.style.backgroundColor = countAqi > 0 ? aqiCat.bg : '#f8fafc';
                badge.style.borderColor = countAqi > 0 ? aqiCat.border : '#e2e8f0';
            }
            const avgAqiDot = document.getElementById('avg-aqi-dot');
            if (avgAqiDot) avgAqiDot.style.backgroundColor = countAqi > 0 ? aqiCat.color : '#94a3b8';
            const avgAqiTitle = document.getElementById('avg-aqi-title');
            if (avgAqiTitle) avgAqiTitle.style.color = countAqi > 0 ? aqiCat.text : '#64748b';

            const miniChips = document.getElementById('mini-chips-container');
            if (miniChips) miniChips.style.display = countAqi > 0 ? 'flex' : 'none';
            const elPm25 = document.getElementById('avg-pm25');
            const elPm10 = document.getElementById('avg-pm10');
            const elCo = document.getElementById('avg-co');
            const elNo2 = document.getElementById('avg-no2');
            const elO3 = document.getElementById('avg-o3');
            if (elPm25) {
                const avgVal = countPm25 > 0 ? (sumPm25/countPm25) : 0;
                elPm25.innerText = countPm25 > 0 ? avgVal.toFixed(3) : '0.000';
                elPm25.style.color = countPm25 > 0 ? getParamColor('pm25', avgVal) : '#0284c7';
            }
            if (elPm10) {
                const avgVal = countPm10 > 0 ? (sumPm10/countPm10) : 0;
                elPm10.innerText = countPm10 > 0 ? avgVal.toFixed(3) : '0.000';
                elPm10.style.color = countPm10 > 0 ? getParamColor('pm10', avgVal) : '#6366f1';
            }
            if (elCo) {
                const avgVal = countCo > 0 ? (sumCo/countCo) : 0;
                elCo.innerText = countCo > 0 ? avgVal.toFixed(3) : '0.000';
                elCo.style.color = countCo > 0 ? getParamColor('co', avgVal) : '#16a34a';
            }
            if (elNo2) {
                const avgVal = countNo2 > 0 ? (sumNo2/countNo2) : 0;
                elNo2.innerText = countNo2 > 0 ? avgVal.toFixed(3) : '0.000';
                elNo2.style.color = countNo2 > 0 ? getParamColor('no2', avgVal) : '#9333ea';
            }
            if (elO3) {
                const avgVal = countO3 > 0 ? (sumO3/countO3) : 0;
                elO3.innerText = countO3 > 0 ? avgVal.toFixed(3) : '0.000';
                elO3.style.color = countO3 > 0 ? getParamColor('o3', avgVal) : '#d97706';
            }
        } else {
            const elTemp = document.getElementById('w-avg-temp');
            const elHum = document.getElementById('w-avg-hum');
            const elWind = document.getElementById('w-avg-wind');
            const elGust = document.getElementById('w-max-gust');
            const elRain = document.getElementById('w-total-rain');
            if (elTemp) elTemp.innerText = countTemp > 0 ? (sumTemp/countTemp).toFixed(1) + '°C' : '--';
            if (elHum)  elHum.innerText = countHum > 0 ? (sumHum/countHum).toFixed(1) + '%' : '--';
            if (elWind) elWind.innerText = countWind > 0 ? (sumWind/countWind).toFixed(1) + ' km/h' : '--';
            if (elGust) elGust.innerText = maxGust > -1 ? maxGust.toFixed(1) + ' km/h' : '--';
            if (elRain) elRain.innerText = sumRain.toFixed(1) + ' mm';
            
            let modeDir = '--';
            if (windDirs.length > 0) {
                const counts = {};
                windDirs.forEach(w => {
                    const d = getCompassDir(w);
                    if (d && d !== '--') counts[d] = (counts[d] || 0) + 1;
                });
                let bestDir = '--', maxC = 0;
                for (const [dir, c] of Object.entries(counts)) {
                    if (c > maxC) {
                        maxC = c;
                        bestDir = dir;
                    }
                }
                modeDir = bestDir;
            }
            const elDir = document.getElementById('w-mode-dir');
            if (elDir) elDir.innerText = modeDir;
        }

        if (window.lucide) {
            lucide.createIcons();
        }
    }

    // Chart Render
    const ctx = document.getElementById('historical-chart').getContext('2d');

    const labels = filteredDayData.map(d => {
        const parts = (d.timestamp || '').split('T');
        if (parts.length === 2) {
            const [yr, mo, da] = parts[0].split('-').map(Number);
            const [hr, mn] = parts[1].split(':').map(Number);
            const dt = new Date(yr, mo - 1, da, hr, mn || 0);
            if (days > 1) {
                return `${String(da).padStart(2,'0')}/${String(mo).padStart(2,'0')} ${dt.toLocaleTimeString('en-US', {hour: '2-digit', hour12: true})}`;
            }
            return dt.toLocaleTimeString('en-US', {hour: '2-digit', hour12: true});
        }
        const dt = new Date(d.timestamp);
        if (days > 1) {
            return `${dt.getDate()}/${dt.getMonth()+1} ${dt.toLocaleTimeString('en-US', {hour: '2-digit', hour12: true})}`;
        }
        return dt.toLocaleTimeString('en-US', {hour: '2-digit', hour12: true});
    });

    // Preserve valid 0 values so Supabase zeros are displayed
    const dataPoints = filteredDayData.map(d => (d[currentParam] != null ? d[currentParam] : null));
    const hasScale = Boolean(POLLUTANT_SCALES[currentParam]);
    const itemColors = hasScale ? dataPoints.map(v => getParamColor(currentParam, v)) : null;
    const color = PARAM_COLORS[currentParam] || '#00bfa5';

    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, color + '66');
    gradient.addColorStop(1, color + '00');

    // Vertical crosshair plugin for silky-smooth touch dragging and crosshair tracking
    const crosshairPlugin = {
        id: 'crosshairLine',
        afterDatasetsDraw(chart) {
            if (chart.tooltip && chart.tooltip._active && chart.tooltip._active.length) {
                const activePoint = chart.tooltip._active[0];
                const ctx = chart.ctx;
                const x = activePoint.element.x;
                const topY = chart.scales.y.top;
                const bottomY = chart.scales.y.bottom;
                const idx = activePoint.index;
                const activeVal = dataPoints[idx];
                const activeColor = hasScale ? getParamColor(currentParam, activeVal) : color;
                ctx.save();
                ctx.beginPath();
                ctx.moveTo(x, topY);
                ctx.lineTo(x, bottomY);
                ctx.lineWidth = 1.5;
                ctx.strokeStyle = activeColor || '#00bfa5';
                ctx.globalAlpha = 0.55;
                ctx.setLineDash([4, 4]);
                ctx.stroke();
                ctx.restore();
            }
        }
    };

    // In-place silent update without destroying or flickering
    if (chartInstance && chartInstance.config && chartInstance.config.type === currentChartType) {
        if (chartInstance.options && chartInstance.options.scales && chartInstance.options.scales.x) {
            chartInstance.options.scales.x.display = false;
        }
        chartInstance.data.labels = labels;
        if (chartInstance.data.datasets && chartInstance.data.datasets[0]) {
            chartInstance.data.datasets[0].label = getParamDisplayName(currentParam);
            chartInstance.data.datasets[0].data = dataPoints;
            chartInstance.data.datasets[0].borderColor = (hasScale && currentChartType === 'bar') ? itemColors : color;
            chartInstance.data.datasets[0].backgroundColor = currentChartType === 'line' ? gradient : (hasScale ? itemColors : color);
            chartInstance.data.datasets[0].borderWidth = currentChartType === 'line' ? 2.5 : 0;
            chartInstance.data.datasets[0].borderRadius = currentChartType === 'bar' ? { topLeft: 4, topRight: 4 } : 0;
            chartInstance.data.datasets[0].borderSkipped = false;
            chartInstance.data.datasets[0].pointRadius = 0;
            chartInstance.data.datasets[0].pointBackgroundColor = hasScale ? itemColors : color;
            chartInstance.data.datasets[0].pointHoverRadius = 5;
            chartInstance.data.datasets[0].pointHitRadius = 28;
            if (hasScale && currentChartType === 'line') {
                chartInstance.data.datasets[0].segment = {
                    borderColor: ctx => getParamColor(currentParam, ctx.p1?.parsed?.y)
                };
            } else {
                delete chartInstance.data.datasets[0].segment;
            }
        }
        chartInstance.update({
            duration: 320,
            easing: 'easeOutQuart'
        });
    } else {
        if (chartInstance) { chartInstance.destroy(); }
        chartInstance = new Chart(ctx, {
            type: currentChartType,
            plugins: [crosshairPlugin],
            data: {
                labels: labels,
                datasets: [{
                    label: getParamDisplayName(currentParam),
                    data: dataPoints,
                    borderColor: (hasScale && currentChartType === 'bar') ? itemColors : color,
                    backgroundColor: currentChartType === 'line' ? gradient : (hasScale ? itemColors : color),
                    borderWidth: currentChartType === 'line' ? 2.5 : 0,
                    borderRadius: currentChartType === 'bar' ? { topLeft: 4, topRight: 4 } : 0,
                    borderSkipped: false,
                    barPercentage: days > 7 ? 0.9 : 0.72,
                    categoryPercentage: 0.88,
                    segment: hasScale && currentChartType === 'line' ? {
                        borderColor: ctx => getParamColor(currentParam, ctx.p1?.parsed?.y)
                    } : undefined,
                    fill: currentChartType === 'line',
                    tension: 0.4,
                    pointBackgroundColor: hasScale ? itemColors : color,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHitRadius: 28,
                    spanGaps: false
                }]
            },
            options: {
                animation: {
                    duration: 320,
                    easing: 'easeOutQuart'
                },
                responsive: true,
                maintainAspectRatio: false,
                events: ['mousemove', 'mouseout', 'click', 'touchstart', 'touchmove'],
                interaction: {
                    mode: 'index',
                    intersect: false,
                    axis: 'x'
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#94a3b8',
                        bodyColor: '#ffffff',
                        bodyFont: { size: 14, weight: 'bold', family: 'monospace' },
                        padding: 10,
                        cornerRadius: 12,
                        displayColors: hasScale,
                        boxWidth: 9,
                        boxHeight: 9,
                        usePointStyle: true,
                        callbacks: {
                            title: function(items) {
                                if (!items.length) return '';
                                const idx = items[0].dataIndex;
                                const item = filteredDayData[idx];
                                if (!item || !item.timestamp) return items[0].label;
                                const parts = item.timestamp.split('T');
                                if (parts.length === 2) {
                                    const [yr, mo, da] = parts[0].split('-').map(Number);
                                    const [hr, mn] = parts[1].split(':').map(Number);
                                    const dt = new Date(yr, mo - 1, da, hr, mn || 0);
                                    return `${String(da).padStart(2,'0')}/${String(mo).padStart(2,'0')}/${yr} • ${dt.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'})}`;
                                }
                                return items[0].label;
                            },
                            label: function(item) {
                                const val = item.parsed.y;
                                if (val == null) return '';
                                const unit = PARAM_UNITS[currentParam] || '';
                                const paramName = getParamDisplayName(currentParam);
                                if (hasScale) {
                                    const tier = getParamTierInfo(currentParam, val);
                                    const dispVal = (currentParam === 'cpcb_aqi' || currentParam === 'aqi') 
                                        ? Math.round(val) 
                                        : (currentParam === 'co' ? Number(val).toFixed(2) : Number(val).toFixed(1));
                                    return ` ${paramName}: ${dispVal}${unit ? ' ' + unit : ''} (${tier.label})`;
                                }
                                return ` ${paramName}: ${val}${unit ? ' ' + unit : ''}`;
                            },
                            labelColor: function(item) {
                                const val = item.parsed.y;
                                const c = hasScale ? getParamColor(currentParam, val) : color;
                                return {
                                    borderColor: c,
                                    backgroundColor: c,
                                    borderWidth: 1
                                };
                            }
                        }
                    }
                },
                scales: {
                    x: { display: false },
                    y: { grid: { color: '#e2e8f0', borderDash: [3, 3] }, ticks: { color: '#64748b' }, beginAtZero: true }
                }
            }
        });
    }

    // Table Render
    const theadRow = document.getElementById('table-head-row');
    const tbody = document.getElementById('table-body');
    
    if(currentSubTab === 'aqi') {
        theadRow.innerHTML = `
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">Date</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">Time</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">AQI</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">PM2.5 (µg/m³)</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">PM10 (µg/m³)</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">CO (mg/m³)</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">NO₂ (µg/m³)</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">O₃ (µg/m³)</th>
        `;
        
        tbody.innerHTML = filteredDayData.map((d, i) => {
            const parts = (d.timestamp || '').split('T');
            let dateStr = '', timeStr = '';
            if (parts.length === 2) {
                const [yr, mo, da] = parts[0].split('-').map(Number);
                const [hr, mn] = parts[1].split(':').map(Number);
                const dt = new Date(yr, mo - 1, da, hr, mn || 0);
                dateStr = `${String(da).padStart(2, '0')}/${String(mo).padStart(2, '0')}/${yr}`;
                timeStr = dt.toLocaleTimeString('en-US', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute:'2-digit' });
            } else {
                const dt = new Date(d.timestamp);
                dateStr = dt.toLocaleDateString('en-GB', { timeZone: 'Asia/Kolkata' });
                timeStr = dt.toLocaleTimeString('en-US', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute:'2-digit' });
            }
            return `<tr style="border-bottom: 1px solid #f1f5f9; background-color: ${i % 2 === 0 ? '#ffffff' : '#f8fafc'};">
                <td style="padding: 10px 16px; color: #0f172a; font-weight: 600; white-space: nowrap;">${dateStr}</td>
                <td style="padding: 10px 16px; color: #00bfa5; font-family: var(--font-mono); font-weight: 700; white-space: nowrap;">${timeStr}</td>
                <td style="padding: 10px 16px; font-weight: 800; color: ${getAqiCategory(d.cpcb_aqi).text};">${d.cpcb_aqi != null ? Math.round(d.cpcb_aqi) : '--'}</td>
                <td style="padding: 10px 16px; font-weight: 700; color: ${d.pm25 != null ? getParamColor('pm25', d.pm25) : '#475569'};">${d.pm25 != null ? Number(d.pm25).toFixed(3) : '0.000'}</td>
                <td style="padding: 10px 16px; font-weight: 700; color: ${d.pm10 != null ? getParamColor('pm10', d.pm10) : '#475569'};">${d.pm10 != null ? Number(d.pm10).toFixed(3) : '0.000'}</td>
                <td style="padding: 10px 16px; font-weight: 700; color: ${d.co != null ? getParamColor('co', d.co) : '#475569'};">${d.co != null ? Number(d.co).toFixed(3) : '0.000'}</td>
                <td style="padding: 10px 16px; font-weight: 700; color: ${d.no2 != null ? getParamColor('no2', d.no2) : '#475569'};">${d.no2 != null ? Number(d.no2).toFixed(3) : '0.000'}</td>
                <td style="padding: 10px 16px; font-weight: 700; color: ${d.o3 != null ? getParamColor('o3', d.o3) : '#475569'};">${d.o3 != null ? Number(d.o3).toFixed(3) : '0.000'}</td>
            </tr>`;
        }).join('');
    } else {
        theadRow.innerHTML = `
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">Date</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">Time</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">Temp (°C)</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">Humidity (%)</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">Wind Spd (km/h)</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">Wind Gust (km/h)</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">Wind Dir</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">Rain (mm)</th>
        `;
        
        tbody.innerHTML = filteredDayData.map((d, i) => {
            const parts = (d.timestamp || '').split('T');
            let dateStr = '', timeStr = '';
            if (parts.length === 2) {
                const [yr, mo, da] = parts[0].split('-').map(Number);
                const [hr, mn] = parts[1].split(':').map(Number);
                const dt = new Date(yr, mo - 1, da, hr, mn || 0);
                dateStr = `${String(da).padStart(2, '0')}/${String(mo).padStart(2, '0')}/${yr}`;
                timeStr = dt.toLocaleTimeString('en-US', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute:'2-digit' });
            } else {
                const dt = new Date(d.timestamp);
                dateStr = dt.toLocaleDateString('en-GB', { timeZone: 'Asia/Kolkata' });
                timeStr = dt.toLocaleTimeString('en-US', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute:'2-digit' });
            }
            return `<tr style="border-bottom: 1px solid #f1f5f9; background-color: ${i % 2 === 0 ? '#ffffff' : '#f8fafc'};">
                <td style="padding: 10px 16px; color: #0f172a; font-weight: 600; white-space: nowrap;">${dateStr}</td>
                <td style="padding: 10px 16px; color: #00bfa5; font-family: var(--font-mono); font-weight: 700; white-space: nowrap;">${timeStr}</td>
                <td style="padding: 10px 16px; color: #0f172a; font-weight: 600;">${d.temperature != null ? Number(d.temperature).toFixed(1) : '--'}</td>
                <td style="padding: 10px 16px; color: #475569;">${d.humidity != null ? Number(d.humidity).toFixed(1) : '--'}</td>
                <td style="padding: 10px 16px; color: #475569;">${d.wind_speed != null ? Number(d.wind_speed).toFixed(1) : '0.0'}</td>
                <td style="padding: 10px 16px; color: #475569;">${d.wind_gust != null ? Number(d.wind_gust).toFixed(1) : '0.0'}</td>
                <td style="padding: 10px 16px; color: #475569;">${getCompassDir(d.wind_direction)}</td>
                <td style="padding: 10px 16px; color: #475569;">${d.rain_gauge != null ? Number(d.rain_gauge).toFixed(1) : '0.0'}</td>
            </tr>`;
        }).join('');
    }
}

function getPreviousDayDateStr() {
    const today = new Date();
    const prev = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
    const yr = prev.getFullYear();
    const mo = String(prev.getMonth() + 1).padStart(2, '0');
    const da = String(prev.getDate()).padStart(2, '0');
    return `${yr}-${mo}-${da}`;
}

function openDatasetModal() {
    const scopeLabel = currentSubTab === 'aqi' ? 'AQI Telemetry' : 'Weather Telemetry';
    const timeFrame = currentSubTab === 'aqi' ? '12:00 AM – 12:00 AM' : '8:00 AM – Next Day 8:00 AM';
    
    // Strictly provide data of the previous day (yesterday)
    const prevDateStr = getPreviousDayDateStr();
    window.__requestedDatasetDate = prevDateStr;
    
    renderModalScopeHtml(scopeLabel, prevDateStr, timeFrame);
    
    // Auto-populate logged-in user email and name with real authenticated credentials
    const emailInput = document.getElementById('dataset-email-input') || document.querySelector('#dataset-form input[name="email"]');
    const nameInput = document.querySelector('#dataset-form input[name="fullName"]');

    // Purge any stale mock values that might have been prefilled or cached
    if (emailInput && (emailInput.value === 'manishb@sode-edu.in' || emailInput.value === 'manish@sode-edu.in')) {
        emailInput.value = '';
    }
    if (nameInput && nameInput.value === 'Manish B') {
        nameInput.value = '';
    }

    // Resolve real authenticated user email from Firebase Auth, Session, or LocalStorage
    let resolvedEmail = '';
    if (window.getFirebaseAuthEmail && window.getFirebaseAuthEmail()) {
        resolvedEmail = window.getFirebaseAuthEmail();
    } else if (window.firebaseAuth && window.firebaseAuth.currentUser && window.firebaseAuth.currentUser.email) {
        resolvedEmail = window.firebaseAuth.currentUser.email;
    } else if (window.__currentUser && window.__currentUser.email && window.__currentUser.email !== 'manishb@sode-edu.in' && window.__currentUser.email !== 'manish@sode-edu.in') {
        resolvedEmail = window.__currentUser.email;
    } else if (localStorage.getItem('auth_email') && localStorage.getItem('auth_email') !== 'manishb@sode-edu.in' && localStorage.getItem('auth_email') !== 'manish@sode-edu.in') {
        resolvedEmail = localStorage.getItem('auth_email');
    }

    if (emailInput && resolvedEmail) {
        emailInput.value = resolvedEmail;
    }

    // Resolve real authenticated user name
    let resolvedName = '';
    if (window.getFirebaseAuthName && window.getFirebaseAuthName()) {
        resolvedName = window.getFirebaseAuthName();
    } else if (window.firebaseAuth && window.firebaseAuth.currentUser && window.firebaseAuth.currentUser.displayName) {
        resolvedName = window.firebaseAuth.currentUser.displayName;
    } else if (window.__currentUser && window.__currentUser.name && window.__currentUser.name !== 'Manish B') {
        resolvedName = window.__currentUser.name;
    } else if (localStorage.getItem('auth_name') && localStorage.getItem('auth_name') !== 'Manish B') {
        resolvedName = localStorage.getItem('auth_name');
    } else if (resolvedEmail) {
        resolvedName = resolvedEmail.split('@')[0];
    }

    if (nameInput && resolvedName) {
        nameInput.value = resolvedName;
    }

    // Reset views & proof section
    document.getElementById('dataset-form').style.display = 'flex';
    document.getElementById('dataset-success').style.display = 'none';
    resetIndustryProofSection();
    
    const modal = document.getElementById('dataset-modal');
    modal.style.display = 'flex';
    setTimeout(() => { modal.style.opacity = '1'; }, 10);
    
    lucide.createIcons();
}

// Global state for ID Proof
window.__selectedProofFile = null;
window.__proofRequired = false;


window.INDUSTRY_PROOFS = {
    'Student': 'Upload Student ID Card / Proof *',
    'Academic / Research': 'Upload Faculty / Institutional ID Proof *',
    'Government / Public Sector': 'Upload Official Government Photo ID / Letter *',
    'Environmental Agency': 'Upload Environmental Agency Accreditation / ID *',
    'Other': 'Upload Official ID Card / Proof Document *'
};

function handleIndustryProofRequirement(val) {
    const section = document.getElementById('industry-proof-section');
    const fileLabel = document.getElementById('proof-file-label');

    if (!val) {
        if (section) section.style.display = 'none';
        window.__proofRequired = false;
        return;
    }

    if (fileLabel) {
        fileLabel.textContent = window.INDUSTRY_PROOFS[val] || 'Upload Official ID Card / Proof Document *';
    }

    window.__proofRequired = true;
    if (section) {
        section.style.display = 'block';
    }
    lucide.createIcons();
}

function handleProofFileSelected(input) {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];
    
    // File size guard (5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert("Selected document exceeds the maximum 5MB size limit. Please upload a smaller compressed PDF or image.");
        input.value = '';
        return;
    }

    const validTypes = ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
    const ext = file.name.split('.').pop().toLowerCase();
    if (!validTypes.includes(file.type) && !['pdf', 'png', 'jpg', 'jpeg', 'webp'].includes(ext)) {
        alert("Invalid file format. Please upload a PDF or image (PNG, JPG, WEBP).");
        input.value = '';
        return;
    }

    window.__selectedProofFile = file;

    const preview = document.getElementById('proof-file-preview');
    const dropzone = document.getElementById('proof-dropzone');
    const nameEl = document.getElementById('proof-filename');
    const sizeEl = document.getElementById('proof-filesize');

    if (nameEl) nameEl.textContent = file.name;
    if (sizeEl) sizeEl.textContent = formatBytes(file.size);

    if (dropzone) dropzone.style.display = 'none';
    if (preview) {
        preview.style.display = 'flex';
        preview.style.borderColor = '#00bfa5';
    }
    lucide.createIcons();
}

function removeSelectedProofFile(e) {
    if (e) e.stopPropagation();
    window.__selectedProofFile = null;
    const input = document.getElementById('proof-file-input');
    if (input) input.value = '';
    
    const preview = document.getElementById('proof-file-preview');
    const dropzone = document.getElementById('proof-dropzone');
    
    if (preview) preview.style.display = 'none';
    if (dropzone) {
        dropzone.style.display = 'block';
        dropzone.style.borderColor = '#e2e8f0';
        dropzone.style.backgroundColor = '#f8fafc';
    }
    lucide.createIcons();
}

function resetIndustryProofSection() {
    window.__selectedProofFile = null;
    window.__proofRequired = false;
    const input = document.getElementById('proof-file-input');
    if (input) input.value = '';
    const preview = document.getElementById('proof-file-preview');
    if (preview) preview.style.display = 'none';
    const dropzone = document.getElementById('proof-dropzone');
    if (dropzone) {
        dropzone.style.display = 'block';
        dropzone.style.borderColor = '#e2e8f0';
        dropzone.style.backgroundColor = '#f8fafc';
    }
    const section = document.getElementById('industry-proof-section');
    if (section) section.style.display = 'none';
}

function handleUseCaseInput(el) {
    if (!el) return;
    if (el.value && el.value.trim().length > 0) {
        el.style.borderColor = '#00bfa5';
    } else {
        el.style.borderColor = '#e2e8f0';
    }
}

function formatBytes(bytes) {
    if (!bytes || bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function compressImageIfNeeded(file) {
    return new Promise((resolve) => {
        // If not an image (e.g. PDF), read directly
        if (!file || !file.type || !file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = () => resolve(null);
            reader.readAsDataURL(file);
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const maxDim = 1600;
                let width = img.width;
                let height = img.height;
                if (width > maxDim || height > maxDim) {
                    if (width > height) {
                        height = Math.round((height * maxDim) / width);
                        width = maxDim;
                    } else {
                        width = Math.round((width * maxDim) / height);
                        height = maxDim;
                    }
                }
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                // 0.85 quality JPEG keeps crisp text clarity while compressing 8MB to ~200KB
                const compressedBase64 = canvas.toDataURL('image/jpeg', 0.85);
                resolve(compressedBase64);
            };
            img.onerror = () => resolve(e.target.result);
            img.src = e.target.result;
        };
        reader.onerror = () => resolve(null);
        reader.readAsDataURL(file);
    });
}

function readFileAsBase64(file) {
    return compressImageIfNeeded(file);
}

function renderModalScopeHtml(scopeLabel, targetDate, timeFrame) {
    document.getElementById('modal-dataset-scope').innerHTML = `
      <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
        <div>
          <div style="font-size: 14.5px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
            <span>${scopeLabel} — <span style="color: #00bfa5; font-family: var(--font-mono);">${targetDate}</span></span>
          </div>
          <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
            Cycle: <strong style="color: #334155;">${timeFrame}</strong>
          </div>
        </div>
      </div>
      <div style="font-size: 12px; color: #ef4444; margin-top: 8px; padding-top: 6px; border-top: 1px dashed #e2e8f0; font-weight: 600;">
        * Data provided is strictly limited to 24 hours of the previous day (${timeFrame}).
      </div>
      <div style="margin-top: 8px; padding-top: 6px; border-top: 1px dashed #e2e8f0; font-size: 12.5px; color: #475569; font-weight: 500; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
        <span>For more dataset</span>
        <span style="color: #94a3b8;">→</span>
        <button type="button" onclick="closeDatasetModal(); document.getElementById('contact-modal').style.display='flex'; if (window.lucide) { lucide.createIcons(); }" style="background: none; border: none; font-size: 12.5px; font-weight: 700; color: #00bfa5; cursor: pointer; text-decoration: underline; text-decoration-color: rgba(0,191,165,0.4); text-underline-offset: 3px; display: inline-flex; align-items: center; gap: 2px; padding: 0; transition: all 0.2s;" onmouseover="this.style.color='#0d9488';" onmouseout="this.style.color='#00bfa5';">
          Contact Us
        </button>
      </div>
    `;
    lucide.createIcons();
}

function closeDatasetModal() {
    const modal = document.getElementById('dataset-modal');
    modal.style.opacity = '0';
    setTimeout(() => {
        modal.style.display = 'none';
        resetIndustryProofSection();
        const ta = document.getElementById('use-case-textarea');
        if (ta) {
            ta.value = '';
            handleUseCaseInput(ta);
        }
    }, 300); // Wait for transition
}

async function submitDatasetRequest(e) {
    e.preventDefault();
    const btn = document.getElementById('dataset-submit-btn');
    const originalText = btn.innerHTML;

    const indSelect = document.getElementById('industry-select');
    const indVal = indSelect ? indSelect.value : '';

    // MANDATORY PROOF ENFORCEMENT:
    if (indVal) {
        if (!window.__selectedProofFile) {
            alert("Mandatory Verification Proof Required: Please upload your official ID card or proof document.");
            const dropzone = document.getElementById('proof-dropzone');
            if (dropzone) {
                dropzone.style.borderColor = '#ef4444';
                dropzone.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }
    }

    // USE CASE VALIDATION:
    const formDataObj = new FormData(e.target);
    const useCaseVal = (formDataObj.get('use_case') || '').trim();
    if (!useCaseVal) {
        alert("Please describe your operational or analytical requirements for this telemetry dataset.");
        const ta = document.getElementById('use-case-textarea');
        if (ta) {
            ta.focus();
            ta.style.borderColor = '#ef4444';
        }
        return;
    }
    
    const targetDate = window.__requestedDatasetDate || getPreviousDayDateStr();
    const scopeLabel = currentSubTab === 'aqi' ? 'AQI Telemetry' : 'Weather Telemetry';
    const timeFrame = currentSubTab === 'aqi' ? '12:00 AM – 12:00 AM' : '8:00 AM – Next Day 8:00 AM';
    const scopeDescription = `${scopeLabel} — ${targetDate} (${timeFrame}) [Previous Day]`;

    btn.innerHTML = `<i data-lucide="loader-2" class="lucide-spin" style="width: 16px; height: 16px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle;"></i> <span style="vertical-align: middle;">Securing Proof &amp; Transmitting...</span>`;
    btn.style.pointerEvents = 'none';
    lucide.createIcons();

    let proofBase64 = null;
    let proofFileName = null;

    try {
        if (window.__selectedProofFile) {
            proofBase64 = await readFileAsBase64(window.__selectedProofFile);
            proofFileName = window.__selectedProofFile.name;
        }

        // Gather form data
        const formData = {
            name: formDataObj.get('fullName'),
            email: formDataObj.get('email'),
            org: formDataObj.get('org'),
            industry: indVal,
            proof_name: proofFileName,
            proof_data: proofBase64,
            use_case: formDataObj.get('use_case'),
            scope: scopeDescription,
            target_date: targetDate,
            telemetry_type: currentSubTab
        };
        
        const response = await fetch('api/submit_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        const data = await response.json();

        if (data.success) {
            // Immediately switch UI to success screen for instant responsive UX
            document.getElementById('dataset-form').style.display = 'none';
            document.getElementById('dataset-success').style.display = 'flex';
            document.getElementById('dataset-form').reset();
            resetIndustryProofSection();
            lucide.createIcons();

            // Save to Firebase Realtime Database asynchronously in background (non-blocking)
            const reqData = {
                id: data.request_id || 'req_' + Math.floor(Math.random()*10000),
                name: formData.name,
                email: formData.email,
                org: formData.org,
                industry: formData.industry,
                proof_file: data.proof_file || '',
                proof_name: proofFileName || '',
                proof_data: proofBase64 || '',
                use_case: formData.use_case,
                description: formData.use_case,
                scope: formData.scope,
                target_date: targetDate,
                telemetry_type: currentSubTab,
                status: 'pending',
                created_at: new Date().toISOString()
            };
            
            const fbDbUrl = (window.ENV_CONFIG && window.ENV_CONFIG.firebaseDatabaseUrl) 
                ? window.ENV_CONFIG.firebaseDatabaseUrl.replace(/\/$/, '') 
                : 'https://smart-air-net-default-rtdb.firebaseio.com';
            
            (async () => {
                try {
                    let idToken = '';
                    if (window.firebaseAuth && window.firebaseAuth.currentUser) {
                        idToken = await window.firebaseAuth.currentUser.getIdToken(true);
                    }
                    const url = idToken ? `${fbDbUrl}/requests.json?auth=${encodeURIComponent(idToken)}` : `${fbDbUrl}/requests.json`;
                    fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(reqData)
                    }).catch(e => console.warn("Firebase sync non-blocking error:", e));
                } catch(tokenErr) {
                    console.warn("Firebase ID token error:", tokenErr);
                }
            })();
        } else {
            alert("Error: " + (data.error || "Unknown error occurred"));
        }
    } catch (err) {
        console.error("Error submitting request", err);
        alert("Failed to connect to the server or process ID proof document.");
    } finally {
        btn.innerHTML = originalText;
        btn.style.pointerEvents = 'auto';
    }
}

function downloadCSV() {
    if(filteredDayData.length === 0) return;
    
    let csv = '';
    if(currentSubTab === 'aqi') {
        csv += 'Date,Time,AQI,PM2.5,PM10,CO,NO2,O3\n';
        filteredDayData.forEach(d => {
            const dt = new Date(d.timestamp);
            csv += `${dt.toLocaleDateString('en-GB')},${dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'})},${Math.round(d.cpcb_aqi)},${d.pm25},${d.pm10},${d.co},${d.no2},${d.o3}\n`;
        });
    } else {
        csv += 'Date,Time,Temp,Humidity,WindSpeed,WindGust,WindDir,Rain\n';
        filteredDayData.forEach(d => {
            const dt = new Date(d.timestamp);
            csv += `${dt.toLocaleDateString('en-GB')},${dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'})},${d.temperature},${d.humidity},${d.wind_speed},${d.wind_gust},${d.wind_direction},${d.rain_gauge}\n`;
        });
    }
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${currentSubTab}_history_${document.getElementById('history-date-picker').value}_${currentRangeDays}d.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}

var autoRefreshHistInterval = null;

function startAutoRefreshHist() {
    if (autoRefreshHistInterval) clearInterval(autoRefreshHistInterval);
    autoRefreshHistInterval = setInterval(() => {
        if (document.hidden) return;
        const dp = document.getElementById('history-date-picker');
        if (!dp) return;
        const todayStr = getLocalDateStr();
        if (dp.value === todayStr && currentRangeDays === 1) {
            fetchAllHistory(todayStr, true);
        }
    }, 12000);
}

window.__cleanupCurrentPage = function() {
    if (autoRefreshHistInterval) {
        clearInterval(autoRefreshHistInterval);
        autoRefreshHistInterval = null;
    }
    if (chartInstance) {
        chartInstance.destroy();
        chartInstance = null;
    }
};

setTimeout(() => {
    const dp = document.getElementById('history-date-picker');
    if (dp) {
        if (!dp.value) dp.value = _initState.date || '<?= $today_ist ?>';
        dp.onchange = () => {
            persistHistoricalState();
            fetchAllHistory();
        };
    }
    if (_initState.days && _initState.days !== 1) {
        setRangeDays(_initState.days, true);
    }
    if (_initState.tab && _initState.tab !== 'aqi') {
        switchHistTab(_initState.tab, true);
    }
    if (_initState.param) {
        setParam(_initState.param, true);
    }
    if (_initState.type && _initState.type !== 'bar') {
        setChartType(_initState.type, true);
    }

    // Attach on-demand prefetch handlers to range dropdown & buttons so queries run only when touched/hovered
    const rSelect = document.getElementById('range-select');
    if (rSelect) {
        const prefetchCommon = () => {
            const picker = document.getElementById('history-date-picker');
            const qDate = picker && picker.value ? picker.value : '<?= $today_ist ?>';
            [3, 7].forEach(d => {
                const k = getCacheKey(qDate, d);
                if (!clientRangeCache[k] && !pendingRangeFetches[k]) {
                    fetchRangeData(qDate, d).catch(() => {});
                }
            });
        };
        rSelect.addEventListener('pointerenter', prefetchCommon, { passive: true });
        rSelect.addEventListener('focus', prefetchCommon, { passive: true });
    }
    [1, 3, 7, 30].forEach(d => {
        const btn = document.getElementById('btn-range-' + d);
        if (btn) {
            const prefetchRange = () => {
                const picker = document.getElementById('history-date-picker');
                const qDate = picker && picker.value ? picker.value : '<?= $today_ist ?>';
                const k = getCacheKey(qDate, d);
                if (!clientRangeCache[k] && !pendingRangeFetches[k]) {
                    fetchRangeData(qDate, d).catch(() => {});
                }
            };
            btn.addEventListener('pointerenter', prefetchRange, { passive: true });
            btn.addEventListener('pointerdown', prefetchRange, { passive: true });
        }
    });

    persistHistoricalState();
    fetchAllHistory();
    startAutoRefreshHist();
}, 0);
</script>
