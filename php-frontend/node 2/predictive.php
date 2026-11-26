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

if (!function_exists('smartairnet_get_active_node_id')) {
    require_once __DIR__ . '/../config/nodes.php';
}
$active_node_id = '2';
$is_node_2 = true;

$preloadedForecastJson = 'null';

// Initial AQI category theming for Node 2 (Station 2 Standby)
$init_f_bg = '#f1f5f9';
$init_f_card_bg = 'linear-gradient(135deg, #ffffff 0%, #f8fafc 50%, #f1f5f9 100%)';
$init_f_border = 'rgba(148, 163, 184, 0.38)';
$init_f_text = '#64748b';
$init_f_color = '#94a3b8';
$init_f_shadow = 'rgba(148, 163, 184, 0.15)';
$init_f_label = 'STANDBY';
$init_f_aqi = '--';
$init_f_advice = 'Predictive model is in standby for Station 2.';

$init_f_pin_x = 15;
$init_f_pin_y = 58;
?>

<div class="forecast-page-container p-2.5 sm:p-6 flex flex-col gap-3 sm:gap-6 max-w-[1200px] mx-auto text-slate-900 font-sans">

  <div class="mb-0.5 sm:mb-2">
    <h1 class="page-heading live-page-title" style="font-size: clamp(20px, 2.5vw, 24px); font-weight: 800; color: #0f172a; font-family: var(--font-sans); letter-spacing: -0.02em; margin: 0; text-align: left;">Weather Forecast</h1>
    <p class="m-0 text-slate-500 text-[11px] sm:text-sm font-medium mt-0.5">Atmospheric weather and air quality predictive forecasting for Station 2 (Standby).</p>
  </div>



  <!-- ── 3. Forecast Hero Summary Card: Predicted AQI & Weather Telemetry ── -->
  <div id="forecast-hero-container" class="rounded-[16px] sm:rounded-[24px] p-4 sm:p-7 border relative overflow-hidden" style="background: #ffffff !important; border: 1.5px solid <?= $init_f_border ?> !important; box-shadow: 0 4px 24px rgba(15, 23, 42, 0.05) !important; transition: border-color 0.5s ease, box-shadow 0.5s ease;">
    <div class="flex flex-col gap-4 sm:gap-6 w-full">
      
      <!-- Top Row: Title, Date, Hour Target Badge -->
      <div id="forecast-hero-divider-h" class="flex items-center justify-between flex-wrap gap-2 text-xs font-bold uppercase tracking-wider pb-1">
        <div class="flex items-center gap-2 flex-wrap">
          <span id="forecast-hero-title" class="font-extrabold" style="color: <?= $init_f_color ?>;">Predicted Weather &amp; Air Quality Index</span>
          <span style="opacity: 0.5;">•</span>
          <span class="text-slate-600 font-semibold" id="forecast-date-str">Loading...</span>
          <span style="opacity: 0.5;">•</span>
          <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border font-mono shadow-xs transition-colors duration-300" id="forecast-hero-target-window" style="background-color: <?= $init_f_bg ?>; border-color: <?= $init_f_border ?>; color: <?= $init_f_text ?>;">Hourly Prediction</span>
        </div>
      </div>

      <!-- Main Row: Left (Rainbow Arc Gauge) + Right (Big Temperature, Humidity & Rain Cards) -->
      <div class="flex flex-col md:flex-row items-center md:items-stretch justify-between gap-6 md:gap-8">

        <!-- Left Side: Sleek Modern AQI Arc Widget (matching user reference card, in clean light theme) -->
        <div class="hero-aqi-col flex flex-col justify-center w-full md:flex-1 shrink-0 py-1">
          <div id="aqi-hero-card" class="rounded-2xl p-4 sm:p-5 w-full flex flex-col justify-between relative overflow-hidden h-full" style="background: <?= $init_f_card_bg ?> !important; border: 1.5px solid <?= $init_f_border ?> !important; box-shadow: 0 4px 20px <?= $init_f_shadow ?> !important; transition: background 0.5s ease, border-color 0.5s ease, box-shadow 0.5s ease;">

            <!-- Content Row: Arc on Left, Reading & Health Advice on Right -->
            <div class="flex items-center justify-between gap-4 sm:gap-6 w-full my-auto">
              
              <!-- Left Column: Smooth Low Arc with Category Text below -->
              <div class="flex flex-col items-center justify-center shrink-0" style="width: 140px; max-width: 145px;">
                <div class="relative w-full flex flex-col items-center justify-center">
                  <svg viewBox="0 0 150 72" class="w-full h-auto overflow-visible select-none" id="forecast-rainbow-svg">
                    <defs>
                      <linearGradient id="aqi-rainbow-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#22c55e" />
                        <stop offset="22%" stop-color="#84cc16" />
                        <stop offset="42%" stop-color="#eab308" />
                        <stop offset="65%" stop-color="#f97316" />
                        <stop offset="85%" stop-color="#ec4899" />
                        <stop offset="100%" stop-color="#dc2626" />
                      </linearGradient>
                      <filter id="aqi-bead-shadow" x="-50%" y="-50%" width="200%" height="200%">
                        <feDropShadow dx="0" dy="1.5" stdDeviation="2" flood-color="#0f172a" flood-opacity="0.30"/>
                      </filter>
                    </defs>

                    <!-- Background Base Track -->
                    <path d="M 15 58 A 65 65 0 0 1 135 58" fill="none" stroke="rgba(0, 0, 0, 0.06)" stroke-width="5.5" stroke-linecap="round" />

                    <!-- Smooth Continuous Rainbow Gradient Arc -->
                    <path d="M 15 58 A 65 65 0 0 1 135 58" fill="none" stroke="url(#aqi-rainbow-gradient)" stroke-width="5" stroke-linecap="round" />

                    <!-- Dynamic Sliding White Bead Pin -->
                    <circle id="forecast-rainbow-pin" cx="<?= $init_f_pin_x ?>" cy="<?= $init_f_pin_y ?>" r="5.5" fill="#ffffff" stroke="<?= $init_f_color ?>" stroke-width="2.2" filter="url(#aqi-bead-shadow)" style="transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);" />
                  </svg>
                </div>
                <!-- Category Text right below the curve apex -->
                <div id="forecast-hero-category-text" class="text-xs sm:text-[13.5px] font-black uppercase tracking-wider font-sans mt-0.5 text-center" style="color: <?= $init_f_color ?>;"><?= $init_f_label ?></div>
              </div>

              <!-- Right Column: Big AQI Value + Health Impact Advisory -->
              <div class="flex flex-col justify-center flex-1 min-w-0 pr-2 sm:pr-4">
                <div class="flex items-baseline gap-1.5 leading-none">
                  <span id="forecast-hero-aqi" class="text-3xl sm:text-4xl lg:text-[44px] font-black font-mono tracking-tight text-slate-900 leading-none"><?= $init_f_aqi ?></span>
                  <span class="text-base sm:text-lg font-extrabold text-slate-500 font-sans tracking-tight">AQI</span>
                </div>
                <p id="forecast-hero-health-advice" class="text-xs sm:text-[13px] font-semibold text-slate-500 mt-1.5 line-clamp-2 leading-snug m-0"><?= $init_f_advice ?></p>
              </div>

            </div>

          </div>
        </div>

        <!-- Right Side: Weather Box (Clean, without comfort/wind/limits) -->
        <div class="hero-weather-col flex flex-col justify-center w-full md:w-auto shrink-0 md:min-w-[280px] lg:min-w-[300px] py-1">
          <div id="weather-hero-box" class="weather-hero-box rounded-2xl p-4 sm:p-4.5 w-full flex flex-col justify-between" style="background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 25%, #e0f2fe 65%, #bae6fd 100%) !important; border: 1.5px solid rgba(14, 165, 233, 0.38) !important; box-shadow: 0 4px 20px rgba(14, 165, 233, 0.12) !important; transition: border-color 0.4s ease, box-shadow 0.4s ease;">
            
            <!-- Box Header -->
            <div class="weather-box-header flex items-center justify-between mb-2">
              <div class="flex items-center gap-1.5 text-[11px] font-extrabold text-slate-700 uppercase tracking-wider font-sans">
                <i data-lucide="cloud-sun" id="weather-hero-icon" class="w-4 h-4" style="color: #0284c7;"></i>
                <span>Weather</span>
              </div>
            </div>

            <!-- Main Temperature (Clean and Prominent) -->
            <div class="weather-box-main flex items-baseline justify-start my-1.5">
              <div class="weather-temp-row flex items-baseline gap-1">
                <span id="forecast-weather-temp" class="text-3xl sm:text-[38px] font-black font-mono leading-none tracking-tight text-slate-900">--</span>
                <span class="weather-temp-unit text-base sm:text-lg font-bold font-mono" style="color: #0284c7;">°C</span>
              </div>
            </div>

            <!-- Micro Metrics Row (Humidity & Rain Percentage) -->
            <div id="weather-metrics-divider" class="weather-metrics-grid grid grid-cols-2 gap-2.5 pt-2.5 mt-1 border-t" style="border-color: rgba(56, 189, 248, 0.3);">
              
              <!-- Humidity -->
              <div id="weather-micro-humidity" class="weather-micro-card rounded-xl p-2 sm:p-2.5 text-center shadow-xs transition-colors duration-300" style="background: rgba(255, 255, 255, 0.88); border: 1px solid rgba(14, 165, 233, 0.3); box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);">
                <div class="weather-micro-label text-[10px] sm:text-[10.5px] font-bold text-slate-600 flex items-center justify-center gap-1 mb-0.5">
                  <i data-lucide="droplets" class="w-3.5 h-3.5 text-[#00bfa5]"></i>
                  <span>Humidity</span>
                </div>
                <div id="forecast-weather-humidity" class="font-mono text-xs sm:text-[14px] font-extrabold text-slate-900">--%</div>
              </div>

              <!-- Rain Percentage -->
              <div id="weather-micro-rain" class="weather-micro-card rounded-xl p-2 sm:p-2.5 text-center shadow-xs transition-colors duration-300" style="background: rgba(255, 255, 255, 0.88); border: 1px solid rgba(14, 165, 233, 0.3); box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);">
                <div class="weather-micro-label text-[10px] sm:text-[10.5px] font-bold text-slate-600 flex items-center justify-center gap-1 mb-0.5">
                  <i data-lucide="cloud-rain" class="w-3.5 h-3.5 text-[#0284c7]"></i>
                  <span>Rain</span>
                </div>
                <div id="forecast-weather-rain" class="font-mono text-xs sm:text-[14px] font-extrabold text-slate-900">--%</div>
              </div>

            </div>

          </div>
        </div>

      </div>

    </div>
  </div>

  <!-- ── 3.5. Predicted Model Parameters (Outputs) Grid ── -->
  <div class="predicted-params-section bg-transparent sm:bg-white rounded-none sm:rounded-[24px] p-0 sm:p-6 border-0 sm:border border-slate-200 shadow-none sm:shadow-[0_4px_24px_rgba(15,23,42,0.05)] mt-3 sm:mt-0">
    <div class="params-header flex items-center justify-between mb-3 px-0.5">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-[#e6fcf9] text-[#00bfa5] flex items-center justify-center shrink-0">
          <i data-lucide="activity" class="w-4 h-4"></i>
        </div>
        <h2 class="text-[17px] sm:text-lg font-bold text-slate-900 m-0 font-sans tracking-tight">Predicted Air Quality Parameters</h2>
      </div>
      <span class="text-[11px] font-bold font-mono px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-500 border border-slate-200">STANDBY</span>
    </div>

    <!-- Grid of the 5 criteria air pollutants -->
    <div id="predicted-parameters-grid" class="pollutants-grid-responsive grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5 sm:gap-4">
      
      <!-- PM2.5 -->
      <div id="card-box-pm25" class="pollutant-card flex flex-col justify-between gap-2.5 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 shadow-[0_4px_16px_rgba(15,23,42,0.04)] relative overflow-hidden h-full min-h-[110px]" style="border-left: 5px solid #cbd5e1;">
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
            <div id="card-pm25" class="font-mono text-lg sm:text-2xl font-extrabold text-slate-400 leading-none">--</div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">µg/m³</div>
          </div>
          <div id="tag-pm25" class="pollutant-tag px-2 py-1 rounded-full text-[9px] sm:text-[11px] font-bold flex items-center gap-1 shrink-0" style="background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b;">
            <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background-color: #94a3b8;"></span>
            <span>Standby</span>
          </div>
        </div>
      </div>

      <!-- PM10 -->
      <div id="card-box-pm10" class="pollutant-card flex flex-col justify-between gap-2.5 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 shadow-[0_4px_16px_rgba(15,23,42,0.04)] relative overflow-hidden h-full min-h-[110px]" style="border-left: 5px solid #cbd5e1;">
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
            <div id="card-pm10" class="font-mono text-lg sm:text-2xl font-extrabold text-slate-400 leading-none">--</div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">µg/m³</div>
          </div>
          <div id="tag-pm10" class="pollutant-tag px-2 py-1 rounded-full text-[9px] sm:text-[11px] font-bold flex items-center gap-1 shrink-0" style="background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b;">
            <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background-color: #94a3b8;"></span>
            <span>Standby</span>
          </div>
        </div>
      </div>

      <!-- CO -->
      <div id="card-box-co" class="pollutant-card flex flex-col justify-between gap-2.5 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 shadow-[0_4px_16px_rgba(15,23,42,0.04)] relative overflow-hidden h-full min-h-[110px]" style="border-left: 5px solid #cbd5e1;">
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
            <div id="card-co" class="font-mono text-lg sm:text-2xl font-extrabold text-slate-400 leading-none">--</div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">mg/m³</div>
          </div>
          <div id="tag-co" class="pollutant-tag px-2 py-1 rounded-full text-[9px] sm:text-[11px] font-bold flex items-center gap-1 shrink-0" style="background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b;">
            <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background-color: #94a3b8;"></span>
            <span>Standby</span>
          </div>
        </div>
      </div>

      <!-- NO2 -->
      <div id="card-box-no2" class="pollutant-card flex flex-col justify-between gap-2.5 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 shadow-[0_4px_16px_rgba(15,23,42,0.04)] relative overflow-hidden h-full min-h-[110px]" style="border-left: 5px solid #cbd5e1;">
        <div class="pollutant-card-top flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <div class="pollutant-icon-box w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center text-sm sm:text-lg shrink-0">🏭</div>
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
            <div id="card-no2" class="font-mono text-lg sm:text-2xl font-extrabold text-slate-400 leading-none">--</div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">µg/m³</div>
          </div>
          <div id="tag-no2" class="pollutant-tag px-2 py-1 rounded-full text-[9px] sm:text-[11px] font-bold flex items-center gap-1 shrink-0" style="background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b;">
            <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background-color: #94a3b8;"></span>
            <span>Standby</span>
          </div>
        </div>
      </div>

      <!-- O3 -->
      <div id="card-box-o3" class="pollutant-card flex flex-col justify-between gap-2.5 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 shadow-[0_4px_16px_rgba(15,23,42,0.04)] relative overflow-hidden h-full min-h-[110px]" style="border-left: 5px solid #cbd5e1;">
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
            <div id="card-o3" class="font-mono text-lg sm:text-2xl font-extrabold text-slate-400 leading-none">--</div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">µg/m³</div>
          </div>
          <div id="tag-o3" class="pollutant-tag px-2 py-1 rounded-full text-[9px] sm:text-[11px] font-bold flex items-center gap-1 shrink-0" style="background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b;">
            <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background-color: #94a3b8;"></span>
            <span>Standby</span>
          </div>
        </div>
      </div>

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
          <h2 id="hourly-forecast-heading" class="text-sm sm:text-base font-extrabold text-slate-900 m-0 tracking-tight truncate">Hourly Forecast (Standby)</h2>
          <p class="text-[11px] sm:text-xs text-slate-400 m-0 truncate">Atmospheric forecast inference is in standby mode for Station 2</p>
        </div>
      </div>
    </div>

    <!-- Edge-to-Edge Horizontal Reel with High-Performance Smooth Scrolling -->
    <div id="hourly-forecast-reel" class="hourly-forecast-reel flex gap-3 overflow-x-auto py-2 px-1">
      <div class="w-full py-8 px-4 text-center rounded-xl bg-slate-50 border border-dashed border-slate-200 flex flex-col items-center justify-center gap-1.5">
        <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-1">
          <i data-lucide="clock" class="w-5 h-5"></i>
        </div>
        <div class="text-xs sm:text-sm font-bold text-slate-600 font-sans">Predictive Forecast Standby</div>
        <div class="text-[11px] sm:text-xs text-slate-400 max-w-[420px]">Machine learning forecast inference is active for Station 1. Station 2 predictive values remain blank in standby mode.</div>
      </div>
    </div>

  </div>

  <!-- ── 5. Rainfall Prediction Probability Scale & Status Legend ── -->
  <div class="bg-white rounded-[20px] sm:rounded-[24px] p-4 sm:p-6 border border-slate-200 shadow-[0_4px_24px_rgba(15,23,42,0.05)] flex flex-col gap-4">
    
    <div>
      <h2 class="text-base sm:text-lg font-bold text-slate-900 m-0 flex items-center gap-2">
        <i data-lucide="cloud-rain" class="w-5 h-5 text-[#00bfa5]"></i>
        <span>Rainfall Prediction Scale</span>
      </h2>
      <p class="text-xs sm:text-[12.5px] text-slate-500 mt-1 mb-0">
        Operational classification standard for machine-learned precipitation probability forecasts.
      </p>
    </div>

    <div class="flex flex-col gap-2.5">
      
      <!-- Tier 1: Dry / Clear (0% to 25%) -->
      <div id="rain-tier-1" class="rain-scale-item flex items-center justify-between p-3 sm:p-3.5 rounded-xl sm:rounded-2xl bg-[#f0fdf4] border border-[#bbf7d0] gap-3 flex-wrap transition-all">
        <div class="flex items-center gap-2.5">
          <span class="w-3 h-3 rounded-[4px] bg-[#10b981] shrink-0"></span>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-sm font-bold text-slate-900">Dry / Clear</span>
              <span class="text-xs font-bold font-mono text-[#059669]">(0% to 25%)</span>
            </div>
            <div class="text-[11.5px] text-slate-600 mt-0.5">Dry and clear atmospheric conditions; minimal or zero probability of precipitation.</div>
          </div>
        </div>
      </div>

      <!-- Tier 2: Mostly Dry (26% to 49%) -->
      <div id="rain-tier-2" class="rain-scale-item flex items-center justify-between p-3 sm:p-3.5 rounded-xl sm:rounded-2xl bg-[#ecfeff] border border-[#a5f3fc] gap-3 flex-wrap transition-all">
        <div class="flex items-center gap-2.5">
          <span class="w-3 h-3 rounded-[4px] bg-[#06b6d4] shrink-0"></span>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-sm font-bold text-slate-900">Mostly Dry</span>
              <span class="text-xs font-bold font-mono text-[#0891b2]">(26% to 49%)</span>
            </div>
            <div class="text-[11.5px] text-slate-600 mt-0.5">Overcast skies or slight moisture; precipitation remains unlikely.</div>
          </div>
        </div>
      </div>

      <!-- Tier 3: Chance of Rain (50% to 69%) -->
      <div id="rain-tier-3" class="rain-scale-item flex items-center justify-between p-3 sm:p-3.5 rounded-xl sm:rounded-2xl bg-[#eff6ff] border border-[#bfdbfe] gap-3 flex-wrap transition-all">
        <div class="flex items-center gap-2.5">
          <span class="w-3 h-3 rounded-[4px] bg-[#3b82f6] shrink-0"></span>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-sm font-bold text-slate-900">Chance of Rain</span>
              <span class="text-xs font-bold font-mono text-[#2563eb]">(50% to 69%)</span>
            </div>
            <div class="text-[11.5px] text-slate-600 mt-0.5">Scattered showers or drizzle possible; consider carrying an umbrella.</div>
          </div>
        </div>
      </div>

      <!-- Tier 4: Rain Likely (70% to 84%) -->
      <div id="rain-tier-4" class="rain-scale-item flex items-center justify-between p-3 sm:p-3.5 rounded-xl sm:rounded-2xl bg-[#fffbeb] border border-[#fde68a] gap-3 flex-wrap transition-all">
        <div class="flex items-center gap-2.5">
          <span class="w-3 h-3 rounded-[4px] bg-[#f59e0b] shrink-0"></span>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-sm font-bold text-slate-900">Rain Likely</span>
              <span class="text-xs font-bold font-mono text-[#d97706]">(70% to 84%)</span>
            </div>
            <div class="text-[11.5px] text-slate-600 mt-0.5">High confidence of rainfall; steady precipitation or showers anticipated.</div>
          </div>
        </div>
      </div>

      <!-- Tier 5: Heavy Rain (85% to 100%) -->
      <div id="rain-tier-5" class="rain-scale-item flex items-center justify-between p-3 sm:p-3.5 rounded-xl sm:rounded-2xl bg-[#faf5ff] border border-[#e9d5ff] gap-3 flex-wrap transition-all">
        <div class="flex items-center gap-2.5">
          <span class="w-3 h-3 rounded-[4px] bg-[#8b5cf6] shrink-0"></span>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-sm font-bold text-slate-900">Heavy Rain</span>
              <span class="text-xs font-bold font-mono text-[#7c3aed]">(85% to 100%)</span>
            </div>
            <div class="text-[11.5px] text-slate-600 mt-0.5">Intense precipitation or torrential downpours expected; outdoor activities disrupted.</div>
          </div>
        </div>
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

/* Pollutant Card Base & Mobile Styles (100% parity with Live page) */
.pollutant-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-top-color 0.2s ease, border-right-color 0.2s ease, border-bottom-color 0.2s ease !important;
}
.pollutant-card:hover {
  border-top-color: #00bfa5 !important;
  border-right-color: #00bfa5 !important;
  border-bottom-color: #00bfa5 !important;
}
.pollutant-chevron {
  display: none !important;
}

@media (max-width: 640px) {
  /* Seamless full-width container on mobile matching live page */
  .predicted-params-section {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    border-radius: 0 !important;
    margin-top: 10px !important;
  }
  .predicted-params-section .params-header {
    padding: 0 2px 8px 2px !important;
    margin-bottom: 2px !important;
  }

  /* Pollutants Grid Mobile Refinements (Ultra-compact 2-column layout matching live page) */
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
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 3px rgba(15,23,42,0.04) !important;
    background: #ffffff !important;
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
  .pollutant-chevron {
    grid-area: chevron !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: #94a3b8 !important;
    padding-left: 2px !important;
  }
  .pollutant-chevron svg {
    width: 14px !important;
    height: 14px !important;
  }
  .pollutant-card .pollutant-info-icon,
  .pollutant-card [id^="tag-"],
  .pollutant-card .pollutant-tag {
    display: none !important;
  }
}
</style>

<!-- ── JavaScript Forecast Model Engine ── -->
<script>
(function() {
    let trainedModelData = <?php echo $preloadedForecastJson ?: 'null'; ?>;

    // AQI Colors and Categories as per Live Page Bar Standard
    function getAqiBadge(aqi) {
        if (aqi === null || aqi === undefined || aqi === '--' || isNaN(Number(aqi))) {
            return {
                label: 'Standby',
                advice: 'Awaiting station telemetry.',
                bg: '#f8fafc',
                cardBg: 'linear-gradient(135deg, #ffffff 0%, #f8fafc 40%, #f1f5f9 100%)',
                text: '#64748b',
                border: '#cbd5e1',
                dot: '#94a3b8',
                color: '#64748b',
                shadow: 'rgba(100, 116, 139, 0.08)'
            };
        }
        const v = Number(aqi);
        if (v <= 50) return {
            label: 'Good',
            advice: 'Air quality is satisfactory.',
            bg: '#f0fdf4',
            cardBg: 'linear-gradient(135deg, #ffffff 0%, #f0fdf4 25%, #dcfce7 55%, #bbf7d0 78%, #4ade80 92%, #22c55e 100%)',
            text: '#15803d',
            border: 'rgba(22, 163, 74, 0.35)',
            dot: '#16a34a',
            color: '#16a34a',
            shadow: 'rgba(22, 163, 74, 0.16)'
        };
        if (v <= 100) return {
            label: 'Moderate',
            advice: 'Acceptable air quality.',
            bg: '#fefce8',
            cardBg: 'linear-gradient(135deg, #ffffff 0%, #fefce8 25%, #fef08a 52%, #fde047 78%, #eab308 92%, #ca8a04 100%)',
            text: '#854d0e',
            border: 'rgba(202, 138, 4, 0.38)',
            dot: '#eab308',
            color: '#ca8a04',
            shadow: 'rgba(234, 179, 8, 0.18)'
        };
        if (v <= 200) return {
            label: 'Poor',
            advice: 'Sensitive people affected.',
            bg: '#fff7ed',
            cardBg: 'linear-gradient(135deg, #ffffff 0%, #fff7ed 22%, #ffedd5 48%, #fed7aa 72%, #fb923c 90%, #f97316 100%)',
            text: '#c2410c',
            border: 'rgba(234, 88, 12, 0.38)',
            dot: '#f97316',
            color: '#ea580c',
            shadow: 'rgba(249, 115, 22, 0.18)'
        };
        if (v <= 300) return {
            label: 'Unhealthy',
            advice: 'Health effects for everyone.',
            bg: '#fff1f2',
            cardBg: 'linear-gradient(135deg, #ffffff 0%, #fff1f2 22%, #ffe4e6 48%, #fecdd3 72%, #fb7185 90%, #e11d48 100%)',
            text: '#9f1239',
            border: 'rgba(225, 29, 72, 0.38)',
            dot: '#ef4444',
            color: '#e11d48',
            shadow: 'rgba(225, 29, 72, 0.18)'
        };
        if (v <= 400) return {
            label: 'Severe',
            advice: 'Emergency health warning.',
            bg: '#faf5ff',
            cardBg: 'linear-gradient(135deg, #ffffff 0%, #faf5ff 22%, #f3e8ff 48%, #e9d5ff 72%, #c084fc 90%, #9333ea 100%)',
            text: '#6b21a8',
            border: 'rgba(147, 51, 234, 0.38)',
            dot: '#a855f7',
            color: '#9333ea',
            shadow: 'rgba(147, 51, 234, 0.18)'
        };
        return {
            label: 'Hazardous',
            advice: 'Serious health crisis.',
            bg: '#fef2f2',
            cardBg: 'linear-gradient(135deg, #ffffff 0%, #fef2f2 22%, #fee2e2 48%, #fca5a5 72%, #f87171 90%, #b91c1c 100%)',
            text: '#881337',
            border: 'rgba(185, 28, 28, 0.40)',
            dot: '#b91c1c',
            color: '#b91c1c',
            shadow: 'rgba(185, 28, 28, 0.20)'
        };
    }

    // 5-Tier Rainfall Probability Scale & Status Standard
    function getRainStatus(prob) {
        if (prob === null || prob === undefined || prob === '--' || isNaN(Number(prob))) {
            return { tier: 1, label: 'Dry / Clear', color: '#10b981' };
        }
        const p = Math.round(Number(prob));
        if (p >= 85) return { tier: 5, label: 'Heavy Rain', color: '#8b5cf6' };
        if (p >= 70) return { tier: 4, label: 'Rain Likely', color: '#f59e0b' };
        if (p >= 50) return { tier: 3, label: 'Chance of Rain', color: '#3b82f6' };
        if (p >= 26) return { tier: 2, label: 'Mostly Dry', color: '#06b6d4' };
        return { tier: 1, label: 'Dry / Clear', color: '#10b981' };
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

    // Standby Hourly & Phase Generators for Station 2
    function generateHourlyData() {
        return [];
    }

    function generateTimelinePhases() {
        return [];
    }

    // Scroll helper for horizontal reel
    window.scrollHourlyReel = function(direction) {
        const reel = document.getElementById('hourly-forecast-reel');
        if (reel) {
            reel.scrollBy({ left: direction * 280, behavior: 'smooth' });
        }
    };

    // Render Function: ensures Node 2 remains strictly blank / in standby with no values passed
    function renderForecastUI() {
        // 1. Date and Sync time
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' });
        const dateEl = document.getElementById('forecast-date-str');
        if (dateEl) dateEl.innerText = dateStr;

        const headingEl = document.getElementById('hourly-forecast-heading');
        if (headingEl) {
            headingEl.innerText = 'Hourly Forecast (Standby)';
        }

        // 2. Hero Card: Predicted Weather & AQI kept strictly blank / standby
        const heroAqi = document.getElementById('forecast-hero-aqi');
        if (heroAqi) heroAqi.innerText = '--';

        const catText = document.getElementById('forecast-hero-category-text');
        if (catText) {
            catText.innerText = 'STANDBY';
            catText.style.color = '#94a3b8';
        }

        const healthAdvice = document.getElementById('forecast-hero-health-advice');
        if (healthAdvice) {
            healthAdvice.innerText = 'Predictive model is in standby for Station 2.';
        }

        const targetWinEl = document.getElementById('forecast-hero-target-window');
        if (targetWinEl) {
            targetWinEl.innerText = 'Standby';
            targetWinEl.style.backgroundColor = '#f1f5f9';
            targetWinEl.style.borderColor = 'rgba(148, 163, 184, 0.38)';
            targetWinEl.style.color = '#64748b';
        }

        const rainbowPin = document.getElementById('forecast-rainbow-pin');
        if (rainbowPin) {
            rainbowPin.setAttribute('cx', '15');
            rainbowPin.setAttribute('cy', '58');
            rainbowPin.setAttribute('stroke', '#94a3b8');
        }

        const aqiCard = document.getElementById('aqi-hero-card');
        if (aqiCard) {
            aqiCard.style.setProperty('background', 'linear-gradient(135deg, #ffffff 0%, #f8fafc 50%, #f1f5f9 100%)', 'important');
            aqiCard.style.setProperty('border-color', 'rgba(148, 163, 184, 0.38)', 'important');
            aqiCard.style.boxShadow = '0 4px 20px rgba(148, 163, 184, 0.15)';
        }

        // Weather Box
        const weatherTemp = document.getElementById('forecast-weather-temp');
        if (weatherTemp) weatherTemp.innerText = '--';

        const weatherHum = document.getElementById('forecast-weather-humidity');
        if (weatherHum) weatherHum.innerText = '--%';

        const weatherRain = document.getElementById('forecast-weather-rain');
        if (weatherRain) weatherRain.innerText = '--%';

        // Clear active tier on Rainfall Prediction Scale
        document.querySelectorAll('.rain-scale-item').forEach(el => {
            el.classList.remove('ring-2', 'ring-teal-500', 'shadow-md');
        });

        // 3. Ensure 5 Predicted Pollutants Grid values remain blank / standby
        ['pm25', 'pm10', 'co', 'no2', 'o3'].forEach(k => {
            const valEl = document.getElementById('card-' + k);
            if (valEl) valEl.innerText = '--';
            const tagEl = document.getElementById('tag-' + k);
            if (tagEl) {
                tagEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full shrink-0" style="background-color: #94a3b8;"></span><span>Standby</span>';
                tagEl.style.backgroundColor = '#f1f5f9';
                tagEl.style.borderColor = '#e2e8f0';
                tagEl.style.color = '#64748b';
            }
        });

        // 4. Hourly Reel: Standby Message
        const reel = document.getElementById('hourly-forecast-reel');
        if (reel) {
            reel.innerHTML = `
                <div class="w-full py-8 px-4 text-center rounded-xl bg-slate-50 border border-dashed border-slate-200 flex flex-col items-center justify-center gap-1.5">
                    <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-1">
                        <i data-lucide="clock" class="w-5 h-5"></i>
                    </div>
                    <div class="text-xs sm:text-sm font-bold text-slate-600 font-sans">Predictive Forecast Standby</div>
                    <div class="text-[11px] sm:text-xs text-slate-400 max-w-[420px]">Machine learning forecast inference is active for Station 1. Station 2 predictive values remain blank in standby mode.</div>
                </div>
            `;
        }

        if (window.lucide) lucide.createIcons();
    }

    // Node 2 predictive loader: does not fetch or load Node 1 forecast
    async function loadForecastData(force = false) {
        trainedModelData = null;
        renderForecastUI();
    }

    // Prevent external injection of Node 1 forecast into Node 2
    window.loadTrainedForecastModel = function(data) {
        trainedModelData = null;
        renderForecastUI();
    };

    let autoRefreshForecastTimer = null;

    // Clean up on SPA navigation
    window.__cleanupCurrentPage = function() {
        if (autoRefreshForecastTimer) {
            clearInterval(autoRefreshForecastTimer);
            autoRefreshForecastTimer = null;
        }
    };

    function initForecastPage() {
        renderForecastUI();
        if (autoRefreshForecastTimer) clearInterval(autoRefreshForecastTimer);
        autoRefreshForecastTimer = setInterval(() => {
            if (!document.hidden) {
                loadForecastData(true);
            }
        }, 15000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initForecastPage);
    } else {
        initForecastPage();
    }
})();
</script>
