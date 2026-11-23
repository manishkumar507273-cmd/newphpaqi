const { spawn } = require('child_process');
const fs = require('fs');

async function verifyAll() {
  const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
    '--headless=new',
    '--remote-debugging-port=9252',
    '--no-sandbox',
    '--disable-gpu',
    '--window-size=390,844'
  ]);
  let targets = null;
  for (let i = 0; i < 10; i++) {
    try {
      await new Promise(r => setTimeout(r, 600));
      const res = await fetch('http://127.0.0.1:9252/json');
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

  const checkScript = `(() => {
    const footer = document.getElementById('global-footer');
    const station = document.getElementById('footer-station-name');
    const nav = document.getElementById('mobile-bottom-nav');
    const fComp = footer ? window.getComputedStyle(footer) : null;
    const fRect = footer ? footer.getBoundingClientRect() : null;
    const nRect = nav ? nav.getBoundingClientRect() : null;
    return {
      footerExists: !!footer,
      footerDisplay: fComp ? fComp.display : null,
      footerMarginBottom: fComp ? fComp.marginBottom : null,
      stationName: station ? station.innerText.trim() : null,
      footerTop: fRect ? fRect.top : null,
      footerBottom: fRect ? fRect.bottom : null,
      footerHeight: fRect ? fRect.height : null,
      navTop: nRect ? nRect.top : null,
      gapAboveNav: (fRect && nRect) ? (nRect.top - fRect.bottom) : null,
      scrollY: window.scrollY,
      scrollHeight: document.documentElement.scrollHeight
    };
  })()`;

  // 1. Node 1 Mobile
  await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
  await send('Page.navigate', { url: 'http://localhost:8001/?page=overview&node=1' });
  await new Promise(r => setTimeout(r, 1500));
  await send('Runtime.evaluate', { expression: `window.scrollTo(0, document.documentElement.scrollHeight)` });
  await new Promise(r => setTimeout(r, 500));
  const n1Check = await send('Runtime.evaluate', { expression: checkScript, returnByValue: true });
  console.log('Node 1 Mobile Check:', JSON.stringify(n1Check.result.value, null, 2));
  const shot1 = await send('Page.captureScreenshot', { format: 'png' });
  fs.writeFileSync('C:\\Users\\deeks\\.gemini\\antigravity\\brain\\e179fc64-cd8c-4b18-8aea-0eb347d1c4c0\\node1_mobile_footer_verified.png', Buffer.from(shot1.data, 'base64'));

  // 2. Node 2 Mobile
  await send('Page.navigate', { url: 'http://localhost:8001/?page=overview&node=2' });
  await new Promise(r => setTimeout(r, 1500));
  await send('Runtime.evaluate', { expression: `window.scrollTo(0, document.documentElement.scrollHeight)` });
  await new Promise(r => setTimeout(r, 500));
  const n2Check = await send('Runtime.evaluate', { expression: checkScript, returnByValue: true });
  console.log('Node 2 Mobile Check:', JSON.stringify(n2Check.result.value, null, 2));
  const shot2 = await send('Page.captureScreenshot', { format: 'png' });
  fs.writeFileSync('C:\\Users\\deeks\\.gemini\\antigravity\\brain\\e179fc64-cd8c-4b18-8aea-0eb347d1c4c0\\node2_mobile_footer_verified.png', Buffer.from(shot2.data, 'base64'));

  // 3. Node 2 Desktop
  await send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 800, deviceScaleFactor: 1, mobile: false });
  await send('Page.navigate', { url: 'http://localhost:8001/?page=overview&node=2' });
  await new Promise(r => setTimeout(r, 1500));
  await send('Runtime.evaluate', { expression: `window.scrollTo(0, document.documentElement.scrollHeight)` });
  await new Promise(r => setTimeout(r, 500));
  const n2DesktopCheck = await send('Runtime.evaluate', { expression: checkScript, returnByValue: true });
  console.log('Node 2 Desktop Check:', JSON.stringify(n2DesktopCheck.result.value, null, 2));
  const shot3 = await send('Page.captureScreenshot', { format: 'png' });
  fs.writeFileSync('C:\\Users\\deeks\\.gemini\\antigravity\\brain\\e179fc64-cd8c-4b18-8aea-0eb347d1c4c0\\node2_desktop_footer_verified.png', Buffer.from(shot3.data, 'base64'));

  chrome.kill();
  process.exit(0);
}
verifyAll();
