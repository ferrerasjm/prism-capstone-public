<?php

declare(strict_types=1);

namespace Tests\Unit\MobileUniversityServiceTracking;

use App\Models\CaseResolution;
use App\Models\DisciplinaryCase;
use App\Models\RiskFactorWeight;
use App\Models\Sanction;
use App\Models\StudentMonitoringFlag;
use App\Models\UsAssignment;
use App\Models\User;
use App\Services\USAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Module 7 - Mobile University Service Tracking
 * Component: Deadline Extension (App\Services\USAssignmentService::extendDeadline)
 *
 * An officer can push a University Service target completion date out when
 * the delay isn't the student's fault (e.g. the receiving office was
 * unavailable). If the assignment had already been flagged non-compliant,
 * extending restores it to a status matching the hours actually rendered and
 * lifts the registration hold.
 */
class UsExtendDeadlineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Carbon::setTestNow('2026-08-01 08:00:00');

        Notification::fake();
        $this->seedRiskWeights();
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_extending_a_non_compliant_assignment_with_hours_rendered_restores_it_to_in_progress(): void
    {
        [$assignment, $sanction, $student, $staff] = $this->assignment(hoursRendered: 4.0);
        $service = new USAssignmentService;
        $service->markNonCompliant($assignment, 'Missed target completion date.', $staff->id);

        $service->extendDeadline(
            $assignment->fresh(),
            '2026-08-31',
            'The office had no supervisor available for the last two weeks of the term.',
            $staff->id
        );

        $this->assertSame('in_progress', $assignment->fresh()->status);
        $this->assertSame('2026-08-31', $assignment->fresh()->target_completion_date->toDateString());
        $this->assertSame('in_progress', $sanction->fresh()->status);
    }

    public function test_extending_a_non_compliant_assignment_with_no_hours_rendered_restores_it_to_pending(): void
    {
        [$assignment, , , $staff] = $this->assignment(hoursRendered: 0.0);
        $service = new USAssignmentService;
        $service->markNonCompliant($assignment, 'Missed target completion date.', $staff->id);

        $service->extendDeadline(
            $assignment->fresh(),
            '2026-08-31',
            'The office was closed for renovation through the original deadline.',
            $staff->id
        );

        $this->assertSame('pending', $assignment->fresh()->status);
    }

    public function test_extending_a_non_compliant_assignment_lifts_the_registration_hold(): void
    {
        [$assignment, , $student, $staff] = $this->assignment(hoursRendered: 4.0);
        $service = new USAssignmentService;
        $service->markNonCompliant($assignment, 'Missed target completion date.', $staff->id);

        $service->extendDeadline(
            $assignment->fresh(),
            '2026-08-31',
            'The office had no supervisor available for the last two weeks of the term.',
            $staff->id
        );

        $this->assertFalse(
            StudentMonitoringFlag::where('student_id', $student->id)
                ->where('flag_type', 'registration_hold')
                ->where('is_active', true)
                ->exists()
        );
    }

    public function test_extending_an_assignment_that_was_never_non_compliant_only_moves_the_date(): void
    {
        [$assignment, $sanction] = $this->assignment(hoursRendered: 4.0);
        $service = new USAssignmentService;

        $service->extendDeadline(
            $assignment,
            '2026-08-31',
            'The office asked to push the deadline out before it became an issue.',
            1
        );

        $this->assertSame('in_progress', $assignment->fresh()->status);
        $this->assertSame('2026-08-31', $assignment->fresh()->target_completion_date->toDateString());
        $this->assertSame('in_progress', $sanction->fresh()->status);
    }

    public function test_extension_preserves_holds_required_by_other_sanctions(): void
    {
        foreach (['discipline_education', 'non_readmission', 'exclusion', 'expulsion'] as $type) {
            [$assignment, , $student, $staff] = $this->assignment(4.0);
            $service = new USAssignmentService;
            $service->markNonCompliant($assignment, 'Missed deadline.', $staff->id);
            Sanction::create([
                'student_id' => $student->id,
                'sanction_type' => $type,
                'status' => $type === 'discipline_education' ? 'non_compliant' : 'completed',
            ]);
            $service->extendDeadline($assignment->fresh(), '2026-08-31', 'The receiving office was unavailable.', $staff->id);
            $this->assertSame('in_progress', $assignment->fresh()->status);
            $this->assertTrue(StudentMonitoringFlag::where('student_id', $student->id)
                ->where('flag_type', 'registration_hold')->where('is_active', true)->exists(), $type);
        }
    }

    public function test_past_extension_date_is_rejected_without_changing_the_assignment(): void
    {
        [$assignment, , , $staff] = $this->assignment(4.0);
        \Illuminate\Support\Carbon::setTestNow('2026-08-15');
        try {
            (new USAssignmentService)->extendDeadline($assignment, '2026-08-10', 'The receiving office was unavailable.', $staff->id);
            $this->fail('A past deadline was accepted.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('target_completion_date', $exception->errors());
        }
        $this->assertSame('2026-07-31', $assignment->fresh()->target_completion_date->toDateString());
        Notification::assertNothingSent();
    }

    public function test_direct_request_cannot_mark_completed_service_non_compliant(): void
    {
        [$assignment, $sanction, $student, $staff] = $this->assignment(12.0);
        $assignment->caseResolution->case->update(['assigned_to' => $staff->id]);
        $assignment->update(['status' => 'completed']);
        $sanction->update(['status' => 'completed']);
        $this->actingAs($staff)->post(route('officer.us.non-compliant', $assignment), [
            'reason' => 'Attempt to change a completed assignment.',
        ])->assertSessionHasErrors('status');
        $this->assertSame('completed', $assignment->fresh()->status);
        $this->assertSame('completed', $sanction->fresh()->status);
        $this->assertFalse(StudentMonitoringFlag::where('student_id', $student->id)->exists());
        Notification::assertNothingSent();
    }

    public function test_extension_request_rejects_past_dates_and_accepts_today(): void
    {
        [$assignment, , , $staff] = $this->assignment(4.0);
        $assignment->caseResolution->case->update(['assigned_to' => $staff->id]);
        \Illuminate\Support\Carbon::setTestNow('2026-08-15');
        $this->actingAs($staff)->post(route('officer.us.extend-deadline', $assignment), [
            'target_completion_date' => '2026-08-10',
            'reason' => 'The receiving office was unavailable.',
        ])->assertSessionHasErrors('target_completion_date');
        $this->assertSame('2026-07-31', $assignment->fresh()->target_completion_date->toDateString());
        $this->actingAs($staff)->post(route('officer.us.extend-deadline', $assignment), [
            'target_completion_date' => '2026-08-15',
            'reason' => 'The receiving office was unavailable.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-08-15', $assignment->fresh()->target_completion_date->toDateString());
    }

    /** @return array{0: UsAssignment, 1: Sanction, 2: User, 3: User} */
    private function assignment(float $hoursRendered): array
    {
        static $sequence = 0;
        $sequence++;

        $student = User::create([
            'last_name' => 'Torres',
            'first_name' => 'Extend'.$sequence,
            'email' => "extend.student{$sequence}@students.nu-clark.edu.ph",
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $staff = User::create([
            'last_name' => 'Reyes',
            'first_name' => 'Extending'.$sequence,
            'email' => "extend.staff{$sequence}@nu-clark.edu.ph",
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-2026-79'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
            'student_id' => $student->id,
            'case_type' => 'minor',
            'status' => 'resolved',
            'prior_offense_count' => 0,
            'escalation_flagged' => false,
            'forwarded_to_coordinator' => false,
            'void_requires_coordinator_approval' => false,
        ]);

        $resolution = CaseResolution::create([
            'case_id' => $case->id,
            'resolution_level' => 'sdo_level',
            'intervention_category' => 2,
            'us_hours_assigned' => 12.0,
            'resolved_at' => now(),
            'appeal_deadline' => now()->addDays(5)->toDateString(),
            'appeal_filed' => false,
            'created_at' => now(),
        ]);

        $sanction = Sanction::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'sanction_type' => 'university_service',
            'status' => 'in_progress',
        ]);

        $assignment = UsAssignment::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'office_name' => 'Facilities Management Office',
            'total_hours_required' => 12.0,
            'hours_rendered' => $hoursRendered,
            'start_date' => '2026-07-01',
            'target_completion_date' => '2026-07-31',
            'status' => 'in_progress',
            'totp_secret' => encrypt('placeholder-secret'),
            'totp_secret_generated_at' => now(),
        ]);

        return [$assignment, $sanction, $student, $staff];
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
