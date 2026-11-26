<?php
if (!function_exists('smartairnet_get_active_node_id')) {
    require_once __DIR__ . '/../config/nodes.php';
}
$active_node_id = '1';
$is_node_2 = false;

$today_ist = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
$req_subtab = (isset($_GET['tab']) && in_array($_GET['tab'], ['aqi', 'weather', 'soil'])) ? $_GET['tab'] : 'aqi';
$req_range_days = (isset($_GET['days']) && in_array((int)$_GET['days'], [1, 3, 7, 30])) ? (int)$_GET['days'] : 1;
$valid_params = [
    'cpcb_aqi', 'pm25', 'pm10', 'co', 'no2', 'o3',
    'temperature', 'humidity', 'wind_speed', 'wind_gust', 'wind_direction', 'rain_gauge', 'pressure', 'uv_index', 'solar_radiation',
    'soil_temperature', 'soil_humidity', 'soil_moisture', 'soil_ph', 'soil_conductivity'
];
$default_param = ($req_subtab === 'soil') ? 'soil_moisture' : (($req_subtab === 'weather') ? 'temperature' : 'cpcb_aqi');
$req_param = (isset($_GET['param']) && in_array($_GET['param'], $valid_params)) ? $_GET['param'] : $default_param;
$req_type = (isset($_GET['type']) && in_array($_GET['type'], ['bar', 'line'])) ? $_GET['type'] : 'bar';
$req_date = (isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) ? $_GET['date'] : $today_ist;

$init_history_json = 'null';
?>
<style>
/* Sleek Dark Trend Graph Card matching user's requested specification */
.trend-graph-card {
    background: linear-gradient(145deg, #181e2b 0%, #202736 100%);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 24px;
    box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.05);
    color: #f8fafc;
    position: relative;
    overflow: visible;
}
.trend-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 14px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.14);
    color: #f1f5f9;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    backdrop-filter: blur(8px);
}
.trend-pill-btn:hover {
    background: rgba(255, 255, 255, 0.14);
    border-color: rgba(255, 255, 255, 0.22);
}
.trend-dropdown-menu {
    position: absolute;
    right: 0;
    top: calc(100% + 8px);
    z-index: 60;
    background: rgba(22, 28, 40, 0.98);
    backdrop-filter: blur(14px);
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 14px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
    padding: 6px;
    min-width: 220px;
    display: none;
}
@media (max-width: 640px) {
    .trend-dropdown-menu {
        left: 0 !important;
        right: auto !important;
        max-width: calc(100vw - 36px) !important;
    }
}
.trend-dropdown-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 9px 12px;
    border-radius: 10px;
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
    background: rgba(0, 191, 165, 0.15);
    color: #00d6b9;
}
.trend-check-box {
    width: 17px;
    height: 17px;
    border-radius: 4px;
    border: 1.5px solid rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
    color: transparent;
    transition: all 0.15s ease;
    flex-shrink: 0;
}
.trend-dropdown-item.active .trend-check-box {
    background: #00bfa5;
    border-color: #00bfa5;
    color: #ffffff;
}

/* Unified Node 2 Sub-Navigation Switcher (Identical across Live & Analytical on Mobile & Desktop) */
#node2-hist-subnav {
    width: 100% !important;
    padding: 0 4px !important;
    box-sizing: border-box !important;
}
.node2-subtab-container {
    display: inline-flex !important;
    align-items: center !important;
    background-color: #ffffff !important;
    padding: 3.5px !important;
    border-radius: 9999px !important;
    border: 1px solid #cbd5e1 !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
    gap: 3px !important;
    width: 100% !important;
    max-width: 390px !important;
    margin: 0 auto !important;
    justify-content: space-between !important;
    box-sizing: border-box !important;
}
@media (min-width: 640px) {
    .node2-subtab-container {
        width: auto !important;
        max-width: none !important;
        justify-content: center !important;
        gap: 5px !important;
        padding: 4px !important;
    }
}
.node2-subtab-btn {
    flex: 1 1 0px !important;
    min-width: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 4px !important;
    padding: 5px 8px !important;
    border-radius: 9999px !important;
    font-weight: 700 !important;
    font-size: 11.5px !important;
    min-height: 30px !important;
    white-space: nowrap !important;
    cursor: pointer !important;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
    border: none !important;
    outline: none !important;
    text-decoration: none !important;
}
@media (min-width: 640px) {
    .node2-subtab-btn {
        flex: 0 0 auto !important;
        padding: 6px 16px !important;
        font-size: 12.5px !important;
        min-height: 32px !important;
        gap: 6px !important;
    }
}
.node2-subtab-btn.active {
    background-color: #00bfa5 !important;
    color: #ffffff !important;
    box-shadow: 0 1px 3px rgba(0, 191, 165, 0.28) !important;
}
.node2-subtab-btn:not(.active) {
    background-color: transparent !important;
    color: #64748b !important;
}
.node2-subtab-btn:not(.active):hover {
    color: #0f172a !important;
    background-color: #f8fafc !important;
}
</style>
<div class="p-2.5 sm:p-6 flex flex-col gap-3.5 sm:gap-6 max-w-[1200px] mx-auto text-slate-900 font-sans">
  
  <!-- Sub-Navigation Selector: AQI vs Weather vs Soil (Comfortable Responsive Switcher) -->
  <div class="flex items-center justify-center mb-2.5 sm:mb-4 w-full px-1" id="node2-hist-subnav">
    <div class="node2-subtab-container">
      <button id="tab-aqi" onclick="switchHistTab('aqi')" class="node2-subtab-btn <?= $req_subtab === 'aqi' ? 'active' : '' ?>">
        <i data-lucide="activity" class="w-3.5 h-3.5 shrink-0"></i>
        <span>AQI</span>
      </button>
      <button id="tab-weather" onclick="switchHistTab('weather')" class="node2-subtab-btn <?= $req_subtab === 'weather' ? 'active' : '' ?>">
        <i data-lucide="cloud-sun" class="w-3.5 h-3.5 shrink-0"></i>
        <span>Weather</span>
      </button>
      <button id="tab-soil" onclick="switchHistTab('soil')" class="node2-subtab-btn <?= $req_subtab === 'soil' ? 'active' : '' ?>">
        <i data-lucide="sprout" class="w-3.5 h-3.5 shrink-0"></i>
        <span>Soil</span>
      </button>
    </div>
  </div>

  <!-- Page Header Title -->
  <div style="display: flex; justify-content: flex-start; align-items: center; text-align: left; width: 100%; margin-top: 6px; margin-bottom: 2px;">
    <h1 id="analytics-page-heading" class="page-heading live-page-title" style="font-size: clamp(20px, 2.5vw, 24px); font-weight: 800; color: #0f172a; font-family: var(--font-sans); letter-spacing: -0.02em; margin: 0; text-align: left;">
      <?= $req_subtab === 'weather' ? 'Historical Weather Telemetry' : ($req_subtab === 'soil' ? 'Historical Soil Telemetry' : 'Historical Air Quality Telemetry') ?>
    </h1>
  </div>

  <!-- ── Analytics Date & Range Picker Card ── -->
  <div class="bg-white rounded-[20px] sm:rounded-[24px] p-3.5 sm:p-5 border border-slate-200/90 shadow-[0_4px_20px_rgba(0,0,0,0.03)] hover:shadow-[0_6px_24px_rgba(0,191,165,0.08)] transition-all">

    <!-- Top Header Row: Date & Range Selector Controls -->
    <div class="flex items-center justify-between flex-wrap gap-3 sm:gap-4">
      
      <!-- Left Title & Subtitle -->
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-teal-50 border border-teal-200/70 flex items-center justify-center text-[#00bfa5] shrink-0 shadow-xs">
          <i data-lucide="calendar" class="w-5 h-5"></i>
        </div>
        <div>
          <div class="text-sm sm:text-[15px] font-extrabold text-slate-800 tracking-tight flex items-center gap-2">
            <span id="range-header-title">Select Date (24-Hour Telemetry)</span>
          </div>
          <div class="text-[11.5px] sm:text-xs text-slate-500 font-medium mt-0.5">
            <span id="date-subtitle-desktop">Hourly sensor progression: 12:00 AM – 12:00 AM</span>
          </div>
        </div>
      </div>

      <!-- Right Action Controls: Custom Range Dropdown & Custom Date Picker -->
      <div class="flex items-center gap-2 sm:gap-2.5 flex-wrap w-full sm:w-auto justify-between sm:justify-end">
        <!-- Custom Range Selector Dropdown -->
        <div class="relative inline-flex items-center" id="custom-range-dropdown">
          <select id="range-select" onchange="setRangeDays(this.value)" class="sr-only" aria-hidden="true" tabindex="-1">
            <option value="1" <?= $req_range_days === 1 ? 'selected' : '' ?>>24h</option>
            <option value="3" <?= $req_range_days === 3 ? 'selected' : '' ?>>3 Days</option>
            <option value="7" <?= $req_range_days === 7 ? 'selected' : '' ?>>7 Days</option>
            <option value="30" <?= $req_range_days === 30 ? 'selected' : '' ?>>30 Days</option>
          </select>
          <button type="button" id="range-dropdown-btn" onclick="toggleRangeMenu(event)" class="min-h-[38px] sm:min-h-[40px] inline-flex items-center gap-2 px-3 sm:px-3.5 py-2 rounded-xl border border-slate-200 bg-[#f8fafc] hover:bg-white hover:border-[#00bfa5] text-xs sm:text-[13px] font-bold text-slate-800 shadow-xs transition-all cursor-pointer outline-none select-none">
            <i data-lucide="clock-4" class="w-3.5 h-3.5 text-[#00bfa5] shrink-0"></i>
            <span id="range-dropdown-label"><?= $req_range_days === 1 ? '24h' : ($req_range_days . ' Days') ?></span>
            <i data-lucide="chevron-down" id="range-dropdown-chevron" class="w-3.5 h-3.5 text-slate-400 transition-transform duration-150 shrink-0"></i>
          </button>
          <div id="range-dropdown-menu" style="display: none; position: absolute; top: calc(100% + 6px); left: 0; min-width: 158px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 6px; box-shadow: 0 16px 36px -6px rgba(15, 23, 42, 0.16); z-index: 80;">
            <?php foreach ([1 => '24h', 3 => '3 Days', 7 => '7 Days', 30 => '30 Days'] as $rv => $rl): ?>
            <button type="button" data-range-opt="<?= $rv ?>" onclick="selectRangeOption(<?= $rv ?>)" class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs sm:text-[13px] font-bold transition-all cursor-pointer <?= $req_range_days === $rv ? 'bg-teal-50 text-[#00bfa5]' : 'text-slate-700 hover:bg-slate-50' ?>">
              <span><?= $rl ?></span>
              <span class="range-opt-check text-[#00bfa5] font-extrabold text-xs"><?= $req_range_days === $rv ? '✓' : '' ?></span>
            </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Custom Date Picker -->
        <div class="relative inline-flex items-center" id="custom-date-picker">
          <input type="date" id="history-date-picker" value="<?= htmlspecialchars($req_date) ?>" max="<?= htmlspecialchars($today_ist) ?>" class="sr-only" aria-hidden="true" tabindex="-1">
          <button type="button" id="date-picker-btn" onclick="toggleDateCalendar(event)" class="min-h-[38px] sm:min-h-[40px] inline-flex items-center gap-2 px-3 sm:px-3.5 py-2 rounded-xl border border-slate-200 bg-[#f8fafc] hover:bg-white hover:border-[#00bfa5] text-xs sm:text-[13px] font-bold text-slate-800 shadow-xs transition-all cursor-pointer outline-none select-none">
            <i data-lucide="calendar" class="w-3.5 h-3.5 text-[#00bfa5] shrink-0"></i>
            <span id="date-picker-label"><?= htmlspecialchars(date('d M Y', strtotime($req_date))) ?></span>
            <i data-lucide="chevron-down" id="date-picker-chevron" class="w-3.5 h-3.5 text-slate-400 transition-transform duration-150 shrink-0"></i>
          </button>
          <div id="date-calendar-popover" style="display: none; position: absolute; top: calc(100% + 6px); right: 0; width: 286px; max-width: calc(100vw - 24px); background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 14px; box-shadow: 0 18px 42px -8px rgba(15, 23, 42, 0.2); z-index: 80;">
            <div class="flex items-center justify-between mb-3">
              <span id="cal-month-title" class="text-sm font-extrabold text-slate-800 tracking-tight">November 2026</span>
              <div class="flex items-center gap-1">
                <button type="button" id="cal-prev-btn" onclick="shiftCalendarMonth(-1)" class="w-7 h-7 rounded-full border border-slate-200 bg-slate-50 hover:bg-teal-50 hover:border-[#00bfa5] hover:text-[#00bfa5] flex items-center justify-center text-slate-600 font-bold cursor-pointer transition-all" aria-label="Previous Month">‹</button>
                <button type="button" id="cal-next-btn" onclick="shiftCalendarMonth(1)" class="w-7 h-7 rounded-full border border-slate-200 bg-slate-50 hover:bg-teal-50 hover:border-[#00bfa5] hover:text-[#00bfa5] flex items-center justify-center text-slate-600 font-bold cursor-pointer transition-all" aria-label="Next Month">›</button>
              </div>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center mb-1.5">
              <span class="text-[10.5px] font-bold text-slate-400">Mo</span>
              <span class="text-[10.5px] font-bold text-slate-400">Tu</span>
              <span class="text-[10.5px] font-bold text-slate-400">We</span>
              <span class="text-[10.5px] font-bold text-slate-400">Th</span>
              <span class="text-[10.5px] font-bold text-slate-400">Fr</span>
              <span class="text-[10.5px] font-bold text-slate-400">Sa</span>
              <span class="text-[10.5px] font-bold text-slate-400">Su</span>
            </div>
            <div id="cal-days-grid" class="grid grid-cols-7 gap-1 text-center"></div>
            <div class="flex items-center justify-between mt-3 pt-2.5 border-t border-slate-100">
              <button type="button" onclick="selectCalendarDate('<?= htmlspecialchars($today_ist) ?>')" class="px-3 py-1.5 rounded-lg bg-teal-50 hover:bg-[#00bfa5] text-[#00bfa5] hover:text-white text-xs font-bold transition-all cursor-pointer">Today</button>
              <button type="button" onclick="closeDateCalendar()" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold transition-all cursor-pointer">Close</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── TREND GRAPH CARD (Historical Telemetry & Diurnal Baselines) ── -->
  <div class="trend-graph-card p-4 sm:p-6 mb-4">
    <!-- Top Header: Title & Pill Controls -->
    <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
      <div>
        <h2 id="trend-graph-title" class="text-base sm:text-lg font-bold text-white m-0 tracking-tight flex items-center gap-2">
          <i data-lucide="trending-up" class="w-4 h-4 text-[#00bfa5]" id="trend-title-icon"></i>
          <span id="trend-graph-title-text"><?= $req_subtab === 'weather' ? 'Soil Telemetry Trend Graph' : 'Air Quality Trend Graph' ?></span>
        </h2>
        <p id="trend-graph-subtitle" class="m-0 text-slate-400 text-xs font-medium mt-1">
          <?= $req_subtab === 'weather' ? 'Hourly soil subsurface telemetry (Temperature, Moisture, pH, EC)' : 'Hourly pollutant concentration telemetry and measured trends' ?>
        </p>
      </div>

      <div class="flex items-center gap-2.5 flex-wrap">
        <!-- Chart Type Switcher (Bar / Graph) -->
        <div class="inline-flex items-center rounded-full p-0.5 border border-white/15 bg-slate-900/70" id="chart-type-switcher" role="group" aria-label="Chart Type">
          <button type="button" id="chart-type-bar-btn" onclick="setChartType('bar')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold transition-all cursor-pointer text-slate-300 hover:text-white">
            <i data-lucide="bar-chart-2" class="w-3.5 h-3.5"></i>
            <span>Bar</span>
          </button>
          <button type="button" id="chart-type-line-btn" onclick="setChartType('line')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold transition-all cursor-pointer text-slate-300 hover:text-white">
            <i data-lucide="trending-up" class="w-3.5 h-3.5"></i>
            <span>Graph</span>
          </button>
        </div>

        <!-- Parameter Selector Dropdown Pill Button -->
        <div class="relative inline-flex items-center" id="trend-param-container">
          <button type="button" id="trend-param-btn" class="trend-pill-btn" onclick="toggleTrendParamMenu()">
            <span id="trend-param-icon"><?= $req_subtab === 'weather' ? ($req_param === 'soil_humidity' ? '💧' : ($req_param === 'soil_ph' ? '🧪' : ($req_param === 'soil_conductivity' ? '⚡' : '🌱'))) : ($req_param === 'pm25' ? '🌁' : ($req_param === 'pm10' ? '💨' : ($req_param === 'co' ? '🧪' : ($req_param === 'no2' ? '🚗' : ($req_param === 'o3' ? '☀️' : '🌿'))))) ?></span>
            <span id="trend-param-label"><?= $req_subtab === 'weather' ? ($req_param === 'soil_humidity' ? 'Soil Humidity' : ($req_param === 'soil_ph' ? 'Soil pH' : ($req_param === 'soil_conductivity' ? 'Soil Conductivity' : 'Soil Temperature'))) : ($req_param === 'pm25' ? 'PM2.5' : ($req_param === 'pm10' ? 'PM10' : ($req_param === 'co' ? 'CO' : ($req_param === 'no2' ? 'NO₂' : ($req_param === 'o3' ? 'O₃' : 'CPCB AQI'))))) ?></span>
            <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400"></i>
          </button>

          <!-- Floating Parameter Checklist Menu -->
          <div id="trend-param-menu" class="trend-dropdown-menu">
            <!-- Soil Items for Node 2 -->
            <div id="trend-menu-soil" style="display: <?= $req_subtab === 'soil' ? 'block' : 'none' ?>;">
              <div class="trend-dropdown-item <?= in_array($req_param, ['soil_moisture', 'soil_humidity']) ? 'active' : '' ?>" data-param="soil_moisture" onclick="selectTrendParam('soil_moisture', '💧', 'Soil Moisture', '%')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">💧</span>
                  <span>Soil Moisture</span>
                </div>
                <div class="trend-check-box"><?= in_array($req_param, ['soil_moisture', 'soil_humidity']) ? '✓' : '' ?></div>
              </div>
              <div class="trend-dropdown-item <?= $req_param === 'soil_temperature' ? 'active' : '' ?>" data-param="soil_temperature" onclick="selectTrendParam('soil_temperature', '🌱', 'Soil Temperature', '°C')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">🌱</span>
                  <span>Soil Temperature</span>
                </div>
                <div class="trend-check-box"><?= $req_param === 'soil_temperature' ? '✓' : '' ?></div>
              </div>
              <div class="trend-dropdown-item <?= $req_param === 'soil_ph' ? 'active' : '' ?>" data-param="soil_ph" onclick="selectTrendParam('soil_ph', '🧪', 'Soil pH', 'pH')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">🧪</span>
                  <span>Soil pH</span>
                </div>
                <div class="trend-check-box"><?= $req_param === 'soil_ph' ? '✓' : '' ?></div>
              </div>
              <div class="trend-dropdown-item <?= $req_param === 'soil_conductivity' ? 'active' : '' ?>" data-param="soil_conductivity" onclick="selectTrendParam('soil_conductivity', '⚡', 'Soil Conductivity', 'µS/cm')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">⚡</span>
                  <span>Soil Conductivity</span>
                </div>
                <div class="trend-check-box"><?= $req_param === 'soil_conductivity' ? '✓' : '' ?></div>
              </div>
            </div>

            <!-- Weather Items for Node 2 -->
            <div id="trend-menu-weather" style="display: <?= $req_subtab === 'weather' ? 'block' : 'none' ?>;">
              <div class="trend-dropdown-item <?= $req_param === 'temperature' ? 'active' : '' ?>" data-param="temperature" onclick="selectTrendParam('temperature', '🌡️', 'Temperature', '°C')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">🌡️</span>
                  <span>Temperature</span>
                </div>
                <div class="trend-check-box"><?= $req_param === 'temperature' ? '✓' : '' ?></div>
              </div>
              <div class="trend-dropdown-item <?= $req_param === 'humidity' ? 'active' : '' ?>" data-param="humidity" onclick="selectTrendParam('humidity', '💧', 'Humidity', '%')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">💧</span>
                  <span>Humidity</span>
                </div>
                <div class="trend-check-box"><?= $req_param === 'humidity' ? '✓' : '' ?></div>
              </div>
              <div class="trend-dropdown-item <?= $req_param === 'wind_speed' ? 'active' : '' ?>" data-param="wind_speed" onclick="selectTrendParam('wind_speed', '💨', 'Wind Speed', 'km/h')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">💨</span>
                  <span>Wind Speed</span>
                </div>
                <div class="trend-check-box"><?= $req_param === 'wind_speed' ? '✓' : '' ?></div>
              </div>
              <div class="trend-dropdown-item <?= $req_param === 'wind_gust' ? 'active' : '' ?>" data-param="wind_gust" onclick="selectTrendParam('wind_gust', '⚡', 'Wind Gust', 'm/s')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">⚡</span>
                  <span>Wind Gust</span>
                </div>
                <div class="trend-check-box"><?= $req_param === 'wind_gust' ? '✓' : '' ?></div>
              </div>
              <div class="trend-dropdown-item <?= $req_param === 'rain_gauge' ? 'active' : '' ?>" data-param="rain_gauge" onclick="selectTrendParam('rain_gauge', '🌧️', 'Rain Gauge', 'mm')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">🌧️</span>
                  <span>Rain Gauge</span>
                </div>
                <div class="trend-check-box"><?= $req_param === 'rain_gauge' ? '✓' : '' ?></div>
              </div>
              <div class="trend-dropdown-item <?= $req_param === 'pressure' ? 'active' : '' ?>" data-param="pressure" onclick="selectTrendParam('pressure', '⏱️', 'Pressure', 'mb')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">⏱️</span>
                  <span>Pressure</span>
                </div>
                <div class="trend-check-box"><?= $req_param === 'pressure' ? '✓' : '' ?></div>
              </div>
              <div class="trend-dropdown-item <?= $req_param === 'uv_index' ? 'active' : '' ?>" data-param="uv_index" onclick="selectTrendParam('uv_index', '☀️', 'UV Index', '')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">☀️</span>
                  <span>UV Index</span>
                </div>
                <div class="trend-check-box"><?= $req_param === 'uv_index' ? '✓' : '' ?></div>
              </div>
              <div class="trend-dropdown-item <?= $req_param === 'solar_radiation' ? 'active' : '' ?>" data-param="solar_radiation" onclick="selectTrendParam('solar_radiation', '☀️', 'Solar Radiation', 'W/m²')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">☀️</span>
                  <span>Solar Radiation</span>
                </div>
                <div class="trend-check-box"><?= $req_param === 'solar_radiation' ? '✓' : '' ?></div>
              </div>
            </div>

            <!-- AQI Items for Node 2 -->
            <div id="trend-menu-aqi" style="display: <?= $req_subtab === 'aqi' ? 'block' : 'none' ?>;">
              <div class="trend-dropdown-item <?= $req_param === 'cpcb_aqi' ? 'active' : '' ?>" data-param="cpcb_aqi" onclick="selectTrendParam('cpcb_aqi', '🌿', 'CPCB AQI', '')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">🌿</span>
                  <span>CPCB AQI</span>
                </div>
                <div class="trend-check-box"><?= $req_param === 'cpcb_aqi' ? '✓' : '' ?></div>
              </div>
              <div class="trend-dropdown-item <?= $req_param === 'pm25' ? 'active' : '' ?>" data-param="pm25" onclick="selectTrendParam('pm25', '🌁', 'PM2.5', 'µg/m³')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">🌁</span>
                  <span>PM2.5</span>
                </div>
                <div class="trend-check-box"><?= $req_param === 'pm25' ? '✓' : '' ?></div>
              </div>
              <div class="trend-dropdown-item <?= $req_param === 'pm10' ? 'active' : '' ?>" data-param="pm10" onclick="selectTrendParam('pm10', '💨', 'PM10', 'µg/m³')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">💨</span>
                  <span>PM10</span>
                </div>
                <div class="trend-check-box"><?= $req_param === 'pm10' ? '✓' : '' ?></div>
              </div>
              <div class="trend-dropdown-item <?= $req_param === 'co' ? 'active' : '' ?>" data-param="co" onclick="selectTrendParam('co', '🧪', 'CO', 'mg/m³')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">🧪</span>
                  <span>CO</span>
                </div>
                <div class="trend-check-box"><?= $req_param === 'co' ? '✓' : '' ?></div>
              </div>
              <div class="trend-dropdown-item <?= $req_param === 'no2' ? 'active' : '' ?>" data-param="no2" onclick="selectTrendParam('no2', '🚗', 'NO₂', 'µg/m³')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">🚗</span>
                  <span>NO₂</span>
                </div>
                <div class="trend-check-box"><?= $req_param === 'no2' ? '✓' : '' ?></div>
              </div>
              <div class="trend-dropdown-item <?= $req_param === 'o3' ? 'active' : '' ?>" data-param="o3" onclick="selectTrendParam('o3', '☀️', 'O₃', 'µg/m³')">
                <div class="flex items-center gap-2.5">
                  <span class="text-base">☀️</span>
                  <span>O₃</span>
                </div>
                <div class="trend-check-box"><?= $req_param === 'o3' ? '✓' : '' ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Chart Canvas Container -->
    <div style="width: 100%; height: 320px; position: relative;">
      <canvas id="historical-chart" style="transition: opacity 0.2s ease-in-out;"></canvas>
      <!-- Chart Loading Indicator -->
      <div id="chart-loading" style="display: none; position: absolute; inset: 0; flex-direction: column; align-items: center; justify-content: center; background: rgba(22, 28, 40, 0.85); gap: 10px; color: #00bfa5; z-index: 4;">
        <div style="width: 28px; height: 28px; border: 3px solid #00bfa5; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
        <span style="font-size: 12px; font-weight: 600; color: #94a3b8;">Loading telemetry data...</span>
      </div>
      <div id="chart-empty" style="display: none; position: absolute; inset: 0; flex-direction: column; align-items: center; justify-content: center; background: rgba(22, 28, 40, 0.85); gap: 8px; color: #94a3b8; z-index: 5;">
        <i data-lucide="info" style="width: 24px; height: 24px; color: #64748b;"></i>
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
      <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; min-width: 980px;">
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
    let param = tab === 'aqi' ? 'cpcb_aqi' : 'soil_temperature';
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
var _initState = getHistoricalInitialState();
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
var clientRangeCache = window.__historicalCache = window.__historicalCache || {};
var pendingRangeFetches = window.__pendingHistFetches = window.__pendingHistFetches || {};

function getCacheKey(date, days) {
    const activeNode = (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));
    return `${date}_${days}_n${activeNode}`;
}

function syncCustomRangeUI(days) {
    const lbl = document.getElementById('range-dropdown-label');
    if (lbl) lbl.innerText = Number(days) === 1 ? '24h' : `${Number(days)} Days`;
    document.querySelectorAll('[data-range-opt]').forEach(btn => {
        const isAct = Number(btn.getAttribute('data-range-opt')) === Number(days);
        btn.className = `w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs sm:text-[13px] font-bold transition-all cursor-pointer ${isAct ? 'bg-teal-50 text-[#00bfa5]' : 'text-slate-700 hover:bg-slate-50'}`;
        const chk = btn.querySelector('.range-opt-check');
        if (chk) chk.innerText = isAct ? '✓' : '';
    });
}

function toggleRangeMenu(e) {
    if (e) e.stopPropagation();
    closeDateCalendar();
    const menu = document.getElementById('range-dropdown-menu');
    const chev = document.getElementById('range-dropdown-chevron');
    if (!menu) return;
    const isOpen = menu.style.display === 'block';
    menu.style.display = isOpen ? 'none' : 'block';
    if (chev) chev.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
}

function selectRangeOption(days) {
    const menu = document.getElementById('range-dropdown-menu');
    const chev = document.getElementById('range-dropdown-chevron');
    if (menu) menu.style.display = 'none';
    if (chev) chev.style.transform = 'rotate(0deg)';
    setRangeDays(days);
}

var calViewYear = null;
var calViewMonth = null;

function syncCustomDateUI(ymd) {
    const lbl = document.getElementById('date-picker-label');
    if (lbl && ymd) {
        const [y, m, d] = parseDateParts(ymd);
        const dt = new Date(y, m - 1, d);
        lbl.innerText = dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }
}

function closeDateCalendar() {
    const pop = document.getElementById('date-calendar-popover');
    const chev = document.getElementById('date-picker-chevron');
    if (pop) pop.style.display = 'none';
    if (chev) chev.style.transform = 'rotate(0deg)';
}

function toggleDateCalendar(e) {
    if (e) e.stopPropagation();
    const rMenu = document.getElementById('range-dropdown-menu');
    if (rMenu) rMenu.style.display = 'none';
    const pop = document.getElementById('date-calendar-popover');
    const chev = document.getElementById('date-picker-chevron');
    if (!pop) return;
    const isOpen = pop.style.display === 'block';
    if (!isOpen) {
        const dp = document.getElementById('history-date-picker');
        const curYmd = normalizeDateToYmd(dp && dp.value ? dp.value : getLocalDateStr());
        const [y, m] = parseDateParts(curYmd);
        calViewYear = y;
        calViewMonth = m - 1;
        renderCustomCalendar();
        pop.style.display = 'block';
        if (chev) chev.style.transform = 'rotate(180deg)';
        // Prefetch 30d data on calendar open for instant date selection
        const k30 = getCacheKey('<?= $today_ist ?>', 30);
        if (!clientRangeCache[k30] && !pendingRangeFetches[k30]) {
            fetchRangeData('<?= $today_ist ?>', 30).catch(() => {});
        }
    } else {
        closeDateCalendar();
    }
}

function shiftCalendarMonth(delta) {
    if (calViewYear === null || calViewMonth === null) return;
    const next = new Date(calViewYear, calViewMonth + delta, 1);
    const todayStr = getLocalDateStr();
    const [ty, tm] = parseDateParts(todayStr);
    if (next.getFullYear() > ty || (next.getFullYear() === ty && next.getMonth() > tm - 1)) return;
    calViewYear = next.getFullYear();
    calViewMonth = next.getMonth();
    renderCustomCalendar();
}

function renderCustomCalendar() {
    const titleEl = document.getElementById('cal-month-title');
    const gridEl = document.getElementById('cal-days-grid');
    const nextBtn = document.getElementById('cal-next-btn');
    if (!gridEl) return;
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    if (titleEl) titleEl.innerText = `${monthNames[calViewMonth]} ${calViewYear}`;

    const todayStr = getLocalDateStr();
    const [ty, tm] = parseDateParts(todayStr);
    if (nextBtn) {
        const atMax = (calViewYear > ty || (calViewYear === ty && calViewMonth >= tm - 1));
        nextBtn.disabled = atMax;
        nextBtn.style.opacity = atMax ? '0.35' : '1';
        nextBtn.style.pointerEvents = atMax ? 'none' : 'auto';
    }

    const dp = document.getElementById('history-date-picker');
    const selectedYmd = normalizeDateToYmd(dp && dp.value ? dp.value : todayStr);
    const firstDay = new Date(calViewYear, calViewMonth, 1).getDay();
    const offset = (firstDay + 6) % 7; // Monday-first
    const daysInMonth = new Date(calViewYear, calViewMonth + 1, 0).getDate();

    let html = '';
    for (let i = 0; i < offset; i++) {
        html += '<span></span>';
    }
    for (let day = 1; day <= daysInMonth; day++) {
        const ymd = `${calViewYear}-${String(calViewMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const isFuture = ymd > todayStr;
        const isSelected = ymd === selectedYmd;
        const isToday = ymd === todayStr;
        let cls = 'h-8 w-8 mx-auto rounded-xl text-xs font-bold flex items-center justify-center transition-all ';
        if (isFuture) {
            cls += 'text-slate-300 cursor-not-allowed';
            html += `<button type="button" disabled class="${cls}">${day}</button>`;
        } else if (isSelected) {
            cls += 'bg-[#00bfa5] text-white shadow-[0_4px_12px_rgba(0,191,165,0.35)] cursor-pointer';
            html += `<button type="button" data-cal-date="${ymd}" onclick="selectCalendarDate('${ymd}')" class="${cls}">${day}</button>`;
        } else if (isToday) {
            cls += 'border border-[#00bfa5] text-[#00bfa5] bg-teal-50/50 hover:bg-teal-50 cursor-pointer';
            html += `<button type="button" data-cal-date="${ymd}" onclick="selectCalendarDate('${ymd}')" class="${cls}">${day}</button>`;
        } else {
            cls += 'text-slate-700 hover:bg-teal-50 hover:text-[#00bfa5] cursor-pointer';
            html += `<button type="button" data-cal-date="${ymd}" onclick="selectCalendarDate('${ymd}')" class="${cls}">${day}</button>`;
        }
    }
    gridEl.innerHTML = html;
}

function selectCalendarDate(ymd) {
    closeDateCalendar();
    const dp = document.getElementById('history-date-picker');
    if (dp) dp.value = ymd;
    syncCustomDateUI(ymd);
    persistHistoricalState();
    fetchAllHistory(ymd);
}

if (!window.__histPopoverDocListenerAttached) {
    window.__histPopoverDocListenerAttached = true;
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#custom-range-dropdown')) {
            const rm = document.getElementById('range-dropdown-menu');
            const rc = document.getElementById('range-dropdown-chevron');
            if (rm) rm.style.display = 'none';
            if (rc) rc.style.transform = 'rotate(0deg)';
        }
        if (!e.target.closest('#custom-date-picker')) {
            closeDateCalendar();
        }
    });
}

function setRangeDays(days, skipFetch = false) {
    currentRangeDays = Number(days) || 1;
    
    // Sync Range Dropdown
    const rangeSelect = document.getElementById('range-select');
    if (rangeSelect && Number(rangeSelect.value) !== currentRangeDays) {
        rangeSelect.value = currentRangeDays;
    }
    syncCustomRangeUI(currentRangeDays);

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

    persistHistoricalState();
    if (!skipFetch) fetchAllHistory();
}

var PARAM_COLORS = {
    cpcb_aqi: '#00bfa5', pm25: '#0284c7', pm10: '#6366f1',
    co: '#16a34a', no2: '#9333ea', o3: '#d97706',
    temperature: '#00bfa5', humidity: '#00bfa5',
    wind_speed: '#4f46e5', wind_gust: '#8b5cf6',
    wind_direction: '#0284c7', rain_gauge: '#0891b2',
    pressure: '#ec4899', uv_index: '#f43f5e', solar_radiation: '#f59e0b',
    soil_temperature: '#d97706', soil_humidity: '#0284c7', soil_ph: '#10b981'
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

var calculateCpcbSubIndex = function(param, val) {
    if (val == null || isNaN(val)) return 0;
    const v = Number(val);
    const breakpoints = {
        pm25: [[0,30,0,50],[30,60,51,100],[60,90,101,200],[90,120,201,300],[120,250,301,400],[250,500,401,500]],
        pm10: [[0,50,0,50],[50,100,51,100],[100,250,101,200],[250,350,201,300],[350,430,301,400],[430,600,401,500]],
        co:   [[0,1,0,50],[1,2,51,100],[2,10,101,200],[10,17,201,300],[17,34,301,400],[34,50,401,500]],
        no2:  [[0,40,0,50],[40,80,51,100],[80,180,101,200],[180,280,201,300],[280,400,301,400],[400,500,401,500]],
        o3:   [[0,50,0,50],[50,100,51,100],[100,168,101,200],[168,208,201,300],[208,748,301,400],[748,1000,401,500]]
    };
    const tiers = breakpoints[param];
    if (!tiers) return 0;
    for (const [clo, chi, ilo, ihi] of tiers) {
        if (v <= chi) return Math.round(((ihi - ilo) / (chi - clo)) * (v - clo) + ilo);
    }
    const [clo, chi, ilo, ihi] = tiers[tiers.length - 1];
    return Math.round(((ihi - ilo) / (chi - clo)) * (v - clo) + ilo);
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
    wind_speed: 'km/h', wind_gust: 'm/s', wind_direction: '°', rain_gauge: 'mm',
    pressure: 'mb', uv_index: '', solar_radiation: 'W/m²',
    soil_temperature: '°C', soil_humidity: '%', soil_moisture: '%', soil_ph: 'pH', soil_conductivity: 'µS/cm'
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
    if (param === 'pressure') return 'Pressure';
    if (param === 'uv_index') return 'UV Index';
    if (param === 'solar_radiation') return 'Solar Radiation';
    if (param === 'soil_temperature') return 'Soil Temperature';
    if (param === 'soil_humidity') return 'Soil Humidity';
    if (param === 'soil_ph') return 'Soil pH';
    return param ? param.toUpperCase() : '';
}

var getCompassDir = function(val) {
    if (val == null) return '--';
    const deg = Math.round(Number(val) % 360);
    if (isNaN(deg)) return val;
    const dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
    return dirs[Math.round(deg / 22.5) % 16];
};

var parseDateParts = function(dateStr) {
    if (!dateStr) {
        const now = new Date();
        return [now.getFullYear(), now.getMonth() + 1, now.getDate()];
    }
    const clean = String(dateStr).trim();
    if (/^\d{4}-\d{1,2}-\d{1,2}$/.test(clean)) {
        const [y, m, d] = clean.split('-').map(Number);
        return [y, m, d];
    }
    if (/^\d{1,2}-\d{1,2}-\d{4}$/.test(clean)) {
        const [d, m, y] = clean.split('-').map(Number);
        return [y, m, d];
    }
    if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(clean)) {
        const [d, m, y] = clean.split('/').map(Number);
        return [y, m, d];
    }
    if (/^\d{4}\/\d{1,2}\/\d{1,2}$/.test(clean)) {
        const [y, m, d] = clean.split('/').map(Number);
        return [y, m, d];
    }
    const dt = new Date(clean);
    if (!isNaN(dt.getTime())) {
        return [dt.getFullYear(), dt.getMonth() + 1, dt.getDate()];
    }
    const now = new Date();
    return [now.getFullYear(), now.getMonth() + 1, now.getDate()];
};
window.parseDateParts = parseDateParts;

var normalizeDateToYmd = function(dateStr) {
    const [y, m, d] = parseDateParts(dateStr);
    return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
};
window.normalizeDateToYmd = normalizeDateToYmd;

var getLocalDateStr = function(d = new Date()) {
    const yr = d.getFullYear();
    const mo = String(d.getMonth() + 1).padStart(2, '0');
    const da = String(d.getDate()).padStart(2, '0');
    return `${yr}-${mo}-${da}`;
};

async function fetchRangeData(queryDate, days, force = false) {
    const normDate = normalizeDateToYmd(queryDate || getLocalDateStr());
    const key = getCacheKey(normDate, days);
    const isToday = (normDate === getLocalDateStr() && Number(days) === 1);

    if (!force && clientRangeCache[key]) {
        const age = Date.now() - (clientRangeCache[key].fetchedAt || 0);
        // For today's view, cache expires after 15 seconds so new hourly readings stream in
        if (!isToday || age < 15000) {
            return clientRangeCache[key];
        }
    }
    if (!force && pendingRangeFetches[key]) {
        return pendingRangeFetches[key];
    }
    const fetchPromise = (async () => {
        try {
            let payload;
            const activeNode = (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));
            if (typeof window.fetchHistoryBulk === 'function') {
                payload = await window.fetchHistoryBulk(normDate, days, force, activeNode);
            } else {
                const response = await fetch(`api/telemetry.php?action=history_bulk&node=${activeNode}&date=${normDate}&days=${days}&_t=${Date.now()}`);
                const data = await response.json();
                payload = {
                    aqi: data.aqi || [],
                    weather: data.weather || [],
                    fetchedAt: Date.now()
                };
            }
            if (payload && ((payload.aqi && payload.aqi.length > 0) || (payload.weather && payload.weather.length > 0))) {
                payload.fetchedAt = Date.now();
                clientRangeCache[key] = payload;
                try {
                    if (typeof window.setLocalCache === 'function') {
                        window.setLocalCache('smartairnet_hist_' + key, payload);
                    }
                } catch(e) {}
            }
            return payload || { aqi: [], weather: [], fetchedAt: Date.now() };
        } catch(e) {
            console.error(`Failed to fetch history for ${key}:`, e);
            return clientRangeCache[key] || { aqi: [], weather: [], fetchedAt: Date.now() };
        } finally {
            delete pendingRangeFetches[key];
        }
    })();
    pendingRangeFetches[key] = fetchPromise;
    return fetchPromise;
}

function prefetchOtherRanges(queryDate) {
    if (!queryDate) return;
    const normDate = normalizeDateToYmd(queryDate);
    // Only prefetch 3-day range after 2.5 seconds of user idle time, avoiding connection hogging
    const key3 = getCacheKey(normDate, 3);
    if (currentRangeDays === 1 && !clientRangeCache[key3] && !pendingRangeFetches[key3]) {
        setTimeout(() => {
            if (currentRangeDays === 1 && !clientRangeCache[key3] && !pendingRangeFetches[key3]) {
                fetchRangeData(normDate, 3).catch(() => {});
            }
        }, 2500);
    }
}

var latestHistoryReqId = 0;

async function fetchAllHistory(forcedDate = null, skipCache = false, silent = false) {
    const reqId = ++latestHistoryReqId;
    try {
        const datePicker = document.getElementById('history-date-picker');
        let rawDate = forcedDate || (datePicker ? datePicker.value : null);
        let queryDate = normalizeDateToYmd(rawDate || getLocalDateStr());
        
        if (datePicker && datePicker.value !== queryDate) {
            datePicker.value = queryDate;
        }
        syncCustomDateUI(queryDate);
        if (datePicker && !datePicker.max) {
            datePicker.max = getLocalDateStr();
        }

        const days = currentRangeDays || 1;
        const key = getCacheKey(queryDate, days);
        const isToday = (queryDate === getLocalDateStr() && days === 1);

        // Instant 0ms pre-hydration from initial server payload
        if (!skipCache && window.INITIAL_HISTORY && queryDate === '<?= $today_ist ?>' && days === 1) {
            clientRangeCache[key] = window.INITIAL_HISTORY;
            clientRangeCache[key].fetchedAt = Date.now();
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
            // Silently fetch fresh data in background to ensure all latest hours are rendered
            fetchRangeData(queryDate, days, true).then(freshData => {
                if (reqId !== latestHistoryReqId || Number(currentRangeDays) !== Number(days)) return;
                if (freshData && ((freshData.aqi && freshData.aqi.length > 0) || (freshData.weather && freshData.weather.length > 0))) {
                    const prevLenAqi = historicalDataAqi.length;
                    const prevLenW = historicalDataWeather.length;
                    const newLenAqi = (freshData.aqi || []).length;
                    const newLenW = (freshData.weather || []).length;
                    if (prevLenAqi !== newLenAqi || prevLenW !== newLenW) {
                        historicalDataAqi = freshData.aqi || [];
                        historicalDataWeather = freshData.weather || [];
                        renderUI();
                    }
                }
            }).catch(() => {});
            return;
        }

        // Instant render from client memory or localStorage cache (0ms latency!)
        let cached = (!skipCache && clientRangeCache[key]) ? clientRangeCache[key] : null;
        if (!cached && !skipCache && typeof window.getLocalCache === 'function') {
            const stored = window.getLocalCache('smartairnet_hist_' + key);
            if (stored && ((stored.aqi && stored.aqi.length > 0) || (stored.weather && stored.weather.length > 0))) {
                cached = stored;
                clientRangeCache[key] = stored;
            }
        }

        if (cached && !skipCache) {
            historicalDataAqi = cached.aqi || [];
            historicalDataWeather = cached.weather || [];
            renderUI();
            prefetchOtherRanges(queryDate);

            // Silently revalidate in background if records are for today or older than 30s
            const ageMs = Date.now() - (cached.fetchedAt || 0);
            if (isToday || ageMs > 30000) {
                fetchRangeData(queryDate, days, true).then(freshData => {
                    if (reqId !== latestHistoryReqId || Number(currentRangeDays) !== Number(days)) return;
                    if (freshData && ((freshData.aqi && freshData.aqi.length > 0) || (freshData.weather && freshData.weather.length > 0))) {
                        const prevLenAqi = historicalDataAqi.length;
                        const prevLenW = historicalDataWeather.length;
                        const newLenAqi = (freshData.aqi || []).length;
                        const newLenW = (freshData.weather || []).length;
                        if (prevLenAqi !== newLenAqi || prevLenW !== newLenW) {
                            historicalDataAqi = freshData.aqi || [];
                            historicalDataWeather = freshData.weather || [];
                            renderUI();
                        }
                    }
                }).catch(() => {});
            }
            return;
        }

        if (!cached && !skipCache && days === 1) {
            // Instant 0ms fallback: if a cached multi-day dataset already covers queryDate, render immediately while fetching fresh 1-day slice
            const activeNode = (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));
            const multiKeys = Object.keys(clientRangeCache).filter(k => k.endsWith(`_n${activeNode}`) && !k.includes(`_1_n${activeNode}`));
            for (const mk of multiKeys) {
                const mc = clientRangeCache[mk];
                if (mc && ((mc.aqi && mc.aqi.some(r => (r.timestamp || '').startsWith(queryDate))) || (mc.weather && mc.weather.some(r => (r.timestamp || '').startsWith(queryDate))))) {
                    historicalDataAqi = mc.aqi || [];
                    historicalDataWeather = mc.weather || [];
                    renderUI();
                    fetchRangeData(queryDate, 1, true).then(freshData => {
                        if (reqId !== latestHistoryReqId || Number(currentRangeDays) !== 1) return;
                        if (freshData && ((freshData.aqi && freshData.aqi.length > 0) || (freshData.weather && freshData.weather.length > 0))) {
                            historicalDataAqi = freshData.aqi || [];
                            historicalDataWeather = freshData.weather || [];
                            renderUI();
                        }
                    }).catch(() => {});
                    return;
                }
            }
        }

        const chartCanvas = document.getElementById('historical-chart');
        const chartLoading = document.getElementById('chart-loading');
        const chartEmpty = document.getElementById('chart-empty');
        if (!silent) {
            if (chartCanvas) chartCanvas.style.opacity = '0.7';
            if (chartLoading) chartLoading.style.display = 'flex';
            if (chartEmpty) chartEmpty.style.display = 'none';
        }

        const data = await fetchRangeData(queryDate, days, skipCache);
        if (reqId !== latestHistoryReqId || Number(currentRangeDays) !== Number(days)) return;
        
        const newAqi = data.aqi || [];
        const newWeather = data.weather || [];
        const changed = !silent
            || newAqi.length !== (historicalDataAqi || []).length
            || newWeather.length !== (historicalDataWeather || []).length
            || (newAqi[0]?.timestamp !== historicalDataAqi?.[0]?.timestamp)
            || (newWeather[0]?.timestamp !== historicalDataWeather?.[0]?.timestamp);

        historicalDataAqi = newAqi;
        historicalDataWeather = newWeather;
        
        if (chartLoading) chartLoading.style.display = 'none';
        if (chartCanvas) chartCanvas.style.opacity = '1';

        if (changed) {
            renderUI();
        }
        if (!silent) {
            prefetchOtherRanges(queryDate);
        }
    } catch(e) {
        console.error("Error fetching historical data", e);
        const chartCanvas = document.getElementById('historical-chart');
        const chartLoading = document.getElementById('chart-loading');
        if (chartLoading) chartLoading.style.display = 'none';
        if (chartCanvas) chartCanvas.style.opacity = '1';
    }
}


function toggleTrendParamMenu() {
    const menu = document.getElementById('trend-param-menu');
    if (!menu) return;
    const isHidden = (menu.style.display === 'none' || !menu.style.display);
    menu.style.display = isHidden ? 'block' : 'none';
}

function updateTrendLegend(paramKey, label) {
    const legText = document.getElementById('leg-text');
    const legSwatch = document.getElementById('leg-swatch');
    const colorMap = {
        'soil_temperature': '#f59e0b',
        'soil_humidity': '#0284c7',
        'soil_ph': '#10b981',
        'soil_conductivity': '#8b5cf6',
        'cpcb_aqi': '#00bfa5',
        'pm25': '#00bfa5',
        'pm10': '#6366f1',
        'co': '#10b981',
        'no2': '#a855f7',
        'o3': '#f59e0b'
    };
    const c = colorMap[paramKey] || '#00bfa5';
    if (legText) legText.innerText = `${label} (Recorded Telemetry)`;
    if (legSwatch) legSwatch.style.background = c;
}

function selectTrendParam(paramKey, icon, label, unitLabel) {
    if (window.triggerHaptic) window.triggerHaptic('selection');
    currentParam = paramKey;

    const iconEl = document.getElementById('trend-param-icon');
    const labelEl = document.getElementById('trend-param-label');
    if (iconEl) iconEl.innerText = icon;
    if (labelEl) labelEl.innerText = label;

    const menu = document.getElementById('trend-param-menu');
    if (menu) {
        const items = menu.querySelectorAll('.trend-dropdown-item');
        items.forEach(it => {
            const isMatch = it.getAttribute('data-param') === paramKey;
            it.classList.toggle('active', isMatch);
            const chk = it.querySelector('.trend-check-box');
            if (chk) chk.innerText = isMatch ? '✓' : '';
        });
        menu.style.display = 'none';
    }

    updateTrendLegend(paramKey, label);
    persistHistoricalState();
    renderUI();
}

document.addEventListener('click', function(e) {
    const container = document.getElementById('trend-param-container');
    const menu = document.getElementById('trend-param-menu');
    if (menu && container && !container.contains(e.target)) {
        menu.style.display = 'none';
    }
});

function switchHistTab(tab, skipRender = false) {
    currentSubTab = tab;
    if (tab === 'aqi') {
        if (!['cpcb_aqi', 'pm25', 'pm10', 'co', 'no2', 'o3'].includes(currentParam)) {
            currentParam = 'cpcb_aqi';
        }
    } else if (tab === 'weather') {
        if (!['temperature', 'humidity', 'wind_speed', 'wind_gust', 'rain_gauge', 'pressure', 'uv_index', 'solar_radiation'].includes(currentParam)) {
            currentParam = 'temperature';
        }
    } else { // soil
        if (!['soil_moisture', 'soil_humidity', 'soil_temperature', 'soil_ph', 'soil_conductivity'].includes(currentParam)) {
            currentParam = 'soil_moisture';
        }
    }
    
    // Switch styles on subnav pills
    const tabAqi = document.getElementById('tab-aqi');
    const tabWeather = document.getElementById('tab-weather');
    const tabSoil = document.getElementById('tab-soil');
    const days = currentRangeDays || 1;

    const activeCls = 'node2-subtab-btn active';
    const inactiveCls = 'node2-subtab-btn';

    if (tabAqi) tabAqi.className = (tab === 'aqi') ? activeCls : inactiveCls;
    if (tabWeather) tabWeather.className = (tab === 'weather') ? activeCls : inactiveCls;
    if (tabSoil) tabSoil.className = (tab === 'soil') ? activeCls : inactiveCls;

    const pageHeading = document.getElementById('analytics-page-heading');
    const tTitle = document.getElementById('trend-graph-title-text');
    const tSub = document.getElementById('trend-graph-subtitle');
    const legText = document.getElementById('leg-text');
    const mWeather = document.getElementById('trend-menu-weather');
    const mAqi = document.getElementById('trend-menu-aqi');
    const mSoil = document.getElementById('trend-menu-soil');
    const tableTitle = document.getElementById('table-title');

    if (tab === 'aqi') {
        if (pageHeading) pageHeading.innerText = 'Historical Air Quality Telemetry';
        if (tTitle) tTitle.innerText = 'Air Quality Trend Graph';
        if (tSub) tSub.innerText = 'Hourly pollutant concentration telemetry and measured trends';
        if (legText) legText.innerText = 'AQI Telemetry (Recorded)';
        if (mAqi) mAqi.style.display = 'block';
        if (mWeather) mWeather.style.display = 'none';
        if (mSoil) mSoil.style.display = 'none';
        if (tableTitle) tableTitle.innerText = days === 1 ? 'AQI Historical Telemetry Table (12:00 AM – 12:00 AM)' : `AQI Historical Telemetry Table (${days} Days)`;
    } else if (tab === 'weather') {
        if (pageHeading) pageHeading.innerText = 'Historical Weather Telemetry';
        if (tTitle) tTitle.innerText = 'Weather Trend Graph';
        if (tSub) tSub.innerText = 'Hourly ambient meteorological telemetry and measured trends';
        if (legText) legText.innerText = 'Weather Telemetry (Recorded)';
        if (mAqi) mAqi.style.display = 'none';
        if (mWeather) mWeather.style.display = 'block';
        if (mSoil) mSoil.style.display = 'none';
        if (tableTitle) tableTitle.innerText = days === 1 ? 'Weather Historical Telemetry Table (12:00 AM – 12:00 AM)' : `Weather Historical Telemetry Table (${days} Days)`;
    } else {
        if (pageHeading) pageHeading.innerText = 'Historical Soil Telemetry';
        if (tTitle) tTitle.innerText = 'Soil Telemetry Trend Graph';
        if (tSub) tSub.innerText = 'Hourly soil subsurface telemetry (Moisture, Temperature, pH, EC)';
        if (legText) legText.innerText = 'Soil Telemetry (Recorded)';
        if (mAqi) mAqi.style.display = 'none';
        if (mWeather) mWeather.style.display = 'none';
        if (mSoil) mSoil.style.display = 'block';
        if (tableTitle) tableTitle.innerText = days === 1 ? 'Soil Historical Telemetry Table (12:00 AM – 12:00 AM)' : `Soil Historical Telemetry Table (${days} Days)`;
    }
    
    setParam(currentParam, skipRender);
    persistHistoricalState();
}

function syncChartTypeButtons(type) {
    const barBtn = document.getElementById('chart-type-bar-btn');
    const lineBtn = document.getElementById('chart-type-line-btn');
    const activeCls = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold transition-all cursor-pointer bg-[#00bfa5] text-white shadow-xs';
    const inactiveCls = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold transition-all cursor-pointer text-slate-300 hover:text-white';
    if (barBtn) barBtn.className = (type === 'bar') ? activeCls : inactiveCls;
    if (lineBtn) lineBtn.className = (type !== 'bar') ? activeCls : inactiveCls;
}

function setChartType(type, skipRender = false) {
    currentChartType = (type === 'bar') ? 'bar' : 'line';
    syncChartTypeButtons(currentChartType);
    persistHistoricalState();
    if (!skipRender) renderUI();
}

function setParam(param, skipRender = false) {
    currentParam = param;
    const paramMeta = {
        'soil_moisture': { icon: '💧', label: 'Soil Moisture', unit: '%', color: '#0284c7' },
        'soil_humidity': { icon: '💧', label: 'Soil Humidity', unit: '%', color: '#0284c7' },
        'soil_temperature': { icon: '🌱', label: 'Soil Temperature', unit: '°C', color: '#ea580c' },
        'soil_ph': { icon: '🧪', label: 'Soil pH', unit: 'pH', color: '#10b981' },
        'soil_conductivity': { icon: '⚡', label: 'Soil Conductivity', unit: 'µS/cm', color: '#8b5cf6' },
        'temperature': { icon: '🌡️', label: 'Temperature', unit: '°C', color: '#f97316' },
        'humidity': { icon: '💧', label: 'Humidity', unit: '%', color: '#0284c7' },
        'wind_speed': { icon: '💨', label: 'Wind Speed', unit: 'km/h', color: '#0d9488' },
        'wind_gust': { icon: '⚡', label: 'Wind Gust', unit: 'm/s', color: '#0891b2' },
        'rain_gauge': { icon: '🌧️', label: 'Rain Gauge', unit: 'mm', color: '#2563eb' },
        'pressure': { icon: '⏱️', label: 'Pressure', unit: 'mb', color: '#ec4899' },
        'uv_index': { icon: '☀️', label: 'UV Index', unit: '', color: '#f59e0b' },
        'solar_radiation': { icon: '☀️', label: 'Solar Radiation', unit: 'W/m²', color: '#f59e0b' },
        'cpcb_aqi': { icon: '🌿', label: 'CPCB AQI', unit: '', color: '#00bfa5' },
        'pm25': { icon: '🌁', label: 'PM2.5', unit: 'µg/m³', color: '#00bfa5' },
        'pm10': { icon: '💨', label: 'PM10', unit: 'µg/m³', color: '#6366f1' },
        'co': { icon: '🧪', label: 'CO', unit: 'mg/m³', color: '#10b981' },
        'no2': { icon: '🚗', label: 'NO₂', unit: 'µg/m³', color: '#a855f7' },
        'o3': { icon: '☀️', label: 'O₃', unit: 'µg/m³', color: '#f59e0b' }
    };
    const meta = paramMeta[param] || (currentSubTab === 'soil'
        ? { icon: '💧', label: 'Soil Moisture', unit: '%', color: '#0284c7' }
        : (currentSubTab === 'weather'
            ? { icon: '🌡️', label: 'Temperature', unit: '°C', color: '#f97316' }
            : { icon: '🌿', label: 'CPCB AQI', unit: '', color: '#00bfa5' }));

    const iconEl = document.getElementById('trend-param-icon');
    const labelEl = document.getElementById('trend-param-label');
    if (iconEl) iconEl.innerText = meta.icon;
    if (labelEl) labelEl.innerText = meta.label;

    const menu = document.getElementById('trend-param-menu');
    if (menu) {
        const items = menu.querySelectorAll('.trend-dropdown-item');
        items.forEach(it => {
            const itemParam = it.getAttribute('data-param');
            const isMatch = (itemParam === param) || (param === 'soil_moisture' && itemParam === 'soil_humidity');
            it.classList.toggle('active', isMatch);
            const chk = it.querySelector('.trend-check-box');
            if (chk) chk.innerText = isMatch ? '✓' : '';
        });
    }

    updateTrendLegend(param, meta.label);
    persistHistoricalState();
    if (!skipRender) renderUI();
}

function renderUI(skipDayFilter = false) {
    const datePicker = document.getElementById('history-date-picker');
    if (!datePicker) return;
    const dateInput = normalizeDateToYmd(datePicker.value || getLocalDateStr());
    if (!dateInput) return;

    const parseIsoLocal = (str) => {
        if (!str) return 0;
        const cleanStr = String(str).replace(/[Zz]|[+-]\d{2}(:\d{2})?$/, '');
        const parts = cleanStr.split('T');
        if (parts.length !== 2) return new Date(str).getTime();
        const [yr, mo, da] = parseDateParts(parts[0]);
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

        const [y, m, d] = parseDateParts(dateInput);
        let startWindow, endWindow;
        if (days === 1) {
            startWindow = new Date(y, m - 1, d, 0, 0, 0, 0).getTime();
            endWindow = new Date(y, m - 1, d, 23, 59, 59, 999).getTime();
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
                const normD = normalizeDateToYmd(parts[0]);
                hrKey = normD + ' ' + parts[1].slice(0, 2);
            } else {
                const dt = new Date(item.timestamp);
                const yr = dt.getFullYear();
                const mo = String(dt.getMonth() + 1).padStart(2, '0');
                const da = String(dt.getDate()).padStart(2, '0');
                const hr = String(dt.getHours()).padStart(2, '0');
                hrKey = `${yr}-${mo}-${da} ${hr}`;
            }
            hourlyMap.set(hrKey, item);
        });

        // Ensure every hour slot is populated across the selected range (0 to 23 for past days; 0 to current hour for today)
        const todayYmd = getLocalDateStr();
        const currentHour = new Date().getHours();
        
        for (let i = days - 1; i >= 0; i--) {
            const curDate = new Date(y, m - 1, d - i);
            const curYmd = `${curDate.getFullYear()}-${String(curDate.getMonth() + 1).padStart(2, '0')}-${String(curDate.getDate()).padStart(2, '0')}`;
            const isCurToday = (curYmd === todayYmd);
            const isFuture = (curYmd > todayYmd);
            if (isFuture) continue;
            
            const maxH = isCurToday ? Math.min(23, currentHour) : 23;
            for (let h = 0; h <= maxH; h++) {
                const hrKey = `${curYmd} ${String(h).padStart(2, '0')}`;
                if (!hourlyMap.has(hrKey)) {
                    hourlyMap.set(hrKey, {
                        timestamp: `${curYmd}T${String(h).padStart(2, '0')}:00:00`,
                        cpcb_aqi: null,
                        pm25: null,
                        pm10: null,
                        co: null,
                        no2: null,
                        o3: null,
                        temperature: null,
                        humidity: null,
                        pressure: null,
                        uv_index: null,
                        solar_radiation: null,
                        wind_speed: null,
                        wind_gust: null,
                        wind_direction: null,
                        rain_gauge: null,
                        isMissing: true
                    });
                }
            }
        }

        filteredDayData = Array.from(hourlyMap.values());
        filteredDayData.sort((a,b) => parseIsoLocal(a.timestamp) - parseIsoLocal(b.timestamp));
        
        const isAqi = currentSubTab === 'aqi';
        const isSoil = currentSubTab === 'soil';
        const loggedCount = filteredDayData.filter(d => !d.isMissing && (
            isAqi ? d.cpcb_aqi != null : (
                isSoil ? (d.soil_temperature != null || d.soil_humidity != null || d.soil_moisture != null) : (d.temperature != null || d.humidity != null || d.pressure != null || d.uv_index != null || d.solar_radiation != null)
            )
        )).length;
        
        const subtitleEl = document.getElementById('date-subtitle-desktop');
        if (subtitleEl) {
            subtitleEl.innerText = days === 1
                ? 'Hourly sensor progression telemetry (12:00 AM – 12:00 AM)'
                : `Multi-day telemetry trend analysis (${days} Days: ${rangeLabel})`;
        }
        
        const chartTitle = document.getElementById('chart-main-title');
        if (chartTitle) {
            const subLabel = isAqi ? 'Air Quality' : (isSoil ? 'Soil Telemetry' : 'Weather');
            chartTitle.innerText = days === 1
                ? `${subLabel} Hourly Trend Analysis (12:00 AM – 12:00 AM)`
                : `${subLabel} ${days}-Day Trend Analysis (${rangeLabel})`;
        }
        
        const chartSubTitle = document.getElementById('chart-sub-title');
        if (chartSubTitle) {
            chartSubTitle.innerText = `${days === 1 ? '24-Hour progression' : days + '-Day progression'}: ${rangeLabel}`;
        }
        
        const tableTitle = document.getElementById('table-title');
        if (tableTitle) {
            const subLabel = isAqi ? 'AQI' : (isSoil ? 'Soil' : 'Weather');
            tableTitle.innerText = days === 1
                ? `${subLabel} Historical Telemetry Table (12:00 AM – 12:00 AM)`
                : `${subLabel} Historical Telemetry Table (${days} Days: ${rangeLabel})`;
        }
        
        const tableSubtitle = document.getElementById('table-subtitle');
        if (tableSubtitle) {
            tableSubtitle.innerText = `${days === 1 ? '24 Hourly Telemetry Rows' : days + '-Day Telemetry Rows'} (${rangeLabel}) — ${loggedCount} Logged Readings`;
        }
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

            const elSoilTemp = document.getElementById('w-avg-soil-temp');
            if (elSoilTemp) elSoilTemp.innerText = '--';
            const elSoilHum = document.getElementById('w-avg-soil-hum');
            if (elSoilHum) elSoilHum.innerText = '--';
            const elSoilPh = document.getElementById('w-avg-soil-ph');
            if (elSoilPh) elSoilPh.innerText = '--';
            const elSoilEc = document.getElementById('w-avg-soil-ec');
            if (elSoilEc) elSoilEc.innerText = '--';

            const elWTemp = document.getElementById('w-avg-temp');
            if (elWTemp) elWTemp.innerText = '--';
            const elWHum = document.getElementById('w-avg-hum');
            if (elWHum) elWHum.innerText = '--';
            const elWWind = document.getElementById('w-avg-wind');
            if (elWWind) elWWind.innerText = '--';
            const elWRain = document.getElementById('w-avg-rain');
            if (elWRain) elWRain.innerText = '--';
        }
        document.getElementById('chart-empty').style.display = 'flex';
        document.getElementById('chart-empty-text').innerText = `No records found for ${rangeLabel}.`;
        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }
        document.getElementById('table-body').innerHTML = `<tr><td colspan="11" style="text-align: center; padding: 36px 20px; color: #64748b;">No telemetry records available for ${rangeLabel}.</td></tr>`;
        return;
    }

    const isAqiCurrent = currentSubTab === 'aqi';
    const isSoilCurrent = currentSubTab === 'soil';
    const loggedCount = filteredDayData.filter(d => !d.isMissing && (
        isAqiCurrent ? d.cpcb_aqi != null : (
            isSoilCurrent ? (d.soil_temperature != null || d.soil_humidity != null || d.soil_moisture != null) : (d.temperature != null || d.humidity != null || d.pressure != null || d.uv_index != null || d.solar_radiation != null)
        )
    )).length;

    if (loggedCount === 0) {
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

            const elSoilTemp = document.getElementById('w-avg-soil-temp');
            if (elSoilTemp) elSoilTemp.innerText = '--';
            const elSoilHum = document.getElementById('w-avg-soil-hum');
            if (elSoilHum) elSoilHum.innerText = '--';
            const elSoilPh = document.getElementById('w-avg-soil-ph');
            if (elSoilPh) elSoilPh.innerText = '--';
            const elSoilEc = document.getElementById('w-avg-soil-ec');
            if (elSoilEc) elSoilEc.innerText = '--';

            const elWTemp = document.getElementById('w-avg-temp');
            if (elWTemp) elWTemp.innerText = '--';
            const elWHum = document.getElementById('w-avg-hum');
            if (elWHum) elWHum.innerText = '--';
            const elWWind = document.getElementById('w-avg-wind');
            if (elWWind) elWWind.innerText = '--';
            const elWRain = document.getElementById('w-avg-rain');
            if (elWRain) elWRain.innerText = '--';
        }
        document.getElementById('chart-empty').style.display = 'flex';
        document.getElementById('chart-empty-text').innerText = `No telemetry recorded for ${rangeLabel}.`;
        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }
    } else {
        document.getElementById('chart-empty').style.display = 'none';
    }

    // ── Summary Calculations for 1-Day View ──
    if (days === 1) {
        let sumAqi = 0, countAqi = 0;
        let sumPm25 = 0, countPm25 = 0;
        let sumPm10 = 0, countPm10 = 0;
        let sumCo = 0, countCo = 0;
        let sumNo2 = 0, countNo2 = 0;
        let sumO3 = 0, countO3 = 0;
        let minAqi = 9999, maxAqi = -1;
        let sumSoilTemp = 0, countSoilTemp = 0;
        let sumSoilHum = 0, countSoilHum = 0;
        let sumSoilPh = 0, countSoilPh = 0;
        let sumSoilEc = 0, countSoilEc = 0;
        let sumWTemp = 0, countWTemp = 0;
        let sumWHum = 0, countWHum = 0;
        let sumWWind = 0, countWWind = 0;
        let sumWRain = 0;

        filteredDayData.forEach(d => {
            if (currentSubTab === 'aqi') {
                if (d.cpcb_aqi != null) { sumAqi += d.cpcb_aqi; countAqi++; if (d.cpcb_aqi < minAqi) minAqi = d.cpcb_aqi; if (d.cpcb_aqi > maxAqi) maxAqi = d.cpcb_aqi; }
                if (d.pm25 != null)    { sumPm25 += d.pm25; countPm25++; }
                if (d.pm10 != null)    { sumPm10 += d.pm10; countPm10++; }
                if (d.co != null)      { sumCo   += d.co;   countCo++;   }
                if (d.no2 != null)     { sumNo2  += d.no2;  countNo2++;  }
                if (d.o3 != null)      { sumO3   += d.o3;   countO3++;   }
            } else if (currentSubTab === 'soil') {
                if (d.soil_temperature != null && d.soil_temperature !== '') {
                    sumSoilTemp += Number(d.soil_temperature);
                    countSoilTemp++;
                }
                const hum = d.soil_humidity != null ? d.soil_humidity : d.soil_moisture;
                if (hum != null && hum !== '') {
                    sumSoilHum += Number(hum);
                    countSoilHum++;
                }
                if (d.soil_ph != null && d.soil_ph !== '') {
                    sumSoilPh += Number(d.soil_ph);
                    countSoilPh++;
                }
                if (d.soil_conductivity != null && d.soil_conductivity !== '') {
                    sumSoilEc += Number(d.soil_conductivity);
                    countSoilEc++;
                }
            } else { // weather
                if (d.temperature != null && d.temperature !== '') {
                    sumWTemp += Number(d.temperature);
                    countWTemp++;
                }
                if (d.humidity != null && d.humidity !== '') {
                    sumWHum += Number(d.humidity);
                    countWHum++;
                }
                if (d.wind_speed != null && d.wind_speed !== '') {
                    sumWWind += Number(d.wind_speed);
                    countWWind++;
                }
                if (d.rain_gauge != null && d.rain_gauge !== '') {
                    sumWRain += Number(d.rain_gauge);
                }
            }
        });

        if (currentSubTab === 'aqi') {
            // Node 2 standard CPCB NAQI calculation from 24-hr mean criteria pollutant concentrations
            const avgPm25Val = countPm25 > 0 ? (sumPm25 / countPm25) : null;
            const avgPm10Val = countPm10 > 0 ? (sumPm10 / countPm10) : null;
            const avgCoVal   = countCo > 0 ? (sumCo / countCo) : null;
            const avgNo2Val  = countNo2 > 0 ? (sumNo2 / countNo2) : null;
            const avgO3Val   = countO3 > 0 ? (sumO3 / countO3) : null;

            const subIndices = {
                pm25: avgPm25Val != null ? calculateCpcbSubIndex('pm25', avgPm25Val) : 0,
                pm10: avgPm10Val != null ? calculateCpcbSubIndex('pm10', avgPm10Val) : 0,
                co:   avgCoVal != null ? calculateCpcbSubIndex('co', avgCoVal) : 0,
                no2:  avgNo2Val != null ? calculateCpcbSubIndex('no2', avgNo2Val) : 0,
                o3:   avgO3Val != null ? calculateCpcbSubIndex('o3', avgO3Val) : 0
            };

            let maxSub = 0;
            let dominantPollutant = 'PM2.5';
            const domMap = { pm25: 'PM2.5', pm10: 'PM10', co: 'CO', no2: 'NO₂', o3: 'O₃' };
            for (const [param, sub] of Object.entries(subIndices)) {
                if (sub >= maxSub) {
                    maxSub = sub;
                    dominantPollutant = domMap[param] || 'PM2.5';
                }
            }

            // The official 24-hr CPCB AQI is the maximum of the individual pollutant sub-indices
            const avgAqi = (countPm25 > 0 || countPm10 > 0) ? Math.round(maxSub) : (countAqi > 0 ? Math.round(sumAqi / countAqi) : 0);
            const aqiCat = getAqiCategory(avgAqi);
            const avgAqiVal = document.getElementById('avg-aqi-val');
            if (avgAqiVal) {
                avgAqiVal.innerText = (countPm25 > 0 || countPm10 > 0 || countAqi > 0) ? avgAqi : '--';
                avgAqiVal.style.color = (countPm25 > 0 || countPm10 > 0 || countAqi > 0) ? aqiCat.text : '#64748b';
            }

            const label = document.getElementById('avg-aqi-label');
            if (label) {
                if (countPm25 > 0 || countPm10 > 0 || countAqi > 0) {
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
            if (minAqiVal) minAqiVal.innerText = (countAqi > 0 && minAqi !== 9999) ? minAqi : '--';
            if (maxAqiVal) maxAqiVal.innerText = (countAqi > 0 && maxAqi !== -1) ? maxAqi : '--';
            if (minMaxAqi) minMaxAqi.style.display = countAqi > 0 ? 'flex' : 'none';

            const badge = document.getElementById('avg-aqi-badge-container');
            if (badge) {
                badge.style.backgroundColor = (countPm25 > 0 || countPm10 > 0 || countAqi > 0) ? aqiCat.bg : '#f8fafc';
                badge.style.borderColor = (countPm25 > 0 || countPm10 > 0 || countAqi > 0) ? aqiCat.border : '#e2e8f0';
                badge.title = `Dominant Pollutant: ${dominantPollutant} (Sub-index: ${maxSub})`;
            }
            const avgAqiDot = document.getElementById('avg-aqi-dot');
            if (avgAqiDot) avgAqiDot.style.backgroundColor = (countPm25 > 0 || countPm10 > 0 || countAqi > 0) ? aqiCat.color : '#94a3b8';
            const avgAqiTitle = document.getElementById('avg-aqi-title');
            if (avgAqiTitle) {
                avgAqiTitle.style.color = (countPm25 > 0 || countPm10 > 0 || countAqi > 0) ? aqiCat.text : '#64748b';
                avgAqiTitle.title = `Dominant: ${dominantPollutant}`;
            }

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
        } else if (currentSubTab === 'soil') {
            const elSoilTemp = document.getElementById('w-avg-soil-temp');
            const elSoilHum = document.getElementById('w-avg-soil-hum');
            const elSoilPh = document.getElementById('w-avg-soil-ph');
            const elSoilEc = document.getElementById('w-avg-soil-ec');
            if (elSoilTemp) elSoilTemp.innerText = countSoilTemp > 0 ? (sumSoilTemp / countSoilTemp).toFixed(1) + '°C' : '--';
            if (elSoilHum)  elSoilHum.innerText = countSoilHum > 0 ? (sumSoilHum / countSoilHum).toFixed(1) + '%' : '--';
            if (elSoilPh)   elSoilPh.innerText = countSoilPh > 0 ? (sumSoilPh / countSoilPh).toFixed(2) : '--';
            if (elSoilEc)   elSoilEc.innerText = countSoilEc > 0 ? (sumSoilEc / countSoilEc).toFixed(1) + ' µS/cm' : '--';
        } else { // weather
            const elWTemp = document.getElementById('w-avg-temp');
            const elWHum = document.getElementById('w-avg-hum');
            const elWWind = document.getElementById('w-avg-wind');
            const elWRain = document.getElementById('w-avg-rain');
            if (elWTemp) elWTemp.innerText = countWTemp > 0 ? (sumWTemp / countWTemp).toFixed(1) + '°C' : '--';
            if (elWHum)  elWHum.innerText = countWHum > 0 ? (sumWHum / countWHum).toFixed(1) + '%' : '--';
            if (elWWind) elWWind.innerText = countWWind > 0 ? (sumWWind / countWWind).toFixed(1) + ' km/h' : '--';
            if (elWRain) elWRain.innerText = sumWRain > 0 ? sumWRain.toFixed(1) + ' mm' : '0.0 mm';
        }

        if (window.lucide) {
            lucide.createIcons();
        }
    }

    // Chart Render - Sleek Dark Trend Graph (Single Real Series matching table below)
    const chartCanvas = document.getElementById('historical-chart');
    if (chartCanvas) {
        const ctx = chartCanvas.getContext('2d');
        const isWeather = (currentSubTab === 'weather');
        const isSoil = (currentSubTab === 'soil');
        
        // Meta definition for Node 2 parameters
        const paramMeta = {
            'soil_moisture': { icon: '💧', label: 'Soil Moisture', unit: '%', color: '#0284c7' },
            'soil_humidity': { icon: '💧', label: 'Soil Humidity', unit: '%', color: '#0284c7' },
            'soil_temperature': { icon: '🌱', label: 'Soil Temperature', unit: '°C', color: '#ea580c' },
            'soil_ph': { icon: '🧪', label: 'Soil pH', unit: 'pH', color: '#10b981' },
            'soil_conductivity': { icon: '⚡', label: 'Soil Conductivity', unit: 'µS/cm', color: '#8b5cf6' },
            'temperature': { icon: '🌡️', label: 'Temperature', unit: '°C', color: '#f97316' },
            'humidity': { icon: '💧', label: 'Humidity', unit: '%', color: '#0284c7' },
            'wind_speed': { icon: '💨', label: 'Wind Speed', unit: 'km/h', color: '#0d9488' },
            'wind_gust': { icon: '⚡', label: 'Wind Gust', unit: 'm/s', color: '#0891b2' },
            'rain_gauge': { icon: '🌧️', label: 'Rain Gauge', unit: 'mm', color: '#2563eb' },
            'pressure': { icon: '⏱️', label: 'Pressure', unit: 'mb', color: '#ec4899' },
            'uv_index': { icon: '☀️', label: 'UV Index', unit: '', color: '#f59e0b' },
            'solar_radiation': { icon: '☀️', label: 'Solar Radiation', unit: 'W/m²', color: '#f59e0b' },
            'cpcb_aqi': { icon: '🌿', label: 'CPCB AQI', unit: '', color: '#00bfa5' },
            'pm25': { icon: '🌁', label: 'PM2.5', unit: 'µg/m³', color: '#00bfa5' },
            'pm10': { icon: '💨', label: 'PM10', unit: 'µg/m³', color: '#6366f1' },
            'co': { icon: '🧪', label: 'CO', unit: 'mg/m³', color: '#10b981' },
            'no2': { icon: '🚗', label: 'NO₂', unit: 'µg/m³', color: '#a855f7' },
            'o3': { icon: '☀️', label: 'O₃', unit: 'µg/m³', color: '#f59e0b' }
        };
        const meta = paramMeta[currentParam] || (isSoil
            ? { icon: '💧', label: 'Soil Moisture', unit: '%', color: '#0284c7' }
            : (isWeather 
                ? { icon: '🌡️', label: 'Temperature', unit: '°C', color: '#f97316' } 
                : { icon: '🌿', label: 'CPCB AQI', unit: '', color: '#00bfa5' }));

        // Update top param button & legend
        const pIcon = document.getElementById('trend-param-icon');
        const pLabel = document.getElementById('trend-param-label');
        if (pIcon) pIcon.innerText = meta.icon;
        if (pLabel) pLabel.innerText = meta.label;
        updateTrendLegend(currentParam, meta.label);

        // Build 24-hour diurnal or multi-day labels & series from REAL recorded data
        let labels = [];
        let actualData = [];

        if (days === 1) {
            labels = ['12 AM', '1 AM', '2 AM', '3 AM', '4 AM', '5 AM', '6 AM', '7 AM', '8 AM', '9 AM', '10 AM', '11 AM', '12 PM', '1 PM', '2 PM', '3 PM', '4 PM', '5 PM', '6 PM', '7 PM', '8 PM', '9 PM', '10 PM', '11 PM'];
            actualData = new Array(24).fill(null);

            const hourMap = {};
            filteredDayData.forEach(d => {
                if (!d.timestamp) return;
                const parts = d.timestamp.split('T');
                if (parts.length === 2) {
                    const hr = parseInt(parts[1].split(':')[0], 10);
                    if (!isNaN(hr) && hr >= 0 && hr < 24) {
                        let val = d[currentParam];
                        if ((val === null || val === undefined || val === '') && (currentParam === 'soil_humidity' || currentParam === 'soil_moisture')) {
                            val = d.soil_moisture != null ? d.soil_moisture : d.soil_humidity;
                        }
                        if ((val === null || val === undefined || val === '') && currentParam === 'solar_radiation') {
                            val = d.solar_radiation != null ? d.solar_radiation : (d.solar != null ? d.solar : d.radiation);
                        }
                        if ((val === null || val === undefined || val === '') && currentParam === 'temperature') {
                            val = d.temperature != null ? d.temperature : d.soil_temperature;
                        }
                        if ((val === null || val === undefined || val === '') && currentParam === 'humidity') {
                            val = d.humidity != null ? d.humidity : (d.soil_moisture != null ? d.soil_moisture : d.soil_humidity);
                        }
                        if (val !== null && val !== undefined && val !== '') {
                            const num = parseFloat(val);
                            if (!isNaN(num)) hourMap[hr] = num;
                        }
                    }
                }
            });

            for (let h = 0; h < 24; h++) {
                if (hourMap[h] !== undefined) {
                    actualData[h] = Number(hourMap[h].toFixed(2));
                }
            }

            // Do not show future hours in advance for today's date
            if (dateInput === '<?= $today_ist ?>') {
                const nowIstHour = Number(new Date().toLocaleString('en-US', { timeZone: 'Asia/Kolkata', hour: 'numeric', hour12: false })) % 24;
                let lastDataHr = -1;
                for (let h = 0; h < 24; h++) {
                    if (actualData[h] !== null) lastDataHr = h;
                }
                const maxHr = Math.min(23, Math.max(isNaN(nowIstHour) ? 0 : nowIstHour, lastDataHr));
                labels = labels.slice(0, maxHr + 1);
                actualData = actualData.slice(0, maxHr + 1);
            }
        } else {
            // Multi-day range (3, 7, 30 days)
            const dayMap = {};
            filteredDayData.forEach(d => {
                if (!d.timestamp) return;
                const dayKey = d.timestamp.split('T')[0];
                if (!dayMap[dayKey]) dayMap[dayKey] = [];
                let val = d[currentParam];
                if ((val === null || val === undefined || val === '') && (currentParam === 'soil_humidity' || currentParam === 'soil_moisture')) {
                    val = d.soil_moisture != null ? d.soil_moisture : d.soil_humidity;
                }
                if ((val === null || val === undefined || val === '') && currentParam === 'solar_radiation') {
                    val = d.solar_radiation != null ? d.solar_radiation : (d.solar != null ? d.solar : d.radiation);
                }
                if ((val === null || val === undefined || val === '') && currentParam === 'temperature') {
                    val = d.temperature != null ? d.temperature : d.soil_temperature;
                }
                if ((val === null || val === undefined || val === '') && currentParam === 'humidity') {
                    val = d.humidity != null ? d.humidity : (d.soil_moisture != null ? d.soil_moisture : d.soil_humidity);
                }
                if (val !== null && val !== undefined && val !== '') {
                    const num = parseFloat(val);
                    if (!isNaN(num)) dayMap[dayKey].push(num);
                }
            });

            const dayKeys = Object.keys(dayMap).sort();
            if (dayKeys.length > 0) {
                labels = dayKeys.map(k => {
                    const p = k.split('-');
                    const dt = new Date(parseInt(p[0]), parseInt(p[1]) - 1, parseInt(p[2]));
                    return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                });
                actualData = dayKeys.map(k => {
                    const vals = dayMap[k];
                    if (!vals.length) return null;
                    const avg = vals.reduce((a, b) => a + b, 0) / vals.length;
                    return Number(avg.toFixed(2));
                });
            }
        }

        // Compute legend statistics for the real recorded series matching the table
        const validValues = actualData.filter(v => v !== null && !isNaN(v));
        const latestVal = validValues.length > 0 ? validValues[validValues.length - 1] : null;
        const peakVal = validValues.length > 0 ? Math.max(...validValues) : null;
        const minVal = validValues.length > 0 ? Math.min(...validValues) : null;
        const avgVal = validValues.length > 0 ? (validValues.reduce((a, b) => a + b, 0) / validValues.length) : null;

        const u = meta.unit ? ` ${meta.unit}` : '';
        const elLatest = document.getElementById('leg-latest-val');
        const elPeak = document.getElementById('leg-peak-val');
        const elMin = document.getElementById('leg-min-val');
        const elAvg = document.getElementById('leg-avg-val');
        if (elLatest) elLatest.innerText = latestVal !== null ? `${latestVal}${u}` : '--';
        if (elPeak) elPeak.innerText = peakVal !== null ? `${peakVal.toFixed(1)}${u}` : '--';
        if (elMin) elMin.innerText = minVal !== null ? `${minVal.toFixed(1)}${u}` : '--';
        if (elAvg) elAvg.innerText = avgVal !== null ? `${avgVal.toFixed(1)}${u}` : '--';

        const primaryColor = meta.color || '#00bfa5';
        const targetChartType = (currentChartType === 'bar') ? 'bar' : 'line';
        syncChartTypeButtons(targetChartType);

        const getPointColor = (val) => {
            if (val == null || isNaN(val)) return primaryColor;
            if (currentParam === 'cpcb_aqi' || currentParam === 'aqi') return getAqiCategory(val).color;
            if (POLLUTANT_SCALES[currentParam]) return getParamColor(currentParam, val);
            return primaryColor;
        };
        const hasTierColors = (currentParam === 'cpcb_aqi' || currentParam === 'aqi' || Boolean(POLLUTANT_SCALES[currentParam]));
        const pointColors = actualData.map(v => getPointColor(v));
        const latestPointColor = latestVal !== null ? getPointColor(latestVal) : primaryColor;

        const datasetConfig = targetChartType === 'bar'
            ? {
                label: meta.label,
                paramKey: currentParam,
                paramUnit: meta.unit || '',
                data: actualData,
                backgroundColor: pointColors.map(c => c + 'd9'),
                hoverBackgroundColor: pointColors,
                borderColor: pointColors,
                borderWidth: 1.5,
                borderRadius: 6,
                maxBarThickness: 28
            }
            : {
                label: meta.label,
                paramKey: currentParam,
                paramUnit: meta.unit || '',
                data: actualData,
                borderColor: latestPointColor,
                borderWidth: 2.6,
                tension: 0.38,
                pointRadius: days === 1 ? 4 : 3,
                pointHoverRadius: 6,
                pointBackgroundColor: pointColors,
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                segment: hasTierColors ? {
                    borderColor: (segCtx) => {
                        const yVal = (segCtx.p1 && segCtx.p1.parsed) ? segCtx.p1.parsed.y : null;
                        return yVal != null ? getPointColor(yVal) : latestPointColor;
                    }
                } : undefined,
                backgroundColor: function(ctx) {
                    const chart = ctx.chart;
                    const {ctx: c, chartArea} = chart;
                    if (!chartArea) return 'rgba(0, 191, 165, 0.22)';
                    if (hasTierColors && actualData.length > 1) {
                        const hGrad = c.createLinearGradient(chartArea.left, 0, chartArea.right, 0);
                        actualData.forEach((v, idx) => {
                            const stop = actualData.length > 1 ? idx / (actualData.length - 1) : 0;
                            hGrad.addColorStop(Math.min(1, Math.max(0, stop)), getPointColor(v) + '38');
                        });
                        return hGrad;
                    }
                    const grad = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                    grad.addColorStop(0, latestPointColor + '55');
                    grad.addColorStop(0.5, latestPointColor + '20');
                    grad.addColorStop(1, 'rgba(0,0,0,0)');
                    return grad;
                },
                fill: 'start',
                spanGaps: false
            };

        const datasets = [datasetConfig];

        const yMin = (minVal !== null && minVal < 0) ? undefined : 0;
        const ySuggestedMax = (peakVal !== null && peakVal > 0) ? undefined : 1.0;

        try {
            if (chartInstance && chartInstance.ctx && chartInstance.canvas === chartCanvas && chartInstance.config && chartInstance.config.type === targetChartType) {
                chartInstance.data.labels = labels;
                chartInstance.data.datasets = datasets;
                if (chartInstance.options && chartInstance.options.scales && chartInstance.options.scales.y) {
                    if (chartInstance.options.scales.y.title) {
                        chartInstance.options.scales.y.title.text = meta.unit ? `${meta.label} (${meta.unit})` : meta.label;
                    }
                    chartInstance.options.scales.y.min = yMin;
                    chartInstance.options.scales.y.suggestedMin = 0;
                    chartInstance.options.scales.y.suggestedMax = ySuggestedMax;
                    chartInstance.options.scales.y.beginAtZero = true;
                }
                chartInstance.update();
            } else {
                if (chartInstance) {
                    try { chartInstance.destroy(); } catch(e) {}
                }
                chartInstance = new Chart(ctx, {
                    type: targetChartType,
                    data: { labels, datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 350 },
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(22, 27, 38, 0.96)',
                                borderColor: 'rgba(255, 255, 255, 0.15)',
                                borderWidth: 1,
                                titleColor: '#ffffff',
                                titleFont: { weight: '700', size: 12, family: 'var(--font-sans)' },
                                bodyColor: '#cbd5e1',
                                bodyFont: { size: 11, family: 'var(--font-mono)' },
                                padding: 10,
                                cornerRadius: 10,
                                boxPadding: 4,
                                callbacks: {
                                    title: function(items) {
                                        return `${items[0].label} • ${dateInput}`;
                                    },
                                    label: function(c) {
                                        const ds = c.dataset || {};
                                        const pKey = ds.paramKey || currentParam;
                                        const u = (ds.paramUnit !== undefined) ? ds.paramUnit : (PARAM_UNITS[pKey] || '');
                                        const unitStr = u ? ` ${u}` : '';
                                        const rawVal = c.parsed.y;
                                        if (rawVal == null || isNaN(rawVal)) return ` ${ds.label || 'Value'}: No data`;

                                        if (pKey === 'cpcb_aqi' || pKey === 'aqi') {
                                            const aqiVal = Math.round(rawVal);
                                            const cat = getAqiCategory(aqiVal);
                                            return ` ${ds.label || 'CPCB AQI'}: ${aqiVal} (${cat.label})`;
                                        }

                                        const dispVal = Number.isInteger(rawVal) ? rawVal : Number(rawVal).toFixed(1);
                                        return ` ${ds.label || meta.label}: ${dispVal}${unitStr}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: true, color: 'rgba(255, 255, 255, 0.05)', borderDash: [2, 2] },
                                ticks: {
                                    color: '#94a3b8',
                                    font: { family: 'var(--font-mono)', size: 10 },
                                    maxRotation: 45,
                                    minRotation: 0,
                                    autoSkip: false
                                },
                                title: { display: true, text: days > 1 ? 'Days Progression' : 'Hours (24-Hour Diurnal Progression)', color: '#64748b', font: { size: 11, weight: '600' } }
                            },
                            y: {
                                min: yMin,
                                suggestedMin: 0,
                                suggestedMax: ySuggestedMax,
                                beginAtZero: true,
                                grid: { color: 'rgba(255, 255, 255, 0.07)', borderDash: [3, 3] },
                                ticks: { color: '#94a3b8', font: { family: 'var(--font-mono)', size: 10 } },
                                title: { display: true, text: meta.unit ? `${meta.label} (${meta.unit})` : meta.label, color: '#94a3b8', font: { size: 11, weight: '700' } }
                            }
                        }
                    }
                });
            }
        } catch(err) {
            console.warn('Historical trend chart render error:', err);
        }
    }

    // Table Render (Newest readings at the top, oldest at the bottom)
    const theadRow = document.getElementById('table-head-row');
    const tbody = document.getElementById('table-body');
    const tableRows = [...filteredDayData].reverse();
    
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
        
        tbody.innerHTML = tableRows.map((d, i) => {
            const parts = (d.timestamp || '').split('T');
            let dateStr = '', timeStr = '';
            if (parts.length === 2) {
                const [yr, mo, da] = parts[0].split('-').map(Number);
                const timeParts = parts[1].split(':');
                const hr = Number(timeParts[0]) || 0;
                const mn = Number(timeParts[1]) || 0;
                const period = hr >= 12 ? 'PM' : 'AM';
                const displayHr = hr % 12 === 0 ? 12 : hr % 12;
                dateStr = `${String(da).padStart(2, '0')}/${String(mo).padStart(2, '0')}/${yr}`;
                timeStr = `${String(displayHr).padStart(2, '0')}:${String(mn).padStart(2, '0')} ${period}`;
            } else {
                const dt = new Date(d.timestamp);
                dateStr = dt.toLocaleDateString('en-GB');
                timeStr = dt.toLocaleTimeString('en-US', { hour: '2-digit', minute:'2-digit' });
            }
            const isMissing = Boolean(d.isMissing || (d.cpcb_aqi == null && d.pm25 == null && d.pm10 == null));
            const emptyCell = `<span style="color: #94a3b8; font-weight: 600;">--</span>`;

            const aqiCell = d.cpcb_aqi != null 
                ? `<span style="font-weight: 800; color: ${getAqiCategory(d.cpcb_aqi).text};">${Math.round(d.cpcb_aqi)}</span>`
                : emptyCell;
            const pm25Cell = d.pm25 != null 
                ? `<span style="font-weight: 700; color: ${getParamColor('pm25', d.pm25)}; font-family: var(--font-mono);">${Number(d.pm25).toFixed(3)}</span>`
                : emptyCell;
            const pm10Cell = d.pm10 != null 
                ? `<span style="font-weight: 700; color: ${getParamColor('pm10', d.pm10)}; font-family: var(--font-mono);">${Number(d.pm10).toFixed(3)}</span>`
                : emptyCell;
            const coCell = d.co != null 
                ? `<span style="font-weight: 700; color: ${getParamColor('co', d.co)}; font-family: var(--font-mono);">${Number(d.co).toFixed(3)}</span>`
                : emptyCell;
            const no2Cell = d.no2 != null 
                ? `<span style="font-weight: 700; color: ${getParamColor('no2', d.no2)}; font-family: var(--font-mono);">${Number(d.no2).toFixed(3)}</span>`
                : emptyCell;
            const o3Cell = d.o3 != null 
                ? `<span style="font-weight: 700; color: ${getParamColor('o3', d.o3)}; font-family: var(--font-mono);">${Number(d.o3).toFixed(3)}</span>`
                : emptyCell;

            return `<tr style="border-bottom: 1px solid #f1f5f9; background-color: ${isMissing ? '#fffdfa' : (i % 2 === 0 ? '#ffffff' : '#f8fafc')};">
                <td style="padding: 10px 16px; color: ${isMissing ? '#64748b' : '#0f172a'}; font-weight: 600; white-space: nowrap;">${dateStr}</td>
                <td style="padding: 10px 16px; color: ${isMissing ? '#0d9488' : '#00bfa5'}; font-family: var(--font-mono); font-weight: 700; white-space: nowrap;">${timeStr}</td>
                <td style="padding: 10px 16px;">${aqiCell}</td>
                <td style="padding: 10px 16px;">${pm25Cell}</td>
                <td style="padding: 10px 16px;">${pm10Cell}</td>
                <td style="padding: 10px 16px;">${coCell}</td>
                <td style="padding: 10px 16px;">${no2Cell}</td>
                <td style="padding: 10px 16px;">${o3Cell}</td>
            </tr>`;
        }).join('');
    } else if (currentSubTab === 'soil') {
        theadRow.innerHTML = `
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">Date</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">Time</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap; color: #0284c7;">Soil Moisture (%)</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap; color: #ea580c;">Soil Temp (°C)</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap; color: #059669;">Soil pH</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap; color: #7c3aed;">Soil EC (µS/cm)</th>
        `;
        
        tbody.innerHTML = tableRows.map((d, i) => {
            const parts = (d.timestamp || '').split('T');
            let dateStr = '', timeStr = '';
            if (parts.length === 2) {
                const [yr, mo, da] = parts[0].split('-').map(Number);
                const timeParts = parts[1].split(':');
                const hr = Number(timeParts[0]) || 0;
                const mn = Number(timeParts[1]) || 0;
                const period = hr >= 12 ? 'PM' : 'AM';
                const displayHr = hr % 12 === 0 ? 12 : hr % 12;
                dateStr = `${String(da).padStart(2, '0')}/${String(mo).padStart(2, '0')}/${yr}`;
                timeStr = `${String(displayHr).padStart(2, '0')}:${String(mn).padStart(2, '0')} ${period}`;
            } else {
                const dt = new Date(d.timestamp);
                dateStr = dt.toLocaleDateString('en-GB');
                timeStr = dt.toLocaleTimeString('en-US', { hour: '2-digit', minute:'2-digit' });
            }
            const isMissing = Boolean(d.isMissing || (d.soil_temperature == null && d.soil_humidity == null && d.soil_moisture == null));
            const emptyCell = `<span style="color: #94a3b8; font-weight: 600;">--</span>`;

            const sHum = d.soil_moisture != null ? d.soil_moisture : d.soil_humidity;
            const soilHumCell = (sHum != null && sHum !== '') 
                ? `<span style="color: #0284c7; font-weight: 700; font-family: var(--font-mono);">${Number(sHum).toFixed(1)}%</span>` 
                : emptyCell;
            const soilTempCell = (d.soil_temperature != null && d.soil_temperature !== '') 
                ? `<span style="color: #ea580c; font-weight: 700; font-family: var(--font-mono);">${Number(d.soil_temperature).toFixed(1)}°C</span>` 
                : emptyCell;
            const soilPhCell = (d.soil_ph != null && d.soil_ph !== '') 
                ? `<span style="color: #059669; font-weight: 700; font-family: var(--font-mono);">${Number(d.soil_ph).toFixed(2)}</span>` 
                : emptyCell;
            const soilEcCell = (d.soil_conductivity != null && d.soil_conductivity !== '') 
                ? `<span style="color: #7c3aed; font-weight: 700; font-family: var(--font-mono);">${Number(d.soil_conductivity).toFixed(1)}</span>` 
                : emptyCell;

            return `<tr style="border-bottom: 1px solid #f1f5f9; background-color: ${isMissing ? '#fffdfa' : (i % 2 === 0 ? '#ffffff' : '#f8fafc')};">
                <td style="padding: 10px 16px; color: ${isMissing ? '#64748b' : '#0f172a'}; font-weight: 600; white-space: nowrap;">${dateStr}</td>
                <td style="padding: 10px 16px; color: ${isMissing ? '#0d9488' : '#00bfa5'}; font-family: var(--font-mono); font-weight: 700; white-space: nowrap;">${timeStr}</td>
                <td style="padding: 10px 16px;">${soilHumCell}</td>
                <td style="padding: 10px 16px;">${soilTempCell}</td>
                <td style="padding: 10px 16px;">${soilPhCell}</td>
                <td style="padding: 10px 16px;">${soilEcCell}</td>
            </tr>`;
        }).join('');
    } else {
        theadRow.innerHTML = `
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">Date</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap;">Time</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap; color: #ea580c;">Temp (°C)</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap; color: #0284c7;">Humidity (%)</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap; color: #ec4899;">Pressure (mb)</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap; color: #f43f5e;">UV Index</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap; color: #f59e0b;">Solar (W/m²)</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap; color: #0d9488;">Wind Speed (km/h)</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap; color: #0891b2;">Wind Gust (m/s)</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap; color: #64748b;">Wind Dir</th>
            <th style="padding: 11px 16px; font-weight: 700; white-space: nowrap; color: #2563eb;">Rain (mm)</th>
        `;
        
        tbody.innerHTML = tableRows.map((d, i) => {
            const parts = (d.timestamp || '').split('T');
            let dateStr = '', timeStr = '';
            if (parts.length === 2) {
                const [yr, mo, da] = parts[0].split('-').map(Number);
                const timeParts = parts[1].split(':');
                const hr = Number(timeParts[0]) || 0;
                const mn = Number(timeParts[1]) || 0;
                const period = hr >= 12 ? 'PM' : 'AM';
                const displayHr = hr % 12 === 0 ? 12 : hr % 12;
                dateStr = `${String(da).padStart(2, '0')}/${String(mo).padStart(2, '0')}/${yr}`;
                timeStr = `${String(displayHr).padStart(2, '0')}:${String(mn).padStart(2, '0')} ${period}`;
            } else {
                const dt = new Date(d.timestamp);
                dateStr = dt.toLocaleDateString('en-GB');
                timeStr = dt.toLocaleTimeString('en-US', { hour: '2-digit', minute:'2-digit' });
            }
            const isMissing = Boolean(d.isMissing || (d.temperature == null && d.humidity == null && d.pressure == null && d.uv_index == null && d.solar_radiation == null));
            const emptyCell = `<span style="color: #94a3b8; font-weight: 600;">--</span>`;

            const tempCell = (d.temperature != null && d.temperature !== '') 
                ? `<span style="color: #ea580c; font-weight: 700; font-family: var(--font-mono);">${Number(d.temperature).toFixed(1)}°C</span>` 
                : emptyCell;
            const humCell = (d.humidity != null && d.humidity !== '') 
                ? `<span style="color: #0284c7; font-weight: 700; font-family: var(--font-mono);">${Number(d.humidity).toFixed(1)}%</span>` 
                : emptyCell;
            const pressCell = (d.pressure != null && d.pressure !== '') 
                ? `<span style="color: #ec4899; font-weight: 700; font-family: var(--font-mono);">${Math.round(Number(d.pressure))} mb</span>` 
                : emptyCell;
            const uvCell = (d.uv_index != null && d.uv_index !== '') 
                ? `<span style="color: #f43f5e; font-weight: 700; font-family: var(--font-mono);">${Number(d.uv_index).toFixed(1)}</span>` 
                : emptyCell;
            const rawSolarVal = (d.solar_radiation != null && d.solar_radiation !== '')
                ? d.solar_radiation
                : ((d.solar != null && d.solar !== '') ? d.solar : ((d.radiation != null && d.radiation !== '') ? d.radiation : null));
            const solarCell = (rawSolarVal != null && rawSolarVal !== '')
                ? `<span style="color: #f59e0b; font-weight: 700; font-family: var(--font-mono);">${Number(rawSolarVal).toFixed(1)} W/m²</span>` 
                : emptyCell;
            const windCell = (d.wind_speed != null && d.wind_speed !== '') 
                ? `<span style="color: #0d9488; font-weight: 700; font-family: var(--font-mono);">${Number(d.wind_speed).toFixed(1)}</span>` 
                : emptyCell;
            const gustCell = (d.wind_gust != null && d.wind_gust !== '') 
                ? `<span style="color: #0891b2; font-weight: 700; font-family: var(--font-mono);">${Number(d.wind_gust).toFixed(1)}</span>` 
                : emptyCell;
            const dirCell = (d.wind_direction != null && d.wind_direction !== '')
                ? `<span style="color: #475569; font-weight: 700;">${getCompassDir(d.wind_direction)}</span>`
                : emptyCell;
            const rainCell = (d.rain_gauge != null && d.rain_gauge !== '') 
                ? `<span style="color: #2563eb; font-weight: 700; font-family: var(--font-mono);">${Number(d.rain_gauge).toFixed(1)}</span>` 
                : emptyCell;

            return `<tr style="border-bottom: 1px solid #f1f5f9; background-color: ${isMissing ? '#fffdfa' : (i % 2 === 0 ? '#ffffff' : '#f8fafc')};">
                <td style="padding: 10px 16px; color: ${isMissing ? '#64748b' : '#0f172a'}; font-weight: 600; white-space: nowrap;">${dateStr}</td>
                <td style="padding: 10px 16px; color: ${isMissing ? '#0d9488' : '#00bfa5'}; font-family: var(--font-mono); font-weight: 700; white-space: nowrap;">${timeStr}</td>
                <td style="padding: 10px 16px;">${tempCell}</td>
                <td style="padding: 10px 16px;">${humCell}</td>
                <td style="padding: 10px 16px;">${pressCell}</td>
                <td style="padding: 10px 16px;">${uvCell}</td>
                <td style="padding: 10px 16px;">${solarCell}</td>
                <td style="padding: 10px 16px;">${windCell}</td>
                <td style="padding: 10px 16px;">${gustCell}</td>
                <td style="padding: 10px 16px;">${dirCell}</td>
                <td style="padding: 10px 16px;">${rainCell}</td>
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
    const scopeLabel = currentSubTab === 'aqi' ? 'AQI Telemetry' : (currentSubTab === 'soil' ? 'Soil Telemetry' : 'Weather Telemetry');
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
    const scopeLabel = currentSubTab === 'aqi' ? 'AQI Telemetry' : (currentSubTab === 'soil' ? 'Soil Telemetry' : 'Weather Telemetry');
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
        csv += 'Date,Time,AQI,Temp,Humidity,PM2.5,PM10,CO,NO2,O3\n';
        filteredDayData.forEach(d => {
            const parts = (d.timestamp || '').split('T');
            let dateStr = '', timeStr = '';
            if (parts.length === 2) {
                const [yr, mo, da] = parts[0].split('-').map(Number);
                const timeParts = parts[1].split(':');
                const hr = Number(timeParts[0]) || 0;
                const mn = Number(timeParts[1]) || 0;
                const period = hr >= 12 ? 'PM' : 'AM';
                const displayHr = hr % 12 === 0 ? 12 : hr % 12;
                dateStr = `${String(da).padStart(2, '0')}/${String(mo).padStart(2, '0')}/${yr}`;
                timeStr = `${String(displayHr).padStart(2, '0')}:${String(mn).padStart(2, '0')} ${period}`;
            } else {
                const dt = new Date(d.timestamp);
                dateStr = dt.toLocaleDateString('en-GB');
                timeStr = dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'});
            }
            let tempVal = d.temperature;
            let humVal = d.humidity;
            if ((tempVal == null || humVal == null) && Array.isArray(historicalDataWeather) && d.timestamp) {
                const matchPrefix = d.timestamp.slice(0, 13);
                const matchW = historicalDataWeather.find(w => w.timestamp && w.timestamp.slice(0, 13) === matchPrefix);
                if (matchW) {
                    if (tempVal == null && matchW.temperature != null) tempVal = matchW.temperature;
                    if (humVal == null && matchW.humidity != null) humVal = matchW.humidity;
                }
            }
            const aqi = d.cpcb_aqi != null ? Math.round(d.cpcb_aqi) : '';
            const temp = tempVal != null ? tempVal : '';
            const hum = humVal != null ? humVal : '';
            const pm25 = d.pm25 != null ? d.pm25 : '';
            const pm10 = d.pm10 != null ? d.pm10 : '';
            const co = d.co != null ? d.co : '';
            const no2 = d.no2 != null ? d.no2 : '';
            const o3 = d.o3 != null ? d.o3 : '';
            csv += `${dateStr},${timeStr},${aqi},${temp},${hum},${pm25},${pm10},${co},${no2},${o3}\n`;
        });
    } else if (currentSubTab === 'soil') {
        csv += 'Date,Time,SoilMoisture,SoilTemp,SoilPH,SoilEC\n';
        filteredDayData.forEach(d => {
            const parts = (d.timestamp || '').split('T');
            let dateStr = '', timeStr = '';
            if (parts.length === 2) {
                const [yr, mo, da] = parts[0].split('-').map(Number);
                const timeParts = parts[1].split(':');
                const hr = Number(timeParts[0]) || 0;
                const mn = Number(timeParts[1]) || 0;
                const period = hr >= 12 ? 'PM' : 'AM';
                const displayHr = hr % 12 === 0 ? 12 : hr % 12;
                dateStr = `${String(da).padStart(2, '0')}/${String(mo).padStart(2, '0')}/${yr}`;
                timeStr = `${String(displayHr).padStart(2, '0')}:${String(mn).padStart(2, '0')} ${period}`;
            } else {
                const dt = new Date(d.timestamp);
                dateStr = dt.toLocaleDateString('en-GB');
                timeStr = dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'});
            }
            const sHum = d.soil_moisture != null ? d.soil_moisture : (d.soil_humidity != null ? d.soil_humidity : '');
            const sTemp = d.soil_temperature != null ? d.soil_temperature : '';
            const sPh = d.soil_ph != null ? d.soil_ph : '';
            const sEc = d.soil_conductivity != null ? d.soil_conductivity : '';
            csv += `${dateStr},${timeStr},${sHum},${sTemp},${sPh},${sEc}\n`;
        });
    } else {
        csv += 'Date,Time,Temp,Humidity,Pressure,UVIndex,SolarRadiation,WindSpeed,WindGust,WindDir,Rain\n';
        filteredDayData.forEach(d => {
            const parts = (d.timestamp || '').split('T');
            let dateStr = '', timeStr = '';
            if (parts.length === 2) {
                const [yr, mo, da] = parts[0].split('-').map(Number);
                const timeParts = parts[1].split(':');
                const hr = Number(timeParts[0]) || 0;
                const mn = Number(timeParts[1]) || 0;
                const period = hr >= 12 ? 'PM' : 'AM';
                const displayHr = hr % 12 === 0 ? 12 : hr % 12;
                dateStr = `${String(da).padStart(2, '0')}/${String(mo).padStart(2, '0')}/${yr}`;
                timeStr = `${String(displayHr).padStart(2, '0')}:${String(mn).padStart(2, '0')} ${period}`;
            } else {
                const dt = new Date(d.timestamp);
                dateStr = dt.toLocaleDateString('en-GB');
                timeStr = dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'});
            }
            const temp = d.temperature != null ? d.temperature : '';
            const hum = d.humidity != null ? d.humidity : '';
            const press = d.pressure != null ? d.pressure : '';
            const uv = d.uv_index != null ? d.uv_index : '';
            const rawSolar = d.solar_radiation != null ? d.solar_radiation : (d.solar != null ? d.solar : (d.radiation != null ? d.radiation : ''));
            const wind = d.wind_speed != null ? d.wind_speed : '';
            const gust = d.wind_gust != null ? d.wind_gust : '';
            const dir = d.wind_direction != null ? getCompassDir(d.wind_direction) : '';
            const rain = d.rain_gauge != null ? d.rain_gauge : '';
            csv += `${dateStr},${timeStr},${temp},${hum},${press},${uv},${rawSolar},${wind},${gust},${dir},${rain}\n`;
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
        const currentPickerVal = normalizeDateToYmd(dp.value);
        if (currentPickerVal === todayStr && currentRangeDays === 1) {
            fetchAllHistory(todayStr, true, true);
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
        if (!dp.value) dp.value = normalizeDateToYmd(_initState.date || '<?= $today_ist ?>');
        else dp.value = normalizeDateToYmd(dp.value);
        dp.onchange = () => {
            if (!dp.value) return;
            persistHistoricalState();
            fetchAllHistory(dp.value);
        };
        const prefetch30OnInteract = () => {
            const k30 = getCacheKey('<?= $today_ist ?>', 30);
            if (!clientRangeCache[k30] && !pendingRangeFetches[k30]) {
                fetchRangeData('<?= $today_ist ?>', 30).catch(() => {});
            }
        };
        dp.addEventListener('pointerenter', prefetch30OnInteract, { passive: true });
        dp.addEventListener('focus', prefetch30OnInteract, { passive: true });
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
            const qDate = normalizeDateToYmd(picker && picker.value ? picker.value : '<?= $today_ist ?>');
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
                const qDate = normalizeDateToYmd(picker && picker.value ? picker.value : '<?= $today_ist ?>');
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
    if (window.lucide) {
        lucide.createIcons();
    }
    startAutoRefreshHist();
}, 0);
</script>
