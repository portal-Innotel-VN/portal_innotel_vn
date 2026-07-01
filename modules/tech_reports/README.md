# Tech Reports Module - Perfex CRM

## Overview
The Tech Reports module enables daily work reporting for technical staff, compliance tracking, and management oversight. It includes automated compliance checking via cron jobs and email/notification reminders.

## Features

### Core Functionality
- ✅ Daily tech work report submission
- ✅ Task categorization (Development, Bug Fix, Maintenance, Support, Meeting, Research, Documentation, Other)
- ✅ Hours tracking per task
- ✅ Status tracking (Not Started, In Progress, Completed, Blocked)
- ✅ Report followers system
- ✅ Related task linking

### Management Features
- ✅ Dashboard with statistics and charts
- ✅ Compliance reporting (80% threshold)
- ✅ Tech department staff management
- ✅ Excel import/export functionality
- ✅ Date range filtering and reports

### Automation
- ✅ Automated compliance checking (runs daily after 17:00 via cron)
- ✅ Email reminders for non-compliant staff
- ✅ In-app notifications
- ✅ Dashboard widget for quick access

## Database Tables

### 1. tbltech_daily_reports
Main reports table storing daily tech work submissions.

**Columns:**
- `id` - Primary key
- `staff_id` - Foreign key to tblstaff
- `report_date` - Date of the report
- `task_description` - Detailed task description
- `task_category` - Task type (enum)
- `hours_spent` - Time spent on task
- `status` - Task status (enum)
- `assigned_by` - Staff who assigned the task
- `created_by` - Staff who created the report
- `related_task_id` - Link to related task (optional)
- `notes` - Additional notes
- `created_at` - Timestamp

### 2. tbltech_report_followers
Tracks staff following specific reports.

**Columns:**
- `id` - Primary key
- `report_id` - Foreign key to tbltech_daily_reports
- `staff_id` - Foreign key to tblstaff
- `created_at` - Timestamp

### 3. tbltech_compliance_log
Logs compliance checks for audit trail.

**Columns:**
- `id` - Primary key
- `staff_id` - Foreign key to tblstaff
- `check_date` - Date of compliance check
- `has_submitted` - Boolean (1 = compliant, 0 = non-compliant)
- `notified` - Boolean (1 = notification sent)
- `resolved_by` - Staff who resolved the issue
- `notes` - Resolution notes
- `created_at` - Timestamp

### 4. tbltech_department_staff
Defines which staff are part of tech department.

**Columns:**
- `id` - Primary key
- `staff_id` - Foreign key to tblstaff
- `is_active` - Boolean (1 = active, requires reports)
- `role` - Tech role (member, senior, lead, manager)
- `created_at` - Timestamp
- `updated_at` - Timestamp

## Installation

1. **Activate the module** in Setup → Modules
2. **Configure tech staff** in Tech Reports → Manage Staff
3. **Set up cron job** to run daily (recommended: after 17:00)
4. **Configure permissions** for staff roles

## Permissions

- `view` - View tech reports
- `create` - Create new reports
- `edit` - Edit existing reports
- `delete` - Delete reports

## Usage

### For Tech Staff

**Daily Report Submission:**
1. Navigate to Tech Reports → New Report
2. Fill in required fields:
   - Staff Member (auto-selected)
   - Report Date
   - Task Description
3. Optional fields:
   - Task Category
   - Hours Spent
   - Status
   - Assigned By
   - Related Task ID
   - Followers
   - Notes
4. Click Submit

**Quick Access:**
- Use the dashboard widget for today's summary
- View "My Reports" filter to see personal submissions

### For Managers

**Dashboard Overview:**
1. Navigate to Tech Reports → Dashboard
2. View summary cards:
   - Total Reports
   - Total Hours
   - Compliance Rate
3. Filter by date range
4. Review compliance by staff member

**Compliance Monitoring:**
1. Navigate to Tech Reports → Compliance
2. View staff compliance rates
3. Filter by date range
4. Identify non-compliant staff (< 80%)

**Staff Management:**
1. Navigate to Tech Reports → Manage Staff
2. Select staff members for tech department
3. Assign roles (Member, Senior, Lead, Manager)
4. Toggle active status

### Excel Import

**Import Process:**
1. Navigate to Tech Reports → Import
2. Download the Excel template
3. Fill in data following the format:
   - Staff ID (required)
   - Report Date in YYYY-MM-DD format (required)
   - Task Description (required)
   - Task Category (optional)
   - Hours Spent (optional)
   - Status (optional)
   - Assigned By (optional)
   - Related Task ID (optional)
   - Notes (optional)
4. Upload the completed file
5. Review import results

**Valid Categories:**
- development
- bug_fix
- maintenance
- support
- meeting
- research
- documentation
- other

**Valid Statuses:**
- not_started
- in_progress
- completed
- blocked

## Cron Job Configuration

The compliance check runs automatically via Perfex CRM's cron system.

**What it does:**
1. Runs daily after 17:00 (5 PM)
2. Checks all active tech staff
3. Verifies if they submitted reports for the current day
4. Logs compliance status to database
5. Sends email reminders to non-compliant staff
6. Creates in-app notifications

**Manual trigger:**
```php
// In your cron controller or manual trigger
$this->load->model('tech_reports_model');
$this->tech_reports_model->check_daily_compliance();
```

## Email Templates

The module uses the following email template:

**Subject:** Daily Tech Report Reminder

**Body:**
```
Hi {staff_name},

This is a reminder that you have not submitted your daily tech report for {report_date}.

Please submit your report as soon as possible.

Thank you!
```

## API / Hooks

The module registers the following hooks:

- `admin_init` - Initialize admin menu and permissions
- `after_cron_run` - Run compliance checks after cron
- `app_admin_head` - Add custom CSS/JS (if needed)

## File Structure

```
modules/tech_reports/
├── tech_reports.php           # Main module file
├── install.php                # Database installation
├── README.md                  # This file
├── controllers/
│   └── Tech_reports.php       # Main controller
├── models/
│   └── Tech_reports_model.php # Data model
├── views/
│   ├── manage.php             # List all reports
│   ├── report_form.php        # Add/edit form
│   ├── dashboard.php          # Manager dashboard
│   ├── compliance_report.php  # Compliance view
│   ├── import.php             # Excel import
│   ├── manage_staff.php       # Staff configuration
│   ├── dashboard_widget.php   # Homepage widget
│   └── tables/
│       └── reports_table.php  # DataTable AJAX handler
├── libraries/
│   └── Import_tech_reports.php # Excel import library
└── language/
    └── english/
        └── tech_reports_lang.php # Language strings
```

## Technical Notes

### Database Prefix
- Uses `db_prefix()` for Active Record queries
- Uses raw `tbl` prefix for direct SQL

### Dependencies
- PHPSpreadsheet (for Excel import/export)
- Chart.js (for dashboard charts)
- Perfex CRM core functions

### Compliance Logic
- Threshold: 80% (configurable in model)
- Calculation: (reports_submitted / expected_workdays) * 100
- Expected workdays: Weekdays (Mon-Fri) in date range

### Performance
- Uses indexes on foreign keys
- DataTables for efficient pagination
- AJAX loading for large datasets

## Troubleshooting

**Issue: Compliance checks not running**
- Verify cron is configured in Perfex CRM
- Check cron logs for errors
- Ensure tech staff are marked as "active"

**Issue: Excel import fails**
- Verify file format is .xlsx or .xls
- Check column headers match template
- Validate staff IDs exist in system
- Ensure date format is YYYY-MM-DD

**Issue: Notifications not sent**
- Verify email configuration in Perfex CRM
- Check staff email addresses are valid
- Review email logs in Setup → Email Queue

**Issue: Permission denied**
- Verify staff role has required permissions
- Check Setup → Roles → Tech Reports permissions

## Support

For issues or feature requests, contact your system administrator.

## Version History

- **v1.0.0** - Initial release
  - Daily report submission
  - Compliance tracking
  - Excel import/export
  - Dashboard and widgets
  - Automated cron checks
  - Email notifications

## Credits

Developed for Perfex CRM Innotel Project.
