<?php

namespace App\Notifications;

use App\Models\UsAssignment;
use Illuminate\Notifications\Notification;

class UsDeadlineReviewNotification extends Notification
{
    public function __construct(private readonly UsAssignment $assignment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'us_deadline_review',
            'title' => 'University Service deadline needs review',
            'message' => "The service deadline has passed with {$this->assignment->remainingDuration()} remaining. Review the delay and extend the deadline or mark non-compliant. No registration hold was applied automatically.",
            'us_assignment_id' => $this->assignment->id,
            'action_label' => 'Review Service',
            'url' => route('officer.us.show', $this->assignment, false),
        ];
    }
}
