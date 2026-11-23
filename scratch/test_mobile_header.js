const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const artifactDir = 'C:\\Users\\deeks\\.gemini\\antigravity\\brain\\e179fc64-cd8c-4b18-8aea-0eb347d1c4c0';

async function runTest() {
  const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
    '--headless=new',
    '--remote-debugging-port=9233',
    '--no-sandbox',
    '--disable-gpu',
    '--window-size=390,844'
  ]);
  await new Promise(r => setTimeout(r, 1500));
  
  const res = await fetch('http://127.0.0.1:9233/json');
  const targets = await res.json();
  const ws = new WebSocket(targets[0].webSocketDebuggerUrl);
  
  let id = 1;
  function send(method, params = {}) {
    return new Promise((resolve) => {
      const msgId = id++;
      const handler = (evt) => {
        const d = JSON.parse(evt.data);
        if (d.id === msgId) {
          ws.removeEventListener('message', handler);
          resolve(d.result);
        }
      };
      ws.addEventListener('message', handler);
      ws.send(JSON.stringify({ id: msgId, method, params }));
    });
  }

  await new Promise(r => ws.onopen = r);
  await send('Page.enable');
  await send('Emulation.setDeviceMetricsOverride', {
    width: 390,
    height: 844,
    deviceScaleFactor: 2,
    mobile: true
  });

  // 1. Test Node 1 Overview
  console.log("Navigating to Node 1 Overview...");
  await send('Page.navigate', { url: 'http://localhost:8001/?auth=true&email=deekshith@gmail.com&name=Deekshith&page=overview&node=1' });
  await new Promise(r => setTimeout(r, 2500));
  
  let shot1 = await send('Page.captureScreenshot', { format: 'png', clip: { x: 0, y: 0, width: 390, height: 180, scale: 2 } });
  fs.writeFileSync(path.join(artifactDir, 'node1_mobile_header.png'), Buffer.from(shot1.data, 'base64'));
  console.log("Saved node1_mobile_header.png");

  // 2. Test Node 2 Overview
  console.log("Navigating to Node 2 Overview...");
  await send('Page.navigate', { url: 'http://localhost:8001/?auth=true&email=deekshith@gmail.com&name=Deekshith&page=overview&node=2' });
  await new Promise(r => setTimeout(r, 2500));
  
  let shot2 = await send('Page.captureScreenshot', { format: 'png', clip: { x: 0, y: 0, width: 390, height: 180, scale: 2 } });
  fs.writeFileSync(path.join(artifactDir, 'node2_mobile_header.png'), Buffer.from(shot2.data, 'base64'));
  console.log("Saved node2_mobile_header.png");

  // 3. Test Node 1 full mobile top
  await send('Page.navigate', { url: 'http://localhost:8001/?auth=true&email=deekshith@gmail.com&name=Deekshith&page=overview&node=1' });
  await new Promise(r => setTimeout(r, 2000));
  let shot3 = await send('Page.captureScreenshot', { format: 'png', clip: { x: 0, y: 0, width: 390, height: 500, scale: 2 } });
  fs.writeFileSync(path.join(artifactDir, 'node1_mobile_overview_full.png'), Buffer.from(shot3.data, 'base64'));
  console.log("Saved node1_mobile_overview_full.png");

  chrome.kill();
  process.exit(0);
}

runTest().catch(e => {
  console.error(e);
  process.exit(1);
});
