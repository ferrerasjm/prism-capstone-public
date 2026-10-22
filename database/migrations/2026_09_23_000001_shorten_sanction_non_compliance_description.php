<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PREVIOUS_DESCRIPTION = 'Current sanctions formally marked non-compliant, or evaluation monitoring periods the officer closed with a finding of Needs Further Intervention. Capped at three sanctions.';

    private const NEW_DESCRIPTION = 'Non-compliant sanctions or evaluations needing further intervention. Capped at three sanctions.';

    public function up(): void
    {
        DB::table('risk_factor_weights')
            ->where('factor_code', 'non_compliant_sanctions')
            ->where('description', self::PREVIOUS_DESCRIPTION)
            ->update(['description' => self::NEW_DESCRIPTION, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('risk_factor_weights')
            ->where('factor_code', 'non_compliant_sanctions')
            ->where('description', self::NEW_DESCRIPTION)
            ->update(['description' => self::PREVIOUS_DESCRIPTION, 'updated_at' => now()]);
    }
};
