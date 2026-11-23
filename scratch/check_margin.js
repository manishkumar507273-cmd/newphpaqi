const { spawn } = require('child_process');

async function checkMargin() {
  const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
    '--headless=new',
    '--remote-debugging-port=9246',
    '--no-sandbox',
    '--disable-gpu',
    '--window-size=390,844'
  ]);
  let targets = null;
  for (let i = 0; i < 10; i++) {
    try {
      await new Promise(r => setTimeout(r, 600));
      const res = await fetch('http://127.0.0.1:9246/json');
      targets = await res.json();
      if (targets && targets.length > 0) break;
    } catch(e) {}
  }
  const ws = new WebSocket(targets[0].webSocketDebuggerUrl);
  let id = 1;
  function send(method, params = {}) {
    return new Promise(res => {
      const msgId = id++;
      const handler = (evt) => {
        const d = JSON.parse(evt.data);
        if (d.id === msgId) { ws.removeEventListener('message', handler); res(d.result); }
      };
      ws.addEventListener('message', handler);
      ws.send(JSON.stringify({ id: msgId, method, params }));
    });
  }
  await new Promise(r => ws.onopen = r);
  await send('Page.enable');
  await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
  
  // Localhost Node 2
  await send('Page.navigate', { url: 'http://localhost:8001/?page=overview&node=2' });
  await new Promise(r => setTimeout(r, 2000));
  const r2 = await send('Runtime.evaluate', { 
    expression: `(() => {
      const f = document.getElementById('global-footer');
      return f ? {
        mb: window.getComputedStyle(f).marginBottom,
        display: window.getComputedStyle(f).display,
        offsetTop: f.offsetTop,
        offsetHeight: f.offsetHeight
      } : 'no-footer';
    })()`,
    returnByValue: true
  });
  console.log('Node 2 footer on localhost:', r2.result.value);

  // Vercel Node 2
  await send('Page.navigate', { url: 'https://newphpaqi-php-frontend.vercel.app/?page=overview&node=2' });
  await new Promise(r => setTimeout(r, 2000));
  const rVercel = await send('Runtime.evaluate', { 
    expression: `(() => {
      const f = document.getElementById('global-footer');
      return f ? {
        mb: window.getComputedStyle(f).marginBottom,
        display: window.getComputedStyle(f).display,
        offsetTop: f.offsetTop,
        offsetHeight: f.offsetHeight
      } : 'no-footer';
    })()`,
    returnByValue: true
  });
  console.log('Node 2 footer on Vercel:', rVercel.result.value);

  // Take screenshot of Vercel Node 2 when scrolled down
  await send('Runtime.evaluate', { expression: `window.scrollTo(0, document.body.scrollHeight)` });
  await new Promise(r => setTimeout(r, 500));
  const shot = await send('Page.captureScreenshot', { format: 'png' });
  const fs = require('fs');
  fs.writeFileSync('C:\\Users\\deeks\\.gemini\\antigravity\\brain\\e179fc64-cd8c-4b18-8aea-0eb347d1c4c0\\vercel_node2_mobile.png', Buffer.from(shot.data, 'base64'));
  console.log('Saved vercel_node2_mobile.png');

  // Also check Vercel Node 1
  await send('Page.navigate', { url: 'https://newphpaqi-php-frontend.vercel.app/?page=overview&node=1' });
  await new Promise(r => setTimeout(r, 2000));
  await send('Runtime.evaluate', { expression: `window.scrollTo(0, document.body.scrollHeight)` });
  await new Promise(r => setTimeout(r, 500));
  const shot1 = await send('Page.captureScreenshot', { format: 'png' });
  fs.writeFileSync('C:\\Users\\deeks\\.gemini\\antigravity\\brain\\e179fc64-cd8c-4b18-8aea-0eb347d1c4c0\\vercel_node1_mobile.png', Buffer.from(shot1.data, 'base64'));
  console.log('Saved vercel_node1_mobile.png');

  chrome.kill();
  process.exit(0);
}
checkMargin();
