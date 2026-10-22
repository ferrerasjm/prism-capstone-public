<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('report_number', 30)->unique();
            $table->enum('source_type', [
                'violation_slip',
                'student_complaint',
                'faculty_report',
                'security_report',
                'direct_encoding',
            ]);
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reported_by_name', 255)->nullable();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_of_incident');
            $table->time('time_of_incident')->nullable();
            $table->string('location', 255)->nullable();
            $table->text('description');
            $table->foreignId('suggested_offense_id')->nullable()->constrained('offense_catalog')->nullOnDelete();
            $table->enum('status', [
                'pending_review',
                'under_review',
                'converted_to_case',
                'dismissed',
            ]);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('dismissal_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_reports');
    }
};
