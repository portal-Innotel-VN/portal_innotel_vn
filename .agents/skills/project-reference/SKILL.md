---
name: project-reference
description: Tra cứu cấu trúc dự án Perfex CRM Innotel. Kích hoạt khi cần tìm file, kiểm tra bảng database, tra cứu routes, xem helper functions, kiểm tra hooks/events, tìm hiểu authentication flow, hoặc cần thông tin tham chiếu chung về dự án.
---

# Perfex CRM - Innotel Portal 18: Project Reference

## 1. Directory Structure

```
portal_18/                     # Project root
├── application/               # Core application (CI3 standard)
│   ├── config/                # Configuration files
│   │   ├── app-config.php     # ⚠️ SENSITIVE: DB credentials, encryption key, base URL
│   │   ├── database.php       # Database connection (reads from app-config.php)
│   │   ├── migration.php      # Migration version control
│   │   ├── routes.php         # URL routing + supports my_routes.php override
│   │   ├── hooks.php          # CI hooks + supports my_hooks.php override
│   │   ├── autoload.php       # Auto-loaded libraries, helpers, models
│   │   └── constants.php      # Application constants
│   ├── controllers/           # Public-facing controllers
│   │   ├── admin/             # Admin panel controllers (require staff login)
│   │   └── *.php              # Client-facing controllers (Authentication, Clients, etc.)
│   ├── core/                  # Extended CI core classes
│   │   ├── AdminController.php    # Base controller for ALL admin routes
│   │   ├── ClientsController.php  # Base controller for client-facing routes
│   │   ├── App_Controller.php     # Top-level base controller
│   │   └── App_Model.php         # Base model class
│   ├── helpers/               # Helper function files (~46 files)
│   ├── hooks/                 # CI hook classes (Autoloader, Init, etc.)
│   ├── libraries/             # Application libraries
│   ├── models/                # Database models (~43 files)
│   ├── migrations/            # Database migration files (sequential numbering)
│   ├── views/                 # View templates
│   │   ├── admin/             # Admin panel views
│   │   ├── themes/            # Client-facing theme views
│   │   ├── authentication/    # Login/register views
│   │   └── forms/             # Public form views
│   ├── language/              # i18n language files
│   ├── services/              # Service layer classes
│   └── vendor/                # Composer dependencies
├── modules/                   # HMVC Modules (plugins/extensions)
│   ├── appointly/             # Appointment scheduling module
│   ├── goals/                 # Goals module
│   ├── mailbox/               # Mailbox module
│   ├── menu_setup/            # Menu customization module
│   ├── prchat/                # Chat module
│   ├── surveys/               # Surveys module
│   └── theme_style/           # Theme styling module
├── assets/                    # Static assets (CSS, JS, images, fonts)
├── uploads/                   # User uploaded files
├── media/                     # Media files
└── system/                    # CodeIgniter 3 system core (DO NOT MODIFY)
```

---

## 2. Key Tables Reference

### 2.1 Main Tables

| Table Name (without prefix) | Description | Primary Key |
|---|---|---|
| `staff` | Admin/Staff users | `staffid` |
| `clients` | Client companies | `userid` |
| `contacts` | Client contacts | `id` |
| `my_customers` | Custom customers table (Innotel custom) | `userid` |
| `invoices` | Invoices | `id` |
| `estimates` | Estimates/Quotes | `id` |
| `leads` | Sales leads | `id` |
| `projects` | Projects | `id` |
| `tasks` | Tasks | `id` |
| `tickets` | Support tickets | `ticketid` |
| `contracts` | Contracts | `id` |
| `expenses` | Expenses | `id` |
| `proposals` | Proposals | `id` |

### 2.2 Column Naming Conventions

- `staffid` — Foreign key referencing `tblstaff.staffid`
- `userid` — Can reference client ID or custom customer ID
- `datecreated` — Timestamp when record was created (format: `Y-m-d H:i:s`)
- `addedfrom` — Staff ID who created the record
- `rel_id` / `rel_type` — Polymorphic relationship pattern used throughout

---

## 3. Hooks & Events System

### 3.1 CodeIgniter Hooks

Configured in: `application/config/hooks.php`
Custom hooks: `application/config/my_hooks.php` (if exists)

Key hooks:
- `pre_system`: BadUserAgentBlock, App_Autoloader
- `pre_controller`: EloquentHook (boots Eloquent ORM — available but CI3 Active Record is primary)
- `pre_controller_constructor`: InitHook

### 3.2 Application Hooks (Event System)

- Uses `hooks()->do_action('hook_name')` pattern.
- Similar to WordPress action/filter system.
- Common hooks: `pre_admin_init`, `pre_upgrade_database`

---

## 4. Routing Rules

### 4.1 Important Routes

| Route | Controller | Purpose |
|---|---|---|
| `/admin` | `admin/dashboard` | Admin dashboard |
| `/admin/authentication` | `admin/authentication` | Admin login |
| `/admin/profile` | `admin/staff/profile` | Staff profile |
| `/login` | `authentication/login` | Client login |
| `/register` | `authentication/register` | Client registration |
| `/admin/modules` | `admin/mods` | Module management |

### 4.2 Custom Routes

- Custom routes go in: `application/config/my_routes.php`
- This file is auto-included if it exists.

---

## 5. Authentication

### 5.1 Staff Authentication

- Controller: `application/controllers/admin/Authentication.php`
- Model: `application/models/Authentication_model.php`
- Session keys: `staff_user_id`, `staff_logged_in`
- Check: `is_staff_logged_in()` helper function
- Admin check: `is_admin()` helper function

### 5.2 Client Authentication

- Controller: `application/controllers/Authentication.php`
- Session keys: `client_user_id`, `contact_user_id`, `client_logged_in`

### 5.3 Flash Messages

- Uses CI's `set_flashdata()` / `flashdata()` pattern.
- Flash messages appear once and are destroyed after display.
- ⚠️ **Known behavior**: Flash messages from login failures may persist across page navigations if not properly consumed.

---

## 6. View & Template System

### 6.1 Admin Views

- Path: `application/views/admin/`
- Layout: Uses `$this->load->view()` with template structure.
- Assets: `assets/` directory (JS, CSS, images).
- Template init: `init_head()` at top, `init_tail()` before closing `</body>`.

### 6.2 Client Theme Views

- Path: `application/views/themes/`
- Theme-based rendering system.

### 6.3 Helper Functions for Views

- `admin_url($path)` — Generates admin URL.
- `site_url($path)` — Generates site URL.
- `base_url($path)` — Generates base URL.
- `get_admin_uri()` — Returns the admin URI segment.

---

## 7. Module System (HMVC)

### 7.1 Module Structure

Each module in `modules/` follows this structure:
```
modules/{module_name}/
├── {module_name}.php          # Module init/registration file
├── install.php                # Installation script
├── controllers/               # Module controllers
├── models/                    # Module models
├── views/                     # Module views
├── helpers/                   # Module helpers
├── libraries/                 # Module libraries
├── language/                  # Module language files
├── migrations/                # Module-specific migrations
├── assets/                    # Module static assets
├── composer.json              # Module dependencies
└── vendor/                    # Module vendor packages
```

### 7.2 Installed Modules

| Module | Description |
|---|---|
| `appointly` | Appointment scheduling |
| `goals` | Goal tracking |
| `mailbox` | Email mailbox integration |
| `menu_setup` | Menu customization |
| `prchat` | Real-time chat |
| `surveys` | Survey/feedback system |
| `theme_style` | Theme customization |

---

## 8. Development Environment

### 8.1 Local Server

- **URL**: `http://localhost:8000/`
- **Server**: PHP built-in server via `php -S localhost:8000`
- **Or**: MAMP/XAMPP pointing to project root

### 8.2 Running the Project

```bash
cd "/Users/dieterhoang/Documents/Tài liệu - MacBook Pro's  Dieter/CRM_Innotel/portal_18"
php -S localhost:8000
```

### 8.3 Key Admin URLs

| URL | Purpose |
|---|---|
| `http://localhost:8000/admin` | Dashboard |
| `http://localhost:8000/admin/authentication` | Login page |
| `http://localhost:8000/admin/clients` | Client management |
| `http://localhost:8000/admin/staff` | Staff management |
| `http://localhost:8000/admin/settings` | System settings |
| `http://localhost:8000/admin/my_customers` | Custom customers (Innotel) |

---

## 9. Custom Innotel Features

### 9.1 My Customers Module

- **Controller**: `application/controllers/admin/My_customers.php`
- **Model**: `application/models/My_customers_model.php`
- **Table**: `tblmy_customers`
- **Purpose**: Custom customer management specific to Innotel business workflow.
- **Custom Column**: `assigned_staff` (FK → `tblstaff.staffid`, added in migration 245)

---

## 10. Helper Functions Reference

| Function | File | Description |
|---|---|---|
| `db_prefix()` | system | Returns database table prefix (`tbl`) |
| `is_staff_logged_in()` | `staff_helper.php` | Check if admin/staff is logged in |
| `is_admin()` | `staff_helper.php` | Check if current user is admin |
| `get_staff_user_id()` | `staff_helper.php` | Get current logged-in staff ID |
| `admin_url($path)` | `admin_helper.php` | Generate admin panel URL |
| `hooks()` | `hooks.php` | Get hooks instance for event system |
| `get_option($name)` | `settings_helper.php` | Get system setting value |
| `set_alert($type, $msg)` | `general_helper.php` | Set flash alert message |
| `has_permission($feature, $contact_id, $action)` | `misc_helper.php` | Check staff permission |
| `_l($key)` | language system | Get translated language string |
| `html_escape($str)` | CI system | Escape HTML entities |
