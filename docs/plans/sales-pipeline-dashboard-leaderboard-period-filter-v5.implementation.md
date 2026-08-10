# Sales Pipeline Dashboard Leaderboard Period Filter v5 Implementation

Implemented on: 2026-08-10

## Summary

- Added `sales_pipeline_compact_money()` helper and loaded it in the module/controller.
- Completed dashboard leaderboard period filtering with a stable partial wrapper and AJAX endpoint.
- Added delegated JavaScript handling for dashboard period changes with request aborting and loading cleanup.
- Added period metrics in `Sales_pipeline_model`: estimates, deals, won deals, win rate, and revenue.
- Fixed `/admin/sales_pipeline` manage-page pagination by clamping out-of-range pages.
- Fixed manage-page summary to include document filters so it matches filtered table data.
- Updated Vietnamese labels:
  - `Báo giá`
  - `Cơ hội (Deal)`

## Verification

- `php -l` passed for changed PHP files:
  - `modules/sales_pipeline/helpers/sales_pipeline_helper.php`
  - `modules/sales_pipeline/controllers/Sales_pipeline.php`
  - `modules/sales_pipeline/models/Sales_pipeline_model.php`
  - `modules/sales_pipeline/views/dashboard.php`
  - `modules/sales_pipeline/views/partials/_leaderboard.php`
  - Vietnamese and English language files
  - `modules/sales_pipeline/sales_pipeline.php`
- `node --check modules/sales_pipeline/assets/js/dashboard.js` passed.
- `git diff --check` passed for the touched files.
- DB read verification:
  - Requesting page 12 when only 7 pages exist now clamps to page 7.
  - Clamped page returned 9 rows.
  - Document-filtered summary matched filtered table count: 158 / 158.
- Local HTTP smoke:
  - `/admin/sales_pipeline` returned 307 to authentication when unauthenticated.
  - `dashboard.css` returned 200.
  - AJAX leaderboard endpoint returned 307 when unauthenticated, as expected without a logged-in session.

## Notes

Authenticated browser verification is still recommended for:

- Dropdown selected-period state after repeated AJAX changes.
- DOM wrapper not nesting `.sp-dashboard-leaderboard`.
- Staff drawer after AJAX replacement.
- Manage-page filter/pagination behavior in the signed-in UI.
