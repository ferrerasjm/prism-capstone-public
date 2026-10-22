<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseResolution;
use App\Models\CounselingReferral;
use App\Models\DisciplinaryCase;
use App\Models\DisciplineEducationAttendance;
use App\Models\Sanction;
use App\Models\UsAssignment;
use App\Models\User;
use App\Notifications\DisciplineEducationAttendanceNotification;
use App\Notifications\DisciplineEducationScheduleNotification;
use App\Notifications\EvaluationMonitoringNotification;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SanctionTrackingTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\DataProvider('programScheduleDates')]
    public function test_program_schedule_starts_tomorrow(string $sessionAt, bool $accepted): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-15 06:00:00'));
        Notification::fake();
        $officer = $this->user('discipline_officer', 'schedule-officer@example.test');
        $student = $this->user('student', 'schedule-student@example.test');
        $case = $this->case($student, $officer, 'DC-SCHEDULE-DATE');
        $case->update(['status' => 'pending_resolution', 'assigned_sanction_category' => 2]);

        $this->actingAs($officer)->get(route('officer.cases.resolution', $case))
            ->assertOk()
            ->assertSee('min="2026-09-16T00:00"', false);

        $response = $this->post(route('officer.cases.resolution.store', $case), [
            'intervention_category' => 2,
            'discipline_education_required' => 1,
            'discipline_education_session_at' => $sessionAt,
        ]);

        if ($accepted) {
            $response->assertSessionHasNoErrors();
            $this->assertDatabaseHas('discipline_education_attendances', [
                'session_at' => str_replace('T', ' ', $sessionAt).':00',
            ]);
        } else {
            $response->assertSessionHasErrors('discipline_education_session_at');
            $this->assertDatabaseMissing('case_resolutions', ['case_id' => $case->id]);
        }
    }

    public static function programScheduleDates(): array
    {
        return [
            'later today' => ['2026-09-15T23:59', false],
            'tomorrow at midnight' => ['2026-09-16T00:00', true],
            'later date' => ['2026-09-17T09:00', true],
        ];
    }

    public function test_officer_can_record_discipline_education_attendance(): void
    {
        $officer = $this->user('discipline_officer', 'attendance-officer@example.test');
        $student = $this->user('student', 'attendance-student@example.test');
        $sanction = Sanction::create([
            'student_id' => $student->id,
            'sanction_type' => 'discipline_education',
            'status' => 'in_progress',
        ]);
        DisciplineEducationAttendance::create([
            'sanction_id' => $sanction->id,
            'session_at' => now()->subHour(),
            'status' => 'scheduled',
            'recorded_by' => $officer->id,
        ]);

        $this->actingAs($officer)->post(
            route('officer.sanctions.discipline-education.attendance', $sanction),
            [
                'attendance_status' => 'present',
            ]
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('discipline_education_attendances', [
            'sanction_id' => $sanction->id,
            'status' => 'present',
            'recorded_by' => $officer->id,
        ]);
        $this->assertSame('completed', $sanction->fresh()->status);
        $this->assertSame($officer->id, $sanction->fresh()->verified_by);
    }

    public function test_evaluation_monitoring_lists_only_cases_opened_inside_its_period(): void
    {
        $officer = $this->user('discipline_officer', 'monitoring-officer@example.test');
        $student = $this->user('student', 'monitoring-student@example.test');
        $origin = $this->case($student, $officer, 'DC-2026-ORIGIN');
        $resolution = $this->resolution($origin, $officer);
        $sanction = Sanction::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'sanction_type' => 'evaluation_monitoring',
            'status' => 'in_progress',
        ]);
        $sanction->forceFill(['created_at' => now()->subDays(7)])->saveQuietly();

        $inside = $this->case($student, $officer, 'DC-2026-INSIDE');
        $inside->forceFill(['created_at' => now()->subDays(3)])->saveQuietly();
        $outside = $this->case($student, $officer, 'DC-2026-OUTSIDE');
        $outside->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

        $this->actingAs($officer)->patch(
            route('officer.sanctions.evaluation-monitoring.update', $sanction),
            ['due_date' => now()->addDays(7)->toDateString(), 'status' => 'in_progress', 'notes' => 'Review weekly.']
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($officer)
            ->get(route('officer.sanctions.show', $sanction))
            ->assertOk()
            ->assertSee('Cases during the Intervention Period')
            ->assertSee('DC-2026-INSIDE')
            ->assertDontSee('DC-2026-OUTSIDE');
    }

    public function test_student_sanction_links_to_the_single_university_service_progress_view(): void
    {
        $officer = $this->user('discipline_officer', 'service-officer@example.test');
        $student = $this->user('student', 'service-student@example.test');
        $case = $this->case($student, $officer, 'DC-2026-SERVICE');
        $resolution = $this->resolution($case, $officer);
        Sanction::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'sanction_type' => 'university_service',
            'status' => 'in_progress',
        ]);
        $assignment = UsAssignment::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'contact_person_name' => 'Alex Supervisor',
            'assigned_by' => $officer->id,
            'office_name' => 'Facilities Management Office',
            'total_hours_required' => 16,
            'hours_rendered' => 4,
            'start_date' => today(),
            'target_completion_date' => today()->addDays(14),
            'status' => 'in_progress',
            'totp_secret' => 'TESTSECRET',
        ]);

        $this->actingAs($student)
            ->get(route('student.sanctions.index'))
            ->assertOk()
            ->assertSee('View Progress')
            ->assertSee(route('student.us.show', $assignment), false);

        $this->actingAs($student)
            ->get(route('student.us.show', $assignment))
            ->assertOk()
            ->assertSee('Assignment details, attendance QR, and service history in one place.')
            ->assertSee('Attendance History')
            ->assertDontSee('View QR Code');

        $this->actingAs($student)
            ->get(route('student.us.qr', $assignment))
            ->assertRedirect(route('student.us.show', $assignment));
    }

    public function test_student_sanctions_are_paginated_ten_at_a_time(): void
    {
        $student = $this->user('student', 'paginated-sanctions@example.test');

        foreach (range(1, 11) as $number) {
            Sanction::create([
                'student_id' => $student->id,
                'sanction_type' => 'written_warning',
                'status' => 'in_progress',
                'notes' => "Pagination test sanction {$number}",
            ]);
        }

        $this->actingAs($student)
            ->get(route('student.sanctions.index'))
            ->assertOk()
            ->assertViewHas('sanctions', fn ($sanctions) => $sanctions->perPage() === 10
                && $sanctions->count() === 10
                && $sanctions->lastPage() === 2)
            ->assertSee('pagination');
    }

    public function test_supervisor_logs_service_time_with_separate_hours_and_minutes(): void
    {
        $supervisor = $this->user('us_supervisor', 'duration-supervisor@example.test');
        $student = $this->user('student', 'duration-student@example.test');
        $assignment = UsAssignment::create([
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
            'office_name' => 'University Library',
            'total_hours_required' => 8,
            'hours_rendered' => 0,
            'start_date' => today(),
            'target_completion_date' => today()->addDays(7),
            'status' => 'pending',
            'totp_secret' => 'DURATIONTESTSECRET',
        ]);

        $this->actingAs($supervisor)->post(route('supervisor.us.log', $assignment), [
            'date_of_service' => today()->toDateString(),
            'time_in' => '09:00',
            'time_out' => '10:30',
            'duration_hours' => 1,
            'duration_minutes' => 30,
            'tasks_performed' => 'Library inventory support.',
        ])->assertRedirect(route('supervisor.us.show', $assignment))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('us_attendance_logs', [
            'us_assignment_id' => $assignment->id,
            'hours_rendered' => 1.50,
        ]);
        $this->assertSame('1 hour 30 minutes', $assignment->fresh()->renderedDuration());
    }

    public function test_decimal_service_hours_are_presented_as_hours_and_minutes(): void
    {
        $this->assertSame('5 minutes', UsAssignment::formatDuration(0.09));
        $this->assertSame('1 hour 30 minutes', UsAssignment::formatDuration(1.5));
        $this->assertSame('16 hours', UsAssignment::formatDuration(16));
        $this->assertSame('0 minutes', UsAssignment::formatDuration(0));
    }

    public function test_officer_schedules_send_student_email_and_database_notifications(): void
    {
        Notification::fake();
        $officer = $this->user('discipline_officer', 'schedule-officer@example.test');
        $student = $this->user('student', 'schedule-student@example.test');
        $case = $this->case($student, $officer, 'DC-2026-SCHEDULE');
        $evaluation = Sanction::create([
            'student_id' => $student->id,
            'sanction_type' => 'evaluation_monitoring',
            'status' => 'in_progress',
        ]);

        (new CaseService)->recordResolution($case, [
            'resolution_level' => 'sdo_level',
            'intervention_category' => 2,
            'discipline_education_required' => true,
            'discipline_education_session_at' => now()->addWeek(),
        ], $officer->id);
        $this->actingAs($officer)->patch(route('officer.sanctions.evaluation-monitoring.update', $evaluation), [
            'due_date' => today()->addMonth()->toDateString(),
            'status' => 'in_progress',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($student, DisciplineEducationScheduleNotification::class, fn ($notification, $channels) => in_array('database', $channels, true) && in_array('mail', $channels, true));
        Notification::assertSentTo($student, EvaluationMonitoringNotification::class, fn ($notification, $channels) => in_array('database', $channels, true) && in_array('mail', $channels, true));
    }

    public function test_command_sends_each_upcoming_intervention_reminder_once(): void
    {
        Notification::fake();
        $officer = $this->user('discipline_officer', 'reminder-officer@example.test');
        $student = $this->user('student', 'reminder-student@example.test');
        $education = Sanction::create([
            'student_id' => $student->id,
            'sanction_type' => 'discipline_education',
            'status' => 'in_progress',
        ]);
        $attendance = DisciplineEducationAttendance::create([
            'sanction_id' => $education->id,
            'session_at' => now()->addDay(),
            'status' => 'scheduled',
            'recorded_by' => $officer->id,
        ]);
        $evaluation = Sanction::create([
            'student_id' => $student->id,
            'sanction_type' => 'evaluation_monitoring',
            'status' => 'in_progress',
            'due_date' => today()->addDays(2),
        ]);

        $this->artisan('sanctions:send-intervention-reminders')
            ->expectsOutput('Sent 1 Discipline Education reminder(s) and 1 Evaluation Monitoring reminder(s).')
            ->assertSuccessful();
        $this->artisan('sanctions:send-intervention-reminders')
            ->expectsOutput('Sent 0 Discipline Education reminder(s) and 0 Evaluation Monitoring reminder(s).')
            ->assertSuccessful();

        Notification::assertSentToTimes($student, DisciplineEducationScheduleNotification::class, 1);
        Notification::assertSentToTimes($student, EvaluationMonitoringNotification::class, 1);
        $this->assertNotNull($attendance->fresh()->reminder_sent_at);
        $this->assertNotNull($evaluation->fresh()->intervention_reminder_sent_at);
    }

    public function test_discipline_education_status_follows_the_scheduled_session_automatically(): void
    {
        Notification::fake();
        $officer = $this->user('discipline_officer', 'status-officer@example.test');
        $student = $this->user('student', 'status-student@example.test');
        $started = Sanction::create([
            'student_id' => $student->id,
            'sanction_type' => 'discipline_education',
            'status' => 'pending',
        ]);
        DisciplineEducationAttendance::create([
            'sanction_id' => $started->id,
            'session_at' => now()->subMinute(),
            'status' => 'scheduled',
            'recorded_by' => $officer->id,
        ]);
        $expired = Sanction::create([
            'student_id' => $student->id,
            'sanction_type' => 'discipline_education',
            'status' => 'in_progress',
        ]);
        $expiredAttendance = DisciplineEducationAttendance::create([
            'sanction_id' => $expired->id,
            'session_at' => now()->subDay(),
            'status' => 'scheduled',
            'recorded_by' => $officer->id,
        ]);

        $this->artisan('sanctions:send-intervention-reminders')->assertSuccessful();

        $this->assertSame('in_progress', $started->fresh()->status);
        $this->assertSame('non_compliant', $expired->fresh()->status);
        $this->assertSame('absent', $expiredAttendance->fresh()->status);
        Notification::assertSentTo($student, DisciplineEducationAttendanceNotification::class);
    }

    public function test_superseded_counseling_referral_sanction_links_to_its_re_referred_successor(): void
    {
        $officer = $this->user('discipline_officer', 'superseded-officer@example.test');
        $student = $this->user('student', 'superseded-student@example.test');
        $case = $this->case($student, $officer, 'DC-2026-SUPERSEDED');
        $resolution = $this->resolution($case, $officer);

        $original = Sanction::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'sanction_type' => 'counseling_referral',
            'status' => 'non_compliant',
        ]);
        $original->forceFill(['created_at' => now()->subMinute()])->saveQuietly();
        CounselingReferral::create([
            'sanction_id' => $original->id,
            'student_id' => $student->id,
            'referred_by' => $officer->id,
            'referred_at' => now()->subDays(3),
            'status' => 'incomplete',
            'incomplete_reason' => 'Student did not attend the two scheduled sessions.',
        ]);

        $successor = Sanction::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'sanction_type' => 'counseling_referral',
            'status' => 'pending',
        ]);
        CounselingReferral::create([
            'sanction_id' => $successor->id,
            'student_id' => $student->id,
            'referred_by' => $officer->id,
            'referred_at' => now(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($officer)->get(route('officer.sanctions.show', $original));

        $response->assertOk();
        $response->assertSee('This case was re-referred to Guidance');
        $response->assertSee(route('officer.sanctions.show', $successor), false);
    }

    public function test_sanction_page_shows_active_registration_hold_for_a_non_compliant_referral(): void
    {
        $officer = $this->user('discipline_officer', 'hold-officer@example.test');
        $student = $this->user('student', 'hold-student@example.test');
        $case = $this->case($student, $officer, 'DC-2026-HOLD');
        $resolution = $this->resolution($case, $officer);

        $sanction = Sanction::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'sanction_type' => 'counseling_referral',
            'status' => 'non_compliant',
        ]);
        CounselingReferral::create([
            'sanction_id' => $sanction->id,
            'student_id' => $student->id,
            'referred_by' => $officer->id,
            'referred_at' => now(),
            'status' => 'incomplete',
            'incomplete_reason' => 'Second consecutive miss.',
        ]);

        \App\Models\StudentMonitoringFlag::create([
            'student_id' => $student->id,
            'flag_type' => 'registration_hold',
            'flag_reason' => 'Automatic hold: 2 consecutive non-compliant counseling referrals on this case.',
            'is_active' => true,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($officer)->get(route('officer.sanctions.show', $sanction));

        $response->assertOk();
        $response->assertSee('Registration Hold Active');
        $response->assertSee('2 consecutive non-compliant counseling referrals');
    }

    public function test_student_sees_the_staff_member_who_recorded_each_sanction(): void
    {
        $student = $this->user('student', 'attribution-student@example.test');
        $officer = $this->user('discipline_officer', 'attribution-officer@example.test');
        $coordinator = $this->user('discipline_coordinator', 'attribution-coordinator@example.test');

        foreach ([$officer, $coordinator] as $staff) {
            $case = $this->case($student, $staff, 'ATTR-'.$staff->id);
            $resolution = $this->resolution($case, $staff);
            Sanction::create([
                'case_resolution_id' => $resolution->id,
                'student_id' => $student->id,
                'sanction_type' => 'written_warning',
                'status' => 'pending',
            ]);
        }

        $this->actingAs($student)
            ->get(route('student.sanctions.index'))
            ->assertOk()
            ->assertSee('Sanction recorded by: '.$officer->full_name)
            ->assertSee('Sanction recorded by: '.$coordinator->full_name);
    }

    public function test_later_counseling_referral_shows_its_referring_officer(): void
    {
        $student = $this->user('student', 'referral-student@example.test');
        $coordinator = $this->user('discipline_coordinator', 'referral-coordinator@example.test');
        $officer = $this->user('discipline_officer', 'referral-officer@example.test');
        $resolution = $this->resolution($this->case($student, $coordinator, 'ATTR-REFERRAL'), $coordinator);
        $sanction = Sanction::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'sanction_type' => 'counseling_referral',
            'status' => 'pending',
        ]);
        CounselingReferral::create([
            'sanction_id' => $sanction->id,
            'student_id' => $student->id,
            'referred_by' => $officer->id,
            'referred_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($student)
            ->get(route('student.sanctions.index'))
            ->assertOk()
            ->assertSee('Sanction recorded by: '.$officer->full_name)
            ->assertDontSee('Sanction recorded by: '.$coordinator->full_name);
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'Sanction',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function case(User $student, User $officer, string $number): DisciplinaryCase
    {
        return DisciplinaryCase::create([
            'case_number' => $number,
            'student_id' => $student->id,
            'case_type' => 'major',
            'assigned_to' => $officer->id,
            'status' => 'resolved',
            'prior_offense_count' => 0,
        ]);
    }

    private function resolution(DisciplinaryCase $case, User $officer): CaseResolution
    {
        return CaseResolution::create([
            'case_id' => $case->id,
            'resolution_level' => 'sdo_level',
            'intervention_category' => 2,
            'resolved_by' => $officer->id,
            'resolved_at' => now(),
            'appeal_deadline' => today()->addDays(5),
            'appeal_filed' => false,
            'created_at' => now(),
        ]);
    }
}
