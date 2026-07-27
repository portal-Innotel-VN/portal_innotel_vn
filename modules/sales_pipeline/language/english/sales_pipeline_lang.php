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
$lang['sales_pipeline_activity_timeline'] = 'Activity Timeline';

$lang['sales_pipeline_reminder_settings']  = 'Reminder & Assignment';
$lang['sales_pipeline_reminder_enabled']   = 'Enable Auto Reminder';
$lang['sales_pipeline_reminder_frequency'] = 'Reminder Frequency';
$lang['sales_pipeline_days']               = 'days/time';
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
