<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Migration 246: Sales Pipeline Module - Extended Fields & Import Log
 * 
 * Mở rộng module Sales Pipeline với:
 * - Các trường confidence_level, deal_phase, reporting_period, import_batch_id, notes
 * - Bảng tblsales_pipeline_import_log để truy vết lịch sử import
 */
class Migration_Version_246 extends CI_Migration
{
    public function __construct()
    {
        parent::__construct();
    }

    public function up()
    {
        // 1. Thêm các cột mới vào bảng tblsales_pipeline
        if ($this->db->table_exists(db_prefix() . 'sales_pipeline')) {
            
            // Kiểm tra và thêm cột confidence_level
            if (!$this->db->field_exists('confidence_level', db_prefix() . 'sales_pipeline')) {
                $this->dbforge->add_column('sales_pipeline', [
                    'confidence_level' => [
                        'type' => 'VARCHAR',
                        'constraint' => 50,
                        'null' => true,
                        'comment' => 'prospect|tracking|confirmed - Mức độ tin cậy deal',
                        'after' => 'status'
                    ],
                ]);
            }

            // Kiểm tra và thêm cột deal_phase
            if (!$this->db->field_exists('deal_phase', db_prefix() . 'sales_pipeline')) {
                $this->dbforge->add_column('sales_pipeline', [
                    'deal_phase' => [
                        'type' => 'VARCHAR',
                        'constraint' => 50,
                        'null' => true,
                        'comment' => 'lead|active|won|lost - Giai đoạn deal',
                        'after' => 'confidence_level'
                    ],
                ]);
            }

            // Kiểm tra và thêm cột reporting_period
            if (!$this->db->field_exists('reporting_period', db_prefix() . 'sales_pipeline')) {
                $this->dbforge->add_column('sales_pipeline', [
                    'reporting_period' => [
                        'type' => 'VARCHAR',
                        'constraint' => 20,
                        'null' => true,
                        'comment' => 'Q1-2026, Q2-2026, etc. - Quý báo cáo',
                        'after' => 'deal_phase'
                    ],
                ]);
            }

            // Kiểm tra và thêm cột import_batch_id
            if (!$this->db->field_exists('import_batch_id', db_prefix() . 'sales_pipeline')) {
                $this->dbforge->add_column('sales_pipeline', [
                    'import_batch_id' => [
                        'type' => 'INT',
                        'constraint' => 11,
                        'null' => true,
                        'comment' => 'FK tblsales_pipeline_import_log',
                        'after' => 'reporting_period'
                    ],
                ]);
            }

            // Kiểm tra và thêm cột notes
            if (!$this->db->field_exists('notes', db_prefix() . 'sales_pipeline')) {
                $this->dbforge->add_column('sales_pipeline', [
                    'notes' => [
                        'type' => 'TEXT',
                        'null' => true,
                        'comment' => 'Ghi chú tổng hợp từ Excel',
                        'after' => 'import_batch_id'
                    ],
                ]);
            }

            // Cho phép deal_name và deal_value NULL khi là prospect
            $this->db->query("ALTER TABLE `" . db_prefix() . "sales_pipeline` 
                MODIFY COLUMN `deal_name` VARCHAR(500) NULL COMMENT 'Mô tả sản phẩm/dịch vụ';");
            
            $this->db->query("ALTER TABLE `" . db_prefix() . "sales_pipeline` 
                MODIFY COLUMN `deal_value` DECIMAL(15,2) NULL DEFAULT 0.00 COMMENT 'Doanh số VNĐ';");
        }

        // 2. Tạo bảng tblsales_pipeline_import_log
        if (!$this->db->table_exists(db_prefix() . 'sales_pipeline_import_log')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'file_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => false,
                ],
                'file_path' => [
                    'type' => 'VARCHAR',
                    'constraint' => 500,
                    'null' => true,
                    'comment' => 'Đường dẫn file đã upload',
                ],
                'uploaded_by' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => false,
                    'comment' => 'FK tblstaff.staffid - Người tải lên',
                ],
                'uploaded_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
                'rows_imported' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => false,
                    'default' => 0,
                    'comment' => 'Số dòng import thành công',
                ],
                'rows_skipped' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => false,
                    'default' => 0,
                    'comment' => 'Số dòng bị bỏ qua',
                ],
                'import_status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                    'null' => false,
                    'default' => 'processing',
                    'comment' => 'processing|completed|failed',
                ],
                'error_message' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'comment' => 'Thông báo lỗi nếu có',
                ],
            ]);
            
            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('uploaded_by');
            $this->dbforge->create_table('sales_pipeline_import_log', true, ['ENGINE' => 'InnoDB']);
        }

        // 3. Thêm index cho các trường mới để tối ưu query
        if ($this->db->table_exists(db_prefix() . 'sales_pipeline')) {
            // Check if index exists before creating
            $indexes = $this->db->query("SHOW INDEX FROM `" . db_prefix() . "sales_pipeline` WHERE Key_name = 'idx_confidence_level'")->result_array();
            if (empty($indexes)) {
                $this->db->query("ALTER TABLE `" . db_prefix() . "sales_pipeline` ADD INDEX `idx_confidence_level` (`confidence_level`);");
            }
            
            $indexes = $this->db->query("SHOW INDEX FROM `" . db_prefix() . "sales_pipeline` WHERE Key_name = 'idx_deal_phase'")->result_array();
            if (empty($indexes)) {
                $this->db->query("ALTER TABLE `" . db_prefix() . "sales_pipeline` ADD INDEX `idx_deal_phase` (`deal_phase`);");
            }
            
            $indexes = $this->db->query("SHOW INDEX FROM `" . db_prefix() . "sales_pipeline` WHERE Key_name = 'idx_reporting_period'")->result_array();
            if (empty($indexes)) {
                $this->db->query("ALTER TABLE `" . db_prefix() . "sales_pipeline` ADD INDEX `idx_reporting_period` (`reporting_period`);");
            }
            
            $indexes = $this->db->query("SHOW INDEX FROM `" . db_prefix() . "sales_pipeline` WHERE Key_name = 'idx_import_batch'")->result_array();
            if (empty($indexes)) {
                $this->db->query("ALTER TABLE `" . db_prefix() . "sales_pipeline` ADD INDEX `idx_import_batch` (`import_batch_id`);");
            }
        }
    }

    public function down()
    {
        // Rollback: Xóa các cột đã thêm
        if ($this->db->table_exists(db_prefix() . 'sales_pipeline')) {
            // Xóa indexes trước
            $this->db->query("ALTER TABLE `" . db_prefix() . "sales_pipeline` DROP INDEX IF EXISTS `idx_confidence_level`;");
            $this->db->query("ALTER TABLE `" . db_prefix() . "sales_pipeline` DROP INDEX IF EXISTS `idx_deal_phase`;");
            $this->db->query("ALTER TABLE `" . db_prefix() . "sales_pipeline` DROP INDEX IF EXISTS `idx_reporting_period`;");
            $this->db->query("ALTER TABLE `" . db_prefix() . "sales_pipeline` DROP INDEX IF EXISTS `idx_import_batch`;");
            
            // Xóa cột
            if ($this->db->field_exists('notes', db_prefix() . 'sales_pipeline')) {
                $this->dbforge->drop_column('sales_pipeline', 'notes');
            }
            if ($this->db->field_exists('import_batch_id', db_prefix() . 'sales_pipeline')) {
                $this->dbforge->drop_column('sales_pipeline', 'import_batch_id');
            }
            if ($this->db->field_exists('reporting_period', db_prefix() . 'sales_pipeline')) {
                $this->dbforge->drop_column('sales_pipeline', 'reporting_period');
            }
            if ($this->db->field_exists('deal_phase', db_prefix() . 'sales_pipeline')) {
                $this->dbforge->drop_column('sales_pipeline', 'deal_phase');
            }
            if ($this->db->field_exists('confidence_level', db_prefix() . 'sales_pipeline')) {
                $this->dbforge->drop_column('sales_pipeline', 'confidence_level');
            }
        }

        // Xóa bảng import log
        if ($this->db->table_exists(db_prefix() . 'sales_pipeline_import_log')) {
            $this->dbforge->drop_table('sales_pipeline_import_log', true);
        }
    }
}
