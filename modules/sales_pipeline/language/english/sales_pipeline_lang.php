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
$lang['sales_pipeline_dashboard_title'] = 'Sales Performance';
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
$lang['sales_pipeline_dashboard_revenue_week'] = 'Won deal value this week';
$lang['sales_pipeline_dashboard_revenue_month'] = 'Won deal value this month';
$lang['sales_pipeline_dashboard_status_success'] = 'On target';
$lang['sales_pipeline_dashboard_leaderboard'] = 'Leaderboard';
$lang['sales_pipeline_dashboard_actionable_feed'] = 'Activity log';
$lang['sales_pipeline_dashboard_reminder_pending'] = 'Awaiting response';
$lang['sales_pipeline_dashboard_reminder_responded'] = 'Responded';
$lang['sales_pipeline_dashboard_staff_response'] = 'Staff response';
$lang['sales_pipeline_dashboard_reminder_sent_at'] = 'Reminder sent';
$lang['sales_pipeline_dashboard_response_time'] = 'Response time';
$lang['sales_pipeline_dashboard_no_reminder_responses'] = 'No reminder responses are available in your viewing scope.';
$lang['sales_pipeline_dashboard_rank'] = 'Rank';
$lang['sales_pipeline_dashboard_staff'] = 'Staff';
$lang['sales_pipeline_dashboard_view_staff'] = 'Open staff Pipeline';
$lang['sales_pipeline_dashboard_open_deals'] = 'Open deals';
$lang['sales_pipeline_dashboard_no_staff'] = 'No performance data is available in your viewing scope.';
$lang['sales_pipeline_dashboard_loading'] = 'Loading staff Pipeline...';
$lang['sales_pipeline_dashboard_load_failed'] = 'Unable to load the staff Pipeline. Please try again.';
$lang['sales_pipeline_dashboard_close'] = 'Close details';
$lang['sales_pipeline_dashboard_staff_pipeline'] = 'Detailed information';
$lang['sales_pipeline_rule_reminder'] = '%s — %s';
$lang['sales_pipeline_reminder_estimate_period'] = 'Estimate period';
$lang['sales_pipeline_reminder_context'] = 'Reminder context';
$lang['sales_pipeline_reminder_informational'] = 'Informational';
$lang['sales_pipeline_reminder_informational_help'] = 'This reminder does not require an explanation. Open the estimate to review and take action when needed.';
$lang['sales_pipeline_reminder_manager_read_only'] = 'You can review this reminder, but only the assigned staff member can respond.';
$lang['sales_pipeline_reminder_response_not_required'] = 'This reminder is informational and does not accept a response.';
$lang['sales_pipeline_go_to_estimate'] = 'Go to estimate';
$lang['sales_pipeline_reminder_email_view_entity'] = 'Open related record';
$lang['sales_pipeline_deal_frequency_message'] = 'Deal %s for customer %s is due for an update. Please report progress and next steps.';
$lang['sales_pipeline_estimate_count_message'] = 'The current valid estimate count is %s, below the required %s. Please explain the cause and recovery plan.';
$lang['sales_pipeline_estimate_weekly_message'] = 'Accepted estimate revenue this week is %s VND, below the %s VND threshold.';
$lang['sales_pipeline_estimate_lifecycle_message'] = '%s — %s needs follow-up: %s.';
$lang['sales_pipeline_estimate_lifecycle_draft_title'] = 'Estimate draft is too old';
$lang['sales_pipeline_estimate_lifecycle_sent_title'] = 'Customer has not responded';
$lang['sales_pipeline_estimate_lifecycle_declined_title'] = 'Estimate was recently declined';
$lang['sales_pipeline_estimate_lifecycle_expired_title'] = 'Estimate has expired';
$lang['sales_pipeline_estimate_lifecycle_accepted_title'] = 'Accepted estimate has no invoice';
$lang['sales_pipeline_estimate_risk_draft_too_long'] = 'The draft has existed for more than 3 days';
$lang['sales_pipeline_estimate_risk_sent_no_response'] = 'Sent more than 3 days ago or near expiry without a response';
$lang['sales_pipeline_estimate_risk_declined'] = 'The customer declined within the last 7 days';
$lang['sales_pipeline_estimate_risk_expired'] = 'The estimate is no longer valid';
$lang['sales_pipeline_estimate_risk_accepted_not_invoiced'] = 'The estimate was accepted but has not been invoiced';
$lang['sales_pipeline_staff_follow_up_customers'] = 'Needs follow-up';
$lang['sales_pipeline_no_follow_up_customers'] = 'There are no at-risk estimates to follow up right now.';
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
$lang['sales_pipeline_target'] = 'Objective:';
$lang['sales_pipeline_reminder_revenue'] = 'Revenue';
$lang['sales_pipeline_reminder_total_amount'] = 'Total amount';
$lang['sales_pipeline_reminder_estimate_expiry'] = 'Estimate expiry';
$lang['sales_pipeline_reminder_snapshot_target'] = '%s "%s" - %s (%s: %s):';
$lang['sales_pipeline_reminder_snapshot_target_question'] = 'What is this week\'s outcome?';
$lang['sales_pipeline_reminder_view_entity'] = 'View %s details';
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
$lang['sales_pipeline_staff_open_deals'] = 'Needs follow-up';
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
$lang['sales_pipeline_dashboard_period_quotes'] = 'Estimates';
$lang['sales_pipeline_dashboard_period_deals'] = 'Opportunities (Deal)';
$lang['sales_pipeline_dashboard_win_rate'] = 'Win rate (%%)';
$lang['sales_pipeline_dashboard_period_revenue'] = 'Revenue';
$lang['sales_pipeline_dashboard_period_filter'] = 'Reporting period';
$lang['sales_pipeline_dashboard_key_metrics'] = 'Key performance metrics';
$lang['sales_pipeline_dashboard_selected_period'] = 'Selected period';
$lang['sales_pipeline_dashboard_current_snapshot'] = 'Current snapshot';
$lang['sales_pipeline_dashboard_open_deals_count'] = 'Current open deals';
$lang['sales_pipeline_dashboard_period_won_deals'] = 'Won deals in period';
$lang['sales_pipeline_dashboard_month_won_value'] = 'Won deal value this month';
$lang['sales_pipeline_dashboard_period_won_value'] = 'Won deal value in period';
$lang['sales_pipeline_dashboard_total_period_deals'] = 'Total deals in period';
$lang['sales_pipeline_dashboard_won_ratio'] = '%s won / %s total';

// Dashboard — Estimates (independent from Deals)
$lang['sales_pipeline_dashboard_data_view'] = 'Choose dashboard data flow';
$lang['sales_pipeline_dashboard_tab_deals'] = 'Deals / Opportunities';
$lang['sales_pipeline_dashboard_tab_estimates'] = 'Estimates';
$lang['sales_pipeline_quote_acceptance_rate'] = 'Acceptance rate (%%)';
$lang['sales_pipeline_quote_leaderboard'] = 'Leaderboard';
$lang['sales_pipeline_performance_leaderboard'] = 'Leaderboard';
$lang['sales_pipeline_performance_leaderboard_description'] = 'The score combines quote volume, accepted value, and quote effectiveness for the selected period.';
$lang['sales_pipeline_performance_score'] = 'Performance score';
$lang['sales_pipeline_performance_your_score'] = 'Your score';
$lang['sales_pipeline_performance_points'] = 'points';
$lang['sales_pipeline_performance_you'] = 'You';
$lang['sales_pipeline_performance_your_rank'] = 'Your rank';
$lang['sales_pipeline_performance_rank_fraction'] = 'Rank %s/%s';
$lang['sales_pipeline_performance_provisional'] = 'Provisional';
$lang['sales_pipeline_performance_view_details'] = 'View score details';
$lang['sales_pipeline_performance_breakdown'] = 'Performance score breakdown';
$lang['sales_pipeline_performance_quote_component'] = 'Estimate volume';
$lang['sales_pipeline_performance_revenue_component'] = 'Accepted value';
$lang['sales_pipeline_performance_acceptance_component'] = 'Acceptance rate';
$lang['sales_pipeline_performance_reminder_component'] = 'Reminder response';
$lang['sales_pipeline_performance_effective_weight'] = 'Effective weight %s%%';
$lang['sales_pipeline_performance_inactive'] = 'Inactive in v1';
$lang['sales_pipeline_performance_not_configured_title'] = 'Performance score is not available';
$lang['sales_pipeline_performance_not_configured_description'] = 'Targets have not been configured for this period. Please contact an administrator.';
$lang['sales_pipeline_performance_empty_admin'] = 'No staff are eligible for ranking in this period.';
$lang['sales_pipeline_performance_empty_staff'] = 'You do not have quote data in this period.';
$lang['sales_pipeline_quote_acceptance_rate_percent'] = 'Acceptance rate (%%)';
$lang['sales_pipeline_quote_estimate_count'] = 'Estimates';
$lang['sales_pipeline_quote_revenue'] = 'Revenue';
$lang['sales_pipeline_quote_revenue_missing_rates_notice'] = '%s accepted estimates have no historical exchange rate and are excluded from Revenue.';
$lang['sales_pipeline_quote_rate_fraction'] = '%s / %s closed';
$lang['sales_pipeline_quote_empty_title'] = 'No estimate data is available in this scope.';
$lang['sales_pipeline_quote_empty_description'] = 'Create the first estimate to start tracking revisions and acceptance rate.';
$lang['sales_pipeline_quote_create_action'] = 'Create estimate';
$lang['sales_pipeline_quote_invalid_estimate'] = 'The source estimate could not be found.';
$lang['sales_pipeline_quote_duplicate_failed'] = 'The estimate could not be copied and linked as a revision. Please try again.';
$lang['sales_pipeline_quote_duplicate_success'] = 'The estimate was copied and linked to its revision group.';
$lang['sales_pipeline_estimate_reminder_daily_title'] = 'Daily estimate report';
$lang['sales_pipeline_estimate_reminder_monthly_title'] = 'Monthly estimate closing report';
$lang['sales_pipeline_estimate_reminder_weekly_title'] = 'Weekly estimate revenue report';
$lang['sales_pipeline_estimate_reminder_default_title'] = 'Periodic estimate reminder';
$lang['sales_pipeline_dashboard_total_deals'] = 'Total Deals';
$lang['sales_pipeline_dashboard_total_revenue'] = 'Total Revenue';
$lang['sales_pipeline_dashboard_total_estimates'] = 'Total Estimates';
$lang['sales_pipeline_settings_reminders'] = 'Reminders';
$lang['sp_reminder_global_switch'] = 'Enable automatic reminders';
$lang['sp_reminder_skip_weekends_label'] = 'Skip weekends';
$lang['sp_reminder_holiday_dates_label'] = 'Holiday dates (one YYYY-MM-DD per line)';
$lang['sp_reminder_quiet_hours_label'] = 'Quiet hours';
$lang['sp_reminder_start'] = 'Start';
$lang['sp_reminder_end'] = 'End';
$lang['sp_reminder_section_deal'] = 'Deal — Frequency';
$lang['sp_reminder_section_kpi'] = 'Estimates — Period targets';
$lang['sp_reminder_section_lifecycle'] = 'Estimates — Lifecycle';
$lang['sp_reminder_deal_note'] = 'Frequency is configured on each Deal';
$lang['sp_reminder_daily'] = 'Daily';
$lang['sp_reminder_monthly'] = 'Monthly';
$lang['sp_reminder_weekly'] = 'Weekly';
$lang['sp_reminder_threshold_label'] = 'Minimum estimates';
$lang['sp_reminder_check_time_label'] = 'Check at';
$lang['sp_reminder_monthly_d10'] = 'D10 target';
$lang['sp_reminder_monthly_d20'] = 'D20 target';
$lang['sp_reminder_monthly_final'] = 'Month-end target';
$lang['sp_reminder_weekly_target'] = 'Weekly revenue target';
$lang['sp_reminder_weekly_midweek_time'] = 'Midweek check (Wed)';
$lang['sp_reminder_weekly_final_time'] = 'End-of-week check (Fri)';
$lang['sp_reminder_days_label'] = 'Days';
$lang['sp_reminder_lc_draft'] = 'Draft too long';
$lang['sp_reminder_lc_sent'] = 'Sent — no response';
$lang['sp_reminder_lc_sent_expiry'] = 'Near expiry within';
$lang['sp_reminder_lc_declined'] = 'Recently declined';
$lang['sp_reminder_lc_expired'] = 'Expired';
$lang['sp_reminder_lc_accepted'] = 'Accepted — not invoiced';
$lang['sp_reminder_save_changes'] = 'Save changes';
$lang['sp_reminder_error_channel_required'] = 'Select at least one delivery channel for %s.';
$lang['sp_reminder_error_invalid_number'] = 'Invalid value for %s (between %s and %s).';
$lang['sp_reminder_error_invalid_time'] = 'Invalid time format for %s.';
$lang['sp_reminder_error_invalid_date'] = 'Invalid holiday date: %s.';
$lang['sp_reminder_error_quiet_hours_incomplete'] = 'Both quiet-hour times are required.';

// Reminder Email Carbon Copy (CC) Settings
$lang['sp_settings_reminder_email_cc_manager']          = 'Carbon Copy (CC) reminder emails to Managers';
$lang['sp_settings_reminder_email_cc_manager_help']     = 'Automatically CC Administrators/Managers when sending reminder emails to sales staff.';
$lang['sp_settings_reminder_email_cc_scope']            = 'CC Scope';
$lang['sp_settings_reminder_email_cc_scope_all']        = 'All reminders';
$lang['sp_settings_reminder_email_cc_scope_critical']   = 'Critical reminders only';
$lang['sp_settings_reminder_email_cc_fallback']         = 'Fallback Manager Emails (comma separated)';
$lang['sp_reminder_error_invalid_fallback_email']       = 'Invalid fallback email address: %s';
$lang['sp_reminder_supervisor_mode_notice']             = 'You are viewing %s\'s reminder notification in Supervisor mode (Read-only).';
