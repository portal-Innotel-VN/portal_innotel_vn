<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_101 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // 1. Thêm các cột mở rộng nếu chưa có trong DB (để nâng cấp từ bản cũ)
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

        // 2. Thêm các index mở rộng nếu chưa có
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
}
