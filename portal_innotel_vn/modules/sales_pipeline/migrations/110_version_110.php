<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Additive Reminder delivery resilience schema. */
class Migration_Version_110 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        require_once module_dir_path('sales_pipeline', 'includes/reminder_repository_schema.php');
        sales_pipeline_ensure_reminder_repository_schema($CI);

        require_once module_dir_path('sales_pipeline', 'includes/reminder_rule_defaults.php');
        sales_pipeline_add_reminder_options();
    }

    public function down()
    {
        // Non-destructive rollback: preserve delivery audit, quota, and incident data.
    }
}
