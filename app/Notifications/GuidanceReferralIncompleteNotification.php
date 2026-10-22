<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CounselingReferral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the Discipline Officer that the Guidance Office returned a counseling
 * referral as incomplete, so the resulting Non-Compliant sanction needs a
 * follow-up decision on the related case. Without this, that outcome was
 * only visible if the officer happened to reopen the sanction on their own.
 */
class GuidanceReferralIncompleteNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly CounselingReferral $referral) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'guidance_referral_incomplete',
            'title' => 'Counseling referral returned incomplete',
            'message' => "The Guidance Office returned {$this->referral->student?->full_name}'s counseling referral as incomplete. The sanction is now Non-Compliant and needs your follow-up.",
            'referral_id' => $this->referral->id,
            'sanction_id' => $this->referral->sanction_id,
            'action_label' => 'View Sanction',
            'url' => route('officer.sanctions.show', $this->referral->sanction_id, false),
        ];
    }
}
