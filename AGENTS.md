# portal_18 Agent Entry Point

Mọi AI agent làm việc trong repository này phải đọc và tuân thủ:

1. `.agents/AGENTS.md` — chính sách điều phối và thứ tự nạp ngữ cảnh.
2. `.agents/context/project.md` — kiến trúc kỹ thuật của portal_18.
3. `.agents/context/ai-driven-crm.md` — mục tiêu sản phẩm AI-Driven CRM.
4. Các rule, role, skill và workflow liên quan trực tiếp đến task.

Kế hoạch tính năng được lưu tại `docs/plans/` và là nguồn sự thật chung giữa
Codex, Gemini/Antigravity và con người. Quy trình mặc định là: Codex lập hoặc
cập nhật Plan; Codex hoặc Gemini/Antigravity triển khai ngay theo Plan; sau đó
Verifier đối chiếu diff, kiểm tra và acceptance criteria.

## Agent skills

### Issue tracker

Issues are tracked in GitHub Issues for `HalluHip-ai/portal_18`. See `docs/agents/issue-tracker.md`.

### Domain docs

This repository uses a single-context domain documentation layout. See `docs/agents/domain.md`.
