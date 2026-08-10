# QC Report: Panel deal đang mở và nhật ký hoạt động trong chi tiết nhân viên

## Metadata

- plan_id: `staff-detail-open-deals-activity-panels`
- plan_version: `1`
- qc_reviewer: `Gemini 3.6 Flash (High)`
- qc_date: `2026-08-08`
- qc_status: `PASS`

## Tóm tắt cách hiểu (Tối đa 5 bullets)

1. **Mục tiêu**: Thêm 2 panel chỉ đọc (Open Deals & System Activity Log) vào trang chi tiết nhân viên (`admin/staff/member/{id}`) dành cho full admin, hoàn toàn không sửa file core Perfex CRM.
2. **Luồng route/hook/view**: Bắt `staff_id` qua hook core `staff_member_edit_view_profile`, sau đó từ `sales_pipeline` bootstrap gắn callback `app_admin_head` và `app_admin_footer` để render view partial và gắn JS scoped mount fragment vào đầu cột `.small-table-right-col`.
3. **Phân quyền & Ranh giới**: Đóng băng hiển thị bằng cờ `is_admin()`, bảo đảm tuân thủ ranh giới truy cập của System Activity Log (`Utilities::activity_log`), không hạ thấp security scope.
4. **An toàn dữ liệu & SQL**: Lọc Open Deals theo `is_won = 0 AND is_lost = 0` và `staff_id`, System Activity theo `staffid = staff_id`; ép kiểu `(int)$staff_id`, escape HTML toàn bộ dữ liệu động và kiểm tra định dạng hex color của status.
5. **Fallback Skill & Working Tree**: Sử dụng kiến trúc module Perfex chuẩn để fallback cho skill `improve-codebase-architecture` (không có trong workspace); bảo toàn toàn bộ thay đổi chưa commit khác trong working tree bẩn.

## Ma trận kiểm tra quy định (Governance Checklist)

| Hạng mục kiểm tra | Trạng thái | Bằng chứng / Ghi chú |
|---|---|---|
| Trace Route → Hook → Model → View | **PASS** | `admin/staff/member/{id}` → `staff_member_edit_view_profile` → `Sales_pipeline_model` → `_staff_detail_panels.php` |
| Không sửa Core Framework / App | **PASS** | Mọi thay đổi nằm gọn trong `modules/sales_pipeline/`; file `Staff.php` và `member.php` nguyên vẹn |
| Phân quyền & Scope Activity Log | **PASS** | Kiểm tra `is_admin()` trước mọi query và render |
| Bảo toàn Dirty Working Tree | **PASS** | Được ghi nhận rõ trong Non-Goals, AC8 và Implementation plan |
| Query Builder / Escaping / Anti-SQLi | **PASS** | Cast `(int)$staff_id`, Active Record binding, `html_escape()`, validate regex `#HEX` color |
| Fallback skill thiếu | **PASS** | Đã xử lý tại Plan (line 30), tuân thủ fallback module convention |
| Text hiển thị & Song ngữ | **PASS** | Khai báo key trong `english` & `vietnamese` language files, hiển thị qua `_l()` |
| PHP Syntax & Audit Tests | **PASS** | AC9 yêu cầu `php -l` và `rg` verification trước khi hoàn tất |

## Chi tiết Findings (Delta)

### Finding 1 (Advisory / Low): Kiểm tra sự tồn tại của bảng module trước khi truy vấn
- **Mức độ**: Low (Advisory)
- **Bằng chứng file/symbol**: `modules/sales_pipeline/sales_pipeline.php`, `modules/sales_pipeline/models/Sales_pipeline_model.php`
- **Plan delta đề nghị**: Giữ nguyên hướng dẫn trong Plan section "Required behavior and data flow" (step 4) và "Failure states", nhắc Implementer dùng `$CI->db->table_exists(db_prefix() . 'sales_pipeline')` trước khi query để tránh SQL exception nếu module chưa khởi tạo bảng.

### Finding 2 (Advisory / Info): Tối ưu truy vấn đếm tổng số deal đang mở
- **Mức độ**: Info (Recommendation)
- **Bằng chứng file/symbol**: `Sales_pipeline_model.php::get_staff_open_deals()`
- **Plan delta đề nghị**: Khi lấy mảng open deals (limit 10), trả về kèm `total_count` trong mảng kết quả của model để tránh lặp điều kiện `WHERE` khi gọi đếm badge.

## Kết luận

**QC RESULT: PASS**

Plan `docs/plans/staff-detail-open-deals-activity-panels.md` đạt chuẩn kiến trúc, an toàn bảo mật, giữ đúng ranh giới core và có kế hoạch kiểm thử đầy đủ. Plan sẵn sàng cho giai đoạn triển khai (Implementation).
