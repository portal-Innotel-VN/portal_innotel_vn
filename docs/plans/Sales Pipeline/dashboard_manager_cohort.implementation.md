# Kết quả triển khai lọc quản lý Dashboard

Ngày: 2026-09-10.

- Model: thay cờ nội bộ `is_admin` bằng `is_manager` ở hai đường metrics; đổi điều kiện lọc và xóa cờ trước khi trả dữ liệu; bỏ fallback khôi phục cohort rỗng ở Báo giá.
- Test mới: `modules/sales_pipeline/tests/Dashboard_manager_cohort_test.php`, thực thi các block khởi tạo/lọc trích từ source model với fixture xác định; FAIL trước sửa và PASS sau sửa. Không dùng DB thật.
- Lint model và test đạt. Hồi quy đạt: Performance_score_calculator, Performance_score_period_options, Performance_score_v2_and_snapshot, Reminder_sla_reconcile.
- Hoàn thiện tinh chỉnh hoạt động bán hàng trong kỳ: Báo giá chỉ giữ quản lý có estimate_count, accepted_count hoặc declined_count trong kỳ (loại bỏ nhắc nhở eligible_reminders); Thương Vụ chỉ giữ quản lý có period_deals, period_estimates hoặc period_revenue trong kỳ đang xem (loại bỏ báo giá tháng hiện hành ngoài kỳ và deal lịch sử).
- Test cập nhật: `modules/sales_pipeline/tests/Dashboard_manager_cohort_test.php` kiểm chứng thành công cả 3 ca biên: loại trừ hoạt động ngoài kỳ / deal cũ, loại trừ thuần nhắc nhở, giữ quản lý khi có declined_count hoặc period_estimates, giữ NVKD thường không có số và giữ rỗng khi toàn bộ quản lý nhàn rỗi. PASS 100%.

