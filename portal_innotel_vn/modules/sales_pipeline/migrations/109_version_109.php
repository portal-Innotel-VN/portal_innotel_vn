<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Migration Version 109:
 * 1. Create table tblsales_pipeline_deal_estimate_groups (1:N relationship with is_primary flag).
 * 2. Add manual lock columns to tblsales_pipeline (is_manual_lock, manual_lock_by, manual_lock_at, manual_lock_reason).
 */
class Migration_Version_109 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // 1. Create Bridge Table tblsales_pipeline_deal_estimate_groups
        $bridgeTable = db_prefix() . 'sales_pipeline_deal_estimate_groups';
        if (!$CI->db->table_exists($bridgeTable)) {
            $charset = $CI->db->char_set ? $CI->db->char_set : 'utf8mb4';
            $collate = $CI->db->dbcollat ? $CI->db->dbcollat : 'utf8mb4_unicode_ci';

            $CI->db->query("CREATE TABLE `{$bridgeTable}` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `pipeline_id` INT(11) NOT NULL COMMENT 'FK tblsales_pipeline.id (Deal)',
                `estimate_group_id` INT(11) NOT NULL COMMENT 'FK tblsales_pipeline_estimate_groups.id',
                `is_primary` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Group đại diện chính của Deal',
                `linked_by` INT(11) NULL COMMENT 'Staff thực hiện liên kết',
                `datecreated` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_estimate_group` (`estimate_group_id`),
                KEY `idx_pipeline` (`pipeline_id`),
                KEY `idx_estimate_group` (`estimate_group_id`),
                KEY `idx_deal_primary` (`pipeline_id`, `is_primary`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};");
        }

        // 2. Add manual lock fields to tblsales_pipeline
        $pipelineTable = db_prefix() . 'sales_pipeline';
        if ($CI->db->table_exists($pipelineTable)) {
            if (!$CI->db->field_exists('is_manual_lock', $pipelineTable)) {
                $CI->db->query("ALTER TABLE `{$pipelineTable}` ADD COLUMN `is_manual_lock` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Khóa thủ công' AFTER `notes`;");
            }
            if (!$CI->db->field_exists('manual_lock_by', $pipelineTable)) {
                $CI->db->query("ALTER TABLE `{$pipelineTable}` ADD COLUMN `manual_lock_by` INT(11) NULL COMMENT 'Staff khóa' AFTER `is_manual_lock`;");
            }
            if (!$CI->db->field_exists('manual_lock_at', $pipelineTable)) {
                $CI->db->query("ALTER TABLE `{$pipelineTable}` ADD COLUMN `manual_lock_at` DATETIME NULL COMMENT 'Thời điểm khóa' AFTER `manual_lock_by`;");
            }
            if (!$CI->db->field_exists('manual_lock_reason', $pipelineTable)) {
                $CI->db->query("ALTER TABLE `{$pipelineTable}` ADD COLUMN `manual_lock_reason` TEXT NULL COMMENT 'Lý do khóa' AFTER `manual_lock_at`;");
            }

            // Add index if not exists
            $indexes = $CI->db->query("SHOW INDEX FROM `{$pipelineTable}` WHERE Key_name = 'idx_manual_lock'")->result_array();
            if (empty($indexes)) {
                $CI->db->query("ALTER TABLE `{$pipelineTable}` ADD INDEX `idx_manual_lock` (`is_manual_lock`);");
            }
        }
    }

    public function down()
    {
        // Non-destructive: We preserve audit and business data on downgrade
    }
}
