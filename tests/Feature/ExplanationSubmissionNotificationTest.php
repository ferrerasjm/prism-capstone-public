<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseNotice;
use App\Models\DisciplinaryCase;
use App\Models\User;
use App\Notifications\CaseExplanationSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExplanationSubmissionNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_explanation_notifies_assigned_discipline_officer(): void
    {
        [$officer, $student, $case] = $this->caseActors();
        $this->activeNte($case, $officer);

        $this->actingAs($student)
            ->post(route('student.cases.explanation.store', $case), [
                'explanation_text' => str_repeat('This is my written explanation for the reported incident. ', 2),
            ])
            ->assertRedirect(route('student.cases.show', $case));

        $this->assertDatabaseHas('notifications', [
            'type' => CaseExplanationSubmittedNotification::class,
            'notifiable_id' => $officer->id,
        ]);

        $notification = $officer->notifications()
            ->where('type', CaseExplanationSubmittedNotification::class)
            ->firstOrFail();

        $this->assertSame('case_explanation_submitted', $notification->data['kind']);
        $this->assertSame($case->id, $notification->data['case_id']);
        $this->assertSame(route('officer.cases.show', $case, false).'#action-panel', $notification->data['url']);

        $this->actingAs($officer)
            ->get(route('officer.dashboard'))
            ->assertOk()
            ->assertSee('Student explanation submitted')
            ->assertSee($case->case_number);

        $this->actingAs($officer)
            ->post(route('notifications.open', $notification))
            ->assertRedirect(route('officer.cases.show', $case).'#action-panel');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    private function caseActors(): array
    {
        $officer = $this->user('discipline_officer', 'explanation-officer@example.test');
        $student = $this->user('student', 'explanation-student@example.test');

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-EXPLANATION-NOTIFY',
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => 'major',
            'case_type_locked' => true,
            'status' => 'pending_nte',
            'prior_offense_count' => 0,
        ]);

        return [$officer, $student, $case];
    }

    private function activeNte(DisciplinaryCase $case, User $officer): void
    {
        CaseNotice::create([
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
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'Explanation',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
