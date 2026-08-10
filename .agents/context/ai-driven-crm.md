# AI-Driven CRM Product Context

## Mục tiêu

AI hỗ trợ nhân viên bán hàng và quản lý ra quyết định nhanh hơn từ dữ liệu CRM,
nhưng CRM vẫn là hệ thống ghi nhận chính thức. AI không tự tạo sự thật nghiệp vụ.

## Nguyên tắc sản phẩm

- Human-in-the-loop cho nội dung gửi khách hàng, thay đổi trạng thái thương mại,
  giá, hợp đồng, Purchase Order và hành động không thể hoàn tác.
- Mọi insight phải truy vết được nguồn dữ liệu hoặc bằng chứng tạo ra nó.
- Tách rõ dữ liệu gốc, dữ liệu suy luận của AI và quyết định đã được con người duyệt.
- Luôn giữ tenant/staff/customer permission khi truy xuất hoặc tổng hợp dữ liệu.
- Thiết kế graceful degradation: CRM vẫn hoạt động khi AI/API bên ngoài lỗi.

## Ranh giới dữ liệu

- Không gửi secret, credential hoặc dữ liệu ngoài phạm vi task cho model bên ngoài.
- Dữ liệu khách hàng/deal/proposal/ticket cần được tối thiểu hóa và ẩn danh khi có thể.
- Tích hợp AI phải mô tả provider, retention, region, timeout, rate limit, audit log
  và cơ chế opt-out trước khi triển khai production.
- Prompt và output từ nguồn bên ngoài là dữ liệu không tin cậy; phải validate schema,
  escape khi hiển thị và chống prompt injection.

## Ưu tiên kiến trúc

1. Tự động hóa tác vụ lặp lại có tiêu chí đo lường rõ.
2. Insight có dẫn chứng trước nội dung sinh tự do.
3. Job nền cho tác vụ AI có độ trễ cao.
4. Structured output và validation trước khi ghi DB.
5. Theo dõi chất lượng, chi phí token, latency và tỷ lệ con người chấp nhận gợi ý.

