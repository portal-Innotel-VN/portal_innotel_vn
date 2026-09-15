<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Migration 115: Reminder Manager Recipient Roles & Policy V2 Options.
 *
 * Seeds configuration options for decoupling data access from notification recipients:
 * - sp_reminder_manager_recipient_source: 'explicit_view' (default) or 'selected_staff'
 * - sp_reminder_manager_recipient_staff_ids: JSON array of designated staff IDs
 * - sp_reminder_recipient_policy_version: '2.0'
 * - sp_reminder_recipient_policy_v2_enabled: '0' (canary-safe default)
 */
class Migration_Version_115 extends App_module_migration
{
    public function up()
    {
        $options = [
            'sp_reminder_manager_recipient_source'    => 'explicit_view',
            'sp_reminder_manager_recipient_staff_ids' => '[]',
            'sp_reminder_recipient_policy_version'    => '2.0',
            'sp_reminder_recipient_policy_v2_enabled' => '0',
        ];

        foreach ($options as $name => $defaultValue) {
            add_option($name, $defaultValue);
        }
    }

    /**
     * Non-destructive rollback:
     * Preserve administrator configuration and audit trail.
     */
    public function down()
    {
        // Intentionally non-destructive.
    }
}
