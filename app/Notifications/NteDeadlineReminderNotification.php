<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CaseNotice;
use App\Models\DisciplinaryCase;

class NteDeadlineReminderNotification extends StudentNotification
{
    public function __construct(
        private readonly DisciplinaryCase $case,
        private readonly CaseNotice $notice,
    ) {}

    public function via(object $notifiable): array
    {
        return config('notifications.nte_reminder.mail_enabled', true)
            ? ['database', 'mail']
            : ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'nte_reminder',
            'title' => 'NTE response deadline approaching',
            'message' => "Your response for {$this->case->case_number} is due on {$this->notice->response_deadline->format('F d, Y')}.",
            'case_id' => $this->case->id,
            'case_number' => $this->case->case_number,
            'notice_id' => $this->notice->id,
            'deadline' => $this->notice->response_deadline->toDateString(),
            'action_label' => 'Review and Respond',
            'url' => route('student.cases.explain', $this->case, false),
        ];
    }
}
