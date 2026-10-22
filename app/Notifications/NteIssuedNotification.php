<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CaseNotice;
use App\Models\DisciplinaryCase;

class NteIssuedNotification extends StudentNotification
{
    public function __construct(
        private readonly DisciplinaryCase $case,
        private readonly CaseNotice $notice
    ) {}

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'nte',
            'title' => 'Notice to Explain',
            'message' => "A Notice to Explain has been issued for case {$this->case->case_number}. Review the notice and submit your written explanation.",
            'case_id' => $this->case->id,
            'case_number' => $this->case->case_number,
            'notice_id' => $this->notice->id,
            'deadline' => $this->notice->response_deadline->toDateString(),
            'action_label' => 'Review and Respond',
            'url' => route('student.cases.explain', $this->case, false),
        ];
    }
}
