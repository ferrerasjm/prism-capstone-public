<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ScheduledReport;
use App\Services\ReportExportService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Generates and stores the previous month's violation summary archive.
 * Scheduled monthly on the 1st; `prism:automation-check` reports a warning
 * if the expected archive is missing.
 */
class ArchiveMonthlyViolationReport extends Command
{
    protected $signature = 'reports:archive-monthly
        {--month= : Month number to archive, from 1 to 12. Defaults to the previous month.}
        {--year= : Four-digit year for the selected month. Required when --month is used.}
        {--format=pdf : Archive format: pdf or xlsx.}
        {--force : Generate another archive even when the period is already archived.}';

    protected $description = 'Generate and store the previous month violation summary archive';

    /**
     * Generates and stores the monthly violation summary archive.
     *
     * Defaults to the previous month, which is what the scheduled run on
     * the 1st needs. --month/--year backfill a specific period and
     * --force re-archives one that already exists, which is how a missed
     * run is recovered.
     */
    public function handle(ReportExportService $exports): int
    {
        $format = strtolower((string) $this->option('format'));

        if (! in_array($format, ['xlsx', 'pdf'], true)) {
            $this->error('The --format option must be either xlsx or pdf.');

            return self::INVALID;
        }

        $monthOption = $this->option('month');
        $yearOption = $this->option('year');
        $monthProvided = $monthOption !== null && $monthOption !== '';
        $yearProvided = $yearOption !== null && $yearOption !== '';

        if (($monthProvided && ! $yearProvided) || ($yearProvided && ! $monthProvided)) {
            $this->error('Use --month and --year together, or omit both to archive the previous month.');

            return self::INVALID;
        }

        if (($monthProvided && ! is_numeric($monthOption)) || ($yearProvided && ! is_numeric($yearOption))) {
            $this->error('The --month and --year options must be numeric.');

            return self::INVALID;
        }

        $month = $monthProvided ? (int) $monthOption : null;
        $year = $yearProvided ? (int) $yearOption : null;

        if (($month !== null && ($month < 1 || $month > 12)) || ($year !== null && ($year < 2000 || $year > 2100))) {
            $this->error('The archive period must use a valid month and a year between 2000 and 2100.');

            return self::INVALID;
        }

        $target = $month !== null && $year !== null
            ? CarbonImmutable::create($year, $month, 1)
            : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

        $periodStart = $target->startOfMonth()->toDateString();
        $periodEnd = $target->endOfMonth()->toDateString();

        $exists = ScheduledReport::query()
            ->where('report_type', 'monthly_violation_summary')
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd)
            ->exists();

        if ($exists && ! $this->option('force')) {
            // Expected on most days: this command is scheduled daily so a missed run can catch
            // up, and every run after the first for a given month is a deliberate no-op.
            $this->line("Monthly violation archive for {$target->format('F Y')} already exists. Use --force to generate another copy.");

            return self::SUCCESS;
        }

        $report = $exports->generate(
            type: 'monthly_violation_summary',
            filters: [
                'year' => $target->year,
                'month' => $target->month,
            ],
            generatedBy: null,
            format: $format
        );

        $this->info("Archived monthly violation summary for {$target->format('F Y')}: {$report->file_path}");

        return self::SUCCESS;
    }
}
