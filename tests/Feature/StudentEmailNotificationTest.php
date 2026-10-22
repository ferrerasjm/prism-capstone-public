<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseNotice;
use App\Models\CaseResolution;
use App\Models\CounselingReferral;
use App\Models\DisciplinaryCase;
use App\Models\UpccReferral;
use App\Models\UsAssignment;
use App\Models\User;
use App\Notifications\CaseCreatedNotification;
use App\Notifications\CaseEscalatedNotification;
use App\Notifications\CaseResolvedNotification;
use App\Notifications\GuidanceReferralStatusNotification;
use App\Notifications\NfiIssuedNotification;
use App\Notifications\NteDeadlineReminderNotification;
use App\Notifications\NteIssuedNotification;
use App\Notifications\SanctionsAssignedNotification;
use App\Notifications\StudentNotification;
use App\Notifications\UniversityServiceStatusNotification;
use App\Notifications\UpccDecisionNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StudentEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_required_and_formal_decision_notifications_use_queued_email(): void
    {
        $notifications = [
            CaseResolvedNotification::class,
            NfiIssuedNotification::class,
            NteDeadlineReminderNotification::class,
            NteIssuedNotification::class,
        ];

        foreach ($notifications as $notification) {
            $this->assertTrue(is_subclass_of($notification, StudentNotification::class));
            $this->assertTrue(is_subclass_of($notification, ShouldQueue::class));
        }

        $this->assertTrue(is_subclass_of(CaseCreatedNotification::class, StudentNotification::class));
        $this->assertTrue(is_subclass_of(CaseCreatedNotification::class, ShouldQueue::class));
    }

    public function test_notification_channels_follow_the_student_email_priority_policy(): void
    {
        $student = $this->student();
        $case = new DisciplinaryCase;
        $resolution = new CaseResolution;

        $this->assertSame(['database', 'mail'], (new CaseCreatedNotification($case))->via($student));
        $this->assertSame(['database'], (new CaseEscalatedNotification($case))->via($student));
        $this->assertSame(
            ['database'],
            (new SanctionsAssignedNotification($case, $resolution))->via($student)
        );

        $completedReferral = (new CounselingReferral)->forceFill(['status' => 'completed']);
        $incompleteReferral = (new CounselingReferral)->forceFill(['status' => 'incomplete']);
        $this->assertSame(
            ['database'],
            (new GuidanceReferralStatusNotification($completedReferral))->via($student)
        );
        $this->assertSame(
            ['database', 'mail'],
            (new GuidanceReferralStatusNotification($incompleteReferral))->via($student)
        );

        $assignment = new UsAssignment;
        $this->assertSame(
            ['database'],
            (new UniversityServiceStatusNotification($assignment, 'completed'))->via($student)
        );
        $this->assertSame(
            ['database', 'mail'],
            (new UniversityServiceStatusNotification($assignment, 'assigned'))->via($student)
        );
        $this->assertSame(
            ['database', 'mail'],
            (new UniversityServiceStatusNotification($assignment, 'non_compliant'))->via($student)
        );

        $referral = new UpccReferral;
        $this->assertSame(
            ['database'],
            (new UpccDecisionNotification($referral, 'finalized'))->via($student)
        );
        $this->assertSame(
            ['database', 'mail'],
            (new UpccDecisionNotification($referral, 'distributed'))->via($student)
        );
        $this->assertSame(
            ['database', 'mail'],
            (new UpccDecisionNotification($referral, 'reconsideration_resolved'))->via($student)
        );
    }

    public function test_case_created_email_is_privacy_safe_and_links_to_the_secure_portal(): void
    {
        $student = $this->student();
        $case = DisciplinaryCase::create([
            'case_number' => 'PRIVATE-CASE-CREATED-001',
            'student_id' => $student->id,
            'status' => 'open',
            'prior_offense_count' => 0,
        ]);
        $notification = new CaseCreatedNotification($case);

        $mail = $notification->toMail($student);
        $html = (string) $mail->render();

        $this->assertSame('A disciplinary case has been opened in PRISM', $mail->subject);
        $this->assertSame('Case update', $mail->viewData['content']['label']);
        $this->assertSame(config('app.public_url').'/login', $mail->viewData['actionUrl']);
        $this->assertStringContainsString('A case has been opened for review', $html);
        $this->assertStringContainsString('complete and official record', $html);
        $this->assertStringContainsString('This email contains a summary only', $html);
        $this->assertStringContainsString('View Case', $html);
        $this->assertStringNotContainsString($case->case_number, $html);
    }

    public function test_pending_setup_student_still_receives_case_created_notification(): void
    {
        Notification::fake();
        $student = $this->student('pending_setup');
        $case = DisciplinaryCase::create([
            'case_number' => 'PENDING-SETUP-CASE-001',
            'student_id' => $student->id,
            'status' => 'open',
            'prior_offense_count' => 0,
        ]);

        $student->notify(new CaseCreatedNotification($case));

        Notification::assertSentTo(
            $student,
            CaseCreatedNotification::class,
            fn (CaseCreatedNotification $notification): bool => $notification->via($student) === ['database', 'mail']
                && $notification->toMail($student)->subject === 'A disciplinary case has been opened in PRISM',
        );
    }

    public function test_student_email_is_privacy_safe_and_links_to_the_secure_portal(): void
    {
        $student = $this->student();
        $case = DisciplinaryCase::create([
            'case_number' => 'PRIVATE-CASE-001',
            'student_id' => $student->id,
            'status' => 'open',
            'prior_offense_count' => 0,
        ]);
        $notification = new CaseResolvedNotification($case);

        $this->assertSame(['database', 'mail'], $notification->via($student));
        $this->assertSame('sync', $notification->viaConnections()['database']);
        $this->assertSame(config('queue.default'), $notification->viaConnections()['mail']);

        $mail = $notification->toMail($student);
        $html = (string) $mail->render();

        $this->assertSame('Your case resolution is available in PRISM', $mail->subject);
        $this->assertSame('emails.student-notification', $mail->view['html']);
        $this->assertSame('Decision available', $mail->viewData['content']['label']);
        $this->assertSame(config('app.public_url').'/login', $mail->viewData['actionUrl']);
        $this->assertStringContainsString('Hello, Email Test Student', $html);
        $this->assertStringContainsString('A formal resolution has been recorded', $html);
        $this->assertStringContainsString('View Resolution', $html);
        $this->assertStringContainsString('NU Clark', $html);
        $this->assertStringNotContainsString($case->case_number, $html);
        $this->assertStringContainsString('complete and official record', $html);
        $this->assertStringContainsString('src="data:image/png;base64,', $html);
        $this->assertStringContainsString("font-family: 'Poppins'", $html);
        $this->assertStringNotContainsString('&nbsp;→', $html);
    }

    public function test_deadline_email_includes_the_due_date_without_exposing_case_details(): void
    {
        $student = $this->student();
        $case = DisciplinaryCase::create([
            'case_number' => 'PRIVATE-DEADLINE-001',
            'student_id' => $student->id,
            'status' => 'pending_nte',
            'prior_offense_count' => 0,
        ]);
        $notice = CaseNotice::create([
            'case_id' => $case->id,
            'notice_type' => 'notice_to_explain',
            'issued_at' => now(),
            'handbook_section' => 'Section VI',
            'handbook_page' => '10',
            'incident_date' => today(),
            'response_deadline' => '2026-08-15',
            'is_voided' => false,
            'created_at' => now(),
        ]);

        $mail = (new NteDeadlineReminderNotification($case, $notice))->toMail($student);
        $html = (string) $mail->render();

        $this->assertSame('Reminder: your response deadline is approaching', $mail->subject);
        $this->assertSame('August 15, 2026', $mail->viewData['content']['deadline']);
        $this->assertStringContainsString('Your response is due soon', $html);
        $this->assertStringContainsString('August 15, 2026', $html);
        $this->assertStringNotContainsString($case->case_number, $html);
        $this->assertSame(config('app.public_url').'/login', $mail->viewData['actionUrl']);
    }

    public function test_university_service_email_uses_assignment_specific_copy(): void
    {
        $student = $this->student();
        $assignment = (new UsAssignment)->forceFill([
            'office_name' => 'University Library',
            'total_hours_required' => 12,
            'target_completion_date' => '2026-09-30',
            'status' => 'pending',
        ]);

        $mail = (new UniversityServiceStatusNotification($assignment, 'assigned'))
            ->toMail($student);

        $this->assertSame('Your University Service assignment is ready', $mail->subject);
        $this->assertSame('September 30, 2026', $mail->viewData['content']['deadline']);
        $this->assertStringContainsString(
            '12 hours of University Service at University Library',
            $mail->viewData['content']['message']
        );
    }

    private function student(string $status = 'active'): User
    {
        return User::create([
            'last_name' => 'Student',
            'first_name' => 'Email',
            'middle_name' => 'Test',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => $status,
        ]);
    }
}
