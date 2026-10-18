<?php
/**
 * Smart WeatherNet - Predictive Forecast Page
 * Edge-to-edge hourly reel, 7-day accordion with min/max slider bars,
 * and high-visibility AQI advisory alerts.
 */
?>

<div class="forecast-page-container p-3.5 sm:p-6 flex flex-col gap-5 sm:gap-6 max-w-[1200px] mx-auto text-slate-900 font-sans">

  <!-- ── 1. Top Section Header ── -->
  <div class="flex justify-between items-end mb-1 sm:mb-2">
    <div>
      <div class="flex items-center gap-2 mb-1">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200 font-mono">
          <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span> MODEL STANDBY
        </span>
        <span class="text-xs text-slate-400 font-medium">SMVITM Campus Model · Training Pending</span>
      </div>
      <h1 class="text-2xl sm:text-[28px] font-extrabold text-slate-900 m-0 tracking-tight">Weather &amp; AQI Forecast</h1>
      <p class="m-0 text-slate-500 text-xs sm:text-sm font-medium mt-0.5">High-resolution predictive microclimate modeling for Bantakal region.</p>
    </div>
    
    <button onclick="refreshForecast()" id="forecast-refresh-btn" class="hidden sm:inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-slate-600 hover:text-slate-900 font-bold text-xs shadow-sm hover:shadow transition-all">
      <i data-lucide="refresh-cw" class="w-3.5 h-3.5 text-[#00bfa5]" id="forecast-refresh-icon"></i>
      <span>Refresh Model</span>
    </button>
  </div>

  <!-- ── 2. High-Visibility AQI Advisory Banner ── -->
  <div id="forecast-advisory-banner" class="bg-gradient-to-r from-slate-500/10 via-slate-400/10 to-slate-500/10 border-[1.5px] border-slate-300 rounded-[20px] p-4 sm:p-5 shadow-[0_4px_20px_rgba(15,23,42,0.04)] relative overflow-hidden transition-all">
    <div class="flex items-start gap-3.5">
      <div class="w-10 h-10 rounded-xl bg-slate-700 text-white flex items-center justify-center shrink-0 shadow-md shadow-slate-700/20 mt-0.5">
        <i data-lucide="clock" class="w-5 h-5"></i>
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
        <div id="advisory-expand-section" class="mt-3 pt-3 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs text-slate-600" style="display: none;">
          <div class="flex items-center gap-2 bg-white/80 p-2.5 rounded-xl border border-slate-200">
            <span class="text-base">🏃</span>
            <span><strong>Outdoor Sports:</strong> Awaiting model prediction</span>
          </div>
          <div class="flex items-center gap-2 bg-white/80 p-2.5 rounded-xl border border-slate-200">
            <span class="text-base">🪟</span>
            <span><strong>Campus Ventilation:</strong> Awaiting model prediction</span>
          </div>
          <div class="flex items-center gap-2 bg-white/80 p-2.5 rounded-xl border border-slate-200">
            <span class="text-base">💧</span>
            <span><strong>Hydration / Advisory:</strong> Awaiting model prediction</span>
          </div>
        </div>

        <button onclick="toggleAdvisoryDetails()" id="btn-toggle-advisory" class="mt-2.5 text-xs font-bold text-slate-700 hover:text-slate-900 flex items-center gap-1.5 cursor-pointer bg-transparent border-none p-0">
          <span id="toggle-advisory-text">View Model Parameters</span>
          <i data-lucide="chevron-down" class="w-3.5 h-3.5 transition-transform" id="toggle-advisory-icon"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- ── 3. Forecast Hero Summary Card ── -->
  <div class="bg-white rounded-[24px] p-5 sm:p-7 border border-slate-200 shadow-[0_4px_24px_rgba(15,23,42,0.05)] relative overflow-hidden">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-5 sm:gap-6">
      
      <!-- Left Hero Info -->
      <div class="flex-1">
        <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
          <span>Today's Forecast Summary</span>
          <span>•</span>
          <span class="text-slate-600 font-semibold" id="forecast-date-str">Loading...</span>
        </div>

        <div class="flex items-baseline gap-3 mb-2">
          <span id="forecast-hero-temp" class="text-5xl sm:text-6xl font-extrabold text-slate-400 font-mono tracking-tight">--</span>
          <div class="flex flex-col">
            <span class="text-xl sm:text-2xl font-bold text-slate-400 font-mono">°C</span>
            <span id="forecast-condition-text" class="text-sm font-bold text-slate-500">Awaiting Model</span>
          </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
          <span id="forecast-hero-aqi-pill" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-600 border border-slate-200">
            <span class="w-2 h-2 rounded-full bg-slate-400"></span>
            <span id="forecast-hero-aqi-text">AQI -- · Standby</span>
          </span>
          <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
            <i data-lucide="droplets" class="w-3 h-3 text-slate-400"></i>
            <span id="forecast-rain-prob">Rain Chance: --%</span>
          </span>
          <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
            <i data-lucide="wind" class="w-3 h-3 text-slate-400"></i>
            <span id="forecast-wind-summary">Wind: -- km/h</span>
          </span>
        </div>
      </div>

      <!-- Right: Min/Max & Atmospheric Indicators -->
      <div class="flex sm:flex-row md:flex-col gap-3 justify-between md:justify-center md:items-end border-t md:border-t-0 md:border-l border-slate-100 pt-4 md:pt-0 md:pl-6">
        <div class="flex items-center gap-4 text-center">
          <div class="bg-slate-50 px-3.5 py-2.5 rounded-2xl border border-slate-200">
            <div class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider">Day Low</div>
            <div class="text-xl sm:text-2xl font-extrabold text-slate-500 font-mono" id="forecast-day-low">--°</div>
          </div>
          <div class="bg-slate-50 px-3.5 py-2.5 rounded-2xl border border-slate-200">
            <div class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider">Day High</div>
            <div class="text-xl sm:text-2xl font-extrabold text-slate-500 font-mono" id="forecast-day-high">--°</div>
          </div>
        </div>
        <div class="text-right text-[11px] text-slate-400 font-medium">
          Confidence: <strong class="text-slate-600">--%</strong> (Awaiting Model)
        </div>
      </div>

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
          <h2 class="text-base font-extrabold text-slate-900 m-0 tracking-tight">Hourly Forecast (Next 24 Hours)</h2>
          <p class="text-xs text-slate-400 m-0">Swipe horizontally to track temperature and AQI trends</p>
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

  <!-- ── 5. 7-Day Forecast: Vertical Accordion with Min/Max Range Slider ── -->
  <div class="bg-white rounded-[24px] p-4 sm:p-6 border border-slate-200 shadow-[0_4px_24px_rgba(15,23,42,0.05)]">
    
    <div class="flex items-center justify-between mb-4 px-1">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-[#e6fcf9] text-[#00bfa5] flex items-center justify-center">
          <i data-lucide="calendar" class="w-4 h-4"></i>
        </div>
        <div>
          <h2 class="text-base font-extrabold text-slate-900 m-0 tracking-tight">7-Day Extended Outlook</h2>
          <p class="text-xs text-slate-400 m-0">Tap any day row for detailed wind, rain, and health advisory</p>
        </div>
      </div>
      <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider font-mono">Bantakal Station</span>
    </div>

    <!-- Accordion List Container -->
    <div id="seven-day-accordion" class="flex flex-col gap-2.5">
      <!-- Populated dynamically by JS with smooth accordion cards -->
      <div class="text-xs text-slate-400 py-6 text-center">Loading 7-day outlook...</div>
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
      <span class="inline-block w-2 h-2 rounded-full bg-amber-400"></span>
      <span>Sync Status: <strong class="text-slate-700" id="forecast-sync-time">Model Standby</strong></span>
    </div>
  </div>

</div>

<!-- ── Custom Styles for Forecast Page ── -->
<style>
/* Horizontal Reel Custom Styling */
.hourly-forecast-reel::-webkit-scrollbar {
  display: none;
}
.hourly-card {
  scroll-snap-align: start;
  flex: 0 0 84px;
  min-width: 84px;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 12px 8px;
  border-radius: 18px;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  user-select: none;
}
.hourly-card:hover {
  transform: translateY(-2px);
  border-color: #00bfa5;
  background: #ffffff;
  box-shadow: 0 6px 18px rgba(0, 191, 165, 0.12);
}
.hourly-card.active-hour {
  background: #ffffff;
  border-color: #00bfa5;
  box-shadow: 0 6px 20px rgba(0, 191, 165, 0.18);
}
.hourly-card.active-hour .hour-label {
  color: #00bfa5;
  font-weight: 800;
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
  max-height: 280px;
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

    // CPCB Colors and Categories
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
            text.innerText = 'Hide Parameters';
        } else {
            sec.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
            text.innerText = 'View Model Parameters';
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

    // Generate upcoming 24 hours structure with awaiting-model placeholders
    function generateHourlyData() {
        if (trainedModelData && trainedModelData.hourly) {
            return trainedModelData.hourly;
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
                rain: '--',
                icon: '⏳',
                isNow: i === 0
            });
        }
        return hours;
    }

    // Generate upcoming 7 days structure with awaiting-model placeholders
    function generate7DayData() {
        if (trainedModelData && trainedModelData.sevenDays) {
            return trainedModelData.sevenDays;
        }

        const days = [];
        const now = new Date();

        for (let i = 0; i < 7; i++) {
            const d = new Date(now.getTime() + (i * 86400000));
            const dayName = i === 0 ? 'Today' : (i === 1 ? 'Tomorrow' : d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }));

            days.push({
                index: i,
                dayName: dayName,
                minTemp: '--',
                maxTemp: '--',
                leftPct: '0',
                widthPct: '0',
                condition: 'Awaiting Model',
                icon: '⏳',
                rain: '--',
                aqi: '--',
                gust: '-- km/h',
                uv: '--',
                dominant: '--',
                advice: 'Projections and health recommendations will be generated once your predictive model is trained and linked.'
            });
        }
        return days;
    }

    // Render Function
    function renderForecastUI() {
        const hourly = generateHourlyData();
        const sevenDays = generate7DayData();

        // 1. Date and Sync time
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' });
        const dateEl = document.getElementById('forecast-date-str');
        if (dateEl) dateEl.innerText = dateStr;
        const syncEl = document.getElementById('forecast-sync-time');
        if (syncEl) syncEl.innerText = trainedModelData ? 'Model Active' : 'Model Standby';

        // 2. Hero values
        if (!trainedModelData) {
            const heroTemp = document.getElementById('forecast-hero-temp');
            if (heroTemp) heroTemp.innerText = '--';
            const dayLow = document.getElementById('forecast-day-low');
            if (dayLow) dayLow.innerText = '--°';
            const dayHigh = document.getElementById('forecast-day-high');
            if (dayHigh) dayHigh.innerText = '--°';
            const condText = document.getElementById('forecast-condition-text');
            if (condText) condText.innerText = 'Awaiting Model';
        }

        // 3. Render Hourly Horizontal Reel
        const reel = document.getElementById('hourly-forecast-reel');
        if (reel) {
            reel.innerHTML = hourly.map(item => {
                const b = getAqiBadge(item.aqi);
                return `
                    <div class="hourly-card ${item.isNow ? 'active-hour' : ''}">
                      <div class="hour-label text-[11px] font-bold text-slate-500 mb-1.5 whitespace-nowrap">${item.label}</div>
                      <div class="text-2xl my-1 leading-none text-slate-400 font-sans">${item.icon}</div>
                      <div class="font-mono text-base font-extrabold text-slate-400 leading-tight">${item.temp}°</div>
                      <div class="text-[9.5px] font-medium text-slate-400 mt-1 flex items-center gap-0.5"><span>💧</span>${item.rain === '--' ? '--%' : item.rain + '%'}</div>
                      <div class="mt-2 px-2 py-0.5 rounded-full text-[9px] font-extrabold font-mono" style="background:${b.bg}; color:${b.text}; border:1px solid ${b.border}; white-space:nowrap;">
                        AQI ${item.aqi}
                      </div>
                    </div>
                `;
            }).join('');
        }

        // 4. Render 7-Day Accordion
        const acc = document.getElementById('seven-day-accordion');
        if (acc) {
            acc.innerHTML = sevenDays.map((d, idx) => {
                const b = getAqiBadge(d.aqi);
                const isOpenDefault = idx === 0; // open Today by default
                return `
                    <div class="day-row-container ${isOpenDefault ? 'open' : ''}" id="day-row-${idx}">
                      <!-- Clickable Header Row -->
                      <div class="day-row-header" onclick="toggleDayAccordion(${idx})">
                        <!-- Left: Day & Icon -->
                        <div class="flex items-center gap-3 min-w-[110px] sm:min-w-[130px]">
                          <span class="text-xl sm:text-2xl shrink-0 text-slate-400 font-sans">${d.icon}</span>
                          <div>
                            <div class="text-xs sm:text-[13px] font-bold text-slate-900 leading-tight">${d.dayName}</div>
                            <div class="text-[10px] sm:text-[11px] text-slate-400 font-medium truncate max-w-[90px] sm:max-w-[120px]">${d.condition}</div>
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
                          <span class="hidden sm:inline-flex px-2 py-0.5 rounded-full text-[10px] font-extrabold font-mono" style="background:${b.bg}; color:${b.text}; border:1px solid ${b.border};">
                            AQI ${d.aqi}
                          </span>
                          <i data-lucide="chevron-down" class="day-chevron w-4 h-4 text-slate-400 transition-transform" id="day-chevron-${idx}" style="transform: ${isOpenDefault ? 'rotate(180deg)' : 'rotate(0deg)'};"></i>
                        </div>
                      </div>

                      <!-- Expandable Accordion Drawer -->
                      <div class="day-row-drawer">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mb-3 text-xs">
                          <div class="bg-white p-2.5 rounded-xl border border-slate-200/80">
                            <div class="text-[10px] font-bold text-slate-400 uppercase">Air Quality</div>
                            <div class="font-extrabold mt-0.5 font-mono" style="color: ${b.text};">AQI ${d.aqi} · ${b.label}</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">Dominant: <strong>${d.dominant}</strong></div>
                          </div>
                          <div class="bg-white p-2.5 rounded-xl border border-slate-200/80">
                            <div class="text-[10px] font-bold text-slate-400 uppercase">Rain Probability</div>
                            <div class="font-extrabold text-slate-600 mt-0.5 font-mono">${d.rain === '--' ? '--%' : d.rain + '%'} Chance</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">Awaiting model</div>
                          </div>
                          <div class="bg-white p-2.5 rounded-xl border border-slate-200/80">
                            <div class="text-[10px] font-bold text-slate-400 uppercase">Wind Gusts</div>
                            <div class="font-extrabold text-slate-600 mt-0.5 font-mono">${d.gust}</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">Awaiting model</div>
                          </div>
                          <div class="bg-white p-2.5 rounded-xl border border-slate-200/80">
                            <div class="text-[10px] font-bold text-slate-400 uppercase">UV Radiation</div>
                            <div class="font-extrabold text-slate-600 mt-0.5 font-mono">${d.uv}</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">Awaiting model</div>
                          </div>
                        </div>

                        <!-- Activity Guidance -->
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

    // Refresh Forecast
    function refreshForecast() {
        const icon = document.getElementById('forecast-refresh-icon');
        if (icon) icon.classList.add('animate-spin');
        setTimeout(() => {
            if (icon) icon.classList.remove('animate-spin');
            renderForecastUI();
        }, 300);
    }

    window.refreshForecast = refreshForecast;

    /**
     * Public Hook to inject your trained machine learning model predictions later:
     * window.loadTrainedForecastModel({
     *   hero: { temp: 28.5, dayLow: 23.4, dayHigh: 31.8, condition: 'Partly Cloudy', rain: 20, wind: '12 km/h' },
     *   hourly: [...],
     *   sevenDays: [...]
     * });
     */
    window.loadTrainedForecastModel = function(data) {
        trainedModelData = data;
        renderForecastUI();
    };

    // Initial render
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderForecastUI);
    } else {
        renderForecastUI();
    }
})();
</script>
