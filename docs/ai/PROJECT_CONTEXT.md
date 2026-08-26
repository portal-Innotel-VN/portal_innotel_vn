# Project Context — portal_18

`portal_18` là dự án Perfex CRM có module tùy chỉnh `sales_pipeline`. Module quản lý Deal, Báo giá, dashboard, reminder, KPI và Điểm hiệu suất.

## Nguồn tài liệu

- `docs/specifications/`: nguồn sự thật nghiệp vụ hiện hành.
- `docs/user-guides/`: hướng dẫn người dùng.
- `docs/ai/`: bản đồ context cho Agent.
- `docs/plans/`: kế hoạch, lịch sử quyết định và implementation notes.
- `docs/agents/`: cấu hình engineering skills/issue tracker, không phải domain specification.

## Quy tắc làm việc

1. Đọc Basecode trước khi khẳng định tính năng đã triển khai.
2. Phân biệt file tồn tại, migration đã tạo và migration đã chạy trên DB.
3. Phân biệt contract/source test với DB-backed và manual UI test.
4. Không sửa migration đã có khả năng được áp dụng.
5. Không thay đổi invariant nghiệp vụ nếu chưa cập nhật specification và test.
6. Giữ nguyên thay đổi không liên quan trong worktree.

## Sales Pipeline

Context chi tiết: [SALES_PIPELINE_CONTEXT.md](./SALES_PIPELINE_CONTEXT.md).
