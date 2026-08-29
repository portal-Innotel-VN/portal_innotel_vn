# Code Map — Sales Pipeline

| Khu vực | File chính |
|---|---|
| Hooks/bootstrap/permissions/assets | `modules/sales_pipeline/sales_pipeline.php` |
| HTTP endpoints | `modules/sales_pipeline/controllers/Sales_pipeline.php` |
| Deal, dashboard, KPI, Group queries | `modules/sales_pipeline/models/Sales_pipeline_model.php` |
| Revision, link/unlink, Deal sync | `modules/sales_pipeline/libraries/Estimate_revision_service.php` |
| Performance Score | `modules/sales_pipeline/libraries/Performance_score_calculator.php` |
| Reminder Rule/Delivery Engine | `modules/sales_pipeline/libraries/Reminder_engine.php` |
| Reminder Repository schema | `modules/sales_pipeline/includes/reminder_repository_schema.php` |
| Reminder Bell feed/acknowledge | `modules/sales_pipeline/models/Sales_pipeline_model.php`, `modules/sales_pipeline/controllers/Sales_pipeline.php` |
| Reminder Bell UI | `modules/sales_pipeline/assets/js/reminder_bell.js`, `modules/sales_pipeline/assets/css/reminder_bell.css` |
| Reminder Bell migration | `modules/sales_pipeline/migrations/111_version_111.php` |
| Deal Reminder evaluator | `modules/sales_pipeline/libraries/Deal_reminder_rule_evaluator.php` |
| Group schema | `modules/sales_pipeline/includes/estimate_group_schema.php` |
| Performance defaults | `modules/sales_pipeline/includes/performance_score_defaults.php` |
| Migration Deal Bridge | `modules/sales_pipeline/migrations/109_version_109.php` |
| Estimate intent UI | `modules/sales_pipeline/assets/js/estimate_revision.js` |
| Version History UI | `modules/sales_pipeline/assets/js/estimate_version_history.js` |
| Dashboard leaderboard | `modules/sales_pipeline/views/partials/_estimates_leaderboard.php` |
| Tests | `modules/sales_pipeline/tests/` |

Tìm symbol bằng `rg` trước khi mở file lớn. Không suy luận route/hook chỉ từ tên tài liệu.
