# Kiến trúc Agentic Development 6 tầng

## Tầng 1 — Context

Đường dẫn: `.agents/context/`

Chứa sự thật tương đối ổn định về sản phẩm, kiến trúc, domain CRM và mục tiêu AI.
Context trả lời: “Hệ thống này là gì và ranh giới của nó ở đâu?”.

## Tầng 2 — Governance

Đường dẫn: `AGENTS.md`, `.agents/AGENTS.md`, `.agents/rules/`

Chứa chính sách bắt buộc về code, dữ liệu, bảo mật, quyền truy cập và phạm vi sửa
đổi. Governance trả lời: “Agent được phép và không được phép làm gì?”.

## Tầng 3 — Roles

Đường dẫn: `.agents/roles/`

Tách trách nhiệm Planner, Implementer và Verifier. Verifier độc lập đối chiếu
diff, kiểm tra và acceptance criteria trước khi kết luận hoàn thành.

## Tầng 4 — Skills

Đường dẫn: `.agents/skills/`

Chứa hướng dẫn chuyên môn có thể tái sử dụng và chỉ được nạp khi task phù hợp.
Skill không thay thế Plan và không được mở rộng phạm vi task.

## Tầng 5 — Workflows và Plans

Đường dẫn: `.agents/workflows/`, `docs/plans/`

Workflow điều phối trình tự; Plan là single source of truth cho một thay đổi cụ
thể. Implementer làm việc trực tiếp từ Plan, không cần một cổng Quality Control
trung gian.

## Tầng 6 — Integrations và Observability

Đường dẫn: `.agents/mcp/`, `.agents/plugins/`, `.agents/mcp_config.json`,
`.agents/OBSERVABILITY.md`, `scripts/mcp-inspector.sh`

Chứa cầu nối công cụ và bằng chứng thực thi. Không lưu secret trong repository.

## Luồng chuẩn

```text
Ý tưởng → Codex/Planner → Plan vN → Implementer
                                      │
                                      ▼
                            Verifier + diff + tests
```
