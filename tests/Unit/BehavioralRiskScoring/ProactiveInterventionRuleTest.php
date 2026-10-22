<?php

declare(strict_types=1);

namespace Tests\Unit\BehavioralRiskScoring;

use App\Models\RiskScore;
use App\Models\User;
use App\Services\RiskInterventionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Module 4 - Behavioral Risk Scoring
 * Component: Proactive Intervention Rules (App\Services\RiskInterventionService)
 * Test Cycle No. 1 - 5 test cases.
 */
class ProactiveInterventionRuleTest extends TestCase
{
    use RefreshDatabase;

    /** Scenario 1 - Normal: a high-risk student can be given an open support action. */
    public function test_a_high_risk_student_receives_an_open_support_action(): void
    {
        $student = $this->student();
        $officer = $this->officer();
        $this->riskScore($student, 'high', 62.5);

        $intervention = (new RiskInterventionService)->create($student, $this->payload(), $officer->id);

        $this->assertSame('in_progress', $intervention->status);
        $this->assertSame($student->id, $intervention->student_id);
        $this->assertSame($officer->id, $intervention->assigned_to);
        $this->assertSame('high', $intervention->risk_band);
    }

    /** Scenario 2 - Failure: only student accounts can receive support actions. */
    public function test_a_non_student_account_cannot_receive_a_support_action(): void
    {
        $officer = $this->officer();
        $this->riskScore($officer, 'critical', 88.0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Risk interventions can only be recorded for student accounts.');

        (new RiskInterventionService)->create($officer, $this->payload(), $officer->id);
    }

    /** Scenario 3 - Boundary: a moderate assessment is below the intervention threshold. */
    public function test_a_moderate_assessment_is_below_the_intervention_threshold(): void
    {
        $student = $this->student();
        $officer = $this->officer();
        $this->riskScore($student, 'moderate', 40.0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A new proactive support action requires a current high or critical risk assessment.');

        (new RiskInterventionService)->create($student, $this->payload(), $officer->id);
    }

    /** Scenario 4 - Invalid: only an active Discipline Officer can record a support action. */
    public function test_only_an_active_discipline_officer_can_record_a_support_action(): void
    {
        $student = $this->student();
        $officer = $this->officer();
        $this->riskScore($student, 'critical', 82.0);

        $guidance = User::create([
            'last_name' => 'Bautista',
            'first_name' => 'Guidance',
            'email' => 'guidance.officer@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'guidance_officer',
            'status' => 'active',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Support actions can only be recorded by an active Discipline Officer.');

        (new RiskInterventionService)->create($student, $this->payload(), $guidance->id);
    }

    /** Scenario 5 - Failure: an open action cannot be moved to a status that no longer exists. */
    public function test_an_open_action_cannot_be_moved_to_a_retired_status(): void
    {
        $student = $this->student();
        $officer = $this->officer();
        $this->riskScore($student, 'high', 60.0);
        $intervention = (new RiskInterventionService)->create($student, $this->payload(), $officer->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A support action cannot move from In Progress to Planned.');

        (new RiskInterventionService)->update($intervention, [
            'status' => 'planned',
            'follow_up_on' => now()->addDays(7)->toDateString(),
            'outcome' => 'Attempted to move the action back to planning.',
        ], $officer->id);
    }

    private function payload(): array
    {
        return [
            'action_type' => 'student_support_meeting',
            'action_plan' => 'Meet the student, review the standing obligations, and agree on next steps.',
            'follow_up_on' => now()->addDays(7)->toDateString(),
        ];
    }

    private function student(): User
    {
        return User::create([
            'last_name' => 'Ramos',
            'first_name' => 'Risk',
            'email' => 'risk.student@students.nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);
    }

    private function officer(): User
    {
        return User::create([
            'last_name' => 'Tan',
            'first_name' => 'Discipline',
            'email' => 'risk.officer@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);
    }

    private function riskScore(User $user, string $band, float $score): RiskScore
    {
        return RiskScore::create([
            'student_id' => $user->id,
            'composite_score' => $score,
            'risk_band' => $band,
            'trend' => 'stable',
            'contributing_factors' => [],
            'computed_at' => now(),
        ]);
    }
}
