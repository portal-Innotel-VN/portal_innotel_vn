<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Authoritative Exchange Rate Provider
 *
 * Provides approved currency exchange rates for the Sales Pipeline.
 * Invariants:
 * 1. Base currency to base currency always returns 1.0.
 * 2. Foreign currency rate must be approved (approved_at IS NOT NULL, is_active = 1).
 * 3. Enforces rate_unit = 'base_currency_per_source_currency' (1 source = N base).
 * 4. Picks the latest approved effective_date <= target_date.
 * 5. Never fallbacks to 1.0 when rate is missing or unapproved — returns NULL fail-closed.
 */
class Authoritative_exchange_rate_provider
{
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
     * Resolve exchange rate from context.
     *
     * @param array $context [source_currency_id, base_currency_id, target_date|captured_at]
     * @return array ['rate' => float|null, 'source' => string, 'effective_date' => string|null]
     */
    public function resolve_rate(array $context)
    {
        $sourceCurrencyId = (int) ($context['source_currency_id'] ?? 0);
        $baseCurrencyId = (int) ($context['base_currency_id'] ?? 0);
        $rawDate = $context['target_date'] ?? $context['captured_at'] ?? date('Y-m-d');
        $targetDate = substr((string) $rawDate, 0, 10);

        // 1. Same currency is identity (rate 1.0)
        if ($sourceCurrencyId > 0 && $sourceCurrencyId === $baseCurrencyId) {
            return [
                'rate'           => 1.0,
                'source'         => 'base_identity',
                'effective_date' => $targetDate,
            ];
        }

        // 2. Query from database if DB available
        if ($this->CI && !empty($this->CI->db)) {
            $table = db_prefix() . 'sales_pipeline_exchange_rates';
            if ($this->CI->db->table_exists($table)) {
                $row = $this->CI->db
                    ->from($table)
                    ->where('source_currency_id', $sourceCurrencyId)
                    ->where('base_currency_id', $baseCurrencyId)
                    ->where('rate_unit', 'base_currency_per_source_currency')
                    ->where('is_active', 1)
                    ->where('approved_at IS NOT NULL', null, false)
                    ->where('effective_date <=', $targetDate)
                    ->order_by('effective_date', 'DESC')
                    ->order_by('rate_version', 'DESC')
                    ->limit(1)
                    ->get()
                    ->row_array();

                if ($row && isset($row['exchange_rate_to_base']) && (float) $row['exchange_rate_to_base'] > 0) {
                    return [
                        'rate'           => (float) $row['exchange_rate_to_base'],
                        'source'         => 'authoritative_db',
                        'effective_date' => $row['effective_date'],
                    ];
                }
            }
        }

        // 3. Fallback fail-closed: return NULL
        return [
            'rate'           => null,
            'source'         => 'missing_approved_rate',
            'effective_date' => null,
        ];
    }

    /**
     * Pure in-memory dataset lookup helper for testing and batch processing.
     *
     * @param array $dataset
     * @param int $sourceCurrencyId
     * @param int $baseCurrencyId
     * @param string $targetDate
     * @return array|null
     */
    public function find_rate_in_dataset(array $dataset, $sourceCurrencyId, $baseCurrencyId, $targetDate)
    {
        $targetDate = substr((string) $targetDate, 0, 10);
        $candidates = [];

        foreach ($dataset as $row) {
            $rateUnit = $row['rate_unit'] ?? 'base_currency_per_source_currency';
            if ((int) $row['source_currency_id'] === (int) $sourceCurrencyId
                && (int) $row['base_currency_id'] === (int) $baseCurrencyId
                && $rateUnit === 'base_currency_per_source_currency'
                && !empty($row['is_active'])
                && !empty($row['approved_at'])
                && $row['effective_date'] <= $targetDate) {
                $candidates[] = $row;
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Sort descending by effective_date
        usort($candidates, function ($a, $b) {
            return strcmp($b['effective_date'], $a['effective_date']);
        });

        return [
            'rate'           => (float) $candidates[0]['exchange_rate_to_base'],
            'effective_date' => $candidates[0]['effective_date'],
        ];
    }
}

if (!function_exists('sales_pipeline_resolve_quote_exchange_rate_hook')) {
    /**
     * Standard filter hook callback for 'sales_pipeline_quote_exchange_rate'.
     */
    function sales_pipeline_resolve_quote_exchange_rate_hook($rate, $estimate_id = null, $source_currency_id = null, $base_currency_id = null, $captured_at = null)
    {
        $provider = new Authoritative_exchange_rate_provider();
        $res = $provider->resolve_rate([
            'estimate_id'        => $estimate_id,
            'source_currency_id' => $source_currency_id,
            'base_currency_id'   => $base_currency_id,
            'captured_at'        => $captured_at,
        ]);
        return $res['rate'];
    }
}
