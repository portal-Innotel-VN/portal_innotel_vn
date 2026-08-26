# Sales Pipeline Context

## Mục tiêu nghiệp vụ

Phân biệt Báo giá mới với revision, tránh duplicate KPI và vẫn cho phép NVKD thao tác linh hoạt.

## Workflow

- Clone/Duplicate có source rõ ràng → append vào Group nguồn.
- Tạo form trắng mặc định standalone.
- Smart Prompt chỉ gợi ý; người dùng xác nhận mới tạo revision intent.
- Tạo nhầm được hậu kiểm bằng Manual Link/Unlink có audit.
- Explicit link lỗi phải fallback standalone và cảnh báo.

## Aggregate

- Estimate Group là aggregate root của KPI Báo giá.
- Estimate Version là chứng từ vật lý.
- Deal là quan hệ tùy chọn qua bridge.
- Group outcome là source of truth của kết quả.

## Performance Score

`performance_score_v1` đang dùng quote count, accepted revenue và acceptance rate. Reminder response chưa kích hoạt. Công thức chi tiết tại `docs/specifications/PERFORMANCE_SCORE.md`.

## Entry points

- Module bootstrap/hooks: `modules/sales_pipeline/sales_pipeline.php`
- Controller: `modules/sales_pipeline/controllers/Sales_pipeline.php`
- Model: `modules/sales_pipeline/models/Sales_pipeline_model.php`
- Revision service: `modules/sales_pipeline/libraries/Estimate_revision_service.php`
- Score calculator: `modules/sales_pipeline/libraries/Performance_score_calculator.php`
- Schema: `modules/sales_pipeline/includes/estimate_group_schema.php`

## Đọc thêm

- [CREATE_ESTIMATES_WORKFLOW.md](../specifications/CREATE_ESTIMATES_WORKFLOW.md)
- [DOMAIN_MODEL.md](../specifications/DOMAIN_MODEL.md)
- [AUDIT_SECURITY_RULES.md](../specifications/AUDIT_SECURITY_RULES.md)
- [DEAL_BRIDGE.md](../specifications/DEAL_BRIDGE.md)
