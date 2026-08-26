# Implementation Status

## Đã hiện diện trong Basecode

- Estimate Group/Version schema và reconciliation cursor.
- Request intent standalone/revision.
- Clone/Duplicate grouping.
- Smart Prompt endpoint/model/UI.
- Manual Link/Unlink và Version History assets/API.
- Deal Bridge migration 109 và manual lock fields.
- `performance_score_v1` calculator, target defaults và role projection.
- Deal Reminder `DEAL_PIPELINE_MIN_COUNT` và hai tầng `DEAL_STALE_FOLLOW_UP`/`DEAL_STALE_BACKLOG`; dùng Reminder workflow hiện hữu, không thêm schema activity.

## Mức xác minh

- Có test harness/contract tests cho Estimate Group, Performance Score và pure evaluator cho Deal Reminder.
- Việc file/method tồn tại không chứng minh migration đã chạy trên DB production.
- DB-backed transaction/concurrency và manual UI cần được xác nhận theo môi trường trước release.

## Cách cập nhật trạng thái

Mỗi mục mới phải ghi:

- file/method;
- migration/schema;
- test đã chạy;
- manual verification;
- blocker còn lại.

Không dùng cụm “hoàn thành 100%” nếu chưa có bằng chứng cho DB và UI runtime.
