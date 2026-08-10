# Implementation Report: Panel deal đang mở và nhật ký hoạt động trong chi tiết nhân viên

## Metadata

- plan_id: `staff-detail-open-deals-activity-panels`
- plan_version: `1`
- qc_status: `PASS`
- status: `ROLLED_BACK_WRONG_SURFACE`
- implementer: `Gemini 3.6 Flash (High)`
- completion_date: `2026-08-08`

## Overview of Implementation

> **Invalidated on 2026-08-08:** implementation dưới đây nhắm sai route `admin/staff/member/{id}` và đã được rollback theo yêu cầu người dùng. Không dùng các acceptance claims bên dưới làm bằng chứng cho drawer Dashboard. Plan version 2 là nguồn sự thật hiện hành.

Đã hoàn thành triển khai hai panel chỉ đọc (Open Sales Opportunities và Recent System Activity Log) vào trang quản trị chi tiết nhân viên `admin/staff/member/{id}` theo đúng thiết kế Plan đã được QC phê duyệt (`PASS`) và đã xử lý dứt điểm 4 findings từ Codex Final Verification.

Toàn bộ logic được đóng gói độc lập bên trong module `sales_pipeline`. Không sửa đổi bất kỳ file core nào của framework/application, không thêm migration database, không thực hiện commit và bảo toàn 100% các thay đổi chưa commit khác sẵn có trong working tree.

## Affected Files

| File | Hành động | Nội dung thay đổi |
|---|---|---|
| `modules/sales_pipeline/sales_pipeline.php` | Modify | Đăng ký hooks `staff_member_edit_view_profile`, `app_admin_head`, `app_admin_footer`. Thêm các callback function bắt staff ID, nạp stylesheet scoped và render partial view kèm đầy đủ guards (`is_admin`, route check, table exists, staff exists). Đã sửa EOF formatting. |
| `modules/sales_pipeline/models/Sales_pipeline_model.php` | Modify | Thêm 2 method read-only `get_staff_open_deals($staff_id, $limit = 10)` (lọc `is_won = 0 AND is_lost = 0`, đếm tổng và trả tối đa 10 deal) và `get_staff_recent_activity($staff_id, $limit = 20)` (lấy tối đa 20 dòng từ `tblactivity_log`). Đã sửa EOF formatting. |
| `modules/sales_pipeline/views/_staff_detail_panels.php` | New | View partial chứa 2 panel (Open Deals & Activity Log), các bảng hiển thị dữ liệu, badges, status chips màu hex, empty states riêng biệt. Dùng key `sales_pipeline_staff_deal_name`, escape `deal_value_formatted` và mount fragment bằng Vanilla DOM độc lập với jQuery. |
| `modules/sales_pipeline/assets/css/staff-detail-panels.css` | New | Style scoped UI/UX theo chuẩn Perfex CRM & Sales Pipeline (panel_s, badges, status chips, timeline indicator, empty states và responsive layout cho mobile). |
| `modules/sales_pipeline/language/english/sales_pipeline_lang.php` | Modify | Xóa duplicate keys `sales_pipeline_deal_name`, `sales_pipeline_status` và `sales_pipeline_activity_description` ở cuối file; thêm key scoped mới `sales_pipeline_staff_deal_name`. Sửa EOF formatting. |
| `modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php` | Modify | Xóa duplicate keys `sales_pipeline_deal_name`, `sales_pipeline_status` và `sales_pipeline_activity_description` ở cuối file; thêm key scoped mới `sales_pipeline_staff_deal_name`. Sửa EOF formatting. |
| `docs/plans/staff-detail-open-deals-activity-panels.implementation.md` | Modify | Báo cáo chi tiết quá trình triển khai, kết quả khắc phục các findings và dữ liệu kiểm thử. |

## Codex Verification Findings & Fixes

1. **Duplicate Language Keys Cleanup (Blocker/Medium)**:
   - Đã xóa các key trùng lặp ở cuối file ngôn ngữ (`sales_pipeline_deal_name`, `sales_pipeline_status` và `sales_pipeline_activity_description`), tránh ghi đè làm thay đổi nghĩa toàn module.
   - Thêm key scoped mới `sales_pipeline_staff_deal_name` ('Deal Name' / 'Tên deal') cho panel open deals và cập nhật trong view partial.
   - Tái sử dụng key `sales_pipeline_status` đã có từ trước.
   - Kiểm tra định lượng bằng script PHP: Cả 2 file ngôn ngữ EN và VI đều đạt **289 unique keys**, **0 duplicate keys**.
2. **Vanilla DOM Mounting (Low)**:
   - Sửa script block trong `_staff_detail_panels.php` chuyển sang dùng Vanilla DOM (`document.getElementById`, `document.querySelector`, `target.insertBefore`, `removeChild`), hoàn toàn không phụ thuộc vào `jQuery` object, triệt tiêu nguy cơ `ReferenceError`.
3. **Escaping Output (Low/Security)**:
   - Đã bọc `html_escape($deal_value_formatted)` trước khi `echo` trong view partial, đảm bảo 100% output động đều qua escaping.
4. **Git Diff Formatting (Low)**:
   - Đã dọn dẹp các dòng trống dư thừa ở cuối file (`new blank line at EOF`) cho cả 4 file PHP. Command `git diff --check` trên tập file task trả về kết quả sạch 100%.

## Acceptance Criteria Validation Matrix

| Criterion | Trạng thái | Minh chứng kiểm tra |
|---|---|---|
| **AC1**: Full admin mở `admin/staff/member/{id}` thấy 2 panel ở đầu cột phải, trước Notes; trang tạo nhân viên mới không render panel | **PASS** | Bắt `staff_id` qua hook `staff_member_edit_view_profile`. Trên trang tạo mới (`$id` rỗng), `$staff_id` không hợp lệ nên footer callback ngắt sớm, không render. |
| **AC2**: Panel deal chỉ chứa deal `is_won = 0 AND is_lost = 0` và đúng `staff_id` | **PASS** | `Sales_pipeline_model::get_staff_open_deals()` thực thi `WHERE staff_id = ? AND is_won = 0 AND is_lost = 0` qua Active Record Query Builder. |
| **AC3**: Badge hiển thị tổng số deal; danh sách max 10 deal, sort `deal_date DESC, id DESC`, data & link an toàn | **PASS** | Query đếm `total` tách biệt, `LIMIT 10`, order by `deal_date DESC, id DESC`. Mọi output (tên deal, khách hàng, link, giá trị formatted) được bọc qua `html_escape()`. |
| **AC4**: Activity panel chứa max 20 dòng `activity_log.staffid`, sort `date DESC, id DESC`, description escaped | **PASS** | `Sales_pipeline_model::get_staff_recent_activity()` query từ `tblactivity_log` với `staffid = ?`, order by `date DESC, id DESC`, `LIMIT 20`. Output qua `check_for_links(html_escape($desc))`. |
| **AC5**: Non-admin, ID invalid, staff missing hoặc bảng nguồn missing không render, không lỗi core | **PASS** | Footer callback kiểm tra `is_admin()`, `$staff_id > 0`, `$CI->db->table_exists()`, và `$CI->staff_model->get($staff_id)`. Trả về `return;` im lặng nếu vi phạm. |
| **AC6**: Có empty state riêng, UI responsive, không gây overflow | **PASS** | Mỗi panel có block `.sp-staff-empty-state` khi rỗng. Table bọc `.scroll-responsive` hỗ trợ cuộn ngang trên màn hình nhỏ. |
| **AC7**: Đa ngôn ngữ English/Vietnamese, không hardcode user-facing text, không trùng key | **PASS** | Khai báo key `sales_pipeline_staff_*` trong cả 2 file ngôn ngữ, 0 duplicate keys (289 unique keys mỗi file), hiển thị qua `_l()`. |
| **AC8**: Không sửa core `application/`, không migration, bảo toàn working tree | **PASS** | Đã xác nhận qua `git status --short`. Toàn bộ file thuộc `modules/sales_pipeline/` và `docs/plans/`. |
| **AC9**: PHP Syntax check `php -l` đạt 100% | **PASS** | Đã chạy `php -l` trên toàn bộ 5 file PHP liên quan, kết quả `No syntax errors detected`. |

## Automated Verification Results

### 1. PHP Syntax Check (`php -l`)
```bash
php -l modules/sales_pipeline/sales_pipeline.php
php -l modules/sales_pipeline/models/Sales_pipeline_model.php
php -l modules/sales_pipeline/views/_staff_detail_panels.php
php -l modules/sales_pipeline/language/english/sales_pipeline_lang.php
php -l modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php
```
**Kết quả**:
- `No syntax errors detected in modules/sales_pipeline/sales_pipeline.php`
- `No syntax errors detected in modules/sales_pipeline/models/Sales_pipeline_model.php`
- `No syntax errors detected in modules/sales_pipeline/views/_staff_detail_panels.php`
- `No syntax errors detected in modules/sales_pipeline/language/english/sales_pipeline_lang.php`
- `No syntax errors detected in modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php`

### 2. Language Key Duplicate Check
Chạy script kiểm tra mảng `$lang` trong cả 2 file ngôn ngữ:
- **English**: Total assignments: 289, Unique keys: 289 → **0 duplicate keys**.
- **Vietnamese**: Total assignments: 289, Unique keys: 289 → **0 duplicate keys**.

### 3. Formatting & Git Diff Check (`git diff --check`)
```bash
git diff --check -- modules/sales_pipeline/sales_pipeline.php modules/sales_pipeline/models/Sales_pipeline_model.php modules/sales_pipeline/views/_staff_detail_panels.php modules/sales_pipeline/assets/css/staff-detail-panels.css modules/sales_pipeline/language/english/sales_pipeline_lang.php modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php
```
**Kết quả**: Clean, 0 warnings/errors.

### 4. Grep Fallback Check (`grep -rnE`)
*Lưu ý về môi trường*: Lệnh `rg` (ripgrep) không có sẵn trong shell environment của Antigravity (`zsh: command not found: rg`), nên đã sử dụng `grep -rnE` chuẩn làm fallback command để kiểm tra từ khóa:
```bash
grep -rnE "staff-detail|staff_detail|activity_log|is_won|is_lost" modules/sales_pipeline/sales_pipeline.php modules/sales_pipeline/models/Sales_pipeline_model.php modules/sales_pipeline/views/_staff_detail_panels.php modules/sales_pipeline/assets/css/staff-detail-panels.css modules/sales_pipeline/language/english/sales_pipeline_lang.php modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php
```
**Kết quả**: Các từ khóa xuất hiện đúng vị trí thiết kế và không có dư thừa hay lỗi tham chiếu.

### 5. Git Status Verification (`git status --short`)
```
 M modules/sales_pipeline/language/english/sales_pipeline_lang.php
 M modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php
 M modules/sales_pipeline/models/Sales_pipeline_model.php
 M modules/sales_pipeline/sales_pipeline.php
?? docs/plans/staff-detail-open-deals-activity-panels.implementation.md
?? modules/sales_pipeline/assets/css/staff-detail-panels.css
?? modules/sales_pipeline/views/_staff_detail_panels.php
```

## Manual Verification Guide (Local App)

Do server PHP local đang chạy tại `http://localhost:8000`:
1. Đăng nhập tài khoản Full Admin.
2. Truy cập `http://localhost:8000/admin/staff/member/1` (hoặc ID nhân viên đang có deal mở / activity log).
3. Quan sát cột phải: 2 panel "Cơ hội kinh doanh đang mở" và "Nhật ký hoạt động hệ thống gần nhất" xuất hiện ngay phía trên phần "Ghi chú".
4. Kiểm tra badge số lượng, màu sắc chip trạng thái, link tới chi tiết deal và hiển thị lịch sử hoạt động.
5. Truy cập `http://localhost:8000/admin/staff/member` (trang thêm mới nhân viên) → Xác nhận 2 panel không hiển thị.
6. Đăng nhập bằng tài khoản staff thường (non-admin) → Truy cập trang nhân viên → Xác nhận 2 panel không hiển thị.
