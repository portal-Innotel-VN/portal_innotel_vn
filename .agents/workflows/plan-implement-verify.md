# Workflow: Plan → Implement → Verify

## 1. Intake

- Ghi Goal, phạm vi, non-goals và acceptance criteria.
- Chọn `plan-id` dạng kebab-case và tạo `docs/plans/<plan-id>.md` từ template.

## 2. Codex lập Plan

- Đảm nhiệm role Planner.
- Đọc basecode mục tiêu và ghi bằng chứng cụ thể vào Plan.
- Đặt `plan_version: 1`, `plan_status: READY_FOR_IMPLEMENTATION`.

## 3. Implement

- Codex hoặc Gemini/Antigravity đảm nhiệm role Implementer ngay sau khi Plan
  hoàn chỉnh; không cần Quality Control như một giai đoạn hoặc điều kiện chặn.
- Kiểm tra working tree; chỉ sửa đúng phạm vi Plan và bảo toàn thay đổi không
  thuộc task.
- Nếu basecode mâu thuẫn với Plan hoặc phát sinh thay đổi thiết kế, dừng vùng bị
  ảnh hưởng để Codex cập nhật Plan trước khi tiếp tục.
- Ghi implementation report tại `docs/plans/<plan-id>.implementation.md`.

## 4. Verify

- Verifier đọc Plan, implementation report và diff thực tế.
- Chạy syntax/test tương xứng với rủi ro và đối chiếu từng acceptance criterion.
- Nếu có finding, giao Implementer sửa đúng phạm vi Plan, chạy lại kiểm tra rồi
  cập nhật implementation report.
- Chỉ kết thúc khi không còn blocker; ghi rõ kiểm tra nào chưa thể thực hiện.

## Cách giảm token

- Truyền đường dẫn Plan và implementation report, không copy toàn bộ nội dung
  qua chat.
- Khi Plan đổi, chỉ đọc phần delta và vùng ảnh hưởng.
- Báo cáo test bằng lệnh + kết quả ngắn, không dán log không liên quan.
