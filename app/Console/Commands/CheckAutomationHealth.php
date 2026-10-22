<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CaseNotice;
use App\Models\ScheduledReport;
use App\Models\UsAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Operational health check for PRISM automation: queue depth, failed jobs,
 * unprocessed overdue NTE notices, unflagged overdue University Service
 * assignments and the previous monthly archive. Run it before a demo to
 * see the system's own view of its health.
 */
class CheckAutomationHealth extends Command
{
    protected $signature = 'prism:automation-check {--fail-on-warning : Return a failing exit code when warnings are found}';

    protected $description = 'Check scheduler and queue readiness for PRISM automation';

    public function handle(): int
    {
        $warnings = [];

        $this->info('PRISM automation health check');
        $this->line('Timezone: '.config('app.timezone'));
        $this->checkQueue($warnings);
        $this->checkNteAutomation($warnings);
        $this->checkUniversityServiceAutomation($warnings);
        $this->checkMonthlyArchive($warnings);

        if ($warnings === []) {
            $this->info('Automation health looks good.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Warnings found:');

        foreach ($warnings as $warning) {
            $this->warn('- '.$warning);
        }

        return $this->option('fail-on-warning') ? self::FAILURE : self::SUCCESS;
    }

    private function checkQueue(array &$warnings): void
    {
        $connection = (string) config('queue.default');

        $this->line("Queue connection: {$connection}");

        if (app()->environment('production') && in_array($connection, ['sync', 'null'], true)) {
            $warnings[] = 'Production queue connection should not be sync/null. Use database, redis, or another persistent queue driver.';
        }

        if ($connection !== 'database') {
            return;
        }

        foreach (['jobs', 'failed_jobs'] as $table) {
            if (! Schema::hasTable($table)) {
                $warnings[] = "Database queue table '{$table}' is missing. Run migrations before starting queue workers.";

                return;
            }
        }

        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        $this->line("Pending queued jobs: {$pendingJobs}");
        $this->line("Failed queued jobs: {$failedJobs}");

        if ($failedJobs > 0) {
            $warnings[] = "There are {$failedJobs} failed queued job(s). Review them with php artisan queue:failed.";
        }
    }

    private function checkNteAutomation(array &$warnings): void
    {
        $unprocessed = CaseNotice::query()
            ->where('notice_type', 'notice_to_explain')
            ->where('is_voided', false)
            ->whereNull('student_acknowledged_at')
            ->whereNull('overdue_flagged_at')
            ->whereDate('response_deadline', '<', today())
            ->whereHas('case', fn ($query) => $query
                ->where('status', 'pending_nte')
                ->whereDoesntHave('explanation'))
            ->count();

        $this->line("Unprocessed overdue NTE notices: {$unprocessed}");

        if ($unprocessed > 0) {
            $warnings[] = "{$unprocessed} overdue NTE notice(s) have not been flagged. Confirm the scheduler is running cases:process-nte-deadlines.";
        }
    }

    private function checkUniversityServiceAutomation(array &$warnings): void
    {
        $unflagged = UsAssignment::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereDate('target_completion_date', '<', today())
            ->whereNull('deadline_review_flagged_at')
            ->count();

        $this->line("Unflagged overdue University Service assignments: {$unflagged}");

        if ($unflagged > 0) {
            $warnings[] = "{$unflagged} University Service assignment(s) still need deadline review notifications. Confirm us:flag-overdue is scheduled and an officer is assigned.";
        }
    }

    private function checkMonthlyArchive(array &$warnings): void
    {
        $now = CarbonImmutable::now();

        if ($now->day === 1) {
            $this->line('Monthly archive check: waiting until after the first day of the month.');

            return;
        }

        $previousMonth = $now->subMonthNoOverflow()->startOfMonth();
        $periodStart = $previousMonth->toDateString();
        $periodEnd = $previousMonth->endOfMonth()->toDateString();

        $exists = ScheduledReport::query()
            ->where('report_type', 'monthly_violation_summary')
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd)
            ->exists();

        $status = $exists ? 'archived' : 'missing';
        $this->line("Previous monthly violation archive ({$previousMonth->format('F Y')}): {$status}");

        if (! $exists) {
            $warnings[] = "Monthly violation archive for {$previousMonth->format('F Y')} is missing. Confirm reports:archive-monthly runs every first day of the month.";
        }
    }
}
