<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Sales Pipeline
Description: Quản lý Tiến Độ Kinh Doanh - Số hóa quy trình bán hàng, theo dõi deal, nhắc nhở tự động
Version: 1.0.0
Requires at least: 2.3.*
Author: Hiệp - Innotel Dev Team
*/

define('SALES_PIPELINE_MODULE_NAME', 'sales_pipeline');

// === HOOKS ===
hooks()->add_action('admin_init', 'sales_pipeline_init_menu_items');
hooks()->add_action('admin_init', 'sales_pipeline_permissions');
hooks()->add_action('after_cron_run', 'sales_pipeline_cron_reminder');
hooks()->add_filter('get_dashboard_widgets', 'sales_pipeline_add_dashboard_widget');

/**
 * Đăng ký quyền truy cập module
 */
function sales_pipeline_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view'     => _l('permission_view') . '(' . _l('permission_global') . ')',
        'view_own' => _l('permission_view_own'),
        'create'   => _l('permission_create'),
        'edit'   => _l('permission_edit'),
        'delete' => _l('permission_delete'),
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
    $CI->sales_pipeline_model->process_weekly_reminders();
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
