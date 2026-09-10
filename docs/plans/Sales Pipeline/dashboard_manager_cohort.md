# Chuẩn hóa quản lý trong tập KPI Dashboard

Ngày: 2026-09-10.

- Phạm vi: `Sales_pipeline_model::get_estimate_performance_ranking()` và `get_staff_kpi_metrics()`.
- Quản lý được nhận diện bằng Admin hoặc `has_permission('sales_pipeline', staffid, 'view')`; permission truy cập không bị thay đổi.
- Loại quản lý không có hoạt động khỏi cohort chung trước khi tính rank/đếm quân số. Giữ NVKD không hoạt động. Khi cohort Báo giá rỗng, không thêm lại những người đã bị loại.
- Định nghĩa hoạt động chuẩn hóa theo kỳ: Báo giá chỉ xét quote count, accepted hoặc declined count trong kỳ đang xem (loại bỏ eligible reminders). Thương Vụ chỉ xét period_deals, period_estimates hoặc period_revenue trong kỳ đang xem (loại bỏ báo giá tháng hiện hành ngoài kỳ và deal lịch sử). Quản lý chỉ có declined_count hoặc period_estimates trong kỳ vẫn được giữ lại.
- Giữ dữ liệu khi truy vấn trực tiếp một staff trong `get_staff_kpi_metrics($staff_id)`; không đổi quyền dashboard, recipient reminder, công thức điểm hoặc công thức mục tiêu.
- Kiểm chứng: test tập nhân sự idle/active, out-of-period quotes/historical deals, pure reminder exclusion, all-manager-empty, mục tiêu theo quân số đã lọc, staff drilldown và test hồi quy Performance Score.

