# BÁO CÁO RÀ SOÁT CHUYÊN SÂU TOÀN DIỆN VỀ KIẾN TRÚC VÀ BẢO TRÌ
## MODULE SALES PIPELINE — PERFEX CRM (PORTAL_18)
**Ngày lập**: 07/09/2026  
**Trạng thái**: Hoàn tất rà soát chuyên sâu  
**Phạm vi kiểm toán**: `modules/sales_pipeline/` (Toàn bộ Controller, Model, Library, View, Asset, Migration, Language) và các điểm tích hợp Core Perfex CRM (`Emails_model.php`, `App_Email.php`, `Cron_model.php`).

---

## MỤC LỤC
1. [A. Executive Summary](#a-executive-summary)
2. [B. Danh Sách 14 Findings Chi Tiết](#b-danh-sách-14-findings-chi-tiết)
3. [C. Ma Trận Kiến Trúc Hiện Tại & Đề Xuất](#c-ma-trận-kiến-trúc-hiện-tại--đề-xuất)
4. [D. Danh Sách Zombie / Dead Code Candidates](#d-danh-sách-zombie--dead-code-candidates)
5. [E. Báo Cáo Localization & Đối Soát Ngôn Ngữ](#e-báo-cáo-localization--đối-soát-ngôn-ngữ)
6. [F. Danh Sách Tối Ưu Theo Thứ Tự Ưu Tiên](#f-danh-sách-tối-ưu-theo-thứ-tự-ưu-tiên)
7. [G. Kế Hoạch Refactor An Toàn (Safe Refactoring Plan)](#g-kế-hoạch-refactor-an-toàn-safe-refactoring-plan)

---

## A. EXECUTIVE SUMMARY

### 1. Tổng quan tình trạng module
Module Sales Pipeline đã hoàn thành đầy đủ 7 Phase kiểm thử thực địa nghiêm ngặt (từ xử lý đa phiên bản Báo giá, đồng bộ tài chính, concurrency locking, benchmark chịu tải, cơ chế CRM Bell, cho đến giao vận Email Reminder thực tế qua SMTP server doanh nghiệp).
- **Cú pháp toàn module**: 100% PHP Valid (`php -l`), 0 Syntax Errors trên toàn bộ 31 files.
- **Tính toàn vẹn ngôn ngữ**: 697/697 keys khớp 1:1 tuyệt đối giữa tiếng Việt và tiếng Anh.
- **Nghiệp vụ cốt lõi**: Hoạt động chính xác, đảm bảo tính bất biến dữ liệu (Invariants), không làm sai lệch số liệu doanh số hay thất thoát Báo giá.

### 2. Thống kê phát hiện theo mức độ nghiêm trọng
| Mức độ (Severity) | Số lượng | Đánh giá tác động |
|---|:---:|---|
| **P0 — Critical** | **0** | Không phát hiện lỗ hổng RCE, SQLi nguy cấp, crash hệ thống hay vỡ luồng dữ liệu. |
| **P1 — High** | **2** | Rủi ro hiệu năng cao tải (DDL check ở `app_init`, N+1 loop trong Cron Reconcile). |
| **P2 — Medium** | **6** | Vi phạm kiến trúc MVC, CDN ApexCharts thiếu offline fallback, N+1 query đếm Deal, V2 score logic mồ côi. |
| **P3 — Low** | **5** | Chuỗi hardcode tiếng Việt trong file JS, dead private methods, Cost Price Alert subsystem chưa dùng. |
| **Không tái hiện trên Production path** | **1** | FIND-03 dựa trên giả định dispatcher chạy qua HTTP; Basecode hiện chỉ gọi qua `after_cron_run`. |
| **Tổng cộng** | **14** | |

### 3. Kết luận điều kiện Go-Live
**QUYẾT ĐỊNH CHÍNH THỨC: `APPROVE WITH REQUIRED FIXES`**
- **Đạt điều kiện vận hành**: Có thể đưa lên Production ngay sau khi hoàn thành gói vá chặn P1 (PR #1).
- **Mục chặn bắt buộc trước khi mở rộng quy mô toàn công ty**:
  1. Loại bỏ kiểm tra DDL schema ở runtime HTTP request trên hook `app_init`.
  2. Giảm batch size mặc định của Cron Reconcile Báo giá từ `1000` xuống `100`.
  3. Giữ invariant dispatch qua cron; không thêm trạng thái `queued` nếu chưa có `core_mail_queue_id` và cơ chế đồng bộ kết quả Core Queue.

---

## B. DANH SÁCH 14 FINDINGS CHI TIẾT

---

### [FIND-01] [P1 - High] DDL Schema Bootstrap Chạy Trên Mọi HTTP Request Admin
- **Nhóm vấn đề**: Hiệu năng & Vòng lặp / Kiến trúc Bootstrap
- **File & Dòng code**: [`modules/sales_pipeline/sales_pipeline.php:283-392`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/sales_pipeline.php#L283-L392)
- **Bằng chứng / Call Path**:
  ```
  HTTP GET /admin/* ➔ hook 'app_init' ➔ sales_pipeline_estimate_group_schema_bootstrap() & sales_pipeline_reminder_repository_schema_bootstrap()
  ```
- **Hành vi hiện tại**:
  Tại mỗi request truy cập giao diện quản trị Admin, hook `app_init` gọi trực tiếp hai hàm bootstrap schema. Hai hàm này liên tục phát lệnh `SHOW TABLES`, `SHOW COLUMNS FROM ...`, `SHOW INDEX FROM ...`, và tiềm ẩn gọi `ALTER TABLE` trực tiếp trên Database nếu thiếu cột.
- **Rủi ro Production**:
  Gây ra độ trễ overhead 15ms – 45ms cho **mọi request HTTP** của nhân viên, làm nghẽn Database Connection Pool khi có hàng chục nhân viên truy cập đồng thời. Metadata Lock có thể gây nghẽn toàn bảng nếu có thao tác ghi cùng lúc.
- **Cách tái hiện / Xác minh**:
  Bật MySQL General Log hoặc CodeIgniter Profiler (`$this->output->enable_profiler(TRUE)`), tải một trang admin bất kỳ, danh sách queries hiển thị lặp lại các câu `SHOW COLUMNS` liên tục.
- **Hướng khắc phục tối thiểu**:
  Bọc kiểm tra schema qua flag cache (`get_option('sales_pipeline_schema_bootstrapped')`) hoặc chuyển toàn bộ logic DDL vào migration file chuẩn (đã có từ migration 101–114) và gỡ bỏ hoàn toàn việc tự động kiểm tra DDL ở runtime `app_init`.
- **Phạm vi ảnh hưởng**: Toàn bộ Admin HTTP requests.
- **Test bắt buộc**: Kiểm tra `CI_Profiler` trên admin dashboard, xác nhận không còn query `SHOW COLUMNS`.
- **Mức tin cậy**: High (Chứng minh từ Basecode).
- **Phân loại**: Technical debt / Architectural violation.

---

### [FIND-02] [P1 - High] Vòng Lặp N+1 Query và Quét Không Tối Ưu Trong Cron Reconcile Báo Giá
- **Nhóm vấn đề**: Hiệu năng và vòng lặp
- **File & Dòng code**: [`modules/sales_pipeline/models/Sales_pipeline_model.php:2004-2068`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/models/Sales_pipeline_model.php#L2004-L2068)
- **Bằng chứng / Call Path**:
  ```
  Cron job tick ➔ Sales_pipeline_model::reconcile_estimate_groups($limit = 1000) ➔ foreach ($ids as $row) { $this->db->get(); reconcile_group_version_snapshots(); sync_estimate_group(); }
  ```
- **Hành vi hiện tại**:
  Hàm lấy danh sách lên tới 1.000 IDs của estimate groups cần đối soát. Trong vòng lặp `foreach`, nó thực hiện:
  1. Query lại group: `$this->db->where('id', $group_id)->get(...)`
  2. Query snapshots: `reconcile_group_version_snapshots()`
  3. Query recalculate: `sync_estimate_group()`
  4. Query update: `$this->db->where('id', $group_id)->update(...)`
  Tổng số query phát sinh trong một lượt chạy cron có thể lên đến **4.000 – 8.000 queries SQL**.
- **Rủi ro Production**:
  Khi số lượng Báo giá tích lũy qua các tháng tăng lên, cron job chạy định kỳ 5–10 phút sẽ chiếm dụng CPU Database, làm tăng Disk I/O và có nguy cơ chạm `max_execution_time` (PHP timeout) hoặc gây lock record khiến nhân viên không lưu được Báo giá mới.
- **Cách tái hiện / Xác minh**:
  Tạo fixture 500 groups, gọi `Sales_pipeline_model::reconcile_estimate_groups(500)` và đo đếm số queries qua `$this->db->queries`.
- **Hướng khắc phục tối thiểu**:
  - Giảm default chunk size từ `1000` xuống `50` hoặc `100` bản ghi mỗi chu kỳ cron.
  - Sử dụng eager loading nạp trước các estimate groups và items liên quan theo batch ID (`WHERE id IN (...)`).
  - Thêm persistent cursor hoặc điều kiện `reconcile_priority` để chỉ xử lý các group có thay đổi gần nhất.
- **Phạm vi ảnh hưởng**: Cron daemon background execution.
- **Test bắt buộc**: Chạy kịch bản cron cadence benchmark đo thời gian thực thi dưới 2 giây.
- **Mức tin cậy**: High (Chứng minh từ Basecode).
- **Phân loại**: Confirmed performance defect.

---

### [FIND-03] [NOT REPRODUCIBLE ON PRODUCTION PATH] Giả định lệch pha `sent` / Core Mail Queue
- **Nhóm vấn đề**: Kiến trúc Reminder Engine & Giao vận Email
- **File & Dòng code**: [`modules/sales_pipeline/libraries/Reminder_engine.php:683-690`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/libraries/Reminder_engine.php#L683-L690)
- **Bằng chứng / Call Path**:
  ```
  Reminder_engine::dispatchDeliveryRows() ➔ Reminder_engine::sendEmailDelivery() ➔ $this->CI->emails_model->send_simple_email() ➔ App_Email::send()
  ```
- **Kết quả đối chiếu Basecode**:
  `Reminder_engine::process()` chỉ được gọi từ hook `after_cron_run`. Trong ngữ cảnh này Core `App_Email::send()` có điều kiện `(defined('CRON') && !is_staff_logged_in())` và gọi SMTP trực tiếp, kể cả khi `email_queue_enabled = 1`. Không tìm thấy production HTTP route nào gọi `dispatchPendingDeliveries()`.
- **Kết luận**:
  Không tái hiện được sai lệch `sent`/`queued` trên call path Production hiện tại. Việc chỉ đổi chuỗi trạng thái thành `queued` sẽ tạo bản ghi không bao giờ được hoàn tất vì schema chưa có `core_mail_queue_id`, Core không trả queue ID từ `send_simple_email()`, và module chưa có callback/reconciler chuyển `queued → sent/failed`.
- **Guardrail**:
  Giữ dispatcher ở cron/direct-SMTP và có regression contract. Nếu tương lai mở HTTP dispatch hoặc chủ động tích hợp Core Queue, phải triển khai đầy đủ migration, queue correlation ID, state machine và synchronizer; không được vá bằng đổi trạng thái đơn lẻ.
- **Mức tin cậy**: High (đối chiếu `sales_pipeline.php`, `Cron_model.php`, `App_Email.php`).
- **Phân loại**: False positive cho Production path hiện tại / future architectural consideration.

---

### [FIND-04] [P2 - Medium] N+1 Query Trong Quy Tắc Kiểm Tra Hạn Mức Deal Tối Thiểu Của Sales
- **Nhóm vấn đề**: Hiệu năng và vòng lặp
- **File & Dòng code**: [`modules/sales_pipeline/libraries/Reminder_engine.php:60-82`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/libraries/Reminder_engine.php#L60-L82)
- **Bằng chứng / Call Path**:
  ```
  Reminder_engine::processDealPipelineMinimum() ➔ foreach ($staffRows as $staff) { $this->CI->db->select('COUNT(*)')->where('staff_id', $staffId)->get(); }
  ```
- **Hành vi hiện tại**:
  Trong rule `deal_pipeline_minimum`, hàm lấy toàn bộ nhân viên active không phải admin, sau đó duyệt từng `$staffId` để chạy một câu query đếm số deals mở:
  `$this->CI->db->select('COUNT(*) as total')->from(...)->where('staff_id', $staffId)->...->get()->row();`
- **Rủi ro Production**:
  Nếu công ty có 100 Sales, rule này sẽ bắn ra 100 queries liên tiếp lên bảng `tblsales_pipeline_deals`.
- **Cách tái hiện / Xác minh**: Đọc mã nguồn tại dòng 60–82.
- **Hướng khắc phục tối thiểu**:
  Thay thế 100 câu query con bằng một câu query duy nhất sử dụng `GROUP BY staff_id`:
  ```sql
  SELECT staff_id, COUNT(*) AS total FROM tblsales_pipeline_deals WHERE is_won = 0 AND is_lost = 0 GROUP BY staff_id;
  ```
  Sau đó map kết quả vào mảng PHP `[staff_id => total]`.
- **Phạm vi ảnh hưởng**: Cron nhắc nhở rule `deal_pipeline_minimum`.
- **Test bắt buộc**: Chạy `Reminder_engine_test.php` với 50 nhân viên giả lập.
- **Mức tin cậy**: High.
- **Phân loại**: Confirmed performance defect.

---

### [FIND-05] [P2 - Medium] Chèn Từng Dòng Riêng Lẻ Trong Quá Trình Vật Lý Hóa (Materialize) Delivery
- **Nhóm vấn đề**: Hiệu năng và vòng lặp
- **File & Dòng code**: [`modules/sales_pipeline/libraries/Reminder_engine.php:405-422`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/libraries/Reminder_engine.php#L405-L422)
- **Bằng chứng / Call Path**:
  ```
  Reminder_engine::materializeDeliveries() ➔ foreach ($recipients as $recipientId) { foreach ($channels as $channel) { $this->CI->db->query("INSERT IGNORE INTO tblsales_pipeline_reminder_deliveries ...") } }
  ```
- **Hành vi hiện tại**:
  Vòng lặp kép lồng nhau giữa số người nhận và danh sách kênh thông báo (email, crm_bell). Mỗi cặp nhân viên–kênh phát sinh một câu query `INSERT IGNORE` riêng lẻ qua `$this->CI->db->query(...)`.
- **Rủi ro Production**:
  Khi phát hiện 50 nhắc nhở vi phạm, mỗi nhắc nhở gửi cho Sales và CC cho Manager qua 2 kênh, hệ thống sẽ thực hiện `50 * 2 * 2 = 200` câu `INSERT IGNORE` rời rạc.
- **Cách tái hiện / Xác minh**: Đọc mã nguồn `materializeDeliveries()`.
- **Hướng khắc phục tối thiểu**:
  Gom mảng dữ liệu và thực hiện một lệnh chèn duy nhất bằng `INSERT IGNORE INTO ... VALUES (...), (...), (...)` hoặc `$this->CI->db->insert_batch()`.
- **Phạm vi ảnh hưởng**: Giai đoạn Rule Materialization trong cron reminder.
- **Test bắt buộc**: Chạy test sinh nhắc nhở hàng loạt và kiểm tra query log.
- **Mức tin cậy**: High.
- **Phân loại**: Performance optimization.

---

### [FIND-06] [P2 - Medium] Truy Vấn Thông Tin Staff Lặp Đi Lặp Lại Trong Vòng Lặp Dispatch
- **Nhóm vấn đề**: Hiệu năng / Caching
- **File & Dòng code**: [`modules/sales_pipeline/libraries/Reminder_engine.php:578-640`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/libraries/Reminder_engine.php#L578-L640)
- **Bằng chứng / Call Path**:
  ```
  Reminder_engine::dispatchDeliveryRows() ➔ foreach ($rows as $row) { $this->CI->db->get_where(db_prefix() . 'staff', ['staffid' => $row['recipient_staff_id']]) }
  ```
- **Hành vi hiện tại**:
  Khi duyệt qua danh sách delivery để gửi thư, hệ thống liên tục truy vấn bảng `tblstaff` để lấy email, họ tên của người nhận (`recipient_staff_id`) và người quản lý (`team_leader_id`) mà không có cơ chế memoize (in-memory cache).
- **Rủi ro Production**:
  Nếu 100 thông báo cùng gửi cho 5 nhân viên kinh doanh trong ngày, bảng `tblstaff` bị query tới 100–200 lần cho cùng một tập ID nhân viên cố định.
- **Cách tái hiện / Xác minh**: Đọc mã nguồn tại dòng 578–640.
- **Hướng khắc phục tối thiểu**:
  Khai báo mảng cục bộ `private $staffCache = [];` trong `Reminder_engine`. Trước khi query `tblstaff`, kiểm tra xem `$staffCache[$staffId]` đã có hay chưa.
- **Phạm vi ảnh hưởng**: Dispatcher vòng lặp cron.
- **Test bắt buộc**: Dispatch 20 deliveries cho cùng 1 staff, xác nhận chỉ có 1 query `tblstaff`.
- **Mức tin cậy**: High.
- **Phân loại**: Performance optimization.

---

### [FIND-07] [P2 - Medium] Vi Phạm Mô Hình MVC: Controller Trực Tiếp Thực Thi Query Update DB và Quản Lý Transaction
- **Nhóm vấn đề**: Vi phạm MVC & Separation of Concerns
- **File & Dòng code**:
  - [`modules/sales_pipeline/controllers/Sales_pipeline.php:415`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/controllers/Sales_pipeline.php#L415) (`set_deal_manual_lock`)
  - [`modules/sales_pipeline/controllers/Sales_pipeline.php:1469, 1498`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/controllers/Sales_pipeline.php#L1469) (`settings` POST handler)
  - [`modules/sales_pipeline/controllers/Sales_pipeline.php:1830`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/controllers/Sales_pipeline.php#L1830) (`set_finance_lock`)
- **Bằng chứng / Call Path**:
  ```php
  // Dòng 415:
  $this->db->where('id', $deal_id)->update($pipeline_table, ['is_manual_locked' => $manual_lock, ...]);
  // Dòng 1469 & 1498:
  $this->db->trans_begin();
  ...
  $this->db->trans_commit();
  ```
- **Hành vi hiện tại**:
  Controller trực tiếp gọi `$this->db->update()`, truy vấn trực tiếp bảng dữ liệu và mở `trans_begin()` / `trans_commit()` thay vì đóng gói trong Model hoặc Service layer.
- **Rủi ro Production**:
  Khó viết Unit Test cô lập (Unit tests bắt buộc phải nạp cả database driver hoặc mock Controller). Logic lock và lưu settings bị phân tán, nếu sau này có API hoặc CLI command cần lock deal thì phải copy lại logic từ Controller.
- **Cách tái hiện / Xác minh**: Đọc mã nguồn các dòng tương ứng trong Controller.
- **Hướng khắc phục tối thiểu**:
  Chuyển các thao tác `$this->db->update()` vào các method tương ứng của `Sales_pipeline_model`:
  - `$this->sales_pipeline_model->set_deal_lock($dealId, $state);`
  - `$this->sales_pipeline_model->save_pipeline_settings($postData);`
- **Phạm vi ảnh hưởng**: Controller `Sales_pipeline.php`.
- **Test bắt buộc**: Chạy kiểm thử endpoint `set_deal_manual_lock` và `set_finance_lock`.
- **Mức tin cậy**: High.
- **Phân loại**: Maintainability / Code quality debt.

---

### [FIND-08] [P2 - Medium] Phụ Thuộc External CDN Script (ApexCharts) Không Có SRI Hash và Thiếu Fallback
- **Nhóm vấn đề**: Bảo mật / Hạ tầng Kỹ thuật
- **File & Dòng code**: [`modules/sales_pipeline/views/dashboard.php:211`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/views/dashboard.php#L211)
- **Bằng chứng / Call Path**:
  ```html
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
  ```
- **Hành vi hiện tại**:
  Thẻ script nhúng trực tiếp ApexCharts từ jsDelivr public CDN mà không có thuộc tính `integrity` (Subresource Integrity - SRI) và không có file fallback nội bộ.
- **Rủi ro Production**:
  1. Nếu đường truyền mạng nội bộ doanh nghiệp hạn chế truy cập ra Internet công cộng hoặc CDN jsDelivr gặp sự cố nghẽn, Dashboard Sales Pipeline sẽ vỡ giao diện biểu đồ.
  2. Rủi ro Supply Chain Attack nếu CDN bị thỏa hiệp.
- **Cách tái hiện / Xác minh**: Ngắt kết nối mạng Internet của trình duyệt, truy cập Dashboard ➔ Biểu đồ phân bổ Pipeline không hiển thị.
- **Hướng khắc phục tối thiểu**:
  Tải file `apexcharts.min.js` lưu vào thư mục `modules/sales_pipeline/assets/js/apexcharts.min.js` và nạp thông qua helper nội bộ:
  `<script src="<?= module_dir_url('sales_pipeline', 'assets/js/apexcharts.min.js') ?>"></script>`
- **Phạm vi ảnh hưởng**: Trang Dashboard quản trị Sales Pipeline.
- **Test bắt buộc**: Tải trang Dashboard trong môi trường Offline/Sandbox không có Internet.
- **Mức tin cậy**: High.
- **Phân loại**: Technical debt / Security hardening.

---

### [FIND-09] [P2 - Medium] Kiến Trúc Tính Điểm Hiệu Suất V2 Bị Đứt Đoạn: Chỉ Lấy Phiên Bản Nhưng Gọi Ngược V1
- **Nhóm vấn đề**: Kiến trúc / Dead Code logic
- **File & Dòng code**:
  - [`modules/sales_pipeline/models/Sales_pipeline_model.php:2486-2493`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/models/Sales_pipeline_model.php#L2486-L2493)
  - [`modules/sales_pipeline/libraries/Performance_score_calculator_v2.php`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/libraries/Performance_score_calculator_v2.php)
- **Bằng chứng / Call Path**:
  ```php
  // Sales_pipeline_model.php:2486
  require_once module_libs_path('sales_pipeline', 'Performance_score_dispatcher.php');
  $dispatcher = new Performance_score_dispatcher();
  $formula_version = $dispatcher->get_active_formula_version(); // Chỉ gọi để lấy chuỗi version
  // Dòng 2493 lại khởi tạo V1:
  $calculator = new Performance_score_calculator($weights);
  $score = $calculator->calculate($metrics);
  ```
- **Hành vi hiện tại**:
  Hệ thống có file `Performance_score_calculator_v2.php` rất đồ sộ và hoàn chỉnh (hỗ trợ snapshot, chốt kỳ, tính provisional score, có unit test reflection kiểm tra `hasMethod`), nhưng trong Model runtime thực tế:
  Dispatcher chỉ được dùng để lấy chuỗi `'formula_version'`, còn việc tính toán điểm số thực tế thì dòng 2493 lại khởi tạo trực tiếp `Performance_score_calculator` (bản V1). Toàn bộ các method nâng cao của V2 không bao giờ được gọi ở Production.
- **Rủi ro Production**:
  Tạo ra hiểu lầm cho đội ngũ phát triển rằng hệ thống đang vận hành bộ tính điểm V2 mới nhất với đầy đủ cơ chế audit & snapshot, nhưng runtime thực tế vẫn chỉ chạy thuật toán V1 cũ.
- **Cách tái hiện / Xác minh**: Đặt log trong `Performance_score_calculator_v2::calculate_score()` ➔ Log không bao giờ xuất hiện khi xem Bảng xếp hạng.
- **Hướng khắc phục tối thiểu**:
  Hoàn thiện việc chuyển tiếp tính điểm trong `Performance_score_dispatcher`: Khi `formula_version == 'v2'`, dispatcher phải trả về instance tính điểm V2 thực thi thay vì để Model gọi trực tiếp V1.
- **Phạm vi ảnh hưởng**: Module tính điểm xếp hạng Sales (`get_estimate_performance_ranking`).
- **Test bắt buộc**: Kiểm thử đối soát bảng điểm Leaderboard giữa V1 và V2.
- **Mức tin cậy**: High.
- **Phân loại**: Architectural discrepancy / Technical debt.

---

### [FIND-10] [P3 - Low] Nối Chuỗi Trực Tiếp Biến Số Vào Câu Lệnh Khóa Transaction `FOR UPDATE`
- **Nhóm vấn đề**: Hardcode kỹ thuật / SQL Safety Convention
- **File & Dòng code**: [`modules/sales_pipeline/libraries/Estimate_revision_service.php:870-871`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/libraries/Estimate_revision_service.php#L870-L871)
- **Bằng chứng / Call Path**:
  ```php
  $firstLockId  = (int) $groupLockIds[0];
  $secondLockId = (int) $groupLockIds[1];
  $this->CI->db->query("SELECT id, current_estimate_id FROM `{$groupTable}` WHERE id = {$firstLockId} FOR UPDATE");
  ```
- **Hành vi hiện tại**:
  Biến `$firstLockId` và `$secondLockId` được nối chuỗi trực tiếp (string interpolation) vào câu query SQL `SELECT ... FOR UPDATE` thay vì sử dụng parameter binding `?`.
- **Rủi ro Production**:
  Mặc dù biến đã được ép kiểu tường minh `(int)` nên **không có nguy cơ SQL Injection**, nhưng cách viết này vi phạm quy chuẩn nhất quán (coding standard) của dự án và làm giảm khả năng tận dụng MySQL Query Prepared Statement Cache.
- **Cách tái hiện / Xác minh**: Đọc mã nguồn dòng 870–871.
- **Hướng khắc phục tối thiểu**:
  Chuyển sang dạng binding chuẩn của CodeIgniter:
  `$this->CI->db->query("SELECT id, current_estimate_id FROM `{$groupTable}` WHERE id = ? FOR UPDATE", [$firstLockId]);`
- **Phạm vi ảnh hưởng**: Hàm `merge_groups()` trong `Estimate_revision_service`.
- **Test bắt buộc**: Chạy `Estimate_group_integration_test.php`.
- **Mức tin cậy**: High.
- **Phân loại**: Code style / Maintainability.

---

### [FIND-11] [P3 - Low] Hardcode Nhãn Tiền Tệ và Chú Thích Bằng Tiếng Việt Trong Javascript & Partial Views — RESOLVED
- **Nhóm vấn đề**: Hardcode từ vựng và Localization
- **File & Dòng code**:
  - [`modules/sales_pipeline/assets/js/dashboard.js:142, 148, 153, 168, 172`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/assets/js/dashboard.js#L142-L172)
  - [`modules/sales_pipeline/assets/js/estimate_revision.js:163, 612`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/assets/js/estimate_revision.js#L163)
  - [`modules/sales_pipeline/views/_dashboard_staff_pipeline.php:183, 206`](file:///Users/dieterhoang/Developer/portal_18/views/_dashboard_staff_pipeline.php#L183)
- **Bằng chứng / Call Path**:
  ```javascript
  // dashboard.js:142:
  return val + ' tỷ';
  // dashboard.js:148:
  return val + ' đ';
  // dashboard.js:168:
  name: 'Kỳ này',
  // estimate_revision.js:163:
  placeholder: "Tìm kiếm báo giá..."
  // _dashboard_staff_pipeline.php:183:
  <small class="text-muted">(Chiếm 25% trọng số)</small>
  ```
- **Hành vi hiện tại**:
  Các chuỗi hiển thị đơn vị tiền tệ, tooltip biểu đồ và nhãn trọng số KPI được viết cứng bằng tiếng Việt thay vì lấy từ object localization được truyền từ PHP (`app.lang`).
- **Rủi ro Production**:
  Khi chuyển đổi giao diện hệ thống Perfex sang tiếng Anh, toàn bộ trang CRM hiển thị tiếng Anh nhưng các tooltip của biểu đồ Pipeline vẫn hiển thị `' tỷ'`, `' đ'`, `'Kỳ này'`, làm suy giảm trải nghiệm người dùng quốc tế.
- **Cách tái hiện / Xác minh**: Đổi ngôn ngữ người dùng sang English, xem biểu đồ Dashboard.
- **Hướng khắc phục tối thiểu**:
  Khởi tạo biến ngôn ngữ tại view chính qua đối tượng JavaScript toàn cục, ví dụ:
  `window.spLang = { billion: "<?= _l('sales_pipeline_billion') ?>", ... }` và sử dụng trong file JS.
- **Phạm vi ảnh hưởng**: Giao diện Dashboard và Báo giá Frontend.
- **Test bắt buộc**: Chuyển đổi qua lại giữa tiếng Việt và tiếng Anh trên Dashboard.
- **Mức tin cậy**: High.
- **Phân loại**: Localization defect.
- **Kết quả vá ngày 2026-09-07**:
  - `dashboard.js` lấy locale, đơn vị tiền tệ và nhãn kỳ hiện tại/kỳ trước từ `window.salesPipelineI18n`, được PHP tạo bằng `_l()`.
  - `estimate_revision.js` không còn fallback tiếng Việt hardcoded; placeholder, empty-state và nhãn liên quan được truyền qua `window.salesPipelineEstimateRevisionI18n`.
  - Nhãn trọng số KPI trong `_dashboard_staff_pipeline.php`, tier/gap trong `sales_pipeline_helper.php`, khoảng ngày trong Model, fallback người nhận email và lỗi xử lý AJAX đều dùng language key.
  - Hai catalog English/Vietnamese có cùng bộ key mới; `Localization_hardcode_guard_test.php` bảo vệ chống tái xuất hiện các chuỗi cứng đã phát hiện.
- **Trạng thái nghiệm thu**: `PASS` — kiểm tra tự động và smoke test Dashboard tiếng Việt trên Staging Local đều đạt; không thay đổi cấu trúc HTML/CSS hay luồng nghiệp vụ.

---

### [FIND-12] [P3 - Low] Dead Private Methods Trong `Sales_pipeline_model.php`
- **Nhóm vấn đề**: Zombie / Dead code
- **File & Dòng code**:
  - `Sales_pipeline_model::get_reminder_context()`: dòng 1124–1175
  - `Sales_pipeline_model::create_estimate_group()`: dòng 2631–2690
  - `Sales_pipeline_model::append_estimate_revision()`: dòng 2692–2746
  - `Sales_pipeline_model::insert_estimate_version()`: dòng 2748–2800
  - `Sales_pipeline_model::get_estimate_copy_source_id()`: dòng 1656–1670
- **Bằng chứng / Call Path**:
  Tìm kiếm toàn bộ codebase (`rg "get_reminder_context"`, `rg "create_estimate_group"`) cho thấy các hàm này có phạm vi truy cập `private` và không có bất kỳ dòng code nào trong Model hay Module gọi tới chúng.
- **Hành vi hiện tại**:
  Trước đây các hàm này quản lý việc tạo nhóm báo giá và nạp context nhắc nhở. Khi refactor sang `Estimate_revision_service` và `Reminder_engine`, logic đã được chuyển hẳn sang các thư viện chuyên trách, nhưng code cũ trong Model vẫn được giữ lại nguyên vẹn.
- **Rủi ro Production**:
  Gây phình to kích thước file Model (>2.900 dòng), gây phân tâm và nhầm lẫn cho kỹ sư khi bảo trì hoặc đọc mã nguồn.
- **Cách tái hiện / Xác minh**: Sử dụng công cụ phân tích tĩnh hoặc tìm kiếm toàn dự án, không có call site.
- **Hướng khắc phục tối thiểu**:
  Đánh dấu `@deprecated` ở giai đoạn 1, sau đó tiến hành gỡ bỏ trong kỳ refactor sau Production.
- **Phạm vi ảnh hưởng**: Nội bộ `Sales_pipeline_model.php`.
- **Test bắt buộc**: Chạy toàn bộ test suite để đảm bảo không có test nào dùng Reflection gọi vào các hàm private này.
- **Mức tin cậy**: High.
- **Phân loại**: Dead code / Technical debt.

---

### [FIND-13] [P3 - Low] Phân Hệ Cost Price Alert Bị Mồ Côi Không Có Điểm Kích Hoạt Runtime
- **Nhóm vấn đề**: Zombie / Dead code
- **File & Dòng code**: [`modules/sales_pipeline/models/Sales_pipeline_model.php:756-880`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/models/Sales_pipeline_model.php#L756-L880)
- **Bằng chứng / Call Path**:
  Các method: `create_cost_price_alert()`, `resolve_cost_price_alert()`, `send_cost_price_alert()`, `process_cost_price_alerts()` được định nghĩa công khai trong Model nhưng không có Controller, Cron hay Hook nào gọi tới.
- **Hành vi hiện tại**:
  Hệ thống cảnh báo giá vốn (Cost Price Alert) được thiết kế dở dang hoặc bị bỏ quên. Tại dòng 788, `resolve_cost_price_alert()` thực hiện cập nhật giải quyết alert cho một bản ghi mà chưa từng có luồng nào tạo ra.
- **Rủi ro Production**: Không gây lỗi runtime, nhưng tạo ra các bảng và dữ liệu schema không dùng tới.
- **Cách tái hiện / Xác minh**: Tìm kiếm `process_cost_price_alerts` trong toàn bộ repo, chỉ xuất hiện ở định nghĩa hàm.
- **Hướng khắc phục tối thiểu**:
  Giữ nguyên tạm thời hoặc bổ sung tài liệu đặc tả: đây là tính năng dự kiến cho Phase tương lai (Backlog), tránh xóa vội gây mất mát logic nghiệp vụ đã viết.
- **Phạm vi ảnh hưởng**: `Sales_pipeline_model.php`.
- **Test bắt buộc**: N/A.
- **Mức tin cậy**: High.
- **Phân loại**: Feature in backlog / Unused code.

---

### [FIND-14] [P3 - Low] Thư Viện `Currency_data_sanitizer.php` Thiếu Runtime Entry Point
- **Nhóm vấn đề**: Zombie code / Chưa tích hợp
- **File & Dòng code**: [`modules/sales_pipeline/libraries/Currency_data_sanitizer.php`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/libraries/Currency_data_sanitizer.php)
- **Bằng chứng / Call Path**:
  Thư viện này chỉ được gọi bên trong các file test (`tests/Currency_data_sanitizer_test.php`), không có bất kỳ Controller, Model hay Hook nào khởi tạo hoặc gọi class này trong luồng xử lý thực tế của ứng dụng.
- **Hành vi hiện tại**:
  Được xây dựng như một công cụ chuẩn hóa dữ liệu tiền tệ nhưng chưa được gắn vào pipeline lưu trữ dữ liệu của Deal hoặc Estimate.
- **Rủi ro Production**: Không ảnh hưởng trực tiếp đến dữ liệu hiện hành.
- **Cách tái hiện / Xác minh**: `rg "Currency_data_sanitizer"` chỉ ra kết quả trong file định nghĩa và file test.
- **Hướng khắc phục tối thiểu**: Giữ nguyên tài liệu hóa làm helper dùng chung hoặc gắn vào hook `before_deal_updated` khi có nhu cầu chuẩn hóa dữ liệu cũ.
- **Mức tin cậy**: High.
- **Phân loại**: Maintainability / Standalone component.

---

## C. MA TRẬN KIẾN TRÚC HIỆN TẠI & ĐỀ XUẤT

| Thành phần | Trách nhiệm hiện tại | Vi phạm / Rủi ro phát hiện | Kiến trúc đề xuất |
|---|---|---|---|
| **Module Bootstrap** (`sales_pipeline.php`) | Đăng ký hooks, menu, permissions, asset injection và kiêm luôn việc kiểm tra/chạy DDL schema. | Vi phạm Separation of Concerns: Hook `app_init` chạy DDL (`SHOW TABLES/COLUMNS`) trên mọi request admin. | Giữ bootstrap thuần túy là Service Provider/Registrar. Toàn bộ logic DDL chuyển về `migrations/`. |
| **Controller Chính** (`Sales_pipeline.php`) | Điều phối HTTP request, render View, nhưng trực tiếp chạy query DB update và quản lý transaction. | Vi phạm MVC: Xử lý database trực tiếp (`$this->db->update`) tại các endpoint toggle lock và settings. | Controller chỉ validate đầu vào, ủy quyền toàn bộ nghiệp vụ và lưu trữ dữ liệu cho Model hoặc Service. |
| **Model Trung Tâm** (`Sales_pipeline_model.php`) | Chứa 2.900+ dòng code; xử lý Deals, Settings, Reconcile, Performance Rankings, và chứa nhiều method cũ. | File quá lớn; chứa dead code và N+1 query loop trong Cron Reconcile (xử lý 1.000 groups tuần tự). | Tách nhỏ thành các domain models/services: `Deal_repository`, `Reconcile_service`. Gỡ bỏ private dead methods. |
| **Reminder Engine** (`Reminder_engine.php`) | Đánh giá 6 nhóm quy tắc hạn chót, tạo reminder logs, vật lý hóa deliveries, gửi Email & Bell CRM. | N+1 query đếm deals của nhân viên; thiếu batch insert/cache Staff. Production path hiện gửi trực tiếp qua cron, không enqueue Core Queue. | Tách biệt rõ 3 tầng: `Rule_Evaluator`, `Delivery_Materializer` và `Dispatch_Transport`; chỉ tích hợp Core Queue khi có correlation + state synchronizer đầy đủ. |
| **Estimate Revision** (`Estimate_revision_service.php`) | Quản lý phiên bản báo giá, nhóm báo giá, khóa phân tán giao dịch `FOR UPDATE`. | Câu query `FOR UPDATE` nối chuỗi biến số thay vì dùng parameter binding `?`. | Chuyển toàn bộ query SQL sang prepared statement binding. |
| **Dashboard Assets** (`dashboard.js`, `dashboard.php`) | Hiển thị biểu đồ Pipeline, bộ lọc thời gian và bảng xếp hạng nhân viên. | Phụ thuộc external CDN không có SRI/offline fallback; hardcode chuỗi tiếng Việt trong JavaScript. | Bundle thư viện ApexCharts vào local assets; truyền từ vựng i18n qua data attribute hoặc global JS object. |

---

## D. DANH SÁCH ZOMBIE / DEAD CODE CANDIDATES

| STT | Đối tượng / Candidate | Vị trí định nghĩa | Dynamic Hooks / Call Sites Đã Kiểm Tra | Runtime Entry Point | Khuyến nghị xử lý |
|---|---|---|---|---|---|
| 1 | `Sales_pipeline_model::get_reminder_context` | `Sales_pipeline_model.php:1124` | Đã kiểm tra `$this->get_reminder_context`, hook và dynamic call | **Không có** (Private method) | **Remove after test**: Gỡ bỏ an toàn, đã được thay thế bởi `Reminder_engine`. |
| 2 | `Sales_pipeline_model::create_estimate_group` | `Sales_pipeline_model.php:2631` | Đã kiểm tra toàn bộ controllers và cron callbacks | **Không có** (Private method) | **Remove after test**: Logic đã chuyển sang `Estimate_revision_service`. |
| 3 | `Sales_pipeline_model::append_estimate_revision` | `Sales_pipeline_model.php:2692` | Đã kiểm tra các luồng clone/edit estimate | **Không có** (Private method) | **Remove after test**: Đã chuyển sang `Estimate_revision_service`. |
| 4 | `Sales_pipeline_model::insert_estimate_version` | `Sales_pipeline_model.php:2748` | Chỉ được gọi bởi 2 private methods dead ở trên | **Không có** (Private method) | **Remove after test**: Đi kèm cụm phương thức Báo giá cũ. |
| 5 | `Sales_pipeline_model::get_estimate_copy_source_id` | `Sales_pipeline_model.php:1656` | Kiểm tra hook `after_estimate_copied` | **Không có** (Gọi trực tiếp service context) | **Deprecate**: Thêm `@deprecated` trước khi gỡ hoàn toàn. |
| 6 | Subsystem `Cost_price_alerts` (4 methods) | `Sales_pipeline_model.php:756-880` | Kiểm tra Cron jobs, Hooks và Admin Controllers | **Không có** (Không có caller) | **Keep / Needs Investigation**: Giữ nguyên làm backlog feature, không xóa vội. |
| 7 | `Currency_data_sanitizer.php` | `libraries/Currency_data_sanitizer.php` | Kiểm tra toàn bộ modules và core application | **Chỉ có trong Unit Tests** | **Keep**: Giữ nguyên làm standalone utility phục vụ data migration khi cần. |
| 8 | `Performance_score_calculator_v2.php` | `libraries/Performance_score_calculator_v2.php` | Dispatcher chỉ đọc version string, không gọi execute | **Chỉ có trong Reflection Tests** | **Needs Investigation**: Cần nối lại vào Dispatcher thay vì gọi trực tiếp V1. |

---

## E. BÁO CÁO LOCALIZATION VÀ ĐỐI SOÁT NGÔN NGỮ

### 1. Đối soát file ngôn ngữ hệ thống
- **File tiếng Việt**: [`modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php)
- **File tiếng Anh**: [`modules/sales_pipeline/language/english/sales_pipeline_lang.php`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/language/english/sales_pipeline_lang.php)
- **Kết quả kiểm toán**:
  - Cả 2 file đều có chính xác **697 language keys**.
  - **Tỷ lệ khớp 1:1**: 100% (Không có key nào tồn tại ở file này mà thiếu ở file kia).
  - **Key `sales_pipeline_deal`**: Đã được khai báo đầy đủ và chính xác:
    - Tiếng Việt: `$lang['sales_pipeline_deal'] = 'Cơ hội';`
    - Tiếng Anh: `$lang['sales_pipeline_deal'] = 'Deal';`
  - Email template view `modules/sales_pipeline/views/emails/reminder.php` hiển thị `_l('sales_pipeline_deal')` hoàn toàn hợp lệ, không bị lỗi hiển thị text thô.

### 2. Các chuỗi hardcoded đã chuyển sang i18n

| Vị trí đã vá | Chuỗi trước khi vá | Ngữ cảnh hiển thị | Language Key đã dùng | Bản dịch Tiếng Việt | Bản dịch Tiếng Anh |
|---|---|---|---|---|---|
| `assets/js/dashboard.js:142` | `' tỷ'` | Trục số biểu đồ doanh thu | `sales_pipeline_unit_billion` | `tỷ` | `B` |
| `assets/js/dashboard.js:148` | `' đ'` / `' VNĐ'` | Tooltip biểu đồ tiền tệ | `sales_pipeline_unit_currency` | `đ` | `VND` |
| `assets/js/dashboard.js:168` | `'Kỳ này'` | Legend so sánh kỳ | `sales_pipeline_current_period` | `Kỳ này` | `Current Period` |
| `assets/js/dashboard.js:172` | `'Kỳ trước'` | Legend so sánh kỳ | `sales_pipeline_previous_period`| `Kỳ trước` | `Previous Period` |
| `assets/js/estimate_revision.js:163`| `'Tìm kiếm báo giá...'` | Ô tìm kiếm danh sách báo giá | `sales_pipeline_search_estimates` | `Tìm kiếm báo giá...` | `Search estimates...` |
| `views/_dashboard_staff_pipeline.php:183`| `'(Chiếm 25% trọng số)'` | Nhãn phân bổ KPI Sales | `sales_pipeline_weight_25` | `(Chiếm 25% trọng số)` | `(25% weight)` |
| `views/_dashboard_staff_pipeline.php:206`| `'(Chiếm 15% trọng số)'` | Nhãn phân bổ KPI Sales | `sales_pipeline_weight_15` | `(Chiếm 15% trọng số)` | `(15% weight)` |

---

## F. DANH SÁCH TỐI ƯU THEO THỨ TỰ ƯU TIÊN

### 1. Bắt buộc trước Production (Must-have / Guardrails)
1. **[P1 - FIND-01] Vô hiệu hóa DDL Schema Check ở Runtime**: Thêm cờ kiểm tra hoặc gỡ bỏ `sales_pipeline_estimate_group_schema_bootstrap` và `sales_pipeline_reminder_repository_schema_bootstrap` khỏi hook `app_init` (chỉ chạy khi module kích hoạt hoặc chạy migration).
2. **[Guardrail - FIND-03] Giữ dispatch qua Cron/direct SMTP**: Không thêm `queued` nếu chưa triển khai đầy đủ queue correlation và state synchronizer.
3. **[P2 - FIND-08] Localize Asset ApexCharts**: Lưu trữ `apexcharts.min.js` cục bộ trong thư mục assets để loại bỏ hoàn toàn sự phụ thuộc vào mạng ngoài khi người dùng xem biểu đồ.

### 2. Nên hoàn tất ngay sau Production (Should-have / First Sprint)
1. **[P1 - FIND-02] Tối ưu hóa Cron Reconcile Báo Giá**: Giảm chunk size mặc định xuống 100 và thực hiện eager-loading batch query để loại bỏ N+1 query.
2. **[P2 - FIND-04] Batch Group By trong Rule Engine**: Thay thế vòng lặp đếm Deal từng nhân viên bằng 1 câu query `GROUP BY staff_id`.
3. **[P2 - FIND-05 & FIND-06] Batch Insert & Caching Staff**: Sử dụng `insert_batch` cho delivery materialization và memoize thông tin staff trong vòng lặp gửi email.
4. **[P3 - FIND-11 — RESOLVED] Bản địa hóa chuỗi giao diện**: Dashboard, Estimate Revision, KPI partial và các fallback liên quan đã dùng `_l()` cùng object i18n PHP → JavaScript song ngữ.

### 3. Technical Debt Dài Hạn (Long-term / Backlog)
1. **[P2 - FIND-07] Tái cấu trúc MVC Controller**: Chuyển các câu lệnh cập nhật DB và transaction từ Controller về Model.
2. **[P2 - FIND-09] Hoàn thiện tích hợp Performance Score V2**: Nối kết quả tính toán từ Dispatcher trực tiếp sang bộ tính V2.
3. **[P3 - FIND-12] Dọn dẹp Zombie Code**: Gỡ bỏ an toàn các private dead methods trong `Sales_pipeline_model.php` sau khi kết thúc chu kỳ phát hành v1.1.0.

---

## G. KẾ HOẠCH REFACTOR AN TOÀN (SAFE REFACTORING PLAN)

Toàn bộ các cải tiến cần được tách thành **5 PR độc lập**, có khả năng rollback ngay lập tức mà không ảnh hưởng lẫn nhau:

### PR #1: Hạ Tải Runtime Bootstrap & Localize Third-party CDN
- **Mục tiêu**: Loại bỏ 100% DDL overhead trên mọi request admin và bảo vệ biểu đồ Dashboard khi mất kết nối mạng ngoài.
- **File ảnh hưởng**:
  - `modules/sales_pipeline/sales_pipeline.php`
  - `modules/sales_pipeline/views/dashboard.php`
  - `modules/sales_pipeline/assets/js/apexcharts.min.js` (Thêm mới)
- **Test cần chạy**: Kiểm tra `CI_Profiler` xác nhận 0 câu query DDL `SHOW COLUMNS`; tắt Wi-Fi kiểm tra biểu đồ dashboard hiển thị bình thường.
- **Rủi ro regression**: Rất thấp (Không chạm vào logic nghiệp vụ).
- **Tiêu chí nghiệm thu**: Thời gian tải trang Dashboard giảm 20–30ms; không còn phụ thuộc vào `cdn.jsdelivr.net`.

### PR #2: Guardrail Ngữ Nghĩa Giao Vận Email
- **Mục tiêu**: Khóa invariant Reminder dispatcher chỉ chạy qua cron/direct SMTP; ngăn việc thêm trạng thái `queued` không có lifecycle hoàn chỉnh.
- **File ảnh hưởng**:
  - `modules/sales_pipeline/tests/Production_readiness_hardening_test.php`
- **Test cần chạy**: Xác minh hook `after_cron_run`, nhánh direct SMTP trong `App_Email`, và cấm `queued` khi thiếu queue correlation/reconciler.
- **Rủi ro regression**: Rất thấp.
- **Tiêu chí nghiệm thu**: Regression contract PASS; mọi đề xuất Core Queue tương lai phải có migration + correlation + synchronizer.

### PR #3: Tối Ưu Hóa Hiệu Năng Vòng Lặp Rule Engine & Materialization
- **Mục tiêu**: Xóa bỏ N+1 query đếm deals của nhân viên và chuyển `INSERT IGNORE` đơn lẻ thành batch chèn hàng loạt.
- **File ảnh hưởng**:
  - `modules/sales_pipeline/libraries/Reminder_engine.php`
- **Test cần chạy**: `Reminder_engine_test.php`, kiểm tra memory peak và số lượng queries trong log.
- **Rủi ro regression**: Thấp đến trung bình (Cần đối soát kỹ danh sách nhân viên vi phạm).
- **Tiêu chí nghiệm thu**: Số lượng query phát sinh trong quá trình đánh giá rule giảm >80%.

### PR #4: Phân Trang & Giới Hạn Tải Cho Cron Reconcile Báo Giá
- **Mục tiêu**: Ngăn chặn tình trạng lock bảng hoặc timeout khi số lượng Báo giá tích lũy lớn.
- **File ảnh hưởng**:
  - `modules/sales_pipeline/models/Sales_pipeline_model.php`
- **Test cần chạy**: `Estimate_group_integration_test.php`, `run_reconcile_cadence.php`.
- **Rủi ro regression**: Trung bình (Cần đảm bảo dữ liệu phiên bản Báo giá đồng bộ toàn vẹn).
- **Tiêu chí nghiệm thu**: Cron chạy hoàn tất dưới 1,5 giây cho batch 100 nhóm báo giá.

### PR #5: Bản Địa Hóa Toàn Diện & Dọn Dẹp Dead Code
- **Mục tiêu**: Đưa toàn bộ các nhãn tiền tệ, tooltip JS vào hệ thống đa ngôn ngữ; gỡ bỏ các hàm private không còn dùng trong Model.
- **File ảnh hưởng**:
  - `modules/sales_pipeline/assets/js/dashboard.js`
  - `modules/sales_pipeline/assets/js/estimate_revision.js`
  - `modules/sales_pipeline/models/Sales_pipeline_model.php`
  - `modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php`
  - `modules/sales_pipeline/language/english/sales_pipeline_lang.php`
- **Test cần chạy**: `Performance_tier_and_language_test.php`.
- **Rủi ro regression**: Thấp.
- **Tiêu chí nghiệm thu**: Chuyển đổi giao diện tiếng Anh không còn sót bất kỳ từ tiếng Việt nào trong biểu đồ; test suite pass 100%.
