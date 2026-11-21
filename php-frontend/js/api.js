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
        if (activeNode === '2') {
            window.INITIAL_DATA = {
                aqi: null,
                weather: null,
                aqi_history: [],
                weather_history: [],
                history: [],
                today_rain_total: 0.0,
                node: '2',
                status: 'STANDBY'
            };
            return;
        }

        const cachedBulk = getLocalCache(STORAGE_KEYS.BULK + '_n' + activeNode) || getLocalCache(STORAGE_KEYS.BULK);
        if (cachedBulk && (cachedBulk.aqi || cachedBulk.weather)) {
            if (!window.INITIAL_DATA || (!window.INITIAL_DATA.aqi && !window.INITIAL_DATA.weather)) {
                window.INITIAL_DATA = cachedBulk;
            }
            const stamp = cachedBulk.fetchedAt || cachedBulk.time || 0;
            if (!apiCache.liveBulk) apiCache.liveBulk = { data: cachedBulk, time: stamp };
            if (!apiCache.overviewBulk) apiCache.overviewBulk = { data: cachedBulk, time: stamp };
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
        if (hour === 8) offset = -0.5;
        else if (hour === 9) offset = -1.0;
        else if (hour === 10) offset = -1.5;
        else if (hour >= 11 && hour <= 16) offset = -2.0;
        else if (hour === 17) offset = -1.5;
        else if (hour === 18) offset = -1.0;
        else if (hour === 19) offset = -0.5;
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
    if (activeNode === '2') {
        window.updateGlobalLiveBadge(false, 'Node 2 is currently in standby mode. Telemetry hardware pending setup.', 'Standby');
        return;
    }

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
function getSbConfig() {
    const injected = window.SUPABASE_CONFIG || {};
    const activeNode = (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));
    const isNode2 = (activeNode === '2');
    return {
        url: injected.url || 'https://sgkdpliqlhgiqsabxzxe.supabase.co',
        key: injected.key || 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNna2RwbGlxbGhnaXFzYWJ4enhlIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQ4NzgzMTIsImV4cCI6MjEwMDQ1NDMxMn0.vMtbXomFdmOcBkhhSoiyYyp_vFxOhg4MYCFCw9-pL30',
        tableAqiLive: injected.tableAqiLive || (isNode2 ? 'AQI_LIVE_NODE2' : 'AQI_LIVE_NODE1'),
        tableWeatherLive: injected.tableWeatherLive || (isNode2 ? 'WEATHER_LIVE_NODE2' : 'WEATHER_LIVE_NODE1'),
        tableAqiHist: injected.tableAqiHist || (isNode2 ? 'AQI_NODE2' : 'AQI_NODE1'),
        tableWeatherHist: injected.tableWeatherHist || (isNode2 ? 'WEATHER_NODE2' : 'WEATHER_NODE1'),
        tableForecast: injected.tableForecast || (isNode2 ? 'aqi_forecasts_node2' : 'aqi_forecasts_24h')
    };
}

function formatRawReadingClient(raw) {
    if (!raw) return null;
    const tsRaw = raw.timestamp_hour || raw.created_at || raw.timestamp || null;
    const rawPm25 = (raw.pm25 != null && raw.pm25 !== '') ? Number(raw.pm25) : ((raw['pm2.5'] != null && raw['pm2.5'] !== '') ? Number(raw['pm2.5']) : null);
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
        wind_direction: raw.wind_direction || null,
        rain_gauge: (raw.rain_gauge != null) ? Number(Number(raw.rain_gauge).toFixed(2)) : ((raw.rain != null) ? Number(Number(raw.rain).toFixed(2)) : null),
    };
}
window.formatRawReadingClient = formatRawReadingClient;

function formatWeatherReadingClient(raw) {
    if (!raw) return null;
    const tsRaw = raw.timestamp_hour || raw.created_at || raw.timestamp || null;
    return {
        id: raw.id || null,
        created_at: raw.created_at || raw.timestamp || null,
        timestamp: parseToIstIso(tsRaw),
        temperature: (raw.temperature != null) ? adjustTempByHour(raw.temperature, tsRaw) : null,
        humidity: (raw.humidity != null) ? Number(Number(raw.humidity).toFixed(2)) : null,
        wind_speed: (raw.wind_speed != null) ? Number(Number(raw.wind_speed).toFixed(2)) : null,
        wind_gust: (raw.wind_gust != null) ? Number(Number(raw.wind_gust).toFixed(2)) : null,
        rain_gauge: (raw.rain_gauge != null) ? Number(Number(raw.rain_gauge).toFixed(2)) : null,
        wind_direction: raw.wind_direction || null
    };
}
window.formatWeatherReadingClient = formatWeatherReadingClient;

/**
 * Direct Client-Side Supabase Telemetry Fetcher (Zero-Delay Streaming)
 * Directly connects to Cloudflare/Supabase in Mumbai (~80ms), completely bypassing Vercel Lambda cold start!
 */
async function fetchLiveBulk(forceNetwork = false) {
    const activeNode = (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));
    if (activeNode === '2') {
        const standbyBulk = {
            node: '2',
            node_name: 'Node 2 (Station 2)',
            status: 'STANDBY',
            is_live: false,
            aqi: null,
            weather: null,
            aqi_history: [],
            weather_history: [],
            history: [],
            today_rain_total: 0.0,
            today_temp_min: null,
            today_temp_max: null,
            time: Date.now(),
            fetchedAt: Date.now()
        };
        apiCache.liveBulk = { data: standbyBulk, time: Date.now() };
        apiCache.overviewBulk = { data: standbyBulk, time: Date.now() };
        if (typeof window.updateGlobalLiveBadge === 'function') {
            window.updateGlobalLiveBadge(false, 'Node 2 is currently in standby mode.', 'Standby');
        }
        return standbyBulk;
    }

    // 1. Fast Memory Cache Return only if freshly fetched within the last 4 seconds
    if (!forceNetwork && apiCache.liveBulk && apiCache.liveBulk.time && (Date.now() - apiCache.liveBulk.time < 4000)) {
        return apiCache.liveBulk.data;
    }

    const cfg = getSbConfig();
    const headers = {
        'apikey': cfg.key,
        'Authorization': `Bearer ${cfg.key}`
    };

    try {
        const urlAqi = `${cfg.url}/rest/v1/${cfg.tableAqiLive}?order=created_at.desc.nullslast&limit=60`;
        const urlWeather = `${cfg.url}/rest/v1/${cfg.tableWeatherLive}?select=id,temperature,humidity,wind_speed,rain_gauge,created_at,wind_direction,wind_gust&order=created_at.desc.nullslast&limit=60`;

        const [resAqi, resWeather] = await Promise.all([
            fetch(urlAqi, { headers, cache: 'no-cache' }),
            fetch(urlWeather, { headers, cache: 'no-cache' })
        ]);

        if (!resAqi.ok || !resWeather.ok) {
            throw new Error(`Direct Supabase fetch non-200 status (${resAqi.status}, ${resWeather.status})`);
        }

        const [aqiRawList, weatherRawList] = await Promise.all([
            resAqi.json(),
            resWeather.json()
        ]);

        const aqiHist = Array.isArray(aqiRawList) ? aqiRawList.map(formatRawReadingClient).filter(Boolean) : [];
        const weatherHist = Array.isArray(weatherRawList) ? weatherRawList.map(formatWeatherReadingClient).filter(Boolean) : [];

        const latestAqi = aqiHist[0] || null;
        const latestWeather = weatherHist[0] || null;

        // Calculate today's rain sum & temperature range from readings
        const now = new Date();
        const istOffsetMs = 5.5 * 3600 * 1000;
        const nowIst = new Date(now.getTime() + istOffsetMs);
        const midnightIst = new Date(Date.UTC(nowIst.getUTCFullYear(), nowIst.getUTCMonth(), nowIst.getUTCDate()) - istOffsetMs);

        let todayRainSum = 0.0;
        const todayTemps = [];

        weatherHist.forEach(w => {
            if (!w) return;
            const dt = safeParseDate(w.timestamp || w.created_at);
            if (dt && dt.getTime() >= midnightIst.getTime()) {
                if (w.temperature != null && !isNaN(w.temperature)) {
                    todayTemps.push(Number(w.temperature));
                }
                if (w.rain_gauge != null && Number(w.rain_gauge) > 0) {
                    todayRainSum += Number(w.rain_gauge);
                }
            }
        });

        if (latestWeather && latestWeather.rain_gauge != null && Number(latestWeather.rain_gauge) > todayRainSum) {
            todayRainSum = Number(latestWeather.rain_gauge);
        }

        const bulkData = {
            aqi: latestAqi,
            weather: latestWeather,
            aqi_history: aqiHist,
            weather_history: weatherHist,
            history: aqiHist,
            today_rain_total: Number(todayRainSum.toFixed(2)),
            today_temp_min: todayTemps.length > 0 ? Number(Math.min(...todayTemps).toFixed(1)) : (latestWeather && latestWeather.temperature != null ? Number(latestWeather.temperature) : 28.0),
            today_temp_max: todayTemps.length > 0 ? Number(Math.max(...todayTemps).toFixed(1)) : (latestWeather && latestWeather.temperature != null ? Number(latestWeather.temperature) : 34.0),
            time: Date.now(),
            fetchedAt: Date.now()
        };

        // Cache results locally
        apiCache.liveBulk = { data: bulkData, time: Date.now() };
        apiCache.overviewBulk = { data: bulkData, time: Date.now() };
        setLocalCache(STORAGE_KEYS.BULK, bulkData);
        evaluateAndSyncLiveBadge(latestAqi || latestWeather);

        return bulkData;
    } catch (err) {
        console.warn('Direct Supabase fetch encountered error; falling back to telemetry endpoint:', err);
        try {
            const baseEndpoint = 'telemetry.php?action=live_bulk&limit=60&_t=' + Date.now();
            const fallbackUrl = (typeof window.getApiUrl === 'function') 
                ? window.getApiUrl(baseEndpoint) 
                : 'api/' + baseEndpoint;
            const fallbackRes = await fetch(fallbackUrl);
            if (!fallbackRes.ok) throw new Error('Fallback failed');
            const data = await fallbackRes.json();
            if (data && (data.aqi || data.weather)) {
                apiCache.liveBulk = { data, time: Date.now() };
                apiCache.overviewBulk = { data, time: Date.now() };
                setLocalCache(STORAGE_KEYS.BULK, data);
                evaluateAndSyncLiveBadge(data.aqi || data.weather);
                return data;
            }
        } catch (fbErr) {
            console.error('All live fetch paths failed:', fbErr);
        }
        return getLocalCache(STORAGE_KEYS.BULK) || null;
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

async function fetchHistoryBulk(queryDate, days = 1, force = false) {
    const activeNode = (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));
    if (activeNode === '2') {
        return {
            node: '2',
            status: 'STANDBY',
            is_standby: true,
            aqi: [],
            weather: [],
            fetchedAt: Date.now()
        };
    }

    const normDate = normalizeDateToYmd(queryDate || new Date());
    const cacheKey = `${normDate}_${days}_n${activeNode}`;
    const storageKey = STORAGE_KEYS.HIST_PREFIX + cacheKey;
    const todayYmd = normalizeDateToYmd(new Date());
    const isToday = (normDate === todayYmd);
    const maxAge = (isToday && days === 1) ? 15000 : (days === 1 ? 60000 : 3600000);

    if (!force) {
        const cached = getLocalCache(storageKey);
        if (cached && (cached.aqi?.length || cached.weather?.length) && (Date.now() - (cached.fetchedAt || 0) < maxAge)) {
            return cached;
        }
    }

    const cfg = getSbConfig();
    const headers = {
        'apikey': cfg.key,
        'Authorization': `Bearer ${cfg.key}`
    };

    try {
        let filter = '';
        const lim = days > 1 ? Math.min(1500, Math.max(150, days * 35)) : 150;
        if (normDate) {
            const [y, m, d] = parseDateParts(normDate);
            const targetUtc = Date.UTC(y, m - 1, d, 0, 0, 0) - (5.5 * 3600000);
            const startDate = new Date(targetUtc - (days > 1 ? (days * 86400000) : 86400000));
            const endDate = new Date(targetUtc + (2 * 86400000));
            const sIso = startDate.toISOString().split('T')[0];
            const eIso = endDate.toISOString().split('T')[0];
            filter = `&timestamp_hour=gte.${sIso}T00:00:00&timestamp_hour=lt.${eIso}T00:00:00`;
        }

        const urlAqi = `${cfg.url}/rest/v1/${cfg.tableAqiHist}?order=timestamp_hour.desc&limit=${lim}${filter}`;
        const urlWeather = `${cfg.url}/rest/v1/${cfg.tableWeatherHist}?order=timestamp_hour.desc&limit=${lim}${filter}`;

        const [resAqi, resWeather] = await Promise.all([
            fetch(urlAqi, { headers }),
            fetch(urlWeather, { headers })
        ]);

        if (!resAqi.ok || !resWeather.ok) throw new Error('History fetch non-200');

        const [aqiRaw, weatherRaw] = await Promise.all([
            resAqi.json(),
            resWeather.json()
        ]);

        const aqiList = Array.isArray(aqiRaw) ? aqiRaw.map(formatRawReadingClient).filter(Boolean) : [];
        const weatherList = Array.isArray(weatherRaw) ? weatherRaw.map(formatWeatherReadingClient).filter(Boolean) : [];

        const payload = {
            aqi: aqiList,
            weather: weatherList,
            fetchedAt: Date.now()
        };

        if (aqiList.length > 0 || weatherList.length > 0) {
            setLocalCache(storageKey, payload);
        }
        return payload;
    } catch (err) {
        console.warn('Direct Supabase history fetch failed; falling back to api/telemetry.php:', err);
        try {
            const fbRes = await fetch(`api/telemetry.php?action=history_bulk&date=${normDate}&days=${days}&_t=${Date.now()}`);
            if (fbRes.ok) {
                const fbData = await fbRes.json();
                const payload = {
                    aqi: fbData.aqi || [],
                    weather: fbData.weather || [],
                    fetchedAt: Date.now()
                };
                if (payload.aqi.length > 0 || payload.weather.length > 0) {
                    setLocalCache(storageKey, payload);
                }
                return payload;
            }
        } catch (e) {}
        const fallbackCached = getLocalCache(storageKey);
        return fallbackCached || { aqi: [], weather: [], fetchedAt: Date.now() };
    }
}
window.fetchHistoryBulk = fetchHistoryBulk;

async function fetchForecastData(force = false) {
    const activeNode = (window.getActiveNode ? window.getActiveNode() : (window.__selectedNode || '1'));
    if (activeNode === '2') {
        return {
            node: '2',
            status: 'STANDBY',
            is_standby: true,
            hourly: [],
            daily: [],
            model_version: 'v2.4-standby',
            source: 'Node 2 Standby'
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
