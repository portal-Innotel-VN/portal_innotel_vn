<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Bảng trạng thái deal
if (!$CI->db->table_exists(db_prefix() . 'sales_pipeline_statuses')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "sales_pipeline_statuses` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `color` varchar(10) NOT NULL DEFAULT '#333333',
        `order` int(3) NOT NULL DEFAULT 0,
        `is_won` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Trạng thái thắng deal',
        `is_lost` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Trạng thái mất deal',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');

    // Seed trạng thái mặc định (dựa trên phân tích Excel thực tế phòng KD)
    $statuses = [
        ['Đang tư vấn',           '#3498db', 1, 0, 0],
        ['Đang báo giá',          '#f39c12', 2, 0, 0],
        ['Đã gửi báo giá',        '#e67e22', 3, 0, 0],
        ['KH đang duyệt',         '#9b59b6', 4, 0, 0],
        ['Đã ký hợp đồng',        '#27ae60', 5, 1, 0],
        ['Đã xuất hóa đơn',       '#2ecc71', 6, 1, 0],
        ['Đã triển khai',          '#1abc9c', 7, 1, 0],
        ['Tạm ngưng',              '#95a5a6', 8, 0, 0],
        ['KH chọn NCC khác',      '#e74c3c', 9, 0, 1],
        ['Không phê duyệt',       '#c0392b', 10, 0, 1],
        ['Vượt ngân sách',         '#d35400', 11, 0, 1],
    ];

    foreach ($statuses as $s) {
        $CI->db->insert(db_prefix() . 'sales_pipeline_statuses', [
            'name'    => $s[0],
            'color'   => $s[1],
            'order'   => $s[2],
            'is_won'  => $s[3],
            'is_lost' => $s[4],
        ]);
    }
}

// Bảng pipeline chính
if (!$CI->db->table_exists(db_prefix() . 'sales_pipeline')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "sales_pipeline` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `staff_id` int(11) NOT NULL COMMENT 'FK tblstaff - nhân viên sale phụ trách',

        `customer_name` varchar(255) NOT NULL,
        `contact_name` varchar(255) DEFAULT NULL,
        `contact_phone` varchar(50) DEFAULT NULL,
        `contact_email` varchar(100) DEFAULT NULL,
        `source` varchar(100) DEFAULT NULL COMMENT 'Nguồn KH: SEO, Ads, Giới thiệu...',

        `deal_name` varchar(500) NOT NULL COMMENT 'Mô tả sản phẩm/dịch vụ',
        `deal_value` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Giá bán VNĐ (doanh số)',
        `cost_price` decimal(15,2) DEFAULT NULL COMMENT 'Giá nhập VNĐ - Cho phép NULL nếu Sale chưa biết',
        `deal_date` date NOT NULL COMMENT 'Ngày tạo deal',

        `status` int(11) NOT NULL DEFAULT 1 COMMENT 'FK tblsales_pipeline_statuses',
        `contract_signed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Đã ký HĐ',
        `invoice_issued` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Đã xuất HĐ',

        `reminder_enabled` tinyint(1) NOT NULL DEFAULT 1,
        `reminder_frequency` int(11) DEFAULT 2 COMMENT 'Tần suất nhắc (ngày)',
        `last_reminder_sent` datetime DEFAULT NULL,

        `estimate_id` int(11) DEFAULT NULL COMMENT 'FK tblestimates',
        `lead_id` int(11) DEFAULT NULL COMMENT 'FK tblleads',

        `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `datemodified` datetime DEFAULT NULL,
        `addedfrom` int(11) NOT NULL COMMENT 'Staff tạo record',
        `imported_by` int(11) DEFAULT NULL COMMENT 'FK tblstaff - Người thực hiện import',
        PRIMARY KEY (`id`),
        KEY `staff_id` (`staff_id`),
        KEY `status` (`status`),
        KEY `deal_date` (`deal_date`),
        KEY `idx_cost_price` (`cost_price`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
} else {
    // Nếu bảng đã tồn tại, kiểm tra xem đã có cột cost_price chưa để alter table
    if (!$CI->db->field_exists('cost_price', db_prefix() . 'sales_pipeline')) {
        // Kiểm tra xem có cột cũ purchase_price không, nếu có thì CHANGE, nếu không thì ADD
        if ($CI->db->field_exists('purchase_price', db_prefix() . 'sales_pipeline')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` CHANGE `purchase_price` `cost_price` decimal(15,2) DEFAULT NULL COMMENT "Giá nhập VNĐ - Cho phép NULL nếu Sale chưa biết";');
        } else {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` ADD `cost_price` decimal(15,2) DEFAULT NULL COMMENT "Giá nhập VNĐ - Cho phép NULL nếu Sale chưa biết" AFTER `deal_value`;');
        }
    }
    // Xóa profit_margin và expected_profit nếu còn tồn tại
    if ($CI->db->field_exists('profit_margin', db_prefix() . 'sales_pipeline')) {
        $CI->dbforge->drop_column('sales_pipeline', 'profit_margin');
    }
    if ($CI->db->field_exists('expected_profit', db_prefix() . 'sales_pipeline')) {
        $CI->dbforge->drop_column('sales_pipeline', 'expected_profit');
    }
    // Thêm imported_by nếu chưa có
    if (!$CI->db->field_exists('imported_by', db_prefix() . 'sales_pipeline')) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` ADD `imported_by` int(11) DEFAULT NULL COMMENT "FK tblstaff - Người thực hiện import" AFTER `addedfrom`;');
    }
    // Thêm index cho cost_price nếu chưa có
    $indexes = $CI->db->query("SHOW INDEX FROM `" . db_prefix() . "sales_pipeline` WHERE Key_name = 'idx_cost_price'")->result_array();
    if (empty($indexes)) {
        $CI->db->query("ALTER TABLE `" . db_prefix() . "sales_pipeline` ADD INDEX `idx_cost_price` (`cost_price`);");
    }
}

// Bảng activity log (timeline tiến độ thay cho cột ghi chú nối →)
if (!$CI->db->table_exists(db_prefix() . 'sales_pipeline_activity')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "sales_pipeline_activity` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `pipeline_id` int(11) NOT NULL COMMENT 'FK tblsales_pipeline',
        `staff_id` int(11) NOT NULL COMMENT 'Người cập nhật',
        `description` text NOT NULL COMMENT 'Nội dung cập nhật',
        `old_status` int(11) DEFAULT NULL,
        `new_status` int(11) DEFAULT NULL,
        `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `pipeline_id` (`pipeline_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// Bảng log nhắc nhở tự động
if (!$CI->db->table_exists(db_prefix() . 'sales_pipeline_reminders_log')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "sales_pipeline_reminders_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `pipeline_id` int(11) NOT NULL COMMENT 'FK tblsales_pipeline',
        `staff_id` int(11) NOT NULL COMMENT 'Nhân viên được nhắc',
        `reminder_type` varchar(50) NOT NULL DEFAULT 'email' COMMENT 'email / notification',
        `message` text NOT NULL COMMENT 'Nội dung nhắc nhở',
        `staff_response` text DEFAULT NULL COMMENT 'Phản hồi từ nhân viên',
        `responded_at` datetime DEFAULT NULL,
        `sent_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `pipeline_id` (`pipeline_id`),
        KEY `staff_id` (`staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// Bảng theo dõi alerts cho deal thiếu giá nhập
if (!$CI->db->table_exists(db_prefix() . 'sales_pipeline_cost_alerts')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "sales_pipeline_cost_alerts` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `pipeline_id` int(11) NOT NULL COMMENT 'FK tblsales_pipeline',
        `alerted_staff_ids` text NULL COMMENT 'JSON array của staff IDs đã được nhắc',
        `last_alert_sent` datetime NULL COMMENT 'Lần cuối gửi alert',
        `alert_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Số lần đã alert',
        `resolved_at` datetime NULL COMMENT 'Thời điểm cost_price được cập nhật',
        `resolved_by` int(11) NULL COMMENT 'Staff đã cập nhật cost_price',
        `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `pipeline_id` (`pipeline_id`),
        KEY `last_alert_sent` (`last_alert_sent`),
        KEY `resolved_at` (`resolved_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}
