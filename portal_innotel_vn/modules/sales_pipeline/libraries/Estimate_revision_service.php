<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Service handling Quote / Estimate Revisions, Grouping, Intent Normalization,
 * Business Guards, Fail-Safe Fallbacks, Manual Link/Unlink, Deal Bridge, and Immutable Audit Logging.
 */
class Estimate_revision_service
{
    /**
     * Request-scoped captured intent from form submission (before_estimate_added).
     * @var array|null
     */
    private static $capturedContext = null;

    /**
     * Request-scoped copy context from controller (native copy or module copy).
     * @var array|null
     */
    private static $copyContext = null;

    /**
     * CodeIgniter super-object reference.
     * @var CI_Controller
     */
    protected $CI;

    public function __construct()
    {
        $this->CI = get_instance();
        $this->CI->load->model('sales_pipeline/sales_pipeline_model');
    }

    /**
     * Capture and strip sales_pipeline namespace from before_estimate_added filter.
     *
     * @param array $hookPayload ['data' => ..., 'items' => ...]
     * @return array Modified hook payload with sales_pipeline stripped from data
     */
    public function capture_request_context(array $hookPayload)
    {
        $data = isset($hookPayload['data']) && is_array($hookPayload['data']) ? $hookPayload['data'] : [];

        if (isset($data['sales_pipeline']) && is_array($data['sales_pipeline'])) {
            $raw = $data['sales_pipeline'];
            $intent = isset($raw['intent']) && $raw['intent'] === 'revision' ? 'declared_revision' : 'standalone';
            $sourceId = !empty($raw['revision_of_estimate_id']) ? (int) $raw['revision_of_estimate_id'] : null;
            $overrideAccepted = !empty($raw['override_accepted']);
            $overrideReason = isset($raw['override_reason']) ? trim((string) $raw['override_reason']) : null;
            $actorId = get_staff_user_id() ? (int) get_staff_user_id() : null;

            self::$capturedContext = [
                'intent'             => $intent,
                'source_estimate_id' => $sourceId,
                'parent_estimate_id' => $sourceId,
                'link_method'        => ($intent === 'declared_revision' && $sourceId > 0) ? 'declared_revision' : 'origin',
                'override_accepted'  => $overrideAccepted,
                'override_reason'    => $overrideReason,
                'actor_staff_id'     => $actorId,
            ];

            // Crucial: strip namespace so Perfex core insert into tblestimates won't fail
            unset($hookPayload['data']['sales_pipeline']);
        } else {
            self::$capturedContext = null;
        }

        return $hookPayload;
    }

    /**
     * Get the request-scoped captured context.
     *
     * @return array|null
     */
    public function get_captured_context()
    {
        return self::$capturedContext;
    }

    /**
     * Clear captured context.
     */
    public function clear_captured_context()
    {
        self::$capturedContext = null;
    }

    /**
     * Set explicit copy context (e.g. from duplicate_estimate or native copy).
     *
     * @param int $sourceId
     * @param string $linkMethod
     * @param int|null $parentEstimateId
     */
    public function set_copy_context($sourceId, $linkMethod = 'native_copy', $parentEstimateId = null)
    {
        self::$copyContext = [
            'intent'             => 'copy',
            'source_estimate_id' => (int) $sourceId,
            'parent_estimate_id' => $parentEstimateId ? (int) $parentEstimateId : (int) $sourceId,
            'link_method'        => $linkMethod,
            'override_accepted'  => false,
            'override_reason'    => null,
            'actor_staff_id'     => get_staff_user_id() ? (int) get_staff_user_id() : null,
        ];
    }

    /**
     * Get copy context.
     *
     * @return array|null
     */
    public function get_copy_context()
    {
        return self::$copyContext;
    }

    /**
     * Clear copy context.
     */
    public function clear_copy_context()
    {
        self::$copyContext = null;
    }

    /**
     * Handle after_estimate_added event: link as revision or create standalone group.
     *
     * @param int $estimateId
     * @param array|null $context
     * @return array Standard result contract
     */
    public function handle_estimate_added($estimateId, ?array $context = null)
    {
        $estimateId = (int) $estimateId;
        if ($estimateId <= 0) {
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $estimateId,
                'estimate_group_id' => null,
                'revision_no'       => null,
                'warning_code'      => 'invalid_id',
                'message_key'       => 'sales_pipeline_quote_invalid_estimate',
            ];
        }

        // Resolve context priority: explicit arg > copyContext > capturedContext
        if ($context === null) {
            if (self::$copyContext !== null) {
                $context = self::$copyContext;
            } elseif (self::$capturedContext !== null) {
                $context = self::$capturedContext;
            }
        }

        // Clean up request-scoped state
        self::$copyContext = null;
        self::$capturedContext = null;

        $sourceId = !empty($context['source_estimate_id']) ? (int) $context['source_estimate_id'] : 0;
        $linkMethod = !empty($context['link_method']) ? (string) $context['link_method'] : 'origin';

        // Case 1: Standalone creation (no source specified or intent is standalone)
        if ($sourceId <= 0 || $linkMethod === 'origin') {
            $groupId = $this->create_standalone_group($estimateId, 'standalone');
            return [
                'success'           => true,
                'action'            => 'standalone',
                'estimate_id'       => $estimateId,
                'estimate_group_id' => (int) $groupId,
                'revision_no'       => 1,
                'warning_code'      => null,
                'message_key'       => 'sales_pipeline_estimate_group_created',
            ];
        }

        // Case 2: Explicit revision or copy -> validate business guards
        $validation = $this->validate_revision_context($estimateId, $context);

        if (!$validation['valid']) {
            $actorStaffId = $context['actor_staff_id'] ?? (get_staff_user_id() ? (int) get_staff_user_id() : null);
            $fallbackGroupId = $this->create_fallback_standalone_with_audit(
                $estimateId,
                $sourceId,
                $validation['target_group_id'] ?? null,
                $linkMethod,
                $actorStaffId,
                $validation['error_code'],
                [
                    'override_reason' => $context['override_reason'] ?? null,
                ]
            );

            if (!$fallbackGroupId) {
                return [
                    'success'           => false,
                    'action'            => 'none',
                    'estimate_id'       => $estimateId,
                    'estimate_group_id' => null,
                    'revision_no'       => null,
                    'warning_code'      => 'fallback_failed',
                    'message_key'       => 'sales_pipeline_error_occurred',
                ];
            }

            if (function_exists('set_alert')) {
                set_alert('warning', _l($validation['message_key'] ?? 'sales_pipeline_revision_fallback_warning'));
            }

            return [
                'success'           => true,
                'action'            => 'fallback_standalone',
                'estimate_id'       => $estimateId,
                'estimate_group_id' => (int) $fallbackGroupId,
                'revision_no'       => 1,
                'warning_code'      => $validation['error_code'],
                'message_key'       => $validation['message_key'],
            ];
        }

        // Validation passed -> append revision to target group
        $targetGroupId = (int) $validation['target_group_id'];
        $revisionNo = $this->append_revision($targetGroupId, $estimateId, $context, $validation['is_accepted_override'] ?? false);

        if ($revisionNo === false) {
            // Append transaction failed -> fallback standalone atomically
            $actorStaffId = $context['actor_staff_id'] ?? (get_staff_user_id() ? (int) get_staff_user_id() : null);
            $fallbackGroupId = $this->create_fallback_standalone_with_audit(
                $estimateId,
                $sourceId,
                $targetGroupId,
                $linkMethod,
                $actorStaffId,
                'append_failed',
                [
                    'override_reason' => $context['override_reason'] ?? null,
                ]
            );

            if (!$fallbackGroupId) {
                return [
                    'success'           => false,
                    'action'            => 'none',
                    'estimate_id'       => $estimateId,
                    'estimate_group_id' => null,
                    'revision_no'       => null,
                    'warning_code'      => 'fallback_failed',
                    'message_key'       => 'sales_pipeline_error_occurred',
                ];
            }

            if (function_exists('set_alert')) {
                set_alert('warning', _l('sales_pipeline_revision_fallback_warning'));
            }

            return [
                'success'           => true,
                'action'            => 'fallback_standalone',
                'estimate_id'       => $estimateId,
                'estimate_group_id' => (int) $fallbackGroupId,
                'revision_no'       => 1,
                'warning_code'      => 'append_failed',
                'message_key'       => 'sales_pipeline_revision_fallback_warning',
            ];
        }

        // Outside transaction: Sync target group & sync deal if linked
        $this->CI->sales_pipeline_model->sync_estimate_group($targetGroupId);
        $this->sync_deal_from_estimate_group($targetGroupId);

        return [
            'success'           => true,
            'action'            => 'linked',
            'estimate_id'       => $estimateId,
            'estimate_group_id' => $targetGroupId,
            'revision_no'       => (int) $revisionNo,
            'warning_code'      => null,
            'message_key'       => 'sales_pipeline_revision_linked',
        ];
    }

    /**
     * Validate business guards for linking an estimate as a revision.
     *
     * @param int $estimateId
     * @param array $context
     * @return array ['valid' => bool, 'target_group_id' => int, 'error_code' => string, 'message_key' => string]
     */
    public function validate_revision_context($estimateId, array $context)
    {
        $sourceId = (int) ($context['source_estimate_id'] ?? 0);
        $estimateTable = db_prefix() . 'estimates';
        $groupTable = db_prefix() . 'sales_pipeline_estimate_groups';
        $versionTable = db_prefix() . 'sales_pipeline_estimate_versions';
        $actorStaffId = $context['actor_staff_id'] ?? (get_staff_user_id() ? (int) get_staff_user_id() : 0);

        // Check view permission on source estimate
        if (function_exists('user_can_view_estimate') && $actorStaffId > 0 && $sourceId > 0) {
            if (!user_can_view_estimate($sourceId, $actorStaffId)) {
                return [
                    'valid'        => false,
                    'error_code'   => 'permission_denied_source',
                    'message_key'  => 'sales_pipeline_permission_denied',
                ];
            }
        }

        // Check view permission on new/target estimate if already created
        if (function_exists('user_can_view_estimate') && $actorStaffId > 0 && (int) $estimateId > 0) {
            if (!user_can_view_estimate((int) $estimateId, $actorStaffId)) {
                return [
                    'valid'        => false,
                    'error_code'   => 'permission_denied_target',
                    'message_key'  => 'sales_pipeline_permission_denied',
                ];
            }
        }

        $newEstimate = $this->CI->db
            ->select('id, clientid, total, currency, status, datecreated, sale_agent, addedfrom')
            ->where('id', (int) $estimateId)
            ->get($estimateTable)
            ->row_array();

        $sourceEstimate = $this->CI->db
            ->select('id, clientid, total, currency, status, datecreated')
            ->where('id', $sourceId)
            ->get($estimateTable)
            ->row_array();

        if (!$newEstimate || !$sourceEstimate) {
            return [
                'valid'        => false,
                'error_code'   => 'estimate_not_found',
                'message_key'  => 'sales_pipeline_quote_invalid_estimate',
            ];
        }

        // Hard-guard 1: Customer boundary (clientid must match)
        if ((int) $newEstimate['clientid'] !== (int) $sourceEstimate['clientid']) {
            return [
                'valid'        => false,
                'error_code'   => 'customer_mismatch',
                'message_key'  => 'sales_pipeline_revision_client_mismatch',
            ];
        }

        // Resolve target group for source estimate
        $sourceVersion = $this->CI->db
            ->where('estimate_id', $sourceId)
            ->get($versionTable)
            ->row_array();

        if (!$sourceVersion) {
            // Source doesn't have a group yet -> bootstrap source as standalone origin
            $targetGroupId = $this->create_standalone_group($sourceId, 'origin_backfill');
        } else {
            $targetGroupId = (int) $sourceVersion['estimate_group_id'];
        }

        if (!$targetGroupId) {
            return [
                'valid'        => false,
                'error_code'   => 'target_group_missing',
                'message_key'  => 'sales_pipeline_quote_invalid_estimate',
            ];
        }

        $targetGroup = $this->CI->db
            ->where('id', $targetGroupId)
            ->get($groupTable)
            ->row_array();

        if (!$targetGroup) {
            return [
                'valid'        => false,
                'error_code'   => 'target_group_missing',
                'message_key'  => 'sales_pipeline_quote_invalid_estimate',
            ];
        }

        // Hard-guard 2: Accepted Group Lock (Source of Truth is grp.outcome)
        $isAccepted = ($targetGroup['outcome'] === 'accepted');
        $isAcceptedOverride = false;

        if ($isAccepted) {
            $canManage = is_admin($actorStaffId)
                || (function_exists('has_permission') && has_permission('sales_pipeline', (string) $actorStaffId, 'manage_estimate_revisions'));

            if (!$canManage) {
                return [
                    'valid'        => false,
                    'error_code'   => 'accepted_locked',
                    'message_key'  => 'sales_pipeline_revision_accepted_locked',
                ];
            }

            $reason = trim((string) ($context['override_reason'] ?? ''));
            if ($reason === '') {
                return [
                    'valid'        => false,
                    'error_code'   => 'override_reason_required',
                    'message_key'  => 'sales_pipeline_override_reason_required',
                ];
            }

            $isAcceptedOverride = true;
        }

        return [
            'valid'                => true,
            'target_group_id'      => $targetGroupId,
            'is_accepted_override' => $isAcceptedOverride,
        ];
    }

    /**
     * Create a standalone Estimate Group (revision_no = 1).
     *
     * @param int $estimateId
     * @param string $source
     * @param int|null $linkedBy
     * @return int|false Group ID
     */
    public function create_standalone_group($estimateId, $source = 'standalone', $linkedBy = null)
    {
        $estimateId = (int) $estimateId;
        $versionTable = db_prefix() . 'sales_pipeline_estimate_versions';
        $groupTable = db_prefix() . 'sales_pipeline_estimate_groups';

        // Check if already in a version
        $existing = $this->CI->db
            ->where('estimate_id', $estimateId)
            ->get($versionTable)
            ->row_array();

        if ($existing) {
            return (int) $existing['estimate_group_id'];
        }

        $snapshot = $this->get_estimate_snapshot($estimateId);
        if (!$snapshot) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $groupData = [
            'client_id'          => (int) $snapshot['clientid'],
            'owner_staff_id'     => (int) $snapshot['owner_staff_id'],
            'current_estimate_id'=> $estimateId,
            'origin_estimate_id' => $estimateId,
            'outcome'            => (int) $snapshot['status'] === 4 ? 'accepted' : ((int) $snapshot['status'] === 3 ? 'declined' : 'pending'),
            'decision_at'        => (int) $snapshot['status'] === 4 ? ($snapshot['invoiced_date'] ?: $now) : null,
            'decision_source'    => (int) $snapshot['status'] === 4 ? 'origin' : null,
            'source_currency_id' => (int) $snapshot['currency'],
            'base_currency_id'   => (int) $snapshot['base_currency_id'],
            'created_value_base' => $snapshot['base_total'],
            'grouping_source'    => $source,
            'datecreated'        => $now,
            'datemodified'       => $now,
        ];

        $this->CI->db->trans_start();
        $this->CI->db->insert($groupTable, $groupData);
        $groupId = (int) $this->CI->db->insert_id();

        if ($groupId > 0) {
            $versionData = [
                'estimate_group_id'     => $groupId,
                'estimate_id'           => $estimateId,
                'parent_estimate_id'    => null,
                'link_method'           => 'origin',
                'linked_by'             => $linkedBy ? (int) $linkedBy : (get_staff_user_id() ? (int) get_staff_user_id() : null),
                'revision_no'           => 1,
                'source_currency_id'    => (int) $snapshot['currency'],
                'source_total'          => $snapshot['total'],
                'exchange_rate_to_base' => $snapshot['exchange_rate'],
                'base_currency_id'      => (int) $snapshot['base_currency_id'],
                'base_total'            => $snapshot['base_total'],
                'rate_captured_at'      => $snapshot['exchange_rate'] === null ? null : $now,
                'date_linked'           => $now,
            ];
            $this->CI->db->insert($versionTable, $versionData);

            // Record audit event
            $recorded = $this->record_event('group_created', $estimateId, null, null, $groupId, $versionData['linked_by'], $source, ['source' => $source]);
            if (!$recorded) {
                $this->CI->db->trans_rollback();
                return false;
            }
        }

        $this->CI->db->trans_complete();

        if ($this->CI->db->trans_status() === false || $groupId <= 0) {
            return false;
        }

        return $groupId;
    }

    /**
     * Create a fallback standalone Estimate Group and write both failed & fallback audit events atomically.
     * If any insert or audit write fails, rolls back the entire transaction.
     *
     * @param int $estimateId
     * @param int $sourceEstimateId
     * @param int|null $targetGroupId
     * @param string $linkMethod
     * @param int|null $actorStaffId
     * @param string $errorCode
     * @param array $metadata
     * @return int|false Fallback Group ID
     */
    public function create_fallback_standalone_with_audit($estimateId, $sourceEstimateId, $targetGroupId, $linkMethod, $actorStaffId, $errorCode, array $metadata = [])
    {
        $estimateId = (int) $estimateId;
        $versionTable = db_prefix() . 'sales_pipeline_estimate_versions';
        $groupTable = db_prefix() . 'sales_pipeline_estimate_groups';

        $snapshot = $this->get_estimate_snapshot($estimateId);
        if (!$snapshot) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $groupData = [
            'client_id'           => (int) $snapshot['clientid'],
            'owner_staff_id'      => (int) $snapshot['owner_staff_id'],
            'current_estimate_id' => $estimateId,
            'origin_estimate_id'  => $estimateId,
            'outcome'             => (int) $snapshot['status'] === 4 ? 'accepted' : ((int) $snapshot['status'] === 3 ? 'declined' : 'pending'),
            'decision_at'         => (int) $snapshot['status'] === 4 ? ($snapshot['invoiced_date'] ?: $now) : null,
            'decision_source'     => (int) $snapshot['status'] === 4 ? 'origin' : null,
            'source_currency_id'  => (int) $snapshot['currency'],
            'base_currency_id'    => (int) $snapshot['base_currency_id'],
            'created_value_base'  => $snapshot['base_total'],
            'grouping_source'     => 'fallback_standalone',
            'datecreated'         => $now,
            'datemodified'        => $now,
        ];

        $this->CI->db->trans_start();

        $this->CI->db->insert($groupTable, $groupData);
        $groupId = (int) $this->CI->db->insert_id();

        if ($groupId <= 0) {
            $this->CI->db->trans_rollback();
            return false;
        }

        $versionData = [
            'estimate_group_id'     => $groupId,
            'estimate_id'           => $estimateId,
            'parent_estimate_id'    => null,
            'link_method'           => 'origin',
            'linked_by'             => $actorStaffId ? (int) $actorStaffId : (get_staff_user_id() ? (int) get_staff_user_id() : null),
            'revision_no'           => 1,
            'source_currency_id'    => (int) $snapshot['currency'],
            'source_total'          => $snapshot['total'],
            'exchange_rate_to_base' => $snapshot['exchange_rate'],
            'base_currency_id'      => (int) $snapshot['base_currency_id'],
            'base_total'            => $snapshot['base_total'],
            'rate_captured_at'      => $snapshot['exchange_rate'] === null ? null : $now,
            'date_linked'           => $now,
        ];
        $this->CI->db->insert($versionTable, $versionData);

        // Audit 1: group_created
        $evt1 = $this->record_event('group_created', $estimateId, null, null, $groupId, $versionData['linked_by'], 'fallback_standalone', ['source' => 'fallback_standalone']);

        // Audit 2: revision_link_failed
        $metaLinkFailed = array_merge([
            'source_estimate_id'    => (int) $sourceEstimateId,
            'intended_target_group' => $targetGroupId ? (int) $targetGroupId : null,
            'intended_link_method'  => $linkMethod,
            'error_code'            => $errorCode,
            'actor_staff_id'        => $actorStaffId,
        ], $metadata);
        $evt2 = $this->record_event('revision_link_failed', $estimateId, $sourceEstimateId, $targetGroupId, null, $actorStaffId, $errorCode, $metaLinkFailed);

        // Audit 3: revision_fallback_standalone
        $metaFallback = array_merge([
            'source_estimate_id'    => (int) $sourceEstimateId,
            'fallback_group_id'     => $groupId,
            'intended_link_method'  => $linkMethod,
            'error_code'            => $errorCode,
            'actor_staff_id'        => $actorStaffId,
        ], $metadata);
        $evt3 = $this->record_event('revision_fallback_standalone', $estimateId, $sourceEstimateId, null, $groupId, $actorStaffId, $errorCode, $metaFallback);

        if (!$evt1 || !$evt2 || !$evt3) {
            $this->CI->db->trans_rollback();
            return false;
        }

        $this->CI->db->trans_complete();

        if ($this->CI->db->trans_status() === false) {
            return false;
        }

        return $groupId;
    }

    /**
     * Append an estimate as a revision to an existing group.
     *
     * @param int $groupId
     * @param int $estimateId
     * @param array $context
     * @param bool $isAcceptedOverride
     * @return int|false New revision_no
     */
    public function append_revision($groupId, $estimateId, array $context, $isAcceptedOverride = false)
    {
        $groupId = (int) $groupId;
        $estimateId = (int) $estimateId;
        $groupTable = db_prefix() . 'sales_pipeline_estimate_groups';
        $versionTable = db_prefix() . 'sales_pipeline_estimate_versions';

        $snapshot = $this->get_estimate_snapshot($estimateId);
        if (!$snapshot) {
            return false;
        }

        $this->CI->db->trans_start();

        // Lock target group row to prevent concurrent revision number collisions
        $this->CI->db->query(
            'SELECT id, current_estimate_id FROM `' . $groupTable . '` WHERE `id` = ? FOR UPDATE',
            [$groupId]
        );

        // Determine next revision number: MAX(revision_no) + 1
        $maxRow = $this->CI->db
            ->select('MAX(revision_no) as max_rev')
            ->where('estimate_group_id', $groupId)
            ->get($versionTable)
            ->row_array();

        $nextRevisionNo = $maxRow && $maxRow['max_rev'] !== null ? ((int) $maxRow['max_rev'] + 1) : 1;
        $now = date('Y-m-d H:i:s');
        $parentEstimateId = !empty($context['parent_estimate_id']) ? (int) $context['parent_estimate_id'] : (!empty($context['source_estimate_id']) ? (int) $context['source_estimate_id'] : null);
        $linkMethod = !empty($context['link_method']) ? (string) $context['link_method'] : 'declared_revision';
        $linkedBy = !empty($context['actor_staff_id']) ? (int) $context['actor_staff_id'] : (get_staff_user_id() ? (int) get_staff_user_id() : null);

        $versionData = [
            'estimate_group_id'     => $groupId,
            'estimate_id'           => $estimateId,
            'parent_estimate_id'    => $parentEstimateId,
            'link_method'           => $linkMethod,
            'linked_by'             => $linkedBy,
            'revision_no'           => $nextRevisionNo,
            'source_currency_id'    => (int) $snapshot['currency'],
            'source_total'          => $snapshot['total'],
            'exchange_rate_to_base' => $snapshot['exchange_rate'],
            'base_currency_id'      => (int) $snapshot['base_currency_id'],
            'base_total'            => $snapshot['base_total'],
            'rate_captured_at'      => $snapshot['exchange_rate'] === null ? null : $now,
            'date_linked'           => $now,
        ];
        $this->CI->db->insert($versionTable, $versionData);

        // Update group current_estimate_id
        $this->CI->db->where('id', $groupId)->update($groupTable, [
            'current_estimate_id' => $estimateId,
            'datemodified'        => $now,
        ]);

        // Record audit event
        $eventType = $isAcceptedOverride ? 'accepted_override' : 'revision_linked';
        $recorded = $this->record_event(
            $eventType,
            $estimateId,
            $parentEstimateId,
            null,
            $groupId,
            $linkedBy,
            $context['override_reason'] ?? null,
            [
                'revision_no' => $nextRevisionNo,
                'link_method' => $linkMethod,
            ]
        );

        if (!$recorded) {
            $this->CI->db->trans_rollback();
            return false;
        }

        $this->CI->db->trans_complete();

        if ($this->CI->db->trans_status() === false) {
            return false;
        }

        return $nextRevisionNo;
    }

    /**
     * Manual Link: Link a standalone estimate to an existing target group.
     *
     * @param int $sourceEstimateId
     * @param int $targetEstimateId
     * @param int|null $actorStaffId
     * @param string|null $reason
     * @return array Standard result contract
     */
    public function link_standalone_revision($sourceEstimateId, $targetEstimateId, $actorStaffId = null, $reason = null)
    {
        $sourceEstimateId = (int) $sourceEstimateId;
        $targetEstimateId = (int) $targetEstimateId;
        if ($actorStaffId === null) {
            $actorStaffId = get_staff_user_id() ? (int) get_staff_user_id() : 0;
        }

        if ($sourceEstimateId <= 0 || $targetEstimateId <= 0 || $sourceEstimateId === $targetEstimateId) {
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $sourceEstimateId,
                'estimate_group_id' => null,
                'revision_no'       => null,
                'warning_code'      => 'invalid_id',
                'message_key'       => 'sales_pipeline_quote_invalid_estimate',
            ];
        }

        // Manual Link is a revision-management action. Require the capability
        // even when both estimates are visible and the target group is pending.
        $canManage = is_admin($actorStaffId)
            || (function_exists('has_permission') && has_permission('sales_pipeline', (string) $actorStaffId, 'manage_estimate_revisions'));
        if (!$canManage) {
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $sourceEstimateId,
                'estimate_group_id' => null,
                'revision_no'       => null,
                'warning_code'      => 'permission_denied',
                'message_key'       => 'sales_pipeline_permission_denied',
            ];
        }

        // Check permissions
        if (function_exists('user_can_view_estimate') && $actorStaffId > 0) {
            if (!user_can_view_estimate($sourceEstimateId, $actorStaffId) || !user_can_view_estimate($targetEstimateId, $actorStaffId)) {
                return [
                    'success'           => false,
                    'action'            => 'none',
                    'estimate_id'       => $sourceEstimateId,
                    'estimate_group_id' => null,
                    'revision_no'       => null,
                    'warning_code'      => 'permission_denied',
                    'message_key'       => 'sales_pipeline_permission_denied',
                ];
            }
        }

        $versionTable = db_prefix() . 'sales_pipeline_estimate_versions';
        $groupTable = db_prefix() . 'sales_pipeline_estimate_groups';
        $estimateTable = db_prefix() . 'estimates';

        $sourceEstimate = $this->CI->db->where('id', $sourceEstimateId)->get($estimateTable)->row_array();
        $targetEstimate = $this->CI->db->where('id', $targetEstimateId)->get($estimateTable)->row_array();

        if (!$sourceEstimate || !$targetEstimate) {
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $sourceEstimateId,
                'estimate_group_id' => null,
                'revision_no'       => null,
                'warning_code'      => 'estimate_not_found',
                'message_key'       => 'sales_pipeline_quote_invalid_estimate',
            ];
        }

        // Hard Guard 1: Customer boundary
        if ((int) $sourceEstimate['clientid'] !== (int) $targetEstimate['clientid']) {
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $sourceEstimateId,
                'estimate_group_id' => null,
                'revision_no'       => null,
                'warning_code'      => 'customer_mismatch',
                'message_key'       => 'sales_pipeline_revision_client_mismatch',
            ];
        }

        // Resolve versions & groups
        $sourceVersion = $this->CI->db->where('estimate_id', $sourceEstimateId)->get($versionTable)->row_array();
        $targetVersion = $this->CI->db->where('estimate_id', $targetEstimateId)->get($versionTable)->row_array();

        $sourceGroupId = $sourceVersion ? (int) $sourceVersion['estimate_group_id'] : $this->create_standalone_group($sourceEstimateId);
        $targetGroupId = $targetVersion ? (int) $targetVersion['estimate_group_id'] : $this->create_standalone_group($targetEstimateId);

        if ($sourceGroupId === $targetGroupId) {
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $sourceEstimateId,
                'estimate_group_id' => $targetGroupId,
                'revision_no'       => null,
                'warning_code'      => 'already_same_group',
                'message_key'       => 'sales_pipeline_already_same_group',
            ];
        }

        // Hard Guard 2: Source must be standalone with exactly 1 revision and pending
        $sourceCount = (int) $this->CI->db->where('estimate_group_id', $sourceGroupId)->count_all_results($versionTable);
        $sourceGroup = $this->CI->db->where('id', $sourceGroupId)->get($groupTable)->row_array();

        if ($sourceCount > 1 || ($sourceGroup && $sourceGroup['outcome'] !== 'pending')) {
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $sourceEstimateId,
                'estimate_group_id' => null,
                'revision_no'       => null,
                'warning_code'      => 'source_not_standalone',
                'message_key'       => 'sales_pipeline_source_not_standalone',
            ];
        }

        // Hard Guard 3: Target Accepted Lock
        $targetGroup = $this->CI->db->where('id', $targetGroupId)->get($groupTable)->row_array();
        $isAccepted = $targetGroup && ($targetGroup['outcome'] === 'accepted');
        $isAcceptedOverride = false;

        if ($isAccepted) {
            $canManage = is_admin($actorStaffId)
                || (function_exists('has_permission') && has_permission('sales_pipeline', (string) $actorStaffId, 'manage_estimate_revisions'));

            if (!$canManage) {
                return [
                    'success'           => false,
                    'action'            => 'none',
                    'estimate_id'       => $sourceEstimateId,
                    'estimate_group_id' => null,
                    'revision_no'       => null,
                    'warning_code'      => 'accepted_locked',
                    'message_key'       => 'sales_pipeline_revision_accepted_locked',
                ];
            }

            $reason = trim((string) $reason);
            if ($reason === '') {
                return [
                    'success'           => false,
                    'action'            => 'none',
                    'estimate_id'       => $sourceEstimateId,
                    'estimate_group_id' => null,
                    'revision_no'       => null,
                    'warning_code'      => 'override_reason_required',
                    'message_key'       => 'sales_pipeline_override_reason_required',
                ];
            }
            $isAcceptedOverride = true;
        }

        // Transaction Execution: Lock groups in ascending ID order
        $firstLockId = min($sourceGroupId, $targetGroupId);
        $secondLockId = max($sourceGroupId, $targetGroupId);

        $this->CI->db->trans_start();
        $this->CI->db->query(
            "SELECT id, current_estimate_id FROM `{$groupTable}` WHERE id = ? FOR UPDATE",
            [$firstLockId]
        );
        $this->CI->db->query(
            "SELECT id, current_estimate_id FROM `{$groupTable}` WHERE id = ? FOR UPDATE",
            [$secondLockId]
        );

        // Target's current_estimate_id becomes parent_estimate_id
        $freshTargetGroup = $this->CI->db->where('id', $targetGroupId)->get($groupTable)->row_array();
        $targetParentId = $freshTargetGroup ? (int) $freshTargetGroup['current_estimate_id'] : $targetEstimateId;

        // Next revision number in target group
        $maxRow = $this->CI->db->select('MAX(revision_no) as max_rev')->where('estimate_group_id', $targetGroupId)->get($versionTable)->row_array();
        $nextRevNo = $maxRow && $maxRow['max_rev'] !== null ? ((int) $maxRow['max_rev'] + 1) : 1;
        $now = date('Y-m-d H:i:s');

        // Move version row from source group to target group
        $this->CI->db->where('estimate_id', $sourceEstimateId)->update($versionTable, [
            'estimate_group_id'  => $targetGroupId,
            'revision_no'        => $nextRevNo,
            'parent_estimate_id' => $targetParentId,
            'link_method'        => 'manual_link',
            'linked_by'          => $actorStaffId,
            'date_linked'        => $now,
        ]);

        // Update target group current_estimate_id
        $this->CI->db->where('id', $targetGroupId)->update($groupTable, [
            'current_estimate_id' => $sourceEstimateId,
            'datemodified'        => $now,
        ]);

        // Clean up empty source group (Audit events are preserved)
        $this->CI->db->where('id', $sourceGroupId)->delete($groupTable);

        // Record audit event
        $eventType = $isAcceptedOverride ? 'accepted_override' : 'revision_linked';
        $recorded = $this->record_event(
            $eventType,
            $sourceEstimateId,
            $targetParentId,
            $sourceGroupId,
            $targetGroupId,
            $actorStaffId,
            $reason,
            [
                'action'       => 'manual_link',
                'revision_no'  => $nextRevNo,
                'link_method'  => 'manual_link',
            ]
        );

        if (!$recorded) {
            $this->CI->db->trans_rollback();
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $sourceEstimateId,
                'estimate_group_id' => null,
                'revision_no'       => null,
                'warning_code'      => 'audit_insert_failed',
                'message_key'       => 'sales_pipeline_error_occurred',
            ];
        }

        $this->CI->db->trans_complete();

        if ($this->CI->db->trans_status() === false) {
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $sourceEstimateId,
                'estimate_group_id' => null,
                'revision_no'       => null,
                'warning_code'      => 'transaction_failed',
                'message_key'       => 'sales_pipeline_error_occurred',
            ];
        }

        // Outside transaction: Sync target group & sync deal
        $this->CI->sales_pipeline_model->sync_estimate_group($targetGroupId);
        $this->sync_deal_from_estimate_group($targetGroupId);

        return [
            'success'           => true,
            'action'            => 'linked',
            'estimate_id'       => $sourceEstimateId,
            'estimate_group_id' => $targetGroupId,
            'revision_no'       => $nextRevNo,
            'warning_code'      => null,
            'message_key'       => 'sales_pipeline_revision_linked',
        ];
    }

    /**
     * Manual Unlink: Unlink the latest revision from a group and create a new standalone group.
     *
     * @param int $estimateId
     * @param int|null $actorStaffId
     * @param string|null $reason
     * @return array Standard result contract
     */
    public function unlink_estimate_revision($estimateId, $actorStaffId = null, $reason = null)
    {
        $estimateId = (int) $estimateId;
        if ($actorStaffId === null) {
            $actorStaffId = get_staff_user_id() ? (int) get_staff_user_id() : 0;
        }

        $canManage = is_admin($actorStaffId)
            || (function_exists('has_permission') && has_permission('sales_pipeline', (string) $actorStaffId, 'manage_estimate_revisions'));

        if (!$canManage) {
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $estimateId,
                'estimate_group_id' => null,
                'revision_no'       => null,
                'warning_code'      => 'permission_denied',
                'message_key'       => 'sales_pipeline_permission_denied',
            ];
        }

        $reason = trim((string) $reason);
        if ($reason === '') {
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $estimateId,
                'estimate_group_id' => null,
                'revision_no'       => null,
                'warning_code'      => 'reason_required',
                'message_key'       => 'sales_pipeline_override_reason_required',
            ];
        }

        $versionTable = db_prefix() . 'sales_pipeline_estimate_versions';
        $groupTable = db_prefix() . 'sales_pipeline_estimate_groups';
        $estimateTable = db_prefix() . 'estimates';

        $versionRow = $this->CI->db->where('estimate_id', $estimateId)->get($versionTable)->row_array();
        if (!$versionRow) {
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $estimateId,
                'estimate_group_id' => null,
                'revision_no'       => null,
                'warning_code'      => 'version_not_found',
                'message_key'       => 'sales_pipeline_quote_invalid_estimate',
            ];
        }

        $oldGroupId = (int) $versionRow['estimate_group_id'];
        $oldRevNo = (int) $versionRow['revision_no'];

        // Check group has > 1 revision
        $allVersions = $this->CI->db->where('estimate_group_id', $oldGroupId)->order_by('revision_no', 'desc')->get($versionTable)->result_array();
        if (count($allVersions) <= 1) {
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $estimateId,
                'estimate_group_id' => $oldGroupId,
                'revision_no'       => 1,
                'warning_code'      => 'cannot_unlink_only_revision',
                'message_key'       => 'sales_pipeline_cannot_unlink_only_revision',
            ];
        }

        // Must be latest revision
        $latestVersion = $allVersions[0];
        if ((int) $latestVersion['estimate_id'] !== $estimateId) {
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $estimateId,
                'estimate_group_id' => $oldGroupId,
                'revision_no'       => $oldRevNo,
                'warning_code'      => 'not_latest_revision',
                'message_key'       => 'sales_pipeline_must_unlink_latest_revision',
            ];
        }

        // Must not be decision estimate of accepted group
        $oldGroup = $this->CI->db->where('id', $oldGroupId)->get($groupTable)->row_array();
        if ($oldGroup && $oldGroup['outcome'] === 'accepted' && (int) $oldGroup['decision_estimate_id'] === $estimateId) {
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $estimateId,
                'estimate_group_id' => $oldGroupId,
                'revision_no'       => $oldRevNo,
                'warning_code'      => 'cannot_unlink_accepted_decision',
                'message_key'       => 'sales_pipeline_cannot_unlink_accepted_decision',
            ];
        }

        $snapshot = $this->get_estimate_snapshot($estimateId);
        if (!$snapshot) {
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $estimateId,
                'estimate_group_id' => $oldGroupId,
                'revision_no'       => $oldRevNo,
                'warning_code'      => 'snapshot_failed',
                'message_key'       => 'sales_pipeline_quote_invalid_estimate',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $this->CI->db->trans_start();

        // Lock old group
        $this->CI->db->query("SELECT id FROM `{$groupTable}` WHERE id = ? FOR UPDATE", [$oldGroupId]);

        // Create new group for unlinked revision
        $newGroupData = [
            'client_id'          => (int) $snapshot['clientid'],
            'owner_staff_id'     => (int) $snapshot['owner_staff_id'],
            'current_estimate_id'=> $estimateId,
            'origin_estimate_id' => $estimateId,
            'outcome'            => (int) $snapshot['status'] === 4 ? 'accepted' : ((int) $snapshot['status'] === 3 ? 'declined' : 'pending'),
            'decision_at'        => (int) $snapshot['status'] === 4 ? ($snapshot['invoiced_date'] ?: $now) : null,
            'decision_source'    => (int) $snapshot['status'] === 4 ? 'manual_unlink' : null,
            'source_currency_id' => (int) $snapshot['currency'],
            'base_currency_id'   => (int) $snapshot['base_currency_id'],
            'created_value_base' => $snapshot['base_total'],
            'grouping_source'    => 'manual_unlink',
            'datecreated'        => $now,
            'datemodified'       => $now,
        ];
        $this->CI->db->insert($groupTable, $newGroupData);
        $newGroupId = (int) $this->CI->db->insert_id();

        // Move version row to new group
        $this->CI->db->where('estimate_id', $estimateId)->update($versionTable, [
            'estimate_group_id'  => $newGroupId,
            'revision_no'        => 1,
            'parent_estimate_id' => null,
            'link_method'        => 'manual_unlink',
            'linked_by'          => $actorStaffId,
            'date_linked'        => $now,
        ]);

        // Previous revision becomes current_estimate_id of old group
        $previousRevision = $allVersions[1];
        $this->CI->db->where('id', $oldGroupId)->update($groupTable, [
            'current_estimate_id' => (int) $previousRevision['estimate_id'],
            'datemodified'        => $now,
        ]);

        // Record audit event
        $recorded = $this->record_event(
            'revision_unlinked',
            $estimateId,
            null,
            $oldGroupId,
            $newGroupId,
            $actorStaffId,
            $reason,
            [
                'action'                            => 'manual_unlink',
                'previous_group_id'                 => $oldGroupId,
                'previous_revision_no'              => $oldRevNo,
                'new_group_id'                      => $newGroupId,
                'new_revision_no'                   => 1,
                'old_group_remaining_versions'      => count($allVersions) - 1,
                'old_group_new_current_estimate_id' => (int) $previousRevision['estimate_id'],
                'reason'                            => $reason,
            ]
        );

        if (!$recorded) {
            $this->CI->db->trans_rollback();
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $estimateId,
                'estimate_group_id' => $oldGroupId,
                'revision_no'       => $oldRevNo,
                'warning_code'      => 'audit_insert_failed',
                'message_key'       => 'sales_pipeline_error_occurred',
            ];
        }

        $this->CI->db->trans_complete();

        if ($this->CI->db->trans_status() === false || $newGroupId <= 0) {
            return [
                'success'           => false,
                'action'            => 'none',
                'estimate_id'       => $estimateId,
                'estimate_group_id' => $oldGroupId,
                'revision_no'       => $oldRevNo,
                'warning_code'      => 'transaction_failed',
                'message_key'       => 'sales_pipeline_error_occurred',
            ];
        }

        // Outside transaction: Sync both groups & sync deal
        $this->CI->sales_pipeline_model->sync_estimate_group($oldGroupId);
        $this->CI->sales_pipeline_model->sync_estimate_group($newGroupId);
        $this->sync_deal_from_estimate_group($oldGroupId);
        $this->sync_deal_from_estimate_group($newGroupId);

        return [
            'success'           => true,
            'action'            => 'unlinked',
            'estimate_id'       => $estimateId,
            'estimate_group_id' => $newGroupId,
            'revision_no'       => 1,
            'warning_code'      => null,
            'message_key'       => 'sales_pipeline_revision_unlinked',
        ];
    }

    /**
     * Get complete Version History and Audit Timeline for an estimate.
     *
     * @param int $estimateId
     * @return array
     */
    public function get_estimate_version_history($estimateId)
    {
        $estimateId = (int) $estimateId;
        $versionTable = db_prefix() . 'sales_pipeline_estimate_versions';
        $groupTable = db_prefix() . 'sales_pipeline_estimate_groups';
        $estimateTable = db_prefix() . 'estimates';
        $staffTable = db_prefix() . 'staff';
        $eventsTable = db_prefix() . 'sales_pipeline_estimate_group_events';

        $versionRow = $this->CI->db->where('estimate_id', $estimateId)->get($versionTable)->row_array();
        if (!$versionRow) {
            return [
                'estimate_id' => $estimateId,
                'group'       => null,
                'versions'    => [],
                'events'      => [],
            ];
        }

        $groupId = (int) $versionRow['estimate_group_id'];
        $group = $this->CI->db->where('id', $groupId)->get($groupTable)->row_array();

        // Get all versions in group
        $this->CI->db->select('ev.*, e.number, e.prefix, e.number_format, e.date, e.expirydate, e.total, e.currency, e.status, '
            . 'st_created.firstname as creator_firstname, st_created.lastname as creator_lastname, '
            . 'st_linked.firstname as linked_firstname, st_linked.lastname as linked_lastname');
        $this->CI->db->from($versionTable . ' ev');
        $this->CI->db->join($estimateTable . ' e', 'e.id = ev.estimate_id', 'left');
        $this->CI->db->join($staffTable . ' st_created', 'st_created.staffid = e.addedfrom', 'left');
        $this->CI->db->join($staffTable . ' st_linked', 'st_linked.staffid = ev.linked_by', 'left');
        $this->CI->db->where('ev.estimate_group_id', $groupId);
        $this->CI->db->order_by('ev.revision_no', 'asc');
        $versions = $this->CI->db->get()->result_array();

        // Format versions
        foreach ($versions as &$v) {
            $v['estimate_number'] = function_exists('format_estimate_number') ? format_estimate_number($v['estimate_id']) : (string) $v['estimate_id'];
            $v['total_formatted'] = function_exists('app_format_money') ? app_format_money($v['source_total'], $v['source_currency_id']) : number_format((float) $v['source_total'], 2);
            $v['status_label'] = function_exists('format_estimate_status') ? strip_tags(format_estimate_status($v['status'], '', false)) : (string) $v['status'];
            $v['creator_name'] = trim(($v['creator_firstname'] ?? '') . ' ' . ($v['creator_lastname'] ?? ''));
            $v['linked_by_name'] = trim(($v['linked_firstname'] ?? '') . ' ' . ($v['linked_lastname'] ?? ''));
            $rawDate = !empty($v['date']) ? $v['date'] : (!empty($v['date_linked']) ? $v['date_linked'] : '');
            $v['date_formatted'] = !empty($rawDate) ? (function_exists('_d') ? _d(explode(' ', $rawDate)[0]) : date('d/m/Y', strtotime($rawDate))) : '';
        }

        // Get all audit events for this group
        $events = [];
        if ($this->CI->db->table_exists($eventsTable)) {
            $this->CI->db->select('ev.*, st.firstname as actor_firstname, st.lastname as actor_lastname');
            $this->CI->db->from($eventsTable . ' ev');
            $this->CI->db->join($staffTable . ' st', 'st.staffid = ev.actor_staff_id', 'left');
            $this->CI->db->group_start();
            $this->CI->db->where('ev.from_group_id', $groupId);
            $this->CI->db->or_where('ev.to_group_id', $groupId);
            $this->CI->db->group_end();
            $this->CI->db->order_by('ev.datecreated', 'asc');
            $events = $this->CI->db->get()->result_array();

            foreach ($events as &$ev) {
                $ev['actor_name'] = trim(($ev['actor_firstname'] ?? '') . ' ' . ($ev['actor_lastname'] ?? ''));
                $ev['metadata'] = !empty($ev['metadata_json']) ? json_decode($ev['metadata_json'], true) : [];
                $ev['datecreated_formatted'] = !empty($ev['datecreated']) ? (function_exists('_dt') ? _dt($ev['datecreated']) : date('d/m/Y H:i', strtotime($ev['datecreated']))) : '';
            }
        }

        $actorStaffId = get_staff_user_id() ? (int) get_staff_user_id() : 0;
        $isManager = is_admin($actorStaffId)
            || (function_exists('has_permission') && has_permission('sales_pipeline', (string) $actorStaffId, 'manage_estimate_revisions'));

        return [
            'estimate_id' => $estimateId,
            'group'       => $group,
            'versions'    => $versions,
            'events'      => $events,
            'is_manager'  => $isManager,
        ];
    }

    /**
     * Deal Bridge: Link an Estimate Group to a Deal (1:N relationship).
     *
     * @param int $dealId
     * @param int $estimateGroupId
     * @param int|null $actorStaffId
     * @param bool $isPrimary
     * @return bool
     */
    public function link_deal_estimate_group($dealId, $estimateGroupId, $actorStaffId = null, $isPrimary = false)
    {
        $dealId = (int) $dealId;
        $estimateGroupId = (int) $estimateGroupId;
        $bridgeTable = db_prefix() . 'sales_pipeline_deal_estimate_groups';

        if (!$this->CI->db->table_exists($bridgeTable) || $dealId <= 0 || $estimateGroupId <= 0) {
            return false;
        }

        // Check if group already linked to a deal
        $existing = $this->CI->db->where('estimate_group_id', $estimateGroupId)->get($bridgeTable)->row_array();
        if ($existing) {
            $oldDealId = (int) $existing['pipeline_id'];
            if ($oldDealId === $dealId) {
                return true;
            }
            // Move group to new deal
            $this->CI->db->where('id', $existing['id'])->update($bridgeTable, [
                'pipeline_id' => $dealId,
                'linked_by'   => $actorStaffId,
            ]);

            // If it was primary in the old deal, reassign primary in old deal
            if ((int) $existing['is_primary'] === 1) {
                $nextOldGroup = $this->CI->db->where('pipeline_id', $oldDealId)->order_by('datecreated', 'asc')->get($bridgeTable)->row_array();
                if ($nextOldGroup) {
                    $this->CI->db->where('id', $nextOldGroup['id'])->update($bridgeTable, ['is_primary' => 1]);
                }
            }

            // Resync BOTH old and new deals
            $this->sync_deal($oldDealId);
            $this->sync_deal($dealId);
            return true;
        }

        // Check if this is the first group linked to deal -> automatically make primary
        $currentGroupsCount = $this->CI->db->where('pipeline_id', $dealId)->count_all_results($bridgeTable);
        $primaryFlag = ($currentGroupsCount === 0 || $isPrimary) ? 1 : 0;

        if ($primaryFlag === 1) {
            $this->CI->db->where('pipeline_id', $dealId)->update($bridgeTable, ['is_primary' => 0]);
        }

        $now = date('Y-m-d H:i:s');
        $this->CI->db->insert($bridgeTable, [
            'pipeline_id'        => $dealId,
            'estimate_group_id'  => $estimateGroupId,
            'is_primary'         => $primaryFlag,
            'linked_by'          => $actorStaffId,
            'datecreated'        => $now,
        ]);

        $this->sync_deal($dealId);
        return true;
    }

    /**
     * Deal Bridge: Unlink an Estimate Group from a Deal.
     *
     * @param int $dealId
     * @param int $estimateGroupId
     * @return bool
     */
    public function unlink_deal_estimate_group($dealId, $estimateGroupId)
    {
        $dealId = (int) $dealId;
        $estimateGroupId = (int) $estimateGroupId;
        $bridgeTable = db_prefix() . 'sales_pipeline_deal_estimate_groups';

        if (!$this->CI->db->table_exists($bridgeTable)) {
            return false;
        }

        $row = $this->CI->db->where('pipeline_id', $dealId)->where('estimate_group_id', $estimateGroupId)->get($bridgeTable)->row_array();
        if (!$row) {
            return false;
        }

        $wasPrimary = (int) $row['is_primary'] === 1;
        $this->CI->db->where('id', $row['id'])->delete($bridgeTable);

        // If removed group was primary, assign primary to next available group
        if ($wasPrimary) {
            $nextGroup = $this->CI->db->where('pipeline_id', $dealId)->order_by('datecreated', 'asc')->get($bridgeTable)->row_array();
            if ($nextGroup) {
                $this->CI->db->where('id', $nextGroup['id'])->update($bridgeTable, ['is_primary' => 1]);
            }
        }

        // Resync the deal that lost the group
        $this->sync_deal($dealId);
        return true;
    }

    /**
     * Deal Bridge: Synchronize a Deal by an Estimate Group ID (convenience wrapper).
     *
     * @param int $estimateGroupId
     * @return bool
     */
    public function sync_deal_from_estimate_group($estimateGroupId)
    {
        $estimateGroupId = (int) $estimateGroupId;
        $bridgeTable = db_prefix() . 'sales_pipeline_deal_estimate_groups';

        if (!$this->CI->db->table_exists($bridgeTable)) {
            return false;
        }

        $bridgeRow = $this->CI->db->where('estimate_group_id', $estimateGroupId)->get($bridgeTable)->row_array();
        if (!$bridgeRow) {
            return false;
        }

        return $this->sync_deal((int) $bridgeRow['pipeline_id']);
    }

    /**
     * Deal Bridge: One-way synchronization from all linked Estimate Groups to a Deal.
     *
     * @param int $dealId
     * @return bool
     */
    public function sync_deal($dealId)
    {
        $dealId = (int) $dealId;
        $bridgeTable = db_prefix() . 'sales_pipeline_deal_estimate_groups';
        $pipelineTable = db_prefix() . 'sales_pipeline';
        $groupTable = db_prefix() . 'sales_pipeline_estimate_groups';

        if (!$this->CI->db->table_exists($bridgeTable) || !$this->CI->db->table_exists($pipelineTable) || $dealId <= 0) {
            return false;
        }

        $deal = $this->CI->db->where('id', $dealId)->get($pipelineTable)->row_array();
        if (!$deal) {
            return false;
        }

        // Query all groups linked to this Deal
        $this->CI->db->select('bg.is_primary, grp.*');
        $this->CI->db->from($bridgeTable . ' bg');
        $this->CI->db->join($groupTable . ' grp', 'grp.id = bg.estimate_group_id', 'inner');
        $this->CI->db->where('bg.pipeline_id', $dealId);
        $groups = $this->CI->db->get()->result_array();

        $primaryGroup = null;
        foreach ($groups as $group) {
            if ((int) ($group['is_primary'] ?? 0) === 1) {
                $primaryGroup = $group;
                break;
            }
        }
        if (!$primaryGroup && !empty($groups)) {
            $primaryGroup = $groups[0];
        }

        $primaryBaseTotal = null;
        if ($primaryGroup) {
            $version = $this->CI->db
                ->select('base_total')
                ->where('estimate_group_id', (int) $primaryGroup['id'])
                ->where('estimate_id', (int) $primaryGroup['current_estimate_id'])
                ->get(db_prefix() . 'sales_pipeline_estimate_versions')
                ->row_array();
            $primaryBaseTotal = $version ? $version['base_total'] : null;
        }

        $this->CI->load->library('sales_pipeline/Deal_bridge_calculator');
        $result = $this->CI->deal_bridge_calculator->calculate(
            $groups,
            $primaryBaseTotal,
            !empty($deal['is_manual_lock']),
            !empty($deal['is_finance_locked'])
        );

        if ($result['action'] === 'preserve') {
            if (in_array($result['reason'] ?? null, ['manual_lock', 'finance_locked'], true)) {
                return true;
            }
            log_message('error', 'Sales Pipeline Deal bridge preserved Deal #' . $dealId . ': ' . $result['reason']);
            return false;
        }
        if ($result['action'] === 'fail') {
            log_message('error', 'Sales Pipeline Deal bridge blocked for Deal #' . $dealId . ': ' . $result['reason']);
            return false;
        }

        $updateData = [];
        if (array_key_exists('deal_value', $result)) {
            $updateData['deal_value'] = $result['deal_value'];
        }
        if (array_key_exists('estimate_id', $result)) {
            $updateData['estimate_id'] = $result['estimate_id'];
        }

        if (($result['status_intent'] ?? null) === 'won') {
            $status = $this->CI->db->select('id')->where('is_won', 1)->order_by('order', 'asc')->get(db_prefix() . 'sales_pipeline_statuses')->row_array();
            if ($status) {
                $updateData['status'] = (int) $status['id'];
            }
        } elseif (($result['status_intent'] ?? null) === 'lost') {
            $status = $this->CI->db->select('id')->where('is_lost', 1)->order_by('order', 'asc')->get(db_prefix() . 'sales_pipeline_statuses')->row_array();
            if ($status) {
                $updateData['status'] = (int) $status['id'];
            }
        }

        if (!empty($updateData)) {
            $updateData['datemodified'] = date('Y-m-d H:i:s');
            $this->CI->db->where('id', $dealId)->update($pipelineTable, $updateData);
        }

        return true;
    }

    /**
     * Record an immutable event in tblsales_pipeline_estimate_group_events.
     *
     * @param string $eventType
     * @param int|null $estimateId
     * @param int|null $sourceEstimateId
     * @param int|null $fromGroupId
     * @param int|null $toGroupId
     * @param int|null $actorStaffId
     * @param string|null $reason
     * @param array|null $metadata
     * @return bool
     */
    public function record_event($eventType, $estimateId = null, $sourceEstimateId = null, $fromGroupId = null, $toGroupId = null, $actorStaffId = null, $reason = null, ?array $metadata = null)
    {
        $eventsTable = db_prefix() . 'sales_pipeline_estimate_group_events';
        if (!$this->CI->db->table_exists($eventsTable)) {
            return false;
        }

        $data = [
            'event_type'         => substr((string) $eventType, 0, 30),
            'estimate_id'        => $estimateId ? (int) $estimateId : null,
            'source_estimate_id' => $sourceEstimateId ? (int) $sourceEstimateId : null,
            'from_group_id'      => $fromGroupId ? (int) $fromGroupId : null,
            'to_group_id'        => $toGroupId ? (int) $toGroupId : null,
            'actor_staff_id'     => $actorStaffId ? (int) $actorStaffId : (get_staff_user_id() ? (int) get_staff_user_id() : null),
            'reason'             => $reason ? substr(trim((string) $reason), 0, 500) : null,
            'metadata_json'      => !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            'datecreated'        => date('Y-m-d H:i:s'),
        ];

        return (bool) $this->CI->db->insert($eventsTable, $data);
    }

    /**
     * Helper to retrieve snapshot values for an estimate.
     *
     * @param int $estimateId
     * @return array|null
     */
    private function get_estimate_snapshot($estimateId)
    {
        $estimate = $this->CI->db
            ->select('id, clientid, sale_agent, addedfrom, status, currency, total, datecreated, invoiced_date')
            ->where('id', (int) $estimateId)
            ->get(db_prefix() . 'estimates')
            ->row_array();

        if (!$estimate) {
            return null;
        }

        $currencyTable = db_prefix() . 'currencies';
        $baseCurrency = $this->CI->db->select('id')->where('isdefault', 1)->get($currencyTable)->row_array();
        $baseCurrencyId = $baseCurrency ? (int) $baseCurrency['id'] : 0;

        $sourceCurrencyId = (int) $estimate['currency'];
        $total = (float) $estimate['total'];
        $this->CI->load->library('sales_pipeline/Quote_currency_resolver');
        $candidateRate = null;
        if ($sourceCurrencyId !== $baseCurrencyId) {
            $candidateRate = hooks()->apply_filters('sales_pipeline_quote_exchange_rate', null, [
                'estimate_id'        => (int) $estimate['id'],
                'source_currency_id' => $sourceCurrencyId,
                'base_currency_id'   => $baseCurrencyId,
                'captured_at'        => $estimate['datecreated'],
                'rate_unit'          => Quote_currency_resolver::RATE_UNIT,
            ]);
        }
        $currencySnapshot = $this->CI->quote_currency_resolver->resolve(
            $sourceCurrencyId,
            $baseCurrencyId,
            $total,
            $candidateRate
        );
        $ownerStaffId = !empty($estimate['sale_agent']) ? (int) $estimate['sale_agent'] : ((int) $estimate['addedfrom'] ?: (get_staff_user_id() ? (int) get_staff_user_id() : 1));

        return [
            'id'               => (int) $estimate['id'],
            'clientid'         => (int) $estimate['clientid'],
            'owner_staff_id'   => $ownerStaffId,
            'status'           => (int) $estimate['status'],
            'currency'         => $sourceCurrencyId,
            'base_currency_id' => $baseCurrencyId,
            'total'            => $total,
            'exchange_rate'    => $currencySnapshot['exchange_rate_to_base'],
            'base_total'       => $currencySnapshot['base_total'],
            'datecreated'      => $estimate['datecreated'],
            'invoiced_date'    => $estimate['invoiced_date'],
        ];
    }
}
