# Kiểm thử: Hệ thống nhắc nhở thông minh (Deals & Estimates)

> **Module**: Sales Pipeline  
> **Phạm vi kiểm thử**: Luồng nhắc nhở tự động qua 2 kênh (🔔 Quả chuông CRM & ✉️ Email cá nhân) cho cả **Deal (Thương vụ)** và **Báo giá (Estimates)**.
> **Tài liệu tham chiếu**: `workflow_reminder.md`, `core_plane.md`

---

## Trước khi bắt đầu

### 1. Yêu cầu môi trường

| Mục | Yêu cầu |
|---|---|
| Tài khoản Nhân viên Sale | Tài khoản nhân viên có Deal và Báo giá phụ trách (VD: Staff ID = 22) |
| Tài khoản Quản lý / Admin | Tài khoản Admin để kiểm tra góc độ giám sát (Supervisor Mode) |
| Kênh nhận thông báo | Biểu tượng 🔔 Quả chuông trên thanh menu CRM và Hộp thư Email |

### 2. Cách kích hoạt nhắc nhở thủ công (Trigger Cron)

Có 2 cách để kích hoạt hệ thống quét ngay lập tức:

* **Cách 1 (Từ giao diện Admin - Khuyên dùng)**: Truy cập:
  ```text
  http://localhost:8000/admin/misc/run_cron_manually
  ```
* **Cách 2 (Public Cron URL)**:
  ```text
  http://localhost:8000/cron/index
  ```

---

## Phần I: Kiểm thử Nhắc nhở DEAL (Thương vụ)

### TC-D01 — Nhắc nhở Deal đến hạn cập nhật
* **Điều kiện**: Deal đang mở (`is_won = 0, is_lost = 0`), có `reminder_enabled = 1`, `last_reminder_sent` là `NULL` hoặc quá hạn.
* **Các bước**:
  1. Đăng nhập tài khoản NVKD phụ trách Deal.
  2. Kích hoạt Cron qua URL chạy thủ công.
  3. F5 lại trang CRM.
* **Kết quả mong đợi**:
  - [ ] Biểu tượng 🔔 Quả chuông tăng số thông báo.
  - [ ] Nội dung thông báo hiển thị tên Deal / Khách hàng.
  - [ ] Nhấp vào thông báo $\rightarrow$ mở trang Phản hồi nhanh với Header **DEAL**.

### TC-D02 — NVKD gửi phản hồi nhanh cho Deal
* **Các bước**:
  1. Tại trang Phản hồi Deal (`admin/sales_pipeline/reminder_response/{id}`), nhập nội dung vào ô Textarea.
  2. Bấm **`Gửi phản hồi`**.
* **Kết quả mong đợi**:
  - [ ] Form chuyển sang trạng thái đã khóa (Read-only), hiển thị badge `✓ Đã phản hồi`.
  - [ ] Vào chi tiết Deal (`admin/sales_pipeline/deal/{id}`): Lịch sử hoạt động ghi nhận nội dung vừa phản hồi.

---

## Phần II: Kiểm thử Nhắc nhở BÁO GIÁ (Estimates)

### TC-E01 — Nhắc nhở Báo giá đã hết hạn (`ESTIMATE_EXPIRED`)
* **Chuẩn bị**: Tạo 1 Báo giá có **Ngày hết hạn (Expiry Date)** là ngày trong quá khứ (VD: hôm qua).
* **Các bước**:
  1. Chạy Cron thủ công.
  2. Đăng nhập tài khoản NVKD phụ trách Báo giá.
* **Kết quả mong đợi**:
  - [ ] Quả chuông 🔔 xuất hiện thông báo: **"Báo giá đã hết hạn"** kèm Mã báo giá (`BG-xxx`) và Tên khách hàng.
  - [ ] Nhấp vào thông báo $\rightarrow$ mở trang nhắc nhở Báo giá với badge màu xanh thông tin `ℹ Thông tin theo dõi`.
  - [ ] Có nút hành động `[ Xem chi tiết Báo giá ]` dẫn thẳng tới `admin/estimates/list_estimates/{id}#{id}`.

### TC-E02 — Nhắc nhở Báo giá bị khách từ chối (`ESTIMATE_DECLINED_RECENT`)
* **Chuẩn bị**: Chuyển 1 Báo giá sang trạng thái **Từ chối (Declined - status 3)**.
* **Các bước**:
  1. Chạy Cron thủ công.
  2. Mở thông báo nhận được trên Quả chuông 🔔.
* **Kết quả mong đợi**:
  - [ ] Tiêu đề hiển thị: **"Báo giá bị từ chối gần đây"** với trạng thái `◷ Chờ phản hồi`.
  - [ ] Có ô Textarea yêu cầu NVKD giải trình lý do khách từ chối.
  - [ ] Nhập lý do (VD: *"Khách chọn đối thủ vì giá rẻ hơn 5%"*) và bấm **`Gửi phản hồi`** thành công.

### TC-E03 — Nhắc nhở Báo giá nháp để quá lâu (`ESTIMATE_DRAFT_TOO_LONG`)
* **Điều kiện**: Báo giá ở trạng thái Nháp (Draft - status 1) tạo cách đây $\ge 3$ ngày.
* **Kết quả mong đợi**:
  - [ ] Nhận thông báo **"Báo giá nháp quá lâu"**.
  - [ ] Hướng dẫn NVKD hoàn thiện hoặc gửi báo giá cho khách hàng.

---

## Phần III: Kiểm thử Giám sát & Báo cáo của Quản lý

### TC-M01 — Quản lý xem Nhật ký hoạt động phân luồng độc lập theo Tab Dashboard
* **Mục tiêu**: Đảm bảo tab **"Nhật ký hoạt động"** trong Drawer hiển thị thông tin độc lập 100% theo đúng ngữ cảnh của Tab đang chọn trên Dashboard (`Deal (Thương vụ)` hoặc `Báo giá`).

#### Kịch bản 1: Đang ở Tab "Deal (Thương vụ)"
1. Truy cập `admin/sales_pipeline/dashboard?dashboard_tab=deals`.
2. Bấm vào tên NVKD để mở Drawer $\rightarrow$ chọn tab **"Nhật ký hoạt động"**.
* **Kết quả mong đợi**:
  - [ ] Danh sách chỉ hiển thị lịch sử nhắc nhở và phản hồi của **Deal (Thương vụ)** (`entity_type = 'deal'`).
  - [ ] Không bị lẫn các cảnh báo về Báo giá.

#### Kịch bản 2: Đang ở Tab "Báo giá"
1. Truy cập `admin/sales_pipeline/dashboard?dashboard_tab=estimates`.
2. Bấm vào tên NVKD để mở Drawer $\rightarrow$ chọn tab **"Nhật ký hoạt động"**.
* **Kết quả mong đợi**:
  - [ ] Danh sách chỉ hiển thị lịch sử nhắc nhở và phản hồi của **Báo giá (Estimates)** (`entity_type IN ('estimate', 'staff_estimate_period')`).
  - [ ] Hiển thị rõ Mã báo giá (`BG-xxx`), lý do cảnh báo (Hết hạn, Từ chối, Nháp lâu) cùng nội dung phản hồi của NVKD.
  - [ ] Không bị lẫn các thông báo của Deal.

---

### TC-M02 — Chế độ Giám sát (Supervisor Mode)
* **Các bước**:
  1. Quản lý truy cập link `admin/sales_pipeline/reminder_response/{id}` của NVKD.
* **Kết quả mong đợi**:
  - [ ] Hiển thị banner màu xanh: **"Chế độ Giám sát (Supervisor Mode - Read-only)"**.
  - [ ] Quản lý đọc được toàn bộ nội dung phản hồi của NVKD nhưng form không cho phép chỉnh sửa thay.

---

## Bảng tổng hợp kết quả kiểm thử

| Mã TC | Hạng mục kiểm thử | Đối tượng | Pass | Fail | Ghi chú |
|---|---|:---:|:---:|:---:|---|
| TC-D01 | Nhắc nhở Deal đến hạn | Deal | ☐ | ☐ | |
| TC-D02 | NVKD phản hồi Deal | Deal | ☐ | ☐ | |
| TC-E01 | Báo giá hết hạn | Báo giá | ☐ | ☐ | |
| TC-E02 | Báo giá bị từ chối (có form phản hồi) | Báo giá | ☐ | ☐ | |
| TC-E03 | Báo giá nháp quá lâu | Báo giá | ☐ | ☐ | |
| TC-M01 | Quản lý xem Nhật ký hoạt động độc lập (Deal / Báo giá) | Giám sát | ☐ | ☐ | |
| TC-M02 | Chế độ Giám sát (Supervisor Mode) | Giám sát | ☐ | ☐ | |
