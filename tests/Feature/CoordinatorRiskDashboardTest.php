<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\RiskScore;
use App\Models\RiskScoreSnapshot;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoordinatorRiskDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_can_view_risk_dashboard(): void
    {
        $coordinator = $this->user('discipline_coordinator');

        $response = $this->actingAs($coordinator)->get(route('coordinator.risk.index'));

        $response->assertOk();
        $response->assertSee('UPCC Risk Dashboard');
        $response->assertSee('UPCC Cases by Risk Priority');
        $response->assertSee('Critical Risk');
        $response->assertSee('High Risk');
        $response->assertSee('Moderate Risk');
        $response->assertSee('Low Risk');
    }

    public function test_coordinator_can_open_the_read_only_risk_profile_for_a_forwarded_case_student(): void
    {
        $coordinator = $this->user('discipline_coordinator');
        $student = $this->student();
        $this->forwardedCase($student);

        $score = RiskScore::create([
            'student_id' => $student->id,
            'composite_score' => 42.50,
            'risk_band' => 'moderate',
            'trend' => 'increasing',
            'contributing_factors' => [
                'unresolved_cases' => [
                    'factor_name' => 'Unresolved Cases',
                    'description' => 'Open disciplinary cases on record.',
                    'factor_type' => 'count',
                    'contribution' => 42.5,
                    'weight' => 0.5,
                    'raw_value' => 1,
                    'cap' => 3,
                ],
            ],
            'computed_at' => now(),
        ]);

        RiskScoreSnapshot::create([
            'student_id' => $student->id,
            'snapshot_type' => 'event',
            'composite_score' => 42.50,
            'risk_band' => 'moderate',
            'trigger' => 'case_created',
            'factor_changes' => [],
            'snapshot_date' => today(),
            'created_at' => now(),
        ]);

        $this->actingAs($coordinator)
            ->get(route('coordinator.risk.show', $student))
            ->assertOk()
            ->assertSee($student->full_name)
            ->assertSee('Risk Indicators')
            ->assertSee('Unresolved Cases')
            ->assertSee('Score Timeline')
            // Score Timeline is collapsible, same toggle as the officer page.
            ->assertSee('score-timeline-toggle', false)
            ->assertSee('id="coordScoreTimeline"', false)
            ->assertSee('Support Actions')
            // Read-only: no intervention editing controls.
            ->assertDontSee('Update Action')
            ->assertDontSee('interventions.store', false);
    }

    public function test_risk_profile_is_forbidden_for_a_student_with_no_case_under_upcc(): void
    {
        $coordinator = $this->user('discipline_coordinator');
        $student = $this->student();

        $this->actingAs($coordinator)
            ->get(route('coordinator.risk.show', $student))
            ->assertForbidden();
    }

    public function test_risk_dashboard_view_link_points_at_the_risk_profile(): void
    {
        $coordinator = $this->user('discipline_coordinator');
        $student = $this->student();
        $this->forwardedCase($student);
        RiskScore::create([
            'student_id' => $student->id,
            'composite_score' => 10,
            'risk_band' => 'low',
            'contributing_factors' => [],
            'computed_at' => now(),
        ]);

        $this->actingAs($coordinator)
            ->get(route('coordinator.risk.index'))
            ->assertOk()
            ->assertSee(route('coordinator.risk.show', $student), false)
            // Columns mirror the officer risk dashboard, minus Support Follow-up.
            ->assertSeeInOrder([
                'Risk-Informed Score', 'Band', 'Top Risk Indicator', 'Last Assessed',
            ])
            ->assertDontSee('Support Follow-up')
            ->assertDontSee('<th>UPCC Stage</th>', false);
    }

    private function student(): User
    {
        $student = User::create([
            'last_name' => 'Pagurayan',
            'first_name' => 'Adrian',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => '2099-0000023',
            'program' => 'BS Information Technology',
            'year_level' => '3',
        ]);

        return $student;
    }

    private function forwardedCase(User $student): DisciplinaryCase
    {
        return DisciplinaryCase::create([
            'case_number' => 'DC-RISK-'.fake()->unique()->numerify('#####'),
            'case_type' => 'major',
            'case_type_locked' => true,
            'student_id' => $student->id,
            'status' => 'pending_upcc',
            'prior_offense_count' => 0,
            'forwarded_to_coordinator' => true,
            'forwarded_at' => now(),
        ]);
    }

    private function user(string $role): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'RiskDashboard',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
