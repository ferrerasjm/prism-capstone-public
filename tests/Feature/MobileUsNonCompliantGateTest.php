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
use Tests\Support\SignsMobileAttendance;
use Tests\TestCase;

/**
 * A non-compliant assignment missed its deadline. Rather than letting a
 * supervisor quietly work around that by continuing to scan/log hours, new
 * attendance is blocked until an officer reviews it via extendDeadline() —
 * which already restores the status once hours or an extension justify it.
 * An already-open session is exempt from the block so it isn't stranded.
 */
class MobileUsNonCompliantGateTest extends TestCase
{
    use RefreshDatabase;
    use SignsMobileAttendance;

    public function test_time_in_is_blocked_on_a_non_compliant_assignment(): void
    {
        [$assignment, $token] = $this->fixture();

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_in',
            'occurred_at' => now()->toIso8601String(),
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'deadline_non_compliant');
    }

    public function test_time_out_still_closes_a_session_left_open_before_the_deadline_lapsed(): void
    {
        [$assignment, $token, $supervisor] = $this->fixture();

        // Simulates a session opened while still in_progress, with the
        // non-compliance sweep landing before the matching Time-Out.
        UsAttendanceLog::create([
            'us_assignment_id' => $assignment->id,
            'student_id' => $assignment->student_id,
            'date_of_service' => today(),
            'time_in' => '09:00:00',
            'time_out' => null,
            'hours_rendered' => null,
            'log_method' => 'qr_scan',
            'totp_verified' => true,
            'client_scanned_at' => now()->subHour(),
            'logged_by' => $supervisor->id,
        ]);

        $code = explode(':', app(USAssignmentService::class)->generateTotpUri($assignment))[3] ?? '';

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_out',
            'occurred_at' => now()->toIso8601String(),
            'totp' => $code,
        ])
            ->assertCreated()
            ->assertJsonPath('eventType', 'time_out');
    }

    public function test_web_manual_log_is_blocked_on_a_non_compliant_assignment(): void
    {
        [$assignment, , $supervisor] = $this->fixture();

        $response = $this->actingAs($supervisor)->post(
            route('supervisor.us.log', $assignment),
            [
                'date_of_service' => today()->toDateString(),
                'time_in' => '09:00',
                'time_out' => '11:00',
                'duration_hours' => 2,
                'duration_minutes' => 0,
                'tasks_performed' => 'Attempted to log around the compliance gate.',
            ]
        );

        $response->assertSessionHasErrors('status');
        $this->assertSame(0, UsAttendanceLog::where('us_assignment_id', $assignment->id)->count());
    }

    /**
     * @return array{0: UsAssignment, 1: string, 2: User}
     */
    private function fixture(): array
    {
        UniversityServiceOffice::query()->firstOrCreate(
            ['name' => 'Learning Resource Center'],
            ['is_active' => true]
        );

        $supervisor = User::create([
            'first_name' => 'Gate',
            'last_name' => 'Supervisor',
            'email' => 'gate-supervisor@nu-clark.edu.ph',
            'institutional_id' => 'US-GATE-SUP',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor',
            'office_name' => 'Learning Resource Center',
            'status' => 'active',
        ]);

        $student = User::create([
            'first_name' => 'Gate',
            'last_name' => 'Student',
            'email' => 'gate-student@students.nu-clark.edu.ph',
            'institutional_id' => '2099-GATE',
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
            'start_date' => today()->subWeeks(2),
            'target_completion_date' => today()->subDay(),
            'status' => 'non_compliant',
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'totp_secret_generated_at' => now(),
        ]);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'gate-supervisor@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ])->json('access_token');

        return [$assignment, $token, $supervisor];
    }
}
