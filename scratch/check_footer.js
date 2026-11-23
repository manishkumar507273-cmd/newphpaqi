const { execSync } = require('child_process');
const http = require('http');

async function testWithChrome() {
  const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
  
  // Launch Chrome with remote debugging
  const chromeProc = require('child_process').spawn(chromePath, [
    '--headless=new',
    '--remote-debugging-port=9222',
    '--disable-gpu',
    '--no-sandbox',
    '--window-size=412,915'
  ]);

  await new Promise(r => setTimeout(r, 1500));

  try {
    const list = await new Promise((resolve, reject) => {
      http.get('http://127.0.0.1:9222/json/list', res => {
        let d = ''; res.on('data', c => d += c); res.on('end', () => resolve(JSON.parse(d)));
      }).on('error', reject);
    });

    const webSocketDebuggerUrl = list[0].webSocketDebuggerUrl;
    const WebSocket = require('ws');
    const ws = new WebSocket(webSocketDebuggerUrl);

    await new Promise(r => ws.on('open', r));

    let msgId = 1;
    function send(method, params = {}) {
      return new Promise(resolve => {
        const id = msgId++;
        const handler = (msg) => {
          const data = JSON.parse(msg);
          if (data.id === id) {
            ws.off('message', handler);
            resolve(data.result);
          }
        };
        ws.on('message', handler);
        ws.send(JSON.stringify({ id, method, params }));
      });
    }

    await send('Page.enable');
    await send('DOM.enable');
    await send('Emulation.setDeviceMetricsOverride', {
      width: 412,
      height: 915,
      deviceScaleFactor: 2.6,
      mobile: true
    });

    // Navigate to Node 2 Overview
    await send('Page.navigate', { url: 'http://localhost:8001/?page=overview&node=2' });
    await new Promise(r => setTimeout(r, 2000));

    const evalResult = await send('Runtime.evaluate', {
      expression: `(() => {
        const footer = document.getElementById('global-footer');
        const nav = document.getElementById('mobile-bottom-nav');
        const root = document.querySelector('.overview-page-root');
        const dots = document.getElementById('overview-chart-dots');
        
        const fRect = footer ? footer.getBoundingClientRect() : null;
        const nRect = nav ? nav.getBoundingClientRect() : null;
        const dRect = dots ? dots.getBoundingClientRect() : null;
        
        const fComp = footer ? window.getComputedStyle(footer) : null;
        
        return {
          footerFound: !!footer,
          footerDisplay: fComp ? fComp.display : null,
          footerVisibility: fComp ? fComp.visibility : null,
          footerMarginBottom: fComp ? fComp.marginBottom : null,
          fRect: fRect ? { top: fRect.top, bottom: fRect.bottom, height: fRect.height } : null,
          nRect: nRect ? { top: nRect.top, bottom: nRect.bottom, height: nRect.height } : null,
          dRect: dRect ? { top: dRect.top, bottom: dRect.bottom, height: dRect.height } : null,
          windowInnerHeight: window.innerHeight,
          scrollY: window.scrollY,
          documentScrollHeight: document.documentElement.scrollHeight
        };
      })()`,
      returnByValue: true
    });

    console.log('Node 2 metrics on load:', JSON.stringify(evalResult.result.value, null, 2));

    // Now scroll all the way to the bottom and re-evaluate
    await send('Runtime.evaluate', {
      expression: `window.scrollTo(0, document.documentElement.scrollHeight);`
    });
    await new Promise(r => setTimeout(r, 500));

    const evalScrolled = await send('Runtime.evaluate', {
      expression: `(() => {
        const footer = document.getElementById('global-footer');
        const nav = document.getElementById('mobile-bottom-nav');
        const fRect = footer ? footer.getBoundingClientRect() : null;
        const nRect = nav ? nav.getBoundingClientRect() : null;
        return {
          scrollY: window.scrollY,
          fRect: fRect ? { top: fRect.top, bottom: fRect.bottom, height: fRect.height } : null,
          nRect: nRect ? { top: nRect.top, bottom: nRect.bottom, height: nRect.height } : null,
          isFooterCoveredByNav: fRect && nRect ? (fRect.bottom > nRect.top) : null
        };
      })()`,
      returnByValue: true
    });

    console.log('Node 2 metrics after scroll to bottom:', JSON.stringify(evalScrolled.result.value, null, 2));

    // Capture screenshot
    const shot = await send('Page.captureScreenshot', { format: 'png' });
    const fs = require('fs');
    fs.writeFileSync('c:/Users/deeks/OneDrive/Documents/AQi10/newphpaqi/scratch/node2_overview_mobile.png', Buffer.from(shot.data, 'base64'));
    console.log('Screenshot saved to scratch/node2_overview_mobile.png');

    ws.close();
  } catch(e) {
    console.error('Error:', e);
  } finally {
    chromeProc.kill();
  }
}

testWithChrome();
