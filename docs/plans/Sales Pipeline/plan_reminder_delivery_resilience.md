# Kế hoạch Tối ưu Reminder Delivery và SMTP Production

> **Module**: `sales_pipeline`  
> **Phiên bản**: 1.0  
> **Ngày lập**: 2026-08-26  
> **Trạng thái**: Planned - chưa triển khai  
> **Phạm vi**: Reminder outbox, CRM delivery, email delivery, SMTP/CC, vận hành và quan sát

## 1. Mục tiêu

Đảm bảo Reminder Engine có thể tạo và phân phối thông báo trên production mà
không làm nghẽn CRON, không gửi trùng do worker chạy đồng thời, không làm CRM
Notification Bell bị chặn bởi SMTP, và không làm mất email khi nhà cung cấp tạm
thời giới hạn tốc độ.

Kế hoạch này chỉ tối ưu tầng delivery. Các phần đã được xác minh và phải giữ
nguyên:

- rule evaluation và `dedupe_key`;
- Reminder Repository và delivery outbox;
- dữ liệu To/CC và quy tắc loại trừ email của nhân viên;
- liên kết Reminder Response và entity;
- template email được render từ dữ liệu đã lưu trong `snapshot_json`;
- semantics at-least-once hiện tại.

## 2. Basecode và trạng thái hiện tại

### 2.1 Nguồn runtime đã đối chiếu

- `modules/sales_pipeline/libraries/Reminder_engine.php`
  - `process()` vừa evaluate rule vừa dispatch outbox.
  - `dispatchPendingDeliveries()` lấy tối đa 100 delivery trong một query.
  - CRM pending/retry được ưu tiên trước email pending/retry.
  - Mỗi row được claim bằng update có điều kiện sang `processing`.
  - Row `processing` quá 15 phút được đưa về `failed`.
  - Email thất bại retry cố định sau 1 giờ, tối đa 3 lần.
  - `send_simple_email()` trả `false` chỉ được lưu thành
    `Delivery provider returned false`.
- `modules/sales_pipeline/includes/reminder_repository_schema.php`
  - outbox đã có `status`, `attempt_count`, `last_error`, `next_retry_at`,
    `provider_message_id`, `sent_at`, `updated_at`;
  - index worker hiện tại là `(status, next_retry_at)`;
  - unique key bảo vệ một delivery theo reminder/channel/recipient.
- `modules/sales_pipeline/includes/reminder_rule_defaults.php`
  - chưa có cấu hình batch, throttle, retry hoặc circuit breaker.
- `modules/sales_pipeline/controllers/Sales_pipeline.php`
  - settings chỉ xử lý rule, channel, quiet hours và CC.
- `application/models/Emails_model.php`
  - core đã có `before_send_simple_email` và hỗ trợ `cc`;
  - core trả boolean sau khi gửi. Kế hoạch không sửa file này.

### 2.2 Bằng chứng sự cố localhost

- Mailtrap đã trả `550 5.7.0 Too many emails per second`.
- Các lần gửi tiếp trong cùng đợt sinh `503 nested MAIL command`.
- Rule, dedupe, reminder log, delivery, To và CC đã được kiểm thử thành công khi
  chỉ còn một email trong outbox.

### 2.3 Khoảng trống hiện tại

- Không có giới hạn email riêng theo mỗi lần CRON.
- Không có khoảng cách tối thiểu giữa hai email.
- Không phân loại transient/permanent/rate-limit error.
- Không có persisted cooldown khi SMTP rate-limit.
- Không có backoff tăng dần và jitter.
- Không có channel-level lock; hai worker có thể claim hai email khác nhau và
  cùng gửi đồng thời dù row-level claim vẫn ngăn gửi trùng từng row.
- Không có màn hình quan sát outbox và retry một delivery.

## 3. "Khả năng SMTP" trên production là gì

`SMTP Host`, port, TLS và credential chỉ cho biết cách kết nối. Khả năng SMTP
cần để cấu hình delivery là tập giới hạn của nhà cung cấp và của runtime:

### 3.1 Thông số phải lấy từ nhà cung cấp/gói dịch vụ

1. Số message tối đa mỗi giây/phút và burst window.
2. Số SMTP recipients tối đa mỗi giây/phút. Một email có 1 To và 5 CC có thể
   được tính là 1 message nhưng 6 envelope recipients.
3. Số kết nối SMTP đồng thời và có cho phép connection reuse hay không.
4. Quota theo giờ/ngày/tháng và hành vi khi vượt quota.
5. Số recipient tối đa trong một message, kích thước message và attachment.
6. Timeout kết nối/DATA và giới hạn thời gian một SMTP session.
7. Mã lỗi và chính sách retry của provider:
   - `4xx`: thường là tạm thời;
   - `5xx`: thường là vĩnh viễn;
   - một số provider dùng `5xx` cho rate-limit, vì vậy phải đọc cả code và text;
   - có hay không `Retry-After` hoặc cooldown được công bố.
8. Giới hạn/rate policy theo account, credential, sending domain hoặc IP.
9. Dashboard/log/webhook nào có sẵn để đối chiếu accepted, bounced, delivered.

### 3.2 Thông số phải đo từ ứng dụng production

1. Tần suất CRON và số worker/instance có thể chạy đồng thời.
2. Số reminder email trung bình và đỉnh theo mỗi lần CRON.
3. Số To + CC trung bình trên mỗi message.
4. Thời gian SMTP trung bình/p95 và `max_execution_time` của PHP/CRON.
5. Backlog lớn nhất chấp nhận được và SLA gửi, ví dụ 95% trong 15 phút.
6. Các loại email core khác cùng dùng chung SMTP trong cùng đợt CRON.

### 3.3 Cách suy ra cấu hình an toàn

Đặt:

- `M`: message/giây provider cho phép;
- `R`: recipient/giây provider cho phép;
- `A`: số recipient trung bình trên một message, gồm To và CC;
- `S`: safety factor, đề xuất `0.6-0.8`;
- `W`: số giây dành cho email trong một lần CRON.

Tốc độ ứng dụng tối đa:

```text
effective_rate = min(M, R / A) * S
```

Batch tối đa theo execution window:

```text
email_batch_size <= floor(effective_rate * W)
```

Giá trị production không được sao chép máy móc từ Mailtrap. Phải lấy thông số
gói SMTP thật, đo lường peak, sau đó chọn safety margin và chạy load test staging.

## 4. Thiết kế mục tiêu cho 7 hạng mục

### 4.1 Giới hạn batch email

**Mục tiêu**: một backlog lớn không chiếm toàn bộ thời gian CRON.

Thực thi:

- Tách selector CRM và email thay vì một query `LIMIT 100` dùng chung.
- CRM được dispatch trước với batch riêng và không bị trừ vào email budget.
- Email dùng `sp_reminder_delivery_email_batch_size`.
- Query email lọc `channel='email'` và sắp xếp:
  1. pending mới;
  2. failed đến hạn retry;
  3. `next_retry_at`, sau đó `id` để ổn định.
- Default đề xuất:
  - localhost/Mailtrap: `1`;
  - production ban đầu: chưa chốt, lấy từ capacity assessment;
  - validation: `1..100`.

Acceptance criteria:

- 100 email pending với batch 5 chỉ claim tối đa 5 email mỗi lần.
- CRM delivery vẫn được gửi trong cùng lần dù email backlog lớn.

### 4.2 Giới hạn tốc độ gửi

**Mục tiêu**: không vượt message/recipient rate của provider.

Thực thi:

- Thêm `sp_reminder_delivery_email_min_interval_ms`.
- Dispatcher đo elapsed time từ lần gửi email trước và chỉ `usleep()` phần thời
  gian còn thiếu, không sleep sau email cuối.
- Chỉ throttle trong email batch nhỏ; không sleep trên query 100 row.
- Tính rate theo message; capacity assessment phải bù trừ CC recipients.
- Default localhost đề xuất `1100ms`; production chốt sau staging.
- Validation: `0..60000ms`.

Acceptance criteria:

- Timestamp bắt đầu giữa hai SMTP attempt không nhỏ hơn interval cấu hình.
- Tổng thời gian CRON nằm trong execution budget.

### 4.3 Dừng batch khi SMTP rate-limit

**Mục tiêu**: một lỗi throttle không tạo thêm hàng chục lỗi dây chuyền.

Thực thi:

- Sau `send_simple_email() === false`, đọc ngay
  `$this->CI->email->print_debugger()` trong module.
- Chuẩn hóa lỗi qua một classifier module-scope:
  - `rate_limited`;
  - `transient_transport`;
  - `permanent_recipient`;
  - `authentication/configuration`;
  - `unknown`.
- Nhận diện rate-limit bằng SMTP enhanced code và pattern provider, gồm
  `too many`, `rate limit`, `throttl`, `quota temporarily`.
- Khi `rate_limited`:
  - cập nhật row hiện tại thành failed và đặt cooldown;
  - hoãn `next_retry_at` của các email due còn lại đến sau cooldown;
  - `break` email loop;
  - CRM không bị ảnh hưởng.
- Không coi mọi `550` là transient; `5.1.1 invalid recipient` phải permanent.

Acceptance criteria:

- Provider rate-limit ở email thứ 2 thì email 3..N không bị attempt trong lần đó.
- Không còn chuỗi `nested MAIL command` do tiếp tục gửi sau rate-limit.

### 4.4 Retry với exponential backoff và jitter

**Mục tiêu**: retry không đồng loạt và không tạo retry storm.

Thực thi:

- Thay retry cố định `+1 hour` bằng schedule code-owned, đề xuất:
  `1m, 5m, 15m, 1h, 6h`.
- Thêm jitter `0..20%` cho transient/rate-limit.
- `sp_reminder_delivery_max_attempts`, validation `1..10`, default đề xuất `5`.
- Permanent recipient/configuration error không retry tự động; đặt
  `attempt_count=max_attempts` và giữ error class để xử lý thủ công.
- Rate-limit cooldown không ngăn CRM channel.
- Manual retry reset duy nhất delivery được chọn, có audit log.

Acceptance criteria:

- Hai delivery thất bại cùng lúc không có `next_retry_at` trùng hoàn toàn.
- Permanent error không quay lại selector tự động.
- Một email thành công xóa `last_error*` và `next_retry_at`.

### 4.5 Lưu lỗi SMTP thật và an toàn

**Mục tiêu**: vận hành biết lý do thất bại mà không lộ secret/PII.

Thực thi:

- Tạo helper module-scope `Reminder_delivery_error_classifier`.
- Sanitizer bắt buộc loại:
  - SMTP username/password/token;
  - connection DSN;
  - raw debug transcript có credential;
  - full message body;
  - danh sách recipient nếu không cần thiết.
- Lưu message ngắn đã sanitize vào `last_error` tối đa 500 ký tự.
- Migration/schema additive đề xuất:
  - `last_error_code varchar(64) NULL`;
  - `last_error_class varchar(32) NULL`;
  - `last_attempt_at datetime NULL`.
- Không sửa `application/models/Emails_model.php`.
- Nếu provider không trả message ID qua API hiện tại, giữ
  `provider_message_id=NULL`; không tự tạo giá trị giả.

Acceptance criteria:

- Mailtrap rate-limit được lưu là `rate_limited`, không còn chỉ có
  `Delivery provider returned false`.
- Test sanitizer không tìm thấy credential và full email address trong error.

### 4.6 Khóa worker/CRON đồng thời

**Mục tiêu**: nhiều CRON worker không vượt aggregate email rate và không gửi
trùng một delivery.

Thực thi:

- Giữ row-level conditional claim hiện tại để bảo vệ từng delivery.
- Thêm channel-level advisory lock MySQL/MariaDB cho email dispatcher:
  `GET_LOCK(<db-prefix>:sales_pipeline:reminder:email, 0)`.
- Nếu không lấy được lock, worker bỏ qua email batch nhưng vẫn có thể xử lý CRM.
- `RELEASE_LOCK()` trong `finally`.
- Giữ stale-processing recovery, nhưng max attempts và backoff phải được áp dụng
  khi thu hồi row stuck.
- Test crash window và ghi rõ residual risk at-least-once: provider accepted
  nhưng process crash trước khi cập nhật DB vẫn có thể gửi lại.

Acceptance criteria:

- Hai worker đồng thời: chỉ một worker dispatch email; CRM không bị chặn.
- Worker fail/exception vẫn release lock khi connection còn tồn tại; DB đóng
  connection sẽ tự release advisory lock.

### 4.7 Quan sát outbox và thao tác vận hành

**Mục tiêu**: admin phát hiện backlog trước khi người dùng báo lỗi.

Thực thi:

- Thêm khu vực Delivery Health tại settings Reminder, chỉ admin truy cập:
  - count `pending/processing/failed/sent` theo channel;
  - oldest pending age;
  - số rate-limit trong 24 giờ;
  - thời điểm gửi thành công gần nhất;
  - circuit/cooldown đến khi nào.
- Danh sách failed gần nhất chỉ hiện delivery ID, rule, entity, error class,
  attempt và next retry; mask recipient.
- Endpoint retry một delivery:
  - POST + CSRF;
  - `is_admin()`;
  - chỉ retry `failed`/permanent sau khi admin xác nhận nguyên nhân đã sửa;
  - không sửa reminder/dedupe/recipient;
  - audit staff ID và delivery ID.
- Không thêm nút `Retry all` trong phiên bản đầu.
- Đặt cảnh báo vận hành đề xuất:
  - oldest pending > SLA;
  - failed rate > 5% trong 15 phút;
  - authentication/configuration error > 0;
  - processing stale > 0.

Acceptance criteria:

- Admin nhìn được backlog mà không cần query SQL.
- Staff không có quyền settings không truy cập được dữ liệu delivery.

## 5. Schema, index và cấu hình dự kiến

### 5.1 Migration additive

Tạo migration mới, không sửa migration đã tồn tại:

- thêm `last_error_code`, `last_error_class`, `last_attempt_at`;
- thêm index worker:

```sql
KEY idx_delivery_channel_worker
    (channel, status, next_retry_at, id)
```

Cập nhật cả migration và
`sales_pipeline_ensure_reminder_repository_schema()` để active installation và
fresh install cùng đạt một schema. Mỗi ALTER phải idempotent.

### 5.2 Options dự kiến

```text
sp_reminder_delivery_email_batch_size
sp_reminder_delivery_email_min_interval_ms
sp_reminder_delivery_max_attempts
sp_reminder_delivery_rate_limit_cooldown_seconds
```

Không lưu SMTP credential, provider token hoặc raw debug trong module options.

## 6. Thứ tự triển khai

### Giai đoạn 0 - Capacity assessment production

1. Xác định provider/gói SMTP production.
2. Thu thập message rate, recipient rate, quota, concurrency và error policy.
3. Đo peak reminder volume, CC fan-out, cron interval và execution budget.
4. Chốt batch/interval/cooldown ban đầu với safety factor.

**Gate**: không chốt giá trị production nếu chưa có bảng capacity.

### Giai đoạn 1 - Batch và channel isolation

1. Viết test selector CRM/email.
2. Tách query và batch budget theo channel.
3. Thêm option batch size và validation.
4. Xác minh CRM vẫn được gửi khi email backlog lớn.

### Giai đoạn 2 - Throttle

1. Viết test clock/throttle bằng injectable clock/sleeper.
2. Thêm minimum interval cho email batch.
3. Xác minh execution budget và không sleep sau email cuối.

### Giai đoạn 3 - Error classification và stop-on-limit

1. Viết fixture cho Mailtrap 550 rate-limit, SMTP 4xx, auth 535 và recipient
   5.1.1.
2. Thêm sanitizer/classifier module-scope.
3. Lưu error thật đã sanitize.
4. Break email loop và persist cooldown khi rate-limit.

### Giai đoạn 4 - Backoff và concurrency lock

1. Viết test backoff/jitter và max attempts.
2. Thêm channel advisory lock.
3. Test hai worker và stale-processing recovery.
4. Ghi nhận residual at-least-once risk.

### Giai đoạn 5 - Schema và observability

1. Thêm migration additive và bootstrap schema idempotent.
2. Thêm index channel worker.
3. Thêm Delivery Health và manual retry có permission/CSRF/audit.
4. Test masking và access control.

### Giai đoạn 6 - Staging load test

1. Tạo 5, 20 và 100 delivery test có To/CC khác nhau.
2. Mô phỏng provider success, latency, transient failure và rate-limit.
3. Chạy nhiều CRON worker đồng thời.
4. Xác minh không mất delivery, không gửi manager thành email riêng, CRM không
   bị email chặn và backlog drain đúng SLA.

### Giai đoạn 7 - Production rollout

1. Backup DB và chạy migration.
2. Deploy với batch nhỏ và safety factor bảo thủ.
3. Canary một nhóm rule/email trước khi bật toàn bộ.
4. Theo dõi error rate, oldest pending, cron duration và SMTP dashboard.
5. Tăng batch/rate từng bước nếu còn dư capacity.

## 7. Chiến lược test

### 7.1 Contract/unit

- defaults và validation của các option mới;
- deterministic ordering và channel isolation;
- batch cap;
- backoff + jitter trong khoảng cho phép;
- SMTP classifier/sanitizer;
- rate-limit dừng batch;
- permanent error không retry;
- advisory lock skip/release.

### 7.2 DB integration

- hai worker cùng select một outbox;
- worker crash sau claim;
- stuck processing recovery;
- index được sử dụng cho query due email;
- manual retry chỉ reset đúng delivery;
- dedupe reminder không bị thay đổi.

### 7.3 Manual staging/Mailtrap

- một message gồm To và CC trong header;
- batch nhỏ được gửi qua nhiều lần CRON;
- không có `Too many emails per second` ở cấu hình staging;
- template và link đúng snapshot/entity;
- Mailtrap rate-limit fixture làm circuit mở và backlog giữ nguyên.

## 8. Tiêu chí chấp nhận release

1. CRM Notification Bell hoạt động ngay cả khi SMTP down/rate-limit.
2. Email không vượt batch và minimum interval đã cấu hình.
3. Rate-limit dừng email batch, không tạo chuỗi lỗi tiếp theo.
4. Transient retry theo backoff; permanent error không retry storm.
5. `last_error` có nguyên nhân đã sanitize và error class.
6. Hai worker không dispatch email đồng thời.
7. Admin quan sát được backlog và retry một delivery có audit.
8. Không sửa core `Emails_model.php`.
9. Rule, dedupe, CC và Reminder Response regression tests vẫn pass.
10. Staging load test đạt SLA đã chốt trong capacity assessment.

## 9. Rollback và graceful degradation

- Schema mới là additive, không xóa cột/bảng trong rollback khẩn cấp.
- Giảm `email_batch_size` về `1` và tăng interval nếu provider bắt đầu throttle.
- Tắt channel email theo rule nếu SMTP sự cố; CRM channel vẫn hoạt động.
- Không xóa reminder log/outbox để "dọn backlog" trên production.
- Không reset hàng loạt attempt. Manual retry chỉ thực hiện sau khi nguyên nhân đã
  được sửa và có audit.
- Nếu UI Delivery Health lỗi, dispatcher không phụ thuộc UI và vẫn chạy theo
  options/defaults.

## 10. Deliverables

- migration additive và bootstrap schema;
- options/defaults + settings validation/UI;
- dispatcher tách channel, batch và throttle;
- error classifier/sanitizer;
- backoff, cooldown và advisory lock;
- Delivery Health + manual retry endpoint;
- contract, unit, DB integration và manual test checklist;
- implementation report ghi thông số SMTP production, kết quả load test và
  cấu hình rollout cuối cùng.
