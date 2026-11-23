const { spawn } = require('child_process');
async function test() {
  const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
    '--headless=new',
    '--remote-debugging-port=9249',
    '--no-sandbox',
    '--disable-gpu',
    '--window-size=390,844'
  ]);
  let targets = null;
  for (let i = 0; i < 10; i++) {
    try {
      await new Promise(r => setTimeout(r, 600));
      const res = await fetch('http://127.0.0.1:9249/json');
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
  await new Promise(r => setTimeout(r, 2000));
  const res = await send('Runtime.evaluate', { 
    expression: "window.getComputedStyle(document.getElementById('global-footer')).marginBottom",
    returnByValue: true
  });
  console.log('Vercel Node 2 marginBottom:', res.result.value);

  chrome.kill();
  process.exit(0);
}
test();
