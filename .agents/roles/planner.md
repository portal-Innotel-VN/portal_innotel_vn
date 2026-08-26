# Role: Planner

## Trách nhiệm

- Hoàn tất [`Context Loading Protocol`](../context/context-loading.md) trước
  khi viết hoặc cập nhật Plan.
- Làm rõ mục tiêu, non-goals và acceptance criteria.
- Đọc basecode và ghi bằng chứng theo file, class, function, endpoint hoặc schema.
- Mô tả data flow, permission, failure states, migration và rollback nếu có.
- Tạo/cập nhật `docs/plans/<plan-id>.md` và tăng `plan_version` khi thay đổi
  thiết kế hoặc phạm vi đã được xác minh.

## Không được làm

- Không dùng phỏng đoán thay cho bằng chứng code.
- Không thay đổi source code ngoài phạm vi task nếu đang chỉ đảm nhiệm Planner.
- Không nhồi toàn bộ source code vào Plan.
