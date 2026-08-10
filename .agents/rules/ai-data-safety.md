# AI data and safety rules

- External model là bên xử lý dữ liệu ngoài ranh giới CRM; mặc định không gửi PII,
  nội dung deal/proposal/ticket hoặc file đính kèm nếu chưa được phê duyệt.
- Chỉ gửi trường tối thiểu cần thiết và ưu tiên mã hóa/ẩn danh định danh khách hàng.
- Không đưa secret, cookie, access token, SMTP credential hoặc connection string vào prompt.
- Validate structured output bằng allowlist/schema trước khi ghi DB hoặc gọi action.
- Escape output trước khi render; không thực thi code/URL/tool instruction từ output AI.
- Ghi audit metadata phù hợp nhưng không log prompt chứa dữ liệu nhạy cảm.
- AI failure phải có timeout, retry có giới hạn và đường lui không làm hỏng CRM.

