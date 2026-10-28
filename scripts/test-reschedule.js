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

    // 1. Click "Reschedule" or "Schedule Follow-up"
    const openRes = await send('Runtime.evaluate', {
      expression: `
        (function() {
          const btns = Array.from(document.querySelectorAll('button'));
          const btn = btns.find(b => b.innerText.includes('Reschedule') || b.innerText.includes('Schedule Follow-up'));
          if (!btn) return { success: false, error: 'Button not found' };
          btn.click();
          return { success: true, btnText: btn.innerText };
        })()
      `,
      returnByValue: true
    });
    console.log('Open modal:', openRes.result);

    await new Promise(r => setTimeout(r, 600));

    // 2. Set scheduled_at = current time + 7 minutes
    const subRes = await send('Runtime.evaluate', {
      expression: `
        (function() {
          const d = new Date(Date.now() + 7 * 60 * 1000);
          const pad = n => String(n).padStart(2, '0');
          const dtStr = \`\${d.getFullYear()}-\${pad(d.getMonth() + 1)}-\${pad(d.getDate())}T\${pad(d.getHours())}:\${pad(d.getMinutes())}\`;

          const dtInput = document.querySelector('input[type="datetime-local"]');
          const notesInput = Array.from(document.querySelectorAll('textarea')).find(t => t.placeholder.includes('finalize IT'));

          if (!dtInput) return { success: false, error: 'Datetime input not found' };

          dtInput.value = dtStr;
          dtInput.dispatchEvent(new Event('input', { bubbles: true }));

          if (notesInput) {
            notesInput.value = 'Executive review of service deliverables with client';
            notesInput.dispatchEvent(new Event('input', { bubbles: true }));
          }

          const form = document.querySelector('.fixed.inset-0 form');
          if (!form) return { success: false, error: 'Form not found' };

          form.requestSubmit();
          return { success: true, scheduled_at: dtStr };
        })()
      `,
      returnByValue: true
    });
    console.log('Submit modal:', subRes.result);

    setTimeout(async () => {
      // Check pending notifications via Capacitor
      const notifRes = await send('Runtime.evaluate', {
        expression: 'window.Capacitor.Plugins.LocalNotifications.getPending()',
        returnByValue: true,
        awaitPromise: true
      });
      console.log('Pending notifications:', JSON.stringify(notifRes.result, null, 2));

      ws.close();
      process.exit(0);
    }, 3000);
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
