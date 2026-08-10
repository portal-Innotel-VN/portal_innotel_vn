# Engineering rules for portal_18

- Truy vết route → hook → controller → model → view trước khi thiết kế thay đổi.
- Ưu tiên module, API và helper hiện có; tránh nhân đôi business logic.
- Không hardcode text hiển thị ngoài language dictionaries.
- Dùng `db_prefix()` đúng convention và không nối dữ liệu người dùng vào raw SQL.
- Giữ permission, CSRF, escaping và dữ liệu theo staff/customer scope.
- Migration phải tuần tự, có `up()`/`down()` và kế hoạch rollback.
- Không sửa dependency, generated file hoặc core framework nếu chưa được phê duyệt.
- Kiểm tra PHP syntax và test tập trung cho phạm vi đã đổi.

