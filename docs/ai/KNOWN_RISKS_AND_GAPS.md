# Known Risks and Gaps

## Checklist rà soát & Trạng thái xác minh (Đã giải quyết 6 Blockers Pre-Production)

Toàn bộ 6 Blockers Pre-Production đã được giải quyết và kiểm chứng bằng bằng chứng thực tế:

- **Fallback Group và audit atomic:** ĐÃ XÁC MINH (Transaction + audit log trong `Sales_pipeline_model`).
- **Permission source/target/history tại server:** ĐÃ XÁC MINH (Endpoint kiểm tra quyền chặt chẽ trong controller).
- **Schema bootstrap & migration 114:** ĐÃ XÁC MINH (Module version `1.1.4`, migration `114` đã chạy và khớp 100% schema).
- **Deal bridge cleanup:** ĐÃ XÁC MINH (Migration 109 & logic đồng bộ trạng thái Deal - Group).
- **Tải đồng thời đa nhân viên (Blocker 1 & 3):** ĐÃ XÁC MINH QUA PHASE 6 (15 sales staff độc lập, 5 FPM workers, 450 request, P95 < 310ms, 0 lỗi, 0 deadlock).
- **Khóa Advisory Lock & Hai-worker crash (Blocker 6):** ĐÃ XÁC MINH QUA PHASE 2.5 & PHASE 3 (MySQL Advisory Lock `tblsales_pipeline:first_sent_reconcile` với `pconnect = false`, SIGKILL recovery an toàn).
- **Tự động hóa Cron Cadence:** ĐÃ XÁC MINH QUA PHASE 2.5 (macOS launchd kích hoạt tự động 2 chu kỳ thật, cooldown 300s tự nhiên, checkpoint persistent cursor).
- **Giao vận SMTP & cô lập hàng đợi (Blocker 4):** ĐÃ XÁC MINH QUA PHASE 4 (Stage 3A CLI Isolated Cron gửi trực tiếp qua PHPMailer ESMTP handshake 250 OK, `tblmail_queue` giữ nguyên 36, 0 rò rỉ; Stage 3B gửi nhận thành công).
- **SQL EXPLAIN trên dữ liệu thật (Blocker 5):** ĐÃ XÁC MINH QUA PHASE 2 (4 query canonical dùng index tối ưu `ref`/`range`, không full scan, ~3.68ms).
- **Cờ Feature Flag Bell Inbox (Blocker 2):** ĐÃ XÁC MINH QUA PHASE 5 (Mặc định `sp_reminder_crm_inbox_enabled = 0`, fallback an toàn sang `add_notification()` mặc định).
- **Client Mailbox & Localization:** ĐÃ XÁC MINH QUA PHASE 7 (Giao vận MDaemon & Outlook Mobile PASS; Khóa ngôn ngữ `sales_pipeline_deal` đã bổ sung vào Basecode).

Khi một rủi ro mới phát sinh, cập nhật file này cùng bằng chứng test. Hiện tại không còn blocker kỹ thuật nào cản trở Release.
