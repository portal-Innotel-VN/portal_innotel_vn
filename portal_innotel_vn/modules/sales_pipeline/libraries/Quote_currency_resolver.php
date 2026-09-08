<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pure currency conversion for immutable Estimate Version snapshots.
 *
 * Rate unit: one source-currency unit equals N base-currency units.
 */
class Quote_currency_resolver
{
    const RATE_UNIT = 'base_currency_per_source_currency';

    /**
     * @param int              $sourceCurrencyId
     * @param int              $baseCurrencyId
     * @param int|float|string $sourceTotal
     * @param int|float|string|null $candidateRate
     * @return array
     */
    public function resolve($sourceCurrencyId, $baseCurrencyId, $sourceTotal, $candidateRate = null)
    {
        $sourceCurrencyId = (int) $sourceCurrencyId;
        $baseCurrencyId = (int) $baseCurrencyId;
        $rate = null;

        if ($sourceCurrencyId > 0 && $sourceCurrencyId === $baseCurrencyId) {
            $rate = 1.0;
        } elseif (is_numeric($candidateRate) && (float) $candidateRate > 0) {
            $rate = (float) $candidateRate;
        }

        return [
            'source_currency_id'    => $sourceCurrencyId,
            'base_currency_id'      => $baseCurrencyId,
            'exchange_rate_to_base' => $rate,
            'base_total'            => $rate === null
                ? null
                : round((float) $sourceTotal * $rate, 2),
            'rate_unit'             => self::RATE_UNIT,
        ];
    }
}

