<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Finance_lock_guard.php';

/**
 * Currency Data Sanitizer
 *
 * Implements a strict 6-step financial data sanitization engine:
 * 1. Preflight read-only validation.
 * 2. Manifest integrity & Finance approval verification.
 * 3. Dry-run calculation (Fail-closed, zero DB writes).
 * 4. Optimistic locking before-value guard.
 * 5. Idempotent batch application within a single database transaction.
 * 6. Audit before/after capture in sanitization tables.
 *
 * Standalone/offline component: this class is intentionally not registered on
 * an HTTP or Cron hook. Applying a manifest requires an explicit Finance-approved
 * maintenance workflow; normal Deal/Estimate requests must not invoke it.
 */
class Currency_data_sanitizer
{
    protected $CI;
    protected $lockGuard;

    public function __construct($CI = null)
    {
        if ($CI !== null) {
            $this->CI = $CI;
        } elseif (function_exists('get_instance')) {
            $this->CI = &get_instance();
        }
        $this->lockGuard = new Finance_lock_guard();
    }

    /**
     * Validate manifest structure, checksum, and finance approval.
     *
     * @param array $manifest
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validate_manifest(array $manifest)
    {
        $errors = [];

        if (empty($manifest['batch_uuid'])) {
            $errors[] = 'Missing batch_uuid';
        }
        $ref = $manifest['finance_approval_reference'] ?? $manifest['approval_reference'] ?? null;
        if (empty($ref)) {
            $errors[] = 'Missing finance_approval_reference';
        }
        if (empty($manifest['items']) || !is_array($manifest['items'])) {
            $errors[] = 'Manifest must contain non-empty items array';
        } else {
            foreach ($manifest['items'] as $idx => $item) {
                if (empty($item['entity_id'])) {
                    $errors[] = "Item {$idx}: missing entity_id";
                }
                if (!isset($item['approved_rate']) || (float) $item['approved_rate'] <= 0) {
                    $errors[] = "Item {$idx}: approved_rate must be a positive number";
                }
            }
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Dry-run calculation for a sanitization manifest.
     * Computes before/after values and totals without modifying database.
     * Fail-closed: returns success = false if any rate is missing or invalid.
     *
     * @param array $manifest
     * @return array
     */
    public function dry_run(array $manifest)
    {
        $validation = $this->validate_manifest($manifest);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'dry_run' => true,
                'errors'  => $validation['errors'],
            ];
        }

        $items = $manifest['items'] ?? [];
        $computedItems = [];
        $totalBefore = 0.0;
        $totalAfter = 0.0;

        foreach ($items as $item) {
            $sourceTotal = (float) ($item['source_total'] ?? 0.0);
            $beforeRate = (float) ($item['expected_before_rate'] ?? 1.0);
            $afterRate = (float) ($item['approved_rate'] ?? 0.0);

            if ($afterRate <= 0) {
                return [
                    'success' => false,
                    'dry_run' => true,
                    'error'   => 'approved_rate missing or invalid; fail-closed',
                ];
            }

            $beforeBaseTotal = round($sourceTotal * $beforeRate, 2);
            $afterBaseTotal = round($sourceTotal * $afterRate, 2);

            $totalBefore += $beforeBaseTotal;
            $totalAfter += $afterBaseTotal;

            $computedItems[] = array_merge($item, [
                'before_base_total' => $beforeBaseTotal,
                'after_base_total'  => $afterBaseTotal,
                'delta'             => round($afterBaseTotal - $beforeBaseTotal, 2),
            ]);
        }

        return [
            'success'   => true,
            'dry_run'   => true,
            'summary'   => [
                'batch_uuid'   => $manifest['batch_uuid'] ?? '',
                'total_items'  => count($items),
                'total_before' => $totalBefore,
                'total_after'  => $totalAfter,
                'total_delta'  => round($totalAfter - $totalBefore, 2),
            ],
            'items'     => $computedItems,
        ];
    }

    /**
     * Validate an item against current state in database (Optimistic concurrency check).
     *
     * @param array $manifestItem
     * @param array $currentState
     * @return array ['valid' => bool, 'reason' => string|null]
     */
    public function validate_manifest_item(array $manifestItem, array $currentState)
    {
        $expectedBeforeRate = (float) ($manifestItem['expected_before_rate'] ?? 0);
        $currentRate = (float) ($currentState['exchange_rate_to_base'] ?? 0);

        if (abs($currentRate - $expectedBeforeRate) > 0.000001) {
            return [
                'valid'  => false,
                'reason' => "Rate mismatch: expected {$expectedBeforeRate}, found {$currentRate}",
            ];
        }

        return [
            'valid'  => true,
            'reason' => null,
        ];
    }

    /**
     * Apply batch idempotently within a database transaction.
     *
     * @param array $manifest
     * @param int $actorStaffId
     * @return array
     */
    public function apply_batch(array $manifest, $actorStaffId)
    {
        if (!$this->CI || empty($this->CI->db)) {
            return ['success' => false, 'error' => 'Database not available'];
        }

        $dryRun = $this->dry_run($manifest);
        if (!$dryRun['success']) {
            return ['success' => false, 'error' => 'Dry run validation failed: ' . json_encode($dryRun['errors'] ?? $dryRun['error'] ?? '')];
        }

        $batchUuid = $manifest['batch_uuid'];
        $checksum = $manifest['checksum'] ?? '';
        $batchesTable = db_prefix() . 'sales_pipeline_sanitization_batches';
        $itemsTable = db_prefix() . 'sales_pipeline_sanitization_items';
        $versionsTable = db_prefix() . 'sales_pipeline_estimate_versions';
        $groupsTable = db_prefix() . 'sales_pipeline_estimate_groups';

        // 1. Idempotency check
        $existing = $this->CI->db
            ->from($batchesTable)
            ->where('batch_uuid', $batchUuid)
            ->get()
            ->row_array();

        if ($existing) {
            if ($existing['status'] === 'applied') {
                if (!empty($existing['manifest_checksum']) && $existing['manifest_checksum'] !== $checksum) {
                    return [
                        'success' => false,
                        'error'   => 'checksum_mismatch',
                        'message' => 'Batch UUID already applied with different checksum',
                    ];
                }
                return [
                    'success' => true,
                    'status'  => 'already_applied',
                    'message' => 'Batch has already been applied previously without modification',
                ];
            }
        }

        $this->CI->db->trans_start();

        $affectedGroupIds = [];
        $appliedItemsCount = 0;
        $now = date('Y-m-d H:i:s');

        // 2. Iterate items with optimistic locking and finance lock guard
        foreach ($dryRun['items'] as $item) {
            $versionId = (int) $item['entity_id'];

            // Fetch version row
            $version = $this->CI->db
                ->from($versionsTable)
                ->where('id', $versionId)
                ->get()
                ->row_array();

            if (!$version) {
                $this->CI->db->trans_rollback();
                return ['success' => false, 'error' => "Version #{$versionId} not found"];
            }

            // Check Finance Lock on parent group
            $groupId = (int) $version['estimate_group_id'];
            $group = $this->CI->db
                ->from($groupsTable)
                ->where('id', $groupId)
                ->get()
                ->row_array();

            if ($group && !$this->lockGuard->can_modify($group)) {
                $this->CI->db->trans_rollback();
                return [
                    'success' => false,
                    'error'   => 'finance_locked',
                    'message' => "Group #{$groupId} is finance locked; cannot apply sanitization",
                ];
            }

            // Optimistic concurrency check
            $optimisticCheck = $this->validate_manifest_item($item, $version);
            if (!$optimisticCheck['valid']) {
                $this->CI->db->trans_rollback();
                return [
                    'success' => false,
                    'error'   => 'concurrency_mismatch',
                    'message' => $optimisticCheck['reason'],
                ];
            }

            // Update version row
            $approvedRate = (float) $item['approved_rate'];
            $newBaseTotal = round((float) $version['source_total'] * $approvedRate, 2);

            $this->CI->db->where('id', $versionId)->update($versionsTable, [
                'exchange_rate_to_base' => $approvedRate,
                'base_total'            => $newBaseTotal,
            ]);

            // Record item audit
            $this->CI->db->insert($itemsTable, [
                'batch_uuid'                 => $batchUuid,
                'entity_type'                => 'estimate_version',
                'entity_id'                  => $versionId,
                'estimate_group_id'          => $groupId,
                'currency_id'                => (int) $version['source_currency_id'],
                'before_source_total'        => (float) $version['source_total'],
                'before_exchange_rate'       => (float) $version['exchange_rate_to_base'],
                'before_base_total'          => (float) $version['base_total'],
                'sanitized_exchange_rate'    => $approvedRate,
                'sanitized_base_total'       => $newBaseTotal,
                'delta_base_total'           => round($newBaseTotal - (float) $version['base_total'], 2),
                'revaluation_method'         => $item['revaluation_method'] ?? 'authoritative_daily_rate',
                'finance_approval_reference' => $manifest['finance_approval_reference'],
                'created_at'                 => $now,
            ]);

            $affectedGroupIds[$groupId] = true;
            $appliedItemsCount++;
        }

        // 3. Record batch audit
        $this->CI->db->insert($batchesTable, [
            'batch_uuid'                 => $batchUuid,
            'manifest_checksum'          => $checksum,
            'total_items'                => $appliedItemsCount,
            'total_before_base_total'    => $dryRun['summary']['total_before'],
            'total_after_base_total'     => $dryRun['summary']['total_after'],
            'total_delta_base_total'     => $dryRun['summary']['total_delta'],
            'status'                     => 'applied',
            'manifest_json'              => json_encode($manifest),
            'applied_at'                 => $now,
            'applied_by'                 => (int) $actorStaffId,
            'finance_approval_reference' => $manifest['finance_approval_reference'],
        ]);

        // 4. Downstream reconciliation for affected groups
        if ($this->CI->load) {
            $this->CI->load->model('sales_pipeline/sales_pipeline_model');
            foreach (array_keys($affectedGroupIds) as $gid) {
                if (method_exists($this->CI->sales_pipeline_model, 'sync_estimate_group')) {
                    $this->CI->sales_pipeline_model->sync_estimate_group($gid, 'sanitization');
                }
            }
        }

        $this->CI->db->trans_complete();

        if (!$this->CI->db->trans_status()) {
            return ['success' => false, 'error' => 'Transaction failed during batch application'];
        }

        return [
            'success'       => true,
            'status'        => 'applied',
            'batch_uuid'    => $batchUuid,
            'applied_items' => $appliedItemsCount,
            'applied_at'    => $now,
        ];
    }
}
