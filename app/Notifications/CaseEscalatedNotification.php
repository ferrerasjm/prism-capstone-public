<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DisciplinaryCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CaseEscalatedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly DisciplinaryCase $case) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'case_escalated',
            'title' => 'Case classification updated',
            'message' => "Case {$this->case->case_number} was escalated for formal review under Habitual Disregard. This is not yet a final decision.",
            'case_id' => $this->case->id,
            'case_number' => $this->case->case_number,
            'action_label' => 'View Case',
            'url' => route('student.cases.show', $this->case, false),
        ];
    }
}
