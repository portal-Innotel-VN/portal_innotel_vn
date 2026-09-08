# KẾ HOẠCH VÀ LỘ TRÌNH PHÁT HÀNH PRODUCTION CHUẨN SENIOR SOFTWARE ENGINEER
## MODULE SALES PIPELINE — PERFEX CRM (PORTAL_18)
**Tài liệu**: Production Release Runbook & Operational Standard Operating Procedure (SOP)  
**Mã tài liệu**: `SOP-PROD-RELEASE-SP-20260907`  
**Ngày ban hành**: 07/09/2026  
**Mục tiêu**: Đảm bảo quá trình đưa Module Sales Pipeline lên môi trường Production diễn ra an toàn tuyệt đối, 0 Downtime không kiểm soát, bảo toàn 100% dữ liệu tài chính/báo giá, và có kịch bản Rollback trong vòng 5 phút nếu xảy ra sự cố.

---

## MỤC LỤC
1. [Nguyên Tắc Cốt Lõi Của Senior Software Engineer](#1-nguyên-tắc-cốt-lõi-của-senior-software-engineer)
2. [Lộ Trình Triển Khai 6 Giai Đoạn](#2-lộ-trình-triển-khai-6-giai-đoạn)
   - [Giai đoạn 1: Pre-flight Audit & Code Freeze (T - 24 Giờ)](#giai-đoạn-1-pre-flight-audit--code-freeze-t---24-giờ)
   - [Giai đoạn 2: Backup, DB Snapshot & Rollback Readiness (T - 2 Giờ)](#giai-đoạn-2-backup-db-snapshot--rollback-readiness-t---2-giờ)
   - [Giai đoạn 3: Deployment & Cutover Execution (T - 0 Giờ: Giờ G)](#giai-đoạn-3-deployment--cutover-execution-t---0-giờ-giờ-g)
   - [Giai đoạn 4: Post-deployment Smoke Test & Canary (T + 15 Phút)](#giai-đoạn-4-post-deployment-smoke-test--canary-t--15-phút)
   - [Giai đoạn 5: Giám Sát Cao Độ & Cadence Health (T + 24 Giờ)](#giai-đoạn-5-giám-sát-cao-độ--cadence-health-t--24-giờ)
   - [Giai đoạn 6: Sign-off, Bàn Giao & Release Notes (T + 48 Giờ)](#giai-đoạn-6-sign-off-bàn-giao--release-notes-t--48-giờ)
3. [Kịch Bản Rollback Khẩn Cấp (Emergency Rollback Runbook)](#3-kịch-bản-rollback-khẩn-cấp-emergency-rollback-runbook)
4. [Checklist Nghiệm Thu Từng Bước (Go/No-Go Checklist)](#4-checklist-nghiệm-thu-từng-bước-gono-go-checklist)

---

## 1. NGUYÊN TẮC CỐT LÕI CỦA SENIOR SOFTWARE ENGINEER

1. **Không triển khai bằng FTP/Zip đè thủ công**: Mọi thay đổi mã nguồn trên Production phải xuất phát từ một **Git Tag định danh duy nhất**, đảm bảo tính truy vết (traceability) và tính tái lặp (reproducibility).
2. **Không chạy Migration khi Cron đang mở**: Mọi thao tác DDL schema trên MySQL phải diễn ra trong cửa sổ tạm dừng Cron để tránh tranh chấp Metadata Lock (`Waiting for table metadata lock`).
3. **Luôn có đường lui (Never deploy without Rollback)**: Một lệnh deploy chỉ được phê duyệt khi đã chuẩn bị sẵn lệnh rollback tương ứng đã được test thành công trên Staging.
4. **Bảo toàn Invariants**: Không làm sai lệch số liệu doanh thu đã chốt, không ghi đè Báo giá đã chấp nhận, và luôn cô lập giao vận email qua SMTP an toàn.

---

## 2. LỘ TRÌNH TRIỂN KHAI 6 GIAI ĐOẠN

```
Timeline: [T-24h] ------------> [T-2h] ------------> [T-0h: Cutover] ------> [T+15m] ---------> [T+24h] ---------> [T+48h]
           Code Freeze           Snapshot & Plan B    Deploy & Migrate        Smoke Test        Observability       Sign-off
```

---

### GIAI ĐOẠN 1: PRE-FLIGHT AUDIT & CODE FREEZE (T - 24 GIỜ)
*Mục tiêu: Đóng băng mã nguồn, đối soát an toàn toàn diện và chuẩn bị bản phát hành.*

- [ ] **Bước 1.1: Chạy toàn bộ Test Suite tự động**:
  Chạy kiểm tra cú pháp PHP và 4 hợp đồng kiểm thử nghiêm ngặt:
  ```bash
  # 1. Kiểm tra cú pháp PHP toàn module
  php -l modules/sales_pipeline/controllers/Sales_pipeline.php
  php -l modules/sales_pipeline/models/Sales_pipeline_model.php
  php -l modules/sales_pipeline/libraries/Performance_score_service.php
  php -l modules/sales_pipeline/libraries/Performance_score_dispatcher.php
  php -l modules/sales_pipeline/libraries/Reminder_engine.php

  # 2. Chạy bộ 4 contract tests
  php modules/sales_pipeline/tests/Mvc_v2_and_standalone_contract_test.php
  php modules/sales_pipeline/tests/Localization_hardcode_guard_test.php
  php modules/sales_pipeline/tests/Production_readiness_hardening_test.php
  php modules/sales_pipeline/tests/Performance_tier_and_language_test.php
  ```
  *Tiêu chuẩn*: 100% PASS, không có bất kỳ warning hay deprecation notice nào.

- [ ] **Bước 1.2: Dọn dẹp thư mục làm việc (Worktree Cleanup)**:
  - Di chuyển hoặc xóa các file test script tạm trong `scripts/` (các file benchmark tải cao, mock smtp không được đưa lên Prod).
  - Xác nhận `git status --short` chỉ còn các file mã nguồn và tài liệu chuẩn.

- [ ] **Bước 1.3: Tạo Git Release Tag chính thức**:
  ```bash
  git tag -a v1.0.0-gold -m "Production Release: Sales Pipeline Module with V2 Score, MVC Refactoring & Hardening"
  git push origin v1.0.0-gold
  ```

- [ ] **Bước 1.4: Lên lịch Cửa sổ bảo trì (Maintenance Window)**:
  - Khung giờ khuyến nghị: **19:00 - 20:00 (Tối thứ Sáu)** hoặc **12:00 - 13:00 (Trưa thứ Bảy)**.
  - Gửi thông báo cho toàn thể nhân sự:
    > *"Hệ thống CRM Innotel sẽ tiến hành nâng cấp phân hệ Sales Pipeline trong 30 phút từ 19:00 đến 19:30. Trong thời gian này, vui lòng tạm dừng thao tác tạo Báo giá mới."*

---

### GIAI ĐOẠN 2: BACKUP, DB SNAPSHOT & ROLLBACK READINESS (T - 2 GIỜ)
*Mục tiêu: Đảm bảo khả năng phục hồi dữ liệu 100% trong vòng 5 phút.*

- [ ] **Bước 2.1: Full Database Dump có transaction lock**:
  ```bash
  mysqldump -u <db_user> -p \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    innotel_portal > /backup/crm_prod_pre_release_$(date +%Y%m%d_%H%M%S).sql
  ```
  Xác minh dung lượng file dump khác 0 (`ls -lh /backup/`).

- [ ] **Bước 2.2: Snapshot nhanh các bảng dữ liệu cốt lõi**:
  Xuất riêng snapshot dữ liệu các bảng chịu ảnh hưởng trực tiếp:
  ```bash
  mysqldump -u <db_user> -p innotel_portal \
    tblestimates \
    tblestimates_groups \
    tblestimates_group_versions \
    tblsales_pipeline \
    tblsales_pipeline_reminders_log \
    tblsales_pipeline_reminder_deliveries \
    tblstaff \
    tbloptions > /backup/core_tables_snapshot_$(date +%Y%m%d).sql
  ```

- [ ] **Bước 2.3: Kiểm tra sẵn sàng kịch bản Rollback**:
  Chuẩn bị sẵn file lệnh rollback để có thể paste ngay vào terminal nếu cần hủy đợt phát hành.

---

### GIAI ĐOẠN 3: DEPLOYMENT & CUTOVER EXECUTION (T - 0 GIỜ: GIỜ G)
*Mục tiêu: Nạp mã nguồn, chạy migration và kích hoạt tính năng an toàn.*

- [ ] **Bước 3.1: Tạm dừng Cron CRM (Crucial Step)**:
  Mở crontab trên máy chủ:
  ```bash
  crontab -e
  ```
  Thêm dấu `#` trước dòng chạy cron của Perfex:
  ```bash
  # * * * * * php /var/www/portal_18/index.php cron/index > /dev/null 2>&1
  ```
  *Mục đích*: Ngăn chặn cron chạy đồng thời khi đang cập nhật file và chạy migration.

- [ ] **Bước 3.2: Kéo mã nguồn qua Git Tag**:
  Tại thư mục gốc của dự án trên Production:
  ```bash
  cd /var/www/portal_18
  git fetch --tags
  git checkout tags/v1.0.0-gold
  ```

- [ ] **Bước 3.3: Phân quyền tệp tin (File Permissions)**:
  Đảm bảo webserver (Nginx/Apache) có đủ quyền đọc code và ghi vào thư mục cache/uploads:
  ```bash
  chown -R www-data:www-data modules/sales_pipeline/
  chmod -R 755 modules/sales_pipeline/
  ```

- [ ] **Bước 3.4: Chạy Migration Schema (101 ➔ 114)**:
  - Đăng nhập tài khoản Super Admin truy cập: `https://crm.innotel.com.vn/admin/modules`.
  - Nếu module chưa kích hoạt: Bấm **Activate**.
  - Nếu module đã kích hoạt từ trước: Tải lại trang để trigger runner `App_module_migration`.
  - Kiểm tra bảng `tblmigrations` trong MySQL xác nhận:
    ```sql
    SELECT * FROM tblmigrations WHERE module_name = 'sales_pipeline';
    -- Phiên bản phải đạt: 114
    ```

- [ ] **Bước 3.5: Xóa Cache Ứng dụng & Trình duyệt (Cache Busting)**:
  - Xóa cache CodeIgniter:
    ```bash
    rm -rf /var/www/portal_18/application/cache/*
    ```
  - Kiểm tra các file assets: `reminder_bell.css`, `dashboard.js`, `apexcharts.min.js` đã sẵn sàng phục vụ từ local.

- [ ] **Bước 3.6: Mở lại Cron CRM**:
  Mở lại crontab và gỡ bỏ dấu `#`:
  ```bash
  crontab -e
  # Khôi phục: * * * * * php /var/www/portal_18/index.php cron/index > /dev/null 2>&1
  ```

---

### GIAI ĐOẠN 4: POST-DEPLOYMENT SMOKE TEST & CANARY (T + 15 PHÚT)
*Mục tiêu: Đội ngũ kỹ thuật trực tiếp kiểm thử thực địa trên Production trước khi mở cho toàn thể nhân viên.*

Thực hiện theo ma trận 5 bài kiểm tra nghiệm thu:

| STT | Luồng kiểm thử | Thao tác thực hiện | Kết quả mong đợi (Pass Criteria) | Trạng thái |
|:---:|---|---|---|:---:|
| **1** | **Dashboard & Local Assets** | Đăng nhập Admin ➔ Mở menu `Sales Pipeline` | Biểu đồ nạp từ `assets/js/apexcharts.min.js`, không có request ra CDN ngoài, bảng xếp hạng V2 hiển thị đầy đủ cột. | [ ] |
| **2** | **Quản Lý Phiên Bản Báo Giá** | Mở Báo giá bất kỳ ➔ Bấm `Tạo phiên bản điều chỉnh` | Drawer mở mượt mà, tạo thành công Version mới, dữ liệu tiền tệ chính xác. | [ ] |
| **3** | **Khóa Tài Chính & Phân Quyền** | Chọn 1 Deal thử nghiệm ➔ Bấm `Khóa tài chính` | Nhập lý do khóa ➔ Dữ liệu lưu đúng bảng `tblsales_pipeline` kèm `finance_lock_reason`. | [ ] |
| **4** | **Hộp Thư Chuông CRM Bell** | Gửi 1 thông báo nhắc nhở tới tài khoản Admin | Chuông CRM rung, badge đỏ hiển thị số lượng, bấm mở xem và phản hồi được ngay. | [ ] |
| **5** | **Canary Giao Vận Email** | Chạy 1 chu kỳ cron kiểm thử | Email reminder gửi thành công tới hộp thư thật Outlook của 1 tài khoản nội bộ (`hiephh@innotel.com.vn`), không đọng thư trong `tblmail_queue`. | [ ] |

---

### GIAI ĐOẠN 5: GIÁM SÁT CAO ĐỘ & CADENCE HEALTH (T + 24 GIỜ)
*Mục tiêu: Theo dõi sát sao tải hệ thống và tính toàn vẹn trong ngày làm việc đầu tiên.*

- [ ] **Bước 5.1: Theo dõi Web Server Error Logs**:
  ```bash
  tail -f /var/log/nginx/error.log | grep -E "sales_pipeline|PHP Fatal|PHP Parse|Database Error"
  ```
  *Tiêu chuẩn*: 0 Fatal Errors.

- [ ] **Bước 5.2: Kiểm tra MySQL Slow Query Log**:
  Giám sát thời gian thực thi của Cron Reconcile Báo giá (batch size 100).
  ```sql
  SELECT * FROM information_schema.processlist WHERE command != 'Sleep' AND time > 2;
  ```
  *Tiêu chuẩn*: Không có query nào bị nghẽn (`locked`) quá 2 giây.

- [ ] **Bước 5.3: Giám sát Tỷ lệ Giao vận Email**:
  Chạy câu truy vấn kiểm tra tỷ lệ gửi thành công trong ngày:
  ```sql
  SELECT status, COUNT(*) AS total 
  FROM tblsales_pipeline_reminder_deliveries 
  WHERE created_at >= CURDATE() 
  GROUP BY status;
  ```
  *Tiêu chuẩn*: Tỷ lệ `sent` đạt > 98%, `failed` = 0%.

---

### GIAI ĐOẠN 6: SIGN-OFF, BÀN GIAO & RELEASE NOTES (T + 48 GIỜ)
*Mục tiêu: Đóng đợt phát hành, thông báo hoàn tất và bàn giao cho bộ phận vận hành.*

- [ ] **Bước 6.1: Ban hành Release Notes nội bộ**:
  Gửi email thông báo cho Ban Giám đốc, Trưởng phòng Kinh doanh và đội ngũ Sales:
  - **Bảng xếp hạng KPI V2**: Bổ sung chỉ số đánh giá phản hồi nhắc nhở đúng hạn (15% trọng số).
  - **Quản lý Báo giá đa phiên bản**: Tính năng xem lịch sử các lần điều chỉnh giá qua Drawer.
  - **Chuông CRM Bell & Email Outlook**: Cơ chế nhận diện và nhắc việc thông minh 2 chiều.
- [ ] **Bước 6.2: Cập nhật tài liệu kỹ thuật dự án**:
  - Đánh dấu trạng thái `PRODUCTION: LIVE & STABLE` trong [`docs/ai/IMPLEMENTATION_STATUS.md`](file:///Users/dieterhoang/Developer/portal_18/docs/ai/IMPLEMENTATION_STATUS.md).
  - Lưu trữ hồ sơ rà soát [`sales_pipeline_deep_audit_findings_20260907.md`](file:///Users/dieterhoang/Developer/portal_18/docs/plans/Sales%20Pipeline/sales_pipeline_deep_audit_findings_20260907.md).

---

## 3. KỊCH BẢN ROLLBACK KHẨN CẤP (EMERGENCY ROLLBACK RUNBOOK)

> [!CAUTION]
> **Điều kiện kích hoạt Rollback ngay lập tức (P0 Triggers)**:
> 1. Xảy ra lỗi Fatal làm sập trang Admin hoặc không mở được màn hình Báo giá/Hóa đơn của hệ thống.
> 2. Database bị Deadlock kéo dài làm treo kết nối của toàn bộ nhân viên.
> 3. Phát hiện dữ liệu doanh số hoặc trạng thái Báo giá cũ bị sai lệch.

### Quy trình Rollback 3 bước trong 5 phút:

#### Bước 1: Trả mã nguồn về Tag ổn định trước đó
```bash
cd /var/www/portal_18
# Tạm dừng Cron
crontab -l | grep -v 'cron/index' | crontab -

# Checkout về commit/tag trước khi nâng cấp
git checkout master # Hoặc tag phiên bản trước: git checkout v0.9.x
```

#### Bước 2: Phục hồi Database Snapshot (Nếu có lỗi dữ liệu)
```bash
mysql -u <db_user> -p innotel_portal < /backup/crm_prod_pre_release_<timestamp>.sql
```

#### Bước 3: Xóa Cache và Bật lại Cron
```bash
rm -rf /var/www/portal_18/application/cache/*
crontab -e # Khôi phục dòng cron
```
Xác nhận lại hệ thống truy cập bình thường.

---

## 4. CHECKLIST NGHIỆM THU TỪNG BƯỚC (GO/NO-GO CHECKLIST)

Trước khi chuyển trạng thái sang **GO PRODUCTION**, người phụ trách kỹ thuật (Tech Lead) phải ký duyệt xác nhận:

- [ ] **Code Quality**: Đã chạy 4/4 contract tests, kết quả 100% PASS.
- [ ] **Localization**: Khớp 697/697 language keys Anh - Việt, không còn chuỗi hardcode.
- [ ] **DDL Overhead**: Đã gỡ bỏ schema checks trên hook `app_init`.
- [ ] **Cron Batching**: Đã cấu hình batch reconcile an toàn ($limit = 100).
- [ ] **Data Backup**: Đã có file dump DB toàn phần lưu trữ tại thư mục an toàn ngoài web root.
- [ ] **Rollback Plan**: Đã có kịch bản rollback sẵn sàng và được diễn tập.

**KÝ DUYỆT BỞI (TECH LEAD / SENIOR SE)**: `___________________`  
**NGÀY PHÊ DUYỆT**: `____ / ____ / 2026`  
**QUYẾT ĐỊNH CUỐI CÙNG**: `[  ] GO PRODUCTION`   `[  ] HOLD`
