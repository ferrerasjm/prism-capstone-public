<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upcc_referrals', function (Blueprint $table): void {
            $table->timestamp('proceedings_started_at')->nullable()->after('student_no_show_recorded_by');
            $table->timestamp('proceedings_ended_at')->nullable()->after('proceedings_started_at');
            $table->timestamp('final_report_submitted_at')->nullable()->after('decision_issued_at');
            $table->timestamp('records_filed_at')->nullable()->after('decision_final_at');
        });
    }

    public function down(): void
    {
        Schema::table('upcc_referrals', function (Blueprint $table): void {
            $table->dropColumn([
                'proceedings_started_at',
                'proceedings_ended_at',
                'final_report_submitted_at',
                'records_filed_at',
            ]);
        });
    }
};
