<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Description-only sync with config/risk_scoring.php: the "Sanction Non-Compliance"
 * factor's weight, cap, and code are unchanged, but its raw-value query now also counts
 * Evaluation periods closed as "Needs Further Intervention" (see RiskScoringService).
 * The stored description otherwise goes stale relative to what the factor now measures.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('risk_factor_weights')
            ->where('factor_code', 'non_compliant_sanctions')
            ->update([
                'description' => 'Current sanctions formally marked non-compliant, or evaluation '
                    .'monitoring periods the officer closed with a finding of Needs Further '
                    .'Intervention. Capped at three sanctions.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('risk_factor_weights')
            ->where('factor_code', 'non_compliant_sanctions')
            ->update([
                'description' => 'Current sanctions formally marked non-compliant. Capped at three sanctions.',
                'updated_at' => now(),
            ]);
    }
};
