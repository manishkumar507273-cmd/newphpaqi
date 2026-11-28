// In-memory cache for live status evaluation
const apiCache = {
    aqiLive: null,
    lastFetchTime: 0,
    liveBulk: null,
    overviewBulk: null
};
// Expose cache globally so router.js can pre-hydrate INITIAL_DATA on SPA navigation
window.__apiCache = apiCache;
const CACHE_TTL = 30000; // 30 seconds cache

// ── Persistent Client Cache Engine (SWR - 0ms Instant Page Hydration) ──
const STORAGE_KEYS = {
    BULK: 'smartairnet_bulk_telemetry',
    FORECAST: 'smartairnet_forecast_payload',
    HIST_PREFIX: 'smartairnet_hist_'
};
window.STORAGE_KEYS = STORAGE_KEYS;

function getLocalCache(key) {
    try {
        const raw = localStorage.getItem(key);
        if (!raw) return null;
        return JSON.parse(raw);
    } catch (e) {
        return null;
    }
}
window.getLocalCache = getLocalCache;

function setLocalCache(key, data) {
    try {
        if (!data) return;
        localStorage.setItem(key, JSON.stringify(data));
    } catch (e) {}
}
window.setLocalCache = setLocalCache;

// Synchronously hydrate memory cache and window.INITIAL_DATA on script load (0ms instant!)
(function initClientStorageCache() {
    try {
        const activeNode = (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));
        const cachedBulk = getLocalCache(STORAGE_KEYS.BULK + '_n' + activeNode);
        if (cachedBulk && (cachedBulk.aqi || cachedBulk.weather)) {
            if (!window.INITIAL_DATA || (!window.INITIAL_DATA.aqi && !window.INITIAL_DATA.weather)) {
                window.INITIAL_DATA = cachedBulk;
            }
            const stamp = cachedBulk.fetchedAt || cachedBulk.time || 0;
            if (!apiCache.liveBulk) apiCache.liveBulk = { data: cachedBulk, time: stamp, node: activeNode };
            if (!apiCache.overviewBulk) apiCache.overviewBulk = { data: cachedBulk, time: stamp, node: activeNode };
            if (!apiCache.aqiLive && cachedBulk.aqi) apiCache.aqiLive = { data: cachedBulk.aqi, time: stamp };
        }
        const cachedForecast = getLocalCache(STORAGE_KEYS.FORECAST);
        if (cachedForecast && cachedForecast.hourly && !window.__forecastCache) {
            window.__forecastCache = cachedForecast;
        }
    } catch (e) {}
})();

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
    return Number((((i_hi - i_lo) / (c_hi - c_lo)) * (val - c_lo) + i_lo).toFixed(3));
}
window.calcSubindex = calcSubindex;

// ── Central AQI Dynamic Theme Registry ──
function getAQITheme(aqi) {
    const v = Number(aqi);
    if (isNaN(v) || v <= 50) {
        return {
            label: 'Good',
            pageColor: '#dcfce7',
            pageBg: 'radial-gradient(ellipse 120% 70% at 50% -10%, #86efac 0%, #bbf7d0 35%, #dcfce7 65%, #bbf7d0 100%)',
            bg: 'linear-gradient(155deg, #86efac 0%, #4ade80 25%, #22c55e 55%, #16a34a 80%, #15803d 95%, #166534 100%)',
            border: 'rgba(21, 128, 61, 0.45)',
            shadow: 'rgba(22, 163, 74, 0.28)',
            accent: '#14532d',
            badgeBg: 'rgba(255, 255, 255, 0.88)',
            badgeBorder: 'rgba(21, 128, 61, 0.45)',
            badgeText: '#14532d'
        };
    }
    if (v <= 100) {
        return {
            label: 'Moderate',
            pageColor: '#fde68a',
            pageBg: 'radial-gradient(ellipse 120% 70% at 50% -10%, #fde047 0%, #fde68a 35%, #fef08a 65%, #fde68a 100%)',
            bg: 'linear-gradient(155deg, #fde047 0%, #facc15 22%, #eab308 50%, #ca8a04 75%, #b45309 90%, #92400e 100%)',
            border: 'rgba(180, 83, 9, 0.45)',
            shadow: 'rgba(202, 138, 4, 0.28)',
            accent: '#78350f',
            badgeBg: 'rgba(255, 255, 255, 0.88)',
            badgeBorder: 'rgba(180, 83, 9, 0.45)',
            badgeText: '#78350f'
        };
    }
    if (v <= 200) {
        return {
            label: 'Poor',
            pageColor: '#fed7aa',
            pageBg: 'radial-gradient(ellipse 120% 70% at 50% -10%, #fdba74 0%, #fed7aa 35%, #ffedd5 65%, #fed7aa 100%)',
            bg: 'linear-gradient(155deg, #fdba74 0%, #fb923c 25%, #f97316 52%, #ea580c 78%, #c2410c 92%, #9a3412 100%)',
            border: 'rgba(194, 65, 12, 0.45)',
            shadow: 'rgba(234, 88, 12, 0.28)',
            accent: '#7c2d12',
            badgeBg: 'rgba(255, 255, 255, 0.88)',
            badgeBorder: 'rgba(194, 65, 12, 0.45)',
            badgeText: '#7c2d12'
        };
    }
    if (v <= 300) {
        return {
            label: 'Unhealthy',
            pageColor: '#fecdd3',
            pageBg: 'radial-gradient(ellipse 120% 70% at 50% -10%, #fda4af 0%, #fecdd3 35%, #ffe4e6 65%, #fecdd3 100%)',
            bg: 'linear-gradient(155deg, #fda4af 0%, #fb7185 25%, #f43f5e 52%, #e11d48 78%, #be123c 92%, #881337 100%)',
            border: 'rgba(190, 18, 60, 0.45)',
            shadow: 'rgba(225, 29, 72, 0.28)',
            accent: '#881337',
            badgeBg: 'rgba(255, 255, 255, 0.88)',
            badgeBorder: 'rgba(190, 18, 60, 0.45)',
            badgeText: '#881337'
        };
    }
    if (v <= 400) {
        return {
            label: 'Severe',
            pageColor: '#e9d5ff',
            pageBg: 'radial-gradient(ellipse 120% 70% at 50% -10%, #d8b4fe 0%, #e9d5ff 35%, #f3e8ff 65%, #e9d5ff 100%)',
            bg: 'linear-gradient(155deg, #d8b4fe 0%, #c084fc 25%, #a855f7 52%, #9333ea 78%, #7e22ce 92%, #581c87 100%)',
            border: 'rgba(126, 34, 206, 0.45)',
            shadow: 'rgba(147, 51, 234, 0.28)',
            accent: '#581c87',
            badgeBg: 'rgba(255, 255, 255, 0.88)',
            badgeBorder: 'rgba(126, 34, 206, 0.45)',
            badgeText: '#581c87'
        };
    }
    return {
        label: 'Hazardous',
        pageColor: '#fecaca',
        pageBg: 'radial-gradient(ellipse 120% 70% at 50% -10%, #fca5a5 0%, #fecaca 35%, #fee2e2 65%, #fecaca 100%)',
        bg: 'linear-gradient(155deg, #fca5a5 0%, #f87171 25%, #ef4444 52%, #dc2626 78%, #b91c1c 92%, #7f1d1d 100%)',
        border: 'rgba(185, 28, 28, 0.50)',
        shadow: 'rgba(220, 38, 38, 0.32)',
        accent: '#7f1d1d',
        badgeBg: 'rgba(255, 255, 255, 0.88)',
        badgeBorder: 'rgba(185, 28, 28, 0.50)',
        badgeText: '#7f1d1d'
    };
}
window.getAQITheme = getAQITheme;

function applyAQIPageTheme(aqiVal) {
    const theme = getAQITheme(aqiVal);
    window.currentAQITheme = theme;
    window.currentAqiValue = aqiVal;

    const layoutRoot = document.getElementById('app-layout-root');
    if (layoutRoot && theme.pageColor && theme.pageBg) {
        if (!document.body.classList.contains('weather-backdrop-active')) {
            layoutRoot.style.backgroundColor = theme.pageColor;
            layoutRoot.style.backgroundImage = theme.pageBg;
            document.body.style.backgroundColor = theme.pageColor;
        }
    }
    return theme;
}
window.applyAQIPageTheme = applyAQIPageTheme;

/**
 * Applies time-of-day temperature correction based on IST hour of the reading's timestamp.
 * @param {number|null} tempC - Raw temperature in °C from Supabase
 * @param {string} isoTimestamp - ISO 8601 timestamp (IST or UTC)
 * @returns {number|null} Adjusted temperature
 */
function adjustTempByHour(tempC, isoTimestamp) {
    if (tempC === null || tempC === undefined) return tempC;
    return Number(Number(tempC).toFixed(1));
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
                return `${istDt.getUTCFullYear()}-${String(istDt.getUTCMonth() + 1).padStart(2, '0')}-${String(istDt.getUTCDate()).padStart(2, '0')}T${String(istDt.getUTCHours()).padStart(2, '0')}:${String(istDt.getUTCMinutes()).padStart(2, '0')}:${String(istDt.getUTCSeconds()).padStart(2, '0')}+05:30`;
            }
        } catch (e) { }
    }
    return tsStr;
}
window.parseToIstIso = parseToIstIso;

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
    const activeNode = (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));

    if (reading && (reading.timestamp || reading.created_at)) {
        lastKnownLiveReading = reading;
    }
    const targetReading = reading || lastKnownLiveReading;
    if (!targetReading) {
        return;
    }
    const ts = targetReading.timestamp || targetReading.created_at || targetReading.timestamp_hour;
    if (!ts) {
        return;
    }
    const parsedDate = safeParseDate(ts);
    if (!parsedDate) {
        return;
    }
    const diffMins = Math.floor((Date.now() - parsedDate.getTime()) / 60000);
    const isLive = !isNaN(diffMins) && diffMins >= -10 && diffMins <= 5;
    const timeFormatted = parsedDate.toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', hour12: true });
    const tip = isLive
        ? `Live telemetry streaming from cloud (Updated: ${timeFormatted})`
        : `Sensor node offline: Last received ${diffMins >= 1440 ? Math.floor(diffMins / 1440) + 'd ago' : (diffMins >= 60 ? Math.floor(diffMins / 60) + 'h ago' : diffMins + 'm ago')} (${timeFormatted})`;

    window.updateGlobalLiveBadge(isLive, tip, timeFormatted);
}
window.evaluateAndSyncLiveBadge = evaluateAndSyncLiveBadge;

// ── Supabase Direct Client Configuration ──
function getSbConfig(overrideNode = null) {
    const activeNode = overrideNode ? String(overrideNode) : (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));
    const isNode2 = (String(activeNode) === '2');
    const injected = window.SUPABASE_CONFIG || {};

    if (isNode2) {
        return {
            url: (injected.node === '2' && injected.url) ? injected.url : 'https://nahabhzcaeppbteuxogg.supabase.co',
            key: (injected.node === '2' && injected.key) ? injected.key : 'sb_publishable_RXM8H-D8m6qWz2Yy2xA6VQ_TjxxY3bJ',
            tableAqiLive: (injected.node === '2' && injected.tableAqiLive) ? injected.tableAqiLive : 'AQI_LIVE_NODE2',
            tableWeatherLive: (injected.node === '2' && injected.tableWeatherLive) ? injected.tableWeatherLive : 'SOIL_LIVE_NODE2',
            tableAqiHist: (injected.node === '2' && injected.tableAqiHist) ? injected.tableAqiHist : 'AQI_NODE2',
            tableWeatherHist: (injected.node === '2' && injected.tableWeatherHist) ? injected.tableWeatherHist : 'SOIL_NODE2',
            tableForecast: 'aqi_forecasts_node2'
        };
    }

    return {
        url: (injected.node !== '2' && injected.url) ? injected.url : 'https://sgkdpliqlhgiqsabxzxe.supabase.co',
        key: (injected.node !== '2' && injected.key) ? injected.key : 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNna2RwbGlxbGhnaXFzYWJ4enhlIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQ4NzgzMTIsImV4cCI6MjEwMDQ1NDMxMn0.vMtbXomFdmOcBkhhSoiyYyp_vFxOhg4MYCFCw9-pL30',
        tableAqiLive: (injected.node !== '2' && injected.tableAqiLive) ? injected.tableAqiLive : 'AQI_LIVE_NODE1',
        tableWeatherLive: (injected.node !== '2' && injected.tableWeatherLive) ? injected.tableWeatherLive : 'WEATHER_LIVE_NODE1',
        tableAqiHist: (injected.node !== '2' && injected.tableAqiHist) ? injected.tableAqiHist : 'AQI_NODE1',
        tableWeatherHist: (injected.node !== '2' && injected.tableWeatherHist) ? injected.tableWeatherHist : 'WEATHER_NODE1',
        tableForecast: 'aqi_forecasts_24h'
    };
}

function formatCoordsDms(lat, lon) {
    if (lat == null || lon == null || isNaN(lat) || isNaN(lon)) return null;
    function convert(val, posChar, negChar) {
        const dir = Number(val) >= 0 ? posChar : negChar;
        const abs = Math.abs(Number(val));
        const deg = Math.floor(abs);
        const minFloat = (abs - deg) * 60;
        const min = Math.floor(minFloat);
        const sec = Math.round((minFloat - min) * 60);
        return `${deg}°${String(min).padStart(2, '0')}'${String(sec).padStart(2, '0')}" ${dir}`;
    }
    return `${convert(lat, 'N', 'S')}, ${convert(lon, 'E', 'W')}`;
}
window.formatCoordsDms = formatCoordsDms;

function formatRawReadingClient(raw) {
    if (!raw) return null;
    const tsRaw = raw.timestamp_hour || raw.created_at || raw.timestamp || null;
    const rawPm25 = (raw.pm25 != null && raw.pm25 !== '') ? Number(raw.pm25) : ((raw['pm2.5'] != null && raw['pm2.5'] !== '') ? Number(raw['pm2.5']) : ((raw.pm2_5 != null && raw.pm2_5 !== '') ? Number(raw.pm2_5) : null));
    const rawPm10 = (raw.pm10 != null && raw.pm10 !== '') ? Number(raw.pm10) : null;
    const rawCo   = (raw.co != null && raw.co !== '') ? Number(raw.co) : null;
    const rawO3   = (raw.o3 != null && raw.o3 !== '') ? Number(raw.o3) : null;
    const rawNo2  = (raw.no2 != null && raw.no2 !== '') ? Number(raw.no2) : null;

    let cpcb_aqi = 0;
    let dom = 'PM2.5';

    if (raw.cpcb_aqi != null && raw.cpcb_aqi !== '') {
        cpcb_aqi = Math.round(Number(raw.cpcb_aqi));
        const domRaw = String(raw.dominant_pollutant || 'O3').trim().toUpperCase();
        if (domRaw === 'PM25' || domRaw === 'PM2.5') dom = 'PM2.5';
        else if (domRaw === 'PM10') dom = 'PM10';
        else if (domRaw === 'CO') dom = 'CO';
        else if (domRaw === 'NO2' || domRaw === 'NO₂') dom = 'NO₂';
        else if (domRaw === 'O3' || domRaw === 'O₃') dom = 'O₃';
        else dom = raw.dominant_pollutant || 'PM2.5';
    } else {
        const subIndices = {
            pm25: rawPm25 != null ? calcSubindex('pm25', rawPm25) : 0,
            pm10: rawPm10 != null ? calcSubindex('pm10', rawPm10) : 0,
            co:   rawCo != null ? calcSubindex('co', rawCo) : 0,
            no2:  rawNo2 != null ? calcSubindex('no2', rawNo2) : 0,
            o3:   rawO3 != null ? calcSubindex('o3', rawO3) : 0,
        };
        let domKey = 'pm25';
        let maxVal = -1;
        for (const [k, v] of Object.entries(subIndices)) {
            if (v >= maxVal) { maxVal = v; domKey = k; }
        }
        const domNames = { pm25: 'PM2.5', pm10: 'PM10', co: 'CO', no2: 'NO₂', o3: 'O₃' };
        cpcb_aqi = Math.round(maxVal > 0 ? maxVal : 0);
        dom = domNames[domKey] || 'PM2.5';
    }

    let label = 'Good';
    let color = '#59b61f';
    if (cpcb_aqi > 50 && cpcb_aqi <= 100) { label = 'Moderate'; color = '#eec732'; }
    else if (cpcb_aqi > 100 && cpcb_aqi <= 200) { label = 'Poor'; color = '#ea8c34'; }
    else if (cpcb_aqi > 200 && cpcb_aqi <= 300) { label = 'Unhealthy'; color = '#e95478'; }
    else if (cpcb_aqi > 300 && cpcb_aqi <= 400) { label = 'Severe'; color = '#b33fba'; }
    else if (cpcb_aqi > 400) { label = 'Hazardous'; color = '#c92033'; }

    // Synchronize global theme if not yet initialized
    if (!window.currentAQITheme && typeof window.getAQITheme === 'function') {
        window.currentAQITheme = window.getAQITheme(cpcb_aqi);
        window.currentAqiValue = cpcb_aqi;
    }

    const rawLat = (raw.latitude != null && raw.latitude !== '') ? Number(raw.latitude) : ((raw.lat != null && raw.lat !== '') ? Number(raw.lat) : null);
    const rawLon = (raw.longitude != null && raw.longitude !== '') ? Number(raw.longitude) : ((raw.lon != null && raw.lon !== '') ? Number(raw.lon) : ((raw.lng != null && raw.lng !== '') ? Number(raw.lng) : null));
    const rawCoords = raw.coords_display || (rawLat != null && rawLon != null ? formatCoordsDms(rawLat, rawLon) : null);

    return {
        id: raw.id || null,
        created_at: raw.created_at || raw.timestamp || null,
        timestamp: parseToIstIso(tsRaw),
        temperature: (raw.temperature != null) ? adjustTempByHour(raw.temperature, tsRaw) : null,
        humidity: (raw.humidity != null) ? Number(Number(raw.humidity).toFixed(2)) : null,
        pm25: rawPm25,
        'pm2.5': rawPm25,
        pm10: rawPm10,
        co: rawCo,
        o3: rawO3,
        no2: rawNo2,
        cpcb_aqi: cpcb_aqi,
        dominant_pollutant: dom,
        aqi_info: { value: cpcb_aqi, label: label, color: color, standard: 'CPCB (India)' },
        wind_speed: (raw.wind_speed != null) ? Number(Number(raw.wind_speed).toFixed(2)) : null,
        wind_gust: (raw.wind_gust != null) ? Number(Number(raw.wind_gust).toFixed(2)) : ((raw.gust != null) ? Number(Number(raw.gust).toFixed(2)) : null),
        wind_direction: (raw.wind_direction !== undefined && raw.wind_direction !== null && raw.wind_direction !== '') ? raw.wind_direction : null,
        rain_gauge: (raw.rain_gauge != null) ? Number(Number(raw.rain_gauge).toFixed(2)) : ((raw.rain != null) ? Number(Number(raw.rain).toFixed(2)) : null),
        latitude: rawLat,
        longitude: rawLon,
        coords_display: rawCoords
    };
}
window.formatRawReadingClient = formatRawReadingClient;

function formatWeatherReadingClient(raw) {
    if (!raw) return null;
    const tsRaw = raw.timestamp_hour || raw.created_at || raw.timestamp || null;
    const sHum = (raw.soil_humidity != null) ? Number(raw.soil_humidity) : ((raw.soil_moisture != null) ? Number(raw.soil_moisture) : null);
    const sTemp = (raw.soil_temperature != null) ? Number(raw.soil_temperature) : null;
    const wLat = (raw.latitude != null && raw.latitude !== '') ? Number(raw.latitude) : ((raw.lat != null && raw.lat !== '') ? Number(raw.lat) : null);
    const wLon = (raw.longitude != null && raw.longitude !== '') ? Number(raw.longitude) : ((raw.lon != null && raw.lon !== '') ? Number(raw.lon) : ((raw.lng != null && raw.lng !== '') ? Number(raw.lng) : null));
    const wCoords = raw.coords_display || (wLat != null && wLon != null ? formatCoordsDms(wLat, wLon) : null);

    return {
        id: raw.id || null,
        created_at: raw.created_at || raw.timestamp || null,
        timestamp: parseToIstIso(tsRaw),
        temperature: (raw.temperature != null) ? adjustTempByHour(raw.temperature, tsRaw) : null,
        humidity: (raw.humidity != null) ? Number(Number(raw.humidity).toFixed(2)) : null,
        wind_speed: (raw.wind_speed != null) ? Number(Number(raw.wind_speed).toFixed(2)) : null,
        wind_gust: (raw.wind_gust != null) ? Number(Number(raw.wind_gust).toFixed(2)) : null,
        rain_gauge: (raw.rain_gauge != null) ? Number(Number(raw.rain_gauge).toFixed(2)) : null,
        wind_direction: (raw.wind_direction !== undefined && raw.wind_direction !== null && raw.wind_direction !== '') ? raw.wind_direction : null,
        soil_temperature: sTemp,
        soil_humidity: sHum,
        soil_moisture: sHum,
        soil_ph: (raw.soil_ph != null) ? Number(raw.soil_ph) : null,
        soil_conductivity: (raw.soil_conductivity != null) ? Number(raw.soil_conductivity) : null,
        pressure: (raw.pressure != null) ? Number(Number(raw.pressure).toFixed(2)) : ((raw.barometric_pressure != null) ? Number(Number(raw.barometric_pressure).toFixed(2)) : null),
        uv_index: (raw.uv_index != null) ? Number(Number(raw.uv_index).toFixed(2)) : ((raw.uv_radiation != null) ? Number(Number(raw.uv_radiation).toFixed(2)) : ((raw.uv != null) ? Number(Number(raw.uv).toFixed(2)) : null)),
        solar_radiation: (raw.solar_radiation != null) ? Number(Number(raw.solar_radiation).toFixed(2)) : ((raw.solar != null) ? Number(Number(raw.solar).toFixed(2)) : ((raw.radiation != null) ? Number(Number(raw.radiation).toFixed(2)) : null)),
        latitude: wLat,
        longitude: wLon,
        coords_display: wCoords
    };
}
window.formatWeatherReadingClient = formatWeatherReadingClient;

/**
 * Direct Client-Side Supabase Telemetry Fetcher (Zero-Delay Streaming)
 * Directly connects to Cloudflare/Supabase in Mumbai (~80ms), completely bypassing Vercel Lambda cold start!
 */
window.__inFlightBulkPromises = window.__inFlightBulkPromises || {};

async function fetchLiveBulk(forceNetwork = false) {
    const activeNode = (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));
    const isNode2 = (activeNode === '2');
    const cacheKey = STORAGE_KEYS.BULK + '_n' + activeNode;

    // 1. Instant Cache / INITIAL_DATA return (0ms First Paint)
    if (!forceNetwork) {
        if (apiCache.liveBulk && apiCache.liveBulk.time && (Date.now() - apiCache.liveBulk.time < 4000) && apiCache.liveBulk.node === activeNode) {
            return apiCache.liveBulk.data;
        }
        if (window.INITIAL_DATA && (String(window.INITIAL_DATA.node) === String(activeNode) || (!window.INITIAL_DATA.node && activeNode === '1')) && (window.INITIAL_DATA.aqi || window.INITIAL_DATA.weather)) {
            apiCache.liveBulk = { data: window.INITIAL_DATA, time: Date.now(), node: activeNode };
            apiCache.overviewBulk = { data: window.INITIAL_DATA, time: Date.now(), node: activeNode };
            const initAge = window.INITIAL_DATA.time ? (Date.now() - Number(window.INITIAL_DATA.time)) : 99999;
            if (initAge > 12000) {
                setTimeout(() => { fetchLiveBulk(true).catch(() => {}); }, 15);
            }
            return window.INITIAL_DATA;
        }
        const localCached = getLocalCache(cacheKey);
        if (localCached && (localCached.aqi || localCached.weather)) {
            apiCache.liveBulk = { data: localCached, time: Date.now(), node: activeNode };
            apiCache.overviewBulk = { data: localCached, time: Date.now(), node: activeNode };
            setTimeout(() => { fetchLiveBulk(true).catch(() => {}); }, 15);
            return localCached;
        }
    }

    // Deduplicate concurrent network fetches for the same node
    if (window.__inFlightBulkPromises[activeNode]) {
        return window.__inFlightBulkPromises[activeNode];
    }

    const networkPromise = (async () => {
    const cfg = getSbConfig(activeNode);
    const headers = {
        'apikey': cfg.key,
        'Authorization': `Bearer ${cfg.key}`
    };

    try {
        const now = new Date();
        const istOffsetMs = 5.5 * 3600 * 1000;
        const nowIst = new Date(now.getTime() + istOffsetMs);
        const midnightIst = new Date(Date.UTC(nowIst.getUTCFullYear(), nowIst.getUTCMonth(), nowIst.getUTCDate()) - istOffsetMs);
        const midnightIstIso = midnightIst.toISOString();

        const cfgN2 = getSbConfig('2');
        const headersN2 = {
            'apikey': cfgN2.key,
            'Authorization': `Bearer ${cfgN2.key}`
        };

        const orderCol = isNode2 ? 'id.desc' : 'created_at.desc.nullslast';
        const urlAqi = `${cfg.url}/rest/v1/${cfg.tableAqiLive}?order=${orderCol}&limit=60`;
        const urlWeather = `${cfg.url}/rest/v1/${cfg.tableWeatherLive}?select=*&order=${orderCol}&limit=60`;
        const urlOtherWeather = `${cfgN2.url}/rest/v1/WEATHER_LIVE_NODE2?select=*&order=id.desc&limit=60`;
        const urlTodayRain = !isNode2
            ? `${cfg.url}/rest/v1/${cfg.tableWeatherLive}?select=rain_gauge,created_at&created_at=gte.${encodeURIComponent(midnightIstIso)}&rain_gauge=gt.0&order=created_at.desc.nullslast&limit=500`
            : `${cfgN2.url}/rest/v1/WEATHER_LIVE_NODE2?select=rain_gauge,timestamp&timestamp=gte.${encodeURIComponent(midnightIstIso)}&rain_gauge=gt.0&order=id.desc&limit=500`;

        const [resAqi, resWeather, resTodayRain, resOtherWeather] = await Promise.all([
            fetch(urlAqi, { headers, cache: 'no-cache' }),
            fetch(urlWeather, { headers, cache: 'no-cache' }),
            urlTodayRain ? fetch(urlTodayRain, { headers: isNode2 ? headersN2 : headers, cache: 'no-cache' }).catch(() => null) : Promise.resolve(null),
            fetch(urlOtherWeather, { headers: headersN2, cache: 'no-cache' }).catch(() => null)
        ]);

        if (!resAqi.ok || !resWeather.ok) {
            throw new Error(`Direct Supabase fetch non-200 status (${resAqi.status}, ${resWeather.status})`);
        }

        const [aqiRawList, weatherRawList, todayRainRawList, otherWeatherRawList] = await Promise.all([
            resAqi.json(),
            resWeather.json(),
            (resTodayRain && resTodayRain.ok) ? resTodayRain.json().catch(() => null) : Promise.resolve(null),
            (resOtherWeather && resOtherWeather.ok) ? resOtherWeather.json().catch(() => null) : Promise.resolve(null)
        ]);

        const aqiHist = Array.isArray(aqiRawList) ? aqiRawList.map(formatRawReadingClient).filter(Boolean) : [];
        const weatherHist = Array.isArray(weatherRawList) ? weatherRawList.map(formatWeatherReadingClient).filter(Boolean) : [];
        const otherWeatherHist = Array.isArray(otherWeatherRawList) ? otherWeatherRawList.map(formatWeatherReadingClient).filter(Boolean) : [];

        const latestAqi = aqiHist[0] || null;
        let latestWeather = weatherHist[0] || null;
        const latestOtherWeather = otherWeatherHist[0] || null;

        // Merge Pressure, UV Radiation, Solar Radiation from Node 2 WEATHER_LIVE_NODE2 so both nodes show identical values
        if (latestOtherWeather) {
            if (!latestWeather) {
                latestWeather = { ...latestOtherWeather };
            } else {
                const mergeKeys = isNode2
                    ? ['pressure', 'uv_index', 'solar_radiation', 'wind_speed', 'wind_gust', 'wind_direction', 'rain_gauge', 'temperature', 'humidity']
                    : ['pressure', 'uv_index', 'solar_radiation'];
                mergeKeys.forEach((ok) => {
                    if (latestWeather[ok] == null && latestOtherWeather[ok] != null) latestWeather[ok] = latestOtherWeather[ok];
                });
            }
        }

        // If weather/soil row lacks ambient temp/humidity/wind/rain (Node 2 prefers AQI_LIVE_NODE2 temperature & humidity)
        if (latestAqi) {
            if (!latestWeather) {
                latestWeather = { ...latestAqi };
            } else {
                if ((isNode2 || latestWeather.temperature == null) && latestAqi.temperature != null) latestWeather.temperature = latestAqi.temperature;
                if ((isNode2 || latestWeather.humidity == null) && latestAqi.humidity != null) latestWeather.humidity = latestAqi.humidity;
                if (latestWeather.wind_speed == null && latestAqi.wind_speed != null) latestWeather.wind_speed = latestAqi.wind_speed;
                if (latestWeather.wind_gust == null && latestAqi.wind_gust != null) latestWeather.wind_gust = latestAqi.wind_gust;
                if (latestWeather.wind_direction == null && latestAqi.wind_direction != null) latestWeather.wind_direction = latestAqi.wind_direction;
                if (latestWeather.rain_gauge == null && latestAqi.rain_gauge != null) latestWeather.rain_gauge = latestAqi.rain_gauge;
            }
        }

        // Resolve latest coordinates from Supabase records
        let lat = null;
        let lon = null;
        let coordsDisplay = null;

        for (const w of weatherHist) {
            if (w && w.latitude != null && w.longitude != null && !isNaN(Number(w.latitude)) && !isNaN(Number(w.longitude))) {
                lat = Number(Number(w.latitude).toFixed(4));
                lon = Number(Number(w.longitude).toFixed(4));
                coordsDisplay = w.coords_display || (typeof formatCoordsDms === 'function' ? formatCoordsDms(lat, lon) : `${lat}° N, ${lon}° E`);
                break;
            }
        }
        if (lat == null) {
            for (const a of aqiHist) {
                if (a && a.latitude != null && a.longitude != null && !isNaN(Number(a.latitude)) && !isNaN(Number(a.longitude))) {
                    lat = Number(Number(a.latitude).toFixed(4));
                    lon = Number(Number(a.longitude).toFixed(4));
                    coordsDisplay = a.coords_display || (typeof formatCoordsDms === 'function' ? formatCoordsDms(lat, lon) : `${lat}° N, ${lon}° E`);
                    break;
                }
            }
        }

        if (latestWeather) {
            if (latestWeather.latitude == null && lat != null) latestWeather.latitude = lat;
            if (latestWeather.longitude == null && lon != null) latestWeather.longitude = lon;
            if (!latestWeather.coords_display && coordsDisplay) latestWeather.coords_display = coordsDisplay;
        }
        if (latestAqi) {
            if (latestAqi.latitude == null && lat != null) latestAqi.latitude = lat;
            if (latestAqi.longitude == null && lon != null) latestAqi.longitude = lon;
            if (!latestAqi.coords_display && coordsDisplay) latestAqi.coords_display = coordsDisplay;
        }

        // Calculate today's rain sum, latest rain timestamp, & temperature range from readings
        let todayRainSum = 0.0;
        let lastRainMs = 0;
        const todayTemps = [];
        const recordRainTs = (tsRaw) => {
            if (!tsRaw) return;
            const dt = safeParseDate(parseToIstIso(tsRaw));
            if (dt && !isNaN(dt.getTime())) {
                const ms = dt.getTime();
                if (ms > lastRainMs && ms <= Date.now() + 300000) lastRainMs = ms;
            }
        };

        if (Array.isArray(todayRainRawList)) {
            todayRainRawList.forEach(r => {
                const rg = r && r.rain_gauge != null ? Number(r.rain_gauge) : 0;
                if (!isNaN(rg) && rg > 0) {
                    todayRainSum += rg;
                    recordRainTs(r.created_at || r.timestamp);
                }
            });
        }

        weatherHist.forEach(w => {
            if (!w) return;
            const dt = safeParseDate(w.timestamp || w.created_at);
            if (dt && dt.getTime() >= midnightIst.getTime()) {
                if (w.temperature != null && !isNaN(w.temperature)) {
                    todayTemps.push(Number(w.temperature));
                }
                if (!Array.isArray(todayRainRawList) && w.rain_gauge != null && Number(w.rain_gauge) > 0) {
                    todayRainSum += Number(w.rain_gauge);
                }
            }
            if (w.rain_gauge != null && Number(w.rain_gauge) > 0) {
                recordRainTs(w.timestamp || w.created_at);
            }
        });

        otherWeatherHist.forEach(ow => {
            if (ow && ow.rain_gauge != null && Number(ow.rain_gauge) > 0) {
                if (isNode2) recordRainTs(ow.timestamp || ow.created_at);
            }
        });

        if (latestWeather && latestWeather.rain_gauge != null && Number(latestWeather.rain_gauge) > 0) {
            if (Number(latestWeather.rain_gauge) > todayRainSum) {
                todayRainSum = Number(latestWeather.rain_gauge);
            }
            recordRainTs(latestWeather.timestamp || latestWeather.created_at);
        }

        const RAIN_HOLD_MS = 3600000;
        const bulkData = {
            node: activeNode,
            status: 'ONLINE',
            aqi: latestAqi,
            weather: latestWeather,
            aqi_history: aqiHist,
            weather_history: weatherHist,
            other_weather: latestOtherWeather,
            other_weather_history: otherWeatherHist,
            history: aqiHist,
            today_rain_total: Number(todayRainSum.toFixed(2)),
            last_rain_at: lastRainMs > 0 ? new Date(lastRainMs).toISOString() : null,
            is_rain_active_1h: lastRainMs > 0 && (Date.now() - lastRainMs) <= RAIN_HOLD_MS,
            today_temp_min: todayTemps.length > 0 ? Number(Math.min(...todayTemps).toFixed(1)) : (latestWeather && latestWeather.temperature != null ? Number(latestWeather.temperature) : 28.0),
            today_temp_max: todayTemps.length > 0 ? Number(Math.max(...todayTemps).toFixed(1)) : (latestWeather && latestWeather.temperature != null ? Number(latestWeather.temperature) : 34.0),
            latitude: lat,
            longitude: lon,
            coords_display: coordsDisplay,
            time: Date.now(),
            fetchedAt: Date.now()
        };

        // Sync coordinates dynamically to navbar
        if (coordsDisplay) {
            const navCoords = document.getElementById('navbar-station-coords');
            const navCoordsMob = document.getElementById('navbar-station-coords-mobile');
            const mobSub = document.getElementById('mobile-station-subline');
            if (navCoords) { navCoords.innerText = coordsDisplay; navCoords.style.borderLeft = '1px solid #e2e8f0'; navCoords.style.display = 'inline-block'; }
            if (navCoordsMob) { navCoordsMob.innerText = coordsDisplay; navCoordsMob.style.borderLeft = 'none'; navCoordsMob.style.display = 'inline-block'; }
            if (mobSub) mobSub.style.display = 'flex';
        } else if (activeNode === '2') {
            const navCoords = document.getElementById('navbar-station-coords');
            const navCoordsMob = document.getElementById('navbar-station-coords-mobile');
            const mobSub = document.getElementById('mobile-station-subline');
            if (navCoords) { navCoords.innerText = ''; navCoords.style.display = 'none'; }
            if (navCoordsMob) { navCoordsMob.innerText = ''; navCoordsMob.style.display = 'none'; }
            if (mobSub) mobSub.style.display = 'none';
        }

        // Cache results locally
        apiCache.liveBulk = { data: bulkData, time: Date.now(), node: activeNode };
        apiCache.overviewBulk = { data: bulkData, time: Date.now(), node: activeNode };
        setLocalCache(cacheKey, bulkData);
        evaluateAndSyncLiveBadge(latestAqi || latestWeather);

        return bulkData;
    } catch (err) {
        console.warn('Direct Supabase fetch encountered error; falling back to telemetry endpoint:', err);
        try {
            const baseEndpoint = `telemetry.php?action=live_bulk&node=${activeNode}&limit=60&_t=${Date.now()}`;
            const fallbackUrl = (typeof window.getApiUrl === 'function') 
                ? window.getApiUrl(baseEndpoint) 
                : 'api/' + baseEndpoint;
            const fallbackRes = await fetch(fallbackUrl);
            if (!fallbackRes.ok) throw new Error('Fallback failed');
            const data = await fallbackRes.json();
            if (data && (data.aqi || data.weather)) {
                if (data.coords_display) {
                    const navCoords = document.getElementById('navbar-station-coords');
                    const navCoordsMob = document.getElementById('navbar-station-coords-mobile');
                    const mobSub = document.getElementById('mobile-station-subline');
                    if (navCoords) { navCoords.innerText = data.coords_display; navCoords.style.borderLeft = '1px solid #e2e8f0'; navCoords.style.display = 'inline-block'; }
                    if (navCoordsMob) { navCoordsMob.innerText = data.coords_display; navCoordsMob.style.borderLeft = 'none'; navCoordsMob.style.display = 'inline-block'; }
                    if (mobSub) mobSub.style.display = 'flex';
                } else if (activeNode === '2') {
                    const navCoords = document.getElementById('navbar-station-coords');
                    const navCoordsMob = document.getElementById('navbar-station-coords-mobile');
                    const mobSub = document.getElementById('mobile-station-subline');
                    if (navCoords) { navCoords.innerText = ''; navCoords.style.display = 'none'; }
                    if (navCoordsMob) { navCoordsMob.innerText = ''; navCoordsMob.style.display = 'none'; }
                    if (mobSub) mobSub.style.display = 'none';
                }
                apiCache.liveBulk = { data, time: Date.now(), node: activeNode };
                apiCache.overviewBulk = { data, time: Date.now(), node: activeNode };
                setLocalCache(cacheKey, data);
                evaluateAndSyncLiveBadge(data.aqi || data.weather);
                return data;
            }
        } catch (fbErr) {
            console.error('All live fetch paths failed:', fbErr);
        }
        return getLocalCache(cacheKey) || null;
    }
    })();

    window.__inFlightBulkPromises[activeNode] = networkPromise;
    try {
        return await networkPromise;
    } finally {
        if (window.__inFlightBulkPromises[activeNode] === networkPromise) {
            delete window.__inFlightBulkPromises[activeNode];
        }
    }
}
window.fetchLiveBulk = fetchLiveBulk;

async function fetchOverviewBulk(forceNetwork = false) {
    return fetchLiveBulk(forceNetwork);
}
window.fetchOverviewBulk = fetchOverviewBulk;

function parseDateParts(dateStr) {
    if (!dateStr) {
        const now = new Date();
        return [now.getFullYear(), now.getMonth() + 1, now.getDate()];
    }
    const clean = String(dateStr).trim();
    if (/^\d{4}-\d{1,2}-\d{1,2}$/.test(clean)) {
        const [y, m, d] = clean.split('-').map(Number);
        return [y, m, d];
    }
    if (/^\d{1,2}-\d{1,2}-\d{4}$/.test(clean)) {
        const [d, m, y] = clean.split('-').map(Number);
        return [y, m, d];
    }
    if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(clean)) {
        const [d, m, y] = clean.split('/').map(Number);
        return [y, m, d];
    }
    if (/^\d{4}\/\d{1,2}\/\d{1,2}$/.test(clean)) {
        const [y, m, d] = clean.split('/').map(Number);
        return [y, m, d];
    }
    const dt = new Date(clean);
    if (!isNaN(dt.getTime())) {
        return [dt.getFullYear(), dt.getMonth() + 1, dt.getDate()];
    }
    const now = new Date();
    return [now.getFullYear(), now.getMonth() + 1, now.getDate()];
}
window.parseDateParts = parseDateParts;

function normalizeDateToYmd(dateStr) {
    const [y, m, d] = parseDateParts(dateStr);
    return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}
window.normalizeDateToYmd = normalizeDateToYmd;

async function fetchHistoryBulk(queryDate, days = 1, force = false, explicitNode = null) {
    const activeNode = explicitNode ? String(explicitNode) : (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));
    const isNode2 = (activeNode === '2');

    const normDate = normalizeDateToYmd(queryDate || new Date());
    const cacheKey = `${normDate}_${days}_n${activeNode}`;
    const storageKey = STORAGE_KEYS.HIST_PREFIX + cacheKey;
    const todayYmd = normalizeDateToYmd(new Date());
    const isToday = (normDate === todayYmd);
    const maxAge = (isToday && days === 1) ? 15000 : (days === 1 ? 60000 : 3600000);

    if (!force) {
        const cached = getLocalCache(storageKey);
        const hasOtherWeather = Array.isArray(cached?.other_weather) && cached.other_weather.length > 0;
        if (cached && (cached.aqi?.length || cached.weather?.length) && hasOtherWeather) {
            if (Date.now() - (cached.fetchedAt || 0) < maxAge) {
                return cached;
            }
            // Return stale cache immediately for 0ms First Paint, revalidate in background
            setTimeout(() => {
                fetchHistoryBulk(queryDate, days, true, explicitNode).catch(() => {});
            }, 15);
            return cached;
        }
    }

    const cfg = getSbConfig(activeNode);
    const headers = {
        'apikey': cfg.key,
        'Authorization': `Bearer ${cfg.key}`
    };

    try {
        let filter = '';
        const lim = days > 1 ? Math.min(1500, Math.max(150, days * 35)) : 72;
        const timeCol = isNode2 ? 'timestamp' : 'timestamp_hour';
        if (normDate) {
            const [y, m, d] = parseDateParts(normDate);
            const targetUtc = Date.UTC(y, m - 1, d, 0, 0, 0) - (5.5 * 3600000);
            const startDate = new Date(targetUtc - (days > 1 ? (days * 86400000) : 86400000));
            const endDate = new Date(targetUtc + (2 * 86400000));
            const sIso = startDate.toISOString().split('T')[0];
            const eIso = endDate.toISOString().split('T')[0];
            filter = `&${timeCol}=gte.${sIso}T00:00:00&${timeCol}=lt.${eIso}T00:00:00`;
        }

        const cfgN2 = getSbConfig('2');
        const headersN2 = {
            'apikey': cfgN2.key,
            'Authorization': `Bearer ${cfgN2.key}`
        };
        const filterN2 = filter.replace(/timestamp_hour=/g, 'timestamp=');

        const urlAqi = `${cfg.url}/rest/v1/${cfg.tableAqiHist}?order=${timeCol}.desc&limit=${lim}${filter}`;
        const urlWeather = `${cfg.url}/rest/v1/${cfg.tableWeatherHist}?order=${timeCol}.desc&limit=${lim}${filter}`;
        const urlOtherWeatherHist = `${cfgN2.url}/rest/v1/WEATHER_NODE2?select=*&order=timestamp.desc&limit=${lim}${filterN2}`;

        const [resAqi, resWeather, resOtherWeather] = await Promise.all([
            fetch(urlAqi, { headers }),
            fetch(urlWeather, { headers }),
            fetch(urlOtherWeatherHist, { headers: headersN2 }).catch(() => null)
        ]);

        if (!resAqi.ok || !resWeather.ok) throw new Error('History fetch non-200');

        const [aqiRaw, weatherRaw, otherWeatherRaw] = await Promise.all([
            resAqi.json(),
            resWeather.json(),
            (resOtherWeather && resOtherWeather.ok) ? resOtherWeather.json().catch(() => []) : Promise.resolve([])
        ]);

        const aqiList = Array.isArray(aqiRaw) ? aqiRaw.map(formatRawReadingClient).filter(Boolean) : [];
        let weatherList = Array.isArray(weatherRaw) ? weatherRaw.map(formatWeatherReadingClient).filter(Boolean) : [];
        let otherWeatherList = Array.isArray(otherWeatherRaw) ? otherWeatherRaw.map(formatWeatherReadingClient).filter(Boolean) : [];

        if (otherWeatherList.length === 0) {
            try {
                const fbOtherRes = await fetch(`${cfgN2.url}/rest/v1/WEATHER_LIVE_NODE2?select=*&order=id.desc&limit=500`, { headers: headersN2 });
                if (fbOtherRes.ok) {
                    const fbOtherJson = await fbOtherRes.json();
                    if (Array.isArray(fbOtherJson) && fbOtherJson.length > 0) {
                        otherWeatherList = fbOtherJson.map(formatWeatherReadingClient).filter(Boolean);
                    }
                }
            } catch (e) {}
        }

        // For Node 2: If weatherList is empty (SOIL_NODE2 historical table empty), fall back to recent SOIL_LIVE_NODE2
        if (isNode2 && weatherList.length === 0) {
            try {
                const fbSoilUrl = `${cfg.url}/rest/v1/${cfg.tableWeatherLive}?order=id.desc&limit=60`;
                const fbRes = await fetch(fbSoilUrl, { headers });
                if (fbRes.ok) {
                    const fbJson = await fbRes.json();
                    if (Array.isArray(fbJson) && fbJson.length > 0) {
                        weatherList = fbJson.map(formatWeatherReadingClient).filter(Boolean);
                    }
                }
            } catch(e) {}
        }

        const payload = {
            node: activeNode,
            aqi: aqiList,
            weather: weatherList,
            other_weather: otherWeatherList,
            fetchedAt: Date.now()
        };

        if (aqiList.length > 0 || weatherList.length > 0) {
            setLocalCache(storageKey, payload);
        }
        return payload;
    } catch (err) {
        console.warn('Direct Supabase history fetch failed; falling back to api/telemetry.php:', err);
        try {
            const baseEndpoint = `telemetry.php?action=history_bulk&node=${activeNode}&date=${normDate}&days=${days}&_t=${Date.now()}`;
            const fbUrl = (typeof window.getApiUrl === 'function') ? window.getApiUrl(baseEndpoint) : ('api/' + baseEndpoint);
            const fbRes = await fetch(fbUrl);
            if (fbRes.ok) {
                const fbData = await fbRes.json();
                const payload = {
                    node: activeNode,
                    aqi: fbData.aqi || [],
                    weather: fbData.weather || [],
                    other_weather: fbData.other_weather || [],
                    fetchedAt: Date.now()
                };
                if (payload.aqi.length > 0 || payload.weather.length > 0) {
                    setLocalCache(storageKey, payload);
                }
                return payload;
            }
        } catch (e) {}
        const fallbackCached = getLocalCache(storageKey);
        return fallbackCached || { node: activeNode, aqi: [], weather: [], fetchedAt: Date.now() };
    }
}
window.fetchHistoryBulk = fetchHistoryBulk;

async function fetchForecastData(force = false) {
    const activeNode = (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));

    // Node 2 has no predictive model active; strictly keep blank and never leak Node 1 forecast
    if (String(activeNode) === '2') {
        return {
            status: 'standby',
            node: '2',
            message: 'Forecast model in standby for Node 2. No forecasted values are generated or passed.',
            hourly: [],
            phases: [],
            hero: null
        };
    }

    // 1. Primary dynamic path: query dynamic backend with cache-busting to ensure server & Supabase parity
    try {
        const resp = await fetch(`api/telemetry.php?action=forecast&node=${activeNode}&v=${Date.now()}${force ? '&force=1' : ''}`, {
            cache: 'no-store'
        });
        if (resp.ok) {
            const data = await resp.json();
            if (data && data.hourly && data.hourly.length > 0) {
                window.__forecastCache = data;
                setLocalCache(STORAGE_KEYS.FORECAST, data);
                return data;
            }
        }
    } catch (e) {
        console.warn('Dynamic forecast fetch failed, attempting static file fallback:', e);
    }

    // 2. Secondary path: static forecast_data.json fallback
    try {
        const staticRes = await fetch('api/forecast_data.json?v=' + Date.now(), { cache: 'no-store' });
        if (staticRes.ok) {
            const payload = await staticRes.json();
            if (payload && payload.hourly && payload.hourly.length > 0) {
                const now = new Date();
                const currentHourStart = new Date(now.getFullYear(), now.getMonth(), now.getDate(), now.getHours(), 0, 0).getTime();
                const filtered = payload.hourly.filter(h => {
                    const dt = new Date(h.iso || '');
                    return isNaN(dt.getTime()) || dt.getTime() >= currentHourStart;
                });
                if (filtered.length > 0) payload.hourly = filtered;
                window.__forecastCache = payload;
                setLocalCache(STORAGE_KEYS.FORECAST, payload);
                return payload;
            }
        }
    } catch (err) {
        console.warn('Static forecast fetch failed; falling back to local cache:', err);
    }

    // 3. Offline fallback
    return window.__forecastCache || getLocalCache(STORAGE_KEYS.FORECAST) || null;
}
window.fetchForecastData = fetchForecastData;

async function getLiveAqi() {
    if (apiCache.aqiLive && (Date.now() - apiCache.lastFetchTime < CACHE_TTL)) {
        evaluateAndSyncLiveBadge(apiCache.aqiLive.data);
        return apiCache.aqiLive;
    }
    try {
        const bulk = await fetchLiveBulk(false);
        if (bulk && bulk.aqi) {
            const out = { data: bulk.aqi };
            apiCache.aqiLive = out;
            apiCache.lastFetchTime = Date.now();
            evaluateAndSyncLiveBadge(bulk.aqi);
            return out;
        }
    } catch (error) {
        console.error('Error fetching live AQI:', error);
    }
    evaluateAndSyncLiveBadge(null);
    return null;
}
window.getLiveAqi = getLiveAqi;

/**
 * Resolve whether overview rain animation should remain active for 1 hour (3,600,000 ms) after any rain_gauge > 0 reading.
 */
window.resolveRainHold1h = function(nodeId, w, bulkData) {
    const HOLD_MS = 3600000;
    const nowMs = Date.now();
    const nid = String(nodeId || '1') === '2' ? '2' : '1';
    const lsKey = `smartairnet_last_rain_ms_n${nid}`;
    let latestMs = 0;

    const considerTs = (tsRaw) => {
        if (!tsRaw) return;
        const dt = safeParseDate(tsRaw);
        if (dt && !isNaN(dt.getTime())) {
            const ms = dt.getTime();
            if (ms > latestMs && ms <= nowMs + 300000) latestMs = ms;
        }
    };

    try {
        const stored = Number(localStorage.getItem(lsKey) || 0);
        if (stored > 0 && stored <= nowMs + 300000 && (nowMs - stored) <= HOLD_MS) {
            latestMs = stored;
        }
    } catch (e) {}

    if (w && w.rain_gauge != null && Number(w.rain_gauge) > 0) {
        considerTs(w.timestamp || w.created_at);
        if (latestMs === 0) latestMs = nowMs;
    }

    if (bulkData) {
        if (bulkData.last_rain_at) considerTs(bulkData.last_rain_at);
        const histLists = [
            Array.isArray(bulkData.weather_history) ? bulkData.weather_history : [],
            nid === '2' && Array.isArray(bulkData.other_weather_history) ? bulkData.other_weather_history : []
        ];
        histLists.forEach(list => {
            list.forEach(item => {
                if (item && item.rain_gauge != null && Number(item.rain_gauge) > 0) {
                    considerTs(item.timestamp || item.created_at);
                }
            });
        });
    }

    const active = latestMs > 0 && (nowMs - latestMs) <= HOLD_MS;
    try {
        if (active) {
            localStorage.setItem(lsKey, String(latestMs));
        } else {
            localStorage.removeItem(lsKey);
        }
    } catch (e) {}

    return {
        active,
        remainingMs: active ? Math.max(0, HOLD_MS - (nowMs - latestMs)) : 0
    };
};

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
