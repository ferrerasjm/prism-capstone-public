<?php

declare(strict_types=1);

use Database\Seeders\SanctionMatrixSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanction_matrix', function (Blueprint $table): void {
            $table->decimal('us_hours_min', 6, 2)->nullable()->after('us_hours_recommended');
            $table->decimal('us_hours_max', 6, 2)->nullable()->after('us_hours_min');
            $table->text('us_hours_guidance')->nullable()->after('us_hours_max');
        });

        // Backfill deployed databases. On a fresh install these tables are empty
        // until DatabaseSeeder runs, so the same source is safely used by both
        // migration and seeding workflows.
        $rows = DB::table('sanction_matrix')
            ->join('offense_catalog', 'offense_catalog.id', '=', 'sanction_matrix.offense_catalog_id')
            ->select([
                'sanction_matrix.id',
                'sanction_matrix.offense_count',
                'offense_catalog.code',
            ])
            ->orderBy('sanction_matrix.id')
            ->get();

        foreach ($rows as $row) {
            $guideline = SanctionMatrixSeeder::guidelineFor(
                $row->code,
                (int) $row->offense_count
            );
            $recommended = $guideline && $guideline['min'] === $guideline['max']
                ? $guideline['min']
                : null;

            DB::table('sanction_matrix')
                ->where('id', $row->id)
                ->update([
                    'us_hours_recommended' => $recommended,
                    'us_hours_min' => $guideline['min'] ?? null,
                    'us_hours_max' => $guideline['max'] ?? null,
                    'us_hours_guidance' => $guideline['guidance'] ?? null,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('sanction_matrix', function (Blueprint $table): void {
            $table->dropColumn([
                'us_hours_min',
                'us_hours_max',
                'us_hours_guidance',
            ]);
        });
    }
};
