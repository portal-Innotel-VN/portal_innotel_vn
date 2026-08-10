# Feature Plans — Single Source of Truth

Mỗi thay đổi đáng kể có hai artifact cùng `plan-id`:

- `<plan-id>.md`: Plan do Codex sở hữu và cập nhật.
- `<plan-id>.implementation.md`: bằng chứng triển khai và kiểm thử do
  Implementer cập nhật.

Tạo Plan từ `docs/plans/_template.md`. Không dán lại toàn bộ Plan qua chat; hãy
chỉ định đường dẫn để agent đọc trực tiếp từ repository.

Các file Plan không chứa secret, dữ liệu khách hàng thực hoặc credential.
