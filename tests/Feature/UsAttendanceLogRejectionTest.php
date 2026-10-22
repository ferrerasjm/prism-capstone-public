<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseResolution;
use App\Models\DisciplinaryCase;
use App\Models\Sanction;
use App\Models\StudentProfile;
use App\Models\UniversityServiceOffice;
use App\Models\UsAssignment;
use App\Models\UsAttendanceLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A supervisor's way to void a session they've reviewed and found invalid —
 * raised directly by a supervisor interviewed during UAT, who wanted a way
 * to reject a logged session when the student didn't actually perform the
 * tasks, or didn't do anything during the time-in/time-out window at all.
 */
class UsAttendanceLogRejectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_covering_supervisor_can_reject_a_completed_session(): void
    {
        [$supervisor, $assignment, $log] = $this->fixture();

        $response = $this->actingAs($supervisor)->post(
            route('supervisor.us.reject-log', [$assignment, $log]),
            ['rejection_reason' => 'Student was not present during this window at all.']
        );

        $response->assertRedirect(route('supervisor.us.show', $assignment));
        $response->assertSessionHas('success');

        $log->refresh();
        $this->assertTrue($log->isRejected());
        $this->assertSame($supervisor->id, $log->rejected_by);
        $this->assertSame(
            'Student was not present during this window at all.',
            $log->rejection_reason
        );

        // The log stays on record — it's voided, not deleted.
        $this->assertDatabaseHas('us_attendance_logs', ['id' => $log->id]);
    }

    public function test_assignment_page_renders_a_rejected_log_with_its_reason(): void
    {
        [$supervisor, $assignment, $log] = $this->fixture();

        $this->actingAs($supervisor)->post(
            route('supervisor.us.reject-log', [$assignment, $log]),
            ['rejection_reason' => 'Verified with the office — no work was actually done.']
        );

        $response = $this->actingAs($supervisor)->get(route('supervisor.us.show', $assignment));

        $response->assertOk();
        $response->assertSee('Rejected');
        $response->assertSee('Verified with the office', false);
        // The reject button/modal must not render again for an already-rejected log.
        $response->assertDontSee('rejectLogModal'.$log->id, false);
    }

    public function test_rejecting_excludes_the_session_from_rendered_hours(): void
    {
        [$supervisor, $assignment, $log] = $this->fixture();

        $this->assertSame(2.0, (float) $assignment->hours_rendered);

        $this->actingAs($supervisor)->post(
            route('supervisor.us.reject-log', [$assignment, $log]),
            ['rejection_reason' => 'Tasks performed were fabricated.']
        );

        $assignment->refresh();
        $this->assertSame(0.0, (float) $assignment->hours_rendered);
        $this->assertSame('pending', $assignment->status);

        $sanction = Sanction::where('case_resolution_id', $assignment->case_resolution_id)
            ->where('sanction_type', 'university_service')
            ->first();
        $this->assertSame('pending', $sanction->status);
    }

    public function test_rejecting_one_of_two_sessions_retracts_a_completed_assignment_to_in_progress(): void
    {
        // A 5h session (kept) plus a 3h session (to be rejected) together
        // complete an 8h requirement; rejecting only the 3h one should drop
        // the assignment to in_progress at 5h, not wipe it back to pending.
        [$supervisor, $assignment, $keptLog] = $this->fixture(hoursRendered: 5, totalRequired: 8);

        $rejectedLog = UsAttendanceLog::create([
            'us_assignment_id' => $assignment->id,
            'student_id' => $assignment->student_id,
            'date_of_service' => today()->subDay(),
            'time_in' => '09:00:00',
            'time_out' => '12:00:00',
            'hours_rendered' => 3,
            'tasks_performed' => 'Session that turned out to be fabricated.',
            'log_method' => 'manual_web',
            'logged_by' => $supervisor->id,
        ]);
        $assignment->update(['hours_rendered' => 8, 'status' => 'completed']);

        $this->actingAs($supervisor)->post(
            route('supervisor.us.reject-log', [$assignment, $rejectedLog]),
            ['rejection_reason' => 'This session never actually happened.']
        );

        $assignment->refresh();
        $this->assertSame('in_progress', $assignment->status);
        $this->assertSame(5.0, (float) $assignment->hours_rendered);
        $this->assertFalse($keptLog->fresh()->isRejected());
    }

    public function test_rejecting_notifies_the_student(): void
    {
        [$supervisor, $assignment, $log, $student] = $this->fixture();

        $this->actingAs($supervisor)->post(
            route('supervisor.us.reject-log', [$assignment, $log]),
            ['rejection_reason' => 'No evidence the tasks were completed.']
        );

        $this->assertTrue(
            DatabaseNotification::where('notifiable_id', $student->id)
                ->where('data->kind', 'university_service_session_rejected')
                ->exists()
        );
    }

    public function test_a_supervisor_who_does_not_cover_the_assignment_cannot_reject(): void
    {
        [, $assignment, $log] = $this->fixture();

        $outsider = User::create([
            'first_name' => 'Outside',
            'last_name' => 'Supervisor',
            'email' => 'reject-outsider@nu-clark.edu.ph',
            'institutional_id' => 'US-REJECT-OUTSIDER',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor',
            'office_name' => 'A Different Office',
            'status' => 'active',
        ]);

        $response = $this->actingAs($outsider)->post(
            route('supervisor.us.reject-log', [$assignment, $log]),
            ['rejection_reason' => 'Trying to reject someone else\'s log.']
        );

        $response->assertForbidden();
        $this->assertFalse($log->fresh()->isRejected());
    }

    public function test_a_log_cannot_be_rejected_twice(): void
    {
        [$supervisor, $assignment, $log] = $this->fixture();

        $this->actingAs($supervisor)->post(
            route('supervisor.us.reject-log', [$assignment, $log]),
            ['rejection_reason' => 'First rejection.']
        );

        $firstRejectedAt = $log->fresh()->rejected_at;

        $response = $this->actingAs($supervisor)->post(
            route('supervisor.us.reject-log', [$assignment, $log]),
            ['rejection_reason' => 'Second attempt should not overwrite the first.']
        );

        $response->assertRedirect(route('supervisor.us.show', $assignment));
        $response->assertSessionHas('error');
        $this->assertTrue($firstRejectedAt->equalTo($log->fresh()->rejected_at));
        $this->assertSame('First rejection.', $log->fresh()->rejection_reason);
    }

    public function test_a_no_show_log_cannot_be_rejected(): void
    {
        [$supervisor, $assignment] = $this->fixture();

        $noShow = UsAttendanceLog::create([
            'us_assignment_id' => $assignment->id,
            'student_id' => $assignment->student_id,
            'date_of_service' => today(),
            'hours_rendered' => 0,
            'log_method' => 'no_show',
            'logged_by' => $supervisor->id,
            'notes' => 'Student did not show up for the scheduled session.',
        ]);

        $response = $this->actingAs($supervisor)->post(
            route('supervisor.us.reject-log', [$assignment, $noShow]),
            ['rejection_reason' => 'Nothing to reject here.']
        );

        $response->assertSessionHas('error');
        $this->assertFalse($noShow->fresh()->isRejected());
    }

    public function test_an_open_session_without_a_time_out_cannot_be_rejected(): void
    {
        [$supervisor, $assignment] = $this->fixture();

        $openLog = UsAttendanceLog::create([
            'us_assignment_id' => $assignment->id,
            'student_id' => $assignment->student_id,
            'date_of_service' => today(),
            'time_in' => '09:00:00',
            'time_out' => null,
            'hours_rendered' => null,
            'log_method' => 'qr_scan',
            'totp_verified' => true,
            'logged_by' => $supervisor->id,
        ]);

        $response = $this->actingAs($supervisor)->post(
            route('supervisor.us.reject-log', [$assignment, $openLog]),
            ['rejection_reason' => 'Session is still open.']
        );

        $response->assertSessionHas('error');
        $this->assertFalse($openLog->fresh()->isRejected());
    }

    public function test_rejection_reason_is_required_and_has_a_minimum_length(): void
    {
        [$supervisor, $assignment, $log] = $this->fixture();

        $this->actingAs($supervisor)
            ->post(route('supervisor.us.reject-log', [$assignment, $log]), ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($supervisor)
            ->post(route('supervisor.us.reject-log', [$assignment, $log]), ['rejection_reason' => 'short'])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertFalse($log->fresh()->isRejected());
    }

    public function test_a_log_belonging_to_a_different_assignment_returns_not_found(): void
    {
        [$supervisor, $assignment, $log] = $this->fixture();
        [, $otherAssignment] = $this->fixture(emailPrefix: 'reject-other-');

        $response = $this->actingAs($supervisor)->post(
            route('supervisor.us.reject-log', [$otherAssignment, $log]),
            ['rejection_reason' => 'Mismatched assignment/log pair.']
        );

        $response->assertNotFound();
        $this->assertFalse($log->fresh()->isRejected());
    }

    /**
     * @return array{0: User, 1: UsAssignment, 2: UsAttendanceLog, 3: User}
     */
    private function fixture(
        float $hoursRendered = 2,
        float $totalRequired = 8,
        string $status = 'in_progress',
        string $emailPrefix = 'reject-'
    ): array {
        UniversityServiceOffice::query()->firstOrCreate(
            ['name' => 'Learning Resource Center'],
            ['is_active' => true]
        );

        $officer = User::create([
            'first_name' => 'Case',
            'last_name' => 'Officer',
            'email' => $emailPrefix.'officer@example.test',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $supervisor = User::create([
            'first_name' => 'Reviewing',
            'last_name' => 'Supervisor',
            'email' => $emailPrefix.'supervisor@nu-clark.edu.ph',
            'institutional_id' => strtoupper($emailPrefix).'SUP',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor',
            'office_name' => 'Learning Resource Center',
            'status' => 'active',
        ]);

        $student = User::create([
            'first_name' => 'Rejected',
            'last_name' => 'Student',
            'email' => $emailPrefix.'student@students.nu-clark.edu.ph',
            'institutional_id' => '2099-'.strtoupper($emailPrefix).'01',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => '2099-'.strtoupper($emailPrefix).'01',
            'program' => 'BS Information Technology',
            'year_level' => '3',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'CASE-'.strtoupper($emailPrefix).'001',
            'student_id' => $student->id,
            'case_type' => 'major',
            'assigned_to' => $officer->id,
            'status' => 'resolved',
            'prior_offense_count' => 0,
        ]);

        $resolution = CaseResolution::create([
            'case_id' => $case->id,
            'resolution_level' => 'sdo_level',
            'intervention_category' => 2,
            'us_hours_assigned' => $totalRequired,
            'resolved_by' => $officer->id,
            'resolved_at' => now(),
            'appeal_deadline' => now()->addDays(5),
            'appeal_filed' => false,
            'created_at' => now(),
        ]);

        $assignment = UsAssignment::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
            'assigned_by' => $officer->id,
            'office_name' => 'Learning Resource Center',
            'total_hours_required' => $totalRequired,
            'hours_rendered' => $hoursRendered,
            'start_date' => today()->subWeek(),
            'target_completion_date' => today()->addWeek(),
            'status' => $status,
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'totp_secret_generated_at' => now(),
        ]);

        Sanction::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'sanction_type' => 'university_service',
            'status' => $status,
            'due_date' => $assignment->target_completion_date,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $log = UsAttendanceLog::create([
            'us_assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'date_of_service' => today(),
            'time_in' => '09:00:00',
            'time_out' => today()->setTime(9 + (int) $hoursRendered, 0)->format('H:i:s'),
            'hours_rendered' => $hoursRendered,
            'tasks_performed' => 'Reported tasks that were later found to be untrue.',
            'log_method' => 'manual_web',
            'logged_by' => $supervisor->id,
            'verified_by' => $supervisor->id,
            'verified_at' => now(),
        ]);

        return [$supervisor, $assignment, $log, $student];
    }
}
