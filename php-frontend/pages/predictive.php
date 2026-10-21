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
?>

<div class="forecast-page-container p-3 sm:p-6 flex flex-col gap-4 sm:gap-6 max-w-[1200px] mx-auto text-slate-900 font-sans">

  <div class="mb-0.5 sm:mb-2">
    <h1 class="text-xl sm:text-[28px] font-extrabold text-slate-900 m-0 tracking-tight">Air Quality Index (AQI) Forecast</h1>
    <p class="m-0 text-slate-500 text-xs sm:text-sm font-medium mt-0.5">Atmospheric pollutant and AQI predictive forecasting for SMVITM Campus.</p>
  </div>

  <!-- ── 2. High-Visibility AQI Advisory Banner ── -->
  <div id="forecast-advisory-banner" class="bg-gradient-to-r from-slate-500/10 via-slate-400/10 to-slate-500/10 border-[1.5px] border-slate-300 rounded-[18px] sm:rounded-[20px] p-3.5 sm:p-5 shadow-[0_4px_20px_rgba(15,23,42,0.04)] relative overflow-hidden transition-all">
    <div class="flex items-start gap-3 sm:gap-3.5">
      <div id="advisory-icon-box" class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-slate-700 text-white flex items-center justify-center shrink-0 shadow-md shadow-slate-700/20 mt-0.5">
        <i data-lucide="clock" class="w-4 h-4 sm:w-5 sm:h-5"></i>
      </div>
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap mb-1">
          <span class="font-extrabold text-slate-900 text-sm sm:text-base tracking-tight" id="advisory-title">Predictive Advisory System · Standby</span>
          <span id="advisory-severity-badge" class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-200 text-slate-700 border border-slate-300 tracking-wider uppercase font-mono">
            AWAITING MODEL
          </span>
        </div>
        <p class="text-xs sm:text-[13px] text-slate-700 leading-relaxed m-0" id="advisory-desc">
          Predictive advisories and microclimate alerts will automatically generate once the custom trained forecast model is linked. Real-time campus telemetry continues monitoring baseline conditions.
        </p>

        <!-- Collapsible Action Guide -->
        <div id="advisory-expand-section" class="mt-3 pt-3 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-2.5 text-xs text-slate-600" style="display: none;">
          <div class="flex items-center gap-2 bg-white/80 p-2 sm:p-2.5 rounded-xl border border-slate-200">
            <span class="text-base">🏃</span>
            <span><strong>Outdoor Sports:</strong> <span id="advisory-sports-val">Awaiting model prediction</span></span>
          </div>
          <div class="flex items-center gap-2 bg-white/80 p-2 sm:p-2.5 rounded-xl border border-slate-200">
            <span class="text-base">🪟</span>
            <span><strong>Campus Ventilation:</strong> <span id="advisory-vent-val">Awaiting model prediction</span></span>
          </div>
          <div class="flex items-center gap-2 bg-white/80 p-2 sm:p-2.5 rounded-xl border border-slate-200">
            <span class="text-base">💧</span>
            <span><strong>Hydration / Advisory:</strong> <span id="advisory-hydr-val">Awaiting model prediction</span></span>
          </div>
        </div>

        <button onclick="toggleAdvisoryDetails()" id="btn-toggle-advisory" class="mt-2.5 text-xs font-bold text-slate-700 hover:text-slate-900 flex items-center gap-1.5 cursor-pointer bg-transparent border-none p-0">
          <span id="toggle-advisory-text">View Action Guidance</span>
          <i data-lucide="chevron-down" class="w-3.5 h-3.5 transition-transform" id="toggle-advisory-icon"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- ── 3. Forecast Hero Summary Card: Predicted AQI ── -->
  <div class="bg-white rounded-[20px] sm:rounded-[24px] p-4 sm:p-7 border border-slate-200 shadow-[0_4px_24px_rgba(15,23,42,0.05)] relative overflow-hidden">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 sm:gap-6">
      
      <!-- Left Hero Info: Big AQI & Category -->
      <div class="flex-1">
        <div class="flex items-center gap-2 flex-wrap text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5 sm:mb-2">
          <span>Predicted Air Quality Index</span>
          <span>•</span>
          <span class="text-slate-600 font-semibold" id="forecast-date-str">Loading...</span>
          <span id="forecast-target-time-badge" class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-[#00bfa5]/10 text-[#00bfa5] border border-teal-200 font-mono tracking-tight">Next Timestamp</span>
        </div>

        <div class="flex items-baseline gap-3 mb-2 sm:mb-2.5">
          <span id="forecast-hero-aqi" class="text-5xl sm:text-6xl font-extrabold text-slate-400 font-mono tracking-tight">--</span>
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

        <div class="flex items-center gap-2 text-xs text-slate-400 font-medium">
          <i data-lucide="thermometer" class="w-3.5 h-3.5 text-slate-400"></i>
          <span>Ambient: <strong id="forecast-hero-temp" class="text-slate-600 font-mono">--°C</strong>, <strong id="forecast-hero-humidity" class="text-slate-600 font-mono">--%</strong> Humidity</span>
        </div>
      </div>



    </div>
  </div>

  <!-- ── 3.5. 7 Predicted Model Parameters (Outputs) Grid ── -->
  <div class="bg-white rounded-[24px] p-4 sm:p-6 border border-slate-200 shadow-[0_4px_24px_rgba(15,23,42,0.05)]">
    <div class="flex items-center justify-between mb-4 px-1">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-[#e6fcf9] text-[#00bfa5] flex items-center justify-center">
          <i data-lucide="activity" class="w-4 h-4"></i>
        </div>
        <div>
          <h2 class="text-base font-extrabold text-slate-900 m-0 tracking-tight">Predicted Parameters (Next 24 Hours)</h2>
          <p id="forecast-model-subtitle" class="text-xs text-slate-400 m-0">Direct multi-target outputs from trained LSTM model</p>
        </div>
      </div>
      <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider font-mono">7 Targets + AQI</span>
    </div>

    <!-- Grid of the 7 predicted parameters -->
    <div id="predicted-parameters-grid" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2.5 sm:gap-3">
      <div class="text-xs text-slate-400 py-6 text-center col-span-full">Loading predicted parameters...</div>
    </div>
  </div>

  <!-- ── 4. Hourly Forecast: Edge-to-Edge Horizontally Scrolling Reel ── -->
  <div class="bg-white rounded-[24px] p-4 sm:p-6 border border-slate-200 shadow-[0_4px_24px_rgba(15,23,42,0.05)]">
    
    <div class="flex items-center justify-between mb-3 px-1">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-[#e6fcf9] text-[#00bfa5] flex items-center justify-center">
          <i data-lucide="clock" class="w-4 h-4"></i>
        </div>
        <div>
          <h2 id="hourly-forecast-heading" class="text-base font-extrabold text-slate-900 m-0 tracking-tight">Hourly Forecast (Next 24 Hours)</h2>
          <p class="text-xs text-slate-400 m-0">Swipe horizontally to track predicted temperature, humidity, and AQI</p>
        </div>
      </div>
      <div class="hidden sm:flex items-center gap-1.5 text-xs text-slate-400 font-medium">
        <span>👈 Drag / Scroll 👉</span>
      </div>
    </div>

    <!-- Edge-to-Edge Horizontal Reel with Scroll-Snap -->
    <div id="hourly-forecast-reel" class="hourly-forecast-reel flex gap-3 overflow-x-auto py-2 px-1" style="scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; scrollbar-width: none;">
      <!-- Populated dynamically by JS with smooth cards -->
      <div class="text-xs text-slate-400 py-6 text-center w-full">Loading hourly predictions...</div>
    </div>

  </div>



  <!-- ── 6. Model Footnote & Campus Station Attribution ── -->
  <div class="bg-slate-50 border border-slate-200 rounded-[20px] p-4 sm:p-5 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-[#00bfa5] shrink-0 shadow-sm">
        <i data-lucide="cpu" class="w-4 h-4"></i>
      </div>
      <div>
        <div class="font-bold text-slate-700">SMVITM Atmospheric Predictive Engine (SAPE)</div>
        <div class="text-[11px] text-slate-400 mt-0.5">Telemetry ground sensors connected • Custom trained model integration ready</div>
      </div>
    </div>
    <div class="flex items-center gap-2 font-mono text-[11px]">
      <span class="inline-block w-2 h-2 rounded-full bg-amber-400" id="forecast-sync-dot"></span>
      <span>Sync Status: <strong class="text-slate-700" id="forecast-sync-time">Active</strong></span>
    </div>
  </div>

</div>

<!-- ── Custom Styles for Forecast Page ── -->
<style>
/* Horizontal Reel Custom Styling (Mobile & Desktop) */
.hourly-forecast-reel {
  display: flex !important;
  flex-direction: row !important;
  flex-wrap: nowrap !important;
  overflow-x: auto !important;
  -webkit-overflow-scrolling: touch !important;
  scroll-snap-type: x mandatory !important;
  scrollbar-width: none !important;
  gap: 10px !important;
  padding: 8px 4px 12px 4px !important;
  width: 100% !important;
  box-sizing: border-box !important;
}
.hourly-forecast-reel::-webkit-scrollbar {
  display: none !important;
  width: 0 !important;
  height: 0 !important;
}
.hourly-card {
  scroll-snap-align: start !important;
  flex: 0 0 82px !important;
  min-width: 82px !important;
  max-width: 82px !important;
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  padding: 10px 6px !important;
  border-radius: 16px !important;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  user-select: none;
  box-sizing: border-box !important;
}
.hourly-card:hover {
  transform: translateY(-2px);
  border-color: #00bfa5;
  background: #ffffff;
  box-shadow: 0 6px 18px rgba(0, 191, 165, 0.12);
}
.hourly-card.active-hour {
  background: #ffffff !important;
  border-color: #00bfa5 !important;
  box-shadow: 0 6px 20px rgba(0, 191, 165, 0.18) !important;
}
.hourly-card.active-hour .hour-label {
  color: #00bfa5 !important;
  font-weight: 800 !important;
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
</style>

<!-- ── JavaScript Forecast Model Engine ── -->
<script>
(function() {
    let trainedModelData = null; // Will hold user's model once trained and connected

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

    // Toggle advisory actions
    window.toggleAdvisoryDetails = function() {
        const sec = document.getElementById('advisory-expand-section');
        const icon = document.getElementById('toggle-advisory-icon');
        const text = document.getElementById('toggle-advisory-text');
        if (!sec) return;
        const isHidden = sec.style.display === 'none';
        if (isHidden) {
            sec.style.display = 'grid';
            icon.style.transform = 'rotate(180deg)';
            text.innerText = 'Hide Action Guidance';
        } else {
            sec.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
            text.innerText = 'View Action Guidance';
        }
    };

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

    // Discard past hours and ensure forecast starts strictly from the NEXT upcoming timestamp
    function filterAndNormalizeHourly(rawList) {
        if (!rawList || !Array.isArray(rawList) || rawList.length === 0) return [];

        const now = new Date();
        const nowMs = now.getTime();

        // Discard any hour whose timestamp has already started or elapsed (<= nowMs)
        // so the forecast starts strictly from the NEXT upcoming timestamp
        const unelapsed = rawList.filter(item => {
            if (item.iso) {
                const itemTime = new Date(item.iso).getTime();
                return itemTime > nowMs;
            }
            if (typeof item.hour === 'number') {
                return item.hour > now.getHours();
            }
            return true;
        });

        // Fallback: if all 24 hours elapsed (e.g. older model file from days ago),
        // cleanly align the 24 predictions starting from the next upcoming clock hour
        let activeList = unelapsed;
        if (unelapsed.length === 0) {
            const nextHourDt = new Date(now.getFullYear(), now.getMonth(), now.getDate(), now.getHours() + 1, 0, 0, 0);
            activeList = rawList.map((item, idx) => {
                const shiftedDt = new Date(nextHourDt.getTime() + (idx * 3600000));
                return {
                    ...item,
                    index: idx,
                    hour: shiftedDt.getHours(),
                    iso: shiftedDt.toISOString()
                };
            });
        }

        return activeList.map((item, idx) => {
            const isFirst = (idx === 0);
            let timeLabel = item.label;

            if (item.iso) {
                const dt = new Date(item.iso);
                timeLabel = dt.toLocaleTimeString('en-US', { hour: 'numeric', hour12: true });
            } else if (typeof item.hour === 'number') {
                const h = item.hour % 24;
                const ampm = h >= 12 ? 'PM' : 'AM';
                const h12 = h % 12 || 12;
                timeLabel = `${h12} ${ampm}`;
            }

            return {
                ...item,
                index: idx,
                isNext: isFirst,
                label: timeLabel
            };
        });
    }

    // Generate upcoming hours structure, filtering out any hours that have passed
    function generateHourlyData() {
        if (trainedModelData && trainedModelData.hourly) {
            return filterAndNormalizeHourly(trainedModelData.hourly);
        }

        const now = new Date();
        const currentHour = now.getHours();
        const hours = [];

        for (let i = 0; i < 24; i++) {
            const h = (currentHour + i) % 24;
            const hourDate = new Date(now.getTime() + (i * 3600000));
            const timeLabel = i === 0 ? 'Now' : hourDate.toLocaleTimeString('en-US', { hour: 'numeric', hour12: true });

            hours.push({
                index: i,
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
                isNow: i === 0
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
        const hourly = generateHourlyData();
        const phases = generateTimelinePhases();

        // Update heading with active remaining hour count
        const headingEl = document.getElementById('hourly-forecast-heading');
        if (headingEl && hourly && hourly.length > 0) {
            headingEl.innerText = `Hourly Forecast (Next ${hourly.length} Hours)`;
        }

        // 1. Date and Sync time
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' });
        const dateEl = document.getElementById('forecast-date-str');
        if (dateEl) dateEl.innerText = dateStr;
        const syncEl = document.getElementById('forecast-sync-time');
        if (syncEl) syncEl.innerText = trainedModelData ? 'Model Active' : 'Model Standby';

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

            // Next Timestamp Badge
            const badgeEl = document.getElementById('forecast-target-time-badge');
            if (badgeEl) {
                if (isNext) {
                    badgeEl.className = "px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-[#00bfa5]/10 text-[#00bfa5] border border-teal-200 font-mono tracking-tight flex items-center gap-1";
                    badgeEl.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Next Timestamp: ${curHour.label}`;
                } else {
                    badgeEl.className = "px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-50 text-blue-700 border border-blue-200 font-mono tracking-tight flex items-center gap-1.5";
                    badgeEl.innerHTML = `<span>Viewing: ${curHour.label}</span> · <span onclick="selectForecastHour(0)" class="underline cursor-pointer hover:text-blue-900 font-bold">Reset to Next Hour</span>`;
                }
            }

            // 3. Render 7 Predicted Model Parameters Grid for this timestamp
            const paramsGrid = document.getElementById('predicted-parameters-grid');
            if (paramsGrid && trainedModelData && trainedModelData.parameters) {
                const p = trainedModelData.parameters;
                const curSub = curHour.sub_indices || {};
                const items = [
                    { key: 'pm25', name: 'PM2.5', icon: '💨', current: curHour.pm25, subindex: curSub['PM2.5'] ?? curSub['pm25'] ?? p.pm25.current_subindex, unit: 'µg/m³', range: `${p.pm25.min} - ${p.pm25.max}`, mean: p.pm25.mean, limit: p.pm25.safe_limit },
                    { key: 'pm10', name: 'PM10', icon: '🌫️', current: curHour.pm10, subindex: curSub['PM10'] ?? curSub['pm10'] ?? p.pm10.current_subindex, unit: 'µg/m³', range: `${p.pm10.min} - ${p.pm10.max}`, mean: p.pm10.mean, limit: p.pm10.safe_limit },
                    { key: 'no2', name: 'NO₂', icon: '🚦', current: curHour.no2, subindex: curSub['NO₂'] ?? curSub['no2'] ?? curSub['NO2'] ?? p.no2.current_subindex, unit: 'µg/m³', range: `${p.no2.min} - ${p.no2.max}`, mean: p.no2.mean, limit: p.no2.safe_limit },
                    { key: 'co', name: 'CO', icon: '🏭', current: curHour.co, subindex: curSub['CO'] ?? curSub['co'] ?? p.co.current_subindex, unit: 'mg/m³', range: `${p.co.min} - ${p.co.max}`, mean: p.co.mean, limit: p.co.safe_limit },
                    { key: 'o3', name: 'O₃', icon: '☀️', current: curHour.o3, subindex: curSub['O₃'] ?? curSub['o3'] ?? curSub['O3'] ?? p.o3.current_subindex, unit: 'µg/m³', range: `${p.o3.min} - ${p.o3.max}`, mean: p.o3.mean, limit: p.o3.safe_limit },
                    { key: 'temp', name: 'Temp', icon: '🌡️', current: curHour.temp, subindex: null, unit: '°C', range: `${p.temp.min}° - ${p.temp.max}°`, mean: `${p.temp.mean}°`, limit: 'Campus Ambient' },
                    { key: 'humidity', name: 'Humidity', icon: '💧', current: curHour.humidity, subindex: null, unit: '%', range: `${p.humidity.min}% - ${p.humidity.max}%`, mean: `${p.humidity.mean}%`, limit: 'Comfort 40-70%' }
                ];

                paramsGrid.innerHTML = items.map((it, pIdx) => `
                    <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-2.5 sm:p-3 flex flex-col justify-between hover:border-teal-500/40 transition-all shadow-xs ${pIdx === 6 ? 'col-span-2 sm:col-span-1' : ''}">
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
                          <span>Safe: <strong class="text-slate-500 font-mono">${it.limit}</strong></span>
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
            const tierHours = trainedModelData.tier_hours || 168;
            const tierSel = trainedModelData.tier_selection;
            const aqiTable = (tierSel && tierSel.aqi_table) || 'AQI_NODE1';

            if (topChip) {
                topChip.className = "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10.5px] sm:text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 font-mono";
                topChip.innerHTML = `<span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> MODEL ACTIVE · ${tierHours}H TIER`;
            }
            const topSubtext = document.getElementById('forecast-model-subtext');
            if (topSubtext) {
                topSubtext.innerText = `Ingest: ${aqiTable} · ${tierHours}h Input Data Window`;
            }

            // Ingest Status Bar
            const tableChip = document.getElementById('tier-input-table-chip');
            if (tableChip) tableChip.innerText = aqiTable;

            const availChip = document.getElementById('tier-available-hours-chip');
            if (availChip) {
                const count = tierSel ? tierSel.available_hours : tierHours;
                availChip.innerText = `${count}h Available in ${aqiTable}`;
            }

            const expText = document.getElementById('tier-explanation-text');
            if (expText && tierSel && tierSel.explanation) {
                const cleanExp = (tierSel.explanation || '')
                    .replace(/\s*\([^)]*\.keras\)/gi, '')
                    .replace(/\s*\(model_tier_[^)]+\)/gi, '')
                    .replace(/\.keras/gi, '');
                expText.innerText = cleanExp;
            }

            // Update active tier button state
            document.querySelectorAll('.tier-selector-btn').forEach(btn => {
                btn.className = "tier-selector-btn px-2 py-1 rounded-lg text-[10.5px] font-mono font-semibold bg-white text-slate-700 border border-slate-200 hover:border-teal-400 cursor-pointer transition-all";
            });
            const activeTierBtn = document.getElementById('tier-btn-' + tierHours) || document.getElementById('tier-btn-auto');
            if (activeTierBtn) {
                activeTierBtn.className = "tier-selector-btn px-2.5 py-1 rounded-lg text-[10.5px] font-mono font-extrabold bg-[#00bfa5] text-white border border-teal-500 shadow-xs cursor-pointer transition-all";
            }
            const subtitleEl = document.getElementById('forecast-model-subtitle');
            if (subtitleEl) {
                subtitleEl.innerText = `Direct multi-target outputs from trained model`;
            }

            // Advisory Banner
            if (trainedModelData.advisory) {
                const adv = trainedModelData.advisory;
                const advTitle = document.getElementById('advisory-title');
                if (advTitle) advTitle.innerText = adv.title;

                const advBadge = document.getElementById('advisory-severity-badge');
                if (advBadge) {
                    advBadge.innerText = adv.severity;
                    if (adv.severity === 'GOOD') {
                        advBadge.className = "px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300 tracking-wider uppercase font-mono";
                    } else if (adv.severity === 'SATISFACTORY') {
                        advBadge.className = "px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-lime-100 text-lime-800 border border-lime-300 tracking-wider uppercase font-mono";
                    } else {
                        advBadge.className = "px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-800 border border-amber-300 tracking-wider uppercase font-mono";
                    }
                }

                const advDesc = document.getElementById('advisory-desc');
                if (advDesc) advDesc.innerText = adv.desc;

                const sportsVal = document.getElementById('advisory-sports-val');
                if (sportsVal) sportsVal.innerText = adv.sports;
                const ventVal = document.getElementById('advisory-vent-val');
                if (ventVal) ventVal.innerText = adv.ventilation;
                const hydrVal = document.getElementById('advisory-hydr-val');
                if (hydrVal) hydrVal.innerText = adv.hydration;

                const banner = document.getElementById('forecast-advisory-banner');
                if (banner) {
                    banner.className = "bg-gradient-to-r from-teal-500/10 via-emerald-400/10 to-teal-500/10 border-[1.5px] border-emerald-200 rounded-[20px] p-4 sm:p-5 shadow-[0_4px_20px_rgba(15,23,42,0.04)] relative overflow-hidden transition-all";
                }
                const iconBox = document.getElementById('advisory-icon-box');
                if (iconBox) {
                    iconBox.className = "w-10 h-10 rounded-xl bg-[#00bfa5] text-white flex items-center justify-center shrink-0 shadow-md shadow-teal-500/20 mt-0.5";
                }
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
                return `
                    <div class="hourly-card ${isSelected ? 'active-hour' : ''}" onclick="selectForecastHour(${idx})" style="cursor: pointer;" title="Click to view ${item.label} predictions">
                      <div class="hour-label text-[11px] font-bold ${isFirst ? 'text-teal-600' : 'text-slate-500'} mb-1.5 whitespace-nowrap flex items-center gap-1">
                        <span>${item.label}</span>
                        ${isFirst ? '<span class="text-[8.5px] font-mono px-1 rounded bg-teal-50 text-teal-700 border border-teal-200">NEXT</span>' : ''}
                      </div>
                      <div class="text-2xl my-1 leading-none text-slate-400 font-sans">${item.icon}</div>
                      <div class="font-mono text-base font-extrabold text-slate-800 leading-tight">${item.temp}°C</div>
                      <div class="text-[9.5px] font-semibold text-blue-600 mt-1 flex items-center gap-0.5"><span>💧</span>${item.humidity}%</div>
                      <div class="mt-2 px-2 py-0.5 rounded-full text-[9px] font-extrabold font-mono" style="background:${b.bg}; color:${b.text}; border:1px solid ${b.border}; white-space:nowrap;">
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

    // Auto-refresh interval (every 15 seconds) + real-time hour change checker
    function startAutoRefreshForecast() {
        if (autoRefreshForecastTimer) clearInterval(autoRefreshForecastTimer);
        let lastRenderHour = new Date().getHours();

        autoRefreshForecastTimer = setInterval(() => {
            const currentHour = new Date().getHours();
            // If the clock hour has rolled over (e.g. 10 -> 11), re-render immediately to drop the passed hour
            if (currentHour !== lastRenderHour) {
                lastRenderHour = currentHour;
                renderForecastUI();
                loadForecastData(true);
                return;
            }
            if (!document.hidden) {
                loadForecastData(false);
            }
        }, 15000);
    }

    // Clean up timer on SPA navigation
    window.__cleanupCurrentPage = function() {
        if (autoRefreshForecastTimer) {
            clearInterval(autoRefreshForecastTimer);
            autoRefreshForecastTimer = null;
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
