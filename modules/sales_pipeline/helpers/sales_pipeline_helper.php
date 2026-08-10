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
