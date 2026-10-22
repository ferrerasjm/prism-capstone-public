<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_score_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('composite_score', 5, 2);
            $table->enum('risk_band', ['low', 'moderate', 'high', 'critical']);
            $table->string('term', 20);
            $table->date('snapshot_date');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_score_snapshots');
    }
};
