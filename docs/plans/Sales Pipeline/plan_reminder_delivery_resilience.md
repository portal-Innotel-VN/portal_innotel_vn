# Kế hoạch Tối ưu Reminder Delivery và SMTP Production

> **Module**: `sales_pipeline`  
> **Phiên bản**: 1.3
> **Ngày cập nhật**: 2026-08-27
> **Trạng thái**: Đã triển khai trên Basecode/localhost; chờ xác minh staging và production
> **Phạm vi**: Reminder outbox, CRM delivery, email delivery, SMTP/CC, vận hành và quan sát
> **Bảo mật**: Không lưu hoặc công bố SMTP password, token, account, hostname/IP
> cụ thể hay raw authentication transcript

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
  - core tự nối BCC hệ thống từ option `bcc_emails` sau hook của module;
  - core trả boolean sau khi gửi, không trả kết quả cấu trúc theo từng To/CC/BCC.
    Kế hoạch không sửa file này.
- `application/config/database.php`
  - Basecode hiện đặt `'pconnect' => false`; cấu hình runtime production vẫn
    phải được xác minh lại trước release.

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
- Không có hạn sử dụng cho delivery tồn đọng.
- Không có persisted authentication circuit để chặn vòng lặp lỗi 535.
- Không có phân loại riêng cho system BCC bị over quota.
- Không có retention/archive job cho outbox và reminder log.

## 3. "Khả năng SMTP" trên production là gì

`SMTP Host`, port, TLS và credential chỉ cho biết cách kết nối. Khả năng SMTP
cần để cấu hình delivery là tập giới hạn của nhà cung cấp và của runtime:

### 3.1 Hồ sơ dịch vụ SMTP production đã xác định

Doanh nghiệp đang sử dụng gói **Email Server Pro #4 (Email Pro #4)** của
**P.A Việt Nam**. Theo trang sản phẩm chính thức, gói này có:

| Hạng mục | Thông số được công bố |
|---|---:|
| Dung lượng | 80 GB SSD |
| Địa chỉ email | 120 |
| Email forwarder | 120 |
| Mail list | 10 |
| Park Domains | 3 |

Theo tài liệu cấu hình Email Server của P.A Việt Nam, kết nối SMTP mã hóa dùng:

- hostname máy chủ được cấp có dạng `mailXX.maychuemail.com`;
- SMTP port `465`;
- SSL được bật;
- MX, SPF, DKIM và DMARC phải được cấu hình theo thông tin P.A cung cấp khi
  khởi tạo dịch vụ.

Khi triển khai production, cấu hình tại `/admin/settings?group=email` phải dùng
đúng hostname cụ thể do P.A cấp, port `465` và SSL. Không suy đoán giá trị `XX`,
không ghi SMTP credential vào kế hoạch, log hoặc module options.

Các thông số 80 GB và 120 địa chỉ email mô tả dung lượng/tài khoản của gói,
**không chứng minh tốc độ gửi SMTP**. Cụm từ quảng bá như "gửi/nhận không giới
hạn" cũng không được dùng để suy ra rằng hệ thống có thể gửi không giới hạn
message/giây, recipient/giây hoặc số kết nối đồng thời.

### 3.2 Giới hạn chính thức và khoảng trống còn lại

Thỏa thuận sử dụng dịch vụ Email Server của P.A Việt Nam công bố các giới hạn
chung cho nhóm **Email Server Pro**, áp dụng làm trần cho gói Email Pro #4:

| Hạng mục | Thông tin chính thức | Cách dùng trong kế hoạch |
|---|---:|---|
| Giới hạn tốc độ gửi | 200 mail/giờ/tài khoản | Trần toàn CRM dùng chung SMTP account |
| Giới hạn tỷ lệ người nhận | 200 mail/giờ/tài khoản | Theo dõi thêm envelope recipient ở mức bảo thủ |
| Số người nhận tối đa | 50 email/lần/tài khoản | Tạm hiểu là tổng To + CC + BCC đến khi P.A xác nhận |
| Kích thước thư tối đa | 30 MB | Reminder canary không dùng attachment và tự đặt trần thấp hơn |
| Hành vi khi vượt tốc độ | Message bị từ chối và phải thử lại | Phân loại rate-limit và áp dụng cooldown |
| SMTP được khuyến nghị | Port 465/SSL hoặc 587/TLS | Canary dùng 465/SSL theo cấu hình đã xác định |
| SMTP port 25 | Bị từ chối cho chứng thực SMTP | Không dùng trong CRM |

Giới hạn `200 mail/giờ/tài khoản` tương đương trung bình một message mỗi 18 giây
nếu một account sử dụng toàn bộ quota. Đây chỉ là phép quy đổi toán học, không
phải burst limit do P.A công bố. Không được dùng phép quy đổi này để suy ra rằng
có thể gửi đều một message mỗi 18 giây hoặc dồn 200 message trong thời gian ngắn.

Các thông tin dưới đây vẫn chưa được P.A công bố đủ chi tiết:

| Hạng mục cần cho Reminder Delivery | Trạng thái |
|---|---|
| Burst tối đa mỗi giây/phút và loại cửa sổ tính quota | Cần P.A xác nhận |
| Cách tính từng To, CC và BCC vào hai giới hạn 200/giờ | Cần P.A xác nhận |
| Số kết nối SMTP đồng thời | Cần P.A xác nhận |
| Có cho phép tái sử dụng kết nối SMTP hay không | Cần P.A xác nhận |
| Quota gửi theo ngày/tháng | Cần P.A xác nhận |
| 50 recipient có phải tổng To + CC + BCC hay không | Cần P.A xác nhận |
| 30 MB được tính trước hay sau MIME/Base64 | Cần P.A xác nhận |
| Timeout và giới hạn thời gian SMTP session | Cần P.A xác nhận |
| Mã lỗi rate-limit, cooldown và chính sách retry | Cần P.A xác nhận |
| Hạn mức áp dụng thêm theo domain hoặc IP | Cần P.A xác nhận |
| IP gửi dùng chung hay riêng cho gói | Cần P.A xác nhận |
| Khả năng truy cập SMTP log, deferred và bounce | Cần P.A xác nhận |

Không đưa một giá trị chưa được P.A xác nhận vào production defaults. Các giới
hạn cPanel chung hoặc thông số của gói khác không được coi là giới hạn của Email
Pro #4. Trong thời gian chờ ticket, chỉ dùng profile Siêu an toàn/Canary tại mục
3.7.

### 3.3 Phiếu xác nhận các thông tin còn thiếu với P.A Việt Nam

Trước khi chốt cấu hình production, gửi một ticket cho P.A và yêu cầu trả lời
bằng văn bản các nội dung sau:

1. Với Email Pro #4, giới hạn 200 mail/giờ sử dụng cửa sổ cố định hay cửa sổ
   trượt; burst tối đa mỗi giây/phút và quota ngày/tháng là bao nhiêu?
2. To, từng địa chỉ CC và BCC được tính thành bao nhiêu đơn vị message/recipient
   quota; giới hạn áp dụng thêm theo domain hoặc IP hay không?
3. Giới hạn 50 email/lần có phải tổng To + CC + BCC; 30 MB được tính trước hay
   sau MIME/Base64?
4. Được mở tối đa bao nhiêu SMTP connection đồng thời; có hỗ trợ connection
   reuse hay không?
5. Khi vượt hạn mức, máy chủ trả SMTP code/enhanced code nào; thời gian cooldown
   hoặc thời điểm được retry là bao lâu?
6. P.A có giới hạn riêng đối với email giao dịch tự động từ CRM hay không?
7. Gói dùng IP gửi chung hay riêng; khách hàng xem SMTP log, deferred và bounce
   ở đâu, và cần cung cấp dữ liệu gì khi mở ticket hỗ trợ?

Lưu câu trả lời của P.A cùng ngày xác nhận vào hồ sơ triển khai. Nếu P.A chỉ trả
lời bằng cuộc gọi, người triển khai phải lập biên bản và yêu cầu xác nhận lại
qua ticket/email trước khi nâng tốc độ gửi.

### 3.4 Thông số phải lấy từ nhà cung cấp/gói dịch vụ

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

### 3.5 Thông số phải đo từ ứng dụng production

1. Tần suất CRON và số worker/instance có thể chạy đồng thời.
2. Số reminder email trung bình và đỉnh theo mỗi lần CRON.
3. Số To + CC + BCC trung bình trên mỗi message.
4. Thời gian SMTP trung bình/p95 và `max_execution_time` của PHP/CRON.
5. Backlog lớn nhất chấp nhận được và SLA gửi, ví dụ 95% trong 15 phút.
6. Các loại email core khác cùng dùng chung SMTP trong cùng đợt CRON.
7. Tổng message và envelope recipient của toàn CRM, không chỉ riêng module
   `sales_pipeline`.

### 3.6 Cách suy ra cấu hình an toàn

Đặt:

- `M_h`: message/giờ provider cho phép, hiện xác định là `200`;
- `R_h`: recipient/giờ provider cho phép, tạm lấy bảo thủ là `200` đến khi P.A
  xác nhận cách tính;
- `A`: số recipient trung bình trên một message, gồm To, CC và BCC;
- `S`: safety factor, đề xuất `0.6-0.8`;
- `C_m`: message/giờ phải dành cho email core ngoài Reminder Delivery;
- `C_r`: recipient/giờ phải dành cho email core ngoài Reminder Delivery.

Ngân sách Reminder Delivery tối đa mỗi giờ:

```text
effective_messages_per_hour = max(0, min(M_h - C_m, (R_h - C_r) / A) * S)
```

Ngoài công thức capacity, dispatcher phải áp dụng hard cap theo cửa sổ rolling
60 phút và 24 giờ. `min_interval_ms` chỉ làm phẳng burst, không thay thế quota
window.

Batch tối đa theo ngân sách còn lại:

```text
email_batch_size <= min(configured_batch, remaining_message_budget,
                        floor(remaining_recipient_budget / recipients_in_message))
```

Giá trị production không được sao chép máy móc từ Mailtrap. Phải lấy thông số
gói SMTP thật, đo lường peak, sau đó chọn safety margin và chạy load test staging.
Nếu chưa có phản hồi chính thức từ P.A, chỉ được chạy canary với một delivery
mỗi lần và danh sách người nhận nội bộ đã cho phép; chưa được bật toàn bộ backlog.

### 3.7 Profile Siêu an toàn/Canary

Profile này là giới hạn nội bộ tạm thời cho các thông tin P.A chưa xác nhận. Nó
không mô tả capacity thật của nhà cung cấp và phải được thay thế sau khi ticket
có phản hồi chính thức.

| Tham số | Giá trị Canary | Lý do |
|---|---:|---|
| Email batch size | 1 | Không tạo burst trong một CRON |
| SMTP concurrency của module | 1 | Advisory lock chỉ cho một worker email |
| Khoảng cách tối thiểu | 30.000 ms | Cao hơn phép quy đổi trung bình 18 giây |
| Message attempt của module/rolling 60 phút | 10 | Dùng tối đa 5% trần 200/giờ |
| Envelope recipient attempt/rolling 60 phút | 25 | Dành phần lớn recipient budget cho email core |
| Message attempt của module/rolling 24 giờ | 50 | Canary có trần ngày nội bộ |
| Envelope recipient attempt/rolling 24 giờ | 125 | Canary có trần recipient ngày nội bộ |
| Envelope recipient tối đa/message | 10 | Thấp hơn trần chính thức 50 |
| Kích thước email render tối đa | 1 MB | Reminder không có attachment |
| Rate-limit cooldown | 3.600 giây + jitter 0..900 giây | Tránh retry dồn trong cùng cửa sổ giờ |
| SMTP connection reuse | Không giả định hỗ trợ | Batch 1 không phụ thuộc reuse |
| Authentication failure | Mở circuit vô thời hạn | Không tự động retry lỗi 535/5.7.8 |

Quy tắc tính Canary:

1. Mỗi To, CC và BCC được tính là một envelope recipient riêng.
2. Mỗi SMTP attempt tiêu thụ budget trước khi gửi, kể cả attempt thất bại hoặc
   process crash sau khi P.A đã nhận DATA.
3. Không sử dụng Mailing List, attachment hoặc gửi đến recipient bên ngoài danh
   sách canary đã phê duyệt.
4. SMTP service account chỉ được hệ thống CRM sử dụng từ nguồn mạng production
   đã phê duyệt; không dùng cùng account trên Outlook hoặc thiết bị cá nhân trong
   giai đoạn Canary.
5. Khi hết một trong các budget, selector giữ delivery ở hàng đợi và đặt
   `next_retry_at` đến thời điểm budget gần nhất được giải phóng; không đánh dấu
   là SMTP failure.
6. Profile này chỉ kiểm soát module. Email core vẫn có thể dùng capacity còn lại,
   do đó Delivery Health phải cảnh báo khi quan sát thấy provider throttle dù
   budget module chưa hết.
7. Không lưu SMTP hostname cụ thể, username, password, IP hoặc raw authentication
   transcript trong tài liệu, option, log hay giao diện.

## 4. Thiết kế mục tiêu cho tầng phân phối

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
  - production Canary: `1`;
  - production chính thức: chưa chốt, lấy từ capacity assessment và tổng lưu
    lượng SMTP của toàn CRM;
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
- Default localhost đề xuất `1100ms`.
- Production Canary dùng batch `1`, interval `30000ms`, rolling message budget
  và rolling recipient budget tại mục 3.7.
- Validation interval: `0..300000ms`; không cho phép production Canary thấp hơn
  `30000ms`.
- Quota được kiểm tra và giữ chỗ trước SMTP attempt. Hết budget chỉ hoãn row,
  không tăng `attempt_count` và không ghi SMTP error giả.

Lưu ý: gói Email Pro #4 là SMTP dùng chung cho các loại email của CRM. Throttle
riêng trong `sales_pipeline` không nhìn thấy email core đang gửi đồng thời, vì
vậy capacity dành cho module phải trừ một phần dự phòng cho email core. Giai
đoạn đầu không thay đổi `Emails_model.php`; nếu đo lường cho thấy nhiều nguồn
gửi vẫn tranh chấp cùng hạn mức, phải lập kế hoạch riêng cho một hàng đợi SMTP
toàn hệ thống.

Ngoài giới hạn kỹ thuật, P.A khuyến nghị duy trì uy tín domain/IP và mật độ gửi
hợp lý để hạn chế email vào spam. Vì vậy dispatcher phải làm phẳng burst, không
dồn toàn bộ backlog vào một lần CRON và không dùng gói production cho bài thử
phát tán tải lớn.

Acceptance criteria:

- Timestamp bắt đầu giữa hai SMTP attempt không nhỏ hơn interval cấu hình.
- Tổng attempt trong rolling 60 phút/24 giờ không vượt profile Canary.
- To + CC + BCC của một message không vượt recipient budget còn lại.
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
- Chỉ dựa vào việc session kết thúc để nhả lock khi runtime đã xác nhận
  `pconnect=false`; `GET_LOCK(..., 0)` không phải lock TTL.
- Giữ stale-processing recovery, nhưng max attempts và backoff phải được áp dụng
  khi thu hồi row stuck.
- Test crash window và ghi rõ residual risk at-least-once: provider accepted
  nhưng process crash trước khi cập nhật DB vẫn có thể gửi lại.

Acceptance criteria:

- Hai worker đồng thời: chỉ một worker dispatch email; CRM không bị chặn.
- Worker fail/exception vẫn release lock khi connection còn tồn tại; session DB
  kết thúc sẽ tự release advisory lock.
- Release gate thất bại nếu runtime production bật persistent connection.

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
- Đối chiếu định kỳ với SMTP log của P.A để phân biệt `accepted` tại CRM với
  `delivered`, `deferred` hoặc `bounced` tại máy chủ email.

Acceptance criteria:

- Admin nhìn được backlog mà không cần query SQL.
- Staff không có quyền settings không truy cập được dữ liệu delivery.

## 5. Quyết định cho 7 tình huống biên

### 5.1 Tranh chấp lưu lượng với Email Core của Perfex CRM

**Bằng chứng Basecode**:

- advisory lock của module chỉ có thể tuần tự hóa email do
  `Reminder_engine` gửi;
- Báo giá, Hóa đơn, Công việc và các email core khác vẫn gọi chung
  `Emails_model` và cùng sử dụng SMTP account của CRM;
- kế hoạch này không sửa `application/models/Emails_model.php`, do đó không thể
  áp đặt một global rate limiter cho toàn Perfex CRM trong phạm vi hiện tại.

**Quyết định thiết kế**:

1. Capacity của Email Pro #4 là ngân sách của **toàn CRM**, không phải ngân sách
   riêng của Reminder Delivery.
2. `C_m` và `C_r` trong công thức tại mục 3.6 phải lấy từ peak của email core và
   có thêm khoảng dự phòng. Không được đặt chúng bằng `0` chỉ vì staging chưa phát sinh Báo giá
   hoặc Hóa đơn cùng lúc.
3. Trước khi P.A trả lời các khoảng trống, áp dụng nguyên vẹn profile Canary tại
   mục 3.7. Không chỉ cấu hình interval mà bỏ qua rolling quota.
4. Profile Canary phân bổ cho module tối đa 10 message attempt và 25 recipient
   attempt mỗi giờ, tương ứng giữ lại phần lớn trần công bố `200/giờ/tài khoản`
   cho email core. Đây là phân bổ nội bộ, không bảo đảm email core sẽ không tự
   vượt quota.
5. Không chạy Reminder load test cùng thời điểm với chiến dịch gửi Hóa đơn/Báo
   giá trên production.
6. Thu thập số lượng email core theo cửa sổ một phút, năm phút và một giờ để
   hiệu chỉnh `C_m/C_r`. Nếu không đo được email core, giữ profile Canary và không
   tăng batch.
7. Nếu quan sát cho thấy email core và module vẫn thường xuyên va chạm, mở một
   kế hoạch riêng cho global SMTP queue/rate limiter của toàn CRM. Không âm thầm
   mở rộng phạm vi bằng cách sửa core trong kế hoạch này.

**Tiêu chí kiểm thử**:

- fake SMTP nhận đồng thời một email core và một reminder; tổng tải được ghi
  nhận, reminder không giả định mình sở hữu toàn bộ capacity;
- canary không giảm interval nếu chưa có số liệu peak email core;
- tài liệu rollout ghi rõ giá trị `C_m/C_r`, nguồn số liệu và safety factor.

### 5.2 Advisory lock, tiến trình crash và persistent connection

**Bằng chứng Basecode**: `application/config/database.php` hiện cấu hình
`'pconnect' => false`. MySQL advisory lock gắn với connection và được giải phóng
khi connection đóng.

**Quyết định thiết kế**:

1. Dùng `GET_LOCK(lock_name, 0)` để worker không chờ: nếu không lấy được lock,
   worker bỏ qua email batch và vẫn xử lý CRM channel.
2. `GET_LOCK(..., 0)` **không làm khóa cũ tự hết hạn**; tham số `0` chỉ có nghĩa
   là không chờ lấy khóa. Không mô tả nó như một lock TTL.
3. Giữ `RELEASE_LOCK()` trong `finally`, đồng thời dựa vào việc đóng connection
   khi PHP process kết thúc để xử lý crash/OS kill.
4. Giai đoạn 0 và mỗi lần release phải kiểm tra cấu hình runtime production vẫn
   là `pconnect=false`. Không chỉ dựa vào file trong repository nếu môi trường
   có override.
5. Ghi log đã sanitize cho kết quả lấy/nhả lock, `CONNECTION_ID()` và thời gian
   giữ lock; không ghi DB credential.
6. Nếu production chuyển sang persistent connection, advisory lock hiện tại
   không còn đạt điều kiện chấp nhận. Khi đó phải thay bằng lease table có
   `owner_token`, `acquired_at`, `lease_expires_at` và compare-and-swap; không
   cố "phá" advisory lock của connection khác.
7. Do P.A chưa công bố SMTP concurrency, profile Canary giả định concurrency
   bằng `1`. Advisory lock là cơ chế thực thi giả định này trong phạm vi module;
   nó không khóa email core.

**Tiêu chí kiểm thử**:

- hai worker đồng thời chỉ một worker gửi email;
- kill worker giữa batch khi `pconnect=false`, worker ở lần CRON sau lấy được
  lock sau khi connection cũ đóng;
- mô phỏng `pconnect=true` làm release gate thất bại và chặn rollout;
- worker không lấy được lock không làm CRM Notification Bell bị chặn.
- fake SMTP không quan sát thấy hai SMTP session Reminder chồng lấn trong
  profile Canary.

### 5.3 Hạn sử dụng của reminder và backlog cũ

**Rủi ro**: khi cooldown hoặc SMTP outage kéo dài, snapshot trong email có thể
không còn hợp thời dù delivery vẫn đến hạn retry.

**Quyết định thiết kế**:

1. Thêm `expires_at` và `expired_at` cho delivery. Giá trị `expires_at` được chốt
   tại thời điểm enqueue từ thời hạn của rule, không được tính lại sau mỗi retry.
2. Thêm trạng thái terminal `expired`. Selector phải chuyển delivery email chưa
   gửi sang `expired` trước khi claim nếu `expires_at <= NOW()`.
3. Delivery `expired` không được retry tự động và không được tính là SMTP
   failure. CRM delivery đã gửi thành công vẫn giữ nguyên.
4. `max_valid_age` phải hỗ trợ theo rule; chỉ dùng global default khi rule chưa
   cấu hình. Khoảng `12-24 giờ` là đề xuất cần Product Owner xác nhận, không phải
   mặc định nghiệp vụ tự động.
5. Không hết hạn một reminder đã có phản hồi hoặc email đã `sent`.
6. Delivery Health hiển thị số lượng expired và lý do
   `reminder_validity_window_elapsed`.
7. Manual retry delivery expired chỉ được phép sau khi hệ thống đánh giá lại
   rule và tạo reminder mới với snapshot/dedupe mới; không hồi sinh snapshot cũ.

**Tiêu chí kiểm thử**:

- email hết hạn trong lúc cooldown không được gửi ở CRON tiếp theo;
- thời gian server, timezone ứng dụng và timezone DB không làm hết hạn sớm;
- reminder hết hạn không phá liên kết/audit của reminder log;
- backlog SLA và expired count được quan sát riêng.

### 5.4 Crash window và bảo đảm at-least-once

SMTP không có giao dịch hai pha với database. P.A có thể đã nhận message và trả
`250 OK`, nhưng CRM mất kết nối trước khi ghi `status='sent'`; delivery đó có thể
được gửi lại.

**Quyết định thiết kế**:

1. Chấp nhận và công bố semantics **at-least-once**: ưu tiên không bỏ sót reminder
   hơn việc đảm bảo tuyệt đối không trùng email.
2. Không đánh dấu `sent` nếu adapter không nhận được kết quả thành công rõ ràng.
3. Không tự đặt `provider_message_id` hoặc idempotency key giả khi SMTP/provider
   không hỗ trợ xác nhận tương ứng.
4. Đưa Reminder ID và Delivery ID ổn định vào phần metadata/footer đã thiết kế
   cho vận hành, tránh lộ thông tin nội bộ nhạy cảm. Chúng chỉ giúp đối chiếu,
   không được quảng bá là cơ chế chống trùng SMTP.
5. Ghi metric `possible_duplicate_after_unknown_outcome` khi lỗi xảy ra sau giai
   đoạn DATA/đang chờ `250`, nếu transcript cho phép phân biệt.
6. Runbook phải hướng dẫn Admin kiểm tra SMTP log của P.A trước khi manual retry
   một delivery có kết quả không xác định.

**Tiêu chí kiểm thử**:

- fake SMTP nhận DATA rồi ngắt kết nối trước phản hồi; row được phân loại
  `unknown_outcome`, không bị ghi sai là `sent`;
- retry có thể tạo bản sao và hành vi này được ghi rõ trong runbook;
- dedupe của Reminder Repository không bị hiểu nhầm là dedupe ở tầng SMTP.

### 5.5 Hòm thư BCC hệ thống đầy dung lượng

**Bằng chứng Basecode**: `Emails_model::send_simple_email()` tự nối option
`bcc_emails` sau hook `before_send_simple_email`. Module chỉ nhận boolean từ
core và không có kết quả cấu trúc theo từng SMTP recipient.

**Quyết định thiết kế**:

1. Classifier bổ sung `system_bcc_over_quota` cho `552`, `5.2.2`, `mailbox full`
   hoặc thông điệp tương đương **chỉ khi** SMTP transcript xác định địa chỉ bị
   từ chối thuộc danh sách `get_option('bcc_emails')`.
2. Sanitize transcript sau khi phân loại; không lưu nguyên địa chỉ BCC hoặc toàn
   bộ SMTP conversation vào `last_error`.
3. Lỗi được chứng minh chỉ thuộc BCC không được mở global rate-limit circuit và
   không `break` toàn bộ email loop. Tạo một Bell alert đã dedupe cho Admin theo
   mỗi incident/cooldown thay vì một alert cho mỗi delivery.
4. Vì adapter hiện chỉ trả boolean, nếu transcript không chứng minh To đã được
   chấp nhận thì không được đánh dấu delivery là `sent`. Phân loại
   `unknown_recipient_outcome`, dừng auto-retry của chính row đó và yêu cầu đối
   chiếu SMTP log để tránh gửi trùng To.
5. Nếu P.A chấp nhận To nhưng phát sinh bounce BCC bất đồng bộ, CRM có thể vẫn
   nhận `true`; Delivery Health phải dựa thêm vào SMTP log/bounce monitoring,
   không chỉ dựa vào boolean của core.
6. Runbook cho incident gồm: kiểm tra quota `crmbcc`, tăng quota hoặc dọn hộp
   thư, gửi email canary, xác nhận BCC nhận được rồi mới đóng cảnh báo.

**Tiêu chí kiểm thử**:

- fake SMTP từ chối đúng BCC bằng `552 5.2.2` nhưng chấp nhận To;
- fake SMTP trả lỗi 552 mơ hồ không chứa recipient;
- BCC incident chỉ sinh một Bell alert có audit và không mở rate-limit circuit;
- địa chỉ BCC không xuất hiện nguyên vẹn trong error hiển thị cho người không có
  quyền Admin.

### 5.6 Đổi mật khẩu SMTP và authentication circuit breaker

**Rủi ro**: retry lỗi `535 Authentication Failed` theo CRON có thể làm P.A xem
đây là hành vi dò mật khẩu và khóa IP/account.

**Quyết định thiết kế**:

1. Phân loại `535`, `5.7.8`, `authentication failed`, `invalid credentials` và
   thông điệp tương đương thành `authentication_configuration`.
2. Ngay lần đầu xác định chắc chắn lỗi authentication:
   - đóng circuit email của Reminder Delivery ở trạng thái persisted;
   - dừng email loop;
   - không tăng/đẩy retry định kỳ cho các row chưa attempt;
   - giữ CRM Notification Bell hoạt động;
   - gửi Bell alert đã dedupe cho các Admin đang active và ghi audit/log đã
     sanitize; tuyệt đối không gửi cảnh báo bằng email qua SMTP đang lỗi.
3. Circuit authentication không tự hết hạn. Admin phải cập nhật credential tại
   `/admin/settings?group=email`, chạy một thao tác kiểm tra kết nối/email canary,
   rồi chủ động mở lại circuit có CSRF, permission và audit.
4. Không lưu username/password hoặc raw auth exchange trong option, database,
   log hay giao diện Delivery Health.
5. Rate-limit/transient cooldown và authentication circuit là hai trạng thái
   khác nhau; không dùng chung một `paused_until` để tránh authentication tự mở
   lại sau vài phút.

**Tiêu chí kiểm thử**:

- hai lần CRON sau lỗi 535 không tạo thêm SMTP authentication attempt;
- Admin nhận một Bell alert, không nhận lặp mỗi năm phút;
- staff thường không thể mở circuit hoặc xem dữ liệu nhạy cảm;
- chỉ test canary thành công và thao tác Admin có audit mới cho phép dispatch lại.

### 5.7 Retention, archive và kiểm soát tăng trưởng database

**Rủi ro**: outbox tăng liên tục làm selector/retry và màn hình vận hành chậm.
Reminder log còn chứa snapshot, phản hồi và audit nên không được xóa cùng
delivery một cách máy móc.

**Quyết định thiết kế**:

1. Thêm maintenance job chạy hàng tuần với lock riêng, giới hạn thời gian chạy
   và xóa/archive theo batch nhỏ, đề xuất `500` row mỗi vòng.
2. Chính sách khởi đầu đề xuất:
   - delivery `sent`, `expired` hoặc `cancelled` cũ hơn `60` ngày: đủ điều kiện
     archive/xóa;
   - delivery `failed`, `processing`, `unknown_outcome` hoặc đang mở incident:
     không tự động xóa;
   - reminder log: giữ tối thiểu `365` ngày và không xóa row có
     `staff_response`; thời hạn cuối cùng cần Product Owner/Compliance duyệt.
3. Xóa delivery con trước; chỉ archive/xóa reminder log khi không còn delivery,
   không có phản hồi cần giữ và đã qua retention được phê duyệt.
4. Lần triển khai đầu ưu tiên archive hoặc chỉ purge delivery terminal; không
   xóa reminder log nếu chưa có quyết định retention nghiệp vụ.
5. Thêm index phục vụ purge; không chạy một `DELETE` không giới hạn trên bảng
   lớn. Ghi số row đã xử lý, thời gian chạy và lần chạy gần nhất.
6. Manual purge phải có dry-run count, xác nhận Admin, CSRF và audit. Không thêm
   nút `Purge all`.

**Tiêu chí kiểm thử**:

- maintenance job không khóa lâu selector email;
- failed/processing/response log không bị xóa;
- job có thể chạy lại an toàn sau khi dừng giữa batch;
- query worker vẫn dùng index sau khi bảng đạt quy mô dữ liệu giả lập lớn.

## 6. Schema, index và cấu hình dự kiến

### 6.1 Migration additive

Tạo migration mới, không sửa migration đã tồn tại:

- thêm `last_error_code`, `last_error_class`, `last_attempt_at`;
- thêm `expires_at`, `expired_at` để quản lý hạn sử dụng;
- giữ `status` dạng `varchar(20)` hiện tại và bổ sung contract cho các trạng thái
  terminal `expired`, `cancelled`, cùng error class `unknown_outcome`; không dùng
  một status mới nếu error class đã mô tả đủ trạng thái;
- thêm index worker và index retention:

```sql
KEY idx_delivery_channel_worker
    (channel, status, next_retry_at, expires_at, id)

KEY idx_delivery_retention
    (status, sent_at, expired_at, id)
```

Cập nhật cả migration và
`sales_pipeline_ensure_reminder_repository_schema()` để active installation và
fresh install cùng đạt một schema. Mỗi ALTER phải idempotent.

Nếu chọn archive thay vì xóa, migration tạo bảng archive riêng với schema tối
thiểu đã được duyệt. Không dùng `CREATE TABLE ... AS SELECT` trong runtime vì
không giữ đầy đủ index/constraint và có thể khóa bảng khó kiểm soát.

Tạo bảng minute bucket module-scope để quota không bị mất giữa các PHP process:

```sql
sales_pipeline_reminder_delivery_rate_buckets
    scope_key varchar(40) NOT NULL
    bucket_minute datetime NOT NULL
    message_attempts int unsigned NOT NULL DEFAULT 0
    recipient_attempts int unsigned NOT NULL DEFAULT 0
    updated_at datetime NOT NULL
    UNIQUE KEY uq_scope_minute (scope_key, bucket_minute)
```

- `scope_key` dùng hằng logic như `default_smtp_account`, không lưu hoặc hash
  SMTP username, hostname, IP hay credential.
- Worker giữ chỗ message/recipient budget trong transaction trước khi gọi SMTP.
- Nếu process crash sau khi giữ chỗ, token không được hoàn lại; đây là lựa chọn
  bảo thủ để tránh gửi vượt quota.
- Bucket cũ hơn retention kỹ thuật, đề xuất 48 giờ, được maintenance job xóa theo
  batch nhỏ.

### 6.2 Options và state dự kiến

```text
sp_reminder_delivery_email_batch_size
sp_reminder_delivery_email_min_interval_ms
sp_reminder_delivery_email_hourly_message_limit
sp_reminder_delivery_email_hourly_recipient_limit
sp_reminder_delivery_email_daily_message_limit
sp_reminder_delivery_email_daily_recipient_limit
sp_reminder_delivery_email_max_recipients_per_message
sp_reminder_delivery_email_max_rendered_bytes
sp_reminder_delivery_max_attempts
sp_reminder_delivery_rate_limit_cooldown_seconds
sp_reminder_delivery_default_max_valid_age_hours
sp_reminder_delivery_retention_days
sp_reminder_delivery_email_circuit_state
sp_reminder_delivery_email_circuit_opened_at
sp_reminder_delivery_email_circuit_reason
```

Không lưu SMTP credential, provider token hoặc raw debug trong module options.
`email_circuit_state` chỉ nhận các giá trị contract như `closed` và
`open_authentication`; rate-limit cooldown vẫn dùng thời điểm hết hạn riêng và
không tự đóng/mở authentication circuit.

Defaults production Canary:

```text
sp_reminder_delivery_email_batch_size=1
sp_reminder_delivery_email_min_interval_ms=30000
sp_reminder_delivery_email_hourly_message_limit=10
sp_reminder_delivery_email_hourly_recipient_limit=25
sp_reminder_delivery_email_daily_message_limit=50
sp_reminder_delivery_email_daily_recipient_limit=125
sp_reminder_delivery_email_max_recipients_per_message=10
sp_reminder_delivery_email_max_rendered_bytes=1048576
sp_reminder_delivery_rate_limit_cooldown_seconds=3600
```

Jitter `0..900` giây là code-owned behavior, không lưu một giá trị ngẫu nhiên cố
định trong options. Không đưa SMTP account, password, token, hostname cụ thể hay
IP vào tên option hoặc giá trị option.

### 6.3 Contract trạng thái delivery

| Trạng thái | Có được selector tự động lấy lại? | Ý nghĩa |
|---|---|---|
| `pending` | Có | Chưa attempt |
| `processing` | Không | Đã được worker claim |
| `failed` | Có, nếu transient và đến hạn | Attempt thất bại có thể retry |
| `sent` | Không | Adapter xác nhận thành công |
| `expired` | Không | Quá hạn sử dụng trước khi gửi |
| `cancelled` | Không | Dừng có chủ đích và có audit |

`unknown_outcome`, `system_bcc_over_quota`, `rate_limited` và
`authentication_configuration` là **error class**, không nhất thiết là status.
Selector phải kết hợp status, error class, attempt count, circuit state,
`next_retry_at` và `expires_at`; không chỉ lọc `status='failed'`.

## 7. Thứ tự triển khai

### Giai đoạn 0 - Capacity assessment production

1. Ghi nhận provider là P.A Việt Nam và gói Email Server Pro #4.
2. Ghi nhận trần công bố: 200 message/giờ/tài khoản, 200 recipient/giờ/tài
   khoản, 50 recipient/message và 30 MB/message.
3. Đối chiếu `/admin/settings?group=email` với hostname cụ thể được cấp, SMTP
   port `465`, SSL và tài khoản dịch vụ; không chụp hoặc lưu mật khẩu vào hồ sơ.
4. Kiểm tra MX, SPF, DKIM và DMARC của sending domain theo hồ sơ P.A đã cấp.
5. Gửi phiếu xác nhận các khoảng trống tại mục 3.3 và lưu phản hồi của P.A.
6. Thu thập burst, quota ngày/tháng, concurrency, cách tính recipient và error
   policy còn thiếu.
7. Đo peak reminder volume, CC fan-out, cron interval, email core dùng chung SMTP
   và execution budget.
8. Áp dụng profile Canary tại mục 3.7, không tự nới hạn mức.
9. Xác minh runtime production dùng `pconnect=false`, option `bcc_emails`, quota
   của mailbox giám sát và danh sách Admin nhận cảnh báo Bell.

**Gate**: giới hạn theo giờ đã có căn cứ để chạy Canary, nhưng không bật toàn bộ
backlog và không nới profile nếu P.A chưa trả lời burst, cách tính To/CC/BCC,
concurrency, cooldown và log. Canary chỉ gửi đến người nhận nội bộ được phép.

### Giai đoạn 1 - Schema và state foundation

1. Viết migration additive và bootstrap schema idempotent.
2. Thêm error fields, expiration fields, rate bucket và index worker/retention.
3. Thêm options Canary có validation và authentication circuit state.
4. Bảo đảm schema/options không chứa SMTP username, password, hostname hoặc IP.
5. Chốt contract trạng thái tại mục 6.3 trước khi viết selector.

### Giai đoạn 2 - Batch, channel isolation và expiration

1. Viết test selector CRM/email và expiration theo timezone.
2. Tách query và batch budget theo channel.
3. Expire email quá `expires_at` trước khi claim.
4. Xác minh CRM vẫn được gửi khi email backlog lớn hoặc email đã hết hạn.

### Giai đoạn 3 - Throttle và concurrency lock

1. Viết test clock/throttle bằng injectable clock/sleeper.
2. Thêm minimum interval, rolling quota 60 phút/24 giờ và capacity reservation
   `C_m/C_r` cho email batch.
3. Giữ chỗ message/recipient token trước mỗi SMTP attempt.
4. Thêm channel advisory lock và kiểm tra `pconnect=false`.
5. Test hai worker, worker crash, execution budget và không sleep sau email cuối.

### Giai đoạn 4 - Error classification và circuit breaker

1. Viết fixture cho Mailtrap rate-limit, SMTP 4xx, auth 535, recipient 5.1.1,
   BCC 552/5.2.2 và unknown outcome sau DATA.
2. Thêm sanitizer/classifier module-scope.
3. Lưu error thật đã sanitize.
4. Break email loop và persist cooldown khi rate-limit.
5. Mở authentication circuit vĩnh viễn đến khi Admin kiểm tra và resume.
6. Phân loại BCC ở mức best-effort, cảnh báo Bell có dedupe và không mở nhầm
   global rate-limit circuit.

### Giai đoạn 5 - Backoff, observability và runbook

1. Viết test backoff/jitter, max attempts và stale-processing recovery.
2. Thêm Delivery Health, circuit state và manual retry/resume có
   permission/CSRF/audit.
3. Hiển thị expired, unknown outcome và incident BCC/authentication đã mask.
4. Viết runbook cho duplicate, BCC full, đổi mật khẩu và SMTP outage.
5. Test masking, alert dedupe và access control.

### Giai đoạn 6 - Retention maintenance

1. Viết test retention và dry-run trên dữ liệu quy mô lớn.
2. Thêm weekly maintenance job với lock riêng và batch giới hạn.
3. Chỉ purge/archive trạng thái terminal theo retention đã duyệt.
4. Xác minh reminder có phản hồi và incident chưa đóng không bị xóa.
5. Chạy `EXPLAIN` cho selector và purge query.

### Giai đoạn 7 - Staging load test

1. Tạo 5, 20 và 100 delivery test có To/CC khác nhau.
2. Mô phỏng provider success, latency, transient failure và rate-limit.
3. Chạy nhiều CRON worker đồng thời.
4. Xác minh không mất delivery, không gửi manager thành email riêng, CRM không
   bị email chặn và backlog drain đúng SLA.
5. Thực hiện tải tổng hợp bằng fake SMTP/Mailtrap; không gửi 100 email thử vào
   Email Pro #4 nếu chưa được P.A cho phép.
6. Với Email Pro #4 chỉ chạy canary đến các mailbox nội bộ đã được phê duyệt,
   quan sát SMTP log và tăng tải từng bước trong hạn mức đã xác nhận.
7. Kiểm thử va chạm với email core bằng fake SMTP và xác minh tổng capacity.
8. Kiểm thử crash window, authentication circuit, BCC full và reminder hết hạn.
9. Kiểm thử rolling window ở biên 59/60 phút và 23/24 giờ, kể cả worker crash
   sau khi giữ token.

### Giai đoạn 8 - Production rollout

1. Backup DB và chạy migration.
2. Xác minh cấu hình SMTP production dùng đúng hostname P.A cấp, port `465` và
   SSL; gửi một email kiểm tra đến mailbox nội bộ.
3. Deploy với batch nhỏ và safety factor bảo thủ.
4. Áp dụng nguyên vẹn profile mục 3.7 và Canary một nhóm rule/email trước khi bật
   toàn bộ.
5. Theo dõi error rate, oldest pending, cron duration và SMTP log/dashboard.
6. Tăng batch/rate từng bước nếu còn dư capacity đã được P.A xác nhận.
7. Bật retention job sau khi dry-run count được Admin kiểm tra.
8. Diễn tập đổi mật khẩu SMTP và xác minh circuit ngăn retry 535.
9. Không nới quota/interval nếu chưa có change record ghi phản hồi P.A, kết quả
   đo email core và phê duyệt vận hành.

## 8. Chiến lược test

### 8.1 Contract/unit

- defaults và validation của các option mới;
- deterministic ordering và channel isolation;
- batch cap;
- backoff + jitter trong khoảng cho phép;
- SMTP classifier/sanitizer;
- rate-limit dừng batch;
- permanent error không retry;
- advisory lock skip/release;
- expiration theo rule và timezone;
- authentication circuit không tự mở lại;
- BCC classifier chỉ kết luận khi recipient khớp;
- alert dedupe và retention eligibility.
- rolling quota 60 phút/24 giờ và recipient accounting;
- token đã giữ không được hoàn lại sau unknown outcome/crash;
- validation Canary không chấp nhận interval hoặc quota vượt profile khi chưa
  có quyền/chính sách nâng cấp.

### 8.2 DB integration

- hai worker cùng select một outbox;
- worker crash sau claim;
- stuck processing recovery;
- index được sử dụng cho query due email;
- manual retry chỉ reset đúng delivery;
- dedupe reminder không bị thay đổi;
- advisory lock được giải phóng sau crash khi `pconnect=false`;
- selector bỏ qua expired và email khi authentication circuit đang mở;
- retention chạy theo batch và không xóa log có phản hồi.
- rate bucket transaction không oversubscribe khi hai worker cạnh tranh;
- bucket không chứa account, hostname, IP hoặc credential.

### 8.3 Manual staging/Mailtrap

- một message gồm To và CC trong header;
- batch nhỏ được gửi qua nhiều lần CRON;
- không có `Too many emails per second` ở cấu hình staging;
- template và link đúng snapshot/entity;
- Mailtrap rate-limit fixture làm circuit mở và backlog giữ nguyên;
- fake SMTP mô phỏng To accepted/BCC 552, lỗi 535 và mất kết nối sau DATA;
- một email core được gửi xen kẽ reminder để đo aggregate rate.
- hết rolling budget hoãn delivery mà không tạo SMTP attempt/error giả.

### 8.4 Canary Email Pro #4

- dùng đúng SMTP SSL `465` và hostname P.A cấp;
- gửi đến mailbox nội bộ đã được phê duyệt, bắt đầu với một delivery;
- xác minh To/CC trong header và dữ liệu reminder/entity;
- đối chiếu trạng thái outbox với SMTP log của P.A;
- thử transient/rate-limit bằng fake SMTP hoặc fixture, không cố tình bắn vượt
  quota trên dịch vụ production;
- chỉ tăng batch/interval sau khi có capacity chính thức và canary không phát
  sinh deferred, bounce hoặc throttle;
- xác minh mailbox BCC còn quota và Bell alert đến đúng Admin khi circuit mở.
- xác minh trong một giờ không quá 10 message attempt/25 recipient attempt và
  trong 24 giờ không quá 50/125.

## 9. Tiêu chí chấp nhận release

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
11. Hồ sơ có phản hồi chính thức của P.A về rate/quota/concurrency và cách tính
    To/CC trước khi bật toàn bộ Reminder Delivery.
12. Cấu hình production dùng đúng hostname được cấp, SMTP `465`, SSL và domain
    đã thiết lập MX/SPF/DKIM.
13. Runtime production xác nhận `pconnect=false`; kill test không để advisory
    lock chặn email ở lần CRON kế tiếp.
14. Delivery quá `expires_at` chuyển sang `expired`, không gửi snapshot cũ và
    không được manual retry trực tiếp.
15. Sau lỗi authentication xác định chắc chắn, hai lần CRON tiếp theo không tạo
    thêm SMTP auth attempt; chỉ Admin có thể resume sau canary thành công.
16. BCC over-quota đã xác định không mở global rate-limit circuit; lỗi mơ hồ
    không bị đánh dấu sai là `sent`.
17. Runbook công bố at-least-once và hướng dẫn xử lý `unknown_outcome` trước khi
    retry để giảm gửi trùng.
18. Retention job chạy theo batch, không xóa failed/processing/incident hoặc
    reminder log có phản hồi.
19. Hồ sơ rollout ghi nhận peak email core, giá trị `C_m/C_r`, safety factor và
    lý do chọn `min_interval_ms`.
20. Production Canary không vượt 10 message attempt/25 recipient attempt trong
    rolling 60 phút hoặc 50/125 trong rolling 24 giờ.
21. Mọi To/CC/BCC đều được tính vào recipient budget trước SMTP attempt.
22. Source, option, log, UI và tài liệu không chứa SMTP password, token, hostname
    cụ thể, IP hoặc raw authentication transcript.

## 10. Rollback và graceful degradation

- Schema mới là additive, không xóa cột/bảng trong rollback khẩn cấp.
- Giảm `email_batch_size` về `1` và tăng interval nếu provider bắt đầu throttle.
- Tắt channel email theo rule nếu SMTP sự cố; CRM channel vẫn hoạt động.
- Không xóa thủ công reminder log/outbox để "dọn backlog" trên production;
  chỉ retention job đã duyệt được archive/purge trạng thái đủ điều kiện.
- Không reset hàng loạt attempt. Manual retry chỉ thực hiện sau khi nguyên nhân đã
  được sửa và có audit.
- Authentication circuit giữ trạng thái mở khi rollback UI/observability; chỉ
  mở lại sau khi SMTP credential được sửa và canary thành công.
- Nếu advisory lock không an toàn vì runtime chuyển sang persistent connection,
  giữ email channel tạm dừng hoặc batch `1` cho đến khi lease lock được triển
  khai và kiểm thử.
- Nếu UI Delivery Health lỗi, dispatcher không phụ thuộc UI và vẫn chạy theo
  options/defaults.

## 11. Deliverables

- migration additive và bootstrap schema;
- options/defaults + settings validation/UI;
- dispatcher tách channel, batch và throttle;
- persisted rolling quota bucket cho message/recipient attempt;
- error classifier/sanitizer;
- backoff, cooldown và advisory lock;
- expiration policy và contract trạng thái delivery;
- authentication circuit + Bell alert có dedupe;
- BCC/unknown-outcome classifier và incident runbook;
- Delivery Health + manual retry/resume endpoint;
- weekly retention/archive job, dry-run và báo cáo maintenance;
- contract, unit, DB integration và manual test checklist;
- phiếu xác nhận capacity Email Pro #4 và biên bản phản hồi của P.A Việt Nam;
- hồ sơ kiểm tra port/SSL/MX/SPF/DKIM/DMARC với account, hostname và IP đã che;
- implementation report ghi thông số SMTP production, kết quả load test và
  cấu hình rollout cuối cùng.

## 12. Nguồn tham chiếu chính thức

- [P.A Việt Nam - Email Server cho doanh nghiệp](https://www.pavietnam.vn/vn/email-server/may-chu-email-cho-doanh-nghiep),
  thông số công khai của gói Email Pro #4, truy cập ngày 2026-08-27.
- [P.A Việt Nam - Hướng dẫn sử dụng dịch vụ Email Server](https://kb.pavietnam.vn/huong-dan-su-dung-dich-vu-email-server.html),
  hostname máy chủ và các cổng SSL, truy cập ngày 2026-08-27.
- [P.A Việt Nam - 10 cách giúp hạn chế mail vào spam](https://kb.pavietnam.vn/10-cach-giup-han-che-mail-vao-spam.html),
  hướng dẫn về uy tín domain/IP và mật độ gửi, truy cập ngày 2026-08-27.
- [P.A Việt Nam - Thỏa thuận sử dụng dịch vụ Email Server](https://www.pavietnam.vn/vn/thoa-thuan-su-dung-dich-vu-email-server.html),
  giới hạn Email Server Pro, quy định kết nối SMTP và DNS, truy cập ngày
  2026-08-27.
- [MySQL 8.0 Reference Manual - Locking Functions](https://dev.mysql.com/doc/refman/8.0/en/locking-functions.html),
  semantics của `GET_LOCK()`, timeout và giải phóng lock khi session kết thúc,
  truy cập ngày 2026-08-27.
- [RFC 3463 - Enhanced Mail System Status Codes](https://www.rfc-editor.org/info/rfc3463/),
  mã `X.2.2` cho mailbox full, truy cập ngày 2026-08-27.
- [RFC 4954 - SMTP Service Extension for Authentication](https://www.rfc-editor.org/info/rfc4954/),
  mã `535 5.7.8` cho authentication credential không hợp lệ, truy cập ngày
  2026-08-27.

Các nguồn trên xác nhận đặc tính gói, cách kết nối và giới hạn chung của Email
Server Pro. Burst, cách tính To/CC/BCC, quota ngày/tháng, concurrency, cooldown,
IP và SMTP log vẫn chỉ được xem là đã xác nhận khi có phản hồi chính thức của
P.A. Profile Canary là chính sách nội bộ tạm thời, không phải thông số nhà cung
cấp.

## 13. Kết quả triển khai ngày 2026-08-27

Sáu giai đoạn trong phạm vi Basecode đã được triển khai theo profile Canary.
Migration `110_version_110.php`, bootstrap idempotent, channel isolation,
expiration, throttle xuyên nhiều lần Cron, rolling quota, advisory lock,
classifier/sanitizer, authentication circuit, Delivery Health, manual
retry/resume và maintenance hàng tuần đã có test contract tương ứng.

Kết quả kiểm thử tại localhost:

- 12 test của module đạt, gồm 44 case Estimate Group hiện hữu;
- PHP lint đạt cho các file PHP tạo mới/chỉnh sửa;
- Delivery Health đã được kiểm tra bằng phiên CRM thật ở desktop và mobile,
  không overflow và không còn lỗi JavaScript sau lần reload cuối;
- không chỉnh sửa `application/models/Emails_model.php`;
- chưa chạy load test SMTP production, crash test hai worker thật hoặc xác minh
  `EXPLAIN` từ DB CLI vì database chỉ khả dụng qua runtime web hiện tại;
- giới hạn chưa được P.A xác nhận vẫn giữ profile Canary, không tự nới quota.

Walkthrough, runbook, danh sách file và release gate được ghi tại
[`plan_reminder_delivery_resilience.implementation.md`](plan_reminder_delivery_resilience.implementation.md).
