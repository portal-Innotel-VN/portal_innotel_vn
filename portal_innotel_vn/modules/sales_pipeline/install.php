<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI =& get_instance();

require_once(__DIR__ . '/includes/estimate_group_schema.php');
require_once(__DIR__ . '/includes/performance_score_defaults.php');
require_once(__DIR__ . '/includes/reminder_rule_defaults.php');

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

    // Seed trạng thái mặc định (chuẩn UI/UX tâm lý học màu sắc)
    $statuses = [
        ['Đang tư vấn',           '#5B93D3', 1, 0, 0],
        ['Đang báo giá',          '#3B7DD8', 2, 0, 0],
        ['Đã gửi báo giá',        '#F5A623', 3, 0, 0],
        ['KH đang duyệt',         '#E8913A', 4, 0, 0],
        ['Đã ký hợp đồng',        '#27AE60', 5, 1, 0],
        ['Đã xuất hóa đơn',       '#2ECC71', 6, 1, 0],
        ['Đã triển khai',          '#1ABC9C', 7, 1, 0],
        ['Tạm ngưng',              '#95A5A6', 8, 0, 0],
        ['KH chọn NCC khác',      '#E74C3C', 9, 0, 1],
        ['Không phê duyệt',       '#C0392B', 10, 0, 1],
        ['Vượt ngân sách',         '#8E44AD', 11, 0, 1],
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
} else {
    // Nếu bảng đã tồn tại, tự động cập nhật lại màu sắc mới cho các trạng thái chuẩn
    $color_updates = [
        'Đang tư vấn'           => '#5B93D3',
        'Đang báo giá'          => '#3B7DD8',
        'Đã gửi báo giá'        => '#F5A623',
        'KH đang duyệt'         => '#E8913A',
        'Đã ký hợp đồng'        => '#27AE60',
        'Đã xuất hóa đơn'       => '#2ECC71',
        'Đã triển khai'          => '#1ABC9C',
        'Tạm ngưng'              => '#95A5A6',
        'KH chọn NCC khác'      => '#E74C3C',
        'Không phê duyệt'       => '#C0392B',
        'Vượt ngân sách'         => '#8E44AD',
    ];
    foreach ($color_updates as $name => $color) {
        $CI->db->where('name', $name);
        $CI->db->update(db_prefix() . 'sales_pipeline_statuses', ['color' => $color]);
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
        `source_id` int(11) DEFAULT NULL COMMENT 'FK tblsales_pipeline_sources - Nguồn KH',

        `deal_name` varchar(500) DEFAULT NULL COMMENT 'Mô tả sản phẩm/dịch vụ',
        `deal_value` decimal(15,2) DEFAULT 0.00 COMMENT 'Giá bán VNĐ (doanh số)',
        `cost_price` decimal(15,2) DEFAULT NULL COMMENT 'Giá nhập VNĐ - Cho phép NULL nếu Sale chưa biết',
        `deal_date` date NOT NULL COMMENT 'Ngày tạo deal',

        `status` int(11) NOT NULL DEFAULT 1 COMMENT 'FK tblsales_pipeline_statuses',
        `confidence_level` varchar(50) DEFAULT NULL COMMENT 'prospect|tracking|confirmed - Mức độ tin cậy deal',
        `deal_phase` varchar(50) DEFAULT NULL COMMENT 'lead|active|won|lost - Giai đoạn deal',
        `reporting_period` varchar(20) DEFAULT NULL COMMENT 'Q1-2026, Q2-2026, etc. - Quý báo cáo',
        `import_batch_id` int(11) DEFAULT NULL COMMENT 'FK tblsales_pipeline_import_log',
        `notes` text DEFAULT NULL COMMENT 'Ghi chú tổng hợp từ Excel',

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
        KEY `idx_cost_price` (`cost_price`),
        KEY `idx_confidence_level` (`confidence_level`),
        KEY `idx_deal_phase` (`deal_phase`),
        KEY `idx_reporting_period` (`reporting_period`),
        KEY `idx_import_batch` (`import_batch_id`)
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

    // Kiểm tra cột source_id nếu bảng đã tồn tại
    if (!$CI->db->field_exists('source_id', db_prefix() . 'sales_pipeline')) {
        if ($CI->db->field_exists('source', db_prefix() . 'sales_pipeline')) {
            // Có cột source kiểu cũ, chuyển đổi sang source_id kiểu mới
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` ADD `source_id` int(11) DEFAULT NULL COMMENT "FK tblsales_pipeline_sources - Nguồn KH" AFTER `contact_email`;');
            
            // Di chuyển dữ liệu cũ nếu có
            $deals = $CI->db->get(db_prefix() . 'sales_pipeline')->result_array();
            foreach ($deals as $deal) {
                if (!empty($deal['source'])) {
                    // Tìm nguồn tương ứng trong tblsales_pipeline_sources
                    $CI->db->where('name', $deal['source']);
                    $src = $CI->db->get(db_prefix() . 'sales_pipeline_sources')->row_array();
                    if ($src) {
                        $CI->db->where('id', $deal['id']);
                        $CI->db->update(db_prefix() . 'sales_pipeline', ['source_id' => $src['id']]);
                    } else {
                        // Thêm mới vào tblsales_pipeline_sources để khớp
                        $CI->db->insert(db_prefix() . 'sales_pipeline_sources', ['name' => $deal['source']]);
                        $new_id = $CI->db->insert_id();
                        $CI->db->where('id', $deal['id']);
                        $CI->db->update(db_prefix() . 'sales_pipeline', ['source_id' => $new_id]);
                    }
                }
            }
            // Xóa cột source cũ
            $CI->dbforge->drop_column('sales_pipeline', 'source');
        } else {
            // Chưa có cột nào, thêm mới cột source_id
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` ADD `source_id` int(11) DEFAULT NULL COMMENT "FK tblsales_pipeline_sources - Nguồn KH" AFTER `contact_email`;');
        }
    }

    // Tự động kiểm tra và bổ sung các cột nâng cao khác nếu thiếu (Self-Healing)
    $extended_columns = [
        'confidence_level' => "VARCHAR(50) NULL COMMENT 'prospect|tracking|confirmed - Mức độ tin cậy deal' AFTER `status`",
        'deal_phase'       => "VARCHAR(50) NULL COMMENT 'lead|active|won|lost - Giai đoạn deal' AFTER `confidence_level`",
        'reporting_period' => "VARCHAR(20) NULL COMMENT 'Q1-2026, Q2-2026, etc. - Quý báo cáo' AFTER `deal_phase`",
        'import_batch_id'  => "INT(11) NULL COMMENT 'FK tblsales_pipeline_import_log' AFTER `reporting_period`",
        'notes'            => "TEXT NULL COMMENT 'Ghi chú tổng hợp từ Excel' AFTER `import_batch_id`"
    ];

    foreach ($extended_columns as $column => $definition) {
        if (!$CI->db->field_exists($column, db_prefix() . 'sales_pipeline')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . "sales_pipeline` ADD `{$column}` {$definition};");
        }
    }

    // Thêm các index mở rộng nếu chưa có
    $extended_indexes = [
        'idx_confidence_level' => 'confidence_level',
        'idx_deal_phase'       => 'deal_phase',
        'idx_reporting_period' => 'reporting_period',
        'idx_import_batch'     => 'import_batch_id'
    ];

    foreach ($extended_indexes as $idx_name => $col_name) {
        $indexes = $CI->db->query("SHOW INDEX FROM `" . db_prefix() . "sales_pipeline` WHERE Key_name = '{$idx_name}'")->result_array();
        if (empty($indexes)) {
            $CI->db->query("ALTER TABLE `" . db_prefix() . "sales_pipeline` ADD INDEX `{$idx_name}` (`{$col_name}`);");
        }
    }
}

sales_pipeline_ensure_estimate_group_schema($CI);

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
        `pipeline_id` int(11) DEFAULT NULL COMMENT 'FK tblsales_pipeline; NULL với reminder theo kỳ',
        `staff_id` int(11) NOT NULL COMMENT 'Nhân viên được nhắc',
        `reminder_type` varchar(50) NOT NULL DEFAULT 'email' COMMENT 'email / notification',
        `rule_code` varchar(80) NOT NULL,
        `entity_type` varchar(40) NOT NULL DEFAULT 'deal',
        `entity_id` int(11) NULL,
        `period_key` varchar(20) NOT NULL,
        `checkpoint` varchar(20) NOT NULL,
        `severity` varchar(20) NOT NULL,
        `response_required` tinyint(1) NOT NULL DEFAULT 1,
        `response_sla_hours` smallint(5) unsigned NULL,
        `title` varchar(255) NULL,
        `message` text NULL COMMENT 'Nội dung nhắc nhở',
        `snapshot_json` longtext NOT NULL,
        `acknowledged_at` datetime NULL,
        `acknowledged_by` int(11) NULL,
        `dedupe_key` varchar(191) NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `staff_response` text DEFAULT NULL COMMENT 'Phản hồi từ nhân viên',
        `responded_at` datetime DEFAULT NULL,
        `sent_at` datetime NULL DEFAULT NULL,
        `response_due_at` datetime NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_sales_pipeline_reminder_dedupe` (`dedupe_key`),
        KEY `pipeline_id` (`pipeline_id`),
        KEY `staff_id` (`staff_id`),
        KEY `idx_reminder_staff_period` (`staff_id`, `period_key`),
        KEY `idx_reminder_rule_period` (`rule_code`, `period_key`),
        KEY `idx_reminder_inbox_queue` (`staff_id`, `response_required`, `acknowledged_at`, `staff_response`(100)),
        KEY `idx_reminder_sla_eval` (`staff_id`, `entity_type`, `response_required`, `response_due_at`, `responded_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

require_once(module_dir_path('sales_pipeline', 'includes/reminder_repository_schema.php'));
sales_pipeline_ensure_reminder_repository_schema($CI);

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

// Bảng nguồn KH (sales_pipeline_sources)
if (!$CI->db->table_exists(db_prefix() . 'sales_pipeline_sources')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "sales_pipeline_sources` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// Seed nguồn khách hàng mặc định nếu bảng trống
$total_sources = $CI->db->count_all(db_prefix() . 'sales_pipeline_sources');
if ($total_sources == 0) {
    $default_sources = [
        ['name' => 'Email'],
        ['name' => 'Facebook'],
        ['name' => 'Google'],
        ['name' => 'Giới thiệu'],
        ['name' => 'Khác']
    ];
    foreach ($default_sources as $src) {
        $CI->db->insert(db_prefix() . 'sales_pipeline_sources', $src);
    }
}

// Bảng log import (sales_pipeline_import_log)
if (!$CI->db->table_exists(db_prefix() . 'sales_pipeline_import_log')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "sales_pipeline_import_log` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `file_name` varchar(255) NOT NULL,
        `file_path` varchar(500) DEFAULT NULL,
        `uploaded_by` int(11) NOT NULL,
        `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `rows_imported` int(11) NOT NULL DEFAULT 0,
        `rows_skipped` int(11) NOT NULL DEFAULT 0,
        `import_status` varchar(50) NOT NULL DEFAULT 'processing',
        `error_message` text,
        PRIMARY KEY (`id`),
        KEY `uploaded_by` (`uploaded_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// Performance Score v1 defaults. add_option preserves administrator overrides.
sales_pipeline_seed_performance_score_options();
sales_pipeline_seed_reminder_rule_options();

// Ensure Migration 109 & 113 Architecture schemas and backfill exist authoritatively
require_once(__DIR__ . '/includes/architecture_113_schema.php');
sales_pipeline_ensure_deal_estimate_groups_schema($CI);
sales_pipeline_ensure_architecture_113_schema($CI);
sales_pipeline_run_architecture_113_backfill($CI);
sales_pipeline_upgrade_target_options_113();
update_option('sp_schema_v114_synced', '1');

