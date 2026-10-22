<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseResolution;
use App\Models\DisciplinaryCase;
use App\Models\StudentProfile;
use App\Models\UniversityServiceOffice;
use App\Models\UsAssignment;
use App\Models\UsAttendanceLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * When a session is opened by one supervisor and closed by another (the
 * multi-device flow — see UsAssignment::isCoveredBy()), the officer and
 * supervisor tables must surface both accounts, not just whoever logged the
 * time-in, and a covering supervisor's own mobile history must include
 * sessions they only verified.
 */
class UsAttendanceSplitDeviceAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_view_shows_who_verified_a_split_session(): void
    {
        [$officer, $assignment, $supervisorA, $supervisorB] = $this->splitSessionFixture();

        $response = $this->actingAs($officer)->get(route('officer.us.show', $assignment));

        $response->assertOk();
        $response->assertSee($supervisorA->list_name);
        $response->assertSee('Verified by '.$supervisorB->list_name);
    }

    public function test_supervisor_view_shows_logged_by_and_verified_by(): void
    {
        [, $assignment, $supervisorA, $supervisorB] = $this->splitSessionFixture();

        // Supervisor B never opened this session, only closed it, but covers the same office.
        $response = $this->actingAs($supervisorB)->get(route('supervisor.us.show', $assignment));

        $response->assertOk();
        $response->assertSee($supervisorA->list_name);
        $response->assertSee('Verified by '.$supervisorB->list_name);
    }

    public function test_officer_view_omits_verified_by_when_the_same_supervisor_did_both(): void
    {
        [$officer, $assignment, $supervisorA] = $this->splitSessionFixture(sameSupervisor: true);

        $response = $this->actingAs($officer)->get(route('officer.us.show', $assignment));

        $response->assertOk();
        $response->assertSee($supervisorA->list_name);
        $response->assertDontSee('Verified by');
    }

    public function test_mobile_history_includes_sessions_the_supervisor_only_verified(): void
    {
        [, $assignment, $supervisorA, $supervisorB] = $this->splitSessionFixture();

        $token = $this->postJson('/api/auth/login', [
            'email' => $supervisorB->email,
            'password' => 'FixtureOnly@2026',
        ])->json('access_token');

        $this->withToken($token)
            ->getJson('/api/mobile/us/history')
            ->assertOk()
            ->assertJsonPath('data.0.assignment_id', (string) $assignment->id);
    }

    /**
     * @return array{0: User, 1: UsAssignment, 2: User, 3: User}
     */
    private function splitSessionFixture(bool $sameSupervisor = false): array
    {
        UniversityServiceOffice::query()->firstOrCreate(
            ['name' => 'Learning Resource Center'],
            ['is_active' => true]
        );

        $officer = User::create([
            'first_name' => 'Case',
            'last_name' => 'Officer',
            'email' => 'split-officer@example.test',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $supervisorA = User::create([
            'first_name' => 'Supervisor',
            'last_name' => 'Alpha',
            'email' => 'split-sup-a@nu-clark.edu.ph',
            'institutional_id' => 'US-SPLIT-A',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor',
            'office_name' => 'Learning Resource Center',
            'status' => 'active',
        ]);

        $supervisorB = $sameSupervisor ? $supervisorA : User::create([
            'first_name' => 'Supervisor',
            'last_name' => 'Bravo',
            'email' => 'split-sup-b@nu-clark.edu.ph',
            'institutional_id' => 'US-SPLIT-B',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor',
            'office_name' => 'Learning Resource Center',
            'status' => 'active',
        ]);

        $student = User::create([
            'first_name' => 'Split',
            'last_name' => 'Student',
            'email' => 'split-student@students.nu-clark.edu.ph',
            'institutional_id' => '2099-SPLIT-01',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => '2099-SPLIT-01',
            'program' => 'BS Information Technology',
            'year_level' => '3',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'CASE-US-SPLIT-001',
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

        $assignment = UsAssignment::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'supervisor_id' => $supervisorA->id,
            'assigned_by' => $officer->id,
            'office_name' => 'Learning Resource Center',
            'total_hours_required' => 8,
            'hours_rendered' => 2,
            'start_date' => today()->subWeek(),
            'target_completion_date' => today()->addWeek(),
            'status' => 'in_progress',
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'totp_secret_generated_at' => now(),
        ]);

        UsAttendanceLog::create([
            'us_assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'date_of_service' => today(),
            'time_in' => '09:00:00',
            'time_out' => '11:00:00',
            'hours_rendered' => 2,
            'log_method' => 'qr_scan',
            'totp_verified' => true,
            'logged_by' => $supervisorA->id,
            'verified_by' => $supervisorB->id,
        ]);

        return [$officer, $assignment, $supervisorA, $supervisorB];
    }
}
