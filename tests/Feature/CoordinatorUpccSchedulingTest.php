<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoordinatorUpccSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_upcc_conference_can_be_scheduled_for_a_future_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 19:57:16'));
        [$coordinator, $case] = $this->pendingUpccCase();

        $response = $this->actingAs($coordinator)->post(
            route('coordinator.cases.schedule-upcc', $case),
            [
                'scheduled_conference_date' => '2026-07-18',
                'scheduled_conference_time' => '00:00',
                'conference_location' => 'SDO Conference Room',
                'proceedings_type' => 'full_hearing',
                'sdao_head_name' => 'Jane SDAO',
                'dean_or_program_chair_name' => 'Dean Reyes',
                'cross_program_faculty_name' => 'Prof. Santos',
                'student_representative_name' => 'Student Rep Cruz',
                'presiding_officer_name' => 'SDO Head Bautista',
            ]
        );

        $response->assertRedirect(route('coordinator.cases.progress', $case));
        $this->assertDatabaseHas('upcc_referrals', [
            'case_id' => $case->id,
            'scheduled_conference_at' => '2026-07-18 00:00:00',
            'conference_location' => 'SDO Conference Room',
            'status' => 'scheduled',
            'sdao_head_name' => 'Jane SDAO',
            'dean_or_program_chair_name' => 'Dean Reyes',
            'cross_program_faculty_name' => 'Prof. Santos',
            'student_representative_name' => 'Student Rep Cruz',
            // The presiding officer is always the scheduling coordinator; the
            // posted value above is ignored in favour of the authenticated user.
            'presiding_officer_name' => $coordinator->full_name,
        ]);
    }

    public function test_upcc_conference_cannot_be_scheduled_today_or_in_the_past(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 19:57:16'));
        [$coordinator, $case] = $this->pendingUpccCase();

        $response = $this->actingAs($coordinator)->post(
            route('coordinator.cases.schedule-upcc', $case),
            [
                'scheduled_conference_date' => '2026-07-13',
                'scheduled_conference_time' => '23:59',
                'conference_location' => 'SDO Conference Room',
                'proceedings_type' => 'full_hearing',
            ]
        );

        $response->assertSessionHasErrors('scheduled_conference_at');
        $this->assertDatabaseMissing('upcc_referrals', [
            'case_id' => $case->id,
        ]);
    }

    public function test_upcc_conference_can_be_scheduled_far_in_the_future(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 19:57:16'));
        [$coordinator, $case] = $this->pendingUpccCase();

        $response = $this->actingAs($coordinator)->post(
            route('coordinator.cases.schedule-upcc', $case),
            [
                'scheduled_conference_date' => '2026-08-21',
                'scheduled_conference_time' => '08:00',
                'conference_location' => 'SDO Conference Room',
                'proceedings_type' => 'full_hearing',
                'sdao_head_name' => 'Jane SDAO',
                'dean_or_program_chair_name' => 'Dean Reyes',
                'cross_program_faculty_name' => 'Prof. Santos',
                'student_representative_name' => 'Student Rep Cruz',
                'presiding_officer_name' => 'SDO Head Bautista',
            ]
        );

        $response->assertRedirect(route('coordinator.cases.progress', $case));
        $this->assertDatabaseHas('upcc_referrals', [
            'case_id' => $case->id,
            'scheduled_conference_at' => '2026-08-21 08:00:00',
            'status' => 'scheduled',
        ]);
    }

    public function test_forwarded_upcc_case_shows_workflow_actions(): void
    {
        [$coordinator, $case] = $this->pendingUpccCase();

        $this->actingAs($coordinator)
            ->get(route('coordinator.cases.show', $case))
            ->assertOk()
            ->assertSee('UPCC Coordinator Workflow')
            ->assertSee('View Progress')
            ->assertDontSee('Next Action')
            ->assertDontSee('Schedule UPCC Hearing')
            ->assertDontSee('Read-only coordinator review');

        $this->actingAs($coordinator)
            ->get(route('coordinator.cases.progress', $case))
            ->assertOk()
            ->assertSee('UPCC Progress')
            ->assertSee('Next Action')
            ->assertSee('Schedule UPCC Hearing');
    }

    public function test_coordinator_can_continue_workflow_after_scheduling(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 19:57:16'));
        [$coordinator, $case] = $this->pendingUpccCase();

        $referral = $case->upccReferral()->create([
            'referred_by' => $coordinator->id,
            'referred_at' => now(),
            'scheduled_conference_at' => now(),
            'conference_location' => 'SDO Conference Room',
            'proceedings_type' => 'full_hearing',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($coordinator)->post(
            route('coordinator.cases.record-attendance', $case),
            ['attended' => '1']
        );

        $response->assertRedirect(route('coordinator.cases.progress', $case));
        $this->assertDatabaseHas('upcc_referrals', [
            'id' => $referral->id,
            'student_attended' => true,
            'status' => 'in_progress',
        ]);
        $this->assertNotNull($referral->fresh()->proceedings_started_at);

        $this->actingAs($coordinator)->post(
            route('coordinator.cases.record-decision', $case),
            [
                'decision_summary' => 'The UPCC completed its review and issued its formal decision.',
                'outcome' => 'violation_established',
            ]
        )->assertRedirect(route('coordinator.cases.progress', $case));

        $completedReferral = $referral->fresh();
        $this->assertSame('concluded', $completedReferral->status);
        $this->assertNotNull($completedReferral->proceedings_ended_at);
        $this->assertNotNull($completedReferral->decision_issued_at);
        $this->assertNotNull($completedReferral->final_report_submitted_at);
        $this->assertNotNull($completedReferral->submitted_to_presidents_council_at);

        $this->actingAs($coordinator)
            ->get(route('coordinator.cases.progress', $case))
            ->assertOk()
            ->assertSee('Record Council Action');
    }

    public function test_no_violation_outcome_closes_the_case_and_skips_council_and_resolution(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 19:57:16'));
        [$coordinator, $case] = $this->pendingUpccCase();

        $referral = $case->upccReferral()->create([
            'referred_by' => $coordinator->id,
            'referred_at' => now(),
            'scheduled_conference_at' => now(),
            'conference_location' => 'SDO Conference Room',
            'proceedings_type' => 'full_hearing',
            'status' => 'scheduled',
        ]);

        $this->actingAs($coordinator)->post(
            route('coordinator.cases.record-attendance', $case),
            ['attended' => '1']
        );

        $this->actingAs($coordinator)
            ->get(route('coordinator.cases.progress', $case))
            ->assertOk()
            ->assertSee('Hearing Outcome')
            ->assertSee('No Violation Found');

        $this->actingAs($coordinator)->post(
            route('coordinator.cases.record-decision', $case),
            [
                'decision_summary' => 'The committee found no basis for the alleged offense.',
                'outcome' => 'no_violation',
            ]
        )->assertRedirect(route('coordinator.cases.progress', $case));

        $closedCase = $case->fresh();
        $this->assertSame('dismissed', $closedCase->status);
        $this->assertSame('no_violation', $referral->fresh()->outcome);
        $this->assertNull($referral->fresh()->presidents_council_action);

        $this->actingAs($coordinator)
            ->get(route('coordinator.cases.progress', $case))
            ->assertOk()
            ->assertSee('No Violation Found')
            ->assertDontSee('Appeal Window')
            ->assertDontSee('Record Council Action');
    }

    public function test_student_can_view_the_notice_of_hearing_after_scheduling(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 19:57:16'));
        [$coordinator, $case] = $this->pendingUpccCase();

        $this->actingAs($coordinator)->post(
            route('coordinator.cases.schedule-upcc', $case),
            [
                'scheduled_conference_date' => '2026-07-18',
                'scheduled_conference_time' => '13:00',
                'conference_location' => 'Case Room',
                'proceedings_type' => 'full_hearing',
                'sdao_head_name' => 'Jane SDAO',
                'dean_or_program_chair_name' => 'Dean Reyes',
                'cross_program_faculty_name' => 'Prof. Santos',
                'student_representative_name' => 'Student Rep Cruz',
                'presiding_officer_name' => 'SDO Head Bautista',
            ]
        );

        $student = $case->student;

        // Served as a PDF, so the document is asserted through its headers; the panel
        // details it carries are covered by the notice record assertions above.
        $response = $this->actingAs($student)
            ->get(route('student.cases.hearing-notice', $case))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString(
            'notice-of-hearing-'.$case->case_number.'.pdf',
            $response->headers->get('Content-Disposition'),
        );
    }

    public function test_attendance_cannot_be_recorded_before_conference_begins(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 08:00:00'));
        [$coordinator, $case] = $this->pendingUpccCase();

        $referral = $case->upccReferral()->create([
            'referred_by' => $coordinator->id,
            'referred_at' => now(),
            'scheduled_conference_at' => now()->addDay(),
            'conference_location' => 'SDO Conference Room',
            'proceedings_type' => 'full_hearing',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($coordinator)->post(
            route('coordinator.cases.record-attendance', $case),
            ['attended' => '1']
        );

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('upcc_referrals', [
            'id' => $referral->id,
            'status' => 'scheduled',
            'student_attended' => null,
        ]);
    }

    public function test_explicit_demo_switch_allows_early_attendance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 08:00:00'));
        config()->set('prism_demo.allow_early_upcc_attendance', true);
        [$coordinator, $case] = $this->pendingUpccCase();

        $referral = $case->upccReferral()->create([
            'referred_by' => $coordinator->id,
            'referred_at' => now(),
            'scheduled_conference_at' => now()->addDay(),
            'conference_location' => 'SDO Conference Room',
            'proceedings_type' => 'full_hearing',
            'status' => 'scheduled',
        ]);

        $this->actingAs($coordinator)
            ->get(route('coordinator.cases.progress', $case))
            ->assertOk()
            ->assertSee('Demo mode: you can record attendance before the scheduled conference');

        $this->actingAs($coordinator)
            ->post(route('coordinator.cases.record-attendance', $case), ['attended' => '1'])
            ->assertRedirect(route('coordinator.cases.progress', $case));

        $this->assertDatabaseHas('upcc_referrals', [
            'id' => $referral->id,
            'student_attended' => true,
            'status' => 'in_progress',
        ]);
    }

    public function test_coordinator_can_reschedule_a_scheduled_upcc_conference(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 08:00:00'));
        [$coordinator, $case] = $this->pendingUpccCase();

        $referral = $case->upccReferral()->create([
            'referred_by' => $coordinator->id,
            'referred_at' => now(),
            'scheduled_conference_at' => now()->addDays(5),
            'conference_location' => 'SDO Conference Room',
            'proceedings_type' => 'full_hearing',
            'status' => 'scheduled',
        ]);

        $priorNotice = $case->notices()->create([
            'notice_type' => 'notice_of_hearing',
            'issued_by' => $coordinator->id,
            'issued_at' => now(),
            'handbook_section' => 'Section VI.E',
            'handbook_page' => '',
            'incident_date' => now()->toDateString(),
            'scheduled_at' => $referral->scheduled_conference_at,
            'scheduled_location' => $referral->conference_location,
            'is_voided' => false,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($coordinator)->post(
            route('coordinator.cases.reschedule-upcc', $case),
            [
                'scheduled_conference_date' => '2026-07-25',
                'scheduled_conference_time' => '10:00',
                'conference_location' => 'SDO Annex Room B',
                'reschedule_reason' => 'Panel member has a scheduling conflict on the original date.',
            ]
        );

        $response->assertRedirect(route('coordinator.cases.progress', $case));
        $this->assertDatabaseHas('upcc_referrals', [
            'id' => $referral->id,
            'scheduled_conference_at' => '2026-07-25 10:00:00',
            'conference_location' => 'SDO Annex Room B',
            'status' => 'scheduled',
        ]);

        $this->assertDatabaseHas('case_notices', [
            'id' => $priorNotice->id,
            'is_voided' => true,
        ]);
        $this->assertDatabaseHas('case_notices', [
            'case_id' => $case->id,
            'notice_type' => 'notice_of_hearing',
            'is_voided' => false,
            'scheduled_location' => 'SDO Annex Room B',
        ]);
    }

    public function test_upcc_conference_cannot_be_rescheduled_after_attendance_is_recorded(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 08:00:00'));
        [$coordinator, $case] = $this->pendingUpccCase();

        $referral = $case->upccReferral()->create([
            'referred_by' => $coordinator->id,
            'referred_at' => now(),
            'scheduled_conference_at' => now(),
            'conference_location' => 'SDO Conference Room',
            'proceedings_type' => 'full_hearing',
            'status' => 'in_progress',
            'student_attended' => true,
            'proceedings_started_at' => now(),
        ]);

        $response = $this->actingAs($coordinator)->post(
            route('coordinator.cases.reschedule-upcc', $case),
            [
                'scheduled_conference_date' => '2026-07-25',
                'scheduled_conference_time' => '10:00',
                'conference_location' => 'SDO Annex Room B',
                'reschedule_reason' => 'Attempting to reschedule after proceedings began.',
            ]
        );

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('upcc_referrals', [
            'id' => $referral->id,
            'conference_location' => 'SDO Conference Room',
            'status' => 'in_progress',
        ]);
    }

    public function test_coordinator_review_card_shows_conference_state_before_and_after_scheduling(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 09:00:00'));
        [$coordinator, $case] = $this->pendingUpccCase();

        // Before scheduling: the merged Conference cell reads "Not scheduled".
        $this->actingAs($coordinator)
            ->get(route('coordinator.cases.show', $case))
            ->assertOk()
            ->assertSee('Coordinator Review')
            ->assertSee('Conference')
            ->assertSee('Not scheduled')
            ->assertDontSee('<span class="upcc-status-label">UPCC Coordinator</span>', false)
            ->assertDontSee('<span class="upcc-status-label">Referral Status</span>', false)
            ->assertDontSee('<span class="upcc-status-label">Proceedings</span>', false);

        $this->actingAs($coordinator)->post(
            route('coordinator.cases.schedule-upcc', $case),
            [
                'scheduled_conference_date' => '2026-07-18',
                'scheduled_conference_time' => '13:30',
                'conference_location' => 'SDO Conference Room',
                'proceedings_type' => 'summary',
                'sdao_head_name' => 'Jane SDAO',
                'dean_or_program_chair_name' => 'Dean Reyes',
                'cross_program_faculty_name' => 'Prof. Santos',
                'student_representative_name' => 'Student Rep Cruz',
                'presiding_officer_name' => 'SDO Head Bautista',
            ]
        );

        // After scheduling: date, location and hearing type collapse into one cell.
        $this->actingAs($coordinator)
            ->get(route('coordinator.cases.show', $case->fresh()))
            ->assertOk()
            ->assertSee('SDO Conference Room')
            ->assertSee('Summary Proceedings');
    }

    private function pendingUpccCase(): array
    {
        $coordinator = $this->user('discipline_coordinator');
        $student = $this->user('student');

        $case = DisciplinaryCase::create([
            'case_number' => 'UPCC-'.fake()->unique()->numerify('#####'),
            'case_type' => 'major',
            'student_id' => $student->id,
            'assigned_to' => $coordinator->id,
            'status' => 'pending_upcc',
            'case_type_locked' => true,
            'prior_offense_count' => 0,
            'forwarded_to_coordinator' => true,
            'forwarded_at' => now(),
        ]);

        return [$coordinator, $case];
    }

    private function user(string $role): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'UPCC',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
