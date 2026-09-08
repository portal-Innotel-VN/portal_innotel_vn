<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('sales_pipeline_ensure_reminder_repository_schema')) {
    /**
     * Upgrade the legacy Deal log into the shared Reminder Repository and create
     * the channel-level delivery audit/outbox. Safe to run repeatedly.
     */
    function sales_pipeline_ensure_reminder_repository_schema($CI)
    {
        $reminders = db_prefix() . 'sales_pipeline_reminders_log';
        $deliveries = db_prefix() . 'sales_pipeline_reminder_deliveries';
        $rateBuckets = db_prefix() . 'sales_pipeline_reminder_delivery_rate_buckets';
        $charset = $CI->db->char_set ?: 'utf8';

        if (!$CI->db->table_exists($reminders)) {
            return;
        }

        $columns = [
            'rule_code'        => "varchar(80) NULL AFTER `reminder_type`",
            'entity_type'      => "varchar(40) NULL AFTER `rule_code`",
            'entity_id'        => "int(11) NULL AFTER `entity_type`",
            'period_key'       => "varchar(20) NULL AFTER `entity_id`",
            'checkpoint'       => "varchar(20) NULL AFTER `period_key`",
            'severity'         => "varchar(20) NULL AFTER `checkpoint`",
            'response_required'=> "tinyint(1) NULL DEFAULT 1 AFTER `severity`",
            'response_sla_hours'=> "smallint(5) unsigned NULL AFTER `response_required`",
            'title'            => "varchar(255) NULL AFTER `response_sla_hours`",
            'snapshot_json'    => "longtext NULL AFTER `message`",
            'response_due_at'  => "datetime NULL AFTER `sent_at`",
            'acknowledged_at'  => "datetime NULL AFTER `snapshot_json`",
            'acknowledged_by'  => "int(11) NULL AFTER `acknowledged_at`",
            'dedupe_key'       => "varchar(191) NULL AFTER `acknowledged_by`",
            'created_at'       => "datetime NULL AFTER `dedupe_key`",
        ];
        foreach ($columns as $column => $definition) {
            if (!$CI->db->field_exists($column, $reminders)) {
                $CI->db->query('ALTER TABLE `' . $reminders . '` ADD `' . $column . '` ' . $definition);
            }
        }

        $pipelineColumn = $CI->db->query(
            'SHOW COLUMNS FROM `' . $reminders . '` LIKE ' . $CI->db->escape('pipeline_id')
        )->row_array();
        if ($pipelineColumn && strtoupper((string) $pipelineColumn['Null']) !== 'YES') {
            $CI->db->query('ALTER TABLE `' . $reminders . '` MODIFY `pipeline_id` int(11) NULL');
        }
        $messageColumn = $CI->db->query(
            'SHOW COLUMNS FROM `' . $reminders . '` LIKE ' . $CI->db->escape('message')
        )->row_array();
        if ($messageColumn && strtoupper((string) $messageColumn['Null']) !== 'YES') {
            $CI->db->query('ALTER TABLE `' . $reminders . '` MODIFY `message` text NULL');
        }
        $sentColumn = $CI->db->query(
            'SHOW COLUMNS FROM `' . $reminders . '` LIKE ' . $CI->db->escape('sent_at')
        )->row_array();
        if ($sentColumn && strtoupper((string) $sentColumn['Null']) !== 'YES') {
            $CI->db->query('ALTER TABLE `' . $reminders . '` MODIFY `sent_at` datetime NULL DEFAULT NULL');
        }

        // Legacy rows predate the shared repository. Preserve their retired
        // rule code strictly as audit classification; no evaluator emits it.
        // Their dedupe key cannot be reconstructed reliably, so keep it NULL.
        $CI->db->query('UPDATE `' . $reminders . '` SET'
            . " `rule_code` = COALESCE(NULLIF(`rule_code`, ''), 'DEAL_FREQUENCY_REMINDER'),"
            . " `entity_type` = COALESCE(NULLIF(`entity_type`, ''), 'deal'),"
            . ' `entity_id` = COALESCE(`entity_id`, `pipeline_id`),'
            . " `period_key` = COALESCE(NULLIF(`period_key`, ''), DATE_FORMAT(COALESCE(`sent_at`, NOW()), '%Y-%m-%d')),"
            . " `checkpoint` = COALESCE(NULLIF(`checkpoint`, ''), 'FREQUENCY'),"
            . " `severity` = COALESCE(NULLIF(`severity`, ''), 'warning'),"
            . ' `response_required` = COALESCE(`response_required`, 1),'
            . ' `snapshot_json` = COALESCE(`snapshot_json`, CONCAT('
            . "'{\"legacy\":true,\"pipeline_id\":', COALESCE(`pipeline_id`, 'null'), ',\"evaluated_at\":\"',"
            . " DATE_FORMAT(COALESCE(`sent_at`, NOW()), '%Y-%m-%d %H:%i:%s'), '\"}')) ,"
            . ' `created_at` = COALESCE(`created_at`, `sent_at`, NOW())');

        $required = [
            'rule_code'         => 'varchar(80) NOT NULL',
            'entity_type'       => 'varchar(40) NOT NULL',
            'period_key'        => 'varchar(20) NOT NULL',
            'checkpoint'        => 'varchar(20) NOT NULL',
            'severity'          => 'varchar(20) NOT NULL',
            'response_required' => 'tinyint(1) NOT NULL DEFAULT 1',
            'snapshot_json'     => 'longtext NOT NULL',
            'created_at'        => 'datetime NOT NULL',
        ];
        foreach ($required as $column => $definition) {
            $CI->db->query('ALTER TABLE `' . $reminders . '` MODIFY `' . $column . '` ' . $definition);
        }

        $indexes = $CI->db->query('SHOW INDEX FROM `' . $reminders . '`')->result_array();
        $indexNames = array_unique(array_column($indexes, 'Key_name'));
        if (!in_array('uq_sales_pipeline_reminder_dedupe', $indexNames, true)) {
            $CI->db->query('ALTER TABLE `' . $reminders . '` ADD UNIQUE KEY `uq_sales_pipeline_reminder_dedupe` (`dedupe_key`)');
        }
        if (!in_array('idx_reminder_staff_period', $indexNames, true)) {
            $CI->db->query('ALTER TABLE `' . $reminders . '` ADD KEY `idx_reminder_staff_period` (`staff_id`,`period_key`)');
        }
        if (!in_array('idx_reminder_rule_period', $indexNames, true)) {
            $CI->db->query('ALTER TABLE `' . $reminders . '` ADD KEY `idx_reminder_rule_period` (`rule_code`,`period_key`)');
        }
        if (!in_array('idx_reminder_inbox_queue', $indexNames, true)) {
            $CI->db->query('ALTER TABLE `' . $reminders . '` ADD KEY `idx_reminder_inbox_queue` (`staff_id`,`response_required`,`acknowledged_at`,`staff_response`(100))');
        }
        if (!in_array('idx_reminder_sla_eval', $indexNames, true)) {
            $CI->db->query('ALTER TABLE `' . $reminders . '` ADD KEY `idx_reminder_sla_eval` (`staff_id`,`entity_type`,`response_required`,`response_due_at`,`responded_at`)');
        }

        if (!$CI->db->table_exists($deliveries)) {
            $CI->db->query('CREATE TABLE `' . $deliveries . "` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `reminder_id` int(11) NOT NULL,
                `channel` varchar(20) NOT NULL,
                `recipient_type` varchar(20) NOT NULL,
                `recipient_staff_id` int(11) DEFAULT NULL,
                `recipient_key` varchar(191) NOT NULL,
                `cc_recipients` text DEFAULT NULL,
                `status` varchar(20) NOT NULL DEFAULT 'pending',
                `attempt_count` int(11) NOT NULL DEFAULT 0,
                `last_attempt_at` datetime DEFAULT NULL,
                `provider_message_id` varchar(191) DEFAULT NULL,
                `last_error` varchar(500) DEFAULT NULL,
                `last_error_code` varchar(64) DEFAULT NULL,
                `last_error_class` varchar(32) DEFAULT NULL,
                `next_retry_at` datetime DEFAULT NULL,
                `expires_at` datetime DEFAULT NULL,
                `expired_at` datetime DEFAULT NULL,
                `sent_at` datetime DEFAULT NULL,
                `delivered_at` datetime DEFAULT NULL,
                `read_at` datetime DEFAULT NULL,
                `created_at` datetime NOT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_reminder_channel_recipient` (`reminder_id`,`channel`,`recipient_type`,`recipient_key`),
                KEY `idx_delivery_worker` (`status`,`next_retry_at`),
                KEY `idx_delivery_channel_worker` (`channel`,`status`,`next_retry_at`,`expires_at`,`id`),
                KEY `idx_delivery_retention` (`status`,`sent_at`,`expired_at`,`id`),
                KEY `idx_delivery_reminder` (`reminder_id`),
                KEY `idx_delivery_provider` (`provider_message_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
        }

        $deliveryColumns = [
            'recipient_staff_id' => 'int(11) DEFAULT NULL AFTER `recipient_type`',
            'cc_recipients'      => 'text DEFAULT NULL AFTER `recipient_key`',
            'last_attempt_at'    => 'datetime DEFAULT NULL AFTER `attempt_count`',
            'last_error_code'    => 'varchar(64) DEFAULT NULL AFTER `last_error`',
            'last_error_class'   => 'varchar(32) DEFAULT NULL AFTER `last_error_code`',
            'expires_at'         => 'datetime DEFAULT NULL AFTER `next_retry_at`',
            'expired_at'         => 'datetime DEFAULT NULL AFTER `expires_at`',
        ];
        foreach ($deliveryColumns as $column => $definition) {
            if (!$CI->db->field_exists($column, $deliveries)) {
                $CI->db->query('ALTER TABLE `' . $deliveries . '` ADD `' . $column . '` ' . $definition);
            }
        }
        $CI->db->query('UPDATE `' . $deliveries . '` SET `expires_at`=DATE_ADD('
            . 'COALESCE(`created_at`,`updated_at`,NOW()), INTERVAL 24 HOUR)'
            . " WHERE `channel`='email' AND `status` IN ('pending','failed','processing')"
            . ' AND `expires_at` IS NULL');

        $deliveryIndexes = $CI->db->query('SHOW INDEX FROM `' . $deliveries . '`')->result_array();
        $deliveryIndexNames = array_unique(array_column($deliveryIndexes, 'Key_name'));
        if (!in_array('idx_delivery_channel_worker', $deliveryIndexNames, true)) {
            $CI->db->query('ALTER TABLE `' . $deliveries . '` ADD KEY `idx_delivery_channel_worker`'
                . ' (`channel`,`status`,`next_retry_at`,`expires_at`,`id`)');
        }
        if (!in_array('idx_delivery_retention', $deliveryIndexNames, true)) {
            $CI->db->query('ALTER TABLE `' . $deliveries . '` ADD KEY `idx_delivery_retention`'
                . ' (`status`,`sent_at`,`expired_at`,`id`)');
        }

        if (!$CI->db->table_exists($rateBuckets)) {
            $CI->db->query('CREATE TABLE `' . $rateBuckets . "` (
                `scope_key` varchar(40) NOT NULL,
                `bucket_minute` datetime NOT NULL,
                `message_attempts` int(10) unsigned NOT NULL DEFAULT 0,
                `recipient_attempts` int(10) unsigned NOT NULL DEFAULT 0,
                `updated_at` datetime NOT NULL,
                UNIQUE KEY `uq_scope_minute` (`scope_key`,`bucket_minute`),
                KEY `idx_rate_bucket_minute` (`bucket_minute`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $charset . ';');
        }
    }
}
