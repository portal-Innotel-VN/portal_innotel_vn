<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Tech Reports
Description: Quản lý Báo Cáo Công Việc Kỹ Thuật - Theo dõi công việc hàng ngày, compliance check tự động
Version: 1.0.0
Requires at least: 2.3.*
Author: Hiệp - Innotel Dev Team
*/

define('TECH_REPORTS_MODULE_NAME', 'tech_reports');

// === HOOKS ===
hooks()->add_action('admin_init', 'tech_reports_init_menu_items');
hooks()->add_action('admin_init', 'tech_reports_permissions');
hooks()->add_action('after_cron_run', 'tech_reports_compliance_check');
hooks()->add_filter('get_dashboard_widgets', 'tech_reports_add_dashboard_widget');

/**
 * Đăng ký quyền truy cập module
 */
function tech_reports_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
        'create' => _l('permission_create'),
        'edit'   => _l('permission_edit'),
        'delete' => _l('permission_delete'),
    ];

    register_staff_capabilities('tech_reports', $capabilities, _l('tech_reports'));
}

/**
 * Thêm menu sidebar cho module
 */
function tech_reports_init_menu_items()
{
    $CI = &get_instance();

    $CI->app->add_quick_actions_link([
        'name'       => _l('tech_reports_new_report'),
        'url'        => 'tech_reports/report',
        'permission' => 'tech_reports',
        'position'   => 35,
    ]);

    if (has_permission('tech_reports', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('tech_reports', [
            'name'     => _l('tech_reports'),
            'icon'     => 'fa fa-tasks',
            'href'     => admin_url('tech_reports'),
            'position' => 25,
        ]);

        // Sub-menu
        $CI->app_menu->add_sidebar_children_item('tech_reports', [
            'slug'     => 'tech-reports-dashboard',
            'name'     => _l('tech_reports_dashboard'),
            'href'     => admin_url('tech_reports/dashboard'),
            'position' => 1,
        ]);

        $CI->app_menu->add_sidebar_children_item('tech_reports', [
            'slug'     => 'tech-reports-list',
            'name'     => _l('tech_reports_all_reports'),
            'href'     => admin_url('tech_reports'),
            'position' => 2,
        ]);

        if (has_permission('tech_reports', '', 'view')) {
            $CI->app_menu->add_sidebar_children_item('tech_reports', [
                'slug'     => 'tech-reports-compliance',
                'name'     => _l('tech_reports_compliance'),
                'href'     => admin_url('tech_reports/compliance'),
                'position' => 3,
            ]);
        }
    }
}

/**
 * Widget dashboard tóm tắt báo cáo kỹ thuật
 */
function tech_reports_add_dashboard_widget($widgets)
{
    $widgets[] = [
        'path'      => 'tech_reports/dashboard_widget',
        'container' => 'right-8',
    ];

    return $widgets;
}

/**
 * Cron job: Kiểm tra tuân thủ nhập báo cáo hàng ngày
 * Chạy cuối ngày (17:00) để check nhân viên kỹ thuật đã nhập báo cáo chưa
 */
function tech_reports_compliance_check()
{
    $CI = &get_instance();
    $CI->load->model('tech_reports/tech_reports_model');
    $CI->tech_reports_model->check_daily_compliance();
}

/**
 * Hook khi kích hoạt module - chạy install.php
 */
register_activation_hook(TECH_REPORTS_MODULE_NAME, 'tech_reports_activation_hook');

function tech_reports_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Đăng ký file ngôn ngữ
 */
register_language_files(TECH_REPORTS_MODULE_NAME, [TECH_REPORTS_MODULE_NAME]);
