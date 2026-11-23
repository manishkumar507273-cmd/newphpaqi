const { spawn } = require('child_process');

async function debugFooter() {
  const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
    '--headless=new',
    '--remote-debugging-port=9241',
    '--no-sandbox',
    '--disable-gpu',
    '--window-size=1280,1000'
  ]);
  let targets = null;
  for (let i = 0; i < 10; i++) {
    try {
      await new Promise(r => setTimeout(r, 600));
      const res = await fetch('http://127.0.0.1:9241/json');
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
  await new Promise(r => setTimeout(r, 3000));

  const res = await send('Runtime.evaluate', {
    expression: `(() => {
      const allFooters = Array.from(document.querySelectorAll("footer"));
      const bodyHTML = document.body.innerHTML;
      const hasFooterStr = bodyHTML.includes("global-footer");
      const currentURL = window.location.href;
      return {
        url: currentURL,
        allFootersCount: allFooters.length,
        hasFooterStr,
        bodyLen: bodyHTML.length
      };
    })()`,
    returnByValue: true
  });
  console.log('Result:', res.result.value);
  chrome.kill();
  process.exit(0);
}
debugFooter();
