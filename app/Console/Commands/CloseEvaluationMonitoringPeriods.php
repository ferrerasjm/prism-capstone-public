<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Sanction;
use App\Notifications\EvaluationMonitoringNotification;
use App\Services\MonitoringPeriodService;
use Illuminate\Console\Command;

/**
 * Evaluation is a Category 2 formative intervention (Handbook Section VI.E): it closes
 * with the Discipline Officer's finding on whether the intervention is working, not with
 * a verdict on the student's conduct. Category 2 carries no escalation clause -- only
 * Category 1 probation does -- so nothing here can mark a student non-compliant.
 *
 * A period that ran clean is completed outright with a Satisfactory finding: "no further
 * incidents" is an objective observation the record already supports. A period with new
 * cases needs a judgement rather than an inference, so it is flagged and left for the
 * officer to assess.
 */
class CloseEvaluationMonitoringPeriods extends Command
{
    protected $signature = 'sanctions:close-monitoring-periods';

    protected $description = 'Close evaluation monitoring periods whose end date has passed';

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
            ->with(['resolution.case', 'student'])
            ->where('sanction_type', 'evaluation_monitoring')
            ->where('status', 'in_progress')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->each(function (Sanction $sanction) use (&$completed, &$flagged): void {
                $casesInPeriod = $this->periods->casesDuring($sanction)->count();

                if ($casesInPeriod === 0) {
                    $sanction->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'assessment_outcome' => 'satisfactory',
                    ]);

                    $this->log($sanction, 'completed', 'Monitoring period ended with no new cases.');

                    // A manual close already tells the student; an automatic one must too --
                    // otherwise a clean period closes silently and only ever shows up if the
                    // student happens to revisit their sanctions page.
                    $sanction->student?->notify(
                        new EvaluationMonitoringNotification($sanction, EvaluationMonitoringNotification::CLOSED_SATISFACTORY)
                    );

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
                    'awaiting_assessment',
                    "Monitoring period ended with {$casesInPeriod} new case(s). Officer assessment required.",
                );
                $flagged++;
            });

        $this->info("Completed {$completed} monitoring period(s); {$flagged} awaiting officer assessment.");

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
