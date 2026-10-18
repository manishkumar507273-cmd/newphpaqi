const DEFAULT_KEY = window.ENV_CONFIG?.supabaseKey || '';
const SUPABASE_URL = window.ENV_CONFIG?.supabaseUrl || '';
const HEADERS = {
    'apikey': DEFAULT_KEY,
    'Authorization': `Bearer ${DEFAULT_KEY}`,
    'Content-Type': 'application/json'
};

// In-memory cache to prevent redundant network requests across page navigations
const apiCache = {
    aqiLive: null,
    weatherLive: null,
    aqiHistory: null,
    weatherHistory: null,
    lastFetchTime: 0
};
const CACHE_TTL = 30000; // 30 seconds cache

const TABLE_AQI_LIVE = 'AQI_LIVE_NODE1';
const TABLE_AQI_HISTORICAL = 'AQI_NODE1';
const TABLE_WEATHER_LIVE = 'WEATHER_LIVE_NODE1';
const TABLE_WEATHER_HISTORICAL = 'WEATHER_NODE1';

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

function formatRawReading(raw) {
  if (!raw) return null;
  if (raw.is_calibrated && raw.cpcb_aqi != null) return { ...raw };

  const tempVal = raw.temperature != null ? Number(raw.temperature) : 27.0;
  const humVal = raw.humidity != null ? Number(raw.humidity) : 60.0;

  const rawPm25 = raw.pm25 != null ? Number(raw.pm25) : (raw['pm2.5'] != null ? Number(raw['pm2.5']) : null);
  const rawPm10 = raw.pm10 != null ? Number(raw.pm10) : null;
  const rawCo = raw.co != null ? Number(raw.co) : null;
  const rawO3 = raw.o3 != null ? Number(raw.o3) : null;
  const rawNo2 = raw.no2 != null ? Number(raw.no2) : null;

  const calPm25 = rawPm25 != null ? Math.max(0.0, Number((0.12532894 * rawPm25 - 0.7801631 * tempVal - 0.15188749 * humVal + 41.495258).toFixed(3))) : null;
  const calPm10 = rawPm10 != null ? Math.max(0.0, Number((0.10855827 * rawPm10 - 0.6390711 * tempVal - 0.11117823 * humVal + 34.034309).toFixed(3))) : null;
  const calCo = rawCo != null ? Math.max(0.0, Number((0.24370718 * rawCo + 0.02763672 * tempVal + 0.00444147 * humVal - 0.96570843).toFixed(3))) : null;
  const calNo2 = rawNo2 != null ? Math.max(0.0, Number((0.00920961 * rawNo2 + 0.06749354 * tempVal + 0.00777269 * humVal + 2.903467).toFixed(3))) : null;
  const calO3 = rawO3 != null ? Math.max(0.0, Number((0.05187092 * rawO3 - 1.4830095 * tempVal - 0.23912878 * humVal + 91.72351).toFixed(3))) : null;

  const subIndices = {
    pm25: calcSubindex('pm25', calPm25 ?? rawPm25),
    pm10: calcSubindex('pm10', calPm10 ?? rawPm10),
    co: calcSubindex('co', calCo ?? rawCo),
    no2: calcSubindex('no2', calNo2 ?? rawNo2),
    o3: calcSubindex('o3', calO3 ?? rawO3),
  };

  const domKey = Object.keys(subIndices).reduce((a, b) => subIndices[a] >= subIndices[b] ? a : b);
  const domNames = { pm25: 'PM2.5', pm10: 'PM10', co: 'CO', no2: 'NO₂', o3: 'O₃' };
  const cpcb_aqi = Math.round(subIndices[domKey] || 0);

  let label = "Good", color = "#65ff50";
  if (cpcb_aqi > 50 && cpcb_aqi <= 100) { label = "Satisfactory"; color = "#a3e635"; }
  else if (cpcb_aqi > 100 && cpcb_aqi <= 200) { label = "Moderate"; color = "#facc15"; }
  else if (cpcb_aqi > 200 && cpcb_aqi <= 300) { label = "Poor"; color = "#fb923c"; }
  else if (cpcb_aqi > 300 && cpcb_aqi <= 400) { label = "Very Poor"; color = "#f87171"; }
  else if (cpcb_aqi > 400) { label = "Severe"; color = "#c084fc"; }

  const ts = raw.timestamp_hour || raw.created_at || raw.timestamp;

  return {
    id: raw.id,
    timestamp: parseToIstIso(ts),
    temperature: raw.temperature,
    humidity: raw.humidity,
    pm25: calPm25 ?? rawPm25,
    pm10: calPm10 ?? rawPm10,
    co: calCo ?? rawCo,
    o3: calO3 ?? rawO3,
    no2: calNo2 ?? rawNo2,
    cpcb_aqi,
    dominant_pollutant: domNames[domKey] || 'N/A',
    aqi_info: { value: cpcb_aqi, label, color, standard: 'CPCB (India)' },
    wind_speed: raw.wind_speed,
    wind_gust: raw.wind_gust ?? raw.gust,
    wind_direction: raw.wind_direction,
    rain_gauge: raw.rain_gauge ?? raw.rain
  };
}


async function getLiveAqi() {
    if (apiCache.aqiLive && (Date.now() - apiCache.lastFetchTime < CACHE_TTL)) {
        return apiCache.aqiLive;
    }
    try {
        const response = await fetch(`api/telemetry.php?action=aqi_live`);
        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();
        if (data && data.data) {
            apiCache.aqiLive = data;
            return data;
        }
        return null;
    } catch (error) {
        console.error('Error fetching live AQI:', error);
        return null;
    }
}

async function getLiveWeather() {
    if (apiCache.weatherLive && (Date.now() - apiCache.lastFetchTime < CACHE_TTL)) {
        return apiCache.weatherLive;
    }
    try {
        const response = await fetch(`api/telemetry.php?action=weather_live`);
        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();
        if (data && data.data) {
            apiCache.weatherLive = data;
            return data;
        }
        return null;
    } catch (error) {
        console.error('Error fetching live weather:', error);
        return null;
    }
}

async function getLiveAqiHistory(limit = 50) {
    try {
        const response = await fetch(`api/telemetry.php?action=aqi_live_history&limit=${limit}`);
        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();
        return data || [];
    } catch (error) {
        console.error('Error fetching live AQI history:', error);
        return [];
    }
}

async function getLiveWeatherHistory(limit = 50) {
    try {
        const response = await fetch(`api/telemetry.php?action=weather_live_history&limit=${limit}`);
        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();
        return data || [];
    } catch (error) {
        console.error('Error fetching live weather history:', error);
        return [];
    }
}

async function getHistoricalData() {
    if (apiCache.aqiHistory && (Date.now() - apiCache.lastFetchTime < CACHE_TTL)) {
        return apiCache.aqiHistory;
    }
    try {
        const limit = 500;
        const response = await fetch(`api/telemetry.php?action=aqi_history&limit=${limit}`);
        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();
        
        apiCache.aqiHistory = data;
        apiCache.lastFetchTime = Date.now();
        return data;
    } catch (error) {
        console.error('Error fetching historical AQI:', error);
        return { history: [] };
    }
}

async function getHistoricalWeatherData() {
    if (apiCache.weatherHistory && (Date.now() - apiCache.lastFetchTime < CACHE_TTL)) {
        return apiCache.weatherHistory;
    }
    try {
        const limit = 500;
        const response = await fetch(`api/telemetry.php?action=weather_history&limit=${limit}`);
        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();
        
        apiCache.weatherHistory = data;
        apiCache.lastFetchTime = Date.now();
        return data;
    } catch (error) {
        console.error('Error fetching historical Weather:', error);
        return { history: [] };
    }
}


// Ensure the endpoint in historical.php uses the new function
window.API_BASE_URL = 'superseded';

// Ensure icons render if called manually
document.addEventListener("DOMContentLoaded", () => {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
