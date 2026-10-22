<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guidance_officer_academic_unit', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_unit_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'academic_unit_id'], 'guidance_officer_academic_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guidance_officer_academic_unit');
    }
};
