<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UpccReferral;
use App\Services\CoordinatorCaseService;
use Illuminate\Console\Command;

/**
 * Finalizes UPCC decisions whose appeal window has closed without an
 * appeal being filed. Scheduled daily.
 */
class FinalizeExpiredAppealWindows extends Command
{
    protected $signature = 'cases:finalize-expired-appeals';

    protected $description = 'Automatically finalize distributed UPCC decisions whose appeal windows expired without an appeal';

    public function handle(CoordinatorCaseService $service): int
    {
        $finalized = 0;
        $today = today();

        UpccReferral::query()
            ->with('case.student')
            ->whereNotNull('decision_distributed_at')
            ->whereNull('decision_final_at')
            ->where('appeal_filed', false)
            ->whereDate('appeal_window_end', '<', $today->toDateString())
            ->whereHas('case', fn ($query) => $query->where('status', 'pending_upcc'))
            ->lazyById()
            ->each(function (UpccReferral $referral) use ($service, &$finalized): void {
                $case = $referral->case;

                if (! $case) {
                    return;
                }

                $service->finalizeDecision(
                    $case,
                    $referral,
                    null,
                    'Appeal window expired without a filed appeal; finalized automatically by scheduler.'
                );

                $finalized++;
            });

        $this->info("Finalized {$finalized} expired UPCC appeal window(s).");

        return self::SUCCESS;
    }
}
