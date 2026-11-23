const { spawn } = require('child_process');

async function testOverlap() {
  const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
    '--headless=new',
    '--remote-debugging-port=9238',
    '--no-sandbox',
    '--disable-gpu',
    '--window-size=390,844'
  ]);
  await new Promise(r => setTimeout(r, 1500));
  const res = await fetch('http://127.0.0.1:9238/json');
  const targets = await res.json();
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
  await send('Page.navigate', { url: 'http://localhost:8001/?page=overview&node=2' });
  await new Promise(r => setTimeout(r, 2000));

  await send('Runtime.evaluate', { expression: 'window.scrollTo(0, document.body.scrollHeight)' });
  await new Promise(r => setTimeout(r, 500));

  const check = await send('Runtime.evaluate', {
    expression: `(() => {
      const footer = document.getElementById("global-footer");
      const nav = document.getElementById("mobile-bottom-nav");
      const fRect = footer ? footer.getBoundingClientRect() : null;
      const nRect = nav ? nav.getBoundingClientRect() : null;
      return {
        footer: fRect ? { top: fRect.top, bottom: fRect.bottom, height: fRect.height } : null,
        nav: nRect ? { top: nRect.top, bottom: nRect.bottom, height: nRect.height } : null,
        footerCoveredByNav: fRect && nRect ? (fRect.bottom > nRect.top) : null,
        overlapAmount: fRect && nRect ? Math.max(0, fRect.bottom - nRect.top) : null
      };
    })()`,
    returnByValue: true
  });
  console.log('Scroll bottom check Node 2:', JSON.stringify(check.result.value, null, 2));

  // Check Node 1
  await send('Page.navigate', { url: 'http://localhost:8001/?page=overview&node=1' });
  await new Promise(r => setTimeout(r, 2000));
  await send('Runtime.evaluate', { expression: 'window.scrollTo(0, document.body.scrollHeight)' });
  await new Promise(r => setTimeout(r, 500));

  const check1 = await send('Runtime.evaluate', {
    expression: `(() => {
      const footer = document.getElementById("global-footer");
      const nav = document.getElementById("mobile-bottom-nav");
      const fRect = footer ? footer.getBoundingClientRect() : null;
      const nRect = nav ? nav.getBoundingClientRect() : null;
      return {
        footer: fRect ? { top: fRect.top, bottom: fRect.bottom, height: fRect.height } : null,
        nav: nRect ? { top: nRect.top, bottom: nRect.bottom, height: nRect.height } : null,
        footerCoveredByNav: fRect && nRect ? (fRect.bottom > nRect.top) : null,
        overlapAmount: fRect && nRect ? Math.max(0, fRect.bottom - nRect.top) : null
      };
    })()`,
    returnByValue: true
  });
  console.log('Scroll bottom check Node 1:', JSON.stringify(check1.result.value, null, 2));

  chrome.kill();
  process.exit(0);
}
testOverlap();
