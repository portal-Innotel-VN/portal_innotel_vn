<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_107 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $group_table = db_prefix() . 'sales_pipeline_estimate_groups';

        // R-02 fix: add a dedicated reconciliation cursor.
        // Using datemodified as a cursor conflates business changes with
        // reconciliation sweeps. A separate column lets the job advance
        // to groups that have never been reconciled before moving on to
        // those that were recently changed by business events.
        if (!$CI->db->field_exists('last_reconciled_at', $group_table)) {
            $CI->db->query(
                'ALTER TABLE `' . $group_table . '`'
                . ' ADD `last_reconciled_at` DATETIME NULL DEFAULT NULL'
                . ' AFTER `datemodified`'
            );
        }

        $index_exists = $CI->db->query(
            'SHOW INDEX FROM `' . $group_table . '` WHERE Key_name = "idx_last_reconciled"'
        )->row_array();

        if (!$index_exists) {
            $CI->db->query(
                'ALTER TABLE `' . $group_table . '`'
                . ' ADD INDEX `idx_last_reconciled` (`last_reconciled_at`, `id`)'
            );
        }
    }
}
