# Cẩm Nang Triển Khai Baileys Gateway Cho Kênh WhatsApp (Sales Pipeline)

Tài liệu này hướng dẫn chi tiết quy trình triển khai dịch vụ Baileys Gateway trên VPS Linux riêng biệt và khớp nối hạ tầng với hệ thống CRM `portal.innotel.vn`.

---

## TỔNG QUAN KIẾN TRÚC HẠ TẦNG

```
[CRM portal.innotel.vn (103.130.217.180)]
                 │
      HTTPS (Cổng 443 / TLS)
                 ▼
[VPS Linux (wa-gw.innotel.vn)]
   ├─ Nginx Reverse Proxy (Cổng 443 -> 127.0.0.1:3050)
   │    ├─ ACME Challenge (Cho phép công khai để cấp SSL)
   │    └─ API Path (Chỉ cho phép IP 103.130.217.180)
   └─ Docker Container (Port 3050 nội bộ)
        ├─ Node.js Runtime + Baileys Socket
        └─ Persistent Volume: auth_info_baileys/ & idempotency_store.json
                 │
      WebSocket (24/7 Persistent)
                 ▼
      [WhatsApp Multi-Device Servers]
```

---

## GIAI ĐOẠN 1: CẤU HÌNH DNS & ĐIỆN THOẠI ĐẠI DIỆN

### Bước 1: Tạo Bản Ghi DNS Subdomain
1. Đăng nhập vào trang quản trị DNS quản lý tên miền `innotel.vn` (Cloudflare, PA Việt Nam, Mắt Bão, Viettel, v.v.).
2. Thêm bản ghi mới:
   - **Loại bản ghi (Type)**: `A`
   - **Tên bản ghi (Host / Name)**: `wa-gw` (tên miền đầy đủ là `wa-gw.innotel.vn`)
   - **Giá trị (IPv4 Address)**: Điền địa chỉ Public IPv4 của VPS Gateway mới.
   - **TTL**: Để tự động (Auto) hoặc 300 giây.
3. **LƯU Ý ĐẶC BIỆT KHI DÙNG CLOUDFLARE**:
   - Bắt buộc chọn chế độ **DNS Only** (Đám mây màu xám).
   - **Tuyệt đối KHÔNG bật Proxy** (Đám mây màu cam). Nếu bật Proxy, Nginx trên VPS sẽ nhận IP nguồn từ Cloudflare Edge thay vì IP `103.130.217.180` của máy chủ CRM, dẫn đến request từ CRM bị Nginx chặn lỗi `403 Forbidden`.

### Bước 2: Chuẩn Bị Thiết Bị WhatsApp
- Dùng điện thoại gắn SIM đại diện của công ty.
- Mở ứng dụng WhatsApp -> vào mục **Cài đặt (Settings)** -> **Thiết bị liên kết (Linked Devices)** -> sẵn sàng camera quét mã QR.

---

## GIAI ĐOẠN 2: TRIỂN KHAI GATEWAY TRÊN VPS LINUX

Đăng nhập SSH vào VPS:
```bash
ssh root@<IP_VPS_GATEWAY>
```

### Bước 2.1: Cấu hình Swap 2GB & Cài đặt gói bắt buộc
```bash
apt update && apt upgrade -y

# Tạo 2GB Swap chống tràn RAM cho gói VPS 1GB
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab

# Cài đặt Nginx, Certbot và Docker
apt install -y curl git ufw nginx certbot python3-certbot-nginx
curl -fsSL https://get.docker.com -o get-docker.sh && sh get-docker.sh
apt install -y docker-compose-plugin
```

### Bước 2.2: Tạo thư mục & Sinh Secret Key ngẫu nhiên
```bash
mkdir -p /opt/whatsapp-gateway/auth_info_baileys
cd /opt/whatsapp-gateway

GEN_SECRET=$(openssl rand -hex 32)

cat << EOF > .env
PORT=3050
GATEWAY_SECRET=${GEN_SECRET}
SESSION_DIR=/app/auth_info_baileys
EOF

chmod 600 .env
chmod 700 auth_info_baileys

echo "========================================================="
echo "LƯU SECRET KEY NÀY ĐỂ ĐIỀN VÀO CRM: ${GEN_SECRET}"
echo "========================================================="
```

### Bước 2.3: Tạo `package.json`
```bash
cat << 'EOF' > package.json
{
  "name": "innotel-whatsapp-gateway",
  "version": "1.0.0",
  "type": "module",
  "scripts": {
    "start": "node server.js"
  },
  "dependencies": {
    "@whiskeysockets/baileys": "^6.7.8",
    "cors": "^2.8.5",
    "dotenv": "^16.4.5",
    "express": "^4.19.2",
    "pino": "^9.0.0",
    "qrcode-terminal": "^0.12.0"
  }
}
EOF
```

### Bước 2.4: Tạo `server.js`
File mã nguồn hỗ trợ đầy đủ: Listen `0.0.0.0`, giới hạn body 64kb, ghi trạng thái `in_progress` nguyên tử để chống gửi trùng:
```bash
cat << 'EOF' > server.js
import express from 'express';
import dotenv from 'dotenv';
import pino from 'pino';
import qrcode from 'qrcode-terminal';
import fs from 'node:fs';
import path from 'node:path';
import makeWASocket, { 
    DisconnectReason, 
    useMultiFileAuthState 
} from '@whiskeysockets/baileys';

dotenv.config();

const PORT = parseInt(process.env.PORT || '3050', 10);
const GATEWAY_SECRET = process.env.GATEWAY_SECRET;
const SESSION_DIR = process.env.SESSION_DIR || './auth_info_baileys';

if (!GATEWAY_SECRET || GATEWAY_SECRET.trim().length < 16) {
    console.error('FATAL: GATEWAY_SECRET chưa được cấu hình hoặc quá ngắn (tối thiểu 16 ký tự).');
    process.exit(1);
}

const IDEMPOTENCY_FILE = path.join(SESSION_DIR, 'idempotency_store.json');

function loadIdempotencyStore() {
    try {
        if (fs.existsSync(IDEMPOTENCY_FILE)) {
            return JSON.parse(fs.readFileSync(IDEMPOTENCY_FILE, 'utf8'));
        }
    } catch (e) {
        console.error('Không thể đọc idempotency store:', e.message);
    }
    return {};
}

function saveIdempotencyRecordAtomic(key, record) {
    if (!key) return;
    try {
        const store = loadIdempotencyStore();
        store[key] = {
            ...record,
            updated_at: new Date().toISOString()
        };
        const keys = Object.keys(store);
        if (keys.length > 10000) {
            delete store[keys[0]];
        }
        const tmpFile = IDEMPOTENCY_FILE + '.tmp';
        fs.writeFileSync(tmpFile, JSON.stringify(store, null, 2), 'utf8');
        fs.renameSync(tmpFile, IDEMPOTENCY_FILE);
    } catch (e) {
        console.error('Không thể ghi idempotency store:', e.message);
    }
}

const app = express();
app.use(express.json({ limit: '64kb' }));

let sock = null;
let connectionStatus = 'DISCONNECTED';

async function startWhatsApp() {
    connectionStatus = 'CONNECTING';
    const { state, saveCreds } = await useMultiFileAuthState(SESSION_DIR);

    sock = makeWASocket({
        logger: pino({ level: 'silent' }),
        printQRInTerminal: false,
        auth: state,
        browser: ['Innotel CRM Gateway', 'Chrome', '120.0.0']
    });

    sock.ev.on('connection.update', (update) => {
        const { connection, lastDisconnect, qr } = update;
        
        if (qr) {
            console.log('\n=============================================');
            console.log('📱 HÃY DÙNG APP WHATSAPP QUÉT MÃ QR NÀY:');
            console.log('=============================================\n');
            qrcode.generate(qr, { small: true });
        }

        if (connection === 'close') {
            const shouldReconnect = lastDisconnect?.error?.output?.statusCode !== DisconnectReason.loggedOut;
            console.log('Mất kết nối WhatsApp. Lý do:', lastDisconnect?.error?.message);
            connectionStatus = 'DISCONNECTED';
            if (shouldReconnect) {
                console.log('Đang thử kết nối lại sau 5 giây...');
                setTimeout(startWhatsApp, 5000);
            } else {
                console.log('Session bị Logged Out. Cần xóa thư mục session và quét lại QR.');
            }
        } else if (connection === 'open') {
            console.log('KẾT NỐI WHATSAPP ĐÃ HOẠT ĐỘNG (CONNECTED)!');
            connectionStatus = 'CONNECTED';
        }
    });

    sock.ev.on('creds.update', saveCreds);
}

const authenticate = (req, res, next) => {
    const secret = req.headers['x-gateway-secret'];
    if (!secret || secret !== GATEWAY_SECRET) {
        return res.status(401).json({ success: false, error: 'Unauthorized: Sai Secret Key' });
    }
    next();
};

app.get('/api/v1/health', (req, res) => {
    res.json({
        status: connectionStatus,
        connected: connectionStatus === 'CONNECTED',
        timestamp: new Date().toISOString()
    });
});

app.get('/api/v1/messages/status/:idempotencyKey', authenticate, (req, res) => {
    const { idempotencyKey } = req.params;
    const store = loadIdempotencyStore();
    const record = store[idempotencyKey];

    if (!record) {
        return res.status(404).json({
            status: 'not_found',
            idempotency_key: idempotencyKey
        });
    }

    return res.json({
        status: record.status,
        message_id: record.message_id || null,
        error: record.error || null,
        retry_safe: record.retry_safe !== undefined ? record.retry_safe : (record.status === 'failed'),
        timestamp: record.timestamp
    });
});

app.post('/api/v1/messages/send', authenticate, async (req, res) => {
    if (connectionStatus !== 'CONNECTED') {
        return res.status(503).json({
            success: false,
            error: 'WhatsApp gateway chưa sẵn sàng hoặc mất socket',
            retry_safe: true
        });
    }

    const { recipient, text, idempotency_key } = req.body;
    if (!recipient || !text) {
        return res.status(400).json({ success: false, error: 'Thiếu recipient hoặc text' });
    }

    if (idempotency_key) {
        const store = loadIdempotencyStore();
        const existing = store[idempotency_key];
        
        if (existing) {
            if (existing.status === 'sent') {
                return res.json({
                    success: true,
                    message_id: existing.message_id,
                    idempotency_key: idempotency_key,
                    timestamp: existing.timestamp,
                    cached: true
                });
            }
            if (existing.status === 'in_progress') {
                return res.status(409).json({
                    success: false,
                    error: 'Yêu cầu gửi cho key này đang được xử lý (in_progress)',
                    retry_safe: true
                });
            }
        }

        saveIdempotencyRecordAtomic(idempotency_key, {
            status: 'in_progress',
            recipient: recipient.trim(),
            timestamp: Math.floor(Date.now() / 1000)
        });
    }

    try {
        let jid = recipient.trim();
        if (!jid.includes('@')) {
            let phone = jid.replace(/[^0-9]/g, '');
            if (phone.startsWith('0')) {
                phone = '84' + phone.substring(1);
            }
            jid = phone + '@s.whatsapp.net';
        }

        const sent = await sock.sendMessage(jid, { text });
        const messageId = sent?.key?.id || ('msg_' + Date.now());
        const timestamp = Math.floor(Date.now() / 1000);

        if (idempotency_key) {
            saveIdempotencyRecordAtomic(idempotency_key, {
                status: 'sent',
                message_id: messageId,
                recipient: jid,
                timestamp: timestamp
            });
        }

        return res.json({
            success: true,
            message_id: messageId,
            idempotency_key: idempotency_key,
            timestamp: timestamp
        });
    } catch (err) {
        console.error('Lỗi khi gửi tin nhắn qua Baileys:', err.message);
        if (idempotency_key) {
            saveIdempotencyRecordAtomic(idempotency_key, {
                status: 'failed',
                error: err.message,
                retry_safe: true,
                timestamp: Math.floor(Date.now() / 1000)
            });
        }
        return res.status(500).json({
            success: false,
            error: err.message,
            retry_safe: true
        });
    }
});

app.get('/api/v1/groups', authenticate, async (req, res) => {
    if (connectionStatus !== 'CONNECTED') {
        return res.status(503).json({ success: false, error: 'Gateway offline' });
    }
    try {
        const participating = await sock.groupFetchAllParticipating();
        const list = Object.values(participating).map(g => ({
            jid: g.id,
            subject: g.subject
        }));
        res.json({
            success: true,
            groups: list,
            data: list
        });
    } catch (e) {
        res.status(500).json({ success: false, error: e.message });
    }
});

app.listen(PORT, '0.0.0.0', () => {
    console.log(`Gateway Node đang lắng nghe trong container tại port ${PORT}`);
    startWhatsApp();
});
EOF
```

### Bước 2.5: Tạo `Dockerfile` & `docker-compose.yml`
```bash
cat << 'EOF' > Dockerfile
FROM node:20-alpine
WORKDIR /app
COPY package*.json ./
RUN npm install --omit=dev
COPY . .
RUN mkdir -p /app/auth_info_baileys
EXPOSE 3050
CMD ["node", "server.js"]
EOF

cat << 'EOF' > docker-compose.yml
version: '3.8'
services:
  whatsapp-gateway:
    build: .
    container_name: innotel-whatsapp-gateway
    restart: unless-stopped
    ports:
      - "127.0.0.1:3050:3050"
    volumes:
      - ./auth_info_baileys:/app/auth_info_baileys
    env_file:
      - .env
EOF
```

### Bước 2.6: Khởi chạy và Quét mã QR
```bash
docker compose up -d --build
docker logs -f innotel-whatsapp-gateway
```
- Dùng điện thoại WhatsApp quét mã QR xuất hiện trên terminal.
- Khi màn hình hiện `KẾT NỐI WHATSAPP ĐÃ HOẠT ĐỘNG (CONNECTED)!`, bấm `Ctrl + C` để thoát màn hình xem log.

---

## GIAI ĐOẠN 3: THIẾT LẬP NGINX HTTPS & TÁCH ACME CHALLENGE

### Bước 3.1: Tạo cấu hình Nginx
```bash
cat << 'EOF' > /etc/nginx/sites-available/whatsapp-gateway
server {
    listen 80;
    server_name wa-gw.innotel.vn;

    # Cho phép công khai riêng mục này để Let's Encrypt xác thực HTTP-01
    location ^~ /.well-known/acme-challenge/ {
        allow all;
        default_type "text/plain";
        root /var/www/html;
    }

    # API được bảo vệ nghiêm ngặt: chỉ IP CRM Production được kết nối
    location / {
        allow 103.130.217.180;
        deny all;

        proxy_pass http://127.0.0.1:3050;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_connect_timeout 5s;
        proxy_read_timeout 15s;
    }
}
EOF

mkdir -p /var/www/html
ln -s /etc/nginx/sites-available/whatsapp-gateway /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### Bước 3.2: Cấp chứng chỉ SSL tự động qua Certbot
```bash
certbot --nginx -d wa-gw.innotel.vn --non-interactive --agree-tos -m it@innotel.com.vn
```

### Bước 3.3: Bật tường lửa UFW
```bash
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
ufw status verbose
```

---

## GIAI ĐOẠN 4: THÔNG MẠNG TỪ CPANEL & CẤU HÌNH CRM

1. **Kiểm tra thông mạng từ cPanel CRM**:
   - Mở Terminal trên cPanel của `portal.innotel.vn` và chạy:
     ```bash
     curl -s https://wa-gw.innotel.vn/api/v1/health
     ```
   - Kết quả trả về JSON có `"connected": true, "status": "CONNECTED"` là kết nối đã hoàn toàn thông suốt.
2. **Cấu hình trên CRM**:
   - Truy cập: `https://portal.innotel.vn/admin/sales_pipeline/settings#reminders`
   - Nhập:
     - **Địa chỉ Gateway**: `https://wa-gw.innotel.vn/api/v1/messages/send`
     - **Secret Key**: Chuỗi secret đã sinh ở Bước 2.2.
     - **Timeout**: `5` giây.
     - **Giới hạn tin/giờ**: `60` tin/giờ.
   - Bấm **[Lưu cài đặt]**.
3. **Kiểm tra nút bấm**:
   - Bấm **"KIỂM TRA KẾT NỐI (TEST CONNECTION)"** -> Thông báo thành công.
   - Bấm **"Lấy danh sách nhóm"** -> Nạp danh sách các nhóm WhatsApp của công ty.

---

## GIAI ĐOẠN 5: CANARY TEST & KÍCH HOẠT CHÍNH THỨC

1. Đảm bảo tài khoản Admin/Quản lý trong **Hệ thống** -> **Nhân viên** đã có Số điện thoại WhatsApp hợp lệ.
2. Tại màn hình cài đặt `#reminders`, chọn chế độ nhận tin `direct_only`, bấm nút kiểm tra kết nối để nhận 1 tin test có icon `🔔` đến WhatsApp cá nhân.
3. Kiểm tra tin nhắn hiển thị chuẩn xác và bấm thử liên kết deeplink mở CRM.
4. Tích chọn **"Kích hoạt gửi cảnh báo qua WhatsApp"** (`sp_reminder_whatsapp_enabled = 1`), chọn các quy tắc cần gửi và bấm **[Lưu cài đặt]** để đưa vào vận hành chính thức.
