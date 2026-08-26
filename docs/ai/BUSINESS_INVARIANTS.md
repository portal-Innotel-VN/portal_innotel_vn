# Business Invariants

Các quy tắc sau không được thay đổi ngầm:

1. Estimate Group là một nhu cầu Báo giá logic; revision không tăng quote count.
2. Không link Estimate khác khách hàng.
3. Heuristic không bao giờ tự động merge.
4. Staff không append vào Group Accepted.
5. Manager/Admin override Accepted phải có lý do và audit.
6. Group owner KPI không đổi khi append revision.
7. Accepted revenue thuộc decision owner.
8. Revision number dùng `MAX + 1` và không renumber sau xóa/unlink.
9. Explicit link thất bại phải fallback rõ ràng; không tự chọn target khác.
10. Audit không bị xóa khi Group nguồn rỗng.
11. Deal không phải điều kiện grouping và không được xóa lịch sử Estimate.
12. Rank Staff được tính trên full cohort trước role projection.
