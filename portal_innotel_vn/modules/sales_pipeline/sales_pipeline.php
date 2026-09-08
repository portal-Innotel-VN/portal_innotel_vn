<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Sales Pipeline
Description: Quản lý Tiến Độ Kinh Doanh - Số hóa quy trình bán hàng, theo dõi deal, nhắc nhở tự động
Version: 1.1.4
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
hooks()->add_action('app_admin_footer', 'sales_pipeline_load_js');
hooks()->add_action('app_admin_footer', 'sales_pipeline_load_estimate_revision_js');
hooks()->add_action('app_admin_footer', 'sales_pipeline_load_version_history_js');
hooks()->add_action('app_admin_footer', 'sales_pipeline_load_reminder_bell_js');
hooks()->add_action('app_admin_head', 'sales_pipeline_load_reminder_settings_css');
hooks()->add_action('app_admin_head', 'sales_pipeline_load_estimate_revision_css');
hooks()->add_action('app_admin_head', 'sales_pipeline_load_version_history_css');
hooks()->add_action('app_admin_head', 'sales_pipeline_load_reminder_bell_css');
hooks()->add_filter('before_estimate_added', 'sales_pipeline_capture_estimate_intent');
hooks()->add_action('after_estimate_added', 'sales_pipeline_quote_estimate_added');
hooks()->add_action('after_estimate_updated', 'sales_pipeline_quote_estimate_updated');
hooks()->add_action('estimate_accepted', 'sales_pipeline_quote_estimate_accepted');
hooks()->add_action('estimate_declined', 'sales_pipeline_quote_estimate_declined');
hooks()->add_action('estimate_converted_to_invoice', 'sales_pipeline_quote_estimate_converted_to_invoice');
hooks()->add_action('before_estimate_deleted', 'sales_pipeline_quote_estimate_deleted');
hooks()->add_filter('before_send_simple_email', 'sales_pipeline_inject_reminder_email_cc');
hooks()->add_action('estimate_sent', 'sales_pipeline_handle_estimate_sent');

require_once(__DIR__ . '/libraries/Authoritative_exchange_rate_provider.php');
hooks()->add_filter('sales_pipeline_quote_exchange_rate', 'sales_pipeline_resolve_quote_exchange_rate_hook', 10, 5);
hooks()->add_filter('get_option', 'sales_pipeline_filter_realtime_options', 10, 2);

/**
 * Filter get_option to guarantee real-time option values for cadence and backfill options.
 */
function sales_pipeline_filter_realtime_options($val, $name)
{
    if ($name === 'sp_first_sent_reconcile_cursor' || $name === 'sp_first_sent_reconcile_last_run') {
        $CI = &get_instance();
        if ($CI && isset($CI->db)) {
            $row = $CI->db->select('value')->where('name', $name)->get(db_prefix() . 'options')->row();
            if ($row) {
                return $row->value;
            }
        }
    }
    return $val;
}

/**
 * Đăng ký quyền truy cập module
 */
function sales_pipeline_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view'                      => _l('permission_view') . '(' . _l('permission_global') . ')',
        'view_own'                  => _l('permission_view_own'),
        'create'                    => _l('permission_create'),
        'edit'                      => _l('permission_edit'),
        'delete'                    => _l('permission_delete'),
        'view_deal_details'         => _l('sales_pipeline_permission_view_deal_details'),
        'manage_estimate_revisions' => _l('sales_pipeline_permission_manage_revisions'),
        'manage_finance_lock'       => _l('sales_pipeline_permission_manage_finance_lock'),
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
    $CI->sales_pipeline_model->reconcile_estimate_groups(100);
    $CI->sales_pipeline_model->reconcile_missing_first_sent_groups(100, null, false);
    $CI->load->library('sales_pipeline/Reminder_delivery_maintenance');
    $CI->reminder_delivery_maintenance->runIfDue();
    $CI->load->library('sales_pipeline/Reminder_engine');
    $CI->reminder_engine->reconcile_missing_response_due_at(100);
}

/**
 * Filter before_estimate_added: capture and strip sales_pipeline namespace from POST data.
 *
 * @param array $hook_data ['data' => ..., 'items' => ...]
 * @return array
 */
function sales_pipeline_capture_estimate_intent($hook_data)
{
    $CI = &get_instance();
    $CI->load->library('sales_pipeline/Estimate_revision_service');
    return $CI->estimate_revision_service->capture_request_context($hook_data);
}

/**
 * Link new estimates and native Perfex copies to an Estimate-only group.
 */
function sales_pipeline_quote_estimate_added($estimate_id)
{
    $CI = &get_instance();
    $CI->load->library('sales_pipeline/Estimate_revision_service');

    $source_id = null;
    $link_method = null;

    // Check if module copy context was set
    $copy_context = $CI->estimate_revision_service->get_copy_context();
    if ($copy_context) {
        $source_id = (int) $copy_context['source_estimate_id'];
        $link_method = $copy_context['link_method'];
    } elseif (strtolower((string) $CI->router->fetch_class()) === 'estimates'
        && strtolower((string) $CI->router->fetch_method()) === 'copy') {
        $source_id = (int) $CI->uri->segment(4);
        $link_method = 'native_copy';
    }

    $context = null;
    if ($source_id && $link_method) {
        $context = [
            'source_estimate_id' => $source_id,
            'parent_estimate_id' => $source_id,
            'link_method'        => $link_method,
            'actor_staff_id'     => get_staff_user_id() ? (int) get_staff_user_id() : null,
        ];
    }

    $CI->estimate_revision_service->handle_estimate_added((int) $estimate_id, $context);

    // Capture first-sent evidence if estimate was created directly in a sent state
    $CI->load->library('sales_pipeline/Quote_first_sent_service');
    $CI->quote_first_sent_service->captureFromCurrentEstimate((int) $estimate_id, 'estimate_datesend');
}

function sales_pipeline_quote_estimate_updated($estimate_id)
{
    $CI = &get_instance();
    $CI->load->model('sales_pipeline/sales_pipeline_model');
    $CI->sales_pipeline_model->sync_estimate_group_by_estimate((int) $estimate_id, 'after_estimate_updated');

    // Capture first-sent evidence when estimate transitions to sent or updates datesend
    $CI->load->library('sales_pipeline/Quote_first_sent_service');
    $CI->quote_first_sent_service->captureFromCurrentEstimate((int) $estimate_id, 'estimate_datesend');
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

    $CI->load->library('sales_pipeline/Quote_first_sent_service');
    $CI->quote_first_sent_service->captureFromCurrentEstimate((int) $estimate_id);
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

    $CI->load->library('sales_pipeline/Quote_first_sent_service');
    $CI->quote_first_sent_service->captureFromCurrentEstimate((int) $estimate_id);
}

function sales_pipeline_quote_estimate_converted_to_invoice($data)
{
    $estimate_id = is_array($data) ? (int) ($data['estimate_id'] ?? 0) : (int) $data;
    if ($estimate_id > 0) {
        $CI = &get_instance();
        $CI->load->model('sales_pipeline/sales_pipeline_model');
        $CI->sales_pipeline_model->sync_estimate_group_by_estimate(
            $estimate_id,
            'estimate_converted_to_invoice',
            date('Y-m-d H:i:s')
        );

        $CI->load->library('sales_pipeline/Quote_first_sent_service');
        $CI->quote_first_sent_service->captureFromCurrentEstimate($estimate_id);
    }
}

function sales_pipeline_quote_estimate_deleted($estimate_id)
{
    $CI = &get_instance();
    $CI->load->model('sales_pipeline/sales_pipeline_model');
    $CI->sales_pipeline_model->handle_estimate_deleted((int) $estimate_id);
}

hooks()->add_action('app_init', 'sales_pipeline_load_helpers');
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
            'pleaseWait'        => _l('please_wait'),
            'loading'           => _l('sales_pipeline_dashboard_loading'),
            'loadingEstimates'  => _l('sales_pipeline_dashboard_estimates_loading'),
            'error'             => _l('sales_pipeline_dashboard_load_failed'),
            'onDate'            => _l('sales_pipeline_dashboard_on_date'),
            'invalidDate'       => _l('sales_pipeline_dashboard_history_invalid_date'),
            'locale'            => _l('sales_pipeline_js_locale'),
            'currencyBillion'   => _l('sales_pipeline_currency_billion'),
            'currencyMillion'   => _l('sales_pipeline_currency_million'),
            'currencyVnd'       => _l('sales_pipeline_currency_vnd'),
            'currentPeriod'     => _l('sales_pipeline_kpi_current_period'),
            'previousPeriod'    => _l('sales_pipeline_kpi_previous_period'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        echo '<script>window.salesPipelineI18n=' . $translations . ';</script>';
        echo '<script src="' . module_dir_url('sales_pipeline', 'assets/js/sales_pipeline.js') . '?v=' . time() . '"></script>';
    }
}

function sales_pipeline_load_reminder_settings_css()
{
    $CI = &get_instance();
    if ($CI->router->fetch_module() === 'sales_pipeline' && $CI->router->fetch_method() === 'settings') {
        echo '<link rel="stylesheet" href="' . module_dir_url('sales_pipeline', 'assets/css/settings_reminder.css') . '?v=1.0.10">';
    }
}

/**
 * Load Estimate Revision JS on the estimate create form (admin/estimates/estimate without ID).
 */
function sales_pipeline_load_estimate_revision_js()
{
    $CI = &get_instance();
    $class = strtolower((string) $CI->router->fetch_class());
    $method = strtolower((string) $CI->router->fetch_method());
    $id = $CI->uri->segment(4);

    if ($class === 'estimates' && $method === 'estimate' && (empty($id) || !is_numeric($id))) {
        $translations = json_encode([
            'intentLabel'               => _l('sales_pipeline_estimate_intent_label'),
            'intentStandalone'          => _l('sales_pipeline_estimate_intent_standalone'),
            'intentStandaloneHelp'      => _l('sales_pipeline_estimate_intent_standalone_help'),
            'intentRevision'            => _l('sales_pipeline_estimate_intent_revision'),
            'intentRevisionHelp'        => _l('sales_pipeline_estimate_intent_revision_help'),
            'selectSource'              => _l('sales_pipeline_select_source_estimate'),
            'sourceEstimate'            => _l('sales_pipeline_source_estimate_label'),
            'sourceTotal'               => _l('sales_pipeline_source_estimate_total'),
            'sourceStatus'              => _l('sales_pipeline_source_estimate_status'),
            'sourceDate'                => _l('sales_pipeline_source_estimate_date'),
            'sourceExpiry'              => _l('sales_pipeline_source_estimate_expiry'),
            'sourceDateShort'           => _l('sales_pipeline_source_estimate_date_short'),
            'sourceExpiryShort'         => _l('sales_pipeline_source_estimate_expiry_short'),
            'sourceRevision'            => _l('sales_pipeline_source_estimate_revision'),
            'selectCustomerFirst'       => _l('sales_pipeline_select_customer_first'),
            'noSources'                 => _l('sales_pipeline_no_source_estimates_found'),
            'loadingSources'            => _l('sales_pipeline_loading_source_estimates'),
            'searchPlaceholder'         => _l('sales_pipeline_search_source_estimates'),
            'noSearchResults'           => _l('sales_pipeline_no_matching_source_estimates'),
            'acceptedWarning'           => _l('sales_pipeline_revision_accepted_warning'),
            'overrideReasonLabel'       => _l('sales_pipeline_override_reason_label'),
            'overrideReasonPlaceholder' => _l('sales_pipeline_override_reason_placeholder'),
            'smartPromptTitle'          => _l('sales_pipeline_smart_prompt_title'),
            'smartPromptIntro'          => _l('sales_pipeline_smart_prompt_intro'),
            'smartPromptEstimateNumber'=> _l('sales_pipeline_smart_prompt_estimate_number'),
            'smartPromptCustomer'      => _l('sales_pipeline_smart_prompt_customer'),
            'smartPromptTotal'         => _l('sales_pipeline_smart_prompt_total'),
            'smartPromptStatus'        => _l('sales_pipeline_smart_prompt_status'),
            'smartPromptDate'          => _l('sales_pipeline_smart_prompt_date'),
            'smartPromptExpiry'        => _l('sales_pipeline_smart_prompt_expiry'),
            'smartPromptQuestion'      => _l('sales_pipeline_smart_prompt_question'),
            'smartPromptApply'          => _l('sales_pipeline_smart_prompt_apply'),
            'smartPromptDismiss'        => _l('sales_pipeline_smart_prompt_dismiss'),
            'sourcesUrl'                => admin_url('sales_pipeline/estimate_revision_sources'),
            'candidatesUrl'             => admin_url('sales_pipeline/estimate_revision_candidates'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        echo '<script>window.salesPipelineEstimateRevisionI18n = ' . $translations . ';</script>';
        echo '<script src="' . module_dir_url('sales_pipeline', 'assets/js/estimate_revision.js') . '?v=1.0.17"></script>';
    }
}

/**
 * Load Estimate Revision CSS on the estimate create form.
 */
function sales_pipeline_load_estimate_revision_css()
{
    $CI = &get_instance();
    $class = strtolower((string) $CI->router->fetch_class());
    $method = strtolower((string) $CI->router->fetch_method());
    $id = $CI->uri->segment(4);

    if ($class === 'estimates' && $method === 'estimate' && (empty($id) || !is_numeric($id))) {
        echo '<link rel="stylesheet" href="' . module_dir_url('sales_pipeline', 'assets/css/estimate_revision.css') . '?v=1.1.4">';
    }
}

/**
 * Load Version History JS on estimate view pages.
 */
function sales_pipeline_load_version_history_js()
{
    $CI = &get_instance();
    $class = strtolower((string) $CI->router->fetch_class());

    if ($class === 'estimates') {
        $estimate_id = (int) $CI->uri->segment(4);
        $asset_path = __DIR__ . '/assets/js/estimate_version_history.js';
        $asset_version = is_file($asset_path) ? (string) filemtime($asset_path) : '1.0.9';
        $translations = json_encode([
            'title' => _l('sales_pipeline_version_history_title'),
            'loading' => _l('sales_pipeline_loading_version_history'),
            'loadWarning' => _l('sales_pipeline_version_history_load_warning'),
            'loadError' => _l('sales_pipeline_version_history_load_error'),
            'empty' => _l('sales_pipeline_version_history_empty'),
            'statusAccepted' => _l('sales_pipeline_version_history_status_accepted'),
            'statusDeclined' => _l('sales_pipeline_version_history_status_declined'),
            'statusPending' => _l('sales_pipeline_version_history_status_pending'),
            'groupTitle' => _l('sales_pipeline_version_history_group_title'),
            'versionCount' => _l('sales_pipeline_version_history_version_count'),
            'groupOutcome' => _l('sales_pipeline_version_history_group_outcome'),
            'linkButton' => _l('sales_pipeline_link_standalone_btn'),
            'unlinkButton' => _l('sales_pipeline_unlink_revision_btn'),
            'treeTitle' => _l('sales_pipeline_version_history_tree_title'),
            'auditTitle' => _l('sales_pipeline_version_history_audit_title'),
            'methodOrigin' => _l('sales_pipeline_version_history_method_origin'),
            'methodNativeCopy' => _l('sales_pipeline_version_history_method_native_copy'),
            'methodModuleCopy' => _l('sales_pipeline_version_history_method_module_copy'),
            'methodRevision' => _l('sales_pipeline_version_history_method_revision'),
            'methodManualLink' => _l('sales_pipeline_version_history_method_manual_link'),
            'methodManualUnlink' => _l('sales_pipeline_version_history_method_manual_unlink'),
            'methodUnknown' => _l('sales_pipeline_version_history_method_unknown'),
            'current' => _l('sales_pipeline_version_history_current'),
            'revisionPrefix' => _l('sales_pipeline_version_history_revision_prefix'),
            'parentLabel' => _l('sales_pipeline_version_history_parent_label'),
            'actorLabel' => _l('sales_pipeline_version_history_actor_label'),
            'eventGroupCreated' => _l('sales_pipeline_event_group_created'),
            'eventRevisionLinked' => _l('sales_pipeline_event_revision_linked'),
            'eventRevisionUnlinked' => _l('sales_pipeline_event_revision_unlinked'),
            'eventAcceptedOverride' => _l('sales_pipeline_event_accepted_override'),
            'eventRevisionLinkFailed' => _l('sales_pipeline_event_revision_link_failed'),
            'eventRevisionFallbackStandalone' => _l('sales_pipeline_event_revision_fallback_standalone'),
            'eventUnknown' => _l('sales_pipeline_version_history_event_unknown'),
            'reasonStandalone' => _l('sales_pipeline_reason_standalone'),
            'reason_standalone' => _l('sales_pipeline_reason_standalone'),
            'reason_fallback_standalone' => _l('sales_pipeline_reason_fallback_standalone'),
            'reason_origin' => _l('sales_pipeline_reason_origin'),
            'reason_origin_backfill' => _l('sales_pipeline_reason_origin_backfill'),
            'reason_backfill' => _l('sales_pipeline_reason_backfill'),
            'reason_native_copy' => _l('sales_pipeline_reason_native_copy'),
            'reason_module_copy' => _l('sales_pipeline_reason_module_copy'),
            'reason_revision' => _l('sales_pipeline_reason_revision'),
            'reason_manual_link' => _l('sales_pipeline_reason_manual_link'),
            'reason_manual_unlink' => _l('sales_pipeline_reason_manual_unlink'),
            'reason_override_reason_required' => _l('sales_pipeline_reason_override_reason_required'),
            'reason_accepted_locked' => _l('sales_pipeline_reason_accepted_locked'),
            'reason_customer_mismatch' => _l('sales_pipeline_reason_customer_mismatch'),
            'reason_estimate_not_found' => _l('sales_pipeline_reason_estimate_not_found'),
            'reason_target_group_missing' => _l('sales_pipeline_reason_target_group_missing'),
            'reason_permission_denied' => _l('sales_pipeline_reason_permission_denied'),
            'reason_permission_denied_source' => _l('sales_pipeline_reason_permission_denied_source'),
            'reason_permission_denied_target' => _l('sales_pipeline_reason_permission_denied_target'),
            'reason_invalid_id' => _l('sales_pipeline_reason_invalid_id'),
            'reason_already_same_group' => _l('sales_pipeline_reason_already_same_group'),
            'reason_source_not_standalone' => _l('sales_pipeline_reason_source_not_standalone'),
            'reason_reason_required' => _l('sales_pipeline_reason_reason_required'),
            'reason_version_not_found' => _l('sales_pipeline_reason_version_not_found'),
            'reason_cannot_unlink_only_revision' => _l('sales_pipeline_reason_cannot_unlink_only_revision'),
            'reason_not_latest_revision' => _l('sales_pipeline_reason_not_latest_revision'),
            'reason_must_unlink_latest_revision' => _l('sales_pipeline_reason_must_unlink_latest_revision'),
            'reason_cannot_unlink_accepted_decision' => _l('sales_pipeline_reason_cannot_unlink_accepted_decision'),
            'reason_snapshot_failed' => _l('sales_pipeline_reason_snapshot_failed'),
            'reason_audit_insert_failed' => _l('sales_pipeline_reason_audit_insert_failed'),
            'reason_transaction_failed' => _l('sales_pipeline_reason_transaction_failed'),
            'reason_fallback_failed' => _l('sales_pipeline_reason_fallback_failed'),
            'reason_append_failed' => _l('sales_pipeline_reason_append_failed'),
            'linkModalTitle' => _l('sales_pipeline_version_history_link_modal_title'),
            'targetLabel' => _l('sales_pipeline_version_history_target_label'),
            'targetPlaceholder' => _l('sales_pipeline_version_history_target_placeholder'),
            'reasonLabel' => _l('sales_pipeline_version_history_link_reason_label'),
            'reasonPlaceholder' => _l('sales_pipeline_version_history_link_reason_placeholder'),
            'close' => _l('sales_pipeline_version_history_close'),
            'closeAria' => _l('sales_pipeline_version_history_close_aria'),
            'notAvailable' => _l('sales_pipeline_version_history_not_available'),
            'confirmLink' => _l('sales_pipeline_version_history_confirm_link'),
            'invalidTarget' => _l('sales_pipeline_version_history_invalid_target'),
            'processing' => _l('sales_pipeline_version_history_processing'),
            'linkSuccess' => _l('sales_pipeline_revision_linked'),
            'linkError' => _l('sales_pipeline_version_history_link_error'),
            'unlinkModalTitle' => _l('sales_pipeline_version_history_unlink_modal_title'),
            'unlinkNotice' => _l('sales_pipeline_version_history_unlink_notice'),
            'unlinkReasonLabel' => _l('sales_pipeline_version_history_unlink_reason_label'),
            'unlinkReasonPlaceholder' => _l('sales_pipeline_version_history_unlink_reason_placeholder'),
            'unlinkReasonRequired' => _l('sales_pipeline_version_history_unlink_reason_required'),
            'confirmUnlink' => _l('sales_pipeline_version_history_confirm_unlink'),
            'unlinkSuccess' => _l('sales_pipeline_revision_unlinked'),
            'unlinkError' => _l('sales_pipeline_version_history_unlink_error'),
            'connectionError' => _l('sales_pipeline_version_history_connection_error'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        echo '<script>window.salesPipelineEstimateId = ' . $estimate_id . '; window.salesPipelineVersionHistoryI18n = ' . $translations . ';</script>';
        echo '<script src="' . module_dir_url('sales_pipeline', 'assets/js/estimate_version_history.js') . '?v=' . $asset_version . '"></script>';
    }
}

/**
 * Load Version History CSS on estimate view pages.
 */
function sales_pipeline_load_version_history_css()
{
    $CI = &get_instance();
    $class = strtolower((string) $CI->router->fetch_class());

    if ($class === 'estimates') {
        $asset_path = __DIR__ . '/assets/css/estimate_version_history.css';
        $asset_version = is_file($asset_path) ? (string) filemtime($asset_path) : '1.0.9';
        echo '<link rel="stylesheet" href="' . module_dir_url('sales_pipeline', 'assets/css/estimate_version_history.css') . '?v=' . $asset_version . '">';
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

/**
 * Load Reminder Bell Inbox CSS across Admin pages for logged-in staff.
 */
function sales_pipeline_load_reminder_bell_css()
{
    if (sales_pipeline_can_access_reminder_bell()) {
        $asset_path = __DIR__ . '/assets/css/reminder_bell.css';
        $asset_version = is_file($asset_path) ? (string) filemtime($asset_path) : '1.0.0';
        echo '<link rel="stylesheet" href="' . module_dir_url('sales_pipeline', 'assets/css/reminder_bell.css') . '?v=' . $asset_version . '">';
    }
}

/**
 * Load Reminder Bell Inbox JS across Admin pages for logged-in staff.
 */
function sales_pipeline_load_reminder_bell_js()
{
    if (sales_pipeline_can_access_reminder_bell()) {
        $asset_path = __DIR__ . '/assets/js/reminder_bell.js';
        $asset_version = is_file($asset_path) ? (string) filemtime($asset_path) : '1.0.0';
        $translations = json_encode([
            'inboxError'    => _l('sales_pipeline_reminder_inbox_error'),
            'ackTooltip'    => _l('sales_pipeline_reminder_inbox_ack_tooltip'),
            'actionRespond' => _l('sales_pipeline_reminder_inbox_action_respond'),
            'actionView'    => _l('sales_pipeline_reminder_inbox_action_view'),
            'severity'      => [
                'critical' => _l('sales_pipeline_severity_critical'),
                'warning'  => _l('sales_pipeline_severity_warning'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        echo '<script>window.salesPipelineReminderBellI18n = ' . $translations . ';</script>';
        echo '<script src="' . module_dir_url('sales_pipeline', 'assets/js/reminder_bell.js') . '?v=' . $asset_version . '"></script>';
    }
}

/**
 * Reminder Inbox assets are rollout-controlled and only available to Staff who
 * can use the Sales Pipeline module. Keeping this guard module-owned avoids any
 * change to the Perfex header/Core notification implementation.
 */
function sales_pipeline_can_access_reminder_bell()
{
    if (!is_staff_logged_in() || (int) get_option('sp_reminder_crm_inbox_enabled') !== 1) {
        return false;
    }

    return is_admin()
        || has_permission('sales_pipeline', '', 'view')
        || has_permission('sales_pipeline', '', 'view_own');
}

/**
 * Capture earliest successful estimate sent event for Canonical Quote Count.
 * Atomic update guarantees concurrent deliveries never overwrite an earlier timestamp.
 *
 * @param int $estimate_id
 * @return void
 */
function sales_pipeline_handle_estimate_sent($estimate_id)
{
    $CI = &get_instance();
    if (!$CI || empty($CI->db)) {
        return;
    }

    $estimateId = (int) $estimate_id;
    if ($estimateId <= 0) {
        return;
    }

    $CI->load->library('sales_pipeline/Quote_first_sent_service');
    $CI->quote_first_sent_service->captureFromCurrentEstimate($estimateId, 'activity_email_sent');
}
