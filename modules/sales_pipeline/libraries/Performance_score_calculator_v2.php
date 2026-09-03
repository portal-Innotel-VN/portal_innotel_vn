<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Performance Score Calculator V2
 *
 * Invariants:
 * 1. 4 components:
 *    - Quote Count (weight 20)
 *    - Revenue (weight 40)
 *    - Acceptance Rate (weight 25)
 *    - Reminder Response SLA (weight 15)
 * 2. Dynamic Denominator: 100.0 when eligible_reminders > 0, else 85.0.
 * 3. Confidence Weighting:
 *    - Acceptance: confidence = min(1.0, closed_count / min_closed_quotes) applied before cap.
 *    - Reminder: confidence = min(1.0, eligible_reminders / 3) applied before cap when 0 < eligible_reminders < 3.
 * 4. Maximum component score cap = 120% of base weight.
 * 5. Overall score clamped to [0, 120].
 */
class Performance_score_calculator_v2
{
    const FORMULA_VERSION = 'performance_score_v2';
    const COMPONENT_CAP_RATIO = 1.2;

    const WEIGHT_QUOTES = 20.0;
    const WEIGHT_REVENUE = 40.0;
    const WEIGHT_ACCEPTANCE = 25.0;
    const WEIGHT_REMINDERS = 15.0;

    /**
     * Calculate performance score V2.
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
        $targetResponseSla = (float) ($targets['target_response_sla'] ?? 90.0);
        $minClosedQuotes = (int) ($targets['min_closed_quotes'] ?? 5);

        $actualQuotes = (float) ($metrics['estimate_count'] ?? 0);
        $actualRevenue = (float) ($metrics['accepted_revenue'] ?? 0);
        $closedCount = (int) ($metrics['closed_count'] ?? 0);
        $acceptedCount = (int) ($metrics['accepted_count'] ?? 0);
        $eligibleReminders = isset($metrics['eligible_reminders']) ? (int) $metrics['eligible_reminders'] : 0;
        $onTimeReminders = isset($metrics['on_time_reminders']) ? (int) $metrics['on_time_reminders'] : 0;

        $dataQualityFlags = [];
        $isProvisional = false;

        // 1. Quote Count Component (Max 20 * 1.2 = 24.0)
        $quoteCap = self::WEIGHT_QUOTES * self::COMPONENT_CAP_RATIO;
        $quoteScore = $targetQuotes > 0 ? ($actualQuotes / $targetQuotes) * self::WEIGHT_QUOTES : 0.0;
        $quoteScoreClamped = min($quoteCap, max(0.0, $quoteScore));

        // 2. Revenue Component (Max 40 * 1.2 = 48.0)
        $revenueCap = self::WEIGHT_REVENUE * self::COMPONENT_CAP_RATIO;
        $revenueScore = $targetRevenue > 0 ? ($actualRevenue / $targetRevenue) * self::WEIGHT_REVENUE : 0.0;
        $revenueScoreClamped = min($revenueCap, max(0.0, $revenueScore));

        // 3. Acceptance Rate Component with Confidence Weighting
        $acceptanceCap = self::WEIGHT_ACCEPTANCE * self::COMPONENT_CAP_RATIO;
        $acceptanceRate = $closedCount > 0 ? ($acceptedCount / $closedCount) * 100.0 : null;

        if ($closedCount >= $minClosedQuotes) {
            $acceptanceScore = $targetAcceptance > 0 ? ($acceptanceRate / $targetAcceptance) * self::WEIGHT_ACCEPTANCE : 0.0;
            $acceptanceScoreClamped = min($acceptanceCap, max(0.0, $acceptanceScore));
        } elseif ($closedCount > 0) {
            // Confidence weighting for small sample
            $confidenceAcc = min(1.0, (float) $closedCount / (float) $minClosedQuotes);
            $rawScore = $targetAcceptance > 0 ? ($acceptanceRate / $targetAcceptance) * self::WEIGHT_ACCEPTANCE : 0.0;
            $acceptanceScoreClamped = min($acceptanceCap, max(0.0, $rawScore * $confidenceAcc));
            $dataQualityFlags[] = 'insufficient_closed_quotes';
            $isProvisional = true;
        } else {
            $acceptanceScoreClamped = 0.0;
            $dataQualityFlags[] = 'insufficient_closed_quotes';
            $isProvisional = true;
        }

        // 4. Reminder Response SLA Component with Dynamic Denominator and Confidence Weighting
        $components = [
            'quote_count' => [
                'weight' => self::WEIGHT_QUOTES,
                'actual' => $actualQuotes,
                'target' => $targetQuotes,
                'score'  => round($quoteScoreClamped, 4),
            ],
            'accepted_revenue' => [
                'weight' => self::WEIGHT_REVENUE,
                'actual' => $actualRevenue,
                'target' => $targetRevenue,
                'score'  => round($revenueScoreClamped, 4),
            ],
            'acceptance_rate' => [
                'weight' => self::WEIGHT_ACCEPTANCE,
                'actual' => $acceptanceRate,
                'target' => $targetAcceptance,
                'score'  => round($acceptanceScoreClamped, 4),
            ],
        ];

        $sumComponentScores = $quoteScoreClamped + $revenueScoreClamped + $acceptanceScoreClamped;

        if ($eligibleReminders > 0) {
            $effectiveDenominator = 100.0;
            $responseCap = self::WEIGHT_REMINDERS * self::COMPONENT_CAP_RATIO;
            $onTimeRate = ($onTimeReminders / $eligibleReminders) * 100.0;

            if ($eligibleReminders >= 3) {
                $responseScore = $targetResponseSla > 0 ? ($onTimeRate / $targetResponseSla) * self::WEIGHT_REMINDERS : 0.0;
                $responseScoreClamped = min($responseCap, max(0.0, $responseScore));
            } else {
                // Confidence weighting for small sample (< 3 reminders)
                $confidenceRem = min(1.0, (float) $eligibleReminders / 3.0);
                $rawResponse = $targetResponseSla > 0 ? ($onTimeRate / $targetResponseSla) * self::WEIGHT_REMINDERS : 0.0;
                $responseScoreClamped = min($responseCap, max(0.0, $rawResponse * $confidenceRem));
                $dataQualityFlags[] = 'insufficient_reminder_sample';
                $isProvisional = true;
            }

            $sumComponentScores += $responseScoreClamped;
            $components['reminder_response'] = [
                'weight' => self::WEIGHT_REMINDERS,
                'actual' => $onTimeRate,
                'target' => $targetResponseSla,
                'score'  => round($responseScoreClamped, 4),
            ];
        } else {
            $effectiveDenominator = 85.0;
        }

        $rawTotalScore = ($sumComponentScores / $effectiveDenominator) * 100.0;
        $finalScore = min(120.0, max(0.0, $rawTotalScore));

        return [
            'formula_version'       => self::FORMULA_VERSION,
            'performance_score'     => round($finalScore, 1),
            'performance_score_raw' => round($finalScore, 4),
            'effective_denominator' => $effectiveDenominator,
            'is_provisional'        => $isProvisional,
            'data_quality_flags'    => array_values(array_unique($dataQualityFlags)),
            'components'            => $components,
        ];
    }
}
