<?php
// Retrieve cached bulk telemetry for 0ms instant first-paint on both desktop and mobile
$cache_file = sys_get_temp_dir() . '/smartairnet_cache/' . md5('overview_bulk') . '.json';
$init_bulk = null;
if (file_exists($cache_file)) {
    $raw_cache = @file_get_contents($cache_file);
    if ($raw_cache && strlen($raw_cache) > 20) {
        $init_bulk = @json_decode($raw_cache, true);
    }
}

// AQI pre-calculated fields matching the exact 6 tiers
$init_aqi_val = '--';
$init_aqi_label = 'Good';
$init_aqi_accent = '#22c55e';
$init_aqi_bg = '#ecfdf5';
$init_aqi_border = 'rgba(34, 197, 94, 0.4)';
$init_aqi_shadow = 'rgba(34, 197, 94, 0.18)';
$init_scale_pct = '8px';
$init_pm25 = '-- µg/m³';
$init_pm10 = '-- µg/m³';

if (!empty($init_bulk['aqi'])) {
    $aqi_d = $init_bulk['aqi'];
    if (isset($aqi_d['cpcb_aqi'])) {
        $v = (int)round($aqi_d['cpcb_aqi']);
        $init_aqi_val = (string)$v;
        if ($v <= 50) {
            $init_aqi_label = 'Good';
            $init_aqi_accent = '#22c55e';
            $init_aqi_bg = '#ecfdf5';
            $init_aqi_border = 'rgba(34, 197, 94, 0.4)';
            $init_aqi_shadow = 'rgba(34, 197, 94, 0.18)';
            $init_scale_pct = (4 + ($v / 50) * 9) . '%';
        } elseif ($v <= 100) {
            $init_aqi_label = 'Moderate';
            $init_aqi_accent = '#eab308';
            $init_aqi_bg = '#fef9c3';
            $init_aqi_border = 'rgba(234, 179, 8, 0.45)';
            $init_aqi_shadow = 'rgba(234, 179, 8, 0.22)';
            $init_scale_pct = (21 + (($v - 50) / 50) * 8) . '%';
        } elseif ($v <= 150) {
            $init_aqi_label = 'Poor';
            $init_aqi_accent = '#f97316';
            $init_aqi_bg = '#ffedd5';
            $init_aqi_border = 'rgba(249, 115, 22, 0.45)';
            $init_aqi_shadow = 'rgba(249, 115, 22, 0.22)';
            $init_scale_pct = (37 + (($v - 100) / 50) * 9) . '%';
        } elseif ($v <= 200) {
            $init_aqi_label = 'Unhealthy';
            $init_aqi_accent = '#ef4444';
            $init_aqi_bg = '#fee2e2';
            $init_aqi_border = 'rgba(239, 68, 68, 0.45)';
            $init_aqi_shadow = 'rgba(239, 68, 68, 0.22)';
            $init_scale_pct = (54 + (($v - 150) / 50) * 9) . '%';
        } elseif ($v <= 300) {
            $init_aqi_label = 'Severe';
            $init_aqi_accent = '#a855f7';
            $init_aqi_bg = '#f3e8ff';
            $init_aqi_border = 'rgba(168, 85, 247, 0.45)';
            $init_aqi_shadow = 'rgba(168, 85, 247, 0.22)';
            $init_scale_pct = (71 + (($v - 200) / 100) * 8) . '%';
        } else {
            $init_aqi_label = 'Hazardous';
            $init_aqi_accent = '#f43f5e';
            $init_aqi_bg = '#ffe4e6';
            $init_aqi_border = 'rgba(244, 63, 94, 0.45)';
            $init_aqi_shadow = 'rgba(244, 63, 94, 0.22)';
            $init_scale_pct = (87 + min(($v - 300) / 200, 1) * 9) . '%';
        }
        if (isset($aqi_d['pm25'])) $init_pm25 = number_format((float)$aqi_d['pm25'], 3) . ' µg/m³';
        if (isset($aqi_d['pm10'])) $init_pm10 = number_format((float)$aqi_d['pm10'], 3) . ' µg/m³';
    }
}
?>
<style>
@media (max-width: 768px) {
  /* Mobile compact layout for Live Telemetry */
  #hero-card {
    padding: 10px 12px !important;
    border-radius: 14px !important;
    margin-bottom: 8px !important;
  }
  #hero-card [style*="margin-bottom: 12px"] {
    margin-bottom: 4px !important;
  }
  #hero-card [style*="margin-bottom: 20px"] {
    margin-bottom: 6px !important;
    gap: 12px !important;
  }
  #hero-aqi-val {
    font-size: 32px !important;
    line-height: 1 !important;
  }
  #hero-aqi-subtext {
    font-size: 8.5px !important;
    margin-top: 2px !important;
  }
  #hero-aqi-badge {
    padding: 3px 10px !important;
    font-size: 11px !important;
  }
  #hero-card [style*="margin-bottom: 14px"] {
    margin-bottom: 6px !important;
    gap: 6px !important;
  }
  #hero-card [style*="margin-bottom: 14px"] > div {
    padding: 2px 7px !important;
    font-size: 10px !important;
    border-radius: 6px !important;
  }
  #hero-pm25, #hero-pm10 {
    font-size: 10.5px !important;
  }
  /* Scale Bar Layout & Overlap Prevention */
  .hero-scale-labels {
    display: grid !important;
    grid-template-columns: repeat(6, 1fr) !important;
    margin-bottom: 12px !important;
    padding: 0 !important;
    gap: 1px !important;
  }
  .hero-scale-labels .scale-label {
    font-size: 8px !important;
    font-weight: 700 !important;
    letter-spacing: -0.02em !important;
    line-height: 1 !important;
    white-space: nowrap !important;
  }
  .hero-scale-bar-track {
    height: 8px !important;
    margin-bottom: 10px !important;
  }
  #hero-scale-indicator {
    width: 14px !important;
    height: 14px !important;
    border-width: 2.5px !important;
  }
  .hero-scale-ticks span {
    font-size: 8.5px !important;
  }
  @media (max-width: 380px) {
    .hero-scale-labels .scale-label {
      font-size: 7.2px !important;
      letter-spacing: -0.03em !important;
    }
    .hero-scale-ticks span {
      font-size: 7.5px !important;
    }
  }
  
  /* Pollutants Grid Mobile Refinements */
  .pollutants-grid-responsive {
    gap: 6px !important;
  }
  .pollutant-card {
    padding: 7px 8px !important;
    border-radius: 12px !important;
    gap: 4px !important;
    min-height: 68px !important;
  }
  .pollutant-card .pollutant-icon-box {
    width: 24px !important;
    height: 24px !important;
    font-size: 11px !important;
    border-radius: 6px !important;
  }
  .pollutant-card .pollutant-name {
    font-size: 11.5px !important;
    line-height: 1.1 !important;
  }
  .pollutant-card .pollutant-who {
    font-size: 8px !important;
    margin-top: 0px !important;
  }
  .pollutant-card .pollutant-info-icon {
    width: 16px !important;
    height: 16px !important;
  }
  .pollutant-card .pollutant-info-icon i,
  .pollutant-card .pollutant-info-icon svg {
    width: 9px !important;
    height: 9px !important;
  }
  .pollutant-card [id^="card-"] {
    font-size: 15px !important;
    font-weight: 800 !important;
    line-height: 1 !important;
  }
  .pollutant-card .pollutant-unit {
    font-size: 8px !important;
    margin-top: 1px !important;
  }
  .pollutant-card [id^="tag-"] {
    padding: 1.5px 5px !important;
    font-size: 8.5px !important;
    border-radius: 999px !important;
  }
  .pollutant-card [id^="tag-"] span.w-1\.5 {
    width: 4px !important;
    height: 4px !important;
  }
  
  .page-heading {
    font-size: 18px !important;
  }
}

/* Live Tab Selector Responsive (Compact on Mobile) */
.live-tab-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 6px;
  width: 100%;
}
.live-tab-container {
  display: inline-flex;
  align-items: center;
  background-color: #ffffff;
  padding: 3px;
  border-radius: 999px;
  border: 1px solid #cbd5e1;
  box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
  gap: 3px;
  width: 100%;
  max-width: 320px;
}
.live-tab-btn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  padding: 5px 12px;
  border-radius: 999px;
  border: none;
  font-size: 11.5px;
  font-weight: 700;
  font-family: var(--font-sans);
  cursor: pointer;
  transition: all 0.15s ease;
  min-height: 33px;
  flex: 1;
  white-space: nowrap;
}
.live-tab-btn i, .live-tab-btn svg {
  width: 13.5px;
  height: 13.5px;
  flex-shrink: 0;
}
.live-tab-btn.live-tab-active {
  background-color: #00bfa5;
  color: #ffffff;
}
.live-tab-btn.live-tab-inactive {
  background-color: transparent;
  color: #64748b;
}

@media (min-width: 641px) {
  .live-tab-container {
    max-width: none;
    width: auto;
    padding: 4px;
    gap: 4px;
  }
  .live-tab-btn {
    padding: 8px 20px;
    font-size: 13px;
    min-height: 40px;
    gap: 7px;
    flex: initial;
  }
  .live-tab-btn i, .live-tab-btn svg {
    width: 15px;
    height: 15px;
  }
}
</style>

<div style="display: flex; flex-direction: column; gap: 14px; max-width: 1200px; margin: 0 auto; animation: fade-in 0.4s ease-out;" class="p-2.5 sm:p-6">

  <!-- Sub-Navigation Selector (Compact Mobile Responsive) -->
  <div class="live-tab-wrapper">
    <div class="live-tab-container">
      <button id="tab-aqi" onclick="switchLiveTab('aqi')" class="live-tab-btn live-tab-active" style="background-color: #00bfa5; color: #ffffff;">
        <i data-lucide="activity"></i>
        <span>AQI Telemetry</span>
      </button>
      <button id="tab-weather" onclick="switchLiveTab('weather')" class="live-tab-btn live-tab-inactive" style="background-color: transparent; color: #64748b;">
        <i data-lucide="cloud-sun"></i>
        <span>Weather Telemetry</span>
      </button>
    </div>
  </div>

<div id="aqi-view" style="display: block; animation: fade-in 0.4s ease-out;">

<!-- Offline Banner -->
<div id="aqi-offline-banner" style="display: none; background-color: #fff7ed; border: 1px solid #fed7aa; border-left: 4px solid #f97316; border-radius: 12px; padding: 12px 16px; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
  <div style="display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: #475569;">
    <i data-lucide="alert-triangle" style="width: 16px; height: 16px; color: #f97316; margin-top: 2px; flex-shrink: 0;"></i>
    <span><strong style="color: #c2410c;">AQI Sensor Node Offline:</strong> No new live telemetry received in cloud for >5 mins (Last update: <strong style="color: #f97316;" id="aqi-offline-last-update">--</strong>). Showing last recorded cloud values below.</span>
  </div>
  <div id="aqi-offline-badge-text" style="background-color: #ffedd5; color: #c2410c; font-size: 10px; font-weight: 800; padding: 4px 10px; border-radius: 999px; letter-spacing: 0.05em; font-family: var(--font-mono); white-space: nowrap;">OFFLINE</div>
</div>

  <!-- Page Header Titles -->
  <div>
    <h1 class="page-heading" style="font-size: 24px; font-weight: 700; color: #0f172a; font-family: var(--font-sans); letter-spacing: -0.02em; margin: 0;">
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
  <div id="hero-card" class="hero-card-responsive" style="background-color: <?= $init_aqi_bg ?>; border-radius: 24px; padding: 28px 32px; color: #0f172a; border: 1.5px solid <?= $init_aqi_border ?>; box-shadow: 0 8px 30px <?= $init_aqi_shadow ?>, 0 1px 3px rgba(15,23,42,0.04); position: relative; overflow: hidden; transition: background-color 0.5s ease, border-color 0.5s ease, box-shadow 0.5s ease;">
    <div id="hero-left-bar" style="position: absolute; top: 0; left: 0; bottom: 0; width: 6px; background-color: <?= $init_aqi_accent ?>;"></div>

    <div style="position: relative; z-index: 1;">
      <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 12px;">
        <span id="status-dot" style="width: 8px; height: 8px; border-radius: 50%; background-color: <?= $init_aqi_accent ?>; display: inline-block;"></span>
        <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">
          <span>Status Panel • </span><span id="hero-stream-text">Live Stream</span>
        </span>
      </div>

      <div style="display: flex; align-items: center; gap: 24px; margin-bottom: 20px; flex-wrap: wrap;">
        <div>
          <div id="hero-aqi-val" style="font-family: var(--font-mono); font-size: 72px; font-weight: 800; line-height: 1; letter-spacing: -0.04em; color: #0f172a;">
            <?= $init_aqi_val ?>
          </div>
          <div id="hero-aqi-subtext" style="font-size: 11px; font-weight: 600; color: #64748b; margin-top: 4px; letter-spacing: 0.06em; text-transform: uppercase; font-family: var(--font-mono);">
            LIVE AQI
          </div>
        </div>

        <div>
          <div style="font-size: 12px; color: #64748b; margin-bottom: 6px;">Air Quality is</div>
          <div id="hero-aqi-badge" style="display: inline-flex; align-items: center; gap: 7px; padding: 6px 18px; border-radius: 999px; background-color: #ffffff; border: 1.5px solid <?= $init_aqi_accent ?>60; color: #0f172a; font-size: 16px; font-weight: 800; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);">
            <span id="hero-aqi-badge-dot" style="width: 8px; height: 8px; border-radius: 50%; background-color: <?= $init_aqi_accent ?>;"></span>
            <span id="hero-aqi-label"><?= $init_aqi_label ?></span>
          </div>
        </div>
      </div>

      <!-- Quick PM2.5 & PM10 Summary -->
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 8px; background-color: #ffffff; border: 1px solid rgba(0, 0, 0, 0.07); font-size: 12px; color: #334155; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
          <span style="color: #64748b; font-weight: 600;">PM₂.₅:</span>
          <strong id="hero-pm25" style="color: #0f172a; font-family: var(--font-mono);"><?= $init_pm25 ?></strong>
        </div>
        <div style="display: flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 8px; background-color: #ffffff; border: 1px solid rgba(0, 0, 0, 0.07); font-size: 12px; color: #334155; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
          <span style="color: #64748b; font-weight: 600;">PM₁₀:</span>
          <strong id="hero-pm10" style="color: #0f172a; font-family: var(--font-mono);"><?= $init_pm10 ?></strong>
        </div>
      </div>

      <!-- Overall AQI Scale Bar -->
      <div class="hero-scale-bar-container" style="max-width: 400px; width: 100%;">
        <div class="hero-scale-labels" style="display: grid; grid-template-columns: repeat(6, 1fr); padding: 0 1px; margin-bottom: 12px; gap: 2px;">
          <span class="scale-label" style="font-size: 9.5px; font-weight: 700; color: #22c55e; line-height: 1.1; text-align: left; white-space: nowrap;">Good</span>
          <span class="scale-label" style="font-size: 9.5px; font-weight: 700; color: #eab308; line-height: 1.1; text-align: center; white-space: nowrap;">Moderate</span>
          <span class="scale-label" style="font-size: 9.5px; font-weight: 700; color: #f97316; line-height: 1.1; text-align: center; white-space: nowrap;">Poor</span>
          <span class="scale-label" style="font-size: 9.5px; font-weight: 700; color: #ef4444; line-height: 1.1; text-align: center; white-space: nowrap;">Unhealthy</span>
          <span class="scale-label" style="font-size: 9.5px; font-weight: 700; color: #a855f7; line-height: 1.1; text-align: center; white-space: nowrap;">Severe</span>
          <span class="scale-label" style="font-size: 9.5px; font-weight: 700; color: #f43f5e; line-height: 1.1; text-align: right; white-space: nowrap;">Hazardous</span>
        </div>

        <div class="hero-scale-bar-track" style="position: relative; height: 10px; border-radius: 999px; background-color: #e2e8f0; margin-bottom: 10px;">
          <div style="width: 100%; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #22c55e 0%, #22c55e 16.66%, #eab308 16.66%, #eab308 33.33%, #f97316 33.33%, #f97316 50%, #ef4444 50%, #ef4444 66.66%, #a855f7 66.66%, #a855f7 83.33%, #f43f5e 83.33%, #f43f5e 100%); box-shadow: inset 0 1px 2px rgba(0,0,0,0.06);"></div>
          <div id="hero-scale-indicator" style="position: absolute; top: 50%; left: <?= $init_scale_pct ?>; transform: translate(-50%, -50%); width: 18px; height: 18px; border-radius: 50%; background-color: #ffffff; border: 3px solid <?= $init_aqi_accent ?>; box-shadow: 0 2px 6px rgba(0,0,0,0.25); z-index: 3; transition: left 0.5s ease-out;"></div>
        </div>

        <div class="hero-scale-ticks" style="display: flex; justify-content: space-between; padding: 0 2px;">
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
    <div class="pollutants-grid-responsive grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
      
      <!-- PM2.5 -->
      <div class="pollutant-card flex flex-col justify-between gap-3 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 cursor-pointer shadow-[0_4px_16px_rgba(15,23,42,0.04)] hover:border-[#00bfa5] hover:-translate-y-1 transition-all duration-200 relative overflow-hidden" onclick="openPollutantModal('pm25')">
        <div class="flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <div class="pollutant-icon-box w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-sky-50 text-sky-500 flex items-center justify-center text-sm sm:text-lg shrink-0">🌫️</div>
            <div>
              <div class="pollutant-name text-[13px] sm:text-[15px] font-bold font-sans text-slate-900 leading-tight">PM₂.₅</div>
              <div class="pollutant-who text-[9px] sm:text-[10.5px] text-slate-500 mt-[2px] whitespace-nowrap">WHO: ≤15</div>
            </div>
          </div>
          <div class="pollutant-info-icon w-6 h-6 rounded-full bg-slate-50 border border-slate-300 flex items-center justify-center text-slate-500 shrink-0">
            <i data-lucide="info" class="w-3 h-3"></i>
          </div>
        </div>
        <div class="flex items-end justify-between w-full mt-1">
          <div>
            <div id="card-pm25" class="font-mono text-lg sm:text-2xl font-extrabold text-sky-500 leading-none">--</div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">µg/m³</div>
          </div>
          <div id="tag-pm25" class="px-2 py-1 rounded-full bg-slate-100 border border-slate-200 text-slate-500 text-[9px] sm:text-[11px] font-bold flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
            <span>Load</span>
          </div>
        </div>
      </div>

      <!-- PM10 -->
      <div class="pollutant-card flex flex-col justify-between gap-3 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 cursor-pointer shadow-[0_4px_16px_rgba(15,23,42,0.04)] hover:border-[#00bfa5] hover:-translate-y-1 transition-all duration-200 relative overflow-hidden" onclick="openPollutantModal('pm10')">
        <div class="flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <div class="pollutant-icon-box w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-indigo-50 text-indigo-500 flex items-center justify-center text-sm sm:text-lg shrink-0">☁️</div>
            <div>
              <div class="pollutant-name text-[13px] sm:text-[15px] font-bold font-sans text-slate-900 leading-tight">PM₁₀</div>
              <div class="pollutant-who text-[9px] sm:text-[10.5px] text-slate-500 mt-[2px] whitespace-nowrap">WHO: ≤45</div>
            </div>
          </div>
          <div class="pollutant-info-icon w-6 h-6 rounded-full bg-slate-50 border border-slate-300 flex items-center justify-center text-slate-500 shrink-0">
            <i data-lucide="info" class="w-3 h-3"></i>
          </div>
        </div>
        <div class="flex items-end justify-between w-full mt-1">
          <div>
            <div id="card-pm10" class="font-mono text-lg sm:text-2xl font-extrabold text-indigo-500 leading-none">--</div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">µg/m³</div>
          </div>
          <div id="tag-pm10" class="px-2 py-1 rounded-full bg-slate-100 border border-slate-200 text-slate-500 text-[9px] sm:text-[11px] font-bold flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
            <span>Load</span>
          </div>
        </div>
      </div>

      <!-- CO -->
      <div class="pollutant-card flex flex-col justify-between gap-3 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 cursor-pointer shadow-[0_4px_16px_rgba(15,23,42,0.04)] hover:border-[#00bfa5] hover:-translate-y-1 transition-all duration-200 relative overflow-hidden" onclick="openPollutantModal('co')">
        <div class="flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <div class="pollutant-icon-box w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-slate-50 text-slate-600 flex items-center justify-center text-sm sm:text-lg shrink-0">💨</div>
            <div>
              <div class="pollutant-name text-[13px] sm:text-[15px] font-bold font-sans text-slate-900 leading-tight">CO</div>
              <div class="pollutant-who text-[9px] sm:text-[10.5px] text-slate-500 mt-[2px] whitespace-nowrap">WHO: ≤4</div>
            </div>
          </div>
          <div class="pollutant-info-icon w-6 h-6 rounded-full bg-slate-50 border border-slate-300 flex items-center justify-center text-slate-500 shrink-0">
            <i data-lucide="info" class="w-3 h-3"></i>
          </div>
        </div>
        <div class="flex items-end justify-between w-full mt-1">
          <div>
            <div id="card-co" class="font-mono text-lg sm:text-2xl font-extrabold text-slate-600 leading-none">--</div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">mg/m³</div>
          </div>
          <div id="tag-co" class="px-2 py-1 rounded-full bg-slate-100 border border-slate-200 text-slate-500 text-[9px] sm:text-[11px] font-bold flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
            <span>Load</span>
          </div>
        </div>
      </div>

      <!-- NO2 -->
      <div class="pollutant-card flex flex-col justify-between gap-3 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 cursor-pointer shadow-[0_4px_16px_rgba(15,23,42,0.04)] hover:border-[#00bfa5] hover:-translate-y-1 transition-all duration-200 relative overflow-hidden" onclick="openPollutantModal('no2')">
        <div class="flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <div class="pollutant-icon-box w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-purple-50 text-purple-500 flex items-center justify-center text-sm sm:text-lg shrink-0">🏭</div>
            <div>
              <div class="pollutant-name text-[13px] sm:text-[15px] font-bold font-sans text-slate-900 leading-tight">NO₂</div>
              <div class="pollutant-who text-[9px] sm:text-[10.5px] text-slate-500 mt-[2px] whitespace-nowrap">WHO: ≤25</div>
            </div>
          </div>
          <div class="pollutant-info-icon w-6 h-6 rounded-full bg-slate-50 border border-slate-300 flex items-center justify-center text-slate-500 shrink-0">
            <i data-lucide="info" class="w-3 h-3"></i>
          </div>
        </div>
        <div class="flex items-end justify-between w-full mt-1">
          <div>
            <div id="card-no2" class="font-mono text-lg sm:text-2xl font-extrabold text-purple-500 leading-none">--</div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">µg/m³</div>
          </div>
          <div id="tag-no2" class="px-2 py-1 rounded-full bg-slate-100 border border-slate-200 text-slate-500 text-[9px] sm:text-[11px] font-bold flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
            <span>Load</span>
          </div>
        </div>
      </div>

      <!-- O3 -->
      <div class="pollutant-card flex flex-col justify-between gap-3 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 cursor-pointer shadow-[0_4px_16px_rgba(15,23,42,0.04)] hover:border-[#00bfa5] hover:-translate-y-1 transition-all duration-200 relative overflow-hidden" onclick="openPollutantModal('o3')">
        <div class="flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <div class="pollutant-icon-box w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center text-sm sm:text-lg shrink-0">☀️</div>
            <div>
              <div class="pollutant-name text-[13px] sm:text-[15px] font-bold font-sans text-slate-900 leading-tight">O₃</div>
              <div class="pollutant-who text-[9px] sm:text-[10.5px] text-slate-500 mt-[2px] whitespace-nowrap">WHO: ≤100</div>
            </div>
          </div>
          <div class="pollutant-info-icon w-6 h-6 rounded-full bg-slate-50 border border-slate-300 flex items-center justify-center text-slate-500 shrink-0">
            <i data-lucide="info" class="w-3 h-3"></i>
          </div>
        </div>
        <div class="flex items-end justify-between w-full mt-1">
          <div>
            <div id="card-o3" class="font-mono text-lg sm:text-2xl font-extrabold text-amber-500 leading-none">--</div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">µg/m³</div>
          </div>
          <div id="tag-o3" class="px-2 py-1 rounded-full bg-slate-100 border border-slate-200 text-slate-500 text-[9px] sm:text-[11px] font-bold flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
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
        <div style="display: flex; gap: 8px; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 4px; scrollbar-width: none;" class="sm:flex-wrap" id="pollutant-filters">
          <button onclick="togglePollutant('pm25')" id="filter-pm25" data-active="true" data-color="#0284c7" style="min-height: 38px; padding: 6px 14px; border-radius: 999px; border: 1.5px solid #0284c7; background-color: rgba(2, 132, 199, 0.15); color: #0284c7; font-size: 12.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.15s ease; white-space: nowrap; flex-shrink: 0;">
            <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #0284c7;"></span> PM2.5
          </button>
          <button onclick="togglePollutant('pm10')" id="filter-pm10" data-active="true" data-color="#6366f1" style="min-height: 38px; padding: 6px 14px; border-radius: 999px; border: 1.5px solid #6366f1; background-color: rgba(99, 102, 241, 0.15); color: #6366f1; font-size: 12.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.15s ease; white-space: nowrap; flex-shrink: 0;">
            <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #6366f1;"></span> PM10
          </button>
          <button onclick="togglePollutant('co')" id="filter-co" data-active="true" data-color="#64748b" style="min-height: 38px; padding: 6px 14px; border-radius: 999px; border: 1.5px solid #64748b; background-color: rgba(100, 116, 139, 0.15); color: #64748b; font-size: 12.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.15s ease; white-space: nowrap; flex-shrink: 0;">
            <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #64748b;"></span> CO
          </button>
          <button onclick="togglePollutant('no2')" id="filter-no2" data-active="true" data-color="#9333ea" style="min-height: 38px; padding: 6px 14px; border-radius: 999px; border: 1.5px solid #9333ea; background-color: rgba(147, 51, 234, 0.15); color: #9333ea; font-size: 12.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.15s ease; white-space: nowrap; flex-shrink: 0;">
            <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #9333ea;"></span> NO₂
          </button>
          <button onclick="togglePollutant('o3')" id="filter-o3" data-active="true" data-color="#d97706" style="min-height: 38px; padding: 6px 14px; border-radius: 999px; border: 1.5px solid #d97706; background-color: rgba(217, 119, 6, 0.15); color: #d97706; font-size: 12.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.15s ease; white-space: nowrap; flex-shrink: 0;">
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
    <div style="margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
      <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; font-family: var(--font-sans);">
        <i data-lucide="table" style="width: 18px; height: 18px; color: #00bfa5;"></i>
        <span>Network Readings &amp; Status</span>
      </h2>
      <span style="display: none !important;">
        <i data-lucide="chevrons-right" style="width: 14px; height: 14px;"></i> Swipe table
      </span>
    </div>

    <div style="background-color: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);">
      <!-- Mobile swipe indicator -->
      <div class="block sm:hidden px-4 py-2 bg-slate-50 border-b border-slate-100 text-[11px] text-slate-500 font-medium flex items-center justify-between">
        <span>👈 Swipe horizontally to view all live parameters</span>
        <span class="font-mono text-[10px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded font-bold">STREAM</span>
      </div>

      <div style="overflow-x: auto; max-height: 420px;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; font-family: var(--font-sans); min-width: 800px;">
          <thead style="position: sticky; top: 0; z-index: 1; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
            <tr>
              <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 12px; white-space: nowrap;">Timestamp (IST)</th>
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
              <div style="font-size: 11.5px; color: #475569; margin-top: 2px;">Air quality is considered moderate, and air pollution poses little or no risk.</div>
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
    if (window.triggerHaptic) window.triggerHaptic('light');
    const aqiBtn = document.getElementById('tab-aqi');
    const weatherBtn = document.getElementById('tab-weather');
    const aqiView = document.getElementById('aqi-view');
    const weatherView = document.getElementById('weather-view');

    if (tab === 'aqi') {
        if (aqiBtn) { 
            aqiBtn.classList.add('live-tab-active');
            aqiBtn.classList.remove('live-tab-inactive');
            aqiBtn.style.backgroundColor = '#00bfa5'; 
            aqiBtn.style.color = '#ffffff'; 
        }
        if (weatherBtn) { 
            weatherBtn.classList.add('live-tab-inactive');
            weatherBtn.classList.remove('live-tab-active');
            weatherBtn.style.backgroundColor = 'transparent'; 
            weatherBtn.style.color = '#64748b'; 
        }
        if (aqiView) aqiView.style.display = 'block';
        if (weatherView) weatherView.style.display = 'none';
        try {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', 'aqi');
            history.replaceState(null, '', url.toString());
        } catch(e) {}
    } else {
        if (weatherBtn) { 
            weatherBtn.classList.add('live-tab-active');
            weatherBtn.classList.remove('live-tab-inactive');
            weatherBtn.style.backgroundColor = '#00bfa5'; 
            weatherBtn.style.color = '#ffffff'; 
        }
        if (aqiBtn) { 
            aqiBtn.classList.add('live-tab-inactive');
            aqiBtn.classList.remove('live-tab-active');
            aqiBtn.style.backgroundColor = 'transparent'; 
            aqiBtn.style.color = '#64748b'; 
        }
        if (weatherView) weatherView.style.display = 'block';
        if (aqiView) aqiView.style.display = 'none';
        try {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', 'weather');
            history.replaceState(null, '', url.toString());
        } catch(e) {}
    }
    if (window.lucide) lucide.createIcons();
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

var liveDataStream = [];
var comparisonChartInstance = null;
var visiblePollutants = { pm25: true, pm10: true, co: true, no2: true, o3: true };
var pollutantColors = { pm25: '#0284c7', pm10: '#6366f1', co: '#64748b', no2: '#9333ea', o3: '#d97706' };

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
    
    if (liveDataStream.length === 0) {
        if (comparisonChartInstance) {
            comparisonChartInstance.destroy();
            comparisonChartInstance = null;
        }
        const emptyEl = document.getElementById('comparison-chart-empty');
        if (emptyEl) {
            emptyEl.style.display = 'flex';
            emptyEl.innerText = 'No telemetry stream available.';
        }
        return;
    }
    const emptyEl = document.getElementById('comparison-chart-empty');
    if (emptyEl) emptyEl.style.display = 'none';
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

    if (comparisonChartInstance) {
        comparisonChartInstance.data.labels = labels;
        comparisonChartInstance.data.datasets = datasets;
        comparisonChartInstance.update('none');
        return;
    }

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

function formatSupabaseValue(val, suffix = '', defaultVal = '--', decimals = 3) {
    if (val === null || val === undefined || val === '') return defaultVal;
    const num = Number(val);
    if (isNaN(num)) return String(val) + suffix;
    return num.toFixed(decimals) + suffix;
}

function formatWeatherOneDecimal(val, suffix = '', defaultVal = '--') {
    if (val === null || val === undefined || val === '') return defaultVal;
    const num = Number(val);
    if (isNaN(num)) return String(val) + suffix;
    return num.toFixed(1) + suffix;
}

function formatTempOneDecimal(val, suffix = '', defaultVal = '--') {
    return formatWeatherOneDecimal(val, suffix, defaultVal);
}

function formatStreamTimestamp(row) {
    const rawTs = row.created_at || row.timestamp;
    if (!rawTs) return '--';
    const dt = new Date(rawTs);
    
    // Strict IST formatting: "22/10/2026, 06:49:58 pm" regardless of viewer's device timezone
    const istDate = dt.toLocaleDateString('en-GB', { timeZone: 'Asia/Kolkata' });
    const istTime = dt.toLocaleTimeString('en-US', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', second: '2-digit' }).toLowerCase();
    
    return `${istDate}, ${istTime}`;
}

function renderLiveTable() {
    const tbody = document.getElementById('live-table-body');
    if (!tbody) return;
    
    if (liveDataStream.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="padding: 40px; text-align: center; color: #64748b;">No telemetry stream data.</td></tr>`;
        return;
    }

    tbody.innerHTML = liveDataStream.map((d, i) => {
        const timeStr = formatStreamTimestamp(d);
        const aqiVal = d.cpcb_aqi != null ? Math.round(d.cpcb_aqi) : '--';
        const pm25 = formatSupabaseValue(d.pm25 ?? d['pm2.5']);
        const pm10 = formatSupabaseValue(d.pm10);
        const co = formatSupabaseValue(d.co);
        const no2 = formatSupabaseValue(d.no2);
        const o3 = formatSupabaseValue(d.o3);

        return `<tr style="border-bottom: 1px solid #f1f5f9; background-color: ${i % 2 === 0 ? '#ffffff' : '#f8fafc'};">
            <td style="padding: 11px 16px; font-family: var(--font-mono); font-size: 12px; color: #1e293b; white-space: nowrap; font-weight: 600;">${timeStr}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 800; color: #0f172a; font-size: 14px;">${aqiVal}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #334155;">${pm25}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #334155;">${pm10}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: ${co === '--' ? '#94a3b8' : '#334155'};">${co}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #334155;">${no2}</td>
            <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #334155;">${o3}</td>
        </tr>`;
    }).join('');
}

async function refreshDashboard() {
    try {
        let bulkData;
        if (window.INITIAL_DATA && window.INITIAL_DATA.aqi_history) {
            bulkData = window.INITIAL_DATA;
            window.INITIAL_DATA = null;
        } else {
            const res = await fetch('api/telemetry.php?action=live_bulk&limit=60&_t=' + Date.now());
            bulkData = await res.json();
        }
        
        const aqiData = bulkData.aqi;
        const weatherData = bulkData.weather;
        const weatherHistory = bulkData.weather_history;
        const historyData = bulkData.aqi_history;
        let currentAQITheme = null;

        // Precision non-overlapping positioning inside each of the 6 color segments
        // Ensures indicator is completely situated inside the matching tier color:
        // Good (0-50): 3%-14% (green) | Moderate (51-100): 19%-31% (yellow) | Poor (101-150): 36%-48% (orange)
        // Unhealthy (151-200): 53%-64% (red) | Severe (201-300): 70%-80% (purple) | Hazardous (301+): 86%-97% (rose)
        function getAQIScalePercentage(aqi) {
            const v = Number(aqi);
            if (isNaN(v) || v <= 0) return 4;
            if (v <= 50)  return 4 + (v / 50) * 9;             // 4% to 13% (Good - Green)
            if (v <= 100) return 21 + ((v - 50) / 50) * 8;     // 21% to 29% (Moderate - Yellow)
            if (v <= 150) return 37 + ((v - 100) / 50) * 9;    // 37% to 46% (Poor - Orange)
            if (v <= 200) return 54 + ((v - 150) / 50) * 9;    // 54% to 63% (Unhealthy - Red)
            if (v <= 300) return 71 + ((v - 200) / 100) * 8;   // 71% to 79% (Severe - Purple)
            return 87 + Math.min((v - 300) / 200, 1) * 9;      // 87% to 96% (Hazardous - Rose)
        }

        // AQI category themes matching the exact bar colors and labels
        function getAQITheme(aqi) {
            const v = Number(aqi);
            if (isNaN(v) || v <= 50) {
                return { label: 'Good', bg: '#ecfdf5', border: 'rgba(34, 197, 94, 0.4)', shadow: 'rgba(34, 197, 94, 0.18)', accent: '#22c55e' };
            }
            if (v <= 100) {
                // Moderate: Warm rich yellow matching bar segment (#eab308)
                return { label: 'Moderate', bg: '#fef9c3', border: 'rgba(234, 179, 8, 0.45)', shadow: 'rgba(234, 179, 8, 0.22)', accent: '#eab308' };
            }
            if (v <= 150) {
                // Poor: Orange matching bar segment (#f97316)
                return { label: 'Poor', bg: '#ffedd5', border: 'rgba(249, 115, 22, 0.45)', shadow: 'rgba(249, 115, 22, 0.22)', accent: '#f97316' };
            }
            if (v <= 200) {
                // Unhealthy: Red matching bar segment (#ef4444)
                return { label: 'Unhealthy', bg: '#fee2e2', border: 'rgba(239, 68, 68, 0.45)', shadow: 'rgba(239, 68, 68, 0.22)', accent: '#ef4444' };
            }
            if (v <= 300) {
                // Severe: Purple matching bar segment (#a855f7)
                return { label: 'Severe', bg: '#f3e8ff', border: 'rgba(168, 85, 247, 0.45)', shadow: 'rgba(168, 85, 247, 0.22)', accent: '#a855f7' };
            }
            // Hazardous: Pink/Dark Red matching bar segment (#f43f5e)
            return { label: 'Hazardous', bg: '#ffe4e6', border: 'rgba(244, 63, 94, 0.45)', shadow: 'rgba(244, 63, 94, 0.22)', accent: '#f43f5e' };
        }
        
        if (aqiData) {
            const d = aqiData;
            const aqiValue = d.cpcb_aqi ?? '--';
            document.getElementById('hero-aqi-val').innerText = aqiValue;

            // AQI Badge & Colors
            if (d.aqi_info) {
                const theme = getAQITheme(aqiValue);
                currentAQITheme = theme;
                const color = theme.accent;

                // Hero card — entire block changes to matching category color
                const hero = document.getElementById('hero-card');
                const badge = document.getElementById('hero-aqi-badge');

                hero.style.backgroundColor = theme.bg;
                hero.style.borderColor     = theme.border;
                hero.style.boxShadow       = `0 8px 30px ${theme.shadow}, 0 1px 3px rgba(15,23,42,0.04)`;

                document.getElementById('hero-left-bar').style.backgroundColor = theme.accent;
                document.getElementById('status-dot').style.backgroundColor    = theme.accent;

                badge.style.borderColor = theme.accent + '60';
                document.getElementById('hero-aqi-badge-dot').style.backgroundColor = theme.accent;
                document.getElementById('hero-aqi-label').innerText = theme.label;

                const pct = Math.min(Math.max(getAQIScalePercentage(aqiValue), 0), 100);
                const indicator = document.getElementById('hero-scale-indicator');
                indicator.style.left = `clamp(9px, ${pct}%, calc(100% - 9px))`;
                indicator.style.borderColor = theme.accent;
                
                // Populate Weather tab AQI badge
                const weatherAqiBadge = document.getElementById('weather-hero-aqi-label');
                if (weatherAqiBadge) {
                    weatherAqiBadge.innerText = `AQI ${aqiValue} · ${d.aqi_info.label}`;
                    weatherAqiBadge.style.color           = theme.accent;
                    weatherAqiBadge.style.borderColor     = theme.accent + '50';
                    weatherAqiBadge.style.backgroundColor = theme.bg;
                }
            }

            // Quick PM Summary
            document.getElementById('hero-pm25').innerText = `${formatSupabaseValue(d.pm25 ?? d['pm2.5'])} µg/m³`;
            document.getElementById('hero-pm10').innerText = `${formatSupabaseValue(d.pm10)} µg/m³`;

            // Cards: Display exact raw values from Supabase without forcing 0 for null
            document.getElementById('card-pm25').innerText = formatSupabaseValue(d.pm25 ?? d['pm2.5']);
            document.getElementById('card-pm10').innerText = formatSupabaseValue(d.pm10);
            document.getElementById('card-co').innerText = formatSupabaseValue(d.co);
            document.getElementById('card-no2').innerText = formatSupabaseValue(d.no2);
            document.getElementById('card-o3').innerText = formatSupabaseValue(d.o3);

            updateTag('tag-pm25', d.pm25 ?? d['pm2.5'], 15);
            updateTag('tag-pm10', d.pm10, 45);
            updateTag('tag-co', d.co, 4);
            updateTag('tag-no2', d.no2, 25);
            updateTag('tag-o3', d.o3, 100);
        }
        
        // WEATHER DATA POPULATION
        if (weatherData) {
            const w = weatherData;
            
            document.getElementById('weather-hero-temp').innerText = formatWeatherOneDecimal(w.temperature);
            document.getElementById('card-temp').innerText = formatWeatherOneDecimal(w.temperature, '°C');
            document.getElementById('card-hum').innerText = formatWeatherOneDecimal(w.humidity, '%');
            document.getElementById('card-wind').innerText = formatWeatherOneDecimal(w.wind_speed, ' km/h');
            document.getElementById('card-gust').innerText = formatWeatherOneDecimal(w.wind_gust, ' km/h');
            document.getElementById('card-rain').innerText = formatWeatherOneDecimal(w.rain_gauge, ' mm');
            
            // Today's rainfall synchronized with overview page
            const todayRainEl = document.getElementById('card-today-rain');
            if (todayRainEl) {
                let trVal = (bulkData && bulkData.today_rain_total != null) ? Number(bulkData.today_rain_total) : 0;
                if (w.rain_gauge != null && Number(w.rain_gauge) > 0 && trVal < Number(w.rain_gauge)) {
                    trVal = Number(w.rain_gauge);
                }
                todayRainEl.innerText = trVal.toFixed(1) + ' mm';
            }

            // Humidity condition: Low (< 60%) = Sunny, High (>= 60%) = Cloudy
            const humVal = (w.humidity != null && !isNaN(Number(w.humidity))) ? Number(w.humidity) : null;
            const isCloudy = humVal != null && humVal >= 60;
            const isRaining = w.rain_gauge != null && Number(w.rain_gauge) > 0;

            const wHumBadge = document.getElementById('weather-hum-badge');
            if (wHumBadge) {
                if (humVal != null) {
                    wHumBadge.style.display = 'inline-flex';
                    wHumBadge.innerHTML = isCloudy ? '☁️ Cloudy' : '☀️ Sunny';
                    wHumBadge.style.backgroundColor = isCloudy ? '#f1f5f9' : '#fef9c3';
                    wHumBadge.style.color = isCloudy ? '#475569' : '#854d0e';
                    wHumBadge.style.borderColor = isCloudy ? '#cbd5e1' : '#fde047';
                } else {
                    wHumBadge.style.display = 'none';
                }
            }
            
            const getCompassDir = (val) => {
              if (val == null) return 'N/A';
              const deg = Math.round(Number(val) % 360);
              if (isNaN(deg)) return val;
              const dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
              return dirs[Math.round(deg / 22.5) % 16];
            };
            const dir = (w.wind_direction != null && isNaN(Number(w.wind_direction))) ? w.wind_direction : getCompassDir(w.wind_direction);
            document.getElementById('card-dir').innerText = dir;

            const weatherIcon = isRaining ? '🌧️' : (isCloudy ? '☁️' : '☀️');

            const tempIconEl = document.getElementById('weather-temp-icon');
            if (tempIconEl) tempIconEl.remove();

            const heroLabelEl = document.getElementById('weather-hero-label');
            if (heroLabelEl) heroLabelEl.remove();
            
            const now = new Date();
            const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric', timeZone: 'Asia/Kolkata' });
            document.getElementById('weather-date-str').innerText = dateStr;
        }
        
        // WEATHER HISTORY TABLE & TODAY'S TEMP RANGE
        const tbody = document.getElementById('weather-tbody');
        if (weatherHistory && weatherHistory.length > 0) {
            tbody.innerHTML = '';
            let temps = [];
            
            weatherHistory.forEach((row, i) => {
                const timeStr = formatStreamTimestamp(row);
                const temp = formatWeatherOneDecimal(row.temperature);
                const hum = formatWeatherOneDecimal(row.humidity);
                const wind = formatWeatherOneDecimal(row.wind_speed);
                const gust = formatWeatherOneDecimal(row.wind_gust);
                const dir = row.wind_direction || '--';
                const rain = formatWeatherOneDecimal(row.rain_gauge);

                if (row.temperature != null) temps.push(Number(row.temperature));
                
                const tr = document.createElement('tr');
                tr.style.backgroundColor = i % 2 === 0 ? '#ffffff' : '#f8fafc';
                tr.style.borderBottom = '1px solid #f1f5f9';
                
                tr.innerHTML = `
                  <td style="padding: 11px 16px; font-family: var(--font-mono); font-size: 12px; color: #1e293b; white-space: nowrap; font-weight: 600;">${timeStr}</td>
                  <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 800; color: #0f172a; font-size: 14px;">${temp}</td>
                  <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #334155;">${hum}</td>
                  <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #334155;">${wind}</td>
                  <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #334155;">${gust}</td>
                  <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #334155;">${dir}</td>
                  <td style="padding: 11px 16px; font-family: var(--font-mono); font-weight: 600; color: #334155;">${rain}</td>
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
            tbody.innerHTML = '<tr><td colspan="7" style="padding: 40px; text-align: center; color: #64748b;">No live weather telemetry found.</td></tr>';
        }

        // LIVE AQI HISTORY (for chart and table)
        if (historyData && historyData.length > 0) {
            liveDataStream = historyData;
        }
        renderComparisonChart();
        renderLiveTable();

        const now = new Date();

        function formatOfflineDuration(mins) {
            const minutes = Math.max(0, Math.floor(Number(mins) || 0));
            if (minutes < 60) {
                return {
                    badge: `${minutes}M AGO`,
                    pill: `${minutes}m ago`,
                    timeAgo: `${minutes}m ago`
                };
            } else if (minutes < 1440) { // Between 1 hour and 24 hours: show in hours
                const hours = Math.floor(minutes / 60);
                const remMins = minutes % 60;
                return {
                    badge: `${hours}H AGO`,
                    pill: `${hours}h ago`,
                    timeAgo: remMins > 0 ? `${hours}h ${remMins}m ago` : `${hours}h ago`
                };
            } else { // 24 hours or more: show in days
                const days = Math.floor(minutes / 1440);
                const remHours = Math.floor((minutes % 1440) / 60);
                return {
                    badge: `${days}D AGO`,
                    pill: `${days}d ago`,
                    timeAgo: remHours > 0 ? `${days}d ${remHours}h ago` : `${days}d ago`
                };
            }
        }
        
        // --- AQI Offline Check (Sensors log hourly; healthy threshold is 90 mins) ---
        let isAqiOffline = true;
        let aqiOfflineMinutes = 0;
        let aqiLastUpdateTimeStr = "Unknown";
        
        if (aqiData && aqiData.timestamp) {
            const lastUpdate = new Date(aqiData.timestamp);
            const diffMs = now - lastUpdate;
            aqiOfflineMinutes = Math.floor(diffMs / 60000);
            isAqiOffline = aqiOfflineMinutes > 90;
            const timePart = lastUpdate.toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', hour12: true });
            aqiLastUpdateTimeStr = aqiOfflineMinutes >= 1440
                ? lastUpdate.toLocaleDateString('en-IN', { timeZone: 'Asia/Kolkata', month: 'short', day: 'numeric' }) + ', ' + timePart
                : timePart;
        }

        const aqiBanner = document.getElementById('aqi-offline-banner');
        const heroDot = document.getElementById('status-dot');
        const heroStreamText = document.getElementById('hero-stream-text');
        const heroAqiSubtext = document.getElementById('hero-aqi-subtext');
        const heroLeftBar = document.getElementById('hero-left-bar');

        if (aqiBanner) aqiBanner.style.display = isAqiOffline ? 'flex' : 'none';
        const aqiOffTime = document.getElementById('aqi-offline-last-update');
        if (aqiOffTime) aqiOffTime.innerText = aqiLastUpdateTimeStr;

        const aqiLiveBadge = document.getElementById('aqi-live-badge');
        if (aqiLiveBadge) {
            if (isAqiOffline) {
                aqiLiveBadge.style.backgroundColor = '#fff7ed';
                aqiLiveBadge.style.color = '#c2410c';
                aqiLiveBadge.style.borderColor = '#fed7aa';
                aqiLiveBadge.innerHTML = `<span style="width: 6px; height: 6px; border-radius: 50%; background-color: #f97316;"></span> OFFLINE`;
            } else {
                aqiLiveBadge.style.backgroundColor = '#ecfdf5';
                aqiLiveBadge.style.color = '#059669';
                aqiLiveBadge.style.borderColor = '#a7f3d0';
                aqiLiveBadge.innerHTML = `<span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10b981;"></span> LIVE`;
            }
        }
        const liveAccent = (currentAQITheme && currentAQITheme.accent) ? currentAQITheme.accent : '#22c55e';
        if (heroDot) heroDot.style.backgroundColor = isAqiOffline ? '#f97316' : liveAccent;
        if (heroStreamText) heroStreamText.innerText = isAqiOffline ? 'Cached Stream' : 'Live Stream';
        if (heroAqiSubtext) {
            heroAqiSubtext.innerText = isAqiOffline ? 'CACHED AQI' : 'LIVE AQI';
            heroAqiSubtext.style.color = isAqiOffline ? '#c2410c' : '#64748b';
        }
        if (heroLeftBar) heroLeftBar.style.backgroundColor = isAqiOffline ? '#f97316' : liveAccent;
        const latestTimeEl = document.getElementById('latest-time');
        if (latestTimeEl) latestTimeEl.innerText = aqiLastUpdateTimeStr;

        // --- Weather Offline Check (Sensors log hourly; healthy threshold is 90 mins) ---
        let isWeatherOffline = true;
        let weatherOfflineMinutes = 0;
        let weatherLastUpdateTimeStr = "Unknown";

        if (weatherData && weatherData.timestamp) {
            const wLastUpdate = new Date(weatherData.timestamp);
            const wDiffMs = now - wLastUpdate;
            weatherOfflineMinutes = Math.floor(wDiffMs / 60000);
            isWeatherOffline = weatherOfflineMinutes > 90;
            const wTimePart = wLastUpdate.toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', hour12: true });
            weatherLastUpdateTimeStr = weatherOfflineMinutes >= 1440
                ? wLastUpdate.toLocaleDateString('en-IN', { timeZone: 'Asia/Kolkata', month: 'short', day: 'numeric' }) + ', ' + wTimePart
                : wTimePart;
        }

        const weatherBanner = document.getElementById('weather-offline-banner');
        const weatherLatestTime = document.getElementById('weather-latest-time');
        
        if (weatherBanner) weatherBanner.style.display = isWeatherOffline ? 'flex' : 'none';
        const wOffTime = document.getElementById('weather-offline-last-update');
        if (wOffTime) wOffTime.innerText = weatherLastUpdateTimeStr;

        const weatherLiveBadge = document.getElementById('weather-live-badge');
        if (weatherLiveBadge) {
            if (isWeatherOffline) {
                weatherLiveBadge.style.backgroundColor = '#fff7ed';
                weatherLiveBadge.style.color = '#c2410c';
                weatherLiveBadge.style.borderColor = '#fed7aa';
                weatherLiveBadge.innerHTML = `<span style="width: 6px; height: 6px; border-radius: 50%; background-color: #f97316;"></span> OFFLINE`;
            } else {
                weatherLiveBadge.style.backgroundColor = '#ecfdf5';
                weatherLiveBadge.style.color = '#059669';
                weatherLiveBadge.style.borderColor = '#a7f3d0';
                weatherLiveBadge.innerHTML = `<span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10b981;"></span> LIVE`;
            }
        }
        if (weatherLatestTime) weatherLatestTime.innerText = weatherLastUpdateTimeStr;

        const isOverallLive = (!isAqiOffline || !isWeatherOffline);
        const minOfflineMins = Math.min(
            (aqiData && aqiData.timestamp) ? aqiOfflineMinutes : 99999,
            (weatherData && weatherData.timestamp) ? weatherOfflineMinutes : 99999
        );
        const tip = isOverallLive
            ? 'Live telemetry streaming from cloud'
            : `Sensor offline: Last received ${minOfflineMins >= 1440 ? Math.floor(minOfflineMins/1440) + 'd ago' : (minOfflineMins >= 60 ? Math.floor(minOfflineMins/60) + 'h ago' : minOfflineMins + 'm ago')}`;
        if (window.updateGlobalLiveBadge) {
            window.updateGlobalLiveBadge(isOverallLive, tip);
        }

        lucide.createIcons();
    } catch (e) {
        console.error("Live page error:", e);
        if (window.updateGlobalLiveBadge) {
            window.updateGlobalLiveBadge(false, 'Unable to connect to cloud telemetry');
        }
    }
}

var autoRefreshInterval = null;
var currentTabIsAqi = true;
var onLiveVisibilityChange = function() {
    if (!document.hidden) {
        refreshDashboard();
    }
};

window.__cleanupCurrentPage = function() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
    document.removeEventListener('visibilitychange', onLiveVisibilityChange);
    if (comparisonChartInstance) {
        comparisonChartInstance.destroy();
        comparisonChartInstance = null;
    }
};

setTimeout(() => {
    try {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('tab') === 'weather') {
            switchLiveTab('weather');
        }
    } catch(e) {}

    refreshDashboard();
    
    // Auto-refresh every 4 seconds silently in background
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    autoRefreshInterval = setInterval(() => {
        if (!document.hidden) {
            refreshDashboard();
        }
    }, 4000);

    // Immediately refresh when switching back to tab or unlocking screen
    document.addEventListener('visibilitychange', onLiveVisibilityChange);
}, 0);

var pollutantDetails = {
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
    
    document.getElementById('pm-modal-val').innerText = isNaN(currentVal) ? '--' : currentVal.toFixed(3);
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
    marker.style.left = `clamp(8px, ${pct}%, calc(100% - 8px))`;
    marker.style.borderColor = color;
    
    const modal = document.getElementById('pollutant-modal');
    if (modal) {
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        const scrollBox = modal.querySelector('.pollutant-modal-scroll');
        if (scrollBox) scrollBox.scrollTop = 0;
    }
    if (window.lucide && lucide.createIcons) lucide.createIcons();
}

function closePollutantModal() {
    const modal = document.getElementById('pollutant-modal');
    if (modal) modal.style.display = 'none';
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePollutantModal();
});
</script>

<!-- Pollutant Detail Modal (Mounted directly to viewport) -->
<div id="pollutant-modal" style="display: none; position: fixed; inset: 0; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; z-index: 99999; align-items: center; justify-content: center; padding: clamp(12px, 3vw, 24px); box-sizing: border-box;">
  <!-- Backdrop Click to Close -->
  <div style="position: absolute; inset: 0; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);" onclick="closePollutantModal()"></div>

  <!-- Modal Dialog Card -->
  <div style="position: relative; z-index: 1; background: #ffffff; width: 100%; max-width: 580px; max-height: min(88vh, 720px); border-radius: 24px; box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.3); border: 1px solid #e2e8f0; overflow: hidden; display: flex; flex-direction: column; margin: auto; animation: modal-pop 0.22s cubic-bezier(0.16, 1, 0.3, 1);">
    <button type="button" onclick="closePollutantModal()" title="Close details" style="position: absolute; top: 16px; right: 16px; z-index: 20; width: 34px; height: 34px; border-radius: 50%; border: 1px solid #e2e8f0; background: #ffffff; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; box-shadow: 0 2px 6px rgba(0,0,0,0.06); transition: all 0.2s ease;" onmouseover="this.style.background='#f8fafc'; this.style.color='#0f172a';" onmouseout="this.style.background='#ffffff'; this.style.color='#64748b';">
      <i data-lucide="x" style="width: 17px; height: 17px;"></i>
    </button>
    
    <div class="pollutant-modal-scroll" style="padding: clamp(20px, 4vw, 32px); overflow-y: auto; overscroll-behavior: contain; flex: 1;">
        <!-- Header -->
        <div style="display: flex; gap: 16px; align-items: flex-start; margin-bottom: 22px; padding-right: 36px;">
            <div id="pm-modal-icon-bg" style="width: 54px; height: 54px; border-radius: 16px; background: #f0fdf4; display: flex; align-items: center; justify-content: center; font-size: 26px; flex-shrink: 0; box-shadow: inset 0 2px 4px rgba(255,255,255,0.5); border: 1px solid #e2e8f0;">🌫️</div>
            <div>
                <h2 id="pm-modal-title" style="margin: 0 0 4px 0; font-size: 20px; font-weight: 700; color: #0f172a; font-family: var(--font-sans); line-height: 1.25;">Particulate Matter 2.5 (PM2.5)</h2>
                <div style="font-size: 13.5px; font-weight: 600; color: #64748b;">WHO Safe Limit: <span id="pm-modal-limit" style="color: #00bfa5;">15 µg/m³</span></div>
            </div>
        </div>

        <!-- Current Reading Box -->
        <div style="border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); background: #ffffff;">
            <div>
                <div style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">CURRENT READING</div>
                <div style="display: flex; align-items: baseline; gap: 8px;">
                    <div id="pm-modal-val" style="font-size: 38px; font-weight: 900; color: #0f172a; line-height: 1; font-family: var(--font-mono);">7.09</div>
                    <div id="pm-modal-unit" style="font-size: 20px; font-weight: 700; color: #0f172a; font-family: var(--font-mono);">µg/m³</div>
                </div>
            </div>
            <div id="pm-modal-status" style="display: flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; background: #dcfce7; color: #16a34a; font-size: 13px; font-weight: 700;">
                <i data-lucide="check-circle-2" style="width: 17px; height: 17px;"></i> Within Safe Limit
            </div>
        </div>

        <!-- Concentration Scale Box -->
        <div style="border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px 20px; margin-bottom: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); background: #ffffff;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <div style="font-size: 11.5px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; font-family: var(--font-mono);"><span id="pm-modal-short">PM2.5</span> CONCENTRATION SCALE</div>
                <div style="font-size: 11.5px; font-weight: 700; color: #00bfa5;">Real-time Level</div>
            </div>
            
            <div style="position: relative; width: 100%;">
                <div style="display: grid; grid-template-columns: repeat(6, 1fr); margin-bottom: 10px; font-size: 9.5px; font-weight: 700; line-height: 1;">
                    <div style="color: #22c55e; text-align: left;">Good</div>
                    <div style="color: #eab308; text-align: center;">Moderate</div>
                    <div style="color: #f97316; text-align: center;">Poor</div>
                    <div style="color: #ef4444; text-align: center;">Unhealthy</div>
                    <div style="color: #a855f7; text-align: center;">Severe</div>
                    <div style="color: #f43f5e; text-align: right;">Hazardous</div>
                </div>
                
                <div style="position: relative; height: 10px; border-radius: 999px; margin-bottom: 8px;">
                    <div style="width: 100%; height: 100%; border-radius: 999px; display: flex; overflow: hidden; background: #f1f5f9;">
                        <div style="flex: 1; background: #22c55e;"></div>
                        <div style="flex: 1; background: #eab308;"></div>
                        <div style="flex: 1; background: #f97316;"></div>
                        <div style="flex: 1; background: #ef4444;"></div>
                        <div style="flex: 1; background: #a855f7;"></div>
                        <div style="flex: 1; background: #f43f5e;"></div>
                    </div>
                    <div id="pm-modal-marker" style="position: absolute; top: 50%; left: 10%; transform: translate(-50%, -50%); width: 18px; height: 18px; border-radius: 50%; background: #ffffff; border: 3px solid #22c55e; box-shadow: 0 2px 5px rgba(0,0,0,0.25); transition: left 0.4s ease, border-color 0.4s ease;"></div>
                </div>
                
                <div id="pm-modal-ticks" style="display: flex; justify-content: space-between; font-size: 9.5px; font-weight: 600; color: #64748b; font-family: var(--font-mono); padding: 0 2px;">
                    <div>0</div>
                    <div id="pm-t1">30</div>
                    <div id="pm-t2">60</div>
                    <div id="pm-t3">90</div>
                    <div id="pm-t4">120</div>
                    <div id="pm-t5">250+</div>
                </div>
            </div>
        </div>

        <!-- Info sections -->
        <div style="margin-bottom: 20px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
            <h3 style="font-size: 14.5px; font-weight: 700; color: #0f172a; margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="info" style="width: 16px; height: 16px; color: #00bfa5;"></i> What is <span id="pm-modal-q">PM2.5</span>?
            </h3>
            <p id="pm-modal-desc" style="font-size: 13.5px; color: #475569; line-height: 1.6; margin: 0;">
                Fine inhalable particles...
            </p>
        </div>

        <div>
            <h3 style="font-size: 14.5px; font-weight: 700; color: #0f172a; margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="heart-pulse" style="width: 16px; height: 16px; color: #ef4444;"></i> Health Impact & Symptoms
            </h3>
            <p id="pm-modal-health" style="font-size: 13.5px; color: #475569; line-height: 1.6; margin: 0;">
                Causes respiratory inflammation...
            </p>
        </div>

    </div>
  </div>
</div>
