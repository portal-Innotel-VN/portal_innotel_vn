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

    protected $standard_weights = [
        'quote_score'            => 20.0,
        'accepted_revenue_score' => 40.0,
        'acceptance_score'       => 25.0,
        'reminder_response'      => 15.0,
    ];

    public function get_standard_weights()
    {
        return $this->standard_weights;
    }

    public function set_standard_weights(array $weights)
    {
        $this->standard_weights = $weights;
    }

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
        $calculated_at = isset($config['calculated_at']) ? $config['calculated_at'] : date('Y-m-d H:i:s');

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
                'calculated_at'        => $calculated_at,
                'status'              => 'not_configured',
                'configuration_errors'=> $configuration_errors,
                'leaderboard'         => $rows,
            ];
        }

        $cap = (float) $config['component_cap'];

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

            // Determine reminder_response component status and score
            $eligible_reminders = isset($metric['eligible_reminders']) && $metric['eligible_reminders'] !== null
                ? (int) $metric['eligible_reminders']
                : null;
            $on_time_reminders = isset($metric['on_time_reminders']) && $metric['on_time_reminders'] !== null
                ? (int) $metric['on_time_reminders']
                : null;

            $on_time_rate = null;
            $response_score = null;

            if ($eligible_reminders === null) {
                $reminder_status = 'inactive';
            } elseif ($eligible_reminders === 0) {
                $reminder_status = 'not_applicable';
            } else {
                $reminder_status = 'active';
                $on_time_rate = ($on_time_reminders / $eligible_reminders) * 100;
                $response_score = $this->component_score(
                    $on_time_rate,
                    $config['response_target'],
                    $cap
                );
            }

            $active_components = [
                'quote_score'            => $quote_score,
                'accepted_revenue_score' => $accepted_revenue_score,
                'acceptance_score'       => $acceptance_score,
            ];
            if ($reminder_status === 'active') {
                $active_components['reminder_response'] = $response_score;
            }

            $active_weight_total = 0.0;
            foreach ($active_components as $comp_key => $comp_val) {
                $active_weight_total += (float) ($this->standard_weights[$comp_key] ?? 0.0);
            }

            $effective_weights = [];
            foreach ($this->standard_weights as $comp_key => $weight) {
                if (isset($active_components[$comp_key]) && $active_weight_total > 0) {
                    $effective_weights[$comp_key] = round(($weight / $active_weight_total) * 100, 4);
                } else {
                    $effective_weights[$comp_key] = 0.0;
                }
            }

            $weighted_sum = 0.0;
            foreach ($active_components as $comp_key => $comp_val) {
                $weighted_sum += ((float) $comp_val * (float) ($this->standard_weights[$comp_key] ?? 0.0));
            }
            $performance_score_raw = $active_weight_total > 0 ? ($weighted_sum / $active_weight_total) : 0.0;

            $data_quality_flags = [];
            if (!empty($metric['missing_revenue_rate_count'])) {
                $data_quality_flags[] = 'missing_revenue_rate';
            }
            if ($closed_count < (int) $config['min_closed_quotes']) {
                $data_quality_flags[] = 'insufficient_closed_quotes';
            }
            if ($reminder_status === 'active' && $eligible_reminders < 3) {
                $data_quality_flags[] = 'insufficient_reminder_sample';
            }

            $metric['closed_count'] = $closed_count;
            $metric['acceptance_rate'] = $closed_count > 0 ? round($acceptance_rate, 1) : null;
            $metric['quote_score'] = round($quote_score, 4);
            $metric['accepted_revenue_score'] = round($accepted_revenue_score, 4);
            $metric['acceptance_score'] = round($acceptance_score, 4);
            $metric['eligible_reminders'] = $eligible_reminders;
            $metric['on_time_reminders'] = $on_time_reminders;
            $metric['on_time_rate'] = $on_time_rate !== null ? round($on_time_rate, 1) : null;
            $metric['response_score'] = $response_score !== null ? round($response_score, 4) : null;
            $metric['component_status'] = ['reminder_response' => $reminder_status];
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
            'calculated_at'        => $calculated_at,
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
        foreach (['quote_target', 'revenue_target', 'component_cap'] as $key) {
            if (!isset($config[$key]) || !is_numeric($config[$key]) || (float) $config[$key] <= 0) {
                $errors[] = $key;
            }
        }
        if (!isset($config['acceptance_target']) || !is_numeric($config['acceptance_target']) || (float) $config['acceptance_target'] <= 0 || (float) $config['acceptance_target'] > 100) {
            $errors[] = 'acceptance_target';
        }
        if (!isset($config['response_target']) || !is_numeric($config['response_target']) || (float) $config['response_target'] <= 0 || (float) $config['response_target'] > 100) {
            $errors[] = 'response_target';
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
