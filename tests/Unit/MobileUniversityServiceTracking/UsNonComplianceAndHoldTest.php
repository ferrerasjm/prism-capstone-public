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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Module 7 - Mobile University Service Tracking
 * Component: Non-Compliance and Registration Hold Rules
 *            (App\Services\USAssignmentService::markNonCompliant /
 *             checkAndLiftHold / flagOverdueAssignments)
 * Test Cycle No. 1 - 5 test cases.
 *
 * A student who misses a University Service obligation is flagged
 * non-compliant and a registration hold is raised; the hold is lifted
 * automatically only once every outstanding obligation is cleared.
 */
class UsNonComplianceAndHoldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->seedRiskWeights();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Scenario 1 - Normal: marking non-compliant updates the assignment and its sanction together. */
    public function test_marking_non_compliant_updates_the_assignment_and_its_sanction_together(): void
    {
        [$assignment, $sanction, $student, $staff] = $this->assignment();

        (new USAssignmentService)->markNonCompliant($assignment, 'Missed target completion date.', $staff->id);

        $this->assertSame('non_compliant', $assignment->fresh()->status);
        $this->assertSame('non_compliant', $sanction->fresh()->status);
        $this->assertSame('Missed target completion date.', $sanction->fresh()->notes);
    }

    /** Scenario 2 - Normal: non-compliance raises a registration hold for the student. */
    public function test_non_compliance_raises_a_registration_hold(): void
    {
        [$assignment, , $student, $staff] = $this->assignment();

        (new USAssignmentService)->markNonCompliant($assignment, 'No hours logged in 30 days.', $staff->id);

        $this->assertTrue(
            StudentMonitoringFlag::where('student_id', $student->id)
                ->where('flag_type', 'registration_hold')
                ->where('is_active', true)
                ->exists()
        );
    }

    /** Scenario 3 - Boundary: an already non-compliant assignment does not raise a duplicate hold. */
    public function test_an_already_non_compliant_assignment_does_not_raise_a_duplicate_hold(): void
    {
        [$assignment, , $student, $staff] = $this->assignment();
        $service = new USAssignmentService;

        $service->markNonCompliant($assignment, 'First lapse.', $staff->id);
        $service->markNonCompliant($assignment->fresh(), 'Second call, already non-compliant.', $staff->id);

        $this->assertSame(
            1,
            StudentMonitoringFlag::where('student_id', $student->id)
                ->where('flag_type', 'registration_hold')
                ->count()
        );
    }

    /** Scenario 4 - Normal: the hold lifts once no active obligation remains for the student. */
    public function test_the_hold_lifts_once_no_active_obligation_remains(): void
    {
        [$assignment, $sanction, $student, $staff] = $this->assignment();
        $service = new USAssignmentService;
        $service->markNonCompliant($assignment, 'Missed deadline.', $staff->id);
        $sanction->fresh()->update(['status' => 'completed']);

        $service->checkAndLiftHold($student->id);

        $flag = StudentMonitoringFlag::where('student_id', $student->id)
            ->where('flag_type', 'registration_hold')
            ->latest('id')
            ->first();

        $this->assertFalse($flag->is_active);
        $this->assertNotNull($flag->deactivated_at);
    }

    /** Scenario 5 - Failure: an outstanding obligation elsewhere keeps the hold in place. */
    public function test_an_outstanding_obligation_elsewhere_keeps_the_hold_in_place(): void
    {
        [$assignment, , $student, $staff] = $this->assignment();
        (new USAssignmentService)->markNonCompliant($assignment, 'Missed deadline.', $staff->id);

        // A second, unrelated sanction for the same student is still non-compliant.
        Sanction::create([
            'student_id' => $student->id,
            'sanction_type' => 'discipline_education',
            'status' => 'non_compliant',
        ]);

        (new USAssignmentService)->checkAndLiftHold($student->id);

        $this->assertTrue(
            StudentMonitoringFlag::where('student_id', $student->id)
                ->where('flag_type', 'registration_hold')
                ->where('is_active', true)
                ->exists()
        );
    }

    public function test_missed_deadlines_notify_once_without_non_compliance_or_a_hold(): void
    {
        Carbon::setTestNow('2026-08-01 08:00:00');
        [$assignment, $sanction, $student, $staff] = $this->assignment();
        $assignment->update(['assigned_by' => $staff->id]);
        $service = new USAssignmentService;

        $this->assertSame(1, $service->flagOverdueAssignments());
        $this->assertSame(0, $service->flagOverdueAssignments());
        $this->assertSame('in_progress', $assignment->fresh()->status);
        $this->assertSame('in_progress', $sanction->fresh()->status);
        $this->assertNotNull($assignment->fresh()->deadline_review_flagged_at);
        $this->assertFalse(StudentMonitoringFlag::where('student_id', $student->id)
            ->where('flag_type', 'registration_hold')->exists());
        Notification::assertSentToTimes($staff, \App\Notifications\UsDeadlineReviewNotification::class, 1);

        $service->extendDeadline($assignment->fresh(), '2026-08-15', 'The receiving office was unavailable.', $staff->id);
        $this->assertNull($assignment->fresh()->deadline_review_flagged_at);
        $this->assertSame(0, $service->flagOverdueAssignments());
        Carbon::setTestNow('2026-08-16 08:00:00');
        $this->assertSame(1, $service->flagOverdueAssignments());
        Notification::assertSentToTimes($staff, \App\Notifications\UsDeadlineReviewNotification::class, 2);
    }

    public function test_completed_and_not_yet_due_assignments_do_not_need_review(): void
    {
        Carbon::setTestNow('2026-07-31 08:00:00');
        [$assignment, , , $staff] = $this->assignment();
        $assignment->update(['assigned_by' => $staff->id]);
        $service = new USAssignmentService;
        $this->assertSame(0, $service->flagOverdueAssignments());
        $assignment->update(['status' => 'completed']);
        Carbon::setTestNow('2026-08-01 08:00:00');
        $this->assertSame(0, $service->flagOverdueAssignments());
        Notification::assertNothingSent();
    }

    public function test_student_deadline_notifications_are_once_per_deadline_and_reset_on_extension(): void
    {
        [$assignment, , $student, $staff] = $this->assignment();
        $service = new USAssignmentService;
        Carbon::setTestNow('2026-07-29 06:00:00');
        $this->assertSame(0, $service->sendStudentDeadlineNotifications());
        Carbon::setTestNow('2026-07-30 06:00:00');
        $this->assertSame(1, $service->sendStudentDeadlineNotifications());
        $this->assertSame(0, $service->sendStudentDeadlineNotifications());
        Notification::assertSentTo($student, \App\Notifications\UniversityServiceStatusNotification::class,
            fn ($notification, $channels) => $notification->toDatabase($student)['kind'] === 'university_service_deadline_reminder'
                && $channels === ['database', 'mail']);
        Carbon::setTestNow('2026-07-31 06:00:00');
        $this->assertSame(0, $service->sendStudentDeadlineNotifications());
        Carbon::setTestNow('2026-08-01 06:00:00');
        $this->assertSame(1, $service->sendStudentDeadlineNotifications());
        $this->assertSame(0, $service->sendStudentDeadlineNotifications());
        Notification::assertSentTo($student, \App\Notifications\UniversityServiceStatusNotification::class,
            fn ($notification) => $notification->toDatabase($student)['kind'] === 'university_service_deadline_missed');
        $this->assertSame('in_progress', $assignment->fresh()->status);
        $this->assertFalse(StudentMonitoringFlag::where('student_id', $student->id)->exists());

        $service->extendDeadline($assignment->fresh(), '2026-08-10', 'The receiving office was unavailable.', $staff->id);
        $this->assertNull($assignment->fresh()->student_deadline_reminded_at);
        $this->assertNull($assignment->fresh()->student_deadline_missed_notified_at);
        Carbon::setTestNow('2026-08-09 06:00:00');
        $this->assertSame(1, $service->sendStudentDeadlineNotifications());
        Carbon::setTestNow('2026-08-11 06:00:00');
        $this->assertSame(1, $service->sendStudentDeadlineNotifications());
    }

    public function test_completed_and_non_compliant_services_do_not_send_deadline_notices(): void
    {
        [$assignment] = $this->assignment();
        Carbon::setTestNow('2026-08-01 06:00:00');
        foreach (['completed', 'non_compliant'] as $status) {
            $assignment->update(['status' => $status]);
            $this->assertSame(0, (new USAssignmentService)->sendStudentDeadlineNotifications());
        }
        Notification::assertNothingSent();
    }

    public function test_registration_hold_notifies_student_only_when_newly_raised(): void
    {
        [, , $student, $staff] = $this->assignment();
        $service = new \App\Services\RegistrationHoldService;
        $service->raise($student->id, 'Officer confirmed non-compliance.', $staff->id);
        $service->raise($student->id, 'Repeated request.', $staff->id);
        Notification::assertSentToTimes($student, \App\Notifications\RegistrationHoldNotification::class, 1);
        Notification::assertSentTo($student, \App\Notifications\RegistrationHoldNotification::class,
            fn ($notification, $channels) => $channels === ['database', 'mail']
                && $notification->toDatabase($student)['url'] === route('student.sanctions.index', [], false));
        $service->liftManually($student->id, 'Extension approved.', $staff->id);
        $service->raise($student->id, 'A new confirmed lapse.', $staff->id);
        Notification::assertSentToTimes($student, \App\Notifications\RegistrationHoldNotification::class, 2);
    }

    /** @return array{0: UsAssignment, 1: Sanction, 2: User, 3: User} */
    private function assignment(): array
    {
        static $sequence = 0;
        $sequence++;

        $student = User::create([
            'last_name' => 'Torres',
            'first_name' => 'Hold'.$sequence,
            'email' => "hold.student{$sequence}@students.nu-clark.edu.ph",
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $staff = User::create([
            'last_name' => 'Reyes',
            'first_name' => 'Flagging'.$sequence,
            'email' => "hold.staff{$sequence}@nu-clark.edu.ph",
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-2026-78'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
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
            'hours_rendered' => 4.0,
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
