# Review working tree

Review thay đổi hiện tại mà không sửa file.

1. Đọc `git status --short` và xác định phạm vi file đã đổi.
2. Đọc diff liên quan, ưu tiên lỗi hành vi, rủi ro bảo mật và thiếu kiểm thử.
3. Chạy kiểm tra read-only phù hợp nếu công cụ đã có sẵn; không cài dependency.
4. Báo findings theo mức độ nghiêm trọng với đường dẫn và dòng cụ thể.
5. Nếu không có finding, nêu rõ phần chưa được kiểm thử và rủi ro còn lại.
