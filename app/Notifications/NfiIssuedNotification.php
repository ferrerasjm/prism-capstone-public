<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CaseNotice;
use App\Models\DisciplinaryCase;

class NfiIssuedNotification extends StudentNotification
{
    public function __construct(
        private readonly DisciplinaryCase $case,
        private readonly CaseNotice $notice,
    ) {}

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'nfi',
            'title' => 'Notice of Formative Intervention issued',
            'message' => "An NFI has been issued for {$this->case->case_number}. Review your assigned requirements.",
            'case_id' => $this->case->id,
            'notice_id' => $this->notice->id,
            'action_label' => 'Review NFI',
            'url' => route('student.cases.nfi', $this->case, false),
        ];
    }
}
