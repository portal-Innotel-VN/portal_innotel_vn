<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('sales_pipeline_resolve_dashboard_period')) {
    /**
     * Resolve a dashboard period around an optional historical anchor date.
     * Future or malformed anchors deliberately fall back to today.
     *
     * @param string      $period
     * @param string|null $anchor
     * @param string|null $today Test seam; runtime callers should omit it.
     * @return array
     */
    function sales_pipeline_resolve_dashboard_period($period, $anchor = null, $today = null)
    {
        $allowed = ['this_week', 'this_month', 'this_quarter', 'this_year'];
        $period = is_string($period) ? trim($period) : '';
        if (!in_array($period, $allowed, true)) {
            $period = 'this_month';
        }

        $today = sales_pipeline_normalize_dashboard_date($today) ?: date('Y-m-d');
        $anchor = sales_pipeline_normalize_dashboard_date($anchor);
        if (!$anchor || $anchor > $today) {
            $anchor = $today;
        }

        $anchor_timestamp = strtotime($anchor . ' 12:00:00');

        if ($period === 'this_week') {
            $weekday = (int) date('N', $anchor_timestamp);
            $start = date('Y-m-d', strtotime('-' . ($weekday - 1) . ' days', $anchor_timestamp));
            $end = date('Y-m-d', strtotime('+6 days', strtotime($start . ' 12:00:00')));
        } elseif ($period === 'this_quarter') {
            $month = (int) date('n', $anchor_timestamp);
            $start_month = ((int) floor(($month - 1) / 3) * 3) + 1;
            $start = date('Y-', $anchor_timestamp) . str_pad((string) $start_month, 2, '0', STR_PAD_LEFT) . '-01';
            $end = date('Y-m-t', strtotime($start . ' +2 months'));
        } elseif ($period === 'this_year') {
            $year = date('Y', $anchor_timestamp);
            $start = $year . '-01-01';
            $end = $year . '-12-31';
        } else {
            $start = date('Y-m-01', $anchor_timestamp);
            $end = date('Y-m-t', $anchor_timestamp);
        }

        return [
            'key'    => $period,
            'start'  => $start,
            'end'    => $end,
            'anchor' => $anchor,
        ];
    }
}

if (!function_exists('sales_pipeline_normalize_dashboard_date')) {
    /**
     * Strictly accept an ISO calendar date.
     *
     * @param mixed $value
     * @return string|null
     */
    function sales_pipeline_normalize_dashboard_date($value)
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = DateTime::createFromFormat('!Y-m-d', $value);
        $errors = DateTime::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->format('Y-m-d') === $value ? $value : null;
    }
}
