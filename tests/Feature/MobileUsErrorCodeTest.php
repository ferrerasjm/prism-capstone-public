<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UniversityServiceOffice;
use App\Models\UsAssignment;
use App\Models\UsAttendanceLog;
use App\Models\User;
use App\Services\USAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use OTPHP\TOTP;
use Tests\Support\SignsMobileAttendance;
use Tests\TestCase;

/**
 * The mobile app queues scans while offline and syncs them later, so a rejection
 * can surface minutes or hours after the supervisor scanned. It needs to tell the
 * reasons apart without matching on message text — especially the session-state
 * conflict two same-office devices can legitimately produce.
 */
class MobileUsErrorCodeTest extends TestCase
{
    use RefreshDatabase;
    use SignsMobileAttendance;

    public function test_time_in_against_an_open_session_returns_a_session_state_conflict(): void
    {
        [$assignment, $token] = $this->fixture(withOpenSession: true);

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_in',
            'occurred_at' => now()->toIso8601String(),
            'offline' => true,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'session_state_conflict')
            ->assertJsonPath('expected_event_type', 'time_out')
            // The original wording stays put for builds still matching on it.
            ->assertJsonPath('message', 'An open session already exists. Log a time-out before starting a new one.');
    }

    public function test_time_out_with_no_open_session_returns_a_session_state_conflict(): void
    {
        [$assignment, $token] = $this->fixture();

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_out',
            'occurred_at' => now()->toIso8601String(),
            'offline' => true,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'session_state_conflict')
            ->assertJsonPath('expected_event_type', 'time_in');
    }

    public function test_live_scan_without_a_totp_is_distinguishable_from_a_bad_one(): void
    {
        [$assignment, $token] = $this->fixture();

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_in',
            'totp' => null,
            'occurred_at' => now()->toIso8601String(),
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'totp_required');

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_in',
            'occurred_at' => now()->toIso8601String(),
            'totp' => '000000',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'invalid_totp');
    }

    /**
     * An offline scan using a code from before the officer regenerated the QR (e.g. because
     * it leaked) must be rejected the same as a live one -- accepting it and merely flagging
     * it for review would let the leaked code keep working as long as the device claims to
     * be offline.
     */
    public function test_an_offline_scan_with_an_invalid_totp_is_rejected_like_a_live_one(): void
    {
        [$assignment, $token] = $this->fixture();

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_in',
            'occurred_at' => now()->toIso8601String(),
            'totp' => '000000',
            'offline' => true,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'invalid_totp');

        $this->assertDatabaseCount('us_attendance_logs', 0);
    }

    public function test_offline_scan_accepts_a_stale_qr_at_the_recorded_scan_time(): void
    {
        [$assignment, $token] = $this->fixture();
        $staleCode = $this->codeAt(now()->subMinutes(4)->timestamp);

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_in',
            'occurred_at' => now()->toIso8601String(),
            'totp' => $staleCode,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'invalid_totp');

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_in',
            'occurred_at' => now()->subMinutes(4)->toIso8601String(),
            'totp' => $staleCode,
            'offline' => true,

        ])
            ->assertCreated()
            ->assertJsonPath('totpVerified', true);
    }

    public function test_offline_scan_cannot_claim_a_future_scan_time(): void
    {
        [$assignment, $token] = $this->fixture();
        $code = explode(':', app(USAssignmentService::class)->generateTotpUri($assignment))[3] ?? '';

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_in',
            'occurred_at' => now()->addMinutes(10)->toIso8601String(),
            'totp' => $code,
            'offline' => true,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'invalid_attendance_proof');
    }

    public function test_an_offline_scan_without_a_totp_is_rejected(): void
    {
        [$assignment, $token] = $this->fixture();

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_in',
            'totp' => null,
            'occurred_at' => now()->toIso8601String(),
            'offline' => true,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'totp_required');
    }

    public function test_a_stale_open_session_reports_session_too_long_on_time_out(): void
    {
        [$assignment, $token] = $this->fixture(withOpenSession: true, openedDaysAgo: 3);

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_out',
            'occurred_at' => now()->toIso8601String(),
            'offline' => true,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'session_too_long');
    }

    public function test_a_closed_assignment_reports_assignment_closed(): void
    {
        [$assignment, $token] = $this->fixture();
        $assignment->update(['status' => 'completed']);

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_in',
            'occurred_at' => now()->toIso8601String(),
            'offline' => true,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'assignment_closed');
    }

    public function test_a_replayed_queue_entry_is_marked_already_synced(): void
    {
        [$assignment, $token] = $this->fixture();

        $code = explode(':', app(USAssignmentService::class)->generateTotpUri($assignment))[3] ?? '';

        $payload = $this->signedAttendance($assignment, $token, [
            'event_type' => 'time_in',
            'occurred_at' => now()->toIso8601String(),
            'totp' => $code,

        ]);

        $this->withToken($token)
            ->postJson("/api/mobile/us/assignments/{$assignment->id}/log", $payload)
            ->assertCreated();

        // The device didn't get the first response and retries the same queue entry.
        $this->withToken($token)
            ->postJson("/api/mobile/us/assignments/{$assignment->id}/log", $payload)
            ->assertOk()
            ->assertJsonPath('error_code', 'already_synced');
    }

    public function test_a_replayed_time_out_is_already_synced_after_it_completes_the_assignment(): void
    {
        [$assignment, $token] = $this->fixture(withOpenSession: true);
        $assignment->update(['total_hours_required' => 1]);
        $assignment->openSession()->update([
            'date_of_service' => today(),
            'time_in' => now()->subMinutes(90)->format('H:i:s'),
            'client_scanned_at' => now()->subMinutes(90),
        ]);

        $code = $this->codeAt(now()->timestamp);
        $payload = $this->signedAttendance($assignment, $token, [
            'event_type' => 'time_out',
            'occurred_at' => now()->toIso8601String(),
            'totp' => $code,

            'tasks_performed' => 'Assisted students at the service desk.',
            'override_reason' => 'The service desk closed after the assigned work was completed.',
        ]);

        $this->withToken($token)
            ->postJson("/api/mobile/us/assignments/{$assignment->id}/log", $payload)
            ->assertCreated();

        $this->assertSame('completed', $assignment->fresh()->status);

        // The first response was lost, so the device retries after restoring its
        // authentication. A completed assignment must not hide the accepted log.
        $this->withToken($token)
            ->postJson("/api/mobile/us/assignments/{$assignment->id}/log", $payload)
            ->assertOk()
            ->assertJsonPath('error_code', 'already_synced');

        $this->assertDatabaseCount('us_attendance_logs', 1);
    }

    public function test_an_out_of_office_supervisor_reports_not_authorized(): void
    {
        [$assignment] = $this->fixture();

        User::create([
            'first_name' => 'Other', 'last_name' => 'Office',
            'email' => 'code-other@nu-clark.edu.ph',
            'institutional_id' => 'US-CODE-9',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor', 'office_name' => 'Registrar', 'status' => 'active',
        ]);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'code-other@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ])->json('access_token');

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_in',
            'occurred_at' => now()->toIso8601String(),
            'offline' => true,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'not_authorized');
    }

    /**
     * @return array{0: UsAssignment, 1: string}
     */
    private function fixture(bool $withOpenSession = false, int $openedDaysAgo = 0): array
    {
        $this->travelTo(now()->startOfSecond());
        UniversityServiceOffice::query()->firstOrCreate(['name' => 'Library'], ['is_active' => true]);

        $supervisor = User::create([
            'first_name' => 'Code', 'last_name' => 'Supervisor',
            'email' => 'code-sup@nu-clark.edu.ph',
            'institutional_id' => 'US-CODE-1',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor', 'office_name' => 'Library', 'status' => 'active',
        ]);

        $student = User::create([
            'first_name' => 'Code', 'last_name' => 'Student',
            'email' => 'code-stu@students.nu-clark.edu.ph',
            'institutional_id' => '2099-CODE',
            'password' => Hash::make('password'), 'role' => 'student', 'status' => 'active',
        ]);

        $assignment = UsAssignment::create([
            'case_resolution_id' => null,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
            'assigned_by' => $supervisor->id,
            'office_name' => 'Library',
            'total_hours_required' => 8,
            'hours_rendered' => 0,
            'start_date' => today()->subWeek(),
            'target_completion_date' => today()->addWeek(),
            'status' => 'in_progress',
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'totp_secret_generated_at' => now(),
        ]);

        if ($withOpenSession) {
            UsAttendanceLog::create([
                'us_assignment_id' => $assignment->id,
                'student_id' => $student->id,
                'date_of_service' => today()->subDays($openedDaysAgo),
                'time_in' => '09:00:00',
                'time_out' => null,
                'hours_rendered' => null,
                'log_method' => 'qr_scan',
                'totp_verified' => true,
                'client_scanned_at' => now()->subDays($openedDaysAgo),
                'logged_by' => $supervisor->id,
            ]);
        }

        $token = $this->postJson('/api/auth/login', [
            'email' => 'code-sup@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ])->json('access_token');

        return [$assignment, $token];
    }

    private function codeAt(int $timestamp): string
    {
        $totp = TOTP::createFromSecret('JBSWY3DPEHPK3PXP');
        $totp->setPeriod(120);

        return $totp->at($timestamp);
    }
}
