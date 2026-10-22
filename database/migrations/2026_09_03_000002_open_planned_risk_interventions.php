<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Support actions used to open as 'planned' and needed a separate click to become
     * 'in_progress' - a step nothing downstream ever read. Actions now open in progress,
     * so the rows recorded under the old flow are brought onto the same footing.
     */
    public function up(): void
    {
        DB::table('risk_interventions')
            ->where('status', 'planned')
            ->update([
                'status' => 'in_progress',
                'started_at' => DB::raw('COALESCE(started_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        // The original split cannot be recovered: once merged, there is no record of
        // which open actions had been started. Leaving them in progress is accurate.
    }
};
