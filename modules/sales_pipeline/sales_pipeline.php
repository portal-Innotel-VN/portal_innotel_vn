<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Sales Pipeline
Description: Quản lý Tiến Độ Kinh Doanh - Số hóa quy trình bán hàng, theo dõi deal, nhắc nhở tự động
Version: 1.0.7
Requires at least: 2.3.*
Author: Hiệp - Innotel Developer
*/

define('SALES_PIPELINE_MODULE_NAME', 'sales_pipeline');

require_once(__DIR__ . '/includes/performance_score_defaults.php');
require_once(__DIR__ . '/includes/reminder_rule_defaults.php');

// === HOOKS ===
hooks()->add_action('admin_init', 'sales_pipeline_init_menu_items');
hooks()->add_action('admin_init', 'sales_pipeline_permissions');
hooks()->add_action('after_cron_run', 'sales_pipeline_cron_reminder');
hooks()->add_filter('get_dashboard_widgets', 'sales_pipeline_add_dashboard_widget');
hooks()->add_action('app_admin_footer_js', 'sales_pipeline_load_js');
hooks()->add_action('app_admin_head', 'sales_pipeline_load_reminder_settings_css');
hooks()->add_action('after_estimate_added', 'sales_pipeline_quote_estimate_added');
hooks()->add_action('after_estimate_updated', 'sales_pipeline_quote_estimate_updated');
hooks()->add_action('estimate_accepted', 'sales_pipeline_quote_estimate_accepted');
hooks()->add_action('estimate_declined', 'sales_pipeline_quote_estimate_declined');
hooks()->add_action('before_estimate_deleted', 'sales_pipeline_quote_estimate_deleted');
hooks()->add_filter('before_send_simple_email', 'sales_pipeline_inject_reminder_email_cc');

/**
 * Đăng ký quyền truy cập module
 */
function sales_pipeline_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view'              => _l('permission_view') . '(' . _l('permission_global') . ')',
        'view_own'          => _l('permission_view_own'),
        'create'            => _l('permission_create'),
        'edit'              => _l('permission_edit'),
        'delete'            => _l('permission_delete'),
        'view_deal_details' => _l('sales_pipeline_permission_view_deal_details'),
    ];

    register_staff_capabilities('sales_pipeline', $capabilities, _l('sales_pipeline'));
}

/**
 * Thêm menu sidebar cho module
 */
function sales_pipeline_init_menu_items()
{
    $CI = &get_instance();

    $CI->app->add_quick_actions_link([
        'name'       => _l('sales_pipeline_new_deal'),
        'url'        => 'sales_pipeline/deal',
        'permission' => 'sales_pipeline',
        'position'   => 30,
    ]);

    if (has_permission('sales_pipeline', '', 'view') || has_permission('sales_pipeline', '', 'view_own')) {
        $CI->app_menu->add_sidebar_children_item('sales', [
            'slug'     => 'sales-pipeline',
            'name'     => _l('sales_pipeline'),
            'href'     => admin_url('sales_pipeline'),
            'position' => 5,
        ]);
    }
}

/**
 * Widget dashboard tóm tắt pipeline
 */
function sales_pipeline_add_dashboard_widget($widgets)
{
    $widgets[] = [
        'path'      => 'sales_pipeline/dashboard_widget',
        'container' => 'left-8',
    ];

    return $widgets;
}

/**
 * Cron job: Nhắc nhở tự động hàng tuần cho nhân viên sale
 * Quét deal đang mở → gửi email + notification nếu đến hạn nhắc
 */
function sales_pipeline_cron_reminder()
{
    $CI = &get_instance();
    $CI->load->model('sales_pipeline/sales_pipeline_model');
    $CI->sales_pipeline_model->process_reminder_rules();
    $CI->sales_pipeline_model->reconcile_estimate_groups(1000);
}

/**
 * Link new estimates and native Perfex copies to an Estimate-only group.
 */
function sales_pipeline_quote_estimate_added($estimate_id)
{
    $CI = &get_instance();
    $CI->load->model('sales_pipeline/sales_pipeline_model');

    $source_id = $CI->sales_pipeline_model->get_estimate_copy_source_id();
    if (!$source_id
        && strtolower((string) $CI->router->fetch_class()) === 'estimates'
        && strtolower((string) $CI->router->fetch_method()) === 'copy') {
        $source_id = (int) $CI->uri->segment(4);
    }

    $CI->sales_pipeline_model->handle_estimate_added((int) $estimate_id, $source_id ?: null);
}

function sales_pipeline_quote_estimate_updated($estimate_id)
{
    $CI = &get_instance();
    $CI->load->model('sales_pipeline/sales_pipeline_model');
    $CI->sales_pipeline_model->sync_estimate_group_by_estimate((int) $estimate_id, 'after_estimate_updated');
}

function sales_pipeline_quote_estimate_accepted($estimate_id)
{
    $CI = &get_instance();
    $CI->load->model('sales_pipeline/sales_pipeline_model');
    $CI->sales_pipeline_model->sync_estimate_group_by_estimate(
        (int) $estimate_id,
        'estimate_accepted',
        date('Y-m-d H:i:s')
    );
}

function sales_pipeline_quote_estimate_declined($estimate_id)
{
    $CI = &get_instance();
    $CI->load->model('sales_pipeline/sales_pipeline_model');
    $CI->sales_pipeline_model->sync_estimate_group_by_estimate(
        (int) $estimate_id,
        'estimate_declined',
        date('Y-m-d H:i:s')
    );
}

function sales_pipeline_quote_estimate_deleted($estimate_id)
{
    $CI = &get_instance();
    $CI->load->model('sales_pipeline/sales_pipeline_model');
    $CI->sales_pipeline_model->handle_estimate_deleted((int) $estimate_id);
}

hooks()->add_action('app_init', 'sales_pipeline_load_helpers');
hooks()->add_action('app_init', 'sales_pipeline_estimate_group_schema_bootstrap');
hooks()->add_action('app_init', 'sales_pipeline_reminder_repository_schema_bootstrap');
hooks()->add_action('app_init', 'sales_pipeline_performance_score_options_bootstrap');
hooks()->add_action('app_init', 'sales_pipeline_reminder_rule_options_bootstrap');

function sales_pipeline_load_helpers()
{
    $CI = &get_instance();
    $CI->load->helper('sales_pipeline/sales_pipeline');
}

/**
 * Seed Performance Score v1 targets for active installations.
 */
function sales_pipeline_performance_score_options_bootstrap()
{
    sales_pipeline_seed_performance_score_options();
}

/** Seed options for active installations without overwriting admin settings. */
function sales_pipeline_reminder_rule_options_bootstrap()
{
    sales_pipeline_seed_reminder_rule_options();
}

/**
 * Active installations may not run a module migration until the module manager
 * is visited. Bootstrap the new schema once when it is genuinely missing.
 */
function sales_pipeline_estimate_group_schema_bootstrap()
{
    $CI = &get_instance();
    $group_table = db_prefix() . 'sales_pipeline_estimate_groups';
    $version_table = db_prefix() . 'sales_pipeline_estimate_versions';
    $history_table = db_prefix() . 'sales_pipeline_estimate_outcome_history';
    $legacy_table = db_prefix() . 'sales_pipeline_quote_opportunities';
    if ($CI->db->table_exists($group_table)
        && $CI->db->table_exists($version_table)
        && $CI->db->table_exists($history_table)
        && !$CI->db->table_exists($legacy_table)
        && !$CI->db->field_exists('pipeline_id', $group_table)
        && $CI->db->field_exists('decision_estimate_id', $group_table)
        && $CI->db->field_exists('decision_value_base', $group_table)
        && $CI->db->field_exists('estimate_group_id', $version_table)
        && $CI->db->field_exists('estimate_group_id', $history_table)) {
        return;
    }

    require_once(__DIR__ . '/includes/estimate_group_schema.php');
    sales_pipeline_ensure_estimate_group_schema($CI);
}

/**
 * Keep active installations compatible before the module manager runs 1.0.6.
 */
function sales_pipeline_reminder_repository_schema_bootstrap()
{
    $CI = &get_instance();
    $table = db_prefix() . 'sales_pipeline_reminders_log';
    $deliveries = db_prefix() . 'sales_pipeline_reminder_deliveries';
    if (!$CI->db->table_exists($table)) {
        return;
    }

    $pipeline_column = $CI->db
        ->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $CI->db->escape('pipeline_id'))
        ->row_array();
    if ($CI->db->field_exists('entity_type', $table)
        && $CI->db->field_exists('rule_code', $table)
        && $CI->db->field_exists('dedupe_key', $table)
        && $CI->db->field_exists('entity_id', $table)
        && $CI->db->field_exists('period_key', $table)
        && $CI->db->field_exists('checkpoint', $table)
        && $CI->db->field_exists('severity', $table)
        && $CI->db->field_exists('response_required', $table)
        && $CI->db->field_exists('title', $table)
        && $CI->db->field_exists('created_at', $table)
        && $CI->db->table_exists($deliveries)
        && $CI->db->field_exists('recipient_staff_id', $deliveries)
        && $CI->db->field_exists('cc_recipients', $deliveries)
        && $pipeline_column
        && strtoupper((string) $pipeline_column['Null']) === 'YES') {
        return;
    }

    require_once(__DIR__ . '/includes/reminder_repository_schema.php');
    sales_pipeline_ensure_reminder_repository_schema($CI);
}

/**
 * Hook khi kích hoạt module - chạy install.php
 */
register_activation_hook(SALES_PIPELINE_MODULE_NAME, 'sales_pipeline_activation_hook');

function sales_pipeline_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Đăng ký file ngôn ngữ
 */
register_language_files(SALES_PIPELINE_MODULE_NAME, [SALES_PIPELINE_MODULE_NAME]);

/**
 * Load custom JS module trong Admin Footer
 */
function sales_pipeline_load_js()
{
    $CI = &get_instance();
    if ($CI->router->fetch_module() == 'sales_pipeline') {
        $translations = json_encode([
            'pleaseWait' => _l('please_wait'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        echo '<script>window.salesPipelineI18n=' . $translations . ';</script>';
        echo '<script src="' . module_dir_url('sales_pipeline', 'assets/js/sales_pipeline.js') . '?v=' . time() . '"></script>';
    }
}

function sales_pipeline_load_reminder_settings_css()
{
    $CI = &get_instance();
    if ($CI->router->fetch_module() === 'sales_pipeline' && $CI->router->fetch_method() === 'settings') {
        echo '<link rel="stylesheet" href="' . module_dir_url('sales_pipeline', 'assets/css/settings_reminder.css') . '?v=1.0.7">';
    }
}

/**
 * Inject CC header for reminder emails when Reminder_engine has active CC context
 *
 * @param array $cnf Email configuration array
 * @return array
 */
function sales_pipeline_inject_reminder_email_cc($cnf)
{
    if (class_exists('Reminder_engine', false) && !empty(Reminder_engine::$currentEmailCC)) {
        $cnf['cc'] = Reminder_engine::$currentEmailCC;
    }

    return $cnf;
}
