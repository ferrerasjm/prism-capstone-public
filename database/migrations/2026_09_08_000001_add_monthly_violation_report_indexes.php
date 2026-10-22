<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the Monthly Violation Summary report, which was doing repeated full
 * scans on the hosted database:
 *
 *  - incident_reports.date_of_incident had no index, yet every academic-period
 *    report filters cases by a date range on it.
 *  - case_offenses was only indexed on its foreign keys, so the "top offenses"
 *    join filtered on status row by row.
 *
 * Also normalises student_profiles.program (trim, blank -> NULL) so the report's
 * program filter can be a plain, index-friendly `program <> ''` instead of
 * TRIM(program) <> '' which no index can satisfy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_reports', function (Blueprint $table): void {
            $table->index('date_of_incident', 'incident_reports_date_of_incident_index');
        });

        Schema::table('case_offenses', function (Blueprint $table): void {
            $table->index(['case_id', 'status'], 'case_offenses_case_status_index');
        });

        // NULLIF(TRIM(...), '') collapses whitespace-only programs to NULL and
        // strips padding from the rest. Both TRIM and NULLIF exist on MySQL and
        // SQLite, so this runs the same in production and in tests.
        DB::table('student_profiles')
            ->whereNotNull('program')
            ->whereRaw("(program <> TRIM(program) OR TRIM(program) = '')")
            ->update(['program' => DB::raw("NULLIF(TRIM(program), '')")]);
    }

    public function down(): void
    {
        Schema::table('incident_reports', function (Blueprint $table): void {
            $table->dropIndex('incident_reports_date_of_incident_index');
        });

        Schema::table('case_offenses', function (Blueprint $table): void {
            $table->dropIndex('case_offenses_case_status_index');
        });

        // The program normalisation is data cleanup - nothing to reverse.
    }
};
