<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Performance_score_dispatcher.php';

/**
 * Performance Score Service
 *
 * Manages performance score calculations, ranking, and snapshot persistence.
 * Separates pure runtime calculation from snapshot storage to ensure idempotent GET requests.
 */
class Performance_score_service
{
    protected $CI;
    protected $dispatcher;

    public function __construct($CI = null)
    {
        if ($CI !== null) {
            $this->CI = $CI;
        } elseif (function_exists('get_instance')) {
            $this->CI = &get_instance();
        }
        $this->dispatcher = new Performance_score_dispatcher();
    }

    /**
     * Pure runtime cohort calculation and ranking.
     *
     * @param array $period ['type' => 'month', 'start' => 'YYYY-MM-DD', 'end' => 'YYYY-MM-DD']
     * @param array $cohortMetrics [staff_id => metrics_array]
     * @param array $targets
     * @param string|null $calculatedAt
     * @return array
     */
    public function calculate_runtime(array $period, array $cohortMetrics, array $targets = [], $calculatedAt = null)
    {
        $calculatedAt = $calculatedAt ?: date('Y-m-d H:i:s');
        $periodType = $period['type'] ?? 'month';
        $periodStart = $period['start'];
        $periodEnd = $period['end'];

        $formulaVersion = $this->dispatcher->resolve_formula_version($periodType, $periodStart, $periodEnd);
        $calculator = $this->dispatcher->get_calculator($formulaVersion);

        $calculatorTargets = $this->normalize_calculator_targets($targets);
        $results = [];
        foreach ($cohortMetrics as $staffId => $metrics) {
            $staffId = isset($metrics['staff_id']) ? (int) $metrics['staff_id'] : (int) $staffId;
            $metrics['closed_count'] = isset($metrics['closed_count'])
                ? (int) $metrics['closed_count']
                : (int) ($metrics['accepted_count'] ?? 0) + (int) ($metrics['declined_count'] ?? 0);
            $scoreData = $calculator->calculate_score($metrics, $calculatorTargets);
            $results[$staffId] = $this->build_leaderboard_row(
                $metrics,
                $scoreData,
                $formulaVersion,
                $calculatorTargets,
                $calculatedAt
            );
        }

        // Keep the documented stable order: score descending, then staff name.
        uasort($results, function ($a, $b) {
            if ((float) $b['ranking_score'] !== (float) $a['ranking_score']) {
                return (float) $b['ranking_score'] <=> (float) $a['ranking_score'];
            }
            return strcasecmp((string) ($a['staff_name'] ?? ''), (string) ($b['staff_name'] ?? ''));
        });

        // Standard competition ranking (1, 2, 2, 4)
        $ranked = [];
        $currentRank = 1;
        $processedCount = 0;
        $prevScore = null;

        foreach ($results as $staffId => $row) {
            $processedCount++;
            $score = (float) $row['ranking_score'];

            if ($prevScore !== null && $score < $prevScore) {
                $currentRank = $processedCount;
            }
            $prevScore = $score;

            $row['rank'] = $currentRank;
            $row['total_ranked_staff'] = count($results);
            $ranked[$staffId] = $row;
        }

        return [
            'formula_version' => $formulaVersion,
            'period'          => $period,
            'calculated_at'   => $calculatedAt,
            'cohort'          => $ranked,
        ];
    }

    /**
     * Translate the Model/config vocabulary to the immutable calculator contract.
     *
     * @param array $targets
     * @return array
     */
    private function normalize_calculator_targets(array $targets)
    {
        return [
            'target_quotes'       => $targets['target_quotes'] ?? $targets['quote_target'] ?? 30,
            'target_revenue'      => $targets['target_revenue'] ?? $targets['revenue_target'] ?? 100000000,
            'target_acceptance'   => $targets['target_acceptance'] ?? $targets['acceptance_target'] ?? 40,
            'target_response_sla' => $targets['target_response_sla'] ?? $targets['response_target'] ?? 90,
            'min_closed_quotes'   => $targets['min_closed_quotes'] ?? 5,
        ];
    }

    /**
     * Adapt a versioned calculator result to the existing Dashboard contract.
     * Calculator component scores are weighted points; Dashboard component scores
     * remain normalized percentages so existing views do not change semantics.
     *
     * @param array  $metrics
     * @param array  $scoreData
     * @param string $formulaVersion
     * @param array  $targets
     * @param string $calculatedAt
     * @return array
     */
    private function build_leaderboard_row(array $metrics, array $scoreData, $formulaVersion, array $targets, $calculatedAt)
    {
        $components = $scoreData['components'] ?? [];
        $eligibleReminders = array_key_exists('eligible_reminders', $metrics) && $metrics['eligible_reminders'] !== null
            ? (int) $metrics['eligible_reminders']
            : null;
        $onTimeReminders = array_key_exists('on_time_reminders', $metrics) && $metrics['on_time_reminders'] !== null
            ? (int) $metrics['on_time_reminders']
            : null;
        $reminderStatus = 'inactive';
        if ($formulaVersion === 'performance_score_v2' && $eligibleReminders !== null) {
            $reminderStatus = $eligibleReminders > 0 ? 'active' : 'not_applicable';
        }

        $closedCount = (int) ($metrics['closed_count'] ?? 0);
        $acceptedCount = (int) ($metrics['accepted_count'] ?? 0);
        $acceptanceRate = $closedCount > 0 ? ($acceptedCount / $closedCount) * 100.0 : null;
        $onTimeRate = $eligibleReminders > 0 ? ((int) $onTimeReminders / $eligibleReminders) * 100.0 : null;

        $normalizedComponent = function ($componentKey) use ($components) {
            if (!isset($components[$componentKey])) {
                return null;
            }
            $weight = (float) ($components[$componentKey]['weight'] ?? 0);
            if ($weight <= 0) {
                return null;
            }
            return round(((float) ($components[$componentKey]['score'] ?? 0) / $weight) * 100.0, 4);
        };

        $effectiveDenominator = (float) ($scoreData['effective_denominator'] ?? 0);
        $effectiveWeights = [
            'quote_score'            => $effectiveDenominator > 0 ? round((20.0 / $effectiveDenominator) * 100.0, 4) : 0.0,
            'accepted_revenue_score' => $effectiveDenominator > 0 ? round((40.0 / $effectiveDenominator) * 100.0, 4) : 0.0,
            'acceptance_score'       => $effectiveDenominator > 0 ? round((25.0 / $effectiveDenominator) * 100.0, 4) : 0.0,
            'reminder_response'      => $reminderStatus === 'active' && $effectiveDenominator > 0
                ? round((15.0 / $effectiveDenominator) * 100.0, 4)
                : 0.0,
        ];

        $flags = $scoreData['data_quality_flags'] ?? [];
        if (!empty($metrics['missing_revenue_rate_count'])) {
            $flags[] = 'missing_revenue_rate';
        }
        $flags = array_values(array_unique($flags));

        $row = array_merge($metrics, [
            'staff_id'                   => (int) ($metrics['staff_id'] ?? 0),
            'closed_count'               => $closedCount,
            'acceptance_rate'            => $acceptanceRate === null ? null : round($acceptanceRate, 1),
            'quote_score'                => $normalizedComponent('quote_count'),
            'accepted_revenue_score'     => $normalizedComponent('accepted_revenue'),
            'acceptance_score'           => $normalizedComponent('acceptance_rate'),
            'eligible_reminders'         => $eligibleReminders,
            'on_time_reminders'          => $onTimeReminders,
            'on_time_rate'               => $onTimeRate === null ? null : round($onTimeRate, 1),
            'response_score'             => $normalizedComponent('reminder_response'),
            'component_status'           => ['reminder_response' => $reminderStatus],
            'effective_weights'          => $effectiveWeights,
            'performance_score_raw'      => (float) $scoreData['performance_score_raw'],
            'performance_score'          => (float) $scoreData['performance_score'],
            'ranking_score'              => (float) $scoreData['performance_score'],
            'is_provisional'             => !empty($scoreData['is_provisional']) || !empty($flags),
            'data_quality_flags'         => $flags,
            'components'                 => $components,
            'raw_metrics'                => $metrics,
            'config'                     => $targets,
            'formula_version'            => $formulaVersion,
            'calculated_at'              => $calculatedAt,
        ]);

        return $row;
    }

    /**
     * Reconstruct legacy V1 scores using legacy target defaults and immutable V1 formula.
     *
     * @param array $period
     * @param array $cohortMetrics
     * @return array
     */
    public function reconstruct_legacy_v1(array $period, array $cohortMetrics)
    {
        $calculator = $this->dispatcher->get_calculator('performance_score_v1');
        $periodType = $period['type'] ?? 'month';

        // Legacy targets: Month=20, Quarter=60, Year=240, Week=5
        $legacyTargets = [
            'quote_target' => $periodType === 'quarter' ? 60 : ($periodType === 'year' ? 240 : ($periodType === 'week' ? 5 : 20)),
        ];

        $runtime = $this->calculate_runtime($period, $cohortMetrics, $legacyTargets);
        $cohort = [];

        foreach ($runtime['cohort'] as $staffId => $row) {
            $row['snapshot_status'] = 'reconstructed';
            $row['is_provisional'] = false;
            $cohort[$staffId] = $row;
        }

        $runtime['formula_version'] = 'performance_score_v1';
        $runtime['cohort'] = $cohort;
        return $runtime;
    }

    /**
     * Persist provisional snapshot for in-progress period.
     *
     * @param array $period
     * @param array $cohortResult
     * @return bool
     */
    public function persist_provisional(array $period, array $cohortResult)
    {
        if (!$this->CI || empty($this->CI->db)) {
            return false;
        }

        $table = db_prefix() . 'sales_pipeline_score_snapshots';
        if (!$this->CI->db->table_exists($table)) {
            return false;
        }

        $periodType = $period['type'] ?? 'month';
        $periodStart = $period['start'];
        $periodEnd = $period['end'];
        $formulaVersion = $cohortResult['formula_version'] ?? 'performance_score_v2';
        $now = date('Y-m-d H:i:s');

        $this->CI->db->trans_start();

        foreach ($cohortResult['cohort'] as $staffId => $row) {
            $data = [
                'period_type'               => $periodType,
                'period_start'              => $periodStart,
                'period_end'                => $periodEnd,
                'staff_id'                  => (int) $staffId,
                'formula_version'           => $formulaVersion,
                'performance_score'         => $row['performance_score'],
                'performance_score_raw'     => $row['performance_score_raw'] ?? $row['performance_score'],
                'rank'                      => (int) $row['rank'],
                'total_ranked_staff'        => (int) $row['total_ranked_staff'],
                'is_provisional'            => 1,
                'snapshot_status'           => 'provisional',
                'component_scores_json'     => json_encode($row['components'] ?? []),
                'raw_metrics_json'          => json_encode($row['raw_metrics'] ?? []),
                'config_snapshot_json'      => json_encode($row['config'] ?? []),
                'data_quality_flags_json'   => json_encode($row['data_quality_flags'] ?? []),
                'calculated_at'             => $now,
            ];

            // Upsert provisional snapshot
            $existing = $this->CI->db
                ->select('id, snapshot_status')
                ->from($table)
                ->where('period_type', $periodType)
                ->where('period_start', $periodStart)
                ->where('period_end', $periodEnd)
                ->where('staff_id', (int) $staffId)
                ->get()
                ->row_array();

            if ($existing) {
                if ($existing['snapshot_status'] === 'finalized') {
                    // Finalized snapshots are immutable!
                    continue;
                }
                $this->CI->db->where('id', (int) $existing['id'])->update($table, $data);
            } else {
                $this->CI->db->insert($table, $data);
            }
        }

        $this->CI->db->trans_complete();
        return $this->CI->db->trans_status();
    }

    /**
     * Finalize period snapshot in one transaction. Finalized snapshots are strictly immutable.
     *
     * @param array $period
     * @param array $cohortResult
     * @param int $actorStaffId
     * @param string $reason
     * @return array ['success' => bool, 'status' => string, 'message' => string]
     */
    public function finalize_period(array $period, array $cohortResult, $actorStaffId, $reason = '')
    {
        if (!$this->CI || empty($this->CI->db)) {
            return ['success' => false, 'status' => 'no_db', 'message' => 'Database connection not available'];
        }

        $table = db_prefix() . 'sales_pipeline_score_snapshots';
        if (!$this->CI->db->table_exists($table)) {
            return ['success' => false, 'status' => 'no_table', 'message' => 'Score snapshots table does not exist'];
        }

        $periodType = $period['type'] ?? 'month';
        $periodStart = $period['start'];
        $periodEnd = $period['end'];
        $formulaVersion = $cohortResult['formula_version'] ?? 'performance_score_v2';

        // Check if already finalized
        $alreadyFinalized = $this->CI->db
            ->from($table)
            ->where('period_type', $periodType)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->where('snapshot_status', 'finalized')
            ->count_all_results();

        if ($alreadyFinalized > 0) {
            return [
                'success' => false,
                'status'  => 'already_finalized',
                'message' => 'Period has already been finalized and is immutable',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $runId = uniqid('final_', true);

        $this->CI->db->trans_start();

        // Delete any existing provisional rows for this period
        $this->CI->db
            ->where('period_type', $periodType)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->where('snapshot_status', 'provisional')
            ->delete($table);

        foreach ($cohortResult['cohort'] as $staffId => $row) {
            $this->CI->db->insert($table, [
                'snapshot_run_id'           => $runId,
                'period_type'               => $periodType,
                'period_start'              => $periodStart,
                'period_end'                => $periodEnd,
                'staff_id'                  => (int) $staffId,
                'formula_version'           => $formulaVersion,
                'performance_score'         => $row['performance_score'],
                'performance_score_raw'     => $row['performance_score_raw'] ?? $row['performance_score'],
                'rank'                      => (int) $row['rank'],
                'total_ranked_staff'        => (int) $row['total_ranked_staff'],
                'is_provisional'            => 0,
                'snapshot_status'           => 'finalized',
                'component_scores_json'     => json_encode($row['components'] ?? []),
                'raw_metrics_json'          => json_encode($row['raw_metrics'] ?? []),
                'config_snapshot_json'      => json_encode($row['config'] ?? []),
                'data_quality_flags_json'   => json_encode($row['data_quality_flags'] ?? []),
                'calculated_at'             => $now,
                'finalized_at'              => $now,
                'finalized_by'              => (int) $actorStaffId,
                'finalized_reason'          => $reason,
            ]);
        }

        $this->CI->db->trans_complete();

        if (!$this->CI->db->trans_status()) {
            return ['success' => false, 'status' => 'trans_fail', 'message' => 'Transaction rollback'];
        }

        return [
            'success'         => true,
            'status'          => 'finalized',
            'snapshot_run_id' => $runId,
            'finalized_at'    => $now,
        ];
    }

    /**
     * Read snapshot from database.
     *
     * @param string $periodType
     * @param string $periodStart
     * @param string $periodEnd
     * @param string|null $formulaVersion
     * @return array|null
     */
    public function read_snapshot($periodType, $periodStart, $periodEnd, $formulaVersion = null)
    {
        if (!$this->CI || empty($this->CI->db)) {
            return null;
        }

        $formulaVersion = $formulaVersion ?: $this->dispatcher->resolve_formula_version($periodType, $periodStart, $periodEnd);
        $table = db_prefix() . 'sales_pipeline_score_snapshots';

        if (!$this->CI->db->table_exists($table)) {
            return null;
        }

        $rows = $this->CI->db
            ->from($table)
            ->where('period_type', $periodType)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->where('formula_version', $formulaVersion)
            ->get()
            ->result_array();

        if (empty($rows)) {
            return null;
        }

        $cohort = [];
        foreach ($rows as $r) {
            $cohort[(int) $r['staff_id']] = [
                'staff_id'            => (int) $r['staff_id'],
                'performance_score'   => (float) $r['performance_score'],
                'rank'                => (int) $r['rank'],
                'total_ranked_staff'  => (int) $r['total_ranked_staff'],
                'is_provisional'      => (bool) $r['is_provisional'],
                'snapshot_status'     => $r['snapshot_status'],
                'components'          => json_decode($r['component_scores_json'] ?? '[]', true),
                'raw_metrics'         => json_decode($r['raw_metrics_json'] ?? '[]', true),
                'data_quality_flags'  => json_decode($r['data_quality_flags_json'] ?? '[]', true),
                'finalized_at'        => $r['finalized_at'],
            ];
        }

        return [
            'formula_version' => $formulaVersion,
            'period_type'     => $periodType,
            'period_start'    => $periodStart,
            'period_end'      => $periodEnd,
            'cohort'          => $cohort,
        ];
    }
}
