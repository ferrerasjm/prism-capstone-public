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
        Schema::table('cases', function (Blueprint $table): void {
            $table->enum('case_type', ['minor', 'major'])->nullable()->change();
        });

        DB::table('cases')
            ->whereNull('confirmed_offense_id')
            ->where('case_type_locked', false)
            ->update(['case_type' => null]);
    }

    public function down(): void
    {
        // A rollback must satisfy the original non-null constraint. This value
        // is only a rollback fallback; normal classification remains explicit.
        DB::table('cases')->whereNull('case_type')->update(['case_type' => 'minor']);

        Schema::table('cases', function (Blueprint $table): void {
            $table->enum('case_type', ['minor', 'major'])->nullable(false)->change();
        });
    }
};
