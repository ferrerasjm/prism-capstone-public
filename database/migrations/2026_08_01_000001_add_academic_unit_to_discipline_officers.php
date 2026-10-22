<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('academic_unit_id')
                ->nullable()
                ->after('office_name')
                ->constrained('academic_units')
                ->nullOnDelete();
        });

        $fallbackUnitId = DB::table('academic_units')
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');

        if ($fallbackUnitId) {
            DB::table('users')
                ->where('role', 'discipline_officer')
                ->whereNull('academic_unit_id')
                ->update(['academic_unit_id' => $fallbackUnitId]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('academic_unit_id');
        });
    }
};
