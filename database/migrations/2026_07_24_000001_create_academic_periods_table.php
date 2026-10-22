<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('academic_year', 9);
            $table->string('term_name', 50);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->unique(['academic_year', 'term_name']);
            $table->index(['starts_on', 'ends_on']);
        });

        Schema::table('incident_reports', function (Blueprint $table): void {
            $table->foreignId('academic_period_id')
                ->nullable()
                ->after('student_id')
                ->constrained('academic_periods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('incident_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('academic_period_id');
        });

        Schema::dropIfExists('academic_periods');
    }
};
