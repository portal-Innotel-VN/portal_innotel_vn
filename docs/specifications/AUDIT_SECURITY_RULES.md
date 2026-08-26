# Quy tắc Audit, Permission và Transaction

## Permission

- Mọi source/target Estimate phải qua `user_can_view_estimate()` hoặc guard tương đương.
- Staff không được append revision vào Group Accepted.
- Manager/Admin override Accepted phải nhập lý do.
- Version History và audit timeline chỉ trả dữ liệu trong phạm vi quyền.
- Client payload không được quyết định Staff ID, Group owner hoặc revision number.

## Customer boundary

Không được link hai Estimate khác `clientid`. Đây là hard guard không có ngoại lệ.

## Transaction

- Revision number dùng `MAX(revision_no) + 1` dưới `FOR UPDATE`.
- Structural update và audit bắt buộc commit/rollback nhất quán.
- Hai Group được lock theo ID tăng dần để giảm deadlock.
- Sync KPI/Deal chạy sau structural commit nếu service không transaction-aware.

## Fallback

Khi explicit revision link thất bại:

1. Rollback append.
2. Tạo standalone Group trong transaction mới.
3. Ghi `revision_link_failed` và `revision_fallback_standalone`.
4. Audit lỗi thì rollback fallback.
5. Hiển thị cảnh báo rõ cho người dùng.

## Audit event tối thiểu

Event phải chứa actor, estimate, source estimate, from/to Group, reason, metadata và thời gian. Không xóa audit khi Group nguồn trở thành rỗng.
