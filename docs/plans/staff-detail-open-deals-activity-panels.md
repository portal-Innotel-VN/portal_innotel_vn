# Feature Correction Plan: Hai panel trong drawer chi tiết nhân viên của Sales Pipeline Dashboard

## Metadata

- plan_id: `staff-detail-open-deals-activity-panels`
- plan_version: `2`
- plan_status: `READY_FOR_QC`
- qc_status: `PENDING`
- verification_status: `PENDING`
- planner: `Codex`
- plan_owner: `Codex`
- orchestrator: `Codex`
- qc_reviewer: `Gemini 3.6 Flash (High)`
- implementer: `Gemini 3.6 Flash (High)`
- final_verifier: `Codex`
- updated_at: `2026-08-08`

## Correction reason

Plan version 1 ánh xạ sai cụm từ “Thông tin chi tiết nhân viên” sang route core `admin/staff/member/{id}`. Ảnh người dùng và luồng thao tác thực tế xác nhận bề mặt đúng là drawer `#sp-dashboard-drawer` mở từ nút `.js-sp-open-staff` tại `admin/sales_pipeline/dashboard`.

Hậu quả của sai phạm vi: version 1 đã thêm hai panel vào trang chỉnh sửa nhân viên, còn drawer trong ảnh vẫn render mini Kanban theo tất cả trạng thái và feed phản hồi nhắc nhở cũ. Runtime verification version 1 vì thế không kiểm tra đúng acceptance surface và bị thu hồi.

## Version 1 rollback status

- status: `ROLLED_BACK`
- rolled_back_at: `2026-08-08`
- executor: `Codex`, theo ủy quyền trực tiếp của người dùng cho riêng rollback này.
- Đã gỡ hooks `staff_member_edit_view_profile`, `app_admin_head`, `app_admin_footer` và toàn bộ callback chỉ phục vụ route `admin/staff/member/{id}` khỏi `modules/sales_pipeline/sales_pipeline.php`.
- Đã xóa `modules/sales_pipeline/views/_staff_detail_panels.php` và `modules/sales_pipeline/assets/css/staff-detail-panels.css`.
- Không sửa file core trong `application/` hoặc `system/`; không rollback query model/language key có thể tái sử dụng cho đúng drawer Dashboard.
- Verification: PHP lint `PASS`, `git diff --check` `PASS`, repository search không còn symbol/asset/class của staff-detail injection.

Skill `improve-codebase-architecture` do người dùng yêu cầu không có trong catalog hiện hành hoặc `.agents/skills/`. Plan áp dụng fallback theo architecture/rules của repo: sửa đúng module-owned endpoint/model/partial/CSS, tái sử dụng query tập trung, không hook vào core Staff và không nhân đôi data flow.

## Goal

Trong drawer “Thông tin chi tiết” của Sales Pipeline Dashboard, ngay sau header nhân viên và ba KPI, hiển thị đúng hai panel:

1. `Cơ hội kinh doanh đang mở`: chỉ deal của nhân viên có status `is_won = 0 AND is_lost = 0`.
2. `Nhật ký hoạt động hệ thống gần nhất`: system activity của chính nhân viên đó theo `activity_log.staffid`.

Không đặt hai panel này tại `admin/staff/member/{id}`.

## Reproduction and root-cause evidence

### Red-capable browser loop

Trên Chrome profile Ruvik AX, mở `admin/sales_pipeline/dashboard`, click nút đầu tiên `.js-sp-open-staff`, rồi assert hai heading trong `#sp-dashboard-drawer`.

Kết quả lặp lại hai lần:

```text
expected: Cơ hội kinh doanh đang mở; Nhật ký hoạt động hệ thống gần nhất
actual:   các cột status mini Kanban; Nhật ký hoạt động
verdict:  FAIL
```

Đây là feedback loop bắt đúng triệu chứng người dùng, chạy trong vài giây và sẽ được Codex chạy lại sau triển khai.

### Confirmed root cause

| Evidence | Kết luận |
|---|---|
| `modules/sales_pipeline/views/dashboard.php:154-159` | Nút nhân viên dùng `.js-sp-open-staff` và `data-staff-id`. |
| `modules/sales_pipeline/assets/js/dashboard.js:32-84,102-104` | Drawer gọi AJAX tới `dashboard_staff_pipeline/{staff_id}` và inject HTML vào `[data-dashboard-drawer-content]`. |
| `modules/sales_pipeline/controllers/Sales_pipeline.php:141-184` | Endpoint dựng dữ liệu riêng gồm `board` và `actionable_feed`, rồi render `_dashboard_staff_pipeline`. Không gọi hook Staff. |
| `modules/sales_pipeline/views/_dashboard_staff_pipeline.php:79-224` | Drawer hiện render mini Kanban mọi status và reminder-response feed. |
| `modules/sales_pipeline/sales_pipeline.php:21-23,124-212` | Version 1 chỉ hook route core Staff; đây là code sai bề mặt cần gỡ. |
| `Sales_pipeline_model.php:1779-1857` | Hai query `get_staff_open_deals()` và `get_staff_recent_activity()` đã có đúng semantics và có thể tái sử dụng tại endpoint drawer. |

## Scope

### In scope

- Sửa AJAX endpoint `dashboard_staff_pipeline/{staff_id}` để cấp open deals, total và recent activity cho drawer.
- Giữ nguyên header nhân viên và ba KPI đang có trong drawer.
- Thay phần mini Kanban mọi status và reminder-response feed bằng đúng hai panel sau KPI.
- Open deals: tổng số ở badge; render tối đa 10 deal theo `deal_date DESC, id DESC`; table responsive gồm deal, khách hàng, trạng thái, giá trị, ngày.
- Activity: tối đa 20 row theo `date DESC, id DESC`; timeline gọn và empty state.
- Full admin được query/xem system activity. Người dùng Dashboard không phải full admin không được query nội dung `activity_log`; panel activity hiển thị trạng thái không đủ quyền được dịch, không làm lộ dữ liệu.
- Reuse permission/scope hiện tại của endpoint: người chỉ có `view_own` không thể yêu cầu staff khác.
- Gỡ toàn bộ hook, partial và CSS version 1 chỉ phục vụ sai route `admin/staff/member/{id}`.
- UI desktop/mobile trong drawer; table chỉ scroll nội bộ, drawer/page không overflow ngang.

### Non-goals

- Không sửa core `application/` hoặc `system/`.
- Không migration hoặc ghi dữ liệu CRM.
- Không thay đổi Dashboard leaderboard/KPI bên ngoài drawer.
- Không thay đổi semantics status, quyền module hoặc endpoint URL.
- Không refactor/xóa `get_staff_pipeline_board()` hay reminder helpers ngoài việc ngừng dùng chúng trong endpoint drawer; tránh mở rộng ảnh hưởng không cần thiết.
- Không thay đổi dữ liệu deal/activity để tạo fixture.
- Không gửi dữ liệu CRM ra hệ thống bên ngoài.

## Required behavior and data flow

1. `dashboard.js` tiếp tục gọi endpoint hiện hữu và inject HTML; không cần endpoint hay request mới.
2. Controller giữ nguyên AJAX guard, `can_access_dashboard()`, numeric staff ID, own/all staff scope và active-staff guard.
3. Controller gọi `get_staff_open_deals($staff_id, 10)`.
4. Controller chỉ gọi `get_staff_recent_activity($staff_id, 20)` khi `is_admin()`; non-admin nhận `can_view_staff_activity = false` và không query bảng audit.
5. Controller truyền `open_deals`, `total_open_deals`, `recent_activity`, `can_view_staff_activity`, currency và quyền mở deal vào `_dashboard_staff_pipeline.php`; không truyền `board`/`actionable_feed` nữa.
6. Partial giữ staff header/KPI, sau đó render hai `<section>` có heading semantic và class namespace `sp-drawer-detail-*`.
7. Dữ liệu động được escape; status color chỉ dùng nếu đúng hex, link deal chỉ render khi viewer có quyền hiện hữu.
8. CSS đặt trong `assets/css/dashboard.css`, vì đây là asset đã được route Dashboard load. Không tạo loader/hook mới.

## Permission and failure behavior

- Invalid/non-numeric staff ID, inactive/missing staff, non-AJAX hoặc vượt staff scope giữ nguyên HTTP/JSON behavior hiện tại.
- Nếu không có open deals: badge `0` và empty state deal.
- Nếu full admin nhưng không có activity: badge `0` và empty state activity.
- Nếu non-admin: không query audit log; activity panel hiển thị localized permission-safe state, không giả vờ là “không có dữ liệu”.
- Nếu bảng `activity_log` không tồn tại: controller không làm hỏng drawer; coi activity unavailable và render empty/unavailable state an toàn. `sales_pipeline`/status table là dependency module hiện hữu.
- Không render code version 1 trên `admin/staff/member/{id}` sau correction.

## UI design

- Drawer toolbar/header và KPI giữ nguyên như ảnh.
- Hai panel xếp dọc sau KPI, surface trắng, border/radius/shadow đồng bộ `dashboard.css`.
- Panel open deals xuất hiện trước, panel activity ngay sau; không để activity nằm sau một Kanban rất dài.
- Header panel gồm icon, heading và badge.
- Bảng open deals có header sticky/nhẹ nếu phù hợp CSS hiện hữu; trên mobile bọc vùng scroll ngang, không làm drawer rộng hơn viewport.
- Activity dùng timeline; description wrap và không khóa chiều cao.
- Empty/unavailable state có icon và text ngắn, không dùng trạng thái lỗi đỏ nếu dữ liệu chỉ rỗng.
- Không hiển thị mini Kanban mọi status hoặc reminder-response feed trong drawer sau correction.

## Changes by file

| File | Required delta |
|---|---|
| `docs/plans/staff-detail-open-deals-activity-panels.md` | Plan correction v2 và bằng chứng root cause/runtime. |
| `docs/plans/staff-detail-open-deals-activity-panels.qc.md` | Gemini chỉ ghi QC delta cho v2. |
| `docs/plans/staff-detail-open-deals-activity-panels.implementation.md` | Gemini cập nhật correction, exact diff và test results. |
| `modules/sales_pipeline/controllers/Sales_pipeline.php` | Rewire drawer endpoint từ board/reminder feed sang open deals/activity với permission/table guard. |
| `modules/sales_pipeline/views/_dashboard_staff_pipeline.php` | Giữ header/KPI; thay content cũ bằng đúng hai panel. |
| `modules/sales_pipeline/assets/css/dashboard.css` | Style scoped cho hai panel và responsive drawer. |
| `modules/sales_pipeline/models/Sales_pipeline_model.php` | Giữ/tinh chỉnh hai query v1 nếu QC phát hiện gap; không nhân đôi query. |
| `modules/sales_pipeline/language/{english,vietnamese}/sales_pipeline_lang.php` | Reuse key v1; thêm key permission/unavailable nếu cần; không duplicate. |
| `modules/sales_pipeline/sales_pipeline.php` | Gỡ ba hooks và các helper/callback version 1 cho route Staff. |
| `modules/sales_pipeline/views/_staff_detail_panels.php` | Xóa partial sai bề mặt version 1. |
| `modules/sales_pipeline/assets/css/staff-detail-panels.css` | Xóa stylesheet sai bề mặt version 1. |

Nếu cần sửa file ngoài bảng này hoặc thay đổi schema/permission semantics, implementer phải dừng và báo Plan gap.

## Acceptance criteria

- [ ] AC1 — Trên `admin/sales_pipeline/dashboard`, click nhân viên mở drawer và thấy đúng hai panel sau KPI: open deals trước, activity sau.
- [ ] AC2 — Drawer không còn mini Kanban mọi status hoặc reminder-response feed cũ.
- [ ] AC3 — Open-deal panel chỉ lấy `staff_id` đang xem và status `is_won = 0 AND is_lost = 0`; badge là tổng, list tối đa 10 và sort đúng.
- [ ] AC4 — Full admin activity panel chỉ lấy `activity_log.staffid` của nhân viên, tối đa 20, sort mới nhất; description escaped.
- [ ] AC5 — Staff có dữ liệu hiển thị table/link/status/value/date đúng; staff ID 7 trong dataset hiện tại hiển thị hai empty state độc lập cho deal/activity.
- [ ] AC6 — Non-admin không thể đọc activity log và không thể dùng endpoint lấy staff ngoài scope; panel đưa trạng thái permission-safe.
- [ ] AC7 — Desktop và mobile drawer không overflow ngang ngoài table scroll; headings, badge và empty state đọc được.
- [ ] AC8 — `admin/staff/member/{id}` không còn fragment/panel version 1; không còn hooks/assets sai route.
- [ ] AC9 — Text mới có EN/VI, output động escape, status color validate, không duplicate language key.
- [ ] AC10 — Không sửa core/migration/unrelated changes; PHP lint và `git diff --check` PASS; browser regression loop chuyển từ FAIL sang PASS, console error bằng 0.

## Verification commands

```bash
git diff --check
php -l modules/sales_pipeline/controllers/Sales_pipeline.php
php -l modules/sales_pipeline/models/Sales_pipeline_model.php
php -l modules/sales_pipeline/sales_pipeline.php
php -l modules/sales_pipeline/views/_dashboard_staff_pipeline.php
php -l modules/sales_pipeline/language/english/sales_pipeline_lang.php
php -l modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php
rg -n "staff_member_edit_view_profile|sales_pipeline_staff_detail|sp-staff-detail" modules/sales_pipeline
rg -n "get_staff_open_deals|get_staff_recent_activity|is_won|is_lost|activity_log" modules/sales_pipeline
```

Browser regression on Chrome profile Ruvik AX:

- Dashboard staff ID 1: exact two requested headings, open-deal badge/list limit, no legacy Kanban/feed, no console error.
- Dashboard staff ID 7: deal/activity empty states.
- Mobile `390x844`: no page/drawer overflow; table internal scroll.
- Direct `admin/staff/member/1`: zero `.sp-staff-detail-panel` and no version-1 heading.

## Regression test seam

Repo không có browser/E2E harness hoặc test suite cho AJAX-rendered drawer. Không thêm một static grep test giả tạo vì nó không bắt được lỗi surface này. Regression được khóa bằng browser DOM assertion chạy trên actual endpoint/drawer; thiếu E2E seam được ghi nhận là architecture debt, nhưng không mở rộng task để dựng framework test mới.

## Rollback

Revert controller/partial/dashboard CSS/language delta của v2 và khôi phục version 1 files/hooks nếu cần quay lại trạng thái trước correction. Không có data rollback vì không migration và không write.

## QC history

| Plan version | QC result | Delta incorporated |
|---|---|---|
| 1 | PASS (invalidated) | QC đúng với Plan v1 nhưng Plan v1 nhắm sai UI surface; runtime verification bị thu hồi. |
| 2 | PENDING | Corrected target to Dashboard staff drawer based on user screenshot and deterministic browser reproduction. |
