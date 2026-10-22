<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluation is a Category 2 formative intervention (Handbook Section VI.E), so it closes
 * with the officer's finding on whether the intervention is working -- not with a verdict
 * on the student's conduct. Only Category 1 probation carries an escalation clause.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanctions', function (Blueprint $table): void {
            $table->string('assessment_outcome', 40)
                ->nullable()
                ->after('monitoring_review_flagged_at');
        });
    }

    public function down(): void
    {
        Schema::table('sanctions', function (Blueprint $table): void {
            $table->dropColumn('assessment_outcome');
        });
    }
};
