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
        Schema::create('academic_units', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('academic_programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_unit_id')->constrained()->restrictOnDelete();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['academic_unit_id', 'is_active', 'name']);
        });

        $now = now();
        foreach (config('academics.programs', []) as $unitName => $programNames) {
            $unitId = DB::table('academic_units')->insertGetId([
                'name' => $unitName,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('academic_programs')->insert(
                collect($programNames)->map(fn (string $name) => [
                    'academic_unit_id' => $unitId,
                    'name' => $name,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_programs');
        Schema::dropIfExists('academic_units');
    }
};
