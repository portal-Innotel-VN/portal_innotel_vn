# Đặc tả Deal–Estimate Group Bridge

## Mục tiêu

Deal và Estimate Group là hai aggregate độc lập. Bridge chỉ tạo quan hệ nghiệp vụ tùy chọn; grouping Báo giá không phụ thuộc Deal.

## Cardinality

- Một Deal có thể liên kết nhiều Estimate Group.
- Một Estimate Group thuộc tối đa một Deal.
- Một Group có thể được đánh dấu `is_primary` trong Deal.

## Đồng bộ

- Hướng đồng bộ: Estimate Group → Deal.
- Khi có Group Accepted, `deal_value` dùng tổng `decision_value_base` của các Group Accepted.
- Khi chưa có Accepted, Deal có thể dùng current value của Group primary.
- Deal manual lock không bị tự động ghi đè status hoặc value.
- Di chuyển/unlink Group phải tính lại cả Deal cũ và Deal mới.

## Tính không phá hủy

- Xóa hoặc unlink Deal không xóa Estimate Group, Version hay audit.
- Xóa Deal phải dọn bridge row để không tạo orphan.
- Bridge migration phải idempotent và không sửa migration đã áp dụng.

## Basecode chính

- `modules/sales_pipeline/migrations/109_version_109.php`
- `modules/sales_pipeline/libraries/Estimate_revision_service.php`
- `modules/sales_pipeline/models/Sales_pipeline_model.php`
