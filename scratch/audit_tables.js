const fs = require('fs');

const path = require('path');
const envContent = fs.readFileSync(path.join(__dirname, '../php-frontend/.env'), 'utf8');
const env = {};
envContent.split('\n').forEach(line => {
  const match = line.match(/^\s*([\w.-]+)\s*=\s*(.*)?\s*$/);
  if (match) {
    let key = match[1];
    let val = match[2] || '';
    if (val.startsWith('"') && val.endsWith('"')) val = val.slice(1, -1);
    if (val.startsWith("'") && val.endsWith("'")) val = val.slice(1, -1);
    env[key] = val.trim();
  }
});

const n1Url = env['SUPABASE_URL'] || 'https://sgkdpliqlhgiqsabxzxe.supabase.co';
const n1Key = env['SUPABASE_ANON_KEY'] || env['VITE_SUPABASE_ANON_KEY'];
const n2Url = env['SUPABASE_URL_NODE2'] || 'https://nahabhzcaeppbteuxogg.supabase.co';
const n2Key = env['SUPABASE_ANON_KEY_NODE2'] || env['VITE_SUPABASE_ANON_KEY_NODE2'];

async function checkTable(url, key, table, orderCol = 'timestamp') {
  try {
    const headRes = await fetch(url + '/rest/v1/' + table + '?select=count', {
      headers: {
        'apikey': key,
        'Authorization': 'Bearer ' + key,
        'Prefer': 'count=exact',
        'Range': '0-0'
      }
    });
    const countHeader = headRes.headers.get('content-range');
    const totalCount = countHeader ? countHeader.split('/')[1] : 'unknown';

    // Get latest row
    let latestRes = await fetch(url + '/rest/v1/' + table + '?order=' + orderCol + '.desc&limit=1', {
      headers: { 'apikey': key, 'Authorization': 'Bearer ' + key }
    });
    let rows = await latestRes.json();
    if (!Array.isArray(rows) || rows.length === 0) {
      latestRes = await fetch(url + '/rest/v1/' + table + '?limit=1', {
        headers: { 'apikey': key, 'Authorization': 'Bearer ' + key }
      });
      rows = await latestRes.json();
    }
    return {
      table,
      totalCount,
      latestRow: Array.isArray(rows) && rows.length > 0 ? rows[0] : null,
      status: 'OK'
    };
  } catch (e) {
    return { table, status: 'ERROR', error: e.message };
  }
}

async function run() {
  console.log('Auditing Supabase Tables...\n');

  console.log('--- NODE 1 SUPABASE (' + n1Url + ') ---');
  const n1AqiLive = await checkTable(n1Url, n1Key, 'AQI_LIVE_NODE1', 'timestamp');
  console.log('AQI_LIVE_NODE1: count=' + n1AqiLive.totalCount + ', latest_timestamp=' + (n1AqiLive.latestRow ? n1AqiLive.latestRow.timestamp : 'N/A') + ', aqi=' + (n1AqiLive.latestRow ? n1AqiLive.latestRow.aqi : 'N/A'));

  const n1AqiHist = await checkTable(n1Url, n1Key, 'AQI_NODE1', 'timestamp_hour');
  console.log('AQI_NODE1 (History): count=' + n1AqiHist.totalCount + ', latest_hour=' + (n1AqiHist.latestRow ? n1AqiHist.latestRow.timestamp_hour : 'N/A'));

  const n1WtrLive = await checkTable(n1Url, n1Key, 'WEATHER_LIVE_NODE1', 'timestamp');
  console.log('WEATHER_LIVE_NODE1: count=' + n1WtrLive.totalCount + ', latest_timestamp=' + (n1WtrLive.latestRow ? n1WtrLive.latestRow.timestamp : 'N/A') + ', temp=' + (n1WtrLive.latestRow ? n1WtrLive.latestRow.temperature : 'N/A') + '°C');

  const n1WtrHist = await checkTable(n1Url, n1Key, 'WEATHER_NODE1', 'timestamp_hour');
  console.log('WEATHER_NODE1 (History): count=' + n1WtrHist.totalCount + ', latest_hour=' + (n1WtrHist.latestRow ? n1WtrHist.latestRow.timestamp_hour : 'N/A'));

  console.log('\n--- NODE 2 SUPABASE (' + n2Url + ') ---');
  const n2AqiLive = await checkTable(n2Url, n2Key, 'AQI_LIVE_NODE2', 'timestamp');
  console.log('AQI_LIVE_NODE2: count=' + n2AqiLive.totalCount + ', latest_timestamp=' + (n2AqiLive.latestRow ? n2AqiLive.latestRow.timestamp : 'N/A') + ', aqi=' + (n2AqiLive.latestRow ? n2AqiLive.latestRow.aqi : 'N/A'));

  const n2AqiHist = await checkTable(n2Url, n2Key, 'AQI_NODE2', 'timestamp_hour');
  console.log('AQI_NODE2 (History): count=' + n2AqiHist.totalCount + ', latest_hour=' + (n2AqiHist.latestRow ? n2AqiHist.latestRow.timestamp_hour : 'N/A'));

  const n2SoilLive = await checkTable(n2Url, n2Key, 'SOIL_LIVE_NODE2', 'timestamp');
  console.log('SOIL_LIVE_NODE2: count=' + n2SoilLive.totalCount + ', latest_timestamp=' + (n2SoilLive.latestRow ? n2SoilLive.latestRow.timestamp : 'N/A'));
  if (n2SoilLive.latestRow) {
    console.log('  Live Soil Values: temp=' + n2SoilLive.latestRow.soil_temperature + '°C, moisture=' + n2SoilLive.latestRow.soil_moisture + '%, pH=' + n2SoilLive.latestRow.soil_ph + ', EC=' + n2SoilLive.latestRow.soil_conductivity + ' µS/cm');
  }

  const n2SoilHist = await checkTable(n2Url, n2Key, 'SOIL_NODE2', 'timestamp_hour');
  console.log('SOIL_NODE2 (History): count=' + n2SoilHist.totalCount + ', status=' + n2SoilHist.status);
}

run();
