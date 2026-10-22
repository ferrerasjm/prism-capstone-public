<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\UpccReferral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UpccAppealSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly UpccReferral $referral,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $case = $this->referral->case;

        [$title, $actorLabel] = match ($this->referral->appeal_source) {
            'reporting_user_portal' => ['Complainant appeal submitted', 'The complainant'],
            'coordinator_entry' => ['Appeal recorded', 'A coordinator'],
            default => ['Student appeal submitted', 'The student'],
        };

        $actorName = $this->referral->appealFiledBy?->full_name;
        $actor = $actorName ? "{$actorName} ({$actorLabel})" : $actorLabel;

        return [
            'kind' => 'upcc_appeal_submitted',
            'title' => $title,
            'message' => "{$actor} submitted an appeal for case {$case?->case_number}. Review the appeal and record the reconsideration outcome.",
            'case_id' => $case?->id,
            'case_number' => $case?->case_number,
            'student_id' => $case?->student_id,
            'referral_id' => $this->referral->id,
            'appeal_filed_at' => $this->referral->appeal_filed_at?->toDateTimeString(),
            'action_label' => 'Review Appeal',
            'url' => $case ? route('coordinator.cases.show', $case, false).'#appeal-window' : route('coordinator.cases.index', [], false),
        ];
    }
}
