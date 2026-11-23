const { spawn } = require('child_process');

async function debugVercelDOM() {
  const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
    '--headless=new',
    '--remote-debugging-port=9247',
    '--no-sandbox',
    '--disable-gpu',
    '--window-size=390,844'
  ]);
  let targets = null;
  for (let i = 0; i < 10; i++) {
    try {
      await new Promise(r => setTimeout(r, 600));
      const res = await fetch('http://127.0.0.1:9247/json');
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
  
  await send('Page.navigate', { url: 'https://newphpaqi-php-frontend.vercel.app/?page=overview&node=2' });
  await new Promise(r => setTimeout(r, 3000));

  const check = await send('Runtime.evaluate', { 
    expression: `(() => {
      const allFooters = Array.from(document.querySelectorAll('footer')).map(f => ({
        id: f.id,
        className: f.className,
        style: f.getAttribute('style'),
        parentTag: f.parentElement ? f.parentElement.tagName : null,
        display: window.getComputedStyle(f).display,
        visibility: window.getComputedStyle(f).visibility,
        rect: f.getBoundingClientRect()
      }));
      const globalFooter = document.getElementById('global-footer');
      const main = document.querySelector('main');
      const bodyChildren = Array.from(document.body.children).map(c => ({
        tag: c.tagName,
        id: c.id,
        className: c.className
      }));
      return {
        allFooters,
        globalFooterExists: !!globalFooter,
        mainExists: !!main,
        mainHTMLSlice: main ? main.innerHTML.slice(-500) : null,
        bodyChildren
      };
    })()`,
    returnByValue: true
  });
  console.log('DOM check on Vercel Node 2:', JSON.stringify(check.result.value, null, 2));

  chrome.kill();
  process.exit(0);
}
debugVercelDOM();
