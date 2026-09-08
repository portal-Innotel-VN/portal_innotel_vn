<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Canonical First-Sent Event Capture Service
 *
 * Implements strict Evidence-Rank Decision Matrix and Atomic Earliest-Timestamp Invariants
 * for Canonical Quote Count across Perfex CRM write-paths.
 */
class Quote_first_sent_service
{
    /**
     * @var object|null CodeIgniter instance
     */
    protected $CI;

    /**
     * Source rank mapping (higher integer = stronger authoritative evidence).
     */
    const RANK_ACTIVITY_EMAIL_SENT = 4;
    const RANK_ESTIMATE_DATESEND   = 3;
    const RANK_ACTIVITY_STATUS_SENT= 2;
    const RANK_INFERRED_LEGACY     = 1;
    const RANK_NONE                = 0;

    public function __construct($CI = null)
    {
        if ($CI !== null) {
            $this->CI = $CI;
        } elseif (function_exists('get_instance')) {
            $this->CI = &get_instance();
        }
    }

    /**
     * Resolve database instance safely regardless of whether $this->CI is controller, model, or mock.
     * Avoids PHP empty($this->CI->db) false positive on CI_Model which lacks __isset().
     *
     * @return object|null
     */
    protected function get_db()
    {
        if (is_object($this->CI)) {
            try {
                $db = $this->CI->db;
                if ($db) {
                    return $db;
                }
            } catch (Throwable $e) {}
        }

        if (function_exists('get_instance')) {
            $ci = &get_instance();
            if (is_object($ci)) {
                try {
                    $db = $ci->db;
                    if ($db) {
                        return $db;
                    }
                } catch (Throwable $e) {}
            }
        }

        return null;
    }

    /**
     * Resolve numeric rank for an evidence source string.
     *
     * @param string|null $source
     * @return int
     */
    public function get_source_rank($source)
    {
        if (empty($source) || !is_string($source)) {
            return self::RANK_NONE;
        }

        switch ($source) {
            case 'activity_email_sent':
                return self::RANK_ACTIVITY_EMAIL_SENT;
            case 'estimate_datesend':
                return self::RANK_ESTIMATE_DATESEND;
            case 'activity_status_sent':
            case 'estimate_accepted':
            case 'estimate_declined':
            case 'estimate_expired':
                return self::RANK_ACTIVITY_STATUS_SENT;
            case 'inferred_estimate_date':
            case 'legacy_inferred_estimate_date':
                return self::RANK_INFERRED_LEGACY;
            default:
                if (strpos($source, 'legacy_inferred_') === 0 || strpos($source, 'inferred_') === 0) {
                    return self::RANK_INFERRED_LEGACY;
                }
                return self::RANK_NONE;
        }
    }

    /**
     * Evidence-Rank Decision Matrix: Evaluates whether candidate evidence replaces current evidence.
     *
     * Invariants:
     * 1. Null current -> any candidate with rank >= 1 replaces.
     * 2. Inferred current (Rank 1) -> Real evidence (Rank >= 2) trumps inferred even if timestamp is later.
     * 3. Real evidence current (Rank >= 2) -> Stronger rank replaces if timestamp is valid (earlier or equal).
     * 4. Equal real rank -> keep earliest timestamp (earliest-timestamp invariant).
     * 5. Weaker evidence cannot replace stronger evidence.
     *
     * @param string|null $currentSource
     * @param string|null $currentTimestamp
     * @param string|null $candidateSource
     * @param string|null $candidateTimestamp
     * @return bool True if candidate should replace current
     */
    public function evaluate_evidence_transition($currentSource, $currentTimestamp, $candidateSource, $candidateTimestamp)
    {
        $currentRank = $this->get_source_rank($currentSource);
        $candidateRank = $this->get_source_rank($candidateSource);

        if ($candidateRank <= self::RANK_NONE || empty($candidateTimestamp)) {
            return false;
        }

        $candidateTime = strtotime($candidateTimestamp);
        if ($candidateTime === false) {
            return false;
        }

        // Case 1: Current is empty / NULL
        if ($currentRank === self::RANK_NONE || empty($currentTimestamp)) {
            return true;
        }

        $currentTime = strtotime($currentTimestamp);
        if ($currentTime === false) {
            return true;
        }

        // Case 2: Current is Inferred / Legacy (Rank 1)
        if ($currentRank === self::RANK_INFERRED_LEGACY) {
            // Real evidence (Rank >= 2) always replaces inferred evidence
            if ($candidateRank >= self::RANK_ACTIVITY_STATUS_SENT) {
                return true;
            }
            // Between two inferred: keep earliest
            if ($candidateRank === self::RANK_INFERRED_LEGACY) {
                return $candidateTime < $currentTime;
            }
            return false;
        }

        // Case 3: Current already has Real Evidence (Rank >= 2)
        if ($candidateRank > $currentRank) {
            // Stronger evidence replaces if timestamp is earlier or equal
            return $candidateTime <= $currentTime;
        }

        if ($candidateRank === $currentRank) {
            // Equal rank: earliest timestamp invariant
            return $candidateTime < $currentTime;
        }

        // candidateRank < currentRank: Weaker cannot overwrite stronger
        return false;
    }

    /**
     * Safely parse activity record to detect status 2 transition without executing untrusted objects.
     *
     * @param string $description
     * @param string|null $additionalData Serialized array from Core Perfex log_estimate_activity
     * @return int|null 2 if status 2 transition matched, null otherwise
     */
    public function parse_activity_status_evidence($description, $additionalData)
    {
        if (empty($description)) {
            return null;
        }

        // Check if raw payload contains XML tags
        if (!empty($additionalData) && is_string($additionalData)) {
            // Try secure unserialize with allowed_classes = false
            if (strpos($additionalData, 'a:') === 0) {
                $unserialized = @unserialize($additionalData, ['allowed_classes' => false]);
                if (is_array($unserialized)) {
                    foreach ($unserialized as $item) {
                        if (is_string($item)) {
                            if (preg_match('/<new_status>2<\/new_status>/', $item)
                                || preg_match('/<status>2<\/status>/', $item)) {
                                return 2;
                            }
                        }
                    }
                }
            }

            // Fallback direct regex on serialized string
            if (preg_match('/<new_status>2<\/new_status>/', $additionalData)
                || preg_match('/<status>2<\/status>/', $additionalData)) {
                return 2;
            }
        }

        return null;
    }

    /**
     * Extract strongest evidence from estimate array and optional activity rows.
     *
     * @param array $estimate
     * @param array|null $activities
     * @return array ['source' => string|null, 'occurred_at' => string|null]
     */
    public function extract_estimate_evidence(array $estimate, ?array $activities = null)
    {
        // 1. Priority 1: Activity email sent
        if (!empty($activities)) {
            foreach ($activities as $act) {
                if (($act['description'] ?? '') === 'invoice_estimate_activity_sent_to_client'
                    && !empty($act['date'])) {
                    return [
                        'source'      => 'activity_email_sent',
                        'occurred_at' => $this->normalize_timestamp($act['date']),
                    ];
                }
            }
        }

        // 2. Priority 2: Core datesend with sent = 1
        if (!empty($estimate['sent']) && (int) $estimate['sent'] === 1 && !empty($estimate['datesend'])) {
            return [
                'source'      => 'estimate_datesend',
                'occurred_at' => $this->normalize_timestamp($estimate['datesend']),
            ];
        }

        // 3. Priority 3: Activity status 2 transition
        if (!empty($activities)) {
            foreach ($activities as $act) {
                $status = $this->parse_activity_status_evidence(
                    $act['description'] ?? '',
                    $act['additional_data'] ?? null
                );
                if ($status === 2 && !empty($act['date'])) {
                    return [
                        'source'      => 'activity_status_sent',
                        'occurred_at' => $this->normalize_timestamp($act['date']),
                    ];
                }
            }
        }

        // 4. Priority 4: Finalized non-draft outcomes (Accepted, Converted to Invoice, Declined, Expired)
        // A quote that was accepted, invoiced, or declined was delivered to the client.
        $status = (int) ($estimate['status'] ?? 0);
        $hasInvoice = !empty($estimate['invoiceid']) || !empty($estimate['invoiced_date']);

        if ($hasInvoice || $status === 4) {
            $occurredAt = !empty($estimate['datecreated'])
                ? $estimate['datecreated']
                : (!empty($estimate['invoiced_date']) ? $estimate['invoiced_date'] : ($estimate['date'] ?? null));
            if (!empty($occurredAt)) {
                return [
                    'source'      => 'estimate_accepted',
                    'occurred_at' => $this->normalize_timestamp($occurredAt),
                ];
            }
        }

        if ($status === 3) {
            $occurredAt = !empty($estimate['datecreated']) ? $estimate['datecreated'] : ($estimate['date'] ?? null);
            if (!empty($occurredAt)) {
                return [
                    'source'      => 'estimate_declined',
                    'occurred_at' => $this->normalize_timestamp($occurredAt),
                ];
            }
        }

        if ($status === 5) {
            $occurredAt = !empty($estimate['datecreated']) ? $estimate['datecreated'] : ($estimate['date'] ?? null);
            if (!empty($occurredAt)) {
                return [
                    'source'      => 'estimate_expired',
                    'occurred_at' => $this->normalize_timestamp($occurredAt),
                ];
            }
        }

        // Draft-only (status 1, sent 0, datesend null): no evidence
        return [
            'source'      => null,
            'occurred_at' => null,
        ];
    }

    /**
     * Normalize timestamp to YYYY-MM-DD HH:MM:SS in application timezone (Asia/Ho_Chi_Minh).
     *
     * @param string|int $rawTime
     * @return string
     */
    public function normalize_timestamp($rawTime)
    {
        $tz = new DateTimeZone('Asia/Ho_Chi_Minh');
        if (is_numeric($rawTime)) {
            $dt = new DateTime('@' . $rawTime);
            $dt->setTimezone($tz);
            return $dt->format('Y-m-d H:i:s');
        }

        try {
            $dt = new DateTime((string) $rawTime, $tz);
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return date('Y-m-d H:i:s', strtotime((string) $rawTime));
        }
    }

    /**
     * Primary Capture Entry Point: Resolves group and applies atomic update.
     *
     * @param int $estimateId
     * @param string $occurredAt
     * @param string $source
     * @return array
     */
    public function capture($estimateId, $occurredAt, $source)
    {
        $estimateId = (int) $estimateId;
        if ($estimateId <= 0 || empty($occurredAt) || empty($source)) {
            return [
                'status'  => 'error',
                'message' => 'Invalid parameters for capture',
            ];
        }

        $db = $this->get_db();
        if (!$db) {
            return [
                'status'  => 'error',
                'message' => 'Database connection unavailable',
            ];
        }

        $prefix = db_prefix();
        $versionsTable = $prefix . 'sales_pipeline_estimate_versions';
        $groupsTable = $prefix . 'sales_pipeline_estimate_groups';

        // 1. Resolve Group ID from Version record (Fail-closed: do not silently create group)
        $version = $db
            ->select('estimate_group_id')
            ->where('estimate_id', $estimateId)
            ->get($versionsTable)
            ->row_array();

        if (!$version || empty($version['estimate_group_id'])) {
            return [
                'status'      => 'group_missing',
                'estimate_id' => $estimateId,
                'message'     => 'No estimate group linked to estimate',
            ];
        }

        $groupId = (int) $version['estimate_group_id'];

        // 2. Fetch current group first_sent_* state
        $group = $db
            ->select('id, first_sent_at, first_sent_source, first_sent_estimate_id')
            ->where('id', $groupId)
            ->get($groupsTable)
            ->row_array();

        if (!$group) {
            return [
                'status'      => 'group_missing',
                'estimate_id' => $estimateId,
                'message'     => 'Group row not found',
            ];
        }

        $candidateAt = $this->normalize_timestamp($occurredAt);

        // 3. Evaluate transition against Evidence-Rank Matrix
        $shouldReplace = $this->evaluate_evidence_transition(
            $group['first_sent_source'] ?? null,
            $group['first_sent_at'] ?? null,
            $source,
            $candidateAt
        );

        if (!$shouldReplace) {
            return [
                'status'                => 'unchanged',
                'group_id'              => $groupId,
                'estimate_id'           => $estimateId,
                'current_first_sent_at' => $group['first_sent_at'],
                'current_source'        => $group['first_sent_source'],
            ];
        }

        // 4. Atomic conditional update avoiding sequential assignment pitfall
        $escapedAt = $db->escape($candidateAt);
        $escapedSource = $db->escape($source);
        $escapedEstimateId = (int) $estimateId;

        $sql = "
            UPDATE `{$groupsTable}`
            SET `first_sent_estimate_id` = {$escapedEstimateId},
                `first_sent_source` = {$escapedSource},
                `first_sent_at` = {$escapedAt}
            WHERE `id` = {$groupId}
        ";

        $db->query($sql);

        return [
            'status'        => 'captured',
            'group_id'      => $groupId,
            'estimate_id'   => $estimateId,
            'first_sent_at' => $candidateAt,
            'source'        => $source,
        ];
    }

    /**
     * Convenience entry point: Read estimate record and activities from DB, then capture.
     *
     * @param int $estimateId
     * @param string|null $sourceHint Optional hint e.g. 'activity_email_sent'
     * @return array
     */
    public function captureFromCurrentEstimate($estimateId, $sourceHint = null)
    {
        $estimateId = (int) $estimateId;
        $db = $this->get_db();
        if ($estimateId <= 0 || !$db) {
            return [
                'status'  => 'error',
                'message' => 'Invalid estimate ID or DB unavailable',
            ];
        }

        $prefix = db_prefix();
        $estimate = $db
            ->select('id, status, sent, datesend, date, datecreated, invoiceid, invoiced_date')
            ->where('id', $estimateId)
            ->get($prefix . 'estimates')
            ->row_array();

        if (!$estimate) {
            return [
                'status'      => 'no_evidence',
                'estimate_id' => $estimateId,
                'message'     => 'Estimate record not found',
            ];
        }

        $activities = [];
        if ($db->table_exists($prefix . 'sales_activity')) {
            $activities = $db
                ->where('rel_id', $estimateId)
                ->where('rel_type', 'estimate')
                ->order_by('date', 'ASC')
                ->get($prefix . 'sales_activity')
                ->result_array();
        }

        $evidence = $this->extract_estimate_evidence($estimate, $activities);

        if (empty($evidence['source']) || empty($evidence['occurred_at'])) {
            return [
                'status'      => 'no_evidence',
                'estimate_id' => $estimateId,
                'message'     => 'Estimate has no sent evidence',
            ];
        }

        $source = $sourceHint ?: $evidence['source'];
        return $this->capture($estimateId, $evidence['occurred_at'], $source);
    }
}
