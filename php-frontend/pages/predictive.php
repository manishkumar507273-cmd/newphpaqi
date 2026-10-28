<?php
/**
 * Smart WeatherNet - Predictive Forecast Page
 * Edge-to-edge hourly reel, 7-day accordion with min/max slider bars,
 * and high-visibility AQI advisory alerts.
 */
if (!isset($page) && empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header("Location: /?page=predictive");
    exit;
}

// 0ms instant pre-hydration from disk cache
$forecastJsonFile = __DIR__ . '/../api/forecast_data.json';
$preloadedForecastJson = 'null';
if (file_exists($forecastJsonFile)) {
    $rawPreload = @file_get_contents($forecastJsonFile);
    if ($rawPreload && strlen($rawPreload) > 20) {
        $preloadedForecastJson = $rawPreload;
    }
}
?>

<div class="forecast-page-container p-2.5 sm:p-6 flex flex-col gap-3 sm:gap-6 max-w-[1200px] mx-auto text-slate-900 font-sans">

  <div class="mb-0.5 sm:mb-2">
    <h1 class="text-lg sm:text-[28px] font-extrabold text-slate-900 m-0 tracking-tight">Weather Forecast</h1>
    <p class="m-0 text-slate-500 text-[11px] sm:text-sm font-medium mt-0.5">Atmospheric weather, rainfall, and air quality predictive forecasting for SMVITM Campus.</p>
  </div>



  <!-- ── 3. Forecast Hero Summary Card: Predicted AQI ── -->
  <div class="bg-white rounded-[16px] sm:rounded-[24px] p-3 sm:p-7 border border-slate-200 shadow-[0_4px_24px_rgba(15,23,42,0.05)] relative overflow-hidden">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 sm:gap-6">
      
      <!-- Left Hero Info: Big AQI & Category -->
      <div class="flex-1">
        <div class="flex items-center gap-2 flex-wrap text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5 sm:mb-2">
          <span>Predicted Weather & Air Quality Index</span>
          <span>•</span>
          <span class="text-slate-600 font-semibold" id="forecast-date-str">Loading...</span>
          <span>•</span>
          <span class="px-2 py-0.5 rounded-full text-[10.5px] font-bold bg-teal-50 text-teal-700 border border-teal-200 font-mono" id="forecast-hero-target-window">Hourly Prediction</span>
        </div>

        <div class="flex items-baseline gap-3 mb-2 sm:mb-2.5">
          <span id="forecast-hero-aqi" class="text-4xl sm:text-6xl font-extrabold text-slate-400 font-mono tracking-tight">--</span>
          <div class="flex flex-col">
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold text-slate-400 uppercase tracking-wider font-mono">AQI</span>
              <span id="forecast-hero-category-badge" class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-slate-100 text-slate-600 border border-slate-200 font-mono">Standby</span>
            </div>
            <span id="forecast-hero-dominant" class="text-xs sm:text-[13px] font-semibold text-slate-600 mt-0.5 sm:mt-1">Awaiting Model Inference</span>
          </div>
        </div>

        <!-- 5 Criteria Pollutant Sub-Indices Badges -->
        <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap mb-2 sm:mb-2.5">
          <span class="inline-flex items-center gap-1.5 px-2 sm:px-2.5 py-0.5 sm:py-1 rounded-xl text-[10.5px] sm:text-[11px] font-bold bg-slate-50 text-slate-700 border border-slate-200" title="Sub-Index for PM2.5">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> PM2.5: <strong id="sub-pm25-val" class="font-mono font-extrabold">--</strong>
          </span>
          <span class="inline-flex items-center gap-1.5 px-2 sm:px-2.5 py-0.5 sm:py-1 rounded-xl text-[10.5px] sm:text-[11px] font-bold bg-slate-50 text-slate-700 border border-slate-200" title="Sub-Index for PM10">
            <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span> PM10: <strong id="sub-pm10-val" class="font-mono font-extrabold">--</strong>
          </span>
          <span class="inline-flex items-center gap-1.5 px-2 sm:px-2.5 py-0.5 sm:py-1 rounded-xl text-[10.5px] sm:text-[11px] font-bold bg-slate-50 text-slate-700 border border-slate-200" title="Sub-Index for NO2">
            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span> NO₂: <strong id="sub-no2-val" class="font-mono font-extrabold">--</strong>
          </span>
          <span class="inline-flex items-center gap-1.5 px-2 sm:px-2.5 py-0.5 sm:py-1 rounded-xl text-[10.5px] sm:text-[11px] font-bold bg-slate-50 text-slate-700 border border-slate-200" title="Sub-Index for CO">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> CO: <strong id="sub-co-val" class="font-mono font-extrabold">--</strong>
          </span>
          <span class="inline-flex items-center gap-1.5 px-2 sm:px-2.5 py-0.5 sm:py-1 rounded-xl text-[10.5px] sm:text-[11px] font-bold bg-slate-50 text-slate-700 border border-slate-200" title="Sub-Index for O3">
            <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span> O₃: <strong id="sub-o3-val" class="font-mono font-extrabold">--</strong>
          </span>
        </div>

        <div class="flex items-center gap-2 flex-wrap text-xs text-slate-400 font-medium">
          <div class="flex items-center gap-1.5">
            <i data-lucide="thermometer" class="w-3.5 h-3.5 text-slate-400"></i>
            <span>Ambient: <strong id="forecast-hero-temp" class="text-slate-600 font-mono">--°C</strong>, <strong id="forecast-hero-humidity" class="text-slate-600 font-mono">--%</strong> Humidity</span>
          </div>
          <span class="hidden sm:inline text-slate-300">•</span>
          <div id="forecast-hero-rain-badge" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg bg-blue-50 text-blue-700 border border-blue-200 text-[11px] font-bold">
            <span>🌧️</span> Rain: <strong id="forecast-hero-rain" class="font-mono">-- mm</strong>
            <span id="forecast-hero-rain-risk" class="text-[9.5px] uppercase font-mono px-1 rounded bg-blue-100 text-blue-800">--</span>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- ── 3.5. Predicted Model Parameters (Outputs) Grid ── -->
  <div class="bg-white rounded-[24px] p-4 sm:p-6 border border-slate-200 shadow-[0_4px_24px_rgba(15,23,42,0.05)]">
    <div class="flex items-center justify-between mb-4 px-1">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-[#e6fcf9] text-[#00bfa5] flex items-center justify-center">
          <i data-lucide="activity" class="w-4 h-4"></i>
        </div>
        <h2 class="text-base font-extrabold text-slate-900 m-0 tracking-tight">Predicted Parameters (Next 24 Hours)</h2>
      </div>
    </div>

    <!-- Grid of the 8 predicted parameters -->
    <div id="predicted-parameters-grid" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2.5 sm:gap-3">
      <div class="text-xs text-slate-400 py-6 text-center col-span-full">Loading predicted parameters...</div>
    </div>
  </div>

  <!-- ── 4. Hourly Forecast: Edge-to-Edge Horizontally Scrolling Reel ── -->
  <div class="bg-white rounded-[20px] sm:rounded-[24px] p-3.5 sm:p-6 border border-slate-200 shadow-[0_4px_24px_rgba(15,23,42,0.05)]">
    
    <div class="flex items-center justify-between gap-2 mb-3 px-1">
      <div class="flex items-center gap-2 min-w-0">
        <div class="w-8 h-8 rounded-lg bg-[#e6fcf9] text-[#00bfa5] flex items-center justify-center shrink-0">
          <i data-lucide="clock" class="w-4 h-4"></i>
        </div>
        <div class="min-w-0">
          <h2 id="hourly-forecast-heading" class="text-sm sm:text-base font-extrabold text-slate-900 m-0 tracking-tight truncate">Hourly Forecast (Next 24 Hours)</h2>
          <p class="text-[11px] sm:text-xs text-slate-400 m-0 truncate">Swipe horizontally to track predicted temperature, humidity, and AQI</p>
        </div>
      </div>
      <div class="flex items-center gap-1.5 shrink-0">
        <button type="button" onclick="scrollHourlyReel(-1)" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full border border-slate-200 bg-white hover:bg-slate-50 active:bg-slate-100 text-slate-600 flex items-center justify-center shadow-xs transition-all active:scale-95 cursor-pointer" title="Scroll left">
          <i data-lucide="chevron-left" class="w-4 h-4"></i>
        </button>
        <button type="button" onclick="scrollHourlyReel(1)" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full border border-slate-200 bg-white hover:bg-slate-50 active:bg-slate-100 text-slate-600 flex items-center justify-center shadow-xs transition-all active:scale-95 cursor-pointer" title="Scroll right">
          <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </button>
      </div>
    </div>

    <!-- Edge-to-Edge Horizontal Reel with High-Performance Smooth Scrolling -->
    <div id="hourly-forecast-reel" class="hourly-forecast-reel flex gap-3 overflow-x-auto py-2 px-1">
      <!-- Populated dynamically by JS with smooth cards -->
      <div class="text-xs text-slate-400 py-6 text-center w-full">Loading hourly predictions...</div>
    </div>

  </div>

  <!-- ── 5. Rainfall Prediction Probability Scale & Status Legend ── -->
  <div class="bg-white rounded-[20px] sm:rounded-[24px] p-3.5 sm:p-6 border border-slate-200 shadow-[0_4px_24px_rgba(15,23,42,0.05)]">
    
    <div class="flex items-center gap-2.5 mb-3.5 sm:mb-4 px-1">
      <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
        <i data-lucide="cloud-rain" class="w-4 h-4"></i>
      </div>
      <div>
        <h2 class="text-sm sm:text-base font-extrabold text-slate-900 m-0 tracking-tight flex items-center gap-2">
          <span>Rainfall Prediction Scale</span>
          <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200">5-Tier Standard</span>
        </h2>
        <p class="text-[11px] sm:text-xs text-slate-400 m-0">Operational classification standard for machine-learned precipitation probability forecasts</p>
      </div>
    </div>

    <!-- Responsive Table Card -->
    <div class="overflow-hidden border border-slate-200 rounded-2xl bg-white shadow-xs">
      <div class="w-full overflow-x-auto sm:overflow-x-visible">
        <table class="rain-scale-table w-full text-left border-collapse sm:min-w-[540px]">
          <thead>
            <tr class="bg-slate-50/90 border-b border-slate-200 text-[11.5px] uppercase tracking-wider font-extrabold text-slate-500 font-mono">
              <th class="py-3.5 px-4 w-[28%]">Probability Range</th>
              <th class="py-3.5 px-4 w-[32%]">Status Badge</th>
              <th class="py-3.5 px-4 w-[40%]">Meteorological Advisory</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
            <!-- Tier 1: 0% - 25% -->
            <tr id="rain-scale-tier-1" class="rain-scale-row transition-all hover:bg-slate-50/70">
              <td class="py-3.5 px-4 font-mono font-extrabold text-slate-900 text-sm whitespace-nowrap">
                <span class="inline-flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                  0% – 25%
                </span>
              </td>
              <td class="py-3.5 px-4">
                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-mono font-semibold bg-[#f1f5f9] text-[#334155] border border-[#cbd5e1]/70 shadow-xs">
                  Dry / Clear
                </span>
              </td>
              <td class="py-3.5 px-4 text-slate-500 text-[11.5px] leading-relaxed">
                Dry / clear atmospheric conditions; minimal or zero probability of rain.
              </td>
            </tr>

            <!-- Tier 2: 25% - 49% -->
            <tr id="rain-scale-tier-2" class="rain-scale-row transition-all hover:bg-slate-50/70">
              <td class="py-3.5 px-4 font-mono font-extrabold text-slate-900 text-sm whitespace-nowrap">
                <span class="inline-flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-cyan-500"></span>
                  25% – 49%
                </span>
              </td>
              <td class="py-3.5 px-4">
                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-mono font-semibold bg-[#f1f5f9] text-[#334155] border border-[#cbd5e1]/70 shadow-xs">
                  Mostly Dry
                </span>
              </td>
              <td class="py-3.5 px-4 text-slate-500 text-[11.5px] leading-relaxed">
                Overcast skies or slight moisture; precipitation remains unlikely.
              </td>
            </tr>

            <!-- Tier 3: 50% - 69% -->
            <tr id="rain-scale-tier-3" class="rain-scale-row transition-all hover:bg-slate-50/70">
              <td class="py-3.5 px-4 font-mono font-extrabold text-slate-900 text-sm whitespace-nowrap">
                <span class="inline-flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                  50% – 69%
                </span>
              </td>
              <td class="py-3.5 px-4">
                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-mono font-semibold bg-[#f1f5f9] text-[#334155] border border-[#cbd5e1]/70 shadow-xs">
                  Chance of Rain
                </span>
              </td>
              <td class="py-3.5 px-4 text-slate-500 text-[11.5px] leading-relaxed">
                Scattered showers or drizzle possible; consider carrying an umbrella.
              </td>
            </tr>

            <!-- Tier 4: 70% - 84% -->
            <tr id="rain-scale-tier-4" class="rain-scale-row transition-all hover:bg-slate-50/70">
              <td class="py-3.5 px-4 font-mono font-extrabold text-slate-900 text-sm whitespace-nowrap">
                <span class="inline-flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                  70% – 84%
                </span>
              </td>
              <td class="py-3.5 px-4">
                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-mono font-semibold bg-[#f1f5f9] text-[#334155] border border-[#cbd5e1]/70 shadow-xs">
                  Rain Likely
                </span>
              </td>
              <td class="py-3.5 px-4 text-slate-500 text-[11.5px] leading-relaxed">
                High confidence of steady precipitation; wet ground and slippery pathways anticipated.
              </td>
            </tr>

            <!-- Tier 5: 85% - 100% -->
            <tr id="rain-scale-tier-5" class="rain-scale-row transition-all hover:bg-slate-50/70">
              <td class="py-3.5 px-4 font-mono font-extrabold text-slate-900 text-sm whitespace-nowrap">
                <span class="inline-flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-red-500"></span>
                  85% – 100%
                </span>
              </td>
              <td class="py-3.5 px-4">
                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-mono font-semibold bg-[#f1f5f9] text-[#334155] border border-[#cbd5e1]/70 shadow-xs">
                  Heavy Rain Alert
                </span>
              </td>
              <td class="py-3.5 px-4 text-slate-500 text-[11.5px] leading-relaxed">
                Torrential downpour or high-volume precipitation alert; campus drainage runoff risk.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>

</div>

<!-- ── Custom Styles for Forecast Page ── -->
<style>
/* Horizontal Reel Custom Styling (High Performance, Smooth Momentum) */
.hourly-forecast-reel {
  display: flex !important;
  flex-direction: row !important;
  flex-wrap: nowrap !important;
  overflow-x: auto !important;
  scroll-behavior: smooth !important;
  -webkit-overflow-scrolling: touch !important;
  overscroll-behavior-x: contain !important;
  scrollbar-width: none !important;
  gap: 12px !important;
  padding: 8px 6px 14px 6px !important;
  width: 100% !important;
  box-sizing: border-box !important;
  cursor: grab;
  user-select: none;
  touch-action: pan-x pan-y;
}
.hourly-forecast-reel:active {
  cursor: grabbing;
}
.hourly-forecast-reel::-webkit-scrollbar {
  display: none !important;
  width: 0 !important;
  height: 0 !important;
}
.hourly-card {
  flex: 0 0 136px !important;
  min-width: 136px !important;
  max-width: 136px !important;
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  padding: 13px 10px !important;
  border-radius: 20px !important;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  box-sizing: border-box !important;
  will-change: transform;
  transform: translateZ(0);
  transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease, transform 0.15s ease;
  user-select: none;
}
.hourly-card:hover {
  border-color: #00bfa5;
  background: #ffffff;
  box-shadow: 0 6px 18px rgba(0, 191, 165, 0.14);
  transform: translateY(-2px);
}
.hourly-card.active-hour {
  background: #ffffff !important;
  border-color: #00bfa5 !important;
  box-shadow: 0 8px 24px rgba(0, 191, 165, 0.2) !important;
}
.hourly-card.active-hour .hour-label {
  color: #00bfa5 !important;
  font-weight: 800 !important;
}
@media (max-width: 640px) {
  .hourly-card {
    flex: 0 0 118px !important;
    min-width: 118px !important;
    max-width: 118px !important;
    padding: 11px 8px !important;
    border-radius: 16px !important;
  }
}

/* Accordion Row Styling */
.day-row-container {
  border-radius: 18px;
  border: 1.5px solid #e2e8f0;
  background: #ffffff;
  transition: all 0.2s ease;
  overflow: hidden;
}
.day-row-container:hover {
  border-color: #cbd5e1;
}
.day-row-header {
  padding: 13px 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  -webkit-tap-highlight-color: transparent;
}
.day-row-container.open {
  border-color: #00bfa5;
  box-shadow: 0 4px 18px rgba(0, 191, 165, 0.1);
}
.day-row-drawer {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.35s cubic-bezier(0.16, 1, 0.3, 1), padding 0.25s ease;
  background: #f8fafc;
}
.day-row-container.open .day-row-drawer {
  max-height: 500px;
  padding: 14px 16px 16px;
  border-top: 1px solid #e2e8f0;
}

/* Min-Max Temperature Slider Bar */
.temp-slider-track {
  width: 110px;
  height: 6px;
  border-radius: 999px;
  background: #e2e8f0;
  position: relative;
  overflow: hidden;
}
@media (max-width: 640px) {
  .temp-slider-track {
    width: 80px;
  }
}
.temp-slider-fill {
  position: absolute;
  top: 0;
  bottom: 0;
  border-radius: 999px;
  background: linear-gradient(90deg, #38bdf8 0%, #f59e0b 50%, #ef4444 100%);
}

/* Rainfall Scale Table Active Row Highlighting & Mobile Adaptation */
.rain-scale-row {
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.rain-scale-row.active-rain-tier {
  background-color: #f0fdf4 !important;
}
.rain-scale-row.active-rain-tier td:first-child {
  border-left: 3.5px solid #00bfa5 !important;
  padding-left: 14px !important;
}
.rain-scale-row.active-rain-tier td {
  font-weight: 700;
}

@media (max-width: 640px) {
  .rain-scale-table {
    display: block !important;
    width: 100% !important;
    min-width: 0 !important;
  }
  .rain-scale-table thead {
    display: none !important;
  }
  .rain-scale-table tbody {
    display: flex !important;
    flex-direction: column !important;
    width: 100% !important;
    gap: 0 !important;
  }
  .rain-scale-row {
    display: flex !important;
    flex-wrap: wrap !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 12px 14px !important;
    border-bottom: 1px solid #f1f5f9 !important;
    width: 100% !important;
    box-sizing: border-box !important;
    transition: background 0.15s ease, border-color 0.15s ease;
  }
  .rain-scale-row td {
    border: none !important;
  }
  .rain-scale-row td:nth-child(1) {
    order: 1 !important;
    width: auto !important;
    padding: 0 !important;
    font-size: 13.5px !important;
  }
  .rain-scale-row td:nth-child(2) {
    order: 2 !important;
    width: auto !important;
    padding: 0 !important;
    text-align: right !important;
  }
  .rain-scale-row td:nth-child(3) {
    order: 3 !important;
    width: 100% !important;
    padding: 6px 0 0 0 !important;
    font-size: 11.5px !important;
    line-height: 1.45 !important;
    color: #64748b !important;
    font-weight: 400 !important;
  }
  .rain-scale-row.active-rain-tier {
    border-left: 4px solid #00bfa5 !important;
    background-color: #f0fdf4 !important;
  }
  .rain-scale-row.active-rain-tier td:first-child {
    border-left: none !important;
    padding-left: 0 !important;
  }
  .rain-scale-row.active-rain-tier td:nth-child(3) {
    color: #065f46 !important;
    font-weight: 500 !important;
  }
}
</style>

<!-- ── JavaScript Forecast Model Engine ── -->
<script>
(function() {
    let trainedModelData = <?php echo $preloadedForecastJson ?: 'null'; ?>;

    // AQI Colors and Categories
    function getAqiBadge(aqi) {
        if (aqi === null || aqi === undefined || aqi === '--' || isNaN(Number(aqi))) {
            return { label: 'Standby', bg: '#f8fafc', text: '#64748b', border: '#cbd5e1', dot: '#94a3b8' };
        }
        const v = Number(aqi);
        if (v <= 50) return { label: 'Good', bg: '#f0fdf4', text: '#15803d', border: '#bbf7d0', dot: '#22c55e' };
        if (v <= 100) return { label: 'Satisfactory', bg: '#f7fee7', text: '#3f6212', border: '#bef264', dot: '#84cc16' };
        if (v <= 200) return { label: 'Moderate', bg: '#fffbeb', text: '#92400e', border: '#fde68a', dot: '#eab308' };
        if (v <= 300) return { label: 'Poor', bg: '#fff7ed', text: '#9a3412', border: '#fed7aa', dot: '#f97316' };
        if (v <= 400) return { label: 'Very Poor', bg: '#fef2f2', text: '#991b1b', border: '#fca5a5', dot: '#ef4444' };
        return { label: 'Severe', bg: '#faf5ff', text: '#6b21a8', border: '#e9d5ff', dot: '#a855f7' };
    }

    // 5-Tier Rainfall Probability Scale & Status Badges Standard
    function getRainStatus(prob) {
        if (prob === null || prob === undefined || prob === '--' || isNaN(Number(prob))) {
            return {
                tier: 1,
                range: '0% – 25%',
                badge: 'Dry / Clear',
                color: '#10b981',
                bg: '#ecfdf5',
                border: '#a7f3d0',
                text: '#065f46',
                chipBg: '#d1fae5',
                chipText: '#047857',
                icon: '☀️',
                summary: 'Dry and clear atmospheric conditions; minimal or zero precipitation expected.'
            };
        }
        const p = Math.round(Number(prob));
        if (p >= 85) {
            return {
                tier: 5,
                range: '85% – 100%',
                badge: 'Heavy Rain Alert',
                color: '#ef4444',
                bg: '#fef2f2',
                border: '#fecaca',
                text: '#991b1b',
                chipBg: '#fee2e2',
                chipText: '#b91c1c',
                icon: '⛈️',
                summary: 'Torrential downpour or high-volume precipitation alert; campus drainage runoff risk.'
            };
        } else if (p >= 70) {
            return {
                tier: 4,
                range: '70% – 84%',
                badge: 'Rain Likely',
                color: '#f97316',
                bg: '#fff7ed',
                border: '#fed7aa',
                text: '#9a3412',
                chipBg: '#ffedd5',
                chipText: '#c2410c',
                icon: '🌧️',
                summary: 'High confidence of steady precipitation; wet ground conditions anticipated.'
            };
        } else if (p >= 50) {
            return {
                tier: 3,
                range: '50% – 69%',
                badge: 'Chance of Rain',
                color: '#3b82f6',
                bg: '#eff6ff',
                border: '#bfdbfe',
                text: '#1e40af',
                chipBg: '#dbeafe',
                chipText: '#1d4ed8',
                icon: '🌦️',
                summary: 'Scattered showers or drizzle possible; consider carrying an umbrella.'
            };
        } else if (p >= 25) {
            return {
                tier: 2,
                range: '25% – 49%',
                badge: 'Mostly Dry',
                color: '#06b6d4',
                bg: '#ecfeff',
                border: '#a5f3fc',
                text: '#155e75',
                chipBg: '#cffafe',
                chipText: '#0e7490',
                icon: '⛅',
                summary: 'Overcast skies or slight moisture; precipitation remains unlikely.'
            };
        } else {
            return {
                tier: 1,
                range: '0% – 25%',
                badge: 'Dry / Clear',
                color: '#10b981',
                bg: '#ecfdf5',
                border: '#a7f3d0',
                text: '#065f46',
                chipBg: '#d1fae5',
                chipText: '#047857',
                icon: '☀️',
                summary: 'Dry / clear atmospheric conditions; minimal or zero probability of rain.'
            };
        }
    }

    // Toggle 7-Day Accordion Row
    window.toggleDayAccordion = function(dayIndex) {
        const row = document.getElementById('day-row-' + dayIndex);
        const icon = document.getElementById('day-chevron-' + dayIndex);
        if (!row) return;
        const isOpen = row.classList.contains('open');
        // Close all other rows for clean single-accordion focus
        document.querySelectorAll('.day-row-container').forEach(r => {
            r.classList.remove('open');
            const ch = r.querySelector('.day-chevron');
            if (ch) ch.style.transform = 'rotate(0deg)';
        });
        if (!isOpen) {
            row.classList.add('open');
            if (icon) icon.style.transform = 'rotate(180deg)';
        }
        if (window.lucide) lucide.createIcons();
    };

    // Render the 24 hourly predictions exactly as generated by the scheduled cron job.
    // Static forecast output remains stable across project runs/page views without synthetic hour shifting.
    function generateHourlyData() {
        if (trainedModelData && trainedModelData.hourly && Array.isArray(trainedModelData.hourly) && trainedModelData.hourly.length > 0) {
            const now = new Date();
            // Start strictly from the upcoming next hour (e.g. if now is 7:20 PM, predict 8 PM, 9 PM, 10 PM...)
            const currentHourFloor = new Date(now.getFullYear(), now.getMonth(), now.getDate(), now.getHours(), 0, 0, 0).getTime();

            // Filter out any hours that are in the past or currently in-progress
            let validList = trainedModelData.hourly.filter(item => {
                if (item.iso) {
                    const itemTime = new Date(item.iso).getTime();
                    return itemTime > currentHourFloor;
                }
                return true;
            });

            if (!validList || validList.length === 0) {
                validList = trainedModelData.hourly;
            }

            return validList.map((item, idx) => {
                let timeLabel = item.label;

                if (!timeLabel && item.iso) {
                    const dt = new Date(item.iso);
                    timeLabel = dt.toLocaleTimeString('en-US', { timeZone: 'Asia/Kolkata', hour: 'numeric', hour12: true });
                } else if (!timeLabel && typeof item.hour === 'number') {
                    const h = item.hour % 24;
                    const ampm = h >= 12 ? 'PM' : 'AM';
                    const h12 = h % 12 || 12;
                    timeLabel = `${h12} ${ampm}`;
                }

                return {
                    ...item,
                    index: idx,
                    isNext: (idx === 0),
                    isNow: false,
                    label: timeLabel || `+${idx+1}h`
                };
            });
        }

        const now = new Date();
        const currentHour = now.getHours();
        const hours = [];

        // Fallback: strictly predict upcoming 24 hours starting from next hour (i = 1..24)
        for (let i = 1; i <= 24; i++) {
            const h = (currentHour + i) % 24;
            const hourDate = new Date(now.getTime() + (i * 3600000));
            const timeLabel = hourDate.toLocaleTimeString('en-US', { hour: 'numeric', hour12: true });

            hours.push({
                index: i - 1,
                hour: h,
                label: timeLabel,
                temp: '--',
                aqi: '--',
                humidity: '--',
                pm25: '--',
                pm10: '--',
                co: '--',
                no2: '--',
                o3: '--',
                icon: '⏳',
                isNow: false,
                isNext: (i === 1)
            });
        }
        return hours;
    }

    // Generate upcoming 24-hour day phases structure
    function generateTimelinePhases() {
        if (trainedModelData && trainedModelData.phases) {
            return trainedModelData.phases;
        }

        const defaultPhases = [
            { name: 'Late Afternoon / Evening', hours: '4 PM – 9 PM', icon: '🌅' },
            { name: 'Night to Midnight', hours: '10 PM – 3 AM', icon: '🌙' },
            { name: 'Early Morning Transition', hours: '4 AM – 9 AM', icon: '🌤️' },
            { name: 'Daytime Afternoon Peak', hours: '10 AM – 3 PM', icon: '☀️' }
        ];

        return defaultPhases.map((p, idx) => ({
            index: idx,
            phaseName: p.name,
            hoursSpan: p.hours,
            minTemp: '--',
            maxTemp: '--',
            leftPct: '0',
            widthPct: '0',
            condition: 'Awaiting Model',
            icon: p.icon,
            aqi: '--',
            dominant: '--',
            humidity: '--',
            pm25: '--',
            pm10: '--',
            co: '--',
            no2: '--',
            o3: '--',
            advice: 'Predicted chemical and atmospheric parameters will generate once the forecast model finishes inference.'
        }));
    }

    // Render Function
    function renderForecastUI() {
        if (!document.getElementById('hourly-forecast-heading') && !document.getElementById('forecast-hero-aqi')) return;
        const hourly = generateHourlyData();
        const phases = generateTimelinePhases();

        // Update heading with active remaining hour count
        // Update heading
        const headingEl = document.getElementById('hourly-forecast-heading');
        if (headingEl && hourly && hourly.length > 0) {
            headingEl.innerText = `Hourly Forecast (24-Hour Cron Model Prediction)`;
        }

        // 1. Date and Sync time
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' });
        const dateEl = document.getElementById('forecast-date-str');
        if (dateEl) dateEl.innerText = dateStr;
        const syncEl = document.getElementById('forecast-sync-time');
        if (syncEl) syncEl.innerText = (trainedModelData && trainedModelData.generated_at) ? 'Cron Synced: ' + trainedModelData.generated_at : (trainedModelData ? 'Cron Model Active' : 'Model Standby');

        // Store hourly data globally for interactive reel clicks
        window.__currentHourly = hourly;
        if (typeof window.__selectedHourIdx !== 'number' || window.__selectedHourIdx >= hourly.length) {
            window.__selectedHourIdx = 0;
        }

        // 2. Helper to synchronize Hero Card & 7 Predicted Parameters with any selected timestamp
        function updateHeroAndParams(curHour, isNext) {
            if (!curHour) return;
            const b = getAqiBadge(curHour.aqi);

            // Hero AQI
            const heroAqi = document.getElementById('forecast-hero-aqi');
            if (heroAqi) {
                heroAqi.innerText = curHour.aqi !== undefined ? curHour.aqi : '--';
                heroAqi.style.color = b.color || b.text;
            }

            // Category Badge
            const catBadge = document.getElementById('forecast-hero-category-badge');
            if (catBadge) {
                catBadge.innerText = (curHour.category || b.label).toUpperCase();
                catBadge.style.backgroundColor = b.bg;
                catBadge.style.borderColor = b.border;
                catBadge.style.color = b.text;
            }

            // Target Hour Badge (shows only the hour, e.g. "8 PM")
            const targetWinEl = document.getElementById('forecast-hero-target-window');
            if (targetWinEl && curHour.label) {
                targetWinEl.innerText = curHour.label;
            }

            // Dominant Pollutant
            const domEl = document.getElementById('forecast-hero-dominant');
            if (domEl) {
                domEl.innerHTML = `Dominant Pollutant: <strong class="text-slate-900 font-mono">${curHour.dominant || '--'}</strong> · Max Sub-Index Rule`;
            }

            // Sub-Indices
            const s = curHour.sub_indices || {};
            const setVal = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.innerText = val !== undefined ? val : '--';
            };
            setVal('sub-pm25-val', s['PM2.5'] !== undefined ? s['PM2.5'] : s['pm25']);
            setVal('sub-pm10-val', s['PM10'] !== undefined ? s['PM10'] : s['pm10']);
            setVal('sub-no2-val', s['NO₂'] !== undefined ? s['NO₂'] : (s['no2'] !== undefined ? s['no2'] : s['NO2']));
            setVal('sub-co-val', s['CO'] !== undefined ? s['CO'] : s['co']);
            setVal('sub-o3-val', s['O₃'] !== undefined ? s['O₃'] : (s['o3'] !== undefined ? s['o3'] : s['O3']));

            // Ambient Temperature & Humidity
            const heroTemp = document.getElementById('forecast-hero-temp');
            if (heroTemp) heroTemp.innerText = (curHour.temp !== undefined ? Math.round(curHour.temp) : '--') + '°C';

            const heroHum = document.getElementById('forecast-hero-humidity');
            if (heroHum) heroHum.innerText = (curHour.humidity !== undefined ? curHour.humidity : '--') + '%';

            // Selected Hour's Rainfall Metric in Hero Card
            const heroRain = document.getElementById('forecast-hero-rain');
            const heroRainRisk = document.getElementById('forecast-hero-rain-risk');
            const heroRainBadge = document.getElementById('forecast-hero-rain-badge');
            const rf = trainedModelData.rainfall;

            let hourRainProb = 0;
            let hourRainMm = 0.0;

            if (curHour.rain_prob !== undefined && curHour.rain_prob !== null) {
                hourRainProb = Math.round(curHour.rain_prob);
                hourRainMm = curHour.rain_mm ?? 0.0;
            } else if (rf && rf.hourly && curHour.index !== undefined && rf.hourly[curHour.index]) {
                const match = rf.hourly[curHour.index];
                hourRainProb = Math.round(match.rain_prob_pct ?? 0);
                hourRainMm = match.rain_mm ?? 0.0;
            } else if (trainedModelData.parameters?.rainfall) {
                hourRainMm = trainedModelData.parameters.rainfall.current ?? 0.0;
                hourRainProb = Math.round(trainedModelData.parameters.rainfall.prob ?? (hourRainMm > 0 ? 80 : 0));
            }

            const rainStatus = getRainStatus(hourRainProb);

            if (heroRain) {
                heroRain.innerText = `${hourRainMm} mm`;
            }
            if (heroRainRisk) {
                heroRainRisk.innerText = `${hourRainProb}%`;
            }

            // Highlight active row in Section 5: Rainfall Prediction Scale table
            document.querySelectorAll('.rain-scale-row').forEach(row => {
                row.classList.remove('active-rain-tier');
            });
            const activeRow = document.getElementById(`rain-scale-tier-${rainStatus.tier}`);
            if (activeRow) {
                activeRow.classList.add('active-rain-tier');
            }

            // 3. Render 8 Predicted Model Parameters Grid for this timestamp
            const paramsGrid = document.getElementById('predicted-parameters-grid');
            if (paramsGrid && trainedModelData && trainedModelData.parameters) {
                const p = trainedModelData.parameters;
                const curSub = curHour.sub_indices || {};
                const rfInfo = trainedModelData.rainfall;
                const curRainMm = hourRainMm;
                const curRainChance = hourRainProb;

                const whoSafeLimits = {
                    pm25: '≤15 µg/m³',
                    pm10: '≤45 µg/m³',
                    no2:  '≤25 µg/m³',
                    co:   '≤4 mg/m³',
                    o3:   '≤100 µg/m³'
                };

                const formatCurVal = (val, key) => {
                    if (val === null || val === undefined || isNaN(val)) return '--';
                    if (['pm25', 'pm10', 'no2', 'co', 'o3'].includes(key)) {
                        return Number(val).toFixed(3);
                    }
                    return val;
                };

                const items = [
                    { key: 'pm25', name: 'PM2.5', icon: '💨', current: formatCurVal(curHour.pm25, 'pm25'), subindex: curSub['PM2.5'] ?? curSub['pm25'] ?? p.pm25.current_subindex, unit: 'µg/m³', range: `${p.pm25.min} - ${p.pm25.max}`, mean: p.pm25.mean, limit: whoSafeLimits.pm25 },
                    { key: 'pm10', name: 'PM10', icon: '🌫️', current: formatCurVal(curHour.pm10, 'pm10'), subindex: curSub['PM10'] ?? curSub['pm10'] ?? p.pm10.current_subindex, unit: 'µg/m³', range: `${p.pm10.min} - ${p.pm10.max}`, mean: p.pm10.mean, limit: whoSafeLimits.pm10 },
                    { key: 'no2', name: 'NO₂', icon: '🚦', current: formatCurVal(curHour.no2, 'no2'), subindex: curSub['NO₂'] ?? curSub['no2'] ?? curSub['NO2'] ?? p.no2.current_subindex, unit: 'µg/m³', range: `${p.no2.min} - ${p.no2.max}`, mean: p.no2.mean, limit: whoSafeLimits.no2 },
                    { key: 'co', name: 'CO', icon: '🏭', current: formatCurVal(curHour.co, 'co'), subindex: curSub['CO'] ?? curSub['co'] ?? p.co.current_subindex, unit: 'mg/m³', range: `${p.co.min} - ${p.co.max}`, mean: p.co.mean, limit: whoSafeLimits.co },
                    { key: 'o3', name: 'O₃', icon: '☀️', current: formatCurVal(curHour.o3, 'o3'), subindex: curSub['O₃'] ?? curSub['o3'] ?? curSub['O3'] ?? p.o3.current_subindex, unit: 'µg/m³', range: `${p.o3.min} - ${p.o3.max}`, mean: p.o3.mean, limit: whoSafeLimits.o3 },
                    { key: 'temp', name: 'Temp', icon: '🌡️', current: curHour.temp, subindex: null, unit: '°C', range: `${p.temp.min}° - ${p.temp.max}°`, mean: `${p.temp.mean}°`, limit: 'Campus Ambient' },
                    { key: 'humidity', name: 'Humidity', icon: '💧', current: curHour.humidity, subindex: null, unit: '%', range: `${p.humidity.min}% - ${p.humidity.max}%`, mean: `${p.humidity.mean}%`, limit: 'Comfort 40-70%' },
                    { key: 'rainfall', name: 'Rainfall', icon: '🌧️', current: curRainChance, subindex: null, unit: '%', rainMm: curRainMm, range: `${rfInfo?.total_24h_rainfall_mm ?? p.rainfall?.total_24h ?? 0} mm/24h`, mean: rfInfo ? `Peak: ${rfInfo.peak_hourly_rate_mm} mm/h` : '0 mm/h', limit: `${curRainMm} mm` }
                ];

                paramsGrid.innerHTML = items.map((it) => `
                    <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-2.5 sm:p-3 flex flex-col justify-between hover:border-teal-500/40 transition-all shadow-xs">
                      <div class="flex items-center justify-between mb-1 sm:mb-1.5">
                        <span class="text-xs font-bold text-slate-700 flex items-center gap-1">
                          <span>${it.icon}</span> <span>${it.name}</span>
                        </span>
                        ${it.subindex !== null && it.subindex !== undefined ? `
                          <span class="text-[9px] font-mono font-extrabold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">
                            Sub: ${it.subindex}
                          </span>
                        ` : `
                          <span class="text-[9.5px] font-mono font-bold text-slate-400">${it.unit}</span>
                        `}
                      </div>
                      <div class="my-0.5 sm:my-1">
                        <div class="text-base sm:text-xl font-extrabold font-mono text-slate-900 leading-none">${it.current} <span class="text-[11px] sm:text-xs font-normal text-slate-400">${it.unit}</span></div>
                        <div class="text-[9.5px] sm:text-[10px] text-slate-400 mt-1 truncate">
                          ${it.key === 'rainfall'
                            ? `<span>Precipitation: <strong class="text-slate-500 font-mono">${it.rainMm} mm</strong></span>`
                            : `<span>WHO: <strong class="text-slate-500 font-mono">${it.limit}</strong></span>`
                          }
                        </div>
                      </div>
                    </div>
                `).join('');
            }
        }

        // Interactive function to view any timestamp from the reel in the Hero & Parameter cards
        window.selectForecastHour = function(idx) {
            if (!window.__currentHourly || !window.__currentHourly[idx]) return;
            window.__selectedHourIdx = idx;
            updateHeroAndParams(window.__currentHourly[idx], idx === 0);

            // Update active styling on the horizontal reel cards
            document.querySelectorAll('#hourly-forecast-reel .hourly-card').forEach((card, cIdx) => {
                if (cIdx === idx) {
                    card.classList.add('active-hour');
                } else {
                    card.classList.remove('active-hour');
                }
            });
        };

        // Initialize hero with selected timestamp (default: index 0 = NEXT upcoming timestamp)
        if (!trainedModelData) {
            const heroAqi = document.getElementById('forecast-hero-aqi');
            if (heroAqi) heroAqi.innerText = '--';
            const aqiLow = document.getElementById('forecast-aqi-low');
            if (aqiLow) aqiLow.innerText = '--';
            const aqiHigh = document.getElementById('forecast-aqi-high');
            if (aqiHigh) aqiHigh.innerText = '--';
        } else if (trainedModelData.hero) {
            const selectedItem = (hourly && hourly.length > 0) ? hourly[window.__selectedHourIdx] : trainedModelData.hero;
            updateHeroAndParams(selectedItem, window.__selectedHourIdx === 0);

            // Top Status Chip
            const topChip = document.getElementById('forecast-model-status-chip');
            const isStandby = (trainedModelData.status === 'standby') || (!trainedModelData.hero || trainedModelData.hero.aqi === null);

            if (topChip) {
                if (isStandby) {
                    topChip.className = "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10.5px] sm:text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200 font-mono";
                    topChip.innerHTML = `<span class="w-2 h-2 rounded-full bg-amber-400"></span> AWAITING NEW MODEL · STANDBY`;
                } else {
                    topChip.className = "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10.5px] sm:text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 font-mono";
                    topChip.innerHTML = `<span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> MODEL ACTIVE · INFERENCE LIVE`;
                }
            }
            const topSubtext = document.getElementById('forecast-model-subtext');
            if (topSubtext) {
                topSubtext.innerText = isStandby ? 'Ready for new model deployment in forcast/' : 'Dual-Engine SAPE: AQI (48h Window) + Rainfall Wavelet LSTM (72h Window from WEATHER_NODE1)';
            }



            const syncEl = document.getElementById('forecast-sync-time');
            if (syncEl) syncEl.innerText = `Active`;
            const syncDot = document.getElementById('forecast-sync-dot');
            if (syncDot) syncDot.className = "inline-block w-2 h-2 rounded-full bg-emerald-500";
        }

        // 4. Render Hourly Horizontal Reel (Clickable Cards to Inspect Any Timestamp)
        const reel = document.getElementById('hourly-forecast-reel');
        if (reel) {
            reel.innerHTML = hourly.map((item, idx) => {
                const b = getAqiBadge(item.aqi);
                const isSelected = (idx === window.__selectedHourIdx);
                const isFirst = (idx === 0);

                // Rainfall probability calculation (percentage)
                const rfInfo = trainedModelData?.rainfall;
                const rainChanceRaw = (item.rain_prob !== undefined && item.rain_prob !== null)
                    ? item.rain_prob
                    : ((item.rain_prob_pct !== undefined && item.rain_prob_pct !== null)
                        ? item.rain_prob_pct
                        : (rfInfo?.hourly && rfInfo.hourly[item.index]?.rain_prob_pct !== undefined
                            ? rfInfo.hourly[item.index].rain_prob_pct
                            : (item.rain_mm && item.rain_mm > 0 ? 80 : 0)));
                const rainChance = Math.round(Number(rainChanceRaw) || 0);
                const rStatus = getRainStatus(rainChance);

                return `
                    <div class="hourly-card ${isSelected ? 'active-hour' : ''}" onclick="selectForecastHour(${idx})" style="cursor: pointer;" title="Rain Probability: ${rainChance}%">
                      <div class="hour-label text-xs sm:text-[13px] font-extrabold ${isFirst ? 'text-teal-600' : 'text-slate-600'} mb-1 whitespace-nowrap flex items-center gap-1.5">
                        <span>${item.label}</span>
                        ${isFirst ? '<span class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded-md bg-teal-50 text-teal-700 border border-teal-200">NEXT</span>' : ''}
                      </div>

                      <div class="font-mono text-lg sm:text-[21px] font-black text-slate-800 leading-tight my-1 tracking-tight">${item.temp}°C</div>

                      <div class="w-full bg-white/90 rounded-xl py-1.5 px-2 my-1 flex flex-col gap-1 border border-slate-200/70 shadow-2xs">
                        <div class="flex items-center justify-between text-[11px] font-medium text-slate-600">
                          <span class="flex items-center gap-1">💧 <span class="text-[10px] text-slate-400 font-semibold">Hum</span></span>
                          <span class="font-mono font-bold text-sky-700">${item.humidity}%</span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] font-medium text-slate-600" title="Precipitation: ${rainChance}%">
                          <span class="flex items-center gap-1">🌧️ <span class="text-[10px] text-slate-400 font-semibold">Rain</span></span>
                          <span class="font-mono font-extrabold" style="color:${rStatus.color};">${rainChance}%</span>
                        </div>
                      </div>

                      <div class="mt-1 w-full text-center py-1 rounded-xl text-[10.5px] font-extrabold font-mono shadow-2xs" style="background:${b.bg}; color:${b.text}; border:1px solid ${b.border}; white-space:nowrap;">
                        AQI ${item.aqi}
                      </div>
                    </div>
                `;
            }).join('');
        }

        // 5. Render 24-Hour Timeline Phases (Strictly Predicted Parameters)
        const acc = document.getElementById('seven-day-accordion');
        if (acc) {
            acc.innerHTML = phases.map((d, idx) => {
                const b = getAqiBadge(d.aqi);
                const isOpenDefault = idx === 0;
                return `
                    <div class="day-row-container ${isOpenDefault ? 'open' : ''}" id="day-row-${idx}">
                      <!-- Clickable Header Row -->
                      <div class="day-row-header" onclick="toggleDayAccordion(${idx})">
                        <!-- Left: Phase Name & Icon -->
                        <div class="flex items-center gap-3 min-w-[130px] sm:min-w-[160px]">
                          <span class="text-xl sm:text-2xl shrink-0 text-slate-400 font-sans">${d.icon}</span>
                          <div>
                            <div class="text-xs sm:text-[13px] font-bold text-slate-900 leading-tight">${d.phaseName}</div>
                            <div class="text-[10.5px] text-teal-600 font-semibold font-mono mt-0.5">${d.hoursSpan}</div>
                          </div>
                        </div>

                        <!-- Center: Min/Max Visual Range Slider -->
                        <div class="flex items-center gap-2 sm:gap-3 flex-1 justify-center max-w-[220px]">
                          <span class="font-mono text-xs font-bold text-slate-400">${d.minTemp}°</span>
                          <div class="temp-slider-track">
                            <div class="temp-slider-fill" style="left: ${d.leftPct}%; width: ${d.widthPct}%;"></div>
                          </div>
                          <span class="font-mono text-xs font-bold text-slate-400">${d.maxTemp}°</span>
                        </div>

                        <!-- Right: AQI Chip & Chevron -->
                        <div class="flex items-center gap-2">
                          <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-extrabold font-mono" style="background:${b.bg}; color:${b.text}; border:1px solid ${b.border};">
                            AQI ${d.aqi}
                          </span>
                          <i data-lucide="chevron-down" class="day-chevron w-4 h-4 text-slate-400 transition-transform" id="day-chevron-${idx}" style="transform: ${isOpenDefault ? 'rotate(180deg)' : 'rotate(0deg)'};"></i>
                        </div>
                      </div>

                      <!-- Expandable Accordion Drawer displaying ONLY predicted parameters -->
                      <div class="day-row-drawer">
                        <!-- Hourly Day Breakdown (Morning, Afternoon, Evening, Night) -->
                        <div class="mb-3">
                          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5 flex items-center gap-1">
                            <i data-lucide="clock" class="w-3 h-3 text-[#00bfa5]"></i>
                            <span>Hourly Progression</span>
                          </div>
                          <div class="grid grid-cols-4 gap-1.5 text-center">
                            <div class="bg-white p-2 rounded-xl border border-slate-200/80 shadow-xs">
                              <div class="text-[10px] text-slate-400 font-bold">Morning</div>
                              <div class="text-base my-0.5">🌅</div>
                              <div class="text-xs font-mono font-extrabold text-slate-600">${d.minTemp !== '--' ? Math.round(Number(d.minTemp) + 1) + '°' : '--'}</div>
                            </div>
                            <div class="bg-white p-2 rounded-xl border border-slate-200/80 shadow-xs">
                              <div class="text-[10px] text-slate-400 font-bold">Afternoon</div>
                              <div class="text-base my-0.5">☀️</div>
                              <div class="text-xs font-mono font-extrabold text-slate-600">${d.maxTemp !== '--' ? Math.round(Number(d.maxTemp)) + '°' : '--'}</div>
                            </div>
                            <div class="bg-white p-2 rounded-xl border border-slate-200/80 shadow-xs">
                              <div class="text-[10px] text-slate-400 font-bold">Evening</div>
                              <div class="text-base my-0.5">🌆</div>
                              <div class="text-xs font-mono font-extrabold text-slate-600">${d.maxTemp !== '--' ? Math.round(Number(d.maxTemp) - 1) + '°' : '--'}</div>
                            </div>
                            <div class="bg-white p-2 rounded-xl border border-slate-200/80 shadow-xs">
                              <div class="text-[10px] text-slate-400 font-bold">Night</div>
                              <div class="text-base my-0.5">🌙</div>
                              <div class="text-xs font-mono font-extrabold text-slate-600">${d.minTemp !== '--' ? Math.round(Number(d.minTemp)) + '°' : '--'}</div>
                            </div>
                          </div>
                        </div>

                        <!-- 4 Metric Cards: STRICTLY PREDICTED PARAMETERS -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3 text-xs">
                          <div class="bg-white p-2.5 rounded-xl border border-slate-200/80">
                            <div class="text-[10px] font-bold text-slate-400 uppercase">PM2.5 (Predicted)</div>
                            <div class="font-extrabold text-slate-800 mt-0.5 font-mono">${d.pm25 !== undefined ? d.pm25 + ' µg/m³' : '--'}</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">Safe Threshold: 60</div>
                          </div>
                          <div class="bg-white p-2.5 rounded-xl border border-slate-200/80">
                            <div class="text-[10px] font-bold text-slate-400 uppercase">PM10 (Predicted)</div>
                            <div class="font-extrabold text-slate-800 mt-0.5 font-mono">${d.pm10 !== undefined ? d.pm10 + ' µg/m³' : '--'}</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">Safe Threshold: 100</div>
                          </div>
                          <div class="bg-white p-2.5 rounded-xl border border-slate-200/80">
                            <div class="text-[10px] font-bold text-slate-400 uppercase">CO &amp; NO₂ (Predicted)</div>
                            <div class="font-extrabold text-slate-800 mt-0.5 font-mono">CO: ${d.co || '--'} · NO₂: ${d.no2 || '--'}</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">Concentrations</div>
                          </div>
                          <div class="bg-white p-2.5 rounded-xl border border-slate-200/80">
                            <div class="text-[10px] font-bold text-slate-400 uppercase">O₃ &amp; Humidity (Predicted)</div>
                            <div class="font-extrabold text-slate-800 mt-0.5 font-mono">O₃: ${d.o3 || '--'} · ${d.humidity || '--'}%</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">Atmosphere</div>
                          </div>
                        </div>

                        <!-- Activity Guidance based on predicted air quality -->
                        <div class="p-2.5 rounded-xl bg-slate-100 border border-slate-200 flex items-center gap-2 text-xs text-slate-600 font-medium">
                          <i data-lucide="info" class="w-4 h-4 text-slate-500 shrink-0"></i>
                          <span><strong>Health Guidance:</strong> ${d.advice}</span>
                        </div>
                      </div>

                    </div>
                `;
            }).join('');
        }

        if (window.lucide) lucide.createIcons();
        initReelDrag();
    }

    // Smooth Left/Right Reel Navigation
    window.scrollHourlyReel = function(direction) {
        const reel = document.getElementById('hourly-forecast-reel');
        if (reel) {
            reel.scrollBy({ left: direction * 280, behavior: 'smooth' });
        }
    };

    // Butter-smooth momentum mouse drag-to-scroll
    function initReelDrag() {
        const slider = document.getElementById('hourly-forecast-reel');
        if (!slider || slider.__dragInitialized) return;
        slider.__dragInitialized = true;

        let isDown = false;
        let startX = 0;
        let scrollLeft = 0;
        let isDragging = false;

        slider.addEventListener('mousedown', (e) => {
            isDown = true;
            isDragging = false;
            startX = e.pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;
        });

        window.addEventListener('mouseup', () => {
            if (isDown) {
                isDown = false;
                setTimeout(() => { isDragging = false; }, 50);
            }
        });

        slider.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - slider.offsetLeft;
            const walk = (x - startX) * 1.35;
            if (Math.abs(walk) > 4) {
                isDragging = true;
            }
            slider.scrollLeft = scrollLeft - walk;
        });

        // Intercept card clicks if user was actively dragging
        slider.addEventListener('click', (e) => {
            if (isDragging) {
                e.stopPropagation();
                e.preventDefault();
            }
        }, true);
    }

    let autoRefreshForecastTimer = null;

    // Fetch and load forecast data from backend
    async function loadForecastData(force = false) {
        try {
            const resp = await fetch(`/api/telemetry.php?action=forecast${force ? '&force=1' : ''}`);
            if (!resp.ok) throw new Error('Network error loading forecast');
            const data = await resp.json();
            if (data && data.status === 'success' && data.hourly && data.hourly.length > 0) {
                window.loadTrainedForecastModel(data);
                const syncStatusEl = document.getElementById('forecast-auto-sync-status');
                if (syncStatusEl) {
                    const now = new Date();
                    syncStatusEl.innerText = 'Updated ' + now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
                }
            }
        } catch (err) {
            console.warn('Could not load dynamic model forecast:', err);
        }
    }

    // Auto-refresh: checks clock every 15 seconds, and ONLY updates when the clock hour rolls over (e.g. 6:00 -> 7:00 -> 8:00)
    function startAutoRefreshForecast() {
        if (autoRefreshForecastTimer) clearInterval(autoRefreshForecastTimer);
        let lastRenderHour = new Date().getHours();

        autoRefreshForecastTimer = setInterval(() => {
            const currentHour = new Date().getHours();
            // Strictly trigger only when the clock transitions to a new hour
            if (currentHour !== lastRenderHour) {
                lastRenderHour = currentHour;
                renderForecastUI();
                loadForecastData(true);
            }
        }, 15000);

        window.__onForecastVisibility = () => {
            if (!document.hidden) {
                const currentHour = new Date().getHours();
                // Only refresh if the clock hour rolled over while user was away
                if (currentHour !== lastRenderHour) {
                    lastRenderHour = currentHour;
                    renderForecastUI();
                    loadForecastData(true);
                }
            }
        };
        document.addEventListener('visibilitychange', window.__onForecastVisibility);
    }

    // Clean up timer on SPA navigation
    window.__cleanupCurrentPage = function() {
        if (autoRefreshForecastTimer) {
            clearInterval(autoRefreshForecastTimer);
            autoRefreshForecastTimer = null;
        }
        if (window.__onForecastVisibility) {
            document.removeEventListener('visibilitychange', window.__onForecastVisibility);
            window.__onForecastVisibility = null;
        }
    };

    /**
     * Public Hook to inject your trained machine learning model predictions:
     * window.loadTrainedForecastModel(payload);
     */
    window.loadTrainedForecastModel = function(data) {
        trainedModelData = data;
        renderForecastUI();
    };

    // Initial render and data fetch
    function initForecastPage() {
        renderForecastUI();
        if (!trainedModelData) {
            loadForecastData(false);
        }
        startAutoRefreshForecast();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initForecastPage);
    } else {
        initForecastPage();
    }
})();
</script>
