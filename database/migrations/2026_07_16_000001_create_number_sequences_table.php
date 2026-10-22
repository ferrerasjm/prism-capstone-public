<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('sequence_key', 80)->unique();
            $table->string('prefix', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('current_value')->default(0);
            $table->timestamps();

            $table->index(['prefix', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
