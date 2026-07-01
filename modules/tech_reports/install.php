<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Bảng báo cáo hàng ngày
if (!$CI->db->table_exists(db_prefix() . 'tech_daily_reports')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "tech_daily_reports` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `staff_id` int(11) NOT NULL COMMENT 'FK tblstaff - nhân viên kỹ thuật',
        `report_date` date NOT NULL COMMENT 'Ngày báo cáo',
        `task_description` text NOT NULL COMMENT 'Mô tả công việc đã làm',
        `task_category` varchar(100) DEFAULT NULL COMMENT 'Phân loại: Bảo trì, Triển khai, Sửa lỗi, Hỗ trợ...',
        `hours_spent` decimal(4,2) DEFAULT NULL COMMENT 'Số giờ làm việc',
        `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Đang thực hiện, 2=Hoàn thành, 3=Chờ xử lý',
        `assigned_by` int(11) DEFAULT NULL COMMENT 'FK tblstaff - người phân công',
        `created_by` int(11) NOT NULL COMMENT 'FK tblstaff - người tạo (người nhập báo cáo)',
        `related_task_id` int(11) DEFAULT NULL COMMENT 'FK tbltasks - liên kết task CRM nếu có',
        `notes` text DEFAULT NULL COMMENT 'Ghi chú chi tiết',
        `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `datemodified` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `staff_id` (`staff_id`),
        KEY `report_date` (`report_date`),
        KEY `assigned_by` (`assigned_by`),
        KEY `created_by` (`created_by`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// Bảng người theo dõi
if (!$CI->db->table_exists(db_prefix() . 'tech_report_followers')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "tech_report_followers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `report_id` int(11) NOT NULL COMMENT 'FK tbltech_daily_reports',
        `staff_id` int(11) NOT NULL COMMENT 'FK tblstaff - người theo dõi',
        `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `report_id` (`report_id`),
        KEY `staff_id` (`staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// Bảng log tuân thủ nhập liệu
if (!$CI->db->table_exists(db_prefix() . 'tech_compliance_log')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "tech_compliance_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `staff_id` int(11) NOT NULL COMMENT 'FK tblstaff',
        `check_date` date NOT NULL COMMENT 'Ngày kiểm tra',
        `has_submitted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Chưa nhập, 1=Đã nhập',
        `notified` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Đã gửi thông báo nhắc nhở chưa',
        `notified_at` datetime DEFAULT NULL,
        `resolved_by` int(11) DEFAULT NULL COMMENT 'Quản lý xử lý',
        `resolved_at` datetime DEFAULT NULL,
        `notes` text DEFAULT NULL COMMENT 'Ghi chú xử lý',
        `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_staff_check_date` (`staff_id`, `check_date`),
        KEY `check_date` (`check_date`),
        KEY `has_submitted` (`has_submitted`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// Bảng cấu hình phòng ban kỹ thuật
if (!$CI->db->table_exists(db_prefix() . 'tech_department_staff')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "tech_department_staff` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `staff_id` int(11) NOT NULL COMMENT 'FK tblstaff',
        `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Active trong phòng kỹ thuật',
        `role` varchar(50) DEFAULT NULL COMMENT 'Vai trò: Tech Lead, Senior, Junior...',
        `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `staff_id` (`staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}
