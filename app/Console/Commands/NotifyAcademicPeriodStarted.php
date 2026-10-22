<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AcademicPeriodNotificationService;
use Illuminate\Console\Command;

/**
 * Sends the start-of-period notification for any academic period that has
 * just begun. Scheduled daily.
 */
class NotifyAcademicPeriodStarted extends Command
{
    protected $signature = 'academic-periods:notify-started';

    protected $description = 'Notify Super Administrators when a configured academic term starts';

    public function handle(AcademicPeriodNotificationService $notifications): int
    {
        $sent = $notifications->notifyStartedPeriods();

        $this->info("Sent {$sent} academic-period notification(s).");

        return self::SUCCESS;
    }
}
