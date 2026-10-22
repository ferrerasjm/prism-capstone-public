<?php

declare(strict_types=1);

namespace Tests\Unit\MobileUniversityServiceTracking;

use App\Models\CaseResolution;
use App\Models\DisciplinaryCase;
use App\Models\Sanction;
use App\Models\User;
use App\Services\USAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Module 7 - Mobile University Service Tracking
 * Component: QR Attendance Session Close and Completion Rollup
 *            (App\Services\USAssignmentService::openUsSession / closeUsSession)
 * Test Cycle No. 1 - 5 test cases.
 *
 * The mobile supervisor app opens a session on time-in and closes it on
 * time-out; the rendered hours are computed from the matched instants
 * rather than trusted from the client, and completion is derived from the
 * cumulative total.
 */
class UsAttendanceSessionCloseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Scenario 1 - Normal: opening a session records the time-in with no computed hours yet. */
    public function test_opening_a_session_records_the_time_in_with_no_hours_yet(): void
    {
        [$assignment, $supervisor] = $this->assignment(12.0);
        $timeIn = Carbon::parse('2026-08-10 08:00:00');

        $log = (new USAssignmentService)->openUsSession($assignment, [
            'occurred_at' => $timeIn,
            'totp_verified' => true,
        ], $supervisor->id);

        $this->assertSame('08:00', $log->time_in);
        $this->assertNull($log->hours_rendered);
        $this->assertSame('qr_scan', $log->log_method);
        $this->assertSame('pending', $assignment->fresh()->status);
    }

    /** Scenario 2 - Normal: closing the session computes hours from the matched instants. */
    public function test_closing_the_session_computes_hours_from_the_matched_instants(): void
    {
        [$assignment, $supervisor] = $this->assignment(12.0);
        $service = new USAssignmentService;
        $open = $service->openUsSession($assignment, [
            'occurred_at' => Carbon::parse('2026-08-10 08:00:00'),
            'totp_verified' => true,
        ], $supervisor->id);

        $log = $service->closeUsSession($assignment, $open, [
            'occurred_at' => Carbon::parse('2026-08-10 11:30:00'),
            'totp_verified' => true,
        ], $supervisor->id);

        $this->assertSame('3.50', (string) $log->hours_rendered);
        $this->assertSame('11:30', $log->time_out);
        $this->assertTrue($log->totp_verified);
    }

    /** Scenario 3 - Boundary: an assignment moves to in_progress once partial hours are logged. */
    public function test_the_assignment_moves_to_in_progress_after_partial_hours(): void
    {
        [$assignment, $supervisor] = $this->assignment(12.0);
        $service = new USAssignmentService;
        $open = $service->openUsSession($assignment, [
            'occurred_at' => Carbon::parse('2026-08-10 08:00:00'),
        ], $supervisor->id);

        $service->closeUsSession($assignment, $open, [
            'occurred_at' => Carbon::parse('2026-08-10 11:00:00'),
        ], $supervisor->id);

        $this->assertSame('in_progress', $assignment->fresh()->status);
        $this->assertSame('3.00', (string) $assignment->fresh()->hours_rendered);
    }

    /** Scenario 4 - Normal: reaching the required hours marks the assignment and its sanction completed. */
    public function test_reaching_the_required_hours_completes_the_assignment_and_sanction(): void
    {
        [$assignment, $supervisor, $sanction] = $this->assignmentWithSanction(6.0);
        $service = new USAssignmentService;
        $open = $service->openUsSession($assignment, [
            'occurred_at' => Carbon::parse('2026-08-10 08:00:00'),
        ], $supervisor->id);

        $service->closeUsSession($assignment, $open, [
            'occurred_at' => Carbon::parse('2026-08-10 14:00:00'),
        ], $supervisor->id);

        $this->assertSame('completed', $assignment->fresh()->status);
        $this->assertSame('completed', $sanction->fresh()->status);
        $this->assertNotNull($sanction->fresh()->completed_at);
    }

    /**
     * Scenario 5b - Boundary: scan instants a few seconds past a minute
     * boundary must not make the displayed Time In/Time Out subtract to a
     * different number of minutes than the credited Hours.
     */
    public function test_a_session_off_the_minute_boundary_stays_consistent_with_displayed_times(): void
    {
        [$assignment, $supervisor] = $this->assignment(12.0);
        $service = new USAssignmentService;
        $open = $service->openUsSession($assignment, [
            'occurred_at' => Carbon::parse('2026-08-10 07:08:20'),
        ], $supervisor->id);

        $log = $service->closeUsSession($assignment, $open, [
            'occurred_at' => Carbon::parse('2026-08-10 07:14:50'),
        ], $supervisor->id);

        $this->assertSame('07:08', $open->fresh()->time_in);
        $this->assertSame('07:15', $log->time_out);
        $this->assertSame('0.12', (string) $log->hours_rendered);
    }

    /**
     * Scenario 5c - Boundary: rounding Time In and Time Out independently can
     * drift the credited duration by up to a minute versus what actually
     * elapsed (two roundings compounding instead of cancelling). The elapsed
     * time must be rounded once and Time Out derived from it, so the credited
     * Hours always matches the true elapsed duration the mobile app reports.
     */
    public function test_independently_rounding_each_endpoint_does_not_inflate_the_credited_duration(): void
    {
        [$assignment, $supervisor] = $this->assignment(12.0);
        $service = new USAssignmentService;
        $open = $service->openUsSession($assignment, [
            'occurred_at' => Carbon::parse('2026-08-10 15:33:10'),
        ], $supervisor->id);

        $log = $service->closeUsSession($assignment, $open, [
            'occurred_at' => Carbon::parse('2026-08-10 16:19:38'),
        ], $supervisor->id);

        $this->assertSame('15:33', $open->fresh()->time_in);
        $this->assertSame('16:19', $log->time_out);
        $this->assertSame('0.77', (string) $log->hours_rendered);
    }

    /** Scenario 5 - Boundary: a partially-verified session is recorded as not fully verified. */
    public function test_a_partially_verified_session_is_recorded_as_not_fully_verified(): void
    {
        [$assignment, $supervisor] = $this->assignment(12.0);
        $service = new USAssignmentService;
        $open = $service->openUsSession($assignment, [
            'occurred_at' => Carbon::parse('2026-08-10 08:00:00'),
            'totp_verified' => true,
        ], $supervisor->id);

        $log = $service->closeUsSession($assignment, $open, [
            'occurred_at' => Carbon::parse('2026-08-10 09:00:00'),
            'totp_verified' => false,
        ], $supervisor->id);

        $this->assertFalse($log->totp_verified);
    }

    /** @return array{0: \App\Models\UsAssignment, 1: User} */
    private function assignment(float $requiredHours): array
    {
        [$assignment, $supervisor] = $this->assignmentWithSanction($requiredHours);

        return [$assignment, $supervisor];
    }

    /** @return array{0: \App\Models\UsAssignment, 1: User, 2: Sanction} */
    private function assignmentWithSanction(float $requiredHours): array
    {
        static $sequence = 0;
        $sequence++;

        $student = User::create([
            'last_name' => 'Torres',
            'first_name' => 'Serving'.$sequence,
            'email' => "us.session.student{$sequence}@students.nu-clark.edu.ph",
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $supervisor = User::create([
            'last_name' => 'Ocampo',
            'first_name' => 'Supervising'.$sequence,
            'email' => "us.session.supervisor{$sequence}@nu-clark.edu.ph",
            'password' => Hash::make('password'),
            'role' => 'us_supervisor',
            'status' => 'active',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-2026-77'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
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
            'us_hours_assigned' => $requiredHours,
            'resolved_at' => now(),
            'appeal_deadline' => now()->addDays(5)->toDateString(),
            'appeal_filed' => false,
            'created_at' => now(),
        ]);

        $sanction = Sanction::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'sanction_type' => 'university_service',
            'status' => 'pending',
        ]);

        $assignment = \App\Models\UsAssignment::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
            'office_name' => 'Learning Resource Center',
            'total_hours_required' => $requiredHours,
            'hours_rendered' => 0,
            'start_date' => '2026-08-01',
            'target_completion_date' => '2026-08-31',
            'status' => 'pending',
            'totp_secret' => encrypt('placeholder-secret'),
            'totp_secret_generated_at' => now(),
        ]);

        return [$assignment, $supervisor, $sanction];
    }
}
