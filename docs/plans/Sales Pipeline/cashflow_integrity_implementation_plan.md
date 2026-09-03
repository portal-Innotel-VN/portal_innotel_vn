# Kế hoạch triển khai Cashflow Integrity và Data Sanitization

## 1. Mục tiêu và ranh giới

Kế hoạch này sửa các lỗi toàn vẹn tiền tệ đã có expected behavior rõ ràng mà
không thay đổi ngầm các policy KPI còn chờ phê duyệt.

Các luồng được phép triển khai ngay:

1. Chuẩn hóa snapshot tiền tệ theo đơn vị `1 source currency = N base currency`.
2. Fail closed khi không có tỷ giá; không fallback ngoại tệ về `1.0`.
3. Deal Bridge chỉ ghi `deal_value` bằng giá trị base currency hợp lệ.
4. Không đổi Deal sang Won nếu một Accepted Group thiếu giá trị base.
5. Self-heal `decision_value_base` sau khi Version có tỷ giá hợp lệ.
6. Chặn phản hồi Reminder trước khi delivery và SLA bắt đầu.
7. Chuẩn bị Data Sanitization theo dry-run và danh sách Finance phê duyệt.

Các chính sách đã được phê duyệt chính thức (2026-09-03):

- **Phạm vi module:** Chuẩn hóa thành "Doanh thu & Lợi nhuận Pipeline" (Sales CRM).
- **Quote Count:** Chỉ tính Báo giá hợp lệ (`status IN (2, 3, 4, 5)`), loại bỏ draft hoàn toàn; mốc thời gian tính theo ngày gửi bản thảo đầu tiên.
- **Target tháng chuẩn:** 30 Báo giá/tháng/nhân sự (theo Rule Engine Rule 2 `ESTIMATE_MONTHLY_MIN_COUNT`), đồng bộ giữa Executive KPI và Performance Score.
- **Performance Score V2:** Áp dụng chính thức từ tháng 09/2026 (`2026-09`).
- **Chính sách Provisional:** Áp dụng hệ số suy giảm độ tin cậy (Confidence Weighting) theo kích thước mẫu thay vì loại khỏi bảng xếp hạng.

Các nội dung kỹ thuật còn lại tiếp tục hoàn thiện:
- lifecycle `closed_at` và công thức Win Rate;
- phân tách `pipeline_profit` và `realized_gross_profit`.

## 2. Sửa sai kế hoạch Reconciliation

`reconcile_estimate_groups()` chỉ gọi `sync_estimate_group()` và không refresh
snapshot của từng Estimate Version. Không dùng cron reconciliation thông thường
để backfill dữ liệu tiền tệ.

Data Sanitization phải là một service/command riêng với pipeline:

```text
scan read-only
→ export manifest
→ Finance approve exact record IDs and rates
→ dry-run deterministic calculation
→ compare counts/totals
→ apply approved batch
→ reconcile affected Groups and Deals
→ immutable audit result
```

### Hợp đồng manifest

Mỗi dòng được duyệt tối thiểu phải có:

```text
estimate_version_id
estimate_group_id
estimate_id
source_currency_id
base_currency_id
rate_date
exchange_rate_to_base
approved_by
approval_reference
```

Không tự lấy tỷ giá hiện tại để sửa lịch sử. Rate có nghĩa cố định:

```text
base_total = ROUND(source_total × exchange_rate_to_base, 2)
1 source currency = exchange_rate_to_base base currency
```

### Safety và idempotency

- `--dry-run` là mặc định và không được mở transaction ghi.
- Apply yêu cầu manifest có ID cụ thể; không nhận wildcard hoặc toàn bảng.
- Lock theo batch; cập nhật có optimistic guard trên giá trị before.
- Batch chạy lại phải trả `already_applied`, không tạo audit trùng.
- Không tự đổi Won/Lost trong sanitizer. Deal Bridge chỉ chạy sau bước xác nhận.
- Không sửa migration cũ; schema bổ sung phải dùng migration mới.
- Lưu before/after, actor, approval reference, batch ID và timestamp.

## 3. Thứ tự triển khai theo TDD

### Slice A — Currency resolver

Seam: public pure resolver.

- Base currency trả rate `1`.
- Ngoại tệ có rate tạo đúng `base_total`.
- Ngoại tệ thiếu rate trả `NULL`.
- Rate unit được trả trong result contract.
- Làm tròn monetary amount về hai chữ số.

### Slice B — Deal Bridge

Seam: `Estimate_revision_service::sync_deal()`.

- Pending dùng Version `base_total`, không dùng `estimates.total`.
- Pending thiếu base value giữ nguyên Deal và trả lỗi rõ.
- Accepted thiếu một value giữ nguyên cả value/status.
- Accepted hợp lệ cộng bằng integer cents, không dùng binary float.
- Manual lock không thay đổi dữ liệu.
- Tổng vượt `DECIMAL(15,2)` bị từ chối trước update.

### Slice C — Reconciliation

Seam: public reconciliation/sanitization service, không test private method.

- Group Accepted thiếu value được heal khi decision Version có base value.
- Chạy lại không tạo update/audit mới.
- Finance lock ngăn thay decision snapshot. Hiện chưa có field/trạng thái Finance
  lock trong schema; đây là cổng thiết kế bắt buộc trước khi viết sanitizer apply.

### Slice D — Reminder response

Seam: `submit_reminder_response()`.

- Chưa có `sent_at`/`response_due_at` trả `delivery_pending`.
- Delivery thành công cho phép phản hồi.
- Boundary đúng hạn/trễ hạn được tính đúng.
- Hai submit đồng thời chỉ một request thành công.

### Slice E — Policy-dependent KPI

Chỉ viết test sau khi policy tương ứng được phê duyệt:

- V1/V2 và cutover;
- Quote Count/draft-only/target;
- provisional ranking;
- Win Rate/closed_at;
- profit theo trạng thái.

## 4. SQL verification

Mọi query production trước apply phải read-only và báo cáo ít nhất:

- số Version ngoại tệ có rate `1`;
- số Version ngoại tệ thiếu rate/base total;
- số Accepted Group thiếu/zero decision value;
- số Won Deal có value zero;
- số Pending Deal có value lệch Version base total;
- chênh lệch tổng tiền trước/sau theo currency và batch.

Giá trị `0` và rate `1` chỉ là nghi vấn, không tự động coi là sai.

## 5. Cổng Go-Live

Chỉ đạt khi:

1. Từng regression test đã đỏ trước fix và xanh sau fix.
2. Toàn bộ module tests và PHP syntax checks đạt.
3. SQL dry-run trên snapshot production có Finance sign-off.
4. Apply thử trên staging chạy lại lần hai không thay dữ liệu.
5. UI Admin/Staff hiển thị đúng trạng thái blocked/provisional và không lỗi JS.
6. Có rollback/disable path và bản đối chiếu tổng tiền trước/sau.

## 6. Kết quả thực thi ngày 03/09/2026

| Nhóm | Trạng thái | Bằng chứng / khoảng trống |
|---|---|---|
| Currency resolver | PASS ở domain | Base rate `1`, foreign rate hợp lệ, missing rate `NULL`, rate unit và round 2 chữ số đã có test. Production vẫn cần nguồn tỷ giá authoritative đăng ký hook. |
| Deal bridge | PASS ở domain | Pending dùng Version `base_total`; missing value preserve; Accepted thiếu value fail; integer-cents sum; manual lock; overflow guard. |
| Reconciliation | PASS một phần | Self-heal và lần chạy thứ hai no-op đã có test. Finance decision lock chưa thể test vì schema không có field/trạng thái lock. |
| Performance versioning | BLOCKED | Code hiện chỉ có V1; chưa có V2, cutover resolver hoặc target snapshot lịch sử. |
| Quote count | BLOCKED | Chưa chốt draft-only, ngày ghi nhận revision khác kỳ và target 20/30 thống nhất. |
| Reminder | PASS phần guard/SLA | Guard delivery, on-time/deadline/late và concurrent conditional update có test. Provisional small-sample đang theo hành vi hiện tại, chưa phải policy được Finance/Sales duyệt. |
| Sanitization | BLOCKED apply | Có scan SQL read-only và contract manifest; chưa có Finance lock/approval manifest nên chưa được phép viết batch apply. |

Kết quả chạy kỹ thuật:

- 19/19 test file của module pass.
- 15/15 PHP file thay đổi pass syntax lint.
- SQL local read-only: 0 bất thường currency/Deal trong dữ liệu hiện tại; có
  17 reminder đã được phản hồi khi thiếu `sent_at` hoặc `response_due_at`.
- Computer Use: dashboard local render bình thường. Reminder chưa delivery ban
  đầu vẫn hiện form (fail); sau fix hiển thị `Chờ gửi thành công`, không còn
  textarea/nút submit (pass).

Kết luận: chưa đạt Go-Live cho toàn bộ test matrix. Chỉ các lát cắt Critical có
hành vi không mơ hồ đã đủ cơ sở và đã được vá. Các dòng `BLOCKED` phía trên phải
được chốt đặc tả và bổ sung fixture/staging evidence trước sanitizer apply.
