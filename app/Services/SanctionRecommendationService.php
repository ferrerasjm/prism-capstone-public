<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SanctionMatrix;

/**
 * Looks up the recommended sanction category and University Service hour
 * range from the sanction matrix (handbook Section 2.6), given an offense
 * category and the student's prior-violation count. Read-only lookup — it
 * recommends, it does not assign.
 */
class SanctionRecommendationService
{
    /**
     * Look up the recommended sanction category from the matrix (Section 2.6).
     *
     * @return array{
     *     sanction_category: int|null,
     *     us_hours_recommended: float|null,
     *     us_hours_min: float|null,
     *     us_hours_max: float|null,
     *     us_hours_guidance: string|null,
     *     notes: string|null,
     *     offense_count_used: int
     * }
     */
    public function recommend(int $offenseId, int $priorOffenseCount): array
    {
        $offenseCount = $priorOffenseCount + 1;

        $matrix = SanctionMatrix::where('offense_catalog_id', $offenseId)
            ->where('is_active', true)
            ->where('offense_count', $offenseCount)
            ->first();

        if (! $matrix) {
            // Use the most recent rule at or below this repeat count.
            $matrix = SanctionMatrix::where('offense_catalog_id', $offenseId)
                ->where('is_active', true)
                ->where('offense_count', '<=', $offenseCount)
                ->orderByDesc('offense_count')
                ->first();
        }

        if (! $matrix) {
            return [
                'sanction_category' => null,
                'us_hours_recommended' => null,
                'us_hours_min' => null,
                'us_hours_max' => null,
                'us_hours_guidance' => null,
                'notes' => null,
                'offense_count_used' => $offenseCount,
            ];
        }

        return [
            'sanction_category' => $matrix->sanction_category,
            'us_hours_recommended' => $matrix->us_hours_recommended,
            'us_hours_min' => $matrix->us_hours_min,
            'us_hours_max' => $matrix->us_hours_max,
            'us_hours_guidance' => $matrix->us_hours_guidance,
            'notes' => $matrix->notes,
            'offense_count_used' => $offenseCount,
        ];
    }

    /**
     * Compute the path recommendation based on admission type and sanction category (Section 2.8).
     *
     * @return array{path: string, reason: string}
     */
    public function recommendPath(string $admissionType, int $sanctionCategory): array
    {
        if ($admissionType === 'full_admission' && $sanctionCategory <= 2) {
            return [
                'path' => 'direct_resolution',
                'reason' => 'Full admission with Category 1–2 sanction. Direct resolution at SDO level is appropriate.',
            ];
        }

        if ($sanctionCategory >= 3) {
            return [
                'path' => 'upcc_referral',
                'reason' => 'Category 3+ sanction requires UPCC referral.',
            ];
        }

        if ($admissionType === 'no_response' && $sanctionCategory <= 2) {
            return [
                'path' => 'direct_resolution',
                'reason' => 'No explanation was received within the deadline. Direct resolution at SDO level may proceed based on the original complaint and evidence on record.',
            ];
        }

        return [
            'path' => 'upcc_referral',
            'reason' => 'Partial or full denial warrants fact-finding by the UPCC.',
        ];
    }
}
