<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Safe, administrator-configurable controls for the fixed Reminder rules.
 * Rule expressions, recipients and response behaviour remain code-owned.
 */
function sales_pipeline_reminder_rule_default_options()
{
    return [
        'sp_reminder_global_enabled'          => '1',
        'sp_reminder_skip_weekends'           => '1',
        'sp_reminder_holiday_dates'           => '',
        'sp_reminder_quiet_hours_start'       => '',
        'sp_reminder_quiet_hours_end'         => '',

        'sp_reminder_deal_frequency_enabled'  => '1',
        'sp_reminder_deal_frequency_channels' => 'crm,email',

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
    ];
}

function sales_pipeline_seed_reminder_rule_options()
{
    foreach (sales_pipeline_reminder_rule_default_options() as $name => $value) {
        add_option($name, $value);
    }
}
