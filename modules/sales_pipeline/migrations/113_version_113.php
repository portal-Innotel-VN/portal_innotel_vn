<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Additive schema for Performance V2 snapshot, Authoritative Exchange Rate,
 * Finance Lock, First-Sent Quote Event, and Sanitization Audit Storage.
 */
class Migration_Version_113 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        require_once module_dir_path('sales_pipeline', 'includes/architecture_113_schema.php');

        // 1. Ensure additive tables and columns exist
        sales_pipeline_ensure_architecture_113_schema($CI);

        // 2. Run backfills for first_sent_at and legacy unverified reminders
        sales_pipeline_run_architecture_113_backfill($CI);

        // 3. Upgrade quote target options (preserving admin custom overrides)
        sales_pipeline_upgrade_target_options_113();
    }

    /**
     * Non-destructive rollback:
     * Snapshot and audit logs have financial and compliance value.
     * Do not drop tables or columns on rollback.
     */
    public function down()
    {
        // Intentionally non-destructive to preserve audit history and prevent data loss.
    }
}
