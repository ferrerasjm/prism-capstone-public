<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Sanction;

/**
 * Covers every state change of an Evaluation & Monitoring period: started (NFI
 * issuance), a reminder near the end date, extended (officer moved the end date without
 * closing it), and closed (Satisfactory or Needs Further Intervention). One hardcoded
 * "your period ends on X" message used to be reused for all of these -- accurate at
 * issuance, but wrong once the period had actually already closed, and silent on what
 * the officer's finding was.
 */
class EvaluationMonitoringNotification extends StudentNotification
{
    public const STARTED = 'started';

    public const REMINDER = 'reminder';

    public const EXTENDED = 'extended';

    public const CLOSED_SATISFACTORY = 'closed_satisfactory';

    public const CLOSED_NEEDS_INTERVENTION = 'closed_needs_intervention';

    public function __construct(
        private readonly Sanction $sanction,
        private readonly string $event = self::STARTED,
    ) {}

    public function toDatabase(object $notifiable): array
    {
        $case = $this->sanction->resolution?->case;
        $endDate = $this->sanction->due_date?->format('F d, Y');

        [$kind, $title, $message] = match ($this->event) {
            self::REMINDER => [
                'evaluation_monitoring_reminder',
                'Evaluation monitoring period ending soon',
                "Reminder: Your evaluation monitoring period ends on {$endDate}.",
            ],
            self::EXTENDED => [
                'evaluation_monitoring_extended',
                'Evaluation monitoring period updated',
                "Your evaluation monitoring period now ends on {$endDate}.",
            ],
            self::CLOSED_SATISFACTORY => [
                'evaluation_monitoring_closed',
                'Evaluation monitoring period completed',
                'Your evaluation monitoring period has been completed with an assessment of Satisfactory.',
            ],
            self::CLOSED_NEEDS_INTERVENTION => [
                'evaluation_monitoring_closed',
                'Evaluation monitoring period completed',
                'Your evaluation monitoring period has been completed with an assessment of Needs Further '
                    .'Intervention. You have been referred to the Guidance Services Office as a result.',
            ],
            default => [
                'evaluation_monitoring_scheduled',
                'Evaluation monitoring period set',
                "Your evaluation monitoring period ends on {$endDate}.",
            ],
        };

        return [
            'kind' => $kind,
            'title' => $title,
            'message' => $message,
            'sanction_id' => $this->sanction->id,
            'case_id' => $case?->id,
            'case_number' => $case?->case_number,
            'deadline' => $this->sanction->due_date?->toDateString(),
            'action_label' => 'View My Sanctions',
            'url' => route('student.sanctions.index', [], false),
        ];
    }
}
