# Clone_estimates — Implementation Log

> **Kế hoạch gốc**: [Clone_estiamtes_plan.md](./Clone_estiamtes_plan.md)  
> **Kế hoạch chi tiết**: [implementation_plan.md](../../../.gemini/antigravity-ide/brain/a9e7f56e-fc5d-4f55-8de1-e3e0876cfbc9/implementation_plan.md)  
> **Bắt đầu triển khai**: 2026-08-21  
> **Trạng thái**: Bước 1, Bước 2, Bước 3 & Bước 4 **Hoàn thành 100% (Verified)**

---

## 1. Bước 1 — Vá R-01: Sửa `get_estimate_dashboard_metrics()` ✅

**Ngày**: 2026-08-21  
**File**: `modules/sales_pipeline/models/Sales_pipeline_model.php`

### Vấn đề
Query đếm `estimate_count` tại Dashboard trước đây đếm `COUNT(ev.estimate_id)` từ `estimate_versions JOIN estimates`. Khi nhân viên clone 10 lần một báo giá, KPI bị nhân lên thành 10 thay vì 1.

### Giải pháp
Query được chuyển sang đếm `COUNT(grp.id) FROM estimate_groups` với điều kiện `EXISTS` đảm bảo group có ít nhất một revision không phải Draft (`status IN (2, 3, 4, 5)`). Định nghĩa "Báo giá hợp lệ" này đồng nhất giữa Dashboard, Performance Score và Rule Engine.

```php
// Trước — đếm sai revision vật lý:
COUNT(ev.estimate_id) FROM estimate_versions ev JOIN estimates e ...
WHERE e.datecreated >= ... GROUP BY COALESCE(e.sale_agent, e.addedfrom)

// Sau — đếm đúng nhóm Báo giá logic:
COUNT(grp.id) FROM estimate_groups grp
WHERE grp.owner_staff_id IN (...)
  AND grp.datecreated >= ...
  AND EXISTS (SELECT 1 FROM estimate_versions ev
              JOIN estimates e ON e.id = ev.estimate_id
              WHERE ev.estimate_group_id = grp.id AND e.status IN (2, 3, 4, 5))
GROUP BY grp.owner_staff_id
```

---

## 2. Bước 2 — Vá R-02: Thêm `last_reconciled_at` cursor & Sửa Bootstrap Guard ✅

**Ngày**: 2026-08-21  
**Files**:
- `modules/sales_pipeline/migrations/107_version_107.php`
- `modules/sales_pipeline/includes/estimate_group_schema.php`
- `modules/sales_pipeline/models/Sales_pipeline_model.php`
- `modules/sales_pipeline/sales_pipeline.php`

### Vấn đề & Hiệu chỉnh Bootstrap
- `reconcile_estimate_groups()` sử dụng cursor `ORDER BY COALESCE(last_reconciled_at, '1970-01-01') ASC, id ASC` và luôn stamp `last_reconciled_at = NOW()` sau khi kiểm tra mỗi group (kể cả khi outcome không đổi) để cursor luôn tiến lên, chống starvation khi tổng số group > 1000.
- Sửa điều kiện early return trong `sales_pipeline_estimate_group_schema_bootstrap()`: Kiểm tra sự tồn tại của cả cột và các index (`idx_last_reconciled`, `idx_parent_estimate`, `idx_event_estimate`, `idx_event_from_group`, `idx_event_to_group`). Nếu thiếu bất kỳ thành phần nào, bootstrap tiếp tục chạy để sửa schema tự động.

---

## 3. Bước 3 — Service Layer, Schema Migration 108 & Hook Context ✅

**Ngày**: 2026-08-21  
**Files**:
- `modules/sales_pipeline/migrations/108_version_108.php` *(mới)*
- `modules/sales_pipeline/libraries/Estimate_revision_service.php` *(mới)*
- `modules/sales_pipeline/includes/estimate_group_schema.php`
- `modules/sales_pipeline/sales_pipeline.php`
- `modules/sales_pipeline/models/Sales_pipeline_model.php`

### Thay đổi Kiến trúc
1. **Migration 108 & Schema Extensions**:
   - `tblsales_pipeline_estimate_versions`: Thêm `parent_estimate_id INT NULL`, `link_method VARCHAR(30) NOT NULL DEFAULT 'origin'`, `linked_by INT NULL`, và index `idx_parent_estimate`.
   - Tạo bảng audit append-only `tblsales_pipeline_estimate_group_events` với 3 index `idx_event_estimate`, `idx_event_from_group`, `idx_event_to_group`.
   - Bump module version lên **`1.0.8`**.
2. **Service Layer (`Estimate_revision_service.php`)**:
   - `capture_request_context(array $hookPayload)`: Bóc tách an toàn namespace `sales_pipeline[...]` khỏi `$hookPayload['data']` trong filter `before_estimate_added` để không làm phát sinh lỗi unknown column trên `tblestimates`.
   - Lưu trữ context request-scoped trong memory (không dùng session).
   - `handle_estimate_added(int $estimateId, ?array $context)`: Phân loại `origin`, `declared_revision`, `native_copy`, `module_copy`.
   - **Business Guards**:
     - Customer Boundary: Khác `clientid` tự động tạo Standalone Group an toàn (`fallback_standalone`), không làm mất Estimate mới.
     - Accepted Group Lock: Staff bị chặn link vào group đã accepted; Manager override bắt buộc có reason không rỗng.
     - Lock Transaction: Khóa group bằng `SELECT ... FOR UPDATE` để tính `MAX(revision_no) + 1` nguyên tử.
     - Ghi nhận Audit Event trong cùng transaction.

---

## 4. Bước 4 — UI Intent Panel & Endpoint Nguồn Báo giá ✅

**Ngày**: 2026-08-21  
**Files**:
- `modules/sales_pipeline/controllers/Sales_pipeline.php`
- `modules/sales_pipeline/assets/js/estimate_revision.js` *(mới)*
- `modules/sales_pipeline/assets/css/estimate_revision.css` *(mới)*
- `modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php`
- `modules/sales_pipeline/language/english/sales_pipeline_lang.php`

### Thay đổi Giao diện & Tương tác
1. **Endpoint `GET /admin/sales_pipeline/estimate_revision_sources`**:
   - Trả danh sách Báo giá nguồn hợp lệ của khách hàng theo quyền xem của staff.
   - Staff thông thường không thấy Báo giá `accepted` trong danh sách dropdown; Manager thấy Báo giá `accepted` kèm cờ `is_accepted = true`.
2. **UI Selector (`estimate_revision.js` & `estimate_revision.css`)**:
   - Tự động chèn panel lựa chọn Intent sau trường Khách hàng trên form tạo Báo giá (`admin/estimates/estimate`).
   - Mặc định là `(●) Báo giá mới độc lập`.
   - Khi chọn `( ) Bản điều chỉnh / Thay thế`: Mở dropdown chọn Báo giá nguồn của khách hàng.
   - Khi chọn Báo giá đã accepted: Manager được yêu cầu nhập lý do xác nhận.
   - Progressive enhancement: Nếu JS không tải được, form submit bình thường dạng Standalone.
3. **Đa ngôn ngữ (i18n)**:
   - Đồng bộ 100% các key ngôn ngữ Tiếng Việt và Tiếng Anh.
   - Đăng ký capability `sales_pipeline.manage_estimate_revisions`.

---

## 5. Kết quả Kiểm thử Tự động (Test Results)

Chạy test suite toàn module qua CLI:

```bash
for f in modules/sales_pipeline/tests/*_test.php; do php "$f"; done
```

| Test Suite | Kết quả | Ghi chú |
|---|---|---|
| `Estimate_group_integration_test.php` | ✅ **PASS (35/35 test cases)** | Bao phủ contract, API/UI asset, invariant nghiệp vụ và i18n Version History |
| `Performance_score_calculator_test.php` | ✅ **PASS** | Tính toán KPI & điểm số chuẩn xác |
| `Performance_score_language_render_test.php` | ✅ **PASS** | Render ngôn ngữ leaderboard |
| `Performance_score_period_options_test.php` | ✅ **PASS** | Chỉ tiêu kỳ và fallback options |
| `Reminder_rule_settings_test.php` | ✅ **PASS** | Delivery safety & audit invariants |

**Tổng cộng**: 5/5 test suites PASS (100%). Không có lỗi syntax PHP hay vi phạm query.

---

## 6. Bổ sung Bước 6 — UI injection không sửa core ✅

**Ngày**: 2026-08-22  
**Phạm vi**: Khắc phục việc tab “Lịch sử Phiên bản” biến mất sau khi hoàn tác
thay đổi tại `application/views/admin/estimates/estimate_preview_template.php`.

### Nguyên nhân

Perfex core view không còn chứa tab hoặc `#sp-version-history-container`.
JavaScript Version History trước đây chỉ nạp asset và thoát nếu container không
tồn tại, nên backend API vẫn có nhưng không có điểm mount trên giao diện.

### Giải pháp module-only

- `app_admin_footer` tiếp tục là hook nạp asset của module.
- `estimate_version_history.js` tự xác định Estimate ID từ hidden field, biến
  cấu hình hoặc URL.
- Injector chèn tab ngay sau tab Ghi chú và tạo pane/container trong DOM hiện
  có; khi người dùng mở tab, gọi API Version History.
- Thêm i18n title/loading từ language files của module.
- Không sửa file nào trong `application/` hoặc core Perfex.

### Kiểm thử

```bash
php -l modules/sales_pipeline/sales_pipeline.php
php -l modules/sales_pipeline/tests/Estimate_group_integration_test.php
node --check modules/sales_pipeline/assets/js/estimate_version_history.js
php modules/sales_pipeline/tests/Estimate_group_integration_test.php
```

Kết quả: syntax pass; Estimate Group integration pass **34/34**. Kiểm thử
browser trên môi trường có session đăng nhập vẫn cần thực hiện để xác nhận tab,
API response và Link/Unlink trong UI thật.

### Bổ sung hardening sau kiểm thử UI

- Injector đăng ký `MutationObserver` trên `#estimate` và listener
  `ajaxComplete.salesPipelineVersionHistory`, vì Perfex tải
  `estimate_preview_template` bằng AJAX sau khi footer ban đầu đã chạy.
- Sửa selector tab theo DOM thực tế của Perfex:
  `.preview-tabs-top ul.nav-tabs.nav-tabs-horizontal`; selector cũ hiểu sai
  `nav-tabs-horizontal` là phần tử cha nên không bao giờ tìm thấy danh sách tab.
- Asset Version History dùng `filemtime()` làm cache key để bản JavaScript/CSS
  mới trong module ZIP không bị trình duyệt giữ lại dưới URL `v=1.0.9` cũ.
- Manual Link Pending giờ cũng yêu cầu `manage_estimate_revisions`; quyền xem
  source/target chỉ là điều kiện dữ liệu, không còn đủ để thay đổi grouping.
- Admin hoặc Staff được cấp capability có thể Link/Unlink theo các guard Accepted,
  customer boundary và audit hiện hành.

### Xác minh browser runtime — 2026-08-22

Kiểm thử trực tiếp tại `admin/estimates#100` sau reload:

- Asset JS dùng cache key `filemtime` mới.
- Có đúng 1 link tab “Lịch sử Phiên bản Báo giá”.
- Có đúng 1 pane `#tab_version_history` và 1 container history.
- Click tab tải thành công Group #132, hiển thị 2 revision và 2 audit event.
- `application/` không có thay đổi; toàn bộ bản vá nằm trong module.

## 7. Chuẩn hóa ngôn ngữ Version History — 2026-08-24

- Toàn bộ nhãn trạng thái, phương thức liên kết, tiêu đề cây phiên bản, nhật ký
  sự kiện, nút Link/Unlink, modal và thông báo lỗi/thành công được truyền từ
  `sales_pipeline_lang.php` qua `window.salesPipelineVersionHistoryI18n`.
- Giao diện tiếng Việt không còn hậu tố tiếng Anh như `(Pending)`, `(LINK)`,
  `(Audit Log)` hoặc chuỗi máy `manual_unlink`; sự kiện `revision_unlinked`
  hiển thị là **Tách phiên bản**.
- Đồng bộ key tiếng Việt/Anh trong module; không sửa view hoặc file lõi Perfex.
- Đã xác minh bằng `php -l`, `node --check`, `git diff --check` và test tích hợp
  **39/39**.

### Bổ sung mapping thao tác Sao chép — 2026-08-24

- `native_copy` hiển thị là **Sao chép từ báo giá**.
- `module_copy` hiển thị là **Sao chép thành bản điều chỉnh**.
- Hai nhãn được khai báo trong language Việt/Anh, truyền qua i18n của module và
  được kiểm thử trong integration test **37/37**.

### Chuẩn hóa nhãn số phiên bản — 2026-08-24

- Tiếng Việt: `Phiên bản 1`, `Phiên bản 2`, ...
- Tiếng Anh: `Reversion 1`, `Reversion 2`, ...
- Chỉ thay đổi nhãn hiển thị; `revision_no` và logic đánh số không thay đổi.
- Điều chỉnh vị trí badge bằng CSS để nhãn dài như `Phiên bản 1` không che mã
  báo giá; badge được neo bên ngoài thẻ và giữ trên một dòng.
- Căn lại thành ba cột trực quan: tiêu đề Cây Phiên bản, badge Phiên bản và
  vùng thông tin báo giá; thẻ phiên bản dành riêng khoảng đệm cho badge.
- Tinh chỉnh lần cuối theo giao diện thực tế: badge dịch về trục timeline
  (`left: -40px`) và nội dung Báo giá trở lại gần mép thẻ (`padding-left: 20px`).
- Mã audit `standalone` được hiển thị qua từ vựng **Khởi tạo nhóm báo giá độc
  lập** ở tiếng Việt và **Standalone quote group created** ở tiếng Anh.
