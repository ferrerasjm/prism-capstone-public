<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CaseExplanation;
use App\Models\DisciplinaryCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CaseExplanationSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly DisciplinaryCase $case,
        private readonly CaseExplanation $explanation,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $studentName = $this->case->student?->full_name ?? 'A student';

        return [
            'kind' => 'case_explanation_submitted',
            'title' => 'Student explanation submitted',
            'message' => "{$studentName} submitted a written explanation for case {$this->case->case_number}. Review and evaluate the response.",
            'case_id' => $this->case->id,
            'case_number' => $this->case->case_number,
            'student_id' => $this->case->student_id,
            'explanation_id' => $this->explanation->id,
            'submitted_at' => $this->explanation->submitted_at?->toDateTimeString(),
            'action_label' => 'Review Explanation',
            'url' => route('officer.cases.show', $this->case, false).'#action-panel',
        ];
    }
}
