<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Migration 248: Đổi tên cột expected_close_date → deal_date
 * 
 * Cập nhật tên cột cho đúng ngữ nghĩa thực tế:
 * Trường này lưu ngày nhân viên ghi nhận deal, không phải ngày dự kiến chốt.
 */
class Migration_Version_248 extends CI_Migration
{
    public function up()
    {
        // Đổi tên cột
        $this->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` CHANGE `expected_close_date` `deal_date` DATE NOT NULL COMMENT "Ngày tạo deal"');

        // Đổi tên index
        $this->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` DROP INDEX `expected_close_date`');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` ADD INDEX `deal_date` (`deal_date`)');
    }

    public function down()
    {
        // Khôi phục tên cột
        $this->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` CHANGE `deal_date` `expected_close_date` DATE NOT NULL COMMENT "Ngày dự kiến kết quả"');

        // Khôi phục index
        $this->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` DROP INDEX `deal_date`');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'sales_pipeline` ADD INDEX `expected_close_date` (`expected_close_date`)');
    }
}
