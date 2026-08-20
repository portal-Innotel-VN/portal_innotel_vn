# Advanced Cần theo dõi

## Mục tiêu nâng cấp

`Cần theo dõi` phải là danh sách các Deal hoặc Báo giá đang cần xử lý theo trạng thái nghiệp vụ hiện tại.

Reminder chỉ là lớp bổ sung trạng thái để giúp người dùng hiểu item đó đã được hệ thống nhắc như thế nào. Reminder không được là nguồn dữ liệu gốc quyết định item có xuất hiện trong `Cần theo dõi` hay không.

## Nguyên tắc dữ liệu

### 1. Nguồn chính của Cần theo dõi

Nguồn chính phải đến từ bản thể nghiệp vụ hiện tại:

- Deal lấy từ `sales_pipeline`.
- Báo giá lấy từ `estimates`.

Deal cần xuất hiện trong `Cần theo dõi` khi:

- Deal còn tồn tại.
- Deal chưa thắng.
- Deal chưa thua.
- Deal thuộc nhân viên hoặc phạm vi quyền xem hiện tại.

Báo giá cần xuất hiện trong `Cần theo dõi` khi:

- Báo giá còn tồn tại.
- Báo giá chưa đóng hoặc chưa kết thúc theo nghiệp vụ.
- Báo giá thuộc nhân viên bán hàng hoặc người tạo phù hợp với phạm vi quyền xem.

Điều quan trọng: nếu một Deal hoặc Báo giá cần theo dõi nhưng chưa từng có reminder, item đó vẫn phải xuất hiện trong `Cần theo dõi`.

### 2. Reminder là lớp bổ sung trạng thái

Sau khi đã xác định danh sách Deal/Báo giá cần theo dõi từ nguồn chính, hệ thống có thể join thêm reminder để hiển thị thông tin nhắc nhở.

Các thông tin reminder nên bổ sung:

- Đã từng được nhắc chưa.
- Lần nhắc gần nhất là khi nào.
- Rule nào đã kích hoạt nhắc nhở gần nhất.
- Reminder gần nhất đang chờ phản hồi hay đã phản hồi.
- Nhân viên đã phản hồi chưa.
- Thời điểm phản hồi.
- Nội dung phản hồi.
- Link mở khung phản hồi nhanh: `admin/sales_pipeline/reminder_response/{reminder_id}`.

Reminder chỉ làm giàu thông tin hiển thị, không thay thế trạng thái nghiệp vụ của Deal/Báo giá.

## Quy tắc loại trừ

Nếu reminder cũ vẫn tồn tại nhưng Deal hoặc Báo giá đã đóng, item đó không được còn nằm trong `Cần theo dõi`.

Trường hợp này chỉ nên xuất hiện trong `Nhật ký chung`, vì đó là dữ liệu lịch sử.

Ví dụ:

- Deal đã thắng hoặc đã thua nhưng trước đó từng có reminder.
- Báo giá đã accepted, declined, expired hoặc đã chuyển sang trạng thái kết thúc.
- Nhân viên đã phản hồi reminder trước khi bản thể nghiệp vụ được đóng.

Các item này không còn là việc cần xử lý hiện tại, nên không thuộc `Cần theo dõi`.

## Cần theo dõi và Nhật ký chung khác nhau thế nào

### Cần theo dõi

Mục đích:

- Hiển thị các Deal/Báo giá còn cần hành động ở hiện tại.
- Phục vụ thao tác tiếp theo của nhân viên hoặc quản lý.

Nguồn chính:

- Deal/Báo giá hiện trạng.

Reminder:

- Join phụ để hiển thị trạng thái nhắc nhở gần nhất.
- Không phải điều kiện bắt buộc.

Item chưa có reminder:

- Vẫn hiển thị nếu Deal/Báo giá còn cần xử lý.

Item đã đóng:

- Không hiển thị, dù có reminder cũ.

### Nhật ký chung

Mục đích:

- Hiển thị lịch sử reminder, phản hồi, và hoạt động đã xảy ra.
- Phục vụ truy vết và kiểm tra tiến trình.

Nguồn chính:

- `sales_pipeline_reminders_log`.
- Có thể kết hợp thêm `sales_pipeline_reminder_deliveries` để audit kênh CRM/email.
- Có thể kết hợp thêm `sales_pipeline_activity` nếu muốn gom timeline Deal.

Reminder cũ của item đã đóng:

- Vẫn được giữ và hiển thị tại đây theo bộ lọc thời gian.

## Luồng dữ liệu đề xuất

### Bước 1: Lấy danh sách entity cần theo dõi

Deal:

- Query `sales_pipeline`.
- Join `sales_pipeline_statuses`.
- Lọc `is_won = 0`.
- Lọc `is_lost = 0`.

Báo giá:

- Query `estimates`.
- Lọc các trạng thái chưa đóng theo định nghĩa nghiệp vụ.
- Loại các trạng thái đã kết thúc.

### Bước 2: Join reminder gần nhất

Join `sales_pipeline_reminders_log` theo:

- Deal: `entity_type = 'deal'` và `entity_id = sales_pipeline.id`.
- Báo giá: `entity_type = 'estimate'` và `entity_id = estimates.id`.

Chỉ lấy reminder gần nhất theo:

- `responded_at` nếu đã phản hồi.
- `sent_at` nếu đã gửi.
- `created_at` nếu mới tạo nhưng chưa gửi.
- `id` để phá hòa khi trùng thời điểm.

### Bước 3: Render Cần theo dõi

Mỗi item nên có:

- Loại: Deal hoặc Báo giá.
- Tên Deal hoặc số Báo giá.
- Khách hàng.
- Trạng thái nghiệp vụ hiện tại.
- Giá trị hoặc tổng tiền.
- Ngày liên quan: ngày Deal, ngày gửi, hoặc hạn Báo giá.
- Trạng thái reminder nếu có.
- Link xem chi tiết.
- Link phản hồi nhanh nếu reminder còn cần phản hồi.

### Bước 4: Render Nhật ký chung

`Nhật ký chung` đọc theo log, không đọc theo danh sách entity đang mở.

Mỗi log nên có:

- Loại entity.
- Tên Deal hoặc số Báo giá tại thời điểm hiển thị.
- Nội dung reminder.
- Kênh gửi nếu có.
- Nhân viên được nhắc.
- Phản hồi nếu có.
- Thời điểm gửi và phản hồi.
- Trạng thái hiện tại của entity để người xem biết log đó thuộc item đã đóng hay còn mở.

## Cutoff thời gian

Không nên để `Cần theo dõi` hoặc `Nhật ký chung` kéo dài vô hạn.

Đề xuất:

- `Cần theo dõi`: ưu tiên item còn mở trong một khoảng thời gian mặc định, ví dụ 30 hoặc 90 ngày gần nhất, đồng thời có cơ chế đánh dấu item quá hạn nếu vẫn chưa đóng.
- `Nhật ký chung`: lọc theo `date_from` và `date_to`, mặc định là tháng hiện tại hoặc 30 ngày gần nhất.

Các filter nên hỗ trợ:

- 7 ngày gần nhất.
- 30 ngày gần nhất.
- Tháng này.
- Quý này.
- Tùy chọn từ ngày đến ngày.

## Tiêu chí đúng nghiệp vụ

Triển khai nâng cấp được xem là đúng khi:

- Deal/Báo giá chưa từng có reminder vẫn xuất hiện trong `Cần theo dõi` nếu còn cần xử lý.
- Deal/Báo giá đã đóng không còn xuất hiện trong `Cần theo dõi`.
- Reminder cũ của Deal/Báo giá đã đóng vẫn xem được trong `Nhật ký chung`.
- Reminder chỉ bổ sung trạng thái nhắc nhở, phản hồi, và link phản hồi nhanh.
- Điều kiện xuất hiện trong `Cần theo dõi` luôn dựa trên trạng thái nghiệp vụ hiện tại của Deal/Báo giá.
