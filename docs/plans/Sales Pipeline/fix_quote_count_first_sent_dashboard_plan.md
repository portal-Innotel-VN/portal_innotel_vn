# Kế hoạch vá lỗi Quote Count không cập nhật trên Dashboard và Drawer

## 1. Trạng thái và mục tiêu

- **Loại tài liệu:** Kế hoạch thực thi hotfix theo TDD.
- **Ngày lập:** 03/09/2026.
- **Trạng thái:** Proposed — chưa triển khai.
- **Phạm vi UI:** `/admin/sales_pipeline/dashboard?dashboard_tab=estimates`.
- **Triệu chứng:** Báo giá đã chuyển từ Nháp sang Đã gửi nhưng cột **Số báo giá**
  trên bảng xếp hạng và card **Tổng báo giá** trong Drawer vẫn bằng `0`.

Kết quả đích:

1. Hai Báo giá chỉ ở trạng thái Nháp không được tính.
2. Một Báo giá chuyển hợp lệ sang Đã gửi được tính là một Báo giá logic trong
   đúng kỳ phát sinh sự kiện gửi.
3. Bảng xếp hạng, Drawer, KPI Dashboard và Reminder Rule đọc cùng một nguồn
   Quote Count.
4. Nhiều revision trong cùng Estimate Group không làm tăng số lượng.
5. Dữ liệu `first_sent_at` bị bỏ sót được tự phục hồi từ bằng chứng có sẵn mà
   không ghi đè một timestamp sớm hơn hoặc dữ liệu tài chính đã khóa.

## 2. Bằng chứng lỗi đã xác nhận

Fixture trên DB localhost ngày 03/09/2026:

| Estimate | Trạng thái | `sent` | `datesend` | Group | `first_sent_at` | Kết quả hiện tại |
|---|---:|---:|---|---:|---|---:|
| ID 121 | Nháp (`1`) | `0` | `NULL` | 146 | `NULL` | Không tính — đúng |
| ID 122 | Nháp (`1`) | `0` | `NULL` | 147 | `NULL` | Không tính — đúng |
| ID 123 | Đã gửi (`2`) | `1` | `2026-09-03 12:13:40` | 148 | `NULL` | Không tính — sai |

Activity của ID 123:

```text
12:12:13 — estimate_activity_created
12:13:40 — not_estimate_status_updated: 1 → 2
```

Query canonical yêu cầu `g.first_sent_at` nằm trong kỳ. Vì Group 148 có
`first_sent_at = NULL`, Backend trả `estimate_count = 0`; cả leaderboard và
Drawer cùng render giá trị này.

## 3. Nguyên nhân gốc

Luồng hiện tại:

```text
Chỉnh Báo giá: status 1 → 2
  → Core cập nhật status=2, sent=1, datesend
  → hook after_estimate_updated
  → module chỉ refresh snapshot + sync outcome
  → first_sent_at vẫn NULL
  → Quote_count_repository loại Group
  → leaderboard = 0 và Drawer = 0
```

Module chỉ capture `first_sent_at` qua hook `estimate_sent`, trong khi hook này
chỉ được Core phát sau khi gửi email thành công. Các đường đổi trạng thái bằng
form, action trạng thái hoặc pipeline có thể đặt `status=2`, `sent=1` và
`datesend` mà không phát `estimate_sent`.

Ngoài ra, test hiện tại mới kiểm tra sự tồn tại của hook/handler bằng source
contract; chưa chạy luồng DB thật `Nháp → Đã gửi → Quote Count`.

## 4. Nguồn sự thật và invariant

Thực thi kế hoạch phải giữ các invariant sau:

- Một Estimate Group là một Báo giá logic; revision không tăng Quote Count.
- Draft-only Group không được tính.
- Group owner nhận sản lượng; decision owner chỉ liên quan Accepted Revenue.
- Rank được tính trên full cohort trước role projection.
- Kỳ dùng khoảng nửa mở `[period_start, period_end_exclusive)` theo timezone
  `Asia/Ho_Chi_Minh`.
- Timestamp sớm nhất là bất biến: sự kiện đến sau không được đẩy
  `first_sent_at` về sau.
- Dữ liệu được suy diễn phải có `first_sent_source`; không gắn nhãn `event` cho
  dữ liệu backfill.
- Không sửa migration 113 vì migration này đã có khả năng được áp dụng. Backfill
  bổ sung dùng migration kế tiếp sau khi xác minh version hiện hành.
- Không sửa Core Perfex nếu module hook/service/reconciliation có thể giải quyết.

### 4.1. Contract sự kiện gửi cần chuẩn hóa

Để phù hợp hành vi người dùng và Core Perfex, một Estimate được xem là có bằng
chứng gửi khi thỏa một trong các nguồn sau, theo thứ tự ưu tiên:

1. Activity `invoice_estimate_activity_sent_to_client` từ gửi email thành công.
2. `sent = 1` và `datesend IS NOT NULL` do Core ghi khi chuyển trạng thái sang
   Đã gửi.
3. Activity thay đổi/đánh dấu trạng thái sang `2`, khi dữ liệu legacy thiếu
   `datesend`; timestamp activity là bằng chứng suy diễn.
4. Chỉ dành cho backfill legacy đã được phê duyệt: Estimate có status
   `2/3/4/5` nhưng thiếu toàn bộ bằng chứng trên; dùng chính sách fallback được
   ghi rõ source, không giả mạo thành event thật.

Trước khi triển khai, cập nhật `docs/specifications/PERFORMANCE_SCORE.md` để nêu
rõ rằng thao tác chuyển sang Đã gửi trong Core là một sent event hợp lệ khi Core
đã ghi `sent/datesend`. Đây là thay đổi làm rõ contract, không thay đổi quy tắc
Draft-only và một Group bằng một Quote.

## 5. Vùng ảnh hưởng dự kiến

| Khu vực | File/seam | Trách nhiệm |
|---|---|---|
| Hook | `modules/sales_pipeline/sales_pipeline.php` | Chuyển mọi sent signal vào service chung |
| Capture service | Tạo `libraries/Quote_first_sent_service.php` hoặc mở rộng seam tương đương | Resolve Group, chọn timestamp/source, update nguyên tử |
| Reconciliation | `models/Sales_pipeline_model.php` | Tự phục hồi sent evidence bị bỏ sót |
| Count query | `libraries/Quote_count_repository.php` | Giữ read model canonical; không nới lỏng bằng cách bỏ `first_sent_at` |
| Migration | Migration kế tiếp sau version hiện hành | Backfill NULL có bằng chứng; additive/idempotent |
| Leaderboard | `views/partials/_estimates_leaderboard.php` | Render canonical `estimate_count` |
| Drawer | `views/_dashboard_staff_pipeline.php` | Render cùng `performance_metric.estimate_count` |
| AJAX | `assets/js/dashboard.js` | Giữ refresh theo `period` và `period_anchor`; không tự tính count |
| Specification | `docs/specifications/PERFORMANCE_SCORE.md` | Chuẩn hóa định nghĩa sent evidence |
| Tests | `modules/sales_pipeline/tests/` | Unit, contract, DB-backed và UI regression |

## 6. Các phase thực thi

### Phase 0 — Baseline và bảo vệ worktree

Thực hiện:

1. Chạy `git status --short`, `git diff --check` và đọc diff của mọi file dự kiến
   chạm tới.
2. Ghi nhận các thay đổi đang tồn tại trong working tree; không ghi đè hoặc gom
   chúng vào hotfix khi chưa xác định ownership.
3. Xác minh module version/migration hiện hành trên Basecode và DB localhost.
4. Chạy toàn bộ test hiện có của module và lưu danh sách pass/fail.
5. Chạy preflight read-only:
   - số Group `first_sent_at IS NULL`;
   - số Group NULL nhưng có revision `sent=1 AND datesend IS NOT NULL`;
   - số Group NULL nhưng có activity gửi email;
   - số Group NULL chỉ có revision Nháp;
   - phân bố theo tháng và owner, chỉ xuất aggregate không PII.

Hoàn tất khi có baseline gồm test result, schema version, dirty files và số lượng
dữ liệu cần tự phục hồi.

### Phase 1 — Dựng regression test Red

Viết test qua seam thật trước khi sửa production code.

#### 6.1. Unit/service

- `status=1`, `sent=0`, `datesend=NULL` → không capture.
- Chuyển `1 → 2`, `sent=1`, có `datesend` → capture đúng `datesend`.
- Gọi lại cùng event → no-op, không đổi source/timestamp.
- Event đến sau → không thay timestamp sớm hơn.
- Event đến sớm hơn → cập nhật đồng bộ timestamp, source và estimate ID.
- Estimate chưa link Group → trả trạng thái rõ ràng, không tạo Group ngầm trong
  capture service.

#### 6.2. DB integration — repro chính

Fixture tối thiểu:

```text
Staff A
  Group 1 → Estimate Draft
  Group 2 → Estimate Draft
  Group 3 → Estimate Draft, sau đó update status 1 → 2
```

Assertions:

1. Hai Group Draft có `first_sent_at = NULL`.
2. Group 3 nhận `first_sent_at = datesend`, source và estimate ID đúng.
3. `Quote_count_repository` trả count `1` cho Staff A trong tháng.
4. Cùng Group có thêm revision không làm count thành `2`.
5. Kỳ trước/kỳ sau trả `0`.

#### 6.3. Route variants

Tạo ca cho từng đường có thể đặt trạng thái Đã gửi:

- cập nhật form Estimate;
- gửi email thành công;
- admin mark status `2`;
- kéo card sang trạng thái Đã gửi trong pipeline;
- tạo mới trực tiếp ở trạng thái `2`, nếu UI/Core cho phép.

Mỗi đường phải kết thúc ở cùng service hoặc được reconciliation tự phục hồi.

#### 6.4. Frontend contract

- Leaderboard dùng `estimate_count` từ ranking payload.
- Drawer dùng đúng row `performance_metric` của staff được chọn.
- Với fixture `2 Draft + 1 Sent`, hai vị trí đều render `1`.
- Đổi `period` hoặc `period_anchor` làm cả hai vị trí cùng đổi và không giữ HTML
  cũ.

Hoàn tất khi repro chính đỏ vì Group 3 vẫn có `first_sent_at = NULL`; không chấp
nhận test chỉ grep source hoặc chỉ kiểm tra handler tồn tại.

### Phase 2 — Tạo seam capture duy nhất

Tách logic trong global function hiện tại sang một service có contract rõ:

```php
capture($estimateId, $occurredAt, $source): array
captureFromCurrentEstimate($estimateId, $sourceHint): array
```

Service phải:

1. Validate estimate ID và tìm Estimate Version/Group hiện hữu.
2. Đọc `status`, `sent`, `datesend`, activity và dữ liệu Group cần thiết.
3. Chỉ capture khi evidence hợp lệ theo mục 4.1.
4. Chuẩn hóa timestamp theo timezone ứng dụng.
5. Update có điều kiện:

```text
WHERE group.id = ?
  AND (first_sent_at IS NULL OR first_sent_at > candidate_at)
```

6. Cập nhật `first_sent_at`, `first_sent_source` và
   `first_sent_estimate_id` như một đơn vị nguyên tử.
7. Trả contract `captured | unchanged | no_evidence | group_missing | error` để
   hook/reconciler có thể audit mà không đoán.

Lưu ý MySQL: tránh phụ thuộc thứ tự gán khiến biểu thức của
`first_sent_estimate_id` đọc `first_sent_at` sau khi field này đã bị thay đổi.
Đặt ID/source trước timestamp trong một conditional update đã khóa điều kiện,
hoặc dùng transaction/row lock với test concurrency tương ứng.

Hoàn tất khi unit test về idempotency và earliest-event chuyển Green.

### Phase 3 — Bao phủ mọi write-path trực tiếp

#### 6.5. Gửi email thành công

Giữ hook `estimate_sent`, nhưng handler chỉ resolve timestamp chính xác rồi gọi
capture service. Ưu tiên `datesend`/activity vừa được Core ghi; không dùng `NOW()`
khi đã có timestamp sự kiện.

#### 6.6. Cập nhật form

Trong `after_estimate_updated`:

1. Sync snapshot/group như hiện tại.
2. Nếu Estimate hiện có `status=2`, `sent=1`, `datesend` hợp lệ, gọi capture
   service.
3. Đảm bảo thứ tự cho phép Estimate Version/Group đã tồn tại trước capture.

#### 6.7. Tạo mới trực tiếp ở trạng thái Đã gửi

Sau `handle_estimate_added`, gọi capture service. Draft sẽ trả `no_evidence`; bản
tạo trực tiếp với sent evidence sẽ được ghi ngay.

#### 6.8. Mark status và pipeline

Không sửa Core `Estimates_model`. Nếu Core không phát module hook ở hai đường
này, dựa vào reconciliation tự phục hồi ở Phase 4. Có thể bổ sung module-side
hook chỉ khi xác minh hook đó thật sự tồn tại và được gọi sau commit DB.

Hoàn tất khi form update và email send cập nhật ngay; các đường không có hook có
test chứng minh được heal ở lần reconcile kế tiếp.

### Phase 4 — Reconciliation tự phục hồi

Mở rộng reconciliation theo batch để xử lý Group có `first_sent_at IS NULL`.

Thứ tự evidence:

1. `MIN(activity.date)` của gửi email thành công.
2. `MIN(estimates.datesend)` với `sent=1`.
3. Timestamp activity chuyển/đánh dấu status sang `2` khi parse được an toàn.
4. Legacy fallback chỉ chạy theo migration/remediation được phê duyệt.

Yêu cầu:

- Chỉ update Group NULL hoặc candidate sớm hơn timestamp hiện tại.
- Không đổi owner, decision owner, outcome hay revenue.
- Batch có giới hạn, cursor ổn định và idempotent.
- Dashboard không phát sinh N+1 query; ưu tiên một query aggregate theo batch.
- Ghi aggregate log như `scanned/captured/no_evidence/errors`, không ghi PII.
- Group Draft-only giữ NULL.

Hoàn tất khi Group 148 hoặc fixture tương đương được heal, và lần chạy thứ hai
không tạo thay đổi.

### Phase 5 — Backfill dữ liệu hiện hữu

1. Xác minh migration cao nhất đã chạy trên từng môi trường.
2. Tạo migration additive kế tiếp; không chỉnh migration 113.
3. Backfill chỉ các Group `first_sent_at IS NULL` có evidence mức 1–3 của Phase 4.
4. Lưu source phân biệt:
   - `activity_email_sent`;
   - `estimate_datesend`;
   - `activity_status_sent`;
   - `legacy_inferred_*` nếu được phê duyệt riêng.
5. Chạy được lần hai mà không thay đổi kết quả.
6. `down()` không xóa timestamp/audit đã ghi; rollback code không được làm mất
   lịch sử.

Hoàn tất khi preflight before/after khớp: mọi record được backfill có evidence,
Draft-only không đổi và số Group còn NULL được giải thích theo từng nhóm.

### Phase 6 — Đồng bộ Backend payload và Frontend

Backend:

1. `get_estimate_performance_ranking()` tiếp tục dùng
   `Quote_count_repository`.
2. `get_staff_kpi_metrics()` và `get_estimate_dashboard_metrics()` dùng cùng
   repository, không tạo query đếm riêng.
3. Drawer chọn đúng ranking row theo `staff_id` sau khi rank toàn cohort.
4. Staff projection không lộ metric của người khác.

Frontend:

1. Leaderboard render `metric['estimate_count']`.
2. Drawer tab Báo giá render `performance_metric['estimate_count']`; fallback
   chỉ dùng canonical `period_estimates` khi ranking row thực sự vắng.
3. JavaScript tiếp tục gửi `dashboard_tab`, `period`, `period_anchor` cho cả
   leaderboard và Drawer.
4. Không thêm phép tính Quote Count ở JavaScript.
5. Khi AJAX lỗi, giữ error state hiện có; không biến lỗi thành số `0` giả.

Không cần thay đổi giao diện trực quan nếu bindings hiện tại đã đúng. Mọi thay
đổi frontend chỉ nhằm khóa contract và trạng thái refresh.

Hoàn tất khi cùng một fixture trả cùng count ở bốn consumer và HTML hai vị trí
khớp payload canonical.

### Phase 7 — Verification và nghiệm thu

Chạy tối thiểu:

```bash
php modules/sales_pipeline/tests/First_sent_capture_and_quote_count_test.php
php modules/sales_pipeline/tests/Quote_count_repository_test.php
php modules/sales_pipeline/tests/Sanitizer_and_drawer_binding_test.php
php modules/sales_pipeline/tests/Performance_score_calculator_test.php
php modules/sales_pipeline/tests/Performance_score_period_options_test.php
php modules/sales_pipeline/tests/Dashboard_historical_period_contract.php
```

Ngoài ra:

- chạy test DB-backed mới cho transition `1 → 2`;
- chạy toàn bộ `modules/sales_pipeline/tests/*.php`;
- `php -l` mọi PHP file thay đổi;
- `git diff --check`;
- kiểm tra query count không tạo regression hiệu năng bằng `EXPLAIN` trên DB test.

Checklist UI localhost với Staff Hờ Hờ:

1. Chọn tab Báo giá và kỳ Tháng 09/2026.
2. Tạo hai Báo giá Nháp: leaderboard/Drawer vẫn không tăng.
3. Tạo Báo giá thứ ba ở Nháp: vẫn không tăng.
4. Chuyển Báo giá thứ ba sang Đã gửi bằng form: sau refresh, leaderboard và
   Drawer đều tăng đúng một.
5. Gửi email lại: count không tăng lần hai.
6. Tạo revision và gửi revision: Group vẫn chỉ tính một.
7. Đổi kỳ không chứa `first_sent_at`: cả hai vị trí trả `0`.
8. Đổi lại kỳ chứa sự kiện: cả hai vị trí trả `1`.
9. Kiểm tra role Admin và Staff/view-own; Staff không nhận dữ liệu người khác.

Hoàn tất khi automated tests Green và checklist UI có ảnh/network evidence; source
test Green một mình không đủ để kết luận hoàn thành.

## 7. Rollout, quan sát và rollback

### Rollout

1. Deploy capture/reconciliation code trước hoặc cùng migration backfill.
2. Chạy migration trên staging; lưu aggregate before/after.
3. Mở Dashboard bằng Admin và Staff test, xác nhận count cùng kỳ.
4. Theo dõi trong một chu kỳ tạo/gửi Báo giá:
   - số sent Estimate mới;
   - số Group được capture trực tiếp;
   - số Group phải self-heal;
   - số Group sent còn `first_sent_at IS NULL`.
5. Chỉ rollout production khi số Group mới phải self-heal không tăng liên tục.

### Rollback

- Revert code capture/reconciliation nếu có lỗi runtime.
- Giữ nguyên các timestamp đã backfill vì chúng có evidence và mang giá trị
  audit; không xóa dữ liệu trong `down()`.
- Nếu một batch backfill sai, sửa bằng migration/remediation mới có manifest và
  audit; không UPDATE hàng loạt thủ công không truy vết.

## 8. Rủi ro và biện pháp kiểm soát

| Rủi ro | Kiểm soát |
|---|---|
| Đếm thao tác đổi nhãn nhưng chưa gửi thật | Chốt contract `sent/datesend` của Core và ghi source riêng |
| Event chạy trước khi Version/Group tồn tại | Capture sau `handle_estimate_added`; reconciler tự heal |
| Hai request gửi đồng thời | Conditional update/transaction và concurrency test |
| Timestamp bị đẩy về sau | Chỉ nhận candidate sớm hơn và test idempotency |
| Backfill Draft-only | Query bắt buộc sent evidence; test negative |
| Leaderboard và Drawer lệch nhau | Cùng canonical repository và end-to-end assertion |
| Dashboard chậm | Batch aggregate, index `(owner_staff_id, first_sent_at)`, `EXPLAIN` |
| Ghi đè thay đổi đang làm dở | Baseline dirty worktree và diff ownership trước edit |

## 9. Tiêu chí hoàn tất cuối cùng

Hotfix chỉ được coi là hoàn tất khi tất cả điều kiện sau đạt:

- Repro `2 Draft + 1 Sent` trả Quote Count `1` ở DB canonical.
- Leaderboard **Số báo giá** hiển thị `1`.
- Drawer **Tổng báo giá** hiển thị `1` cho cùng staff/kỳ.
- Draft-only, revision và period boundary đúng invariant.
- Mọi sent path được capture trực tiếp hoặc self-heal có test.
- Backfill idempotent, có source và không thay dữ liệu tài chính/outcome.
- Full module tests, PHP syntax, diff check và DB/UI verification đều đạt.
- Implementation report ghi rõ migration đã chạy ở môi trường nào; không dùng
  việc file tồn tại để suy ra production đã migrate.

