<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upcc_referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('referred_at');
            $table->dateTime('scheduled_conference_at');
            $table->string('conference_location', 255);
            $table->boolean('student_attended')->nullable();
            $table->foreignId('student_no_show_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['scheduled', 'in_progress', 'concluded', 'reconsideration_filed']);
            $table->text('decision_summary')->nullable();
            $table->timestamp('decision_issued_at')->nullable();
            $table->date('appeal_window_start')->nullable();
            $table->date('appeal_window_end')->nullable();
            $table->boolean('appeal_filed')->default(false);
            $table->timestamp('decision_final_at')->nullable();
            $table->timestamp('submitted_to_presidents_council_at')->nullable();
            $table->enum('presidents_council_action', ['approved', 'modified'])->nullable();
            $table->text('presidents_council_notes')->nullable();
            $table->timestamp('presidents_council_acted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upcc_referrals');
    }
};
