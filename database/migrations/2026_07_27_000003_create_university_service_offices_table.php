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
        Schema::create('university_service_offices', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 255)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $names = collect(array_keys(config('university_service.offices', [])))
            ->merge(DB::table('users')->whereNotNull('office_name')->pluck('office_name'))
            ->merge(DB::table('us_assignments')->whereNotNull('office_name')->pluck('office_name'))
            ->map(fn (mixed $name): string => preg_replace('/\s+/', ' ', trim((string) $name)))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $now = now();
        $rows = $names->map(fn (string $name): array => [
            'name' => $name,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows !== []) {
            DB::table('university_service_offices')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('university_service_offices');
    }
};
