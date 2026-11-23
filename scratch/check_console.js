const { spawn } = require('child_process');

async function checkConsoleErrors() {
  const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
    '--headless=new',
    '--remote-debugging-port=9251',
    '--no-sandbox',
    '--disable-gpu',
    '--window-size=1280,800'
  ]);
  let targets = null;
  for (let i = 0; i < 10; i++) {
    try {
      await new Promise(r => setTimeout(r, 600));
      const res = await fetch('http://127.0.0.1:9251/json');
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
  await send('Runtime.enable');
  await send('Log.enable');

  const errors = [];
  ws.addEventListener('message', (evt) => {
    const d = JSON.parse(evt.data);
    if (d.method === 'Runtime.exceptionThrown') {
      errors.push({ type: 'exception', details: d.params.exceptionDetails });
    }
    if (d.method === 'Log.entryAdded' && d.params.entry.level === 'error') {
      errors.push({ type: 'logError', text: d.params.entry.text });
    }
  });

  // Navigate to Node 2 overview on localhost
  await send('Page.navigate', { url: 'http://localhost:8001/?page=overview&node=2' });
  await new Promise(r => setTimeout(r, 3000));

  console.log('Errors on localhost Node 2:', JSON.stringify(errors, null, 2));

  // Navigate to Node 2 overview on Vercel
  errors.length = 0;
  await send('Page.navigate', { url: 'https://newphpaqi-php-frontend.vercel.app/?page=overview&node=2' });
  await new Promise(r => setTimeout(r, 3000));
  console.log('Errors on Vercel Node 2:', JSON.stringify(errors, null, 2));

  chrome.kill();
  process.exit(0);
}
checkConsoleErrors();
