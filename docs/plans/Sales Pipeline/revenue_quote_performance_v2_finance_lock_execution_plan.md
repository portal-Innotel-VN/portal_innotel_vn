# Kế hoạch thực thi kiến trúc Doanh thu, Quote Count, Performance V2 và Finance Lock

## 1. Mục tiêu và điều kiện sử dụng

Tài liệu này là execution plan để giao cho AI agent triển khai theo TDD. Agent
phải hoàn thành tuần tự các phase, lưu bằng chứng sau mỗi gate và không đánh dấu
hoàn tất khi mới có source/contract test mà chưa có DB/UI evidence tương ứng.

Kết quả đích:

1. Một nguồn Quote Count duy nhất dựa trên Estimate Group và `first_sent_at`.
2. Performance V1 được đóng băng; V2 có cutover, confidence weighting và snapshot.
3. Tỷ giá chỉ đến từ nguồn được Finance duyệt, có ngày hiệu lực và audit.
4. Finance lock chặn nhất quán mọi write-path liên quan.
5. Sanitizer chạy bằng manifest, dry-run mặc định, idempotent và đối chiếu được.
6. Dashboard Báo giá hiển thị cùng một metric với leaderboard/performance detail.

Không mở rộng phạm vi sang dòng tiền kế toán, số dư ngân hàng, công nợ, payment
hoặc cash outflow. Hai vấn đề `closed_at`/Win Rate và phân tách pipeline profit với
realized profit tiếp tục là scope riêng; không tuyên bố full Go-Live cho hai vùng
này trong task này.

## 2. Nguồn sự thật và invariant

### 2.1. Context bắt buộc

Trước Phase 0, đọc theo `.agents/context/context-loading.md`, tối thiểu gồm:

- `docs/ai/PROJECT_CONTEXT.md`
- `docs/ai/BUSINESS_INVARIANTS.md`
- `docs/ai/IMPLEMENTATION_STATUS.md`
- `docs/ai/CODE_MAP.md`
- `docs/ai/SALES_PIPELINE_CONTEXT.md`
- `docs/specifications/PERFORMANCE_SCORE.md`
- `docs/specifications/DOMAIN_MODEL.md`
- `docs/specifications/AUDIT_SECURITY_RULES.md`
- `docs/specifications/DATA_DICTIONARY.md`
- `docs/specifications/INTEGRATION_CONTRACTS.md`
- `docs/specifications/DEAL_BRIDGE.md`
- `docs/plans/Sales Pipeline/audit_Cash.md`
- `docs/plans/Sales Pipeline/cashflow_integrity_implementation_plan.md`
- `docs/plans/Sales Pipeline/rule_engine.md`

Tài liệu kế hoạch không thay thế Basecode. Khi khác nhau, ghi lại runtime hiện tại
từ code và dùng các quyết định mục 2.3 làm target cần triển khai.

### 2.2. Invariant không được phá vỡ

- Một Estimate Group là một Báo giá logic; revision không tăng Quote Count.
- Group owner sở hữu sản lượng; decision owner sở hữu Accepted Revenue.
- Không link Estimate khác customer; heuristic không tự merge.
- Deal là aggregate độc lập; xóa/unlink Deal không xóa lịch sử Group/Version.
- Finance/manual lock phải fail closed; không âm thầm ghi một phần dữ liệu.
- Rank được tính trên full cohort trước role projection.
- Dữ liệu suy diễn phải có source; không trình bày reconstructed như finalized.
- Migration đã phát hành không được sửa; mọi schema mới đi qua migration 113+.

### 2.3. Quyết định nghiệp vụ dùng cho implementation

| Hạng mục | Contract đã chốt |
|---|---|
| Phạm vi | Doanh thu & Lợi nhuận Pipeline, không phải cash flow kế toán |
| Quote Count | Đếm Group có ít nhất một Estimate liên kết qua Version với status `2,3,4,5`; draft-only không tính |
| Event date Quote | Lần gửi thành công đầu tiên của bất kỳ Version nào trong Group |
| Target tháng | 30 Báo giá/người/tháng |
| V1 legacy | Quote 20 + Revenue 40 + Acceptance 25; mẫu số cố định 85; không Reminder |
| V2 | Bổ sung Reminder 15; mẫu số 100 khi có reminder eligible, ngược lại 85; có confidence weighting |
| Cutover | Theo quy tắc ở Phase 5, timezone `Asia/Ho_Chi_Minh` |
| Ngoại tệ | `1 source = N base`; rate phải được duyệt; thiếu rate trả `NULL` |
| Deal Bridge | Pending dùng Version `base_total`; Accepted thiếu một value thì chặn cả sync |
| Sanitization | Chỉ apply ID/rate trong manifest Finance-approved; dry-run là mặc định |

## 3. Baseline và bảo vệ worktree

### Phase 0 — Inventory và baseline evidence

Thực hiện:

1. Chạy `git status --short`, `git diff --check` và đọc diff từng file đang sửa.
2. Bảo toàn các seam/test chưa commit hiện có:
   - `Quote_currency_resolver.php`
   - `Deal_bridge_calculator.php`
   - `Estimate_group_reconciliation_policy.php`
   - `Reminder_response_guard.php`
3. Xác minh module hiện ở `1.0.12`, migration cuối là 112 và ghi lại các test
   đang hardcode `Version: 1.0.12`.
4. Chạy toàn bộ `modules/sales_pipeline/tests/*.php`; lưu danh sách pass/fail,
   không chỉ lưu số lượng.
5. Chạy SQL preflight read-only hiện có và lưu aggregate, không xuất PII.
6. Lập impact map: hook → service/model → query → view → test → schema.

Guardrail:

- Mở rộng seam hiện có khi cần tích hợp; chỉ viết lại nếu test chứng minh contract
  hiện tại sai và báo cáo lý do.
- Không chạy migration/apply sanitizer trên production trong Phase 0.
- Không sửa hoặc xóa thay đổi không thuộc task.

Hoàn tất khi có baseline report gồm: files đã đọc, invariant, test result, schema
version, dirty files, DB preflight và toàn bộ khoảng trống chưa xác minh.

## 4. Đóng contract bằng test trước production code

### Phase 1 — Test matrix Red

Viết test qua public seam; mọi regression mới phải đỏ vì hành vi thiếu, không đỏ
do syntax hoặc fixture hỏng.

#### 4.1. Migration/schema

- Migration 113 tạo đủ table/column/index trên schema trống.
- Chạy migration lần hai không lỗi và không nhân dữ liệu.
- Module header lên `1.0.13`; test 1.0.12 liên quan được cập nhật có chủ đích.
- Migration down không drop financial/audit history.

#### 4.2. Quote Count

- Group có ba revision chỉ tính một.
- Draft-only không tính.
- Có một revision status `2/3/4/5` thì Group tính một lần.
- Group tạo kỳ trước nhưng gửi lần đầu kỳ này được tính kỳ này.
- Revision gửi kỳ này của Group đã có `first_sent_at` kỳ trước không tăng count.
- Boundary dùng `[period_start, period_end_exclusive)`.
- Executive, Performance, Estimate Dashboard và Reminder Rule trả cùng count.
- Staff/customer permission và full-cohort projection không thay đổi.

#### 4.3. Performance

- Golden fixtures V1 luôn tái tạo 3 component/mẫu số 85, không đọc Reminder.
- V2 có eligible reminder dùng mẫu số 100; 0/null dùng 85.
- Acceptance confidence và Reminder confidence áp dụng trước cap.
- Component nội bộ round 4 số; total/rank round 1 số.
- Cutover đúng month/week/quarter/year.
- Đồng hạng giữ standard competition ranking `1,2,2,4`.
- Đổi option hiện tại không thay finalized/reconstructed historical snapshot.
- Snapshot toàn cohort dùng một `snapshot_run_id` và transaction atomic.
- Finalized snapshot không bị overwrite bởi request/dashboard refresh.

#### 4.4. Currency/Deal/Reconciliation

- Base currency rate `1`; foreign approved rate đúng; unapproved/missing rate `NULL`.
- Rate unit chính xác; rate lookup lấy approved rate gần nhất không sau target date.
- Approved rate immutable; correction tạo version mới và giữ audit chain.
- Làm tròn base total hai chữ số.
- Pending Deal dùng Version `base_total`; thiếu value giữ nguyên Deal và trả lỗi rõ.
- Một Accepted Group thiếu value chặn value và Won status.
- Accepted values cộng decimal/integer cents; overflow `DECIMAL(15,2)` fail rõ.
- Manual lock hoặc Finance lock giữ nguyên dữ liệu.
- Reconciliation heal null value khi có approved rate; lần hai no-op/no duplicate audit.
- Non-null/Finance-locked decision snapshot không bị tự động revalue.

#### 4.5. Sanitizer/Reminder/UI

- Dry-run không phát sinh `INSERT/UPDATE/DELETE`.
- Manifest sai checksum/rate unit/ID/approval bị từ chối.
- Apply ngoài manifest hoặc lên locked record bị từ chối.
- Optimistic before-value mismatch fail, không partial apply.
- Apply lần hai trả `already_applied`, không nhân audit.
- Row count và tổng tiền trước/sau được đối chiếu theo currency/batch.
- Reminder `legacy_unverified` bị loại khỏi SLA denominator.
- Drawer Báo giá dùng `estimate_count`/`accepted_revenue`; Deal tab không đổi.

Hoàn tất khi từng ca có tên test, public seam, fixture, expected result và bằng
chứng Red. Chưa sửa production code trước khi Red tương ứng tồn tại.

## 5. Migration 113 và schema

### Phase 2 — Additive schema

Tạo `modules/sales_pipeline/migrations/113_version_113.php`; dùng `db_prefix()`,
`table_exists()`/`field_exists()`/index guard và không sửa migration 112.

#### 5.1. Performance snapshot

Tạo `sales_pipeline_score_snapshots`:

```text
id
snapshot_run_id varchar(64)
period_type varchar(32)
period_start date
period_end date
staff_id int
formula_version varchar(32)
config_snapshot_json text
raw_metrics_json text
component_scores_json text
data_quality_flags_json text
performance_score decimal(5,1)
rank int
total_ranked_staff int
is_provisional tinyint
snapshot_status provisional|reconstructed|finalized
calculated_at datetime
finalized_at datetime null
```

Unique business key:

```text
(period_type, period_start, period_end, staff_id, formula_version)
```

Index `snapshot_run_id`, period/status và staff. Mọi row cùng cohort phải có cùng
run ID và config checksum. Finalized rows là immutable. Provisional rows chỉ được
thay thế trong kỳ đang mở. Reconstructed rows chỉ được thay bằng approved historical
manifest hoặc finalization có audit.

#### 5.2. First sent event

Thêm vào `sales_pipeline_estimate_groups`:

```text
first_sent_at datetime null
first_sent_source varchar(32) null
first_sent_estimate_id int null
```

Index chính: `(owner_staff_id, first_sent_at)`.

Backfill:

1. Lấy `MIN(tblsales_activity.date)` của activity
   `invoice_estimate_activity_sent_to_client` trên toàn bộ Estimates thuộc Group.
2. Nếu không có activity nhưng có Estimate status `2,3,4,5`, dùng
   `MIN(tblestimates.date)` và source `inferred_estimate_date`.
3. Không có bằng chứng thì giữ `first_sent_at = NULL`, source `unknown`; Group không
   được tính cho đến khi có evidence/approved remediation.

Future capture dùng hook `estimate_sent` sau khi gửi thành công. Dùng timestamp sự
kiện thực tế `$successfulSentAt`, không dùng `NOW()` làm thay thế ngầm:

```text
first_sent_at = min(existing first_sent_at, successfulSentAt)
first_sent_source = activity khi activity là evidence sớm nhất
first_sent_estimate_id = Estimate tạo ra evidence sớm nhất
```

Cập nhật ba field trong một transaction/conditional update và xử lý hai send đồng thời.

#### 5.3. Authoritative exchange rate

Tạo `sales_pipeline_exchange_rates`:

```text
id
source_currency_id int
base_currency_id int
effective_date date
exchange_rate_to_base decimal(20,8)
rate_unit varchar(64)
source varchar(64)
rate_version int
supersedes_rate_id int null
is_active tinyint
approved_by int null
approved_at datetime null
approval_reference varchar(191) null
created_at datetime
```

Unique `(source_currency_id, base_currency_id, effective_date, rate_version)`;
index lookup `(source_currency_id, base_currency_id, effective_date, is_active)`.
Rate đã duyệt là immutable. Sửa một rate phải insert version mới, trỏ
`supersedes_rate_id` và chuyển active version trong transaction có audit; không
UPDATE giá trị của approved row. Resolver chỉ đọc active row có
`approved_at IS NOT NULL`, rate dương và
`rate_unit = base_currency_per_source_currency`. Chọn `MAX(effective_date)` với
`effective_date <= target_date`. Base currency luôn trả rate `1`, không cần DB row.

Rate-date contract cho task này:

- Version mới: ngày gửi thành công đầu tiên của chính Version.
- Group decision value: dùng immutable `base_total` của decision Version, không
  tự revalue tại `decision_at`.
- Sanitizer: dùng đúng `rate_date`/rate trong approved manifest.
- Version chưa gửi hoặc không tìm được approved rate giữ rate/base total `NULL`.

#### 5.4. Finance lock

Thêm vào Group và Deal:

```text
is_finance_locked tinyint default 0
finance_locked_at datetime null
finance_locked_by int null
finance_approval_reference varchar(191) null
finance_lock_reason text null
```

Thêm permission Finance Lock. Lock/unlock đi qua service/POST endpoint có CSRF,
permission, reason/reference và immutable audit event. Finance lock ưu tiên manual
lock: mở manual lock không được bypass Finance lock. Override Finance lock là hành
động riêng, chỉ Finance/Admin được phép và phải ghi before/after.

#### 5.5. Sanitization storage

Tạo:

- `sales_pipeline_sanitization_batches`: UUID unique, manifest checksum, manifest
  JSON/path reference, approver/reference, totals before/after, status, timestamps.
- `sales_pipeline_sanitization_items`: batch ID, entity type/ID, approved rate date,
  approved rate, expected before values, before/after JSON, status và error.

Unique `(batch_id, entity_type, entity_id)`. Status phải phân biệt
`pending`, `validated`, `applied`, `already_applied`, `failed`. Dry-run không tạo
batch/item row; report dry-run là artifact ngoài DB.

#### 5.6. Reminder quality

Thêm `data_quality_status varchar(32)`, mặc định cho record mới là `verified`.
Backfill chỉ đánh dấu `legacy_unverified` cho row có response nhưng thiếu `sent_at`
hoặc `response_due_at`; ghi aggregate/audit migration. Không tạo deadline hồi tố.

#### 5.7. Target migration

Điều chỉnh option có bảo toàn Admin override:

```text
month: 20 -> 30 chỉ khi current là 20 hoặc option thiếu
quarter: 60 -> 90 chỉ khi current là 60 hoặc option thiếu
year: 240 -> 360 chỉ khi current là 240 hoặc option thiếu
week: giữ nguyên current/default cho đến khi có policy riêng
```

Cập nhật defaults mới và ghi audit các option bị migration đổi. Target V1
reconstructed luôn dùng legacy defaults `5/20/60/240`, không đọc option hiện tại.

Hoàn tất Phase 2 khi migration test Red→Green, chạy hai lần an toàn trên DB test,
schema inspection đúng và không có dữ liệu tài chính bị revalue trong migration.

## 6. Canonical Quote Count

### Phase 3 — Repository và event capture

Tạo `Quote_count_repository.php` với hai public seam:

```text
get_counts_by_staff(staffIds, periodStart, periodEndExclusive)
get_staff_quote_count(staffId, periodStart, periodEndExclusive)
```

Query phải bắt đầu từ Group, lọc owner và `first_sent_at`, dùng `EXISTS` qua Version
→ Estimate status `IN (2,3,4,5)`. Không join rồi `COUNT(version.id)`.

Refactor các consumer:

- `get_staff_kpi_metrics()`
- `get_estimate_performance_ranking()`
- `get_estimate_dashboard_metrics()`
- Rule Engine daily/monthly count

Batch API dùng cho cohort để tránh N+1. Executive, Performance, Dashboard và
Reminder Rule không giữ query đếm riêng sau refactor.

Hoàn tất khi test matrix Quote Count xanh và SQL fixture chứng minh revision khác
kỳ không tăng count.

## 7. Performance versioning và snapshot lifecycle

### Phase 4 — V1/V2 calculators

Tạo:

- `Performance_score_calculator_v1.php`: legacy 3 components, denominator 85.
- `Performance_score_calculator_v2.php`: 4 components, dynamic denominator.
- `Performance_score_dispatcher.php`: chỉ chọn implementation, không tính metric.

Giữ `Performance_score_calculator.php` làm compatibility facade cho V1; mọi consumer
nội bộ mới phải dùng dispatcher. Không để config ghi nhãn V2 trong khi chạy V1.

V2 confidence:

```text
confidence_acc = min(1, closed_count / min_closed_quotes)
acceptance_score = CLAMP(raw_acceptance_score * confidence_acc, 0, component_cap)

confidence_rem = min(1, eligible_reminders / 3), khi eligible_reminders > 0
response_score = CLAMP(raw_response_score * confidence_rem, 0, component_cap)
```

Giữ quality flags và `is_provisional`. Round component 4 chữ số; total/ranking
1 chữ số. Ranking full cohort rồi mới projection.

### Phase 5 — Dispatcher cutover

Theo `period_start`, timezone `Asia/Ho_Chi_Minh`:

| Period | V1 | V2 |
|---|---|---|
| Month | trước 2026-09 | từ 2026-09 |
| Week | 31/08–06/09/2026 và trước đó | từ tuần 07/09/2026 |
| Quarter | Q3/2026 và trước đó | Q4/2026 trở đi |
| Year | 2026 và trước đó | 2027 trở đi |

### Phase 6 — Snapshot service

Tạo `Performance_score_service.php`:

```text
calculate_runtime(period, calculatedAt)
read_snapshot(period, formulaVersion)
persist_provisional(period, cohortResult)
reconstruct_legacy_v1(period, approvedConfigOverride = null)
finalize_period(period, actor, reason)
```

Rules:

- Dashboard GET không tự ghi DB. Một job/command riêng persist/finalize.
- Finalize chỉ cho kỳ đã đóng và khóa toàn cohort trong một transaction.
- Tất cả row cùng run dùng một config snapshot/checksum/calculatedAt.
- Finalized snapshot immutable; finalize lần hai trả `already_finalized`.
- V1 reconstruction dùng legacy defaults, không dùng option hiện tại. Nếu có bằng
  chứng Admin override lịch sử, chỉ nhận approved manifest và giữ status
  `reconstructed` cho đến khi Finance/Admin xác nhận.
- Kỳ cũ chưa có snapshot trả runtime payload gắn `reconstructed`; không gắn nhãn
  finalized và không âm thầm persist từ GET.
- Current/open period được tính runtime; provisional persistence là job tùy chọn.

Hoàn tất khi historical target test, atomic cohort test, immutable finalized test
và cutover matrix đều xanh.

## 8. Currency provider, Finance Lock và Deal Bridge

### Phase 7 — Provider và hook

Tạo repository/service đọc approved rate và đăng ký hook
`sales_pipeline_quote_exchange_rate`. Hook nhận/validate:

```text
estimate_id
source_currency_id
base_currency_id
target_date
rate_unit
```

Không log secret/PII. Missing/unapproved/invalid rate trả `NULL` và data-quality
warning có code ổn định.

Tích hợp `estimate_sent` để capture Version snapshot theo sent timestamp. Không
được dùng normal reconcile như một currency backfill engine.

### Phase 8 — Finance lock enforcement

Guard tập trung phải được gọi từ:

- Version rate/base-total refresh
- Group decision estimate/value update
- reconciliation
- Deal Bridge
- sanitizer apply
- manual Group/Deal edit có ảnh hưởng financial field

Kết quả trả mã rõ như `finance_locked`, không silently succeed. Read-only dashboard,
history và audit vẫn hoạt động. Unlock/override có permission, CSRF và event audit.

### Phase 9 — Preserve Critical fixes

Xác minh và tích hợp các seam hiện có:

- Currency thiếu rate không fallback `1`.
- Pending Deal đọc Version `base_total`.
- Accepted thiếu bất kỳ value nào không cập nhật value/status.
- Accepted values cộng bằng integer cents/decimal, không PHP float.
- Overflow cột Deal fail trước DB update.
- Reconciliation chỉ self-heal null từ approved snapshot; chạy lại no-op.
- Reminder chưa delivery không submit được ở BE/FE.

Hoàn tất khi test Currency/Deal/Reconciliation/Finance Lock xanh và không làm giảm
coverage của các Critical fix hiện có.

## 9. Sanitization và remediation

### Phase 10 — Sanitizer service

Tạo `Currency_data_sanitizer.php`:

```text
scan(filters)
validate_manifest(manifest)
dry_run(manifest)
apply_batch(manifest, actor)
reconcile_approved_entities(batchUuid)
```

Manifest tối thiểu:

```text
batch_uuid
estimate_version_id
estimate_group_id
estimate_id
source_currency_id
base_currency_id
rate_date
exchange_rate_id
exchange_rate_to_base
rate_unit
expected_before_rate
expected_before_base_total
approved_by
approval_reference
```

Apply flow:

1. Verify checksum, approver, exact IDs, currency pair, approved rate và Finance lock.
2. Dry-run tính after state, row count và totals; không ghi DB.
3. Apply bằng transaction và optimistic before-value predicates.
4. Cập nhật đúng Version IDs trong manifest; không quét wildcard/toàn bảng.
5. Self-heal Group sau snapshot; Deal Bridge chỉ chạy cho Deal đã được cho phép và
   không tự đổi Won/Lost nếu manifest/approval không cho phép.
6. Lưu before/after per item và totals per batch.
7. Re-run cùng UUID/checksum trả `already_applied`.

Tạo thin CLI/maintenance entry point; apply mặc định bị vô hiệu trên production
trừ khi có explicit Finance approval reference và environment guard. Không in DB
credential hay dữ liệu khách hàng ra log.

### Phase 11 — Reminder remediation

- Quét mọi response thiếu delivery/SLA evidence, không hardcode số 17.
- Gắn `legacy_unverified`; không bịa sent/due/responded time.
- Loại khỏi `eligible_reminders` và `on_time_reminders`.
- Giữ nguyên nội dung response và ghi aggregate remediation audit.

Hoàn tất khi dry-run/apply/idempotency/out-of-manifest/locked-record/totals tests xanh.

## 10. Dashboard và UI consistency

### Phase 12 — Data binding

Trong `_dashboard_staff_pipeline.php`:

- Estimates tab: total quote = `performance_metric.estimate_count`.
- Estimates tab: total revenue = `performance_metric.accepted_revenue`.
- Không dùng Deal `metric.period_revenue` trong Estimates branch.
- Deal tab giữ nguyên Deal metrics.
- Provisional/reconstructed/finalized status có text EN/VI và accessible status.

Không dùng UI làm lớp permission. Payload Staff tiếp tục chỉ chứa row đã projection.

Hoàn tất khi render/contract tests xanh và Computer Use xác minh leaderboard,
drawer header và score detail cùng quote count/revenue.

## 11. Documentation sync

### Phase 13 — Cập nhật source of truth sau bằng chứng

Cập nhật:

- `rule_engine.md`: canonical repository + `first_sent_at`.
- `PERFORMANCE_SCORE.md`: V1/V2, cutover, confidence, snapshot lifecycle.
- `DEAL_BRIDGE.md`: base currency, missing-rate/lock/overflow behavior.
- `DATA_DICTIONARY.md`: schema 113.
- `INTEGRATION_CONTRACTS.md`: `estimate_sent`, Finance endpoints và maintenance jobs.
- `audit_Cash.md` và implementation plan: trạng thái/bằng chứng thực tế.
- `docs/ai/IMPLEMENTATION_STATUS.md`, `CODE_MAP.md`, `SALES_PIPELINE_CONTEXT.md`.

Chỉ đổi một hạng mục sang `implemented` khi code, migration test và evidence tương
ứng đã đạt. Dùng `blocked`/`partial` cho phần chưa có DB/UI/staging evidence.

## 12. Verification, rollout và rollback

### Phase 14 — Automated verification

1. Chạy toàn bộ PHP tests; báo tên từng failure.
2. `php -l` mọi PHP file đổi/mới.
3. `git diff --check`.
4. DB-backed migration 113 hai lần trên database disposable.
5. Transaction/concurrency tests cho snapshot finalization, first-send capture,
   Finance lock và sanitizer.
6. SQL `EXPLAIN` cho canonical count, rate lookup, snapshot read và SLA query.
7. So sánh before/after counts/totals; record ngoài manifest phải giữ nguyên.

### Phase 15 — Staging browser verification

Dùng Computer Use trên staging/local với dữ liệu fixture:

- Estimates leaderboard và drawer cùng count/revenue.
- V1 historical, V2 current và badge reconstructed/provisional/finalized.
- Finance lock chặn edit/sync và hiển thị lý do phù hợp.
- Reminder pre-delivery không có submit; delivered reminder cho phép submit.
- Desktop và viewport mobile; không lỗi JS/overflow.

Không submit hoặc mutate production qua browser trong verification.

### Phase 16 — Rollout có kiểm soát

Thứ tự:

1. Deploy additive schema/code với V2/provider/sanitizer feature flags tắt.
2. Backfill `first_sent_at`; review inferred/unknown counts.
3. Import và Finance-approve exchange rates.
4. Chạy Quote Count comparison cũ/mới; sign-off Sales Ops.
5. Reconstruct V1 historical snapshots và review status `reconstructed`.
6. Enable canonical count.
7. Enable V2 theo cutover.
8. Chạy sanitizer dry-run; Finance sign-off totals/IDs.
9. Apply trên staging, chạy lần hai xác minh idempotency.
10. Chỉ sau staging evidence mới cân nhắc production apply.

Rollback/disable:

- Tắt feature flags để ngừng V2/provider/sanitizer jobs; không drop schema/audit.
- Finalized snapshot và sanitizer audit không bị xóa khi rollback code.
- Không hoàn tác financial data bằng bulk SQL; tạo approved compensating batch.
- Khôi phục UI read path qua feature flag, không sửa migration cũ.

## 13. Definition of Done cuối cùng

Task chỉ hoàn tất khi:

- Tất cả test matrix Phase 1 Red→Green có evidence.
- 100% module tests và PHP lint pass; `git diff --check` sạch.
- Migration 113 idempotent trên disposable DB và schema inspection đúng.
- Canonical count nhất quán ở bốn consumer, không N+1.
- V1 golden fixtures bất biến; V2/cutover/confidence đúng.
- Historical snapshot không đổi khi current options đổi.
- Finalize full cohort atomic; finalized rows immutable.
- Approved-rate-only, rate unit/date/missing-rate tests pass.
- Finance lock chặn mọi write-path và unlock/override có audit.
- Sanitizer dry-run zero writes; apply idempotent; outside-manifest/locked rows giữ nguyên.
- Row count/totals before-after được Finance xác nhận.
- Reminder legacy rows không làm sai SLA.
- Computer Use xác minh Estimates/Deal drawer và trạng thái score/lock/reminder.
- Docs/AI status phản ánh đúng bằng chứng, không ghi `implemented` sớm.
- Có release note, feature-flag rollback và danh sách blocker còn lại.

## 14. Báo cáo bàn giao bắt buộc của Agent

Agent trả về:

1. Tóm tắt outcome theo từng Phase và gate pass/fail.
2. Danh sách file/migration/schema thay đổi.
3. Công thức V1/V2 và cutover thực tế đã triển khai.
4. SQL before/after và sanitizer batch UUID/checksum (không PII).
5. Kết quả từng test suite, lint, DB-backed, concurrency và Computer Use.
6. Bằng chứng Finance/Sales Ops approval hoặc trạng thái đang chờ.
7. Rollback/disable procedure đã xác minh.
8. Các mục chưa hoàn tất; không dùng câu “100% hoàn thành” khi còn blocker.
