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
            <span id="date-subtitle-desktop">Hourly records: 12:00 AM – 11:00 PM</span>
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
          <span id="chart-main-title">Air Quality Hourly Trend Analysis (12 AM – 11 PM)</span>
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
          <span id="table-title">AQI Historical Telemetry Table (12 AM – 11 PM)</span>
        </h2>
        <p style="font-size: 12px; color: #64748b; margin-top: 2px; margin-bottom: 0;">
          <span id="table-subtitle">24 Hourly Telemetry Rows for Date</span>
        </p>
      </div>
    </div>

    <!-- Bulk Dataset Download Bar -->
    <div class="px-4 py-3 sm:px-6 bg-slate-50 border-b border-slate-200 flex items-center justify-between flex-wrap gap-3">
      <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 700; color: #1e293b;">
          <i data-lucide="file-spreadsheet" style="width: 17px; height: 17px; color: #00bfa5;"></i>
          <span>Bulk Dataset Download:</span>
        </div>
        <button onclick="openDatasetModal()" class="min-h-[38px]" style="display: inline-flex; align-items: center; gap: 7px; padding: 6px 18px; border-radius: 999px; border: none; background-color: #00bfa5; color: #ffffff; font-size: 12.5px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 10px rgba(0, 191, 165, 0.28); transition: all 0.15s ease; white-space: nowrap;">
          <i data-lucide="download" style="width: 14px; height: 14px;"></i>
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
<div id="dataset-modal" style="display: none; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center; padding: 12px sm:padding: 24px; background-color: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px); opacity: 0; transition: opacity 0.3s ease;">
  <div id="dataset-backdrop" onclick="closeDatasetModal()" style="position: absolute; inset: 0;"></div>
  
  <div id="dataset-modal-content" class="w-full max-w-[520px] max-h-[92vh] bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden m-2 sm:m-4" style="position: relative; z-index: 10; transform: scale(0.95); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);">
    
    <!-- Dark Header -->
    <div style="background-color: #1e293b; padding: 20px 24px; position: relative; flex-shrink: 0;">
      <button onclick="closeDatasetModal()" style="position: absolute; top: 16px; right: 16px; width: 32px; height: 32px; border-radius: 8px; border: none; background: rgba(255, 255, 255, 0.1); color: #94a3b8; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease;" onmouseenter="this.style.background='rgba(255, 255, 255, 0.2)'; this.style.color='#ffffff';" onmouseleave="this.style.background='rgba(255, 255, 255, 0.1)'; this.style.color='#94a3b8';">
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
            AQI Telemetry — Data provided is strictly limited to 1 day (yesterday).
          </div>
        </div>

        <!-- Full Name -->
        <div>
          <label style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 8px;">Full Name *</label>
          <div style="position: relative;">
            <i data-lucide="user" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 18px; height: 18px;"></i>
            <input type="text" name="fullName" required placeholder="" value="<?= isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '' ?>" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px 12px 42px; font-size: 14px; font-weight: 500; color: #0f172a; outline: none; transition: all 0.2s; background-color: #f8fafc;" onfocus="this.style.borderColor='#00bfa5'; this.style.backgroundColor='#ffffff'; this.style.boxShadow='0 0 0 3px rgba(0, 191, 165, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.backgroundColor='#f8fafc'; this.style.boxShadow='none';">
          </div>
        </div>

        <!-- Corporate Email -->
        <div>
          <label style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 8px;">Corporate Email *</label>
          <div style="position: relative;">
            <i data-lucide="mail" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 18px; height: 18px;"></i>
            <input type="email" name="email" required placeholder="" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px 12px 42px; font-size: 14px; font-weight: 500; color: #0f172a; outline: none; transition: all 0.2s; background-color: #f8fafc;" onfocus="this.style.borderColor='#00bfa5'; this.style.backgroundColor='#ffffff'; this.style.boxShadow='0 0 0 3px rgba(0, 191, 165, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.backgroundColor='#f8fafc'; this.style.boxShadow='none';">
          </div>
        </div>

        <!-- Organization -->
        <div>
          <label style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 8px;">Organization / Institution *</label>
          <div style="position: relative;">
            <i data-lucide="building" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 18px; height: 18px;"></i>
            <input type="text" name="org" required placeholder="" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px 12px 42px; font-size: 14px; font-weight: 500; color: #0f172a; outline: none; transition: all 0.2s; background-color: #f8fafc;" onfocus="this.style.borderColor='#00bfa5'; this.style.backgroundColor='#ffffff'; this.style.boxShadow='0 0 0 3px rgba(0, 191, 165, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.backgroundColor='#f8fafc'; this.style.boxShadow='none';">
          </div>
        </div>

        <!-- Industry -->
        <div>
          <label style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 8px;">Industry *</label>
          <select name="industry" required style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; font-size: 14px; font-weight: 500; color: #0f172a; outline: none; transition: all 0.2s; background-color: #f8fafc; cursor: pointer; appearance: none;" onfocus="this.style.borderColor='#00bfa5'; this.style.backgroundColor='#ffffff'; this.style.boxShadow='0 0 0 3px rgba(0, 191, 165, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.backgroundColor='#f8fafc'; this.style.boxShadow='none';">
            <option value="" disabled selected hidden>Select Industry</option>
            <option value="research">Academic / Research</option>
            <option value="gov">Government / Public Sector</option>
            <option value="tech">Technology / Data Science</option>
            <option value="env">Environmental Agency</option>
            <option value="other">Other</option>
          </select>
        </div>

        <!-- Intended Use Case -->
        <div>
          <label style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 8px;">Intended Use Case *</label>
          <textarea name="use_case" required placeholder="Describe your operational or analytical requirements for this telemetry data..." style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; font-size: 14px; font-weight: 500; color: #0f172a; outline: none; transition: all 0.2s; background-color: #f8fafc; resize: none; min-height: 80px; font-family: var(--font-sans);" onfocus="this.style.borderColor='#00bfa5'; this.style.backgroundColor='#ffffff'; this.style.boxShadow='0 0 0 3px rgba(0, 191, 165, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.backgroundColor='#f8fafc'; this.style.boxShadow='none';"></textarea>
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
      <button onclick="closeDatasetModal()" style="padding: 10px 24px; border-radius: 8px; border: none; background: #f1f5f9; color: #475569; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s;" onmouseenter="this.style.background='#e2e8f0'; this.style.color='#0f172a';" onmouseleave="this.style.background='#f1f5f9'; this.style.color='#475569';">
        Close Window
      </button>
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

async function fetchAllHistory(forcedDate = null) {
    try {
        const datePicker = document.getElementById('history-date-picker');
        let queryDate = forcedDate || datePicker.value;
        
        // Default date if none selected
        if (!queryDate) {
            queryDate = new Date().toISOString().split('T')[0];
            datePicker.value = queryDate;
        }

        let data;
        if (window.INITIAL_DATA && !forcedDate) {
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
        
        document.getElementById('chart-main-title').innerText = 'Air Quality Hourly Trend Analysis (12 AM – 11 PM)';
        document.getElementById('table-title').innerText = 'AQI Historical Telemetry Table (12 AM – 11 PM)';
    } else {
        tabWeather.style.backgroundColor = '#00bfa5';
        tabWeather.style.color = '#ffffff';
        tabAqi.style.backgroundColor = 'transparent';
        tabAqi.style.color = '#64748b';
        
        document.getElementById('chips-aqi').style.display = 'none';
        document.getElementById('chips-weather').style.display = 'flex';
        
        document.getElementById('summary-aqi-block').style.display = 'none';
        document.getElementById('summary-weather-block').style.display = 'flex';
        
        document.getElementById('chart-main-title').innerText = 'Weather Hourly Trend Analysis (12 AM – 11 PM)';
        document.getElementById('table-title').innerText = 'Weather Historical Telemetry Table (12 AM – 11 PM)';
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
    
    // Filter data for selected date
    filteredDayData = sourceData.filter(d => {
        if (!d.timestamp) return false;
        return d.timestamp.startsWith(dateInput);
    });

    // Sort ascending by time
    filteredDayData.sort((a,b) => new Date(a.timestamp) - new Date(b.timestamp));
    
    const dateStrLong = new Date(dateInput).toLocaleDateString('en-US', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
    
    document.getElementById('date-subtitle-desktop').innerText = `Showing ${filteredDayData.length} hourly data points for ${dateStrLong} (12:00 AM – 11:00 PM)`;
    document.getElementById('summary-date-display').innerText = dateStrLong;
    document.getElementById('log-count').innerText = filteredDayData.length;
    
    document.getElementById('chart-sub-title').innerText = `24 Hourly data points progression for ${dateStrLong} (12:00 AM to 11:00 PM)`;
    document.getElementById('table-subtitle').innerText = `24 Hourly Telemetry Rows for ${dateStrLong} (${filteredDayData.length} Logged Readings)`;
    
    if (filteredDayData.length === 0) {
        document.getElementById('chart-empty').style.display = 'flex';
        document.getElementById('chart-empty-text').innerText = `No records found for ${dateStrLong}.`;
        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }
        document.getElementById('avg-aqi-val').innerText = '--';
        document.getElementById('avg-aqi-label').style.display = 'none';
        document.getElementById('min-max-aqi').style.display = 'none';
        document.getElementById('mini-chips-container').style.display = 'none';
        
        document.getElementById('table-body').innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 36px 20px; color: #64748b;">No telemetry records available for ${dateStrLong}.</td></tr>`;
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
            if ((d.wind_gust || 0) > maxGust) maxGust = d.wind_gust || 0;
            sumRain += d.rain_gauge || 0;
            if (d.wind_direction != null) windDirs.push(d.wind_direction);
        }
    });

    const count = filteredDayData.length;

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
        document.getElementById('avg-pm25').innerText = countPm25 > 0 ? (sumPm25/countPm25).toFixed(3) : 'N/A';
        document.getElementById('avg-pm10').innerText = countPm10 > 0 ? (sumPm10/countPm10).toFixed(3) : 'N/A';
        document.getElementById('avg-co').innerText   = countCo   > 0 ? (sumCo/countCo).toFixed(3)     : 'N/A';
        document.getElementById('avg-no2').innerText  = countNo2  > 0 ? (sumNo2/countNo2).toFixed(3)   : 'N/A';
        document.getElementById('avg-o3').innerText   = countO3   > 0 ? (sumO3/countO3).toFixed(3)     : 'N/A';
    } else {
        document.getElementById('w-avg-temp').innerText = (sumTemp/count).toFixed(1) + '°C';
        document.getElementById('w-avg-hum').innerText = (sumHum/count).toFixed(1) + '%';
        document.getElementById('w-avg-wind').innerText = (sumWind/count).toFixed(1) + ' km/h';
        document.getElementById('w-max-gust').innerText = maxGust > -1 ? maxGust.toFixed(1) + ' km/h' : '--';
        document.getElementById('w-total-rain').innerText = sumRain.toFixed(1) + ' mm';
        
        let modeDir = '--';
        if(windDirs.length > 0) {
            modeDir = getCompassDir(windDirs[0]); // Simplified mode calculation
        }
        document.getElementById('w-mode-dir').innerText = modeDir;
    }

    // Chart Render
    const ctx = document.getElementById('historical-chart').getContext('2d');
    if (chartInstance) { chartInstance.destroy(); }

    const labels = filteredDayData.map(d => {
        const dt = new Date(d.timestamp);
        return dt.toLocaleTimeString('en-US', {hour: '2-digit', hour12: true});
    });
    // Use null for missing values so Chart.js draws a gap instead of a flat 0 line
    const dataPoints = filteredDayData.map(d => (d[currentParam] != null && d[currentParam] !== 0) ? d[currentParam] : null);
    const color = PARAM_COLORS[currentParam];

    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, color + '66');
    gradient.addColorStop(1, color + '00');

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
                    displayColors: false
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#64748b' } },
                y: { grid: { color: '#e2e8f0', borderDash: [3, 3] }, ticks: { color: '#64748b' }, beginAtZero: true }
            }
        }
    });

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
            const dt = new Date(d.timestamp);
            return `<tr style="border-bottom: 1px solid #f1f5f9; background-color: ${i % 2 === 0 ? '#ffffff' : '#f8fafc'};">
                <td style="padding: 10px 16px; color: #0f172a; font-weight: 600;">${dt.toLocaleDateString('en-GB')}</td>
                <td style="padding: 10px 16px; color: #00bfa5; font-family: var(--font-mono);">${dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'})}</td>
                <td style="padding: 10px 16px; font-weight: 800; color: ${getAqiCategory(d.cpcb_aqi).text};">${Math.round(d.cpcb_aqi || 0)}</td>
                <td style="padding: 10px 16px; color: #475569;">${Number(d.pm25 || 0).toFixed(3)}</td>
                <td style="padding: 10px 16px; color: #475569;">${Number(d.pm10 || 0).toFixed(3)}</td>
                <td style="padding: 10px 16px; color: ${d.co != null ? '#475569' : '#94a3b8'}; font-style: ${d.co != null ? 'normal' : 'italic'}">${d.co != null ? Number(d.co).toFixed(3) : 'N/A'}</td>
                <td style="padding: 10px 16px; color: #475569;">${Number(d.no2 || 0).toFixed(3)}</td>
                <td style="padding: 10px 16px; color: #475569;">${Number(d.o3 || 0).toFixed(3)}</td>
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
            const dt = new Date(d.timestamp);
            return `<tr style="border-bottom: 1px solid #f1f5f9; background-color: ${i % 2 === 0 ? '#ffffff' : '#f8fafc'};">
                <td style="padding: 10px 16px; color: #0f172a; font-weight: 600;">${dt.toLocaleDateString('en-GB')}</td>
                <td style="padding: 10px 16px; color: #00bfa5; font-family: var(--font-mono);">${dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'})}</td>
                <td style="padding: 10px 16px; color: #475569; font-weight: 600;">${Number(d.temperature || 0).toFixed(1)}</td>
                <td style="padding: 10px 16px; color: #475569;">${Number(d.humidity || 0).toFixed(1)}</td>
                <td style="padding: 10px 16px; color: #475569;">${Number(d.wind_speed || 0).toFixed(3)}</td>
                <td style="padding: 10px 16px; color: #475569;">${Number(d.wind_gust || 0).toFixed(3)}</td>
                <td style="padding: 10px 16px; color: #475569;">${getCompassDir(d.wind_direction)}</td>
                <td style="padding: 10px 16px; color: #475569;">${Number(d.rain_gauge || 0).toFixed(3)}</td>
            </tr>`;
        }).join('');
    }
}

function openDatasetModal() {
    const scopeLabel = currentSubTab === 'aqi' ? 'AQI Telemetry' : 'Weather Telemetry';
    const dateInput = document.getElementById('history-date-picker').value || 'Selected Date';
    document.getElementById('modal-dataset-scope').innerHTML = `${scopeLabel} — ${dateInput} <br><span style="font-size: 12px; color: #ef4444; margin-top: 4px; display: inline-block; font-weight: 500;">* Data provided is strictly limited to 1 day (for the requested date).</span>`;
    
    // Reset views
    document.getElementById('dataset-form').style.display = 'flex';
    document.getElementById('dataset-success').style.display = 'none';
    
    const modal = document.getElementById('dataset-modal');
    modal.style.display = 'flex';
    // Trigger fade in
    setTimeout(() => { modal.style.opacity = '1'; }, 10);
    
    lucide.createIcons();
}

function closeDatasetModal() {
    const modal = document.getElementById('dataset-modal');
    modal.style.opacity = '0';
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300); // Wait for transition
}

function submitDatasetRequest(e) {
    e.preventDefault();
    const btn = document.getElementById('dataset-submit-btn');
    const originalText = btn.innerHTML;
    
    // Gather form data
    const formDataObj = new FormData(e.target);
    const formData = {
        name: formDataObj.get('fullName'),
        email: formDataObj.get('email'),
        org: formDataObj.get('org'),
        industry: formDataObj.get('industry'),
        use_case: formDataObj.get('use_case'),
        scope: document.getElementById('modal-dataset-scope').innerText
    };

    btn.innerHTML = `<i data-lucide="loader-2" class="lucide-spin" style="width: 16px; height: 16px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle;"></i> <span style="vertical-align: middle;">Transmitting Request...</span>`;
    btn.style.pointerEvents = 'none';
    lucide.createIcons();
    
    fetch('api/submit_request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            // Save to Firebase Realtime Database for true cross-device sync
            const reqData = {
                id: data.request_id || 'req_' + Math.floor(Math.random()*10000),
                name: formData.name,
                email: formData.email,
                org: formData.org,
                industry: formData.industry,
                scope: formData.scope,
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
            }).then(() => {
                document.getElementById('dataset-form').style.display = 'none';
                document.getElementById('dataset-success').style.display = 'flex';
                document.getElementById('dataset-form').reset();
                lucide.createIcons();
            });
        } else {
            alert("Error: " + (data.error || "Unknown error occurred"));
        }
    })
    .catch(err => {
        console.error("Error submitting request", err);
        alert("Failed to connect to the server.");
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.style.pointerEvents = 'auto';
    });
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

window.__cleanupCurrentPage = function() {
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
}, 0);
</script>
