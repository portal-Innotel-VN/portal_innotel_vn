<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Module name
$lang['sales_pipeline']           = 'Tiến Độ Kinh Doanh';
$lang['sales_pipeline_new_deal']  = 'Thêm Deal Mới';
$lang['sales_pipeline_edit_deal'] = 'Sửa Deal';

// Alerts
$lang['sales_pipeline_deal_added']   = 'Thêm deal thành công!';
$lang['sales_pipeline_deal_updated'] = 'Cập nhật deal thành công!';
$lang['sales_pipeline_deal_deleted'] = 'Xóa deal thành công!';

// Form labels
$lang['sales_pipeline_customer_info']    = 'Thông Tin Khách Hàng';
$lang['sales_pipeline_customer_name']    = 'Tên công ty';
$lang['sales_pipeline_contact_name']     = 'Người liên hệ';
$lang['sales_pipeline_contact_phone']    = 'Số điện thoại';
$lang['sales_pipeline_contact_email']    = 'Email';
$lang['sales_pipeline_source']           = 'Nguồn khách hàng';
$lang['sales_pipeline_select_source']    = 'Chọn nguồn';

$lang['sales_pipeline_deal_info']        = 'Thông Tin Deal';
$lang['sales_pipeline_deal_name']        = 'Mô tả sản phẩm/dịch vụ';
$lang['sales_pipeline_deal_value']       = 'Doanh số';
$lang['sales_pipeline_profit_margin']    = 'Lợi nhuận';
$lang['sales_pipeline_expected_profit']  = 'Lợi nhuận dự kiến';
$lang['sales_pipeline_expected_date']    = 'Ngày tạo';
$lang['sales_pipeline_profit']           = 'Lợi nhuận';

$lang['sales_pipeline_status_progress']  = 'Trạng Thái & Tiến Độ';
$lang['sales_pipeline_status']           = 'Trạng thái';
$lang['sales_pipeline_contract_signed']  = 'Đã ký hợp đồng';
$lang['sales_pipeline_invoice_issued']   = 'Đã xuất hóa đơn';
$lang['sales_pipeline_progress_note']    = 'Ghi chú tiến độ';
$lang['sales_pipeline_activity_description'] = 'Nội dung cập nhật';
$lang['sales_pipeline_activity_timeline'] = 'Lịch sử cập nhật';

$lang['sales_pipeline_reminder_settings']  = 'Nhắc Nhở & Phân Công';
$lang['sales_pipeline_reminder_enabled']   = 'Bật nhắc nhở tự động';
$lang['sales_pipeline_reminder_frequency'] = 'Tần suất nhắc nhở';
$lang['sales_pipeline_reminder_days']      = 'ngày/lần';
$lang['sales_pipeline_assigned_staff']     = 'Nhân viên phụ trách';

// Actions
$lang['sales_pipeline_save_deal'] = 'Lưu Deal';
$lang['sales_pipeline_back']      = 'Quay Lại';

// Summary
$lang['sales_pipeline_total_deals'] = 'Tổng deal';
$lang['sales_pipeline_total_value'] = 'Tổng doanh số';
$lang['sales_pipeline_won']         = 'Đã chốt';
$lang['sales_pipeline_lost']        = 'Khách từ chối';
$lang['sales_pipeline_active']      = 'Đang theo dõi';
$lang['sales_pipeline_view_all']    = 'Xem tất cả';

// Filter
$lang['sales_pipeline_filter_quarter'] = 'Lọc theo quý';
$lang['sales_pipeline_all_quarters']   = 'Tất cả quý';
$lang['sales_pipeline_all_years']      = 'Tất cả các năm';
$lang['sales_pipeline_filter_staff']   = 'Lọc theo nhân viên';
$lang['sales_pipeline_all_staff']      = 'Tất cả nhân viên';

// Import
$lang['sales_pipeline_import']          = 'Import Excel';
$lang['sales_pipeline_import_excel']    = 'Upload Excel';
$lang['sales_pipeline_import_note']     = 'Upload file Excel báo cáo kinh doanh';
$lang['sales_pipeline_import_format']   = 'Định dạng các trường có cấu trúc chuẩn theo tệp dưới đây';
$lang['sales_pipeline_select_file']     = 'Chọn file Excel';
$lang['sales_pipeline_drag_file']       = 'Kéo thả file vào đây hoặc click để chọn';
$lang['sales_pipeline_file_types']      = 'Định dạng hỗ trợ';
$lang['sales_pipeline_upload_import']   = 'Upload & Import';
$lang['sales_pipeline_import_success']  = 'Import thành công %s deal!';

// Table
$lang['sales_pipeline_no_deals'] = 'Chưa có deal nào. Hãy thêm deal mới hoặc upload Excel.';

// Notification / Reminder
$lang['sales_pipeline_reminder'] = 'Nhắc nhở Deal của bạn';

// Settings
$lang['sales_pipeline_settings'] = 'Cài đặt';

// Cost Price (NEW - for new business model)
$lang['sales_pipeline_cost_price'] = 'Giá nhập';
$lang['sales_pipeline_update_cost_price'] = 'Cập nhật Giá Nhập';
$lang['sales_pipeline_fill_cost_price'] = 'Điền giá nhập';
$lang['sales_pipeline_enter_cost_price'] = 'Nhập giá nhập (VNĐ)';
$lang['sales_pipeline_cost_price_hint'] = 'Giá nhập là chi phí mua/sản xuất sản phẩm. Lợi nhuận = Giá bán - Giá nhập';
$lang['sales_pipeline_missing_cost_price_hint'] = 'Deal này chưa có giá nhập. Vui lòng cập nhật để tính lợi nhuận chính xác.';
$lang['sales_pipeline_not_filled'] = 'Chưa điền';
$lang['sales_pipeline_profit_preview'] = 'Xem trước Lợi Nhuận';
$lang['sales_pipeline_invalid_cost_price'] = 'Vui lòng nhập giá nhập hợp lệ (số dương)';
$lang['sales_pipeline_missing_cost_prices'] = 'Danh Sách Deal Thiếu Giá Nhập';
$lang['sales_pipeline_missing_cost_alert'] = 'Cảnh báo: Deal này thiếu giá nhập';

// Permissions
$lang['sales_pipeline_permission_view_deal_details'] = 'Xem chi tiết deal';

// Validation Error Messages
$lang['sales_pipeline_validation_customer_name_required']        = 'Vui lòng nhập Tên công ty trên Giấy đăng ký kinh doanh';
$lang['sales_pipeline_validation_customer_name_maxlength']       = 'Tên công ty không được vượt quá 255 ký tự';
$lang['sales_pipeline_validation_contact_name_maxlength']        = 'Tên người liên hệ không được vượt quá 100 ký tự';
$lang['sales_pipeline_validation_contact_phone_digits']         = 'Số điện thoại chỉ được chứa các chữ số';
$lang['sales_pipeline_validation_contact_phone_maxlength']        = 'Số điện thoại không được vượt quá 10 ký tự';
$lang['sales_pipeline_validation_contact_email_email']           = 'Vui lòng nhập địa chỉ email hợp lệ';
$lang['sales_pipeline_validation_contact_email_maxlength']        = 'Địa chỉ email không được vượt quá 100 ký tự';
$lang['sales_pipeline_validation_deal_name_required']            = 'Vui lòng nhập Mô tả sản phẩm/dịch vụ';
$lang['sales_pipeline_validation_deal_name_maxlength']           = 'Mô tả sản phẩm/dịch vụ không được vượt quá 500 ký tự';
$lang['sales_pipeline_validation_deal_value_required']           = 'Vui lòng nhập Doanh số (VNĐ)';
$lang['sales_pipeline_validation_deal_value_numeric']            = 'Doanh số phải là số';
$lang['sales_pipeline_validation_deal_date_required']            = 'Vui lòng chọn Ngày tạo deal';
$lang['sales_pipeline_validation_status_required']               = 'Vui lòng chọn Trạng thái deal';
$lang['sales_pipeline_validation_activity_description_maxlength'] = 'Ghi chú tiến độ không được vượt quá 2000 ký tự';

// Controller & System Messages
$lang['sales_pipeline_invalid_deal']                 = 'Hợp đồng không hợp lệ hoặc không tồn tại.';
$lang['sales_pipeline_template_file_not_found']     = 'File template không tồn tại. Vui lòng liên hệ bộ phận IT.';
$lang['sales_pipeline_invalid_staff']                = 'Nhân viên được chọn không hợp lệ hoặc đã ngừng hoạt động.';
$lang['sales_pipeline_cannot_delete_status_in_use'] = 'Không thể xóa Trạng thái này vì đang có Deal sử dụng.';
$lang['sales_pipeline_cannot_delete_source_in_use'] = 'Không thể xóa Nguồn này vì đang có Deal sử dụng.';
$lang['sales_pipeline_invalid_deal_id']             = 'ID deal không hợp lệ.';
$lang['sales_pipeline_missing_id']                  = 'Thiếu ID bắt buộc.';
$lang['sales_pipeline_deal_not_found']              = 'Deal không tồn tại.';
$lang['sales_pipeline_no_permission_update_cost_price'] = 'Bạn không có quyền cập nhật giá nhập cho deal này.';
$lang['sales_pipeline_cost_price_updated']          = 'Cập nhật giá nhập thành công.';
$lang['sales_pipeline_update_failed']                = 'Cập nhật thất bại. Vui lòng thử lại.';
$lang['sales_pipeline_missing_deal_or_status']       = 'Thiếu thông tin deal hoặc trạng thái.';
$lang['sales_pipeline_no_permission_update_deal']   = 'Bạn không có quyền cập nhật deal này.';
$lang['sales_pipeline_status_updated']              = 'Đã cập nhật trạng thái deal thành công.';
$lang['sales_pipeline_status_update_failed']         = 'Không thể cập nhật trạng thái deal.';

// Import Library Messages
$lang['sales_pipeline_import_only_excel_supported'] = 'Chỉ hỗ trợ file .xls hoặc .xlsx';
$lang['sales_pipeline_import_file_too_large']       = 'File quá lớn. Dung lượng tối đa cho phép là %d MB.';
$lang['sales_pipeline_import_upload_failed']       = 'Không thể upload file. Vui lòng thử lại.';
$lang['sales_pipeline_import_no_valid_data']       = 'File Excel không có dữ liệu hợp lệ (vùng dữ liệu trống).';
$lang['sales_pipeline_import_result_summary']       = 'Import thành công: %d tạo mới, %d cập nhật, %d bỏ qua.';
$lang['sales_pipeline_import_read_error']           = 'Lỗi đọc file';

// Views & UI Confirm Messages
$lang['sales_pipeline_load_more_failed']            = 'Có lỗi xảy ra khi tải thêm deal';
$lang['sales_pipeline_please_select_excel_file']     = 'Vui lòng kéo thả hoặc click chọn tệp tin Excel (.xls, .xlsx) trước khi nhấn Import.';
$lang['sales_pipeline_confirm_contract_received']   = 'Bạn đã cầm Hợp đồng bản cứng trên tay chưa?';
$lang['sales_pipeline_confirm_invoice_issued']      = 'Bạn đã xác nhận việc Kế toán đã xuất VAT chưa?';

// Additional UI / Log translations
$lang['sales_pipeline_quarter'] = 'Quý';
$lang['sales_pipeline_vnd'] = 'VNĐ';
$lang['sales_pipeline_details'] = 'Chi tiết';
$lang['sales_pipeline_no_cost_price_yet'] = 'Chưa có giá nhập';
$lang['sales_pipeline_import_template_instruction_1'] = 'Sử dụng file Template chuẩn để đảm bảo dữ liệu nhập đúng định dạng.';
$lang['sales_pipeline_import_template_instruction_2'] = 'File đã có sẵn Data Validation và hướng dẫn điền đầy đủ.';
$lang['sales_pipeline_download_template'] = 'Tải Template (.xlsx)';
$lang['sales_pipeline_q1'] = 'Quý 1 (T1-3)';
$lang['sales_pipeline_q2'] = 'Quý 2 (T4-6)';
$lang['sales_pipeline_q3'] = 'Quý 3 (T7-9)';
$lang['sales_pipeline_q4'] = 'Quý 4 (T10-12)';
$lang['sales_pipeline_actions'] = 'Thao tác';
$lang['sales_pipeline_no_missing_cost_prices'] = 'Không có thương vụ nào thiếu giá nhập trong bộ lọc này.';
$lang['sales_pipeline_enter_price'] = 'Điền giá';
$lang['sales_pipeline_pagination_showing'] = 'Hiển thị';
$lang['sales_pipeline_pagination_to'] = 'đến';
$lang['sales_pipeline_pagination_of_total'] = 'trong tổng số';
$lang['sales_pipeline_pagination_deals'] = 'thương vụ';
$lang['sales_pipeline_pagination_prev'] = 'Trước';
$lang['sales_pipeline_pagination_next'] = 'Sau';
$lang['sales_pipeline_settings_statuses'] = 'Trạng Thái';
$lang['sales_pipeline_settings_sources'] = 'Nguồn Khách Hàng';
$lang['sales_pipeline_add_status'] = 'Thêm Trạng Thái';
$lang['sales_pipeline_add_source'] = 'Thêm Nguồn';
$lang['sales_pipeline_status_name'] = 'Tên Trạng Thái';
$lang['sales_pipeline_status_color'] = 'Màu Sắc';
$lang['sales_pipeline_status_order'] = 'Thứ Tự';
$lang['sales_pipeline_status_is_won'] = 'Trạng Thái Thắng (Won)';
$lang['sales_pipeline_status_is_lost'] = 'Trạng Thái Thua (Lost)';
$lang['sales_pipeline_options'] = 'Tùy Chọn';
$lang['sales_pipeline_source_name'] = 'Tên Nguồn';

// Activity Logs / Model translations
$lang['sales_pipeline_activity_deal_created'] = 'Tạo deal mới: %s';
$lang['sales_pipeline_activity_new_deal'] = 'Deal mới';
$lang['sales_pipeline_log_new_deal'] = 'Sales Pipeline - Deal mới [ID: %s] %s';
$lang['sales_pipeline_activity_status_changed'] = 'Đổi trạng thái: %s → %s';
$lang['sales_pipeline_log_update_deal'] = 'Sales Pipeline - Cập nhật deal [ID: %s]';
$lang['sales_pipeline_log_delete_deal'] = 'Sales Pipeline - Xóa deal [ID: %s] %s';
$lang['sales_pipeline_status_unknown'] = 'Không xác định';
$lang['sales_pipeline_activity_cost_price_updated_from_null'] = 'Cập nhật Giá nhập: %s VNĐ (từ NULL)';
$lang['sales_pipeline_activity_cost_price_updated'] = 'Cập nhật Giá nhập: %s → %s VNĐ';
$lang['sales_pipeline_log_update_cost_price'] = 'Sales Pipeline - Cập nhật giá nhập cho deal [ID: %s]';
$lang['sales_pipeline_activity_deal_imported'] = 'Tạo Deal thành công qua import';
$lang['sales_pipeline_edit_status'] = 'Sửa Trạng Thái';
$lang['sales_pipeline_edit_source'] = 'Sửa Nguồn';
$lang['sales_pipeline_add_source_title'] = 'Thêm Nguồn Khách Hàng';
$lang['sales_pipeline_edit_source_title'] = 'Sửa Nguồn Khách Hàng';
$lang['sales_pipeline_reminder_responded'] = 'Đã phản hồi nhắc nhở thành công.';
$lang['sales_pipeline_quick_response_title'] = 'Phản hồi nhanh nhắc nhở';
$lang['sales_pipeline_reminder_estimate'] = 'Báo giá';
$lang['sales_pipeline_reminder_deal'] = 'Deal';
$lang['sales_pipeline_reminder_message'] = 'Nội dung nhắc nhở';
$lang['sales_pipeline_your_response'] = 'Nội dung phản hồi';
$lang['sales_pipeline_response_placeholder'] = 'Cập nhật kết quả, vướng mắc hoặc lý do deal chưa thể đóng...';
$lang['sales_pipeline_response_max_length'] = 'Tối đa 2.000 ký tự. Phản hồi chỉ được gửi một lần.';
$lang['sales_pipeline_send_response'] = 'Gửi phản hồi';
$lang['sales_pipeline_view_deal'] = 'Đến trang Deal để thêm Ghi chú';
$lang['sales_pipeline_response_sent'] = 'Phản hồi này đã được gửi.';
$lang['sales_pipeline_response_locked_help'] = 'Phản hồi nhắc nhở không thể sửa hoặc gửi lại. Bạn có thể đến trang Deal để thêm ghi chú tiến độ mới.';
$lang['sales_pipeline_reminder_response_invalid'] = 'Nội dung phản hồi không được để trống và không được vượt quá 2.000 ký tự.';
$lang['sales_pipeline_reminder_already_responded'] = 'Reminder này đã được phản hồi trước đó.';
$lang['sales_pipeline_reminder_not_found'] = 'Không tìm thấy reminder hoặc deal tương ứng.';
$lang['sales_pipeline_reminder_response_failed'] = 'Không thể lưu phản hồi. Vui lòng thử lại.';
$lang['sales_pipeline_activity_reminder_response'] = 'Phản hồi nhắc nhở:';
$lang['please_wait'] = 'Vui lòng chờ...';
$lang['something_went_wrong'] = 'Có lỗi xảy ra, vui lòng thử lại!';

// Dashboard & KPI Translations (No Emoji)
$lang['sales_pipeline_dashboard'] = 'Trung tâm thông tin';
$lang['sales_pipeline_dashboard_title'] = 'Hiệu suất kinh doanh';
$lang['sales_pipeline_kpi_open_value'] = 'Doanh Số Kỳ Vọng';
$lang['sales_pipeline_kpi_gross_profit'] = 'Lợi Nhuận Gộp Kỳ Vọng';
$lang['sales_pipeline_kpi_win_rate'] = 'Tỷ Lệ Thắng Deal';
$lang['sales_pipeline_kpi_avg_cycle'] = 'Chu Kỳ Bán Hàng TB';
$lang['sales_pipeline_kpi_stale_deals'] = 'Deal Tồn Đọng Chậm Tiến Độ';
$lang['sales_pipeline_kpi_response_rate'] = 'Tỷ Lệ Phản Hồi Nhắc Nhở';
$lang['sales_pipeline_funnel_chart'] = 'Phễu Chuyển Đổi Sales Pipeline';
$lang['sales_pipeline_staff_performance'] = 'Bảng Hiệu Suất Nhân Viên Kinh Doanh';
$lang['sales_pipeline_reminder_response_tracker'] = 'Bảng Phản Hồi Nhắc Nhở Tự Động';
$lang['sales_pipeline_widget_title'] = 'Tổng Quan Sales Pipeline';
$lang['sales_pipeline_view_full_dashboard'] = 'Xem Dashboard Chi Tiết';
$lang['sales_pipeline_view_pipeline'] = 'Xem Bảng Pipeline';
$lang['sales_pipeline_days'] = 'ngày';
$lang['sales_pipeline_deals'] = 'deals';
$lang['sales_pipeline_dashboard_refresh'] = 'Làm mới số liệu';
$lang['sales_pipeline_dashboard_estimates_today'] = 'Tổng báo giá hôm nay';
$lang['sales_pipeline_dashboard_estimates_month'] = 'Tổng báo giá trong tháng';
$lang['sales_pipeline_dashboard_revenue_week'] = 'Giá trị deal thắng tuần này';
$lang['sales_pipeline_dashboard_revenue_month'] = 'Giá trị deal thắng tháng này';
$lang['sales_pipeline_dashboard_status_success'] = 'Đạt mục tiêu';
$lang['sales_pipeline_dashboard_leaderboard'] = 'Bảng xếp hạng';
$lang['sales_pipeline_dashboard_actionable_feed'] = 'Nhật ký hoạt động';
$lang['sales_pipeline_dashboard_reminder_pending'] = 'Chưa phản hồi';
$lang['sales_pipeline_dashboard_reminder_responded'] = 'Đã phản hồi';
$lang['sales_pipeline_dashboard_staff_response'] = 'Phản hồi của nhân viên';
$lang['sales_pipeline_dashboard_reminder_sent_at'] = 'Thời gian nhắc';
$lang['sales_pipeline_dashboard_response_time'] = 'Thời gian phản hồi';
$lang['sales_pipeline_dashboard_no_reminder_responses'] = 'Chưa có phản hồi nhắc nhở.';
$lang['sales_pipeline_dashboard_rank'] = 'Hạng';
$lang['sales_pipeline_dashboard_staff'] = 'Nhân viên';
$lang['sales_pipeline_dashboard_view_staff'] = 'Mở Pipeline của nhân viên';
$lang['sales_pipeline_dashboard_open_deals'] = 'Thương vụ đang mở';
$lang['sales_pipeline_dashboard_no_staff'] = 'Chưa có dữ liệu hiệu suất trong phạm vi bạn được xem.';
$lang['sales_pipeline_dashboard_loading'] = 'Đang tải Pipeline nhân viên...';
$lang['sales_pipeline_dashboard_load_failed'] = 'Không thể tải Pipeline nhân viên. Vui lòng thử lại.';
$lang['sales_pipeline_dashboard_close'] = 'Đóng bảng chi tiết';
$lang['sales_pipeline_dashboard_staff_pipeline'] = 'Thông tin chi tiết';
$lang['sales_pipeline_rule_reminder'] = '%s — %s';
$lang['sales_pipeline_reminder_estimate_period'] = 'Kỳ Báo giá';
$lang['sales_pipeline_reminder_context'] = 'Ngữ cảnh lời nhắc';
$lang['sales_pipeline_reminder_informational'] = 'Chỉ thông báo';
$lang['sales_pipeline_reminder_informational_help'] = 'Lời nhắc này không yêu cầu giải trình. Hãy mở Báo giá để kiểm tra và xử lý khi cần.';
$lang['sales_pipeline_reminder_manager_read_only'] = 'Bạn có thể xem nội dung nhưng chỉ nhân viên được nhắc mới có thể gửi phản hồi.';
$lang['sales_pipeline_reminder_response_not_required'] = 'Lời nhắc này chỉ cung cấp thông tin và không nhận phản hồi.';
$lang['sales_pipeline_go_to_estimate'] = 'Đi tới Báo giá';
$lang['sales_pipeline_reminder_email_view_entity'] = 'Mở đối tượng liên quan';
$lang['sales_pipeline_deal_frequency_message'] = 'Thương vụ %s của khách hàng %s đang đến hạn cập nhật. Vui lòng báo cáo tiến độ và kế hoạch tiếp theo.';
$lang['sales_pipeline_estimate_count_message'] = 'Số Báo giá hợp lệ hiện tại là %s, thấp hơn ngưỡng %s. Vui lòng nêu nguyên nhân và kế hoạch xử lý.';
$lang['sales_pipeline_estimate_weekly_message'] = 'Doanh thu Báo giá accepted từ đầu tuần là %s VNĐ, thấp hơn ngưỡng %s VNĐ.';
$lang['sales_pipeline_estimate_lifecycle_message'] = '%s — %s cần được theo dõi: %s.';
$lang['sales_pipeline_estimate_lifecycle_draft_title'] = 'Báo giá nháp quá lâu';
$lang['sales_pipeline_estimate_lifecycle_sent_title'] = 'Khách hàng chưa phản hồi Báo giá';
$lang['sales_pipeline_estimate_lifecycle_declined_title'] = 'Báo giá vừa bị từ chối';
$lang['sales_pipeline_estimate_lifecycle_expired_title'] = 'Báo giá đã hết hạn';
$lang['sales_pipeline_estimate_lifecycle_accepted_title'] = 'Báo giá đã chấp nhận chưa xuất hóa đơn';
$lang['sales_pipeline_estimate_risk_draft_too_long'] = 'Bản nháp đã tồn tại quá 3 ngày';
$lang['sales_pipeline_estimate_risk_sent_no_response'] = 'Đã gửi quá 3 ngày hoặc sắp đến hạn nhưng khách chưa phản hồi';
$lang['sales_pipeline_estimate_risk_declined'] = 'Khách hàng từ chối trong 7 ngày gần nhất';
$lang['sales_pipeline_estimate_risk_expired'] = 'Báo giá đã hết hiệu lực';
$lang['sales_pipeline_estimate_risk_accepted_not_invoiced'] = 'Báo giá đã được chấp nhận nhưng chưa xuất hóa đơn';
$lang['sales_pipeline_staff_follow_up_customers'] = 'Cần theo dõi';
$lang['sales_pipeline_no_follow_up_customers'] = 'Hiện không có Báo giá rủi ro cần theo dõi.';
$lang['sales_pipeline_dashboard_contract'] = 'Hợp đồng đã ký';
$lang['sales_pipeline_dashboard_invoice'] = 'Hóa đơn đã xuất';
$lang['sales_pipeline_dashboard_more_deals'] = 'Xem thêm %s deal';
$lang['sales_pipeline_dashboard_no_deals'] = 'Chưa có deal ở trạng thái này';

// Reminder snapshot & email
$lang['sales_pipeline_reminder_default_addressee'] = 'Anh/Chị';
$lang['sales_pipeline_reminder_snapshot_greeting'] = 'Chào %s, %s này chưa thấy tiến triển mới trong tuần.';
$lang['sales_pipeline_reminder_snapshot_intro'] = 'Bạn vui lòng cập nhật nhanh:';
$lang['sales_pipeline_reminder_snapshot_yesterday_label'] = 'Hôm qua:';
$lang['sales_pipeline_reminder_snapshot_yesterday_body'] = 'Bạn đã xử lý xong những việc gì?';
$lang['sales_pipeline_reminder_snapshot_today_label'] = 'Hôm nay:';
$lang['sales_pipeline_reminder_snapshot_today_body'] = 'Trọng tâm công việc bạn sẽ tập trung đẩy mạnh là gì?';
$lang['sales_pipeline_reminder_snapshot_support_label'] = 'Hỗ trợ:';
$lang['sales_pipeline_reminder_snapshot_support_body'] = 'Bạn đang gặp khó khăn gì? (Cần can thiệp về giá, kỹ thuật, hay giấy tờ?)';
$lang['sales_pipeline_reminder_snapshot_pipeline_warning_label'] = 'Cảnh báo Pipeline:';
$lang['sales_pipeline_reminder_snapshot_pipeline_warning_body'] = 'Hiện có %s nào đang bị tắc nghẽn & Lý do chưa chốt được Purchase Order.';
$lang['sales_pipeline_target'] = 'Mục tiêu:';
$lang['sales_pipeline_reminder_revenue'] = 'Doanh số';
$lang['sales_pipeline_reminder_total_amount'] = 'Tổng tiền';
$lang['sales_pipeline_reminder_estimate_expiry'] = 'Hạn báo giá';
$lang['sales_pipeline_reminder_snapshot_target'] = '%s "%s" - %s (%s: %s):';
$lang['sales_pipeline_reminder_snapshot_target_question'] = 'Kết quả tuần này thế nào?';
$lang['sales_pipeline_reminder_view_entity'] = 'Xem chi tiết %s';
$lang['sales_pipeline_reminder_email_subject'] = '[Thông báo từ CRM] %s';
$lang['sales_pipeline_reminder_email_greeting'] = 'Kính chào %s,';
$lang['sales_pipeline_reminder_email_intro'] = 'Hệ thống CRM xin thông báo Anh/Chị có một thương vụ cần được cập nhật tiến độ. Vui lòng kiểm tra thông tin dưới đây và phản hồi để dữ liệu Pipeline luôn chính xác, hỗ trợ việc phối hợp và theo dõi được kịp thời.';
$lang['sales_pipeline_reminder_email_type'] = 'Loại';
$lang['sales_pipeline_reminder_email_instruction'] = 'Vui lòng nhấp vào %s hoặc xem chi tiết %s để cập nhật trạng thái.';
$lang['sales_pipeline_reminder_email_quick_response'] = 'Phản hồi nhanh';
$lang['sales_pipeline_reminder_email_view_deal'] = 'Xem deal';

// List, filters and shared UI
$lang['sales_pipeline_settings_sources_statuses'] = 'Cài đặt Nguồn & Trạng Thái';
$lang['sales_pipeline_missing_cost_count_alert'] = 'Có %s thương vụ (deal) đang thiếu thông tin giá nhập.';
$lang['sales_pipeline_view_and_update_now'] = '[Xem và cập nhật ngay]';
$lang['sales_pipeline_business_overview'] = 'Tổng quan Kinh Doanh';
$lang['sales_pipeline_switch_to_kanban'] = 'Chuyển sang Kanban';
$lang['sales_pipeline_switch_to_list'] = 'Chuyển sang Danh sách';
$lang['sales_pipeline_search_placeholder'] = 'Tìm kiếm deal... (Nhấn Enter)';
$lang['sales_pipeline_clear_search'] = 'Xóa tìm kiếm';
$lang['sales_pipeline_filter_document'] = 'Lọc chứng từ';
$lang['sales_pipeline_all_documents'] = 'Tất cả chứng từ';
$lang['sales_pipeline_contract_signed_yes'] = 'Đã ký Hợp Đồng';
$lang['sales_pipeline_contract_signed_no'] = 'Chưa ký Hợp Đồng';
$lang['sales_pipeline_invoice_issued_yes'] = 'Đã xuất Hóa Đơn';
$lang['sales_pipeline_invoice_issued_no'] = 'Chưa xuất Hóa Đơn';
$lang['sales_pipeline_per_page'] = '%s / trang';
$lang['sales_pipeline_sort_by'] = 'Sắp xếp theo:';
$lang['sales_pipeline_sort_deal_value'] = 'Giá trị deal';
$lang['sales_pipeline_sequence_number'] = 'Số TT';
$lang['sales_pipeline_notes'] = 'Ghi chú';
$lang['sales_pipeline_view_full_details'] = 'Xem chi tiết đầy đủ và lịch sử cập nhật';
$lang['sales_pipeline_view_details'] = 'Xem chi tiết';
$lang['sales_pipeline_delete_deal'] = 'Xóa deal';
$lang['sales_pipeline_loading_data'] = 'Đang tải dữ liệu...';
$lang['sales_pipeline_paginated_load_failed'] = 'Không thể tải dữ liệu phân trang.';
$lang['sales_pipeline_server_connection_failed'] = 'Lỗi kết nối máy chủ (%s)! Vui lòng thử lại.';
$lang['sales_pipeline_page_load_failed'] = 'Lỗi tải dữ liệu trang %s';
$lang['sales_pipeline_no_matching_deals'] = 'Không tìm thấy thương vụ nào phù hợp.';
$lang['sales_pipeline_missing_cost_information'] = 'Thiếu thông tin giá nhập';
$lang['sales_pipeline_not_entered'] = 'Chưa nhập';
$lang['sales_pipeline_enter_cost'] = 'Nhập giá nhập';
$lang['sales_pipeline_pagination_summary'] = 'Hiển thị %s đến %s trong tổng số %s deal';
$lang['sales_pipeline_close'] = 'Đóng';
$lang['sales_pipeline_display_order'] = 'Thứ tự hiển thị';
$lang['sales_pipeline_is_won_status'] = 'Là trạng thái Thành Công (Won)';
$lang['sales_pipeline_is_lost_status'] = 'Là trạng thái Thất Bại (Lost)';

// Form placeholders and currency units
$lang['sales_pipeline_customer_name_placeholder'] = 'VD: Công Ty TNHH Viễn Thông Sáng Tạo Thuận Phong - INNOTEL';
$lang['sales_pipeline_contact_name_placeholder'] = 'Tên người liên hệ';
$lang['sales_pipeline_contact_phone_placeholder'] = 'SĐT';
$lang['sales_pipeline_contact_email_placeholder'] = 'Email liên hệ';
$lang['sales_pipeline_deal_name_placeholder'] = 'VD: Báo giá Sangfor NSF1200';
$lang['sales_pipeline_progress_note_placeholder'] = 'Nhập nhật ký trao đổi với khách hàng...';
$lang['sales_pipeline_currency_billion'] = 'tỷ';
$lang['sales_pipeline_currency_million'] = 'triệu';
$lang['sales_pipeline_revenue_target_billion'] = '/ 1 tỷ';
$lang['sales_pipeline_js_locale'] = 'vi-VN';

// Internal activity logs
$lang['sales_pipeline_log_import_read_error'] = 'Import_sales_pipeline: Lỗi đọc Excel — %s';
$lang['sales_pipeline_log_import_date_parse_error'] = 'Import_sales_pipeline: Lỗi parse serial date — %s';

// Staff detail panels
$lang['sales_pipeline_staff_open_deals'] = 'Cần theo dõi';
$lang['sales_pipeline_staff_recent_activity'] = 'Nhật ký hoạt động';
$lang['sales_pipeline_no_open_deals'] = 'Không có thương vụ đang mở cho nhân viên này.';
$lang['sales_pipeline_no_recent_activity'] = 'Không có nhật ký hoạt động nào cho nhân viên này.';
$lang['sales_pipeline_staff_deal_name'] = 'Tên deal';
$lang['sales_pipeline_customer'] = 'Khách hàng';
$lang['sales_pipeline_value'] = 'Giá trị';
$lang['sales_pipeline_date'] = 'Ngày';
$lang['sales_pipeline_time'] = 'Thời gian';

// Dashboard period leaderboard
$lang['sales_pipeline_dashboard_filter_this_week'] = 'Tuần này';
$lang['sales_pipeline_dashboard_filter_this_month'] = 'Tháng này';
$lang['sales_pipeline_dashboard_filter_this_quarter'] = 'Quý này';
$lang['sales_pipeline_dashboard_filter_this_year'] = 'Năm nay';
$lang['sales_pipeline_dashboard_period_quotes'] = 'Báo giá';
$lang['sales_pipeline_dashboard_period_deals'] = 'Thương vụ (Deal)';
$lang['sales_pipeline_dashboard_win_rate'] = 'Tỷ lệ thành công (%%)';
$lang['sales_pipeline_dashboard_period_revenue'] = 'Doanh thu';
$lang['sales_pipeline_dashboard_period_filter'] = 'Kỳ báo cáo';
$lang['sales_pipeline_dashboard_key_metrics'] = 'Chỉ số hiệu suất chính';
$lang['sales_pipeline_dashboard_selected_period'] = 'Kỳ đang chọn';
$lang['sales_pipeline_dashboard_current_snapshot'] = 'Tại thời điểm hiện tại';
$lang['sales_pipeline_dashboard_open_deals_count'] = 'Thương vụ đang mở hiện tại';
$lang['sales_pipeline_dashboard_period_won_deals'] = 'Deal thắng trong kỳ';
$lang['sales_pipeline_dashboard_month_won_value'] = 'Giá trị deal thắng tháng này';
$lang['sales_pipeline_dashboard_period_won_value'] = 'Giá trị deal thắng trong kỳ';
$lang['sales_pipeline_dashboard_total_period_deals'] = 'Tổng deal trong kỳ';
$lang['sales_pipeline_dashboard_won_ratio'] = '%s thắng / %s tổng';

// Dashboard — Estimates (independent from Deals)
$lang['sales_pipeline_dashboard_data_view'] = 'Chọn luồng dữ liệu Dashboard';
$lang['sales_pipeline_dashboard_tab_deals'] = 'Deal / Thương vụ';
$lang['sales_pipeline_dashboard_tab_estimates'] = 'Báo giá';
$lang['sales_pipeline_quote_acceptance_rate'] = 'Tỷ lệ chấp nhận (%%)';
$lang['sales_pipeline_quote_leaderboard'] = 'Bảng xếp hạng';
$lang['sales_pipeline_performance_leaderboard'] = 'Bảng xếp hạng';
$lang['sales_pipeline_performance_leaderboard_description'] = 'Điểm được tổng hợp từ sản lượng, giá trị chấp nhận và hiệu quả Báo giá trong kỳ.';
$lang['sales_pipeline_performance_score'] = 'Điểm hiệu suất';
$lang['sales_pipeline_performance_your_score'] = 'Điểm của bạn';
$lang['sales_pipeline_performance_points'] = 'điểm';
$lang['sales_pipeline_performance_you'] = 'Bạn';
$lang['sales_pipeline_performance_your_rank'] = 'Hạng của bạn';
$lang['sales_pipeline_performance_rank_fraction'] = 'Hạng %s/%s';
$lang['sales_pipeline_performance_provisional'] = 'Tạm tính';
$lang['sales_pipeline_performance_view_details'] = 'Xem cách tính điểm';
$lang['sales_pipeline_performance_breakdown'] = 'Cấu thành Điểm hiệu suất';
$lang['sales_pipeline_performance_quote_component'] = 'Sản lượng báo giá';
$lang['sales_pipeline_performance_revenue_component'] = 'Giá trị được chấp nhận';
$lang['sales_pipeline_performance_acceptance_component'] = 'Tỷ lệ chấp nhận';
$lang['sales_pipeline_performance_reminder_component'] = 'Phản hồi nhắc nhở';
$lang['sales_pipeline_performance_effective_weight'] = 'Trọng số hiệu dụng %s%%';
$lang['sales_pipeline_performance_inactive'] = 'Chưa kích hoạt trong v1';
$lang['sales_pipeline_performance_not_configured_title'] = 'Chưa thể tính Điểm hiệu suất';
$lang['sales_pipeline_performance_not_configured_description'] = 'Kỳ này chưa có mục tiêu hiệu suất. Vui lòng liên hệ quản trị viên.';
$lang['sales_pipeline_performance_empty_admin'] = 'Không có nhân viên đủ điều kiện xếp hạng trong kỳ này.';
$lang['sales_pipeline_performance_empty_staff'] = 'Bạn chưa có dữ liệu Báo giá trong kỳ này.';
$lang['sales_pipeline_quote_acceptance_rate_percent'] = 'Tỷ lệ chấp nhận (%%)';
$lang['sales_pipeline_quote_estimate_count'] = 'Số báo giá';
$lang['sales_pipeline_quote_revenue'] = 'Doanh thu';
$lang['sales_pipeline_quote_revenue_missing_rates_notice'] = '%s báo giá đã chấp nhận chưa có tỷ giá lịch sử nên chưa được cộng vào Doanh thu.';
$lang['sales_pipeline_quote_rate_fraction'] = '%s / %s đã đóng';
$lang['sales_pipeline_quote_empty_title'] = 'Chưa có dữ liệu báo giá trong phạm vi đang xem.';
$lang['sales_pipeline_quote_empty_description'] = 'Tạo báo giá đầu tiên để hệ thống bắt đầu theo dõi phiên bản và tỷ lệ chấp nhận.';
$lang['sales_pipeline_quote_create_action'] = 'Tạo báo giá';
$lang['sales_pipeline_quote_invalid_estimate'] = 'Không tìm thấy báo giá nguồn.';
$lang['sales_pipeline_quote_duplicate_failed'] = 'Không thể nhân bản báo giá và liên kết phiên bản. Vui lòng thử lại.';
$lang['sales_pipeline_quote_duplicate_success'] = 'Đã nhân bản và liên kết báo giá với nhóm phiên bản.';
$lang['sales_pipeline_estimate_reminder_daily_title'] = 'Báo cáo báo giá hằng ngày';
$lang['sales_pipeline_estimate_reminder_monthly_title'] = 'Báo cáo chốt số tháng';
$lang['sales_pipeline_estimate_reminder_weekly_title'] = 'Báo cáo doanh thu báo giá tuần';
$lang['sales_pipeline_estimate_reminder_default_title'] = 'Nhắc nhở báo giá theo kỳ';
$lang['sales_pipeline_dashboard_total_deals'] = 'Tổng số Thương vụ';
$lang['sales_pipeline_dashboard_total_revenue'] = 'Tổng doanh thu';
$lang['sales_pipeline_dashboard_total_estimates'] = 'Tổng báo giá';
$lang['sales_pipeline_settings_reminders'] = 'Nhắc nhở';
$lang['sp_reminder_global_switch'] = 'Bật hệ thống nhắc nhở tự động';
$lang['sp_reminder_skip_weekends_label'] = 'Bỏ qua Thứ 7, Chủ nhật';
$lang['sp_reminder_holiday_dates_label'] = 'Ngày nghỉ lễ (mỗi dòng YYYY-MM-DD)';
$lang['sp_reminder_quiet_hours_label'] = 'Giờ im lặng';
$lang['sp_reminder_start'] = 'Bắt đầu';
$lang['sp_reminder_end'] = 'Kết thúc';
$lang['sp_reminder_section_deal'] = 'Deal — Tần suất';
$lang['sp_reminder_section_kpi'] = 'Báo giá — Chỉ tiêu theo kỳ';
$lang['sp_reminder_section_lifecycle'] = 'Báo giá — Vòng đời';
$lang['sp_reminder_deal_note'] = 'Tần suất được cấu hình trên từng Deal';
$lang['sp_reminder_daily'] = 'Hằng ngày';
$lang['sp_reminder_monthly'] = 'Hằng tháng';
$lang['sp_reminder_weekly'] = 'Hằng tuần';
$lang['sp_reminder_threshold_label'] = 'Tối thiểu báo giá';
$lang['sp_reminder_check_time_label'] = 'Kiểm tra lúc';
$lang['sp_reminder_monthly_d10'] = 'Chốt D10';
$lang['sp_reminder_monthly_d20'] = 'Chốt D20';
$lang['sp_reminder_monthly_final'] = 'Chốt cuối tháng';
$lang['sp_reminder_weekly_target'] = 'Mục tiêu doanh thu tuần';
$lang['sp_reminder_weekly_midweek_time'] = 'Giờ giữa tuần (T4)';
$lang['sp_reminder_weekly_final_time'] = 'Giờ cuối tuần (T6)';
$lang['sp_reminder_days_label'] = 'Số ngày';
$lang['sp_reminder_lc_draft'] = 'Nháp quá lâu';
$lang['sp_reminder_lc_sent'] = 'Gửi chưa phản hồi';
$lang['sp_reminder_lc_sent_expiry'] = 'Sắp hết hạn trong';
$lang['sp_reminder_lc_declined'] = 'Bị từ chối gần đây';
$lang['sp_reminder_lc_expired'] = 'Đã hết hạn';
$lang['sp_reminder_lc_accepted'] = 'Đã chấp nhận, chưa xuất hóa đơn';
$lang['sp_reminder_save_changes'] = 'Lưu thay đổi';
$lang['sp_reminder_error_channel_required'] = 'Cần chọn ít nhất một kênh gửi cho %s.';
$lang['sp_reminder_error_invalid_number'] = 'Giá trị %s không hợp lệ (từ %s đến %s).';
$lang['sp_reminder_error_invalid_time'] = 'Định dạng giờ không hợp lệ cho %s.';
$lang['sp_reminder_error_invalid_date'] = 'Ngày nghỉ %s không hợp lệ.';
$lang['sp_reminder_error_quiet_hours_incomplete'] = 'Cần nhập đủ giờ bắt đầu và kết thúc của Giờ im lặng.';

// Reminder Email Carbon Copy (CC) Settings
$lang['sp_settings_reminder_email_cc_manager']          = 'Gửi bản sao (CC) email nhắc nhở đến Quản lý';
$lang['sp_settings_reminder_email_cc_manager_help']     = 'Tự động CC Quản trị viên/Quản lý khi gửi email nhắc nhở đến nhân viên kinh doanh.';
$lang['sp_settings_reminder_email_cc_scope']            = 'Phạm vi gửi CC';
$lang['sp_settings_reminder_email_cc_scope_all']        = 'Tất cả các nhắc nhở';
$lang['sp_settings_reminder_email_cc_scope_critical']   = 'Chỉ các nhắc nhở nghiêm trọng (Critical)';
$lang['sp_settings_reminder_email_cc_fallback']         = 'Email Quản lý dự phòng (ngăn cách bằng dấu phẩy)';
$lang['sp_reminder_error_invalid_fallback_email']       = 'Địa chỉ email dự phòng không hợp lệ: %s';
$lang['sp_reminder_supervisor_mode_notice']             = 'Bạn đang xem thông báo nhắc nhở của nhân viên %s ở chế độ Giám sát (Chỉ xem).';
