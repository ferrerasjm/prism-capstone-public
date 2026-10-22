<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table): void {
            $table->id();
            $table->string('case_number', 30)->unique();
            $table->foreignId('incident_report_id')->nullable()->constrained('incident_reports')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('case_type', ['minor', 'major']);
            $table->boolean('case_type_locked')->default(false);
            $table->text('case_type_change_reason')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', [
                'open',
                'pending_nte',
                'awaiting_explanation',
                'under_review',
                'pending_upcc',
                'pending_resolution',
                'resolved',
                'dismissed',
                'voided',
            ]);
            $table->foreignId('suggested_offense_id')->nullable()->constrained('offense_catalog')->nullOnDelete();
            $table->foreignId('confirmed_offense_id')->nullable()->constrained('offense_catalog')->nullOnDelete();
            $table->text('classification_override_reason')->nullable();
            $table->unsignedTinyInteger('prior_offense_count');
            $table->unsignedTinyInteger('suggested_sanction_category')->nullable();
            $table->unsignedTinyInteger('assigned_sanction_category')->nullable();
            $table->text('sanction_override_reason')->nullable();
            $table->boolean('escalation_flagged')->default(false);
            $table->string('escalation_flag_reason', 255)->nullable();
            $table->foreignId('escalation_acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('escalation_acknowledged_at')->nullable();
            $table->enum('path_recommendation', ['direct_resolution', 'upcc_referral'])->nullable();
            $table->string('path_recommendation_reason', 255)->nullable();
            $table->boolean('forwarded_to_coordinator')->default(false);
            $table->timestamp('forwarded_at')->nullable();
            $table->text('forwarded_reason')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('void_requires_coordinator_approval')->default(false);
            $table->foreignId('void_coordinator_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('void_coordinator_approved_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
