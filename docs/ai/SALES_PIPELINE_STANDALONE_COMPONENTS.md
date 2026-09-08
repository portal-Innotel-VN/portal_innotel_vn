# Sales Pipeline — Standalone và Legacy Components

Tài liệu này phân biệt code chạy trong Production runtime với các thành phần
được giữ lại cho bảo trì, rollback hoặc tác vụ ngoại tuyến. Việc file/class tồn
tại không đồng nghĩa nó được phép gắn vào HTTP hook hoặc Cron hook.

## Cost Price Alert subsystem (FIND-13)

Các method `create_cost_price_alert()`, `send_cost_price_alert()` và
`process_cost_price_alerts()` trong `Sales_pipeline_model.php` hiện không có
Controller, hook hoặc scheduler gọi vào. Chúng là một tính năng backlog, không
phải luồng thông báo đang vận hành.

`resolve_cost_price_alert()` không hoàn toàn dead: `update_cost_price()` gọi
method này để đóng một alert cũ nếu dữ liệu alert từng tồn tại. Vì vậy không xóa
cụm code này trong đợt refactor hiện tại.

Quy tắc sử dụng:

- Không đăng ký `process_cost_price_alerts()` vào Cron nếu chưa có đặc tả về
  recipient, cadence, chống gửi lặp và test delivery.
- Không coi bảng Cost Price Alert là nguồn sự thật cho Reminder Engine.
- Nếu kích hoạt trong tương lai, phải bổ sung specification, migration review,
  permission và regression tests riêng.

## Currency Data Sanitizer (FIND-14)

`Currency_data_sanitizer.php` là công cụ maintenance ngoại tuyến có kiểm soát,
không phải service runtime. Nó chỉ được dùng trong quy trình làm sạch dữ liệu đã
được Finance phê duyệt.

Quy tắc sử dụng:

- Không gọi từ Dashboard, request lưu Deal/Estimate hoặc Cron thông thường.
- Bắt buộc chạy dry-run, xác minh checksum manifest, mã phê duyệt Finance và
  before-value guard trước khi apply.
- Apply phải chạy trong transaction và lưu audit before/after.
- Thiếu rate, approval hoặc lock hợp lệ phải fail closed; không fallback tỷ giá
  `1.0` cho ngoại tệ.

## Legacy Estimate helpers (FIND-12)

Các method sau được giữ tạm để rollback nhưng đã có `@deprecated` và không được
thêm caller mới:

- `get_reminder_context()` — thay bởi context builder trong `Reminder_engine`.
- `get_estimate_copy_source_id()` — copy context thuộc
  `Estimate_revision_service`.
- `create_estimate_group()`, `append_estimate_revision()` và
  `insert_estimate_version()` — thay bởi `Estimate_revision_service`.

Chỉ xóa các method này trong một release riêng sau khi static call-site scan và
toàn bộ Estimate Group/Reminder regression suite đều PASS.

## Performance Score V2 (FIND-09)

`Performance_score_service` là runtime orchestration seam. Service yêu cầu
`Performance_score_dispatcher` chọn calculator theo kỳ và ngày cutover; Model
không được khởi tạo trực tiếp calculator V1/V2 để tính leaderboard.

Cutover hiện hành:

- Tháng từ `2026-09-01`: V2.
- Tuần từ `2026-09-07`: V2.
- Quý từ `2026-10-01`: V2.
- Năm từ `2027-01-01`: V2.

Ranking luôn được tính trên toàn cohort trước khi Controller áp dụng projection
Admin/Staff.
