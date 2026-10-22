<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\OffenseCatalog;
use App\Models\SanctionMatrix;
use App\Services\SanctionRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for the rule-based sanction recommendation engine component
 * (Offense Classification and Escalation Support Module).
 */
class SanctionRecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommend_returns_all_nulls_when_offense_has_no_matrix_rows_at_all(): void
    {
        $offense = $this->offense('MAJOR-NOMATRIX');

        $result = (new SanctionRecommendationService)->recommend($offense->id, 0);

        $this->assertNull($result['sanction_category']);
        $this->assertNull($result['us_hours_recommended']);
        $this->assertNull($result['us_hours_min']);
        $this->assertNull($result['us_hours_max']);
        $this->assertNull($result['us_hours_guidance']);
        $this->assertNull($result['notes']);
        $this->assertSame(1, $result['offense_count_used']);
    }

    public function test_recommend_uses_exact_offense_count_row_when_present(): void
    {
        $offense = $this->offense('MAJOR-EXACT');
        SanctionMatrix::create([
            'offense_catalog_id' => $offense->id,
            'offense_count' => 1,
            'sanction_category' => 1,
        ]);
        SanctionMatrix::create([
            'offense_catalog_id' => $offense->id,
            'offense_count' => 2,
            'sanction_category' => 3,
        ]);

        $result = (new SanctionRecommendationService)->recommend($offense->id, 1);

        $this->assertSame(3, $result['sanction_category']);
        $this->assertSame(2, $result['offense_count_used']);
    }

    public function test_recommend_falls_back_to_highest_available_row_beyond_matrix_range(): void
    {
        $offense = $this->offense('MAJOR-BEYOND');
        SanctionMatrix::create([
            'offense_catalog_id' => $offense->id,
            'offense_count' => 1,
            'sanction_category' => 1,
        ]);
        SanctionMatrix::create([
            'offense_catalog_id' => $offense->id,
            'offense_count' => 3,
            'sanction_category' => 5,
        ]);

        $result = (new SanctionRecommendationService)->recommend($offense->id, 10);

        $this->assertSame(5, $result['sanction_category']);
        $this->assertSame(11, $result['offense_count_used']);
    }

    public function test_recommend_for_a_nonexistent_offense_id_returns_null_result(): void
    {
        $result = (new SanctionRecommendationService)->recommend(999999, 0);

        $this->assertNull($result['sanction_category']);
    }

    public function test_recommend_path_grants_direct_resolution_for_full_admission_and_low_category(): void
    {
        $result = (new SanctionRecommendationService)->recommendPath('full_admission', 2);

        $this->assertSame('direct_resolution', $result['path']);
    }

    public function test_recommend_path_requires_upcc_referral_for_category_three_or_higher(): void
    {
        $result = (new SanctionRecommendationService)->recommendPath('full_admission', 3);

        $this->assertSame('upcc_referral', $result['path']);
        $this->assertStringContainsString('Category 3+', $result['reason']);
    }

    public function test_recommend_path_requires_upcc_referral_for_partial_or_full_denial_even_at_low_category(): void
    {
        $result = (new SanctionRecommendationService)->recommendPath('full_denial', 1);

        $this->assertSame('upcc_referral', $result['path']);
        $this->assertStringContainsString('fact-finding', $result['reason']);
    }

    private function offense(string $code): OffenseCatalog
    {
        return OffenseCatalog::create([
            'code' => $code,
            'offense_type' => 'major',
            'title' => "{$code} Test Offense",
            'description' => 'Test offense.',
            'keywords' => ['test'],
            'created_at' => now(),
        ]);
    }
}
