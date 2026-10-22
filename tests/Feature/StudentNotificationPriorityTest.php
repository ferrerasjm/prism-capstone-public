<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseNotice;
use App\Models\DisciplinaryCase;
use App\Models\User;
use App\Notifications\CaseCreatedNotification;
use App\Notifications\NteDeadlineReminderNotification;
use App\Notifications\NteIssuedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentNotificationPriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_routine_updates_stay_in_notification_bell_without_opening_a_modal(): void
    {
        [$officer, $student, $case] = $this->caseActors('routine');
        $student->notify(new CaseCreatedNotification($case));

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Disciplinary case opened')
            ->assertDontSee('<div id="studentNotificationModal"', false);
    }

    public function test_deadline_reminder_is_prioritized_and_opening_it_clears_related_nte_alerts(): void
    {
        [$officer, $student, $case] = $this->caseActors('urgent');
        $notice = CaseNotice::create([
            'case_id' => $case->id,
            'notice_type' => 'notice_to_explain',
            'issued_by' => $officer->id,
            'issued_at' => now(),
            'handbook_section' => 'Section VI',
            'handbook_page' => '10',
            'incident_date' => today(),
            'response_deadline' => today()->addDays(2),
            'is_voided' => false,
            'created_at' => now(),
        ]);
        $case->update(['status' => 'pending_nte']);

        $student->notify(new CaseCreatedNotification($case));
        $student->notify(new NteIssuedNotification($case, $notice));
        $student->notify(new NteDeadlineReminderNotification($case, $notice));

        $response = $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('studentNotificationModal', false)
            ->assertSee('NTE response deadline approaching')
            ->assertDontSee('1 of 2 urgent')
            ->assertSee('1 other update')
            ->assertDontSee('id="nteModal"', false);

        $this->assertSame(
            1,
            substr_count($response->getContent(), '<div id="studentNotificationModal"')
        );

        $reminder = $student->notifications()
            ->where('type', NteDeadlineReminderNotification::class)
            ->firstOrFail();

        $this->post(route('student.notifications.open', $reminder))
            ->assertRedirect(route('student.cases.explain', $case));

        $student->refresh();
        $this->assertSame(1, $student->unreadNotifications()->count());
        $this->assertSame(
            CaseCreatedNotification::class,
            $student->unreadNotifications()->firstOrFail()->type
        );
    }

    private function caseActors(string $suffix): array
    {
        $officer = $this->user('discipline_officer', "notification-officer-{$suffix}@example.test");
        $student = $this->user('student', "notification-student-{$suffix}@example.test");
        $case = DisciplinaryCase::create([
            'case_number' => 'DC-NOTIFICATION-'.strtoupper($suffix),
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'status' => 'open',
            'prior_offense_count' => 0,
        ]);

        return [$officer, $student, $case];
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'Notification',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
