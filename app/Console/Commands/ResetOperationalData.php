<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetOperationalData extends Command
{
    protected $signature = 'system:reset-operational-data
        {--dry-run : List the tables and current row counts without deleting anything.}
        {--force : Skip the interactive confirmation prompt.}
        {--include-sessions : Also truncate the sessions table, signing out every logged-in user.}';

    protected $description = 'Truncate case/sanction/audit-trail data and reset their IDs, while preserving reference data (users, students, offense catalog, sanction matrix, academic catalog, etc.)';

    /**
     * Explicit list (not "everything except a keep list") so a newly added table is never wiped without a
     * conscious decision here. Truncating resets auto-increment IDs back to 1.
     */
    private const TABLES = [
        'incident_reports',
        'incident_report_subjects',
        'incident_attachments',
        'cases',
        'case_notices',
        'case_explanations',
        'case_explanation_attachments',
        'case_offenses',
        'case_resolutions',
        'upcc_referrals',
        'upcc_appeal_attachments',
        'sanctions',
        'discipline_education_attendances',
        'us_assignments',
        'us_attendance_logs',
        'counseling_referrals',
        'risk_scores',
        'risk_score_snapshots',
        'risk_interventions',
        'student_monitoring_flags',
        'scheduled_reports',
        'audit_logs',
        'notifications',
        'announcements',
        'offense_recommendation_audits',
        'csv_import_logs',
        'number_sequences',
    ];

    public function handle(): int
    {
        $includeSessions = (bool) $this->option('include-sessions');
        $tables = $includeSessions ? [...self::TABLES, 'sessions'] : self::TABLES;

        $counts = collect($tables)
            ->filter(fn (string $table) => Schema::hasTable($table))
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);

        $this->table(
            ['Table', 'Rows to delete'],
            $counts->map(fn (int $count, string $table) => [$table, $count])->values()
        );

        if ($this->option('dry-run')) {
            $this->info('Dry run only — no data was deleted.');

            return self::SUCCESS;
        }

        $warning = 'This permanently deletes all rows in the tables above and resets their IDs to 1. '
            .'Reference data (users, students, offense catalog, sanction matrix, academic catalog, '
            .'account setup tokens) is not touched.';

        if ($includeSessions) {
            $warning .= ' This also signs out every currently logged-in user, including you — you will need to log in again.';
        }

        if (! $this->option('force') && ! $this->confirm(
            $warning.' Have you taken a database backup and want to proceed?',
            false
        )) {
            $this->warn('Cancelled — no data was deleted.');

            return self::FAILURE;
        }

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($counts->keys() as $table) {
                DB::table($table)->truncate();
                $this->line("Truncated {$table}.");
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->info('Operational data reset complete.');

        return self::SUCCESS;
    }
}
