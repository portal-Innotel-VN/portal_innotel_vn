# Kiểm thử: Hệ thống nhắc nhở thông minh (Notification + Email)

> **Module**: Sales Pipeline  
> **Phạm vi kiểm thử**: Luồng gửi thông báo qua 2 kênh — 🔔 Quả chuông (CRM Notification) và ✉️ Email cá nhân  
> **Tài liệu tham chiếu**: `workflow_reminder.md`, `core_plane.md`

---

## Trước khi bắt đầu

### Yêu cầu môi trường

| Mục | Yêu cầu |
|---|---|
| Tài khoản kiểm thử | 1 tài khoản **Nhân viên Sale** có Deal đang mở |
| Tài khoản quan sát | 1 tài khoản **Admin / Quản lý** để kiểm tra từ góc độ giám sát |
| Email nhân viên | Phải là email thật và có thể nhận thư (Gmail/Outlook) |
| Deal có `reminder_enabled = 1` | Mục "Nhắc nhở tự động" đã được bật trên Deal |
| `last_reminder_sent` | Để trống (`NULL`) hoặc cũ hơn `reminder_frequency` ngày |

### Cách kích hoạt nhắc nhở thủ công (không chờ Cron)

Có 2 cách để trigger Cron Job ngay lập tức trong quá trình kiểm thử:

**Cách 1 — Kích hoạt từ giao diện Admin (Khuyên dùng khi đang đăng nhập Admin):**
Mở tab mới trên trình duyệt và truy cập:
```text
http://localhost:8000/admin/misc/run_cron_manually
```
*(Hệ thống sẽ chạy Cron ngay lập tức và tự động chuyển về trang Cấu hình Cron Job)*

**Cách 2 — Kích hoạt qua đường dẫn Cron công khai (Public Cron URL):**
* **Trên trình duyệt**: Mở URL `http://localhost:8000/cron/index`
* **Trên Terminal**: Chạy lệnh `curl -s http://localhost:8000/cron/index`

> [!NOTE]
> Đường dẫn chính xác của Perfex CRM là `/cron/index` (không có chữ `/admin/`). Khi chạy thành công, trang `/cron/index` sẽ hiển thị một trang trắng (empty output) do tác vụ ngầm đã thực thi xong.

---

## Phần I: Kiểm thử kênh 🔔 Notification (Quả chuông)

### TC-N01 — Notification xuất hiện đúng nhân viên

| | |
|---|---|
| **Mục tiêu** | Sau khi Cron chạy, nhân viên Sale phụ trách Deal nhận được thông báo trên thanh navigation |
| **Điều kiện** | Deal chưa thắng/thua, `reminder_enabled = 1`, đến hạn nhắc |

**Các bước:**
1. Đăng nhập bằng tài khoản **Nhân viên Sale** phụ trách Deal kiểm thử.
2. Ghi lại số thông báo hiện tại trên biểu tượng 🔔 (ví dụ: `3`).
3. Kích hoạt Cron (Cách A hoặc B ở trên).
4. Tải lại trang bất kỳ trong Admin (nhấn F5).

**Kết quả mong đợi:**
- [ ] Số thông báo trên biểu tượng 🔔 **tăng thêm 1** (ví dụ: `3` → `4`).
- [ ] Mở danh sách thông báo → thấy mục mới có nội dung đề cập đến tên Deal / tên khách hàng.
- [ ] Thông báo **không** xuất hiện trên tài khoản nhân viên khác không phụ trách Deal.

---

### TC-N02 — Nội dung thông báo đúng

| | |
|---|---|
| **Mục tiêu** | Nội dung thông báo trên chuông phản ánh đúng Deal đang nhắc |

**Các bước:**
1. Mở danh sách thông báo (bấm vào biểu tượng 🔔).
2. Quan sát mục thông báo vừa sinh.

**Kết quả mong đợi:**
- [ ] Hiển thị tên Deal hoặc tên khách hàng trong nội dung thông báo.
- [ ] Khi **bấm vào thông báo** → hệ thống điều hướng đến trang Phản hồi nhanh: `admin/sales_pipeline/reminder_response/{reminder_id}`.
- [ ] URL `reminder_id` trong đường dẫn là số nguyên dương hợp lệ.

---

### TC-N03 — Không trùng lặp thông báo

| | |
|---|---|
| **Mục tiêu** | Cùng một Deal không sinh thêm notification nếu chưa đến hạn nhắc lại |

**Các bước:**
1. Sau khi TC-N01 thành công, chạy Cron thêm một lần nữa ngay lập tức.
2. Tải lại trang.

**Kết quả mong đợi:**
- [ ] Số thông báo trên 🔔 **không tăng thêm** (vì `last_reminder_sent` vừa được cập nhật, chưa đến hạn).

---

### TC-N04 — Admin thấy thông báo ở chế độ Giám sát

| | |
|---|---|
| **Mục tiêu** | Khi Admin/Quản lý truy cập link phản hồi của nhân viên, hiển thị đúng chế độ Giám sát |

**Các bước:**
1. Lấy URL `admin/sales_pipeline/reminder_response/{reminder_id}` từ thông báo của nhân viên.
2. Đăng nhập bằng tài khoản **Admin / Quản lý**.
3. Truy cập URL trên.

**Kết quả mong đợi:**
- [ ] Trang hiển thị banner **"Chế độ Giám sát (Supervisor Mode)"** có màu phân biệt.
- [ ] Không có textarea nhập liệu → chế độ **read-only**.
- [ ] Vẫn thấy đầy đủ thông tin Deal / Báo giá và nội dung nhắc nhở.

---

## Phần II: Kiểm thử kênh ✉️ Email

### TC-E01 — Email được gửi đến đúng địa chỉ

| | |
|---|---|
| **Mục tiêu** | Nhân viên Sale nhận được email nhắc nhở vào hộp thư cá nhân |

**Các bước:**
1. Xác nhận tài khoản nhân viên có email hợp lệ trong hệ thống CRM (`admin/staff/member/{id}`).
2. Kích hoạt Cron để chạy nhắc nhở.
3. Đợi tối đa **2 phút**, kiểm tra hộp thư đến (Inbox) của email nhân viên.
4. Nếu không thấy, kiểm tra thư mục **Spam / Junk**.

**Kết quả mong đợi:**
- [ ] Email đến hộp thư với **tiêu đề** dạng: `[CRM Notification] <Tên Deal hoặc Mã Báo giá>`.
- [ ] Người gửi (From) là email hệ thống CRM đã cấu hình.
- [ ] Email **không** được gửi đến nhân viên khác không phụ trách Deal.

---

### TC-E02 — Nội dung Email đầy đủ

| | |
|---|---|
| **Mục tiêu** | Email chứa đủ thông tin nghiệp vụ và nút hành động |

**Các bước:**
1. Mở email nhận được từ TC-E01.
2. Đọc toàn bộ nội dung email.

**Kết quả mong đợi:**
- [ ] **Lời chào cá nhân**: Xưng tên nhân viên (`Chào [Tên nhân viên],...`).
- [ ] **Thông tin đối tượng**: Loại (Deal / Báo giá), Tên đối tượng, Tên khách hàng.
- [ ] **Trạng thái và giá trị**: Trạng thái Deal/Báo giá, Giá trị, Ngày kỳ vọng.
- [ ] **Hướng dẫn hành động**: 3 câu hỏi định hướng (Hôm qua / Hôm nay / Hỗ trợ).
- [ ] **Nút CTA chính**: `[ Phản hồi nhanh ]` dẫn đến `admin/sales_pipeline/reminder_response/{reminder_id}`.
- [ ] **Link phụ**: `Xem chi tiết Deal` hoặc `Xem chi tiết Báo giá`.

---

### TC-E03 — Nút CTA trong Email hoạt động đúng

| | |
|---|---|
| **Mục tiêu** | Bấm nút CTA trong email mở đúng trang Phản hồi nhanh |

**Các bước:**
1. Trong email nhận được, bấm vào nút **`Phản hồi nhanh`**.
2. Trình duyệt sẽ mở URL.

**Kết quả mong đợi:**
- [ ] URL mở ra dạng `http://<domain>/admin/sales_pipeline/reminder_response/{id}`.
- [ ] Trang tải thành công, **không** ra lỗi 404 / Access Denied.
- [ ] Hiển thị đúng thông tin Deal / Báo giá tương ứng với thông báo trong email.
- [ ] Nếu đăng nhập đúng nhân viên phụ trách → có textarea để nhập phản hồi.

---

### TC-E04 — Email không gửi khi nhân viên không có email

| | |
|---|---|
| **Mục tiêu** | Hệ thống bỏ qua (skip) gửi email nếu nhân viên không có địa chỉ email |

**Các bước:**
1. Tạm thời xóa email khỏi tài khoản nhân viên kiểm thử.
2. Kích hoạt Cron.
3. Kiểm tra bảng `tblsales_pipeline_reminders_log`.

**Kết quả mong đợi:**
- [ ] Bản ghi log vẫn được INSERT (reminder đã được tạo).
- [ ] Không có lỗi PHP / server crash.
- [ ] Notification 🔔 vẫn xuất hiện bình thường (kênh Email thất bại không ảnh hưởng kênh CRM).

---

## Phần III: Kiểm thử Trang Phản hồi nhanh

### TC-R01 — Phản hồi lần đầu thành công

| | |
|---|---|
| **Mục tiêu** | Nhân viên nhập và gửi phản hồi thành công, hệ thống ghi nhận đúng |

**Các bước:**
1. Nhấp vào link phản hồi từ 🔔 hoặc ✉️ (đăng nhập đúng tài khoản nhân viên phụ trách).
2. Quan sát badge trạng thái: phải hiển thị `◷ Chờ phản hồi`.
3. Nhập nội dung vào textarea (ví dụ: `Đã liên hệ khách, đang chờ họ xác nhận ngân sách.`).
4. Bấm nút **`Gửi phản hồi`**.

**Kết quả mong đợi:**
- [ ] Hệ thống hiển thị thông báo thành công (màu xanh lá).
- [ ] Badge trạng thái đổi thành `✓ Đã phản hồi`.
- [ ] Nội dung phản hồi hiển thị lại trong ô read-only màu nền xám nhạt.
- [ ] Form nhập liệu **bị khóa**, không thể nhập thêm.
- [ ] Trong DB: `tblsales_pipeline_reminders_log` — `staff_response` không còn `NULL`, `responded_at` có giá trị datetime.

---

### TC-R02 — Không thể phản hồi hai lần cùng reminder

| | |
|---|---|
| **Mục tiêu** | Sau khi đã phản hồi, cố gắng POST lại sẽ bị từ chối |

**Các bước:**
1. Sau TC-R01, dùng Developer Tools hoặc Postman gửi POST trực tiếp đến:  
   `POST admin/sales_pipeline/respond_reminder/{reminder_id}`  
   với body `staff_response = "thử gửi lại"`.

**Kết quả mong đợi:**
- [ ] Server trả về **HTTP 409** (Already Responded) hoặc thông báo lỗi tương đương.
- [ ] Dữ liệu trong DB **không bị ghi đè**.

---

### TC-R03 — Phản hồi rỗng bị từ chối

| | |
|---|---|
| **Mục tiêu** | Gửi form với textarea để trống phải báo lỗi |

**Các bước:**
1. Truy cập trang phản hồi của reminder chưa phản hồi.
2. **Không nhập gì** vào textarea.
3. Bấm nút `Gửi phản hồi`.

**Kết quả mong đợi:**
- [ ] Server trả về **HTTP 422** hoặc hiển thị thông báo lỗi yêu cầu nhập nội dung.
- [ ] Reminder vẫn ở trạng thái **Pending** (không được đánh dấu là đã phản hồi).

---

## Phần IV: Kiểm thử Tính toàn vẹn Dữ liệu

### TC-D01 — Log nhắc nhở được ghi đúng

| | |
|---|---|
| **Mục tiêu** | Mỗi lần nhắc đều tạo đúng 1 bản ghi trong `tblsales_pipeline_reminders_log` |

**Kiểm tra SQL:**
```sql
SELECT id, pipeline_id, staff_id, reminder_type, message,
       staff_response, responded_at, sent_at
FROM tblsales_pipeline_reminders_log
ORDER BY id DESC
LIMIT 10;
```

**Kết quả mong đợi:**
- [ ] Bản ghi mới nhất có `pipeline_id` khớp với Deal kiểm thử.
- [ ] `staff_id` khớp với ID nhân viên phụ trách.
- [ ] `sent_at` có giá trị datetime hợp lệ (gần với thời điểm chạy Cron).
- [ ] `staff_response = NULL` (chưa phản hồi).

---

### TC-D02 — `last_reminder_sent` được cập nhật trên Deal

| | |
|---|---|
| **Mục tiêu** | Sau khi nhắc, Deal cập nhật mốc thời gian để tính hạn nhắc tiếp |

**Kiểm tra SQL:**
```sql
SELECT id, deal_name, reminder_enabled, reminder_frequency, last_reminder_sent
FROM tblsales_pipeline
WHERE id = {deal_id};
```

**Kết quả mong đợi:**
- [ ] `last_reminder_sent` có giá trị gần với thời điểm Cron chạy.
- [ ] Giá trị này không phải `NULL` (đã được cập nhật sau lần nhắc).

---

### TC-D03 — Phản hồi ghi vào Activity Feed của Deal

| | |
|---|---|
| **Mục tiêu** | Phản hồi của nhân viên xuất hiện trong lịch sử hoạt động của Deal |

**Các bước:**
1. Sau TC-R01 (đã phản hồi thành công), mở trang chi tiết Deal: `admin/sales_pipeline/deal/{deal_id}`.
2. Cuộn xuống phần **Lịch sử hoạt động** (Activity Feed).

**Kết quả mong đợi:**
- [ ] Có bản ghi mới trong Activity Feed ghi nhận nội dung phản hồi của nhân viên.
- [ ] Timestamp phản hồi khớp với `responded_at` trong bảng log.

---

## Bảng tổng hợp kết quả

| Mã TC | Mô tả | Pass | Fail | Ghi chú |
|---|---|:---:|:---:|---|
| TC-N01 | Notification đúng nhân viên | ☐ | ☐ | |
| TC-N02 | Nội dung thông báo đúng | ☐ | ☐ | |
| TC-N03 | Không trùng lặp notification | ☐ | ☐ | |
| TC-N04 | Admin thấy Supervisor Mode | ☐ | ☐ | |
| TC-E01 | Email gửi đúng địa chỉ | ☐ | ☐ | |
| TC-E02 | Nội dung email đầy đủ | ☐ | ☐ | |
| TC-E03 | Nút CTA email hoạt động | ☐ | ☐ | |
| TC-E04 | Skip email khi thiếu địa chỉ | ☐ | ☐ | |
| TC-R01 | Phản hồi lần đầu thành công | ☐ | ☐ | |
| TC-R02 | Không phản hồi hai lần | ☐ | ☐ | |
| TC-R03 | Phản hồi rỗng bị từ chối | ☐ | ☐ | |
| TC-D01 | Log nhắc nhở ghi đúng | ☐ | ☐ | |
| TC-D02 | `last_reminder_sent` cập nhật | ☐ | ☐ | |
| TC-D03 | Phản hồi vào Activity Feed | ☐ | ☐ | |

---

## Điều kiện Pass / Fail toàn bộ

- **Pass**: Tất cả 14 test case đều Pass.
- **Fail có thể chấp nhận**: TC-E04 bỏ qua nếu môi trường kiểm thử không có tài khoản thiếu email.
- **Fail nghiêm trọng (Blocker)**: Bất kỳ lỗi nào trong nhóm TC-N01, TC-E01, TC-R01, TC-D01.
