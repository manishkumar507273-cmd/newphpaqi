const { spawn } = require('child_process');

async function measureBoth() {
  const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
    '--headless=new',
    '--remote-debugging-port=9244',
    '--no-sandbox',
    '--disable-gpu',
    '--window-size=390,844'
  ]);
  let targets = null;
  for (let i = 0; i < 10; i++) {
    try {
      await new Promise(r => setTimeout(r, 600));
      const res = await fetch('http://127.0.0.1:9244/json');
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

  const queryMetrics = `(() => {
    const root = document.querySelector(".overview-page-root");
    const hero = document.getElementById("card-aqi");
    const grid = document.querySelector(".overview-grid");
    const trends = document.querySelector(".overview-chart-carousel-section");
    const dots = document.getElementById("overview-chart-dots");
    const footer = document.getElementById("global-footer");
    const fRect = footer ? footer.getBoundingClientRect() : null;
    return JSON.stringify({
      rootHeight: root ? root.offsetHeight : null,
      heroHeight: hero ? hero.offsetHeight : null,
      gridHeight: grid ? grid.offsetHeight : null,
      trendsHeight: trends ? trends.offsetHeight : null,
      dotsTop: dots ? dots.getBoundingClientRect().top : null,
      footer: fRect ? { top: fRect.top, bottom: fRect.bottom, height: fRect.height } : null,
      scrollY: window.scrollY,
      scrollHeight: document.body.scrollHeight
    });
  })()`;

  // Measure Node 1
  await send('Page.navigate', { url: 'http://localhost:8001/?page=overview&node=1' });
  await new Promise(r => setTimeout(r, 2000));
  const n1 = await send('Runtime.evaluate', { expression: queryMetrics, returnByValue: true });
  console.log('Node 1 metrics:', JSON.parse(n1.result.value));

  // Measure Node 2
  await send('Page.navigate', { url: 'http://localhost:8001/?page=overview&node=2' });
  await new Promise(r => setTimeout(r, 2000));
  const n2 = await send('Runtime.evaluate', { expression: queryMetrics, returnByValue: true });
  console.log('Node 2 metrics:', JSON.parse(n2.result.value));

  chrome.kill();
  process.exit(0);
}
measureBoth();
