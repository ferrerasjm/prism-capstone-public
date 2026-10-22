<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_reports', function (Blueprint $table): void {
            $table->string('violation_type', 100)
                ->nullable()
                ->after('description')
                ->comment('F-009 checkbox selection - for violation slips only');
        });

        Schema::table('incident_reports', function (Blueprint $table): void {
            if (Schema::hasColumn('incident_reports', 'suggested_offense_id')) {
                $table->dropForeign(['suggested_offense_id']);
                $table->dropColumn('suggested_offense_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('incident_reports', function (Blueprint $table): void {
            $table->foreignId('suggested_offense_id')
                ->nullable()
                ->after('description')
                ->constrained('offense_catalog')
                ->nullOnDelete();
        });

        Schema::table('incident_reports', function (Blueprint $table): void {
            $table->dropColumn('violation_type');
        });
    }
};
