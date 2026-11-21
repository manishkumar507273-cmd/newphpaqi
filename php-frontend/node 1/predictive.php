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
$active_node_id = '1';
$is_node_2 = false;

// 0ms instant pre-hydration from disk cache if it contains upcoming forecast hours
$forecastJsonFile = __DIR__ . '/../api/forecast_data.json';
$preloadedForecastJson = 'null';
if (!$is_node_2 && file_exists($forecastJsonFile)) {
    $rawPreload = @file_get_contents($forecastJsonFile);
    if ($rawPreload && strlen($rawPreload) > 20) {
        $decodedPreload = @json_decode($rawPreload, true);
        if (is_array($decodedPreload) && !empty($decodedPreload['hourly'])) {
            $nowIst = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
            $currentHourStartTs = (clone $nowIst)->setTime((int)$nowIst->format('H'), 0, 0)->getTimestamp();
            $hasFutureHour = false;
            foreach ($decodedPreload['hourly'] as $hItem) {
                if (!empty($hItem['iso'])) {
                    try {
                        $dtH = new DateTime($hItem['iso']);
                        if ($dtH->getTimestamp() >= $currentHourStartTs) {
                            $hasFutureHour = true;
                            break;
                        }
                    } catch (Exception $e) {}
                }
            }
            if ($hasFutureHour) {
                $preloadedForecastJson = $rawPreload;
            }
        }
    }
}

// Initial AQI category theming in light (matching Live / Overview AQI tier standards)
$init_f_bg = $is_node_2 ? '#f1f5f9' : '#fefce8';
$init_f_card_bg = $is_node_2 ? 'linear-gradient(135deg, #ffffff 0%, #f8fafc 50%, #f1f5f9 100%)' : 'linear-gradient(135deg, #ffffff 0%, #fefce8 25%, #fef08a 52%, #fde047 78%, #eab308 92%, #ca8a04 100%)';
$init_f_border = $is_node_2 ? 'rgba(148, 163, 184, 0.38)' : 'rgba(202, 138, 4, 0.38)';
$init_f_text = $is_node_2 ? '#64748b' : '#854d0e';
$init_f_color = $is_node_2 ? '#94a3b8' : '#ca8a04';
$init_f_shadow = $is_node_2 ? 'rgba(148, 163, 184, 0.15)' : 'rgba(234, 179, 8, 0.18)';
$init_f_label = $is_node_2 ? 'STANDBY' : 'MODERATE';
$init_f_aqi = $is_node_2 ? '--' : 90;
$init_f_advice = $is_node_2 ? 'Predictive model is in standby.' : 'Acceptable air quality.';

if (!$is_node_2 && isset($decodedPreload['hourly'][0]['aqi'])) {
    $vA = (int)$decodedPreload['hourly'][0]['aqi'];
    $init_f_aqi = $vA;
    if ($vA <= 50) {
        $init_f_bg = '#f0fdf4';
        $init_f_card_bg = 'linear-gradient(135deg, #ffffff 0%, #f0fdf4 25%, #dcfce7 55%, #bbf7d0 78%, #4ade80 92%, #22c55e 100%)';
        $init_f_border = 'rgba(22, 163, 74, 0.35)';
        $init_f_text = '#15803d';
        $init_f_color = '#16a34a';
        $init_f_shadow = 'rgba(22, 163, 74, 0.16)';
        $init_f_label = 'GOOD';
        $init_f_advice = 'Air quality is satisfactory.';
    } elseif ($vA <= 100) {
        // Moderate default above
        $init_f_advice = 'Acceptable air quality.';
    } elseif ($vA <= 200) {
        $init_f_bg = '#fff7ed';
        $init_f_card_bg = 'linear-gradient(135deg, #ffffff 0%, #fff7ed 22%, #ffedd5 48%, #fed7aa 72%, #fb923c 90%, #f97316 100%)';
        $init_f_border = 'rgba(234, 88, 12, 0.38)';
        $init_f_text = '#c2410c';
        $init_f_color = '#ea580c';
        $init_f_shadow = 'rgba(249, 115, 22, 0.18)';
        $init_f_label = 'POOR';
        $init_f_advice = 'Sensitive people affected.';
    } elseif ($vA <= 300) {
        $init_f_bg = '#fff1f2';
        $init_f_card_bg = 'linear-gradient(135deg, #ffffff 0%, #fff1f2 22%, #ffe4e6 48%, #fecdd3 72%, #fb7185 90%, #e11d48 100%)';
        $init_f_border = 'rgba(225, 29, 72, 0.38)';
        $init_f_text = '#9f1239';
        $init_f_color = '#e11d48';
        $init_f_shadow = 'rgba(225, 29, 72, 0.18)';
        $init_f_label = 'UNHEALTHY';
        $init_f_advice = 'Health effects for everyone.';
    } elseif ($vA <= 400) {
        $init_f_bg = '#faf5ff';
        $init_f_card_bg = 'linear-gradient(135deg, #ffffff 0%, #faf5ff 22%, #f3e8ff 48%, #e9d5ff 72%, #c084fc 90%, #9333ea 100%)';
        $init_f_border = 'rgba(147, 51, 234, 0.38)';
        $init_f_text = '#6b21a8';
        $init_f_color = '#9333ea';
        $init_f_shadow = 'rgba(147, 51, 234, 0.18)';
        $init_f_label = 'SEVERE';
        $init_f_advice = 'Emergency health warning.';
    } else {
        $init_f_bg = '#fef2f2';
        $init_f_card_bg = 'linear-gradient(135deg, #ffffff 0%, #fef2f2 22%, #fee2e2 48%, #fca5a5 72%, #f87171 90%, #b91c1c 100%)';
        $init_f_border = 'rgba(185, 28, 28, 0.40)';
        $init_f_text = '#881337';
        $init_f_color = '#b91c1c';
        $init_f_shadow = 'rgba(185, 28, 28, 0.20)';
        $init_f_label = 'HAZARDOUS';
        $init_f_advice = 'Serious health crisis.';
    }
}

$numeric_f_aqi = is_numeric($init_f_aqi) ? (float)$init_f_aqi : 0;
if ($numeric_f_aqi <= 50) {
    $init_f_t = ($numeric_f_aqi / 50) * 0.22;
} elseif ($numeric_f_aqi <= 100) {
    $init_f_t = 0.22 + (($numeric_f_aqi - 50) / 50) * 0.20;
} elseif ($numeric_f_aqi <= 200) {
    $init_f_t = 0.42 + (($numeric_f_aqi - 100) / 100) * 0.23;
} elseif ($numeric_f_aqi <= 300) {
    $init_f_t = 0.65 + (($numeric_f_aqi - 200) / 100) * 0.15;
} elseif ($numeric_f_aqi <= 400) {
    $init_f_t = 0.80 + (($numeric_f_aqi - 300) / 100) * 0.12;
} else {
    $init_f_t = min(1.0, 0.92 + min(($numeric_f_aqi - 400) / 100, 1) * 0.08);
}
$init_f_theta = (-157.38 + $init_f_t * 134.76) * (M_PI / 180);
$init_f_pin_x = round(75 + 65 * cos($init_f_theta), 2);
$init_f_pin_y = round(83 + 65 * sin($init_f_theta), 2);
?>

<div class="forecast-page-container p-2.5 sm:p-6 flex flex-col gap-3 sm:gap-6 max-w-[1200px] mx-auto text-slate-900 font-sans">

  <div class="mb-0.5 sm:mb-2">
    <h1 class="text-lg sm:text-[28px] font-extrabold text-slate-900 m-0 tracking-tight">Weather Forecast</h1>
    <p class="m-0 text-slate-500 text-[11px] sm:text-sm font-medium mt-0.5">Atmospheric weather and air quality predictive forecasting for SMVITM Campus.</p>
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
    </div>

    <!-- Grid of the 5 criteria air pollutants -->
    <div id="predicted-parameters-grid" class="pollutants-grid-responsive grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5 sm:gap-4">
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
                rain_prob: 0,
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

            // Keep outer hero container background white per user request
            const heroCard = document.getElementById('forecast-hero-container');
            if (heroCard) {
                heroCard.style.setProperty('background', '#ffffff', 'important');
                heroCard.style.setProperty('border-color', b.border, 'important');
                heroCard.style.boxShadow = `0 4px 24px rgba(15, 23, 42, 0.05)`;
            }

            const heroTitle = document.getElementById('forecast-hero-title');
            if (heroTitle) {
                heroTitle.style.color = b.color;
            }

            const heroLabel = document.getElementById('forecast-hero-label');
            if (heroLabel) {
                heroLabel.style.color = b.color;
            }

            // Hero AQI Value
            const heroAqi = document.getElementById('forecast-hero-aqi');
            if (heroAqi) {
                heroAqi.innerText = curHour.aqi !== undefined ? curHour.aqi : '--';
            }

            // Category text right under the arc curve apex
            const catText = document.getElementById('forecast-hero-category-text');
            if (catText) {
                catText.innerText = b.label.toUpperCase();
                catText.style.color = b.color;
            }

            // Health Impact Advisory Statement
            const healthAdvice = document.getElementById('forecast-hero-health-advice');
            if (healthAdvice) {
                healthAdvice.innerText = b.advice;
            }

            // Target Hour Badge (shows only the hour, e.g. "8 PM")
            const targetWinEl = document.getElementById('forecast-hero-target-window');
            if (targetWinEl) {
                if (curHour.label) targetWinEl.innerText = curHour.label;
                targetWinEl.style.backgroundColor = b.bg;
                targetWinEl.style.borderColor = b.border;
                targetWinEl.style.color = b.text;
            }

            // Smooth Arc Pin Calculation (Matching user reference card gauge)
            const getAqiProgress = (aqi) => {
                const v = Number(aqi);
                if (isNaN(v) || v <= 0) return 0;
                if (v <= 50)  return (v / 50) * 0.22;
                if (v <= 100) return 0.22 + ((v - 50) / 50) * 0.20;
                if (v <= 200) return 0.42 + ((v - 100) / 100) * 0.23;
                if (v <= 300) return 0.65 + ((v - 200) / 100) * 0.15;
                if (v <= 400) return 0.80 + ((v - 300) / 100) * 0.12;
                return Math.min(1.0, 0.92 + Math.min((v - 400) / 100, 1) * 0.08);
            };

            const t = getAqiProgress(curHour.aqi);
            const thetaStart = -157.38 * (Math.PI / 180);
            const thetaEnd = -22.62 * (Math.PI / 180);
            const currentTheta = thetaStart + t * (thetaEnd - thetaStart);
            const pinX = (75 + 65 * Math.cos(currentTheta)).toFixed(2);
            const pinY = (83 + 65 * Math.sin(currentTheta)).toFixed(2);

            const rainbowPin = document.getElementById('forecast-rainbow-pin');
            if (rainbowPin) {
                rainbowPin.setAttribute('cx', pinX);
                rainbowPin.setAttribute('cy', pinY);
                rainbowPin.setAttribute('stroke', b.color);
            }

            // AQI Hero Card: dynamically theme background to match the AQI bar in light, along with border & glowing shadow
            const aqiCard = document.getElementById('aqi-hero-card');
            if (aqiCard) {
                aqiCard.style.setProperty('background', b.cardBg, 'important');
                aqiCard.style.setProperty('border-color', b.border, 'important');
                aqiCard.style.boxShadow = `0 4px 20px ${b.shadow}`;
            }

            // Weather Box Updates (Fresh Cool Sky Weather Gradient)
            const weatherBox = document.getElementById('weather-hero-box');
            if (weatherBox) {
                weatherBox.style.setProperty('background', 'linear-gradient(135deg, #ffffff 0%, #f0f9ff 25%, #e0f2fe 65%, #bae6fd 100%)', 'important');
                weatherBox.style.setProperty('border-color', 'rgba(14, 165, 233, 0.38)', 'important');
                weatherBox.style.boxShadow = '0 4px 20px rgba(14, 165, 233, 0.12)';
            }

            const weatherDivider = document.getElementById('weather-metrics-divider');
            if (weatherDivider) {
                weatherDivider.style.borderColor = 'rgba(56, 189, 248, 0.3)';
            }

            const weatherIcon = document.getElementById('weather-hero-icon');
            if (weatherIcon) {
                weatherIcon.style.color = '#0284c7';
            }

            const weatherUnit = document.querySelector('.weather-temp-unit');
            if (weatherUnit) {
                weatherUnit.style.color = '#0284c7';
            }

            const microHum = document.getElementById('weather-micro-humidity');
            if (microHum) {
                microHum.style.backgroundColor = 'rgba(255, 255, 255, 0.88)';
                microHum.style.borderColor = 'rgba(14, 165, 233, 0.3)';
            }

            const microRain = document.getElementById('weather-micro-rain');
            if (microRain) {
                microRain.style.backgroundColor = 'rgba(255, 255, 255, 0.88)';
                microRain.style.borderColor = 'rgba(14, 165, 233, 0.3)';
            }

            const weatherTemp = document.getElementById('forecast-weather-temp');
            if (weatherTemp) {
                const t = curHour.temp;
                weatherTemp.innerText = (t !== undefined && t !== null && !isNaN(t)) ? (Number(t) % 1 === 0 ? Number(t) : Number(t).toFixed(1)) : '--';
            }

            const weatherHum = document.getElementById('forecast-weather-humidity');
            if (weatherHum) {
                const h = curHour.humidity;
                weatherHum.innerText = (h !== undefined && h !== null && !isNaN(h)) ? (Number(h) % 1 === 0 ? Number(h) : Number(h).toFixed(1)) + '%' : '--%';
            }

            const weatherRain = document.getElementById('forecast-weather-rain');
            const rp = curHour.rain_prob !== undefined && curHour.rain_prob !== null
                ? curHour.rain_prob
                : (curHour.rain_prob_pct !== undefined ? curHour.rain_prob_pct : 0);
            if (weatherRain) {
                weatherRain.innerText = Math.round(Number(rp)) + '%';
            }

            // Highlight corresponding active tier in Section 5: Rainfall Prediction Scale
            const rStatus = getRainStatus(rp);
            document.querySelectorAll('.rain-scale-item').forEach(el => {
                el.classList.remove('ring-2', 'ring-teal-500', 'shadow-md');
            });
            const activeRainTier = document.getElementById(`rain-tier-${rStatus.tier}`);
            if (activeRainTier) {
                activeRainTier.classList.add('ring-2', 'ring-teal-500', 'shadow-md');
            }

            // 3. Render 5 Predicted Model Criteria Pollutants Grid for this timestamp
            const paramsGrid = document.getElementById('predicted-parameters-grid');
            if (paramsGrid && trainedModelData && trainedModelData.parameters) {
                const p = trainedModelData.parameters;
                const curSub = curHour.sub_indices || {};

                // CPCB NAQI National Standard Safe Limits matching the Live page exactly
                const cpcbSafeLimits = {
                    pm25: '≤60 µg/m³',
                    pm10: '≤100 µg/m³',
                    co:   '≤2 mg/m³',
                    no2:  '≤80 µg/m³',
                    o3:   '≤100 µg/m³'
                };

                // Normal / High status tag based on parameter limit matching Live page
                const getParamTag = (key, val) => {
                    if (val === null || val === undefined || isNaN(val)) return null;
                    const v = Number(val);
                    const limits = { pm25: 60, pm10: 100, co: 2, no2: 80, o3: 100 };
                    const lim = limits[key];
                    if (lim === undefined) return null;
                    const isSafe = v <= lim;
                    return {
                        text: isSafe ? 'Normal' : 'High',
                        dot: isSafe ? '#16a34a' : '#dc2626',
                        bg: isSafe ? '#dcfce7' : '#fee2e2',
                        border: isSafe ? '#bbf7d0' : '#fecaca',
                        color: isSafe ? '#15803d' : '#b91c1c'
                    };
                };

                // Standard CPCB 6-tier status border color matching the Live page scale
                const getParamStatusColor = (key, val) => {
                    if (val === null || val === undefined || isNaN(val)) return '#cbd5e1';
                    const v = Number(val);
                    const colors = ['#59b61f', '#eec732', '#ea8c34', '#e95478', '#b33fba', '#c92033'];
                    const scales = {
                        pm25: [30, 60, 90, 120, 250],
                        pm10: [50, 100, 250, 350, 430],
                        co: [1.0, 2.0, 10.0, 17.0, 34.0],
                        no2: [40, 80, 180, 280, 400],
                        o3: [50, 100, 168, 208, 748]
                    };
                    const sc = scales[key];
                    if (!sc) {
                        return '#00bfa5';
                    }
                    for (let i = 0; i < sc.length; i++) {
                        if (v <= sc[i]) return colors[i];
                    }
                    return colors[colors.length - 1];
                };

                const getValColorClass = (key) => {
                    switch (key) {
                        case 'pm25': return 'text-sky-500';
                        case 'pm10': return 'text-indigo-500';
                        case 'co': return 'text-slate-600';
                        case 'no2': return 'text-purple-500';
                        case 'o3': return 'text-amber-500';
                        default: return 'text-slate-900';
                    }
                };

                const formatCurVal = (val, key) => {
                    if (val === null || val === undefined || isNaN(val)) return '--';
                    const num = Number(val);
                    if (key === 'co') return num.toFixed(2);
                    return num.toFixed(2);
                };

                const items = [
                    {
                        key: 'pm25', name: 'PM₂.₅', icon: '🌫️',
                        iconBgClass: 'bg-sky-50 text-sky-500',
                        current: formatCurVal(curHour.pm25, 'pm25'),
                        unit: 'µg/m³',
                        tag: getParamTag('pm25', curHour.pm25),
                        borderColor: getParamStatusColor('pm25', curHour.pm25),
                        valColor: 'text-sky-500',
                        footerVal: cpcbSafeLimits.pm25
                    },
                    {
                        key: 'pm10', name: 'PM₁₀', icon: '☁️',
                        iconBgClass: 'bg-indigo-50 text-indigo-500',
                        current: formatCurVal(curHour.pm10, 'pm10'),
                        unit: 'µg/m³',
                        tag: getParamTag('pm10', curHour.pm10),
                        borderColor: getParamStatusColor('pm10', curHour.pm10),
                        valColor: 'text-indigo-500',
                        footerVal: cpcbSafeLimits.pm10
                    },
                    {
                        key: 'co', name: 'CO', icon: '💨',
                        iconBgClass: 'bg-slate-50 text-slate-600',
                        current: formatCurVal(curHour.co, 'co'),
                        unit: 'mg/m³',
                        tag: getParamTag('co', curHour.co),
                        borderColor: getParamStatusColor('co', curHour.co),
                        valColor: 'text-slate-600',
                        footerVal: cpcbSafeLimits.co
                    },
                    {
                        key: 'no2', name: 'NO₂', icon: '🏭',
                        iconBgClass: 'bg-purple-50 text-purple-500',
                        current: formatCurVal(curHour.no2, 'no2'),
                        unit: 'µg/m³',
                        tag: getParamTag('no2', curHour.no2),
                        borderColor: getParamStatusColor('no2', curHour.no2),
                        valColor: 'text-purple-500',
                        footerVal: cpcbSafeLimits.no2
                    },
                    {
                        key: 'o3', name: 'O₃', icon: '☀️',
                        iconBgClass: 'bg-amber-50 text-amber-500',
                        current: formatCurVal(curHour.o3, 'o3'),
                        unit: 'µg/m³',
                        tag: getParamTag('o3', curHour.o3),
                        borderColor: getParamStatusColor('o3', curHour.o3),
                        valColor: 'text-amber-500',
                        footerVal: cpcbSafeLimits.o3
                    }
                ];

                paramsGrid.innerHTML = items.map((it) => `
                    <div id="card-box-${it.key}" class="pollutant-card flex flex-col justify-between gap-2.5 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 shadow-[0_4px_16px_rgba(15,23,42,0.04)] relative overflow-hidden h-full min-h-[110px]" style="border-left: 5px solid ${it.borderColor};">
                      <div class="pollutant-card-top flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                          <div class="pollutant-icon-box w-8 h-8 sm:w-10 sm:h-10 rounded-lg ${it.iconBgClass} flex items-center justify-center text-sm sm:text-lg shrink-0">${it.icon}</div>
                          <div class="pollutant-title-box">
                            <div class="pollutant-name text-[13px] sm:text-[15px] font-bold font-sans text-slate-900 leading-tight">${it.name}</div>
                            <div class="pollutant-who text-[9px] sm:text-[10.5px] text-slate-500 mt-[2px] whitespace-nowrap">CPCB: ${it.footerVal}</div>
                          </div>
                        </div>
                        <div class="pollutant-info-icon w-6 h-6 rounded-full bg-slate-50 border border-slate-300 flex items-center justify-center text-slate-500 shrink-0">
                          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                        </div>
                      </div>

                      <div class="pollutant-card-bottom flex items-end justify-between w-full mt-1">
                        <div class="pollutant-val-wrap flex flex-col">
                          <div id="card-${it.key}" class="font-mono text-lg sm:text-2xl font-extrabold ${it.valColor} leading-none">${it.current}</div>
                          <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">${it.unit}</div>
                        </div>
                        ${it.tag ? `
                        <div id="tag-${it.key}" class="pollutant-tag px-2 py-1 rounded-full text-[9px] sm:text-[11px] font-bold flex items-center gap-1 shrink-0" style="background-color: ${it.tag.bg}; border: 1px solid ${it.tag.border}; color: ${it.tag.color};">
                          <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background-color: ${it.tag.dot};"></span>
                          <span>${it.tag.text}</span>
                        </div>
                        ` : ''}
                      </div>

                      <div class="pollutant-chevron text-slate-400 shrink-0">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
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
                topSubtext.innerText = isStandby ? 'Ready for new model deployment in forcast/' : 'Dual-Engine SAPE: AQI (48h Window) + Multi-Weather Wavelet LSTM (72h Window from WEATHER_NODE1)';
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
                const rainProbRaw = (item.rain_prob !== undefined && item.rain_prob !== null) 
                    ? item.rain_prob 
                    : (item.rain_prob_pct !== undefined ? item.rain_prob_pct : 0);
                const rainChance = (rainProbRaw !== '--' && rainProbRaw !== undefined && rainProbRaw !== null && !isNaN(Number(rainProbRaw)))
                    ? Math.round(Number(rainProbRaw))
                    : 0;
                const rStatus = getRainStatus(rainChance);

                return `
                    <div class="hourly-card ${isSelected ? 'active-hour' : ''}" onclick="selectForecastHour(${idx})" style="cursor: pointer;" title="Rain Probability: ${rainChance}% (${rStatus.label})">
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
                        <div class="flex items-center justify-between text-[11px] font-medium text-slate-600 pt-0.5 border-t border-slate-100" title="Precipitation: ${rainChance}%">
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

    // Fetch and load forecast data from backend (Stale-While-Revalidate)
    async function loadForecastData(force = false) {
        try {
            const activeNode = (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));

            // Immediate paint: render any cached forecast right away so UI is instant
            if (!force) {
                if (window.__forecastCache && window.__forecastCache.hourly) {
                    window.loadTrainedForecastModel(window.__forecastCache);
                } else if (typeof window.getLocalCache === 'function') {
                    const localF = window.getLocalCache(window.STORAGE_KEYS ? window.STORAGE_KEYS.FORECAST : 'smartairnet_forecast_payload');
                    if (localF && localF.hourly && localF.hourly.length > 0) {
                        window.__forecastCache = localF;
                        window.loadTrainedForecastModel(localF);
                    }
                }
            }

            // Always revalidate from network in the background to ensure parity with server
            let data;
            const bust = 'v=' + Date.now();
            if (typeof window.fetchForecastData === 'function') {
                data = await window.fetchForecastData(force);
            } else {
                const resp = await fetch(`api/telemetry.php?action=forecast&${bust}${force ? '&force=1' : ''}`, {
                    cache: 'no-store'
                });
                if (!resp.ok) throw new Error('Network error loading forecast');
                data = await resp.json();
            }
            if (data && data.hourly && data.hourly.length > 0) {
                window.__forecastCache = data;
                if (typeof window.setLocalCache === 'function') {
                    window.setLocalCache(window.STORAGE_KEYS ? window.STORAGE_KEYS.FORECAST : 'smartairnet_forecast_payload', data);
                }
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
        if (data && data.hourly && typeof window.setLocalCache === 'function') {
            window.setLocalCache(window.STORAGE_KEYS ? window.STORAGE_KEYS.FORECAST : 'smartairnet_forecast_payload', data);
        }
        renderForecastUI();
    };

    // Initial render and data fetch
    function initForecastPage() {
        renderForecastUI();
        // Always sync latest forecast telemetry in background so local and hosted remain 100% synchronized
        loadForecastData(false);
        startAutoRefreshForecast();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initForecastPage);
    } else {
        initForecastPage();
    }
})();
</script>
