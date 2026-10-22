<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_explanations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->enum('submission_channel', ['portal', 'physical_encoded_by_do']);
            $table->enum('admission_type', ['full_admission', 'partial_admission', 'full_denial']);
            $table->text('explanation_text')->nullable();
            $table->smallInteger('word_count')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('evaluated_at')->nullable();
            $table->text('evaluation_notes')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_explanations');
    }
};
