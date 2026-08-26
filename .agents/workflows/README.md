# Workflows

Workflow điều phối nhiều role qua một chuỗi có điều kiện dừng và bằng chứng xác
minh. Nội dung tính năng cụ thể phải nằm trong `docs/plans/`, không nhúng vào
workflow dùng chung.

Trước khi vào bất kỳ workflow nào, agent phải hoàn tất
[`context-loading.md`](../context/context-loading.md). Đây là Phase 0 bắt buộc
cho mọi cuộc hội thoại mới; workflow không được bắt đầu chỉ từ nội dung prompt
hoặc một Plan cũ.

- `plan-implement-verify.md`: vòng đời Plan → Implement → Verify.
- `review-working-tree.md`: review working tree hiện tại ở chế độ read-only.
- `templates/`: mẫu implementation report.

Plan mới dùng mẫu chuẩn duy nhất tại `docs/plans/_template.md`.
