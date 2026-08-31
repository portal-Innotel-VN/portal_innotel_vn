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
- Reminder Delivery Resilience migration 110 và bootstrap idempotent: expiration,
  channel isolation, throttle xuyên Cron, rolling message/recipient quota,
  advisory lock, SMTP sanitizer/classifier, authentication circuit, Delivery
  Health, manual retry/resume và weekly retention.
- Reminder Bell Inbox migration 111 và module version 1.0.11: acknowledge bền
  vững cho Informational, feed theo CRM delivery/recipient, Actionable Quick
  Response, Pulse Dot/MutationObserver và rollback feature flag. Flag mặc định
  vẫn là `0`; chưa bật production.
- Kích hoạt Điểm Phản hồi Nhắc nhở (`response_score`) migration 112 và module version 1.0.12:
  snapshot `response_sla_hours` cho actionable reminder, conditional update
  `response_due_at` sau provider delivery trong DB transaction, self-healing
  reconciler trong Cron hook `sales_pipeline_cron_reminder()` với lock riêng
  `db_prefix() . ':sales_pipeline:reconciler:sla'`, dynamic denominator per-staff
  (100 khi `eligible > 0` / 85 khi `eligible == 0`), cờ `insufficient_reminder_sample`
  kèm badge "Tạm tính (Mẫu nhỏ)" khi `0 < eligible < 3`, cấu hình Settings tab
  Performance (`#performance`) cô lập form.

## Mức xác minh

- Có test harness/contract tests cho Estimate Group, Performance Score và pure evaluator cho Deal Reminder.
- Có 6 test resilience được version-control trong
  `modules/sales_pipeline/tests/Reminder_delivery_*_test.php`; toàn bộ 12 test
  module đạt ngày 2026-08-27.
- Reminder Bell contract test đã được đưa vào version-control; toàn bộ 13 test
  scripts hiện có đạt ngày 2026-08-28, cùng PHP/JavaScript syntax check.
- Delivery Health đã được xác minh trên localhost bằng phiên Admin thật ở
  desktop/mobile; không có overflow hoặc JavaScript error trong lần reload cuối.
- Việc file/method tồn tại không chứng minh migration đã chạy trên DB production.
- DB-backed transaction/concurrency và manual UI cần được xác nhận theo môi trường trước release.
- Reminder Bell desktop đã xác minh trên localhost rằng feed lỗi/rỗng không tạo
  panel riêng và Core Bell vẫn hiển thị. Migration 111 đã chạy trên localhost;
  feed strict-mode đạt và runtime hiển thị `✕` cho Informational nhưng không cho
  Actionable. Chưa xác minh trên DB snapshot production, HTTP CSRF, concurrent
  acknowledge hoặc checklist mobile/Pusher; không bật production trước khi các
  bước này đạt.
- Chưa hoàn tất staging load test, hai-worker crash test, SMTP production canary
  và `EXPLAIN` trong database runtime; giới hạn P.A chưa xác nhận vẫn dùng profile
  Canary trong kế hoạch v1.3.

## Cách cập nhật trạng thái

Mỗi mục mới phải ghi:

- file/method;
- migration/schema;
- test đã chạy;
- manual verification;
- blocker còn lại.

Không dùng cụm “hoàn thành 100%” nếu chưa có bằng chứng cho DB và UI runtime.
