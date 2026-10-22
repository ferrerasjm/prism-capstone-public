<?php

declare(strict_types=1);

namespace Tests\Unit\BehavioralRiskScoring;

use App\Models\RiskFactorWeight;
use App\Services\RiskScoringService;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Module 4 - Behavioral Risk Scoring
 * Component: Composite Risk Score Calculation (App\Services\RiskScoringService)
 * Test Cycle No. 1 - 5 test cases.
 *
 * calculate() and resolveBand() are deterministic arithmetic over the six
 * documented factors, so the weights are supplied in memory and no database
 * record is required.
 */
class CompositeRiskScoreCalculationTest extends TestCase
{
    /** Scenario 1 - Boundary: a student with no risk signal scores zero and lands in the low band. */
    public function test_a_student_with_no_risk_signal_scores_zero_in_the_low_band(): void
    {
        $result = $this->calculate([
            'total_offense_count' => 0,
            'recent_violation' => 0,
            'major_offense_count' => 0,
            'escalation_flag' => 0,
            'unresolved_cases' => 0,
            'non_compliant_sanctions' => 0,
        ]);

        $this->assertSame(0.0, $result['composite_score']);
        $this->assertSame('low', $result['risk_band']);
        $this->assertCount(6, $result['factors']);
    }

    /** Scenario 2 - Boundary: every factor at its cap produces the maximum score of 100. */
    public function test_every_factor_at_its_cap_produces_the_maximum_score(): void
    {
        $result = $this->calculate([
            'total_offense_count' => 5,
            'recent_violation' => 1,
            'major_offense_count' => 3,
            'escalation_flag' => 1,
            'unresolved_cases' => 3,
            'non_compliant_sanctions' => 3,
        ]);

        $this->assertSame(100.0, $result['composite_score']);
        $this->assertSame('critical', $result['risk_band']);
    }

    /** Scenario 3 - Boundary: count factors are capped, so extra records add nothing. */
    public function test_count_factors_are_capped_so_extra_records_add_nothing(): void
    {
        $atCap = $this->calculate(['total_offense_count' => 5]);
        $overCap = $this->calculate(['total_offense_count' => 40]);

        $this->assertSame(25.0, $atCap['composite_score']);
        $this->assertSame($atCap['composite_score'], $overCap['composite_score']);
        $this->assertSame(5, $overCap['factors']['total_offense_count']['capped_value']);
        $this->assertSame(40, $overCap['factors']['total_offense_count']['raw_value']);
    }

    /** Scenario 4 - Normal: a boolean factor contributes its full weight only when triggered. */
    public function test_a_boolean_factor_contributes_its_full_weight_only_when_triggered(): void
    {
        $triggered = $this->calculate(['escalation_flag' => 1]);
        $notTriggered = $this->calculate(['escalation_flag' => 0]);

        $this->assertSame(15.0, $triggered['composite_score']);
        $this->assertTrue($triggered['factors']['escalation_flag']['triggered']);
        $this->assertSame(0.0, $notTriggered['composite_score']);
        $this->assertFalse($notTriggered['factors']['escalation_flag']['triggered']);
    }

    /** Scenario 5 - Boundary: risk band thresholds are applied at their exact edges. */
    public function test_risk_band_thresholds_are_applied_at_their_exact_edges(): void
    {
        $resolveBand = new ReflectionMethod(RiskScoringService::class, 'resolveBand');
        $service = new RiskScoringService;

        $this->assertSame('low', $resolveBand->invoke($service, 25.0));
        $this->assertSame('moderate', $resolveBand->invoke($service, 26.0));
        $this->assertSame('moderate', $resolveBand->invoke($service, 50.0));
        $this->assertSame('high', $resolveBand->invoke($service, 51.0));
        $this->assertSame('high', $resolveBand->invoke($service, 75.0));
        $this->assertSame('critical', $resolveBand->invoke($service, 76.0));
    }

    private function calculate(array $rawFactors): array
    {
        $method = new ReflectionMethod(RiskScoringService::class, 'calculate');

        return $method->invoke(new RiskScoringService, $rawFactors, $this->weights());
    }

    private function weights(): Collection
    {
        return collect(config('risk_scoring.factors'))
            ->map(fn (array $factor, string $code) => new RiskFactorWeight([
                'factor_code' => $code,
                'factor_name' => $factor['name'],
                'factor_type' => $factor['type'],
                'count_cap' => $factor['cap'],
                'weight' => $factor['weight'],
                'description' => $factor['description'],
                'is_active' => true,
            ]))
            ->values();
    }
}
