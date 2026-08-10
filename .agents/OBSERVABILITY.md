# Observability cho Agent

Tài liệu này phân biệt ba nguồn quan sát: Antigravity IDE, Antigravity CLI và MCP Inspector. MCP Inspector chỉ quan sát giao tiếp MCP; nó không thay thế bộ đếm token hay nhật ký toàn bộ công cụ của Agent.

## 1. Antigravity IDE

### Agent và Tool Calls

1. Mở Agent side panel bằng `Cmd + L` trên macOS hoặc từ biểu tượng Agent.
2. Mở từng tool card trong hội thoại để xem lệnh, trạng thái và kết quả.
3. Dùng các nút phía trên ô nhập để mở `Terminal` (tiến trình nền), `Artifacts`, `Changes Overview` và `Browser`.
4. Với subagent, mở Agent Manager rồi chọn subagent để xem tiến trình và chi tiết thực thi.

### MCP

1. Trong Agent side panel, chọn `...` > `MCP Servers`.
2. Chọn `Manage MCP Servers` để xem trạng thái kết nối.
3. Chọn `View raw config` để mở cấu hình workspace `.agents/mcp_config.json`.
4. Dùng `Refresh` sau khi thay đổi cấu hình.

### Token và Context Window

Tài liệu chính thức hiện không xác nhận một bảng IDE hiển thị chính xác đồng thời `input_tokens`, `output_tokens` và kích thước context. Không cài extension bên thứ ba để suy đoán số liệu này. Muốn xem số chính xác, dùng status line của Antigravity CLI như phần dưới.

## 2. Antigravity CLI

Máy phải có lệnh `agy`. Sau khi mở `agy` tại thư mục dự án:

1. Gõ `/statusline` và bật model, task counters và context percentage.
2. Status line có thể cung cấp `total_input_tokens`, `total_output_tokens`, `context_window_size`, phần trăm đã dùng và còn lại.
3. Gõ `/agents`, chọn một subagent và nhấn `Enter` để xem tool calls cùng stdout.
4. Gõ `/tasks` để xem tiến trình nền và log thực thi; gõ `/mcp` để xem trạng thái MCP.

Antigravity CLI chưa có trên máy tại thời điểm thiết lập này. Dự án không tự cài đặt CLI; hãy cài thủ công từ tài liệu chính thức khi bạn phê duyệt.

## 3. MCP Inspector

Launcher của dự án:

```bash
./scripts/mcp-inspector.sh
```

Lệnh mặc định chỉ dùng package đã có trong npm cache và không tải phần mềm. Nếu package chưa có và bạn đồng ý cho `npx` tải tạm:

```bash
./scripts/mcp-inspector.sh --allow-download
```

Inspector mặc định mở client tại `http://localhost:6274` và proxy tại cổng `6277`. Không bind proxy ra `0.0.0.0`. Để inspect một MCP server local:

```bash
./scripts/mcp-inspector.sh --allow-download node /absolute/path/to/server.js
```

Trong Inspector, chọn transport, kết nối server, rồi dùng các tab `Tools`, `Resources` và `Prompts`. Terminal chạy launcher là nơi xem session token, lỗi kết nối và thời gian tồn tại của tiến trình.

## 4. Extension inventory

`.Antigravity/extensions.json` dùng cấu trúc recommendation quen thuộc của editor. Tài liệu chính thức hiện chưa xác nhận đường dẫn này được Antigravity tự động nạp, vì vậy file đóng vai trò inventory cấp dự án. Danh sách đang rỗng có chủ ý: chưa có extension Token/Agent bên thứ ba nào được tài liệu chính thức xác nhận là bắt buộc hoặc chuẩn. Antigravity cung cấp Agent panel, MCP manager, Terminal, Artifacts và Agent Manager dưới dạng tích hợp sẵn.

Tài liệu tham khảo:

- https://antigravity.google/docs/ide-overview
- https://antigravity.google/docs/mcp
- https://antigravity.google/docs/cli-statusline
- https://antigravity.google/docs/cli/commands/agents
- https://modelcontextprotocol.io/docs/tools/inspector
