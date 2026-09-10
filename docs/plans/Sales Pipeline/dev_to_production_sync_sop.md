# QUY TRÌNH CHUẨN AN TOÀN ĐỒNG BỘ MÃ NGUỒN TỪ DEV LÊN PRODUCTION
## MODULE SALES PIPELINE — INNOTEL CRM
**Mã tài liệu**: `SOP-DEV-TO-PROD-SYNC-20260910`  
**Người thực hiện**: Technical Lead / Developer phụ trách phát hành  
**Mục tiêu**: Đảm bảo toàn bộ tính năng, giao diện, và logic đã phát triển/kiểm thử thành công trên môi trường Dev (`portal_18`) được chuyển giao sang môi trường Production (`portal_innotel_vn`) một cách chuẩn xác 100%, không gây xung đột, không chạm vào database sản xuất khi kiểm tra cục bộ, và dễ dàng rollback nếu cần.

---

## 1. THÔNG TIN MÔI TRƯỜNG & KHÔNG GIAN LÀM VIỆC

| Thuộc tính | Môi trường Dev (Local Staging) | Môi trường Production (Repo & Server) |
| :--- | :--- | :--- |
| **Thư mục cục bộ** | `/Users/dieterhoang/Developer/portal_18` | `/Users/dieterhoang/Developer/portal_innotel_vn` |
| **Thư mục Webroot** | Cấp gốc (`./modules/sales_pipeline`) | Cấp con (`./portal_innotel_vn/modules/sales_pipeline`) |
| **Git Repository** | `https://github.com/HalluHip-ai/portal_18.git` | `https://github.com/portal-Innotel-VN/portal_innotel_vn.git` |
| **Cơ sở dữ liệu** | Database thử nghiệm cục bộ (localhost:8000) | **Database thật trên máy chủ Innotel** |
| **Cơ chế Triển khai** | Code trực tiếp & chạy kiểm thử tự động | cPanel Git Version Control (`portal.innotel.vn`) |

---

## 2. QUY TRÌNH 4 BƯỚC CHUẨN AN TOÀN

```
[BƯỚC 1: ĐỒNG BỘ CODE] 
   └── Tạo nhánh mới trên portal_innotel_vn ──> Chép modules/sales_pipeline
[BƯỚC 2: ĐỐI SOÁT DIFF]
   └── git diff --no-index --stat ──> Xác nhận 0 byte chênh lệch
[BƯỚC 3: KIỂM TRA SYNTAX & OFFLINE TEST]
   └── php -l kiểm tra 100% file PHP ──> Chạy test hợp đồng độc lập (Không chạm DB thật)
[BƯỚC 4: ĐẨY GIT & DEPLOY CPANEL]
   └── Commit ──> Push nhánh ──> Merge vào main trên GitHub ──> cPanel Pull/Update
```

---

### BƯỚC 1: TẠO NHÁNH MỚI & ĐỒNG BỘ MÃ NGUỒN (SYNC CODE)

1. **Chuyển sang thư mục Production cục bộ và cập nhật nhánh `main` mới nhất**:
   ```bash
   cd /Users/dieterhoang/Developer/portal_innotel_vn
   git checkout main
   git pull origin main
   ```

2. **Tạo nhánh mới để chuẩn bị bản phát hành**:
   *Quy ước đặt tên nhánh*: `sync/sales-pipeline-<tên-tính-năng>-<YYYYMMDD>`  
   *Ví dụ*:
   ```bash
   git checkout -b sync/sales-pipeline-ui-reminders-20260910
   ```

3. **Chép thư mục `modules/sales_pipeline` từ Dev sang nhánh mới của Prod**:
   Sử dụng lệnh `rsync` an toàn (giữ nguyên quyền file, cập nhật đè chính xác, loại trừ file rác `.DS_Store`):
   ```bash
   rsync -av --delete \
     --exclude='.DS_Store' \
     --exclude='*.tmp' \
     /Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/ \
     /Users/dieterhoang/Developer/portal_innotel_vn/portal_innotel_vn/modules/sales_pipeline/
   ```

---

### BƯỚC 2: ĐỐI SOÁT TỰ ĐỘNG BẰNG GIT DIFF (CODE DIFF VERIFICATION)

*Mục tiêu: Đảm bảo không có bất kỳ dòng mã nào bị thiếu sót hoặc lệch lạc so với bản Dev đã chạy ổn định.*

1. **Chạy lệnh đối soát diff trực tiếp giữa 2 thư mục**:
   ```bash
   git diff --no-index --stat \
     /Users/dieterhoang/Developer/portal_18/modules/sales_pipeline \
     /Users/dieterhoang/Developer/portal_innotel_vn/portal_innotel_vn/modules/sales_pipeline
   ```

2. **Tiêu chuẩn nghiệm thu**:
   - Lệnh **trả về trống (0 files changed, 0 byte diff)** (hoặc chỉ khác các file test tạm nếu cố ý loại trừ).
   - Điều này chứng minh toàn bộ controller, model, library, view, CSS và file ngôn ngữ đã trùng khớp 100% với bản Dev.

---

### BƯỚC 3: KIỂM TRA CÚ PHÁP TĨNH & TEST OFFLINE (KHÔNG CHẠM DB THẬT)

*Mục tiêu: Đảm bảo code sẵn sàng thực thi và an toàn tuyệt đối cho database sản xuất.*

1. **Kiểm tra cú pháp PHP toàn bộ module vừa copy**:
   ```bash
   find /Users/dieterhoang/Developer/portal_innotel_vn/portal_innotel_vn/modules/sales_pipeline -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
   ```
   *Tiêu chuẩn*: Lệnh không xuất hiện bất kỳ dòng báo lỗi nào.

2. **Chạy bài kiểm thử tích hợp & hợp đồng giao diện độc lập**:
   ```bash
   php /Users/dieterhoang/Developer/portal_innotel_vn/portal_innotel_vn/modules/sales_pipeline/tests/Estimate_group_integration_test.php
   ```
   *Tiêu chuẩn*: `PASS: (44/44 test cases verified successfully)`.  
   *Ghi chú an toàn*: Test case này chạy hoàn toàn bằng PHP CLI cục bộ, kiểm tra logic cấu trúc dữ liệu và regex hợp đồng, **hoàn toàn không kết nối hay gửi query nào lên database thật**.

---

### BƯỚC 4: ĐẨY GIT, MERGE MAIN VÀ DEPLOY TRÊN CPANEL

1. **Kiểm tra lại danh sách file đã thay đổi trên Git**:
   ```bash
   cd /Users/dieterhoang/Developer/portal_innotel_vn
   git status
   git diff --stat
   ```

2. **Commit và đẩy nhánh lên GitHub**:
   ```bash
   git add portal_innotel_vn/modules/sales_pipeline/
   git commit -m "feat(sales_pipeline): sync UI responsive, reminders and version tree enhancements from dev"
   git push -u origin sync/sales-pipeline-ui-reminders-20260910
   ```

3. **Thao tác trên GitHub**:
   - Truy cập: `https://github.com/portal-Innotel-VN/portal_innotel_vn`
   - Tạo **Pull Request (PR)** từ nhánh `sync/sales-pipeline-ui-reminders-20260910` vào nhánh `main`.
   - Xem lại diff lần cuối trên giao diện web của GitHub.
   - Nhấn **Merge pull request** (hoặc Squash and merge).

4. **Kích hoạt triển khai trên cPanel (Git Version Control)**:
   - Đăng nhập vào cPanel quản trị hosting: `https://portal.innotel.vn:2083` (hoặc cổng cPanel tương đương).
   - Tìm và mở mục **Git™ Version Control**.
   - Tìm repository của dự án `portal_innotel_vn` và bấm **Manage**.
   - Chuyển sang tab **Pull or Deploy**:
     - Nhấn **Update from Remote** để kéo commit mới nhất của nhánh `main` về máy chủ.
     - Nhấn **Deploy HEAD Commit** (nếu cấu hình tự động deploy file ra Document Root).

5. **Hậu kiểm trên Production (Post-Deployment Verification)**:
   - **Kích hoạt Migration (nếu có schema mới)**: Đăng nhập quyền Admin truy cập `https://portal.innotel.vn/admin/modules`, tải lại trang để Perfex CRM tự chạy runner migration.
   - **Xóa cache trình duyệt**: Nhấn `Ctrl + F5` (Windows) hoặc `Cmd + Shift + R` (Mac) khi truy cập:
     - `https://portal.innotel.vn/admin/sales_pipeline/dashboard` (Kiểm tra tab Thương vụ không còn thanh cuộn ngang).
     - `https://portal.innotel.vn/admin/sales_pipeline/settings#reminders` (Kiểm tra bố cục 2 cột compact mới).
     - Xem chi tiết báo giá kiểm tra cây lịch sử phiên bản hiển thị êm ái trên mobile.

---

## 3. CHECKLIST TÓM TẮT NHANH (CHEATSHEET CHO MỖI LẦN SHIP)

- [ ] `portal_18` test pass và chạy ổn định trên `localhost:8000`.
- [ ] `portal_innotel_vn` đã tạo nhánh mới từ `main` cập nhật.
- [ ] Lệnh `rsync` đã chép code vào đúng thư mục con `portal_innotel_vn/portal_innotel_vn/modules/sales_pipeline/`.
- [ ] `git diff --no-index --stat` xác nhận 0 byte lệch.
- [ ] `php -l` xác nhận 100% PHP files không có cú pháp lỗi.
- [ ] Nhánh đã push lên GitHub và merge sạch vào `main`.
- [ ] cPanel Git Version Control đã bấm **Update from Remote**.
- [ ] Đã xóa cache trình duyệt và kiểm tra giao diện trực tiếp trên Production.
