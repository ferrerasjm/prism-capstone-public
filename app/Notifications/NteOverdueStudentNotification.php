<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CaseNotice;
use App\Models\DisciplinaryCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NteOverdueStudentNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly DisciplinaryCase $case,
        private readonly CaseNotice $notice,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'nte_overdue',
            'title' => 'Response deadline passed',
            'message' => "The response deadline for {$this->case->case_number} has passed. The case may now proceed using the available evidence.",
            'case_id' => $this->case->id,
            'case_number' => $this->case->case_number,
            'notice_id' => $this->notice->id,
            'deadline' => $this->notice->response_deadline->toDateString(),
            'action_label' => 'View Case',
            'url' => route('student.cases.show', $this->case, false),
        ];
    }
}
