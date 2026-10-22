<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoordinatorUpccResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_coordinator_can_record_resolution_for_a_finalized_upcc_case(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 08:00:00'));
        [$coordinator, $officer, $case] = $this->finalizedUpccCase(category: 1);

        $this->actingAs($coordinator)
            ->get(route('coordinator.cases.resolution', $case))
            ->assertOk()
            ->assertSee('Record Resolution')
            ->assertSee($case->case_number);

        $response = $this->actingAs($coordinator)->post(
            route('coordinator.cases.resolution.store', $case),
            [
                'intervention_category' => 1,
                'probation_terms' => 3,
                'probation_end_date' => now()->addMonths(6)->toDateString(),
                'counseling_referred' => '1',
                'referral_reason' => 'Repeated conflicts with classmates noted during the UPCC hearing.',
                'urgency' => 'priority',
            ]
        );

        $response->assertRedirect(route('coordinator.cases.show', $case));
        $this->assertSame('resolved', $case->fresh()->status);
        $this->assertDatabaseHas('case_resolutions', [
            'case_id' => $case->id,
            'resolution_level' => 'upcc',
            'intervention_category' => 1,
            'resolved_by' => $coordinator->id,
        ]);
    }

    public function test_coordinator_can_issue_nfi_after_recording_a_category_two_resolution(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 08:00:00'));
        [$coordinator, $officer, $case] = $this->finalizedUpccCase(category: 2);

        $this->actingAs($coordinator)->post(
            route('coordinator.cases.resolution.store', $case),
            [
                'intervention_category' => 2,
                'us_hours_assigned' => 10,
                'counseling_referred' => '1',
                'referral_reason' => 'Formative intervention includes a counseling component per the UPCC decision.',
                'urgency' => 'routine',
            ]
        )->assertRedirect(route('coordinator.cases.show', $case));

        $this->assertSame('resolved', $case->fresh()->status);

        $response = $this->actingAs($coordinator)->post(
            route('coordinator.cases.issue-nfi', $case)
        );

        $response->assertRedirect(route('coordinator.cases.show', $case));
        $this->assertDatabaseHas('case_notices', [
            'case_id' => $case->id,
            'notice_type' => 'notice_of_formative_intervention',
        ]);
    }

    public function test_officer_cannot_record_resolution_for_a_upcc_case(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 08:00:00'));
        [$coordinator, $officer, $case] = $this->finalizedUpccCase(category: 1);

        $this->actingAs($officer)
            ->get(route('officer.cases.resolution', $case))
            ->assertRedirect(route('officer.cases.show', $case))
            ->assertSessionHas('info');

        $this->actingAs($officer)->post(
            route('officer.cases.resolution.store', $case),
            ['intervention_category' => 1]
        )->assertSessionHas('error');

        $this->assertSame('pending_resolution', $case->fresh()->status);
    }

    private function finalizedUpccCase(int $category): array
    {
        $coordinator = User::create([
            'last_name' => 'Coordinator',
            'first_name' => 'Discipline',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'discipline_coordinator',
            'status' => 'active',
        ]);

        $officer = User::create([
            'last_name' => 'Officer',
            'first_name' => 'Discipline',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $student = User::create([
            'last_name' => 'Student',
            'first_name' => 'Test',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $offense = OffenseCatalog::create([
            'code' => 'MAJOR-TEST',
            'offense_type' => 'major',
            'title' => 'Test Offense',
            'description' => 'Test offense description.',
            'keywords' => ['test'],
            'created_at' => now(),
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'UPCC-'.fake()->unique()->numerify('#####'),
            'case_type' => 'major',
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'status' => 'pending_resolution',
            'case_type_locked' => true,
            'confirmed_offense_id' => $offense->id,
            'assigned_sanction_category' => $category,
            'prior_offense_count' => 0,
            'forwarded_to_coordinator' => true,
            'forwarded_at' => now()->subDays(10),
        ]);

        $case->upccReferral()->create([
            'referred_by' => $coordinator->id,
            'referred_at' => now()->subDays(10),
            'scheduled_conference_at' => now()->subDays(5),
            'conference_location' => 'SDO Conference Room',
            'proceedings_type' => 'full_hearing',
            'status' => 'concluded',
            'decision_summary' => 'The UPCC found the student liable.',
            'decision_issued_at' => now()->subDays(4),
            'presidents_council_action' => 'approved',
            'presidents_council_acted_at' => now(),
            'decision_distributed_at' => now()->subDays(4),
            'appeal_window_start' => now()->subDays(4)->toDateString(),
            'appeal_window_end' => now()->subDay()->toDateString(),
            'appeal_filed' => false,
            'decision_final_at' => now(),
            'records_filed_at' => now(),
        ]);

        return [$coordinator, $officer, $case];
    }
}
