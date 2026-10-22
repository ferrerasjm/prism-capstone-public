<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_interventions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('risk_score_snapshot_id')->nullable()
                ->constrained('risk_score_snapshots')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 50);
            $table->string('summary', 200);
            $table->text('action_plan');
            $table->decimal('risk_score', 5, 2);
            $table->string('risk_band', 20);
            $table->string('methodology_version', 20)->nullable();
            $table->string('status', 20)->default('planned');
            $table->date('follow_up_on');
            $table->text('outcome')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'status'], 'risk_interventions_student_status_index');
            $table->index(['assigned_to', 'status', 'follow_up_on'], 'risk_interventions_assignee_followup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_interventions');
    }
};
