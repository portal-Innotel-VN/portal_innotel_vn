# Hướng dẫn đọc Điểm hiệu suất

Điểm hiệu suất tổng hợp các yếu tố sau:

- **Số Báo giá logic** (trọng số chuẩn 20%): đánh giá sản lượng Báo giá mới;
- **Doanh thu Báo giá Accepted** (trọng số chuẩn 40%): ghi nhận doanh thu từ Báo giá thành công;
- **Tỷ lệ chấp nhận** (trọng số chuẩn 25%): đo lường hiệu quả chốt Báo giá;
- **Phản hồi nhắc nhở đúng hạn** (trọng số chuẩn 15%): tỷ lệ phản hồi các nhắc nhở bắt buộc đúng hạn SLA (mặc định 24 giờ, target 90%).

Nếu trong kỳ nhân viên không có nhắc nhở bắt buộc phản hồi nào, hệ thống tự động chuyển thành phần này sang **Không áp dụng** và tính điểm trên tổng trọng số 85%. Mỗi thành phần được chuẩn hóa về thang tối đa 120 điểm rồi nhân trọng số tương ứng.

Clone/Duplicate không tăng số Báo giá nếu vẫn thuộc cùng Estimate Group. Doanh thu được ghi cho owner của revision được Accepted.

Badge **Tạm tính** xuất hiện khi thiếu tỷ giá hoặc số Báo giá đóng còn quá ít. Admin xem toàn bộ bảng xếp hạng; Staff chỉ xem điểm và hạng của mình.

Để xem một kỳ cũ, nhấp vào phạm vi ngày cạnh biểu tượng lịch trên Dashboard, chọn Tuần/Tháng/Quý/Năm và chọn một ngày thuộc kỳ cần xem. Dashboard tự mở rộng ngày đó thành trọn kỳ và giữ nguyên target của loại kỳ đã chọn.

Chi tiết công thức: [PERFORMANCE_SCORE.md](../specifications/PERFORMANCE_SCORE.md).
