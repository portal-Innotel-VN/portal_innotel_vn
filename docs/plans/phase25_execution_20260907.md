# Phase 2.5 — Kết Quả Thực Thi & Nghiệm Thu Hoàn Tất Ngày 2026-09-07

## 1. Phạm Vi và Nguồn Đối Chiếu

Đã đối chiếu context trong `docs/ai/` (`PROJECT_CONTEXT`, `BUSINESS_INVARIANTS`, `IMPLEMENTATION_STATUS`, `CODE_MAP`, `SALES_PIPELINE_CONTEXT`), context project, đặc tả `PERFORMANCE_SCORE` và Basecode:
- `modules/sales_pipeline/sales_pipeline.php`
- `modules/sales_pipeline/models/Sales_pipeline_model.php`
- `application/models/Cron_model.php`
- `application/config/database.php` (xác minh `'pconnect' => false`)
- `application/config/app-config.php` (nạp an toàn DB credentials, không hardcode)

Invariant tuân thủ tuyệt đối:
- Thao tác trên Staging DB `innotel_portal` @ `127.0.0.1:3306`.
- Không gọi Core Cron hay hook reminder ngoài phạm vi; không chạm Core mail queue, không gửi email, SMTP tắt (`127.0.0.1:25` Connection refused).
- Không can thiệp server của người dùng tại cổng 8000.
- Bảo toàn toàn bộ uncommitted changes của người dùng trong Git.

---

## 2. Kết Quả Benchmark Hiệu Năng (PHP-FPM) — PASS ✅

- Harness: `/private/tmp/sp-phase25-fpm/acceptance-benchmark.mjs`
- Bằng chứng thô: `/private/tmp/sp-phase25-fpm/acceptance-result-20260907.json`
- Môi trường: Apache 8081 + PHP-FPM 8.5.7, 5 workers, DB `innotel_portal`.
- Tải: 15 session đăng nhập độc lập của admin staging, 10 vòng/session = 150 mẫu/endpoint.
- Nội dung phản hồi được kiểm tra chặt chẽ (Dashboard HTML chứa bảng `sp-performance-table`; Leaderboard AJAX trả `success: true` và HTML > 100 bytes; Drawer Báo giá trả JSON với estimate ID 9740 và versions array).

| Endpoint | SLA Mục tiêu | P95 Đạt được | P99 | Tỷ lệ Lỗi | Trạng thái |
|---|---:|---:|---:|---:|:---:|
| Dashboard Báo giá | < 2.000 ms | **305,1 ms** | 312,9 ms | 0/150 (0%) | **PASS** |
| Leaderboard AJAX | < 1.000 ms | **277,3 ms** | 278,6 ms | 0/150 (0%) | **PASS** |
| Drawer Báo giá (`estimate_id=9740`) | ≤ 1.500 ms | **177,5 ms** | 181,9 ms | 0/150 (0%) | **PASS** |

---

## 3. Khắc Phục Basecode Backfill Cursor & Khóa Concurrency — PASS ✅

1. **Model `Sales_pipeline_model::reconcile_missing_first_sent_groups($limit = 100, $cursor = null, $force = false, $lockAlreadyHeld = false)`**:
   - Quản lý checkpoint bằng option `sp_first_sent_reconcile_cursor` (`autoload = 0`, đọc trực tiếp DB qua helper model, tránh stale cache Perfex).
   - Bao bọc toàn bộ chu kỳ bằng MySQL Advisory Lock `tblsales_pipeline:first_sent_reconcile` (`pconnect = false`), chống race condition.
   - **Error Safety Policy**: Dừng ngay khi có lỗi, chỉ lưu checkpoint đến `$lastSuccessfulGroupId`, không bao giờ nhảy cóc qua nhóm bị lỗi.
   - **Cycle Completion Reset**: Tự động reset cursor về `0` và đánh dấu `cycle_completed = true` khi chạm cuối bảng.
   - Phân loại rõ ràng các trạng thái trả về: `processed`, `skipped_cooldown`, `skipped_lock`, `failed`.
2. **Production Cron Hook `sales_pipeline_cron_reminder()`**:
   - Dòng 130 gọi `$CI->sales_pipeline_model->reconcile_missing_first_sent_groups(100, null, false);`.
   - Phân định rõ phạm vi: Production Hook bao gồm toàn bộ chu trình CRM (rules, maintenance, engine), còn `run_reconcile_cadence.php` là **sub-routine Reconcile-Only** cô lập cho việc dọn dẹp dữ liệu tồn đọng trong nền.

---

## 4. Bằng Chứng Thực Thi OS Scheduler Thực Tế (Native macOS Launchd) — PASS ✅

Đã triển khai Launch Agent chính thức trên macOS (`com.portal18.phase25.cadence.plist`) với `StartInterval = 300` giây (5 phút), nạp qua `launchctl load` và thu thập bằng chứng runtime theo thời gian thực (100% KHÔNG dùng cờ bypass `--force`):

1. **Chu kỳ 1 (Kích hoạt tự động bởi OS launchd lúc 10:46:03 AM)**:
   - OS spawn process (PID `14880`):
   - `run_id`: `rec_20260907_034603_e13824`
   - Started: `03:46:03Z` | Finished: `10:46:08` (+07:00) | Duration: 5.022,98 ms
   - `status`: **`processed`** | `force`: **`false`**
   - Cursor: `0` -> `100` (đã quét 100 nhóm, 0 lỗi, checkpoint lưu chuẩn xác vào DB)
2. **Kiểm chứng Cooldown 300s tự nhiên (Lúc 10:46:13 AM, sau Chu kỳ 1 được 10s)**:
   - Kích hoạt runner thủ công không dùng `--force`:
   - `run_id`: `rec_20260907_034613_96c200`
   - `status`: **`skipped_cooldown`** | `reason`: **`cooldown_active`**
   - Cursor bảo toàn: `100` -> `100` (chứng minh khi thời gian < 300s, runner chặn chạy sớm an toàn)
3. **Chu kỳ 2 (Kích hoạt tự động bởi OS launchd lúc 10:51:08 AM, đúng 305s sau Chu kỳ 1)**:
   - OS launchd tự động đánh thức và gọi run thứ 2:
   - `run_id`: `rec_20260907_035108_c3b67f`
   - Started: `03:51:08Z` | Finished: `10:51:14` (+07:00) | Duration: 5.615,07 ms
   - `status`: **`processed`** | `force`: **`false`** (vượt qua cooldown tự nhiên vì $\Delta t \ge 300s$)
   - Cursor: `100` -> `200` (quét tiếp 100 nhóm tiếp theo, 0 lỗi)
4. **Kiểm tra sức khỏe qua Anomaly Monitor**:
   - Chạy `scripts/check_cadence_health.php`:
   - Kết quả: `healthy: true`, tổng 3 runs đã kiểm tra, **0 anomalies**.
5. **Dọn dẹp Daemon an toàn**:
   - Đã gỡ bỏ service qua `launchctl unload` và xóa file plist. Hệ thống không còn bất kỳ tiến trình nền nào chạy ngầm.

---

## 5. Đối Soát Chi Tiết Cấp Trường (Field-Level Audit) & An Toàn Hàng Đợi — PASS ✅

- **Hàng đợi email**:
  - `tblmail_queue` = 36 (trước và sau test: 0 thay đổi).
  - `tblsales_pipeline_reminder_deliveries` = 15.322 (0 thay đổi).
  - `tblsales_pipeline_reminder_delivery_rate_buckets` = 0 (0 thay đổi).
- **Đối soát chi tiết cấp trường (Field-Level Audit)**:
  - Chạy `scripts/phase25_reconcile_id_rollback.php` để hoàn tác các bản ghi bị cập nhật `last_reconciled_at` về snapshot ban đầu.
  - Chạy `scripts/verify_field_level_diff.php` đối chiếu 9.716 bản ghi giữa `tblsales_pipeline_estimate_groups` và `tbl_phase25_snapshot_groups_first_sent`:
    * Chênh lệch `first_sent_at`: **0**
    * Chênh lệch `first_sent_source`: **0**
    * Chênh lệch `first_sent_estimate_id`: **0**
    * Chênh lệch `last_reconciled_at`: **0**
  - **Kết luận**: Đạt 100% IDENTICAL across all 4 fields for all rows!
- **Bảo mật**: Đã loại bỏ 100% tài khoản, mật khẩu DB ghi cứng trong các script snapshot, rollback, cleanup và test. Nạp an toàn từ `application/config/app-config.php`.

---

## 6. Kết luận & Cập nhật Trạng thái Gate

| Hạng mục | Trạng thái | Bằng chứng kỹ thuật |
|---|:---:|---|
| **Phase 2.5: Benchmark PHP-FPM** | **PASS** ✅ | P95 Dashboard 305ms, Leaderboard 277ms, Drawer 177ms (150 mẫu, 0 lỗi). |
| **Phase 2.5: Basecode Cursor & Advisory Lock** | **PASS** ✅ | Persistent checkpoint cursor trong `tbloptions`, advisory lock, rollback an toàn, unit tests 7/7 pass. |
| **Phase 2.5: Reconcile Runner & Health Monitor** | **PASS** ✅ | Runner độc lập, flock an toàn không unlink, monitor bắt 5/5 kịch bản lỗi. |
| **Phase 2.5: OS Scheduler & Real Cadence Cooldown** | **PASS** ✅ | macOS `launchd` kích hoạt 2 chu kỳ thật (10:46:03 AM & 10:51:08 AM); xác minh cooldown 300s tự nhiên (không dùng `--force`). |
| **Đánh giá chung Phase 2.5** | **FULL PASS** ✅ | **ĐÃ ĐỦ CĂN CỨ ĐÓNG HOÀN TOÀN PHASE 2.5**. Đã dọn dẹp daemon sạch sẽ. |
| **Phase 4: Targeted Dispatch & Pilot** | **Conditional Pass** ⚠️ | Giữ nguyên theo quyết định của user; chờ mailbox external và cấu hình gửi an toàn trước khi pilot mới. |
| **Phase 6: Tải đồng thời đa nhân sự** | **OPEN** ⏳ | Chưa chạy bài test tải đa nhân viên Sales độc lập. |
| **Gatekeeper** | **HOLD** ⛔ | Tiếp tục giữ chặn nghiêm ngặt cho đến khi đóng Phase 4 và Phase 6. |
| **Phase 7** | **HOLD** ⛔ | Giữ an toàn, chưa rollout Production. Phải hoàn tất Client Mailbox Acceptance cho IMAP MDaemon trước khi GO. |

## Phase 7 — Client Mailbox Acceptance bắt buộc trước GO Production

### Mục tiêu

Kiểm chứng đầy đủ chuỗi giao vận:

`Sales Pipeline Reminder Engine → SMTP MDaemon → mailbox nhân viên → IMAP → Outlook/Mobile Mail`

Bằng chứng SMTP server đã nhận thư hoặc Webmail MDaemon nhìn thấy thư **chưa đủ**
để xác nhận ứng dụng Outlook của nhân viên đã đồng bộ. Phase 7 chỉ được chuyển
sang GO khi cả hai lớp server và client đều đạt.

### Điều kiện cấu hình IMAP MDaemon

Quản trị viên email phải cung cấp hostname chính thức có certificate hợp lệ và
cổng được nhà cung cấp xác nhận. Không tự suy ra hostname/cổng từ một lần thử
SMTP trước đó.

| Thành phần | Giá trị cần cấu hình |
|---|---|
| Loại tài khoản | IMAP, không chọn Microsoft 365/Exchange |
| Username | Địa chỉ email đầy đủ của nhân viên, ví dụ `user@innotel.com.vn` |
| Incoming | Hostname IMAP chính thức của MDaemon |
| IMAP bảo mật | Ưu tiên port 993 với SSL/TLS; chỉ dùng 143 với STARTTLS nếu nhà cung cấp xác nhận |
| Outgoing | Hostname SMTP chính thức của MDaemon |
| SMTP bảo mật | Port 465 SSL/TLS hoặc 587 STARTTLS theo tài liệu nhà cung cấp |
| Authentication | Dùng cùng tài khoản/mật khẩu mailbox; bật SMTP authentication |

Nếu certificate chỉ chứa một hostname khác, ví dụ hostname dùng chung của nhà
cung cấp, không hướng dẫn nhân viên bấm “Always Trust” certificate mismatch.
Phải dùng hostname khớp certificate hoặc yêu cầu nhà cung cấp cài certificate
có `mail.innotel.com.vn` trong SAN. Việc bỏ qua cảnh báo certificate không phải
là nghiệm thu Production.

### Quy trình Client Mailbox Acceptance

Thực hiện tuần tự trên ít nhất ba tài khoản đại diện: hai nhân viên Sales và một
quản lý. Nếu Production có nhiều kiểu thiết bị, phải có tối thiểu một Outlook
Desktop và một Mobile Mail/Outlook Mobile trong mẫu nghiệm thu.

1. Đối chiếu staff profile trong CRM: tài khoản Active, email chính xác và được
   phân công vào Deal/Quote có reminder fixture.
2. Thêm tài khoản vào Outlook dưới dạng IMAP bằng hostname/cổng do quản trị viên
   email xác nhận. Kiểm tra trạng thái client là Connected và folder Inbox/Junk
   đã được đồng bộ/subscribed.
3. Tạo một fixture reminder có mã duy nhất cho từng người nhận. Không dùng lại
   fixture cũ và không gửi tới danh sách thật ngoài cohort nghiệm thu.
4. Kích hoạt dispatch theo đúng luồng được Phase 4 phê duyệt; không dùng test
   harness bypass queue để thay thế kiểm thử Production path.
5. Ghi nhận bằng chứng theo thứ tự:
   - delivery của module đạt trạng thái phù hợp với đường chạy đã chọn;
   - MDaemon/Webmail nhận được thư;
   - Outlook Desktop/Mobile nhận được cùng Message-ID hoặc tiêu đề/mã fixture;
   - thư nằm trong Inbox, không phải Junk/Other;
   - popup/âm thanh thông báo hoạt động nếu đó là yêu cầu vận hành;
   - thời gian từ lúc dispatch đến lúc Outlook nhận nằm trong SLA đã thống nhất.
6. Thực hiện một bài kiểm tra đồng bộ ngược: gửi một thư kiểm tra từ mailbox
   hoặc Webmail và xác nhận Outlook thấy thư mới, nhằm phân biệt lỗi tài khoản,
   folder hoặc client sync với lỗi SMTP outbound.
7. Nếu client không nhận, giữ nguyên trạng thái HOLD; thu thập trạng thái
   Connected/Disconnected, thời điểm sync cuối, folder, lỗi certificate và log
   client. Không đánh dấu PASS chỉ vì Webmail đã nhận thư.

### Tiêu chí PASS/FAIL của Phase 7

Phase 7 chỉ được GO khi toàn bộ các điều kiện sau có bằng chứng và người phụ
trách xác nhận:

- Mỗi tài khoản trong cohort có email CRM khớp mailbox và được phân công đúng.
- Mỗi client dùng IMAP MDaemon, không tự động trỏ sang Microsoft 365/Exchange.
- Certificate/hostname hợp lệ, không có cảnh báo mismatch bị bỏ qua.
- Mailbox MDaemon nhận được reminder.
- Outlook Desktop/Mobile nhận được reminder trong SLA và hiển thị đúng Inbox.
- Có bằng chứng cho cả nhân viên Sales và quản lý.
- Không phát sinh duplicate, không gửi BCC ngoài danh sách, không có lỗi SMTP/
  circuit breaker/rate bucket bất thường.
- Có monitoring sau rollout và rollback trong thời gian đã cam kết. Nếu Bell
  flag là global, phải ghi rõ thời điểm bật/tắt và người chịu trách nhiệm; không
  tuyên bố pilot cohort nếu Basecode chưa hỗ trợ targeting theo staff.

Kết quả phải được ghi thành bảng `account → client/device → fixture → server
received_at → client_received_at → status → evidence`. Thiếu bất kỳ dòng nào
thì Phase 7 vẫn HOLD và không phát hành ZIP Production.
