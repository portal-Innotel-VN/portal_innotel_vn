# Feature Plan: Rationalize the Agent environment

## Metadata

- plan_id: `agent-environment-rationalization`
- plan_version: `1`
- plan_status: `READY_FOR_QC`
- qc_status: `PENDING`
- planner: `Codex`
- qc_reviewer: `Gemini/Antigravity`
- implementer: `Gemini/Antigravity`
- updated_at: `2026-08-10`

## Goal

Giản lược tài liệu và skill không còn tạo giá trị, đồng thời sửa các chỉ dẫn mơ
hồ, mâu thuẫn hoặc không khớp basecode. Luồng đích là Codex lập/cập nhật Plan,
Gemini/Antigravity QC và triển khai sau `QC: PASS`.

## Business context

`.agents/` hiện có 49 file, gồm 39 file Markdown và 8 skill. Một phần nội dung
đến từ bộ khung chung, trong khi portal_18 là Perfex CRM legacy trên CodeIgniter
3. Các chỉ dẫn chung đang tạo giả định sai về test runner, PHP compatibility và
người chịu trách nhiệm triển khai.

## Basecode evidence

| File / symbol | Hiện trạng đã xác minh | Ảnh hưởng |
|---|---|---|
| `.agents/AGENTS.md` | Gemini/Antigravity là implementer mặc định sau `QC: PASS` | Đây phải là policy nguồn cho ownership |
| `roles/README.md`, workflow, architecture, implementation template | Vẫn ghi Codex triển khai | Mâu thuẫn trực tiếp với governance |
| `qc-findings.md` | Hardcode `Gemini 3.6 Flash (High)` và dùng `CONDITIONAL_PASS` | Model dễ lỗi thời; enum chưa thống nhất |
| `skills/implement/SKILL.md` | Yêu cầu tự implement, full test và commit | Trái quy trình mới; repo không có full test suite cấu hình sẵn |
| `skills/setup-matt-pocock-skills/` | Setup one-time đã tạo `docs/agents/*`; còn template GitLab/local và pointer tới skill không cài | Không cần cho vận hành thường ngày |
| `skills/tdd/` | Ví dụ TypeScript/Jest; trỏ `/codebase-design` không tồn tại | Không bám PHP/CI3 |
| `skills/diagnosing-bugs/SKILL.md` | Trỏ `CONTEXT.md`, ADR và `/improve-codebase-architecture`; các target không tồn tại | Pointer hỏng |
| `docs/agents/domain.md` | Yêu cầu `CONTEXT.md` và `docs/adr/`; cả hai không tồn tại | Mâu thuẫn với `.agents/context/` |
| `.agents/context/project.md` | Ghi “PHP 7+” | Source yêu cầu PHP 5.6.4 / Composer `>=5.6`; local CLI là PHP 8.5.7 |
| `routes.php`, `hooks.php` | Có include tùy chọn `my_routes.php`, `my_hooks.php`; hai file custom hiện chưa tồn tại | Context cần mô tả cơ chế, không khẳng định file đang có |
| `migration.php` | Migration hiện tại là 249 | Không hardcode version trong tài liệu |
| `rules/formatting-sample.md` | Nội dung là rule thật nhưng tên có chữ `sample` | Mơ hồ giữa mẫu inert và policy active |
| `skills/ui/SKILL.md` | 825 dòng; bắt buộc dùng `--crm-*` nhưng codebase không định nghĩa biến này | Context load lớn và có thể tạo CSS `var()` vô hiệu |
| `sales_pipeline/assets/css/dashboard.css` | Token thật được định nghĩa scoped trên `.sp-dashboard-shell` | Pattern thực tế nên được ưu tiên |
| `.agents/mcp_config.json` | Cấu hình active rỗng | Không có MCP workspace đang hoạt động |
| `.agents/mcp/mcp.sample.json` | Dùng absolute path tới Python/server ngoài repo; target vẫn tồn tại | Không portable nhưng chưa đủ bằng chứng để xóa |
| `.agents/plugins/` | Chỉ có README và manifest mẫu | Chưa có plugin thật |
| `.Antigravity/extensions.json` | Recommendation rỗng | Không tạo observability thực tế |
| `scripts/mcp-inspector.sh` | Readable, executable, `bash -n` PASS; Inspector 2.0.0 có trong npm cache | Hữu dụng, nên giữ |
| Các JSON Agent | Parse thành công | Không có lỗi cú pháp hiện tại |

## Scope

### In scope

- Giữ sáu trách nhiệm: Context, Governance, Roles, Skills, Workflow/Plans và
  Integration/Observability; loại file mẫu/skill không còn chức năng.
- Đồng bộ ownership Codex/Gemini và một source of truth cho từng loại thông tin.
- Bổ sung convention portal_18 đã xác minh từ basecode.
- Rút gọn skill chung để phù hợp PHP/CodeIgniter 3 và công cụ thực tế.

### Non-goals

- Không sửa mã ứng dụng nghiệp vụ.
- Không cài package, CLI, extension hoặc MCP server.
- Không thay đổi credential hay cấu hình ngoài repository.
- Không kích hoạt server `antigravity-qc` khi chưa xác nhận mục đích.

## Required behavior

### Phương án 1: giản lược

1. Xóa `.agents/skills/implement/` vì xung đột ownership và tự commit.
2. Xóa `.agents/skills/setup-matt-pocock-skills/` sau khi bỏ mọi pointer tới nó.
3. Gộp `rules/formatting-sample.md` vào `rules/engineering.md`, rồi xóa file mẫu.
4. Xóa `tdd/tests.md` và `tdd/mocking.md`; giữ một `tdd/SKILL.md` ngắn cho CI3.
5. Xóa `.agents/plugins/` sau khi QC xác minh không cần placeholder discovery.
6. Xóa `.Antigravity/extensions.json` nếu QC không tìm thấy consumer thực.
7. Chưa xóa `mcp.sample.json`; phải xác nhận server ngoài repo có còn dùng cho QC.

### Phương án 2: cập nhật phần hữu dụng

1. `.agents/AGENTS.md` chỉ giữ policy và pointer; workflow chi tiết chỉ nằm tại
   `workflows/plan-qc-implement.md`.
2. Đồng bộ roles, workflow, architecture và template: Gemini/Antigravity triển
   khai sau `QC: PASS`; verifier kiểm tra diff/test độc lập.
3. Chuẩn hóa QC enum: `PENDING | FAIL | CONDITIONAL_PASS | PASS`.
4. Template QC ghi role/provider động, không hardcode phiên bản model.
5. `ARCHITECTURE.md` chỉ mô tả sáu tầng và trỏ workflow, không lặp ownership.
6. `context/project.md` phân biệt legacy minimum, runtime local đã kiểm tra và
   production compatibility cần xác minh trước khi dùng cú pháp PHP mới.
7. Bổ sung convention đã xác minh: custom module; optional `my_routes.php` /
   `my_hooks.php`; Query Builder + `db_prefix()`; DBForge convention; migration
   version đọc từ config; permission, CSRF, escaping và language files.
8. Viết lại `docs/agents/domain.md` để trỏ `.agents/context/*`; bỏ pointer hỏng.
9. Rút gọn `ui/SKILL.md` về rule có bằng chứng. CSS variable phải được định nghĩa
   trên root component/module trước khi dùng; không giả định global `--crm-*`.
10. Sửa `code-review`, `diagnosing-bugs` và `tdd`: dùng Plan/QC làm spec; hỗ trợ
    working tree; không trỏ skill thiếu; không cài test framework hoặc tự commit;
    ưu tiên `php -l`, HTTP/browser check và test có sẵn trong module.
11. Cập nhật `audit-agent-environment`: kiểm tra pointer hỏng, ownership xung đột,
    JSON, launcher và discovery path; bỏ yêu cầu mọi tầng phải có sample.
12. Giữ `OBSERVABILITY.md` nhưng tách rõ IDE, CLI status line và MCP Inspector;
    trạng thái thiếu `agy` phải là kết quả audit có ngày, không là sự thật vĩnh viễn.

## Data, permission and security

- Không đọc hoặc đưa secret từ `app-config.php`, MCP headers, SMTP hay DB vào docs.
- Sample portable không chứa credential hoặc absolute path máy cá nhân.
- GitHub issue chỉ được tạo/sửa khi người dùng yêu cầu; docs không tự cấp quyền
  thay đổi dịch vụ bên ngoài.

## Changes by file

| Action | File / directory | Nội dung |
|---|---|---|
| Update | `.agents/AGENTS.md`, `ARCHITECTURE.md`, `OBSERVABILITY.md` | Rút gọn policy, bỏ cache ownership, sửa mức độ chắc chắn |
| Update | `.agents/context/*.md` | Compatibility, convention và nhãn product target |
| Update | `.agents/rules/engineering.md` | Gộp minimal-change và convention portal_18 |
| Delete | `.agents/rules/formatting-sample.md` | Loại sample khỏi rule active |
| Update | `.agents/roles/*.md` | Ownership, gate và artifact đúng |
| Update | `.agents/workflows/*.md`, `templates/*.md` | Một flow và QC enum thống nhất |
| Delete | `.agents/skills/implement/` | Xung đột governance |
| Delete | `.agents/skills/setup-matt-pocock-skills/` | Setup one-time đã hoàn thành |
| Update | `skills/code-review`, `diagnosing-bugs`, `tdd`, `ui`, `audit-agent-environment` | Bám portal_18 và bỏ pointer hỏng |
| Delete | `.agents/skills/tdd/tests.md`, `mocking.md` | Loại ví dụ TypeScript/Jest |
| Update | `.agents/skills/README.md` | Liệt kê skill còn hoạt động và trigger |
| Update | `docs/agents/domain.md`, `issue-tracker.md` | Trỏ context thật; external state read-only mặc định |
| Conditional delete | `.agents/plugins/`, `.Antigravity/extensions.json` | Chỉ xóa sau QC discovery check |
| Conditional update/delete | `.agents/mcp/mcp.sample.json` | Chờ xác nhận vai trò server QC ngoài repo |

## Failure states and rollback

- Nếu Antigravity mất discovery, khôi phục directory liên quan từ Git và sửa pointer.
- Nếu MCP sample là cầu nối QC đang dùng, giữ nhưng đổi tên rõ là local example;
  không đưa secret vào Git.
- Không suy ra production compatibility từ PHP CLI local.
- Thực hiện theo nhóm nhỏ; sau mỗi nhóm chạy audit read-only và kiểm tra pointer.

## Acceptance criteria

- [ ] Không còn tài liệu nào ghi Codex là implementer mặc định.
- [ ] Codex Plan/update Plan; Gemini/Antigravity QC và implement sau `QC: PASS`.
- [ ] QC metadata chỉ dùng `PENDING | FAIL | CONDITIONAL_PASS | PASS`.
- [ ] Không còn pointer tới skill hoặc file không tồn tại.
- [ ] Không còn ví dụ TypeScript/Jest trong skill test portal_18.
- [ ] UI skill không yêu cầu CSS variable chưa được định nghĩa trong scope.
- [ ] Project context mô tả đúng PHP constraint, không suy đoán production runtime.
- [ ] JSON còn lại hợp lệ; Inspector launcher readable, executable, `bash -n` PASS.
- [ ] `.agents/rules` chỉ chứa rule active có tên rõ nghĩa.
- [ ] Không có secret, credential hoặc absolute path cá nhân trong sample portable.
- [ ] `rg` không còn chuỗi/pointer cũ và `git diff --check` PASS.
- [ ] Gemini/Antigravity ghi delta vào file QC cùng `plan-id`.

## Validation commands

```bash
find .agents -maxdepth 4 -print | sort
rg -n 'Codex.*implement|Codex.*triển khai|implementer: `Codex`' AGENTS.md .agents docs/plans
rg -n 'CONTEXT.md|/codebase-design|/improve-codebase-architecture|/setup-matt-pocock-skills' .agents docs/agents
rg -n 'CONDITIONAL PASS|CONDITIONAL_PASS' .agents docs/plans/_template.md
rg -n 'typescript|jest|createCart|processPayment' .agents/skills/tdd
rg -n -- 'var\(--crm-|--crm-' .agents/skills/ui modules
bash -n scripts/mcp-inspector.sh
test -r scripts/mcp-inspector.sh
test -x scripts/mcp-inspector.sh
git diff --check
```

## Open questions

1. Python và `local_mcp_server.py` mà `mcp.sample.json` trỏ tới đều tồn tại.
   Gemini/Antigravity cần xác nhận server này còn dùng cho QC hay là thử nghiệm cũ.
2. `.Antigravity/extensions.json` là yêu cầu hạ tầng cũ nhưng đang rỗng. QC cần
   xác minh có consumer thực trước khi xóa.

## QC history

| Plan version | QC result | Delta incorporated |
|---|---|---|
| 1 | PENDING | Initial audit and rationalization proposal |
