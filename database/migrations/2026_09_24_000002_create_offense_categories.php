<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offense_categories', function (Blueprint $table): void {
            $table->unsignedTinyInteger('number')->primary();
            $table->string('intervention', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach ([1 => 'Probation', 2 => 'Formative Intervention', 3 => 'Non-Readmission', 4 => 'Exclusion', 5 => 'Expulsion'] as $number => $intervention) {
            DB::table('offense_categories')->insert([
                'number' => $number, 'intervention' => $intervention,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offense_categories');
    }
};
