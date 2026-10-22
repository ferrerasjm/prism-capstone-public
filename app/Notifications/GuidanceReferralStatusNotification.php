<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CounselingReferral;

class GuidanceReferralStatusNotification extends StudentNotification
{
    public function __construct(private readonly CounselingReferral $referral) {}

    public function via(object $notifiable): array
    {
        return $this->referral->status === 'completed'
            ? ['database']
            : parent::via($notifiable);
    }

    public function toDatabase(object $notifiable): array
    {
        $completed = $this->referral->status === 'completed';

        return [
            'kind' => 'guidance_referral_status',
            'title' => $completed ? 'Guidance referral completed' : 'Guidance referral requires follow-up',
            'message' => $completed
                ? 'Your Guidance referral requirement has been marked completed.'
                : 'Your Guidance referral is currently incomplete. Please coordinate with the Student Discipline Office for the required follow-up.',
            'referral_id' => $this->referral->id,
            'status' => $this->referral->status,
            'action_label' => 'View Requirements',
            'url' => route('student.sanctions.index', [], false),
        ];
    }
}
