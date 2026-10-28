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
    await send('Network.enable');
    await send('Runtime.enable');

    const fillScript = `
      (function() {
        const nameInput = document.querySelector('input[placeholder*="Ramesh Hegde"]');
        const phoneInput = document.querySelector('input[placeholder*="98450"]');
        const emailInput = document.querySelector('input[placeholder*="client@organization"]');
        const companyInput = document.querySelector('input[placeholder*="Acme Tech"]');
        const notesInput = document.querySelector('textarea');

        if (!nameInput || !phoneInput) return { success: false, error: 'Inputs not found' };

        nameInput.value = 'QA Test Inquiry';
        nameInput.dispatchEvent(new Event('input', { bubbles: true }));

        phoneInput.value = '+919999900001';
        phoneInput.dispatchEvent(new Event('input', { bubbles: true }));

        if (emailInput) {
          emailInput.value = 'qa.test.inquiry@vistaboundintegrated.com';
          emailInput.dispatchEvent(new Event('input', { bubbles: true }));
        }

        if (companyInput) {
          companyInput.value = 'QA Test Org';
          companyInput.dispatchEvent(new Event('input', { bubbles: true }));
        }

        if (notesInput) {
          notesInput.value = 'Verified controlled test lead for production smoke testing.';
          notesInput.dispatchEvent(new Event('input', { bubbles: true }));
        }

        const buttons = Array.from(document.querySelectorAll('button'));
        const submitBtn = buttons.find(b => b.innerText.includes('Create Lead Record'));
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
    console.log('Fill result:', res.result);

    setTimeout(async () => {
      const locRes = await send('Runtime.evaluate', {
        expression: 'window.location.href',
        returnByValue: true
      });
      console.log('Location after submit:', locRes.result);

      ws.close();
      process.exit(0);
    }, 4000);
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
        console.log(`[API Response] ${status} ${url}`);
      }
    }
  };
}

run().catch(console.error);
