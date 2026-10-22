<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CaseResolution;
use App\Models\DisciplinaryCase;

class SanctionsAssignedNotification extends StudentNotification
{
    public function __construct(
        private readonly DisciplinaryCase $case,
        private readonly CaseResolution $resolution,
    ) {}

    public function via(object $notifiable): array
    {
        // The resolution notification already carries the combined email; this stays database-only.
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $labels = $this->resolution->sanctions
            ->map(fn ($sanction) => $sanction->typeLabel())
            ->values();

        return [
            'kind' => 'sanctions_assigned',
            'title' => 'Disciplinary requirements assigned',
            'message' => "Requirements for {$this->case->case_number}: ".$labels->join(', ', ' and ').'.',
            'case_id' => $this->case->id,
            'case_number' => $this->case->case_number,
            'sanction_types' => $this->resolution->sanctions->pluck('sanction_type')->values()->all(),
            'action_label' => 'View Requirements',
            'url' => route('student.sanctions.index', [], false),
        ];
    }
}
