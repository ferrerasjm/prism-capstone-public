<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseResolution;
use App\Models\DisciplinaryCase;
use App\Models\StudentProfile;
use App\Models\UniversityServiceOffice;
use App\Models\UsAssignment;
use App\Models\User;
use App\Services\USAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\SignsMobileAttendance;
use Tests\TestCase;

class MobileUsApiTest extends TestCase
{
    use RefreshDatabase;
    use SignsMobileAttendance;

    public function test_supervisor_can_list_assignments_and_log_with_totp(): void
    {
        UniversityServiceOffice::query()->firstOrCreate(
            ['name' => 'Library'],
            ['is_active' => true]
        );

        $supervisor = User::create([
            'last_name' => 'Bautista',
            'first_name' => 'Juan Paolo',
            'email' => 'demo-user-006@nu-clark.edu.ph',
            'institutional_id' => 'US-SUP-001',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor',
            'status' => 'active',
            'office_name' => 'Library',
        ]);

        $student = User::create([
            'last_name' => 'Cruz',
            'first_name' => 'Ivy',
            'email' => 'demo-user-010@students.nu-clark.edu.ph',
            'institutional_id' => '2022-00099',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_number' => '2022-00099',
            'program' => 'BS Information Technology',
            'year_level' => '3',
        ]);

        $assignment = UsAssignment::query()->create([
            'case_resolution_id' => null,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
            'assigned_by' => $supervisor->id,
            'office_name' => 'Library',
            'total_hours_required' => 8,
            'hours_rendered' => 0,
            'start_date' => now()->toDateString(),
            'target_completion_date' => now()->addDays(14)->toDateString(),
            'status' => 'pending',
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'totp_secret_generated_at' => now(),
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'demo-user-006@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ]);

        $login->assertOk()->assertJsonStructure(['access_token']);

        $token = $login->json('access_token');

        $this->withToken($token)
            ->getJson('/api/mobile/us/assignments')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $assignment->id)
            ->assertJsonPath('data.0.studentId', '2022-00099');

        $startedAt = now()->subHours(4)->startOfSecond();
        $timeInTotp = $this->attendanceCode($assignment, $startedAt);

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_in',
            'occurred_at' => $startedAt->toIso8601String(),
            'totp' => $timeInTotp,

        ])
            ->assertCreated()
            ->assertJsonPath('eventType', 'time_in')
            ->assertJsonPath('totpVerified', true);

        // A second time-in before closing the open session must be rejected.
        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_in',
            'occurred_at' => $startedAt->toIso8601String(),
            'totp' => $timeInTotp,

        ])
            ->assertStatus(409);

        $uri = app(USAssignmentService::class)->generateTotpUri($assignment->fresh());
        $parts = explode(':', $uri);
        $timeOutTotp = $parts[3] ?? '';

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_out',
            'occurred_at' => now()->toIso8601String(),
            'totp' => $timeOutTotp,

        ])
            ->assertCreated()
            ->assertJsonPath('eventType', 'time_out')
            ->assertJsonPath('totpVerified', true)
            ->assertJsonPath('hoursRendered', 4)
            ->assertJsonPath('assignment.currentHours', 4);

        // A time-out with no open session must be rejected.
        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_out',
            'occurred_at' => now()->toIso8601String(),
            'totp' => $timeOutTotp,

        ])
            ->assertStatus(409);
    }

    public function test_override_reason_is_persisted_for_a_short_session(): void
    {
        UniversityServiceOffice::query()->firstOrCreate(
            ['name' => 'Library'],
            ['is_active' => true]
        );

        $supervisor = User::create([
            'last_name' => 'Reyes',
            'first_name' => 'Carla',
            'email' => 'reyesc@nu-clark.edu.ph',
            'institutional_id' => 'US-SUP-002',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor',
            'status' => 'active',
            'office_name' => 'Library',
        ]);

        $student = User::create([
            'last_name' => 'Santos',
            'first_name' => 'Miguel',
            'email' => 'santosm@students.nu-clark.edu.ph',
            'institutional_id' => '2022-00100',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_number' => '2022-00100',
            'program' => 'BS Information Technology',
            'year_level' => '2',
        ]);

        $assignment = UsAssignment::query()->create([
            'case_resolution_id' => null,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
            'assigned_by' => $supervisor->id,
            'office_name' => 'Library',
            'total_hours_required' => 8,
            'hours_rendered' => 0,
            'start_date' => now()->toDateString(),
            'target_completion_date' => now()->addDays(14)->toDateString(),
            'status' => 'pending',
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'totp_secret_generated_at' => now(),
        ]);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'reyesc@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ])->json('access_token');

        $startedAt = now()->subMinutes(20)->startOfSecond();
        $timeInTotp = $this->attendanceCode($assignment, $startedAt);

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_in',
            'occurred_at' => $startedAt->toIso8601String(),
            'totp' => $timeInTotp,

        ])
            ->assertCreated();

        $uri = app(USAssignmentService::class)->generateTotpUri($assignment->fresh());
        $timeOutTotp = explode(':', $uri)[3] ?? '';

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_out',
            'occurred_at' => now()->toIso8601String(),
            'totp' => $timeOutTotp,

            'override_reason' => 'Facilities office released the student early due to a room conflict.',
        ])
            ->assertCreated()
            ->assertJsonPath('hoursRendered', 0.33);

        $this->assertDatabaseHas('us_attendance_logs', [
            'us_assignment_id' => $assignment->id,
            'override_reason' => 'Facilities office released the student early due to a room conflict.',
        ]);
    }

    public function test_supervisor_receives_and_can_read_new_assignment_notification(): void
    {
        UniversityServiceOffice::query()->firstOrCreate(
            ['name' => 'Library'],
            ['is_active' => true]
        );

        $officer = User::create([
            'last_name' => 'Officer',
            'first_name' => 'Test',
            'email' => 'officer-notif@example.test',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $supervisor = User::create([
            'last_name' => 'Lim',
            'first_name' => 'Andrea',
            'email' => 'liman@nu-clark.edu.ph',
            'institutional_id' => 'US-SUP-003',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor',
            'status' => 'active',
            'office_name' => 'Library',
        ]);

        $student = User::create([
            'last_name' => 'Torres',
            'first_name' => 'Nina',
            'email' => 'torresn@students.nu-clark.edu.ph',
            'institutional_id' => '2022-00101',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_number' => '2022-00101',
            'program' => 'BS Information Technology',
            'year_level' => '1',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'CASE-US-NOTIF-001',
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
            'us_hours_assigned' => 8,
            'resolved_by' => $officer->id,
            'resolved_at' => now(),
            'appeal_deadline' => now()->addDays(5),
            'appeal_filed' => false,
            'created_at' => now(),
        ]);

        $assignment = app(USAssignmentService::class)->createAssignment($resolution, [
            'supervisor_id' => $supervisor->id,
            'office_name' => 'Library',
            'start_date' => now()->addDay()->toDateString(),
            'target_completion_date' => now()->addWeek()->toDateString(),
        ], $officer->id);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'liman@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ])->json('access_token');

        $list = $this->withToken($token)
            ->getJson('/api/mobile/us/notifications')
            ->assertOk()
            ->assertJsonPath('unreadCount', 1)
            ->assertJsonPath('data.0.kind', 'us_supervisor_assignment')
            ->assertJsonPath('data.0.readAt', null);

        $notificationId = $list->json('data.0.id');

        $this->withToken($token)
            ->postJson("/api/mobile/us/notifications/{$notificationId}/read")
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/mobile/us/notifications')
            ->assertOk()
            ->assertJsonPath('unreadCount', 0);

        $this->assertNotNull($supervisor->fresh()->notifications()->first()->read_at);
    }

    public function test_a_different_supervisor_at_the_same_office_can_close_a_session_another_opened(): void
    {
        UniversityServiceOffice::query()->firstOrCreate(
            ['name' => 'Library'],
            ['is_active' => true]
        );

        $assignedSupervisor = User::create([
            'last_name' => 'Bautista',
            'first_name' => 'Juan Paolo',
            'email' => 'bautistajp2@nu-clark.edu.ph',
            'institutional_id' => 'US-SUP-010',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor',
            'status' => 'active',
            'office_name' => 'Library',
        ]);

        $coveringSupervisor = User::create([
            'last_name' => 'Reyes',
            'first_name' => 'Carla',
            'email' => 'reyesc2@nu-clark.edu.ph',
            'institutional_id' => 'US-SUP-011',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor',
            'status' => 'active',
            'office_name' => 'Library',
        ]);

        $otherOfficeSupervisor = User::create([
            'last_name' => 'Santos',
            'first_name' => 'Dennis',
            'email' => 'santosd@nu-clark.edu.ph',
            'institutional_id' => 'US-SUP-012',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor',
            'status' => 'active',
            'office_name' => 'Registrar',
        ]);

        $student = User::create([
            'last_name' => 'Cruz',
            'first_name' => 'Ivy',
            'email' => 'cruzim2@students.nu-clark.edu.ph',
            'institutional_id' => '2022-00199',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_number' => '2022-00199',
            'program' => 'BS Information Technology',
            'year_level' => '3',
        ]);

        $assignment = UsAssignment::query()->create([
            'case_resolution_id' => null,
            'student_id' => $student->id,
            'supervisor_id' => $assignedSupervisor->id,
            'assigned_by' => $assignedSupervisor->id,
            'office_name' => 'Library',
            'total_hours_required' => 8,
            'hours_rendered' => 0,
            'start_date' => now()->toDateString(),
            'target_completion_date' => now()->addDays(14)->toDateString(),
            'status' => 'pending',
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'totp_secret_generated_at' => now(),
        ]);

        $coveringToken = $this->postJson('/api/auth/login', [
            'email' => 'reyesc2@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ])->json('access_token');

        // A same-office supervisor sees the assignment even though it's assigned to someone else.
        $this->withToken($coveringToken)
            ->getJson('/api/mobile/us/assignments')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $assignment->id);

        $startedAt = now()->subHours(2)->startOfSecond();
        $timeInTotp = $this->attendanceCode($assignment, $startedAt);

        // Time-in from the covering supervisor's device.
        $this->postSignedAttendance($assignment, $coveringToken, [
            'event_type' => 'time_in',
            'occurred_at' => $startedAt->toIso8601String(),
            'totp' => $timeInTotp,

        ])
            ->assertCreated();

        $assignedToken = $this->postJson('/api/auth/login', [
            'email' => 'bautistajp2@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ])->json('access_token');

        $uri = app(USAssignmentService::class)->generateTotpUri($assignment->fresh());
        $timeOutTotp = explode(':', $uri)[3] ?? '';

        // Time-out from the originally assigned supervisor's device, closing the session the other one opened.
        $this->postSignedAttendance($assignment, $assignedToken, [
            'event_type' => 'time_out',
            'occurred_at' => now()->toIso8601String(),
            'totp' => $timeOutTotp,

        ])
            ->assertCreated()
            ->assertJsonPath('hoursRendered', 2);

        $otherOfficeToken = $this->postJson('/api/auth/login', [
            'email' => 'santosd@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ])->json('access_token');

        // A supervisor from a different office has no business on this assignment.
        $this->withToken($otherOfficeToken)
            ->getJson('/api/mobile/us/assignments')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postSignedAttendance($assignment, $otherOfficeToken, [
            'event_type' => 'time_in',
            'occurred_at' => now()->toIso8601String(),
            'totp' => $timeInTotp,

        ])
            ->assertStatus(403);
    }
}
