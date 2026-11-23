const fs = require('fs');
const path = require('path');
const envContent = fs.readFileSync(path.join(__dirname, '../php-frontend/.env'), 'utf8');
const env = {};
envContent.split('\n').forEach(line => {
  const match = line.match(/^\s*([\w.-]+)\s*=\s*(.*)?\s*$/);
  if (match) env[match[1]] = (match[2]||'').replace(/^['"]|['"]$/g, '').trim();
});
const n2Url = env['SUPABASE_URL_NODE2'];
const n2Key = env['SUPABASE_ANON_KEY_NODE2'];

async function check() {
  const res = await fetch(n2Url + '/rest/v1/SOIL_LIVE_NODE2?order=id.desc&limit=60', {
    headers: { 'apikey': n2Key, 'Authorization': 'Bearer ' + n2Key }
  });
  const rows = await res.json();
  const nonNull = rows.filter(r => r.soil_temperature !== null || r.soil_moisture !== null);
  console.log('Total fetched:', rows.length, 'Non-null rows count:', nonNull.length);
  console.log('Sample non-null row:', JSON.stringify(nonNull[0] || null, null, 2));
  console.log('Oldest non-null row:', JSON.stringify(nonNull[nonNull.length - 1] || null, null, 2));
}
check();
