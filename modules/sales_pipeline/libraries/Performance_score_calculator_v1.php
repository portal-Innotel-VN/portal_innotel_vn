<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Performance Score Calculator V1 (Legacy Immutable)
 *
 * Invariants:
 * 1. Fixed Denominator: 85.0
 * 2. Exactly 3 components:
 *    - Quote Count (weight 20)
 *    - Revenue (weight 40)
 *    - Acceptance Rate (weight 25)
 * 3. Reminder Response SLA is NEVER included.
 * 4. Maximum component score cap = 120% of base weight.
 * 5. Overall performance score clamped to [0, 120].
 */
class Performance_score_calculator_v1
{
    const FORMULA_VERSION = 'performance_score_v1';
    const COMPONENT_CAP_RATIO = 1.2;

    const WEIGHT_QUOTES = 20.0;
    const WEIGHT_REVENUE = 40.0;
    const WEIGHT_ACCEPTANCE = 25.0;
    const EFFECTIVE_DENOMINATOR = 85.0;

    /**
     * Calculate performance score according to immutable V1 specification.
     *
     * @param array $metrics
     * @param array $targets
     * @return array
     */
    public function calculate_score(array $metrics, array $targets = [])
    {
        $targetQuotes = (float) ($targets['target_quotes'] ?? 30.0);
        $targetRevenue = (float) ($targets['target_revenue'] ?? 100000000.0);
        $targetAcceptance = (float) ($targets['target_acceptance'] ?? 40.0);
        $minClosedQuotes = (int) ($targets['min_closed_quotes'] ?? 5);

        $actualQuotes = (float) ($metrics['estimate_count'] ?? 0);
        $actualRevenue = (float) ($metrics['accepted_revenue'] ?? 0);
        $closedCount = (int) ($metrics['closed_count'] ?? 0);
        $acceptedCount = (int) ($metrics['accepted_count'] ?? 0);

        // 1. Quote Count Component (Max 20 * 1.2 = 24.0)
        $quoteCap = self::WEIGHT_QUOTES * self::COMPONENT_CAP_RATIO;
        $quoteScore = $targetQuotes > 0 ? ($actualQuotes / $targetQuotes) * self::WEIGHT_QUOTES : 0.0;
        $quoteScoreClamped = min($quoteCap, max(0.0, $quoteScore));

        // 2. Revenue Component (Max 40 * 1.2 = 48.0)
        $revenueCap = self::WEIGHT_REVENUE * self::COMPONENT_CAP_RATIO;
        $revenueScore = $targetRevenue > 0 ? ($actualRevenue / $targetRevenue) * self::WEIGHT_REVENUE : 0.0;
        $revenueScoreClamped = min($revenueCap, max(0.0, $revenueScore));

        // 3. Acceptance Rate Component (Max 25 * 1.2 = 30.0)
        $acceptanceCap = self::WEIGHT_ACCEPTANCE * self::COMPONENT_CAP_RATIO;
        $dataQualityFlags = [];
        $isProvisional = false;

        if ($closedCount >= $minClosedQuotes) {
            $acceptanceRate = $closedCount > 0 ? ($acceptedCount / $closedCount) * 100.0 : 0.0;
            $acceptanceScore = $targetAcceptance > 0 ? ($acceptanceRate / $targetAcceptance) * self::WEIGHT_ACCEPTANCE : 0.0;
            $acceptanceScoreClamped = min($acceptanceCap, max(0.0, $acceptanceScore));
        } else {
            // Insufficient sample in V1
            $acceptanceRate = $closedCount > 0 ? ($acceptedCount / $closedCount) * 100.0 : null;
            $acceptanceScoreClamped = 0.0;
            $dataQualityFlags[] = 'insufficient_closed_quotes';
            $isProvisional = true;
        }

        // Aggregate 3 components over fixed denominator 85.0
        $sumComponentScores = $quoteScoreClamped + $revenueScoreClamped + $acceptanceScoreClamped;
        $rawTotalScore = ($sumComponentScores / self::EFFECTIVE_DENOMINATOR) * 100.0;
        $finalScore = min(120.0, max(0.0, $rawTotalScore));

        return [
            'formula_version'       => self::FORMULA_VERSION,
            'performance_score'     => round($finalScore, 1),
            'performance_score_raw' => round($finalScore, 4),
            'effective_denominator' => self::EFFECTIVE_DENOMINATOR,
            'is_provisional'        => $isProvisional,
            'data_quality_flags'    => $dataQualityFlags,
            'components'            => [
                'quote_count' => [
                    'weight'     => self::WEIGHT_QUOTES,
                    'actual'     => $actualQuotes,
                    'target'     => $targetQuotes,
                    'score'      => round($quoteScoreClamped, 4),
                ],
                'accepted_revenue' => [
                    'weight'     => self::WEIGHT_REVENUE,
                    'actual'     => $actualRevenue,
                    'target'     => $targetRevenue,
                    'score'      => round($revenueScoreClamped, 4),
                ],
                'acceptance_rate' => [
                    'weight'     => self::WEIGHT_ACCEPTANCE,
                    'actual'     => $acceptanceRate,
                    'target'     => $targetAcceptance,
                    'score'      => round($acceptanceScoreClamped, 4),
                ],
            ],
        ];
    }
}
