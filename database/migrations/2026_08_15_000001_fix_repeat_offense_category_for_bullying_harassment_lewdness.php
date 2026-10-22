<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Major Offenses Guidelines document lists a 2nd-offense category
     * escalation (Category 2 -> Category 3, with Formative Intervention) for
     * bullying (MAJOR-36), gender-based sexual harassment (MAJOR-37), and
     * lewdness/immorality (MAJOR-40). The original seed data kept these at
     * Category 2 for the 2nd offense and only escalated to Category 3 on the
     * 3rd, which understated the sanction shown on the "Official base
     * guideline" panel. This backfills already-seeded databases to match
     * SanctionMatrixSeeder.
     */
    public function up(): void
    {
        $note = 'Formative Intervention: referral for counseling and related seminar.';
        $offenseIds = $this->offenseCatalogIds();

        if ($offenseIds === []) {
            return;
        }

        DB::table('sanction_matrix')
            ->whereIn('offense_catalog_id', $offenseIds)
            ->where('offense_count', 2)
            ->update(['sanction_category' => 3, 'notes' => $note]);

        DB::table('sanction_matrix')
            ->whereIn('offense_catalog_id', $offenseIds)
            ->whereIn('offense_count', [1, 3])
            ->update(['notes' => $note]);
    }

    public function down(): void
    {
        $offenseIds = $this->offenseCatalogIds();

        if ($offenseIds === []) {
            return;
        }

        DB::table('sanction_matrix')
            ->whereIn('offense_catalog_id', $offenseIds)
            ->where('offense_count', 2)
            ->update(['sanction_category' => 2, 'notes' => null]);

        DB::table('sanction_matrix')
            ->whereIn('offense_catalog_id', $offenseIds)
            ->whereIn('offense_count', [1, 3])
            ->update(['notes' => null]);
    }

    /**
     * @return array<int, int>
     */
    private function offenseCatalogIds(): array
    {
        return DB::table('offense_catalog')
            ->whereIn('code', ['MAJOR-36', 'MAJOR-37', 'MAJOR-40'])
            ->pluck('id')
            ->all();
    }
};
