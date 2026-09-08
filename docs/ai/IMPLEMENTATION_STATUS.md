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
- Persistent Reconcile Checkpoint & Advisory Lock (Migration 114 / Module Version 1.1.4):
  quản lý cursor tự động bằng option `sp_first_sent_reconcile_cursor` (`autoload = 0`),
  bảo vệ bằng MySQL Advisory Lock `tblsales_pipeline:first_sent_reconcile` (`pconnect = false`),
  xử lý lỗi an toàn (không nhảy cóc), reset tự động về 0 khi hoàn tất chu kỳ quét bảng,
  bổ sung đầy đủ khóa ngôn ngữ `sales_pipeline_deal` cho gói tiếng Việt và tiếng Anh.

## Mức xác minh & Hoàn thành 7 Phase (Vá 6 Blockers Pre-Production)

Toàn bộ **6 Blockers Pre-Production** đã được giải quyết triệt để thông qua **7 Phase kiểm định kỹ thuật** (tất cả đều đạt **FULL PASS** tính đến ngày 2026-09-07):

1. **Phase 1 (Module Baseline & Migration 114 — PASS ✅):**
   - Header module `1.1.4`, schema DB khớp 100%, Bell flag mặc định `0`, Dashboard HTTP 200.
2. **Phase 2 (Database Index & 4 Canonical Queries — PASS ✅):**
   - EXPLAIN tối ưu 4 query chuẩn (Quote Count, Exchange Rate, Score Snapshot, Reminder SLA) cho cohort 15 nhân sự: toàn bộ dùng `ref`/`range`, 0 full scan, thời gian thực thi ~3.68 ms (giải quyết triệt để **Blocker 5**).
3. **Phase 2.5 (Profiling, Latency Baseline & Launchd Cron Cadence — PASS ✅):**
   - Đạt benchmark PHP-FPM 5 workers (P95: Dashboard 305ms, Leaderboard 277ms, Drawer 177ms; 0 lỗi).
   - macOS launchd (`com.portal18.phase25.cadence.plist`) tự động kích hoạt 2 chu kỳ thật (10:46:03 & 10:51:08) 100% không dùng cờ `--force`; cooldown 300s hoạt động tự nhiên; 0 anomaly; đối soát cấp trường 9.716 dòng đạt diff = 0 tuyệt đối.
4. **Phase 3 (Concurrency Claim & Resilience Recovery — PASS ✅):**
   - Two-worker atomic claim chống race condition; SIGKILL lock recovery an toàn; stale recovery 15 phút với `pconnect = false` (giải quyết triệt để **Blocker 6**).
5. **Phase 4 (SMTP Transport, Isolated Cron Dispatch & Safe Cleanup — PASS ✅):**
   - Stage 3A: CLI Cron context (`CRON=true`, không staff session); bypass Core queue thành công (`tblmail_queue` giữ nguyên tuyệt đối 36, 0 rò rỉ); rate bucket ghi nhận 1 attempt; wire-level ESMTP handshake `250 OK`; rollback 15.322 deliveries và 7.661 reminders hoàn hảo (giải quyết triệt để **Blocker 4**).
   - Stage 3B: Gửi nhận thực tế qua SMTP relay MDaemon đạt kết quả tốt trên Webmail và Mobile.
6. **Phase 5 (Config & UI Safety Gates — PASS ✅):**
   - Cờ Bell `sp_reminder_crm_inbox_enabled = 0` cô lập an toàn, luồng fallback in-app sang `add_notification()` mặc định của Perfex CRM hoạt động chính xác (giải quyết triệt để **Blocker 2**).
7. **Phase 6 (Tải Đồng Thời Multi-Worker Concurrency — FULL PASS ✅):**
   - 15 tài khoản nhân viên Sales độc lập trên Apache 8081 + 5 PHP-FPM workers; 450 requests; P95 Dashboard **309,4 ms** (< 2s), Leaderboard **272,2 ms** (< 1s), Drawer **182,9 ms** (≤ 1.5s); Tỷ lệ lỗi **0/150 (0%)**; 0 deadlock, 0 duplicate reminder, 0 queue leakage (giải quyết triệt để **Blocker 1 & Blocker 3**).
8. **Phase 7 (Client Mailbox Acceptance & Production Readiness — FULL PASS ✅):**
   - Đã kiểm thử giao vận email thực tế từ CRM qua SMTP MDaemon: Webmail MDaemon nhận đầy đủ 100%, Outlook Mobile nhận hiển thị chuẩn trong Inbox. Người dùng đã phê duyệt bỏ qua độ trễ IMAP sync của phần mềm Outlook Desktop cũ; đã bổ sung khóa ngôn ngữ `sales_pipeline_deal` trong Basecode.
   - **Gatekeeper: APPROVED FOR ROLLOUT ✅**. Toàn bộ hệ thống sẵn sàng đưa lên Production.

## Cách cập nhật trạng thái

Mỗi mục mới phải ghi:
- file/method;
- migration/schema;
- test đã chạy;
- manual verification;
- blocker còn lại.

Trạng thái hiện tại: Đã hoàn tất toàn bộ 7 Phase nghiệm thu kỹ thuật, sẵn sàng triển khai Production.
