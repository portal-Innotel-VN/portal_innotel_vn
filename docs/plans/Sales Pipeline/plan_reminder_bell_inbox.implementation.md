# Nhật ký triển khai Reminder Inbox trong Notification Bell

> **Module**: `sales_pipeline`  
> **Ngày cập nhật**: 2026-08-28  
> **Trạng thái**: Mã nguồn hoàn tất; rollout đang tắt, chưa nghiệm thu production  
> **Kế hoạch nguồn**: [`plan_reminder_bell_inbox.md`](plan_reminder_bell_inbox.md)

## 1. Phạm vi đã triển khai

- Migration `111` bổ sung trạng thái acknowledge và index Inbox.
- Module version `1.0.11` kích hoạt migration `111`; bootstrap `app_init` kiểm
  tra cả `acknowledged_at`, `acknowledged_by` và `idx_reminder_inbox_queue`.
- Feature flag `sp_reminder_crm_inbox_enabled` mặc định `0`. Migration `111`
  chỉ seed `0` khi option chưa tồn tại; không ghi đè môi trường localhost/canary
  đã chủ động bật.
- Khi flag `0`, Core Bell tiếp tục dùng `add_notification()` và module không tải
  asset/không phục vụ feed hoặc acknowledge.
- Khi flag `1`, feed chỉ trả Reminder của Staff hiện tại có CRM delivery `sent`
  đúng recipient. Nếu thiếu Delivery audit hoặc schema acknowledge, feed trả rỗng.
- Informational chỉ có thao tác acknowledge và link entity hợp lệ; chỉ
  Actionable mới có Quick Response.
- Feed sắp xếp theo severity rồi thời gian mới nhất, không ưu tiên cứng
  Actionable để Informational và nút acknowledge không bị backlog che khuất.
- Endpoint acknowledge yêu cầu Staff đăng nhập, quyền Sales Pipeline, AJAX,
  CSRF Core, ownership và `response_required = 0`.
- UI không tạo header/panel “Việc cần xử lý”. Chỉ mount danh sách khi feed có
  Reminder; loading/empty/error đều silent fail về Core Bell. Danh sách có Pulse
  Dot độc lập, `aria-live`, focus state, reduced motion, mobile touch target và
  MutationObserver.
- Item Reminder cuối danh sách reset có namespace đối với định dạng
  `li:last-child` của Core Bell; vì vậy vẫn giữ padding, font-weight và căn trái
  giống các Reminder còn lại mà không sửa CSS Core Perfex.
- Nội dung Reminder tái sử dụng trực tiếp nhịp thị giác của Core Bell: nền trong
  suốt, padding `10px`, border `#f0f0f0`, hover `#fbfbfb`, nội dung 13px/400 màu
  `#333` và thời gian `small.text-muted` màu `#777`. Icon đồng hồ riêng được bỏ;
  dải trái 3px là điểm nhấn duy nhất theo severity hiện có: đỏ `critical` và cam
  `warning`, trong khi typography và bề mặt vẫn liền mạch với Core. Không tạo
  mapping xanh lá vì evaluator hiện không phát severity `info`.
- Severity có nhãn ẩn cho assistive technology và giá trị legacy `danger` được
  chuẩn hóa thành `critical`. Bell sắp xếp đúng `critical → warning`, sửa
  sai lệch cũ từng kiểm tra `danger` dù Reminder Engine lưu `critical`.
- Legacy Core row chỉ bị ẩn sau feed hợp lệ với `reminder_id` khớp chính xác;
  lỗi/timeout/response sai sẽ khôi phục Core fallback.

## 2. Tệp chính

- `modules/sales_pipeline/migrations/111_version_111.php`
- `modules/sales_pipeline/includes/reminder_repository_schema.php`
- `modules/sales_pipeline/includes/reminder_rule_defaults.php`
- `modules/sales_pipeline/libraries/Reminder_engine.php`
- `modules/sales_pipeline/models/Sales_pipeline_model.php`
- `modules/sales_pipeline/controllers/Sales_pipeline.php`
- `modules/sales_pipeline/assets/js/reminder_bell.js`
- `modules/sales_pipeline/assets/css/reminder_bell.css`
- `modules/sales_pipeline/tests/Reminder_bell_inbox_test.php`

Không sửa view/controller/model Core Perfex để tích hợp Bell. Worktree tổng thể
có thể chứa thay đổi ngoài phạm vi module; vì vậy chỉ được kết luận “không sửa
Core cho Reminder Bell”, không dùng báo cáo này để chứng nhận toàn bộ worktree.

## 3. Xác minh tự động đã chạy

- PHP syntax check cho controller, model, engine, schema/default và migration.
- JavaScript syntax check cho `reminder_bell.js`.
- Reminder Bell contract test.
- Toàn bộ test scripts hiện có trong `modules/sales_pipeline/tests/`.
- `git diff --check` cho các tệp thuộc phạm vi Reminder Bell.
- Chrome localhost desktop: sau reload và mở Bell, không còn header/panel
  “Việc cần xử lý”, không hiển thị lỗi feed trong dropdown, Core notification
  vẫn hiển thị. Lần kiểm tra Pulse Dot không có lỗi từ `reminder_bell.js`; console
  vẫn có lỗi Dashboard độc lập `updateBoxPosition is not defined`, ngoài phạm vi
  Reminder Bell và chưa được xử lý trong thay đổi này.
- Sau migration 111 và sửa strict DATETIME query, feed hiển thị 30 Reminder;
  kiểm tra runtime có 1 Informational “Báo giá nháp quá lâu” với nút `✕` và 29
  Actionable chỉ có CTA phản hồi. Không bấm acknowledge lên dữ liệu thật trong
  bước kiểm tra này.
- Pulse Dot dùng token module tại `top: 40px; left: 50%` cùng
  `translateX(-50%)`, căn đúng tâm ngang và nằm ngay dưới glyph chuông. Vòng
  pulse chỉ mở rộng 3px và không animate `transform`, tránh ghi đè phép căn
  giữa. Badge số Core vẫn giữ nguyên selector và vị trí Core
  (`top: 13px; right: 1px` trên desktop, `top: 14px; right: -4px` trên mobile),
  CSS Reminder không chọn hoặc ghi đè `.icon-notifications`.
- Chrome localhost xác nhận desktop: glyph chuông kết thúc tại khoảng `39.45px`,
  Pulse Dot bắt đầu tại `40px`; sai lệch giữa tâm ngang của Dot và glyph nhỏ hơn
  `0.001px`. Vùng badge Core kết thúc khoảng `30–31px` và vẫn nằm ở khu vực riêng.
  Viewport 390px với
  desktop user-agent không render mobile Bell do `is_mobile()` phía server; phần
  mobile được đối chiếu tĩnh theo CSS Core và cần xác nhận thêm bằng mobile UA.
- Chrome localhost xác nhận Reminder cuối có computed style
  `text-align: left`, `padding: 10px`, `font-weight: 400`; contract test khóa
  selector override để ngăn lỗi căn giữa tái xuất hiện.
- Đối chiếu computed style trên Chrome localhost xác nhận Reminder và Core cùng
  title/message `13px`, weight `400`, màu `rgb(51, 51, 51)`, line-height
  `18.5714px`; timestamp cùng `9.1px`, màu `rgb(119, 119, 119)`, line-height
  `13px`; không còn icon đồng hồ trong Reminder item.
- Chrome localhost xác nhận các bản ghi `critical` xếp đầu và có dải đỏ
  `rgb(252, 45, 66)`, bản ghi `warning` có dải cam `rgb(255, 111, 0)`; nhãn đọc
  màn hình tương ứng là “Nghiêm trọng” và “Cảnh báo”. Contract test khóa phạm vi
  hai severity này để UI không tự phát minh trạng thái màu không có trong Rule.

Contract test được đưa vào ngoại lệ `.gitignore` để có thể version-control. Test
khóa các contract rollout/schema/API/UI quan trọng, nhưng không được mô tả như
một DB integration test đầy đủ.

## 4. Chưa được chứng nhận tự động

- Migration `111` trên bản sao database production.
- Hai request acknowledge đồng thời trên MySQL thực.
- CSRF failure qua HTTP runtime và session Staff thật.
- Mobile, Core notification kết hợp Reminder thành công, Pusher/Core re-render
  và tình huống mất mạng chủ động trong browser thật.
- Canary metrics: pending count, API error, response rate và Delivery Health.

Các mục trên phải hoàn thành trước khi đổi
`sp_reminder_crm_inbox_enabled` sang `1` trên production.

## 5. Trình tự rollout

1. Backup/snapshot database và chạy migration `111` khi flag vẫn là `0`.
2. Xác minh Core Bell và Email hoạt động như trước.
3. Bật flag trên localhost, chạy checklist UI/API trong kế hoạch.
4. Bật cho môi trường staging/canary và theo dõi Delivery Health.
5. Chỉ bật production sau khi toàn bộ tiêu chí nghiệm thu đạt.
6. Rollback bằng cách đặt flag về `0`; reload trang để gỡ asset Inbox và quay
   lại Core Bell. Không xóa Reminder Repository hoặc Delivery Log.
