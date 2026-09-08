<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Canonical Quote Count Repository
 * 
 * Provides a single source of truth for Quote Count across:
 * - Executive KPI Dashboard (get_staff_kpi_metrics)
 * - Performance Score Ranking (get_estimate_performance_ranking)
 * - Estimate Dashboard Metrics (get_estimate_dashboard_metrics)
 * - Reminder Rule Engine (ESTIMATE_MONTHLY_MIN_COUNT)
 * 
 * Invariants:
 * 1. 1 Estimate Group = 1 logical quote. Multiple revisions do NOT increase count.
 * 2. Draft-only groups (status = 1 only) are completely excluded.
 * 3. Group must have at least one revision with status IN (2, 3, 4, 5) (Sent, Declined, Accepted, Expired).
 * 4. Event Date: Period boundary applies to first_sent_at in [period_start, period_end_exclusive).
 */
class Quote_count_repository
{
    /**
     * @var object|null CodeIgniter instance
     */
    protected $CI;

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
     * Pure business logic: Evaluate whether an in-memory Estimate Group is eligible for a period.
     *
     * @param array $group
     * @param string $periodStart 'YYYY-MM-DD' or 'YYYY-MM-DD HH:MM:SS'
     * @param string $periodEndExclusive 'YYYY-MM-DD' or 'YYYY-MM-DD HH:MM:SS'
     * @return bool
     */
    public function evaluate_group_eligibility(array $group, $periodStart, $periodEndExclusive)
    {
        // 1. Must have a valid first_sent_at
        if (empty($group['first_sent_at'])) {
            return false;
        }

        $sentTimestamp = strtotime($group['first_sent_at']);
        $startTimestamp = strtotime($periodStart);
        $endTimestamp = strtotime($periodEndExclusive);

        // Boundary condition: [period_start, period_end_exclusive)
        if ($sentTimestamp < $startTimestamp || $sentTimestamp >= $endTimestamp) {
            return false;
        }

        // 2. Must have at least one non-draft version (status IN 2, 3, 4, 5)
        $versions = $group['versions'] ?? [];
        if (empty($versions)) {
            return false;
        }
        $hasValidVersion = false;
        foreach ($versions as $version) {
            $status = isset($version['status']) ? (int) $version['status'] : 0;
            if (in_array($status, [2, 3, 4, 5], true)) {
                $hasValidVersion = true;
                break;
            }
        }
        if (!$hasValidVersion) {
            return false;
        }

        return true;
    }

    /**
     * In-memory cohort aggregation helper.
     *
     * @param array $cohortGroups
     * @param string $periodStart
     * @param string $periodEndExclusive
     * @return array [staff_id => count]
     */
    public function aggregate_cohort_counts(array $cohortGroups, $periodStart, $periodEndExclusive)
    {
        $counts = [];
        foreach ($cohortGroups as $group) {
            if ($this->evaluate_group_eligibility($group, $periodStart, $periodEndExclusive)) {
                $staffId = (int) ($group['owner_staff_id'] ?? 0);
                if ($staffId > 0) {
                    $counts[$staffId] = ($counts[$staffId] ?? 0) + 1;
                }
            }
        }
        return $counts;
    }

    /**
     * Database-backed query for cohort quote counts.
     *
     * @param array $staffIds
     * @param string $periodStart
     * @param string $periodEndExclusive
     * @return array [staff_id => count]
     */
    public function get_counts_by_staff(array $staffIds, $periodStart, $periodEndExclusive)
    {
        $db = $this->get_db();
        if (empty($staffIds) || !$db) {
            return [];
        }

        $staffIds = array_values(array_filter(array_map('intval', $staffIds)));
        if (empty($staffIds)) {
            return [];
        }

        $prefix = db_prefix();
        $groupsTable = $prefix . 'sales_pipeline_estimate_groups';
        $versionsTable = $prefix . 'sales_pipeline_estimate_versions';
        $estimatesTable = $prefix . 'estimates';

        if (!$db->table_exists($groupsTable) || !$db->field_exists('first_sent_at', $groupsTable)) {
            return array_fill_keys($staffIds, 0);
        }

        $escapedStart = $db->escape($periodStart);
        $escapedEnd = $db->escape($periodEndExclusive);

        $sql = "
            SELECT g.owner_staff_id AS staff_id,
                   COUNT(DISTINCT g.id) AS quote_count
            FROM `{$groupsTable}` g
            WHERE g.owner_staff_id IN (" . implode(',', $staffIds) . ")
              AND g.first_sent_at >= {$escapedStart}
              AND g.first_sent_at < {$escapedEnd}
              AND EXISTS (
                  SELECT 1
                  FROM `{$versionsTable}` v
                  JOIN `{$estimatesTable}` e ON e.id = v.estimate_id
                  WHERE v.estimate_group_id = g.id
                    AND e.status IN (2, 3, 4, 5)
              )
            GROUP BY g.owner_staff_id
        ";

        $results = $db->query($sql)->result_array();
        $map = array_fill_keys($staffIds, 0);
        foreach ($results as $row) {
            $map[(int) $row['staff_id']] = (int) $row['quote_count'];
        }

        return $map;
    }

    /**
     * Database-backed query for single staff quote count.
     *
     * @param int $staffId
     * @param string $periodStart
     * @param string $periodEndExclusive
     * @return int
     */
    public function get_staff_quote_count($staffId, $periodStart, $periodEndExclusive)
    {
        $counts = $this->get_counts_by_staff([(int) $staffId], $periodStart, $periodEndExclusive);
        return $counts[(int) $staffId] ?? 0;
    }
}
