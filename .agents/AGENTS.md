# Agent Operating Contract — portal_18

Khi bắt đầu một cuộc hội thoại hoặc task mới trong workspace này, agent phải
chạy [`Context Loading Protocol`](context/context-loading.md) trước khi lập kế
hoạch, đọc kết quả triển khai, sửa code, sửa tài liệu hoặc kết luận review.

Protocol này là điểm vào duy nhất để định tuyến đến:

- `context/`: context ổn định và quy trình nạp context;
- `../docs/ai/`: bản đồ nghiệp vụ, invariant, trạng thái và code map;
- `../docs/specifications/`: đặc tả chuẩn;
- `../docs/user-guides/`: hành vi được giải thích cho người dùng;
- `../docs/plans/`: quyết định và phạm vi triển khai theo từng kế hoạch.

Sau khi đọc, agent phải xác minh tuyên bố quan trọng bằng Basecode và ghi ngắn
gọn file đã đọc, invariant áp dụng, vùng ảnh hưởng, trạng thái hiện tại và các
khoảng trống. Không dùng Plan cũ làm bằng chứng runtime khi chưa đối chiếu code.

Các quy tắc kỹ thuật nằm ở `rules/`, role ở `roles/`, workflow ở `workflows/`.
