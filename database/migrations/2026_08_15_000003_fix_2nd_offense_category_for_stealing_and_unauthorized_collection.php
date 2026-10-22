<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Major Offenses Guidelines document's "2nd Offense" column for
     * unjust enrichment/stealing (MAJOR-02) and unauthorized collection of
     * money (MAJOR-03) contains only an hours figure ("160 hrs" / "120
     * hrs") — not a category name — unlike bullying/harassment/lewdness
     * (MAJOR-36/37/40) where that column literally reads "Category 3". The
     * category for both offenses stays at the single header value,
     * Category 2, for both the 1st and 2nd offense; only the hours change.
     * This was previously seeded as escalating to Category 3 on the 2nd
     * offense, which the source document does not state.
     */
    public function up(): void
    {
        $offenseIds = $this->offenseCatalogIds();

        if ($offenseIds === []) {
            return;
        }

        DB::table('sanction_matrix')
            ->whereIn('offense_catalog_id', $offenseIds)
            ->where('offense_count', 2)
            ->update(['sanction_category' => 2]);
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
            ->update(['sanction_category' => 3]);
    }

    /**
     * @return array<int, int>
     */
    private function offenseCatalogIds(): array
    {
        return DB::table('offense_catalog')
            ->whereIn('code', ['MAJOR-02', 'MAJOR-03'])
            ->pluck('id')
            ->all();
    }
};
