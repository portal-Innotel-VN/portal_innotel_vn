# Hướng dẫn Manual Link/Unlink

## Manual Link

Dùng khi một Báo giá được tạo standalone nhưng thực chất là revision của Group khác.

Điều kiện chính:

- cùng khách hàng;
- source chỉ có một revision và đang pending;
- source và target khác Group;
- target Accepted chỉ cho Manager/Admin override có lý do.

Sau khi Link, source trở thành revision tiếp theo của Group đích; Group nguồn rỗng được xử lý nhưng audit được giữ lại.

## Manual Unlink

Dùng khi revision mới nhất cần trở thành một nhu cầu độc lập.

- Chỉ Manager/Admin hoặc capability phù hợp.
- Chỉ tách latest revision của Group có hơn một version.
- Không tách decision estimate của Group Accepted.
- Bắt buộc nhập lý do.

Revision được tách trở thành Rev.1 của Group mới; Group cũ không renumber.
