<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Safe, administrator-configurable controls for the fixed Reminder rules.
 * Rule expressions, recipients and response behaviour remain code-owned.
 */
function sales_pipeline_reminder_rule_default_options()
{
    return array_merge([
        'sp_reminder_global_enabled'          => '1',
        'sp_reminder_sla_hours'               => '24',
        'sp_reminder_skip_weekends'           => '1',
        'sp_reminder_holiday_dates'           => '',
        'sp_reminder_quiet_hours_start'       => '',
        'sp_reminder_quiet_hours_end'         => '',

        'sp_reminder_deal_pipeline_enabled'    => '1',
        'sp_reminder_deal_pipeline_channels'   => 'crm,email',
        'sp_reminder_deal_pipeline_min_count'  => '1',
        'sp_reminder_deal_pipeline_check_time' => '09:00',

        'sp_reminder_deal_stale_enabled'       => '1',
        'sp_reminder_deal_stale_channels'      => 'crm,email',
        'sp_reminder_deal_stale_cutoff_days'   => '30',
        'sp_reminder_deal_stale_max_per_run'   => '5',

        'sp_reminder_est_daily_enabled'       => '1',
        'sp_reminder_est_daily_channels'      => 'crm,email',
        'sp_reminder_est_daily_threshold'     => '1',
        'sp_reminder_est_daily_time'          => '15:00',

        'sp_reminder_est_monthly_enabled'     => '1',
        'sp_reminder_est_monthly_channels'    => 'crm,email',
        'sp_reminder_est_monthly_d10'         => '10',
        'sp_reminder_est_monthly_d20'         => '20',
        'sp_reminder_est_monthly_final'       => '30',
        'sp_reminder_est_monthly_time'        => '15:00',

        'sp_reminder_est_weekly_enabled'      => '1',
        'sp_reminder_est_weekly_channels'     => 'crm,email',
        'sp_reminder_est_weekly_target'       => '1000000000',
        'sp_reminder_est_weekly_midweek_time' => '16:30',
        'sp_reminder_est_weekly_final_time'   => '16:30',

        'sp_reminder_lc_draft_enabled'        => '1',
        'sp_reminder_lc_draft_channels'       => 'crm,email',
        'sp_reminder_lc_draft_days'           => '3',
        'sp_reminder_lc_sent_enabled'         => '1',
        'sp_reminder_lc_sent_channels'        => 'crm,email',
        'sp_reminder_lc_sent_days'            => '3',
        'sp_reminder_lc_sent_expiry_days'     => '2',
        'sp_reminder_lc_declined_enabled'     => '1',
        'sp_reminder_lc_declined_channels'    => 'crm,email',
        'sp_reminder_lc_declined_days'        => '7',
        'sp_reminder_lc_expired_enabled'      => '1',
        'sp_reminder_lc_expired_channels'     => 'crm,email',
        'sp_reminder_lc_accepted_enabled'     => '1',
        'sp_reminder_lc_accepted_channels'    => 'crm,email',

        'sp_reminder_email_cc_manager_enabled' => '1',
        'sp_reminder_email_cc_scope'           => 'all',
        'sp_reminder_manager_fallback_emails'  => '',

        // Canary-safe: Core Bell remains authoritative until an administrator
        // explicitly enables the module-owned Reminder Inbox.
        'sp_reminder_crm_inbox_enabled'        => '0',

        // WhatsApp Baileys Gateway options
        'sp_reminder_whatsapp_enabled'               => '0',
        'sp_reminder_whatsapp_endpoint'              => 'http://127.0.0.1:3050/api/v1/messages/send',
        'sp_reminder_whatsapp_secret_key'            => '',
        'sp_reminder_whatsapp_manager_mode'          => 'group_only',
        'sp_reminder_whatsapp_group_jid'             => '',
        'sp_reminder_whatsapp_base_url'              => '',
        'sp_reminder_whatsapp_timeout_seconds'       => '5',
        'sp_reminder_delivery_whatsapp_hourly_limit' => '60',
    ], sales_pipeline_reminder_delivery_default_options());
}

/** Canary-safe defaults for the Reminder delivery outbox. */
function sales_pipeline_reminder_delivery_default_options()
{
    return [
        'sp_reminder_delivery_email_batch_size'                 => '1',
        'sp_reminder_delivery_email_min_interval_ms'            => '30000',
        'sp_reminder_delivery_email_last_attempt_started_at'    => '',
        'sp_reminder_delivery_email_hourly_message_limit'       => '10',
        'sp_reminder_delivery_email_hourly_recipient_limit'     => '25',
        'sp_reminder_delivery_email_daily_message_limit'        => '50',
        'sp_reminder_delivery_email_daily_recipient_limit'      => '125',
        'sp_reminder_delivery_email_max_recipients_per_message' => '10',
        'sp_reminder_delivery_email_max_rendered_bytes'         => '1048576',
        'sp_reminder_delivery_max_attempts'                     => '5',
        'sp_reminder_delivery_rate_limit_cooldown_seconds'      => '3600',
        'sp_reminder_delivery_default_max_valid_age_hours'      => '24',
        'sp_reminder_delivery_retention_days'                   => '60',
        'sp_reminder_delivery_email_circuit_state'              => 'closed',
        'sp_reminder_delivery_email_circuit_opened_at'          => '',
        'sp_reminder_delivery_email_circuit_reason'             => '',
        'sp_reminder_delivery_bcc_incident_alerted_at'          => '',
        'sp_reminder_delivery_maintenance_last_run_at'          => '',
        'sp_reminder_delivery_maintenance_last_rows'            => '0',
        'sp_reminder_delivery_maintenance_last_duration_ms'     => '0',
    ];
}

/** Public delivery state contract shared by workers, UI, and tests. */
function sales_pipeline_reminder_delivery_statuses()
{
    return ['pending', 'processing', 'failed', 'sent', 'expired', 'cancelled'];
}

/** Register delivery options without overwriting administrator values. */
function sales_pipeline_add_reminder_options()
{
    foreach (sales_pipeline_reminder_delivery_default_options() as $name => $value) {
        add_option($name, $value);
    }
}

function sales_pipeline_seed_reminder_rule_options()
{
    $deliveryOptions = sales_pipeline_reminder_delivery_default_options();
    foreach (array_diff_key(sales_pipeline_reminder_rule_default_options(), $deliveryOptions) as $name => $value) {
        add_option($name, $value);
    }
    sales_pipeline_add_reminder_options();
}
