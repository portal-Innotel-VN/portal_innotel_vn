# Context Loading Protocol

Đây là cổng bắt buộc trước mọi hành động có thể làm thay đổi thiết kế, code,
schema, tài liệu đặc tả hoặc kết luận review trong một cuộc hội thoại mới.
Agent phải hoàn tất các bước theo thứ tự; tiêu chí hoàn tất là đã ghi được
nguồn đã đọc, invariant áp dụng, vùng code cần truy vết và các điểm còn chưa
biết.

## 1. Khởi tạo bắt buộc

Đọc các tài liệu nền tảng sau trước khi lập kế hoạch hoặc đưa ra kết luận:

1. [`docs/ai/PROJECT_CONTEXT.md`](../../docs/ai/PROJECT_CONTEXT.md)
2. [`docs/ai/BUSINESS_INVARIANTS.md`](../../docs/ai/BUSINESS_INVARIANTS.md)
3. [`docs/ai/IMPLEMENTATION_STATUS.md`](../../docs/ai/IMPLEMENTATION_STATUS.md)
4. [`docs/ai/CODE_MAP.md`](../../docs/ai/CODE_MAP.md)
5. [`project.md`](project.md) và [`ai-driven-crm.md`](ai-driven-crm.md)

Nếu task thuộc Sales Pipeline, đọc thêm
[`docs/ai/SALES_PIPELINE_CONTEXT.md`](../../docs/ai/SALES_PIPELINE_CONTEXT.md).

## 2. Định tuyến theo loại task

Sau khi đọc nền tảng, chỉ đọc các nhánh liên quan:

| Task | Tài liệu phải đọc |
|---|---|
| Tạo, clone, duplicate hoặc link báo giá | `docs/specifications/CREATE_ESTIMATES_WORKFLOW.md`, `DOMAIN_MODEL.md`, `AUDIT_SECURITY_RULES.md`, `INTEGRATION_CONTRACTS.md` |
| Smart Prompt hoặc candidate báo giá | `CREATE_ESTIMATES_WORKFLOW.md`, `AUDIT_SECURITY_RULES.md`, `SALES_PIPELINE_CONTEXT.md` |
| KPI/performance score/dashboard | `docs/specifications/PERFORMANCE_SCORE.md`, `docs/specifications/Calcu_performance_score.md`, kế hoạch Sales Pipeline liên quan |
| Deal–Estimate Group | `docs/specifications/DEAL_BRIDGE.md`, `DOMAIN_MODEL.md`, `INTEGRATION_CONTRACTS.md` |
| Giao diện/hướng dẫn nhân viên | tài liệu đặc tả tương ứng và `docs/user-guides/` tương ứng |
| Migration, audit hoặc security | `AUDIT_SECURITY_RULES.md`, `DATA_DICTIONARY.md`, `INTEGRATION_CONTRACTS.md` |

Đọc kế hoạch trong `docs/plans/` khi cần hiểu quyết định lịch sử, phạm vi đã
cam kết hoặc trạng thái triển khai. Kế hoạch không thay thế bằng chứng từ
Basecode và không được dùng để suy ra tính năng đã tồn tại.

## 3. Xác minh Basecode

Đối chiếu các tuyên bố trong tài liệu với file, class, function, route, hook,
view và migration thực tế được chỉ ra trong `CODE_MAP.md`. Khi tài liệu và
Basecode khác nhau, Basecode hiện hành là bằng chứng runtime; ghi nhận sai lệch
và không tự bịa phần còn thiếu. Với thay đổi code, tiếp tục truy vết
route → hook → controller → model → view và permission/data scope.

## 4. Báo cáo context ngắn

Trước khi hành động, nêu trong phần làm việc (hoặc implementation report):

- **Đã đọc:** danh sách file theo đường dẫn.
- **Invariant áp dụng:** các quy tắc nghiệp vụ và security liên quan.
- **Vùng ảnh hưởng:** file/class/endpoint/schema cần kiểm tra.
- **Trạng thái hiện tại:** đã triển khai, chưa triển khai hoặc chưa xác minh.
- **Khoảng trống:** câu hỏi cần kiểm tra thêm; không biến giả định thành sự thật.

Khi task chỉ là tài liệu, vẫn phải đọc tài liệu nền tảng và kiểm tra Basecode ở
mức đủ để tránh ghi sai trạng thái. Khi task là review/diagnosis, không sửa gì
cho đến khi context và bằng chứng đã được xác lập.

## 5. Tiêu chí hoàn tất

Context initialization chỉ hoàn tất khi mọi file nền tảng và các nhánh bắt buộc
đã được đọc, Basecode liên quan đã được xác định, và không còn tuyên bố quan
trọng nào chỉ dựa trên một kế hoạch cũ hoặc suy đoán.
