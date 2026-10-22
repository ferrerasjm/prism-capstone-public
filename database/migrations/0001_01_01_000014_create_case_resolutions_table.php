<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_resolutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->unique()->nullable()->constrained('cases')->nullOnDelete();
            $table->foreignId('upcc_referral_id')->nullable()->constrained('upcc_referrals')->nullOnDelete();
            $table->enum('resolution_level', ['sdo_level', 'upcc', 'presidents_council']);
            $table->unsignedTinyInteger('intervention_category');
            $table->decimal('us_hours_assigned', 6, 2)->nullable();
            $table->boolean('counseling_referred')->default(false);
            $table->boolean('discipline_education_required')->default(false);
            $table->boolean('evaluation_monitoring_required')->default(false);
            $table->unsignedTinyInteger('probation_terms')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at');
            $table->date('appeal_deadline');
            $table->boolean('appeal_filed')->default(false);
            $table->timestamp('appeal_filed_at')->nullable();
            $table->text('appeal_outcome')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_resolutions');
    }
};
