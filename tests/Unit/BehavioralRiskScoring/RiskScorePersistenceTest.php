<?php

declare(strict_types=1);

namespace Tests\Unit\BehavioralRiskScoring;

use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\RiskFactorWeight;
use App\Models\RiskScore;
use App\Models\RiskScoreSnapshot;
use App\Models\Sanction;
use App\Models\User;
use App\Services\RiskScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Module 4 - Behavioral Risk Scoring
 * Component: Risk Score Persistence and Trend
 *            (App\Services\RiskScoringService::computeForStudent)
 * Test Cycle No. 1 - 5 test cases.
 *
 * This covers what the calculation component does not: gathering the six
 * factors from live records, storing one current score per student, and
 * writing an audit snapshot with the movement since the previous run.
 */
class RiskScorePersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRiskWeights();
    }

    /** Scenario 1 - Boundary: a student with no history scores zero and is banded low. */
    public function test_a_student_with_no_history_scores_zero_and_is_banded_low(): void
    {
        $student = $this->student();

        $score = (new RiskScoringService)->computeForStudent($student->id, 'unit_test');

        $this->assertSame('0.00', (string) $score->composite_score);
        $this->assertSame('low', $score->risk_band);
        $this->assertNull($score->trend);
    }

    /** Scenario 2 - Normal: resolved confirmed cases raise the score and are itemised. */
    public function test_resolved_confirmed_cases_raise_the_score_and_are_itemised(): void
    {
        $student = $this->student();
        $this->resolvedConfirmedCase($student, 'DC-2026-7301', 'minor');

        $score = (new RiskScoringService)->computeForStudent($student->id, 'case_resolved');

        $factors = $score->contributing_factors;
        $this->assertGreaterThan(0, (float) $score->composite_score);
        $this->assertSame(1, $factors['total_offense_count']['raw_value']);
        $this->assertTrue($factors['recent_violation']['triggered']);
        $this->assertSame(0, $factors['major_offense_count']['raw_value']);
    }

    /** Scenario 3 - Normal: only one current score is kept per student, and it is replaced. */
    public function test_only_one_current_score_is_kept_per_student(): void
    {
        $student = $this->student();
        $service = new RiskScoringService;

        $service->computeForStudent($student->id, 'first_run');
        $this->resolvedConfirmedCase($student, 'DC-2026-7302', 'major');
        $second = $service->computeForStudent($student->id, 'case_resolved');

        $this->assertSame(1, RiskScore::where('student_id', $student->id)->count());
        $this->assertGreaterThan(0, (float) $second->composite_score);
        $this->assertSame('increasing', $second->trend);
    }

    /** Scenario 4 - Normal: every run writes an audit snapshot carrying the previous value. */
    public function test_every_run_writes_an_audit_snapshot_carrying_the_previous_value(): void
    {
        $student = $this->student();
        $service = new RiskScoringService;

        $service->computeForStudent($student->id, 'first_run');
        $this->resolvedConfirmedCase($student, 'DC-2026-7303', 'major');
        $service->computeForStudent($student->id, 'case_resolved');

        $snapshots = RiskScoreSnapshot::where('student_id', $student->id)->orderBy('id')->get();
        $latest = $snapshots->last();

        $this->assertCount(2, $snapshots);
        $this->assertSame('case_resolved', $latest->trigger);
        $this->assertSame('0.00', (string) $latest->previous_composite_score);
        $this->assertSame('low', $latest->previous_risk_band);
        $this->assertSame(config('risk_scoring.methodology_version'), $latest->methodology_version);
    }

    /** Scenario 5 - Boundary: a small movement is reported as stable, not as a change. */
    public function test_a_repeat_run_with_no_new_records_is_reported_as_stable(): void
    {
        $student = $this->student();
        Sanction::create([
            'student_id' => $student->id,
            'sanction_type' => 'university_service',
            'status' => 'non_compliant',
        ]);
        $service = new RiskScoringService;

        $first = $service->computeForStudent($student->id, 'first_run');
        $second = $service->computeForStudent($student->id, 'scheduled_recompute');

        $this->assertSame((string) $first->composite_score, (string) $second->composite_score);
        $this->assertSame('stable', $second->trend);
    }

    private function student(): User
    {
        return User::create([
            'last_name' => 'Ramos',
            'first_name' => 'Scored',
            'email' => 'persisted.student@students.nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);
    }

    private function resolvedConfirmedCase(User $student, string $number, string $type): DisciplinaryCase
    {
        $offense = OffenseCatalog::create([
            'code' => 'OFF-'.$number,
            'offense_type' => $type,
            'title' => 'Catalog entry for '.$number,
            'description' => 'Catalog entry for '.$number.'.',
            'keywords' => [],
            'created_at' => now(),
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => $number,
            'student_id' => $student->id,
            'case_type' => $type,
            'status' => 'resolved',
            'resolved_at' => now()->subDays(3),
            'prior_offense_count' => 0,
            'escalation_flagged' => false,
            'forwarded_to_coordinator' => false,
            'void_requires_coordinator_approval' => false,
        ]);

        $case->caseOffenses()->create([
            'offense_catalog_id' => $offense->id,
            'status' => 'confirmed',
            'confirmed_by' => null,
            'confirmed_at' => now(),
        ]);

        return $case;
    }

    private function seedRiskWeights(): void
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
