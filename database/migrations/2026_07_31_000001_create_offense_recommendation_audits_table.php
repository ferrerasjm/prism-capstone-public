<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offense_recommendation_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->char('context_hash', 64);
            $table->char('description_hash', 64);
            $table->string('source', 40);
            $table->string('status', 30);
            $table->string('model_name')->nullable();
            $table->string('model_version')->nullable();
            $table->json('recommendations')->nullable();
            $table->boolean('no_match')->default(false);
            $table->string('guardrail_reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('selected_offense_id')->nullable()->constrained('offense_catalog')->nullOnDelete();
            $table->boolean('decision_was_recommended')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['case_id', 'context_hash']);
            $table->index(['source', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offense_recommendation_audits');
    }
};
