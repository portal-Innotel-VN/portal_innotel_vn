# QC Review: sales-pipeline-staff-drawer-readonly-panels

## Metadata

- plan_id: `sales-pipeline-staff-drawer-readonly-panels`
- plan_version: `1`
- qc_reviewer: `Gemini 3.6 Flash (High)`
- qc_date: `2026-08-08`
- status: `PASS`

## Intent Restatement & Plan Comparison

1. **Header & Panel Layout Ordering**: Retain the current staff profile header and exactly three KPI cards in `_dashboard_staff_pipeline.php`, followed by exactly two read-only panels in fixed order: Panel 1 ("Cơ hội kinh doanh đang mở") and Panel 2 ("Nhật ký hoạt động"). Completely remove all mini Kanban columns, deal cards, and old drawer feeds after them.
2. **Panel 1 (Open Sales Opportunities)**: Query open deals for the target `staff_id` matching `is_won = 0 AND is_lost = 0`. Display a total count badge, maximum 10 recent rows sorted by `deal_date DESC, id DESC`, status badge (with hex color allowlist validation), customer name, formatted value, date (`_d`), and deal link strictly when `can_open_pipeline_deal` is true.
3. **Panel 2 (Activity Log - Reminder Response History)**: Define Panel 2 strictly as automated reminder-response history from `sales_pipeline_reminders_log` fetched via `get_reminder_response_stats(['staff_id' => $staff_id, 'limit' => 20])`. Display status badge (responded/pending), reminder message, staff response (if present), sent time (`_dt`), and response time (`_dt`). Never use `activity_log` or system activity logs.
4. **Permissions & Staff Scope Boundaries**: Strictly preserve existing Dashboard permission guards and staff scoping (`can_access_dashboard()`, `view_own` limited to `$staff_id === get_staff_user_id()`, `can_view_dashboard_all()` required for viewing other staff). Do not introduce new endpoints, permissions, or CRM write/delete operations.
5. **Engineering & Safety Constraints**: No Staff-core UI/hook modifications, no DB migrations/schema changes, no CRM data writes or external transfers, and no changes to leaderboard or KPI calculations. Ensure all dynamic output is escaped (`html_escape`), language-file driven (EN/VI), responsive CSS (preventing page/drawer horizontal overflow), and validated via static checks.

**Comparison Result**: The 5-bullet restatement matches the Plan (`docs/plans/sales-pipeline-staff-drawer-readonly-panels.md`) completely. No shift in meaning or scope detected.

## Basecode Inspection Matrix (Current State vs. Planned Changes)

| Component / File | Current Basecode State (Verified Empirical Fact) | Planned Change in Plan | Evaluation & Alignment |
|---|---|---|---|
| **Controller Route**<br>`Sales_pipeline.php` | L141-L185: Passes `'board'` from `get_staff_pipeline_board($staff_id)` and `'actionable_feed'` to `$view_data`. Endpoint has AJAX-only guard (`is_ajax_request()`), `can_access_dashboard()`, staff ID check, `view_own` scope check, and active staff check. | Remove `'board'`; call `get_staff_open_deals($staff_id, 10)` and pass `'open_deals'` + `'actionable_feed'`; preserve all security & scope guards. | **PASS**: Plan accurately targets endpoint L141-L185 and guard structure. |
| **Model Helpers**<br>`Sales_pipeline_model.php` | Contains `get_staff_open_deals($staff_id, 10)` (L1779-1833) querying `is_won = 0 AND is_lost = 0` with total count. Contains `get_reminder_response_stats` (L1733-1771). Contains unused `get_staff_pipeline_board` (L1658-1684) and unused `get_staff_recent_activity` (L1841-1858) querying `tblactivity_log`. | Retain `get_staff_open_deals` and `get_reminder_response_stats`. Remove `get_staff_pipeline_board` (after removing controller caller) and remove `get_staff_recent_activity` (wrong semantics). | **PASS**: Plan correctly identifies existing model functions and targets cleanup of unused/incorrect helpers. |
| **Drawer View**<br>`_dashboard_staff_pipeline.php` | L31-L77: Header and 3 KPI cards.<br>L79-L146: Renders `.sp-mini-kanban` board columns and deal cards.<br>L148-L224: Renders `.sp-drawer-feed` reminder responses. | Keep L31-L77 header & 3 KPIs intact. Replace L79-L224 with Panel 1 ("Cơ hội kinh doanh đang mở", max 10 rows, status, customer, value, date, link if permitted) followed by Panel 2 ("Nhật ký hoạt động", reminder responses). | **PASS**: Plan correctly targets replacing mini Kanban & old feed with the 2 required panels. |
| **CSS Styling**<br>`dashboard.css` | L1119-L1302: Contains `.sp-mini-kanban` and `.sp-mini-deal` CSS rules. L713-L892: Contains `.sp-drawer-feed` rules. | Remove unused `.sp-mini-kanban` / `.sp-mini-deal` CSS selectors. Add/update styles for Panel 1 table/badges and Panel 2 list. Ensure table wrapper `overflow-x: auto` on mobile. | **PASS**: Plan correctly identifies dead CSS rules to prune and mobile overflow rules to apply. |
| **Language Files**<br>`sales_pipeline_lang.php` (EN & VI) | VI L335: `sales_pipeline_staff_open_deals` ("Cơ hội kinh doanh đang mở").<br>VI L240: `sales_pipeline_dashboard_actionable_feed` ("Nhật ký hoạt động").<br>VI L336: `sales_pipeline_staff_recent_activity` ("Nhật ký hoạt động hệ thống gần nhất"). | Use `sales_pipeline_dashboard_actionable_feed` ("Nhật ký hoạt động") for Panel 2 header. Ensure no system activity wording is rendered for Panel 2. | **PASS**: Plan requires 100% language key driven rendering and correct Vietnamese title semantics. |

## Delta Findings & Technical Notes

### Finding 1 (Low Severity — Status Color Allowlist Hex Validation Regex)
- **Code Reference**: In `_dashboard_staff_pipeline.php`, status color validation must use the exact regex pattern:
  `preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $raw_color)`
  *(with standard unescaped alternation pipe `|` matching 3-digit or 6-digit hex color strings)*.
- **Plan Action**: Implementer must use this exact PHP regex check and fall back to safe default `#64748b` if invalid, preventing inline style injection.

### Finding 2 (Low Severity — Language Key Disambiguation for Panel 2 Header)
- **Code Reference**: `modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php` L240 vs L336.
- **Context**: Existing key `sales_pipeline_dashboard_actionable_feed` maps to `'Nhật ký hoạt động'`, whereas `sales_pipeline_staff_recent_activity` (L336) maps to `'Nhật ký hoạt động hệ thống gần nhất'`.
- **Plan Action**: The view implementation for Panel 2 header must call `_l('sales_pipeline_dashboard_actionable_feed')` to strictly display "Nhật ký hoạt động" without referring to system logs.

## Final Conclusion

`PASS` — The Plan (`docs/plans/sales-pipeline-staff-drawer-readonly-panels.md`) is completely sound, accurate, and aligned with basecode realities. Its proposed changes correctly transform current basecode state (mini Kanban + board helper + system activity helper) into the required target state (2 read-only panels + open deals helper + reminder stats helper) without violating permission boundaries, data safety rules, or core files.
