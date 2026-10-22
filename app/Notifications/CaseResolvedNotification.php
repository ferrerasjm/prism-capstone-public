<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CaseResolution;
use App\Models\DisciplinaryCase;

class CaseResolvedNotification extends StudentNotification
{
    public function __construct(
        private readonly DisciplinaryCase $case,
        private readonly ?CaseResolution $resolution = null,
    ) {}

    public function toDatabase(object $notifiable): array
    {
        $appealText = $this->resolution?->appeal_deadline
            ? ' The appeal deadline is '.$this->resolution->appeal_deadline->format('F d, Y').'.'
            : '';

        return [
            'kind' => 'case_resolved',
            'title' => 'Case resolved',
            'message' => "Case {$this->case->case_number} has been resolved.{$appealText}",
            'case_id' => $this->case->id,
            'case_number' => $this->case->case_number,
            'appeal_deadline' => $this->resolution?->appeal_deadline?->toDateString(),
            'action_label' => 'View Resolution',
            'url' => route('student.cases.show', $this->case, false),
        ];
    }
}
