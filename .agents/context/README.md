# Context

Context chứa thông tin ổn định cần thiết để agent hiểu dự án mà không phải quét
lại toàn bộ repository trong mỗi task.

- `context-loading.md`: quy trình bắt buộc để khởi tạo context ở đầu mỗi cuộc
  hội thoại/task mới; đây là điểm vào trước mọi workflow.
- `project.md`: stack, cấu trúc và convention kỹ thuật.
- `ai-driven-crm.md`: mục tiêu sản phẩm, nguyên tắc AI và ranh giới dữ liệu.

Không đưa kế hoạch tính năng hoặc log thực thi vào thư mục này.

Mọi agent phải chạy `context-loading.md` trước khi lập kế hoạch, triển khai,
review hoặc chẩn đoán. Tài liệu nghiệp vụ chi tiết nằm ở `docs/ai/`,
`docs/specifications/` và `docs/user-guides/`; thư mục này chỉ giữ quy trình và
context ổn định.
