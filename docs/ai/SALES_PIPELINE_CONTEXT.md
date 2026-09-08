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

`performance_score_v1` tổng hợp 4 thành phần: Quote count (20%), Accepted revenue (40%), Acceptance rate (25%), và Reminder response (15%). Thành phần Reminder response hoạt động động theo cơ chế per-staff fallback: áp dụng mẫu số 100 khi có nhắc nhở đến hạn (`eligible_reminders > 0`), tự động chuyển sang `not_applicable` và tính trên mẫu số 85 khi không có nhắc nhở (`eligible_reminders == 0`), và gắn cờ `insufficient_reminder_sample` (`is_provisional = true`) khi `0 < eligible_reminders < 3`. Toàn bộ cohort được đánh giá tại một mốc `$calculated_at` thống nhất. Công thức chi tiết tại [`PERFORMANCE_SCORE.md`](../specifications/PERFORMANCE_SCORE.md).

## Entry points

- Module bootstrap/hooks: `modules/sales_pipeline/sales_pipeline.php`
- Controller: `modules/sales_pipeline/controllers/Sales_pipeline.php`
- Model: `modules/sales_pipeline/models/Sales_pipeline_model.php`
- Revision service: `modules/sales_pipeline/libraries/Estimate_revision_service.php`
- Score calculator: `modules/sales_pipeline/libraries/Performance_score_calculator.php`
- Schema: `modules/sales_pipeline/includes/estimate_group_schema.php`

## Trạng thái phát hành (Release Status)

- **Phiên bản:** `1.1.4` (Active)
- **Migration Runtime:** `114`
- **Tình trạng nghiệm thu:** Toàn bộ **7 Phase** kiểm định kỹ thuật (giải quyết triệt để **6 Blockers Pre-Production**) đã đạt **FULL PASS** (xem chi tiết tại [`master_acceptance_and_rollout_matrix.md`](../plans/master_acceptance_and_rollout_matrix.md)).
- **Khuyến nghị vận hành:** Giữ cờ `sp_reminder_crm_inbox_enabled = 0` khi vừa deploy lên Production; kích hoạt sau 24-48h theo dõi ổn định.

## Đọc thêm

- [Standalone và Legacy Components](./SALES_PIPELINE_STANDALONE_COMPONENTS.md)
- [CREATE_ESTIMATES_WORKFLOW.md](../specifications/CREATE_ESTIMATES_WORKFLOW.md)
- [DOMAIN_MODEL.md](../specifications/DOMAIN_MODEL.md)
- [AUDIT_SECURITY_RULES.md](../specifications/AUDIT_SECURITY_RULES.md)
- [DEAL_BRIDGE.md](../specifications/DEAL_BRIDGE.md)
- [Ma Trận Nghiệm Thu & Rollout](../plans/master_acceptance_and_rollout_matrix.md)
