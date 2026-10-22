<?php

declare(strict_types=1);

use Database\Seeders\SanctionMatrixSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sanction_matrix as matrix')
            ->join('offense_catalog as offense', 'offense.id', '=', 'matrix.offense_catalog_id')
            ->whereNotNull('matrix.us_hours_guidance')
            ->select('matrix.id', 'matrix.offense_count', 'matrix.us_hours_guidance', 'offense.code')
            ->orderBy('matrix.id')
            ->chunk(100, function ($rows): void {
                foreach ($rows as $row) {
                    // Repair only the known mojibake in seeded guidance; preserve custom edits.
                    if (! str_contains($row->us_hours_guidance, 'Ã')
                        && ! str_contains($row->us_hours_guidance, 'â€')) {
                        continue;
                    }

                    $official = SanctionMatrixSeeder::guidelineFor($row->code, (int) $row->offense_count);

                    if ($official && isset($official['guidance'])) {
                        DB::table('sanction_matrix')->where('id', $row->id)
                            ->update(['us_hours_guidance' => $official['guidance']]);
                    }
                }
            });
    }

    public function down(): void
    {
        // The corrupted text cannot be reconstructed safely.
    }
};
