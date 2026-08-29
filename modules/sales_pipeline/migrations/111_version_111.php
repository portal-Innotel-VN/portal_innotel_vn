<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Additive Reminder Bell Inbox acknowledgement schema and options. */
class Migration_Version_111 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        require_once module_dir_path('sales_pipeline', 'includes/reminder_repository_schema.php');
        sales_pipeline_ensure_reminder_repository_schema($CI);

        require_once module_dir_path('sales_pipeline', 'includes/reminder_rule_defaults.php');
        sales_pipeline_add_reminder_options();

        // Fresh installations/upgrades remain canary-safe, while an explicit
        // pre-existing localhost/canary choice is preserved.
        if (get_option('sp_reminder_crm_inbox_enabled') === false) {
            add_option('sp_reminder_crm_inbox_enabled', '0');
        }
    }

    public function down()
    {
        // Non-destructive rollback: preserve reminder repository and audit data.
    }
}
