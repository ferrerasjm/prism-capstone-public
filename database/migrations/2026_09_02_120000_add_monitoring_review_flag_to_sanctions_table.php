<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An evaluation monitoring period that ends with new cases on the student's record
     * is not something the system should rule on by itself - non-compliance raises a
     * registration hold. The scheduler flags it here instead and the officer decides.
     */
    public function up(): void
    {
        Schema::table('sanctions', function (Blueprint $table): void {
            $table->timestamp('monitoring_review_flagged_at')->nullable()->after('intervention_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('sanctions', function (Blueprint $table): void {
            $table->dropColumn('monitoring_review_flagged_at');
        });
    }
};
