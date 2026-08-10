# Agent Governance — portal_18

File này là bộ điều phối trung tâm cho hệ thống Agentic Development của
AI-Driven CRM portal_18.

## Thứ tự nạp ngữ cảnh

Chỉ đọc những tài liệu cần cho task, theo thứ tự:

1. `context/project.md` và `context/ai-driven-crm.md`.
2. Rule liên quan trong `rules/`.
3. Role đang đảm nhiệm trong `roles/`.
4. Skill phù hợp trong `skills/`.
5. Workflow phù hợp trong `workflows/` và Plan hiện hành trong `docs/plans/`.
6. Cấu hình tích hợp/quan sát trong `mcp/`, `plugins/` và `OBSERVABILITY.md`.

## Quy trình mặc định Plan–Implement–Verify

- Codex nhận idea/prompt, phân tích basecode và sở hữu nội dung Plan.
- Codex tạo hoặc cập nhật `docs/plans/<plan-id>.md`, ghi rõ basecode evidence,
  phạm vi, acceptance criteria, validation commands và `plan_version`.
- Ngay sau khi Plan có đủ bằng chứng basecode, phạm vi và acceptance criteria,
  Codex hoặc Gemini/Antigravity được phép triển khai theo Plan.
- Implementer ghi kết quả vào `docs/plans/<plan-id>.implementation.md`.
- Nếu phát hiện basecode mâu thuẫn với Plan hoặc cần mở rộng phạm vi, dừng phần
  bị ảnh hưởng để Codex cập nhật Plan trước khi tiếp tục triển khai.
- Sau triển khai, Verifier đọc diff, chạy kiểm tra và đối chiếu từng acceptance
  criterion; chỉ kết thúc khi không còn blocker trong phạm vi.

## Nguyên tắc bắt buộc

- Không đoán cấu trúc code: phải truy vết route → controller → model → view/hook.
- Ưu tiên custom module trong `modules/`; không sửa core/dependency nếu không được yêu cầu.
- Bảo toàn working tree bẩn và các thay đổi không thuộc task.
- Text hiển thị phải đi qua language files; không hardcode trong controller/model.
- Mọi truy cập dữ liệu CRM phải giữ nguyên permission và staff/customer scope.
- Không gửi dữ liệu khách hàng, deal, proposal, ticket hoặc secret ra AI bên ngoài
  nếu chưa có phạm vi, cơ chế ẩn danh và phê duyệt phù hợp.
- Không migration DB nếu Plan không mô tả schema, rollback và kiểm thử migration.
- Không coi báo cáo của agent là bằng chứng hoàn thành; phải kiểm tra diff và test.

## Nguồn sự thật

- Product/technical context: `.agents/context/`.
- Chính sách: `.agents/AGENTS.md` và `.agents/rules/`.
- Kế hoạch: `docs/plans/<plan-id>.md`.
- Kết quả triển khai: `docs/plans/<plan-id>.implementation.md`.
- Source code và test thực tế luôn có ưu tiên cao hơn mô tả trong tài liệu.

Xem chi tiết sáu tầng tại `.agents/ARCHITECTURE.md`.
