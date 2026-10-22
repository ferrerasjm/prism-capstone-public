<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offense_catalog', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->enum('offense_type', ['minor', 'major']);
            $table->string('title', 255);
            $table->text('description');
            $table->json('keywords');
            $table->string('special_consequence', 100)->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offense_catalog');
    }
};
