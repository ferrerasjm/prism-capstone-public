<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\User;
use App\Notifications\AcademicPeriodStartedNotification;

/**
 * Notifies staff when an academic period has started. Driven by the
 * `academic-periods:notify-started` scheduled command rather than called
 * from a request.
 */
class AcademicPeriodNotificationService
{
    public function notifyStartedPeriods(?AcademicPeriod $onlyPeriod = null): int
    {
        $periods = AcademicPeriod::query()
            ->active()
            ->containing(today())
            ->when($onlyPeriod, fn ($query) => $query->whereKey($onlyPeriod->id))
            ->get();

        if ($periods->isEmpty()) {
            return 0;
        }

        $admins = User::query()
            ->where('role', 'super_admin')
            ->where('status', 'active')
            ->get();
        $sent = 0;

        foreach ($periods as $period) {
            foreach ($admins as $admin) {
                $alreadyNotified = $admin->notifications()
                    ->where('type', AcademicPeriodStartedNotification::class)
                    ->where('data->academic_period_id', $period->id)
                    ->exists();

                if ($alreadyNotified) {
                    continue;
                }

                $admin->notify(new AcademicPeriodStartedNotification($period));
                $sent++;
            }
        }

        return $sent;
    }
}
