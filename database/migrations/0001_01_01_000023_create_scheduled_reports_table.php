<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some existing PRISM databases already contain this baseline table
        // from the original schema import without a matching migration entry.
        if (Schema::hasTable('scheduled_reports')) {
            return;
        }

        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->enum('report_type', ['monthly_violation_summary', 'student_case_report', 'case_summary', 'us_compliance', 'upcc_case_report', 'registration_hold_report', 'escalation_report', 'risk_analytics_report']);
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path', 500);
            $table->timestamp('generated_at');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
    }
};
