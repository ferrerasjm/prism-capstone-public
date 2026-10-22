<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\UpccReferral;

class UpccConferenceScheduledNotification extends StudentNotification
{
    public function __construct(
        private readonly UpccReferral $referral,
        private readonly bool $rescheduled = false,
    ) {}

    public function toDatabase(object $notifiable): array
    {
        $case = $this->referral->case;
        $schedule = $this->referral->scheduled_conference_at->format('F d, Y \a\t g:i A');
        $location = $this->referral->conference_location;

        return [
            'kind' => $this->rescheduled ? 'upcc_conference_rescheduled' : 'upcc_conference_scheduled',
            'title' => $this->rescheduled ? 'Case conference rescheduled' : 'Case conference scheduled',
            'message' => $this->rescheduled
                ? "The case conference for {$case->case_number} has been moved to {$schedule} at {$location}. A corrected Notice of Hearing has been issued; the earlier notice no longer applies."
                : "A case conference for {$case->case_number} is scheduled for {$schedule} at {$location}. Review the Notice of Hearing for details.",
            'case_id' => $case->id,
            'case_number' => $case->case_number,
            'referral_id' => $this->referral->id,
            'deadline' => $this->referral->scheduled_conference_at->toDateString(),
            'action_label' => 'View Case',
            'url' => route('student.cases.show', $case, false),
        ];
    }
}
