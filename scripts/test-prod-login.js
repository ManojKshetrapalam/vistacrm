import http from 'http';

async function getWsUrl() {
  return new Promise((resolve, reject) => {
    http.get('http://127.0.0.1:9222/json', (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          const list = JSON.parse(data);
          if (list.length > 0 && list[0].webSocketDebuggerUrl) {
            resolve(list[0].webSocketDebuggerUrl);
          } else {
            reject(new Error('No debuggable page found'));
          }
        } catch (e) {
          reject(e);
        }
      });
    }).on('error', reject);
  });
}

async function run() {
  const wsUrl = await getWsUrl();
  console.log('Connecting to', wsUrl);
  const ws = new WebSocket(wsUrl);

  let msgId = 1;
  const pending = new Map();

  function send(method, params = {}) {
    return new Promise((resolve, reject) => {
      const id = msgId++;
      pending.set(id, { resolve, reject });
      ws.send(JSON.stringify({ id, method, params }));
    });
  }

  ws.onopen = async () => {
    console.log('Connected to WebView CDP');

    // Enable Network & Runtime
    await send('Network.enable');
    await send('Runtime.enable');

    // Fill form and submit
    const fillScript = `
      (function() {
        const inputs = document.querySelectorAll('input');
        if (inputs.length < 2) return { success: false, error: 'Inputs not found' };
        
        inputs[0].value = 'qa.mobile@vistaboundintegrated.com';
        inputs[0].dispatchEvent(new Event('input', { bubbles: true }));

        inputs[1].value = 'Vb_Qa_Live_2026!#Secure';
        inputs[1].dispatchEvent(new Event('input', { bubbles: true }));

        const submitBtn = document.querySelector('button[type="submit"]');
        if (!submitBtn) return { success: false, error: 'Submit button not found' };
        
        submitBtn.click();
        return { success: true };
      })()
    `;

    const res = await send('Runtime.evaluate', {
      expression: fillScript,
      returnByValue: true,
      awaitPromise: true
    });
    console.log('Form submission evaluation:', res.result);

    // Wait 5 seconds for navigation
    setTimeout(async () => {
      const locRes = await send('Runtime.evaluate', {
        expression: 'window.location.href',
        returnByValue: true
      });
      console.log('Location after login:', locRes.result);

      const tokenRes = await send('Runtime.evaluate', {
        expression: 'localStorage.getItem("vb_crm_token") ? "TOKEN_EXISTS" : "NO_TOKEN"',
        returnByValue: true
      });
      console.log('Token check:', tokenRes.result);

      ws.close();
      process.exit(0);
    }, 5000);
  };

  ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    if (data.id && pending.has(data.id)) {
      const { resolve } = pending.get(data.id);
      pending.delete(data.id);
      resolve(data.result);
    } else if (data.method === 'Network.responseReceived') {
      const url = data.params.response.url;
      const status = data.params.response.status;
      if (url.includes('api/')) {
        console.log(`[Network Response] ${status} ${url}`);
      }
    }
  };

  ws.onerror = (err) => {
    console.error('WS Error:', err);
  };
}

run().catch(console.error);
