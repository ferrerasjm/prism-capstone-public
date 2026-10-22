<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseResolution;
use App\Models\DisciplinaryCase;
use App\Models\UsAssignment;
use App\Models\UsAttendanceLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsAttendanceLogPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_log_paginates_instead_of_growing_without_limit(): void
    {
        [$officer, $assignment] = $this->assignmentWithLogs(14);

        $firstPage = $this->actingAs($officer)->get(route('officer.us.show', $assignment));

        $firstPage->assertOk();
        // The header count covers every session, not just the ones on this page.
        $firstPage->assertSee('14 sessions');
        $firstPage->assertSee('Showing');
        $firstPage->assertSee('logs=2', escape: false);

        $secondPage = $this->actingAs($officer)
            ->get(route('officer.us.show', $assignment).'?logs=2');

        $secondPage->assertOk();
        $secondPage->assertSee('14 sessions');
    }

    public function test_a_short_log_renders_without_pagination_links(): void
    {
        [$officer, $assignment] = $this->assignmentWithLogs(3);

        $response = $this->actingAs($officer)->get(route('officer.us.show', $assignment));

        $response->assertOk();
        $response->assertSee('3 sessions');
        $response->assertDontSee('logs=2', escape: false);
    }

    /**
     * @return array{0: User, 1: UsAssignment}
     */
    private function assignmentWithLogs(int $logCount): array
    {
        $officer = $this->user('discipline_officer', 'log-officer@example.test');
        $student = $this->user('student', 'log-student@example.test');

        $case = DisciplinaryCase::create([
            'case_number' => 'CASE-US-LOGS-001',
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
            'us_hours_assigned' => 20,
            'resolved_by' => $officer->id,
            'resolved_at' => now(),
            'appeal_deadline' => now()->addDays(5),
            'appeal_filed' => false,
            'created_at' => now(),
        ]);

        $assignment = UsAssignment::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'office_name' => 'Facilities Management Office',
            'contact_person_name' => 'Sir Facilities Contact',
            'total_hours_required' => 20,
            'hours_rendered' => 0,
            'start_date' => today()->subMonth(),
            'target_completion_date' => today()->addMonth(),
            'status' => 'in_progress',
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'totp_secret_generated_at' => now(),
        ]);

        for ($i = 0; $i < $logCount; $i++) {
            UsAttendanceLog::create([
                'us_assignment_id' => $assignment->id,
                'student_id' => $student->id,
                'date_of_service' => today()->subDays($i),
                'time_in' => '09:00:00',
                'time_out' => '11:00:00',
                'hours_rendered' => 2,
                'log_method' => 'manual_web',
                'logged_by' => $officer->id,
            ]);
        }

        return [$officer, $assignment];
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => 'Tester',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
