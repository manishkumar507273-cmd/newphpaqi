const { spawn } = require('child_process');

async function testSpaNav() {
  const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
    '--headless=new',
    '--remote-debugging-port=9239',
    '--no-sandbox',
    '--disable-gpu',
    '--window-size=1280,1000'
  ]);
  let targets = null;
  for (let i = 0; i < 10; i++) {
    try {
      await new Promise(r => setTimeout(r, 600));
      const res = await fetch('http://127.0.0.1:9239/json');
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

  // Start on Node 2 Live
  await send('Page.navigate', { url: 'http://localhost:8001/?auth=true&email=deekshith@gmail.com&name=Deekshith&page=live&node=2' });
  await new Promise(r => setTimeout(r, 2000));

  // Click Overview in nav
  await send('Runtime.evaluate', {
    expression: 'document.querySelector(\'[data-nav-link="overview"]\').click()'
  });
  await new Promise(r => setTimeout(r, 2000));

  const check = await send('Runtime.evaluate', {
    expression: `(() => {
      const footer = document.getElementById("global-footer");
      return {
        url: window.location.href,
        footerExists: !!footer,
        footerDisplay: footer ? window.getComputedStyle(footer).display : null,
        footerParent: footer ? footer.parentElement.tagName : null,
        footerHTML: footer ? footer.outerHTML.substring(0, 100) : null
      };
    })()`,
    returnByValue: true
  });
  console.log('SPA Nav check:', JSON.stringify(check.result.value, null, 2));

  // Now test mobile SPA nav
  await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
  await send('Page.navigate', { url: 'http://localhost:8001/?auth=true&email=deekshith@gmail.com&name=Deekshith&page=live&node=2' });
  await new Promise(r => setTimeout(r, 2000));
  await send('Runtime.evaluate', {
    expression: 'document.querySelector(\'[data-bottom-nav="overview"]\').click()'
  });
  await new Promise(r => setTimeout(r, 2000));

  const checkMob = await send('Runtime.evaluate', {
    expression: `(() => {
      const footer = document.getElementById("global-footer");
      return {
        url: window.location.href,
        footerExists: !!footer,
        footerDisplay: footer ? window.getComputedStyle(footer).display : null,
        footerParent: footer ? footer.parentElement.tagName : null,
        footerHTML: footer ? footer.outerHTML.substring(0, 100) : null,
        bodyHeight: document.body.scrollHeight,
        windowHeight: window.innerHeight
      };
    })()`,
    returnByValue: true
  });
  console.log('Mobile SPA Nav check:', JSON.stringify(checkMob.result.value, null, 2));

  chrome.kill();
  process.exit(0);
}
testSpaNav();
