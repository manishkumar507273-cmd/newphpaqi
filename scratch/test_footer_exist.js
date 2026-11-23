const { spawn } = require('child_process');

async function testLiveFooter() {
  const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
    '--headless=new',
    '--remote-debugging-port=9240',
    '--no-sandbox',
    '--disable-gpu',
    '--window-size=1280,1000'
  ]);
  let targets = null;
  for (let i = 0; i < 10; i++) {
    try {
      await new Promise(r => setTimeout(r, 600));
      const res = await fetch('http://127.0.0.1:9240/json');
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
  await send('Page.navigate', { url: 'http://localhost:8001/?auth=true&email=deekshith@gmail.com&name=Deekshith&page=live&node=2' });
  await new Promise(r => setTimeout(r, 2500));

  const check = await send('Runtime.evaluate', {
    expression: 'document.getElementById("global-footer") ? true : false',
    returnByValue: true
  });
  console.log('Live page footer exists:', check.result.value);

  // Check overview page
  await send('Page.navigate', { url: 'http://localhost:8001/?page=overview&node=2' });
  await new Promise(r => setTimeout(r, 2500));
  const checkOv = await send('Runtime.evaluate', {
    expression: 'document.getElementById("global-footer") ? true : false',
    returnByValue: true
  });
  console.log('Overview page footer exists:', checkOv.result.value);

  chrome.kill();
  process.exit(0);
}
testLiveFooter();
