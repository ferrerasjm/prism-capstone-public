<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DisciplinaryCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CoordinatorCaseNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly DisciplinaryCase $case,
        private readonly string $kind,
        private readonly string $title,
        private readonly string $message,
        private readonly ?string $url = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            'case_id' => $this->case->id,
            'case_number' => $this->case->case_number,
            'action_label' => 'View Case',
            'url' => $this->url ?? route('coordinator.cases.show', $this->case, false),
        ];
    }
}