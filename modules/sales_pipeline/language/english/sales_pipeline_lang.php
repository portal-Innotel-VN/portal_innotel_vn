<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Module name
$lang['sales_pipeline']           = 'Sales Pipeline';
$lang['sales_pipeline_new_deal']  = 'Add New Deal';
$lang['sales_pipeline_edit_deal'] = 'Edit Deal';

// Alerts
$lang['sales_pipeline_deal_added']   = 'Deal added successfully!';
$lang['sales_pipeline_deal_updated'] = 'Deal updated successfully!';
$lang['sales_pipeline_deal_deleted'] = 'Deal deleted successfully!';

// Form labels
$lang['sales_pipeline_customer_info']    = 'Customer Information';
$lang['sales_pipeline_customer_name']    = 'Company Name';
$lang['sales_pipeline_contact_name']     = 'Contact Name';
$lang['sales_pipeline_contact_phone']    = 'Phone Number';
$lang['sales_pipeline_contact_email']    = 'Email';
$lang['sales_pipeline_source']           = 'Lead Source';
$lang['sales_pipeline_select_source']    = 'Select Source';

$lang['sales_pipeline_deal_info']        = 'Deal Information';
$lang['sales_pipeline_deal_name']        = 'Product/Service Description';
$lang['sales_pipeline_deal_value']       = 'Deal Value';
$lang['sales_pipeline_profit_margin']    = 'Profit Margin';
$lang['sales_pipeline_expected_profit']  = 'Expected Profit';
$lang['sales_pipeline_expected_date']    = 'Deal Date';
$lang['sales_pipeline_profit']           = 'Profit';

$lang['sales_pipeline_status_progress']  = 'Status & Progress';
$lang['sales_pipeline_status']           = 'Status';
$lang['sales_pipeline_contract_signed']  = 'Contract Signed';
$lang['sales_pipeline_invoice_issued']   = 'Invoice Issued';
$lang['sales_pipeline_progress_note']    = 'Progress Note';
$lang['sales_pipeline_activity_description'] = 'Update Details';
$lang['sales_pipeline_activity_timeline'] = 'Activity Timeline';

$lang['sales_pipeline_reminder_settings']  = 'Reminder & Assignment';
$lang['sales_pipeline_reminder_enabled']   = 'Enable Auto Reminder';
$lang['sales_pipeline_reminder_frequency'] = 'Reminder Frequency';
$lang['sales_pipeline_reminder_days']      = 'days/time';
$lang['sales_pipeline_assigned_staff']     = 'Assigned Staff';

// Actions
$lang['sales_pipeline_save_deal'] = 'Save Deal';
$lang['sales_pipeline_back']      = 'Back';

// Summary
$lang['sales_pipeline_total_deals'] = 'Total Deals';
$lang['sales_pipeline_total_value'] = 'Total Value';
$lang['sales_pipeline_won']         = 'Won';
$lang['sales_pipeline_lost']        = 'Lost';
$lang['sales_pipeline_active']      = 'Active';
$lang['sales_pipeline_view_all']    = 'View All';

// Filter
$lang['sales_pipeline_filter_quarter'] = 'Filter by Quarter';
$lang['sales_pipeline_all_quarters']   = 'All Quarters';
$lang['sales_pipeline_all_years']      = 'All years';
$lang['sales_pipeline_filter_staff']   = 'Filter by Staff';
$lang['sales_pipeline_all_staff']      = 'All Staff';

// Import
$lang['sales_pipeline_import']          = 'Import Excel';
$lang['sales_pipeline_import_excel']    = 'Upload Excel';
$lang['sales_pipeline_import_note']     = 'Upload Sales Report Excel File';
$lang['sales_pipeline_import_format']   = 'Format fields according to standard template below';
$lang['sales_pipeline_select_file']     = 'Select Excel File';
$lang['sales_pipeline_drag_file']       = 'Drag and drop file here or click to select';
$lang['sales_pipeline_file_types']      = 'Supported Formats';
$lang['sales_pipeline_upload_import']   = 'Upload & Import';
$lang['sales_pipeline_import_success']  = 'Successfully imported %s deals!';

// Table
$lang['sales_pipeline_no_deals'] = 'No deals found. Add a new deal or upload Excel.';

// Notification / Reminder
$lang['sales_pipeline_reminder'] = 'Your Deal Reminder';

// Settings
$lang['sales_pipeline_settings'] = 'Settings';

// Cost Price
$lang['sales_pipeline_cost_price'] = 'Cost Price';
$lang['sales_pipeline_update_cost_price'] = 'Update Cost Price';
$lang['sales_pipeline_fill_cost_price'] = 'Fill Cost Price';
$lang['sales_pipeline_enter_cost_price'] = 'Enter Cost Price (VND)';
$lang['sales_pipeline_cost_price_hint'] = 'Cost price is the purchase/production cost. Profit = Deal Value - Cost Price';
$lang['sales_pipeline_missing_cost_price_hint'] = 'This deal is missing cost price. Please update to calculate profit accurately.';
$lang['sales_pipeline_not_filled'] = 'Not Filled';
$lang['sales_pipeline_profit_preview'] = 'Profit Preview';
$lang['sales_pipeline_invalid_cost_price'] = 'Please enter a valid cost price (positive number)';
$lang['sales_pipeline_missing_cost_prices'] = 'Deals Missing Cost Price';
$lang['sales_pipeline_missing_cost_alert'] = 'Warning: This deal is missing cost price';

// Permissions
$lang['sales_pipeline_permission_view_deal_details'] = 'View Deal Details';

// Validation Error Messages
$lang['sales_pipeline_validation_customer_name_required']        = 'Please enter the Company Name as registered';
$lang['sales_pipeline_validation_customer_name_maxlength']       = 'Company Name cannot exceed 255 characters';
$lang['sales_pipeline_validation_contact_name_maxlength']        = 'Contact Name cannot exceed 100 characters';
$lang['sales_pipeline_validation_contact_phone_digits']         = 'Phone number must contain digits only';
$lang['sales_pipeline_validation_contact_phone_maxlength']        = 'Phone number cannot exceed 10 characters';
$lang['sales_pipeline_validation_contact_email_email']           = 'Please enter a valid email address';
$lang['sales_pipeline_validation_contact_email_maxlength']        = 'Email address cannot exceed 100 characters';
$lang['sales_pipeline_validation_deal_name_required']            = 'Please enter the Product/Service Description';
$lang['sales_pipeline_validation_deal_name_maxlength']           = 'Product/Service Description cannot exceed 500 characters';
$lang['sales_pipeline_validation_deal_value_required']           = 'Please enter the Deal Value (VND)';
$lang['sales_pipeline_validation_deal_value_numeric']            = 'Deal Value must be a number';
$lang['sales_pipeline_validation_deal_date_required']            = 'Please select the Deal Date';
$lang['sales_pipeline_validation_status_required']               = 'Please select the Deal Status';
$lang['sales_pipeline_validation_activity_description_maxlength'] = 'Progress Note cannot exceed 2000 characters';

// Controller & System Messages
$lang['sales_pipeline_invalid_deal']                 = 'Invalid deal or deal does not exist.';
$lang['sales_pipeline_template_file_not_found']     = 'Template file not found. Please contact IT department.';
$lang['sales_pipeline_invalid_staff']                = 'Selected staff is invalid or inactive.';
$lang['sales_pipeline_cannot_delete_status_in_use'] = 'Cannot delete this status because it is currently assigned to deals.';
$lang['sales_pipeline_cannot_delete_source_in_use'] = 'Cannot delete this source because it is currently assigned to deals.';
$lang['sales_pipeline_invalid_deal_id']             = 'Invalid deal ID.';
$lang['sales_pipeline_missing_id']                  = 'A required ID is missing.';
$lang['sales_pipeline_deal_not_found']              = 'Deal not found.';
$lang['sales_pipeline_no_permission_update_cost_price'] = 'You do not have permission to update cost price for this deal.';
$lang['sales_pipeline_cost_price_updated']          = 'Cost price updated successfully.';
$lang['sales_pipeline_update_failed']                = 'Update failed. Please try again.';
$lang['sales_pipeline_missing_deal_or_status']       = 'Missing deal ID or status.';
$lang['sales_pipeline_no_permission_update_deal']   = 'You do not have permission to update this deal.';
$lang['sales_pipeline_status_updated']              = 'Deal status updated successfully.';
$lang['sales_pipeline_status_update_failed']         = 'Failed to update deal status.';

// Import Library Messages
$lang['sales_pipeline_import_only_excel_supported'] = 'Only .xls or .xlsx files are supported.';
$lang['sales_pipeline_import_file_too_large']       = 'File is too large. Maximum allowed size is %d MB.';
$lang['sales_pipeline_import_upload_failed']       = 'Failed to upload file. Please try again.';
$lang['sales_pipeline_import_no_valid_data']       = 'Excel file contains no valid data (empty data area).';
$lang['sales_pipeline_import_result_summary']       = 'Import successful: %d created, %d updated, %d skipped.';
$lang['sales_pipeline_import_read_error']           = 'File read error';

// Views & UI Confirm Messages
$lang['sales_pipeline_load_more_failed']            = 'Failed to load more deals';
$lang['sales_pipeline_please_select_excel_file']     = 'Please select or drag & drop an Excel file (.xls, .xlsx) before importing.';
$lang['sales_pipeline_confirm_contract_received']   = 'Have you received the physical signed contract?';
$lang['sales_pipeline_confirm_invoice_issued']      = 'Have you confirmed that the VAT invoice has been issued by accounting?';

// Additional UI / Log translations
$lang['sales_pipeline_quarter'] = 'Quarter';
$lang['sales_pipeline_vnd'] = 'VND';
$lang['sales_pipeline_details'] = 'Details';
$lang['sales_pipeline_no_cost_price_yet'] = 'No cost price yet';
$lang['sales_pipeline_import_template_instruction_1'] = 'Use the standard Template file to ensure correct data format.';
$lang['sales_pipeline_import_template_instruction_2'] = 'The file already has Data Validation and full instructions.';
$lang['sales_pipeline_download_template'] = 'Download Template (.xlsx)';
$lang['sales_pipeline_q1'] = 'Quarter 1 (Jan-Mar)';
$lang['sales_pipeline_q2'] = 'Quarter 2 (Apr-Jun)';
$lang['sales_pipeline_q3'] = 'Quarter 3 (Jul-Sep)';
$lang['sales_pipeline_q4'] = 'Quarter 4 (Oct-Dec)';
$lang['sales_pipeline_actions'] = 'Actions';
$lang['sales_pipeline_no_missing_cost_prices'] = 'No deals missing cost price in this filter.';
$lang['sales_pipeline_enter_price'] = 'Enter price';
$lang['sales_pipeline_pagination_showing'] = 'Showing';
$lang['sales_pipeline_pagination_to'] = 'to';
$lang['sales_pipeline_pagination_of_total'] = 'of';
$lang['sales_pipeline_pagination_deals'] = 'deals';
$lang['sales_pipeline_pagination_prev'] = 'Previous';
$lang['sales_pipeline_pagination_next'] = 'Next';
$lang['sales_pipeline_settings_statuses'] = 'Statuses';
$lang['sales_pipeline_settings_sources'] = 'Lead Sources';
$lang['sales_pipeline_add_status'] = 'Add Status';
$lang['sales_pipeline_add_source'] = 'Add Source';
$lang['sales_pipeline_status_name'] = 'Status Name';
$lang['sales_pipeline_status_color'] = 'Color';
$lang['sales_pipeline_status_order'] = 'Order';
$lang['sales_pipeline_status_is_won'] = 'Is Won';
$lang['sales_pipeline_status_is_lost'] = 'Is Lost';
$lang['sales_pipeline_options'] = 'Options';
$lang['sales_pipeline_source_name'] = 'Source Name';

// Activity Logs / Model translations
$lang['sales_pipeline_activity_deal_created'] = 'Created new deal: %s';
$lang['sales_pipeline_activity_new_deal'] = 'New deal';
$lang['sales_pipeline_log_new_deal'] = 'Sales Pipeline - New deal [ID: %s] %s';
$lang['sales_pipeline_activity_status_changed'] = 'Status changed: %s → %s';
$lang['sales_pipeline_log_update_deal'] = 'Sales Pipeline - Updated deal [ID: %s]';
$lang['sales_pipeline_log_delete_deal'] = 'Sales Pipeline - Deleted deal [ID: %s] %s';
$lang['sales_pipeline_status_unknown'] = 'Unknown';
$lang['sales_pipeline_activity_cost_price_updated_from_null'] = 'Updated Cost Price: %s VND (from NULL)';
$lang['sales_pipeline_activity_cost_price_updated'] = 'Updated Cost Price: %s → %s VND';
$lang['sales_pipeline_log_update_cost_price'] = 'Sales Pipeline - Updated cost price for deal [ID: %s]';
$lang['sales_pipeline_activity_deal_imported'] = 'Deal created successfully via import';
$lang['sales_pipeline_edit_status'] = 'Edit Status';
$lang['sales_pipeline_edit_source'] = 'Edit Source';
$lang['sales_pipeline_add_source_title'] = 'Add Lead Source';
$lang['sales_pipeline_edit_source_title'] = 'Edit Lead Source';
$lang['sales_pipeline_reminder_responded'] = 'Reminder responded successfully.';
$lang['sales_pipeline_quick_response_title'] = 'Quick reminder response';
$lang['sales_pipeline_reminder_estimate'] = 'Estimate';
$lang['sales_pipeline_reminder_deal'] = 'Deal';
$lang['sales_pipeline_reminder_message'] = 'Reminder message';
$lang['sales_pipeline_your_response'] = 'Your response';
$lang['sales_pipeline_response_placeholder'] = 'Share the outcome, blockers, or why the deal has not been closed...';
$lang['sales_pipeline_response_max_length'] = 'Maximum 2,000 characters. A response can only be submitted once.';
$lang['sales_pipeline_send_response'] = 'Send response';
$lang['sales_pipeline_view_deal'] = 'Open Deal to add a Note';
$lang['sales_pipeline_response_sent'] = 'This response has been submitted.';
$lang['sales_pipeline_response_locked_help'] = 'Reminder responses cannot be edited or submitted again. Open the Deal to add a new progress note.';
$lang['sales_pipeline_reminder_response_invalid'] = 'The response is required and cannot exceed 2,000 characters.';
$lang['sales_pipeline_reminder_already_responded'] = 'This reminder has already been answered.';
$lang['sales_pipeline_reminder_not_found'] = 'The reminder or its related deal could not be found.';
$lang['sales_pipeline_reminder_response_failed'] = 'The response could not be saved. Please try again.';
$lang['sales_pipeline_activity_reminder_response'] = 'Reminder response:';
$lang['please_wait'] = 'Please wait...';
$lang['something_went_wrong'] = 'Something went wrong, please try again!';

// Executive Dashboard
$lang['sales_pipeline_dashboard'] = 'Information Center';
$lang['sales_pipeline_dashboard_title'] = 'Information Center';
$lang['sales_pipeline_kpi_open_value'] = 'Expected Revenue';
$lang['sales_pipeline_kpi_gross_profit'] = 'Expected Gross Profit';
$lang['sales_pipeline_kpi_win_rate'] = 'Deal Win Rate';
$lang['sales_pipeline_kpi_avg_cycle'] = 'Average Sales Cycle';
$lang['sales_pipeline_kpi_stale_deals'] = 'Stale Deals';
$lang['sales_pipeline_kpi_response_rate'] = 'Reminder Response Rate';
$lang['sales_pipeline_funnel_chart'] = 'Sales Pipeline Conversion Funnel';
$lang['sales_pipeline_staff_performance'] = 'Sales Staff Performance';
$lang['sales_pipeline_reminder_response_tracker'] = 'Automated Reminder Response Tracker';
$lang['sales_pipeline_widget_title'] = 'Sales Pipeline Overview';
$lang['sales_pipeline_view_full_dashboard'] = 'View Full Dashboard';
$lang['sales_pipeline_view_pipeline'] = 'View Pipeline';
$lang['sales_pipeline_days'] = 'days';
$lang['sales_pipeline_deals'] = 'deals';
$lang['sales_pipeline_dashboard_refresh'] = 'Refresh metrics';
$lang['sales_pipeline_dashboard_estimates_today'] = 'Total estimates today';
$lang['sales_pipeline_dashboard_estimates_month'] = 'Total estimates this month';
$lang['sales_pipeline_dashboard_revenue_week'] = 'Weekly revenue';
$lang['sales_pipeline_dashboard_revenue_month'] = 'Total monthly revenue';
$lang['sales_pipeline_dashboard_status_success'] = 'On target';
$lang['sales_pipeline_dashboard_leaderboard'] = 'Performance leaderboard';
$lang['sales_pipeline_dashboard_actionable_feed'] = 'Activity log';
$lang['sales_pipeline_dashboard_reminder_pending'] = 'Awaiting response';
$lang['sales_pipeline_dashboard_reminder_responded'] = 'Responded';
$lang['sales_pipeline_dashboard_staff_response'] = 'Staff response';
$lang['sales_pipeline_dashboard_reminder_sent_at'] = 'Reminder sent';
$lang['sales_pipeline_dashboard_response_time'] = 'Response time';
$lang['sales_pipeline_dashboard_no_reminder_responses'] = 'No reminder responses are available in your viewing scope.';
$lang['sales_pipeline_dashboard_rank'] = 'Rank';
$lang['sales_pipeline_dashboard_staff'] = 'Sales staff';
$lang['sales_pipeline_dashboard_view_staff'] = 'Open staff Pipeline';
$lang['sales_pipeline_dashboard_open_deals'] = 'Pipeline information';
$lang['sales_pipeline_dashboard_no_staff'] = 'No performance data is available in your viewing scope.';
$lang['sales_pipeline_dashboard_loading'] = 'Loading staff Pipeline...';
$lang['sales_pipeline_dashboard_load_failed'] = 'Unable to load the staff Pipeline. Please try again.';
$lang['sales_pipeline_dashboard_close'] = 'Close details';
$lang['sales_pipeline_dashboard_staff_pipeline'] = 'Detailed information';
$lang['sales_pipeline_dashboard_contract'] = 'Contract signed';
$lang['sales_pipeline_dashboard_invoice'] = 'Invoice issued';
$lang['sales_pipeline_dashboard_more_deals'] = 'View %s more deals';
$lang['sales_pipeline_dashboard_no_deals'] = 'No deals in this status';

// Reminder snapshot & email
$lang['sales_pipeline_reminder_default_addressee'] = 'Sir/Madam';
$lang['sales_pipeline_reminder_snapshot_greeting'] = 'Hello %s, this %s has not shown any new progress this week.';
$lang['sales_pipeline_reminder_snapshot_intro'] = 'Please provide a quick update:';
$lang['sales_pipeline_reminder_snapshot_yesterday_label'] = 'Yesterday:';
$lang['sales_pipeline_reminder_snapshot_yesterday_body'] = 'What work did you complete?';
$lang['sales_pipeline_reminder_snapshot_today_label'] = 'Today:';
$lang['sales_pipeline_reminder_snapshot_today_body'] = 'What will be your main focus?';
$lang['sales_pipeline_reminder_snapshot_support_label'] = 'Support:';
$lang['sales_pipeline_reminder_snapshot_support_body'] = 'Are you facing any blockers? (Do you need help with pricing, technical matters, or documents?)';
$lang['sales_pipeline_reminder_snapshot_pipeline_warning_label'] = 'Pipeline warning:';
$lang['sales_pipeline_reminder_snapshot_pipeline_warning_body'] = 'Is any %s currently blocked, and why has the Purchase Order not been closed?';
$lang['sales_pipeline_reminder_email_subject'] = '[CRM Notification] %s';
$lang['sales_pipeline_reminder_email_greeting'] = 'Dear %s,';
$lang['sales_pipeline_reminder_email_intro'] = 'The CRM system has identified a sales opportunity that needs a progress update. Please review the information below and respond so the Pipeline remains accurate and coordination can happen promptly.';
$lang['sales_pipeline_reminder_email_type'] = 'Type';
$lang['sales_pipeline_reminder_email_instruction'] = 'Please select %s or view the %s details to update its status.';
$lang['sales_pipeline_reminder_email_quick_response'] = 'Quick response';
$lang['sales_pipeline_reminder_email_view_deal'] = 'View deal';

// List, filters and shared UI
$lang['sales_pipeline_settings_sources_statuses'] = 'Configure Sources & Statuses';
$lang['sales_pipeline_missing_cost_count_alert'] = '%s sales opportunities (deals) are missing cost price information.';
$lang['sales_pipeline_view_and_update_now'] = '[View and update now]';
$lang['sales_pipeline_business_overview'] = 'Business Overview';
$lang['sales_pipeline_switch_to_kanban'] = 'Switch to Kanban';
$lang['sales_pipeline_switch_to_list'] = 'Switch to List';
$lang['sales_pipeline_search_placeholder'] = 'Search deals... (Press Enter)';
$lang['sales_pipeline_clear_search'] = 'Clear search';
$lang['sales_pipeline_filter_document'] = 'Filter documents';
$lang['sales_pipeline_all_documents'] = 'All documents';
$lang['sales_pipeline_contract_signed_yes'] = 'Contract Signed';
$lang['sales_pipeline_contract_signed_no'] = 'Contract Not Signed';
$lang['sales_pipeline_invoice_issued_yes'] = 'Invoice Issued';
$lang['sales_pipeline_invoice_issued_no'] = 'Invoice Not Issued';
$lang['sales_pipeline_per_page'] = '%s / page';
$lang['sales_pipeline_sort_by'] = 'Sort by:';
$lang['sales_pipeline_sort_deal_value'] = 'Deal value';
$lang['sales_pipeline_sequence_number'] = 'No.';
$lang['sales_pipeline_notes'] = 'Notes';
$lang['sales_pipeline_view_full_details'] = 'View full details and update history';
$lang['sales_pipeline_view_details'] = 'View details';
$lang['sales_pipeline_delete_deal'] = 'Delete deal';
$lang['sales_pipeline_loading_data'] = 'Loading data...';
$lang['sales_pipeline_paginated_load_failed'] = 'Unable to load paginated data.';
$lang['sales_pipeline_server_connection_failed'] = 'Server connection error (%s)! Please try again.';
$lang['sales_pipeline_page_load_failed'] = 'Failed to load page %s';
$lang['sales_pipeline_no_matching_deals'] = 'No matching sales opportunities were found.';
$lang['sales_pipeline_missing_cost_information'] = 'Missing cost price information';
$lang['sales_pipeline_not_entered'] = 'Not entered';
$lang['sales_pipeline_enter_cost'] = 'Enter cost price';
$lang['sales_pipeline_pagination_summary'] = 'Showing %s to %s of %s deals';
$lang['sales_pipeline_close'] = 'Close';
$lang['sales_pipeline_display_order'] = 'Display order';
$lang['sales_pipeline_is_won_status'] = 'This is a Won status';
$lang['sales_pipeline_is_lost_status'] = 'This is a Lost status';

// Form placeholders and currency units
$lang['sales_pipeline_customer_name_placeholder'] = 'E.g. Innotel Telecommunications Co., Ltd.';
$lang['sales_pipeline_contact_name_placeholder'] = 'Contact name';
$lang['sales_pipeline_contact_phone_placeholder'] = 'Phone number';
$lang['sales_pipeline_contact_email_placeholder'] = 'Contact email';
$lang['sales_pipeline_deal_name_placeholder'] = 'E.g. Sangfor NSF1200 estimate';
$lang['sales_pipeline_progress_note_placeholder'] = 'Enter the customer communication log...';
$lang['sales_pipeline_currency_billion'] = 'billion';
$lang['sales_pipeline_currency_million'] = 'million';
$lang['sales_pipeline_revenue_target_billion'] = '/ 1 billion';
$lang['sales_pipeline_js_locale'] = 'en-US';

// Internal activity logs
$lang['sales_pipeline_log_import_read_error'] = 'Import_sales_pipeline: Excel read error — %s';
$lang['sales_pipeline_log_import_date_parse_error'] = 'Import_sales_pipeline: Serial date parsing error — %s';

// Staff detail panels
$lang['sales_pipeline_staff_open_deals'] = 'Open Sales Opportunities';
$lang['sales_pipeline_staff_recent_activity'] = 'Activity Log';
$lang['sales_pipeline_no_open_deals'] = 'No open sales opportunities for this staff member.';
$lang['sales_pipeline_no_recent_activity'] = 'No activity recorded for this staff member.';
$lang['sales_pipeline_staff_deal_name'] = 'Deal Name';
$lang['sales_pipeline_customer'] = 'Customer';
$lang['sales_pipeline_value'] = 'Value';
$lang['sales_pipeline_date'] = 'Date';
$lang['sales_pipeline_time'] = 'Time';

// Dashboard period leaderboard
$lang['sales_pipeline_dashboard_filter_this_week'] = 'This week';
$lang['sales_pipeline_dashboard_filter_this_month'] = 'This month';
$lang['sales_pipeline_dashboard_filter_this_quarter'] = 'This quarter';
$lang['sales_pipeline_dashboard_filter_this_year'] = 'This year';
$lang['sales_pipeline_dashboard_period_quotes'] = 'Quotes';
$lang['sales_pipeline_dashboard_period_deals'] = 'Opportunities (Deal)';
$lang['sales_pipeline_dashboard_win_rate'] = 'Win rate (%)';
$lang['sales_pipeline_dashboard_period_revenue'] = 'Period revenue';
