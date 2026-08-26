# Trung tâm tài liệu portal_18

## Cấu trúc

| Thư mục | Vai trò | Đối tượng |
|---|---|---|
| [specifications/](./specifications/) | Nguồn sự thật nghiệp vụ hiện hành | Product, Developer, AI Agent |
| [user-guides/](./user-guides/) | Hướng dẫn thao tác và giải thích cho người dùng | NVKD, Manager |
| [ai/](./ai/) | Context, invariant, code map và verification | AI Agent |
| [plans/](./plans/) | Kế hoạch, lịch sử triển khai và tài liệu To-Be | Developer, PM |
| [agents/](./agents/) | Cấu hình engineering skills và issue tracker | Công cụ phát triển |

## Quy tắc ưu tiên

1. Basecode và dữ liệu runtime là bằng chứng triển khai.
2. `specifications/` là nguồn sự thật nghiệp vụ đã chuẩn hóa.
3. `ai/` là bản đồ để tìm đúng đặc tả và code.
4. `user-guides/` diễn giải cách sử dụng, không định nghĩa schema.
5. `plans/` giữ lịch sử và lộ trình; không mặc nhiên là trạng thái hiện tại.

## Bắt đầu nhanh

- NVKD: [SALES_QUOTE_USER_GUIDE.md](./user-guides/SALES_QUOTE_USER_GUIDE.md)
- Manager: [MANUAL_LINK_UNLINK_GUIDE.md](./user-guides/MANUAL_LINK_UNLINK_GUIDE.md)
- AI Agent: [docs/ai/README.md](./ai/README.md)
- Đặc tả Báo giá: [CREATE_ESTIMATES_WORKFLOW.md](./specifications/CREATE_ESTIMATES_WORKFLOW.md)
- Điểm hiệu suất: [PERFORMANCE_SCORE.md](./specifications/PERFORMANCE_SCORE.md)
