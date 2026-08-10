<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: New Detail Items Layout
Description: Splits item description and configuration columns in sales document previews and PDFs.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('NEW_DETAIL_ITEMS_LAYOUT_MODULE_NAME', 'new_detail_items_layout');

register_activation_hook(NEW_DETAIL_ITEMS_LAYOUT_MODULE_NAME, 'new_detail_items_layout_activation_hook');

function new_detail_items_layout_activation_hook()
{
    require_once(__DIR__ . '/install.php');
}

hooks()->add_filter('items_table_class', 'new_detail_items_layout_items_table_class', 10, 5);
hooks()->add_action('app_admin_head', 'new_detail_items_layout_admin_head');
hooks()->add_action('app_customers_head', 'new_detail_items_layout_customers_head');

/**
 * Replace the default items table for sales documents that use item details.
 *
 * @param App_items_table_template $class
 * @param object $transaction
 * @param string $type
 * @param string $for
 * @param bool $admin_preview
 * @return App_items_table_template
 */
function new_detail_items_layout_items_table_class($class, $transaction, $type, $for, $admin_preview)
{
    $supportedTypes = [
        'credit_note',
        'estimate',
        'invoice',
        'proposal',
    ];

    if (!in_array(strtolower($type), $supportedTypes, true)) {
        return $class;
    }

    require_once(module_dir_path(NEW_DETAIL_ITEMS_LAYOUT_MODULE_NAME, 'libraries/New_detail_items_layout_items_table.php'));

    return new New_detail_items_layout_items_table($transaction, $type, $for, $admin_preview);
}

/**
 * Keep the configuration column body readable without overriding table header colors.
 *
 * @return void
 */
function new_detail_items_layout_admin_head()
{
    $supportedSegments = [
        'credit_notes',
        'estimates',
        'invoices',
        'proposals',
        'subscription',
        'subscriptions',
        'viewestimate',
        'viewinvoice',
        'viewproposal',
    ];

    $uri = uri_string();
    $isSupportedPage = false;
    foreach ($supportedSegments as $segment) {
        if (strpos($uri, $segment) !== false) {
            $isSupportedPage = true;
            break;
        }
    }

    if (!$isSupportedPage) {
        return;
    }

    new_detail_items_layout_render_css();
}

/**
 * Apply the same column styling in customer sales document views.
 *
 * @return void
 */
function new_detail_items_layout_customers_head()
{
    new_detail_items_layout_render_css();
}

/**
 * @return void
 */
function new_detail_items_layout_render_css()
{
    echo '<style>
        .items-preview td.configuration {
            color: #555;
            font-size: 13px;
            line-height: 1.5;
            white-space: normal;
        }
    </style>';
}
