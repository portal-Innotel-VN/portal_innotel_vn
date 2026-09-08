<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('sales_pipeline_ensure_estimate_group_schema')) {
    /**
     * Create the Estimate-only grouping schema and upgrade the previous quote schema.
     *
     * Estimate groups are deliberately independent from Deals. Existing rows are
     * renamed in place so installations upgrading from 1.0.2 keep their data.
     *
     * @param CI_Controller $CI
     * @return void
     */
    function sales_pipeline_ensure_estimate_group_schema($CI)
    {
        $charset = $CI->db->char_set ?: 'utf8';
        $group_table = db_prefix() . 'sales_pipeline_estimate_groups';
        $version_table = db_prefix() . 'sales_pipeline_estimate_versions';
        $history_table = db_prefix() . 'sales_pipeline_estimate_outcome_history';
        $legacy_group_table = db_prefix() . 'sales_pipeline_quote_opportunities';
        $legacy_history_table = db_prefix() . 'sales_pipeline_quote_outcome_history';
        $table_exists = function ($table) use ($CI) {
            return (bool) $CI->db
                ->query('SHOW TABLES LIKE ' . $CI->db->escape($table))
                ->row_array();
        };
        $field_exists = function ($field, $table) use ($CI) {
            return (bool) $CI->db
                ->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $CI->db->escape($field))
                ->row_array();
        };

        if ($table_exists($legacy_group_table) && !$table_exists($group_table)) {
            $CI->db->query('RENAME TABLE `' . $legacy_group_table . '` TO `' . $group_table . '`');
        }
        if ($table_exists($legacy_history_table) && !$table_exists($history_table)) {
            $CI->db->query('RENAME TABLE `' . $legacy_history_table . '` TO `' . $history_table . '`');
        }

        if (!$table_exists($group_table)) {
            $CI->db->query('CREATE TABLE `' . $group_table . "` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `client_id` int(11) NOT NULL,
                `owner_staff_id` int(11) NOT NULL,
                `decision_owner_staff_id` int(11) DEFAULT NULL,
                `decision_estimate_id` int(11) DEFAULT NULL,
                `decision_value_base` decimal(18,2) DEFAULT NULL,
                `current_estimate_id` int(11) DEFAULT NULL,
                `origin_estimate_id` int(11) DEFAULT NULL,
                `outcome` enum('pending','accepted','declined') NOT NULL DEFAULT 'pending',
                `decision_at` datetime DEFAULT NULL,
                `decision_source` varchar(30) DEFAULT NULL,
                `source_currency_id` int(11) DEFAULT NULL,
                `base_currency_id` int(11) DEFAULT NULL,
                `created_value_base` decimal(18,2) DEFAULT NULL,
                `grouping_source` varchar(30) NOT NULL DEFAULT 'standalone',
                `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `datemodified` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_origin_estimate_id` (`origin_estimate_id`),
                KEY `idx_client_id` (`client_id`),
                KEY `idx_owner_staff` (`owner_staff_id`),
                KEY `idx_decision_owner` (`decision_owner_staff_id`),
                KEY `idx_decision_estimate` (`decision_estimate_id`),
                KEY `idx_current_estimate` (`current_estimate_id`),
                KEY `idx_outcome_decision` (`outcome`, `decision_at`),
                KEY `idx_datecreated` (`datecreated`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
        }

        if ($field_exists('pipeline_id', $group_table)) {
            $pipeline_index = $CI->db->query(
                'SHOW INDEX FROM `' . $group_table . '` WHERE Key_name = "idx_pipeline_id"'
            )->row_array();
            if ($pipeline_index) {
                $CI->db->query('ALTER TABLE `' . $group_table . '` DROP INDEX `idx_pipeline_id`');
            }
            $CI->db->query('ALTER TABLE `' . $group_table . '` DROP COLUMN `pipeline_id`');
        }
        if (!$field_exists('decision_estimate_id', $group_table)) {
            $CI->db->query('ALTER TABLE `' . $group_table . '`'
                . ' ADD `decision_estimate_id` int(11) DEFAULT NULL AFTER `decision_owner_staff_id`,'
                . ' ADD KEY `idx_decision_estimate` (`decision_estimate_id`)');
        }
        if (!$field_exists('decision_value_base', $group_table)) {
            $CI->db->query('ALTER TABLE `' . $group_table . '`'
                . ' ADD `decision_value_base` decimal(18,2) DEFAULT NULL AFTER `decision_estimate_id`');
        }

        if (!$table_exists($version_table)) {
            $CI->db->query('CREATE TABLE `' . $version_table . "` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `estimate_group_id` int(11) NOT NULL,
                `estimate_id` int(11) NOT NULL,
                `revision_no` int(11) NOT NULL DEFAULT 1,
                `source_currency_id` int(11) DEFAULT NULL,
                `source_total` decimal(18,2) NOT NULL DEFAULT 0.00,
                `exchange_rate_to_base` decimal(20,8) DEFAULT NULL,
                `base_currency_id` int(11) DEFAULT NULL,
                `base_total` decimal(18,2) DEFAULT NULL,
                `rate_captured_at` datetime DEFAULT NULL,
                `date_linked` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_estimate_id` (`estimate_id`),
                UNIQUE KEY `uq_group_revision` (`estimate_group_id`, `revision_no`),
                KEY `idx_estimate_group_id` (`estimate_group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
        } elseif ($field_exists('quote_opportunity_id', $version_table)
            && !$field_exists('estimate_group_id', $version_table)) {
            $CI->db->query('ALTER TABLE `' . $version_table . '` CHANGE `quote_opportunity_id` `estimate_group_id` int(11) NOT NULL');
        }
        $legacy_version_index = $CI->db->query(
            'SHOW INDEX FROM `' . $version_table . '` WHERE Key_name = "idx_quote_opp_id"'
        )->row_array();
        if ($legacy_version_index) {
            $CI->db->query('ALTER TABLE `' . $version_table . '` DROP INDEX `idx_quote_opp_id`, ADD KEY `idx_estimate_group_id` (`estimate_group_id`)');
        }

        if (!$table_exists($history_table)) {
            $CI->db->query('CREATE TABLE `' . $history_table . "` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `estimate_group_id` int(11) NOT NULL,
                `estimate_id` int(11) DEFAULT NULL,
                `previous_outcome` varchar(20) DEFAULT NULL,
                `new_outcome` varchar(20) NOT NULL,
                `effective_at` datetime NOT NULL,
                `observed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `source` varchar(30) NOT NULL,
                `changed_by` int(11) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_group_effective` (`estimate_group_id`, `effective_at`),
                KEY `idx_estimate_id` (`estimate_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
        } elseif ($field_exists('quote_opportunity_id', $history_table)
            && !$field_exists('estimate_group_id', $history_table)) {
            $CI->db->query('ALTER TABLE `' . $history_table . '` CHANGE `quote_opportunity_id` `estimate_group_id` int(11) NOT NULL');
        }
        $legacy_history_index = $CI->db->query(
            'SHOW INDEX FROM `' . $history_table . '` WHERE Key_name = "idx_quote_effective"'
        )->row_array();
        if ($legacy_history_index) {
            $CI->db->query('ALTER TABLE `' . $history_table . '` DROP INDEX `idx_quote_effective`, ADD KEY `idx_group_effective` (`estimate_group_id`, `effective_at`)');
        }

        $CI->db
            ->like('grouping_source', 'legacy', 'after')
            ->update($group_table, ['grouping_source' => 'legacy_import']);

        // R-02 fix: add reconciliation cursor column if missing (idempotent).
        if (!$field_exists('last_reconciled_at', $group_table)) {
            $CI->db->query(
                'ALTER TABLE `' . $group_table . '`'
                . ' ADD `last_reconciled_at` DATETIME NULL DEFAULT NULL'
                . ' AFTER `datemodified`'
            );
        }
        $recon_index = $CI->db->query(
            'SHOW INDEX FROM `' . $group_table . '` WHERE Key_name = "idx_last_reconciled"'
        )->row_array();
        if (!$recon_index) {
            $CI->db->query(
                'ALTER TABLE `' . $group_table . '`'
                . ' ADD INDEX `idx_last_reconciled` (`last_reconciled_at`, `id`)'
            );
        }

        // Step 3 schema: add parent_estimate_id, link_method, linked_by to version table
        if (!$field_exists('parent_estimate_id', $version_table)) {
            $CI->db->query(
                'ALTER TABLE `' . $version_table . '`'
                . ' ADD `parent_estimate_id` INT(11) DEFAULT NULL AFTER `estimate_id`,'
                . ' ADD `link_method` VARCHAR(30) NOT NULL DEFAULT "origin" AFTER `parent_estimate_id`,'
                . ' ADD `linked_by` INT(11) DEFAULT NULL AFTER `link_method`'
            );
        } elseif (!$field_exists('link_method', $version_table)) {
            $CI->db->query(
                'ALTER TABLE `' . $version_table . '`'
                . ' ADD `link_method` VARCHAR(30) NOT NULL DEFAULT "origin" AFTER `parent_estimate_id`,'
                . ' ADD `linked_by` INT(11) DEFAULT NULL AFTER `link_method`'
            );
        } elseif (!$field_exists('linked_by', $version_table)) {
            $CI->db->query(
                'ALTER TABLE `' . $version_table . '`'
                . ' ADD `linked_by` INT(11) DEFAULT NULL AFTER `link_method`'
            );
        }

        $parent_index = $CI->db->query(
            'SHOW INDEX FROM `' . $version_table . '` WHERE Key_name = "idx_parent_estimate"'
        )->row_array();
        if (!$parent_index && $field_exists('parent_estimate_id', $version_table)) {
            $CI->db->query(
                'ALTER TABLE `' . $version_table . '`'
                . ' ADD INDEX `idx_parent_estimate` (`parent_estimate_id`)'
            );
        }

        // Step 3 schema: create audit events table tblsales_pipeline_estimate_group_events
        $events_table = db_prefix() . 'sales_pipeline_estimate_group_events';
        if (!$table_exists($events_table)) {
            $CI->db->query('CREATE TABLE `' . $events_table . "` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `event_type` varchar(30) NOT NULL,
                `estimate_id` int(11) DEFAULT NULL,
                `source_estimate_id` int(11) DEFAULT NULL,
                `from_group_id` int(11) DEFAULT NULL,
                `to_group_id` int(11) DEFAULT NULL,
                `actor_staff_id` int(11) DEFAULT NULL,
                `reason` varchar(500) DEFAULT NULL,
                `metadata_json` longtext DEFAULT NULL,
                `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_event_estimate` (`estimate_id`, `datecreated`),
                KEY `idx_event_from_group` (`from_group_id`, `datecreated`),
                KEY `idx_event_to_group` (`to_group_id`, `datecreated`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
        } else {
            // Ensure all 3 audit indexes exist
            $idx_estimate = $CI->db->query('SHOW INDEX FROM `' . $events_table . '` WHERE Key_name = "idx_event_estimate"')->row_array();
            if (!$idx_estimate) {
                $CI->db->query('ALTER TABLE `' . $events_table . '` ADD KEY `idx_event_estimate` (`estimate_id`, `datecreated`)');
            }
            $idx_from = $CI->db->query('SHOW INDEX FROM `' . $events_table . '` WHERE Key_name = "idx_event_from_group"')->row_array();
            if (!$idx_from) {
                $CI->db->query('ALTER TABLE `' . $events_table . '` ADD KEY `idx_event_from_group` (`from_group_id`, `datecreated`)');
            }
            $idx_to = $CI->db->query('SHOW INDEX FROM `' . $events_table . '` WHERE Key_name = "idx_event_to_group"')->row_array();
            if (!$idx_to) {
                $CI->db->query('ALTER TABLE `' . $events_table . '` ADD KEY `idx_event_to_group` (`to_group_id`, `datecreated`)');
            }
        }

        // Deal-Estimate Group Bridge Table
        $bridge_table = db_prefix() . 'sales_pipeline_deal_estimate_groups';
        if (!$table_exists($bridge_table)) {
            $CI->db->query('CREATE TABLE `' . $bridge_table . "` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `pipeline_id` int(11) NOT NULL,
                `estimate_group_id` int(11) NOT NULL,
                `is_primary` tinyint(1) NOT NULL DEFAULT 0,
                `linked_by` int(11) DEFAULT NULL,
                `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_estimate_group` (`estimate_group_id`),
                KEY `idx_pipeline` (`pipeline_id`),
                KEY `idx_estimate_group` (`estimate_group_id`),
                KEY `idx_deal_primary` (`pipeline_id`, `is_primary`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
        }

        // Manual Lock Columns on tblsales_pipeline
        $pipeline_table = db_prefix() . 'sales_pipeline';
        if ($table_exists($pipeline_table)) {
            if (!$field_exists('is_manual_lock', $pipeline_table)) {
                $CI->db->query('ALTER TABLE `' . $pipeline_table . '` ADD COLUMN `is_manual_lock` tinyint(1) NOT NULL DEFAULT 0 AFTER `notes`');
            }
            if (!$field_exists('manual_lock_by', $pipeline_table)) {
                $CI->db->query('ALTER TABLE `' . $pipeline_table . '` ADD COLUMN `manual_lock_by` int(11) DEFAULT NULL AFTER `is_manual_lock`');
            }
            if (!$field_exists('manual_lock_at', $pipeline_table)) {
                $CI->db->query('ALTER TABLE `' . $pipeline_table . '` ADD COLUMN `manual_lock_at` datetime DEFAULT NULL AFTER `manual_lock_by`');
            }
            if (!$field_exists('manual_lock_reason', $pipeline_table)) {
                $CI->db->query('ALTER TABLE `' . $pipeline_table . '` ADD COLUMN `manual_lock_reason` text DEFAULT NULL AFTER `manual_lock_at`');
            }
            $idx_lock = $CI->db->query('SHOW INDEX FROM `' . $pipeline_table . '` WHERE Key_name = "idx_manual_lock"')->row_array();
            if (!$idx_lock) {
                $CI->db->query('ALTER TABLE `' . $pipeline_table . '` ADD INDEX `idx_manual_lock` (`is_manual_lock`)');
            }
        }

        sales_pipeline_backfill_estimate_groups($CI);

        // Safe backfill for link_method on legacy data
        $CI->db->query('UPDATE `' . $version_table . '` ev'
            . ' JOIN `' . $group_table . '` grp ON grp.id = ev.estimate_group_id'
            . ' SET ev.link_method = "legacy_import"'
            . ' WHERE grp.grouping_source = "legacy_import" AND ev.revision_no = 1 AND ev.link_method = "origin"');
        $CI->db->query('UPDATE `' . $version_table . '`'
            . ' SET link_method = "legacy_revision"'
            . ' WHERE revision_no > 1 AND link_method = "origin"');

        $CI->db->query('UPDATE `' . $group_table . '` grp'
            . ' SET grp.decision_estimate_id = ('
            . ' SELECT ev.estimate_id FROM `' . $version_table . '` ev'
            . ' JOIN `' . db_prefix() . 'estimates` e ON e.id = ev.estimate_id'
            . ' WHERE ev.estimate_group_id = grp.id AND e.status = 4'
            . ' ORDER BY ev.revision_no DESC LIMIT 1)'
            . ' WHERE grp.outcome = "accepted" AND grp.decision_estimate_id IS NULL');
        $CI->db->query('UPDATE `' . $group_table . '`'
            . ' SET decision_estimate_id = current_estimate_id'
            . ' WHERE outcome = "declined" AND decision_estimate_id IS NULL');
        $CI->db->query('UPDATE `' . $group_table . '` grp'
            . ' JOIN `' . $version_table . '` ev ON ev.estimate_id = grp.decision_estimate_id'
            . ' SET grp.decision_value_base = ev.base_total'
            . ' WHERE grp.outcome = "accepted" AND grp.decision_value_base IS NULL');
    }
}

if (!function_exists('sales_pipeline_backfill_estimate_groups')) {
    /**
     * Import unlinked estimates without inferring relationships from customer or Deal.
     *
     * @param CI_Controller $CI
     * @return void
     */
    function sales_pipeline_backfill_estimate_groups($CI)
    {
        $estimate_table = db_prefix() . 'estimates';
        $currency_table = db_prefix() . 'currencies';
        $activity_table = db_prefix() . 'sales_activity';
        $group_table = db_prefix() . 'sales_pipeline_estimate_groups';
        $version_table = db_prefix() . 'sales_pipeline_estimate_versions';
        $history_table = db_prefix() . 'sales_pipeline_estimate_outcome_history';

        if (!$CI->db->table_exists($estimate_table)) {
            return;
        }

        $base_currency = $CI->db->select('id')->where('isdefault', 1)->get($currency_table)->row_array();
        $base_currency_id = $base_currency ? (int) $base_currency['id'] : 0;
        $accepted_activity = '(SELECT MAX(sa.date) FROM `' . $activity_table . '` sa'
            . ' WHERE sa.rel_type = "estimate" AND sa.rel_id = e.id'
            . ' AND (sa.description IN ("estimate_activity_client_accepted", "estimate_activity_client_accepted_and_converted")'
            . ' OR sa.additional_data LIKE "%<new_status>4</new_status>%"'
            . ' OR sa.additional_data LIKE "%<status>4</status>%"))';
        $declined_activity = '(SELECT MAX(sa.date) FROM `' . $activity_table . '` sa'
            . ' WHERE sa.rel_type = "estimate" AND sa.rel_id = e.id'
            . ' AND (sa.description = "estimate_activity_client_declined"'
            . ' OR sa.additional_data LIKE "%<new_status>3</new_status>%"'
            . ' OR sa.additional_data LIKE "%<status>3</status>%"))';

        $CI->db->query('INSERT IGNORE INTO `' . $group_table . '`'
            . ' (`client_id`, `owner_staff_id`, `decision_owner_staff_id`, `decision_estimate_id`, `decision_value_base`, `current_estimate_id`, `origin_estimate_id`,'
            . ' `outcome`, `decision_at`, `decision_source`, `source_currency_id`, `base_currency_id`,'
            . ' `created_value_base`, `grouping_source`, `datecreated`)'
            . ' SELECT e.clientid, CASE WHEN e.sale_agent > 0 THEN e.sale_agent ELSE e.addedfrom END,'
            . ' CASE WHEN e.status IN (3,4) THEN CASE WHEN e.sale_agent > 0 THEN e.sale_agent ELSE e.addedfrom END ELSE NULL END,'
            . ' CASE WHEN e.status IN (3,4) THEN e.id ELSE NULL END,'
            . ' CASE WHEN e.status = 4 AND e.currency = ' . $base_currency_id . ' THEN e.total ELSE NULL END,'
            . ' e.id, e.id, CASE WHEN e.status = 4 THEN "accepted" WHEN e.status = 3 THEN "declined" ELSE "pending" END,'
            . ' CASE WHEN e.status = 4 THEN COALESCE(e.invoiced_date, ' . $accepted_activity . ')'
            . ' WHEN e.status = 3 THEN ' . $declined_activity . ' ELSE NULL END,'
            . ' CASE WHEN e.status IN (3,4) THEN "migration" ELSE NULL END, e.currency, ' . $base_currency_id . ','
            . ' CASE WHEN e.currency = ' . $base_currency_id . ' THEN e.total ELSE NULL END,'
            . ' "legacy_import", e.datecreated'
            . ' FROM `' . $estimate_table . '` e'
            . ' LEFT JOIN `' . $version_table . '` linked ON linked.estimate_id = e.id'
            . ' WHERE linked.id IS NULL');

        $CI->db->query('INSERT IGNORE INTO `' . $version_table . '`'
            . ' (`estimate_group_id`, `estimate_id`, `revision_no`, `source_currency_id`, `source_total`,'
            . ' `exchange_rate_to_base`, `base_currency_id`, `base_total`, `rate_captured_at`, `date_linked`)'
            . ' SELECT grp.id, e.id, 1, e.currency, e.total,'
            . ' CASE WHEN e.currency = ' . $base_currency_id . ' THEN 1 ELSE NULL END, ' . $base_currency_id . ','
            . ' CASE WHEN e.currency = ' . $base_currency_id . ' THEN e.total ELSE NULL END,'
            . ' CASE WHEN e.currency = ' . $base_currency_id . ' THEN e.datecreated ELSE NULL END, e.datecreated'
            . ' FROM `' . $group_table . '` grp'
            . ' JOIN `' . $estimate_table . '` e ON e.id = grp.origin_estimate_id'
            . ' LEFT JOIN `' . $version_table . '` linked ON linked.estimate_id = e.id'
            . ' WHERE linked.id IS NULL');

        $CI->db->query('INSERT INTO `' . $history_table . '`'
            . ' (`estimate_group_id`, `estimate_id`, `previous_outcome`, `new_outcome`, `effective_at`, `observed_at`, `source`, `changed_by`)'
            . ' SELECT grp.id, grp.current_estimate_id, "pending", grp.outcome, grp.decision_at, NOW(), "migration", NULL'
            . ' FROM `' . $group_table . '` grp'
            . ' LEFT JOIN `' . $history_table . '` history ON history.estimate_group_id = grp.id'
            . ' WHERE history.id IS NULL AND grp.outcome IN ("accepted", "declined") AND grp.decision_at IS NOT NULL');
    }
}
