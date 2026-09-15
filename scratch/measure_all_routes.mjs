// measure_all_routes.mjs - Crawl and measure ALL admin pages directly from the sidebar menu
const PORT = 9222;

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
  console.log('Đang kết nối vào Chrome trên cổng 9222...');
  const targetsRes = await fetch(`http://127.0.0.1:${PORT}/json/list`);
  const targets = await targetsRes.json();
  const target = targets.find(t => t.type === 'page' && t.url.includes('portal.innotel.vn'));

  if (!target) {
    console.error('Không tìm thấy tab portal.innotel.vn đang mở!');
    process.exit(1);
  }

  const client = new CDPClient(target.webSocketDebuggerUrl);
  await client.connect();
  await client.send('Page.enable');
  await client.send('Runtime.enable');
  await client.send('Network.enable');

  // Trích xuất toàn bộ menu links từ sidebar của Perfex CRM
  const extractLinksRes = await client.send('Runtime.evaluate', {
    expression: `
      (() => {
        const links = [];
        const anchors = document.querySelectorAll('#side-menu a, .sidebar-menu a, .sidebar a');
        anchors.forEach(a => {
          const href = a.href;
          const text = a.innerText.trim().replace(/\\n/g, ' - ');
          if (href && href.startsWith('https://portal.innotel.vn/admin') && !href.includes('authentication/logout') && !href.includes('#') && !links.some(l => l.href === href)) {
            links.push({ text: text || href, href });
          }
        });
        return JSON.stringify(links);
      })()
    `,
    returnByValue: true
  });

  let menuLinks = JSON.parse(extractLinksRes.result.value);
  console.log(`Tìm thấy ${menuLinks.length} trang menu trên hệ thống!`);

  // Nếu selector không lấy được, dùng danh sách fallback đầy đủ các trang chính
  if (menuLinks.length === 0) {
    menuLinks = [
      { text: 'Bảng tin (Dashboard)', href: 'https://portal.innotel.vn/admin' },
      { text: 'Khách hàng (Customers)', href: 'https://portal.innotel.vn/admin/clients' },
      { text: 'Dự án (Projects)', href: 'https://portal.innotel.vn/admin/projects' },
      { text: 'Công việc (Tasks)', href: 'https://portal.innotel.vn/admin/tasks' },
      { text: 'Hóa đơn (Invoices)', href: 'https://portal.innotel.vn/admin/invoices' },
      { text: 'Báo giá (Estimates)', href: 'https://portal.innotel.vn/admin/estimates' },
      { text: 'Đề xuất (Proposals)', href: 'https://portal.innotel.vn/admin/proposals' },
      { text: 'Thanh toán (Payments)', href: 'https://portal.innotel.vn/admin/payments' },
      { text: 'Hợp đồng (Contracts)', href: 'https://portal.innotel.vn/admin/contracts' },
      { text: 'Chi phí (Expenses)', href: 'https://portal.innotel.vn/admin/expenses' },
      { text: 'Lead (Cơ hội)', href: 'https://portal.innotel.vn/admin/leads' },
      { text: 'Sales Pipeline', href: 'https://portal.innotel.vn/admin/sales_pipeline' },
      { text: 'Kho tiện ích (Media)', href: 'https://portal.innotel.vn/admin/utilities/media' },
      { text: 'Báo cáo (Reports)', href: 'https://portal.innotel.vn/admin/reports' },
      { text: 'Cài đặt (Settings)', href: 'https://portal.innotel.vn/admin/settings' },
      { text: 'Nhân viên (Staff)', href: 'https://portal.innotel.vn/admin/staff' }
    ];
  }

  const results = [];

  console.log('\n================ BẮT ĐẦU DUYỆT VÀ ĐO LƯỜNG TẤT CẢ CÁC TRANG ================');

  for (let i = 0; i < menuLinks.length; i++) {
    const item = menuLinks[i];
    console.log(`\n[${i + 1}/${menuLinks.length}] Đang nhấp vào: ${item.text} (${item.href})...`);

    let loadResolve;
    const loadPromise = new Promise(r => loadResolve = r);

    const onLoad = () => {
      setTimeout(() => loadResolve(), 1200);
    };

    client.on('Page.loadEventFired', onLoad);

    const tStart = Date.now();
    await client.send('Page.navigate', { url: item.href });
    await loadPromise;
    const totalDuration = ((Date.now() - tStart) / 1000).toFixed(2);

    // Lấy thông số Navigation Timing
    const evalResult = await client.send('Runtime.evaluate', {
      expression: `JSON.stringify({
        title: document.title,
        url: window.location.href,
        timing: performance.getEntriesByType('navigation')[0] || {}
      })`,
      returnByValue: true
    });

    const pageData = JSON.parse(evalResult.result.value);
    const nav = pageData.timing;

    let ttfb = 'N/A';
    let domReady = 'N/A';
    let windowLoad = 'N/A';

    if (nav.responseStart && nav.requestStart) {
      ttfb = (nav.responseStart - nav.requestStart).toFixed(0);
      domReady = (nav.domContentLoadedEventEnd - nav.startTime).toFixed(0);
      windowLoad = (nav.loadEventEnd - nav.startTime).toFixed(0);
    }

    console.log(`  ✓ Tiêu đề: ${pageData.title}`);
    console.log(`  ✓ TTFB (Chờ Server): ${ttfb} ms (${(ttfb / 1000).toFixed(2)} s)`);
    console.log(`  ✓ Tổng thời gian: ${totalDuration} s`);

    results.push({
      stt: i + 1,
      name: item.text,
      title: pageData.title,
      url: item.href,
      ttfb: ttfb !== 'N/A' ? `${ttfb} ms (${(ttfb / 1000).toFixed(2)}s)` : 'N/A',
      ttfb_num: parseFloat(ttfb) || 0,
      render: domReady !== 'N/A' ? `${domReady - ttfb} ms` : 'N/A',
      total: `${totalDuration} s`
    });

    client.events.delete('Page.loadEventFired');
    await new Promise(r => setTimeout(r, 600)); // Nghỉ 600ms giữa các trang
  }

  console.log('\n================ BẢNG TỔNG HỢP TOÀN BỘ CÁC TRANG ================');
  console.table(results.map(r => ({
    STT: r.stt,
    'Trang / Menu': r.name,
    'TTFB (Chờ Server)': r.ttfb,
    'Thời gian Render': r.render,
    'Tổng thời gian': r.total
  })));

  client.close();
}

run().catch(console.error);
