<div id="weather-view" style="display: none; animation: fade-in 0.4s ease-out; width: 100%;">
  
  <!-- Offline Banner -->
  <div id="weather-offline-banner" style="display: none; background-color: #fff7ed; border: 1px solid #fed7aa; border-left: 4px solid #f97316; border-radius: 12px; padding: 12px 16px; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
    <div style="display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: #475569;">
      <i data-lucide="alert-triangle" style="width: 16px; height: 16px; color: #f97316; margin-top: 2px; flex-shrink: 0;"></i>
      <span><strong style="color: #c2410c;">Weather Sensor Node Offline:</strong> No new live telemetry received in cloud for >15 mins (Last update: <strong style="color: #f97316;" id="weather-offline-last-update">--</strong>). Showing last recorded cloud values below.</span>
    </div>
    <div id="weather-offline-badge-text" style="background-color: #ffedd5; color: #c2410c; font-size: 10px; font-weight: 800; padding: 4px 10px; border-radius: 999px; letter-spacing: 0.05em; font-family: var(--font-mono); white-space: nowrap;">OFFLINE</div>
  </div>
  <!-- Titles -->
  <div>
    <h1 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0; letter-spacing: -0.02em;">Weather Conditions</h1>
    <div style="font-size: 12.5px; color: #64748b; margin-top: 6px; font-family: var(--font-mono); display: flex; align-items: center; gap: 8px;">
      <span>Last Updated: <span id="weather-latest-time" style="color: #00bfa5; font-weight: 600;">Syncing...</span></span>
      <span id="weather-live-badge" style="display: inline-flex; align-items: center; gap: 5px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; background-color: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;">
        <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10b981;"></span> LIVE
      </span>
    </div>
  </div>

  <!-- Hero Container -->
  <div class="bg-white rounded-[24px] p-5 sm:p-8 border border-slate-200 shadow-[0_4px_20px_rgba(15,23,42,0.05)] mt-6 flex flex-col lg:flex-row gap-6 sm:gap-8 lg:items-center lg:justify-between">
    
    <!-- 1. Left (Hero) -->
    <div class="flex-1 min-w-[280px]">
      <div id="weather-date-str" class="text-[13px] text-slate-500 mb-3 font-semibold">--</div>
      <div class="flex items-center gap-4 mb-3">
        <span id="weather-temp-icon" class="text-5xl sm:text-6xl leading-none">☀️</span>
        <div class="flex items-baseline gap-1">
          <span id="weather-hero-temp" class="font-mono text-[64px] sm:text-[76px] font-extrabold leading-none tracking-tight text-slate-900">--</span>
          <span class="font-mono text-2xl sm:text-[32px] font-bold text-slate-500">°C</span>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <span id="weather-hero-label" class="px-3 py-1.5 rounded-full bg-sky-50 text-sky-600 text-[11px] sm:text-[13px] font-bold">--</span>
        <span id="weather-hero-aqi-label" class="px-3 py-1.5 rounded-full bg-green-50 text-green-700 border border-green-200 text-[11px] sm:text-[13px] font-bold">AQI -- · --</span>
      </div>
    </div>

    <!-- Divider for Mobile -->
    <hr class="border-slate-100 lg:hidden">

    <!-- 2. Right (Temp Range) -->
    <div class="flex-1 min-w-[260px] flex flex-col items-center justify-center lg:border-l lg:border-slate-100 lg:px-6">
      <div class="text-[10px] sm:text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-4">
        Today's Temp Range
      </div>
      <div class="flex items-center justify-between w-full max-w-[260px] text-center">
        <div>
          <div id="weather-low-temp" class="font-mono text-2xl sm:text-3xl font-extrabold text-sky-500">--°</div>
          <div class="text-[10px] sm:text-[11px] font-bold text-slate-400 mt-1">LOW</div>
        </div>
        <div>
          <div id="weather-avg-temp" class="font-mono text-2xl sm:text-3xl font-extrabold text-emerald-500">--°</div>
          <div class="text-[10px] sm:text-[11px] font-bold text-slate-400 mt-1">AVG</div>
        </div>
        <div>
          <div id="weather-high-temp" class="font-mono text-2xl sm:text-3xl font-extrabold text-orange-500">--°</div>
          <div class="text-[10px] sm:text-[11px] font-bold text-slate-400 mt-1">HIGH</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Weather Cards Grid -->
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 mt-6">
    
    <div class="flex flex-col justify-between bg-white border border-slate-200 rounded-[20px] p-4 sm:p-5 shadow-[0_4px_16px_rgba(15,23,42,0.04)] min-h-[130px]">
      <div class="flex justify-between items-start w-full">
        <div class="flex items-center gap-3">
          <div class="w-[38px] h-[38px] rounded-[12px] bg-orange-50 text-orange-600 flex items-center justify-center text-[20px] shrink-0">🌡️</div>
          <div>
            <div class="text-[14px] sm:text-[15px] font-bold text-slate-900 leading-tight tracking-tight">Temperature</div>
            <div class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5 whitespace-nowrap font-medium">Live Telemetry</div>
          </div>
        </div>

      </div>
      <div id="card-temp" class="font-mono text-[22px] sm:text-[26px] font-extrabold text-orange-600 mt-4 leading-none tracking-tight">--</div>
    </div>

    <div class="flex flex-col justify-between bg-white border border-slate-200 rounded-[20px] p-4 sm:p-5 shadow-[0_4px_16px_rgba(15,23,42,0.04)] min-h-[130px]">
      <div class="flex justify-between items-start w-full">
        <div class="flex items-center gap-3">
          <div class="w-[38px] h-[38px] rounded-[12px] bg-sky-50 text-sky-600 flex items-center justify-center text-[20px] shrink-0">💧</div>
          <div>
            <div class="text-[14px] sm:text-[15px] font-bold text-slate-900 leading-tight tracking-tight">Humidity</div>
            <div class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5 whitespace-nowrap font-medium">Live Telemetry</div>
          </div>
        </div>
        <span id="weather-hum-badge" style="padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; border: 1px solid #cbd5e1; background-color: #f1f5f9; color: #475569;">
          ☁️ Cloudy
        </span>
      </div>
      <div id="card-hum" class="font-mono text-[22px] sm:text-[26px] font-extrabold text-sky-600 mt-4 leading-none tracking-tight">--</div>
    </div>

    <div class="flex flex-col justify-between bg-white border border-slate-200 rounded-[20px] p-4 sm:p-5 shadow-[0_4px_16px_rgba(15,23,42,0.04)] min-h-[130px]">
      <div class="flex justify-between items-start w-full">
        <div class="flex items-center gap-3">
          <div class="w-[38px] h-[38px] rounded-[12px] bg-indigo-50 text-indigo-600 flex items-center justify-center text-[20px] shrink-0">💨</div>
          <div>
            <div class="text-[14px] sm:text-[15px] font-bold text-slate-900 leading-tight tracking-tight">Wind Speed</div>
            <div class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5 whitespace-nowrap font-medium">Live Telemetry</div>
          </div>
        </div>

      </div>
      <div id="card-wind" class="font-mono text-[22px] sm:text-[26px] font-extrabold text-indigo-600 mt-4 leading-none tracking-tight">--</div>
    </div>

    <div class="flex flex-col justify-between bg-white border border-slate-200 rounded-[20px] p-4 sm:p-5 shadow-[0_4px_16px_rgba(15,23,42,0.04)] min-h-[130px]">
      <div class="flex justify-between items-start w-full">
        <div class="flex items-center gap-3">
          <div class="w-[38px] h-[38px] rounded-[12px] bg-purple-50 text-purple-600 flex items-center justify-center text-[20px] shrink-0">🌪️</div>
          <div>
            <div class="text-[14px] sm:text-[15px] font-bold text-slate-900 leading-tight tracking-tight">Wind Gust</div>
            <div class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5 whitespace-nowrap font-medium">Live Telemetry</div>
          </div>
        </div>

      </div>
      <div id="card-gust" class="font-mono text-[22px] sm:text-[26px] font-extrabold text-purple-600 mt-4 leading-none tracking-tight">--</div>
    </div>

    <div class="flex flex-col justify-between bg-white border border-slate-200 rounded-[20px] p-4 sm:p-5 shadow-[0_4px_16px_rgba(15,23,42,0.04)] min-h-[130px]">
      <div class="flex justify-between items-start w-full">
        <div class="flex items-center gap-3">
          <div class="w-[38px] h-[38px] rounded-[12px] bg-cyan-50 text-cyan-600 flex items-center justify-center text-[20px] shrink-0">🌧️</div>
          <div>
            <div class="text-[14px] sm:text-[15px] font-bold text-slate-900 leading-tight tracking-tight">Rainfall</div>
            <div class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5 whitespace-nowrap font-medium">Live Telemetry</div>
          </div>
        </div>
        <span id="weather-today-rain-badge" style="padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; border: 1px solid #c7d2fe; background-color: #eef2ff; color: #4338ca;">
          Today: <strong id="card-today-rain">0.0 mm</strong>
        </span>
      </div>
      <div id="card-rain" class="font-mono text-[22px] sm:text-[26px] font-extrabold text-cyan-600 mt-4 leading-none tracking-tight">--</div>
    </div>

    <div class="flex flex-col justify-between bg-white border border-slate-200 rounded-[20px] p-4 sm:p-5 shadow-[0_4px_16px_rgba(15,23,42,0.04)] min-h-[130px]">
      <div class="flex justify-between items-start w-full">
        <div class="flex items-center gap-3">
          <div class="w-[38px] h-[38px] rounded-[12px] bg-amber-50 text-amber-600 flex items-center justify-center text-[20px] shrink-0">🧭</div>
          <div>
            <div class="text-[14px] sm:text-[15px] font-bold text-slate-900 leading-tight tracking-tight">Wind Direction</div>
            <div class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5 whitespace-nowrap font-medium">Live Telemetry</div>
          </div>
        </div>

      </div>
      <div id="card-dir" class="font-sans text-[22px] sm:text-[26px] font-extrabold text-slate-600 mt-4 leading-none tracking-tight">--</div>
    </div>

  </div>

  <!-- Telemetry Stream Table -->
  <div style="margin-top: 24px;">
    <div style="margin-bottom: 12px;">
      <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; font-family: var(--font-sans);">
        <i data-lucide="table" style="width: 18px; height: 18px; color: #00bfa5;"></i>
        <span>Live Temperature &amp; Weather Telemetry Stream</span>
      </h2>
    </div>

    <div style="background-color: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);">
      <!-- Mobile swipe indicator -->
      <div class="block sm:hidden px-4 py-2 bg-slate-50 border-b border-slate-100 text-[11px] text-slate-500 font-medium flex items-center justify-between">
        <span>👈 Swipe horizontally to view all weather columns</span>
        <span class="font-mono text-[10px] bg-sky-100 text-sky-700 px-1.5 py-0.5 rounded font-bold">STREAM</span>
      </div>
      <div style="overflow-x: auto; max-height: 420px; -webkit-overflow-scrolling: touch;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; font-family: var(--font-sans); min-width: 800px;">
          <thead style="position: sticky; top: 0; z-index: 1; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
            <tr>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Time / Timestamp</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Temperature (°C)</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Humidity (%)</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Wind Speed (km/h)</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Rain Gauge (mm)</th>
            </tr>
          </thead>
          <tbody id="weather-tbody">
            <tr><td colspan="5" style="padding: 40px; text-align: center; color: #64748b;">Loading telemetry...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
