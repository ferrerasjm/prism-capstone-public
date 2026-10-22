<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseResolution;
use App\Models\DisciplinaryCase;
use App\Models\UpccReferral;
use App\Models\User;
use App\Notifications\NfiIssuedNotification;
use App\Notifications\UniversityServiceStatusNotification;
use App\Notifications\UpccDecisionNotification;
use App\Services\CaseService;
use App\Services\CoordinatorCaseService;
use App\Services\USAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentOperationalNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_nfi_issuance_notifies_the_student_with_a_working_relative_link(): void
    {
        [$officer, $student, $case, $resolution] = $this->resolvedCase(2);

        (new CaseService)->issueNFI($case, $resolution, $officer->id);

        $notification = $student->notifications()->where('type', NfiIssuedNotification::class)->firstOrFail();
        $this->assertSame(route('student.cases.nfi', $case, false), $notification->data['url']);
    }

    public function test_university_service_assignment_and_completion_notify_the_student(): void
    {
        [$officer, $student, , $resolution] = $this->resolvedCase(2);
        $supervisor = $this->user('us_supervisor');
        $service = new USAssignmentService;
        $assignment = $service->createAssignment($resolution, [
            'supervisor_id' => $supervisor->id,
            'office_name' => 'Library',
            'start_date' => today()->addDay()->toDateString(),
            'target_completion_date' => today()->addWeek()->toDateString(),
        ], $officer->id);

        $service->logSession($assignment, [
            'date_of_service' => today()->addDay()->toDateString(),
            'hours_rendered' => 8,
            'log_method' => 'manual_web',
        ], $supervisor->id);

        $this->assertDatabaseHas('notifications', [
            'type' => UniversityServiceStatusNotification::class,
            'notifiable_id' => $student->id,
        ]);
        $events = $student->notifications()
            ->where('type', UniversityServiceStatusNotification::class)
            ->get()
            ->pluck('data')
            ->pluck('kind');
        $this->assertTrue($events->contains('university_service_assigned'));
        $this->assertTrue($events->contains('university_service_completed'));
    }

    public function test_distributed_upcc_decision_notifies_student_of_appeal_deadline(): void
    {
        [$coordinator, $student, $case] = $this->resolvedCase(3);
        $referral = UpccReferral::create([
            'case_id' => $case->id,
            'referred_by' => $coordinator->id,
            'referred_at' => now(),
            'scheduled_conference_at' => now()->addDay(),
            'conference_location' => 'Conference Room',
            'status' => 'concluded',
            'proceedings_type' => 'full_hearing',
        ]);

        (new CoordinatorCaseService)->distributeDecision($referral, $coordinator->id);

        $notification = $student->notifications()->where('type', UpccDecisionNotification::class)->firstOrFail();
        $this->assertSame('upcc_distributed', $notification->data['kind']);
        $this->assertNotNull($notification->data['appeal_deadline']);
        $this->assertSame(route('student.cases.show', $case, false), $notification->data['url']);
    }

    private function resolvedCase(int $category): array
    {
        $officer = $this->user($category === 3 ? 'discipline_coordinator' : 'discipline_officer');
        $student = $this->user('student');
        $case = DisciplinaryCase::create([
            'case_number' => 'NOTICE-'.fake()->unique()->numerify('#####'),
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => 'major',
            'case_type_locked' => true,
            'status' => $category === 3 ? 'pending_upcc' : 'resolved',
            'prior_offense_count' => 0,
        ]);
        $resolution = CaseResolution::create([
            'case_id' => $case->id,
            'resolution_level' => 'sdo_level',
            'intervention_category' => $category,
            'us_hours_assigned' => $category === 2 ? 8 : null,
            'resolved_by' => $officer->id,
            'resolved_at' => now(),
            'appeal_deadline' => now()->addDays(5),
            'appeal_filed' => false,
            'created_at' => now(),
        ]);

        return [$officer, $student, $case, $resolution];
    }

    private function user(string $role): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'Notice',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
