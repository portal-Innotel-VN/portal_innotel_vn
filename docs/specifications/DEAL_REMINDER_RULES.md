# Đặc tả Rule Nhắc nhở Deal

> Trạng thái: Đã triển khai trong module `sales_pipeline`; cần xác minh cron, email provider và UI trên từng môi trường trước release.

## 1. Ranh giới nghiệp vụ

- Deal và Báo giá là hai luồng dữ liệu độc lập.
- Rule Deal không đọc Estimate Group và không thay đổi Điểm hiệu suất Báo giá.
- Mọi sự kiện dùng Reminder Repository, Delivery, Quick Response và drawer hiện hữu.
- Hai kênh hỗ trợ hiện tại là chuông CRM và email cá nhân NVKD.
- WhatsApp cho Quản lý chưa được triển khai.

## 2. `DEAL_PIPELINE_MIN_COUNT`

Mục tiêu: phát hiện NVKD có quá ít hoặc không có Deal đang mở.

- Nguồn quét là danh sách Staff active, không bắt đầu từ bảng Deal; vì vậy NVKD có `0 Deal` vẫn được đánh giá.
- Chỉ xét Staff không phải Admin có quyền `view` hoặc `view_own` đối với `sales_pipeline`.
- Deal đang mở là Deal có trạng thái không phải Won và không phải Lost.
- Ngưỡng `sp_reminder_deal_pipeline_min_count` do Admin cấu hình; mặc định là `1`.
- Thời điểm kiểm tra dùng `sp_reminder_deal_pipeline_check_time`; mặc định `09:00`.

| Ngày | Checkpoint | Mức độ | Người nhận |
|---|---|---|---|
| Thứ Hai | `START_WEEK` | Warning | NVKD qua CRM + email |
| Thứ Tư | `MIDWEEK` | Warning | NVKD qua CRM + email |
| Thứ Sáu | `FINAL` | Critical | NVKD qua CRM + email; email CC Quản lý nếu CC được bật |

Quản lý không có delivery riêng từ rule này. Cờ `manager_cc` chỉ bật ở `FINAL`, nên cấu hình CC toàn cục không làm phát sinh CC vào Thứ Hai hoặc Thứ Tư.

Dedupe key: `DEAL_PIPELINE_MIN_COUNT:{staff_id}:{iso_week}:{checkpoint}`.

## 3. Hai tầng `DEAL_STALE_FOLLOW_UP` và `DEAL_STALE_BACKLOG`

Mục tiêu: phát hiện Deal đang mở nhưng không được chăm sóc đúng hạn.

- Chỉ xét Deal `reminder_enabled = 1` và trạng thái đang mở.
- Số ngày cho phép không hoạt động lấy từ `reminder_frequency` trên từng Deal.
- `sp_reminder_deal_stale_cutoff_days` mặc định `30`, Admin có thể thay đổi.
- `sp_reminder_deal_stale_max_per_run` mặc định `5`, Admin có thể thay đổi.
- Khi `inactive_days < reminder_frequency`: không nhắc.
- Khi `reminder_frequency <= inactive_days <= cutoff`: tạo `DEAL_STALE_FOLLOW_UP` riêng cho Deal.
- Khi `inactive_days > cutoff`: không tạo follow-up riêng; đưa vào một `DEAL_STALE_BACKLOG` cấp Staff.
- Mỗi Cron run chỉ tạo tối đa `max_per_run` follow-up riêng cho một NVKD.
- Thứ tự chọn cố định: `inactive_days DESC`, `last_meaningful_activity_at ASC`, `deal_id ASC`.
- Mốc hoạt động gần nhất là giá trị mới nhất giữa `datecreated`, `datemodified` của Deal và `datecreated` trong `sales_pipeline_activity`.
- Activity bắt đầu bằng `Phản hồi nhắc nhở:` hoặc `Reminder response:` bị loại trừ; phản hồi Quick Response không được coi là một lần follow-up khách hàng.
- Không cần thêm cột hoặc migration activity.
- Người nhận chỉ là NVKD qua các kênh được cấu hình; không CC Quản lý, kể cả backlog.
- `DEAL_STALE_BACKLOG` dùng `entity_type = staff_deal_backlog`, `pipeline_id = NULL`, và snapshot gồm số lượng ưu tiên, overflow, long-stale, cutoff, oldest age, tổng giá trị và top Deals.
- Backlog chống spam bằng fingerprint của Staff + danh sách `(deal_id,last_meaningful_activity_at)` + cutoff. Khi fingerprint không đổi, Cron không tạo Reminder mới.
- Dedupe follow-up giữ nguyên theo chu kỳ hoạt động: `DEAL_STALE_FOLLOW_UP:{deal_id}:{last_meaningful_activity_at}:{reminder_frequency}`.

Dedupe key: `DEAL_STALE_FOLLOW_UP:{staff_id}:{deal_id}:{last_meaningful_activity}:{required_days}`.

## 4. Hiển thị và phản hồi

- Link CRM/email mở `admin/sales_pipeline/reminder_response/{id}`.
- Reminder theo Staff của rule pipeline dùng `entity_type = staff_deal_period` và xuất hiện trong drawer tab Deal.
- Reminder stale dùng `entity_type = deal`; phản hồi được lưu vào Reminder Log và ghi Activity của Deal theo workflow hiện hành.
- `reminder_response` chưa tham gia trọng số Performance Score.

## 5. File thực thi chính

- `modules/sales_pipeline/libraries/Deal_reminder_rule_evaluator.php`
- `modules/sales_pipeline/libraries/Reminder_engine.php`
- `modules/sales_pipeline/includes/reminder_rule_defaults.php`
- `modules/sales_pipeline/models/Sales_pipeline_model.php`
- `modules/sales_pipeline/views/settings.php`
- `modules/sales_pipeline/tests/Deal_reminder_rule_evaluator_test.php`
