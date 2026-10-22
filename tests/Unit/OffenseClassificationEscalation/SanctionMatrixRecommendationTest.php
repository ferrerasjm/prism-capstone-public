<?php

declare(strict_types=1);

namespace Tests\Unit\OffenseClassificationEscalation;

use App\Models\OffenseCatalog;
use App\Models\SanctionMatrix;
use App\Services\SanctionRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 3 - Offense Classification and Escalation Support
 * Component: Sanction Matrix Recommendation (App\Services\SanctionRecommendationService)
 * Test Cycle No. 1 - 5 test cases.
 *
 * Relocated from Sanction and Referral Monitoring: recommending the matrix
 * category and University Service hours for a confirmed offense is part of
 * classification and escalation support, not referral monitoring.
 */
class SanctionMatrixRecommendationTest extends TestCase
{
    use RefreshDatabase;

    /** Scenario 1 - Normal: a first offence reads the offence-count 1 row of the matrix. */
    public function test_a_first_offence_reads_the_first_matrix_row(): void
    {
        $offense = $this->offenseWithMatrix();

        $result = (new SanctionRecommendationService)->recommend($offense->id, 0);

        $this->assertSame(1, $result['offense_count_used']);
        $this->assertSame(1, $result['sanction_category']);
        $this->assertSame(6.0, $result['us_hours_recommended']);
    }

    /** Scenario 2 - Normal: a repeat offence reads the matching higher matrix row. */
    public function test_a_repeat_offence_reads_the_matching_higher_matrix_row(): void
    {
        $offense = $this->offenseWithMatrix();

        $result = (new SanctionRecommendationService)->recommend($offense->id, 2);

        $this->assertSame(3, $result['offense_count_used']);
        $this->assertSame(3, $result['sanction_category']);
        $this->assertSame(24.0, $result['us_hours_recommended']);
    }

    /** Scenario 3 - Boundary: beyond the last defined row the highest row is reused. */
    public function test_beyond_the_last_defined_row_the_highest_row_is_reused(): void
    {
        $offense = $this->offenseWithMatrix();

        $result = (new SanctionRecommendationService)->recommend($offense->id, 8);

        $this->assertSame(9, $result['offense_count_used']);
        $this->assertSame(3, $result['sanction_category']);
        $this->assertSame(24.0, $result['us_hours_recommended']);
    }

    /** Scenario 4 - Failure: an offence with no matrix entry recommends nothing. */
    public function test_an_offence_without_a_matrix_entry_recommends_nothing(): void
    {
        $offense = OffenseCatalog::create([
            'code' => 'MINOR-99',
            'offense_type' => 'minor',
            'title' => 'Catalog entry without a matrix row',
            'description' => 'Catalog entry without a matrix row.',
            'keywords' => [],
            'created_at' => now(),
        ]);

        $result = (new SanctionRecommendationService)->recommend($offense->id, 0);

        $this->assertNull($result['sanction_category']);
        $this->assertNull($result['us_hours_recommended']);
        $this->assertNull($result['us_hours_guidance']);
        $this->assertSame(1, $result['offense_count_used']);
    }

    /** Scenario 5 - Normal / boundary: the resolution path follows admission type and category. */
    public function test_the_resolution_path_follows_admission_type_and_sanction_category(): void
    {
        $service = new SanctionRecommendationService;

        $this->assertSame('direct_resolution', $service->recommendPath('full_admission', 1)['path']);
        $this->assertSame('direct_resolution', $service->recommendPath('full_admission', 2)['path']);
        $this->assertSame('upcc_referral', $service->recommendPath('full_admission', 3)['path']);
        $this->assertSame('upcc_referral', $service->recommendPath('partial_admission', 1)['path']);
        $this->assertSame('upcc_referral', $service->recommendPath('full_denial', 2)['path']);
        $this->assertNotSame('', $service->recommendPath('full_denial', 2)['reason']);
    }

    private function offenseWithMatrix(): OffenseCatalog
    {
        $offense = OffenseCatalog::create([
            'code' => 'MINOR-01',
            'offense_type' => 'minor',
            'title' => 'Non-wearing of the prescribed uniform',
            'description' => 'Non-wearing of the prescribed uniform.',
            'keywords' => ['uniform'],
            'created_at' => now(),
        ]);

        foreach ([[1, 1, 6.0], [2, 2, 12.0], [3, 3, 24.0]] as [$count, $category, $hours]) {
            SanctionMatrix::create([
                'offense_catalog_id' => $offense->id,
                'offense_count' => $count,
                'sanction_category' => $category,
                'us_hours_recommended' => $hours,
                'us_hours_min' => $hours,
                'us_hours_max' => $hours,
                'us_hours_guidance' => "{$hours} hours of University Service.",
                'notes' => null,
            ]);
        }

        return $offense;
    }
}
