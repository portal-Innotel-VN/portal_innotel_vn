# Walkthrough triển khai Reminder Delivery Resilience v1.3

> **Ngày triển khai Basecode**: 2026-08-27  
> **Module**: `sales_pipeline`  
> **Trạng thái**: Hoàn tất phạm vi code và localhost; chưa phát hành production  
> **Bảo mật**: Tài liệu không chứa mật khẩu, token, hostname SMTP, tài khoản SMTP
> hoặc địa chỉ IP cụ thể

## 1. Kết quả tổng quát

Luồng hiện tại là:

`Rule Engine -> dedupe -> reminder log -> delivery theo channel -> expiration -> claim -> email guard -> SMTP -> To/CC/BCC`

CRM notification được chọn và xử lý độc lập trước email. Email chỉ chạy khi
circuit đóng và worker lấy được advisory lock. Trước mỗi SMTP attempt, hệ thống
kiểm tra kích thước render, đếm To/CC/BCC, giữ token rolling quota và chờ đủ
khoảng cách tối thiểu. Kết quả lỗi được phân loại trong bộ nhớ, sanitize rồi mới
ghi DB.

Không có thay đổi nào trong `application/models/Emails_model.php`. Rule
evaluation, `dedupe_key`, Reminder Repository, To/CC/BCC, liên kết phản hồi và
template từ `snapshot_json` được giữ nguyên.

## 2. Walkthrough theo giai đoạn

### Giai đoạn 1 - Schema và State Foundation

- Thêm migration additive `110_version_110.php`, có `up()` và rollback không phá
  dữ liệu.
- Delivery có thêm `last_error_code`, `last_error_class`, `last_attempt_at`,
  `expires_at`, `expired_at` và hai index worker/retention.
- Thêm bảng rate bucket theo phút, unique theo `scope_key + bucket_minute`.
- Bootstrap idempotent tạo cùng schema cho cài mới và nâng cấp.
- Đăng ký contract trạng thái `pending`, `processing`, `failed`, `sent`,
  `expired`, `cancelled` và toàn bộ option Canary.

### Giai đoạn 2 - Channel Isolation và Expiration

- Selector CRM và email dùng query riêng; CRM có budget riêng và luôn đi trước.
- Email nhận `expires_at` cố định khi materialize. Row quá hạn chuyển sang
  `expired` trước claim, không tăng attempt và không gửi snapshot cũ.
- Email legacy chưa terminal được backfill hạn 24 giờ; selector và manual retry
  từ chối row email không có `expires_at`, ngăn 292 lỗi cũ bị gửi lại ngoài ý
  muốn.
- Claim vẫn là update có điều kiện nên bảo vệ từng delivery.

### Giai đoạn 3 - Throttle, Quota và Lock

- Batch email mặc định là `1`; interval mặc định `30.000 ms` được giữ xuyên
  nhiều lần Cron bằng timestamp không nhạy cảm.
- Rolling 60 phút: `10 message / 25 recipient`; rolling 24 giờ:
  `50 message / 125 recipient`; tối đa `10 recipient/message`.
- Mỗi To, CC và BCC hợp lệ được tính trước SMTP attempt. Token đã giữ không hoàn
  lại khi send thất bại hoặc process crash.
- Advisory lock không chờ đảm bảo một worker email; CRM vẫn hoạt động nếu email
  không lấy được lock. Runtime bật persistent DB connection bị fail-closed.

### Giai đoạn 4 - Error Classification và Circuit Breaker

- Classifier xử lý `rate_limited`, `transient_transport`,
  `permanent_recipient`, `authentication_configuration`,
  `system_bcc_over_quota` và `unknown_outcome`.
- Sanitizer loại credential, auth payload, DSN, email, IP, header và giới hạn
  `last_error` tối đa 500 ký tự.
- Rate-limit áp dụng cooldown `3.600 giây + jitter 0..900 giây`, hoãn email còn
  lại và dừng email loop; CRM không bị ảnh hưởng.
- Lỗi `535/5.7.8` mở circuit `open_authentication` vô thời hạn và tạo một Bell
  Alert cho Admin theo lần mở circuit.
- BCC đầy bị `cancelled` để tránh auto-retry To không rõ kết quả, tạo Bell đã
  dedupe 24 giờ và không mở/dừng global email loop.

### Giai đoạn 5 - Backoff và Operations UI

- Retry transient theo `1m, 5m, 15m, 1h, 6h` cộng jitter `0..20%`.
- Stale `processing` được thu hồi từng row, tăng attempt và nhận backoff; tối đa
  100 row mỗi lần để không tạo update lớn.
- Delivery Health chỉ Admin truy cập, hiển thị backlog theo channel, expired,
  rate-limit 24 giờ, email pending lâu nhất, lần gửi gần nhất, cooldown, circuit
  và incident gần nhất với recipient đã mask.
- Retry chỉ một delivery `failed/cancelled`, còn hạn sử dụng; resume chỉ đóng
  circuit. Endpoint yêu cầu Admin + AJAX POST + CSRF đang bật và ghi audit.

### Giai đoạn 6 - Retention

- Maintenance dùng lock riêng và chạy tối đa một lần mỗi 7 ngày.
- Mỗi lần purge tối đa 500 delivery `sent/expired/cancelled` quá 60 ngày và 500
  rate bucket quá 48 giờ.
- Không xóa reminder log. Reminder có `staff_response`, delivery
  `failed/processing` và incident auth/BCC/unknown outcome luôn được giữ.

## 3. Các file chính

- Schema/options: `includes/reminder_repository_schema.php`,
  `includes/reminder_rule_defaults.php`, `migrations/110_version_110.php`.
- Runtime: `libraries/Reminder_engine.php`, `Reminder_delivery_selector.php`,
  `Reminder_delivery_policy.php`, `Reminder_delivery_throttle.php`,
  `Reminder_delivery_rate_limiter.php`, `Reminder_delivery_lock.php`,
  `Reminder_delivery_backoff.php`, `Reminder_delivery_error_classifier.php`,
  `Reminder_delivery_error_sanitizer.php`.
- Operations: `Reminder_delivery_operations.php`,
  `Reminder_delivery_maintenance.php`, controller Settings, partial Delivery
  Health, CSS và hai language file.
- Tests: sáu file `modules/sales_pipeline/tests/Reminder_delivery_*_test.php`.

## 4. Kết quả kiểm thử

- Toàn bộ 12 test module đạt; bộ Estimate Group hiện hữu đạt 44/44 case.
- PHP lint đạt cho toàn bộ file PHP thuộc thay đổi.
- Browser localhost với Admin thật:
  - Delivery Health render đúng dữ liệu DB;
  - desktop dùng 6 cột metric, mobile co về 2 cột;
  - panel không có horizontal overflow;
  - lần reload cuối không có JavaScript error.
- Không chạy SMTP load test thật để tránh phát sinh email ngoài kiểm soát.
- Chưa chạy được `EXPLAIN` từ CLI do DB chỉ khả dụng trong runtime web; cần chạy
  lại trong container/DB console của môi trường staging.

## 5. Runbook sự cố

### Authentication 535/5.7.8

1. Giữ circuit ở `open_authentication`; không bấm resume ngay.
2. Admin kiểm tra cấu hình tại trang Email Settings mà không ghi credential vào
   ticket/log.
3. Gửi một email canary nội bộ bằng công cụ kiểm tra email của hệ thống.
4. Khi canary thành công, vào Delivery Health và chọn **Mở lại kênh email**.
5. Chỉ retry từng delivery; theo dõi Bell, SMTP log và cooldown.

### BCC hệ thống đầy

1. Kiểm tra quota và dọn/tăng dung lượng mailbox BCC bằng kênh quản trị mail.
2. Đối chiếu SMTP log để xác định To đã được nhận hay chưa.
3. Gửi canary có BCC, xác nhận mailbox nhận được.
4. Chỉ retry delivery `system_bcc_over_quota` sau khi đã đánh giá nguy cơ gửi
   trùng To.

### Rate-limit hoặc SMTP outage

1. Không reset backlog hoặc attempt hàng loạt.
2. Giữ profile Canary và chờ `cooldown_until`.
3. Kiểm tra lưu lượng Email Core vì advisory lock chỉ bảo vệ module.
4. Đối chiếu SMTP log/provider ticket trước khi thay đổi quota.
5. Nếu lỗi kéo dài, tắt channel email của rule liên quan; CRM bell vẫn chạy.

### Unknown outcome và email có thể trùng

SMTP là at-least-once: provider có thể đã nhận DATA nhưng CRM không nhận được
phản hồi cuối. Trước manual retry, Admin phải đối chiếu SMTP log theo thời gian,
recipient đã mask và Delivery ID. Không xem Reminder dedupe là SMTP dedupe.

## 6. Release Gate còn lại

1. Chạy migration 110 và xác minh schema trên staging/production backup.
2. Chạy `EXPLAIN` selector và purge query trong đúng DB runtime.
3. Chạy hai worker đồng thời và kill test để xác minh lock được giải phóng với
   `pconnect=false`.
4. Chạy load test fake SMTP với backlog 5/20/100, không dùng mailbox production.
5. Gửi một canary nội bộ có To/CC/BCC, kiểm tra header và nội dung liên kết.
6. Nhận phản hồi ticket P.A về burst, cách tính recipient, concurrency,
   cooldown và SMTP log; chỉ sau đó mới cân nhắc nới profile Canary.
7. Theo dõi ít nhất một cửa sổ 24 giờ trước khi bật thêm rule email.
