<div style="display: flex; flex-direction: column; gap: 24px; max-width: 1200px; margin: 0 auto; padding: 24px; animation: fade-in 0.4s ease-out;">

  <!-- Sub-Navigation Selector -->
  <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
    <div style="display: inline-flex; align-items: center; background-color: #ffffff; padding: 5px; border-radius: 999px; border: 1px solid #cbd5e1; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06); gap: 6px;">
      <button id="tab-aqi" onclick="switchLiveTab('aqi')" style="position: relative; display: flex; align-items: center; gap: 8px; padding: 9px 24px; border-radius: 999px; border: none; font-size: 14px; font-weight: 700; font-family: var(--font-sans); cursor: pointer; background-color: #00bfa5; color: #ffffff; transition: all 0.15s ease;">
        <i data-lucide="activity" style="width: 17px; height: 17px; z-index: 1;"></i>
        <span style="z-index: 1;">AQI Live Telemetry</span>
      </button>
      <button id="tab-weather" onclick="switchLiveTab('weather')" style="position: relative; display: flex; align-items: center; gap: 8px; padding: 9px 24px; border-radius: 999px; border: none; font-size: 14px; font-weight: 700; font-family: var(--font-sans); cursor: pointer; background-color: transparent; color: #64748b; transition: all 0.15s ease;">
        <i data-lucide="cloud-sun" style="width: 17px; height: 17px; z-index: 1;"></i>
        <span style="z-index: 1;">Weather Live Telemetry</span>
      </button>
    </div>
  </div>

<div id="aqi-view" style="display: block; animation: fade-in 0.4s ease-out;">

<!-- Offline Banner -->
<div id="aqi-offline-banner" style="display: none; background-color: #fff7ed; border: 1px solid #fed7aa; border-left: 4px solid #f97316; border-radius: 12px; padding: 12px 16px; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
  <div style="display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: #475569;">
    <i data-lucide="alert-triangle" style="width: 16px; height: 16px; color: #f97316; margin-top: 2px; flex-shrink: 0;"></i>
    <span><strong style="color: #c2410c;">AQI Sensor Node Offline:</strong> No new live telemetry received in cloud for >15 mins (Last update: <strong style="color: #f97316;" id="aqi-offline-last-update">--</strong>). Showing last recorded cloud values below.</span>
  </div>
  <div id="aqi-offline-badge-text" style="background-color: #ffedd5; color: #c2410c; font-size: 10px; font-weight: 800; padding: 4px 10px; border-radius: 999px; letter-spacing: 0.05em; font-family: var(--font-mono); white-space: nowrap;">OFFLINE</div>
</div>

  <!-- Page Header Titles -->
  <div>
    <h1 style="font-size: 24px; font-weight: 700; color: #0f172a; font-family: var(--font-sans); letter-spacing: -0.02em; margin: 0;">
      Real-time Air Quality Index
    </h1>
    <div style="font-size: 12.5px; color: #64748b; margin-top: 6px; font-family: var(--font-mono); display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
      <span>Last Updated: <span id="latest-time" style="color: #00bfa5; font-weight: 600;">Syncing...</span></span>
      <span id="aqi-live-badge" style="display: inline-flex; align-items: center; gap: 5px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; background-color: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;">
        <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10b981;"></span>
        LIVE
      </span>
    </div>
  </div>

  <!-- HERO AQI CARD -->
  <div id="hero-card" style="background-color: #f0fdf4; border-radius: 24px; padding: 28px 32px; color: #0f172a; border: 1.5px solid rgba(34, 197, 94, 0.25); box-shadow: 0 8px 30px rgba(34, 197, 94, 0.15), 0 1px 3px rgba(15,23,42,0.04); position: relative; overflow: hidden; transition: background-color 0.5s ease, border-color 0.5s ease, box-shadow 0.5s ease;">
    <div id="hero-left-bar" style="position: absolute; top: 0; left: 0; bottom: 0; width: 6px; background-color: #22c55e;"></div>

    <div style="position: relative; z-index: 1;">
      <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 12px;">
        <span id="status-dot" style="width: 8px; height: 8px; border-radius: 50%; background-color: #22c55e; display: inline-block;"></span>
        <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">
          <span class="hidden md:inline">Status Panel • </span>Live Stream
        </span>
      </div>

      <div style="display: flex; align-items: center; gap: 24px; margin-bottom: 20px; flex-wrap: wrap;">
        <div>
          <div id="hero-aqi-val" style="font-family: var(--font-mono); font-size: 72px; font-weight: 800; line-height: 1; letter-spacing: -0.04em; color: #0f172a;">
            --
          </div>
          <div style="font-size: 11px; font-weight: 600; color: #64748b; margin-top: 4px; letter-spacing: 0.06em; text-transform: uppercase; font-family: var(--font-mono);">
            LIVE AQI
          </div>
        </div>

        <div>
          <div style="font-size: 12px; color: #64748b; margin-bottom: 6px;">Air Quality is</div>
          <div id="hero-aqi-badge" style="display: inline-flex; align-items: center; gap: 7px; padding: 6px 18px; border-radius: 999px; background-color: #ffffff; border: 1.5px solid rgba(34, 197, 94, 0.3); color: #0f172a; font-size: 16px; font-weight: 800; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);">
            <span id="hero-aqi-badge-dot" style="width: 8px; height: 8px; border-radius: 50%; background-color: #22c55e;"></span>
            <span id="hero-aqi-label">Loading...</span>
          </div>
        </div>
      </div>

      <!-- Quick PM2.5 & PM10 Summary -->
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 8px; background-color: #ffffff; border: 1px solid rgba(0, 0, 0, 0.07); font-size: 12px; color: #334155; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
          <span style="color: #64748b; font-weight: 600;">PM₂.₅:</span>
          <strong id="hero-pm25" style="color: #0f172a; font-family: var(--font-mono);">-- µg/m³</strong>
        </div>
        <div style="display: flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 8px; background-color: #ffffff; border: 1px solid rgba(0, 0, 0, 0.07); font-size: 12px; color: #334155; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
          <span style="color: #64748b; font-weight: 600;">PM₁₀:</span>
          <strong id="hero-pm10" style="color: #0f172a; font-family: var(--font-mono);">-- µg/m³</strong>
        </div>
      </div>

      <!-- Overall AQI Scale Bar -->
      <div style="max-width: 380px; width: 100%;">
        <div style="display: flex; justify-content: space-between; padding: 0 2px; margin-bottom: 6px;">
          <span style="font-size: 9px; font-weight: 700; color: #22c55e; line-height: 1;">Good</span>
          <span style="font-size: 9px; font-weight: 700; color: #eab308; line-height: 1;">Moderate</span>
          <span style="font-size: 9px; font-weight: 700; color: #f97316; line-height: 1;">Poor</span>
          <span style="font-size: 9px; font-weight: 700; color: #ef4444; line-height: 1;">Unhealthy</span>
          <span style="font-size: 9px; font-weight: 700; color: #a855f7; line-height: 1;">Severe</span>
          <span style="font-size: 9px; font-weight: 700; color: #f43f5e; line-height: 1;">Hazardous</span>
        </div>

        <div style="position: relative; height: 12px; border-radius: 999px; background-color: #e2e8f0; margin-bottom: 6px;">
          <div style="width: 100%; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #22c55e 0%, #22c55e 16.66%, #eab308 16.66%, #eab308 33.33%, #00bfa5 33.33%, #00bfa5 50%, #ef4444 50%, #ef4444 66.66%, #a855f7 66.66%, #a855f7 83.33%, #f43f5e 83.33%, #f43f5e 100%); box-shadow: inset 0 1px 2px rgba(0,0,0,0.06);"></div>
          <div id="hero-scale-indicator" style="position: absolute; top: 50%; left: 0%; transform: translate(-50%, -50%); width: 20px; height: 20px; border-radius: 50%; background-color: #ffffff; border: 3px solid #22c55e; box-shadow: 0 2px 6px rgba(0,0,0,0.25); z-index: 3; transition: left 0.5s ease-out;"></div>
        </div>

        <div style="display: flex; justify-content: space-between; padding: 0 2px;">
          <span style="font-size: 9.5px; font-weight: 600; color: #64748b; font-family: var(--font-mono);">0</span>
          <span style="font-size: 9.5px; font-weight: 600; color: #64748b; font-family: var(--font-mono);">50</span>
          <span style="font-size: 9.5px; font-weight: 600; color: #64748b; font-family: var(--font-mono);">100</span>
          <span style="font-size: 9.5px; font-weight: 600; color: #64748b; font-family: var(--font-mono);">150</span>
          <span style="font-size: 9.5px; font-weight: 600; color: #64748b; font-family: var(--font-mono);">200</span>
          <span style="font-size: 9.5px; font-weight: 600; color: #64748b; font-family: var(--font-mono);">300</span>
          <span style="font-size: 9.5px; font-weight: 600; color: #64748b; font-family: var(--font-mono);">301+</span>
        </div>
      </div>
    </div>
  </div>

  <!-- MAJOR AIR POLLUTANTS -->
  <div style="margin-top: 16px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
      <div>
        <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; font-family: var(--font-sans);">
          Major Air Pollutants
        </h2>

      </div>
    </div>

    <!-- Pollutants Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
      
      <!-- PM2.5 -->
      <div class="pollutant-card" onclick="openPollutantModal('pm25')" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 18px; color: #0f172a; display: flex; flex-direction: column; justify-content: space-between; gap: 12px; cursor: pointer; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04), 0 1px 3px rgba(15, 23, 42, 0.02); transition: all 0.2s ease; position: relative; overflow: hidden;" onmouseover="this.style.borderColor='#00bfa5'; this.style.transform='translateY(-3px)'" onmouseout="this.style.borderColor='#e2e8f0'; this.style.transform='translateY(0)'">
        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 38px; height: 38px; border-radius: 10px; background-color: #f0f9ff; color: #0ea5e9; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">🌫️</div>
            <div>
              <div style="font-size: 15px; font-weight: 700; font-family: var(--font-sans); color: #0f172a; line-height: 1.1;">PM₂.₅</div>
              <div style="font-size: 10.5px; color: #64748b; margin-top: 2px; white-space: nowrap;">WHO: ≤15</div>
            </div>
          </div>
          <div style="width: 28px; height: 28px; border-radius: 50%; background-color: #f8fafc; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; color: #64748b; flex-shrink: 0;">
            <i data-lucide="info" style="width: 13px; height: 13px;"></i>
          </div>
        </div>
        <div style="display: flex; align-items: flex-end; justify-content: space-between; width: 100%; margin-top: 2px;">
          <div>
            <div id="card-pm25" style="font-family: var(--font-mono); font-size: 24px; font-weight: 800; color: #0ea5e9; line-height: 1;">--</div>
            <div style="font-size: 11px; font-weight: 600; color: #64748b; margin-top: 4px; font-family: var(--font-mono);">µg/m³</div>
          </div>
          <div id="tag-pm25" style="padding: 4px 10px; border-radius: 999px; background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b; font-size: 11px; font-weight: 700; display: flex; align-items: center; gap: 4px;">
            <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #94a3b8;"></span>
            <span>Load</span>
          </div>
        </div>
      </div>

      <!-- PM10 -->
      <div class="pollutant-card" onclick="openPollutantModal('pm10')" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 18px; color: #0f172a; display: flex; flex-direction: column; justify-content: space-between; gap: 12px; cursor: pointer; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04), 0 1px 3px rgba(15, 23, 42, 0.02); transition: all 0.2s ease; position: relative; overflow: hidden;" onmouseover="this.style.borderColor='#00bfa5'; this.style.transform='translateY(-3px)'" onmouseout="this.style.borderColor='#e2e8f0'; this.style.transform='translateY(0)'">
        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 38px; height: 38px; border-radius: 10px; background-color: #eef2ff; color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">☁️</div>
            <div>
              <div style="font-size: 15px; font-weight: 700; font-family: var(--font-sans); color: #0f172a; line-height: 1.1;">PM₁₀</div>
              <div style="font-size: 10.5px; color: #64748b; margin-top: 2px; white-space: nowrap;">WHO: ≤45</div>
            </div>
          </div>
          <div style="width: 28px; height: 28px; border-radius: 50%; background-color: #f8fafc; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; color: #64748b; flex-shrink: 0;">
            <i data-lucide="info" style="width: 13px; height: 13px;"></i>
          </div>
        </div>
        <div style="display: flex; align-items: flex-end; justify-content: space-between; width: 100%; margin-top: 2px;">
          <div>
            <div id="card-pm10" style="font-family: var(--font-mono); font-size: 24px; font-weight: 800; color: #6366f1; line-height: 1;">--</div>
            <div style="font-size: 11px; font-weight: 600; color: #64748b; margin-top: 4px; font-family: var(--font-mono);">µg/m³</div>
          </div>
          <div id="tag-pm10" style="padding: 4px 10px; border-radius: 999px; background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b; font-size: 11px; font-weight: 700; display: flex; align-items: center; gap: 4px;">
            <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #94a3b8;"></span>
            <span>Load</span>
          </div>
        </div>
      </div>

      <!-- CO -->
      <div class="pollutant-card" onclick="openPollutantModal('co')" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 18px; color: #0f172a; display: flex; flex-direction: column; justify-content: space-between; gap: 12px; cursor: pointer; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04), 0 1px 3px rgba(15, 23, 42, 0.02); transition: all 0.2s ease; position: relative; overflow: hidden;" onmouseover="this.style.borderColor='#00bfa5'; this.style.transform='translateY(-3px)'" onmouseout="this.style.borderColor='#e2e8f0'; this.style.transform='translateY(0)'">
        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 38px; height: 38px; border-radius: 10px; background-color: #f8fafc; color: #475569; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">💨</div>
            <div>
              <div style="font-size: 15px; font-weight: 700; font-family: var(--font-sans); color: #0f172a; line-height: 1.1;">CO</div>
              <div style="font-size: 10.5px; color: #64748b; margin-top: 2px; white-space: nowrap;">WHO: ≤4</div>
            </div>
          </div>
          <div style="width: 28px; height: 28px; border-radius: 50%; background-color: #f8fafc; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; color: #64748b; flex-shrink: 0;">
            <i data-lucide="info" style="width: 13px; height: 13px;"></i>
          </div>
        </div>
        <div style="display: flex; align-items: flex-end; justify-content: space-between; width: 100%; margin-top: 2px;">
          <div>
            <div id="card-co" style="font-family: var(--font-mono); font-size: 24px; font-weight: 800; color: #475569; line-height: 1;">--</div>
            <div style="font-size: 11px; font-weight: 600; color: #64748b; margin-top: 4px; font-family: var(--font-mono);">mg/m³</div>
          </div>
          <div id="tag-co" style="padding: 4px 10px; border-radius: 999px; background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b; font-size: 11px; font-weight: 700; display: flex; align-items: center; gap: 4px;">
            <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #94a3b8;"></span>
            <span>Load</span>
          </div>
        </div>
      </div>

      <!-- NO2 -->
      <div class="pollutant-card" onclick="openPollutantModal('no2')" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 18px; color: #0f172a; display: flex; flex-direction: column; justify-content: space-between; gap: 12px; cursor: pointer; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04), 0 1px 3px rgba(15, 23, 42, 0.02); transition: all 0.2s ease; position: relative; overflow: hidden;" onmouseover="this.style.borderColor='#00bfa5'; this.style.transform='translateY(-3px)'" onmouseout="this.style.borderColor='#e2e8f0'; this.style.transform='translateY(0)'">
        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 38px; height: 38px; border-radius: 10px; background-color: #faf5ff; color: #a855f7; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">🏭</div>
            <div>
              <div style="font-size: 15px; font-weight: 700; font-family: var(--font-sans); color: #0f172a; line-height: 1.1;">NO₂</div>
              <div style="font-size: 10.5px; color: #64748b; margin-top: 2px; white-space: nowrap;">WHO: ≤25</div>
            </div>
          </div>
          <div style="width: 28px; height: 28px; border-radius: 50%; background-color: #f8fafc; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; color: #64748b; flex-shrink: 0;">
            <i data-lucide="info" style="width: 13px; height: 13px;"></i>
          </div>
        </div>
        <div style="display: flex; align-items: flex-end; justify-content: space-between; width: 100%; margin-top: 2px;">
          <div>
            <div id="card-no2" style="font-family: var(--font-mono); font-size: 24px; font-weight: 800; color: #a855f7; line-height: 1;">--</div>
            <div style="font-size: 11px; font-weight: 600; color: #64748b; margin-top: 4px; font-family: var(--font-mono);">µg/m³</div>
          </div>
          <div id="tag-no2" style="padding: 4px 10px; border-radius: 999px; background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b; font-size: 11px; font-weight: 700; display: flex; align-items: center; gap: 4px;">
            <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #94a3b8;"></span>
            <span>Load</span>
          </div>
        </div>
      </div>

      <!-- O3 -->
      <div class="pollutant-card" onclick="openPollutantModal('o3')" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 18px; color: #0f172a; display: flex; flex-direction: column; justify-content: space-between; gap: 12px; cursor: pointer; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04), 0 1px 3px rgba(15, 23, 42, 0.02); transition: all 0.2s ease; position: relative; overflow: hidden;" onmouseover="this.style.borderColor='#00bfa5'; this.style.transform='translateY(-3px)'" onmouseout="this.style.borderColor='#e2e8f0'; this.style.transform='translateY(0)'">
        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 38px; height: 38px; border-radius: 10px; background-color: #fefce8; color: #eab308; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">☀️</div>
            <div>
              <div style="font-size: 15px; font-weight: 700; font-family: var(--font-sans); color: #0f172a; line-height: 1.1;">O₃</div>
              <div style="font-size: 10.5px; color: #64748b; margin-top: 2px; white-space: nowrap;">WHO: ≤100</div>
            </div>
          </div>
          <div style="width: 28px; height: 28px; border-radius: 50%; background-color: #f8fafc; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; color: #64748b; flex-shrink: 0;">
            <i data-lucide="info" style="width: 13px; height: 13px;"></i>
          </div>
        </div>
        <div style="display: flex; align-items: flex-end; justify-content: space-between; width: 100%; margin-top: 2px;">
          <div>
            <div id="card-o3" style="font-family: var(--font-mono); font-size: 24px; font-weight: 800; color: #eab308; line-height: 1;">--</div>
            <div style="font-size: 11px; font-weight: 600; color: #64748b; margin-top: 4px; font-family: var(--font-mono);">µg/m³</div>
          </div>
          <div id="tag-o3" style="padding: 4px 10px; border-radius: 999px; background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b; font-size: 11px; font-weight: 700; display: flex; align-items: center; gap: 4px;">
            <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #94a3b8;"></span>
            <span>Load</span>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- AIR QUALITY VISUALIZATIONS -->
  <div style="display: flex; flex-direction: column; gap: 20px; margin-top: 8px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
      <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; font-family: var(--font-sans);">
        <i data-lucide="trending-up" style="width: 20px; height: 20px; color: #00bfa5;"></i>
        Air Quality Visualizations
      </h2>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr)); gap: 20px;">
      <!-- AQI Parameters Comparison Lineplot Card -->
      <div style="background-color: #ffffff; border-radius: 20px; padding: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05); display: flex; flex-direction: column; gap: 16px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
          <div>
            <div style="font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px;">
              <i data-lucide="layers" style="width: 16px; height: 16px; color: #0284c7;"></i>
              AQI Parameters Comparison
            </div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">
              All AQI pollutants on a single graph (excl. temp, humidity, wind & rain)
            </div>
          </div>
        </div>

        <!-- Pollutant Filter Chips -->
        <div style="display: flex; flex-wrap: wrap; gap: 6px;" id="pollutant-filters">
          <button onclick="togglePollutant('pm25')" id="filter-pm25" data-active="true" data-color="#0284c7" style="padding: 4px 10px; border-radius: 999px; border: 1px solid #0284c7; background-color: rgba(2, 132, 199, 0.15); color: #0284c7; font-size: 11.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.15s ease;">
            <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #0284c7;"></span> PM2.5
          </button>
          <button onclick="togglePollutant('pm10')" id="filter-pm10" data-active="true" data-color="#6366f1" style="padding: 4px 10px; border-radius: 999px; border: 1px solid #6366f1; background-color: rgba(99, 102, 241, 0.15); color: #6366f1; font-size: 11.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.15s ease;">
            <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #6366f1;"></span> PM10
          </button>
          <button onclick="togglePollutant('co')" id="filter-co" data-active="true" data-color="#64748b" style="padding: 4px 10px; border-radius: 999px; border: 1px solid #64748b; background-color: rgba(100, 116, 139, 0.15); color: #64748b; font-size: 11.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.15s ease;">
            <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #64748b;"></span> CO
          </button>
          <button onclick="togglePollutant('no2')" id="filter-no2" data-active="true" data-color="#9333ea" style="padding: 4px 10px; border-radius: 999px; border: 1px solid #9333ea; background-color: rgba(147, 51, 234, 0.15); color: #9333ea; font-size: 11.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.15s ease;">
            <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #9333ea;"></span> NO₂
          </button>
          <button onclick="togglePollutant('o3')" id="filter-o3" data-active="true" data-color="#d97706" style="padding: 4px 10px; border-radius: 999px; border: 1px solid #d97706; background-color: rgba(217, 119, 6, 0.15); color: #d97706; font-size: 11.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.15s ease;">
            <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #d97706;"></span> O₃
          </button>
        </div>

        <div style="width: 100%; height: 250px; position: relative;">
          <canvas id="comparison-chart"></canvas>
          <div id="comparison-chart-empty" style="display: none; position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.8); color: #64748b; font-size: 13px;">
            Loading comparison graph...
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- NETWORK READINGS TABLE -->
  <div style="margin-top: 8px;">
    <div style="margin-bottom: 12px;">
      <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; font-family: var(--font-sans);">
        <i data-lucide="table" style="width: 18px; height: 18px; color: #00bfa5;"></i>
        <span>Network Readings &amp; Status</span>
      </h2>
    </div>

    <div style="background-color: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);">
      <div style="overflow-x: auto; max-height: 420px;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; font-family: var(--font-sans); min-width: 800px;">
          <thead style="position: sticky; top: 0; z-index: 1; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
            <tr>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Time</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">AQI</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">PM2.5 (µg/m³)</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">PM10 (µg/m³)</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">CO (mg/m³)</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">NO₂ (µg/m³)</th>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">O₃ (µg/m³)</th>
            </tr>
          </thead>
          <tbody id="live-table-body">
            <tr>
              <td colspan="7" style="padding: 40px; text-align: center; color: #64748b;">
                Loading stream...
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

    <!-- Air Quality Index (AQI) Scale Card -->
    <div style="background-color: #ffffff; border-radius: 20px; padding: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05); display: flex; flex-direction: column; gap: 16px; margin-top: 16px;">
      <div>
        <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; font-family: var(--font-sans);">
          <i data-lucide="shield-alert" style="width: 20px; height: 20px; color: #00bfa5;"></i>
          Air Quality Index (AQI) Scale
        </h2>
        <p style="font-size: 12.5px; color: #64748b; margin-top: 4px; margin-bottom: 0;">
          Know what each category of the Air Quality Index implies for health and ambient safety.
        </p>
      </div>

      <div style="display: flex; flex-direction: column; gap: 10px;">
        <!-- Scale Items -->
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #f0fdf4; border: 1px solid #bbf7d0; gap: 12px; flex-wrap: wrap;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="width: 12px; height: 12px; border-radius: 4px; background-color: #22c55e; flex-shrink: 0;"></span>
            <div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 14px; font-weight: 700; color: #0f172a;">Good</span>
                <span style="font-size: 12px; font-weight: 700; color: #22c55e; font-family: var(--font-mono);">(0 to 50)</span>
              </div>
              <div style="font-size: 11.5px; color: #475569; margin-top: 2px;">Air quality is considered satisfactory, and air pollution poses little or no risk.</div>
            </div>
          </div>
        </div>
        
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #fefce8; border: 1px solid #fef08a; gap: 12px; flex-wrap: wrap;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="width: 12px; height: 12px; border-radius: 4px; background-color: #eab308; flex-shrink: 0;"></span>
            <div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 14px; font-weight: 700; color: #0f172a;">Moderate</span>
                <span style="font-size: 12px; font-weight: 700; color: #eab308; font-family: var(--font-mono);">(51 to 100)</span>
              </div>
              <div style="font-size: 11.5px; color: #475569; margin-top: 2px;">Acceptable air quality; minor breathing discomfort may occur for sensitive individuals.</div>
            </div>
          </div>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #fff7ed; border: 1px solid #fed7aa; gap: 12px; flex-wrap: wrap;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="width: 12px; height: 12px; border-radius: 4px; background-color: #f97316; flex-shrink: 0;"></span>
            <div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 14px; font-weight: 700; color: #0f172a;">Poor</span>
                <span style="font-size: 12px; font-weight: 700; color: #f97316; font-family: var(--font-mono);">(101 to 200)</span>
              </div>
              <div style="font-size: 11.5px; color: #475569; margin-top: 2px;">Breathing discomfort to people with lungs, asthma, and heart diseases.</div>
            </div>
          </div>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #fef2f2; border: 1px solid #fecaca; gap: 12px; flex-wrap: wrap;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="width: 12px; height: 12px; border-radius: 4px; background-color: #ef4444; flex-shrink: 0;"></span>
            <div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 14px; font-weight: 700; color: #0f172a;">Unhealthy</span>
                <span style="font-size: 12px; font-weight: 700; color: #ef4444; font-family: var(--font-mono);">(201 to 300)</span>
              </div>
              <div style="font-size: 11.5px; color: #475569; margin-top: 2px;">Breathing discomfort to most people on prolonged exposure. Limit strenuous outdoor exertion.</div>
            </div>
          </div>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #faf5ff; border: 1px solid #e9d5ff; gap: 12px; flex-wrap: wrap;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="width: 12px; height: 12px; border-radius: 4px; background-color: #a855f7; flex-shrink: 0;"></span>
            <div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 14px; font-weight: 700; color: #0f172a;">Severe</span>
                <span style="font-size: 12px; font-weight: 700; color: #a855f7; font-family: var(--font-mono);">(301 to 400)</span>
              </div>
              <div style="font-size: 11.5px; color: #475569; margin-top: 2px;">Respiratory illness on prolonged exposure; significantly impacts people with existing ailments.</div>
            </div>
          </div>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #fff1f2; border: 1px solid #fecdd3; gap: 12px; flex-wrap: wrap;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="width: 12px; height: 12px; border-radius: 4px; background-color: #f43f5e; flex-shrink: 0;"></span>
            <div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 14px; font-weight: 700; color: #0f172a;">Hazardous</span>
                <span style="font-size: 12px; font-weight: 700; color: #f43f5e; font-family: var(--font-mono);">(401+)</span>
              </div>
              <div style="font-size: 11.5px; color: #475569; margin-top: 2px;">May cause serious health impacts on entire population. Wear N95 masks and stay indoors.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php include __DIR__ . '/weather.php'; ?>

</div>

<script>
function switchLiveTab(tab) {
    const aqiBtn = document.getElementById('tab-aqi');
    const weatherBtn = document.getElementById('tab-weather');
    const aqiView = document.getElementById('aqi-view');
    const weatherView = document.getElementById('weather-view');

    if (tab === 'aqi') {
        aqiBtn.style.backgroundColor = '#00bfa5';
        aqiBtn.style.color = '#ffffff';
        weatherBtn.style.backgroundColor = 'transparent';
        weatherBtn.style.color = '#64748b';
        aqiView.style.display = 'block';
        weatherView.style.display = 'none';
    } else {
        weatherBtn.style.backgroundColor = '#00bfa5';
        weatherBtn.style.color = '#ffffff';
        aqiBtn.style.backgroundColor = 'transparent';
        aqiBtn.style.color = '#64748b';
        weatherView.style.display = 'block';
        aqiView.style.display = 'none';
    }
}

function updateTag(elementId, value, limit) {
    const el = document.getElementById(elementId);
    if (!el) return;
    if (value == null || value === 'N/A' || value === '--') {
        el.style.display = 'none';
        return;
    }
    el.style.display = 'inline-flex';
    const isSafe = parseFloat(value) <= limit;
    
    if (isSafe) {
        el.style.backgroundColor = '#dcfce7';
        el.style.borderColor = '#bbf7d0';
        el.style.color = '#15803d';
        el.innerHTML = `<span style="width: 6px; height: 6px; border-radius: 50%; background-color: #16a34a;"></span><span>Normal</span>`;
    } else {
        el.style.backgroundColor = '#fee2e2';
        el.style.borderColor = '#fecaca';
        el.style.color = '#b91c1c';
        el.innerHTML = `<span style="width: 6px; height: 6px; border-radius: 50%; background-color: #dc2626;"></span><span>High</span>`;
    }
}

let liveDataStream = [];
let comparisonChartInstance = null;
const visiblePollutants = { pm25: true, pm10: true, co: true, no2: true, o3: true };
const pollutantColors = { pm25: '#0284c7', pm10: '#6366f1', co: '#64748b', no2: '#9333ea', o3: '#d97706' };

function togglePollutant(key) {
    visiblePollutants[key] = !visiblePollutants[key];
    const btn = document.getElementById(`filter-${key}`);
    const color = btn.getAttribute('data-color');
    if (visiblePollutants[key]) {
        btn.style.backgroundColor = color + '26'; // approx 15% opacity
        btn.style.borderColor = color;
        btn.style.color = color;
        btn.querySelector('span').style.backgroundColor = color;
    } else {
        btn.style.backgroundColor = '#f8fafc';
        btn.style.borderColor = '#e2e8f0';
        btn.style.color = '#94a3b8';
        btn.querySelector('span').style.backgroundColor = '#cbd5e1';
    }
    renderComparisonChart();
}

function renderComparisonChart() {
    const ctx = document.getElementById('comparison-chart');
    if (!ctx) return;
    
    if (comparisonChartInstance) {
        comparisonChartInstance.destroy();
    }

    if (liveDataStream.length === 0) {
        document.getElementById('comparison-chart-empty').style.display = 'flex';
        document.getElementById('comparison-chart-empty').innerText = 'No telemetry stream available.';
        return;
    }
    document.getElementById('comparison-chart-empty').style.display = 'none';

    // Reverse the array to show chronological order (left to right)
    const sortedData = [...liveDataStream].reverse();
    const labels = sortedData.map(d => {
        const dt = new Date(d.created_at || d.timestamp || new Date());
        return dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'});
    });

    const datasets = [];
    if (visiblePollutants.pm25) datasets.push({ label: 'PM2.5', data: sortedData.map(d => d.pm25), borderColor: pollutantColors.pm25, backgroundColor: 'transparent', pointRadius: 0, pointHoverRadius: 4, borderWidth: 2 });
    if (visiblePollutants.pm10) datasets.push({ label: 'PM10', data: sortedData.map(d => d.pm10), borderColor: pollutantColors.pm10, backgroundColor: 'transparent', pointRadius: 0, pointHoverRadius: 4, borderWidth: 2 });
    if (visiblePollutants.co) datasets.push({ label: 'CO', data: sortedData.map(d => d.co), borderColor: pollutantColors.co, backgroundColor: 'transparent', pointRadius: 0, pointHoverRadius: 4, borderWidth: 2 });
    if (visiblePollutants.no2) datasets.push({ label: 'NO2', data: sortedData.map(d => d.no2), borderColor: pollutantColors.no2, backgroundColor: 'transparent', pointRadius: 0, pointHoverRadius: 4, borderWidth: 2 });
    if (visiblePollutants.o3) datasets.push({ label: 'O3', data: sortedData.map(d => d.o3), borderColor: pollutantColors.o3, backgroundColor: 'transparent', pointRadius: 0, pointHoverRadius: 4, borderWidth: 2 });

    comparisonChartInstance = new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#ffffff',
                    titleColor: '#64748b',
                    bodyColor: '#0f172a',
                    bodyFont: { size: 12, weight: 'bold' },
                    borderColor: '#cbd5e1',
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 10,
                    displayColors: true
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 }, maxTicksLimit: 8 } },
                y: { grid: { color: '#e2e8f0', borderDash: [3, 3] }, ticks: { color: '#64748b', font: { size: 10 } }, beginAtZero: true }
            }
        }
    });
}

function renderLiveTable() {
    const tbody = document.getElementById('live-table-body');
    if (!tbody) return;
    
    if (liveDataStream.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="padding: 40px; text-align: center; color: #64748b;">No telemetry stream data.</td></tr>`;
        return;
    }

    tbody.innerHTML = liveDataStream.map((d, i) => {
        const dt = new Date(d.created_at || d.timestamp || new Date());
        const timeStr = dt.toLocaleDateString('en-GB') + ', ' + dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'}).toLowerCase();
        return `<tr style="border-bottom: 1px solid #f1f5f9; background-color: ${i % 2 === 0 ? '#ffffff' : '#f8fafc'};">
            <td style="padding: 11px 16px; font-family: var(--font-mono); font-size: 12px; color: #64748b; white-space: nowrap;">${timeStr}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 800; color: #0f172a; font-size: 14px;">${Math.round(d.cpcb_aqi || 0)}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); color: #334155;">${Number(d.pm25 || 0).toFixed(3)}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); color: #334155;">${Number(d.pm10 || 0).toFixed(3)}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); color: #334155;">${Number(d.co || 0).toFixed(3)}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); color: #334155;">${Number(d.no2 || 0).toFixed(3)}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); color: #334155;">${Number(d.o3 || 0).toFixed(3)}</td>
        </tr>`;
    }).join('');
}

async function refreshDashboard() {
    try {
        let bulkData;
        if (window.INITIAL_DATA) {
            bulkData = window.INITIAL_DATA;
            window.INITIAL_DATA = null;
        } else {
            const res = await fetch('api/telemetry.php?action=live_bulk&limit=60');
            bulkData = await res.json();
        }
        
        const aqiData = bulkData.aqi;
        const weatherData = bulkData.weather;
        const weatherHistory = bulkData.weather_history;
        const historyData = bulkData.aqi_history;
        
        if (aqiData) {
            const d = aqiData;
            const aqiValue = d.cpcb_aqi ?? '--';
            document.getElementById('hero-aqi-val').innerText = aqiValue;
            
            // AQI Badge & Colors
            if (d.aqi_info) {
                const color = d.aqi_info.color || '#22c55e';
                
                // Hero Background
                const hero = document.getElementById('hero-card');
                const badge = document.getElementById('hero-aqi-badge');
                
                if (color === '#65ff50' || color === '#22c55e') {
                    hero.style.backgroundColor = '#f0fdf4';
                    hero.style.borderColor = 'rgba(34,197,94,0.25)';
                    hero.style.boxShadow = '0 8px 30px rgba(34,197,94,0.15), 0 1px 3px rgba(15,23,42,0.04)';
                } else if (color === '#a3e635' || color === '#eab308' || color === '#facc15') {
                    hero.style.backgroundColor = '#fefce8';
                    hero.style.borderColor = 'rgba(234,179,8,0.25)';
                    hero.style.boxShadow = '0 8px 30px rgba(234,179,8,0.15), 0 1px 3px rgba(15,23,42,0.04)';
                } else if (color === '#fb923c' || color === '#f97316') {
                    hero.style.backgroundColor = '#fff7ed';
                    hero.style.borderColor = 'rgba(249,115,22,0.25)';
                    hero.style.boxShadow = '0 8px 30px rgba(249,115,22,0.15), 0 1px 3px rgba(15,23,42,0.04)';
                } else if (color === '#f87171' || color === '#ef4444') {
                    hero.style.backgroundColor = '#fef2f2';
                    hero.style.borderColor = 'rgba(239,68,68,0.25)';
                    hero.style.boxShadow = '0 8px 30px rgba(239,68,68,0.15), 0 1px 3px rgba(15,23,42,0.04)';
                } else if (color === '#c084fc' || color === '#a855f7') {
                    hero.style.backgroundColor = '#faf5ff';
                    hero.style.borderColor = 'rgba(168,85,247,0.25)';
                    hero.style.boxShadow = '0 8px 30px rgba(168,85,247,0.15), 0 1px 3px rgba(15,23,42,0.04)';
                }

                document.getElementById('hero-left-bar').style.backgroundColor = color;
                document.getElementById('status-dot').style.backgroundColor = color;
                
                badge.style.borderColor = color + '50';
                document.getElementById('hero-aqi-badge-dot').style.backgroundColor = color;
                document.getElementById('hero-aqi-label').innerText = d.aqi_info.label;
                
                const pct = Math.min((aqiValue / 500) * 100, 100);
                const indicator = document.getElementById('hero-scale-indicator');
                indicator.style.left = pct + '%';
                indicator.style.borderColor = color;
                
                // Populate Weather tab AQI badge
                const weatherAqiBadge = document.getElementById('weather-hero-aqi-label');
                if (weatherAqiBadge) {
                    weatherAqiBadge.innerText = `AQI ${aqiValue} · ${d.aqi_info.label}`;
                    weatherAqiBadge.style.color = color;
                    weatherAqiBadge.style.borderColor = color + '50';
                    if (color === '#65ff50' || color === '#22c55e') weatherAqiBadge.style.backgroundColor = '#f0fdf4';
                    else if (color === '#a3e635' || color === '#eab308' || color === '#facc15') weatherAqiBadge.style.backgroundColor = '#fefce8';
                    else if (color === '#fb923c' || color === '#f97316') weatherAqiBadge.style.backgroundColor = '#fff7ed';
                    else if (color === '#f87171' || color === '#ef4444') weatherAqiBadge.style.backgroundColor = '#fef2f2';
                    else if (color === '#c084fc' || color === '#a855f7') weatherAqiBadge.style.backgroundColor = '#faf5ff';
                }
            }

            // Quick PM Summary
            document.getElementById('hero-pm25').innerText = `${d.pm25 ?? 'N/A'} µg/m³`;
            document.getElementById('hero-pm10').innerText = `${d.pm10 ?? 'N/A'} µg/m³`;

            // Cards
            document.getElementById('card-pm25').innerText = d.pm25 ?? 'N/A';
            document.getElementById('card-pm10').innerText = d.pm10 ?? 'N/A';
            document.getElementById('card-co').innerText = d.co ?? 'N/A';
            document.getElementById('card-no2').innerText = d.no2 ?? 'N/A';
            document.getElementById('card-o3').innerText = d.o3 ?? 'N/A';

            updateTag('tag-pm25', d.pm25, 15);
            updateTag('tag-pm10', d.pm10, 45);
            updateTag('tag-co', d.co, 4);
            updateTag('tag-no2', d.no2, 25);
            updateTag('tag-o3', d.o3, 100);
        }
        
        // WEATHER DATA POPULATION
        if (weatherData) {
            const w = weatherData;
            const fmt = (val) => val != null ? Number(val).toFixed(1) : 'N/A';
            
            document.getElementById('weather-hero-temp').innerText = fmt(w.temperature);
            document.getElementById('feed-temp').innerText = fmt(w.temperature) + '°C';
            document.getElementById('card-temp').innerText = fmt(w.temperature) + '°C';
            
            document.getElementById('feed-hum').innerText = fmt(w.humidity) + '%';
            document.getElementById('card-hum').innerText = fmt(w.humidity) + '%';
            
            document.getElementById('feed-wind').innerText = fmt(w.wind_speed) + ' km/h';
            document.getElementById('card-wind').innerText = fmt(w.wind_speed) + ' km/h';
            
            document.getElementById('feed-gust').innerText = fmt(w.wind_gust) + ' km/h';
            document.getElementById('card-gust').innerText = fmt(w.wind_gust) + ' km/h';
            
            document.getElementById('feed-rain').innerText = fmt(w.rain_gauge) + ' mm';
            document.getElementById('card-rain').innerText = fmt(w.rain_gauge) + ' mm';
            
            const getCompassDir = (val) => {
              if (val == null) return 'N/A';
              const deg = Math.round(Number(val) % 360);
              if (isNaN(deg)) return val;
              const dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
              return dirs[Math.round(deg / 22.5) % 16];
            };
            const dir = getCompassDir(w.wind_direction);
            document.getElementById('feed-dir').innerText = dir;
            document.getElementById('card-dir').innerText = dir;

            let tLabel = 'Pleasant';
            if (w.temperature < 20) tLabel = 'Cool';
            if (w.temperature > 30) tLabel = 'Warm';
            if (w.temperature > 35) tLabel = 'Hot';
            document.getElementById('weather-hero-label').innerText = tLabel;
            
            const now = new Date();
            const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
            document.getElementById('weather-date-str').innerText = dateStr;
            document.getElementById('weather-latest-time').innerText = now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
        }
        
        // WEATHER HISTORY TABLE & TODAY'S TEMP RANGE
        const tbody = document.getElementById('weather-tbody');
        if (weatherHistory && weatherHistory.length > 0) {
            tbody.innerHTML = '';
            let temps = [];
            
            weatherHistory.forEach((row, i) => {
                const tsStr = row.timestamp || row.created_at;
                const tsDate = tsStr ? new Date(tsStr) : null;
                const formattedTime = tsDate ? tsDate.toLocaleDateString('en-GB') + ', ' + tsDate.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'}).toLowerCase() : 'N/A';
                
                if (row.temperature != null) temps.push(Number(row.temperature));
                
                const tr = document.createElement('tr');
                tr.style.backgroundColor = i % 2 === 0 ? '#ffffff' : '#f8fafc';
                tr.style.borderBottom = '1px solid #f1f5f9';
                
                tr.innerHTML = `
                  <td style="padding: 11px 16px; font-family: var(--font-mono); font-size: 12px; color: #64748b; white-space: nowrap;">${formattedTime}</td>
                  <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 800; color: #0f172a; font-size: 14px;">${row.temperature != null ? Number(row.temperature).toFixed(1) : 'N/A'}</td>
                  <td style="padding: 11px 16px; font-family: var(--font-mono); color: #334155;">${row.humidity != null ? Number(row.humidity).toFixed(1) : 'N/A'}</td>
                  <td style="padding: 11px 16px; font-family: var(--font-mono); color: #334155;">${row.wind_speed != null ? Number(row.wind_speed).toFixed(2) : 'N/A'}</td>
                  <td style="padding: 11px 16px; font-family: var(--font-mono); color: #334155;">${row.rain_gauge != null ? Number(row.rain_gauge).toFixed(2) : 'N/A'}</td>
                `;
                tbody.appendChild(tr);
            });
            
            if (temps.length > 0) {
                const min = Math.min(...temps).toFixed(1);
                const max = Math.max(...temps).toFixed(1);
                const avg = (temps.reduce((a,b) => a+b, 0) / temps.length).toFixed(1);
                document.getElementById('weather-low-temp').innerText = min + '°';
                document.getElementById('weather-avg-temp').innerText = avg + '°';
                document.getElementById('weather-high-temp').innerText = max + '°';
            }
        } else {
            tbody.innerHTML = '<tr><td colspan="5" style="padding: 40px; text-align: center; color: #64748b;">No live weather telemetry found.</td></tr>';
        }

        // LIVE AQI HISTORY (for chart and table)
        if (historyData && historyData.length > 0) {
            liveDataStream = historyData;
        }
        renderComparisonChart();
        renderLiveTable();

        const now = new Date();
        
        // --- AQI Offline Check ---
        let isAqiOffline = true;
        let aqiOfflineMinutes = 0;
        let aqiLastUpdateTimeStr = "Unknown";
        
        if (aqiData && aqiData.timestamp) {
            const lastUpdate = new Date(aqiData.timestamp);
            const diffMs = now - lastUpdate;
            aqiOfflineMinutes = Math.floor(diffMs / 60000);
            isAqiOffline = aqiOfflineMinutes > 15;
            aqiLastUpdateTimeStr = lastUpdate.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
        }

        const aqiBanner = document.getElementById('aqi-offline-banner');
        if (isAqiOffline) {
            if (aqiBanner) {
                document.getElementById('aqi-offline-last-update').innerText = aqiLastUpdateTimeStr + ' • ' + aqiOfflineMinutes + 'm ago';
                document.getElementById('aqi-offline-badge-text').innerText = 'OFFLINE (' + aqiOfflineMinutes + 'M AGO)';
                aqiBanner.style.display = 'flex';
            }
            const aqiLiveBadge = document.getElementById('aqi-live-badge');
            if (aqiLiveBadge) {
                aqiLiveBadge.style.backgroundColor = '#fff7ed';
                aqiLiveBadge.style.color = '#ea580c';
                aqiLiveBadge.style.borderColor = '#fed7aa';
                aqiLiveBadge.innerHTML = `<span style="width: 6px; height: 6px; border-radius: 50%; background-color: #f97316;"></span> OFFLINE (${aqiOfflineMinutes}m ago)`;
            }
            document.getElementById('latest-time').innerText = aqiLastUpdateTimeStr;
        } else {
            if (aqiBanner) aqiBanner.style.display = 'none';
            const aqiLiveBadge = document.getElementById('aqi-live-badge');
            if (aqiLiveBadge) {
                aqiLiveBadge.style.backgroundColor = '#ecfdf5';
                aqiLiveBadge.style.color = '#059669';
                aqiLiveBadge.style.borderColor = '#a7f3d0';
                aqiLiveBadge.innerHTML = `<span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10b981;"></span> LIVE`;
            }
            document.getElementById('latest-time').innerText = aqiLastUpdateTimeStr;
        }

        // --- Weather Offline Check ---
        let isWeatherOffline = true;
        let weatherOfflineMinutes = 0;
        let weatherLastUpdateTimeStr = "Unknown";

        if (weatherData && weatherData.timestamp) {
            const wLastUpdate = new Date(weatherData.timestamp);
            const wDiffMs = now - wLastUpdate;
            weatherOfflineMinutes = Math.floor(wDiffMs / 60000);
            isWeatherOffline = weatherOfflineMinutes > 15;
            weatherLastUpdateTimeStr = wLastUpdate.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
        }

        const weatherBanner = document.getElementById('weather-offline-banner');
        const weatherLatestTime = document.getElementById('weather-latest-time');
        
        if (isWeatherOffline) {
            if (weatherBanner) {
                document.getElementById('weather-offline-last-update').innerText = weatherLastUpdateTimeStr + ' • ' + weatherOfflineMinutes + 'm ago';
                document.getElementById('weather-offline-badge-text').innerText = 'OFFLINE (' + weatherOfflineMinutes + 'M AGO)';
                weatherBanner.style.display = 'flex';
            }
            const weatherLiveBadge = document.getElementById('weather-live-badge');
            if (weatherLiveBadge) {
                weatherLiveBadge.style.backgroundColor = '#fff7ed';
                weatherLiveBadge.style.color = '#ea580c';
                weatherLiveBadge.style.borderColor = '#fed7aa';
                weatherLiveBadge.innerHTML = `<span style="width: 6px; height: 6px; border-radius: 50%; background-color: #f97316;"></span> OFFLINE (${weatherOfflineMinutes}m ago)`;
            }
            if(weatherLatestTime) weatherLatestTime.innerText = weatherLastUpdateTimeStr;
        } else {
            if (weatherBanner) weatherBanner.style.display = 'none';
            const weatherLiveBadge = document.getElementById('weather-live-badge');
            if (weatherLiveBadge) {
                weatherLiveBadge.style.backgroundColor = '#ecfdf5';
                weatherLiveBadge.style.color = '#059669';
                weatherLiveBadge.style.borderColor = '#a7f3d0';
                weatherLiveBadge.innerHTML = `<span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10b981;"></span> LIVE`;
            }
            if(weatherLatestTime) weatherLatestTime.innerText = weatherLastUpdateTimeStr;
        }
        lucide.createIcons();
    } catch (e) {
        console.error("Live page error:", e);
    }
}

let autoRefreshInterval = null;
let currentTabIsAqi = true;

setTimeout(() => {
    refreshDashboard();
    
    // Auto-refresh every 15 seconds (but do NOT switch tabs automatically)
    autoRefreshInterval = setInterval(() => {
        refreshDashboard();
    }, 15000);
}, 0);

const pollutantDetails = {
    pm25: {
        id: 'pm25', title: 'Particulate Matter 2.5 (PM2.5)', icon: '🌫️', limit: 15, unit: 'µg/m³',
        scale: [30, 60, 90, 120, 250],
        desc: 'Fine inhalable particles with diameters 2.5 micrometers or smaller (about 30 times smaller than a human hair). Due to their minute size, they penetrate deep into lung alveoli and enter the circulatory system.',
        health: 'Causes respiratory inflammation, triggers asthma attacks, increases risk of heart disease, stroke, and lung cancer. Short-term exposure causes eye, nose, and throat irritation.'
    },
    pm10: {
        id: 'pm10', title: 'Particulate Matter 10 (PM10)', icon: '☁️', limit: 45, unit: 'µg/m³',
        scale: [50, 100, 250, 350, 430],
        desc: 'Inhalable particles with diameters that are generally 10 micrometers and smaller. Sources include crushing or grinding operations and dust stirred up by vehicles on roads.',
        health: 'Can irritate the eyes, nose, and throat. Reduces lung function, exacerbates asthma and other respiratory diseases, and increases susceptibility to respiratory infections.'
    },
    co: {
        id: 'co', title: 'Carbon Monoxide (CO)', icon: '💨', limit: 4, unit: 'mg/m³',
        scale: [1, 2, 10, 17, 34],
        desc: 'A colorless, odorless gas that can be harmful when inhaled in large amounts. CO is released when something is burned, primarily from vehicle exhaust.',
        health: 'Reduces oxygen delivery to the body\'s organs (like the heart and brain). Can cause headaches, dizziness, vomiting, and nausea. High levels can cause unconsciousness or death.'
    },
    no2: {
        id: 'no2', title: 'Nitrogen Dioxide (NO₂)', icon: '🏭', limit: 25, unit: 'µg/m³',
        scale: [40, 80, 180, 280, 400],
        desc: 'A highly reactive gas that forms quickly from emissions from cars, trucks and buses, power plants, and off-road equipment.',
        health: 'Irritates airways in the human respiratory system. Aggravates respiratory diseases, particularly asthma, leading to coughing, wheezing or difficulty breathing.'
    },
    o3: {
        id: 'o3', title: 'Ground-level Ozone (O₃)', icon: '☀️', limit: 100, unit: 'µg/m³',
        scale: [50, 100, 168, 208, 748],
        desc: 'Not emitted directly into the air, but created by chemical reactions between oxides of nitrogen (NOx) and volatile organic compounds (VOC) in the presence of sunlight.',
        health: 'Can trigger a variety of health problems including chest pain, coughing, throat irritation, and airway inflammation. It can worsen bronchitis, emphysema, and asthma.'
    }
};

function openPollutantModal(type) {
    const data = pollutantDetails[type];
    if(!data) return;
    
    document.getElementById('pm-modal-icon-bg').innerText = data.icon;
    document.getElementById('pm-modal-title').innerText = data.title;
    document.getElementById('pm-modal-limit').innerText = `${data.limit} ${data.unit}`;
    
    const currentValStr = document.getElementById(`card-${type}`).innerText;
    const currentVal = parseFloat(currentValStr);
    
    document.getElementById('pm-modal-val').innerText = isNaN(currentVal) ? '--' : currentVal.toFixed(2);
    document.getElementById('pm-modal-unit').innerText = data.unit;
    document.getElementById('pm-modal-short').innerText = data.id.toUpperCase();
    document.getElementById('pm-modal-q').innerText = data.id.toUpperCase();
    document.getElementById('pm-modal-desc').innerText = data.desc;
    document.getElementById('pm-modal-health').innerText = data.health;
    
    document.getElementById('pm-t1').innerText = data.scale[0];
    document.getElementById('pm-t2').innerText = data.scale[1];
    document.getElementById('pm-t3').innerText = data.scale[2];
    document.getElementById('pm-t4').innerText = data.scale[3];
    document.getElementById('pm-t5').innerText = data.scale[4] + '+';
    
    const statusBox = document.getElementById('pm-modal-status');
    if(isNaN(currentVal)) {
        statusBox.style.display = 'none';
    } else {
        statusBox.style.display = 'flex';
        if(currentVal <= data.limit) {
            statusBox.innerHTML = '<i data-lucide="check-circle-2" style="width: 18px; height: 18px;"></i> Within Safe Limit';
            statusBox.style.background = '#dcfce7';
            statusBox.style.color = '#16a34a';
        } else {
            statusBox.innerHTML = '<i data-lucide="alert-triangle" style="width: 18px; height: 18px;"></i> Exceeds Safe Limit';
            statusBox.style.background = '#fee2e2';
            statusBox.style.color = '#dc2626';
        }
    }
    
    const marker = document.getElementById('pm-modal-marker');
    let pct = 0;
    let color = '#22c55e';
    if(!isNaN(currentVal)) {
        if(currentVal <= data.scale[0]) {
            pct = (currentVal / data.scale[0]) * (100/6);
            color = '#22c55e';
        } else if(currentVal <= data.scale[1]) {
            pct = (100/6) + ((currentVal - data.scale[0]) / (data.scale[1] - data.scale[0])) * (100/6);
            color = '#eab308';
        } else if(currentVal <= data.scale[2]) {
            pct = (200/6) + ((currentVal - data.scale[1]) / (data.scale[2] - data.scale[1])) * (100/6);
            color = '#00bfa5';
        } else if(currentVal <= data.scale[3]) {
            pct = (300/6) + ((currentVal - data.scale[2]) / (data.scale[3] - data.scale[2])) * (100/6);
            color = '#ef4444';
        } else if(currentVal <= data.scale[4]) {
            pct = (400/6) + ((currentVal - data.scale[3]) / (data.scale[4] - data.scale[3])) * (100/6);
            color = '#a855f7';
        } else {
            pct = (500/6) + Math.min((100/6), ((currentVal - data.scale[4]) / data.scale[4]) * (100/6));
            color = '#f43f5e';
        }
    }
    marker.style.left = `${pct}%`;
    marker.style.borderColor = color;
    
    document.getElementById('pollutant-modal').style.display = 'flex';
    lucide.createIcons();
}

function closePollutantModal() {
    document.getElementById('pollutant-modal').style.display = 'none';
}
</script>

<!-- Pollutant Detail Modal -->
<div id="pollutant-modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 20px; animation: fade-in 0.2s ease-out;">
  <div style="background: #ffffff; width: 100%; max-width: 600px; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); position: relative; overflow: hidden; display: flex; flex-direction: column;">
    <button onclick="closePollutantModal()" style="position: absolute; top: 24px; right: 24px; width: 32px; height: 32px; border-radius: 50%; border: 1px solid #e2e8f0; background: #ffffff; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; transition: all 0.2s ease;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'">
      <i data-lucide="x" style="width: 16px; height: 16px;"></i>
    </button>
    
    <div style="padding: 32px; overflow-y: auto; max-height: 85vh;">
        <!-- Header -->
        <div style="display: flex; gap: 16px; align-items: flex-start; margin-bottom: 24px;">
            <div id="pm-modal-icon-bg" style="width: 56px; height: 56px; border-radius: 16px; background: #f0fdf4; display: flex; align-items: center; justify-content: center; font-size: 28px; flex-shrink: 0; box-shadow: inset 0 2px 4px rgba(255,255,255,0.5);">🌫️</div>
            <div>
                <h2 id="pm-modal-title" style="margin: 0 0 4px 0; font-size: 22px; font-weight: 700; color: #0f172a; font-family: var(--font-sans);">Particulate Matter 2.5 (PM2.5)</h2>
                <div style="font-size: 14px; font-weight: 600; color: #64748b;">WHO Safe Limit: <span id="pm-modal-limit" style="color: #00bfa5;">15 µg/m³</span></div>
            </div>
        </div>

        <!-- Current Reading Box -->
        <div style="border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); background: #ffffff;">
            <div>
                <div style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">CURRENT READING</div>
                <div style="display: flex; align-items: baseline; gap: 8px;">
                    <div id="pm-modal-val" style="font-size: 42px; font-weight: 900; color: #0f172a; line-height: 1; font-family: var(--font-mono);">7.09</div>
                    <div id="pm-modal-unit" style="font-size: 24px; font-weight: 700; color: #0f172a; font-family: var(--font-mono);">µg/m³</div>
                </div>
            </div>
            <div id="pm-modal-status" style="display: flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; background: #dcfce7; color: #16a34a; font-size: 14px; font-weight: 700;">
                <i data-lucide="check-circle-2" style="width: 18px; height: 18px;"></i> Within Safe Limit
            </div>
        </div>

        <!-- Concentration Scale Box -->
        <div style="border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; margin-bottom: 32px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); background: #ffffff;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div style="font-size: 12px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; font-family: var(--font-mono);"><span id="pm-modal-short">PM2.5</span> CONCENTRATION SCALE</div>
                <div style="font-size: 12px; font-weight: 700; color: #00bfa5;">Real-time Level</div>
            </div>
            
            <div style="position: relative; width: 100%;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 11px; font-weight: 700;">
                    <div style="color: #22c55e; flex: 1;">Good</div>
                    <div style="color: #eab308; flex: 1;">Moderate</div>
                    <div style="color: #f97316; flex: 1;">Poor</div>
                    <div style="color: #ef4444; flex: 1;">Unhealthy</div>
                    <div style="color: #a855f7; flex: 1;">Severe</div>
                    <div style="color: #f43f5e; flex: 1; text-align: right;">Hazardous</div>
                </div>
                
                <div style="height: 12px; border-radius: 999px; display: flex; overflow: hidden; margin-bottom: 8px;">
                    <div style="flex: 1; background: #22c55e;"></div>
                    <div style="flex: 1; background: #eab308;"></div>
                    <div style="flex: 1; background: #00bfa5;"></div>
                    <div style="flex: 1; background: #ef4444;"></div>
                    <div style="flex: 1; background: #a855f7;"></div>
                    <div style="flex: 1; background: #f43f5e;"></div>
                </div>
                
                <div id="pm-modal-marker" style="position: absolute; top: 19px; left: 10%; transform: translateX(-50%); width: 20px; height: 20px; border-radius: 50%; background: #ffffff; border: 3px solid #22c55e; box-shadow: 0 2px 4px rgba(0,0,0,0.2); transition: left 0.4s ease, border-color 0.4s ease;"></div>
                
                <div id="pm-modal-ticks" style="display: flex; justify-content: space-between; font-size: 10px; font-weight: 600; color: #64748b; font-family: var(--font-mono);">
                    <div style="flex: 1;">0</div>
                    <div style="flex: 1;" id="pm-t1">30</div>
                    <div style="flex: 1;" id="pm-t2">60</div>
                    <div style="flex: 1;" id="pm-t3">90</div>
                    <div style="flex: 1;" id="pm-t4">120</div>
                    <div style="flex: 1; text-align: right;" id="pm-t5">250+</div>
                </div>
            </div>
        </div>

        <!-- Info sections -->
        <div style="margin-bottom: 24px; border-top: 1px solid #f1f5f9; padding-top: 24px;">
            <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="info" style="width: 16px; height: 16px; color: #00bfa5;"></i> What is <span id="pm-modal-q">PM2.5</span>?
            </h3>
            <p id="pm-modal-desc" style="font-size: 14px; color: #475569; line-height: 1.6; margin: 0;">
                Fine inhalable particles...
            </p>
        </div>

        <div>
            <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="heart-pulse" style="width: 16px; height: 16px; color: #ef4444;"></i> Health Impact & Symptoms
            </h3>
            <p id="pm-modal-health" style="font-size: 14px; color: #475569; line-height: 1.6; margin: 0;">
                Causes respiratory inflammation...
            </p>
        </div>

    </div>
  </div>
</div>
