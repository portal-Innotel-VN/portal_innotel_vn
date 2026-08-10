# Roles

Mỗi task phải xác định rõ agent đang giữ role nào. Một agent có thể đổi role theo
từng giai đoạn; kết quả chỉ hoàn tất sau khi Verifier đối chiếu thay đổi thực tế.

- `planner.md`: Codex mặc định.
- `implementer.md`: Codex hoặc Gemini/Antigravity.
- `verifier.md`: agent kiểm tra diff và test sau triển khai.
