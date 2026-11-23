const { spawn } = require('child_process');

async function measureElementByElement() {
  const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
    '--headless=new',
    '--remote-debugging-port=9250',
    '--no-sandbox',
    '--disable-gpu',
    '--window-size=390,844'
  ]);
  let targets = null;
  for (let i = 0; i < 10; i++) {
    try {
      await new Promise(r => setTimeout(r, 600));
      const res = await fetch('http://127.0.0.1:9250/json');
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

  const script = `(() => {
    const header = document.querySelector('header');
    const headerRect = header ? header.getBoundingClientRect() : null;
    const subline = document.getElementById('mobile-station-subline');
    const sublineRect = subline ? subline.getBoundingClientRect() : null;
    const pageWrapper = document.getElementById('page-content-wrapper');
    const root = document.querySelector('.overview-page-root');
    const hero = document.getElementById('card-aqi');
    const grid = document.querySelector('.overview-grid');
    const trends = document.querySelector('.overview-chart-carousel-section');
    const dots = document.getElementById('overview-chart-dots');
    const footer = document.getElementById('global-footer');
    const nav = document.getElementById('mobile-bottom-nav');

    return {
      headerHeight: headerRect ? headerRect.height : null,
      sublineHeight: sublineRect ? sublineRect.height : null,
      sublineDisplay: subline ? window.getComputedStyle(subline).display : null,
      pageWrapperPadding: pageWrapper ? window.getComputedStyle(pageWrapper).paddingTop : null,
      rootHeight: root ? root.offsetHeight : null,
      heroHeight: hero ? hero.offsetHeight : null,
      gridHeight: grid ? grid.offsetHeight : null,
      trendsHeight: trends ? trends.offsetHeight : null,
      dotsHeight: dots ? dots.offsetHeight : null,
      footerTop: footer ? footer.getBoundingClientRect().top : null,
      footerBottom: footer ? footer.getBoundingClientRect().bottom : null,
      footerHeight: footer ? footer.offsetHeight : null,
      navTop: nav ? nav.getBoundingClientRect().top : null,
      windowHeight: window.innerHeight,
      documentHeight: document.documentElement.scrollHeight
    };
  })()`;

  await send('Page.navigate', { url: 'http://localhost:8001/?page=overview&node=1' });
  await new Promise(r => setTimeout(r, 2000));
  const n1 = await send('Runtime.evaluate', { expression: script, returnByValue: true });
  console.log('Node 1 element heights:', JSON.stringify(n1.result.value, null, 2));

  await send('Page.navigate', { url: 'http://localhost:8001/?page=overview&node=2' });
  await new Promise(r => setTimeout(r, 2000));
  const n2 = await send('Runtime.evaluate', { expression: script, returnByValue: true });
  console.log('Node 2 element heights:', JSON.stringify(n2.result.value, null, 2));

  chrome.kill();
  process.exit(0);
}
measureElementByElement();
