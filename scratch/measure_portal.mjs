// measure_portal.mjs - Direct CDP Measurement via native WebSocket & fetch in Node 22+
import http from 'http';

const PORT = 9222;
const ROUTES = [
  { name: 'Dashboard (Admin)', url: 'https://portal.innotel.vn/admin' },
  { name: 'Invoices (Hóa đơn)', url: 'https://portal.innotel.vn/admin/invoices' },
  { name: 'Media (Tiện ích Media)', url: 'https://portal.innotel.vn/admin/utilities/media' },
  { name: 'Clients (Khách hàng)', url: 'https://portal.innotel.vn/admin/clients' }
];

async function getBrowserTargets() {
  const res = await fetch(`http://127.0.0.1:${PORT}/json/list`);
  return await res.json();
}

class CDPClient {
  constructor(wsUrl) {
    this.ws = new WebSocket(wsUrl);
    this.id = 1;
    this.callbacks = new Map();
    this.events = new Map();
  }

  async connect() {
    return new Promise((resolve, reject) => {
      this.ws.onopen = () => resolve();
      this.ws.onerror = (err) => reject(err);
      this.ws.onmessage = (msg) => {
        const data = JSON.parse(msg.data);
        if (data.id && this.callbacks.has(data.id)) {
          const { resolve, reject } = this.callbacks.get(data.id);
          this.callbacks.delete(data.id);
          if (data.error) reject(data.error);
          else resolve(data.result);
        } else if (data.method) {
          const listeners = this.events.get(data.method) || [];
          listeners.forEach(fn => fn(data.params));
        }
      };
    });
  }

  send(method, params = {}) {
    return new Promise((resolve, reject) => {
      const id = this.id++;
      this.callbacks.set(id, { resolve, reject });
      this.ws.send(JSON.stringify({ id, method, params }));
    });
  }

  on(method, fn) {
    if (!this.events.has(method)) this.events.set(method, []);
    this.events.get(method).push(fn);
  }

  close() {
    this.ws.close();
  }
}

async function run() {
  console.log('Connecting to Chrome on port', PORT, '...');
  let targets;
  try {
    targets = await getBrowserTargets();
  } catch (e) {
    console.error('ERROR: Could not connect to Chrome on port 9222. Is Chrome running with --remote-debugging-port=9222?');
    process.exit(1);
  }

  // Find active page or portal page
  let target = targets.find(t => t.type === 'page' && t.url.includes('portal.innotel.vn')) ||
               targets.find(t => t.type === 'page' && !t.url.startsWith('chrome-devtools://'));

  if (!target) {
    console.log('Creating new tab...');
    const createRes = await fetch(`http://127.0.0.1:${PORT}/json/new?https://portal.innotel.vn/admin`);
    target = await createRes.json();
  }

  console.log(`Using tab: ${target.title} (${target.url})`);
  const client = new CDPClient(target.webSocketDebuggerUrl);
  await client.connect();

  await client.send('Page.enable');
  await client.send('Network.enable');

  console.log('\n================ BẮT ĐẦU ĐO ĐẠC CÁC ROUTE ================');

  for (const route of ROUTES) {
    console.log(`\n>>> Đang điều hướng tới: ${route.name} (${route.url})`);
    
    let mainDocRequest = null;
    let mainDocResponse = null;
    let ajaxRequests = [];
    let loadResolve;
    const loadPromise = new Promise(r => loadResolve = r);

    const onReq = (params) => {
      if (params.type === 'Document' && !mainDocRequest) {
        mainDocRequest = params;
      } else if (params.type === 'XHR' || params.type === 'Fetch') {
        ajaxRequests.push(params);
      }
    };

    const onResp = (params) => {
      if (params.type === 'Document' && (!mainDocResponse || params.response.url.includes(route.url.split('/').pop()))) {
        mainDocResponse = params;
      }
    };

    const onLoad = () => {
      setTimeout(() => loadResolve(), 1500); // Đợi thêm 1.5s cho AJAX hoàn tất
    };

    client.on('Network.requestWillBeSent', onReq);
    client.on('Network.responseReceived', onResp);
    client.on('Page.loadEventFired', onLoad);

    const tStart = Date.now();
    await client.send('Page.navigate', { url: route.url });
    await loadPromise;
    const totalDuration = ((Date.now() - tStart) / 1000).toFixed(2);

    // Thu thập Navigation Timing từ JavaScript trong Page
    const evalResult = await client.send('Runtime.evaluate', {
      expression: `JSON.stringify({
        url: window.location.href,
        title: document.title,
        timing: performance.getEntriesByType('navigation')[0] || {}
      })`,
      returnByValue: true
    });

    const pageData = JSON.parse(evalResult.result.value);
    const nav = pageData.timing;

    console.log(`--- KẾT QUẢ CHO: ${route.name} ---`);
    console.log(`Tiêu đề trang: ${pageData.title}`);
    console.log(`URL cuối cùng: ${pageData.url}`);
    console.log(`Tổng thời gian từ lúc bấm tới khi Load xong: ${totalDuration}s`);

    if (nav.responseStart && nav.requestStart) {
      const ttfb = (nav.responseStart - nav.requestStart).toFixed(2);
      const domContent = (nav.domContentLoadedEventEnd - nav.startTime).toFixed(2);
      const fullLoad = (nav.loadEventEnd - nav.startTime).toFixed(2);
      console.log(`  * TTFB (Waiting for server response): ${ttfb} ms (${(ttfb/1000).toFixed(2)} s)`);
      console.log(`  * DOMContentLoaded: ${domContent} ms (${(domContent/1000).toFixed(2)} s)`);
      console.log(`  * Window Load: ${fullLoad} ms (${(fullLoad/1000).toFixed(2)} s)`);
    } else if (mainDocResponse && mainDocResponse.response.timing) {
      const t = mainDocResponse.response.timing;
      console.log(`  * TTFB (Waiting for server response): ${t.receiveHeadersEnd} ms`);
    }

    if (mainDocResponse) {
      const serverTiming = mainDocResponse.response.headers['server-timing'] || mainDocResponse.response.headers['Server-Timing'];
      if (serverTiming) console.log(`  * Server-Timing: ${serverTiming}`);
    }

    console.log(`  * Số request AJAX ngầm: ${ajaxRequests.length}`);

    // Clean up listeners for next route
    client.events.delete('Network.requestWillBeSent');
    client.events.delete('Network.responseReceived');
    client.events.delete('Page.loadEventFired');
    
    // Nghỉ 1 giây giữa các route
    await new Promise(r => setTimeout(r, 1000));
  }

  console.log('\n================ HOÀN TẤT ĐO ĐẠC ================');
  client.close();
}

run().catch(console.error);
