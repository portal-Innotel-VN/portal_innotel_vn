<?php

/**
 * Pure calculator for performance_score_v1.
 *
 * This class deliberately has no CodeIgniter dependencies so the scoring and
 * projection rules can be tested without bootstrapping the application.
 */
class Performance_score_calculator
{
    const FORMULA_VERSION = 'performance_score_v1';

    private $standard_weights = [
        'quote_score'            => 20.0,
        'accepted_revenue_score' => 40.0,
        'acceptance_score'       => 25.0,
        'reminder_response'      => 15.0,
    ];

    /**
     * Calculate and rank an already-aggregated cohort.
     *
     * @param array $metrics
     * @param array $config
     * @return array
     */
    public function calculate_leaderboard($metrics, $config)
    {
        $configuration_errors = $this->validate_config($config);
        $total_ranked_staff = count($metrics);

        if ($configuration_errors) {
            $rows = [];
            foreach ($metrics as $metric) {
                $metric['performance_score_raw'] = null;
                $metric['performance_score'] = null;
                $metric['ranking_score'] = null;
                $metric['rank'] = null;
                $metric['total_ranked_staff'] = $total_ranked_staff;
                $metric['is_provisional'] = true;
                $metric['data_quality_flags'] = ['not_configured'];
                $rows[] = $metric;
            }

            usort($rows, [$this, 'compare_names']);

            return [
                'formula_version'     => isset($config['formula_version']) ? $config['formula_version'] : self::FORMULA_VERSION,
                'status'              => 'not_configured',
                'configuration_errors'=> $configuration_errors,
                'leaderboard'         => $rows,
            ];
        }

        $cap = (float) $config['component_cap'];
        $active_weight_total = $this->standard_weights['quote_score']
            + $this->standard_weights['accepted_revenue_score']
            + $this->standard_weights['acceptance_score'];
        $effective_weights = [
            'quote_score'            => round(($this->standard_weights['quote_score'] / $active_weight_total) * 100, 4),
            'accepted_revenue_score' => round(($this->standard_weights['accepted_revenue_score'] / $active_weight_total) * 100, 4),
            'acceptance_score'       => round(($this->standard_weights['acceptance_score'] / $active_weight_total) * 100, 4),
        ];

        $rows = [];
        foreach ($metrics as $metric) {
            $estimate_count = isset($metric['estimate_count']) ? (int) $metric['estimate_count'] : 0;
            $accepted_revenue = isset($metric['accepted_revenue']) ? (float) $metric['accepted_revenue'] : 0.0;
            $accepted_count = isset($metric['accepted_count']) ? (int) $metric['accepted_count'] : 0;
            $declined_count = isset($metric['declined_count']) ? (int) $metric['declined_count'] : 0;
            $closed_count = $accepted_count + $declined_count;
            $acceptance_rate = $closed_count > 0
                ? ($accepted_count / $closed_count) * 100
                : 0.0;

            $quote_score = $this->component_score($estimate_count, $config['quote_target'], $cap);
            $accepted_revenue_score = $this->component_score(
                $accepted_revenue,
                $config['revenue_target'],
                $cap
            );
            $acceptance_score = $this->component_score(
                $acceptance_rate,
                $config['acceptance_target'],
                $cap
            );

            $performance_score_raw = (
                ($quote_score * $this->standard_weights['quote_score'])
                + ($accepted_revenue_score * $this->standard_weights['accepted_revenue_score'])
                + ($acceptance_score * $this->standard_weights['acceptance_score'])
            ) / $active_weight_total;

            $data_quality_flags = [];
            if (!empty($metric['missing_revenue_rate_count'])) {
                $data_quality_flags[] = 'missing_revenue_rate';
            }
            if ($closed_count < (int) $config['min_closed_quotes']) {
                $data_quality_flags[] = 'insufficient_closed_quotes';
            }

            $metric['closed_count'] = $closed_count;
            $metric['acceptance_rate'] = $closed_count > 0 ? round($acceptance_rate, 1) : null;
            $metric['quote_score'] = round($quote_score, 4);
            $metric['accepted_revenue_score'] = round($accepted_revenue_score, 4);
            $metric['acceptance_score'] = round($acceptance_score, 4);
            $metric['response_score'] = null;
            $metric['component_status'] = ['reminder_response' => 'inactive'];
            $metric['effective_weights'] = $effective_weights;
            $metric['performance_score_raw'] = round($performance_score_raw, 4);
            $metric['performance_score'] = round($performance_score_raw, 1);
            $metric['ranking_score'] = $metric['performance_score'];
            $metric['is_provisional'] = !empty($data_quality_flags);
            $metric['data_quality_flags'] = $data_quality_flags;
            $rows[] = $metric;
        }

        usort($rows, [$this, 'compare_scores']);

        $previous_score = null;
        $previous_rank = null;
        foreach ($rows as $index => &$row) {
            $score = (float) $row['ranking_score'];
            if ($previous_score !== null && $score === $previous_score) {
                $row['rank'] = $previous_rank;
            } else {
                $row['rank'] = $index + 1;
                $previous_rank = $row['rank'];
                $previous_score = $score;
            }
            $row['total_ranked_staff'] = $total_ranked_staff;
        }
        unset($row);

        return [
            'formula_version'      => isset($config['formula_version']) ? $config['formula_version'] : self::FORMULA_VERSION,
            'status'               => 'ready',
            'configuration_errors' => [],
            'leaderboard'          => $rows,
        ];
    }

    /**
     * Enforce the role-aware response projection after the full cohort is ranked.
     *
     * @param array $leaderboard
     * @param bool  $can_view_all
     * @param int   $current_staff_id
     * @return array
     */
    public function project_leaderboard($leaderboard, $can_view_all, $current_staff_id)
    {
        $current_staff_id = (int) $current_staff_id;
        if ($can_view_all) {
            foreach ($leaderboard as &$row) {
                $row['is_current_staff'] = (int) $row['staff_id'] === $current_staff_id;
                $row['can_open_details'] = true;
            }
            unset($row);

            return array_values($leaderboard);
        }

        $allowed_fields = [
            'staff_id',
            'staff_name',
            'estimate_count',
            'accepted_revenue',
            'acceptance_rate',
            'performance_score',
            'rank',
            'total_ranked_staff',
            'is_provisional',
        ];

        foreach ($leaderboard as $row) {
            if ((int) $row['staff_id'] !== $current_staff_id) {
                continue;
            }

            $projected = [];
            foreach ($allowed_fields as $field) {
                if (array_key_exists($field, $row)) {
                    $projected[$field] = $row[$field];
                }
            }
            $projected['is_current_staff'] = true;
            $projected['can_open_details'] = true;

            return [$projected];
        }

        return [];
    }

    private function component_score($actual, $target, $cap)
    {
        $score = ((float) $actual / (float) $target) * 100;

        return max(0.0, min((float) $cap, $score));
    }

    private function validate_config($config)
    {
        $errors = [];
        foreach (['quote_target', 'revenue_target', 'acceptance_target', 'component_cap'] as $key) {
            if (!isset($config[$key]) || !is_numeric($config[$key]) || (float) $config[$key] <= 0) {
                $errors[] = $key;
            }
        }
        if (!isset($config['min_closed_quotes']) || !is_numeric($config['min_closed_quotes']) || (int) $config['min_closed_quotes'] < 0) {
            $errors[] = 'min_closed_quotes';
        }

        return $errors;
    }

    private function compare_scores($first, $second)
    {
        if ((float) $first['ranking_score'] === (float) $second['ranking_score']) {
            return $this->compare_names($first, $second);
        }

        return (float) $second['ranking_score'] <=> (float) $first['ranking_score'];
    }

    private function compare_names($first, $second)
    {
        return strcasecmp((string) $first['staff_name'], (string) $second['staff_name']);
    }
}
