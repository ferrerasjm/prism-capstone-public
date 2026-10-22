<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('composite_score', 5, 2);
            $table->enum('risk_band', ['low', 'moderate', 'high', 'critical']);
            $table->json('contributing_factors');
            $table->timestamp('computed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_scores');
    }
};
