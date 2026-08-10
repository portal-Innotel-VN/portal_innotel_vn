# Sales Pipeline Dashboard Leaderboard Period Filter v5

Status: QC: PASS

Source received from user: `/Users/dieterhoang/Downloads/implementation_plan5.md`

## Scope

Implement the v5.2 plan for the Sales Pipeline dashboard leaderboard period filter and stabilize Sales Pipeline manage-page filtering/pagination regressions observed after the update.

## Required Changes

- Add shared helper `sales_pipeline_compact_money()`.
- Load the helper for the `sales_pipeline` module.
- Render dashboard leaderboard through a stable wrapper and partial.
- Add AJAX endpoint `admin/sales_pipeline/ajax_dashboard_leaderboard`.
- Add delegated dashboard period filter behavior with abort handling and loading cleanup.
- Add period metrics for quotes, deals, win rate, and revenue while preserving legacy summary-card metrics.
- Keep staff drawer behavior delegated and working after AJAX replacement.
- Fix manage page pagination so invalid/out-of-range pages clamp to the nearest valid page.
- Fix manage page summary so document filters are included consistently with the table data.
- Update Vietnamese vocabulary:
  - `Báo giá (Kỳ)` -> `Báo giá`
  - `Cơ hội (Kỳ)` -> `Cơ hội (Deal)`

## Acceptance Criteria

- Dashboard period filter supports `this_week`, `this_month`, `this_quarter`, and `this_year`.
- Invalid dashboard period falls back to `this_month`.
- AJAX leaderboard response returns JSON with `data.html`.
- Non-AJAX leaderboard endpoint returns 404.
- Unauthorized leaderboard endpoint returns JSON 403.
- Dashboard wrapper never nests duplicate leaderboard sections after repeated filter changes.
- Staff drawer still opens after leaderboard AJAX replacement.
- Manage-page filter and pagination return non-empty clamped results when records exist.
- Manage-page summary matches the same filters used by the table.
- PHP syntax checks pass for all changed PHP files.
- `git diff --check` passes.
