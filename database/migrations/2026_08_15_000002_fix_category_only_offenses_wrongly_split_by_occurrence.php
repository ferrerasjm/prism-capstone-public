<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Major Offenses Guidelines document gives a single, unconditional
     * "Category 3" for brawl (MAJOR-10), physical injuries (MAJOR-11),
     * provocation/confrontation (MAJOR-13), and bringing the University into
     * disrepute (MAJOR-18) — no hours, no occurrence-based split, identical
     * in structure to perjury (MAJOR-06) and direct assault (MAJOR-12),
     * which were already seeded correctly as a single Category 3 row. These
     * four were instead seeded as escalating from Category 2 on the 1st
     * offense to Category 3 on the 2nd, understating the 1st-offense
     * sanction. Forging/falsifying/tampering (MAJOR-05) is similarly listed
     * as a single "Category 3" header (with hours that do vary by
     * occurrence) but was seeded starting at Category 2.
     */
    public function up(): void
    {
        $offenseIds = $this->offenseCatalogIds(['MAJOR-05', 'MAJOR-10', 'MAJOR-11', 'MAJOR-13', 'MAJOR-18']);

        if ($offenseIds === []) {
            return;
        }

        // MAJOR-05 keeps its two occurrence rows (it does have occurrence-based
        // hours); only the 1st-offense category was wrong.
        $major05 = $this->offenseCatalogIds(['MAJOR-05']);
        DB::table('sanction_matrix')
            ->whereIn('offense_catalog_id', $major05)
            ->where('offense_count', 1)
            ->update(['sanction_category' => 3]);

        // MAJOR-10, 11, 13, 18 have no occurrence-based hours in the source
        // document at all — collapse back to a single Category 3 row each,
        // matching the already-correct MAJOR-06/12 pattern.
        $singleRowCodes = $this->offenseCatalogIds(['MAJOR-10', 'MAJOR-11', 'MAJOR-13', 'MAJOR-18']);

        DB::table('sanction_matrix')
            ->whereIn('offense_catalog_id', $singleRowCodes)
            ->where('offense_count', 1)
            ->update(['sanction_category' => 3]);

        DB::table('sanction_matrix')
            ->whereIn('offense_catalog_id', $singleRowCodes)
            ->where('offense_count', 2)
            ->delete();
    }

    public function down(): void
    {
        $major05 = $this->offenseCatalogIds(['MAJOR-05']);
        DB::table('sanction_matrix')
            ->whereIn('offense_catalog_id', $major05)
            ->where('offense_count', 1)
            ->update(['sanction_category' => 2]);

        $singleRowCodes = $this->offenseCatalogIds(['MAJOR-10', 'MAJOR-11', 'MAJOR-13', 'MAJOR-18']);

        foreach ($singleRowCodes as $offenseId) {
            DB::table('sanction_matrix')
                ->where('offense_catalog_id', $offenseId)
                ->where('offense_count', 1)
                ->update(['sanction_category' => 2]);

            DB::table('sanction_matrix')->insert([
                'offense_catalog_id' => $offenseId,
                'offense_count' => 2,
                'sanction_category' => 3,
            ]);
        }
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<int, int>
     */
    private function offenseCatalogIds(array $codes): array
    {
        return DB::table('offense_catalog')
            ->whereIn('code', $codes)
            ->pluck('id')
            ->all();
    }
};
