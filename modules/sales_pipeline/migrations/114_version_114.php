<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Migration 114: Canonical First-Sent Quote Remediation & Multi-Batch Backfill.
 *
 * Implements:
 * 1. Evidence-Rank Decision Matrix remediation (datesend replaces inferred timestamp).
 * 2. Policy A remediation: Reverses inferred timestamp without real sent evidence back to NULL (e.g. Group 145).
 * 3. Multi-batch cursor loop with strictly increasing cursor and safety bound against fake success.
 * 4. Per-batch transaction boundary; this migration owns the unified advisory
 *    lock `sales_pipeline:first_sent_reconcile` across all batches and tells
 *    the model not to acquire it again.
 * 5. Non-destructive rollback to preserve audit history.
 */
class Migration_Version_114 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $CI->load->model('sales_pipeline/sales_pipeline_model');

        $lockName = db_prefix() . 'sales_pipeline:first_sent_reconcile';
        $lockRes = $CI->db->query("SELECT GET_LOCK('{$lockName}', 10) as is_locked")->row();
        if (!$lockRes || (int) $lockRes->is_locked !== 1) {
            throw new RuntimeException("Migration 114 aborted: Could not acquire lock {$lockName}");
        }

        $cursor = 0;
        $batchSize = 100;
        $maxIterations = 1000;
        $iteration = 0;
        $hasMore = true;

        try {
            while ($hasMore && $iteration < $maxIterations) {
                $iteration++;

                $CI->db->trans_begin();
                try {
                    // Migration owns GET_LOCK/RELEASE_LOCK for the complete run;
                    // the model is explicitly told not to nest the same lock.
                    $batchResult = $CI->sales_pipeline_model->reconcile_missing_first_sent_groups($batchSize, $cursor, true, true);
                } catch (Exception $e) {
                    $CI->db->trans_rollback();
                    throw $e;
                }

                if ($CI->db->trans_status() === false) {
                    $CI->db->trans_rollback();
                    throw new RuntimeException("Migration 114 transaction failed at iteration {$iteration}, cursor {$cursor}");
                }
                $CI->db->trans_commit();

                if (!isset($batchResult['next_cursor'], $batchResult['has_more'], $batchResult['scanned'])) {
                    throw new RuntimeException("Migration 114: Malformed batch result at iteration {$iteration}");
                }

                if ($batchResult['has_more'] && $batchResult['next_cursor'] <= $cursor) {
                    throw new RuntimeException("Migration 114 aborted: Cursor did not advance at {$cursor}");
                }

                $cursor = $batchResult['next_cursor'];
                $hasMore = (bool) $batchResult['has_more'];
            }

            if ($hasMore) {
                throw new RuntimeException("Migration 114 aborted: max iterations ({$maxIterations}) reached with pending records remaining");
            }
        } finally {
            $CI->db->query("SELECT RELEASE_LOCK('{$lockName}')");
        }
    }

    /**
     * Non-destructive rollback:
     * Audited first_sent_at and evidence sources have business and compliance value.
     */
    public function down()
    {
        // Intentionally non-destructive to preserve audit history and prevent data loss.
    }
}
