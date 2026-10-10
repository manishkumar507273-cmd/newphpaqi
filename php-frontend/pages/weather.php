<div id="weather-view" style="display: none; animation: fade-in 0.4s ease-out; width: 100%;">
  


  <!-- Titles -->
  <div>
    <h1 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0; letter-spacing: -0.02em;">Weather Conditions</h1>
    <div style="font-size: 12.5px; color: #64748b; margin-top: 6px; font-family: var(--font-mono); display: flex; align-items: center; gap: 8px;">
      <span>Last Updated: <span id="weather-latest-time" style="color: #00bfa5; font-weight: 600;">Syncing...</span></span>
      <span style="display: inline-flex; align-items: center; gap: 5px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; background-color: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;">
        <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10b981;"></span> LIVE
      </span>
    </div>
  </div>

  <!-- Hero Container -->
  <div style="background-color: #ffffff; border-radius: 24px; padding: 32px 36px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05); margin-top: 24px; display: flex; flex-wrap: wrap; gap: 32px; justify-content: space-between; align-items: center;">
    
    <div style="flex: 1.2; min-width: 320px;">
      <div id="weather-date-str" style="font-size: 13px; color: #64748b; margin-bottom: 12px; font-weight: 600;">--</div>
      <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 14px;">
        <span id="weather-temp-icon" style="font-size: 64px; line-height: 1;">☀️</span>
        <div style="display: flex; align-items: baseline; gap: 4px;">
          <span id="weather-hero-temp" style="font-family: var(--font-mono); font-size: 76px; font-weight: 800; line-height: 1; letter-spacing: -0.04em; color: #0f172a;">--</span>
          <span style="font-family: var(--font-mono); font-size: 32px; font-weight: 700; color: #64748b;">°C</span>
        </div>
      </div>
      <div style="display: flex; align-items: center; gap: 10px;">
        <span id="weather-hero-label" style="padding: 6px 16px; border-radius: 999px; background-color: #e0f2fe; color: #0284c7; font-size: 14px; font-weight: 700;">--</span>
        <span id="weather-hero-aqi-label" style="padding: 6px 16px; border-radius: 999px; background-color: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; font-size: 14px; font-weight: 700;">AQI -- · --</span>
      </div>
    </div>

    <!-- Today's Temp Range (Middle Section) -->
    <div style="flex: 1; min-width: 260px; display: flex; flex-direction: column; align-items: center; justify-content: center; border-left: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9; padding: 0 24px;">
      <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 16px;">
        Today's Temp Range
      </div>
      <div style="display: flex; align-items: center; gap: 24px; text-align: center;">
        <div>
          <div id="weather-low-temp" style="font-family: var(--font-mono); font-size: 24px; font-weight: 800; color: #0ea5e9;">--°</div>
          <div style="font-size: 11px; font-weight: 700; color: #94a3b8; margin-top: 4px;">LOW</div>
        </div>
        <div>
          <div id="weather-avg-temp" style="font-family: var(--font-mono); font-size: 24px; font-weight: 800; color: #22c55e;">--°</div>
          <div style="font-size: 11px; font-weight: 700; color: #94a3b8; margin-top: 4px;">AVG</div>
        </div>
        <div>
          <div id="weather-high-temp" style="font-family: var(--font-mono); font-size: 24px; font-weight: 800; color: #f97316;">--°</div>
          <div style="font-size: 11px; font-weight: 700; color: #94a3b8; margin-top: 4px;">HIGH</div>
        </div>
      </div>
    </div>

    <!-- Live Sensor Feed Mini-Grid -->
    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px 24px; flex: 1.5; min-width: 320px;">
      <div style="font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 10px; display: flex; justify-content: space-between;">
        <span>Live Sensor Feed</span>
        <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #10b981; display: inline-block;"></span>
      </div>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; font-size: 12px; color: #0f172a;">
        <div style="padding: 6px 10px; border-radius: 10px; background-color: #ffffff; border: 1px solid #e2e8f0; display: flex; gap: 6px;">
          <span>🌡️</span><span>Temp: <strong id="feed-temp" style="font-family: var(--font-mono); color: #ea580c;">--</strong></span>
        </div>
        <div style="padding: 6px 10px; border-radius: 10px; background-color: #ffffff; border: 1px solid #e2e8f0; display: flex; gap: 6px;">
          <span>💧</span><span>Humidity: <strong id="feed-hum" style="font-family: var(--font-mono); color: #0284c7;">--</strong></span>
        </div>
        <div style="padding: 6px 10px; border-radius: 10px; background-color: #ffffff; border: 1px solid #e2e8f0; display: flex; gap: 6px;">
          <span>💨</span><span>Wind: <strong id="feed-wind" style="font-family: var(--font-mono); color: #6366f1;">--</strong></span>
        </div>
        <div style="padding: 6px 10px; border-radius: 10px; background-color: #ffffff; border: 1px solid #e2e8f0; display: flex; gap: 6px;">
          <span>🌪️</span><span>Gust: <strong id="feed-gust" style="font-family: var(--font-mono); color: #8b5cf6;">--</strong></span>
        </div>
        <div style="padding: 6px 10px; border-radius: 10px; background-color: #ffffff; border: 1px solid #e2e8f0; display: flex; gap: 6px;">
          <span>🌧️</span><span>Rain: <strong id="feed-rain" style="font-family: var(--font-mono); color: #0891b2;">--</strong></span>
        </div>
        <div style="padding: 6px 10px; border-radius: 10px; background-color: #ffffff; border: 1px solid #e2e8f0; display: flex; gap: 6px;">
          <span>🧭</span><span>Dir: <strong id="feed-dir" style="font-family: var(--font-mono); color: #475569;">--</strong></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Weather Cards Grid -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-top: 24px;">
    
    <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; display: flex; flex-direction: column; gap: 16px; box-shadow: 0 4px 16px rgba(15,23,42,0.04);">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 38px; height: 38px; border-radius: 10px; background-color: #ffedd5; color: #ea580c; display: flex; align-items: center; justify-content: center; font-size: 20px;">🌡️</div>
        <div>
          <div style="font-size: 14.5px; font-weight: 700; color: #0f172a;">Temperature</div>
          <div style="font-size: 10.5px; color: #64748b;">Live Telemetry</div>
        </div>
      </div>
      <div id="card-temp" style="font-family: var(--font-mono); font-size: 21px; font-weight: 800; color: #ea580c;">--</div>
    </div>

    <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; display: flex; flex-direction: column; gap: 16px; box-shadow: 0 4px 16px rgba(15,23,42,0.04);">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 38px; height: 38px; border-radius: 10px; background-color: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 20px;">💧</div>
        <div>
          <div style="font-size: 14.5px; font-weight: 700; color: #0f172a;">Humidity</div>
          <div style="font-size: 10.5px; color: #64748b;">Live Telemetry</div>
        </div>
      </div>
      <div id="card-hum" style="font-family: var(--font-mono); font-size: 21px; font-weight: 800; color: #0284c7;">--</div>
    </div>

    <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; display: flex; flex-direction: column; gap: 16px; box-shadow: 0 4px 16px rgba(15,23,42,0.04);">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 38px; height: 38px; border-radius: 10px; background-color: #e0e7ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-size: 20px;">💨</div>
        <div>
          <div style="font-size: 14.5px; font-weight: 700; color: #0f172a;">Wind Speed</div>
          <div style="font-size: 10.5px; color: #64748b;">Live Telemetry</div>
        </div>
      </div>
      <div id="card-wind" style="font-family: var(--font-mono); font-size: 21px; font-weight: 800; color: #4f46e5;">--</div>
    </div>

    <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; display: flex; flex-direction: column; gap: 16px; box-shadow: 0 4px 16px rgba(15,23,42,0.04);">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 38px; height: 38px; border-radius: 10px; background-color: #f5f3ff; color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 20px;">🌪️</div>
        <div>
          <div style="font-size: 14.5px; font-weight: 700; color: #0f172a;">Wind Gust</div>
          <div style="font-size: 10.5px; color: #64748b;">Live Telemetry</div>
        </div>
      </div>
      <div id="card-gust" style="font-family: var(--font-mono); font-size: 21px; font-weight: 800; color: #8b5cf6;">--</div>
    </div>

    <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; display: flex; flex-direction: column; gap: 16px; box-shadow: 0 4px 16px rgba(15,23,42,0.04);">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 38px; height: 38px; border-radius: 10px; background-color: #cff4fc; color: #0891b2; display: flex; align-items: center; justify-content: center; font-size: 20px;">🌧️</div>
        <div>
          <div style="font-size: 14.5px; font-weight: 700; color: #0f172a;">Rainfall</div>
          <div style="font-size: 10.5px; color: #64748b;">Live Telemetry</div>
        </div>
      </div>
      <div id="card-rain" style="font-family: var(--font-mono); font-size: 21px; font-weight: 800; color: #0891b2;">--</div>
    </div>

    <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; display: flex; flex-direction: column; gap: 16px; box-shadow: 0 4px 16px rgba(15,23,42,0.04);">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 38px; height: 38px; border-radius: 10px; background-color: #f1f5f9; color: #475569; display: flex; align-items: center; justify-content: center; font-size: 20px;">🧭</div>
        <div>
          <div style="font-size: 14.5px; font-weight: 700; color: #0f172a;">Wind Direction</div>
          <div style="font-size: 10.5px; color: #64748b;">Live Telemetry</div>
        </div>
      </div>
      <div id="card-dir" style="font-family: var(--font-sans); font-size: 21px; font-weight: 800; color: #475569;">--</div>
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
      <div style="overflow-x: auto; max-height: 420px;">
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
