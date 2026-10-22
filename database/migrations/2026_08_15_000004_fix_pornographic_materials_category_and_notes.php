<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Major Offenses Guidelines document leaves every column blank for
     * "Possessing, displaying, or distributing pornographic materials"
     * (MAJOR-41) except the Remarks column, which states the Formative
     * Intervention (counseling referral + related seminar) applies
     * regardless of 1st or 2nd offense. There is no basis in the document
     * for escalating to Category 3 on the 2nd offense, so this corrects the
     * previously-seeded Category 2 -> Category 3 escalation back to a flat
     * Category 2 with clarifying notes.
     */
    public function up(): void
    {
        $offenseIds = $this->offenseCatalogIds();

        if ($offenseIds === []) {
            return;
        }

        $note = 'Formative Intervention: referral for counseling and related seminar, regardless of 1st or 2nd offense.';

        DB::table('sanction_matrix')
            ->whereIn('offense_catalog_id', $offenseIds)
            ->update(['sanction_category' => 2, 'notes' => $note]);
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
            ->update(['sanction_category' => 3, 'notes' => null]);

        DB::table('sanction_matrix')
            ->whereIn('offense_catalog_id', $offenseIds)
            ->where('offense_count', 1)
            ->update(['notes' => null]);
    }

    /**
     * @return array<int, int>
     */
    private function offenseCatalogIds(): array
    {
        return DB::table('offense_catalog')
            ->where('code', 'MAJOR-41')
            ->pluck('id')
            ->all();
    }
};
