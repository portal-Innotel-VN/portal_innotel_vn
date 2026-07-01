<?php

# Tech Reports Module Language File

$lang['tech_reports'] = 'Tech Reports';
$lang['tech_reports_menu'] = 'Tech Reports';
$lang['tech_reports_dashboard'] = 'Tech Reports Dashboard';
$lang['tech_reports_compliance'] = 'Compliance Report';
$lang['tech_reports_import'] = 'Import Tech Reports';
$lang['tech_reports_manage_staff'] = 'Manage Tech Staff';

# Menu items
$lang['tech_reports_all_reports'] = 'All Reports';
$lang['tech_reports_my_reports'] = 'My Reports';
$lang['tech_reports_new_report'] = 'New Report';

# Form labels
$lang['tech_report_id'] = 'ID';
$lang['tech_report_staff'] = 'Staff Member';
$lang['tech_report_date'] = 'Report Date';
$lang['tech_report_task'] = 'Task Description';
$lang['tech_report_category'] = 'Task Category';
$lang['tech_report_hours'] = 'Hours Spent';
$lang['tech_report_status'] = 'Status';
$lang['tech_report_assigned_by'] = 'Assigned By';
$lang['tech_report_created_by'] = 'Created By';
$lang['tech_report_related_task'] = 'Related Task';
$lang['tech_report_notes'] = 'Notes';
$lang['tech_report_followers'] = 'Followers';
$lang['tech_report_created_at'] = 'Created At';

# Buttons
$lang['tech_report_add'] = 'Add New Report';
$lang['tech_report_edit'] = 'Edit Report';
$lang['tech_report_delete'] = 'Delete Report';
$lang['tech_report_view'] = 'View Report';
$lang['tech_report_export'] = 'Export Reports';
$lang['tech_report_filter'] = 'Filter Reports';

# Task categories
$lang['tech_category_development'] = 'Development';
$lang['tech_category_bug_fix'] = 'Bug Fix';
$lang['tech_category_maintenance'] = 'Maintenance';
$lang['tech_category_support'] = 'Support';
$lang['tech_category_meeting'] = 'Meeting';
$lang['tech_category_research'] = 'Research';
$lang['tech_category_documentation'] = 'Documentation';
$lang['tech_category_other'] = 'Other';

# Status
$lang['tech_status_not_started'] = 'Not Started';
$lang['tech_status_in_progress'] = 'In Progress';
$lang['tech_status_completed'] = 'Completed';
$lang['tech_status_blocked'] = 'Blocked';

# Messages
$lang['tech_report_created_successfully'] = 'Tech report created successfully';
$lang['tech_report_create_failed'] = 'Failed to create tech report';
$lang['tech_report_updated_successfully'] = 'Tech report updated successfully';
$lang['tech_report_update_failed'] = 'Failed to update tech report';
$lang['tech_report_deleted_successfully'] = 'Tech report deleted successfully';
$lang['tech_report_delete_failed'] = 'Failed to delete tech report';
$lang['required_fields_missing'] = 'Required fields are missing';

# Import
$lang['tech_reports_import_file'] = 'Import Excel File';
$lang['tech_reports_download_template'] = 'Download Template';
$lang['tech_reports_import_instructions'] = 'Upload an Excel file (.xlsx) with tech report data. Download the template for the correct format.';
$lang['tech_reports_import_success'] = 'Successfully imported %s of %s reports';
$lang['tech_reports_import_failed'] = 'Import failed';
$lang['tech_reports_import_with_errors'] = 'Import completed with errors';
$lang['file_not_selected'] = 'Please select a file to upload';

# Compliance
$lang['tech_compliance_report'] = 'Compliance Report';
$lang['tech_compliance_rate'] = 'Compliance Rate';
$lang['tech_compliance_submitted'] = 'Reports Submitted';
$lang['tech_compliance_missing'] = 'Reports Missing';
$lang['tech_compliance_status'] = 'Compliance Status';
$lang['tech_compliance_compliant'] = 'Compliant';
$lang['tech_compliance_non_compliant'] = 'Non-Compliant';
$lang['tech_compliance_pending'] = 'Pending';
$lang['tech_compliance_resolved'] = 'Resolved';
$lang['tech_compliance_updated_successfully'] = 'Compliance status updated successfully';
$lang['tech_compliance_update_failed'] = 'Failed to update compliance status';
$lang['tech_compliance_resolve'] = 'Resolve';
$lang['tech_compliance_check_date'] = 'Check Date';
$lang['tech_compliance_notified'] = 'Notified';
$lang['tech_compliance_resolved_by'] = 'Resolved By';

# Dashboard
$lang['tech_dashboard_summary'] = 'Summary';
$lang['tech_dashboard_total_reports'] = 'Total Reports';
$lang['tech_dashboard_total_hours'] = 'Total Hours';
$lang['tech_dashboard_compliance_rate'] = 'Compliance Rate';
$lang['tech_dashboard_today_reports'] = 'Today\'s Reports';
$lang['tech_dashboard_my_reports'] = 'My Reports';
$lang['tech_dashboard_pending_compliance'] = 'Pending Compliance';
$lang['tech_dashboard_date_range'] = 'Date Range';
$lang['tech_dashboard_filter'] = 'Filter';

# Staff management
$lang['tech_staff_list'] = 'Tech Department Staff';
$lang['tech_staff_role'] = 'Role';
$lang['tech_staff_active'] = 'Active';
$lang['tech_staff_inactive'] = 'Inactive';
$lang['tech_staff_add'] = 'Add Tech Staff';
$lang['tech_staff_remove'] = 'Remove from Tech Department';
$lang['tech_staff_updated_successfully'] = '%s tech staff members updated successfully';
$lang['tech_staff_update_failed'] = 'Failed to update tech staff';
$lang['no_staff_selected'] = 'No staff members selected';

# Roles
$lang['tech_role_manager'] = 'Manager';
$lang['tech_role_lead'] = 'Team Lead';
$lang['tech_role_senior'] = 'Senior Developer';
$lang['tech_role_member'] = 'Member';

# Widget
$lang['tech_widget_title'] = 'Tech Reports';
$lang['tech_widget_no_reports'] = 'No reports today';
$lang['tech_widget_view_all'] = 'View All Reports';
$lang['tech_widget_add_report'] = 'Add Report';

# Notifications
$lang['tech_notification_compliance_reminder'] = 'Reminder: Please submit your daily tech report';
$lang['tech_notification_compliance_body'] = 'You have not submitted your tech report for %s. Please submit it as soon as possible.';

# Email templates
$lang['tech_email_compliance_reminder_subject'] = 'Daily Tech Report Reminder';
$lang['tech_email_compliance_reminder_body'] = 'Hi {staff_name},<br><br>This is a reminder that you have not submitted your daily tech report for {report_date}.<br><br>Please submit your report as soon as possible.<br><br>Thank you!';

# Permissions
$lang['tech_reports_permission_view'] = 'View Tech Reports';
$lang['tech_reports_permission_create'] = 'Create Tech Reports';
$lang['tech_reports_permission_edit'] = 'Edit Tech Reports';
$lang['tech_reports_permission_delete'] = 'Delete Tech Reports';

# Errors
$lang['tech_error_no_permission'] = 'You do not have permission to perform this action';
$lang['tech_error_report_not_found'] = 'Tech report not found';
$lang['tech_error_invalid_date'] = 'Invalid date format';
$lang['tech_error_invalid_staff'] = 'Invalid staff member';

# Info
$lang['tech_info_no_reports'] = 'No tech reports found';
$lang['tech_info_no_compliance_issues'] = 'No compliance issues found';
$lang['tech_info_all_compliant'] = 'All tech staff are compliant';

# Table headers
$lang['tech_table_actions'] = 'Actions';
$lang['tech_table_no_data'] = 'No data available';

# Date formats
$lang['tech_date_format'] = 'Y-m-d';
$lang['tech_datetime_format'] = 'Y-m-d H:i:s';
