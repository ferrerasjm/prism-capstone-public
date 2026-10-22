<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CaseNotice;
use App\Models\DisciplinaryCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NteOverdueNotification extends Notification
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
            'title' => 'NTE response deadline passed',
            'message' => "No response was received for {$this->case->case_number} by its deadline of {$this->notice->response_deadline->format('F d, Y')}. The case may proceed using the available evidence.",
            'case_id' => $this->case->id,
            'case_number' => $this->case->case_number,
            'notice_id' => $this->notice->id,
            'deadline' => $this->notice->response_deadline->toDateString(),
            'action_label' => 'Review Case',
            'url' => route('officer.cases.show', $this->case, false),
        ];
    }
}
