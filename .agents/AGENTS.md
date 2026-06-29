# PERFEX CRM - INNOTEL PORTAL 18: Agent Directives & Rules

## 1. Project Overview

- **Project**: Perfex CRM - Customized for Innotel
- **Framework**: CodeIgniter 3.x (HMVC via MX third_party)
- **PHP Version**: 7.x+
- **Database**: MySQL/MariaDB (prefix: `tbl`, name: `innotel_portal`)
- **Base URL**: `http://localhost:8000/`
- **Current Migration Version**: 245
- **CSRF Protection**: Enabled
- **Session Driver**: Database (table: `sessions`)

> Tra cứu chi tiết cấu trúc dự án, bảng DB, routes, helpers → xem skill `project-reference`.

---

## 2. Agent Thinking Process (BẮT BUỘC TRƯỚC KHI CODE)

Khi nhận task fix bug hoặc build tính năng mới, PHẢI đi qua checklist này:

1. **Analyze the Boundary**: Core hay standalone feature?
   - *Mặc định*: Tạo Custom Module trong `modules/`.
   - *Ngoại lệ*: Chỉ sửa `application/` nếu được chỉ đạo rõ ràng (ví dụ: tính năng `My Customers`).
2. **Trace the Data Flow**: Xác định Request lifecycle: `Route → Hook → Controller → Model → View`. Không đoán — check files.
3. **Check the Stack**:
   - **DB Queries**: Dùng CI3 Active Record (`$this->db`). Eloquent ORM được boot qua hooks nhưng chỉ dùng khi cần thiết.
   - **External APIs**: Dùng Guzzle 6.x (xem §7).

---

## 3. File Management Rules

- **Config Rule**: Được phép đọc/sửa `application/config/app-config.php` cho setup local. KHÔNG BAO GIỜ commit file này.
- **Core Rule**: `system/` = hoàn toàn read-only. `application/` = read-only trừ khi được chỉ đạo khác.
- **Vendor Rule**: `application/vendor/` = managed by Composer, KHÔNG sửa trực tiếp.
- **Custom Overrides**: Dùng `my_routes.php` cho routes, `my_hooks.php` cho hooks — KHÔNG sửa trực tiếp `routes.php` hay `hooks.php`.

---

## 4. Database Prefix Rule (QUAN TRỌNG — DỄ SAI)

| Ngữ cảnh | Prefix `tbl`? | Ví dụ đúng |
|---|---|---|
| Active Record (`$this->db->get()`, `insert()`, ...) | ✅ Dùng `db_prefix()` | `$this->db->get(db_prefix() . 'clients')` |
| Raw SQL (`$this->db->query()`) | ✅ Ghi `tbl` thủ công | `SELECT * FROM tblclients` |
| DBForge (`add_column()`, `create_table()`, ...) | ❌ KHÔNG prefix | `$this->dbforge->add_column('clients', $fields)` |

**KHÔNG BAO GIỜ** hardcode `tbl` trong Active Record. Luôn dùng `db_prefix()`.

---

## 5. Controller Architecture

```
CI_Controller
  └── App_Controller (application/core/App_Controller.php)
        ├── AdminController → ALL admin/* controllers
        └── ClientsController → ALL public-facing controllers
```

- **Admin routes**: Controller PHẢI extend `AdminController`. Authentication tự động qua constructor. Không cần check session thủ công.
- **Client routes**: Controller PHẢI extend `ClientsController`. Authentication xử lý per-controller.
- URL pattern admin: `admin/{controller_name}/{method}`

---

## 6. Migration Critical Rules

- **Sequential numbering**: File `{N}_version_{N}.php`, class `Migration_Version_{N}`.
- **Core migrations** extend `CI_Migration`. **Module migrations** extend `App_module_migration`.
- **Luôn viết cả `up()` và `down()`** — reversibility bắt buộc.
- **Drop FK TRƯỚC khi drop column** trong `down()`.
- **Prefix rule áp dụng**: dbforge = KHÔNG prefix, raw SQL = CÓ `tbl`.
- Cập nhật `$config['migration_version']` trong `application/config/migration.php`.

> Templates và chi tiết → xem skill `create-migration`.

---

## 7. External API Rule (Guzzle)

Khi viết HTTP request đến external services (LLMs, payment gateways, webhooks):
- **KHÔNG** dùng raw `cURL` hoặc `file_get_contents`.
- **PHẢI** dùng Guzzle 6.x client.
- Handle exceptions: `try-catch (\GuzzleHttp\Exception\RequestException $e)`.
- Log failures.

---

## 8. Debugging Behavior

Khi gặp fatal error hoặc blank screen:
1. **Check Environment**: Đảm bảo `index.php` set `development` mode để errors hiển thị.
2. **Trace Hooks**: Perfex dùng nhiều `hooks()->do_action()`. Nếu data mutate bất thường, search hook actions trước khi debug controller.
3. **Check FlashData**: Nếu redirect loop hoặc login failure không rõ nguyên nhân, inspect CI flashdata session variables.
4. **Check Logs**: Xem `application/logs/` cho PHP errors.

---

## 9. Coding Standards

- Mọi file PHP: `defined('BASEPATH') or exit('No direct script access allowed');`
- Trong helpers: `$CI = &get_instance();`
- File encoding: UTF-8
- Admin alerts: `set_alert('type', 'message')` — types: `success`, `danger`, `warning`, `info`
- Form: Dùng `form_open()` / `form_close()` (tự thêm CSRF token)
- Permission check: `has_permission('module', '', 'action')` trước mọi CRUD action

---

## 10. Communication Language

- **Code**: English (variable names, class names, function names)
- **Comments**: Vietnamese or English (team preference: Vietnamese)
- **Documentation**: Vietnamese
- **User-facing text**: Vietnamese (managed via language files)
