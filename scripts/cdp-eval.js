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

async function evalExpression(expr) {
  const wsUrl = await getWsUrl();
  const ws = new WebSocket(wsUrl);

  return new Promise((resolve, reject) => {
    ws.onopen = () => {
      const msg = JSON.stringify({
        id: 1,
        method: 'Runtime.evaluate',
        params: {
          expression: expr,
          returnByValue: true,
          awaitPromise: true
        }
      });
      ws.send(msg);
    };

    ws.onmessage = (event) => {
      const resp = JSON.parse(event.data);
      if (resp.id === 1) {
        ws.close();
        resolve(resp.result);
      }
    };

    ws.onerror = reject;
  });
}

const expr = process.argv.slice(2).join(' ') || 'window.location.href';
evalExpression(expr)
  .then(res => console.log(JSON.stringify(res, null, 2)))
  .catch(err => {
    console.error('CDP Error:', err);
    process.exit(1);
  });
