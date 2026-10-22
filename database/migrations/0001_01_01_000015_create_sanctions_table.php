<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_resolution_id')->nullable()->constrained('case_resolutions')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('sanction_type', [
                'written_warning',
                'university_service',
                'counseling_referral',
                'discipline_education',
                'evaluation_monitoring',
                'probation',
                'non_readmission',
                'exclusion',
                'expulsion',
            ]);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'non_compliant', 'waived']);
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('completion_document_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctions');
    }
};
