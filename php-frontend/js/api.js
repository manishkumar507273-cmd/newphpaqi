// In-memory cache for live status evaluation
const apiCache = {
    aqiLive: null,
    lastFetchTime: 0
};
const CACHE_TTL = 30000; // 30 seconds cache

// CPCB Calibration Logic
const CPCB_BREAKPOINTS = {
  pm25: [[0.0, 30.0, 0, 50], [30.0, 60.0, 51, 100], [60.0, 90.0, 101, 200], [90.0, 120.0, 201, 300], [120.0, 250.0, 301, 400], [250.0, 500.0, 401, 500]],
  pm10: [[0.0, 50.0, 0, 50], [50.0, 100.0, 51, 100], [100.0, 250.0, 101, 200], [250.0, 350.0, 201, 300], [350.0, 430.0, 301, 400], [430.0, 600.0, 401, 500]],
  co: [[0.0, 1.0, 0, 50], [1.0, 2.0, 51, 100], [2.0, 10.0, 101, 200], [10.0, 17.0, 201, 300], [17.0, 34.0, 301, 400], [34.0, 50.0, 401, 500]],
  no2: [[0.0, 40.0, 0, 50], [40.0, 80.0, 51, 100], [80.0, 180.0, 101, 200], [180.0, 280.0, 201, 300], [280.0, 400.0, 301, 400], [400.0, 500.0, 401, 500]],
  o3: [[0.0, 50.0, 0, 50], [50.0, 100.0, 51, 100], [100.0, 168.0, 101, 200], [168.0, 208.0, 201, 300], [208.0, 748.0, 301, 400], [748.0, 1000.0, 401, 500]],
};

function calcSubindex(paramKey, cp) {
  if (cp == null || !CPCB_BREAKPOINTS[paramKey]) return 0.0;
  const val = Math.max(0.0, Number(cp));
  const tiers = CPCB_BREAKPOINTS[paramKey];
  for (const [c_lo, c_hi, i_lo, i_hi] of tiers) {
    if (val <= c_hi) return Number((((i_hi - i_lo) / (c_hi - c_lo)) * (val - c_lo) + i_lo).toFixed(3));
  }
  const [c_lo, c_hi, i_lo, i_hi] = tiers[tiers.length - 1];
  return Math.min(500.0, Number((((i_hi - i_lo) / (c_hi - c_lo)) * (val - c_lo) + i_lo).toFixed(3)));
}

/**
 * Applies time-of-day temperature correction based on IST hour of the reading's timestamp.
 * @param {number|null} tempC - Raw temperature in °C from Supabase
 * @param {string} isoTimestamp - ISO 8601 timestamp (IST or UTC)
 * @returns {number|null} Adjusted temperature
 */
function adjustTempByHour(tempC, isoTimestamp) {
    if (tempC === null || tempC === undefined) return tempC;
    if (!isoTimestamp) return Number(Number(tempC).toFixed(1));
    try {
        let tsStr = String(isoTimestamp).trim().replace(' ', 'T');
        if (!/[Zz]$/.test(tsStr) && !/[+\-]\d{2}(:?\d{2})?$/.test(tsStr)) {
            tsStr += '+05:30';
        }
        const dt = new Date(tsStr);
        if (isNaN(dt.getTime())) return Number(Number(tempC).toFixed(1));
        const istDt = new Date(dt.getTime() + 5.5 * 3600 * 1000);
        const hour = istDt.getUTCHours();
        let offset = 0;
        if (hour === 8)                      offset = -0.5;
        else if (hour === 9)                 offset = -1.0;
        else if (hour === 10)                offset = -1.5;
        else if (hour >= 11 && hour <= 16)  offset = -2.0;
        else if (hour === 17)                offset = -1.5;
        else if (hour === 18)                offset = -1.0;
        else if (hour === 19)                offset = -0.5;
        // hour >= 20 or hour <= 7: offset = 0 (original value as in supabase)
        return Number((Number(tempC) + offset).toFixed(1));
    } catch (e) {
        return Number(Number(tempC).toFixed(1));
    }
}
window.adjustTempByHour = adjustTempByHour;

function parseToIstIso(tsRaw) {
    if (!tsRaw) return '';
    const tsStr = String(tsRaw).trim();
    if (tsStr.includes('+00:00') || tsStr.endsWith('Z')) {
        try {
            const dt = new Date(tsStr);
            if (!isNaN(dt.getTime())) {
                const istOffsetMs = 5.5 * 60 * 60 * 1000;
                const istDt = new Date(dt.getTime() + istOffsetMs);
                return `${istDt.getUTCFullYear()}-${String(istDt.getUTCMonth()+1).padStart(2,'0')}-${String(istDt.getUTCDate()).padStart(2,'0')}T${String(istDt.getUTCHours()).padStart(2,'0')}:${String(istDt.getUTCMinutes()).padStart(2,'0')}:${String(istDt.getUTCSeconds()).padStart(2,'0')}`;
            }
        } catch(e) {}
    }
    return tsStr;
}

function safeParseDate(ts) {
    if (!ts) return null;
    if (ts instanceof Date) return isNaN(ts.getTime()) ? null : ts;
    let s = String(ts).trim();
    // Normalize space between date and time to 'T'
    s = s.replace(/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})/, '$1T$2');
    // Normalize timezone like +00 or +05 to +00:00 or +05:00
    s = s.replace(/([+-]\d{2})$/, '$1:00');
    // If no timezone offset is specified, treat as IST (+05:30) because sensor telemetry is strictly Indian Standard Time
    if (!/[Zz+-]\d{2}/.test(s) && /T\d{2}:\d{2}/.test(s)) {
        s += '+05:30';
    }
    const d = new Date(s);
    return isNaN(d.getTime()) ? null : d;
}
window.safeParseDate = safeParseDate;

let lastKnownLiveReading = null;

function evaluateAndSyncLiveBadge(reading) {
    if (!window.updateGlobalLiveBadge) return;
    if (reading && (reading.timestamp || reading.created_at)) {
        lastKnownLiveReading = reading;
    }
    const targetReading = reading || lastKnownLiveReading;
    if (!targetReading) {
        window.updateGlobalLiveBadge(false, 'No telemetry data received from cloud');
        return;
    }
    const ts = targetReading.timestamp || targetReading.created_at || targetReading.timestamp_hour;
    if (!ts) {
        window.updateGlobalLiveBadge(false, 'No timestamp received from cloud');
        return;
    }
    const parsedDate = safeParseDate(ts);
    if (!parsedDate) {
        window.updateGlobalLiveBadge(false, 'Invalid timestamp format');
        return;
    }
    const diffMins = Math.floor((Date.now() - parsedDate.getTime()) / 60000);
    const isLive = !isNaN(diffMins) && diffMins >= -10 && diffMins <= 90;
    const timeFormatted = parsedDate.toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', hour12: true });
    const tip = isLive 
        ? `Live telemetry streaming from cloud (Updated: ${timeFormatted})` 
        : `Sensor node offline: Last received ${diffMins >= 1440 ? Math.floor(diffMins/1440) + 'd ago' : (diffMins >= 60 ? Math.floor(diffMins/60) + 'h ago' : diffMins + 'm ago')} (${timeFormatted})`;
    
    window.updateGlobalLiveBadge(isLive, tip, timeFormatted);
}
window.evaluateAndSyncLiveBadge = evaluateAndSyncLiveBadge;

async function getLiveAqi() {
    if (apiCache.aqiLive && (Date.now() - apiCache.lastFetchTime < CACHE_TTL)) {
        evaluateAndSyncLiveBadge(apiCache.aqiLive.data);
        return apiCache.aqiLive;
    }
    try {
        const response = await fetch(`api/telemetry.php?action=aqi_live`);
        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();
        if (data && data.data) {
            apiCache.aqiLive = data;
            apiCache.lastFetchTime = Date.now();
            evaluateAndSyncLiveBadge(data.data);
            return data;
        }
        evaluateAndSyncLiveBadge(null);
        return null;
    } catch (error) {
        console.error('Error fetching live AQI:', error);
        evaluateAndSyncLiveBadge(null);
        return null;
    }
}

// Ensure initial sync and icons
document.addEventListener("DOMContentLoaded", () => {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    if (window.INITIAL_DATA && (window.INITIAL_DATA.aqi || window.INITIAL_DATA.weather)) {
        evaluateAndSyncLiveBadge(window.INITIAL_DATA.aqi || window.INITIAL_DATA.weather);
    } else {
        getLiveAqi();
    }
    // Background status sync every 30s to keep header status accurate on all pages
    setInterval(() => {
        if (!document.hidden && window.__currentPage !== 'overview' && window.__currentPage !== 'live') {
            getLiveAqi();
        }
    }, 30000);
});
