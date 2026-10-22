<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AcademicPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AcademicPeriodStartedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly AcademicPeriod $period) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'academic_period_started',
            'title' => 'New academic term started',
            'message' => "{$this->period->label} is now the current term.",
            'academic_period_id' => $this->period->id,
            'action_label' => 'View Academic Periods',
            'url' => route('admin.academic-periods.index', absolute: false),
        ];
    }
}
