<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UniversityServiceOffice;
use App\Models\UsAssignment;
use App\Models\UsAttendanceLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The mobile equivalent of Supervisor\USAssignmentController::rejectLog() —
 * lets an officer void a completed session from the app after review, the
 * same action already available on the web supervisor portal.
 */
class MobileUsAssignmentLogsRejectTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_logs_lists_rejectable_and_rejected_state(): void
    {
        [$assignment, $log, , $token] = $this->fixture();

        $response = $this->withToken($token)
            ->getJson("/api/mobile/us/assignments/{$assignment->id}/logs")
            ->assertOk();

        $response->assertJsonPath('data.0.id', (string) $log->id);
        $response->assertJsonPath('data.0.is_rejectable', true);
        $response->assertJsonPath('data.0.is_rejected', false);
    }

    public function test_covering_officer_can_reject_a_completed_session(): void
    {
        [$assignment, $log, , $token] = $this->fixture();

        $response = $this->withToken($token)->postJson(
            "/api/mobile/us/assignments/{$assignment->id}/logs/{$log->id}/reject",
            ['rejection_reason' => 'Student was not present during this window at all.']
        );

        $response->assertOk();
        $response->assertJsonPath('log_id', $log->id);
        $response->assertJsonPath('assignment.assignmentStatus', 'pending');

        $log->refresh();
        $this->assertTrue($log->isRejected());
        $this->assertSame(
            'Student was not present during this window at all.',
            $log->rejection_reason
        );
    }

    public function test_assignment_logs_reflects_rejection_afterwards(): void
    {
        [$assignment, $log, , $token] = $this->fixture();

        $this->withToken($token)->postJson(
            "/api/mobile/us/assignments/{$assignment->id}/logs/{$log->id}/reject",
            ['rejection_reason' => 'Verified with the office — no work was actually done.']
        );

        $response = $this->withToken($token)
            ->getJson("/api/mobile/us/assignments/{$assignment->id}/logs")
            ->assertOk();

        $response->assertJsonPath('data.0.is_rejected', true);
        $response->assertJsonPath('data.0.is_rejectable', false);
        $response->assertJsonPath(
            'data.0.rejection_reason',
            'Verified with the office — no work was actually done.'
        );
    }

    public function test_a_non_covering_officer_cannot_reject(): void
    {
        [$assignment, $log] = $this->fixture();

        UniversityServiceOffice::query()->firstOrCreate(
            ['name' => 'A Different Office'],
            ['is_active' => true]
        );

        $outsider = User::create([
            'first_name' => 'Outside',
            'last_name' => 'Supervisor',
            'email' => 'mobile-reject-outsider@nu-clark.edu.ph',
            'institutional_id' => 'US-MOBILE-REJECT-OUTSIDER',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor',
            'office_name' => 'A Different Office',
            'status' => 'active',
        ]);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'mobile-reject-outsider@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ])->json('access_token');

        $response = $this->withToken($token)->postJson(
            "/api/mobile/us/assignments/{$assignment->id}/logs/{$log->id}/reject",
            ['rejection_reason' => 'Trying to reject someone else\'s log.']
        );

        $response->assertStatus(403);
        $response->assertJsonPath('error_code', 'not_authorized');
        $this->assertFalse($log->fresh()->isRejected());
    }

    public function test_a_log_belonging_to_a_different_assignment_returns_not_found(): void
    {
        [$assignment, $log, $supervisor, $token] = $this->fixture();

        $otherStudent = User::create([
            'first_name' => 'Other',
            'last_name' => 'Student',
            'email' => 'mobile-reject-other-student@students.nu-clark.edu.ph',
            'institutional_id' => '2099-MOBILEREJECTOTHER',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        // Same office as $assignment (so the covering supervisor still passes
        // isCoveredBy) but a distinct assignment — the log belongs to $assignment,
        // not this one.
        $otherAssignment = UsAssignment::create([
            'case_resolution_id' => null,
            'student_id' => $otherStudent->id,
            'supervisor_id' => $supervisor->id,
            'assigned_by' => $supervisor->id,
            'office_name' => 'Learning Resource Center',
            'total_hours_required' => 8,
            'hours_rendered' => 0,
            'start_date' => today()->subWeek(),
            'target_completion_date' => today()->addWeek(),
            'status' => 'in_progress',
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'totp_secret_generated_at' => now(),
        ]);

        $response = $this->withToken($token)->postJson(
            "/api/mobile/us/assignments/{$otherAssignment->id}/logs/{$log->id}/reject",
            ['rejection_reason' => 'Mismatched assignment/log pair.']
        );

        $response->assertStatus(404);
        $response->assertJsonPath('error_code', 'not_found');
        $this->assertFalse($log->fresh()->isRejected());
    }

    public function test_a_log_cannot_be_rejected_twice(): void
    {
        [$assignment, $log, , $token] = $this->fixture();

        $this->withToken($token)->postJson(
            "/api/mobile/us/assignments/{$assignment->id}/logs/{$log->id}/reject",
            ['rejection_reason' => 'First rejection.']
        );

        $response = $this->withToken($token)->postJson(
            "/api/mobile/us/assignments/{$assignment->id}/logs/{$log->id}/reject",
            ['rejection_reason' => 'Second attempt should not overwrite the first.']
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'not_rejectable');
        $this->assertSame('First rejection.', $log->fresh()->rejection_reason);
    }

    public function test_an_open_session_without_a_time_out_cannot_be_rejected(): void
    {
        [$assignment, , $supervisor, $token] = $this->fixture();

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

        $response = $this->withToken($token)->postJson(
            "/api/mobile/us/assignments/{$assignment->id}/logs/{$openLog->id}/reject",
            ['rejection_reason' => 'Session is still open.']
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'not_rejectable');
        $this->assertFalse($openLog->fresh()->isRejected());
    }

    public function test_rejection_reason_is_required_and_has_a_minimum_length(): void
    {
        [$assignment, $log, , $token] = $this->fixture();

        $this->withToken($token)
            ->postJson("/api/mobile/us/assignments/{$assignment->id}/logs/{$log->id}/reject", ['rejection_reason' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rejection_reason');

        $this->withToken($token)
            ->postJson("/api/mobile/us/assignments/{$assignment->id}/logs/{$log->id}/reject", ['rejection_reason' => 'short'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rejection_reason');

        $this->assertFalse($log->fresh()->isRejected());
    }

    /**
     * @return array{0: UsAssignment, 1: UsAttendanceLog, 2: User, 3: string}
     */
    private function fixture(string $emailPrefix = 'mobile-reject-'): array
    {
        UniversityServiceOffice::query()->firstOrCreate(
            ['name' => 'Learning Resource Center'],
            ['is_active' => true]
        );

        $supervisor = User::create([
            'first_name' => 'Reviewing',
            'last_name' => 'Supervisor',
            'email' => $emailPrefix.'supervisor@nu-clark.edu.ph',
            'institutional_id' => strtoupper(str_replace('-', '', $emailPrefix)).'SUP',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor',
            'office_name' => 'Learning Resource Center',
            'status' => 'active',
        ]);

        $student = User::create([
            'first_name' => 'Rejected',
            'last_name' => 'Student',
            'email' => $emailPrefix.'student@students.nu-clark.edu.ph',
            'institutional_id' => '2099-'.strtoupper(str_replace('-', '', $emailPrefix)),
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $assignment = UsAssignment::create([
            'case_resolution_id' => null,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
            'assigned_by' => $supervisor->id,
            'office_name' => 'Learning Resource Center',
            'total_hours_required' => 8,
            'hours_rendered' => 2,
            'start_date' => today()->subWeek(),
            'target_completion_date' => today()->addWeek(),
            'status' => 'in_progress',
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'totp_secret_generated_at' => now(),
        ]);

        $log = UsAttendanceLog::create([
            'us_assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'date_of_service' => today(),
            'time_in' => '09:00:00',
            'time_out' => '11:00:00',
            'hours_rendered' => 2,
            'tasks_performed' => 'Reported tasks that were later found to be untrue.',
            'log_method' => 'manual_web',
            'logged_by' => $supervisor->id,
            'verified_by' => $supervisor->id,
            'verified_at' => now(),
        ]);

        $token = $this->postJson('/api/auth/login', [
            'email' => $emailPrefix.'supervisor@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ])->json('access_token');

        return [$assignment, $log, $supervisor, $token];
    }
}
