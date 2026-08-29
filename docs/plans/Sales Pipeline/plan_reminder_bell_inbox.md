# Kế hoạch Reminder Inbox trong Notification Bell — Không sửa Core Perfex

> **Module**: `sales_pipeline`  
> **Ngày lập**: 2026-08-28  
> **Trạng thái**: Đã triển khai mã nguồn; feature flag mặc định `0`, chờ kiểm thử DB/UI và canary trước khi bật  
> **Phạm vi**: CRM Web UI channel của Reminder; Email không thay đổi  
> **Ràng buộc cứng**: Không sửa file trong `application/`, `system/`, asset hoặc
> view Core Perfex; không override `Misc_model`; không xóa Reminder Repository,
> Delivery Log hoặc Activity Log.

## 1. Mục tiêu

Biến phần Reminder trong Notification Bell thành một **hộp việc cần xử lý**:

- Informational chỉ tồn tại đến khi nhân viên xác nhận bằng nút `✕`;
- Actionable tiếp tục hiển thị cho đến khi nhân viên gửi phản hồi hợp lệ;
- Reminder đã xử lý không chiếm chỗ trong danh sách đang chờ;
- Email tiếp tục hoạt động độc lập;
- toàn bộ reminder, delivery và phản hồi vẫn được lưu để audit;
- không phụ thuộc giới hạn tối đa 30 dòng của dropdown notification Core Perfex;
- không thay đổi hành vi notification của các module Perfex khác.

## 2. Context và bằng chứng Basecode

### 2.1 Core Perfex hiện tại

- `application/models/Misc_model.php::get_user_notifications()`:
  - giới hạn mặc định là 15;
  - tăng theo số chưa đọc nhưng chặn tối đa 30;
  - lấy theo `date DESC`;
  - không loại bản ghi `isread_inline = 1` khỏi kết quả.
- Mở dropdown gọi `set_notifications_read()` và cập nhật `isread = 1` cho tất
  cả notification của Staff.
- Bấm từng notification gọi `set_notification_read_inline()` và cập nhật
  `isread_inline = 1`, nhưng dòng vẫn tiếp tục được lấy ở lần tải sau.
- Core không có hook để module thêm điều kiện vào query trước `LIMIT 30`.

### 2.2 Sales Pipeline hiện tại

- `Reminder_engine.php` gọi `add_notification()` cho delivery `channel='crm'`.
- Notification dùng:
  - `description = 'sales_pipeline_rule_reminder'`;
  - link `sales_pipeline/reminder_response/{reminder_id}`.
- Reminder Repository là `tblsales_pipeline_reminders_log`.
- Delivery audit/outbox là `tblsales_pipeline_reminder_deliveries`.
- `submit_reminder_response()` ghi `staff_response`, `responded_at` và chỉ gọi
  `add_activity()` khi reminder có `pipeline_id`.

### 2.3 Kết luận kỹ thuật

Chỉ thêm nút `✕` và ẩn DOM không giải quyết triệt để vì dòng sẽ trở lại sau
reload/AJAX, tiếp tục chiếm slot trong top 30 và không đồng bộ giữa thiết bị.
Do Core không có query hook phù hợp, giải pháp module-only phải ngừng dùng
`tblnotifications` làm nguồn hiển thị cho Reminder mới.

## 3. Invariant bắt buộc

1. CRM vẫn là nơi phản hồi chính thức; Email chỉ dẫn người dùng về CRM.
2. Không xóa dữ liệu trong `tblsales_pipeline_reminders_log`.
3. Không xóa delivery audit trong `tblsales_pipeline_reminder_deliveries`.
4. Informational và Actionable có vòng đời khác nhau.
5. Actionable chưa phản hồi không được phép dismiss bằng `✕`.
6. Staff chỉ được đọc hoặc xác nhận reminder thuộc chính mình; Admin không được
   giả danh Staff qua endpoint Bell.
7. Dashboard Activity Feed tiếp tục đọc Reminder Repository độc lập với Bell.
8. Deal Activity Timeline chỉ nhận `add_activity()` sau phản hồi hợp lệ.
9. Không sửa Core Perfex để tránh xung đột khi nâng cấp.

## 4. Quyết định kiến trúc

### 4.1 Reminder Inbox do module sở hữu

Sales Pipeline tạo một khu vực Reminder Inbox và chèn khu vực này vào **cùng
dropdown Notification Bell** bằng JavaScript/CSS được đăng ký qua
`app_admin_footer` và `app_admin_head` của module.

Core Bell vẫn giữ nguyên cho Task, Ticket, Project, Estimate native và các
notification khác. Reminder Inbox lấy dữ liệu từ endpoint của Sales Pipeline,
không đọc danh sách 30 dòng đã render bởi `Misc_model`.

### 4.2 Không tạo Notification Core cho Reminder mới

Sau cutover, CRM adapter không gọi `add_notification()` cho Sales Pipeline
Reminder mới. Delivery `channel='crm'` được coi là thành công khi reminder đã
đủ điều kiện xuất hiện trong Reminder Inbox và delivery được đánh dấu `sent`.

Nhờ đó:

- Reminder mới không chiếm top 30 của Core;
- lỗi hoặc lịch sử notification Core không ảnh hưởng Work Queue;
- Email dispatcher và retry không thay đổi;
- delivery log vẫn chứng minh CRM channel đã được phát hành.

### 4.3 Trạng thái hiển thị

Không dùng `isread` làm trạng thái nghiệp vụ. Bổ sung vào Reminder Repository:

| Cột | Kiểu | Ý nghĩa |
|---|---|---|
| `acknowledged_at` | `datetime NULL` | Thời điểm Staff xác nhận Informational |
| `acknowledged_by` | `int(11) NULL` | Staff thực hiện xác nhận |

Không tạo bảng mới. Migration phải nằm trong module và có số version mới; không
sửa migration đã tồn tại hoặc có khả năng đã chạy.

Điều kiện Work Queue:

```text
Actionable:
    response_required = 1
    AND staff_response IS NULL

Informational:
    response_required = 0
    AND acknowledged_at IS NULL

Điều kiện chung:
    staff_id = current_staff_id
    AND CRM delivery tương ứng đã ở trạng thái sent
```

Reminder `responded` hoặc `acknowledged` không còn trong Bell nhưng vẫn còn đầy
đủ trong Repository, Dashboard feed và Delivery audit.

## 5. Luồng mục tiêu

```text
Rule Engine
    │
    ├─> Reminder Repository
    │       │
    │       ├─> delivery email ─> Email độc lập
    │       │
    │       └─> delivery crm = sent
    │                │
    │                └─> Reminder Inbox endpoint
    │                         │
    │                         └─> Module JS chèn vào cùng dropdown Bell
    │
    ├─ Informational ─> bấm ✕ ─> acknowledge ─> biến mất khỏi Inbox
    │
    └─ Actionable ─> mở Quick Response ─> gửi phản hồi
                                      ├─> staff_response/responded_at
                                      ├─> add_activity nếu là Deal
                                      └─> biến mất khỏi Inbox
```

## 6. API module

### 6.1 Đọc Reminder Inbox

```text
GET /admin/sales_pipeline/reminder_bell_feed
```

Yêu cầu:

- chỉ chấp nhận Staff đã đăng nhập;
- query luôn ép `staff_id = get_staff_user_id()`;
- không nhận `staff_id` tùy ý từ request;
- chỉ lấy delivery CRM đã `sent`;
- giới hạn tối đa 30 mục đang chờ của module;
- thứ tự: severity rồi thời gian mới nhất; không ép Actionable lên trước vì có
  thể làm Informational bị đói trong giới hạn 30 mục;
- trả về `items`, `pending_count` và HTML partial đã escape hoặc JSON schema cố
  định; không trả raw `snapshot_json` không cần thiết;
- link entity và quick response phải được tạo server-side theo permission.

### 6.2 Xác nhận Informational

```text
POST /admin/sales_pipeline/reminder_bell_acknowledge/{reminder_id}
```

Yêu cầu:

- AJAX + CSRF. Khi tự tạo payload POST, dùng đúng cấu trúc Basecode:

  ```javascript
  var payload = {};
  payload[csrfData.token_name] = csrfData.hash;
  ```

  Có thể dùng `csrfData.formatted` khi request không có payload nghiệp vụ;
  không dùng các property không tồn tại như
  `csrfData.formatted.csrf_token_name` hoặc
  `csrfData.formatted.csrf_token_hash`. Basecode hiện đặt
  `csrf_regenerate = false`, vì vậy runtime hiện tại không yêu cầu thay hash sau
  mỗi POST. Nếu môi trường tương lai bật regenerate, API phải trả token mới bằng
  contract rõ ràng rồi module mới cập nhật `csrfData`;
- reminder tồn tại và thuộc `get_staff_user_id()`;
- `response_required = 0`;
- update nguyên tử với `acknowledged_at IS NULL`;
- ghi `acknowledged_at = NOW()` và `acknowledged_by`;
- gọi lặp lại trả kết quả idempotent;
- Actionable trả `422`, không được acknowledge bằng endpoint này;
- không nhận nội dung phản hồi tại endpoint acknowledge.

### 6.3 Phản hồi Actionable

Giữ nguyên route Quick Response hiện tại. Sau khi
`submit_reminder_response()` commit thành công, lần fetch Inbox tiếp theo tự
loại reminder vì `staff_response IS NOT NULL`.

## 7. UI trong cùng Notification Bell

### 7.1 Asset module toàn Admin

Tạo asset riêng, ví dụ:

- `assets/js/reminder_bell.js`;
- `assets/css/reminder_bell.css`;
- `views/partials/reminder_bell_items.php` nếu render server-side.

Đăng ký asset qua hook module trên mọi Admin page khi Staff đăng nhập và có
quyền dùng Sales Pipeline. Không sửa:

- `application/views/admin/includes/notifications.php`;
- `assets/js/main.js`;
- `application/models/Misc_model.php`.

### 7.2 Vị trí và hành vi

- Wrapper Core thực tế là
  `li.notifications-wrapper.header-notifications`; không sử dụng selector không
  tồn tại như `li.dropdown-notifications`. Module JS tìm dropdown con
  `.notifications` từ wrapper này sau khi DOM sẵn sàng.
- Chỉ chèn danh sách Reminder có namespace `sp-reminder-bell-*` khi feed thành
  công và có item. Không tạo header/panel thứ hai như “Việc cần xử lý”, không
  thay thế hoặc di chuyển notification Core.
- Fetch một lần khi tải trang để có pending indicator.
- Bind event có namespace vào đúng Bootstrap 3 wrapper, ví dụ
  `show.bs.dropdown.spReminderBell`, để fetch lại khi dropdown mở; fetch lại sau
  acknowledge/response navigation.
- Không khẳng định Core có AJAX polling định kỳ. Basecode hiện chỉ chứng minh
  `fetch_notifications()` có thể thay toàn bộ HTML bên trong wrapper và được gọi
  bởi Pusher khi realtime notification được bật; plugin/custom script cũng có
  thể gọi hàm này.
- Loading, empty, lỗi/timeout/403/500 đều **silent fail** trong dropdown: gỡ danh
  sách Module Inbox, xóa Pulse Dot của module và giữ nguyên Bell Core. Lỗi kỹ
  thuật không được chiếm diện tích hoặc hiển thị như một notification nghiệp vụ.
- Informational hiển thị nút `✕` với label/tooltip rõ nghĩa “Đã nắm thông tin”.
- Actionable không hiển thị `✕`; hiển thị CTA “Phản hồi”.
- Khi acknowledge thành công: animate remove và cập nhật module pending count;
  khi hết item thì gỡ danh sách module. Khi API acknowledge lỗi phải phục hồi item.
- Không tự giảm hoặc ghi đè badge `.icon-notifications` của Core Perfex.
- Module dùng indicator/count riêng trong cùng Bell để tránh sai lệch giữa
  “Core unread” và “Reminder pending”.
- Pending indicator phải sống trên wrapper thay vì là state chỉ nằm trong HTML
  mà Core có thể thay thế. JS đặt class/data attribute, ví dụ:

  ```javascript
  $wrapper
      .toggleClass('has-sp-reminder-pending', pendingCount > 0)
      .attr('data-sp-reminder-count', pendingCount);
  ```

  CSS render Pulse Dot hoặc count từ
  `.notifications-wrapper.header-notifications.has-sp-reminder-pending`; không
  append state duy nhất vào `span.icon-notifications` của Core. Vì Core chỉ
  thay nội dung bên trong wrapper, class/data attribute trên wrapper vẫn tồn tại
  sau re-render.
- Hỗ trợ keyboard, focus state, `aria-live`, reduced motion và mobile width.

### 7.3 Chống duplicate DOM

Asset phải idempotent:

- dùng `MutationObserver` trên `li.notifications-wrapper.header-notifications`
  để phát hiện Core/Pusher thay child HTML, sau đó debounce và mount lại section
  nếu bị mất; không monkey-patch `fetch_notifications()`;
- không chèn section lần hai khi Core/Pusher hoặc script khác render lại nội
  dung wrapper;
- nếu re-render xảy ra khi dropdown đang mở, observer phải phục hồi section và
  refresh feed mà không yêu cầu người dùng đóng/mở lại;
- abort/ignore response cũ khi có request mới;
- event handler dùng namespace;
- dữ liệu mới thay thế section module, không append vô hạn.

## 8. Cutover và dữ liệu legacy

### 8.1 Reminder mới

Từ thời điểm bật feature flag, Reminder CRM mới chỉ xuất hiện trong Module
Inbox và không tạo thêm `tblnotifications`.

Đề xuất option rollout:

```text
sp_reminder_crm_inbox_enabled = 0|1
```

- `0`: hành vi hiện tại dùng `add_notification()`;
- `1`: hành vi mới dùng Module Inbox.

Chỉ bật production sau khi endpoint, UI, permission và delivery health đạt.

### 8.2 Notification Core cũ

Các dòng `tblnotifications` cũ phải được giữ nguyên theo ràng buộc không xóa.
Module JS chỉ được ẩn bản sao legacy sau khi Reminder Inbox fetch thành công và
response xác nhận đúng `reminder_id` đang được render trong Module Inbox. Với
mỗi ID đã xác nhận, script mới tìm link
`sales_pipeline/reminder_response/{reminder_id}` và ẩn `li.notification-wrapper`
tương ứng. Không dùng wildcard để ẩn mù toàn bộ Sales Pipeline notification.

Nếu feed lỗi, timeout hoặc response không hợp lệ, phải giữ Core notification làm
fallback. Logic dedupe phải chạy lại sau mỗi lần `MutationObserver` phát hiện
Core/Pusher re-render. Giải pháp này vẫn phải ghi rõ giới hạn:

- legacy row vẫn tạm thời chiếm slot Core top 30;
- vấn đề này giảm dần vì không có Reminder mới ghi vào Core;
- không được tuyên bố “đã dọn sạch top 30 lịch sử” khi không xóa hoặc sửa query
  Core;
- nếu doanh nghiệp yêu cầu cleanup tức thời, cần một quyết định riêng cho phép
  archive/delete **projection** `tblnotifications`; việc đó nằm ngoài kế hoạch
  này.

## 9. Delivery và quan sát

- CRM delivery vẫn có `status`, `attempt_count`, `sent_at`, `last_error`.
- CRM adapter phải kiểm tra recipient Staff còn active trước khi đánh dấu sent.
- Không đánh dấu delivery sent nếu Reminder Repository thiếu dữ liệu bắt buộc.
- Delivery Health phải phân biệt:
  - CRM Inbox published;
  - Email sent/failed/retry;
  - không gọi CRM Inbox là `add_notification` sau cutover.
- Feature flag rollback không được materialize lại notification cho reminder đã
  có CRM delivery sent, tránh duplicate; rollback chỉ áp dụng delivery mới.

## 10. File dự kiến ảnh hưởng

Chỉ trong module và tài liệu:

- `modules/sales_pipeline/sales_pipeline.php` — hook asset/feature flag;
- `modules/sales_pipeline/controllers/Sales_pipeline.php` — feed và acknowledge;
- `modules/sales_pipeline/models/Sales_pipeline_model.php` — query Inbox và
  atomic acknowledge;
- `modules/sales_pipeline/libraries/Reminder_engine.php` — CRM adapter cutover;
- `modules/sales_pipeline/includes/reminder_repository_schema.php` — bootstrap
  idempotent cho cột mới;
- `modules/sales_pipeline/includes/reminder_rule_defaults.php` — feature flag;
- migration version mới trong `modules/sales_pipeline/migrations/`;
- JS/CSS/view partial mới dưới module;
- language file tiếng Việt và tiếng Anh;
- contract/unit/integration tests dưới `modules/sales_pipeline/tests/`.

Danh sách cấm sửa:

- `application/models/Misc_model.php`;
- `application/views/admin/includes/notifications.php`;
- `application/controllers/admin/Misc.php`;
- `assets/js/main.js` và asset Core Perfex;
- mọi file trong `system/` hoặc dependency/vendor.

## 11. Trình tự triển khai

Nhật ký triển khai và phạm vi xác minh thực tế được lưu tại
[`plan_reminder_bell_inbox.implementation.md`](plan_reminder_bell_inbox.implementation.md).
Các phase dưới đây vẫn là checklist rollout; hoàn thành mã nguồn không đồng nghĩa
đã hoàn thành DB snapshot, UI thủ công hoặc production canary.

### Phase 0 — Contract tests trước cutover

1. Test định nghĩa trạng thái visible/hidden cho Informational và Actionable.
2. Test staff scope và permission.
3. Test duplicate fetch/acknowledge idempotency.
4. Test feature flag mặc định `0` để không đổi runtime trước khi sẵn sàng.

### Phase 1 — Schema và repository

1. Tạo migration module version mới.
2. Thêm bootstrap idempotent cho hai cột acknowledge và index cần thiết.
3. Thêm model query feed và atomic acknowledge.
4. Chạy migration/test trên database snapshot, không chỉ source test.

### Phase 2 — API

1. Tạo feed endpoint và acknowledge endpoint.
2. Kiểm tra CSRF, AJAX, ownership, inactive Staff và `422` cho Actionable.
3. Kiểm tra payload escape và URL permission.

### Phase 3 — Bell UI

1. Tạo JS/CSS/partial theo UI skill của repository khi bắt đầu implementation.
2. Chèn danh sách Reminder không có header riêng vào dropdown Core bằng hook
   asset; bind đúng Bootstrap 3 wrapper và thêm `MutationObserver` idempotent.
3. Dùng class/data attribute trên wrapper để render Pulse Dot/pending count độc
   lập với badge Core.
4. Hoàn thiện silent loading/empty/error, responsive và accessibility.
5. Xác minh không phá click, mark-read hoặc “View all notifications” của Core.

### Phase 4 — CRM adapter cutover

1. Với flag `0`, giữ `add_notification()`.
2. Với flag `1`, publish CRM Inbox và không insert `tblnotifications`.
3. Không thay đổi Email adapter.
4. Xác minh Delivery Health và dedupe.

### Phase 5 — Legacy compatibility và rollout

1. Chỉ ẩn duplicate legacy có `reminder_id` đã được feed trả về thành công;
   không xóa DB và giữ Core item khi feed lỗi.
2. Bật flag trên localhost, sau đó staging cho một nhóm Staff canary.
3. Theo dõi pending count, response rate, API error và CRM delivery health.
4. Bật production sau khi canary đạt tiêu chí nghiệm thu.

## 12. Kiểm thử bắt buộc

### 12.1 Backend

- Informational chưa acknowledge xuất hiện.
- Informational đã acknowledge không xuất hiện.
- Actionable chưa phản hồi xuất hiện dù đã mở/read.
- Actionable đã phản hồi không xuất hiện.
- Staff A không đọc/acknowledge reminder của Staff B.
- POST thiếu CSRF thất bại.
- POST dùng đúng `csrfData.token_name/hash`; không phụ thuộc property không tồn
  tại và không giả định token regenerate ở runtime hiện tại.
- Hai request acknowledge đồng thời chỉ tạo một trạng thái cuối hợp lệ.
- Feed không trả reminder nếu CRM delivery chưa sent.
- Feature flag không tạo đồng thời Core notification và Module Inbox duplicate.

### 12.2 UI thủ công

- Desktop và mobile.
- Bell có Core notification + Reminder cùng lúc.
- Bell chỉ có Reminder.
- Empty, loading, API 403/500 và mất mạng không tạo panel lỗi, không che Bell Core.
- `✕` chỉ có trên Informational.
- Actionable vẫn hiện sau reload nếu chưa phản hồi.
- Actionable biến mất sau phản hồi thành công.
- Informational biến mất xuyên reload/thiết bị sau acknowledge.
- “Mark all as read” của Core không làm mất Actionable Reminder.
- Core Bell, badge, link và “View all notifications” vẫn hoạt động.
- Pulse Dot vẫn tồn tại sau khi Core/Pusher thay HTML notification.
- `MutationObserver` không tạo duplicate request/container và phục hồi Inbox khi
  Core re-render lúc dropdown đang mở.
- Feed lỗi không làm script ẩn Legacy Core notification.

### 12.3 Regression

- Email delivery/retry/CC không đổi.
- Dashboard Activity Feed vẫn có Pending/Informational/Responded đúng entity.
- Deal Activity Timeline chỉ thêm log sau response.
- Reminder dedupe không đổi.
- Cron và Delivery Health không phát sinh backlog CRM giả.

## 13. Tiêu chí nghiệm thu

1. Không có file Core Perfex nào bị thay đổi trong diff.
2. Reminder mới không tạo dòng `tblnotifications` khi feature flag bật.
3. Reminder mới không phụ thuộc top 30 Core.
4. Informational `✕` có trạng thái bền vững, đồng bộ qua reload và thiết bị.
5. Actionable không thể dismiss trước khi phản hồi.
6. Audit Repository và Delivery Log còn nguyên.
7. Email hoạt động độc lập.
8. Permission, CSRF, escaping và ownership test đạt.
9. UI được kiểm tra desktop/mobile và không phá Bell Core.
10. Rollback flag không tạo duplicate cho delivery lịch sử.

## 14. Giới hạn được chấp nhận

- Vì không sửa query Core và không xóa dữ liệu, notification Sales Pipeline cũ
  có thể tiếp tục chiếm slot top 30 trong giai đoạn chuyển tiếp.
- Module Inbox dùng initial fetch, on-open fetch và refresh khi observer phát
  hiện Core/Pusher re-render; không bổ sung interval polling trong phiên bản đầu.
  Realtime riêng cho Reminder là YAGNI cho đến khi có SLA cụ thể.
- Không bổ sung Snooze, bulk dismiss hoặc manager acting-on-behalf trong phiên
  bản đầu.
