<?php

declare(strict_types=1);

namespace Tests\Unit\BehavioralRiskScoring;

use App\Models\RiskFactorWeight;
use App\Services\RiskScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Module 4 - Behavioral Risk Scoring
 * Component: Risk Factor Weight Integrity Guard (App\Services\RiskScoringService)
 * Test Cycle No. 1 - 5 test cases.
 *
 * The guard refuses to score at all unless the six documented factors are
 * active and their weights total exactly 1.0000.
 */
class RiskFactorWeightIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /** Scenario 1 - Normal: the six documented, active factors are accepted. */
    public function test_the_six_documented_active_factors_are_accepted(): void
    {
        $this->seedDocumentedWeights();

        $weights = $this->weights();

        $this->assertCount(6, $weights);
        $this->assertEqualsWithDelta(
            1.0,
            $weights->sum(fn (RiskFactorWeight $weight) => (float) $weight->weight),
            0.0001
        );
    }

    /** Scenario 2 - Failure: a missing factor blocks scoring entirely. */
    public function test_a_missing_factor_blocks_scoring(): void
    {
        $this->seedDocumentedWeights();
        RiskFactorWeight::where('factor_code', 'unresolved_cases')->delete();

        $this->expectException(LogicException::class);

        $this->weights();
    }

    /** Scenario 3 - Failure: deactivating a factor is treated the same as removing it. */
    public function test_deactivating_a_factor_blocks_scoring(): void
    {
        $this->seedDocumentedWeights();
        RiskFactorWeight::where('factor_code', 'escalation_flag')->update(['is_active' => false]);

        $this->expectException(LogicException::class);

        $this->weights();
    }

    /** Scenario 4 - Boundary: weights that do not total 1.0000 are rejected. */
    public function test_weights_that_do_not_total_one_are_rejected(): void
    {
        $this->seedDocumentedWeights();
        RiskFactorWeight::where('factor_code', 'recent_violation')->update(['weight' => 0.2000]);

        $this->expectException(LogicException::class);

        $this->weights();
    }

    /** Scenario 5 - Boundary: an unrecognised extra factor code is ignored, not counted. */
    public function test_an_unrecognised_extra_factor_code_is_ignored(): void
    {
        $this->seedDocumentedWeights();
        RiskFactorWeight::create([
            'factor_code' => 'legacy_unused_factor',
            'factor_name' => 'Legacy Unused Factor',
            'factor_type' => 'count',
            'count_cap' => 3,
            'weight' => 0.5000,
            'description' => 'Retired factor that must not affect scoring.',
            'is_active' => true,
            'updated_at' => now(),
        ]);

        $codes = $this->weights()->pluck('factor_code')->sort()->values()->all();

        $this->assertCount(6, $codes);
        $this->assertNotContains('legacy_unused_factor', $codes);
    }

    private function weights()
    {
        $method = new ReflectionMethod(RiskScoringService::class, 'weights');

        return $method->invoke(new RiskScoringService);
    }

    private function seedDocumentedWeights(): void
    {
        RiskFactorWeight::query()->delete();

        foreach (config('risk_scoring.factors') as $code => $factor) {
            RiskFactorWeight::create([
                'factor_code' => $code,
                'factor_name' => $factor['name'],
                'factor_type' => $factor['type'],
                'count_cap' => $factor['cap'],
                'weight' => $factor['weight'],
                'description' => $factor['description'],
                'is_active' => true,
                'updated_at' => now(),
            ]);
        }
    }
}
