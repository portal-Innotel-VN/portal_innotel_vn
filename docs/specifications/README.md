# Đặc tả chuẩn của portal_18

Thư mục này là nguồn sự thật nghiệp vụ hiện hành. Khi nội dung tại đây khác với `docs/plans/`, đặc tả tại đây được ưu tiên sau khi đã xác minh với Basecode.

| Tài liệu | Nội dung |
|---|---|
| [DOMAIN_MODEL.md](./DOMAIN_MODEL.md) | Thuật ngữ, aggregate và quan hệ dữ liệu |
| [CREATE_ESTIMATES_WORKFLOW.md](./CREATE_ESTIMATES_WORKFLOW.md) | Clone/Duplicate, tạo mới, Smart Prompt và hậu kiểm |
| [PERFORMANCE_SCORE.md](./PERFORMANCE_SCORE.md) | Công thức Điểm hiệu suất NVKD |
| [DEAL_BRIDGE.md](./DEAL_BRIDGE.md) | Quan hệ Deal–Estimate Group |
| [AUDIT_SECURITY_RULES.md](./AUDIT_SECURITY_RULES.md) | Permission, transaction, fallback và audit |
| [DATA_DICTIONARY.md](./DATA_DICTIONARY.md) | Bảng, field và enum quan trọng |
| [INTEGRATION_CONTRACTS.md](./INTEGRATION_CONTRACTS.md) | Hook, endpoint và service contract |
| [DEAL_REMINDER_RULES.md](./DEAL_REMINDER_RULES.md) | Rule phát hiện thiếu Deal và Deal quá hạn follow-up |

Quy tắc cập nhật:

1. Đọc Basecode trước khi thay đổi đặc tả.
2. Ghi rõ trạng thái đã triển khai hay dự kiến.
3. Không dùng tài liệu kế hoạch làm bằng chứng runtime.
4. Thay đổi công thức hoặc invariant phải cập nhật test và implementation status.
