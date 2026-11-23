const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');
const path = require('path');

const artifactDir = 'C:\\Users\\deeks\\.gemini\\antigravity\\brain\\e179fc64-cd8c-4b18-8aea-0eb347d1c4c0';

async function run() {
  const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
  const chrome = spawn(chromePath, [
    '--headless=new',
    '--remote-debugging-port=9223',
    '--disable-gpu',
    '--no-sandbox',
    '--window-size=390,844'
  ]);

  await new Promise(r => setTimeout(r, 1500));

  const list = await new Promise((resolve, reject) => {
    http.get('http://127.0.0.1:9223/json/list', res => {
      let d = ''; res.on('data', c => d += c); res.on('end', () => resolve(JSON.parse(d)));
    }).on('error', reject);
  });

  const wsUrl = list[0].webSocketDebuggerUrl;
  const ws = new WebSocket(wsUrl);
  await new Promise(r => ws.addEventListener('open', r));

  let id = 1;
  const callbacks = new Map();
  ws.addEventListener('message', e => {
    const resp = JSON.parse(e.data);
    if (callbacks.has(resp.id)) {
      const cb = callbacks.get(resp.id);
      callbacks.delete(resp.id);
      if (resp.error) cb.reject(resp.error);
      else cb.resolve(resp.result);
    }
  });

  const send = (method, params = {}) => new Promise((resolve, reject) => {
    const curId = id++;
    callbacks.set(curId, { resolve, reject });
    ws.send(JSON.stringify({ id: curId, method, params }));
  });

  await send('Emulation.setDeviceMetricsOverride', {
    width: 390,
    height: 844,
    deviceScaleFactor: 2,
    mobile: true
  });

  console.log("Navigating to Node 2 Analytics (Soil Tab)...");
  await send('Page.navigate', { url: 'http://localhost:8001/?auth=true&email=deekshith@gmail.com&name=Deekshith&page=historical&node=2&tab=soil' });
  await new Promise(r => setTimeout(r, 3000));

  // Capture viewport screenshot
  let shot = await send('Page.captureScreenshot', { format: 'png' });
  fs.writeFileSync(path.join(artifactDir, 'verify_mobile_analytics_enlarged.png'), Buffer.from(shot.data, 'base64'));
  console.log("Saved verify_mobile_analytics_enlarged.png");

  // Also capture crop around the subtab switcher
  let shotCrop = await send('Page.captureScreenshot', { 
    format: 'png', 
    clip: { x: 0, y: 60, width: 390, height: 440, scale: 2 } 
  });
  fs.writeFileSync(path.join(artifactDir, 'verify_mobile_analytics_switcher_crop.png'), Buffer.from(shotCrop.data, 'base64'));
  console.log("Saved verify_mobile_analytics_switcher_crop.png");

  chrome.kill();
  process.exit(0);
}

run().catch(err => {
  console.error("Error:", err);
  process.exit(1);
});
