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
$lang['sales_pipeline_activity_timeline'] = 'Lịch sử cập nhật';

$lang['sales_pipeline_reminder_settings']  = 'Nhắc Nhở & Phân Công';
$lang['sales_pipeline_reminder_enabled']   = 'Bật nhắc nhở tự động';
$lang['sales_pipeline_reminder_frequency'] = 'Tần suất nhắc nhở';
$lang['sales_pipeline_days']               = 'ngày/lần';
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
$lang['sales_pipeline_import_upload_failed']       = 'Không thể upload file. Vui lòng thử lại.';
$lang['sales_pipeline_import_no_valid_data']       = 'File Excel không có dữ liệu hợp lệ (vùng dữ liệu trống).';
$lang['sales_pipeline_import_result_summary']       = 'Import thành công: %d tạo mới, %d cập nhật, %d bỏ qua.';
$lang['sales_pipeline_import_read_error']           = 'Lỗi đọc file';

// Views & UI Confirm Messages
$lang['sales_pipeline_load_more_failed']            = 'Có lỗi xảy ra khi tải thêm deal';
$lang['sales_pipeline_please_select_excel_file']     = 'Vui lòng kéo thả hoặc click chọn tệp tin Excel (.xls, .xlsx) trước khi nhấn Import.';
$lang['sales_pipeline_confirm_contract_received']   = 'Bạn đã cầm Hợp đồng bản cứng trên tay chưa?';
$lang['sales_pipeline_confirm_invoice_issued']      = 'Bạn đã xác nhận việc Kế toán đã xuất VAT chưa?';
