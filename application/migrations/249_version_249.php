<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Migration 249: Chuyển đổi sang mô hình Lợi nhuận = Giá bán - Giá nhập
 * 
 * Thay đổi chính:
 * - Đổi tên cột purchase_price → cost_price (Giá nhập)
 * - Đảm bảo cost_price cho phép NULL (Sale không cần biết giá nhập)
 * - Xóa cột profit_margin và expected_profit (không lưu DB, tính runtime)
 * - Thêm trường imported_by để phân biệt người import vs người phụ trách
 */
class Migration_Version_249 extends CI_Migration
{
    public function __construct()
    {
        parent::__construct();
    }

    public function up()
    {
        $CI = &get_instance();
        
        if ($CI->db->table_exists(db_prefix() . 'sales_pipeline')) {
            
            // 1. Đổi tên purchase_price → cost_price (nếu tồn tại)
            if ($CI->db->field_exists('purchase_price', db_prefix() . 'sales_pipeline')) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` 
                    CHANGE `purchase_price` `cost_price` DECIMAL(15,2) NULL DEFAULT NULL 
                    COMMENT "Giá nhập VNĐ - Cho phép NULL nếu Sale chưa biết"');
            }
            
            // 2. Nếu chưa có cost_price, tạo mới
            if (!$CI->db->field_exists('cost_price', db_prefix() . 'sales_pipeline')) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` 
                    ADD `cost_price` DECIMAL(15,2) NULL DEFAULT NULL 
                    COMMENT "Giá nhập VNĐ - Cho phép NULL nếu Sale chưa biết" 
                    AFTER `deal_value`');
            }
            
            // 3. Xóa các cột profit_margin và expected_profit (không còn dùng)
            if ($CI->db->field_exists('profit_margin', db_prefix() . 'sales_pipeline')) {
                $CI->dbforge->drop_column('sales_pipeline', 'profit_margin');
            }
            
            if ($CI->db->field_exists('expected_profit', db_prefix() . 'sales_pipeline')) {
                $CI->dbforge->drop_column('sales_pipeline', 'expected_profit');
            }
            
            // 4. Thêm cột imported_by để lưu người thực hiện import (khác với staff_id)
            if (!$CI->db->field_exists('imported_by', db_prefix() . 'sales_pipeline')) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` 
                    ADD `imported_by` INT(11) NULL DEFAULT NULL 
                    COMMENT "FK tblstaff - Người thực hiện import" 
                    AFTER `addedfrom`');
            }
            
            // 5. Thêm index cho cost_price để tối ưu query tìm deal thiếu giá nhập
            $indexes = $CI->db->query("SHOW INDEX FROM `" . db_prefix() . "sales_pipeline` WHERE Key_name = 'idx_cost_price'")->result_array();
            if (empty($indexes)) {
                $CI->db->query("ALTER TABLE `" . db_prefix() . "sales_pipeline` ADD INDEX `idx_cost_price` (`cost_price`);");
            }
            
            // 6. Cập nhật comment cho deal_value để rõ nghĩa
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` 
                MODIFY COLUMN `deal_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00 
                COMMENT "Giá bán VNĐ (doanh số)"');
        }
        
        // 7. Tạo bảng theo dõi alerts cho deal thiếu giá nhập
        if (!$CI->db->table_exists(db_prefix() . 'sales_pipeline_cost_alerts')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . "sales_pipeline_cost_alerts` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `pipeline_id` int(11) NOT NULL COMMENT 'FK tblsales_pipeline',
                `alerted_staff_ids` TEXT NULL COMMENT 'JSON array của staff IDs đã được nhắc',
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
    }

    public function down()
    {
        $CI = &get_instance();
        
        if ($CI->db->table_exists(db_prefix() . 'sales_pipeline')) {
            // Khôi phục lại cấu trúc cũ
            
            // Xóa index
            $CI->db->query("ALTER TABLE `" . db_prefix() . "sales_pipeline` DROP INDEX IF EXISTS `idx_cost_price`;");
            
            // Đổi tên cost_price → purchase_price
            if ($CI->db->field_exists('cost_price', db_prefix() . 'sales_pipeline')) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` 
                    CHANGE `cost_price` `purchase_price` DECIMAL(15,2) NULL DEFAULT NULL 
                    COMMENT "Giá nhập VNĐ"');
            }
            
            // Xóa imported_by
            if ($CI->db->field_exists('imported_by', db_prefix() . 'sales_pipeline')) {
                $CI->dbforge->drop_column('sales_pipeline', 'imported_by');
            }
            
            // Khôi phục profit_margin và expected_profit
            if (!$CI->db->field_exists('profit_margin', db_prefix() . 'sales_pipeline')) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` 
                    ADD `profit_margin` DECIMAL(5,4) DEFAULT 0.0000 
                    COMMENT "Tỷ lệ lợi nhuận (0.10 = 10%)" 
                    AFTER `purchase_price`');
            }
            
            if (!$CI->db->field_exists('expected_profit', db_prefix() . 'sales_pipeline')) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` 
                    ADD `expected_profit` DECIMAL(15,2) DEFAULT 0.00 
                    COMMENT "Tự tính = deal_value * profit_margin" 
                    AFTER `profit_margin`');
            }
        }
        
        // Xóa bảng alerts
        if ($CI->db->table_exists(db_prefix() . 'sales_pipeline_cost_alerts')) {
            $CI->dbforge->drop_table('sales_pipeline_cost_alerts', true);
        }
    }
}
