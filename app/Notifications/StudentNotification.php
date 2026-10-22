<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\UsAssignment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class StudentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        // Pending-setup students must still receive email; account setup status gates portal access, not delivery.
        return ['database', 'mail'];
    }

    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
            'mail' => (string) config('queue.default', 'database'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toDatabase($notifiable);
        $recipient = method_exists($notifiable, 'fresh')
            ? ($notifiable->fresh() ?? $notifiable)
            : $notifiable;
        $name = collect([
            $recipient->first_name ?? null,
            $recipient->middle_name ?? null,
            $recipient->last_name ?? null,
        ])->filter(fn (mixed $part): bool => trim((string) $part) !== '')
            ->map(fn (mixed $part): string => trim((string) $part))
            ->implode(' ');
        $content = $this->emailContent($data);
        $viewData = [
            'recipientName' => $name !== '' ? $name : 'Student',
            'recipientEmail' => (string) ($recipient->email ?? ''),
            'content' => $content,
            'actionLabel' => (string) ($data['action_label'] ?? 'Open PRISM'),
            'actionUrl' => $this->publicUrl('/login'),
            'logoUrl' => $this->publicUrl('/assets/logo.png'),
            'fontRegularUrl' => $this->publicUrl('/email-assets/fonts/poppins/regular'),
            'fontMediumUrl' => $this->publicUrl('/email-assets/fonts/poppins/medium'),
            'fontSemiBoldUrl' => $this->publicUrl('/email-assets/fonts/poppins/semibold'),
            'fontBoldUrl' => $this->publicUrl('/email-assets/fonts/poppins/bold'),
            'year' => now()->year,
        ];

        return (new MailMessage)
            ->subject($content['subject'])
            ->view([
                'html' => 'emails.student-notification',
                'text' => 'emails.student-notification-text',
            ], $viewData);
    }

    abstract public function toDatabase(object $notifiable): array;

    private function emailContent(array $data): array
    {
        $kind = (string) ($data['kind'] ?? 'student_update');

        return match ($kind) {
            'case_created' => [
                'subject' => 'A disciplinary case has been opened in PRISM',
                'label' => 'Case update',
                'tone' => 'action',
                'headline' => 'A case has been opened for review',
                'message' => 'No response is required unless a formal notice is issued. Sign in to PRISM to securely review the case and monitor future updates.',
                'deadlineLabel' => null,
                'deadline' => null,
            ],
            'nte' => [
                'subject' => 'Action required: Notice to Explain',
                'label' => 'Action required',
                'tone' => 'urgent',
                'headline' => 'Your Notice to Explain is ready',
                'message' => 'Please sign in to review the notice and submit your written explanation before the response period closes.',
                'deadlineLabel' => 'Response deadline',
                'deadline' => $this->humanDate($data['deadline'] ?? null),
            ],
            'nte_reminder' => [
                'subject' => 'Reminder: your response deadline is approaching',
                'label' => 'Deadline reminder',
                'tone' => 'urgent',
                'headline' => 'Your response is due soon',
                'message' => 'Your Notice to Explain is still awaiting a response. Open PRISM to review the notice and submit your explanation.',
                'deadlineLabel' => 'Response deadline',
                'deadline' => $this->humanDate($data['deadline'] ?? null),
            ],
            'nfi' => [
                'subject' => 'Your formative intervention notice is available',
                'label' => 'New requirement',
                'tone' => 'action',
                'headline' => 'A formative intervention has been assigned',
                'message' => 'Sign in to review the Notice of Formative Intervention and the requirements recorded for you.',
                'deadlineLabel' => null,
                'deadline' => null,
            ],
            'case_resolved' => [
                'subject' => 'Your case resolution is available in PRISM',
                'label' => 'Decision available',
                'tone' => 'decision',
                'headline' => 'A formal resolution has been recorded',
                'message' => 'Review the resolution, assigned requirements, and any appeal information securely in your student portal.',
                'deadlineLabel' => ! empty($data['appeal_deadline']) ? 'Appeal deadline' : null,
                'deadline' => $this->humanDate($data['appeal_deadline'] ?? null),
            ],
            'guidance_referral_status' => [
                'subject' => 'Guidance referral follow-up required',
                'label' => 'Follow-up required',
                'tone' => 'support',
                'headline' => 'Your Guidance referral needs attention',
                'message' => 'The referral is currently incomplete. Sign in to review your requirements and coordinate with the Student Discipline Office.',
                'deadlineLabel' => null,
                'deadline' => null,
            ],
            'university_service_assigned' => [
                'subject' => 'Your University Service assignment is ready',
                'label' => 'New assignment',
                'tone' => 'service',
                'headline' => 'University Service has been assigned',
                'message' => $this->universityServiceAssignmentMessage($data),
                'deadlineLabel' => ! empty($data['target_completion_date']) ? 'Target completion' : null,
                'deadline' => $this->humanDate($data['target_completion_date'] ?? null),
            ],
            'university_service_deadline_reminder', 'university_service_deadline_missed', 'registration_hold_applied' => [
                'subject' => $data['title'],
                'label' => 'Action required',
                'tone' => 'urgent',
                'headline' => $data['title'],
                'message' => $data['message'],
                'deadlineLabel' => ! empty($data['target_completion_date']) ? 'Target completion' : null,
                'deadline' => $this->humanDate($data['target_completion_date'] ?? null),
            ],
            'university_service_non_compliant' => [
                'subject' => 'University Service follow-up required',
                'label' => 'Immediate follow-up',
                'tone' => 'urgent',
                'headline' => 'Your University Service status needs attention',
                'message' => 'Your assignment was marked non-compliant. Sign in to review the status and contact the Student Discipline Office for the next steps.',
                'deadlineLabel' => null,
                'deadline' => null,
            ],
            'discipline_education_scheduled', 'discipline_education_reminder' => [
                'subject' => $kind === 'discipline_education_reminder'
                    ? 'Reminder: Discipline Education session approaching'
                    : 'Your Discipline Education session has been scheduled',
                'label' => $kind === 'discipline_education_reminder' ? 'Session reminder' : 'Session scheduled',
                'tone' => 'action',
                'headline' => $kind === 'discipline_education_reminder'
                    ? 'Your Discipline Education session is approaching'
                    : 'A Discipline Education session has been scheduled',
                'message' => (string) ($data['message'] ?? ''),
                'deadlineLabel' => 'Session date',
                'deadline' => $this->humanDate($data['deadline'] ?? null),
            ],
            'discipline_education_completed', 'discipline_education_absent' => [
                'subject' => $kind === 'discipline_education_completed'
                    ? 'Discipline Education attendance completed'
                    : 'Discipline Education attendance follow-up required',
                'label' => $kind === 'discipline_education_completed' ? 'Attendance recorded' : 'Follow-up required',
                'tone' => $kind === 'discipline_education_completed' ? 'support' : 'urgent',
                'headline' => (string) ($data['title'] ?? 'Discipline Education attendance updated'),
                'message' => (string) ($data['message'] ?? ''),
                'deadlineLabel' => null,
                'deadline' => null,
            ],
            'evaluation_monitoring_scheduled', 'evaluation_monitoring_reminder', 'evaluation_monitoring_extended' => [
                'subject' => match ($kind) {
                    'evaluation_monitoring_reminder' => 'Reminder: evaluation monitoring period ending soon',
                    'evaluation_monitoring_extended' => 'Your evaluation monitoring period has been updated',
                    default => 'Your evaluation monitoring period has been set',
                },
                'label' => match ($kind) {
                    'evaluation_monitoring_reminder' => 'Period reminder',
                    'evaluation_monitoring_extended' => 'Period updated',
                    default => 'Monitoring period',
                },
                'tone' => 'action',
                'headline' => match ($kind) {
                    'evaluation_monitoring_reminder' => 'Your monitoring period is ending soon',
                    'evaluation_monitoring_extended' => 'Your monitoring end date has changed',
                    default => 'Your monitoring end date is available',
                },
                'message' => (string) ($data['message'] ?? ''),
                'deadlineLabel' => 'Monitoring end date',
                'deadline' => $this->humanDate($data['deadline'] ?? null),
            ],
            'evaluation_monitoring_closed' => [
                'subject' => 'Your evaluation monitoring period has been completed',
                'label' => 'Period completed',
                'tone' => 'decision',
                'headline' => 'Your evaluation monitoring period has been completed',
                'message' => (string) ($data['message'] ?? ''),
                'deadlineLabel' => null,
                'deadline' => null,
            ],
            'upcc_conference_scheduled', 'upcc_conference_rescheduled' => [
                'subject' => $kind === 'upcc_conference_rescheduled'
                    ? 'Your case conference has been rescheduled'
                    : 'Your case conference has been scheduled',
                'label' => $kind === 'upcc_conference_rescheduled' ? 'Schedule changed' : 'Conference scheduled',
                'tone' => 'action',
                'headline' => $kind === 'upcc_conference_rescheduled'
                    ? 'Your case conference has a new date'
                    : 'A case conference has been scheduled',
                'message' => (string) ($data['message'] ?? ''),
                'deadlineLabel' => 'Conference date',
                'deadline' => $this->humanDate($data['deadline'] ?? null),
            ],
            'upcc_distributed' => [
                'subject' => 'Your UPCC decision is available',
                'label' => 'Decision available',
                'tone' => 'decision',
                'headline' => 'The UPCC has released a decision',
                'message' => 'Sign in to securely review the decision and the options available during the appeal period.',
                'deadlineLabel' => ! empty($data['appeal_deadline']) ? 'Appeal deadline' : null,
                'deadline' => $this->humanDate($data['appeal_deadline'] ?? null),
            ],
            'upcc_reconsideration_resolved' => [
                'subject' => 'Your reconsideration outcome is available',
                'label' => 'Outcome available',
                'tone' => 'decision',
                'headline' => 'A reconsideration outcome has been recorded',
                'message' => 'Open PRISM to securely review the recorded outcome and the current status of your case.',
                'deadlineLabel' => null,
                'deadline' => null,
            ],
            default => [
                'subject' => (string) ($data['title'] ?? 'New PRISM student update'),
                'label' => 'Student update',
                'tone' => 'action',
                'headline' => (string) ($data['title'] ?? 'A new update is available'),
                'message' => 'Sign in to PRISM to securely review the update and any next steps.',
                'deadlineLabel' => ! empty($data['deadline']) ? 'Deadline' : null,
                'deadline' => $this->humanDate($data['deadline'] ?? null),
            ],
        };
    }

    private function humanDate(mixed $date): ?string
    {
        return $date ? Carbon::parse($date)->format('F j, Y') : null;
    }

    private function publicUrl(string $path): string
    {
        $baseUrl = rtrim(
            (string) config('app.public_url', config('app.url', 'http://localhost')),
            '/'
        );

        return $baseUrl.'/'.ltrim($path, '/');
    }

    private function universityServiceAssignmentMessage(array $data): string
    {
        $hours = $data['total_hours_required'] ?? null;
        $office = trim((string) ($data['office_name'] ?? ''));

        if ($hours && $office !== '') {
            $duration = UsAssignment::formatDuration($hours);

            return "You have been assigned {$duration} of University Service at {$office}. Open PRISM to review the assignment and attendance instructions.";
        }

        return 'A University Service assignment has been created for you. Open PRISM to review the location, required hours, and attendance instructions.';
    }
}
