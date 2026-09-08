<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('sales_pipeline_ensure_architecture_113_schema')) {
    /**
     * Additive schema for Architecture 113:
     * - tblsales_pipeline_score_snapshots
     * - tblsales_pipeline_exchange_rates
     * - tblsales_pipeline_sanitization_batches
     * - tblsales_pipeline_sanitization_items
     * - first_sent_at, first_sent_source, first_sent_estimate_id on estimate groups
     * - is_finance_locked and audit fields on estimate groups and deals
     * - data_quality_status on reminders log
     */
    function sales_pipeline_ensure_architecture_113_schema($CI)
    {
        $charset = $CI->db->char_set ?: 'utf8';
        $prefix = db_prefix();

        // 1. Table: sales_pipeline_score_snapshots
        $snapshotsTable = $prefix . 'sales_pipeline_score_snapshots';
        if (!$CI->db->table_exists($snapshotsTable)) {
            $CI->db->query("CREATE TABLE `{$snapshotsTable}` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `snapshot_run_id` varchar(64) NOT NULL,
                `period_type` varchar(32) NOT NULL,
                `period_start` date NOT NULL,
                `period_end` date NOT NULL,
                `staff_id` int(11) NOT NULL,
                `formula_version` varchar(32) NOT NULL,
                `config_snapshot_json` text NULL,
                `raw_metrics_json` text NULL,
                `component_scores_json` text NULL,
                `data_quality_flags_json` text NULL,
                `performance_score` decimal(5,1) NOT NULL,
                `rank` int(11) NOT NULL,
                `total_ranked_staff` int(11) NOT NULL,
                `is_provisional` tinyint(1) NOT NULL DEFAULT 0,
                `snapshot_status` enum('provisional','reconstructed','finalized') NOT NULL DEFAULT 'provisional',
                `calculated_at` datetime NOT NULL,
                `finalized_at` datetime NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_score_snapshot` (`period_type`,`period_start`,`period_end`,`staff_id`,`formula_version`),
                KEY `idx_snapshot_run` (`snapshot_run_id`),
                KEY `idx_snapshot_period_status` (`period_type`,`period_start`,`period_end`,`snapshot_status`),
                KEY `idx_snapshot_staff` (`staff_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset};");
        }

        // 2. Table: sales_pipeline_exchange_rates
        $ratesTable = $prefix . 'sales_pipeline_exchange_rates';
        if (!$CI->db->table_exists($ratesTable)) {
            $CI->db->query("CREATE TABLE `{$ratesTable}` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `source_currency_id` int(11) NOT NULL,
                `base_currency_id` int(11) NOT NULL,
                `effective_date` date NOT NULL,
                `exchange_rate_to_base` decimal(20,8) NOT NULL,
                `rate_unit` varchar(64) NOT NULL DEFAULT 'base_currency_per_source_currency',
                `source` varchar(64) NOT NULL DEFAULT 'manual',
                `rate_version` int(11) NOT NULL DEFAULT 1,
                `supersedes_rate_id` int(11) NULL,
                `is_active` tinyint(1) NOT NULL DEFAULT 1,
                `approved_by` int(11) NULL,
                `approved_at` datetime NULL,
                `approval_reference` varchar(191) NULL,
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_exchange_rate` (`source_currency_id`,`base_currency_id`,`effective_date`,`rate_version`),
                KEY `idx_rate_lookup` (`source_currency_id`,`base_currency_id`,`effective_date`,`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset};");
        }

        // 3. Table: sales_pipeline_sanitization_batches
        $batchesTable = $prefix . 'sales_pipeline_sanitization_batches';
        if (!$CI->db->table_exists($batchesTable)) {
            $CI->db->query("CREATE TABLE `{$batchesTable}` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `batch_uuid` varchar(64) NOT NULL,
                `manifest_checksum` varchar(64) NOT NULL,
                `manifest_json` longtext NULL,
                `approved_by` int(11) NOT NULL,
                `approval_reference` varchar(191) NOT NULL,
                `total_items` int(11) NOT NULL DEFAULT 0,
                `status` enum('pending','validated','applied','already_applied','failed') NOT NULL DEFAULT 'pending',
                `applied_at` datetime NULL,
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_batch_uuid` (`batch_uuid`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset};");
        }

        // 4. Table: sales_pipeline_sanitization_items
        $itemsTable = $prefix . 'sales_pipeline_sanitization_items';
        if (!$CI->db->table_exists($itemsTable)) {
            $CI->db->query("CREATE TABLE `{$itemsTable}` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `batch_id` int(11) NOT NULL,
                `entity_type` varchar(32) NOT NULL,
                `entity_id` int(11) NOT NULL,
                `approved_rate_date` date NULL,
                `approved_rate` decimal(20,8) NULL,
                `expected_before_rate` decimal(20,8) NULL,
                `expected_before_base_total` decimal(15,2) NULL,
                `before_state_json` text NULL,
                `after_state_json` text NULL,
                `status` varchar(32) NOT NULL DEFAULT 'pending',
                `error_message` text NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_sanit_item` (`batch_id`,`entity_type`,`entity_id`),
                KEY `idx_sanit_entity` (`entity_type`,`entity_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset};");
        }

        // 5. Columns on sales_pipeline_estimate_groups
        $groupsTable = $prefix . 'sales_pipeline_estimate_groups';
        if ($CI->db->table_exists($groupsTable)) {
            $groupCols = [
                'first_sent_at'              => "datetime NULL AFTER `current_estimate_id`",
                'first_sent_source'          => "varchar(32) NULL AFTER `first_sent_at`",
                'first_sent_estimate_id'      => "int(11) NULL AFTER `first_sent_source`",
                'is_finance_locked'          => "tinyint(1) NOT NULL DEFAULT 0 AFTER `last_reconciled_at`",
                'finance_locked_at'          => "datetime NULL AFTER `is_finance_locked`",
                'finance_locked_by'          => "int(11) NULL AFTER `finance_locked_at`",
                'finance_approval_reference' => "varchar(191) NULL AFTER `finance_locked_by`",
                'finance_lock_reason'        => "text NULL AFTER `finance_approval_reference`",
            ];
            foreach ($groupCols as $col => $definition) {
                if (!$CI->db->field_exists($col, $groupsTable)) {
                    $CI->db->query("ALTER TABLE `{$groupsTable}` ADD `{$col}` {$definition}");
                }
            }

            // Add index for first_sent_at lookup
            $indexes = $CI->db->query("SHOW INDEX FROM `{$groupsTable}` WHERE Key_name = 'idx_group_owner_first_sent'")->result_array();
            if (empty($indexes)) {
                $CI->db->query("ALTER TABLE `{$groupsTable}` ADD KEY `idx_group_owner_first_sent` (`owner_staff_id`, `first_sent_at`)");
            }
        }

        // 6. Columns on sales_pipeline (Deals)
        $dealsTable = $prefix . 'sales_pipeline';
        if ($CI->db->table_exists($dealsTable)) {
            $dealCols = [
                'is_finance_locked'          => "tinyint(1) NOT NULL DEFAULT 0 AFTER `is_manual_lock`",
                'finance_locked_at'          => "datetime NULL AFTER `is_finance_locked`",
                'finance_locked_by'          => "int(11) NULL AFTER `finance_locked_at`",
                'finance_approval_reference' => "varchar(191) NULL AFTER `finance_locked_by`",
                'finance_lock_reason'        => "text NULL AFTER `finance_approval_reference`",
            ];
            foreach ($dealCols as $col => $definition) {
                if (!$CI->db->field_exists($col, $dealsTable)) {
                    $CI->db->query("ALTER TABLE `{$dealsTable}` ADD `{$col}` {$definition}");
                }
            }
        }

        // 7. Column on sales_pipeline_reminders_log
        $remindersTable = $prefix . 'sales_pipeline_reminders_log';
        if ($CI->db->table_exists($remindersTable)) {
            if (!$CI->db->field_exists('data_quality_status', $remindersTable)) {
                $CI->db->query("ALTER TABLE `{$remindersTable}` ADD `data_quality_status` varchar(32) NOT NULL DEFAULT 'verified' AFTER `response_due_at`");
            }
        }
    }
}

if (!function_exists('sales_pipeline_run_architecture_113_backfill')) {
    /**
     * Run backfill for first_sent_at and legacy reminder data quality status.
     */
    function sales_pipeline_run_architecture_113_backfill($CI)
    {
        $prefix = db_prefix();
        $groupsTable = $prefix . 'sales_pipeline_estimate_groups';
        $versionsTable = $prefix . 'sales_pipeline_estimate_versions';
        $estimatesTable = $prefix . 'estimates';
        $activityTable = $prefix . 'sales_activity';
        $remindersTable = $prefix . 'sales_pipeline_reminders_log';

        // 1. Backfill first_sent_at for Estimate Groups
        if ($CI->db->table_exists($groupsTable) && $CI->db->table_exists($versionsTable)) {
            // Priority 1: Activity log of sent estimate
            if ($CI->db->table_exists($activityTable)) {
                $activitySql = "
                    UPDATE `{$groupsTable}` g
                    JOIN (
                        SELECT v.estimate_group_id,
                               MIN(a.date) AS min_sent_at,
                               SUBSTRING_INDEX(GROUP_CONCAT(v.estimate_id ORDER BY a.date ASC), ',', 1) AS first_estimate_id
                        FROM `{$versionsTable}` v
                        JOIN `{$activityTable}` a
                             ON a.rel_id = v.estimate_id AND a.rel_type = 'estimate'
                        WHERE a.description = 'invoice_estimate_activity_sent_to_client'
                        GROUP BY v.estimate_group_id
                    ) act ON act.estimate_group_id = g.id
                    SET g.first_sent_at = act.min_sent_at,
                        g.first_sent_source = 'activity',
                        g.first_sent_estimate_id = act.first_estimate_id
                    WHERE g.first_sent_at IS NULL
                ";
                $CI->db->query($activitySql);
            }

            // Priority 2: Fallback for groups with versions having status IN (2,3,4,5)
            if ($CI->db->table_exists($estimatesTable)) {
                $inferredSql = "
                    UPDATE `{$groupsTable}` g
                    JOIN (
                        SELECT v.estimate_group_id,
                               MIN(e.date) AS min_est_date,
                               SUBSTRING_INDEX(GROUP_CONCAT(v.estimate_id ORDER BY e.date ASC), ',', 1) AS first_estimate_id
                        FROM `{$versionsTable}` v
                        JOIN `{$estimatesTable}` e ON e.id = v.estimate_id
                        WHERE e.status IN (2, 3, 4, 5)
                        GROUP BY v.estimate_group_id
                    ) inf ON inf.estimate_group_id = g.id
                    SET g.first_sent_at = CONCAT(inf.min_est_date, ' 09:00:00'),
                        g.first_sent_source = 'inferred_estimate_date',
                        g.first_sent_estimate_id = inf.first_estimate_id
                    WHERE g.first_sent_at IS NULL
                ";
                $CI->db->query($inferredSql);
            }
        }

        // 2. Mark legacy corrupt reminders
        if ($CI->db->table_exists($remindersTable)) {
            $remSql = "
                UPDATE `{$remindersTable}`
                SET `data_quality_status` = 'legacy_unverified'
                WHERE `staff_response` IS NOT NULL
                  AND (`sent_at` IS NULL OR `response_due_at` IS NULL)
                  AND `data_quality_status` = 'verified'
            ";
            $CI->db->query($remSql);
        }
    }
}

if (!function_exists('sales_pipeline_upgrade_target_options_113')) {
    /**
     * Upgrade target options safely, preserving Admin custom overrides.
     */
    function sales_pipeline_upgrade_target_options_113()
    {
        // Month: 20 -> 30 only if current is exactly 20 or not set
        $currentMonth = get_option('performance_quote_target_this_month');
        if ($currentMonth === false) {
            add_option('performance_quote_target_this_month', '30');
        } elseif ((string) $currentMonth === '20') {
            update_option('performance_quote_target_this_month', '30');
        }

        // Quarter: 60 -> 90 only if current is 60 or not set
        $currentQuarter = get_option('performance_quote_target_this_quarter');
        if ($currentQuarter === false) {
            add_option('performance_quote_target_this_quarter', '90');
        } elseif ((string) $currentQuarter === '60') {
            update_option('performance_quote_target_this_quarter', '90');
        }

        // Year: 240 -> 360 only if current is 240 or not set
        $currentYear = get_option('performance_quote_target_this_year');
        if ($currentYear === false) {
            add_option('performance_quote_target_this_year', '360');
        } elseif ((string) $currentYear === '240') {
            update_option('performance_quote_target_this_year', '360');
        }
    }
}
