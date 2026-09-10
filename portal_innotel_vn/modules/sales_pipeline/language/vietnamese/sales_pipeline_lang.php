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

$lang['sales_pipeline_reminder_settings']  = 'Thiết lập tần suất theo dõi';
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
$lang['sales_pipeline_response_placeholder'] = 'Cập nhật kết quả, vướng mắc hoặc lý do đang gặp khó khăn gì...';
$lang['sales_pipeline_response_max_length'] = 'Tối đa 2.000 ký tự. Phản hồi chỉ được gửi một lần.';
$lang['sales_pipeline_send_response'] = 'Gửi phản hồi';
$lang['sales_pipeline_severity_warning'] = 'Cảnh báo';
$lang['sales_pipeline_severity_critical'] = 'Nghiêm trọng';
$lang['sales_pipeline_severity_info'] = 'Thông tin';
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
$lang['sales_pipeline_deal'] = 'Deal';
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
$lang['sales_pipeline_dashboard_estimates_loading'] = 'Đang tải thông tin nhân viên...';
$lang['sales_pipeline_dashboard_load_failed'] = 'Không thể tải Pipeline nhân viên. Vui lòng thử lại.';
$lang['sales_pipeline_dashboard_close'] = 'Đóng bảng chi tiết';
$lang['sales_pipeline_dashboard_staff_pipeline'] = 'Thông tin chi tiết';
$lang['sales_pipeline_rule_reminder'] = '%s — %s';
$lang['sales_pipeline_reminder_email_authentication_alert'] = 'Kênh email nhắc nhở đã tạm dừng do xác thực SMTP thất bại. Quản trị viên cần kiểm tra cấu hình email.';
$lang['sales_pipeline_reminder_email_bcc_quota_alert'] = 'Hộp thư BCC hệ thống đã từ chối email nhắc nhở do đầy dung lượng. Hãy kiểm tra hộp thư và SMTP log trước khi thử lại delivery.';
$lang['sales_pipeline_reminder_estimate_period'] = 'Kỳ Báo giá';
$lang['sales_pipeline_reminder_context'] = 'Ngữ cảnh lời nhắc';
$lang['sales_pipeline_reminder_reason'] = 'Lý do nhắc nhở';
$lang['sales_pipeline_reminder_informational'] = 'Chỉ thông báo';
$lang['sales_pipeline_reminder_informational_help'] = 'Lời nhắc này không yêu cầu giải trình. Hãy mở Báo giá để kiểm tra và xử lý khi cần.';
$lang['sales_pipeline_reminder_manager_read_only'] = 'Bạn có thể xem nội dung nhưng chỉ nhân viên được nhắc mới có thể gửi phản hồi.';
$lang['sales_pipeline_reminder_response_not_required'] = 'Lời nhắc này chỉ cung cấp thông tin và không nhận phản hồi.';
$lang['sales_pipeline_reminder_delivery_pending'] = 'Chưa thể phản hồi cho đến khi lời nhắc được gửi thành công và thời hạn phản hồi được thiết lập.';
$lang['sales_pipeline_reminder_waiting_delivery'] = 'Chờ gửi thành công';
$lang['sales_pipeline_go_to_estimate'] = 'Đi tới Báo giá';
$lang['sales_pipeline_reminder_email_view_entity'] = 'Mở đối tượng liên quan';
$lang['sales_pipeline_deal_pipeline_min_title'] = 'Pipeline Deal dưới mức tối thiểu';
$lang['sales_pipeline_deal_pipeline_min_message'] = 'Bạn hiện có %s Deal đang mở, thấp hơn mức tối thiểu %s. Vui lòng nêu nguyên nhân và kế hoạch bổ sung cơ hội kinh doanh.';
$lang['sales_pipeline_deal_stale_title'] = 'Deal chưa được theo dõi đúng hạn';
$lang['sales_pipeline_deal_stale_message'] = 'Thương vụ %s của khách hàng %s chưa có hoạt động theo dõi có ý nghĩa trong %s ngày. Vui lòng cập nhật tiến độ và bước tiếp theo.';
$lang['sales_pipeline_deal_stale_reason'] = 'Deal đã quá thời hạn theo dõi nhưng chưa có hoạt động mới';
$lang['sales_pipeline_deal_stale_backlog_title'] = 'Tồn đọng Deal cần rà soát';
$lang['sales_pipeline_deal_stale_backlog_message'] = 'Bạn có %s Deal cần rà soát: %s Deal được ưu tiên nhắc riêng, %s Deal chậm follow-up khác và %s Deal tồn đọng quá %s ngày.';
$lang['sales_pipeline_reminder_deal_backlog'] = 'Tồn đọng Deal';
$lang['sales_pipeline_reminder_deal_stale_cutoff_label'] = 'Ngưỡng chuyển sang tồn đọng';
$lang['sales_pipeline_reminder_deal_stale_max_label'] = 'Số Deal nhắc riêng tối đa mỗi lần chạy';
$lang['sales_pipeline_reminder_deal_period'] = 'Kỳ Deal';
$lang['sales_pipeline_reminder_deal_period_count_reason'] = 'Số Deal đang mở hiện tại thấp hơn mức tối thiểu (%s/%s)';
$lang['sales_pipeline_reminder_deal_period_week_target'] = 'Bổ sung cơ hội kinh doanh cho tuần %s';
$lang['sales_pipeline_reminder_period_count_reason'] = 'Số lượng Báo giá hợp lệ hiện tại thấp hơn ngưỡng (%s/%s)';
$lang['sales_pipeline_reminder_period_revenue_reason'] = 'Doanh thu Báo giá đã chấp nhận trong tuần này thấp hơn ngưỡng (%s/%s VNĐ)';
$lang['sales_pipeline_reminder_period_month_target'] = 'Hãy theo dõi chỉ tiêu báo giá tháng %s';
$lang['sales_pipeline_reminder_period_day_target'] = 'Hãy theo dõi chỉ tiêu báo giá ngày %s';
$lang['sales_pipeline_reminder_period_week_target'] = 'Hãy theo dõi doanh thu của Báo giá trong tuần này (%s)';
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
$lang['sales_pipeline_reminder_email_recipient_fallback'] = 'Anh/Chị';
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
$lang['sales_pipeline_currency_vnd'] = 'VNĐ';
$lang['sales_pipeline_revenue_target_billion'] = '/ 1 tỷ';
$lang['sales_pipeline_js_locale'] = 'vi-VN';
$lang['sales_pipeline_date_range'] = '%s đến %s';
$lang['sales_pipeline_response_processing_error'] = 'Lỗi khi xử lý phản hồi Sales Pipeline:';

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
$lang['sales_pipeline_dashboard_history_picker_open'] = 'Chọn kỳ dữ liệu trong lịch sử';
$lang['sales_pipeline_dashboard_history_picker_title'] = 'Chọn kỳ trong lịch sử';
$lang['sales_pipeline_dashboard_history_picker_help'] = 'Chọn loại kỳ và một ngày thuộc kỳ cần xem.';
$lang['sales_pipeline_dashboard_history_anchor'] = 'Ngày thuộc kỳ';
$lang['sales_pipeline_dashboard_history_current'] = 'Về kỳ hiện tại';
$lang['sales_pipeline_dashboard_history_apply'] = 'Áp dụng kỳ';
$lang['sales_pipeline_dashboard_history_invalid_date'] = 'Vui lòng chọn một ngày hợp lệ trong lịch sử.';
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
$lang['sales_pipeline_performance_breakdown'] = 'Bảng Điểm Hiệu Suất';
$lang['sales_pipeline_performance_quote_component'] = 'Sản lượng báo giá';
$lang['sales_pipeline_performance_revenue_component'] = 'Điểm giá trị Báo giá đã chấp nhận';
$lang['sales_pipeline_performance_acceptance_component'] = 'Tỷ lệ chấp nhận';
$lang['sales_pipeline_performance_reminder_component'] = 'Phản hồi nhắc nhở';
$lang['sales_pipeline_performance_effective_weight'] = 'Trọng số hiệu dụng %s%%';
$lang['sales_pipeline_performance_weight_hint'] = '(Chiếm %s%% trọng số)';
$lang['sales_pipeline_performance_marker_accelerate'] = '%s điểm (Cần tăng tốc)';
$lang['sales_pipeline_performance_marker_standard'] = '%s điểm (Đạt chuẩn)';
$lang['sales_pipeline_performance_marker_target'] = '%s điểm (Mục tiêu chuẩn 100%% KPI)';
$lang['sales_pipeline_tier_excellent'] = 'Xuất sắc';
$lang['sales_pipeline_tier_good'] = 'Đạt chuẩn';
$lang['sales_pipeline_tier_warning'] = 'Cần tăng tốc';
$lang['sales_pipeline_tier_critical'] = 'Báo động';
$lang['sales_pipeline_tier_gap_80'] = 'Cách vạch đạt chuẩn (80 điểm): còn %s điểm';
$lang['sales_pipeline_tier_gap_100'] = 'Cách mốc 100%% KPI: còn %s điểm';
$lang['sales_pipeline_tier_target_met'] = 'Đã đạt chuẩn hiệu suất (Vượt chỉ tiêu)';
$lang['sales_pipeline_performance_progress_title'] = 'Tiến độ điểm hiệu suất';
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
$lang['sales_pipeline_settings_reminders'] = 'Quy Tắc Tự Động Hóa';
$lang['sp_reminder_global_switch'] = 'Bật hệ thống nhắc nhở tự động';
$lang['sp_reminder_skip_weekends_label'] = 'Bỏ qua Thứ 7, Chủ nhật';
$lang['sp_reminder_holiday_dates_label'] = 'Ngày nghỉ lễ (mỗi dòng YYYY-MM-DD)';
$lang['sp_reminder_quiet_hours_label'] = 'Giờ im lặng';
$lang['sp_reminder_start'] = 'Bắt đầu';
$lang['sp_reminder_end'] = 'Kết thúc';
$lang['sp_reminder_section_deal'] = 'Deal — Pipeline & Theo dõi';
$lang['sp_reminder_section_kpi'] = 'Báo giá — Chỉ tiêu theo kỳ';
$lang['sp_reminder_section_lifecycle'] = 'Báo giá — Vòng đời';
$lang['sp_reminder_deal_note'] = 'Tần suất được cấu hình trên từng Deal';
$lang['sp_reminder_deal_pipeline_label'] = 'Cảnh báo Pipeline có quá ít Deal (Thứ Hai / Thứ Tư / Thứ Sáu)';
$lang['sp_reminder_deal_pipeline_min_count_label'] = 'Số Deal đang mở tối thiểu';
$lang['sp_reminder_deal_stale_label'] = 'Nhắc Deal không có hoạt động theo dõi (dùng số ngày nhắc trên từng Deal)';
$lang['sp_reminder_deal_stale_cutoff_label'] = 'Cutoff nhắc riêng lẻ (ngày)';
$lang['sales_pipeline_kpi_revenue_title'] = 'Doanh thu kỳ này';
$lang['sales_pipeline_kpi_estimate_revenue_title'] = 'Doanh thu báo giá kỳ này';
$lang['sales_pipeline_kpi_vs_previous'] = 'so với kỳ trước';
$lang['sales_pipeline_kpi_current_period'] = 'Kỳ này';
$lang['sales_pipeline_kpi_previous_period'] = 'Kỳ trước';
$lang['sp_reminder_deal_stale_max_label'] = 'Giới hạn thông báo riêng mỗi lần chạy (Deal)';
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
$lang['sp_reminder_supervisor_mode_notice']             = 'Bạn đang xem thông báo nhắc nhở của nhân viên %s ở chế độ Giám sát (Chỉ xem).';
$lang['sales_pipeline_dashboard_no_reminder_responses_in_period'] = 'Không có hoạt động nhắc nhở nào trong kỳ này';

// Estimate Revision Intent Selector & Guards
$lang['sales_pipeline_permission_manage_revisions']       = 'Quản lý Phiên bản & Liên kết Báo giá (Manage Revisions)';
$lang['sales_pipeline_estimate_intent_label']             = 'Mục đích Báo giá';
$lang['sales_pipeline_estimate_intent_standalone']        = 'Báo giá mới độc lập';
$lang['sales_pipeline_estimate_intent_standalone_help']   = 'Khởi tạo một chuỗi thương vụ / giao dịch hoàn toàn mới cho khách hàng này.';
$lang['sales_pipeline_estimate_intent_revision']          = 'Bản điều chỉnh / Thay thế';
$lang['sales_pipeline_estimate_intent_revision_help']      = 'Sửa đổi số lượng, cấu hình, giá hoặc báo lại cho Báo giá đã hết hạn trước đó.';
$lang['sales_pipeline_select_source_estimate']            = 'Chọn một Báo giá để điều chỉnh hoặc thay thế';
$lang['sales_pipeline_source_estimate_label']             = 'Báo giá';
$lang['sales_pipeline_source_estimate_total']             = 'Tổng tiền';
$lang['sales_pipeline_source_estimate_status']            = 'Trạng thái';
$lang['sales_pipeline_source_estimate_date']              = 'Ngày tạo';
$lang['sales_pipeline_source_estimate_expiry']            = 'Ngày hết hạn';
$lang['sales_pipeline_source_estimate_date_short']        = 'Tạo';
$lang['sales_pipeline_source_estimate_expiry_short']      = 'Hạn';
$lang['sales_pipeline_source_estimate_revision']          = 'Phiên bản';
$lang['sales_pipeline_select_customer_first']            = 'Vui lòng chọn Khách hàng ở ô phía trên trước để tải danh sách Báo giá.';
$lang['sales_pipeline_no_source_estimates_found']         = 'Khách hàng này chưa có Báo giá nào trước đó để liên kết.';
$lang['sales_pipeline_loading_source_estimates']          = 'Đang tải danh sách Báo giá...';
$lang['sales_pipeline_search_source_estimates']           = 'Tìm kiếm báo giá...';
$lang['sales_pipeline_no_matching_source_estimates']      = 'Không tìm thấy báo giá phù hợp.';
$lang['sales_pipeline_revision_accepted_warning']         = 'Báo giá này đã được chấp nhận. Để tạo bản điều chỉnh sau chấp nhận, bạn cần nhập lý do xác nhận.';
$lang['sales_pipeline_override_reason_label']             = 'Lý do điều chỉnh sau chấp nhận (Bắt buộc):';
$lang['sales_pipeline_override_reason_placeholder']       = 'Nhập lý do điều chỉnh kỹ thuật / bổ sung phụ lục...';
$lang['sales_pipeline_revision_client_mismatch']          = 'Không thể liên kết: Báo giá nguồn thuộc về một khách hàng khác.';
$lang['sales_pipeline_revision_accepted_locked']          = 'Báo giá nguồn đã được chấp nhận. Chỉ Quản lý mới có quyền tạo bản điều chỉnh kèm lý do.';
$lang['sales_pipeline_revision_linked']                   = 'Đã liên kết Báo giá thành phiên bản điều chỉnh thành công.';
$lang['sales_pipeline_estimate_group_created']            = 'Đã khởi tạo Nhóm Báo giá mới độc lập thành công.';
$lang['sales_pipeline_revision_fallback_warning']         = 'Không thể liên kết vào Báo giá nguồn do không thỏa điều kiện bảo vệ. Hệ thống đã tự động lưu thành Báo giá mới độc lập để bảo toàn dữ liệu.';

// Smart Prompt & Candidates
$lang['sales_pipeline_smart_prompt_title']                 = 'Gợi ý Báo giá Thông minh';
$lang['sales_pipeline_smart_prompt_intro']                 = 'Có một Báo giá gần đây phù hợp với khách hàng này:';
$lang['sales_pipeline_smart_prompt_estimate_number']       = 'Số Báo giá';
$lang['sales_pipeline_smart_prompt_customer']              = 'Khách hàng';
$lang['sales_pipeline_smart_prompt_total']                 = 'Tổng tiền';
$lang['sales_pipeline_smart_prompt_status']                = 'Trạng thái';
$lang['sales_pipeline_smart_prompt_date']                  = 'Ngày tạo';
$lang['sales_pipeline_smart_prompt_expiry']                = 'Ngày hết hạn';
$lang['sales_pipeline_smart_prompt_question']              = 'Bạn có đang tạo bản điều chỉnh cho Báo giá này không?';
$lang['sales_pipeline_smart_prompt_text']                  = 'Khách hàng này có Báo giá gần đây (%s - %s [%s]). Đây có phải là Bản điều chỉnh không?';
$lang['sales_pipeline_smart_prompt_apply']                 = 'Chọn làm bản điều chỉnh';
$lang['sales_pipeline_smart_prompt_dismiss']               = 'Bỏ qua / Báo giá mới';
$lang['sales_pipeline_reason_same_project']                = 'Cùng dự án';
$lang['sales_pipeline_reason_same_owner']                  = 'Cùng phụ trách';
$lang['sales_pipeline_reason_recently_expired']            = 'Hết hạn gần đây';
$lang['sales_pipeline_reason_recently_declined']           = 'Từ chối gần đây';
$lang['sales_pipeline_reason_recent_activity']            = 'Hoạt động gần đây';

// Version History & Manual Link / Unlink
$lang['sales_pipeline_version_history_title']              = 'Lịch sử Phiên bản Báo giá';
$lang['sales_pipeline_loading_version_history']            = 'Đang tải lịch sử phiên bản...';
$lang['sales_pipeline_version_history_load_warning']       = 'Không thể tải lịch sử phiên bản cho báo giá này.';
$lang['sales_pipeline_version_history_load_error']         = 'Đã xảy ra lỗi khi kết nối máy chủ.';
$lang['sales_pipeline_version_history_empty']              = 'Báo giá này chưa được gán vào nhóm báo giá nào.';
$lang['sales_pipeline_version_history_status_accepted']   = 'Đã chấp nhận';
$lang['sales_pipeline_version_history_status_declined']   = 'Bị từ chối';
$lang['sales_pipeline_version_history_status_pending']    = 'Đang thương lượng';
$lang['sales_pipeline_version_history_group_title']       = 'Nhóm Báo giá';
$lang['sales_pipeline_version_history_version_count']     = 'Tổng số phiên bản';
$lang['sales_pipeline_version_history_group_outcome']     = 'Trạng thái nhóm';
$lang['sales_pipeline_version_history_tree_title']        = 'Cây Phiên bản Báo giá';
$lang['sales_pipeline_version_history_audit_title']       = 'Nhật ký Sự kiện';
$lang['sales_pipeline_version_history_method_origin']     = 'Bản gốc';
$lang['sales_pipeline_version_history_method_native_copy'] = 'Sao chép từ báo giá';
$lang['sales_pipeline_version_history_method_module_copy'] = 'Sao chép thành bản điều chỉnh';
$lang['sales_pipeline_version_history_method_revision']   = 'Bản điều chỉnh';
$lang['sales_pipeline_version_history_method_manual_link'] = 'Gộp thủ công';
$lang['sales_pipeline_version_history_method_manual_unlink'] = 'Đã tách thủ công';
$lang['sales_pipeline_version_history_method_unknown']    = 'Không xác định';
$lang['sales_pipeline_version_history_current']           = 'Đang xem';
$lang['sales_pipeline_version_history_revision_prefix']   = 'Phiên bản';
$lang['sales_pipeline_version_history_parent_label']      = 'Điều chỉnh từ Báo giá';
$lang['sales_pipeline_version_history_actor_label']       = 'Người thực hiện';
$lang['sales_pipeline_version_history_event_unknown']     = 'Sự kiện khác';
$lang['sales_pipeline_version_history_reason_standalone'] = 'Khởi tạo nhóm báo giá độc lập';
$lang['sales_pipeline_link_standalone_btn']                = 'Gộp vào Báo giá khác';
$lang['sales_pipeline_unlink_revision_btn']                = 'Tách phiên bản này';
$lang['sales_pipeline_version_history_link_modal_title']  = 'Gộp Báo giá vào Nhóm có sẵn';
$lang['sales_pipeline_version_history_target_label']      = 'Nhập ID hoặc mã Báo giá mục tiêu cần gộp vào:';
$lang['sales_pipeline_version_history_target_placeholder'] = 'Ví dụ: 95';
$lang['sales_pipeline_version_history_link_reason_label'] = 'Lý do gộp (tùy chọn, bắt buộc nếu mục tiêu đã duyệt):';
$lang['sales_pipeline_version_history_link_reason_placeholder'] = 'Nhập lý do gộp nhóm...';
$lang['sales_pipeline_version_history_confirm_link']     = 'Xác nhận gộp';
$lang['sales_pipeline_version_history_close']            = 'Đóng';
$lang['sales_pipeline_version_history_close_aria']       = 'Đóng cửa sổ';
$lang['sales_pipeline_version_history_not_available']    = 'Chưa cập nhật';
$lang['sales_pipeline_version_history_invalid_target']   = 'Vui lòng nhập ID Báo giá mục tiêu hợp lệ.';
$lang['sales_pipeline_version_history_processing']       = 'Đang xử lý...';
$lang['sales_pipeline_version_history_link_error']       = 'Đã xảy ra lỗi khi gộp Báo giá.';
$lang['sales_pipeline_version_history_unlink_modal_title'] = 'Tách phiên bản Báo giá';
$lang['sales_pipeline_version_history_unlink_notice']    = 'Phiên bản này sẽ được tách khỏi nhóm hiện tại và trở thành một Nhóm Báo giá mới độc lập (Rev.1).';
$lang['sales_pipeline_version_history_unlink_reason_label'] = 'Lý do tách nhóm (bắt buộc):';
$lang['sales_pipeline_version_history_unlink_reason_placeholder'] = 'Nhập lý do tách nhóm (ví dụ: tách hợp đồng riêng, báo giá độc lập)...';
$lang['sales_pipeline_version_history_confirm_unlink']  = 'Xác nhận tách';
$lang['sales_pipeline_version_history_unlink_reason_required'] = 'Vui lòng nhập lý do tách nhóm.';
$lang['sales_pipeline_version_history_unlink_error']    = 'Đã xảy ra lỗi khi tách phiên bản.';
$lang['sales_pipeline_version_history_connection_error'] = 'Lỗi kết nối máy chủ.';
$lang['sales_pipeline_source_not_standalone']              = 'Báo giá nguồn phải là báo giá đơn lẻ (chỉ có 1 phiên bản) và chưa được chấp nhận.';
$lang['sales_pipeline_already_same_group']                 = 'Hai báo giá này đã thuộc về cùng một nhóm.';
$lang['sales_pipeline_cannot_unlink_only_revision']        = 'Không thể tách vì nhóm chỉ còn đúng 1 phiên bản.';
$lang['sales_pipeline_must_unlink_latest_revision']        = 'Chỉ được phép tách phiên bản mới nhất của nhóm.';
$lang['sales_pipeline_cannot_unlink_accepted_decision']    = 'Không thể tách phiên bản đã được khách hàng chấp nhận.';
$lang['sales_pipeline_revision_unlinked']                  = 'Đã tách phiên bản ra thành Nhóm Báo giá độc lập mới thành công.';

// Audit Event Labels
$lang['sales_pipeline_event_group_created']                = 'Khởi tạo nhóm';
$lang['sales_pipeline_event_revision_linked']              = 'Liên kết phiên bản';
$lang['sales_pipeline_event_revision_unlinked']            = 'Tách phiên bản';
$lang['sales_pipeline_event_accepted_override']            = 'Ghi đè Báo giá đã duyệt';
$lang['sales_pipeline_event_revision_link_failed']         = 'Liên kết thất bại';
$lang['sales_pipeline_event_revision_fallback_standalone'] = 'Chuyển sang nhóm độc lập';

// Deal Bridge & Manual Lock
$lang['sales_pipeline_deal_manual_lock']                   = 'Khóa tự động cập nhật';
$lang['sales_pipeline_deal_manual_unlock']                 = 'Mở khóa tự động cập nhật';
$lang['sales_pipeline_deal_manual_lock_hint']              = 'Khi khóa, các thay đổi trạng thái và giá trị từ Nhóm Báo giá sẽ không tự động ghi đè lên Deal này.';
$lang['sales_pipeline_deal_manual_lock_reason']            = 'Lý do khóa thủ công';
$lang['sales_pipeline_deal_primary_group']                 = 'Nhóm Báo giá chính';
$lang['sales_pipeline_activity_deal_locked']                = 'Đã khóa thủ công Deal (Lý do: %s)';
$lang['sales_pipeline_activity_deal_unlocked']              = 'Đã mở khóa cập nhật tự động cho Deal';

// Missing core/controller keys
$lang['sales_pipeline_override_reason_required']            = 'Cần nhập lý do khi điều chỉnh Báo giá đã duyệt.';
$lang['sales_pipeline_permission_denied']                  = 'Bạn không có quyền thực hiện thao tác này.';
$lang['sales_pipeline_permission_denied_source']           = 'Bạn không có quyền xem Báo giá nguồn.';
$lang['sales_pipeline_permission_denied_target']           = 'Bạn không có quyền xem Báo giá mục tiêu.';
$lang['sales_pipeline_target_group_missing']                = 'Không tìm thấy Nhóm Báo giá mục tiêu.';
$lang['sales_pipeline_error_occurred']                      = 'Đã xảy ra lỗi trong quá trình xử lý.';
$lang['sales_pipeline_updated_successfully']                = 'Cập nhật thành công!';
$lang['sp_reminder_error_invalid_fallback_email']           = 'Địa chỉ email dự phòng không hợp lệ: %s';

// Version History Audit Event Reasons / System Codes Localization
$lang['sales_pipeline_reason_standalone']                   = 'Khởi tạo nhóm báo giá độc lập';
$lang['sales_pipeline_reason_fallback_standalone']          = 'Chuyển sang nhóm độc lập do không thể liên kết';
$lang['sales_pipeline_reason_origin']                       = 'Báo giá gốc';
$lang['sales_pipeline_reason_origin_backfill']              = 'Khởi tạo tự động cho Báo giá có sẵn';
$lang['sales_pipeline_reason_backfill']                     = 'Khởi tạo dữ liệu tự động';
$lang['sales_pipeline_reason_native_copy']                  = 'Sao chép từ Báo giá';
$lang['sales_pipeline_reason_module_copy']                  = 'Sao chép thành bản điều chỉnh';
$lang['sales_pipeline_reason_revision']                     = 'Bản điều chỉnh';
$lang['sales_pipeline_reason_manual_link']                  = 'Gộp thủ công';
$lang['sales_pipeline_reason_manual_unlink']                = 'Tách thủ công';

// Guard & Error Codes as Audit Reasons
$lang['sales_pipeline_reason_override_reason_required']     = 'Bắt buộc nhập lý do khi ghi đè Báo giá đã duyệt';
$lang['sales_pipeline_reason_accepted_locked']             = 'Nhóm Báo giá đã được chấp nhận và khóa';
$lang['sales_pipeline_reason_customer_mismatch']            = 'Khách hàng không trùng khớp giữa các Báo giá';
$lang['sales_pipeline_reason_estimate_not_found']           = 'Không tìm thấy Báo giá tương ứng';
$lang['sales_pipeline_reason_target_group_missing']         = 'Không tìm thấy Nhóm Báo giá mục tiêu';
$lang['sales_pipeline_reason_permission_denied']           = 'Không có quyền thực hiện thao tác';
$lang['sales_pipeline_reason_permission_denied_source']    = 'Không có quyền xem Báo giá nguồn';
$lang['sales_pipeline_reason_permission_denied_target']    = 'Không có quyền xem Báo giá mục tiêu';
$lang['sales_pipeline_reason_invalid_id']                   = 'Mã Báo giá không hợp lệ';
$lang['sales_pipeline_reason_already_same_group']          = 'Hai báo giá này đã thuộc về cùng một nhóm';
$lang['sales_pipeline_reason_source_not_standalone']       = 'Báo giá nguồn không phải là báo giá đơn lẻ';
$lang['sales_pipeline_reason_reason_required']              = 'Bắt buộc nhập lý do';
$lang['sales_pipeline_reason_version_not_found']            = 'Không tìm thấy phiên bản Báo giá';
$lang['sales_pipeline_reason_cannot_unlink_only_revision'] = 'Không thể tách vì nhóm chỉ còn 1 phiên bản';
$lang['sales_pipeline_reason_not_latest_revision']         = 'Chỉ được phép tách phiên bản mới nhất';
$lang['sales_pipeline_reason_must_unlink_latest_revision'] = 'Chỉ được phép tách phiên bản mới nhất';
$lang['sales_pipeline_reason_cannot_unlink_accepted_decision'] = 'Không thể tách phiên bản đã được khách hàng chấp nhận';
$lang['sales_pipeline_reason_snapshot_failed']             = 'Không thể lưu ảnh chụp Báo giá';
$lang['sales_pipeline_reason_audit_insert_failed']         = 'Không thể ghi nhật ký sự kiện';
$lang['sales_pipeline_reason_transaction_failed']          = 'Lỗi xử lý giao dịch cơ sở dữ liệu';
$lang['sales_pipeline_reason_fallback_failed']             = 'Không thể chuyển sang nhóm độc lập';
$lang['sales_pipeline_reason_append_failed']               = 'Không thể liên kết phiên bản mới';

// Email Reminder UI
$lang['sales_pipeline_reminder_badge_notice']               = 'THÔNG BÁO TIẾN ĐỘ';
$lang['sales_pipeline_reminder_badge_warning']              = 'NHẮC NHỞ THEO DÕI';
$lang['sales_pipeline_reminder_badge_critical']             = 'CẦN XỬ LÝ GẤP';
$lang['sales_pipeline_inactive_days']                       = 'Thời gian chưa có tương tác mới';
$lang['sales_pipeline_reminder_email_footer_tip']           = 'Bấm vào "Phản hồi nhanh" để ghi nhận cập nhật tiến độ mà không cần đăng nhập phức tạp. Phản hồi sẽ được đồng bộ vào lịch sử.';
$lang['sales_pipeline_reminder_email_auto_generated']       = 'Email được tạo và gửi tự động từ hệ thống CRM. Vui lòng không trả lời trực tiếp email này.';
$lang['sales_pipeline_reminder_email_view_entity']          = 'Xem chi tiết';

// Sức khỏe tầng phân phối email nhắc nhở
$lang['sales_pipeline_reminder_delivery_health'] = 'Tình trạng phân phối nhắc nhở';
$lang['sales_pipeline_reminder_delivery_circuit_open'] = 'Kênh email đang tạm dừng';
$lang['sales_pipeline_reminder_delivery_circuit_closed'] = 'Kênh email đang hoạt động';
$lang['sales_pipeline_reminder_delivery_resume'] = 'Mở lại kênh email';
$lang['sales_pipeline_reminder_delivery_email_pending'] = 'Email đang chờ';
$lang['sales_pipeline_reminder_delivery_email_failed'] = 'Email lỗi';
$lang['sales_pipeline_reminder_delivery_email_sent'] = 'Email đã gửi';
$lang['sales_pipeline_reminder_delivery_expired'] = 'Email hết hạn';
$lang['sales_pipeline_reminder_delivery_crm_pending'] = 'CRM đang chờ';
$lang['sales_pipeline_reminder_delivery_rate_limited_24h'] = 'Bị giới hạn tốc độ (24 giờ)';
$lang['sales_pipeline_reminder_delivery_oldest_pending'] = 'Email chờ lâu nhất';
$lang['sales_pipeline_reminder_delivery_last_sent'] = 'Email thành công gần nhất';
$lang['sales_pipeline_reminder_delivery_cooldown_until'] = 'Tạm hoãn đến';
$lang['sales_pipeline_reminder_delivery_none'] = 'Không có';
$lang['sales_pipeline_reminder_delivery_recent_errors'] = 'Sự cố phân phối gần đây';
$lang['sales_pipeline_reminder_delivery_no_errors'] = 'Chưa ghi nhận sự cố phân phối nào.';
$lang['sales_pipeline_reminder_delivery_id'] = 'Delivery';
$lang['sales_pipeline_reminder_delivery_rule_entity'] = 'Rule / Đối tượng';
$lang['sales_pipeline_reminder_delivery_recipient'] = 'Người nhận';
$lang['sales_pipeline_reminder_delivery_error_class'] = 'Nhóm lỗi';
$lang['sales_pipeline_reminder_delivery_attempts'] = 'Số lần thử';
$lang['sales_pipeline_reminder_delivery_next_retry'] = 'Lần thử kế tiếp';
$lang['sales_pipeline_reminder_delivery_action'] = 'Thao tác';
$lang['sales_pipeline_reminder_delivery_manual'] = 'Xử lý thủ công';
$lang['sales_pipeline_reminder_delivery_retry'] = 'Thử lại delivery này';
$lang['sales_pipeline_reminder_delivery_retry_confirm'] = 'Thử lại delivery này sau khi xác nhận nguyên nhân lỗi đã được xử lý?';
$lang['sales_pipeline_reminder_delivery_resume_confirm'] = 'Mở lại kênh email nhắc nhở sau khi đã xác minh cấu hình SMTP?';
$lang['sales_pipeline_reminder_delivery_retry_success'] = 'Delivery đã sẵn sàng để thử lại.';
$lang['sales_pipeline_reminder_delivery_retry_unavailable'] = 'Delivery này không còn đủ điều kiện để thử lại.';
$lang['sales_pipeline_reminder_delivery_resume_success'] = 'Kênh email nhắc nhở đã được mở lại.';
$lang['sales_pipeline_reminder_delivery_resume_unavailable'] = 'Kênh email nhắc nhở hiện đang hoạt động.';
$lang['sales_pipeline_reminder_delivery_method_not_allowed'] = 'Thao tác này chỉ chấp nhận yêu cầu POST bất đồng bộ đã được xác thực.';
$lang['sales_pipeline_reminder_delivery_request_failed'] = 'Không thể hoàn tất thao tác. Hãy tải lại trang và thử lại.';
$lang['sales_pipeline_last_updated'] = 'Cập nhật';
$lang['sales_pipeline_last_updated_at'] = 'Cập nhật lúc';
$lang['sales_pipeline_dashboard_data_updated_at'] = 'Dữ liệu cập nhật lúc';
$lang['sales_pipeline_dashboard_on_date'] = 'ngày';
$lang['sales_pipeline_dashboard_refresh_action'] = 'Làm mới';

// Reminder Bell Inbox
$lang['sales_pipeline_reminder_inbox_error']             = 'Không thể tải danh sách nhắc nhở.';
$lang['sales_pipeline_reminder_inbox_ack_tooltip']       = 'Đã nắm thông tin';
$lang['sales_pipeline_reminder_inbox_action_respond']    = 'Phản hồi';
$lang['sales_pipeline_reminder_inbox_action_view']       = 'Xem';
$lang['sales_pipeline_reminder_acknowledged']            = 'Đã xác nhận nắm thông tin.';
$lang['sales_pipeline_reminder_acknowledge_failed']      = 'Không thể xác nhận nhắc nhở. Vui lòng thử lại.';
$lang['sales_pipeline_reminder_inbox_recipient_unavailable'] = 'Người nhận nhắc nhở CRM không hoạt động, không tồn tại hoặc không có quyền truy cập Sales Pipeline.';
$lang['sales_pipeline_reminder_response_required_cannot_dismiss'] = 'Nhắc nhở này yêu cầu phản hồi tiến độ, không thể bỏ qua.';

// Performance Response SLA & Settings
$lang['sales_pipeline_performance_not_applicable'] = 'Không áp dụng';
$lang['sales_pipeline_performance_provisional_small_sample'] = 'Tạm tính (Mẫu nhỏ)';
$lang['sp_reminder_sla_hours_label'] = 'Thời hạn SLA phản hồi nhắc nhở (giờ)';
$lang['sales_pipeline_settings_performance'] = 'Điểm hiệu suất';
$lang['sales_pipeline_settings_performance_heading'] = 'Tiêu chuẩn Điểm hiệu suất';
$lang['sales_pipeline_settings_performance_help'] = 'Thiết lập các chỉ số mục tiêu để đánh giá và xếp hạng năng lực nhân viên kinh doanh trên Bảng xếp hạng.';
$lang['performance_response_target_percent_label'] = 'Mục tiêu tỷ lệ phản hồi nhắc nhở đúng hạn';
$lang['performance_response_target_percent_help'] = 'Tỷ lệ hoàn thành nhắc nhở đúng hạn theo SLA mà NVKD cần đạt để nhận điểm chuẩn 100 cho chỉ số này (Khuyến nghị: 90%%).';
$lang['performance_score_error_invalid_response_target'] = 'Mục tiêu tỷ lệ phản hồi phải là số trong khoảng từ 1%% đến 100%%.';
$lang['sales_pipeline_save_settings'] = 'Lưu cấu hình';
$lang['sales_pipeline_perf_how_it_works_title'] = 'Cách tính điểm & Trọng số đánh giá';
$lang['sales_pipeline_perf_weight_desc'] = 'Chỉ số phản hồi nhắc nhở chiếm <strong>15%% trọng số</strong> trong tổng Điểm hiệu suất của NVKD (bên cạnh Doanh thu chốt 40%%, Tỷ lệ chốt 25%%, và Số lượng báo giá 20%%).';
$lang['sales_pipeline_perf_formula_desc'] = '<strong>Cơ chế tính điểm:</strong> Nhân viên hoàn thành đúng hạn đạt hoặc vượt mục tiêu sẽ nhận đủ 100 điểm chuẩn (tối đa 120 điểm nếu vượt trội). Nếu dưới mục tiêu, điểm thành phần được tính theo tỷ lệ: <code>(Tỷ lệ đạt / Mục tiêu) × 100</code>.';
$lang['sales_pipeline_perf_example_desc'] = '<em>Ví dụ thực tế:</em> Với mục tiêu cấu hình 90%%, nếu nhân viên đạt 72%% phản hồi đúng hạn thì điểm thành phần này là (72 / 90) × 100 = 80 điểm.';
$lang['sales_pipeline_perf_sla_link_note'] = 'Thời hạn phản hồi (số giờ SLA) được cấu hình tại tab <a href="#reminders" onclick="$(\'a[href=&quot;#reminders&quot;]\').tab(\'show\'); return false;">Quy Tắc Tự Động Hóa</a>.';
$lang['sales_pipeline_permission_manage_finance_lock'] = 'Quản lý Khóa tài chính & Tỷ giá';
$lang['sales_pipeline_finance_lock_invalid_entity'] = 'Đối tượng hoặc thao tác Khóa tài chính không hợp lệ.';
$lang['sales_pipeline_finance_lock_reference_reason_required'] = 'Cần nhập mã phê duyệt và lý do để khóa dữ liệu tài chính.';
$lang['sales_pipeline_finance_lock_update_failed'] = 'Không thể cập nhật trạng thái Khóa tài chính.';

// Estimate Revenue KPI Filtering & Scope
$lang['sales_pipeline_kpi_filtered_by'] = 'Đang lọc';
$lang['sales_pipeline_view_all_short'] = 'Xem tất cả';
$lang['sales_pipeline_view_company_total'] = 'Quay về tổng doanh thu công ty';

// WhatsApp Baileys Gateway Reminder Channel
$lang['sp_reminder_whatsapp_settings_title'] = 'Kênh Gửi WhatsApp (Baileys Gateway)';
$lang['sp_reminder_whatsapp_enable_label'] = 'Kích hoạt gửi cảnh báo qua WhatsApp';
$lang['sp_reminder_whatsapp_endpoint_label'] = 'Địa chỉ Gateway (Endpoint Send)';
$lang['sp_reminder_whatsapp_secret_label'] = 'Secret Key bảo vệ Gateway';
$lang['sp_reminder_whatsapp_secret_placeholder'] = 'Nhập Secret Key bảo mật...';
$lang['sp_reminder_whatsapp_manager_mode_label'] = 'Chế độ gửi cho Quản lý';
$lang['sp_reminder_whatsapp_mode_group_only'] = 'Chỉ gửi vào Group WhatsApp của Quản lý';
$lang['sp_reminder_whatsapp_mode_direct_only'] = 'Chỉ gửi trực tiếp tới từng Quản lý (qua SĐT Staff)';
$lang['sp_reminder_whatsapp_mode_both'] = 'Gửi cả vào Group và trực tiếp từng Quản lý';
$lang['sp_reminder_whatsapp_mode_direct_hint'] = 'Hệ thống sẽ tự động quét danh sách Quản lý / Admin có số điện thoại hợp lệ trong CRM để gửi thông báo riêng tư.';
$lang['sp_reminder_whatsapp_group_jid_label'] = 'ID Nhóm Quản lý (Group JID)';
$lang['sp_reminder_whatsapp_base_url_label'] = 'CRM Base URL cho Deeplink (Mobile)';
$lang['sp_reminder_whatsapp_timeout_label'] = 'Request Timeout (giây)';
$lang['sp_reminder_whatsapp_hourly_limit_label'] = 'Hạn mức gửi WhatsApp (tin/giờ)';
$lang['sp_reminder_whatsapp_test_button'] = 'Kiểm tra kết nối (Test Connection)';
$lang['sp_reminder_whatsapp_test_connecting'] = 'Đang kiểm tra...';
$lang['sp_reminder_whatsapp_test_connecting_gw'] = 'Đang kết nối tới Gateway...';
$lang['sp_reminder_whatsapp_test_conn_error'] = 'Lỗi kết nối Gateway: %s';
$lang['sp_reminder_whatsapp_test_not_connected'] = 'Gateway đang ở trạng thái "%s". Hãy quét QR hoặc bật session trên Gateway.';
$lang['sp_reminder_whatsapp_test_connected_no_recipient'] = 'Kết nối Gateway thành công! (Chưa gửi tin do chưa chọn nhóm hoặc chưa có SĐT phụ trách).';
$lang['sp_reminder_whatsapp_test_success'] = 'Gửi tin nhắn thử nghiệm thành công tới: %s';
$lang['sp_reminder_whatsapp_test_send_failed'] = 'Gửi tin thử nghiệm thất bại: %s';
$lang['sp_reminder_whatsapp_test_timeout_friendly'] = 'Gateway phản hồi quá thời gian chờ (hết %s giây). Nếu gửi vào nhóm đông người, hãy tăng giá trị "Request Timeout".';
$lang['sp_reminder_whatsapp_test_status_success'] = 'Thành công';
$lang['sp_reminder_whatsapp_test_status_failed'] = 'Thất bại';
$lang['sp_reminder_whatsapp_msg_staff'] = 'Phụ trách';
$lang['sp_reminder_whatsapp_msg_customer'] = 'Khách hàng';
$lang['sp_reminder_whatsapp_msg_deal'] = 'Deal';
$lang['sp_reminder_whatsapp_msg_estimate'] = 'Báo giá';
$lang['sp_reminder_whatsapp_msg_issue'] = 'Vấn đề';
$lang['sp_reminder_whatsapp_msg_content'] = 'Nội dung';
$lang['sp_reminder_whatsapp_msg_inactive_time'] = 'Thời gian bất động';
$lang['sp_reminder_whatsapp_msg_days'] = 'ngày';
$lang['sp_reminder_whatsapp_msg_quick_action'] = 'Thao tác nhanh trên CRM';
$lang['sp_reminder_whatsapp_msg_view_deal'] = 'Xem Deal';
$lang['sp_reminder_whatsapp_msg_view_estimate'] = 'Xem Báo giá';
$lang['sp_reminder_whatsapp_msg_view_reminder'] = 'Xem chi tiết nhắc nhở';
$lang['sp_reminder_whatsapp_msg_test_title'] = 'Sales Pipeline - Test Connection';
$lang['sp_reminder_whatsapp_msg_view_revenue_chart'] = 'Xem Biểu đồ Doanh thu';
$lang['sp_reminder_whatsapp_msg_view_staff_revenue_chart'] = 'Xem Biểu đồ Doanh thu (%s)';
$lang['sp_reminder_whatsapp_fetch_groups_btn'] = 'Tải danh sách nhóm';
$lang['sp_reminder_whatsapp_fetch_groups_loading'] = 'Đang tải nhóm...';
$lang['sp_reminder_whatsapp_fetch_groups_success'] = 'Đã tải thành công %s nhóm từ WhatsApp!';
$lang['sp_reminder_whatsapp_fetch_groups_empty'] = 'Không tìm thấy nhóm WhatsApp nào hoặc Gateway chưa tham gia nhóm.';
$lang['sp_reminder_whatsapp_select_group_placeholder'] = '-- Chọn nhóm WhatsApp từ danh sách --';

// Reminder UI Guide Panels (Performance-style Documentation)
$lang['sp_reminder_guide_global_title'] = 'Cơ chế Vận hành & Khung giờ Gửi';
$lang['sp_reminder_guide_global_schedule'] = '<strong>Tự động quét & Cuối tuần:</strong> Hệ thống tự động kiểm tra định kỳ các cơ hội & báo giá. Khi bật "Bỏ qua Thứ 7, Chủ nhật", mọi thông báo nhắc nhở sẽ tạm dừng trong 2 ngày này.';
$lang['sp_reminder_guide_global_holidays'] = '<strong>Danh sách Ngày nghỉ lễ:</strong> Khai báo ngày nghỉ theo định dạng <code>YYYY-MM-DD</code> (mỗi ngày một dòng, vd: <code>2026-04-30</code>). Hệ thống sẽ không kích hoạt nhắc nhở vào các ngày này.';
$lang['sp_reminder_guide_global_quiet_hours'] = '<strong>Giờ im lặng (Quiet Hours):</strong> Các thông báo phát sinh trong khung giờ nghỉ ngơi sẽ được bảo lưu an toàn và tự động gửi bù vào đầu giờ làm việc tiếp theo.';
$lang['sp_reminder_guide_global_sla'] = '<strong>Thời hạn SLA:</strong> Số giờ tối đa để NVKD phản hồi nhắc nhở trước khi bị tính là vi phạm SLA (kết nối trực tiếp tới Điểm hiệu suất).';

$lang['sp_reminder_guide_email_title'] = 'Nguyên tắc Gửi Bản sao cho Quản lý';
$lang['sp_reminder_guide_email_manager'] = '<strong>Truy vết Quản lý tự động:</strong> Hệ thống tự động nhận diện cấp Quản lý trực tiếp của nhân viên phụ trách thông qua cấu trúc phân quyền và phân cấp Staff trong CRM.';
$lang['sp_reminder_guide_email_scope'] = '<strong>Lọc phạm vi gửi:</strong> Chọn "Chỉ việc khẩn cấp" để giảm tải hộp thư của Quản lý, chỉ gửi khi Deal bị bỏ quên quá hạn hoặc Báo giá sắp hết hiệu lực.';
$lang['sp_reminder_guide_email_fallback'] = '<strong>Email dự phòng:</strong> Áp dụng gửi bản sao tới danh sách email này khi nhân sự chưa được phân bổ Quản lý trực tiếp (hỗ trợ nhiều email phân tách bằng dấu phẩy).';

$lang['sp_reminder_guide_whatsapp_title'] = 'Hướng dẫn Tích hợp WhatsApp Gateway';
$lang['sp_reminder_guide_whatsapp_gateway'] = '<strong>Baileys Gateway Độc lập:</strong> Dịch vụ Gateway Node.js riêng biệt giúp gửi tin nhắn cảnh báo trực tiếp qua giao thức WhatsApp Web ổn định, không phát sinh chi phí theo tin của Meta Business API.';
$lang['sp_reminder_guide_whatsapp_modes'] = '<strong>Chế độ gửi Quản lý:</strong> Linh hoạt lựa chọn gửi tập trung vào Nhóm Quản lý (Group JID), gửi riêng tư tới từng Quản lý (qua SĐT Staff), hoặc gửi đồng thời cả hai.';
$lang['sp_reminder_guide_whatsapp_deeplink'] = '<strong>Deeplink Mobile:</strong> Base URL giúp nút bấm trên tin nhắn WhatsApp mở thẳng vào Deal/Báo giá ngay trên ứng dụng hoặc trình duyệt điện thoại.';
$lang['sp_reminder_guide_whatsapp_limits'] = '<strong>Hạn mức an toàn & Timeout:</strong> Giới hạn số tin/giờ để chống spam và tránh rủi ro số bị chặn. Tăng timeout (10-15s) nếu gửi vào các Group đông thành viên.';

$lang['sp_reminder_guide_deal_title'] = 'Cơ chế Giám sát Pipeline & Deal';
$lang['sp_reminder_guide_deal_desc'] = '<strong>Lịch kiểm tra & Cảnh báo:</strong> Quét định kỳ vào các ngày làm việc (Thứ 2, Thứ 4, Thứ 6) để cảnh báo khi số lượng Deal đang mở dưới mức tối thiểu. Đồng thời tự động phát hiện Deal không có hoạt động theo dõi (Stale) dựa trên tần suất riêng của từng Deal, có giới hạn số lượng nhắc nhở mỗi đợt.';

$lang['sp_reminder_guide_kpi_title'] = 'Cơ chế Theo dõi Chỉ tiêu Báo giá';
// Policy V2 Manager Recipient Roles & Preview
$lang['sp_settings_reminder_manager_roles_title'] = 'Người nhận Cảnh báo Quản lý & Email CC (Chính sách V2)';
$lang['sp_settings_reminder_policy_v2_enabled_label'] = 'Kích hoạt Chính sách Người nhận V2 (Tách biệt quyền xem và trách nhiệm nhận cảnh báo)';
$lang['sp_settings_reminder_policy_v2_enabled_help'] = 'Khi bật, chỉ Quản lý kinh doanh hợp lệ mới nhận CC/WhatsApp; Admin kỹ thuật thuần túy chỉ nhận cảnh báo hạ tầng; không tự động fallback ra ngoài.';
$lang['sp_settings_reminder_manager_source_label'] = 'Nguồn xác định Quản lý kinh doanh:';
$lang['sp_settings_reminder_source_explicit_view'] = 'Chỉ nhân sự có dòng quyền Xem (Chung) được cấp rõ ràng trong Phân quyền';
$lang['sp_settings_reminder_source_selected_staff'] = 'Chỉ định danh sách Quản lý kinh doanh cụ thể (khuyến nghị cho Admin kiêm nhiệm)';
$lang['sp_settings_reminder_selected_staff_label'] = 'Danh sách Quản lý kinh doanh nhận cảnh báo:';
$lang['sp_settings_reminder_selected_staff_help'] = 'Chỉ các nhân sự đang hoạt động và có quyền xem toàn cục mới hợp lệ. Nhân sự chỉ có quyền xem riêng (view_own) sẽ bị loại bỏ.';
$lang['sp_settings_reminder_email_cc_fallback_help'] = 'Lưu ý: Trong Chính sách V2, nếu danh sách Quản lý rỗng, hệ thống sẽ tạm dừng gửi cảnh báo Quản lý để bảo mật dữ liệu, không tự động gửi tới email dự phòng này.';
$lang['sp_reminder_preview_recipients_btn'] = 'Xem trước Người nhận';
$lang['sp_reminder_preview_panel_title'] = 'Kết quả Phân tích Người nhận Cảnh báo (Live Preview)';
$lang['sp_reminder_preview_empty_warning'] = 'CẢNH BÁO: Danh sách Quản lý kinh doanh đang rỗng!';
$lang['sp_reminder_preview_included_managers'] = 'Quản lý kinh doanh hợp lệ sẽ nhận cảnh báo';
$lang['sp_reminder_preview_none_included'] = 'Chưa có Quản lý kinh doanh nào được xác định theo cấu hình hiện tại.';
$lang['sp_reminder_preview_excluded_candidates'] = 'Các trường hợp bị loại trừ';
$lang['sp_reminder_preview_technical_admins'] = 'Admin Kỹ thuật nhận cảnh báo lỗi hạ tầng (SMTP / BCC / Gateway)';
$lang['sp_reminder_preview_technical_admins_desc'] = 'Các tài khoản này chỉ nhận thông báo khi có sự cố kỹ thuật hạ tầng, không nhận cảnh báo kinh doanh.';
$lang['sp_reminder_guide_roles_title'] = 'Nguyên tắc Phân quyền Người nhận (V2)';
$lang['sp_reminder_guide_roles_p1'] = '<strong>Tách quyền xem khỏi nhận tin:</strong> Nhân sự có quyền xem Dashboard không mặc định phải nhận cảnh báo nhắc nhở hằng ngày.';
$lang['sp_reminder_guide_roles_p2'] = '<strong>Bảo vệ Admin kỹ thuật:</strong> Tài khoản Admin IT thuần túy chỉ nhận lỗi hạ tầng, không bị làm phiền bởi thông báo Deal hay Báo giá.';
$lang['sp_reminder_guide_roles_p3'] = '<strong>An toàn dữ liệu & Không fallback ngầm:</strong> Nếu không có Quản lý hợp lệ, hệ thống không tự ý chuyển tiếp cảnh báo ra ngoài danh sách kiểm soát.';
$lang['sp_reminder_guide_roles_note'] = 'Nhân viên phụ trách (Owner) luôn nhận thông báo riêng của chính mình qua Bell CRM và Email.';


