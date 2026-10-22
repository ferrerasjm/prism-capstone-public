<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\User;
use App\Notifications\CaseResolvedNotification;
use App\Notifications\SanctionsAssignedNotification;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentCaseNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_minor_resolution_notifies_the_student(): void
    {
        [$officer, $student] = $this->users();
        $case = $this->case($officer, $student, 'minor', 'open');

        (new CaseService)->resolveMinorCase($case, 'Minor intervention completed.', $officer->id);

        $this->assertDatabaseHas('notifications', [
            'type' => CaseResolvedNotification::class,
            'notifiable_id' => $student->id,
        ]);
        $notification = $student->notifications()->firstOrFail();
        $this->assertSame(route('student.cases.show', $case, false), $notification->data['url']);
    }

    public function test_formal_resolution_notifies_student_of_resolution_and_sanctions(): void
    {
        [$officer, $student] = $this->users();
        $case = $this->case($officer, $student, 'major', 'pending_resolution');

        (new CaseService)->recordResolution($case, [
            'resolution_level' => 'sdo_level',
            'intervention_category' => 3,
            'notes' => 'Formal resolution recorded.',
        ], $officer->id);

        $this->assertDatabaseHas('notifications', [
            'type' => CaseResolvedNotification::class,
            'notifiable_id' => $student->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'type' => SanctionsAssignedNotification::class,
            'notifiable_id' => $student->id,
        ]);

        $sanctionNotification = $student->notifications()
            ->where('type', SanctionsAssignedNotification::class)
            ->firstOrFail();
        $this->assertSame(
            route('student.sanctions.index', [], false),
            $sanctionNotification->data['url']
        );
        $this->assertSame(['non_readmission'], $sanctionNotification->data['sanction_types']);
    }

    private function users(): array
    {
        $officer = User::create([
            'last_name' => 'Officer',
            'first_name' => 'Notification',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $student = User::create([
            'last_name' => 'Student',
            'first_name' => 'Notification',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        return [$officer, $student];
    }

    private function case(User $officer, User $student, string $type, string $status): DisciplinaryCase
    {
        return DisciplinaryCase::create([
            'case_number' => 'NOTIFY-'.fake()->unique()->numerify('#####'),
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => $type,
            'case_type_locked' => true,
            'status' => $status,
            'prior_offense_count' => 0,
        ]);
    }
}
