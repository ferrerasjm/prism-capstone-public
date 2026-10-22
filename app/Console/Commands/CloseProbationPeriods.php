<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Sanction;
use App\Services\MonitoringPeriodService;
use Illuminate\Console\Command;

/**
 * Category 1 probation is the one intervention the handbook attaches an escalation clause
 * to: "Subsequent commission of any major offense and/or infringement of the University
 * Rules and Regulations committed during the probationary period shall mean SUSPENSION or
 * NON-READMISSION the next term" (Section VI.E).
 *
 * "Any major offense and/or infringement" is broader than a disciplinary case -- minor
 * offenses are recorded as violation slips and never become cases on their own -- so the
 * breach check covers both.
 *
 * A clean period is completed outright. A breach is flagged rather than acted on: the
 * handbook leaves the choice between suspension and non-readmission to the officer, and
 * neither is a penalty this system should impose off an inference of its own.
 */
class CloseProbationPeriods extends Command
{
    protected $signature = 'sanctions:close-probation-periods';

    protected $description = 'Close probation periods whose end date has passed';

    public function __construct(
        private readonly MonitoringPeriodService $periods
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $completed = 0;
        $flagged = 0;

        Sanction::query()
            ->with('resolution.case')
            ->where('sanction_type', 'probation')
            ->where('status', 'in_progress')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->each(function (Sanction $sanction) use (&$completed, &$flagged): void {
                $breaches = $this->periods->probationBreachCount($sanction);

                if ($breaches === 0) {
                    $sanction->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);

                    $this->log($sanction, 'completed', 'Probation period ended with no further infringements.');
                    $completed++;

                    return;
                }

                // Flagged once: re-running the command must not spam the audit trail.
                if ($sanction->monitoring_review_flagged_at !== null) {
                    return;
                }

                $sanction->update(['monitoring_review_flagged_at' => now()]);

                $this->log(
                    $sanction,
                    'breach_flagged_for_review',
                    "Probation period ended with {$breaches} infringement(s). Officer decision required "
                        .'on suspension or non-readmission.',
                );
                $flagged++;
            });

        $this->info("Completed {$completed} probation period(s); flagged {$flagged} for officer decision.");

        return self::SUCCESS;
    }

    private function log(Sanction $sanction, string $outcome, string $reason): void
    {
        AuditLog::create([
            'user_id' => null,
            'action' => 'update',
            'module' => 'sanctions',
            'record_id' => $sanction->id,
            'old_values' => ['status' => 'in_progress'],
            'new_values' => ['status' => $sanction->status, 'outcome' => $outcome, 'reason' => $reason],
            'ip_address' => null,
            'created_at' => now(),
        ]);
    }
}
