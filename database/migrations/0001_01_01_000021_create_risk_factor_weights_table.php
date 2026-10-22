<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_factor_weights', function (Blueprint $table) {
            $table->id();
            $table->string('factor_code', 50)->unique();
            $table->string('factor_name', 255);
            $table->enum('factor_type', ['count', 'boolean']);
            $table->unsignedTinyInteger('count_cap')->nullable();
            $table->decimal('weight', 5, 4);
            $table->text('description');
            $table->boolean('is_active')->default(true);
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_factor_weights');
    }
};
