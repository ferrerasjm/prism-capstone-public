<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanction_matrix', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offense_catalog_id')->constrained('offense_catalog')->cascadeOnDelete();
            $table->unsignedTinyInteger('offense_count');
            $table->unsignedTinyInteger('sanction_category');
            $table->decimal('us_hours_recommended', 6, 2)->nullable();
            $table->string('notes', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanction_matrix');
    }
};
