<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('sales_pipeline_compact_money')) {
    /**
     * Format large VND amounts for dashboard tables.
     *
     * @param mixed $value
     * @return string
     */
    function sales_pipeline_compact_money($value)
    {
        $value = (float) $value;
        $absolute = abs($value);

        if ($absolute >= 1000000000) {
            $formatted = rtrim(rtrim(number_format($value / 1000000000, 1, ',', '.'), '0'), ',');
            return $formatted . ' ' . _l('sales_pipeline_currency_billion');
        }

        if ($absolute >= 1000000) {
            $formatted = rtrim(rtrim(number_format($value / 1000000, 1, ',', '.'), '0'), ',');
            return $formatted . ' ' . _l('sales_pipeline_currency_million');
        }

        return number_format($value, 0, ',', '.') . ' ' . _l('sales_pipeline_vnd');
    }
}

if (!function_exists('sales_pipeline_resolve_performance_tier')) {
    /**
     * Resolve performance tier metadata for a given performance score (0 - 120).
     * Strictly text/CSS based without icons.
     *
     * Tiers:
     * - >= 100: excellent (Xuất sắc)
     * - 80 - 99.9: good (Đạt chuẩn)
     * - 50 - 79.9: warning (Cần tăng tốc)
     * - < 50: critical (Báo động)
     *
     * @param float|int $score
     * @return array
     */
    function sales_pipeline_resolve_performance_tier($score)
    {
        $score = (float) $score;
        if ($score >= 100.0) {
            return [
                'key'         => 'excellent',
                'label'       => _l('sales_pipeline_tier_excellent'),
                'class'       => 'sp-tier--excellent',
                'badge_class' => 'sp-performance-tier-badge--excellent',
                'score_class' => 'sp-score--excellent',
                'gap_text'    => _l('sales_pipeline_tier_target_met'),
            ];
        }

        if ($score >= 80.0) {
            $gap = round(100.0 - $score, 1);
            return [
                'key'         => 'good',
                'label'       => _l('sales_pipeline_tier_good'),
                'class'       => 'sp-tier--good',
                'badge_class' => 'sp-performance-tier-badge--good',
                'score_class' => 'sp-score--good',
                'gap_text'    => _l('sales_pipeline_tier_gap_100', [number_format($gap, 1, ',', '.')]),
            ];
        }

        if ($score >= 50.0) {
            $gap = round(80.0 - $score, 1);
            return [
                'key'         => 'warning',
                'label'       => _l('sales_pipeline_tier_warning'),
                'class'       => 'sp-tier--warning',
                'badge_class' => 'sp-performance-tier-badge--warning',
                'score_class' => 'sp-score--warning',
                'gap_text'    => _l('sales_pipeline_tier_gap_80', [number_format($gap, 1, ',', '.')]),
            ];
        }

        $gap = round(80.0 - $score, 1);
        return [
            'key'         => 'critical',
            'label'       => _l('sales_pipeline_tier_critical'),
            'class'       => 'sp-tier--critical',
            'badge_class' => 'sp-performance-tier-badge--critical',
            'score_class' => 'sp-score--critical',
            'gap_text'    => _l('sales_pipeline_tier_gap_80', [number_format($gap, 1, ',', '.')]),
        ];
    }
}
