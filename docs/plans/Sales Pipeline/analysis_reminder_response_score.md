# Kế hoạch Triển khai: Kích hoạt Điểm Phản hồi Nhắc nhở (response_score) trong Performance Score & Dashboard Drawer

## Context & Cơ sở Tham chiếu

- [`PERFORMANCE_SCORE.md`](file:///Users/dieterhoang/Developer/portal_18/docs/specifications/PERFORMANCE_SCORE.md) — Đặc tả chuẩn công thức tính điểm.
- [`Performance_score_calculator.php`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/libraries/Performance_score_calculator.php) — Pure Calculator cho Performance Score.
- [`_dashboard_staff_pipeline.php`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/views/_dashboard_staff_pipeline.php) — View drawer "Thông tin chi tiết" tab Estimates.
- [`Sales_pipeline.php` (Controller)](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/controllers/Sales_pipeline.php) — Controller Dashboard & Settings.
- [`Sales_pipeline_model.php`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/models/Sales_pipeline_model.php) — Data aggregate & ranking query.
- [`Reminder_engine.php`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/libraries/Reminder_engine.php) — Engine gửi và quản lý delivery nhắc nhở.
- [`sales_pipeline.php`](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/sales_pipeline.php) — Cron entry point `sales_pipeline_cron_reminder()` và hooks.
- [`performance_score.md` plan §5.4](file:///Users/dieterhoang/Developer/portal_18/docs/plans/Sales%20Pipeline/performance_score.md#L197-L221) — Thiết kế nghiệp vụ ban đầu.

---

## 1. Hiện trạng Basecode & Điểm kẹt Kỹ thuật

### 1.1. Hiện trạng Basecode đã xác minh
1. **Calculator:** Đang hard-code `response_score = null` và `component_status = ['reminder_response' => 'inactive']`. Mẫu số trọng số đang là **85** ($20 + 40 + 25$) tại [`Performance_score_calculator.php` L107-108](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/libraries/Performance_score_calculator.php#L107-L108).
2. **UI Drawer:** Đang hard-code card "Phản hồi nhắc nhở" là `inactive` với dấu "—" tại [`_dashboard_staff_pipeline.php` L157-170](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/views/_dashboard_staff_pipeline.php#L157-L170).
3. **Repository Nhắc nhở:** Đã có bảng `tblsales_pipeline_reminders_log` và bảng delivery là `tblsales_pipeline_reminder_deliveries`. Đã có `response_required`, `staff_response`, `responded_at`.
4. **Điểm kẹt cốt lõi:** Bảng `tblsales_pipeline_reminders_log` **chưa có cột `response_due_at` và `response_sla_hours`**, nên hệ thống chưa thể phân biệt phản hồi đúng hạn vs trễ hạn cũng như cô lập an toàn dữ liệu legacy.

---

## 2. Đặc tả Nghiệp vụ & Contract Triển khai Chuẩn

### 2.1. Formula Version, Đặc tả Kích hoạt & Đồng bộ Tài liệu
- **Formula Version:** Giữ nguyên định danh **`performance_score_v1`** (bản thân đặc tả chuẩn v1 đã thiết kế sẵn 4 components với standard weights 20/40/25/15).
- **Trạng thái Component:** Chuyển trạng thái component `reminder_response` từ `inactive` sang hoạt động linh hoạt (`active` khi có nhắc nhở đến hạn / `not_applicable` khi không có).
- **Semantics khi đổi Cấu hình Target:** Giống như các target khác trong Basecode hiện tại (`quote_target`, `revenue_target`, `acceptance_target`), `performance_response_target_percent` là option toàn cục được áp dụng tại thời điểm tính toán động (on-the-fly calculation); khi Admin thay đổi target, kết quả tính toán trên Dashboard sẽ áp dụng target mới cho mọi kỳ khi tải lại trang.
- **Đồng bộ Tài liệu Bắt buộc:**
  1. Cập nhật [`PERFORMANCE_SCORE.md`](file:///Users/dieterhoang/Developer/portal_18/docs/specifications/PERFORMANCE_SCORE.md) để mô tả chính thức cơ chế kích hoạt động (active/not_applicable, mẫu số 85/100, `response_due_at` eligibility theo kỳ, và cờ `insufficient_reminder_sample`).
  2. Đánh dấu trong [`performance_score.md` plan](file:///Users/dieterhoang/Developer/portal_18/docs/plans/Sales%20Pipeline/performance_score.md): quy tắc cũ "yêu cầu tạo formula version mới khi kích hoạt reminder" đã được thay thế bằng quy tắc "kích hoạt component động trong `performance_score_v1`".
  3. Cập nhật [`docs/ai/IMPLEMENTATION_STATUS.md`](file:///Users/dieterhoang/Developer/portal_18/docs/ai/IMPLEMENTATION_STATUS.md) sau khi hoàn tất kiểm thử và xác minh Basecode.

### 2.2. Phạm vi Reminder tính điểm cho Estimates Dashboard
Đường ranking hiện tại là `get_estimate_performance_ranking()` dành riêng cho tab Báo giá (Estimates). Do đó, chỉ những nhắc nhở thuộc phạm vi Báo giá mới được tham gia tính điểm:
```sql
entity_type IN ('estimate', 'staff_estimate_period')
AND response_required = 1
```
*(Tuyệt đối không query lẫn nhắc nhở của Deal như `deal`, `staff_deal_period`, `staff_deal_backlog`).*

### 2.3. SLA Snapshot Policy, Delivery Transaction & Self-healing Reconciler

#### A. Snapshot SLA Policy chỉ cho Actionable Reminder
1. Thêm cột `response_sla_hours SMALLINT UNSIGNED NULL AFTER response_required` vào `tblsales_pipeline_reminders_log`.
2. Khi insert reminder log:
   - Nếu `response_required == 1`: snapshot giá trị SLA hợp lệ tại thời điểm đó (mặc định `24` hoặc từ cấu hình `sp_reminder_sla_hours`).
   - Nếu `response_required != 1` (Informational reminder): lưu `response_sla_hours = NULL`.
3. Toàn bộ nhắc nhở cũ (legacy) giữ `response_sla_hours = NULL`.

#### B. Luồng Delivery Transaction An toàn (Post-Provider Success)
1. Gọi channel adapter (CRM / Email hoặc các channel adapter hiện được hỗ trợ trong Basecode).
2. Provider trả kết quả thành công.
3. Bắt đầu DB Transaction (`trans_start`).
4. Update delivery row trong `tblsales_pipeline_reminder_deliveries` của nhân viên (`recipient_type = 'staff' AND recipient_staff_id = reminder.staff_id`) sang trạng thái `sent` và ghi nhận `sent_at`.
5. Thực hiện conditional update trên `tblsales_pipeline_reminders_log`:
   ```sql
   UPDATE tblsales_pipeline_reminders_log
   SET sent_at = ?,
       response_due_at = DATE_ADD(?, INTERVAL response_sla_hours HOUR)
   WHERE id = ?
     AND response_required = 1
     AND response_sla_hours IS NOT NULL
     AND sent_at IS NULL
     AND response_due_at IS NULL;
   ```
6. Commit DB Transaction (`trans_complete`).

*Lưu ý kiến trúc:* Nếu process crash trước khi commit DB transaction, delivery vẫn ở trạng thái `processing` hoặc pending, được cơ chế stale retry hiện hữu xử lý. Reconciler xử lý trường hợp DB đã có staff delivery `sent` nhưng log thiếu deadline do dữ liệu bất nhất.

#### C. Lifecycle của Self-healing Reconciler trong Cron (Ngoài các Gate của Engine)
- **Vị trí Thực thi Độc lập:** Được gọi trực tiếp trong entry point Cron `sales_pipeline_cron_reminder()` (hoặc hàm maintenance riêng), **chạy hoàn toàn độc lập với các gate của `Reminder_engine::process()`** (vẫn chạy khi `sp_reminder_global_enabled == '0'`, khi trong quiet hours hoặc ngày nghỉ). **Tuyệt đối không write DB trong request xem Dashboard**.
- **Lock Riêng Biệt Chuẩn db_prefix():** Sử dụng library lock riêng `modules/sales_pipeline/libraries/Reminder_sla_reconcile_lock.php` với lock name `db_prefix() . ':sales_pipeline:reconciler:sla'` (fail-closed nếu worker khác đang giữ lock, tránh xung đột giữa các database instance).
- **Tương thích DB (2-step Batch):**
  1. *Step 1 (Select ID):*
     ```sql
     SELECT rl.id, rl.response_sla_hours, MIN(d.sent_at) as first_staff_sent_at
     FROM tblsales_pipeline_reminders_log rl
     JOIN tblsales_pipeline_reminder_deliveries d
       ON d.reminder_id = rl.id
      AND d.recipient_type = 'staff'
      AND d.recipient_staff_id = rl.staff_id
      AND d.status = 'sent'
      AND d.sent_at IS NOT NULL
     WHERE rl.response_required = 1
       AND rl.response_sla_hours IS NOT NULL
       AND rl.response_due_at IS NULL
     GROUP BY rl.id, rl.response_sla_hours
     LIMIT 100;
     ```
  2. *Step 2 (Conditional Update theo batch với đầy đủ guards):*
     Lặp qua danh sách ID từ Step 1 và update bằng chính `first_staff_sent_at` đã select (không dùng `NOW()` hay SLA hiện tại):
     ```sql
     UPDATE tblsales_pipeline_reminders_log
     SET sent_at = COALESCE(sent_at, ?),
         response_due_at = DATE_ADD(?, INTERVAL response_sla_hours HOUR)
     WHERE id = ?
       AND response_required = 1
       AND response_sla_hours IS NOT NULL
       AND response_due_at IS NULL;
     ```
- **Ghi vết:** Log số dòng reconciled nếu $> 0$.

### 2.4. Mốc Thời gian Tính toán Duy nhất (`calculated_at`) & Timezone
- Khi bắt đầu hàm `get_estimate_performance_ranking()`, capture một mốc thời gian duy nhất:
  ```php
  $calculated_at = date('Y-m-d H:i:s');
  ```
- Dùng cùng `$calculated_at` này cho toàn bộ cohort, các query SQL aggregate và config của `Performance_score_calculator`.
- Trả `$calculated_at` trong output payload của Leaderboard để phục vụ audit.
- Đảm bảo PHP timezone và MySQL session timezone đồng bộ.

### 2.5. Contract Phân loại Hợp lệ (Eligible) & Đúng hạn (On-time)
Áp dụng cho 4 loại kỳ chuẩn (`this_week`, `this_month`, `this_quarter`, `this_year` theo `resolve_dashboard_period()`):

```text
eligible_reminders =
    response_required = 1
    AND entity_type IN ('estimate', 'staff_estimate_period')
    AND response_due_at IS NOT NULL
    AND response_due_at >= period_start
    AND response_due_at < period_end_exclusive
    AND response_due_at <= calculated_at

on_time_reminders =
    eligible_reminders
    AND responded_at IS NOT NULL
    AND responded_at <= response_due_at
```

---

## 3. Công thức Tính Điểm, Phân bổ Mẫu số Động & Cờ Mẫu nhỏ

### 3.1. Điểm thành phần Phản hồi nhắc nhở (`response_score`)
$$\text{on\_time\_rate} = \frac{\text{on\_time\_reminders}}{\text{eligible\_reminders}} \times 100$$
$$\text{response\_score} = \text{CLAMP}\left(\frac{\text{on\_time\_rate}}{\text{response\_target\_percent}} \times 100,\ 0,\ 120\right)$$

* `response_target_percent` mặc định là **90%** (khoảng cấu hình 1–100%).
* Đạt 90% đúng hạn $\rightarrow$ `response_score = 100` điểm.
* Đạt 100% đúng hạn $\rightarrow$ `response_score = (100 / 90) * 100 = 111.1` điểm (điểm thưởng).
* Khi `response_target_percent = 80%` và `on_time_rate = 100%` $\rightarrow$ `raw_score = 125`, bị chặn đúng tại trần cap **120**.

### 3.2. Cơ chế Mẫu số Động theo Nhân viên trong Cohort
1. **Nếu nhân viên CÓ nhắc nhở đến hạn trong kỳ (`eligible_reminders > 0`):**
   - Mẫu số tổng trọng số = **100** ($20 + 40 + 25 + 15$).
2. **Nếu nhân viên KHÔNG CÓ nhắc nhở đến hạn trong kỳ (`eligible_reminders == 0`):**
   - `response_score = null`, trạng thái `reminder_response = not_applicable`.
   - Mẫu số tổng trọng số tự động chuẩn hóa về **85** ($20 + 40 + 25$).

### 3.3. Xử lý Mẫu nhỏ & Cờ Cảnh báo Chất lượng Dữ liệu
- **Quy tắc gắn cờ:**
  ```text
  eligible_reminders > 0 AND eligible_reminders < 3
  ```
  $\rightarrow$ Calculator tự động bổ sung cờ `insufficient_reminder_sample` vào `data_quality_flags` và bật `is_provisional = true`.
  *(Nhân viên có `eligible_reminders == 0` rơi vào `not_applicable`, tuyệt đối không gắn cờ `insufficient_reminder_sample`).*
- **Hiển thị UI Drawer:** Badge "Tạm tính (Mẫu nhỏ)" chỉ hiển thị khi:
  ```php
  in_array('insufficient_reminder_sample', $performance_metric['data_quality_flags'] ?? [], true)
  ```
  *(Tránh nhầm lẫn với các nguyên nhân provisional khác như thiếu closed quotes hay thiếu tỷ giá).*

---

## 4. Các Quyết định Nghiệp vụ & Cấu hình Settings Đã Thống nhất

| Quyết định | Nội dung đã chốt | Vị trí & Nhánh Xử lý Controller |
|---|---|---|
| **4.1. SLA Deadline** | Mặc định **24 giờ liên tục** (wall-clock hours). Khoảng hợp lệ: **1–720 giờ**. | Tab **Quy tắc tự động hóa** (`reminders`) $\rightarrow$ nhánh `setting_type === 'reminder'` |
| **4.2. Target đúng hạn** | Mặc định **90%** (`performance_response_target_percent`). Khoảng hợp lệ: **1–100%**. | Tab mới **Điểm hiệu suất** (`performance`) $\rightarrow$ nhánh `setting_type === 'performance'` |
| **4.3. Form Isolation** | Submit tab Performance chỉ lưu options Performance; tuyệt đối không reset toggles của Reminders. | Nhánh xử lý độc lập trong `Sales_pipeline::settings()`, hỗ trợ hash `#performance` |
| **4.4. Migration Number** | File migration **`112_version_112.php`**, nâng module version lên **`1.0.12`**. | `migrations/112_version_112.php` |

---

## 5. Danh sách File Ảnh hưởng & Chi tiết Kỹ thuật

| # | File | Nội dung thay đổi |
|---|---|---|
| 1 | `modules/sales_pipeline/migrations/112_version_112.php` | Thêm cột `response_sla_hours SMALLINT UNSIGNED NULL` và `response_due_at DATETIME NULL` vào `tblsales_pipeline_reminders_log`. Thêm index `idx_reminder_sla_eval` (`staff_id`, `entity_type`, `response_required`, `response_due_at`, `responded_at`). Khởi tạo default options. |
| 2 | `modules/sales_pipeline/install.php` | Thêm 2 cột mới và index vào schema khởi tạo cho bản cài đặt mới. |
| 3 | `modules/sales_pipeline/includes/reminder_repository_schema.php` | Cập nhật schema repository và mảng fields idempotent (`response_sla_hours`, `response_due_at`). |
| 4 | `modules/sales_pipeline/includes/reminder_rule_defaults.php` | Bổ sung default option `sp_reminder_sla_hours => 24`. |
| 5 | `modules/sales_pipeline/includes/performance_score_defaults.php` | Bổ sung default option `performance_response_target_percent => 90.0`. |
| 6 | `modules/sales_pipeline/libraries/Reminder_sla_reconcile_lock.php` | Library quản lý advisory lock riêng `db_prefix() . ':sales_pipeline:reconciler:sla'` (acquire/release/fail-closed). |
| 7 | `modules/sales_pipeline/libraries/Reminder_engine.php` | Snapshot `response_sla_hours` cho actionable reminder; bọc update delivery status và update reminder log trong DB transaction (sau provider success); thêm hàm reconciliation 2-step batch an toàn có đầy đủ guard. |
| 8 | `modules/sales_pipeline/models/Sales_pipeline_model.php` | Schema guard kiểm tra cả 2 cột `response_due_at` và `response_sla_hours` qua `field_exists()`; capture `$calculated_at` duy nhất; thêm query aggregate `eligible_reminders` và `on_time_reminders` trong `get_estimate_performance_ranking()`. |
| 9 | `modules/sales_pipeline/libraries/Performance_score_calculator.php` | Thêm validation `response_target` (1–100); tính `response_score` chuẩn CLAMP, xử lý `not_applicable` per-staff khi `eligible = 0` (mẫu số 85) và active khi `eligible > 0` (mẫu số 100); gắn cờ `insufficient_reminder_sample` khi `eligible > 0 && eligible < 3`; trả `calculated_at` trong output. |
| 10 | `modules/sales_pipeline/controllers/Sales_pipeline.php` | Tách nhánh `setting_type === 'performance'` (cô lập form); whitelist và validate `performance_response_target_percent` (1–100%) và `sp_reminder_sla_hours` (1–720h); chuẩn bị `$performance_options` cho view Settings; truyền dữ liệu chuẩn vào `dashboard_staff_pipeline()`. |
| 11 | `modules/sales_pipeline/views/settings.php` | Thêm tab **Điểm hiệu suất** (`performance`) với input Target (%); thêm input SLA (giờ) tại tab **Quy tắc tự động hóa** (`reminders`); hỗ trợ URL hash `#performance`. |
| 12 | `modules/sales_pipeline/views/_dashboard_staff_pipeline.php` | Cập nhật card thứ 4 trong Drawer: hiển thị điểm khi active, trọng số 15%, badge "Không áp dụng" khi `not_applicable`, và badge "Tạm tính (Mẫu nhỏ)" chỉ khi có cờ `insufficient_reminder_sample`. |
| 13 | `modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php` & `english/` | Thêm các key ngôn ngữ tương ứng. |
| 14 | `modules/sales_pipeline/sales_pipeline.php` | Nâng module version lên `1.0.12`; gọi reconciler trực tiếp trong Cron hook `sales_pipeline_cron_reminder()` ngoài các gate của engine. |
| 15 | `docs/specifications/PERFORMANCE_SCORE.md` | Cập nhật đặc tả chính thức: activation động, SLA eligibility, mẫu số 85/100, cờ mẫu nhỏ. |
| 16 | `docs/plans/Sales Pipeline/performance_score.md` | Đánh dấu cập nhật quyết định kiến trúc: thay thế quy tắc version mới bằng activation động trong v1. |
| 17 | `docs/ai/IMPLEMENTATION_STATUS.md` | Cập nhật trạng thái implementation và verification sau khi hoàn thành. |
| 18 | `modules/sales_pipeline/tests/` | Bổ sung các test scripts mới và chạy toàn bộ test harness của module. |

---

## 6. Matrix Kiểm thử & Verification Plan (Bắt buộc)

### 6.1. Bộ Lệnh Kiểm thử Chạy Toàn bộ Test Suite
Sau khi code, bắt buộc chạy:
1. Syntax check toàn bộ 14 file PHP sửa đổi:
   ```bash
   php -l modules/sales_pipeline/migrations/112_version_112.php
   php -l modules/sales_pipeline/install.php
   php -l modules/sales_pipeline/includes/reminder_repository_schema.php
   php -l modules/sales_pipeline/includes/reminder_rule_defaults.php
   php -l modules/sales_pipeline/includes/performance_score_defaults.php
   php -l modules/sales_pipeline/libraries/Reminder_sla_reconcile_lock.php
   php -l modules/sales_pipeline/libraries/Performance_score_calculator.php
   php -l modules/sales_pipeline/libraries/Reminder_engine.php
   php -l modules/sales_pipeline/models/Sales_pipeline_model.php
   php -l modules/sales_pipeline/controllers/Sales_pipeline.php
   php -l modules/sales_pipeline/views/settings.php
   php -l modules/sales_pipeline/views/_dashboard_staff_pipeline.php
   php -l modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php
   php -l modules/sales_pipeline/language/english/sales_pipeline_lang.php
   php -l modules/sales_pipeline/sales_pipeline.php
   ```
2. Chạy toàn bộ test harness hiện có và test mới:
   ```bash
   for test in modules/sales_pipeline/tests/*.php; do php "$test"; done
   ```

### 6.2. 22 Kịch bản Acceptance Matrix
1. **Manager Delivery First:** Manager gửi thành công trước $\rightarrow$ `reminders_log.sent_at` và `response_due_at` vẫn giữ `NULL`.
2. **Staff Delivery Failed:** Kênh gửi đến nhân viên thất bại $\rightarrow$ `response_due_at` vẫn `NULL`, SLA không khởi động.
3. **Staff Concurrent Delivery Race:** Hai kênh gửi nhân viên thành công gần đồng thời $\rightarrow$ Transaction & conditional update đảm bảo chỉ channel đầu tiên ghi deadline, channel sau không overwrite.
4. **Delivery Atomicity & Reconcile:** Reconciler chạy độc lập với engine gates, bù deadline từ `MIN(staff_sent_at) + response_sla_hours` với `recipient_staff_id = reminder.staff_id` và **không chạm vào reminder legacy** (`response_sla_hours IS NULL`).
5. **Reconcile Lock Fail-closed:** Khi lock `db_prefix() . ':sales_pipeline:reconciler:sla'` đang bị giữ bởi tiến trình khác, reconciler thoát an toàn không xung đột.
6. **Exact Deadline Response:** Phản hồi chính xác tại thời điểm deadline (`responded_at == response_due_at`) $\rightarrow$ Tính là `on_time`.
7. **Late Response:** Phản hồi trễ (`responded_at > response_due_at`) $\rightarrow$ Tính vào `eligible`, không tính vào `on_time`.
8. **Overdue Unresponded:** Quá hạn chưa phản hồi (`staff_response IS NULL` và `response_due_at <= calculated_at`) $\rightarrow$ Tính vào `eligible`, không tính vào `on_time`.
9. **Period Boundary Allocation:** Deadline nằm sát biên đầu (`response_due_at == period_start`) hoặc sát biên cuối (`response_due_at == period_end_exclusive - 1s`) $\rightarrow$ Phân bổ chính xác vào kỳ.
10. **Future Deadline Filtering:** Nhắc nhở có deadline tương lai trong kỳ hiện tại (`response_due_at > calculated_at`) $\rightarrow$ Chưa tính vào `eligible`.
11. **Legacy Reminders Isolation:** Nhắc nhở cũ có `response_due_at IS NULL` $\rightarrow$ Bị loại hoàn toàn khỏi `eligible`.
12. **Deal Reminders Exclusion:** Nhắc nhở thuộc Deal (`entity_type = 'deal'`) có `response_required = 1` $\rightarrow$ Không bị tính lẫn vào Estimates Ranking.
13. **Mixed Cohort Calculation:** Trong cùng 1 Leaderboard, Staff A có `eligible > 0` dùng mẫu số 100, Staff B có `eligible = 0` dùng mẫu số 85 $\rightarrow$ Tổng điểm và xếp hạng toàn cohort chính xác.
14. **Cap 120 Limit:** Khi `eligible = 10, on_time = 10, response_target = 80` (raw score = 125) $\rightarrow$ Điểm thành phần bị chặn đúng tại trần `120`.
15. **Small Sample Provisional Flag:** Khi `eligible_reminders` từ 1 đến 2 $\rightarrow$ Bật cờ `insufficient_reminder_sample` và `is_provisional = true`; khi `eligible = 0` $\rightarrow$ `not_applicable`, không bật cờ này.
16. **Calculator Configuration Error:** Khi `response_target` thiếu hoặc nằm ngoài khoảng 1–100 $\rightarrow$ Calculator trả status `not_configured` kèm `configuration_errors`.
17. **Role & Projection Privacy:** Staff thường chỉ nhận `response_score` của chính mình; không nhận raw reminder metrics hoặc điểm chi tiết của nhân viên khác.
18. **Settings Form Isolation:** Submit tab Performance không reset toggles của tab Reminders; validation biên cho `sla_hours` (1–720h) và `response_target` (1–100%).
19. **Migration 112 Idempotency:** Migration 112 chạy an toàn trên DB mới lẫn DB nâng cấp từ 111 nhiều lần không gây lỗi.
20. **Database Index Verification (`EXPLAIN`):** Chạy `EXPLAIN` kiểm tra query aggregate trong `get_estimate_performance_ranking()` sử dụng đúng index `idx_reminder_sla_eval`.
21. **Dual-column Schema Readiness Fallback:** Khi chạy code trên DB thiếu một trong hai cột (`response_due_at`, `response_sla_hours`) $\rightarrow$ `field_exists()` bảo vệ không phát sinh lỗi fatal, fallback an toàn về `inactive`.
22. **Drawer UI States:** Drawer hiển thị đầy đủ và chuẩn xác các trạng thái: Active có điểm, Not-applicable (Chưa có nhắc nhở), Provisional chỉ khi có cờ `insufficient_reminder_sample`, và Responsive trên desktop/mobile.
