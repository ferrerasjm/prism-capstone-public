<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\UpccReferral;

class UpccDecisionNotification extends StudentNotification
{
    public function __construct(
        private readonly UpccReferral $referral,
        private readonly string $event,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->event === 'finalized'
            ? ['database']
            : parent::via($notifiable);
    }

    public function toDatabase(object $notifiable): array
    {
        $case = $this->referral->case;
        [$title, $message] = match ($this->event) {
            'distributed' => [
                'UPCC decision available',
                "A decision for {$case->case_number} has been distributed. The appeal window closes on {$this->referral->appeal_window_end->format('F d, Y')}.",
            ],
            'reconsideration_resolved' => [
                'Appeal outcome recorded',
                "The reconsideration outcome for {$case->case_number} has been recorded.",
            ],
            'finalized' => [
                'UPCC decision finalized',
                "The decision for {$case->case_number} is final and is proceeding to formal resolution.",
            ],
            'cleared' => [
                'No violation found',
                "The UPCC found no violation in {$case->case_number}. The case is now closed and no sanction applies.",
            ],
            default => ['UPCC case updated', "Case {$case->case_number} has been updated."],
        };

        return [
            'kind' => 'upcc_'.$this->event,
            'title' => $title,
            'message' => $message,
            'case_id' => $case->id,
            'referral_id' => $this->referral->id,
            'appeal_deadline' => $this->referral->appeal_window_end?->toDateString(),
            'action_label' => 'View Case',
            'url' => route('student.cases.show', $case, false),
        ];
    }
}
