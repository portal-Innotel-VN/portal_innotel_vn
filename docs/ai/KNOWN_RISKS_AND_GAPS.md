# Known Risks and Gaps

Danh sách này là checklist rà soát, không mặc định khẳng định lỗi vẫn còn. Agent phải kiểm tra Basecode hiện tại:

- Fallback Group và audit có thực sự atomic không.
- Permission source/target/history có được kiểm tra ở server không.
- Schema bootstrap có đủ table variables, columns và indexes không.
- Deal delete/move/unlink có dọn bridge và resync cả Deal cũ/mới không.
- Migration 109 đã chạy ở môi trường đích chưa.
- Contract tests có bị diễn giải nhầm thành DB integration tests không.
- `git diff --check` và PHP lint có pass trên worktree hiện tại không.
- Ranking path và dashboard metric cũ có dùng cùng định nghĩa valid quote không.
- Reminder response vẫn inactive trong Performance Score v1.

Khi một rủi ro được xác minh đã xử lý, cập nhật file này cùng bằng chứng test.
