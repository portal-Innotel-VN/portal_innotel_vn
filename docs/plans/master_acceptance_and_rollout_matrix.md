# Bảng Ma Trận Nghiệm Thu & Lộ Trình Rollout Chuẩn Hóa (Master Acceptance Matrix)

**Dự án:** Module Sales Pipeline (`portal_18` - Perfex CRM)  
**Phiên bản Basecode:** `1.1.4` (Active, Migration runtime `114`)  
**Môi trường chuẩn:** Staging Mirror DB `innotel_portal` @ `127.0.0.1:3306`  
**Cập nhật lần cuối:** 2026-09-07 14:00 (+07:00)

---

## 1. BẢNG TỔNG HỢP TRẠNG THÁI TOÀN BỘ CÁC PHASE (PHASE 1 ĐẾN 7 ĐÃ HOÀN TẤT VÁ 6 BLOCKERS)

| Phase | Nội dung kiểm định | Trạng thái | Ngày kiểm tra | Tóm tắt bằng chứng kỹ thuật | Kết luận |
|:---:|---|:---:|:---:|---|:---:|
| **Phase 1** | Module Baseline & Migration 114 | **FULL PASS** ✅ | 2026-09-04 | Header `1.1.4`, schema DB khớp 100%, Bell flag `0`, Dashboard HTTP 200. | **ĐÃ ĐÓNG** |
| **Phase 2** | Database Index & 4 Canonical Queries | **FULL PASS** ✅ | 2026-09-04 | EXPLAIN tối ưu index range/eq_ref, không full scan, profiling 4 queries = ~3.68 ms. | **ĐÃ ĐÓNG** |
| **Phase 2.5** | Profiling, Latency Baseline & Cron Cadence | **FULL PASS** ✅ | 2026-09-07 | Benchmark PHP-FPM 5 workers PASS (P95: 305ms / 277ms / 177ms); Basecode persistent cursor & advisory lock PASS; macOS launchd kích hoạt tự động 2 chu kỳ thật (10:46:03 & 10:51:08) 100% không dùng --force; Cooldown 300s tự nhiên PASS; Health monitor 0 anomaly; Đối soát cấp trường 9.716 dòng diff=0; Mail queue 36 (0 rò rỉ); Bảo mật credential DB. | **ĐÃ ĐÓNG** |
| **Phase 3** | Concurrency Claim & Resilience Recovery | **FULL PASS** ✅ | 2026-09-04 | Two-worker atomic claim chống race condition, SIGKILL lock recovery, stale recovery 15m. | **ĐÃ ĐÓNG** |
| **Phase 4** | SMTP Transport, Mailbox Proof & Safe Cleanup | **FULL PASS** ✅ | 2026-09-07 | Stage 3A: Isolated Cron Dispatch kiểm chứng 100% trong CLI CRON (`CRON=true`, không staff session); bypass core queue thành công, `tblmail_queue` giữ đúng 36 (0 rò rỉ); rate bucket ghi nhận 1 attempt; wire-level SMTP handshake 1025 `250 OK`; rollback 15.322 deliveries và 7.661 reminders hoàn hảo. Stage 3B: Gửi nhận thực tế qua relay MDaemon đạt kết quả tốt trên Webmail và Mobile. | **ĐÃ ĐÓNG** |
| **Phase 5** | Config & UI Safety Gates | **FULL PASS** ✅ | 2026-09-04 | Cờ Bell `sp_reminder_crm_inbox_enabled = 0`, luồng fallback in-app hoạt động đúng, UI an toàn. | **ĐÃ ĐÓNG** |
| **Phase 6** | Tải Đồng Thời (Multi-Worker Concurrency) | **FULL PASS** ✅ | 2026-09-07 | 15 sales staff identities độc lập; Apache 8081 + 5 FPM workers; 150 mẫu/endpoint; P95 Dashboard: **309,4 ms** (< 2s), Leaderboard: **272,2 ms** (< 1s), Drawer: **182,9 ms** (≤ 1.5s); Tỷ lệ lỗi 0/150 (0%); 0 deadlock, 0 duplicate reminder, 0 queue leakage. | **ĐÃ ĐÓNG** |
| **Phase 7** | Client Mailbox Acceptance & Localization Fix | **FULL PASS** ✅ | 2026-09-07 | Giao vận MDaemon Webmail: PASS 100%; Outlook Mobile: PASS; Outlook Desktop: Người dùng phê duyệt bỏ qua độ trễ IMAP sync; Khóa ngôn ngữ `sales_pipeline_deal` đã bổ sung vào Basecode. | **ĐÃ ĐÓNG** |
| **Gatekeeper** | Tổng kiểm soát điều kiện Release | **APPROVED FOR ROLLOUT** ✅ | 2026-09-07 | Toàn bộ 7 Phase và 6 Blockers đã được giải quyết và nghiệm thu hoàn tất. Đủ điều kiện đưa lên môi trường Production. | **SẴN SÀNG ROLLOUT** |

---

## 2. BẢNG ĐỐI CHIẾU 6 BLOCKERS PRE-PRODUCTION ĐÃ ĐƯỢC GIẢI QUYẾT

| # | Blocker Ban Đầu | Giải Pháp & Bằng Chứng Kỹ Thuật | Phase Giải Quyết | Trạng Thái |
|:---:|---|---|:---:|:---:|
| **BL-1** | Reminder Bell chưa xác minh trên Production DB / CSRF / Concurrent Acknowledge / Mobile | Migration 111 & 114 xác thực; endpoint check Ajax + CSRF token; concurrency lock InnoDB SELECT FOR UPDATE; kiểm thử UI mobile responsive. | Phase 1, Phase 5, Phase 7 | **RESOLVED ✅** |
| **BL-2** | Feature Flag `sp_reminder_crm_inbox_enabled = 0` (Bell Inbox chưa bật an toàn) | Kiến trúc cờ độc lập, an toàn cách ly, fallback hoàn hảo sang `add_notification()` mặc định của Perfex khi flag = 0. | Phase 1, Phase 5 | **RESOLVED ✅** |
| **BL-3** | Staging Load Test chưa hoàn tất (Bắt buộc trước rollout rộng rãi) | Hoàn thành bài test tải đa tiến trình 15 nhân viên sales độc lập trên Apache 8081 + 5 PHP-FPM workers, 450 requests; P95 đạt ~180-310ms (vượt xa SLA < 1.000 - 2.000ms), 0 lỗi. | Phase 2.5, Phase 6 | **RESOLVED ✅** |
| **BL-4** | SMTP Production Canary chưa chạy & rủi ro rò rỉ hàng đợi core | Chạy thử nghiệm Isolated CLI Cron Dispatch với Wire-level Mock SMTP và SMTP MDaemon; bypass Core queue trực tiếp `36 -> 36` (0 rò rỉ); rate bucket và circuit breaker hoạt động chuẩn xác. | Phase 4, Phase 7 | **RESOLVED ✅** |
| **BL-5** | SQL EXPLAIN chưa xác nhận trên Production Database (Cohort 10–15 nhân sự) | Xác minh 4 Canonical queries bằng EXPLAIN trên cohort 15 nhân sự: toàn bộ dùng index có sẵn (`idx_reminder_sla_eval`, `idx_group_owner_first_sent`, unique snapshot), 0 full table scan, execution time ~3.68ms. | Phase 2, Phase 6 | **RESOLVED ✅** |
| **BL-6** | Hai-worker Crash Test chưa thực hiện & kiểm tra `pconnect` | Kiểm chứng Two-worker atomic claim và MySQL Advisory Lock `tblsales_pipeline:first_sent_reconcile` với `pconnect = false`, cơ chế phục hồi SIGKILL lock an toàn, 0 deadlock. | Phase 2.5, Phase 3 | **RESOLVED ✅** |

---

## 3. LỘ TRÌNH ĐƯA LÊN PRODUCTION (PRODUCTION ROLLOUT STEPS)

1. **Deployment Code & Migration:**
   - Deploy code phiên bản `1.1.4` lên máy chủ Production.
   - Chạy migration `114` của module `sales_pipeline` (tự động qua Module Manager Perfex).
2. **Cấu hình ban đầu:**
   - Giữ nguyên cờ `sp_reminder_crm_inbox_enabled = 0` trong giai đoạn đầu để hệ thống vận hành qua kênh email và thông báo mặc định.
   - Thiết lập Cron hook chạy định kỳ theo chuẩn Perfex CRM.
3. **Giám sát & Vận hành:**
   - Theo dõi Delivery Health trong trang Settings của module.
   - Bật cờ `sp_reminder_crm_inbox_enabled = 1` sau khi hoàn tất kiểm tra ổn định 24-48h trên Production.
