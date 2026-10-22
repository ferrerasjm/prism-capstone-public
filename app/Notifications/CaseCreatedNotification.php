<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DisciplinaryCase;

class CaseCreatedNotification extends StudentNotification
{
    public function __construct(private readonly DisciplinaryCase $case) {}

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'case_created',
            'title' => 'Disciplinary case opened',
            'message' => "Case {$this->case->case_number} has been opened for review. No response is required unless a formal notice is issued.",
            'case_id' => $this->case->id,
            'case_number' => $this->case->case_number,
            'action_label' => 'View Case',
            'url' => route('student.cases.show', $this->case, false),
        ];
    }
}
