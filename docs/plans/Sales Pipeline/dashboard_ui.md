# Phân tích Giao diện (UI/UX) - Sales Pipeline Dashboard

> **Module**: Sales Pipeline  
> **Đường dẫn**: `/admin/sales_pipeline/dashboard`  
> **Phiên bản tài liệu**: 1.1  
> **Cập nhật lần cuối**: 2026-08-12

Tài liệu này mô tả kiến trúc HTML/CSS và luồng xử lý JavaScript (Client-side) của trang Dashboard phân tích hiệu suất Sales Pipeline dựa trên Basecode hiện tại.

> **Ranh giới trạng thái**: Mục 1–4 là **As-Is**. Mục 5 là **To-Be**, mô tả phân hệ “Khách hàng cần theo dõi” dựa trên Reminder Repository/Lifecycle Rule và chưa tồn tại hoàn chỉnh trong basecode.

---

## 1. Controller & Routing (Server-side)

Trang Dashboard được khởi tạo thông qua Controller `Sales_pipeline.php`:

| Endpoint | Method | Trách nhiệm | View render |
|---|---|---|---|
| `dashboard()` | GET | Khởi tạo trang khung (Shell). Render toàn bộ layout ban đầu với dữ liệu kỳ hiện tại (`this_month`). | `views/dashboard.php` |
| `ajax_dashboard_leaderboard()` | GET | Endpoint AJAX trả về một chuỗi HTML khi người dùng thay đổi bộ lọc thời gian. | `views/partials/_dashboard_content.php` |
| `dashboard_staff_pipeline/{id}` | GET | Endpoint AJAX trả về chuỗi HTML chứa cơ hội mở và reminder feed của một Staff để hiển thị trong Drawer. | `views/_dashboard_staff_pipeline.php` |

---

## 2. Kiến trúc HTML / CSS

Cấu trúc giao diện được chia thành một "Khung ứng dụng" (Shell) và các vùng nội dung (Content area) có thể tải lại bất đồng bộ. File View chính là `modules/sales_pipeline/views/dashboard.php`.

### 2.1 CSS & Naming Convention
- File style chính: `assets/css/dashboard.css`
- Sử dụng phương pháp đặt tên chuẩn **BEM (Block Element Modifier)** kết hợp với tiền tố `sp-` để tránh xung đột với Perfex CRM gốc (VD: `sp-dashboard-shell`, `sp-dashboard-drawer__panel`, `sp-dashboard-state--error`).

### 2.2 Cấu trúc Khung (Shell)

Khung chính của Dashboard bao gồm:
1. **`main.sp-dashboard-shell`**: Bọc toàn bộ nội dung. Các thẻ `data-*` (như `data-sales-pipeline-dashboard`, `data-staff-url`, `data-loading-message`) được nhúng trực tiếp vào đây để truyền cấu hình từ PHP sang Javascript, loại bỏ việc hard-code JS.
2. **`section.sp-dashboard-overview`**: Chứa Header (Tiêu đề, nút Refresh, nút View Pipeline) và Filterbar (Dropdown chọn thời gian `this_week`, `this_month`,...).
3. **`div.sp-dashboard-content-wrapper`**: Đây là vùng chứa (container) động có thuộc tính `data-dashboard-content-wrapper`. Mỗi khi đổi bộ lọc, toàn bộ nội dung bên trong vùng này sẽ bị ghi đè bởi HTML trả về từ AJAX (`_dashboard_content.php`).

### 2.3 Mô hình UI: Drawer (Slide-out Panel)
Thay vì chuyển trang khi bấm vào xem chi tiết một nhân viên (Staff Leaderboard), hệ thống sử dụng UI Pattern **Drawer** (Ngăn kéo trượt từ cạnh phải màn hình):
- Thẻ HTML: `div#sp-dashboard-drawer.sp-dashboard-drawer`
- Gồm 1 lớp phủ nền (`sp-dashboard-drawer__backdrop`) và 1 bảng điều khiển (`sp-dashboard-drawer__panel`).
- Hỗ trợ tốt Accessibility (a11y) với `role="dialog"`, `aria-modal="true"`.

---

## 3. Luồng xử lý Javascript (`dashboard.js`)

File `assets/js/dashboard.js` là một module đóng gói dạng IIFE (`(function ($) { ... })(jQuery);`), hoàn toàn độc lập và không rò rỉ biến ra Global. Dưới đây là các luồng tương tác chính:

### 3.1 Xử lý URL & Bookmark (History API)
Hàm `updateDashboardUrl(period, tab)` sử dụng `window.history.replaceState` để tự động cập nhật Query String trên thanh địa chỉ của trình duyệt mỗi khi người dùng đổi bộ lọc thời gian hoặc chuyển tab. 
- **Lợi ích**: Giúp người dùng có thể tải lại trang (F5) hoặc gửi link cho người khác mà vẫn giữ nguyên giao diện họ đang xem (không bị reset về mặc định).

### 3.2 Xử lý Tabs (Báo giá / Deal)
- Hệ thống hỗ trợ 2 tab chính: `deals` và `estimates`.
- **Keyboard Navigation**: Hỗ trợ người dùng chuyển tab bằng bàn phím (Phím mũi tên `ArrowLeft`, `ArrowRight`, `Home`, `End`) thông qua sự kiện `keydown` - một tiêu chuẩn UI cao cấp.
- Khi chuyển tab, class `is-active` và thuộc tính `aria-selected` sẽ được toggle tương ứng.

### 3.3 Tương tác AJAX Bảng xếp hạng (Leaderboard)
Khi Dropdown thời gian (`#sp-dashboard-time-filter`) thay đổi:
1. JS Hủy (abort) AJAX request cũ nếu đang chạy.
2. Cập nhật URL trình duyệt (replaceState).
3. Đặt hiệu ứng loading lên container (`.sp-loading`, `aria-busy="true"`).
4. Bắn AJAX GET tới `ajax_dashboard_leaderboard` kèm theo `period` và `dashboard_tab`.
5. **Thành công**: Ghi đè HTML vào container và kích hoạt lại các Tooltip (Bootstrap tooltip).
6. **Thất bại**: Bắn thông báo báo lỗi qua hàm `sp_alert('danger', message)`.

### 3.4 Tương tác AJAX Drawer (Chi tiết nhân viên)
Khi bấm vào một nhân viên (`.js-sp-open-staff`):
1. Drawer được bật class `is-open` trượt ra ngoài, body bị add class chống cuộn trang (`sp-dashboard-drawer-open`).
2. Trạng thái Loading (Spinner) được vẽ ra trong Drawer.
3. Bắn AJAX tới `dashboard_staff_pipeline/{staffId}`.
4. Render HTML chi tiết vào trong Drawer.
5. **Thoát Drawer**: Có thể bấm phím `Escape` trên bàn phím, bấm nút X (`.js-sp-close-drawer`), hoặc bấm ra ngoài màn mờ (backdrop). JS tự động trả focus (tiêu điểm) về đúng nút nhân viên vừa bấm trước đó.

---

## 4. Đánh giá chất lượng UI Basecode
- **Ưu điểm**: 
  - Code JS rất "sạch", modular, kiểm soát AJAX request (abort khi request liên tục).
  - Chuẩn chỉnh về mặt a11y (Accessibility) cho người khiếm thị / dùng bàn phím (`aria-live`, `aria-busy`, phím mũi tên).
  - Tách bạch rõ giữa Logic lấy dữ liệu (Controller/Model) và Logic render (HTML Partial).
- **Liên hệ với Rule Engine**: Giao diện UI Dashboard hiện tại hoạt động theo cơ chế Pull (người dùng chủ động xem). Trong khi đó Hệ thống Reminder là cơ chế Push. Hai luồng này độc lập với nhau, giao diện này không ảnh hưởng đến kiến trúc Giai đoạn 1 của hệ thống nhắc nhở.

---

## 5. To-Be — Phân hệ “Khách hàng cần theo dõi”

### 5.1 Mục tiêu nghiệp vụ

Drawer chi tiết Staff bổ sung tab **Khách hàng cần theo dõi** đặt cạnh tab **Nhật ký hoạt động**. Tab mới là work queue giúp Sale/Manager biết Báo giá nào cần xử lý ngay; Nhật ký hoạt động vẫn là audit timeline của tất cả reminder và phản hồi.

Thứ tự tab đề xuất:

```text
[ Cơ hội đang mở ] [ Khách hàng cần theo dõi ] [ Nhật ký hoạt động ]
```

Nguồn dữ liệu lifecycle là `tblsales_pipeline_reminders_log`:

```text
entity_type = estimate
rule_code IN (
  ESTIMATE_DRAFT_TOO_LONG,
  ESTIMATE_SENT_NO_RESPONSE,
  ESTIMATE_DECLINED_RECENT,
  ESTIMATE_EXPIRED,
  ESTIMATE_ACCEPTED_NOT_INVOICED
)
```

Các rule định kỳ theo Staff (`entity_type = staff_estimate_period`) vẫn xuất hiện trong Nhật ký hoạt động nhưng không nằm trong danh sách khách hàng cụ thể.

### 5.2 Luồng dữ liệu Backend

Endpoint As-Is `dashboard_staff_pipeline/{staff_id}` được mở rộng trả hai collection riêng:

```php
$follow_up_estimates = $model->get_staff_estimate_follow_ups($staff_id, 20);
$activity_feed = $model->get_reminder_response_stats([
    'staff_id' => $staff_id,
    'entity_types' => ['deal', 'staff_estimate_period', 'estimate'],
    'limit' => 50,
]);
```

`get_staff_estimate_follow_ups()` lấy lifecycle reminder mới nhất theo `entity_id` và ưu tiên:

1. Actionable chưa phản hồi: `response_required = 1 AND staff_response IS NULL`.
2. Informational mới nhất: `response_required = 0`.
3. Actionable đã phản hồi, dùng để đối chiếu gần đây.

Không dùng query As-Is hiện tại nguyên trạng vì [Sales_pipeline_model.php](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/models/Sales_pipeline_model.php) đang lọc đúng một `entity_type` theo Dashboard tab. To-Be phải hỗ trợ allow-list và xử lý `entity_type = estimate`.

Projection To-Be của feed phải bổ sung tối thiểu `entity_id`, `response_required`, `rule_code`, `snapshot_json`, `staff_response`, `sent_at` và `responded_at`. Nếu thiếu `response_required`, view không thể phân biệt informational với actionable một cách an toàn.

Snapshot lifecycle đã lưu sẵn `estimate_number`, `customer_name`, `status_label`, `risk_reason` và `condition_since`. Vì vậy Drawer/Activity Feed đọc trực tiếp snapshot để tái hiện lịch sử, không cần join lại nhiều bảng để suy đoán trạng thái cũ. Chỉ dùng `entity_id` để sinh link Estimate sau khi kiểm tra quyền.

### 5.3 Bảng “Khách hàng cần theo dõi”

Mỗi hàng hiển thị:

| Cột | Nội dung |
|---|---|
| Khách hàng | `customer_name`; dòng phụ là `estimate_number` |
| Lý do cần theo dõi | `risk_reason` từ snapshot |
| Từ thời điểm | `condition_since` |
| Phản hồi | `Chờ phản hồi`, `Đã phản hồi` hoặc `Chỉ thông báo` |
| Hành động | `Phản hồi ngay` nếu actionable; luôn có `Đi tới Báo giá` khi có quyền |

Signature element của tab là **dải rủi ro 3px bên trái mỗi hàng**, dùng token CRM:

- `--crm-danger` cho Expired/Accepted chưa invoice mức critical.
- `--crm-warning` cho Draft/Sent/Declined mức warning.
- Không thêm gradient/card trang trí khác; giữ bảng gọn để ưu tiên hành động.

Microcopy bắt buộc:

- CTA actionable: `Phản hồi ngay`.
- CTA entity: `Đi tới Báo giá`.
- Informational badge: `Chỉ thông báo`.
- Actionable chưa trả lời: `Chờ phản hồi`.
- Empty state: `Không có Báo giá nào cần theo dõi lúc này.` kèm CTA `Xem danh sách Báo giá` nếu có quyền.

### 5.4 Nhật ký hoạt động dùng chung Reminder Record

Tab Nhật ký hoạt động hiển thị lịch sử từ cùng bảng `tblsales_pipeline_reminders_log`, gồm Deal, KPI theo kỳ và lifecycle Estimate. Ví dụ:

```text
Báo giá #123 bị từ chối
Sale giải trình: Khách chọn đối thủ
Gửi lúc: 10/08/2026 09:20 · Phản hồi lúc: 10/08/2026 10:05
```

Quy tắc trạng thái phải dùng `response_required`, không chỉ kiểm tra `staff_response`:

```php
if ((int) $item['response_required'] === 0) {
    $uiStatus = 'informational';
} elseif ($item['staff_response'] === null) {
    $uiStatus = 'pending';
} else {
    $uiStatus = 'responded';
}
```

Điều này sửa hạn chế của view As-Is: logic hiện tại xem mọi record chưa có `staff_response` là Pending, nên informational Draft/Expired sẽ bị hiển thị sai nếu không phân nhánh theo cờ.

Activity renderer chọn title/link theo entity:

- `deal`: link tới Deal nếu còn tồn tại.
- `staff_estimate_period`: title theo rule Daily/Monthly/Weekly, không có entity link bắt buộc.
- `estimate`: title từ snapshot và link `Đi tới Báo giá` theo `entity_id`.

### 5.5 Quick Response từ Drawer

- `response_required = 0`: nút `Xem cảnh báo` mở Quick Response read-only; không textarea; CTA chính `Đi tới Báo giá`.
- `response_required = 1`: nút `Phản hồi ngay` mở cùng route `reminder_response/{id}`; textarea bắt buộc; submit thành công cập nhật ngay trạng thái hàng thành `Đã phản hồi` khi Drawer được tải lại.
- Không cho Manager/Admin phản hồi thay Sale; họ xem read-only.
- Mọi link Estimate phải qua kiểm tra `user_can_view_estimate()`/permission tương đương ở server.

### 5.6 Accessibility, responsive và trạng thái giao diện

- Tab mới dùng Bootstrap 3 `role="tab"`, `aria-controls`, `aria-selected` và hỗ trợ keyboard như các tab hiện tại.
- Badge không chỉ phân biệt bằng màu; luôn có text trạng thái.
- CTA/touch target tối thiểu `38×38px`, focus ring dùng `--crm-info`.
- Desktop hiển thị bảng; màn hình `<= 767px` chuyển mỗi hàng thành stacked card nhưng giữ nguyên thứ tự thông tin và CTA.
- Loading dùng `aria-busy`; error state nêu rõ `Không thể tải danh sách cần theo dõi. Vui lòng thử lại.`
- Tôn trọng `prefers-reduced-motion`; không thêm animation ngoài chuyển động Drawer hiện có.

### 5.7 Tiêu chí nghiệm thu UI

1. Drawer có tab “Khách hàng cần theo dõi” cạnh “Nhật ký hoạt động”.
2. Chỉ lifecycle reminder `entity_type = estimate` xuất hiện trong work queue.
3. Informational không bị hiển thị Pending và không có CTA phản hồi.
4. Actionable chưa có `staff_response` luôn hiển thị `Chờ phản hồi`.
5. Nhật ký hoạt động hiển thị được ví dụ “Báo giá bị từ chối → Sale giải trình”.
6. Link Báo giá chỉ xuất hiện khi người dùng có quyền.
7. Empty/loading/error/keyboard/mobile states đáp ứng tiêu chuẩn UI của module.
