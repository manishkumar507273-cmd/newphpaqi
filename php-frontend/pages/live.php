<?php
$req_live_tab = (isset($_GET['tab']) && $_GET['tab'] === 'weather') ? 'weather' : 'aqi';
// Retrieve cached bulk telemetry for 0ms instant first-paint on both desktop and mobile
$cache_candidates = [
    sys_get_temp_dir() . '/smartairnet_cache/' . md5('live_bulk_60') . '.json',
    sys_get_temp_dir() . '/smartairnet_cache/' . md5('overview_bulk') . '.json',
    sys_get_temp_dir() . '/smartairnet_cache/last_known_hero.json',
];
$init_bulk = null;
$best_epoch = 0;
foreach ($cache_candidates as $cf) {
    if (file_exists($cf)) {
        $raw_cache = @file_get_contents($cf);
        if ($raw_cache && strlen($raw_cache) > 20) {
            $parsed = @json_decode($raw_cache, true);
            if ($parsed) {
                if (isset($parsed['cpcb_aqi']) && !isset($parsed['aqi'])) {
                    $parsed = ['aqi' => $parsed];
                }
                $aqi_item = null;
                if (!empty($parsed['aqi'])) {
                    if (isset($parsed['aqi'][0]) && is_array($parsed['aqi'][0])) {
                        $aqi_item = $parsed['aqi'][0];
                    } elseif (is_array($parsed['aqi'])) {
                        $aqi_item = $parsed['aqi'];
                    }
                }
                $ts_val = $aqi_item['timestamp'] ?? $aqi_item['created_at'] ?? null;
                $e = $ts_val ? strtotime($ts_val) : (isset($parsed['time']) ? (int)$parsed['time'] : filemtime($cf));
                if ($e > $best_epoch && $aqi_item) {
                    $best_epoch = $e;
                    $init_bulk = $parsed;
                    if (isset($init_bulk['aqi'][0])) {
                        $init_bulk['aqi'] = $init_bulk['aqi'][0];
                    }
                }
            }
        }
    }
}

// Fallback to cookie if server disk cache was flushed
if ($init_bulk === null && !empty($_COOKIE['aqi_last_hero'])) {
    $c = @json_decode(urldecode($_COOKIE['aqi_last_hero']), true);
    if ($c && !empty($c['aqi'])) {
        $init_bulk = [
            'aqi' => [
                'cpcb_aqi' => $c['aqi'],
                'pm25' => $c['pm25'] ?? null,
                'pm10' => $c['pm10'] ?? null,
                'timestamp' => $c['timestamp'] ?? date('c'),
            ]
        ];
    }
}

// AQI pre-calculated fields matching the exact 6 tiers with high light-and-dark gradient contrast
$init_aqi_val = '--';
$init_aqi_label = 'Moderate';
$init_aqi_accent = '#ca8a04';
$init_aqi_bg = 'linear-gradient(155deg, #ffffff 0%, #fefce8 25%, #fef08a 52%, #fde047 78%, #eab308 92%, #ca8a04 100%)';
$init_aqi_border = 'rgba(202, 138, 4, 0.35)';
$init_aqi_shadow = 'rgba(234, 179, 8, 0.20)';
$init_aqi_badge_bg = 'rgba(255, 255, 255, 0.75)';
$init_aqi_badge_border = 'rgba(202, 138, 4, 0.38)';
$init_aqi_badge_text = '#854d0e';
$init_scale_pct = '25%';
$init_pm25 = '--';
$init_pm10 = '--';

if (!empty($init_bulk['aqi'])) {
    $aqi_d = $init_bulk['aqi'];
    if (isset($aqi_d[0]) && is_array($aqi_d[0])) $aqi_d = $aqi_d[0];
    $v = isset($aqi_d['cpcb_aqi']) ? (int)round($aqi_d['cpcb_aqi']) : (isset($aqi_d['aqi_info']['value']) ? (int)round($aqi_d['aqi_info']['value']) : null);
    if ($v !== null) {
        $init_aqi_val = (string)$v;
        if ($v <= 50) {
            $init_aqi_label = 'Good';
            $init_aqi_accent = '#16a34a';
            $init_aqi_bg = 'linear-gradient(155deg, #ffffff 0%, #f0fdf4 25%, #dcfce7 55%, #86efac 80%, #34d399 92%, #10b981 100%)';
            $init_aqi_border = 'rgba(22, 163, 74, 0.35)';
            $init_aqi_shadow = 'rgba(16, 185, 129, 0.20)';
            $init_aqi_badge_bg = 'rgba(255, 255, 255, 0.75)';
            $init_aqi_badge_border = 'rgba(22, 163, 74, 0.35)';
            $init_aqi_badge_text = '#15803d';
            $init_scale_pct = round(max(2, ($v / 50) * 16.66), 2) . '%';
        } elseif ($v <= 100) {
            $init_aqi_label = 'Moderate';
            $init_aqi_accent = '#ca8a04';
            $init_aqi_bg = 'linear-gradient(155deg, #ffffff 0%, #fefce8 25%, #fef08a 52%, #fde047 78%, #eab308 92%, #ca8a04 100%)';
            $init_aqi_border = 'rgba(202, 138, 4, 0.35)';
            $init_aqi_shadow = 'rgba(234, 179, 8, 0.20)';
            $init_aqi_badge_bg = 'rgba(255, 255, 255, 0.75)';
            $init_aqi_badge_border = 'rgba(202, 138, 4, 0.38)';
            $init_aqi_badge_text = '#854d0e';
            $init_scale_pct = round(16.66 + (($v - 50) / 50) * 16.67, 2) . '%';
        } elseif ($v <= 200) {
            $init_aqi_label = 'Poor';
            $init_aqi_accent = '#ea580c';
            $init_aqi_bg = 'linear-gradient(155deg, #ffffff 0%, #fff7ed 22%, #ffedd5 48%, #fed7aa 72%, #fb923c 90%, #f97316 100%)';
            $init_aqi_border = 'rgba(234, 88, 12, 0.38)';
            $init_aqi_shadow = 'rgba(249, 115, 22, 0.22)';
            $init_aqi_badge_bg = 'rgba(255, 255, 255, 0.72)';
            $init_aqi_badge_border = 'rgba(234, 88, 12, 0.38)';
            $init_aqi_badge_text = '#c2410c';
            $init_scale_pct = round(33.33 + (($v - 100) / 100) * 16.67, 2) . '%';
        } elseif ($v <= 300) {
            $init_aqi_label = 'Unhealthy';
            $init_aqi_accent = '#e11d48';
            $init_aqi_bg = 'linear-gradient(155deg, #ffffff 0%, #fff1f2 22%, #ffe4e6 48%, #fecdd3 72%, #fb7185 90%, #e11d48 100%)';
            $init_aqi_border = 'rgba(225, 29, 72, 0.38)';
            $init_aqi_shadow = 'rgba(225, 29, 72, 0.22)';
            $init_aqi_badge_bg = 'rgba(255, 255, 255, 0.72)';
            $init_aqi_badge_border = 'rgba(225, 29, 72, 0.38)';
            $init_aqi_badge_text = '#9f1239';
            $init_scale_pct = round(50 + (($v - 200) / 100) * 16.66, 2) . '%';
        } elseif ($v <= 400) {
            $init_aqi_label = 'Severe';
            $init_aqi_accent = '#9333ea';
            $init_aqi_bg = 'linear-gradient(155deg, #ffffff 0%, #faf5ff 22%, #f3e8ff 48%, #e9d5ff 72%, #c084fc 90%, #9333ea 100%)';
            $init_aqi_border = 'rgba(147, 51, 234, 0.38)';
            $init_aqi_shadow = 'rgba(147, 51, 234, 0.22)';
            $init_aqi_badge_bg = 'rgba(255, 255, 255, 0.72)';
            $init_aqi_badge_border = 'rgba(147, 51, 234, 0.38)';
            $init_aqi_badge_text = '#6b21a8';
            $init_scale_pct = round(66.66 + (($v - 300) / 100) * 16.67, 2) . '%';
        } else {
            $init_aqi_label = 'Hazardous';
            $init_aqi_accent = '#b91c1c';
            $init_aqi_bg = 'linear-gradient(155deg, #ffffff 0%, #fef2f2 22%, #fee2e2 48%, #fca5a5 72%, #f87171 90%, #b91c1c 100%)';
            $init_aqi_border = 'rgba(185, 28, 28, 0.40)';
            $init_aqi_shadow = 'rgba(185, 28, 28, 0.25)';
            $init_aqi_badge_bg = 'rgba(255, 255, 255, 0.72)';
            $init_aqi_badge_border = 'rgba(185, 28, 28, 0.40)';
            $init_aqi_badge_text = '#881337';
            $init_scale_pct = round(min(98, 83.33 + min(1, ($v - 400) / 100) * 15.67), 2) . '%';
        }
        if (isset($aqi_d['pm25'])) $init_pm25 = is_numeric($aqi_d['pm25']) ? (string)round((float)$aqi_d['pm25']) : (string)$aqi_d['pm25'];
        elseif (isset($aqi_d['pm2.5'])) $init_pm25 = is_numeric($aqi_d['pm2.5']) ? (string)round((float)$aqi_d['pm2.5']) : (string)$aqi_d['pm2.5'];
        if (isset($aqi_d['pm10'])) $init_pm10 = is_numeric($aqi_d['pm10']) ? (string)round((float)$aqi_d['pm10']) : (string)$aqi_d['pm10'];
    }
}

$init_is_aqi_offline = false;
$init_time_str = 'Live Stream';
$init_aqi_ts = $init_bulk['aqi']['timestamp'] ?? ($init_bulk['aqi']['created_at'] ?? null);
if (!empty($init_aqi_ts)) {
    $ts = strtotime($init_aqi_ts);
    if ($ts > 0) {
        $init_time_str = date('h:i A', $ts);
    }
}

$init_is_weather_offline = false;
$init_weather_time_str = 'Live Stream';
$init_w_ts = $init_bulk['weather']['timestamp'] ?? ($init_bulk['weather']['created_at'] ?? null);
if (!empty($init_w_ts)) {
    $w_ts = strtotime($init_w_ts);
    if ($w_ts > 0) {
        $init_weather_time_str = date('h:i A', $w_ts);
    }
}

if (!function_exists('getPollutantScalePhp')) {
    function getPollutantScalePhp($param, $val) {
        $scales = [
            'pm25' => [30, 60, 90, 120, 250],
            'pm10' => [50, 100, 250, 350, 430],
            'co'   => [1, 2, 10, 17, 34],
            'no2'  => [40, 80, 180, 280, 400],
            'o3'   => [50, 100, 168, 208, 748]
        ];
        $colors = ['#59b61f', '#eec732', '#ea8c34', '#e95478', '#b33fba', '#c92033'];
        if ($val === null || !is_numeric($val) || !isset($scales[$param])) {
            return ['pct' => '8px', 'color' => '#59b61f'];
        }
        $v = (float)$val;
        $s = $scales[$param];
        if ($v <= $s[0]) {
            $pct = ($v / $s[0]) * 16.666;
            $tier = 0;
        } elseif ($v <= $s[1]) {
            $pct = 16.666 + (($v - $s[0]) / ($s[1] - $s[0])) * 16.667;
            $tier = 1;
        } elseif ($v <= $s[2]) {
            $pct = 33.333 + (($v - $s[1]) / ($s[2] - $s[1])) * 16.667;
            $tier = 2;
        } elseif ($v <= $s[3]) {
            $pct = 50.0 + (($v - $s[2]) / ($s[3] - $s[2])) * 16.666;
            $tier = 3;
        } elseif ($v <= $s[4]) {
            $pct = 66.666 + (($v - $s[3]) / ($s[4] - $s[3])) * 16.667;
            $tier = 4;
        } else {
            $pct = 83.333 + min(1.0, ($v - $s[4]) / $s[4]) * 15.667;
            $tier = 5;
        }
        $pct = round(min(98.0, max(2.0, $pct)), 2) . '%';
        return ['pct' => $pct, 'color' => $colors[$tier]];
    }
}

$aqi_d_sub = !empty($init_bulk['aqi']) ? $init_bulk['aqi'] : [];
$init_pollutant_bars = [
    'pm25' => getPollutantScalePhp('pm25', isset($aqi_d_sub['pm25']) ? $aqi_d_sub['pm25'] : (isset($aqi_d_sub['pm2.5']) ? $aqi_d_sub['pm2.5'] : null)),
    'pm10' => getPollutantScalePhp('pm10', isset($aqi_d_sub['pm10']) ? $aqi_d_sub['pm10'] : null),
    'co'   => getPollutantScalePhp('co', isset($aqi_d_sub['co']) ? $aqi_d_sub['co'] : null),
    'no2'  => getPollutantScalePhp('no2', isset($aqi_d_sub['no2']) ? $aqi_d_sub['no2'] : null),
    'o3'   => getPollutantScalePhp('o3', isset($aqi_d_sub['o3']) ? $aqi_d_sub['o3'] : null),
];

$init_pollutants = [
    'pm25' => isset($aqi_d_sub['pm25']) ? number_format((float)$aqi_d_sub['pm25'], 3) : (isset($aqi_d_sub['pm2.5']) ? number_format((float)$aqi_d_sub['pm2.5'], 3) : '--'),
    'pm10' => isset($aqi_d_sub['pm10']) ? number_format((float)$aqi_d_sub['pm10'], 3) : '--',
    'co'   => isset($aqi_d_sub['co']) && $aqi_d_sub['co'] !== null ? number_format((float)$aqi_d_sub['co'], 3) : '--',
    'no2'  => isset($aqi_d_sub['no2']) && $aqi_d_sub['no2'] !== null ? number_format((float)$aqi_d_sub['no2'], 3) : '--',
    'o3'   => isset($aqi_d_sub['o3']) && $aqi_d_sub['o3'] !== null ? number_format((float)$aqi_d_sub['o3'], 3) : '--',
];

$init_tags = [];
$pollutant_limits = ['pm25' => 60, 'pm10' => 100, 'co' => 2, 'no2' => 80, 'o3' => 100];
foreach (['pm25', 'pm10', 'co', 'no2', 'o3'] as $p) {
    $rawVal = $aqi_d_sub[$p] ?? ($p === 'pm25' ? ($aqi_d_sub['pm2.5'] ?? null) : null);
    if ($rawVal !== null && is_numeric($rawVal)) {
        $isSafe = (float)$rawVal <= $pollutant_limits[$p];
        $init_tags[$p] = [
            'show' => true,
            'isSafe' => $isSafe,
            'text' => $isSafe ? 'Normal' : 'High',
            'bg' => $isSafe ? '#dcfce7' : '#fee2e2',
            'border' => $isSafe ? '#bbf7d0' : '#fecaca',
            'color' => $isSafe ? '#15803d' : '#b91c1c',
            'dot' => $isSafe ? '#16a34a' : '#dc2626'
        ];
    } else {
        $init_tags[$p] = ['show' => false, 'text' => 'Load', 'bg' => '#f1f5f9', 'border' => '#e2e8f0', 'color' => '#64748b', 'dot' => '#94a3b8'];
    }
}
?>
<style>
@media (max-width: 768px) {
  /* Mobile compact layout for Live Telemetry */
  #hero-card {
    padding: 12px 14px !important;
    border-radius: 16px !important;
    margin-bottom: 8px !important;
  }
  .hero-skyline-silhouette {
    height: 48px !important;
    opacity: 0.12 !important;
  }
  .hero-header-row {
    margin-bottom: 10px !important;
    gap: 16px !important;
    align-items: flex-end !important;
  }
  #hero-aqi-val {
    font-size: 46px !important;
    line-height: 1 !important;
  }
  #hero-aqi-subtext {
    font-size: 11px !important;
    margin-bottom: 4px !important;
  }
  #hero-aqi-badge {
    min-width: 80px !important;
    padding: 4px 12px !important;
    border-radius: 9px !important;
  }
  #hero-aqi-label {
    font-size: 14px !important;
  }
  #hero-aqi-title {
    font-size: 11px !important;
    margin-bottom: 3px !important;
  }
  .hero-pm-row {
    margin-bottom: 10px !important;
    font-size: 12px !important;
  }
  .hero-pm-row strong {
    font-size: 13.5px !important;
  }
  .hero-pm-row span.hero-pm-unit {
    font-size: 9.5px !important;
  }
  #hero-pm25, #hero-pm10 {
    font-size: 13.5px !important;
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
    height: 5px !important;
    margin-bottom: 8px !important;
  }
  #hero-scale-indicator {
    width: 14px !important;
    height: 14px !important;
    border-width: 3px !important;
  }
  .hero-scale-ticks span {
    font-size: 8.5px !important;
  }
  
  /* Pollutants Grid Mobile Refinements */
  .pollutants-grid-responsive {
    gap: 6px !important;
  }
  .pollutant-card {
    padding: 7px 8px !important;
    border-radius: 12px !important;
    gap: 4px !important;
    min-height: 84px !important;
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

  /* Pollutant Detail Modal Mobile Optimization */
  #pollutant-modal {
    padding: 10px !important;
  }
  .pollutant-modal-card {
    border-radius: 18px !important;
    max-height: 92vh !important;
    width: 100% !important;
  }
  .pollutant-modal-scroll {
    padding: 16px 14px !important;
  }
  .pollutant-modal-close {
    top: 14px !important;
    right: 14px !important;
    width: 36px !important;
    height: 36px !important;
    min-width: 36px !important;
    min-height: 36px !important;
    z-index: 50 !important;
    cursor: pointer !important;
  }
  #pm-modal-icon-bg {
    width: 44px !important;
    height: 44px !important;
    font-size: 20px !important;
    border-radius: 12px !important;
  }
  #pm-modal-title {
    font-size: 16px !important;
    line-height: 1.2 !important;
  }
  #pm-modal-limit {
    font-size: 12px !important;
  }
  .pm-reading-box {
    padding: 12px 14px !important;
    margin-bottom: 14px !important;
    border-radius: 12px !important;
    gap: 10px !important;
  }
  #pm-modal-val {
    font-size: 28px !important;
  }
  #pm-modal-unit {
    font-size: 15px !important;
  }
  #pm-modal-status {
    font-size: 11px !important;
    padding: 5px 10px !important;
    border-radius: 6px !important;
  }
  .pm-scale-box {
    padding: 12px 14px !important;
    margin-bottom: 16px !important;
    border-radius: 12px !important;
  }
  .pm-scale-title {
    font-size: 10px !important;
  }
  .pm-scale-badge {
    font-size: 10px !important;
  }
  .pm-scale-labels {
    margin-bottom: 6px !important;
  }
  .pm-scale-labels > div {
    font-size: 8px !important;
    font-weight: 700 !important;
    letter-spacing: -0.02em !important;
    line-height: 1 !important;
    white-space: nowrap !important;
  }
  .pm-scale-track {
    height: 5px !important;
    margin-bottom: 6px !important;
  }
  #pm-modal-marker {
    width: 13px !important;
    height: 13px !important;
    border-width: 2.5px !important;
  }
  #pm-modal-ticks {
    height: 14px !important;
  }
  #pm-modal-ticks span {
    font-size: 8.5px !important;
  }

  /* AQI Reference Scale List Responsive */
  .aqi-scale-item {
    padding: 9px 12px !important;
    border-radius: 12px !important;
    gap: 8px !important;
  }
  .aqi-scale-item .scale-dot {
    width: 10px !important;
    height: 10px !important;
  }
  .aqi-scale-item .scale-title {
    font-size: 13px !important;
  }
  .aqi-scale-item .scale-range {
    font-size: 11px !important;
  }
  .aqi-scale-item .scale-desc {
    font-size: 11px !important;
    line-height: 1.35 !important;
  }
  
  .page-heading {
    font-size: 18px !important;
  }
}

@media (max-width: 380px) {
  .hero-scale-labels .scale-label {
    font-size: 7.2px !important;
    letter-spacing: -0.03em !important;
  }
  .hero-scale-ticks span {
    font-size: 7.5px !important;
  }
  .pm-scale-labels > div {
    font-size: 7.2px !important;
    letter-spacing: -0.03em !important;
  }
  #pm-modal-ticks span {
    font-size: 7.5px !important;
  }
  #pm-modal-val {
    font-size: 24px !important;
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

/* Live AQI Beacon Glow Animation (Glows when Online, Static when Offline) */
.status-dot-glowing #status-dot-halo {
  position: relative;
  animation: aqiHaloBreath 2s ease-in-out infinite;
}

.status-dot-glowing #status-dot {
  box-shadow: 0 0 3px currentColor;
}

@keyframes aqiHaloBreath {
  0%, 100% {
    transform: scale(0.96);
    opacity: 0.75;
    box-shadow: 0 0 0 0 transparent;
  }
  50% {
    transform: scale(1.06);
    opacity: 1;
    box-shadow: 0 0 3px currentColor;
  }
}

/* Offline: No glow, no pulse animations, static flat state */
.status-dot-offline #status-dot-halo,
.status-dot-offline #status-dot {
  animation: none !important;
  box-shadow: none !important;
  filter: none !important;
  transform: none !important;
  opacity: 1 !important;
}
</style>

<div style="display: flex; flex-direction: column; gap: 14px; max-width: 1200px; margin: 0 auto; animation: fade-in 0.4s ease-out;" class="p-2.5 sm:p-6">

  <!-- Sub-Navigation Selector (Compact Mobile Responsive) -->
  <div class="live-tab-wrapper">
    <div class="live-tab-container">
      <button id="tab-aqi" onclick="switchLiveTab('aqi')" class="live-tab-btn <?= $req_live_tab === 'weather' ? 'live-tab-inactive' : 'live-tab-active' ?>" style="<?= $req_live_tab === 'weather' ? 'background-color: transparent; color: #64748b;' : 'background-color: #00bfa5; color: #ffffff;' ?>">
        <i data-lucide="activity"></i>
        <span>AQI Telemetry</span>
      </button>
      <button id="tab-weather" onclick="switchLiveTab('weather')" class="live-tab-btn <?= $req_live_tab === 'weather' ? 'live-tab-active' : 'live-tab-inactive' ?>" style="<?= $req_live_tab === 'weather' ? 'background-color: #00bfa5; color: #ffffff;' : 'background-color: transparent; color: #64748b;' ?>">
        <i data-lucide="cloud-sun"></i>
        <span>Weather Telemetry</span>
      </button>
    </div>
  </div>

<div id="aqi-view" style="display: <?= $req_live_tab === 'weather' ? 'none' : 'block' ?>; animation: fade-in 0.4s ease-out;">

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
      <span>Last Updated: <span id="latest-time" style="color: #00bfa5; font-weight: 600;"><?= htmlspecialchars($init_time_str) ?></span></span>
      <span id="aqi-live-badge" style="display: inline-flex; align-items: center; gap: 5px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; background-color: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;">
        <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10b981;"></span>
        LIVE
      </span>
    </div>
  </div>

  <!-- HERO AQI CARD -->
  <div id="hero-card" class="hero-card-responsive" style="background: <?= $init_aqi_bg ?>; border-radius: 24px; padding: 26px 30px; color: #0f172a; border: 1.5px solid <?= $init_aqi_border ?>; box-shadow: 0 10px 30px <?= $init_aqi_shadow ?>, 0 1px 3px rgba(15,23,42,0.04); position: relative; overflow: hidden; transition: background 0.6s ease, border-color 0.5s ease, box-shadow 0.5s ease;">
    <!-- Indian Monuments Silhouette Watermark (Matching AQI Target Visual) -->
    <div class="hero-skyline-silhouette" style="position: absolute; bottom: 0; left: 0; right: 0; height: 68px; pointer-events: none; z-index: 0; opacity: 0.16; overflow: hidden; display: flex; align-items: flex-end;">
      <svg id="hero-skyline-svg" viewBox="0 0 1000 90" preserveAspectRatio="none" style="width: 100%; height: 100%; fill: <?= $init_aqi_accent ?>; transition: fill 0.5s ease;">
        <!-- India Gate (Left) -->
        <path d="M40 90 L40 38 L48 38 L48 30 L55 30 L55 24 L105 24 L105 30 L112 30 L112 38 L120 38 L120 90 L98 90 L98 52 C98 42 62 42 62 52 L62 90 Z" />
        <!-- Taj Mahal (Center-Left) -->
        <path d="M190 90 L190 32 L193 32 L193 25 L190 25 L192 18 L194 25 L195 90 Z" />
        <path d="M210 90 L210 50 L242 50 C242 42 248 36 254 36 C260 36 266 42 266 50 L274 50 C274 38 282 28 290 14 C290 8 291 8 291 14 C299 28 307 38 307 50 L315 50 C315 42 321 36 327 36 C333 36 339 42 339 50 L370 50 L370 90 L318 90 L318 64 C318 56 262 56 262 64 L262 90 Z" />
        <path d="M390 90 L390 32 L393 32 L393 25 L390 25 L392 18 L394 25 L395 90 Z" />
        <!-- Lotus Temple (Center-Right) -->
        <path d="M500 90 C520 62 548 38 565 24 C582 38 610 62 630 90 L595 90 C585 72 575 50 565 42 C555 50 545 72 535 90 Z" />
        <path d="M480 90 C495 72 515 55 532 46 C525 60 520 75 515 90 Z" />
        <path d="M650 90 C635 72 615 55 598 46 C605 60 610 75 615 90 Z" />
        <!-- Charminar (Right) -->
        <path d="M740 90 L740 22 L744 22 L744 14 L741 14 L743 8 L745 14 L746 90 Z" />
        <path d="M840 90 L840 22 L844 22 L844 14 L841 14 L843 8 L845 14 L846 90 Z" />
        <path d="M750 90 L750 42 L836 42 L836 90 L812 90 L812 60 C812 52 774 52 774 60 L774 90 Z" />
        <!-- Distant Birds -->
        <path d="M890 28 Q894 24 898 28 Q902 24 906 28 Q902 26 898 29 Q894 26 890 28 Z" />
        <path d="M915 22 Q918 19 922 22 Q925 19 929 22 Q925 20 922 23 Q918 20 915 22 Z" />
      </svg>
    </div>

    <div style="position: relative; z-index: 1;">
      <!-- Live AQI & Air Quality Status Row (Matching Target Design) -->
      <div class="hero-header-row" style="display: flex; align-items: flex-end; justify-content: flex-start; gap: clamp(24px, 6vw, 64px); margin-bottom: 18px; flex-wrap: wrap;">
        <!-- Left: Live AQI & Big Value -->
        <div style="display: flex; flex-direction: column;">
          <div id="status-dot-container" class="<?= $init_is_aqi_offline ? 'status-dot-offline' : 'status-dot-glowing' ?>" style="display: flex; align-items: center; gap: 7px; margin-bottom: 6px; color: <?= $init_aqi_accent ?>;">
            <span id="status-dot-halo" style="display: inline-flex; align-items: center; justify-content: center; width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; background-color: <?= $init_is_aqi_offline ? 'rgba(148, 163, 184, 0.20)' : $init_aqi_accent . '33' ?>; color: <?= $init_is_aqi_offline ? '#94a3b8' : $init_aqi_accent ?>; transition: background-color 0.5s ease, color 0.5s ease;">
              <span id="status-dot" style="width: 6.5px; height: 6.5px; border-radius: 50%; background-color: <?= $init_is_aqi_offline ? '#94a3b8' : $init_aqi_accent ?>; color: <?= $init_is_aqi_offline ? '#94a3b8' : $init_aqi_accent ?>; display: inline-block; transition: background-color 0.5s ease, color 0.5s ease;"></span>
            </span>
            <span id="hero-stream-text" style="font-size: 13px; font-weight: 700; color: #334155; font-family: var(--font-sans); letter-spacing: -0.01em;"><?= $init_is_aqi_offline ? 'Cached AQI' : 'Live AQI' ?></span>
          </div>
          <div style="display: flex; align-items: baseline; gap: 8px;">
            <div id="hero-aqi-val" style="font-family: var(--font-mono); font-size: 74px; font-weight: 900; line-height: 0.95; letter-spacing: -0.04em; color: <?= $init_aqi_accent ?>; transition: color 0.5s ease;">
              <?= $init_aqi_val ?>
            </div>
            <span id="hero-aqi-subtext" style="font-size: 13px; font-weight: 700; color: #64748b; font-family: var(--font-mono); text-transform: uppercase; letter-spacing: 0.02em; white-space: nowrap;">AQI (IN)</span>
          </div>
        </div>

        <!-- Right: Air Quality is & Rounded Status Box -->
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: flex-end;">
          <div id="hero-aqi-title" style="font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 5px; font-family: var(--font-sans); text-align: center;">Air Quality is</div>
          <div id="hero-aqi-badge" style="display: inline-flex; align-items: center; justify-content: center; min-width: 100px; padding: 6px 16px; border-radius: 12px; background: <?= $init_aqi_badge_bg ?>; border: 1.5px solid <?= $init_aqi_badge_border ?>; box-shadow: 0 2px 8px rgba(0,0,0,0.03); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); transition: background 0.5s ease, border-color 0.5s ease;">
            <span id="hero-aqi-label" style="font-size: 18px; font-weight: 800; color: <?= $init_aqi_badge_text ?>; font-family: var(--font-sans); line-height: 1.15; letter-spacing: -0.01em; transition: color 0.5s ease;"><?= $init_aqi_label ?></span>
          </div>
        </div>
      </div>

      <!-- Quick PM2.5 & PM10 Row (Matching Target Visual) -->
      <div class="hero-pm-row" style="display: flex; align-items: baseline; justify-content: space-between; max-width: 440px; width: 100%; margin-bottom: 16px; font-size: 14px; font-weight: 700;">
        <div style="display: flex; align-items: baseline; gap: 6px;">
          <span style="color: #1e3a5f; font-weight: 800; letter-spacing: -0.01em;">PM2.5 :</span>
          <strong id="hero-pm25" style="color: #0f172a; font-size: 16px; font-family: var(--font-mono); font-weight: 800;"><?= $init_pm25 ?></strong>
          <span class="hero-pm-unit" style="font-size: 11px; color: #475569; font-weight: 600;">µg/m³</span>
        </div>
        <div style="display: flex; align-items: baseline; gap: 6px;">
          <span style="color: #1e3a5f; font-weight: 800; letter-spacing: -0.01em;">PM10 :</span>
          <strong id="hero-pm10" style="color: #0f172a; font-size: 16px; font-family: var(--font-mono); font-weight: 800;"><?= $init_pm10 ?></strong>
          <span class="hero-pm-unit" style="font-size: 11px; color: #475569; font-weight: 600;">µg/m³</span>
        </div>
      </div>

      <!-- Overall AQI Scale Bar -->
      <div class="hero-scale-bar-container" style="max-width: 440px; width: 100%;">
        <div class="hero-scale-labels" style="display: grid; grid-template-columns: repeat(6, 1fr); padding: 0; margin-bottom: 8px;">
          <span class="scale-label" style="font-size: 11px; font-weight: 700; color: #334155; line-height: 1.1; text-align: center; white-space: nowrap;">Good</span>
          <span class="scale-label" style="font-size: 11px; font-weight: 700; color: #334155; line-height: 1.1; text-align: center; white-space: nowrap;">Moderate</span>
          <span class="scale-label" style="font-size: 11px; font-weight: 700; color: #334155; line-height: 1.1; text-align: center; white-space: nowrap;">Poor</span>
          <span class="scale-label" style="font-size: 11px; font-weight: 700; color: #334155; line-height: 1.1; text-align: center; white-space: nowrap;">Unhealthy</span>
          <span class="scale-label" style="font-size: 11px; font-weight: 700; color: #334155; line-height: 1.1; text-align: center; white-space: nowrap;">Severe</span>
          <span class="scale-label" style="font-size: 11px; font-weight: 700; color: #334155; line-height: 1.1; text-align: center; white-space: nowrap;">Hazardous</span>
        </div>

        <div class="hero-scale-bar-track" style="position: relative; height: 7px; border-radius: 999px; background-color: #e2e8f0; margin-bottom: 8px;">
          <div style="width: 100%; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #22c55e 0%, #22c55e 16.666%, #eab308 16.666%, #eab308 33.333%, #f97316 33.333%, #f97316 50%, #ef4444 50%, #ef4444 66.666%, #a855f7 66.666%, #a855f7 83.333%, #b91c1c 83.333%, #b91c1c 100%); box-shadow: inset 0 1px 2px rgba(0,0,0,0.06);"></div>
          <div id="hero-scale-indicator" style="position: absolute; top: 50%; left: <?= $init_scale_pct ?>; transform: translate(-50%, -50%); width: 17px; height: 17px; border-radius: 50%; background-color: #ffffff; border: 3.5px solid <?= $init_aqi_accent ?>; box-shadow: 0 1px 4px rgba(0,0,0,0.25); z-index: 3; transition: left 0.5s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.3s ease;"></div>
        </div>

        <div class="hero-scale-ticks" style="position: relative; height: 16px;">
          <span style="position: absolute; left: 0%; font-size: 10.5px; font-weight: 700; color: #475569; font-family: var(--font-mono); line-height: 1;">0</span>
          <span style="position: absolute; left: 16.666%; transform: translateX(-50%); font-size: 10.5px; font-weight: 700; color: #475569; font-family: var(--font-mono); line-height: 1;">50</span>
          <span style="position: absolute; left: 33.333%; transform: translateX(-50%); font-size: 10.5px; font-weight: 700; color: #475569; font-family: var(--font-mono); line-height: 1;">100</span>
          <span style="position: absolute; left: 50%; transform: translateX(-50%); font-size: 10.5px; font-weight: 700; color: #475569; font-family: var(--font-mono); line-height: 1;">200</span>
          <span style="position: absolute; left: 66.666%; transform: translateX(-50%); font-size: 10.5px; font-weight: 700; color: #475569; font-family: var(--font-mono); line-height: 1;">300</span>
          <span style="position: absolute; left: 83.333%; transform: translateX(-50%); font-size: 10.5px; font-weight: 700; color: #475569; font-family: var(--font-mono); line-height: 1;">400</span>
          <span style="position: absolute; right: 0%; font-size: 10.5px; font-weight: 700; color: #475569; font-family: var(--font-mono); line-height: 1;">500+</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Instant Synchronous Hydration from LocalStorage (0ms Shield to guarantee zero flash/shift on mobile) -->
  <script>
  (function() {
    try {
      var raw = localStorage.getItem('aqi_last_known_hero');
      if (!raw) return;
      var h = JSON.parse(raw);
      if (!h || !h.aqi) return;

      var card = document.getElementById('hero-card');
      if (card && h.bg) {
        card.style.background = h.bg;
        if (h.border) card.style.borderColor = h.border;
        if (h.shadow) card.style.boxShadow = '0 10px 30px ' + h.shadow + ', 0 1px 3px rgba(15,23,42,0.04)';
      }
      var valEl = document.getElementById('hero-aqi-val');
      if (valEl) {
        valEl.innerText = h.aqi;
        if (h.accent) valEl.style.color = h.accent;
      }
      var labelEl = document.getElementById('hero-aqi-label');
      if (labelEl && h.label) {
        labelEl.innerText = h.label;
        if (h.badgeText) labelEl.style.color = h.badgeText;
      }
      var badgeEl = document.getElementById('hero-aqi-badge');
      if (badgeEl && h.badgeBg) {
        badgeEl.style.background = h.badgeBg;
        if (h.badgeBorder) badgeEl.style.borderColor = h.badgeBorder;
      }
      var dot = document.getElementById('status-dot');
      if (dot && h.accent) {
        dot.style.backgroundColor = h.accent;
        dot.style.color = h.accent;
      }
      var dotHalo = document.getElementById('status-dot-halo');
      if (dotHalo && h.accent) {
        dotHalo.style.backgroundColor = h.accent + '33';
        dotHalo.style.color = h.accent;
      }
      var statusContainer = document.getElementById('status-dot-container');
      if (statusContainer && h.accent) {
        statusContainer.style.color = h.accent;
      }
      var indicator = document.getElementById('hero-scale-indicator');
      if (indicator && h.scalePct) {
        indicator.style.left = h.scalePct;
        if (h.accent) indicator.style.borderColor = h.accent;
      }
      var skyline = document.getElementById('hero-skyline-svg');
      if (skyline && h.accent) {
        skyline.style.fill = h.accent;
      }
      var pm25El = document.getElementById('hero-pm25');
      if (pm25El && h.pm25) pm25El.innerText = h.pm25;
      var pm10El = document.getElementById('hero-pm10');
      if (pm10El && h.pm10) pm10El.innerText = h.pm10;
    } catch(e) {}
  })();
  </script>

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
      <div class="pollutant-card flex flex-col justify-between gap-2.5 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 cursor-pointer shadow-[0_4px_16px_rgba(15,23,42,0.04)] hover:border-[#00bfa5] hover:-translate-y-1 transition-all duration-200 relative overflow-hidden" onclick="openPollutantModal('pm25')">
        <div class="flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <div class="pollutant-icon-box w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-sky-50 text-sky-500 flex items-center justify-center text-sm sm:text-lg shrink-0">🌫️</div>
            <div>
              <div class="pollutant-name text-[13px] sm:text-[15px] font-bold font-sans text-slate-900 leading-tight">PM₂.₅</div>
              <div class="pollutant-who text-[9px] sm:text-[10.5px] text-slate-500 mt-[2px] whitespace-nowrap">CPCB: ≤60</div>
            </div>
          </div>
          <div class="pollutant-info-icon w-6 h-6 rounded-full bg-slate-50 border border-slate-300 flex items-center justify-center text-slate-500 shrink-0">
            <i data-lucide="info" class="w-3 h-3"></i>
          </div>
        </div>
        <div class="flex items-end justify-between w-full mt-1">
          <div>
            <div id="card-pm25" class="font-mono text-lg sm:text-2xl font-extrabold text-sky-500 leading-none"><?= $init_pollutants['pm25'] ?></div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">µg/m³</div>
          </div>
          <div id="tag-pm25" class="px-2 py-1 rounded-full text-[9px] sm:text-[11px] font-bold flex items-center gap-1" style="<?= $init_tags['pm25']['show'] ? '' : 'display: none;' ?> background-color: <?= $init_tags['pm25']['bg'] ?>; border: 1px solid <?= $init_tags['pm25']['border'] ?>; color: <?= $init_tags['pm25']['color'] ?>;">
            <span class="w-1.5 h-1.5 rounded-full" style="background-color: <?= $init_tags['pm25']['dot'] ?>;"></span>
            <span><?= $init_tags['pm25']['text'] ?></span>
          </div>
        </div>
      </div>

      <!-- PM10 -->
      <div class="pollutant-card flex flex-col justify-between gap-2.5 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 cursor-pointer shadow-[0_4px_16px_rgba(15,23,42,0.04)] hover:border-[#00bfa5] hover:-translate-y-1 transition-all duration-200 relative overflow-hidden" onclick="openPollutantModal('pm10')">
        <div class="flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <div class="pollutant-icon-box w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-indigo-50 text-indigo-500 flex items-center justify-center text-sm sm:text-lg shrink-0">☁️</div>
            <div>
              <div class="pollutant-name text-[13px] sm:text-[15px] font-bold font-sans text-slate-900 leading-tight">PM₁₀</div>
              <div class="pollutant-who text-[9px] sm:text-[10.5px] text-slate-500 mt-[2px] whitespace-nowrap">CPCB: ≤100</div>
            </div>
          </div>
          <div class="pollutant-info-icon w-6 h-6 rounded-full bg-slate-50 border border-slate-300 flex items-center justify-center text-slate-500 shrink-0">
            <i data-lucide="info" class="w-3 h-3"></i>
          </div>
        </div>
        <div class="flex items-end justify-between w-full mt-1">
          <div>
            <div id="card-pm10" class="font-mono text-lg sm:text-2xl font-extrabold text-indigo-500 leading-none"><?= $init_pollutants['pm10'] ?></div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">µg/m³</div>
          </div>
          <div id="tag-pm10" class="px-2 py-1 rounded-full text-[9px] sm:text-[11px] font-bold flex items-center gap-1" style="<?= $init_tags['pm10']['show'] ? '' : 'display: none;' ?> background-color: <?= $init_tags['pm10']['bg'] ?>; border: 1px solid <?= $init_tags['pm10']['border'] ?>; color: <?= $init_tags['pm10']['color'] ?>;">
            <span class="w-1.5 h-1.5 rounded-full" style="background-color: <?= $init_tags['pm10']['dot'] ?>;"></span>
            <span><?= $init_tags['pm10']['text'] ?></span>
          </div>
        </div>
      </div>

      <!-- CO -->
      <div class="pollutant-card flex flex-col justify-between gap-2.5 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 cursor-pointer shadow-[0_4px_16px_rgba(15,23,42,0.04)] hover:border-[#00bfa5] hover:-translate-y-1 transition-all duration-200 relative overflow-hidden" onclick="openPollutantModal('co')">
        <div class="flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <div class="pollutant-icon-box w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-slate-50 text-slate-600 flex items-center justify-center text-sm sm:text-lg shrink-0">💨</div>
            <div>
              <div class="pollutant-name text-[13px] sm:text-[15px] font-bold font-sans text-slate-900 leading-tight">CO</div>
              <div class="pollutant-who text-[9px] sm:text-[10.5px] text-slate-500 mt-[2px] whitespace-nowrap">CPCB: ≤2</div>
            </div>
          </div>
          <div class="pollutant-info-icon w-6 h-6 rounded-full bg-slate-50 border border-slate-300 flex items-center justify-center text-slate-500 shrink-0">
            <i data-lucide="info" class="w-3 h-3"></i>
          </div>
        </div>
        <div class="flex items-end justify-between w-full mt-1">
          <div>
            <div id="card-co" class="font-mono text-lg sm:text-2xl font-extrabold text-slate-600 leading-none"><?= $init_pollutants['co'] ?></div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">mg/m³</div>
          </div>
          <div id="tag-co" class="px-2 py-1 rounded-full text-[9px] sm:text-[11px] font-bold flex items-center gap-1" style="<?= $init_tags['co']['show'] ? '' : 'display: none;' ?> background-color: <?= $init_tags['co']['bg'] ?>; border: 1px solid <?= $init_tags['co']['border'] ?>; color: <?= $init_tags['co']['color'] ?>;">
            <span class="w-1.5 h-1.5 rounded-full" style="background-color: <?= $init_tags['co']['dot'] ?>;"></span>
            <span><?= $init_tags['co']['text'] ?></span>
          </div>
        </div>
      </div>

      <!-- NO2 -->
      <div class="pollutant-card flex flex-col justify-between gap-2.5 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 cursor-pointer shadow-[0_4px_16px_rgba(15,23,42,0.04)] hover:border-[#00bfa5] hover:-translate-y-1 transition-all duration-200 relative overflow-hidden" onclick="openPollutantModal('no2')">
        <div class="flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <div class="pollutant-icon-box w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-purple-50 text-purple-500 flex items-center justify-center text-sm sm:text-lg shrink-0">🏭</div>
            <div>
              <div class="pollutant-name text-[13px] sm:text-[15px] font-bold font-sans text-slate-900 leading-tight">NO₂</div>
              <div class="pollutant-who text-[9px] sm:text-[10.5px] text-slate-500 mt-[2px] whitespace-nowrap">CPCB: ≤80</div>
            </div>
          </div>
          <div class="pollutant-info-icon w-6 h-6 rounded-full bg-slate-50 border border-slate-300 flex items-center justify-center text-slate-500 shrink-0">
            <i data-lucide="info" class="w-3 h-3"></i>
          </div>
        </div>
        <div class="flex items-end justify-between w-full mt-1">
          <div>
            <div id="card-no2" class="font-mono text-lg sm:text-2xl font-extrabold text-purple-500 leading-none"><?= $init_pollutants['no2'] ?></div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">µg/m³</div>
          </div>
          <div id="tag-no2" class="px-2 py-1 rounded-full text-[9px] sm:text-[11px] font-bold flex items-center gap-1" style="<?= $init_tags['no2']['show'] ? '' : 'display: none;' ?> background-color: <?= $init_tags['no2']['bg'] ?>; border: 1px solid <?= $init_tags['no2']['border'] ?>; color: <?= $init_tags['no2']['color'] ?>;">
            <span class="w-1.5 h-1.5 rounded-full" style="background-color: <?= $init_tags['no2']['dot'] ?>;"></span>
            <span><?= $init_tags['no2']['text'] ?></span>
          </div>
        </div>
      </div>

      <!-- O3 -->
      <div class="pollutant-card flex flex-col justify-between gap-2.5 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 text-slate-900 cursor-pointer shadow-[0_4px_16px_rgba(15,23,42,0.04)] hover:border-[#00bfa5] hover:-translate-y-1 transition-all duration-200 relative overflow-hidden" onclick="openPollutantModal('o3')">
        <div class="flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <div class="pollutant-icon-box w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center text-sm sm:text-lg shrink-0">☀️</div>
            <div>
              <div class="pollutant-name text-[13px] sm:text-[15px] font-bold font-sans text-slate-900 leading-tight">O₃</div>
              <div class="pollutant-who text-[9px] sm:text-[10.5px] text-slate-500 mt-[2px] whitespace-nowrap">CPCB: ≤100</div>
            </div>
          </div>
          <div class="pollutant-info-icon w-6 h-6 rounded-full bg-slate-50 border border-slate-300 flex items-center justify-center text-slate-500 shrink-0">
            <i data-lucide="info" class="w-3 h-3"></i>
          </div>
        </div>
        <div class="flex items-end justify-between w-full mt-1">
          <div>
            <div id="card-o3" class="font-mono text-lg sm:text-2xl font-extrabold text-amber-500 leading-none"><?= $init_pollutants['o3'] ?></div>
            <div class="pollutant-unit text-[9px] sm:text-[11px] font-semibold text-slate-500 mt-1 font-mono">µg/m³</div>
          </div>
          <div id="tag-o3" class="px-2 py-1 rounded-full text-[9px] sm:text-[11px] font-bold flex items-center gap-1" style="<?= $init_tags['o3']['show'] ? '' : 'display: none;' ?> background-color: <?= $init_tags['o3']['bg'] ?>; border: 1px solid <?= $init_tags['o3']['border'] ?>; color: <?= $init_tags['o3']['color'] ?>;">
            <span class="w-1.5 h-1.5 rounded-full" style="background-color: <?= $init_tags['o3']['dot'] ?>;"></span>
            <span><?= $init_tags['o3']['text'] ?></span>
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
        <div class="aqi-scale-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #f0fdf4; border: 1px solid #bbf7d0; gap: 12px; flex-wrap: wrap;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span class="scale-dot" style="width: 12px; height: 12px; border-radius: 4px; background-color: #59b61f; flex-shrink: 0;"></span>
            <div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span class="scale-title" style="font-size: 14px; font-weight: 700; color: #0f172a;">Good</span>
                <span class="scale-range" style="font-size: 12px; font-weight: 700; color: #59b61f; font-family: var(--font-mono);">(0 to 50)</span>
              </div>
              <div class="scale-desc" style="font-size: 11.5px; color: #475569; margin-top: 2px;">Air quality is considered satisfactory, and air pollution poses little or no risk.</div>
            </div>
          </div>
        </div>
        
        <div class="aqi-scale-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #fefce8; border: 1px solid #fef08a; gap: 12px; flex-wrap: wrap;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span class="scale-dot" style="width: 12px; height: 12px; border-radius: 4px; background-color: #eec732; flex-shrink: 0;"></span>
            <div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span class="scale-title" style="font-size: 14px; font-weight: 700; color: #0f172a;">Moderate</span>
                <span class="scale-range" style="font-size: 12px; font-weight: 700; color: #854d0e; font-family: var(--font-mono);">(51 to 100)</span>
              </div>
              <div class="scale-desc" style="font-size: 11.5px; color: #475569; margin-top: 2px;">Acceptable air quality; minor breathing discomfort may occur for sensitive individuals.</div>
            </div>
          </div>
        </div>

        <div class="aqi-scale-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #fff7ed; border: 1px solid #fed7aa; gap: 12px; flex-wrap: wrap;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span class="scale-dot" style="width: 12px; height: 12px; border-radius: 4px; background-color: #ea8c34; flex-shrink: 0;"></span>
            <div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span class="scale-title" style="font-size: 14px; font-weight: 700; color: #0f172a;">Poor</span>
                <span class="scale-range" style="font-size: 12px; font-weight: 700; color: #ea8c34; font-family: var(--font-mono);">(101 to 200)</span>
              </div>
              <div class="scale-desc" style="font-size: 11.5px; color: #475569; margin-top: 2px;">Breathing discomfort to people with lungs, asthma, and heart diseases.</div>
            </div>
          </div>
        </div>

        <div class="aqi-scale-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #fef2f2; border: 1px solid #fecaca; gap: 12px; flex-wrap: wrap;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span class="scale-dot" style="width: 12px; height: 12px; border-radius: 4px; background-color: #e95478; flex-shrink: 0;"></span>
            <div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span class="scale-title" style="font-size: 14px; font-weight: 700; color: #0f172a;">Unhealthy</span>
                <span class="scale-range" style="font-size: 12px; font-weight: 700; color: #e95478; font-family: var(--font-mono);">(201 to 300)</span>
              </div>
              <div class="scale-desc" style="font-size: 11.5px; color: #475569; margin-top: 2px;">Breathing discomfort to most people on prolonged exposure. Limit strenuous outdoor exertion.</div>
            </div>
          </div>
        </div>

        <div class="aqi-scale-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #faf5ff; border: 1px solid #e9d5ff; gap: 12px; flex-wrap: wrap;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span class="scale-dot" style="width: 12px; height: 12px; border-radius: 4px; background-color: #b33fba; flex-shrink: 0;"></span>
            <div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span class="scale-title" style="font-size: 14px; font-weight: 700; color: #0f172a;">Severe</span>
                <span class="scale-range" style="font-size: 12px; font-weight: 700; color: #b33fba; font-family: var(--font-mono);">(301 to 400)</span>
              </div>
              <div class="scale-desc" style="font-size: 11.5px; color: #475569; margin-top: 2px;">Respiratory illness on prolonged exposure; significantly impacts people with existing ailments.</div>
            </div>
          </div>
        </div>

        <div class="aqi-scale-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 14px; background-color: #fff1f2; border: 1px solid #fecdd3; gap: 12px; flex-wrap: wrap;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span class="scale-dot" style="width: 12px; height: 12px; border-radius: 4px; background-color: #c92033; flex-shrink: 0;"></span>
            <div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span class="scale-title" style="font-size: 14px; font-weight: 700; color: #0f172a;">Hazardous</span>
                <span class="scale-range" style="font-size: 12px; font-weight: 700; color: #c92033; font-family: var(--font-mono);">(401+)</span>
              </div>
              <div class="scale-desc" style="font-size: 11.5px; color: #475569; margin-top: 2px;">May cause serious health impacts on entire population. Wear N95 masks and stay indoors.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

</div>

<?php include __DIR__ . '/weather.php'; ?>

</div>

<?php if (!empty($init_bulk)): ?>
<script>
window.INITIAL_DATA = <?= json_encode($init_bulk, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<?php endif; ?>

<script>
function switchLiveTab(tab) {
    if (window.triggerHaptic) window.triggerHaptic('light');
    const aqiBtn = document.getElementById('tab-aqi');
    const weatherBtn = document.getElementById('tab-weather');
    const aqiView = document.getElementById('aqi-view');
    const weatherView = document.getElementById('weather-view');

    try {
        sessionStorage.setItem('live_active_tab', tab);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        history.replaceState(null, '', url.toString());
    } catch(e) {}

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

var pollutantDetails = {
    pm25: {
        id: 'pm25', title: 'Particulate Matter 2.5 (PM2.5)', icon: '🌫️', limit: 60, unit: 'µg/m³',
        scale: [30, 60, 90, 120, 250],
        maxTick: '250+',
        desc: 'Fine inhalable particles with diameters 2.5 micrometers or smaller (about 30 times smaller than a human hair). Due to their minute size, they penetrate deep into lung alveoli and enter the circulatory system.',
        health: 'Causes respiratory inflammation, triggers asthma attacks, increases risk of heart disease, stroke, and lung cancer. Short-term exposure causes eye, nose, and throat irritation.'
    },
    pm10: {
        id: 'pm10', title: 'Particulate Matter 10 (PM10)', icon: '☁️', limit: 100, unit: 'µg/m³',
        scale: [50, 100, 250, 350, 430],
        maxTick: '430+',
        desc: 'Inhalable particles with diameters that are generally 10 micrometers and smaller. Sources include crushing or grinding operations and dust stirred up by vehicles on roads.',
        health: 'Can irritate the eyes, nose, and throat. Reduces lung function, exacerbates asthma and other respiratory diseases, and increases susceptibility to respiratory infections.'
    },
    co: {
        id: 'co', title: 'Carbon Monoxide (CO)', icon: '💨', limit: 2, unit: 'mg/m³',
        scale: [1, 2, 10, 17, 34],
        maxTick: '34+',
        desc: 'A colorless, odorless gas that can be harmful when inhaled in large amounts. CO is released when something is burned, primarily from vehicle exhaust.',
        health: 'Reduces oxygen delivery to the body\'s organs (like the heart and brain). Can cause headaches, dizziness, vomiting, and nausea. High levels can cause unconsciousness or death.'
    },
    no2: {
        id: 'no2', title: 'Nitrogen Dioxide (NO₂)', icon: '🏭', limit: 80, unit: 'µg/m³',
        scale: [40, 80, 180, 280, 400],
        maxTick: '400+',
        desc: 'A highly reactive gas that forms quickly from emissions from cars, trucks and buses, power plants, and off-road equipment.',
        health: 'Irritates airways in the human respiratory system. Aggravates respiratory diseases, particularly asthma, leading to coughing, wheezing or difficulty breathing.'
    },
    o3: {
        id: 'o3', title: 'Ground-level Ozone (O₃)', icon: '☀️', limit: 100, unit: 'µg/m³',
        scale: [50, 100, 168, 208, 748],
        maxTick: '748+',
        desc: 'Not emitted directly into the air, but created by chemical reactions between oxides of nitrogen (NOx) and volatile organic compounds (VOC) in the presence of sunlight.',
        health: 'Can trigger a variety of health problems including chest pain, coughing, throat irritation, and airway inflammation. It can worsen bronchitis, emphysema, and asthma.'
    }
};

function getPollutantScaleInfo(param, value) {
    const data = pollutantDetails[param];
    if (!data || value == null || isNaN(parseFloat(value))) {
        return { pct: 8, color: '#59b61f', label: 'Good' };
    }
    const v = parseFloat(value);
    const s = data.scale;
    const colors = ['#59b61f', '#eec732', '#ea8c34', '#e95478', '#b33fba', '#c92033'];
    const labels = ['Good', 'Moderate', 'Poor', 'Unhealthy', 'Severe', 'Hazardous'];
    let pct = 0;
    let tier = 0;
    
    if (v <= s[0]) {
        pct = (v / s[0]) * 16.666;
        tier = 0;
    } else if (v <= s[1]) {
        pct = 16.666 + ((v - s[0]) / (s[1] - s[0])) * 16.667;
        tier = 1;
    } else if (v <= s[2]) {
        pct = 33.333 + ((v - s[1]) / (s[2] - s[1])) * 16.667;
        tier = 2;
    } else if (v <= s[3]) {
        pct = 50.0 + ((v - s[2]) / (s[3] - s[2])) * 16.666;
        tier = 3;
    } else if (v <= s[4]) {
        pct = 66.666 + ((v - s[3]) / (s[4] - s[3])) * 16.667;
        tier = 4;
    } else {
        pct = 83.333 + Math.min(1.0, (v - s[4]) / s[4]) * 15.667;
        tier = 5;
    }
    pct = Math.min(Math.max(pct, 2), 98);
    return { pct, color: colors[tier], label: labels[tier] };
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
    const canvas = document.getElementById('comparison-chart');
    if (!canvas) return;

    if (typeof Chart === 'undefined') {
        setTimeout(renderComparisonChart, 150);
        return;
    }
    
    if (liveDataStream.length === 0) {
        if (comparisonChartInstance) {
            try { comparisonChartInstance.destroy(); } catch(e) {}
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

    // Reverse the array to show chronological order (left to right)
    const sortedData = [...liveDataStream].reverse();
    const labels = sortedData.map(d => {
        const dt = new Date(d.created_at || d.timestamp || new Date());
        return dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'});
    });

    const isSinglePoint = sortedData.length === 1;
    const datasets = [];
    if (visiblePollutants.pm25) datasets.push({ label: 'PM2.5', data: sortedData.map(d => d.pm25), borderColor: pollutantColors.pm25, backgroundColor: 'transparent', pointRadius: 0, pointHoverRadius: 4, borderWidth: 2 });
    if (visiblePollutants.pm10) datasets.push({ label: 'PM10', data: sortedData.map(d => d.pm10), borderColor: pollutantColors.pm10, backgroundColor: 'transparent', pointRadius: 0, pointHoverRadius: 4, borderWidth: 2 });
    if (visiblePollutants.co) datasets.push({ label: 'CO', data: sortedData.map(d => d.co), borderColor: pollutantColors.co, backgroundColor: 'transparent', pointRadius: 0, pointHoverRadius: 4, borderWidth: 2 });
    if (visiblePollutants.no2) datasets.push({ label: 'NO2', data: sortedData.map(d => d.no2), borderColor: pollutantColors.no2, backgroundColor: 'transparent', pointRadius: 0, pointHoverRadius: 4, borderWidth: 2 });
    if (visiblePollutants.o3) datasets.push({ label: 'O3', data: sortedData.map(d => d.o3), borderColor: pollutantColors.o3, backgroundColor: 'transparent', pointRadius: 0, pointHoverRadius: 4, borderWidth: 2 });

    if (comparisonChartInstance && comparisonChartInstance.data && comparisonChartInstance.update) {
        try {
            comparisonChartInstance.data.labels = labels;
            comparisonChartInstance.data.datasets = datasets;
            comparisonChartInstance.update('none');
            return;
        } catch (chartErr) {
            console.warn('Chart update failed, recreating:', chartErr);
            try { comparisonChartInstance.destroy(); } catch(e) {}
            comparisonChartInstance = null;
        }
    } else if (comparisonChartInstance) {
        try { comparisonChartInstance.destroy(); } catch(e) {}
        comparisonChartInstance = null;
    }

    // Safely destroy existing chart attached to canvas if any
    try {
        if (typeof Chart.getChart === 'function') {
            const attached = Chart.getChart(canvas);
            if (attached) {
                attached.destroy();
            }
        }
    } catch(e) {}

    try {
        comparisonChartInstance = new Chart(canvas, {
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
    } catch (createErr) {
        console.error('Failed to create comparisonChartInstance:', createErr);
    }
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

function formatStreamTimestamp(row) {
    const rawTs = row.created_at || row.timestamp;
    if (!rawTs) return '--';
    const dt = new Date(rawTs);
    
    // Strict IST formatting: "22/10/2026, 06:49 pm" without seconds
    const istDate = dt.toLocaleDateString('en-GB', { timeZone: 'Asia/Kolkata' });
    const istTime = dt.toLocaleTimeString('en-US', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit' }).toLowerCase();
    
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

async function refreshDashboard(forceNetwork = false) {
    try {
        let bulkData;
        let isFromCache = false;
        const memoryCache = window.INITIAL_DATA || (window.__apiCache && (window.__apiCache.liveBulk || window.__apiCache.overviewBulk)
            ? (window.__apiCache.liveBulk || window.__apiCache.overviewBulk).data
            : null);

        if (!forceNetwork && memoryCache && (memoryCache.aqi || memoryCache.cpcb_aqi)) {
            bulkData = memoryCache.aqi ? memoryCache : { aqi: memoryCache, aqi_history: [memoryCache] };
            if (!bulkData.aqi_history && bulkData.history) {
                bulkData.aqi_history = bulkData.history;
            }
            window.INITIAL_DATA = null;
            isFromCache = true;
            if (window.__apiCache && bulkData) {
                window.__apiCache.liveBulk = { data: bulkData, time: Date.now() };
                window.__apiCache.overviewBulk = { data: bulkData, time: Date.now() };
            }
            // Instantly schedule live network sync in background so data is up to the current second without delay
            setTimeout(() => {
                refreshDashboard(true);
            }, 60);
        } else {
            const endpoint = 'telemetry.php?action=live_bulk&limit=60';
            const fetchUrl = (typeof window.getApiUrl === 'function') ? window.getApiUrl(endpoint) : ('api/' + endpoint);
            const res = await fetch(fetchUrl);
            bulkData = await res.json();
            if (window.__apiCache && bulkData && bulkData.aqi) {
                window.__apiCache.liveBulk = { data: bulkData, time: Date.now() };
                window.__apiCache.overviewBulk = { data: bulkData, time: Date.now() };
            }
        }
        
        const aqiData = bulkData.aqi;
        const weatherData = bulkData.weather;
        const weatherHistory = bulkData.weather_history;
        const historyData = bulkData.aqi_history;
        let currentAQITheme = null;

        // Precision non-overlapping positioning inside each of the 6 color segments
        // Ensures indicator is completely situated inside the matching tier color:
        // Bar segments (equal 16.666% each): Good 0-50 | Moderate 51-100 | Poor 101-200 | Unhealthy 201-300 | Severe 301-400 | Hazardous 401-500+
        function getAQIScalePercentage(aqi) {
            const v = Number(aqi);
            if (isNaN(v) || v <= 0) return 2;
            if (v <= 50)  return Math.max(2, (v / 50) * 16.666);                    // 0-50 -> 0% to 16.666% (Good - Green)
            if (v <= 100) return 16.666 + ((v - 50) / 50) * 16.667;                // 51-100 -> 16.666% to 33.333% (Moderate - Yellow)
            if (v <= 200) return 33.333 + ((v - 100) / 100) * 16.667;              // 101-200 -> 33.333% to 50.0% (Poor - Orange)
            if (v <= 300) return 50.0 + ((v - 200) / 100) * 16.666;                // 201-300 -> 50.0% to 66.666% (Unhealthy - Rose/Red)
            if (v <= 400) return 66.666 + ((v - 300) / 100) * 16.667;              // 301-400 -> 66.666% to 83.333% (Severe - Purple)
            return Math.min(98, 83.333 + Math.min((v - 400) / 100, 1) * 15.667);   // 401-500+ -> 83.333% to 98% (Hazardous - Crimson)
        }

        // AQI category themes matching the exact bar colors and labels with light-to-dark contrast
        function getAQITheme(aqi) {
            const v = Number(aqi);
            if (isNaN(v) || v <= 50) {
                return {
                    label: 'Good',
                    bg: 'linear-gradient(155deg, #ffffff 0%, #f0fdf4 25%, #dcfce7 55%, #86efac 80%, #34d399 92%, #10b981 100%)',
                    border: 'rgba(22, 163, 74, 0.35)',
                    shadow: 'rgba(16, 185, 129, 0.20)',
                    accent: '#16a34a',
                    badgeBg: 'rgba(255, 255, 255, 0.75)',
                    badgeBorder: 'rgba(22, 163, 74, 0.35)',
                    badgeText: '#15803d'
                };
            }
            if (v <= 100) {
                return {
                    label: 'Moderate',
                    bg: 'linear-gradient(155deg, #ffffff 0%, #fefce8 25%, #fef08a 52%, #fde047 78%, #eab308 92%, #ca8a04 100%)',
                    border: 'rgba(202, 138, 4, 0.35)',
                    shadow: 'rgba(234, 179, 8, 0.20)',
                    accent: '#ca8a04',
                    badgeBg: 'rgba(255, 255, 255, 0.75)',
                    badgeBorder: 'rgba(202, 138, 4, 0.38)',
                    badgeText: '#854d0e'
                };
            }
            if (v <= 200) {
                return {
                    label: 'Poor',
                    bg: 'linear-gradient(155deg, #ffffff 0%, #fff7ed 22%, #ffedd5 48%, #fed7aa 72%, #fb923c 90%, #f97316 100%)',
                    border: 'rgba(234, 88, 12, 0.38)',
                    shadow: 'rgba(249, 115, 22, 0.22)',
                    accent: '#ea580c',
                    badgeBg: 'rgba(255, 255, 255, 0.72)',
                    badgeBorder: 'rgba(234, 88, 12, 0.38)',
                    badgeText: '#c2410c'
                };
            }
            if (v <= 300) {
                return {
                    label: 'Unhealthy',
                    bg: 'linear-gradient(155deg, #ffffff 0%, #fff1f2 22%, #ffe4e6 48%, #fecdd3 72%, #fb7185 90%, #e11d48 100%)',
                    border: 'rgba(225, 29, 72, 0.38)',
                    shadow: 'rgba(225, 29, 72, 0.22)',
                    accent: '#e11d48',
                    badgeBg: 'rgba(255, 255, 255, 0.72)',
                    badgeBorder: 'rgba(225, 29, 72, 0.38)',
                    badgeText: '#9f1239'
                };
            }
            if (v <= 400) {
                return {
                    label: 'Severe',
                    bg: 'linear-gradient(155deg, #ffffff 0%, #faf5ff 22%, #f3e8ff 48%, #e9d5ff 72%, #c084fc 90%, #9333ea 100%)',
                    border: 'rgba(147, 51, 234, 0.38)',
                    shadow: 'rgba(147, 51, 234, 0.22)',
                    accent: '#9333ea',
                    badgeBg: 'rgba(255, 255, 255, 0.72)',
                    badgeBorder: 'rgba(147, 51, 234, 0.38)',
                    badgeText: '#6b21a8'
                };
            }
            return {
                label: 'Hazardous',
                bg: 'linear-gradient(155deg, #ffffff 0%, #fef2f2 22%, #fee2e2 48%, #fca5a5 72%, #f87171 90%, #b91c1c 100%)',
                border: 'rgba(185, 28, 28, 0.40)',
                shadow: 'rgba(185, 28, 28, 0.25)',
                accent: '#b91c1c',
                badgeBg: 'rgba(255, 255, 255, 0.72)',
                badgeBorder: 'rgba(185, 28, 28, 0.40)',
                badgeText: '#881337'
            };
        }
        
        if (aqiData) {
            const d = aqiData;
            const aqiValue = d.cpcb_aqi != null ? Math.round(Number(d.cpcb_aqi)) : (d.aqi_info && d.aqi_info.value != null ? Math.round(Number(d.aqi_info.value)) : '--');
            // AQI Panel Theme & Colors (dynamically changes according to AQI bar)
            const theme = getAQITheme(aqiValue);
            currentAQITheme = theme;

            const heroAqiVal = document.getElementById('hero-aqi-val');
            if (heroAqiVal) {
                heroAqiVal.innerText = aqiValue;
                heroAqiVal.style.color = theme.accent;
            }

            // Hero card — entire block changes to matching AQI bar category color
            const hero = document.getElementById('hero-card');
            const badge = document.getElementById('hero-aqi-badge');

            if (hero) {
                hero.style.background  = theme.bg;
                hero.style.borderColor = theme.border;
                hero.style.boxShadow   = `0 10px 30px ${theme.shadow}, 0 1px 3px rgba(15,23,42,0.04)`;
            }

            const heroSkyline = document.getElementById('hero-skyline-svg');
            if (heroSkyline) heroSkyline.style.fill = theme.accent;

            const statusDot = document.getElementById('status-dot');
            if (statusDot) {
                statusDot.style.backgroundColor = theme.accent;
                statusDot.style.color = theme.accent;
            }

            const statusDotHalo = document.getElementById('status-dot-halo');
            if (statusDotHalo) {
                statusDotHalo.style.backgroundColor = theme.accent + '33';
                statusDotHalo.style.color = theme.accent;
            }

            const statusDotContainer = document.getElementById('status-dot-container');
            if (statusDotContainer) {
                statusDotContainer.style.color = theme.accent;
            }

            if (badge) {
                badge.style.background  = theme.badgeBg;
                badge.style.borderColor = theme.badgeBorder;
            }
            const badgeLabel = document.getElementById('hero-aqi-label');
            if (badgeLabel) {
                badgeLabel.innerText = theme.label;
                badgeLabel.style.color = theme.badgeText;
            }

            const pct = Math.min(Math.max(getAQIScalePercentage(aqiValue), 0), 100);
            const indicator = document.getElementById('hero-scale-indicator');
            if (indicator) {
                indicator.style.left = `clamp(9px, ${pct}%, calc(100% - 9px))`;
                indicator.style.borderColor = theme.accent;
            }
            
            // Populate Weather tab AQI badge
            const weatherAqiBadge = document.getElementById('weather-hero-aqi-label');
            if (weatherAqiBadge) {
                weatherAqiBadge.innerText = `AQI ${aqiValue} · ${theme.label}`;
                weatherAqiBadge.style.color           = theme.badgeText;
                weatherAqiBadge.style.borderColor     = theme.badgeBorder;
                weatherAqiBadge.style.background      = theme.badgeBg;
            }

            // Quick PM Summary
            const heroPm25 = document.getElementById('hero-pm25');
            const pm25Raw = d.pm25 ?? d['pm2.5'];
            if (heroPm25) heroPm25.innerText = pm25Raw != null && !isNaN(pm25Raw) ? Math.round(Number(pm25Raw)) : '--';
            const heroPm10 = document.getElementById('hero-pm10');
            const pm10Raw = d.pm10;
            if (heroPm10) heroPm10.innerText = pm10Raw != null && !isNaN(pm10Raw) ? Math.round(Number(pm10Raw)) : '--';

            // Cache theme state in localStorage & cookie for instant 0ms restoration on mobile reload/navigation
            try {
                const heroState = {
                    aqi: aqiValue,
                    label: theme.label,
                    accent: theme.accent,
                    bg: theme.bg,
                    border: theme.border,
                    shadow: theme.shadow,
                    badgeBg: theme.badgeBg,
                    badgeBorder: theme.badgeBorder,
                    badgeText: theme.badgeText,
                    scalePct: `clamp(9px, ${pct}%, calc(100% - 9px))`,
                    pm25: heroPm25 ? heroPm25.innerText : '',
                    pm10: heroPm10 ? heroPm10.innerText : '',
                    timestamp: d.timestamp || d.created_at || ''
                };
                localStorage.setItem('aqi_last_known_hero', JSON.stringify(heroState));
                document.cookie = "aqi_last_hero=" + encodeURIComponent(JSON.stringify(heroState)) + "; path=/; max-age=31536000; SameSite=Lax";
            } catch(e) {}

            // Cards: Display exact raw values from Supabase without forcing 0 for null
            const cardPm25 = document.getElementById('card-pm25');
            if (cardPm25) cardPm25.innerText = formatSupabaseValue(d.pm25 ?? d['pm2.5']);
            const cardPm10 = document.getElementById('card-pm10');
            if (cardPm10) cardPm10.innerText = formatSupabaseValue(d.pm10);
            const cardCo = document.getElementById('card-co');
            if (cardCo) cardCo.innerText = formatSupabaseValue(d.co);
            const cardNo2 = document.getElementById('card-no2');
            if (cardNo2) cardNo2.innerText = formatSupabaseValue(d.no2);
            const cardO3 = document.getElementById('card-o3');
            if (cardO3) cardO3.innerText = formatSupabaseValue(d.o3);

            updateTag('tag-pm25', d.pm25 ?? d['pm2.5'], 60);
            updateTag('tag-pm10', d.pm10, 100);
            updateTag('tag-co', d.co, 2);
            updateTag('tag-no2', d.no2, 80);
            updateTag('tag-o3', d.o3, 100);
        }
        
        // WEATHER DATA POPULATION
        if (weatherData) {
            const w = weatherData;
            
            const wHeroTemp = document.getElementById('weather-hero-temp');
            if (wHeroTemp) wHeroTemp.innerText = formatWeatherOneDecimal(w.temperature);
            const cardTemp = document.getElementById('card-temp');
            if (cardTemp) cardTemp.innerText = formatWeatherOneDecimal(w.temperature, '°C');
            const cardHum = document.getElementById('card-hum');
            if (cardHum) cardHum.innerText = formatWeatherOneDecimal(w.humidity, '%');
            const cardWind = document.getElementById('card-wind');
            if (cardWind) cardWind.innerText = formatWeatherOneDecimal(w.wind_speed, ' km/h');
            const cardGust = document.getElementById('card-gust');
            if (cardGust) cardGust.innerText = formatWeatherOneDecimal(w.wind_gust, ' km/h');
            const cardRain = document.getElementById('card-rain');
            if (cardRain) cardRain.innerText = formatWeatherOneDecimal(w.rain_gauge, ' mm');
            
            // Today's rainfall synchronized with overview page
            const todayRainEl = document.getElementById('card-today-rain');
            if (todayRainEl) {
                let trVal = (bulkData && bulkData.today_rain_total != null) ? Number(bulkData.today_rain_total) : 0;
                if (w.rain_gauge != null && Number(w.rain_gauge) > 0 && trVal < Number(w.rain_gauge)) {
                    trVal = Number(w.rain_gauge);
                }
                todayRainEl.innerText = trVal.toFixed(1) + ' mm';
            }

            
            const getCompassDir = (val) => {
              if (val == null) return 'N/A';
              const deg = Math.round(Number(val) % 360);
              if (isNaN(deg)) return val;
              const dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
              return dirs[Math.round(deg / 22.5) % 16];
            };
            const dir = (w.wind_direction != null && isNaN(Number(w.wind_direction))) ? w.wind_direction : getCompassDir(w.wind_direction);
            const cardDir = document.getElementById('card-dir');
            if (cardDir) cardDir.innerText = dir;

            const tempIconEl = document.getElementById('weather-temp-icon');
            if (tempIconEl) tempIconEl.remove();

            const heroLabelEl = document.getElementById('weather-hero-label');
            if (heroLabelEl) heroLabelEl.remove();
            
            const now = new Date();
            const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric', timeZone: 'Asia/Kolkata' });
            const wDateStr = document.getElementById('weather-date-str');
            if (wDateStr) wDateStr.innerText = dateStr;
        }
        
        // WEATHER HISTORY TABLE & TODAY'S TEMP RANGE
        const tbody = document.getElementById('weather-tbody');
        if (tbody) {
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
                    const wLow = document.getElementById('weather-low-temp');
                    if (wLow) wLow.innerText = min + '°';
                    const wAvg = document.getElementById('weather-avg-temp');
                    if (wAvg) wAvg.innerText = avg + '°';
                    const wHigh = document.getElementById('weather-high-temp');
                    if (wHigh) wHigh.innerText = max + '°';
                }
            } else {
                tbody.innerHTML = '<tr><td colspan="7" style="padding: 40px; text-align: center; color: #64748b;">No live weather telemetry found.</td></tr>';
            }
        }

        // LIVE AQI HISTORY (for chart and table)
        if (historyData && historyData.length > 0) {
            liveDataStream = historyData;
        }
        renderComparisonChart();
        renderLiveTable();

        const now = new Date();
        
        // --- AQI Offline Check (Sensors log every 60s; healthy threshold is 5 mins) ---
        let isAqiOffline = false;
        let aqiOfflineMinutes = 0;
        let aqiLastUpdateTimeStr = "--";
        
        if (aqiData && (aqiData.timestamp || aqiData.created_at)) {
            const rawTs = aqiData.timestamp || aqiData.created_at;
            const lastUpdate = (typeof safeParseDate === 'function') ? safeParseDate(rawTs) : new Date(rawTs);
            if (lastUpdate && !isNaN(lastUpdate.getTime())) {
                const diffMs = now - lastUpdate;
                aqiOfflineMinutes = Math.floor(diffMs / 60000);
                // Stating offline only when no data arrives for > 5 minutes AND not on initial cache pre-hydration
                if (!isFromCache) {
                    isAqiOffline = aqiOfflineMinutes > 5;
                }
                const timePart = lastUpdate.toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', hour12: true });
                aqiLastUpdateTimeStr = aqiOfflineMinutes >= 1440
                    ? lastUpdate.toLocaleDateString('en-IN', { timeZone: 'Asia/Kolkata', month: 'short', day: 'numeric' }) + ', ' + timePart
                    : timePart;
            }
        }

        const aqiBanner = document.getElementById('aqi-offline-banner');
        const heroDot = document.getElementById('status-dot');
        const heroDotHalo = document.getElementById('status-dot-halo');
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
        const liveAccent = (currentAQITheme && currentAQITheme.accent) ? currentAQITheme.accent : '#59b61f';
        const statusDotContainer = document.getElementById('status-dot-container');
        if (isAqiOffline) {
            // OFFLINE: DO NOT GLOW! Static muted dot, no pulse, no shadow
            if (statusDotContainer) {
                statusDotContainer.classList.remove('status-dot-glowing');
                statusDotContainer.classList.add('status-dot-offline');
                statusDotContainer.style.color = '#94a3b8';
            }
            if (heroDot) {
                heroDot.style.backgroundColor = '#94a3b8';
                heroDot.style.color = '#94a3b8';
                heroDot.style.boxShadow = 'none';
                heroDot.style.filter = 'none';
            }
            if (heroDotHalo) {
                heroDotHalo.style.backgroundColor = 'rgba(148, 163, 184, 0.20)';
                heroDotHalo.style.color = '#94a3b8';
                heroDotHalo.style.boxShadow = 'none';
                heroDotHalo.style.animation = 'none';
            }
            if (heroStreamText) heroStreamText.innerText = 'Cached AQI';
        } else {
            // ONLINE: GLOW! Luminous pulsing beacon in active tier color
            if (statusDotContainer) {
                statusDotContainer.classList.remove('status-dot-offline');
                statusDotContainer.classList.add('status-dot-glowing');
                statusDotContainer.style.color = liveAccent;
            }
            if (heroDot) {
                heroDot.style.backgroundColor = liveAccent;
                heroDot.style.color = liveAccent;
                heroDot.style.boxShadow = '';
                heroDot.style.filter = '';
            }
            if (heroDotHalo) {
                heroDotHalo.style.backgroundColor = liveAccent + '33';
                heroDotHalo.style.color = liveAccent;
                heroDotHalo.style.boxShadow = '';
                heroDotHalo.style.animation = '';
            }
            if (heroStreamText) heroStreamText.innerText = 'Live AQI';
        }
        if (heroAqiSubtext) {
            heroAqiSubtext.innerText = 'AQI (IN)';
            heroAqiSubtext.style.color = '#64748b';
        }
        if (heroLeftBar) heroLeftBar.style.backgroundColor = liveAccent;
        const latestTimeEl = document.getElementById('latest-time');
        if (latestTimeEl) latestTimeEl.innerText = aqiLastUpdateTimeStr;

        // --- Weather Offline Check (Sensors log every 60s; healthy threshold is 5 mins) ---
        let isWeatherOffline = false;
        let weatherOfflineMinutes = 0;
        let weatherLastUpdateTimeStr = "--";

        if (weatherData && (weatherData.timestamp || weatherData.created_at)) {
            const wRawTs = weatherData.timestamp || weatherData.created_at;
            const wLastUpdate = (typeof safeParseDate === 'function') ? safeParseDate(wRawTs) : new Date(wRawTs);
            if (wLastUpdate && !isNaN(wLastUpdate.getTime())) {
                const wDiffMs = now - wLastUpdate;
                weatherOfflineMinutes = Math.floor(wDiffMs / 60000);
                // Stating offline only when no data arrives for > 5 minutes AND not on initial cache pre-hydration
                if (!isFromCache) {
                    isWeatherOffline = weatherOfflineMinutes > 5;
                }
                const wTimePart = wLastUpdate.toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', hour12: true });
                weatherLastUpdateTimeStr = weatherOfflineMinutes >= 1440
                    ? wLastUpdate.toLocaleDateString('en-IN', { timeZone: 'Asia/Kolkata', month: 'short', day: 'numeric' }) + ', ' + wTimePart
                    : wTimePart;
            }
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
            (aqiData && (aqiData.timestamp || aqiData.created_at)) ? aqiOfflineMinutes : 99999,
            (weatherData && (weatherData.timestamp || weatherData.created_at)) ? weatherOfflineMinutes : 99999
        );
        const tip = isOverallLive
            ? 'Live telemetry streaming from cloud'
            : `Sensor offline: Last received ${minOfflineMins >= 1440 ? Math.floor(minOfflineMins/1440) + 'd ago' : (minOfflineMins >= 60 ? Math.floor(minOfflineMins/60) + 'h ago' : minOfflineMins + 'm ago')}`;
        if (window.updateGlobalLiveBadge) {
            window.updateGlobalLiveBadge(isOverallLive, tip);
        }

        // Only re-create icons on first load, not on every auto-refresh (icons don't change)
        if (!window.__liveIconsInitialized) {
            lucide.createIcons();
            window.__liveIconsInitialized = true;
        }
    } catch (e) {
        console.error("Live page error:", e);
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
    // Remove any reparented pollutant modal from document.body to prevent duplicate modal leaks
    const bodyModals = document.querySelectorAll('body > #pollutant-modal');
    bodyModals.forEach(m => m.remove());
    document.body.style.overflow = '';
};

setTimeout(() => {
    try {
        const urlParams = new URLSearchParams(window.location.search);
        const savedTab = urlParams.get('tab') || sessionStorage.getItem('live_active_tab');
        if (savedTab === 'weather') {
            switchLiveTab('weather');
        } else if (savedTab === 'aqi') {
            switchLiveTab('aqi');
        }
    } catch(e) {}

    refreshDashboard();
    
    // Auto-refresh every 7 seconds silently in background (reduced from 4s to lower server load)
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    autoRefreshInterval = setInterval(() => {
        if (!document.hidden) {
            refreshDashboard();
        }
    }, 7000);

    // Immediately refresh when switching back to tab or unlocking screen
    document.addEventListener('visibilitychange', onLiveVisibilityChange);
}, 0);

window.openPollutantModal = function(type) {
    const data = pollutantDetails[type];
    if(!data) return;
    
    // Clean up any stray duplicate modals to avoid ID collisions
    const allModals = document.querySelectorAll('#pollutant-modal');
    if (allModals.length > 1) {
        for (let i = 0; i < allModals.length - 1; i++) {
            allModals[i].remove();
        }
    }
    
    const modal = document.getElementById('pollutant-modal');
    if (!modal) return;
    
    const iconBg = modal.querySelector('#pm-modal-icon-bg');
    if (iconBg) iconBg.innerText = data.icon;
    const titleEl = modal.querySelector('#pm-modal-title');
    if (titleEl) titleEl.innerText = data.title;
    const limitEl = modal.querySelector('#pm-modal-limit');
    if (limitEl) limitEl.innerText = `${data.limit} ${data.unit}`;
    
    const cardEl = document.getElementById(`card-${type}`);
    const currentValStr = cardEl ? cardEl.innerText : '--';
    const currentVal = parseFloat(currentValStr);
    
    const valEl = modal.querySelector('#pm-modal-val');
    if (valEl) valEl.innerText = isNaN(currentVal) ? '--' : currentVal.toFixed(3);
    const unitEl = modal.querySelector('#pm-modal-unit');
    if (unitEl) unitEl.innerText = data.unit;
    const shortEl = modal.querySelector('#pm-modal-short');
    if (shortEl) shortEl.innerText = data.id.toUpperCase();
    const qEl = modal.querySelector('#pm-modal-q');
    if (qEl) qEl.innerText = data.id.toUpperCase();
    const descEl = modal.querySelector('#pm-modal-desc');
    if (descEl) descEl.innerText = data.desc;
    const healthEl = modal.querySelector('#pm-modal-health');
    if (healthEl) healthEl.innerText = data.health;
    
    const t1 = modal.querySelector('#pm-t1'); if (t1) t1.innerText = data.scale[0];
    const t2 = modal.querySelector('#pm-t2'); if (t2) t2.innerText = data.scale[1];
    const t3 = modal.querySelector('#pm-t3'); if (t3) t3.innerText = data.scale[2];
    const t4 = modal.querySelector('#pm-t4'); if (t4) t4.innerText = data.scale[3];
    const t5 = modal.querySelector('#pm-t5'); if (t5) t5.innerText = data.scale[4];
    const t6 = modal.querySelector('#pm-t6'); if (t6) t6.innerText = data.maxTick;
    
    const statusBox = modal.querySelector('#pm-modal-status');
    if (statusBox) {
        if(isNaN(currentVal)) {
            statusBox.style.display = 'none';
        } else {
            statusBox.style.display = 'flex';
            if(currentVal <= data.limit) {
                statusBox.innerHTML = '<i data-lucide="check-circle-2" style="width: 17px; height: 17px;"></i> Within Indian CPCB Limit';
                statusBox.style.background = '#dcfce7';
                statusBox.style.color = '#16a34a';
            } else {
                statusBox.innerHTML = '<i data-lucide="alert-triangle" style="width: 17px; height: 17px;"></i> Exceeds Indian CPCB Limit';
                statusBox.style.background = '#fee2e2';
                statusBox.style.color = '#dc2626';
            }
        }
    }
    
    const marker = modal.querySelector('#pm-modal-marker');
    const scaleInfo = getPollutantScaleInfo(type, currentVal);
    if (marker) {
        marker.style.left = `clamp(8px, ${scaleInfo.pct}%, calc(100% - 8px))`;
        marker.style.borderColor = scaleInfo.color;
    }
    
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    const scrollBox = modal.querySelector('.pollutant-modal-scroll');
    if (scrollBox) scrollBox.scrollTop = 0;
    
    if (window.lucide && lucide.createIcons) lucide.createIcons({ root: modal });
};
var openPollutantModal = window.openPollutantModal;

window.closePollutantModal = function(e) {
    if (e) {
        if (typeof e.preventDefault === 'function') e.preventDefault();
        if (typeof e.stopPropagation === 'function') e.stopPropagation();
    }
    const modals = document.querySelectorAll('#pollutant-modal');
    modals.forEach(m => {
        m.style.display = 'none';
    });
    document.body.style.overflow = '';
};
var closePollutantModal = window.closePollutantModal;

// Global delegated capture listeners ensuring close button, backdrop, and Escape always work
if (!window.__pollutantModalListenersBound) {
    window.__pollutantModalListenersBound = true;
    
    const handlePollutantCloseTrigger = function(e) {
        const closeBtn = e.target && e.target.closest && e.target.closest('.pollutant-modal-close');
        const backdrop = e.target && (
            (e.target.classList && e.target.classList.contains('pollutant-modal-backdrop')) ||
            e.target.id === 'pollutant-modal-backdrop'
        );
        if (closeBtn || backdrop) {
            if (typeof e.preventDefault === 'function') e.preventDefault();
            if (typeof e.stopPropagation === 'function') e.stopPropagation();
            if (typeof window.closePollutantModal === 'function') {
                window.closePollutantModal(e);
            }
        }
    };

    document.addEventListener('click', handlePollutantCloseTrigger, true);
    document.addEventListener('touchend', handlePollutantCloseTrigger, { capture: true, passive: false });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            if (typeof window.closePollutantModal === 'function') {
                window.closePollutantModal(e);
            }
        }
    });
}
</script>

<!-- Pollutant Detail Modal (Mounted directly to viewport) -->
<div id="pollutant-modal" style="display: none; position: fixed; inset: 0; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; z-index: 99999; align-items: center; justify-content: center; padding: clamp(12px, 3vw, 24px); box-sizing: border-box;">
  <!-- Backdrop Click to Close -->
  <div id="pollutant-modal-backdrop" class="pollutant-modal-backdrop" style="position: absolute; inset: 0; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); cursor: pointer;" onclick="window.closePollutantModal(event)"></div>

  <!-- Modal Dialog Card -->
  <div class="pollutant-modal-card" style="position: relative; z-index: 1; background: #ffffff; width: 100%; max-width: 580px; max-height: min(88vh, 720px); border-radius: 24px; box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.3); border: 1px solid #e2e8f0; overflow: hidden; display: flex; flex-direction: column; margin: auto; animation: modal-pop 0.22s cubic-bezier(0.16, 1, 0.3, 1);">
    <button type="button" class="pollutant-modal-close" onclick="window.closePollutantModal(event)" title="Close details" aria-label="Close" style="position: absolute; top: 16px; right: 16px; z-index: 50; width: 36px; height: 36px; min-width: 36px; min-height: 36px; border-radius: 50%; border: 1px solid #e2e8f0; background: #ffffff; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: all 0.2s ease; touch-action: manipulation; -webkit-tap-highlight-color: transparent; pointer-events: auto;" onmouseover="this.style.background='#f8fafc'; this.style.color='#0f172a';" onmouseout="this.style.background='#ffffff'; this.style.color='#64748b';">
      <i data-lucide="x" style="width: 18px; height: 18px; pointer-events: none;"></i>
    </button>
    
    <div class="pollutant-modal-scroll" style="padding: clamp(20px, 4vw, 32px); overflow-y: auto; overscroll-behavior: contain; flex: 1;">
        <!-- Header -->
        <div style="display: flex; gap: 16px; align-items: flex-start; margin-bottom: 22px; padding-right: 36px;">
            <div id="pm-modal-icon-bg" style="width: 54px; height: 54px; border-radius: 16px; background: #f0fdf4; display: flex; align-items: center; justify-content: center; font-size: 26px; flex-shrink: 0; box-shadow: inset 0 2px 4px rgba(255,255,255,0.5); border: 1px solid #e2e8f0;">🌫️</div>
            <div>
                <h2 id="pm-modal-title" style="margin: 0 0 4px 0; font-size: 20px; font-weight: 700; color: #0f172a; font-family: var(--font-sans); line-height: 1.25;">Particulate Matter 2.5 (PM2.5)</h2>
                <div style="font-size: 13.5px; font-weight: 600; color: #64748b;">Indian Standard (CPCB Limit): <span id="pm-modal-limit" style="color: #00bfa5;">60 µg/m³</span></div>
            </div>
        </div>

        <!-- Current Reading Box -->
        <div class="pm-reading-box" style="border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); background: #ffffff;">
            <div>
                <div style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">CURRENT READING</div>
                <div style="display: flex; align-items: baseline; gap: 8px;">
                    <div id="pm-modal-val" style="font-size: 38px; font-weight: 900; color: #0f172a; line-height: 1; font-family: var(--font-mono);">7.09</div>
                    <div id="pm-modal-unit" style="font-size: 20px; font-weight: 700; color: #0f172a; font-family: var(--font-mono);">µg/m³</div>
                </div>
            </div>
            <div id="pm-modal-status" style="display: flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; background: #dcfce7; color: #16a34a; font-size: 13px; font-weight: 700;">
                <i data-lucide="check-circle-2" style="width: 17px; height: 17px;"></i> Within Indian CPCB Limit
            </div>
        </div>

        <!-- Concentration Scale Box -->
        <div class="pm-scale-box" style="border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px 20px; margin-bottom: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); background: #ffffff;">
            <div class="pm-scale-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; gap: 8px; flex-wrap: wrap;">
                <div class="pm-scale-title" style="font-size: 11.5px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; font-family: var(--font-mono);"><span id="pm-modal-short">PM2.5</span> CONCENTRATION SCALE (CPCB)</div>
                <div class="pm-scale-badge" style="font-size: 11.5px; font-weight: 700; color: #00bfa5;">Indian NAQI Standard</div>
            </div>
            
            <div style="position: relative; width: 100%;">
                <div class="pm-scale-labels" style="display: grid; grid-template-columns: repeat(6, 1fr); margin-bottom: 8px; font-size: 9.5px; font-weight: 700; line-height: 1;">
                    <div style="color: #475569; text-align: left;">Good</div>
                    <div style="color: #475569; text-align: center;">Moderate</div>
                    <div style="color: #475569; text-align: center;">Poor</div>
                    <div style="color: #475569; text-align: center;">Unhealthy</div>
                    <div style="color: #475569; text-align: center;">Severe</div>
                    <div style="color: #475569; text-align: right;">Hazardous</div>
                </div>
                
                <div class="pm-scale-track" style="position: relative; height: 6px; border-radius: 999px; background-color: #e2e8f0; margin-bottom: 8px;">
                    <div style="width: 100%; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #59b61f 0%, #59b61f 16.666%, #eec732 16.666%, #eec732 33.333%, #ea8c34 33.333%, #ea8c34 50%, #e95478 50%, #e95478 66.666%, #b33fba 66.666%, #b33fba 83.333%, #c92033 83.333%, #c92033 100%); box-shadow: inset 0 1px 2px rgba(0,0,0,0.06);"></div>
                    <div id="pm-modal-marker" style="position: absolute; top: 50%; left: 10%; transform: translate(-50%, -50%); width: 16px; height: 16px; border-radius: 50%; background: #ffffff; border: 3.5px solid #59b61f; box-shadow: 0 1px 4px rgba(0,0,0,0.25); z-index: 3; transition: left 0.4s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.3s ease;"></div>
                </div>
                
                <div id="pm-modal-ticks" class="pm-scale-ticks" style="position: relative; height: 16px; font-size: 10px; font-weight: 600; color: #64748b; font-family: var(--font-mono);">
                    <span style="position: absolute; left: 0%; line-height: 1;">0</span>
                    <span id="pm-t1" style="position: absolute; left: 16.666%; transform: translateX(-50%); line-height: 1;">30</span>
                    <span id="pm-t2" style="position: absolute; left: 33.333%; transform: translateX(-50%); line-height: 1;">60</span>
                    <span id="pm-t3" style="position: absolute; left: 50%; transform: translateX(-50%); line-height: 1;">90</span>
                    <span id="pm-t4" style="position: absolute; left: 66.666%; transform: translateX(-50%); line-height: 1;">120</span>
                    <span id="pm-t5" style="position: absolute; left: 83.333%; transform: translateX(-50%); line-height: 1;">250</span>
                    <span id="pm-t6" style="position: absolute; right: 0%; line-height: 1;">250+</span>
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
