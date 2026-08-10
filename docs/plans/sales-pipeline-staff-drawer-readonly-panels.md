# Feature Plan: Hai panel chỉ đọc trong drawer Thông tin chi tiết Sales Pipeline

## Metadata

- plan_id: `sales-pipeline-staff-drawer-readonly-panels`
- plan_version: `1`
- plan_status: `VERIFIED`
- qc_status: `PASS`
- planner: `Codex`
- qc_reviewer: `Gemini 3.6 Flash (High)`
- implementer: `Gemini 3.6 Flash (High)`
- updated_at: `2026-08-08`

## Goal

Tại `/admin/sales_pipeline/dashboard`, khi bấm một nhân viên và mở drawer “Thông tin chi tiết”, giữ nguyên thông tin nhân viên và ba KPI hiện có, sau đó chỉ hiển thị đúng hai panel chỉ đọc theo thứ tự: “Cơ hội kinh doanh đang mở” và “Nhật ký hoạt động”. Loại bỏ hoàn toàn mini Kanban, feed trùng lặp và mọi nội dung drawer cũ bên dưới hai panel.

## Business context

- “Deal chưa đóng” có một định nghĩa duy nhất: trạng thái của deal có `is_won = 0 AND is_lost = 0`. Không được dùng riêng `is_lost = 0`.
- “Nhật ký hoạt động” trong drawer là lịch sử phản hồi lời nhắc Sales Pipeline của nhân viên, lấy từ `sales_pipeline_reminders_log`; không phải nhật ký hệ thống chung `activity_log`.
- Hai panel phục vụ quản lý xem dữ liệu, không cung cấp thao tác sửa, xóa, thay đổi status deal hoặc gửi/sửa phản hồi lời nhắc.
- Permission và staff scope hiện có của Sales Pipeline Dashboard là ranh giới dữ liệu bắt buộc: `view_own` chỉ xem chính mình; chỉ người có quyền xem toàn Dashboard mới xem nhân viên khác.

## Basecode evidence

| File / symbol | Hiện trạng đã xác minh | Ảnh hưởng |
|---|---|---|
| `modules/sales_pipeline/assets/js/dashboard.js` / `openDrawer()` | Click `.js-sp-open-staff` gọi GET AJAX tới URL từ `data-staff-url` và nạp `response.data.html` vào drawer. | Giữ nguyên luồng AJAX và không tạo endpoint mới. |
| `modules/sales_pipeline/views/dashboard.php` | `data-staff-url` trỏ tới `admin_url('sales_pipeline/dashboard_staff_pipeline')`; drawer và leaderboard đã tồn tại. | Không đổi leaderboard, ba KPI tổng quan hoặc shell drawer. |
| `modules/sales_pipeline/controllers/Sales_pipeline.php` / `dashboard_staff_pipeline($staff_id)` | Endpoint AJAX-only; kiểm tra `can_access_dashboard()`, ép kiểu staff ID, chặn xem nhân viên khác nếu không có `can_view_dashboard_all()`, xác minh staff active; hiện truyền `board` từ `get_staff_pipeline_board()` và `actionable_feed` từ `get_reminder_response_stats(['staff_id' => ..., 'limit' => 20])`. | Giữ nguyên guard/permission; thay dữ liệu mini Kanban bằng danh sách deal mở; tiếp tục dùng reminder helper theo đúng `staff_id`. 404 khi mở trực tiếp là hành vi đúng vì endpoint AJAX-only. |
| `modules/sales_pipeline/controllers/Sales_pipeline.php` / `can_access_dashboard()`, `can_view_dashboard_all()` | `view_own` được vào Dashboard nhưng `view`/admin mới xem toàn bộ. | Không thêm permission/endpoint mới và không nới staff scope. |
| `modules/sales_pipeline/models/Sales_pipeline_model.php` / `get_reminder_response_stats()` | Query `sales_pipeline_reminders_log` alias `rl`, join staff/deal, hỗ trợ `staff_id`, giới hạn 1–50, trả `message`, `staff_response`, `sent_at`, `responded_at`. | Tái sử dụng trực tiếp cho Panel 2 với `staff_id` của drawer và limit 20. Không dùng `activity_log`. |
| `modules/sales_pipeline/models/Sales_pipeline_model.php` / `get_staff_open_deals()` | Helper trong working tree đã query theo `staff_id`, join status, lọc cả `is_won = 0` và `is_lost = 0`, trả danh sách + total; limit mặc định 10. | QC xác minh helper và dùng cho Panel 1; giữ limit 10 và total không phụ thuộc limit. |
| `modules/sales_pipeline/models/Sales_pipeline_model.php` / `get_staff_pipeline_board()` | Helper drawer hiện query deal theo mọi status để dựng mini Kanban. | Loại bỏ nếu không còn caller sau thay đổi. |
| `modules/sales_pipeline/models/Sales_pipeline_model.php` / `get_staff_recent_activity()` | Helper chưa được dùng trong working tree đọc `activity_log`, trái nghĩa nghiệp vụ của Panel 2. | Loại bỏ helper thuộc cùng feature-attempt để tránh tái sử dụng sai; không thay thế bằng activity log khác. |
| `modules/sales_pipeline/views/_dashboard_staff_pipeline.php` | Giữ thông tin nhân viên và đúng ba KPI ở đầu; phần sau hiện là mini Kanban rồi reminder-response feed. Dynamic output đã phần lớn dùng `html_escape`, `_d`, `_dt`. | Giữ nguyên hai phần đầu; thay toàn bộ phần sau KPI bằng đúng hai panel mới. |
| `modules/sales_pipeline/assets/css/dashboard.css` | Có CSS cho drawer, mini Kanban và reminder feed; drawer mobile rộng 100%, content scroll dọc. | Xóa CSS mini Kanban không còn dùng; thêm/reuse CSS panel, badge, table wrapper và activity list, chống overflow ngang. |
| `modules/sales_pipeline/language/{english,vietnamese}/sales_pipeline_lang.php` | Có nhiều key Dashboard/reminder; working tree có nhóm key “Staff detail panels” nhưng một số text đang mô tả system activity. | Dùng/bổ sung key song ngữ đúng semantics, đặc biệt tiêu đề “Nhật ký hoạt động” phải là reminder-response history; không hardcode text hiển thị trong controller/model/view. |
| `modules/sales_pipeline/install.php` | Schema `sales_pipeline_reminders_log` đã có `pipeline_id`, `staff_id`, `message`, `staff_response`, `responded_at`, `sent_at`. | Không migration và không ghi DB. |
| Working tree | Repository đang bẩn với nhiều thay đổi/untracked file có sẵn, gồm cả Dashboard và các thay đổi Sales Pipeline khác. | Implementer chỉ sửa đúng hunk/file trong Plan, không reset/overwrite thay đổi unrelated. |

## Scope

### In scope

- Chỉnh endpoint drawer hiện hữu để lấy 10 deal mở gần nhất của đúng `staff_id`, kèm tổng số deal mở, và 20 log lời nhắc gần nhất qua `get_reminder_response_stats()`.
- Render đúng hai panel chỉ đọc sau ba KPI trong partial drawer.
- Panel 1 hiển thị total badge và bảng gồm tên deal, trạng thái, khách hàng, giá trị, ngày; tên deal chỉ là link khi quyền hiện hữu cho phép mở chi tiết deal, nếu không hiển thị text thuần.
- Panel 2 hiển thị trạng thái đã/chưa phản hồi, nội dung lời nhắc, phản hồi nhân viên nếu có, thời điểm gửi và thời điểm phản hồi nếu có.
- Empty state riêng cho Panel 1 và Panel 2.
- CSS responsive, không gây page/drawer overflow ngang; chỉ wrapper bảng Panel 1 được `overflow-x: auto` trên mobile.
- Xóa view/CSS/model wiring chỉ phục vụ mini Kanban drawer và helper `activity_log` sai semantics thuộc cùng feature-attempt.
- Cập nhật language keys English/Vietnamese phục vụ hai panel.

### Non-goals

- Không thay đổi `/admin/staff/member/{id}`, không dùng hook chèn vào Staff core.
- Không sửa `application/`, `system/`, core Perfex hoặc dependency.
- Không đổi Dashboard leaderboard, KPI hiện có, semantics status hoặc permission module.
- Không tạo endpoint, permission, migration hoặc bảng mới.
- Không tạo UI edit/delete/status transition/reminder response; không ghi dữ liệu CRM.
- Không gửi dữ liệu CRM ra hệ thống bên ngoài.
- Không sửa business behavior ngoài drawer này và các helper/CSS/language trực tiếp phục vụ nó.

## Required behavior

1. `dashboard.js` tiếp tục gọi `dashboard_staff_pipeline/{staff_id}` bằng AJAX khi bấm nhân viên.
2. Partial drawer giữ nguyên phần thông tin nhân viên và đúng ba thẻ KPI hiện có.
3. Ngay sau KPI là Panel 1 “Cơ hội kinh doanh đang mở”.
4. Panel 1 chỉ nhận deal của đúng staff đang xem và chỉ khi status join thỏa `is_won = 0` đồng thời `is_lost = 0`.
5. Total badge phản ánh toàn bộ số deal mở của nhân viên; bảng chỉ render tối đa 10 deal, sắp xếp `deal_date DESC`, rồi `id DESC` như helper hiện có.
6. Mỗi dòng Panel 1 có deal, status, khách hàng, giá trị, ngày. Màu status chỉ dùng nếu giá trị màu vượt allowlist hex hiện có/được validate; fallback an toàn. Link deal chỉ render khi `can_open_pipeline_deal` hiện hữu là true.
7. Ngay sau Panel 1 là Panel 2 “Nhật ký hoạt động”.
8. Panel 2 lấy tối đa 20 record từ `get_reminder_response_stats(['staff_id' => $staff_id, 'limit' => 20])`, tức `sales_pipeline_reminders_log` của đúng nhân viên.
9. Panel 2 hiển thị trạng thái dựa trên `staff_response !== null`, `message`, `staff_response` nếu có, `sent_at`, `responded_at` nếu có. Deal/customer chỉ là ngữ cảnh bổ trợ và link deal vẫn theo quyền hiện hữu.
10. Không query hoặc render `activity_log` cho Panel 2.
11. Sau Panel 2 không có mini Kanban, status columns, deal cards theo từng status, reminder-response feed thứ hai hay nội dung drawer cũ khác.
12. Hai panel không chứa form, button/action sửa/xóa, drag/drop, status control hoặc POST request.

## Data, permission and security

- Controller phải giữ nguyên thứ tự guard: AJAX-only → Dashboard access → staff ID hợp lệ → staff scope → staff active → query/render.
- Người chỉ có `view_own` gọi staff ID khác nhận 403 trước khi model query dữ liệu staff đó.
- Người có `view` toàn Dashboard/admin mới được xem drawer staff khác theo semantics hiện hữu.
- Không mở rộng `can_open_pipeline_deal`: chỉ admin, `edit` hoặc `view_deal_details` mới nhận link chi tiết như hiện trạng; còn lại thấy text chỉ đọc.
- Model dùng Query Builder, `db_prefix()`, integer-cast `staff_id`, bounded limits; không nối input người dùng vào raw SQL.
- Escape mọi output động bằng `html_escape`; dùng `_d()`/`_dt()` cho hiển thị ngày giờ rồi escape; status color phải validate allowlist trước khi đưa vào inline style.
- User-facing text dùng language dictionary English/Vietnamese; không hardcode ở controller/model.
- Không ghi DB, không migration, không external HTTP/AI data transfer.

## Changes by file

1. `modules/sales_pipeline/controllers/Sales_pipeline.php`
   - Cập nhật docblock endpoint từ mini Kanban sang drawer chi tiết chỉ đọc.
   - Gọi `get_staff_open_deals($staff_id, 10)`.
   - Giữ `get_reminder_response_stats()` với `staff_id` và limit 20, đổi biến view thành tên phản ánh activity/reminder history rõ ràng.
   - Bỏ `board` khỏi view data; truyền open-deal result và reminder history; giữ permission booleans hiện hữu.
2. `modules/sales_pipeline/models/Sales_pipeline_model.php`
   - Xác minh/hoàn thiện `get_staff_open_deals()` với cả hai cờ `is_won = 0 AND is_lost = 0`, total riêng và limit 10.
   - Giữ/tái sử dụng `get_reminder_response_stats()`; không nhân đôi query log.
   - Xóa `get_staff_pipeline_board()` nếu không còn caller.
   - Xóa `get_staff_recent_activity()` thuộc cùng feature-attempt vì dùng `activity_log` trái semantics và không được caller nào dùng.
3. `modules/sales_pipeline/views/_dashboard_staff_pipeline.php`
   - Giữ nguyên staff header và ba KPI.
   - Thay toàn bộ markup sau KPI bằng đúng hai `<section>` theo thứ tự bắt buộc.
   - Panel 1: header/title/total badge, table wrapper, 10 rows tối đa, link theo permission, empty state riêng.
   - Panel 2: tái cấu trúc reminder-response list hiện có thành panel duy nhất đúng tiêu đề/semantics và empty state riêng.
4. `modules/sales_pipeline/assets/css/dashboard.css`
   - Xóa selector mini Kanban/deal card không còn dùng trong drawer.
   - Thêm/reuse selector chung cho panel, table wrapper, total badge, status badge, activity list; `min-width: 0`, `max-width: 100%`, wrapping nội dung dài.
   - Đảm bảo chỉ vùng table scroll ngang ở mobile; drawer/page không overflow ngang.
5. `modules/sales_pipeline/language/english/sales_pipeline_lang.php`
6. `modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php`
   - Chuẩn hóa/bổ sung key tiêu đề Panel 1, Panel 2, nhãn cột/status/time/empty states/total badge cần thiết.
   - Vietnamese phải giữ đúng “Cơ hội kinh doanh đang mở” và “Nhật ký hoạt động”; không mô tả Panel 2 là system activity.

Không dự kiến đổi `dashboard.js` hoặc `dashboard.php`; chúng chỉ là điểm truy vết và phải giữ nguyên hành vi. Nếu implementer phát hiện cần đổi ngoài danh sách trên, dừng và trả về Plan/QC thay vì tự mở rộng.

## Failure states and rollback

- Staff ID thiếu/sai: JSON 400 với language message hiện hữu.
- Không đủ permission/staff scope: JSON 403, không query dữ liệu staff khác.
- Staff không tồn tại/không active: JSON 404.
- AJAX lỗi: drawer tiếp tục dùng loading/error state hiện hữu trong `dashboard.js`.
- Không có deal mở hoặc reminder log: mỗi panel có empty state riêng, panel còn lại vẫn render bình thường.
- Rollback: revert riêng các hunk của năm nhóm file trên và Plan artifacts; không reset working tree. Không có schema/data rollback vì không migration/ghi dữ liệu.

## Acceptance criteria

- [x] Bấm nhân viên tại `/admin/sales_pipeline/dashboard` mở drawer có đúng 2 panel sau KPI.
- [x] Drawer chỉ giữ thông tin nhân viên, ba KPI và hai panel; không còn mini Kanban, cột status, deal card theo từng status, reminder-response feed cũ hoặc nội dung drawer cũ bên dưới.
- [x] Panel 1 chỉ chứa deal có `is_won = 0 AND is_lost = 0` của đúng `staff_id`.
- [x] Panel 1 có total badge, tối đa 10 deal gần nhất, trạng thái, khách hàng, giá trị, ngày và link deal theo quyền hiện hữu.
- [x] Panel 2 chỉ chứa lịch sử phản hồi lời nhắc từ `sales_pipeline_reminders_log` của đúng `staff_id`.
- [x] Panel 2 hiển thị trạng thái phản hồi, nội dung lời nhắc, phản hồi nếu có, thời điểm gửi và phản hồi nếu có.
- [x] Không dùng `activity_log` cho Panel 2 và không còn helper drawer mới dựa trên `activity_log`.
- [x] Empty state riêng cho panel deal và panel phản hồi lời nhắc.
- [x] `view_own` không xem được dữ liệu của nhân viên khác; quyền `view`/admin theo semantics hiện hữu mới xem được staff khác.
- [x] Không có panel mới tại `/admin/staff/member/{id}`; không hook Staff core.
- [x] Không sửa core Perfex, không migration, không ghi CRM, không làm mất thay đổi unrelated.
- [x] Không có thao tác sửa/xóa/status/reminder response trong hai panel.
- [x] Drawer/page không overflow ngang; trên mobile chỉ table wrapper Panel 1 scroll ngang.
- [x] PHP lint cho mọi PHP file đổi, `git diff --check`, targeted static checks và browser verification đều PASS.
- [x] Browser desktop và mobile đều PASS, drawer AJAX hoạt động và console error bằng 0.

Verifier runtime evidence: session Chrome đã đăng nhập mở drawer qua AJAX thành công; desktop có 1 staff header, đúng 3 KPI, đúng 2 panel, total badge `27`, 10 dòng deal mở, 2 reminder-log item, 0 mini Kanban và 0 write control. Ở breakpoint mobile thực tế 520px, page/drawer không overflow ngang và table wrapper scroll nội bộ (`437px < 540px`). Console ngay trong lượt mở/kiểm tra drawer không có lỗi từ Sales Pipeline; một lỗi nền muộn từ plugin `prchat` được ghi nhận là unrelated, ngoài các file và luồng của feature này.

## Validation commands

```bash
git status --short
php -l modules/sales_pipeline/controllers/Sales_pipeline.php
php -l modules/sales_pipeline/models/Sales_pipeline_model.php
php -l modules/sales_pipeline/views/_dashboard_staff_pipeline.php
php -l modules/sales_pipeline/language/english/sales_pipeline_lang.php
php -l modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php
git diff --check
rg -n "get_staff_pipeline_board|sp-mini-kanban|sp-mini-deal|get_staff_recent_activity|activity_log" modules/sales_pipeline/controllers/Sales_pipeline.php modules/sales_pipeline/models/Sales_pipeline_model.php modules/sales_pipeline/views/_dashboard_staff_pipeline.php modules/sales_pipeline/assets/css/dashboard.css
rg -n "is_won.*0|is_lost.*0|get_reminder_response_stats|sales_pipeline_reminders_log" modules/sales_pipeline/controllers/Sales_pipeline.php modules/sales_pipeline/models/Sales_pipeline_model.php
```

Browser verification tại `http://localhost:8000/admin/sales_pipeline/dashboard`:

- Desktop: mở drawer, đếm đúng hai panel sau KPI, kiểm tra row/field/link/empty states và không có control ghi dữ liệu.
- Mobile viewport: drawer rộng trong viewport, page không overflow ngang, chỉ bảng Panel 1 scroll ngang.
- Network: request drawer là AJAX tới `dashboard_staff_pipeline/{staff_id}` và thành công trong scope được phép.
- Console: không có JavaScript error.
- Permission: dùng session/role khả dụng để xác minh `view_own` chỉ thấy/gọi chính mình; nếu không có credential role thứ hai, ghi rõ static guard đã xác minh và phần browser chưa thể thực hiện, không tuyên bố đã test runtime.

## Open questions

- Không có. Basecode đủ để suy ra giới hạn hợp lý: 10 deal mở (helper hiện hữu) và 20 reminder log (helper/controller hiện hữu).

## QC history

| Plan version | QC result | Delta incorporated |
|---|---|---|
| 1 | PASS | Gemini xác nhận Plan khớp ý định; Codex yêu cầu sửa lại bằng chứng QC để phân biệt rõ basecode hiện tại với trạng thái sau triển khai. Hai technical note mức Low (regex allowlist màu và language key Panel 2) đã có sẵn trong Plan, không cần đổi semantics hoặc tăng version. |
