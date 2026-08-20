# Engineering rules for portal_18

- Truy vết route → hook → controller → model → view trước khi thiết kế thay đổi.
- Ưu tiên module, API và helper hiện có; tránh nhân đôi business logic.
- Quản lý ngôn ngữ tập trung: Tuyệt đối không hardcode text hiển thị ngoài thư mục `languages/`; khi thêm/sửa bất kỳ chuỗi ngôn ngữ nào, PHẢI đồng bộ cập nhật đầy đủ cả 2 bản Tiếng Việt (`vietnamese`) và Tiếng Anh (`english`).
- Đồng bộ kế hoạch & tài liệu: Khi thực thi thay đổi code hoặc điều chỉnh thiết kế, PHẢI tự động cập nhật lại nội dung tương ứng trong `docs/plans/<plan-id>.md` và ghi nhận kết quả vào `docs/plans/<plan-id>.implementation.md` trước khi kết thúc task.
- Dùng `db_prefix()` đúng convention và không nối dữ liệu người dùng vào raw SQL.
- Giữ permission, CSRF, escaping và dữ liệu theo staff/customer scope.
- Migration phải tuần tự, có `up()`/`down()` và kế hoạch rollback.
- Không sửa dependency, generated file hoặc core framework nếu chưa được phê duyệt.
- Kiểm tra PHP syntax và test tập trung cho phạm vi đã đổi.

