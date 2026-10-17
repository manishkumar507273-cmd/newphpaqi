<div class="p-3.5 sm:p-6 flex flex-col gap-5 sm:gap-6 max-w-[1200px] mx-auto text-slate-900 font-sans">
  
  <!-- Header -->
  <div class="flex justify-between items-end mb-2 sm:mb-4">
    <div>
      <h1 class="text-2xl sm:text-[28px] font-extrabold text-slate-900 m-0 tracking-tight">Analytics Archive</h1>
      <p class="m-0 text-slate-500 text-xs sm:text-sm font-medium mt-1">Access and analyze historical telemetry logs.</p>
    </div>
  </div>

  <!-- Sub-Navigation Selector: AQI Historical vs Weather Historical -->
  <div class="flex items-center justify-center mb-1">
    <div class="w-full sm:w-auto grid grid-cols-2 sm:flex items-center bg-white p-1 sm:p-1.5 rounded-2xl sm:rounded-full border border-slate-300 shadow-sm gap-1 sm:gap-1.5">
      <button id="tab-aqi" onclick="switchHistTab('aqi')" class="flex items-center justify-center gap-2 py-2.5 sm:py-2 px-3 sm:px-6 rounded-xl sm:rounded-full font-bold text-xs sm:text-sm cursor-pointer transition-all bg-[#00bfa5] text-white shadow-sm min-h-[42px]">
        <i data-lucide="activity" class="w-4 h-4 shrink-0"></i>
        <span>AQI <span class="hidden min-[400px]:inline">Historical</span></span>
      </button>
      <button id="tab-weather" onclick="switchHistTab('weather')" class="flex items-center justify-center gap-2 py-2.5 sm:py-2 px-3 sm:px-6 rounded-xl sm:rounded-full font-bold text-xs sm:text-sm cursor-pointer transition-all bg-transparent text-slate-500 hover:text-slate-800 min-h-[42px]">
        <i data-lucide="cloud-sun" class="w-4 h-4 shrink-0"></i>
        <span>Weather <span class="hidden min-[400px]:inline">Historical</span></span>
      </button>
    </div>
  </div>

  <!-- UNIFIED DATE SELECTOR & 24-HOUR SUMMARY CARD -->
  <div class="bg-white rounded-[20px] sm:rounded-[24px] p-4 sm:p-6 border-[1.5px] border-[#00bfa5] shadow-[0_4px_20px_rgba(0,191,165,0.08)]">
    
    <!-- Top Header Row: Date Selector & Controls -->
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
      
      <!-- Left Title & Status -->
      <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 40px; height: 40px; border-radius: 12px; background-color: #ecfdf5; border: 1px solid #a7f3d0; display: flex; align-items: center; justify-content: center; color: #00bfa5; flex-shrink: 0;">
          <i data-lucide="calendar" style="width: 20px; height: 20px;"></i>
        </div>
        <div>
          <div style="font-size: 14px; sm:font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
            <span>Select Date (24-Hour Telemetry)</span>
          </div>
          <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
            <span id="date-subtitle-desktop">Hourly records: 12:00 AM – 12:00 AM</span>
          </div>
        </div>
      </div>

      <!-- Right Action Controls: Native Calendar Input -->
      <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <div style="position: relative; display: inline-flex; align-items: center;">
          <input type="date" id="history-date-picker" class="min-h-[42px]" style="padding: 8px 14px 8px 34px; border-radius: 12px; border: 1.5px solid #cbd5e1; font-size: 13px; font-weight: 600; color: #0f172a; background-color: #f8fafc; cursor: pointer; font-family: var(--font-sans); outline: none;">
          <i data-lucide="calendar" style="position: absolute; left: 11px; width: 15px; height: 15px; color: #00bfa5; pointer-events: none;"></i>
        </div>
      </div>
    </div>

    <!-- Bottom Integrated Section: 24-Hour Dynamic Metrics -->
    <div id="summary-section" style="margin-top: 18px; padding-top: 16px; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
      
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
      <div id="summary-aqi-block" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
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

      <div id="summary-weather-block" style="display: none; align-items: center; gap: 8px; flex-wrap: wrap;">
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
          <button onclick="setChartType('line')" id="btn-type-line" style="display: flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 999px; border: none; font-size: 11.5px; font-weight: 600; cursor: pointer; background-color: #ffffff; color: #0f172a; box-shadow: 0 1px 4px rgba(0,0,0,0.06); transition: all 0.15s ease; min-height: 36px;">
            <i data-lucide="trending-up" style="width: 13px; height: 13px; color: #00bfa5;" id="icon-type-line"></i>
            <span>Curve</span>
          </button>
          <button onclick="setChartType('bar')" id="btn-type-bar" style="display: flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 999px; border: none; font-size: 11.5px; font-weight: 600; cursor: pointer; background-color: transparent; color: #64748b; box-shadow: none; transition: all 0.15s ease; min-height: 36px;">
            <i data-lucide="bar-chart-2" style="width: 13px; height: 13px; color: #64748b;" id="icon-type-bar"></i>
            <span>Bars</span>
          </button>
        </div>

        <!-- Parameter Filter Chips (AQI) - horizontally scrollable on mobile -->
        <div id="chips-aqi" class="flex gap-1.5 overflow-x-auto no-scrollbar py-1 max-w-full flex-nowrap bg-slate-50 p-1 rounded-full border border-slate-200">
          <button onclick="setParam('cpcb_aqi')" id="btn-cpcb_aqi" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer bg-[#00bfa5] text-white transition-all">AQI</button>
          <button onclick="setParam('temperature')" id="btn-temperature_aqi" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer bg-transparent text-slate-500 hover:text-slate-800 transition-all">Temp</button>
          <button onclick="setParam('humidity')" id="btn-humidity_aqi" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer bg-transparent text-slate-500 hover:text-slate-800 transition-all">Hum.</button>
          <button onclick="setParam('pm25')" id="btn-pm25" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer bg-transparent text-slate-500 hover:text-slate-800 transition-all">PM2.5</button>
          <button onclick="setParam('pm10')" id="btn-pm10" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer bg-transparent text-slate-500 hover:text-slate-800 transition-all">PM10</button>
          <button onclick="setParam('co')" id="btn-co" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer bg-transparent text-slate-500 hover:text-slate-800 transition-all">CO</button>
          <button onclick="setParam('no2')" id="btn-no2" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer bg-transparent text-slate-500 hover:text-slate-800 transition-all">NO₂</button>
          <button onclick="setParam('o3')" id="btn-o3" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer bg-transparent text-slate-500 hover:text-slate-800 transition-all">O₃</button>
        </div>

        <!-- Parameter Filter Chips (Weather) - horizontally scrollable on mobile -->
        <div id="chips-weather" class="hidden gap-1.5 overflow-x-auto no-scrollbar py-1 max-w-full flex-nowrap bg-slate-50 p-1 rounded-full border border-slate-200">
          <button onclick="setParam('temperature')" id="btn-temperature" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer bg-[#00bfa5] text-white transition-all">Temp</button>
          <button onclick="setParam('humidity')" id="btn-humidity" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer bg-transparent text-slate-500 hover:text-slate-800 transition-all">Hum.</button>
          <button onclick="setParam('wind_speed')" id="btn-wind_speed" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer bg-transparent text-slate-500 hover:text-slate-800 transition-all">Wind Spd</button>
          <button onclick="setParam('wind_gust')" id="btn-wind_gust" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer bg-transparent text-slate-500 hover:text-slate-800 transition-all">Wind Gust</button>
          <button onclick="setParam('rain_gauge')" id="btn-rain_gauge" class="shrink-0 min-h-[36px] px-3.5 py-1.5 rounded-full border-none font-semibold text-xs cursor-pointer bg-transparent text-slate-500 hover:text-slate-800 transition-all">Rain</button>
        </div>
      </div>
    </div>

    <!-- Chart Canvas Container -->
    <div style="width: 100%; height: 300px; position: relative;">
      <canvas id="historical-chart"></canvas>
      <div id="chart-empty" style="display: none; position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; background: rgba(255,255,255,0.8); gap: 8px; color: #64748b;">
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
      <span class="font-mono text-[10px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded font-bold">24 HRS</span>
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
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <label style="font-size: 13px; font-weight: 700; color: #334155;">Intended Use Case *</label>
            <span id="use-case-counter" style="font-size: 11.5px; font-weight: 600; color: #94a3b8; font-family: var(--font-mono); transition: color 0.2s;">
              0 / 50 letters min
            </span>
          </div>
          <textarea name="use_case" id="use-case-textarea" required minlength="50" oninput="handleUseCaseInput(this)" placeholder="Describe your operational or analytical requirements for this telemetry data (minimum 50 letters / words)..." style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; font-size: 14px; font-weight: 500; color: #0f172a; outline: none; transition: all 0.2s; background-color: #f8fafc; resize: none; min-height: 85px; font-family: var(--font-sans);" onfocus="this.style.borderColor='#00bfa5'; this.style.backgroundColor='#ffffff'; this.style.boxShadow='0 0 0 3px rgba(0, 191, 165, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.backgroundColor='#f8fafc'; this.style.boxShadow='none';"></textarea>
          <div style="font-size: 11.5px; color: #64748b; margin-top: 5px;">
            Minimum 50 letters / characters required to describe intended analytical purpose.
          </div>
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
var currentSubTab = 'aqi';
var currentParam = 'cpcb_aqi';
var currentChartType = 'line';
var historicalDataAqi = [];
var historicalDataWeather = [];
var chartInstance = null;
var filteredDayData = [];

var PARAM_COLORS = {
    cpcb_aqi: '#00bfa5', pm25: '#0284c7', pm10: '#6366f1',
    co: '#16a34a', no2: '#9333ea', o3: '#d97706',
    temperature: '#00bfa5', humidity: '#00bfa5',
    wind_speed: '#4f46e5', wind_gust: '#8b5cf6',
    wind_direction: '#0284c7', rain_gauge: '#0891b2'
};

var getAqiCategory = function(val) {
    const v = Number(val);
    if (v <= 50) return { label: 'Good', color: '#16a34a', bg: '#f0fdf4', border: '#86efac', text: '#15803d', badgeBg: '#16a34a', badgeText: '#ffffff' };
    if (v <= 100) return { label: 'Satisfactory', color: '#65a30d', bg: '#f7fee7', border: '#bef264', text: '#3f6212', badgeBg: '#65a30d', badgeText: '#ffffff' };
    if (v <= 200) return { label: 'Moderate', color: '#d97706', bg: '#fffbeb', border: '#fde68a', text: '#92400e', badgeBg: '#d97706', badgeText: '#ffffff' };
    if (v <= 300) return { label: 'Poor', color: '#ea580c', bg: '#fff7ed', border: '#fed7aa', text: '#9a3412', badgeBg: '#ea580c', badgeText: '#ffffff' };
    if (v <= 400) return { label: 'Very Poor', color: '#dc2626', bg: '#fef2f2', border: '#fca5a5', text: '#991b1b', badgeBg: '#dc2626', badgeText: '#ffffff' };
    return { label: 'Severe', color: '#9333ea', bg: '#faf5ff', border: '#d8b4fe', text: '#6b21a8', badgeBg: '#9333ea', badgeText: '#ffffff' };
};

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

async function fetchAllHistory(forcedDate = null) {
    try {
        const datePicker = document.getElementById('history-date-picker');
        let queryDate = forcedDate || datePicker.value;
        
        // Default date if none selected (use local date YYYY-MM-DD)
        if (!queryDate) {
            queryDate = getLocalDateStr();
            datePicker.value = queryDate;
        }

        let data;
        if (window.INITIAL_DATA && Array.isArray(window.INITIAL_DATA.aqi) && !forcedDate) {
            data = window.INITIAL_DATA;
            window.INITIAL_DATA = null;
        } else {
            const response = await fetch(`api/telemetry.php?action=history_bulk&date=${queryDate}`);
            data = await response.json();
        }
        
        historicalDataAqi = data.aqi || [];
        historicalDataWeather = data.weather || [];
        
        renderUI();
    } catch(e) {
        console.error("Error fetching historical data", e);
    }
}

function switchHistTab(tab) {
    currentSubTab = tab;
    currentParam = tab === 'aqi' ? 'cpcb_aqi' : 'temperature';
    
    // Switch styles
    const tabAqi = document.getElementById('tab-aqi');
    const tabWeather = document.getElementById('tab-weather');
    if(tab === 'aqi') {
        tabAqi.style.backgroundColor = '#00bfa5';
        tabAqi.style.color = '#ffffff';
        tabWeather.style.backgroundColor = 'transparent';
        tabWeather.style.color = '#64748b';
        
        document.getElementById('chips-aqi').style.display = 'flex';
        document.getElementById('chips-weather').style.display = 'none';
        
        document.getElementById('summary-aqi-block').style.display = 'flex';
        document.getElementById('summary-weather-block').style.display = 'none';
        
        document.getElementById('chart-main-title').innerText = 'Air Quality Hourly Trend Analysis (12:00 AM – 12:00 AM)';
        document.getElementById('table-title').innerText = 'AQI Historical Telemetry Table (12:00 AM – 12:00 AM)';
    } else {
        tabWeather.style.backgroundColor = '#00bfa5';
        tabWeather.style.color = '#ffffff';
        tabAqi.style.backgroundColor = 'transparent';
        tabAqi.style.color = '#64748b';
        
        document.getElementById('chips-aqi').style.display = 'none';
        document.getElementById('chips-weather').style.display = 'flex';
        
        document.getElementById('summary-aqi-block').style.display = 'none';
        document.getElementById('summary-weather-block').style.display = 'flex';
        
        document.getElementById('chart-main-title').innerText = 'Weather Hourly Trend Analysis (8:00 AM – 8:00 AM)';
        document.getElementById('table-title').innerText = 'Weather Historical Telemetry Table (8:00 AM – 8:00 AM)';
    }
    
    setParam(currentParam);
}

function setChartType(type) {
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
    renderUI();
}

function setParam(param) {
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
    
    // Also handle duplicate chips for temp/hum in AQI tab
    ['temperature_aqi', 'humidity_aqi'].forEach(b => {
        const el = document.getElementById(`btn-${b}`);
        const realParam = b.split('_')[0];
        if(el) {
            if(realParam === param) {
                el.style.background = PARAM_COLORS[realParam];
                el.style.color = '#ffffff';
                document.getElementById('chart-title-icon').style.color = PARAM_COLORS[realParam];
            } else {
                el.style.background = 'transparent';
                el.style.color = '#64748b';
            }
        }
    });
    renderUI();
}

function renderUI() {
    const dateInput = document.getElementById('history-date-picker').value;
    if (!dateInput) return;
    
    const sourceData = currentSubTab === 'aqi' ? historicalDataAqi : historicalDataWeather;
    
    // Parse ISO string into local timestamp
    const parseIsoLocal = (str) => {
        if (!str) return 0;
        const parts = str.split('T');
        if (parts.length !== 2) return new Date(str).getTime();
        const [yr, mo, da] = parts[0].split('-').map(Number);
        const [hr, mn, sc] = parts[1].split(':').map(Number);
        return new Date(yr, mo - 1, da, hr, mn || 0, sc || 0).getTime();
    };

    // AQI uses 12:00 AM to 12:00 AM (calendar day midnight to midnight), Weather uses 8:00 AM to 8:00 AM
    const [y, m, d] = dateInput.split('-').map(Number);
    const startHour = currentSubTab === 'aqi' ? 0 : 8;
    const startWindow = new Date(y, m - 1, d, startHour, 0, 0, 0).getTime();
    const endWindow = startWindow + (24 * 60 * 60 * 1000);
    const nextDateObj = new Date(endWindow);
    const startDateObj = new Date(y, m - 1, d);
    
    // Filter data strictly within [startWindow, endWindow)
    filteredDayData = sourceData.filter(item => {
        if (!item.timestamp) return false;
        const t = parseIsoLocal(item.timestamp);
        return t >= startWindow && t < endWindow;
    });

    // Sort ascending by time and deduplicate any points sharing the same hour
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
    
    const dateStrLong = startDateObj.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
    const nextDateStrLong = nextDateObj.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
    const nextDateStrShort = nextDateObj.toLocaleDateString('en-US', { day: 'numeric', month: 'short' });
    
    const rangeLabel = currentSubTab === 'aqi'
        ? `${dateStrLong} (12:00 AM – 12:00 AM)`
        : `${dateStrLong} 8:00 AM – ${nextDateStrLong} 8:00 AM`;
    
    document.getElementById('date-subtitle-desktop').innerText = `Showing ${filteredDayData.length} hourly data points (${rangeLabel})`;
    document.getElementById('summary-date-display').innerText = currentSubTab === 'aqi' ? dateStrLong : `${dateStrLong} → ${nextDateStrShort}`;
    document.getElementById('log-count').innerText = filteredDayData.length;
    
    document.getElementById('chart-sub-title').innerText = `24-Hour progression: ${rangeLabel}`;
    document.getElementById('table-subtitle').innerText = `24 Hourly Telemetry Rows (${rangeLabel}) — ${filteredDayData.length} Logged Readings`;
    
    if (filteredDayData.length === 0) {
        document.getElementById('chart-empty').style.display = 'flex';
        document.getElementById('chart-empty-text').innerText = `No records found for ${rangeLabel}.`;
        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }
        document.getElementById('avg-aqi-val').innerText = '--';
        document.getElementById('avg-aqi-label').style.display = 'none';
        document.getElementById('min-max-aqi').style.display = 'none';
        document.getElementById('mini-chips-container').style.display = 'none';
        
        document.getElementById('w-avg-temp').innerText = '--';
        document.getElementById('w-avg-hum').innerText = '--';
        document.getElementById('w-avg-wind').innerText = '--';
        document.getElementById('w-max-gust').innerText = '--';
        document.getElementById('w-mode-dir').innerText = '--';
        document.getElementById('w-total-rain').innerText = '--';
        
        document.getElementById('table-body').innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 36px 20px; color: #64748b;">No telemetry records available for ${rangeLabel}.</td></tr>`;
        return;
    }

    document.getElementById('chart-empty').style.display = 'none';
    
    // Summary calculations — track valid (non-null) counts per field
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

    if (currentSubTab === 'aqi') {
        const avgAqi = countAqi > 0 ? Math.round(sumAqi / countAqi) : 0;
        const aqiCat = getAqiCategory(avgAqi);
        document.getElementById('avg-aqi-val').innerText = avgAqi;
        document.getElementById('avg-aqi-val').style.color = aqiCat.text;

        const label = document.getElementById('avg-aqi-label');
        label.innerText = aqiCat.label;
        label.style.display = 'inline-block';
        label.style.backgroundColor = aqiCat.badgeBg;
        label.style.color = aqiCat.badgeText;

        document.getElementById('min-aqi-val').innerText = countAqi > 0 ? minAqi : '--';
        document.getElementById('max-aqi-val').innerText = countAqi > 0 ? maxAqi : '--';
        document.getElementById('min-max-aqi').style.display = 'flex';

        const badge = document.getElementById('avg-aqi-badge-container');
        badge.style.backgroundColor = aqiCat.bg;
        badge.style.borderColor = aqiCat.border;
        document.getElementById('avg-aqi-dot').style.backgroundColor = aqiCat.color;
        document.getElementById('avg-aqi-title').style.color = aqiCat.text;

        document.getElementById('mini-chips-container').style.display = 'flex';
        document.getElementById('avg-pm25').innerText = countPm25 > 0 ? (sumPm25/countPm25).toFixed(3) : '0.000';
        document.getElementById('avg-pm10').innerText = countPm10 > 0 ? (sumPm10/countPm10).toFixed(3) : '0.000';
        document.getElementById('avg-co').innerText   = countCo   > 0 ? (sumCo/countCo).toFixed(3)     : '0.000';
        document.getElementById('avg-no2').innerText  = countNo2  > 0 ? (sumNo2/countNo2).toFixed(3)   : '0.000';
        document.getElementById('avg-o3').innerText   = countO3   > 0 ? (sumO3/countO3).toFixed(3)     : '0.000';
    } else {
        document.getElementById('w-avg-temp').innerText = countTemp > 0 ? (sumTemp/countTemp).toFixed(1) + '°C' : '--';
        document.getElementById('w-avg-hum').innerText = countHum > 0 ? (sumHum/countHum).toFixed(1) + '%' : '--';
        document.getElementById('w-avg-wind').innerText = countWind > 0 ? (sumWind/countWind).toFixed(1) + ' km/h' : '--';
        document.getElementById('w-max-gust').innerText = maxGust > -1 ? maxGust.toFixed(1) + ' km/h' : '--';
        document.getElementById('w-total-rain').innerText = sumRain.toFixed(1) + ' mm';
        
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
        document.getElementById('w-mode-dir').innerText = modeDir;
    }

    // Chart Render
    const ctx = document.getElementById('historical-chart').getContext('2d');

    const labels = filteredDayData.map(d => {
        const parts = (d.timestamp || '').split('T');
        if (parts.length === 2) {
            const [yr, mo, da] = parts[0].split('-').map(Number);
            const [hr, mn] = parts[1].split(':').map(Number);
            const dt = new Date(yr, mo - 1, da, hr, mn || 0);
            return dt.toLocaleTimeString('en-US', {hour: '2-digit', hour12: true});
        }
        const dt = new Date(d.timestamp);
        return dt.toLocaleTimeString('en-US', {hour: '2-digit', hour12: true});
    });
    // Preserve valid 0 values so Supabase zeros are displayed
    const dataPoints = filteredDayData.map(d => (d[currentParam] != null ? d[currentParam] : null));
    const color = PARAM_COLORS[currentParam];

    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, color + '66');
    gradient.addColorStop(1, color + '00');

    // In-place silent update without destroying or flickering
    if (chartInstance && chartInstance.config && chartInstance.config.type === currentChartType) {
        if (chartInstance.options && chartInstance.options.scales && chartInstance.options.scales.x) {
            chartInstance.options.scales.x.display = false;
        }
        chartInstance.data.labels = labels;
        if (chartInstance.data.datasets && chartInstance.data.datasets[0]) {
            chartInstance.data.datasets[0].label = currentParam.toUpperCase();
            chartInstance.data.datasets[0].data = dataPoints;
            chartInstance.data.datasets[0].borderColor = color;
            chartInstance.data.datasets[0].backgroundColor = currentChartType === 'line' ? gradient : color;
            chartInstance.data.datasets[0].borderWidth = currentChartType === 'line' ? 2.5 : 0;
            chartInstance.data.datasets[0].borderRadius = currentChartType === 'bar' ? 4 : 0;
            chartInstance.data.datasets[0].pointBackgroundColor = color;
        }
        chartInstance.update('none');
    } else {
        if (chartInstance) { chartInstance.destroy(); }
        chartInstance = new Chart(ctx, {
            type: currentChartType,
            data: {
                labels: labels,
                datasets: [{
                    label: currentParam.toUpperCase(),
                    data: dataPoints,
                    borderColor: color,
                    backgroundColor: currentChartType === 'line' ? gradient : color,
                    borderWidth: currentChartType === 'line' ? 2.5 : 0,
                    borderRadius: currentChartType === 'bar' ? 4 : 0,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: color,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    spanGaps: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#94a3b8',
                        bodyColor: color,
                        bodyFont: { size: 14, weight: 'bold', family: 'monospace' },
                        padding: 10,
                        cornerRadius: 12,
                        displayColors: false,
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
                timeStr = dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'});
            } else {
                const dt = new Date(d.timestamp);
                dateStr = dt.toLocaleDateString('en-GB');
                timeStr = dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'});
            }
            return `<tr style="border-bottom: 1px solid #f1f5f9; background-color: ${i % 2 === 0 ? '#ffffff' : '#f8fafc'};">
                <td style="padding: 10px 16px; color: #0f172a; font-weight: 600;">${dateStr}</td>
                <td style="padding: 10px 16px; color: #00bfa5; font-family: var(--font-mono); font-weight: 700;">${timeStr}</td>
                <td style="padding: 10px 16px; font-weight: 800; color: ${getAqiCategory(d.cpcb_aqi).text};">${d.cpcb_aqi != null ? Math.round(d.cpcb_aqi) : '--'}</td>
                <td style="padding: 10px 16px; color: #475569;">${d.pm25 != null ? Number(d.pm25).toFixed(3) : '0.000'}</td>
                <td style="padding: 10px 16px; color: #475569;">${d.pm10 != null ? Number(d.pm10).toFixed(3) : '0.000'}</td>
                <td style="padding: 10px 16px; color: #475569;">${d.co != null ? Number(d.co).toFixed(3) : '0.000'}</td>
                <td style="padding: 10px 16px; color: #475569;">${d.no2 != null ? Number(d.no2).toFixed(3) : '0.000'}</td>
                <td style="padding: 10px 16px; color: #475569;">${d.o3 != null ? Number(d.o3).toFixed(3) : '0.000'}</td>
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
                timeStr = dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'});
            } else {
                const dt = new Date(d.timestamp);
                dateStr = dt.toLocaleDateString('en-GB');
                timeStr = dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'});
            }
            return `<tr style="border-bottom: 1px solid #f1f5f9; background-color: ${i % 2 === 0 ? '#ffffff' : '#f8fafc'};">
                <td style="padding: 10px 16px; color: #0f172a; font-weight: 600;">${dateStr}</td>
                <td style="padding: 10px 16px; color: #00bfa5; font-family: var(--font-mono); font-weight: 700;">${timeStr}</td>
                <td style="padding: 10px 16px; color: #0f172a; font-weight: 600;">${d.temperature != null ? Number(d.temperature).toFixed(1) : '--'}</td>
                <td style="padding: 10px 16px; color: #475569;">${d.humidity != null ? Number(d.humidity).toFixed(1) : '--'}</td>
                <td style="padding: 10px 16px; color: #475569;">${d.wind_speed != null ? Number(d.wind_speed).toFixed(3) : '0.000'}</td>
                <td style="padding: 10px 16px; color: #475569;">${d.wind_gust != null ? Number(d.wind_gust).toFixed(3) : '0.000'}</td>
                <td style="padding: 10px 16px; color: #475569;">${getCompassDir(d.wind_direction)}</td>
                <td style="padding: 10px 16px; color: #475569;">${d.rain_gauge != null ? Number(d.rain_gauge).toFixed(3) : '0.000'}</td>
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


const INDUSTRY_PROOFS = {
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
        fileLabel.textContent = INDUSTRY_PROOFS[val] || 'Upload Official ID Card / Proof Document *';
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
    const val = el.value || '';
    const charCount = val.trim().length;
    const words = val.trim() ? val.trim().split(/\s+/).filter(Boolean).length : 0;
    const counter = document.getElementById('use-case-counter');
    if (!counter) return;

    if (charCount >= 50) {
        counter.innerHTML = `<span style="color: #16a34a; font-weight: 700;">✓ ${charCount}/50 letters (${words} words)</span>`;
        el.style.borderColor = '#00bfa5';
    } else {
        const remaining = 50 - charCount;
        counter.innerHTML = `<span style="color: #ef4444; font-weight: 600;">${charCount}/50 letters (${remaining} more needed)</span>`;
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

function readFileAsBase64(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = error => reject(error);
        reader.readAsDataURL(file);
    });
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

    // MANDATORY MINIMUM 50 LETTERS / WORDS ENFORCEMENT FOR USE CASE:
    const formDataObj = new FormData(e.target);
    const useCaseVal = (formDataObj.get('use_case') || '').trim();
    if (useCaseVal.length < 50) {
        alert(`Intended Use Case requires a minimum of 50 letters / characters (currently ${useCaseVal.length}). Please provide more details on your operational or research requirements.`);
        const ta = document.getElementById('use-case-textarea');
        if (ta) {
            ta.focus();
            ta.style.borderColor = '#ef4444';
            handleUseCaseInput(ta);
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
            
            fetch(`${fbDbUrl}/requests.json`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(reqData)
            }).catch(e => console.warn("Firebase sync non-blocking error:", e));
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
    a.download = `${currentSubTab}_history_${document.getElementById('history-date-picker').value}.csv`;
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
        if (dp.value === todayStr) {
            fetchAllHistory(todayStr);
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
        dp.onchange = () => fetchAllHistory();
    }
    fetchAllHistory();
    startAutoRefreshHist();
}, 0);
</script>
