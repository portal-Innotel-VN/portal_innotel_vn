<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Reminder_delivery_maintenance
{
    private $CI;
    private $lockAcquired = false;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function runIfDue()
    {
        $now = new DateTimeImmutable('now');
        $lastRunAt = (string) get_option('sp_reminder_delivery_maintenance_last_run_at');
        if (!$this->isDue($now, $lastRunAt)) {
            return ['skipped' => true, 'deliveries_purged' => 0, 'rate_buckets_purged' => 0];
        }

        return $this->run($now);
    }

    public function isDue(DateTimeImmutable $now, $lastRunAt)
    {
        if (trim((string) $lastRunAt) === '') {
            return true;
        }
        try {
            $lastRun = new DateTimeImmutable($lastRunAt, $now->getTimezone());
        } catch (Exception $exception) {
            return true;
        }

        return $lastRun <= $now->modify('-7 days');
    }

    public function run(DateTimeImmutable $now)
    {
        $startedAt = microtime(true);
        if (!$this->acquireLock()) {
            return ['skipped' => true, 'deliveries_purged' => 0, 'rate_buckets_purged' => 0];
        }

        try {
            $retentionDays = max(1, (int) get_option('sp_reminder_delivery_retention_days'));
            $cutoff = $now->modify('-' . $retentionDays . ' days')->format('Y-m-d H:i:s');
            $candidates = $this->CI->db->query('SELECT d.`id` FROM `'
                . db_prefix() . 'sales_pipeline_reminder_deliveries` d'
                . ' JOIN `' . db_prefix() . 'sales_pipeline_reminders_log` r ON r.`id`=d.`reminder_id`'
                . " WHERE d.`status` IN ('sent','expired','cancelled')"
                . " AND ((d.`status`='sent' AND d.`sent_at`<?)"
                . " OR (d.`status`='expired' AND d.`expired_at`<?)"
                . " OR (d.`status`='cancelled' AND d.`updated_at`<?))"
                . ' AND r.`staff_response` IS NULL'
                . " AND (d.`last_error_class` IS NULL OR d.`last_error_class` NOT IN ('authentication_configuration','system_bcc_over_quota','unknown_outcome'))"
                . ' ORDER BY d.`id` ASC LIMIT 500', [$cutoff, $cutoff, $cutoff])->result_array();

            $deliveryIds = array_values(array_filter(array_map('intval', array_column($candidates, 'id'))));
            $deliveriesPurged = 0;
            if ($deliveryIds) {
                $this->CI->db->query('DELETE d FROM `' . db_prefix() . 'sales_pipeline_reminder_deliveries` d'
                    . ' JOIN `' . db_prefix() . 'sales_pipeline_reminders_log` r ON r.`id`=d.`reminder_id`'
                    . ' WHERE d.`id` IN (' . implode(',', $deliveryIds) . ')'
                    . " AND d.`status` IN ('sent','expired','cancelled')"
                    . ' AND r.`staff_response` IS NULL'
                    . " AND (d.`last_error_class` IS NULL OR d.`last_error_class` NOT IN ('authentication_configuration','system_bcc_over_quota','unknown_outcome'))");
                $deliveriesPurged = (int) $this->CI->db->affected_rows();
            }

            $bucketCutoff = $now->modify('-48 hours')->format('Y-m-d H:i:s');
            $this->CI->db->query('DELETE FROM `' . db_prefix() . 'sales_pipeline_reminder_delivery_rate_buckets`'
                . ' WHERE `bucket_minute`<? ORDER BY `bucket_minute` ASC LIMIT 500', [$bucketCutoff]);
            $rateBucketsPurged = (int) $this->CI->db->affected_rows();

            update_option('sp_reminder_delivery_maintenance_last_run_at', $now->format('Y-m-d H:i:s'));
            update_option('sp_reminder_delivery_maintenance_last_rows', (string) ($deliveriesPurged + $rateBucketsPurged));
            update_option('sp_reminder_delivery_maintenance_last_duration_ms',
                (string) (int) round((microtime(true) - $startedAt) * 1000));

            return [
                'skipped' => false,
                'deliveries_purged' => $deliveriesPurged,
                'rate_buckets_purged' => $rateBucketsPurged,
            ];
        } finally {
            $this->releaseLock();
        }
    }

    private function acquireLock()
    {
        if (!empty($this->CI->db->pconnect)) {
            return false;
        }
        $row = $this->CI->db->query('SELECT GET_LOCK(?, 0) AS lock_result', [
            db_prefix() . ':sales_pipeline:reminder:maintenance',
        ])->row_array();
        $this->lockAcquired = isset($row['lock_result']) && (int) $row['lock_result'] === 1;

        return $this->lockAcquired;
    }

    private function releaseLock()
    {
        if (!$this->lockAcquired) {
            return;
        }
        $this->CI->db->query('SELECT RELEASE_LOCK(?) AS lock_result', [
            db_prefix() . ':sales_pipeline:reminder:maintenance',
        ]);
        $this->lockAcquired = false;
    }
}
