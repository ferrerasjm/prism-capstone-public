<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('us_assignments')
            ->select(['case_resolution_id', 'status'])
            ->orderBy('id')
            ->each(function (object $assignment): void {
                DB::table('sanctions')
                    ->where('case_resolution_id', $assignment->case_resolution_id)
                    ->where('sanction_type', 'university_service')
                    ->update([
                        'status' => $assignment->status,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Status history cannot be reconstructed safely.
    }
};
