const { spawn } = require('child_process');

async function trackFooter() {
  const chrome = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
    '--headless=new',
    '--remote-debugging-port=9243',
    '--no-sandbox',
    '--disable-gpu',
    '--window-size=1280,1000'
  ]);
  let targets = null;
  for (let i = 0; i < 10; i++) {
    try {
      await new Promise(r => setTimeout(r, 600));
      const res = await fetch('http://127.0.0.1:9243/json');
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

  // Inject mutation observer before navigation
  await send('Page.addScriptToEvaluateOnNewDocument', {
    source: `
      window.__footerEvents = [];
      const obs = new MutationObserver(mutations => {
        for (const m of mutations) {
          for (const node of m.removedNodes) {
            if (node.id === 'global-footer' || (node.querySelector && node.querySelector('#global-footer'))) {
              window.__footerEvents.push({
                type: 'removed',
                time: performance.now(),
                target: m.target.tagName + (m.target.id ? '#' + m.target.id : ''),
                stack: new Error().stack
              });
            }
          }
        }
      });
      document.addEventListener('DOMContentLoaded', () => {
        obs.observe(document.body, { childList: true, subtree: true });
        window.__footerEvents.push({
          type: 'initial',
          time: performance.now(),
          exists: !!document.getElementById('global-footer')
        });
      });
    `
  });

  await send('Page.navigate', { url: 'http://localhost:8001/?page=overview&node=2' });
  await new Promise(r => setTimeout(r, 3000));

  const result = await send('Runtime.evaluate', {
    expression: 'JSON.stringify({ events: window.__footerEvents, currentExists: !!document.getElementById("global-footer") }, null, 2)',
    returnByValue: true
  });
  console.log('Tracking result:', result.result.value);

  chrome.kill();
  process.exit(0);
}
trackFooter();
