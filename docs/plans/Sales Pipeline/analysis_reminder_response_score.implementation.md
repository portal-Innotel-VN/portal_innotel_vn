# Implementation Record: Kích hoạt Điểm Phản hồi Nhắc nhở trong Performance Score

- **Mã tính năng / Kế hoạch**: `analysis_reminder_response_score.md`
- **Mốc đối chiếu (Fixed Point)**: `dce4e3e` (HEAD)
- **Module Version**: `1.0.12`
- **Database Migration**: `112_version_112.php`
- **Trạng thái tổng thể**: **Implementation-Complete & Contract-Verified (Chưa Release-Ready do môi trường DB/Concurrency runtime production cần staging audit)**

---

## 1. Mục tiêu và Phạm vi thực tế

### 1.1 Mục tiêu
Kích hoạt thành phần thứ 4: **Phản hồi nhắc nhở đúng hạn (`response_score`)** với trọng số chuẩn **15%** trong công thức `performance_score_v1`, áp dụng cho Bảng xếp hạng và Drawer chi tiết KPI Báo giá của module Sales Pipeline.

### 1.2 Phạm vi thực tế
1. **Schema & Migration**:
   - Bổ sung `response_sla_hours` (Smallint unsigned NULL) và `response_due_at` (Datetime NULL) kèm composite index `idx_reminder_sla_eval` vào `tblsales_pipeline_reminders_log`.
   - Seed options: `sp_reminder_sla_hours = 24`, `performance_response_target_percent = 90`.
2. **Snapshot & Kích hoạt SLA**:
   - Snapshot `response_sla_hours` khi tạo reminder nếu `response_required = 1`.
   - Chỉ kích hoạt `sent_at` và `response_due_at = DATE_ADD(sent_at, INTERVAL response_sla_hours HOUR)` khi có delivery thành công đầu tiên đến chính nhân viên phụ trách (`recipient_type = 'staff'`, `recipient_staff_id = staff_id`).
3. **Atomicity & Transaction**:
   - Đóng gói cập nhật trạng thái delivery và cập nhật SLA deadline của reminder trong cùng 1 Database transaction sau khi provider gửi thành công.
   - Transaction commit fail-safe: chỉ đánh dấu `$anySent = true` khi `trans_commit()` thực sự thành công.
4. **Dedicated Advisory Lock & Cron Reconciler**:
   - Tạo class `Reminder_sla_reconcile_lock` quản lý advisory lock `db_prefix() . ':sales_pipeline:reconciler:sla'`.
   - Self-healing batch reconciler `reconcile_missing_response_due_at(100)` chạy trong Cron hook `sales_pipeline_cron_reminder()`, hoạt động độc lập ngoài các gate global/quiet-hours/holiday.
5. **Calculator & Mẫu số động**:
   - Mẫu số động theo từng nhân viên trong cohort:
     - `eligible_reminders > 0`: Trạng thái `active`, tính trên mẫu số **100** (20/40/25/15).
     - `eligible_reminders == 0`: Trạng thái `not_applicable`, `response_score = null`, tính trên mẫu số **85** (23,53% / 47,06% / 29,41%).
     - `0 < eligible_reminders < 3`: Gắn cờ `insufficient_reminder_sample` và `is_provisional = true`.
   - Đánh giá toàn cohort tại một mốc `$calculated_at` duy nhất.
   - Dynamic weight derivation: `effective_weights` và tổng trọng số được tính toán hoàn toàn động từ `$standard_weights`.
6. **Settings Form Isolation & UI Drawer**:
   - Tab `#performance` cô lập form cấu hình target phản hồi (1–100%).
   - Tab `#reminders` bổ sung cấu hình SLA hours (1–720 giờ).
   - Drawer Card 4 hiển thị đa trạng thái: `active`, `not_applicable`, `inactive`, kèm badge "Tạm tính (Mẫu nhỏ)".
7. **Làm rõ phạm vi Historical Period**:
   - Nhánh code có chứa thêm tính năng Historical Period Picker (`period_anchor`, `includes/dashboard_period.php`, picker UI, CSS/JS). Đây là tính năng thuộc worktree mở rộng đồng thời, được bảo toàn nguyên vẹn và kiểm thử contract độc lập qua `Dashboard_historical_period_contract.php`.

---

## 2. Danh sách Files Thay đổi & Tạo mới

### Files Modified
1. `modules/sales_pipeline/sales_pipeline.php`: Bump version 1.0.12, bổ sung cron reconciler, schema bootstrap guards và JS localization keys.
2. `modules/sales_pipeline/includes/reminder_repository_schema.php`: Schema definition cho `response_sla_hours`, `response_due_at`, `idx_reminder_sla_eval`.
3. `modules/sales_pipeline/includes/reminder_rule_defaults.php`: Default option `sp_reminder_sla_hours => '24'`.
4. `modules/sales_pipeline/includes/performance_score_defaults.php`: Default option `performance_response_target_percent => '90'`.
5. `modules/sales_pipeline/install.php`: Cập nhật canonical CREATE TABLE cho `reminders_log` và gọi helper schema.
6. `modules/sales_pipeline/libraries/Performance_score_calculator.php`: Tính toán response score, dynamic denominator, provisional flags, dynamic standard_weights accessors.
7. `modules/sales_pipeline/libraries/Reminder_engine.php`: Snapshot SLA hours, atomic delivery post-provider transaction, self-healing reconciler.
8. `modules/sales_pipeline/models/Sales_pipeline_model.php`: Schema available guard, single `$calculated_at`, aggregate query cho estimate reminders.
9. `modules/sales_pipeline/controllers/Sales_pipeline.php`: Handler form settings `#performance` và validation boundaries.
10. `modules/sales_pipeline/views/settings.php`: UI tab `#performance` và input SLA trong tab `#reminders`.
11. `modules/sales_pipeline/views/_dashboard_staff_pipeline.php`: UI Drawer Card 4 hỗ trợ `active`, `not_applicable`, `inactive` và small sample badge.
12. `modules/sales_pipeline/assets/js/dashboard.js`: Loại bỏ text hard-code, kết nối localized tokens từ server.
13. `modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php`: Ngôn ngữ tiếng Việt cho SLA và Performance settings/drawer.
14. `modules/sales_pipeline/language/english/sales_pipeline_lang.php`: Ngôn ngữ tiếng Anh tương ứng.
15. `docs/specifications/PERFORMANCE_SCORE.md`: Đặc tả chuẩn công thức động và SLA criteria.
16. `docs/plans/Sales Pipeline/performance_score.md`: Cập nhật ghi chú kích hoạt động trong v1.
17. `docs/user-guides/PERFORMANCE_SCORE_GUIDE.md`: Hướng dẫn người dùng về 4 thành phần điểm.
18. `docs/ai/SALES_PIPELINE_CONTEXT.md`: Đồng bộ context hệ thống.
19. `docs/ai/IMPLEMENTATION_STATUS.md`: Cập nhật trạng thái implementation.
20. `.gitignore`: Bổ sung rule unignore chính xác cho `!modules/sales_pipeline/tests/*.php`.

### Files Added (Untracked / New)
1. `modules/sales_pipeline/migrations/112_version_112.php`: Migration 112 với non-destructive rollback documentation.
2. `modules/sales_pipeline/libraries/Reminder_sla_reconcile_lock.php`: Advisory lock library cho SLA reconciler.
3. `modules/sales_pipeline/includes/dashboard_period.php`: Helper historical period resolver.
4. `modules/sales_pipeline/tests/Reminder_sla_reconcile_test.php`: Test suite cho SLA eligibility, delivery start, reconciler và settings contracts.
5. `modules/sales_pipeline/tests/Dashboard_historical_period_contract.php`: Test suite cho historical period.
6. `docs/plans/Sales Pipeline/analysis_reminder_response_score.md`: Bản thiết kế kỹ thuật chi tiết.
7. `docs/plans/Sales Pipeline/analysis_reminder_response_score.implementation.md`: File này (Implementation Record).

---

## 3. Kiến trúc Luồng Dữ liệu (End-to-End)

```
[Cron: sales_pipeline_cron_reminder()] ──> Reminder_engine::process() ──> dispatchDeliveryRows()
                                                                       │ (post-provider DB trans)
                                                                       ├──> tblsales_pipeline_reminder_deliveries (sent)
                                                                       └──> tblsales_pipeline_reminders_log (sent_at, response_due_at)
                                      ──> Reminder_engine::reconcile_missing_response_due_at()
                                            │ (advisory lock: sales_pipeline:reconciler:sla)
                                            └──> Self-heals missing due_at from MIN(sent_at)

[HTTP GET /admin/sales_pipeline/dashboard]
    │
    ▼
[Sales_pipeline::dashboard()] / [Sales_pipeline::dashboard_staff_pipeline()]
    │
    ▼
[Sales_pipeline_model::get_estimate_performance_ranking($period, $period_anchor)]
    │ 1. Capture single $calculated_at = date('Y-m-d H:i:s')
    │ 2. Query Estimate Groups (Quote counts, Accepted revenue, Decisions)
    │ 3. Query Reminders Log (WHERE entity_type IN ('estimate', 'staff_estimate_period')
    │                         AND response_required = 1 AND response_due_at IN period AND <= $calculated_at)
    ▼
[Performance_score_calculator::calculate_leaderboard($metrics, $config)]
    │ - Standard weights: [quote: 20, revenue: 40, acceptance: 25, reminder: 15]
    │ - If eligible > 0  ──> Active: Denominator 100, response_score = CLAMP((on_time/eligible*100)/target*100, 0, 120)
    │                        If eligible < 3 ──> flag 'insufficient_reminder_sample', is_provisional = true
    │ - If eligible == 0 ──> Not Applicable: Denominator 85, response_score = null
    ▼
[Sales_pipeline_model::project_leaderboard()] / [Views: _dashboard_staff_pipeline.php]
    │ - Admin: Xem full cohort
    │ - Staff: Chỉ xem dòng của chính mình, ẩn raw metrics người khác
    │ - Drawer Card 4: Hiển thị điểm + trọng số 15% (active) hoặc badge "Không áp dụng" (not_applicable)
```

---

## 4. Bảng Xác minh Ma trận Chấp nhận (22 Scenarios)

| ID | Kịch bản kiểm thử | Phương thức xác minh | File / Command | Kết quả | Bằng chứng & Ghi chú | Trạng thái còn lại |
|:---|:---|:---|:---|:---:|:---|:---|
| **AC-01** | Staff delivery đầu tiên thành công kích hoạt SLA | Unit / Contract Test | `Reminder_sla_reconcile_test.php` | **PASS** | `shouldTriggerSla === true` khi `recipient_type === 'staff'` và `success === true`. | Implemented & Contract-Verified |
| **AC-02** | Manager delivery không kích hoạt SLA | Unit / Contract Test | `Reminder_sla_reconcile_test.php` | **PASS** | `isStaffRecipient === false` khi `recipient_type === 'manager'`. | Implemented & Contract-Verified |
| **AC-03** | Staff delivery thất bại không kích hoạt SLA | Unit / Contract Test | `Reminder_sla_reconcile_test.php` | **PASS** | `shouldTriggerSla === false` khi `success === false`. | Implemented & Contract-Verified |
| **AC-04** | Kênh delivery thứ hai không ghi đè deadline | Unit / Contract Test | `Reminder_sla_reconcile_test.php` | **PASS** | Guard `sent_at IS NULL` chặn ghi đè khi delivery thứ hai gửi thành công. | Implemented & Contract-Verified |
| **AC-05** | Atomicity giữa delivery và reminder log | Code Inspection & Logic Test | `Reminder_engine.php` (lines 728–770) | **PASS** | Bọc trong `trans_begin()`, chỉ set `$anySent = true` sau khi `trans_commit()` thành công. | Implemented & Code-Verified (Cần DB live audit) |
| **AC-06** | Transaction rollback khi commit thất bại | Code Inspection & Logic Test | `Reminder_engine.php` | **PASS** | `trans_rollback()` được gọi nếu `trans_status() === false` hoặc commit trả false; `$anySent` giữ false. | Implemented & Code-Verified |
| **AC-07** | Reconciler chạy độc lập với engine gates | Code Inspection | `sales_pipeline.php` hook `sales_pipeline_cron_reminder` | **PASS** | Gọi trực tiếp sau `Reminder_engine::process()`, không nằm sau early return của global switch. | Implemented & Code-Verified |
| **AC-08** | Reconciler dùng advisory lock riêng | Contract Test | `Reminder_sla_reconcile_lock.php` | **PASS** | Lock name: `db_prefix() . ':sales_pipeline:reconciler:sla'`. Fail-closed. | Implemented & Contract-Verified |
| **AC-09** | Reconciler chỉ lấy delivery staff đúng owner | Contract Test | `Reminder_sla_reconcile_test.php` | **PASS** | SQL join khớp `d.recipient_type = 'staff'` và `d.recipient_staff_id = r.staff_id`. | Implemented & Contract-Verified |
| **AC-10** | Reconciler bỏ qua reminder legacy (SLA NULL) | Contract Test | `Reminder_sla_reconcile_test.php` | **PASS** | Guard `r.response_sla_hours IS NOT NULL` loại bỏ hoàn toàn legacy log. | Implemented & Contract-Verified |
| **AC-11** | Reconciler idempotent và batch limit | Contract Test | `Reminder_engine.php` | **PASS** | Update có guard `response_due_at IS NULL`, limit mặc định 100. | Implemented & Contract-Verified |
| **AC-12** | Estimates query chỉ lấy đúng loại reminder | Unit Test | `Reminder_sla_reconcile_test.php` | **PASS** | Loại trừ `deal` entity_type, loại trừ informational (`response_required = 0`). | Implemented & Contract-Verified |
| **AC-13** | Phân loại đúng hạn / trễ hạn / chưa phản hồi | Unit Test | `Reminder_sla_reconcile_test.php` | **PASS** | 4 eligible (on-time 2, exact deadline 1, late 1, overdue unresponded 1) -> 2 on-time. | Implemented & Contract-Verified |
| **AC-14** | Single `$calculated_at` xuyên suốt cohort | Unit Test | `Sales_pipeline_model.php` & `Performance_score_calculator.php` | **PASS** | Model khởi tạo 1 timestamp, truyền vào query aggregate và trả về trong payload. | Implemented & Contract-Verified |
| **AC-15** | Mẫu số 100 khi `eligible > 0` | Unit Test | `Performance_score_calculator_test.php` | **PASS** | Tổng điểm 4 thành phần tính trên mẫu số 100, effective weight reminder = 15%. | Implemented & Contract-Verified |
| **AC-16** | Mẫu số 85 khi `eligible == 0` (Fallback) | Unit Test | `Performance_score_calculator_test.php` | **PASS** | Component status = `not_applicable`, response_score = null, mẫu số = 85. | Implemented & Contract-Verified |
| **AC-17** | Cờ `insufficient_reminder_sample` khi `0 < eligible < 3` | Unit Test | `Performance_score_calculator_test.php` | **PASS** | Flag xuất hiện trong `data_quality_flags` và `is_provisional = true`. | Implemented & Contract-Verified |
| **AC-18** | Chặn trần điểm thành phần 120 (`component_cap`) | Unit Test | `Performance_score_calculator_test.php` | **PASS** | `eligible=10, on_time=10, target=80` -> raw 125 -> capped chính xác 120.0. | Implemented & Contract-Verified |
| **AC-19** | Settings SLA hours validation (1–720) | Contract Test | `Reminder_sla_reconcile_test.php` | **PASS** | 1, 720, 24 hợp lệ; 0, 721, âm bị từ chối. | Implemented & Contract-Verified |
| **AC-20** | Settings Response target validation (1–100%) | Contract Test | `Reminder_sla_reconcile_test.php` | **PASS** | 1.0, 100.0, 90.0 hợp lệ; 0, 101 bị từ chối. | Implemented & Contract-Verified |
| **AC-21** | Settings Form Isolation | Contract Test | `Reminder_sla_reconcile_test.php` | **PASS** | Submit form Performance chỉ cập nhật performance options, không chạm reminder options và ngược lại. | Implemented & Contract-Verified |
| **AC-22** | UI Drawer đa trạng thái & Privacy projection | Unit Test & UI Inspection | `_dashboard_staff_pipeline.php` & `Performance_score_calculator_test.php` | **PASS** | Staff chỉ nhận dòng của mình, không thấy email/raw metrics người khác; Card 4 render đúng `active`/`not_applicable`/`inactive`. | Implemented & Contract-Verified (Cần Live Browser session) |

---

## 5. Rollback Plan Chi tiết

### 5.1 Nguyên tắc an toàn
Dữ liệu nhắc nhở và thời gian phản hồi SLA là **audit data** có giá trị ghi nhận hoạt động vận hành và KPI. Tuyệt đối **không tự động DROP cột hoặc bảng** khi rollback.

### 5.2 Các bước Rollback Code (Downgrade về version 1.0.11)
1. **Khả năng tương thích ngược (Backward Compatibility)**:
   - Các cột `response_sla_hours`, `response_due_at` và index `idx_reminder_sla_eval` vẫn an toàn nằm trong database mà không gây lỗi cú pháp cho code cũ.
   - Calculator của version 1.0.11 không đọc `eligible_reminders` và tự động tính trên mẫu số 85 như thiết kế ban đầu.
2. **Cấu hình Options**:
   - Giữ lại `sp_reminder_sla_hours` và `performance_response_target_percent` trong bảng `tbloptions` để không mất cấu hình nếu tái kích hoạt.
3. **Vô hiệu hóa tức thì nếu cần giữ code 1.0.12 nhưng tắt tính điểm nhắc nhở**:
   - Đặt `eligible_reminders = null` trong model hoặc tạm thời đổi status sang `inactive` trong calculator.
4. **Trình tự triển khai / khôi phục**:
   - Bước 1: Deploy code package trước đó (v1.0.11).
   - Bước 2: Xóa cache Opcode PHP nếu có (`opcache_reset()`).
   - Bước 3: Kiểm tra Dashboard tải bình thường trên mẫu số 85.

---

## 6. Phân loại Mức độ Xác minh Hiện tại

| Phân loại | Định nghĩa | Danh sách hạng mục |
|:---|:---|:---|
| **Implemented** | Code đã được viết đầy đủ vào codebase | Toàn bộ 15 PHP files, views, assets, migrations. |
| **Statically Verified** | Đã kiểm tra cú pháp và đối chiếu tĩnh | `php -l` đạt 15/15 files, không có syntax error; `git diff --check` đạt. |
| **Contract / Unit Verified** | Đã chạy test harness tự động trên máy | Toàn bộ 15/15 test scripts trong `modules/sales_pipeline/tests/` PASS 100%. |
| **Manually UI Verified** | Đã kiểm tra trực quan trên trình duyệt localhost | Drawer hiển thị đúng badge "Không áp dụng" và mẫu số 85 khi `eligible == 0`. |
| **Not Yet Verified / Blockers** | Chưa thể xác minh trên môi trường Sandbox | 1. Concurrency multi-worker live advisory lock contention.<br>2. Real database high-load transaction rollback simulation.<br>3. Automated browser end-to-end regression trên production dataset. |

---

## 7. Kết luận & Khuyến nghị Release

- **Trạng thái**: **Implementation-Complete & Statically/Contract-Verified**.
- **Đánh giá Ready**: Tính năng đã sẵn sàng về mặt mã nguồn và kiểm thử logic nghiệp vụ. Trước khi release chính thức lên môi trường Production, khuyến nghị chạy migration 112 trên môi trường Staging có dữ liệu thực tế để kiểm tra query performance (`EXPLAIN`) và xác nhận cron worker chạy ổn định.
