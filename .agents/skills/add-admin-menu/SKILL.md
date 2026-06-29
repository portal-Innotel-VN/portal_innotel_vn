---
name: add-admin-menu
description: Thêm menu item mới vào admin sidebar của Perfex CRM. Kích hoạt khi người dùng yêu cầu thêm mục menu, thêm link vào sidebar, thêm menu con, hoặc chỉnh sửa navigation admin panel.
---

# Hướng dẫn thêm Menu Item vào Admin Sidebar

## File cần chỉnh sửa

**File duy nhất**: `application/helpers/menu_helper.php`

**Hàm chính**: `app_init_admin_sidebar_menu_items()`

---

## Loại Menu Item

### 1. Menu Item cấp 1 (Top-level) — Không có menu con

Dùng `add_sidebar_menu_item()`:

```php
$CI->app_menu->add_sidebar_menu_item('slug_duy_nhat', [
    'name'     => 'Tên Hiển Thị',
    'href'     => admin_url('controller_name'),
    'position' => 6,                    // Vị trí sắp xếp (số nhỏ = lên trên)
    'icon'     => 'fa fa-icon-name',    // Font Awesome 4.x icon
]);
```

**Ví dụ thực tế từ dự án** (My Customers, nằm ở dòng 28-33 trong menu_helper.php):
```php
$CI->app_menu->add_sidebar_menu_item('my_customers', [
    'name'     => 'Khách Hàng Của Tôi',
    'href'     => admin_url('my_customers'),
    'position' => 6,
    'icon'     => 'fa fa-address-book',
]);
```

### 2. Menu Item cấp 1 với menu con (Collapsible group)

Bước 1 — Tạo nhóm cha (collapse):
```php
$CI->app_menu->add_sidebar_menu_item('parent_slug', [
    'collapse' => true,                 // Bắt buộc TRUE cho nhóm cha
    'name'     => 'Tên Nhóm',
    'position' => 10,
    'icon'     => 'fa fa-icon-name',
]);
```

Bước 2 — Thêm menu con:
```php
$CI->app_menu->add_sidebar_children_item('parent_slug', [
    'slug'     => 'child_slug',         // Bắt buộc cho menu con
    'name'     => 'Tên Menu Con',
    'href'     => admin_url('controller_name'),
    'position' => 5,
]);
```

**Ví dụ thực tế**: Nhóm "Sales" với các menu con:
```php
// Nhóm cha
$CI->app_menu->add_sidebar_menu_item('sales', [
    'collapse' => true,
    'name'     => _l('als_sales'),
    'position' => 10,
    'icon'     => 'fa fa-balance-scale',
]);

// Menu con 1
$CI->app_menu->add_sidebar_children_item('sales', [
    'slug'     => 'proposals',
    'name'     => _l('proposals'),
    'href'     => admin_url('proposals'),
    'position' => 5,
]);

// Menu con 2
$CI->app_menu->add_sidebar_children_item('sales', [
    'slug'     => 'invoices',
    'name'     => _l('invoices'),
    'href'     => admin_url('invoices'),
    'position' => 15,
]);
```

---

## Thuộc tính Menu Item

| Thuộc tính | Bắt buộc | Mô tả |
|---|---|---|
| `name` | ✅ | Tên hiển thị. Dùng `_l('key')` nếu cần đa ngôn ngữ, hoặc chuỗi trực tiếp |
| `href` | ✅ (trừ collapse) | URL đích. Luôn dùng `admin_url('controller')` |
| `position` | ❌ | Vị trí sắp xếp. Mặc định = cuối cùng |
| `icon` | ❌ | CSS class của icon. Dùng Font Awesome 4.x (`fa fa-xxx`) |
| `collapse` | ❌ | Set `true` nếu là nhóm cha có menu con |
| `slug` | ✅ (cho children) | Định danh duy nhất cho menu con |
| `badge` | ❌ | Số hiển thị badge (notification count) |

---

## Kiểm tra quyền trước khi hiển thị menu

Nên bọc `add_sidebar_menu_item()` trong điều kiện kiểm tra quyền:

```php
if (has_permission('module_name', '', 'view')) {
    $CI->app_menu->add_sidebar_menu_item('module_name', [
        'name'     => 'Tên Module',
        'href'     => admin_url('module_name'),
        'position' => 7,
        'icon'     => 'fa fa-list',
    ]);
}
```

---

## Vị trí (position) các menu hiện tại trong dự án

| Position | Menu Item | Slug |
|---|---|---|
| 1 | Dashboard | `dashboard` |
| 5 | Khách Hàng (Clients) | `customers` |
| 6 | Khách Hàng Của Tôi | `my_customers` |
| 10 | Bán Hàng (Sales) | `sales` |
| 15 | Subscriptions | `subscriptions` |
| 20 | Chi Phí (Expenses) | `expenses` |
| 25 | Hợp Đồng (Contracts) | `contracts` |
| 30 | Dự Án (Projects) | `projects` |
| 35 | Tasks | `tasks` |
| 40 | Tickets | `tickets` |
| 45 | Leads | `leads` |
| 50 | Knowledge Base | `knowledge_base` |

**Mẹo**: Để thêm menu mới giữa 2 item, dùng số nằm giữa (ví dụ position = 8 để nằm giữa Clients và Sales).

---

## Font Awesome 4.x Icons thường dùng

| Icon | Class |
|---|---|
| 🏠 Home | `fa fa-home` |
| 👤 User | `fa fa-user-o` |
| 📋 List | `fa fa-list` |
| 📦 Box | `fa fa-cube` |
| 💰 Money | `fa fa-money` |
| 📊 Chart | `fa fa-bar-chart` |
| ⚙️ Settings | `fa fa-cog` |
| 📝 Edit | `fa fa-pencil` |
| 📁 Folder | `fa fa-folder-o` |
| 🏷️ Tags | `fa fa-tags` |
| 📞 Phone | `fa fa-phone` |
| 📧 Email | `fa fa-envelope-o` |
| 🔔 Bell | `fa fa-bell` |
| 📅 Calendar | `fa fa-calendar` |
| 🛒 Cart | `fa fa-shopping-cart` |

Tra cứu đầy đủ: https://fontawesome.com/v4/icons/

---

## Checklist

- [ ] Slug menu item là DUY NHẤT (không trùng với menu hiện có)
- [ ] `href` dùng `admin_url()` — KHÔNG hardcode URL
- [ ] Kiểm tra quyền `has_permission()` trước khi thêm (nếu cần)
- [ ] Position không trùng với menu hiện có (để tránh sắp xếp lộn xộn)
- [ ] Icon dùng Font Awesome 4.x class
- [ ] Menu con có thuộc tính `slug` bắt buộc
