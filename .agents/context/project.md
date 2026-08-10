# portal_18 Project Context

## Nền tảng

- Perfex CRM tùy biến cho Innotel.
- CodeIgniter 3.x, kiến trúc HMVC cho custom modules.
- PHP 7+ và MySQL/MariaDB.
- Admin local: `http://localhost:8000/admin/`.

## Ranh giới code

- Tính năng tùy biến ưu tiên đặt trong `modules/`.
- `system/` và dependency/vendor là read-only.
- Chỉ sửa `application/` khi task yêu cầu rõ ràng và đã truy vết ảnh hưởng core.
- Route/hook override dùng file custom hiện có; không sửa file core tương ứng nếu
  có cơ chế override.

## Convention quan trọng

- Admin controller kế thừa `AdminController`; public/client controller kế thừa
  controller phù hợp của Perfex.
- Dùng CodeIgniter Query Builder và `db_prefix()` cho tên bảng trong Active Record.
- Permission phải được kiểm tra trước CRUD hoặc trước khi trả dữ liệu qua AJAX.
- PHP file phải có guard `defined('BASEPATH') or exit('No direct script access allowed');`.
- Form dùng helper của framework để giữ CSRF.
- Text người dùng nằm trong language files tiếng Việt/tiếng Anh.
- External HTTP dùng thư viện HTTP đã được dự án quản lý; xử lý timeout, exception
  và log lỗi, không ghi secret hoặc payload nhạy cảm vào log.

## Kiểm tra tối thiểu

- Đọc `git status --short` trước và sau khi sửa.
- Chạy `php -l` cho mọi PHP file đã đổi.
- Kiểm tra permission, dữ liệu theo scope, CSRF và escaping ở các luồng liên quan.
- Với UI, kiểm tra trạng thái có dữ liệu, rỗng, lỗi và responsive.

